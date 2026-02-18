<?php
/**
 * API Endpoint for Business Idea Generator
 * Replaces FastAPI backend with PHP
 * Handles Server-Sent Events (SSE) streaming from OpenAI
 */

// Disable output buffering for streaming
if (ob_get_level() > 0) {
    ob_end_clean();
}
ini_set('output_buffering', 'off');
ini_set('zlib.output_compression', false);

// Set headers for SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Disable buffering for nginx

// Load Composer autoloader, but only if the global OpenAI class doesn't exist yet.
// The class in vendor/openai-php/client/src/OpenAI.php is declared in the GLOBAL
// namespace as `class OpenAI` (not `OpenAI\OpenAI`), so that's the right check.
if (!class_exists('OpenAI', false)) {
    require_once __DIR__ . '/vendor/autoload.php';
}

try {
    // Get API key from environment
    // Get API key from .env file
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
    $apiKey = $_ENV['OPENAI_API_KEY'];
    
    if (!$apiKey) {
        throw new Exception('OPENAI_API_KEY environment variable is not set');
    }
    
    // The OpenAI class is in the global namespace (not OpenAI\OpenAI)
    $client = \OpenAI::client($apiKey);
    
    // Prepare the prompt
    $content = "Reply with a new business idea for AI Agents, formatted with headings, sub-headings and bullet points. ";
    $content .= "The business case should address processes commonly seen in football tournament management systems as well as in sports clubs in general. ";
    $content .= "The business case should Use emojs where appropriate.";
    
    $messages = [
        ['role' => 'user', 'content' => $content]
    ];
    
    // Create streaming request
    $stream = $client->chat()->createStreamed([
        'model' => 'gpt-5-nano',
        'messages' => $messages,
    ]);
    
    // Stream the response
    foreach ($stream as $response) {
        $choice = $response->choices[0] ?? null;
        if ($choice) {
            $text = $choice->delta->content ?? '';
            
            if ($text) {
                // Split by newlines and send each line as SSE data
                $lines = explode("\n", $text);
                foreach ($lines as $line) {
                    echo "data: " . $line . "\n";
                }
                echo "\n";
                flush();
                
                // Check if output buffering is active and flush it
                if (ob_get_level() > 0) {
                    ob_flush();
                }
            }
            
            // Check if stream is finished
            if (isset($choice->finishReason)) {
                break;
            }
        }
    }
    
} catch (Exception $e) {
    // Send error as SSE message
    echo "data: Error: " . htmlspecialchars($e->getMessage()) . "\n\n";
    flush();
}
