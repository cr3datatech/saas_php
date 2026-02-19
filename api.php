<?php
/**
 * API Endpoint – Business Idea Generator
 * Matches saas/api/index.py behaviour:
 *   - Verifies Clerk JWT from Authorization Bearer header
 *   - Reads subscription plan from "pla" JWT claim
 *   - Selects OpenAI model based on plan tier
 *   - Streams SSE response with header (model/plan/JWT claims) then LLM text
 */

set_time_limit(0);

// Disable output buffering for streaming
if (ob_get_level() > 0) {
    ob_end_clean();
}
ini_set('output_buffering', 'off');
ini_set('zlib.output_compression', false);

// SSE headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');
// Allow the browser to send the Authorization header from a different origin
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

// Handle pre-flight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Helpers ────────────────────────────────────────────────────────────────

function sseError(string $message): void
{
    echo "data: **Error:** " . $message . "\n\n";
    flush();
    exit(1);
}

function sseFlush(string $text): void
{
    $lines = explode("\n", $text);
    $last  = array_pop($lines);
    foreach ($lines as $line) {
        echo "data: " . $line . "\n\n";
        echo "data:  \n";
    }
    echo "data: " . $last . "\n\n";
    flush();
    if (ob_get_level() > 0) {
        ob_flush();
    }
}

// ── Bootstrap ──────────────────────────────────────────────────────────────

// Load Composer autoloader only once (global OpenAI class guard)
if (!class_exists('OpenAI', false)) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Load .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// ── Clerk JWT verification (uses clerkinc/backend-php SDK) ────────────────

// Extract Bearer token from Authorization header
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!str_starts_with($authHeader, 'Bearer ')) {
    http_response_code(401);
    sseError('Missing or invalid Authorization header.');
}
$jwt = substr($authHeader, 7);

$clerkSecretKey = $_ENV['CLERK_SECRET_KEY'] ?? '';
if (!$clerkSecretKey) {
    http_response_code(500);
    sseError('CLERK_SECRET_KEY is not configured.');
}

try {
    // VerifyToken fetches Clerk's JWKS using the secret key and verifies the JWT
    $vtOptions = new \Clerk\Backend\Helpers\Jwks\VerifyTokenOptions(
        secretKey: $clerkSecretKey,
    );
    $decoded = \Clerk\Backend\Helpers\Jwks\VerifyToken::verifyToken($jwt, $vtOptions);
} catch (\Clerk\Backend\Helpers\Jwks\TokenVerificationException $e) {
    http_response_code(401);
    sseError('JWT verification failed: ' . $e->getMessage());
} catch (Exception $e) {
    http_response_code(401);
    sseError('JWT error: ' . $e->getMessage());
}

// ── Plan → model mapping (mirrors saas/api/index.py) ──────────────────────

$userId = $decoded->sub ?? 'unknown';
$pla    = $decoded->pla ?? '';
// Strip "u:" or "o:" prefix Clerk adds to plan slugs
$subscriptionPlan = preg_replace('/^[uo]:/', '', $pla) ?: 'free';

if ($subscriptionPlan === 'premium_subscription') {
    $model = 'gpt-4.1';       // Top tier
} elseif ($subscriptionPlan === 'pro_plan') {
    $model = 'gpt-4o-mini';   // Mid tier
} else {
    $model = 'gpt-4o-mini';   // Free / unknown
}

// ── OpenAI client ──────────────────────────────────────────────────────────

$apiKey = $_ENV['OPENAI_API_KEY'] ?? '';
if (!$apiKey) {
    sseError('OPENAI_API_KEY is not set.');
}
$client = \OpenAI::client($apiKey);

// ── Prompt ─────────────────────────────────────────────────────────────────

$content  = "Reply with a new business idea for AI Agents, formatted with headings, sub-headings and bullet points. ";
$content .= "The business case should address processes commonly seen in football tournament management systems as well as in sports clubs in general. ";
$content .= "The business case should Use emojs where appropriate.";

$messages = [['role' => 'user', 'content' => $content]];

// ── Build JWT claims block (same format as saas/api/index.py) ─────────────

$claimsLines = '';
foreach ((array) $decoded as $key => $value) {
    $display      = is_array($value) ? json_encode($value) : (string) $value;
    $claimsLines .= "data: {$key}: {$display}\n";
}

// ── Stream SSE ─────────────────────────────────────────────────────────────

try {
    $stream = $client->chat()->createStreamed([
        'model'    => $model,
        'messages' => $messages,
    ]);

    // Header block: model, plan, separator, JWT claims — mirrors saas/api/index.py
    echo "data: *Model: {$model}, Subscription Plan: {$subscriptionPlan}*\n"
       . "data: \n"
       . "data: ---\n"
       . "data: \n"
       . "data: **JWT Claims:**\n"
       . $claimsLines
       . "data: \n"
       . "data: ---\n"
       . "data: \n\n";
    flush();

    foreach ($stream as $response) {
        $choice = $response->choices[0] ?? null;
        if ($choice) {
            $text = $choice->delta->content ?? '';
            if ($text !== '') {
                sseFlush($text);
            }
            if (isset($choice->finishReason) && $choice->finishReason !== null) {
                break;
            }
        }
    }
} catch (Exception $e) {
    sseError($e->getMessage());
}
