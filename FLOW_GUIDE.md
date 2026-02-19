# Flow Guide: User Page Load → LLM Response Display (PHP)

This document traces the complete path from when a user opens the page to where the LLM response is displayed on screen, in the PHP version of the application.

For the equivalent Next.js + FastAPI version see `../saas/FLOW_GUIDE.md`.

---

## Architecture Overview

| Layer | Technology | File |
|-------|-----------|------|
| Landing page | PHP + HTML + Clerk.js | `index.php` |
| Protected app page | PHP + HTML + Clerk.js | `product.php` |
| Authentication (frontend) | Clerk.js v5 (CDN) | `index.php`, `product.php` |
| Authentication (backend) | `clerkinc/backend-php` SDK | `api.php` |
| API endpoint | PHP (built-in SSE streaming) | `api.php` |
| LLM client | `openai-php/client` | `api.php` |
| Streaming transport | Server-Sent Events (SSE) | `api.php` → `product.php` |
| Markdown rendering | `marked.js` (CDN) | `product.php` |
| SSE client | `@microsoft/fetch-event-source` (esm.sh CDN) | `product.php` |

---

## Pages at a Glance

| URL | File | Access |
|-----|------|--------|
| `http://localhost:8000/` | `index.php` | Public — landing page |
| `http://localhost:8000/product.php` | `product.php` | Requires sign-in + active subscription |
| `http://localhost:8000/api.php` | `api.php` | Requires valid Clerk JWT Bearer token |

---

## Comparison with `saas` (Next.js + FastAPI)

| `saas` | `saas_php` | Notes |
|--------|-----------|-------|
| `pages/index.tsx` | `index.php` | Landing page |
| `pages/product.tsx` | `product.php` | Protected page |
| `api/index.py` (FastAPI) | `api.php` (PHP) | SSE API endpoint |
| `ClerkProvider` in `_app.tsx` | `clerk.load()` in each page | Initialisation |
| `<SignedIn>` / `<SignedOut>` | `clerk.user` check in JS | Auth branching |
| `<Protect condition={...}>` | JWT decode + `hasPlan` check | Plan gating |
| `<PricingTable />` | `clerk.mountPricingTable()` | Subscription UI |
| `<UserButton />` | `clerk.mountUserButton()` | User menu |
| `useAuth().getToken()` | `clerk.session.getToken()` | Get JWT for API |
| `fastapi-clerk-auth` | `clerkinc/backend-php` VerifyToken | JWT verification |
| `StreamingResponse` | `echo` + `flush()` loop | SSE output |
| `react-markdown` + `remark-*` | `marked.js` | Markdown rendering |
| `@microsoft/fetch-event-source` (npm) | same (esm.sh CDN) | SSE client |

---

## Step-by-Step Flow

### 1. User Opens the Landing Page (`index.php`)

**File**: `index.php`

PHP executes on the server **before** HTML is sent:

```php
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$clerkPublishableKey = $_ENV['NEXT_PUBLIC_CLERK_PUBLISHABLE_KEY'];
```

- Composer autoloader is loaded
- `.env` is read to get the Clerk publishable key
- The key is embedded into the HTML as a JS variable:

```html
<script>
    window.__clerk_publishable_key = "pk_test_...";
</script>
```

The browser then loads `@clerk/clerk-js@5` from jsDelivr CDN.

---

### 2. Clerk.js Initialises (Landing Page)

**File**: `index.php`

```js
const clerk = window.Clerk;   // v5: singleton, not a constructor
clerk.load({ publishableKey: window.__clerk_publishable_key }).then(() => {
    if (clerk.user) {
        // Signed in → show "Go to App" + UserButton
    } else {
        // Signed out → show Sign In button
    }
});
```

- `window.Clerk` is the pre-instantiated singleton (v5 changed from `new Clerk()`)
- `clerk.load()` completes the initialisation and resolves the session
- Auth state drives what the nav bar and CTA button show

---

### 3. User Signs In

- User clicks "Sign In" → `clerk.openSignIn()` opens Clerk's hosted modal
- On success, Clerk sets a session cookie and issues a JWT
- The JWT payload contains:
  - `sub` — user ID
  - `pla` — subscription plan slug prefixed with `u:` or `o:` (e.g. `u:premium_subscription`)
- The page re-renders: nav shows "Go to App" and the UserButton

---

### 4. User Navigates to `product.php`

**File**: `product.php`

PHP again runs first — reads `.env` and embeds the publishable key. The HTML contains three sections, all hidden by default:

| Element | Shown when |
|---------|-----------|
| `#auth-loading` | Always initially — full-screen overlay |
| `#pricing-section` | Signed in, no active subscription |
| `#app-section` | Signed in + has `pro_plan` or `premium_subscription` |

---

### 5. Clerk.js Initialises on Product Page

**File**: `product.php`

The script is wrapped in an **async IIFE** (top-level `return` is a SyntaxError in ES modules):

```js
(async () => {
    try {
        const clerk = window.Clerk;
        await clerk.load({ publishableKey: window.__clerk_publishable_key });

        if (!clerk.user) {
            window.location.href = 'index.php';  // Not signed in → redirect
            return;
        }
        // ...
    } catch (err) {
        showError(err.message);
    }
})();
```

---

### 6. Plan Check (Client-Side UI Gate)

**File**: `product.php`

```js
const rawToken = await clerk.session.getToken();
const payload  = JSON.parse(atob(rawToken.split('.')[1]
    .replace(/-/g, '+').replace(/_/g, '/')));

const pla = payload.pla ?? '';
const subscriptionPlan = pla.replace(/^[uo]:/, '');   // strip "u:" or "o:"

const hasPlan = subscriptionPlan === 'pro_plan' ||
                subscriptionPlan === 'premium_subscription';
```

- The JWT payload is decoded **client-side** (base64url decode — no signature check here)
- This is **only for the UI gate**; the backend re-verifies the full signature
- **No plan** → hide `#auth-loading`, show `#pricing-section` + mount `PricingTable`
- **Has plan** → hide `#auth-loading`, show `#app-section` + start SSE stream

---

### 7. Frontend Fetches JWT and Opens SSE Connection

**File**: `product.php`

```js
const token = await clerk.session.getToken();

fetchEventSource('api.php', {
    method: 'GET',
    signal: controller.signal,
    headers: { 'Authorization': `Bearer ${token}` },

    onopen(response) {
        loadingEl.style.display = 'none';
        contentEl.style.display = 'block';
    },
    onmessage(event) {
        buffer += event.data;
        contentEl.innerHTML = marked.parse(buffer);
    },
    onerror(err) {
        controller.abort();
        throw err;
    },
});
```

- `fetchEventSource` (from `esm.sh`) allows custom headers — native `EventSource` does not
- The Clerk JWT is sent in the `Authorization: Bearer` header on every SSE request
- `marked.js` re-parses the growing buffer on every chunk → live markdown rendering

---

### 8. PHP API Receives Request and Disables Buffering

**File**: `api.php`

```php
set_time_limit(0);                  // No timeout for long streams
ob_end_clean();                     // Kill any output buffer
ini_set('output_buffering', 'off');
ini_set('zlib.output_compression', false);

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
```

- PHP's default 30-second execution limit is removed
- All output buffering is disabled so chunks reach the browser immediately

---

### 9. Backend Verifies the Clerk JWT

**File**: `api.php`

```php
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$jwt        = substr($authHeader, 7);   // strip "Bearer "

$vtOptions = new \Clerk\Backend\Helpers\Jwks\VerifyTokenOptions(
    secretKey: $_ENV['CLERK_SECRET_KEY'],
);
$decoded = \Clerk\Backend\Helpers\Jwks\VerifyToken::verifyToken($jwt, $vtOptions);
```

- `clerkinc/backend-php` derives the JWKS URL from the secret key automatically
- Fetches Clerk's public JWKS and verifies the JWT signature + expiry
- `TokenVerificationException` → HTTP 401
- On success, `$decoded` is a `stdClass` with all JWT claims

---

### 10. Backend Reads Plan and Selects Model

**File**: `api.php`

```php
$pla = $decoded->pla ?? '';
$subscriptionPlan = preg_replace('/^[uo]:/', '', $pla) ?: 'free';

if ($subscriptionPlan === 'premium_subscription') {
    $model = 'gpt-4.1';
} elseif ($subscriptionPlan === 'pro_plan') {
    $model = 'gpt-4o-mini';
} else {
    $model = 'gpt-4o-mini';
}
```

- Mirrors the Python logic in `saas/api/index.py` exactly
- `premium_subscription` → top-tier model; everything else → mid-tier

---

### 11. Backend Calls OpenAI with Streaming

**File**: `api.php`

```php
$client = \OpenAI::client($_ENV['OPENAI_API_KEY']);
$stream = $client->chat()->createStreamed([
    'model'    => $model,
    'messages' => $messages,
]);
```

- `openai-php/client` sends the request and returns a generator of response chunks
- Each iteration yields one delta from OpenAI's streaming response

---

### 12. Backend Streams SSE Response

**File**: `api.php`

```php
// Header block first
echo "data: *Model: {$model}, Subscription Plan: {$subscriptionPlan}*\n"
   . "data: \n"
   . "data: ---\n"
   . "data: \n"
   . "data: **JWT Claims:**\n"
   . $claimsLines
   . "data: ---\n"
   . "data: \n\n";
flush();

// Stream LLM chunks
foreach ($stream as $response) {
    $text = $response->choices[0]->delta->content ?? '';
    if ($text !== '') {
        sseFlush($text);   // splits by \n, sends each line as a data: event
    }
}
```

- Header block is sent once: model name, plan, all JWT claims
- Each OpenAI chunk is formatted as `data: <line>\n\n` and flushed immediately
- `flush()` + `ob_flush()` ensure PHP doesn't buffer chunks

---

### 13. Frontend Accumulates Chunks and Renders Markdown

**File**: `product.php`

```js
onmessage(event) {
    buffer += event.data;
    contentEl.innerHTML = marked.parse(buffer);
}
```

- Each SSE `data:` line appends to `buffer`
- `marked.parse()` converts the full accumulated markdown to HTML on every chunk
- The live re-parse produces the streaming typewriter effect

---

## Data Flow Summary

```
User opens http://localhost:8000/
    ↓
PHP reads .env → embeds publishable key in HTML
    ↓
Browser loads Clerk.js v5 from CDN
    ↓
clerk.load() resolves session
    ↓
Not signed in → show Sign In button
Signed in     → show "Go to App"
    ↓
User clicks Sign In → Clerk modal → JWT issued with `pla` claim
    ↓
User navigates to product.php
    ↓
PHP embeds publishable key → Clerk.js loads and initialises
    ↓
clerk.session.getToken() → decode JWT payload client-side
    ↓
No plan → clerk.mountPricingTable() — subscribe flow
Has plan → proceed to SSE
    ↓
clerk.session.getToken() → Authorization: Bearer <jwt>
    ↓
fetchEventSource('api.php', { headers: { Authorization } })
    ↓
api.php: VerifyToken verifies JWT via Clerk JWKS
    ↓
Reads pla claim → selects model (gpt-4.1 or gpt-4o-mini)
    ↓
openai-php/client streams delta chunks
    ↓
echo "data: ..." + flush() per chunk
    ↓
onmessage: buffer += chunk → marked.parse(buffer) → innerHTML
    ↓
Content appears incrementally on screen
```

---

## Installed Packages

### PHP — `composer.json`

```bash
composer install
```

| Package | Version | Purpose |
|---------|---------|---------|
| `openai-php/client` | ^0.19 | OpenAI PHP SDK — streaming chat completions |
| `symfony/http-client` | ^8.0 | HTTP transport for openai-php |
| `nyholm/psr7` | ^1.8 | PSR-7 message implementation (required by openai-php) |
| `vlucas/phpdotenv` | ^5.6 | Loads `.env` file into `$_ENV` |
| `clerkinc/backend-php` | ^0.5.2 | Clerk backend SDK — JWT verification via JWKS |
| `firebase/php-jwt` | ^6.11 | *(transitive)* JWT encode/decode — used internally by `clerkinc/backend-php` |

### Frontend — CDN (no build step)

| Library | CDN | Version | Purpose |
|---------|-----|---------|---------|
| `@clerk/clerk-js` | jsDelivr | `@5` | Clerk browser SDK — auth, session, UI components |
| `marked` | jsDelivr | latest | Markdown → HTML parser |
| `@microsoft/fetch-event-source` | esm.sh | `@2.0.1` | SSE client with custom header support |
| Tailwind CSS | cdn.tailwindcss.com | latest | Utility CSS (CDN play mode) |

### Environment Variables — `.env`

| Variable | Used by | Purpose |
|----------|---------|---------|
| `OPENAI_API_KEY` | `api.php` | Authenticates OpenAI API calls |
| `NEXT_PUBLIC_CLERK_PUBLISHABLE_KEY` | `index.php`, `product.php` | Initialises Clerk.js in the browser |
| `CLERK_SECRET_KEY` | `api.php` | Clerk backend SDK — derives JWKS URL + verifies JWTs |

---

## Important Files Reference

| File | Role |
|------|------|
| `index.php` | Landing page — PHP reads env, Clerk.js handles sign-in UI |
| `product.php` | Protected page — Clerk.js plan gate, SSE client, markdown render |
| `api.php` | API endpoint — JWT verify, model select, OpenAI stream, SSE output |
| `.env` | Environment variables (not committed) |
| `composer.json` | PHP dependency manifest |
| `vendor/` | Installed PHP packages (after `composer install`) |
| `RUN.md` | How to run locally |
