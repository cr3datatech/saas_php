# How to Run the PHP Application Locally

## Quick Start

### Step 1: Install Dependencies

Navigate to the `saas_php` directory and install PHP dependencies:

```bash
cd saas_php
composer install
```

This installs:
- `openai-php/client` — OpenAI streaming API client
- `vlucas/phpdotenv` — loads `.env` files
- `firebase/php-jwt` — Clerk JWT signature verification (uses JWKS)
- `clerkinc/backend-php` — Clerk backend SDK
- HTTP client dependencies

### Step 2: Verify the `.env` file

`saas_php/.env` must contain all four keys:

```
OPENAI_API_KEY=...
NEXT_PUBLIC_CLERK_PUBLISHABLE_KEY=pk_test_...
CLERK_SECRET_KEY=sk_test_...
CLERK_JWKS_URL=https://safe-shark-85.clerk.accounts.dev/.well-known/jwks.json
```

(Already configured — just make sure it's there.)

### Step 3: Allow `localhost:8000` in the Clerk Dashboard

Because Clerk validates redirect URLs you **must** add `http://localhost:8000` once:

1. Go to [https://dashboard.clerk.com](https://dashboard.clerk.com)
2. Select your application (safe-shark-85)
3. **Configure → Domains** → add `http://localhost:8000`
4. **Configure → Paths** → ensure Sign-in / Sign-up paths are set (defaults are fine)

### Step 4: Start the PHP Development Server

Run the built-in PHP server with opcache disabled (prevents class-redeclaration errors):

```bash
php -d opcache.enable=0 -S localhost:8000
```

### Step 5: Open in Browser

```
http://localhost:8000
```

- **Not signed in** → landing page with Sign In button
- **Signed in, no plan** → pricing table (subscribe to unlock)
- **Signed in + subscribed** → idea generator streams AI content

---

## How It Mirrors `saas` (Next.js + FastAPI)

| `saas`                         | `saas_php`                          |
|-------------------------------|--------------------------------------|
| `pages/index.tsx`             | `index.php` (landing + Clerk.js)     |
| `pages/product.tsx`           | `product.php` (protected page)       |
| `api/index.py` (FastAPI)      | `api.php` (PHP SSE endpoint)         |
| `@clerk/nextjs` `<Protect>`   | Clerk.js `clerk.session` JWT decode  |
| `<PricingTable />`            | `clerk.mountPricingTable()`          |
| `<UserButton />`              | `clerk.mountUserButton()`            |
| `fastapi_clerk_auth`          | `firebase/php-jwt` + Clerk JWKS      |
| `gpt-5.1` / `gpt-5-nano`      | `gpt-4.1` / `gpt-4o-mini`           |

---

## Troubleshooting

### "Missing or invalid Authorization header" from `api.php`

The JWT was not forwarded. Ensure `product.php` is calling `api.php` with:

```js
headers: { 'Authorization': `Bearer ${token}` }
```

Check the browser Network tab — the request to `api.php` should have an `Authorization` header.

### "JWT verification failed"

- Confirm `CLERK_JWKS_URL` is correct in `.env`
- Confirm the Clerk session is still valid (not expired)
- Confirm the PHP server can reach the internet (`file_get_contents` to the JWKS URL)

### "Class 'Dotenv\Dotenv' not found"

Run `composer install` to install all dependencies.

### "Cannot redeclare class OpenAI"

Always start the server with:

```bash
php -d opcache.enable=0 -S localhost:8000
```

### Maximum execution time exceeded

The `set_time_limit(0)` in `api.php` disables the limit. If you still see it, check
that PHP's `max_execution_time` ini is not being overridden by a `php.ini` file.

### Clerk sign-in modal does not redirect back

Add `http://localhost:8000` to the **Allowed redirect URLs** list in the Clerk Dashboard.

### Port already in use

```bash
php -d opcache.enable=0 -S localhost:8080
```

Then open `http://localhost:8080`.

---

## File Structure

```
saas_php/
├── index.php        # Landing page — Clerk.js sign-in, pricing preview
├── product.php      # Protected page — JWT check, plan check, SSE idea generator
├── api.php          # API endpoint — JWT verify, model select, OpenAI stream
├── .env             # API keys (OPENAI + Clerk)
├── composer.json    # PHP dependencies
└── vendor/          # Installed packages (after composer install)
```
