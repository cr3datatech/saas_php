# Conversion Guide: Next.js + FastAPI → PHP Only

This document explains what was changed when converting from the Next.js + FastAPI stack to a PHP-only solution.

## Overview

**Yes, this conversion is absolutely possible!** PHP has all the capabilities needed:
- ✅ Server-Sent Events (SSE) support
- ✅ Streaming responses
- ✅ OpenAI API integration
- ✅ HTML rendering
- ✅ JavaScript support (vanilla JS)

## What Changed

### 1. Frontend: React → PHP + Vanilla JavaScript

**Before (Next.js):**
- `pages/index.tsx` - React component with hooks (`useState`, `useEffect`)
- `ReactMarkdown` component for markdown rendering
- Tailwind CSS via build process
- TypeScript

**After (PHP):**
- `index.php` - PHP-rendered HTML page
- Vanilla JavaScript for SSE handling (no React)
- `marked.js` (CDN) for markdown rendering
- Tailwind CSS via CDN
- Plain JavaScript (no TypeScript)

**Key Changes:**
- React state (`useState`) → JavaScript variables
- React effects (`useEffect`) → DOM event listeners
- ReactMarkdown → marked.js library
- Component re-renders → Direct DOM manipulation

### 2. Backend: FastAPI (Python) → PHP

**Before (FastAPI):**
- `api/index.py` - Python FastAPI application
- Python OpenAI SDK
- FastAPI `StreamingResponse`
- Uvicorn server

**After (PHP):**
- `api.php` - PHP script
- PHP OpenAI SDK (`openai-php/client`)
- PHP streaming with `flush()` and output buffering control
- PHP built-in server or Apache/Nginx

**Key Changes:**
- FastAPI decorators → PHP functions
- Python generators → PHP generators
- FastAPI `StreamingResponse` → PHP headers + `flush()`
- Python async → PHP synchronous (SSE handles async nature)

### 3. Dependencies

**Before:**
- `package.json` - Node.js dependencies
- `requirements.txt` - Python dependencies
- npm/yarn for frontend
- pip for backend

**After:**
- `composer.json` - PHP dependencies only
- Composer for PHP packages
- CDN for frontend libraries (marked.js, Tailwind)

### 4. Build Process

**Before:**
- Next.js build process (`next build`)
- Tailwind CSS compilation
- TypeScript compilation
- Two separate servers (Next.js dev server + FastAPI)

**After:**
- No build process needed
- Direct PHP execution
- Single server (PHP)
- CDN resources loaded at runtime

## Technical Details

### Server-Sent Events (SSE)

Both versions use SSE, but implementation differs:

**FastAPI:**
```python
return StreamingResponse(event_stream(), media_type="text/event-stream")
```

**PHP:**
```php
header('Content-Type: text/event-stream');
// ... streaming code ...
flush();
```

### Streaming OpenAI Responses

**FastAPI:**
```python
for chunk in stream:
    text = chunk.choices[0].delta.content
    yield f"data: {line}\n"
```

**PHP:**
```php
foreach ($stream as $response) {
    $text = $response->choices[0]->delta->content ?? '';
    echo "data: " . $line . "\n";
    flush();
}
```

### Markdown Rendering

**React:**
```tsx
<ReactMarkdown remarkPlugins={[remarkGfm, remarkBreaks]}>
    {idea}
</ReactMarkdown>
```

**JavaScript:**
```javascript
marked.setOptions({ breaks: true, gfm: true });
contentEl.innerHTML = marked.parse(buffer);
```

## What Stayed the Same

- ✅ Same user experience (streaming, real-time updates)
- ✅ Same UI design (Tailwind CSS classes)
- ✅ Same prompt and model configuration
- ✅ Same SSE protocol
- ✅ Same OpenAI API usage

## Advantages of PHP Version

1. **Simpler deployment** - Single language, single server
2. **No build step** - Direct execution
3. **Easier hosting** - Works on any PHP host
4. **Smaller footprint** - No Node.js runtime needed
5. **Faster startup** - No compilation needed

## Disadvantages of PHP Version

1. **Less type safety** - No TypeScript
2. **Manual DOM manipulation** - No React abstractions
3. **CDN dependencies** - External resources (can be self-hosted)
4. **Less modern tooling** - No hot reload, etc.

## File Mapping

| Original (Next.js + FastAPI) | New (PHP) |
|------------------------------|-----------|
| `pages/index.tsx` | `index.php` |
| `api/index.py` | `api.php` |
| `package.json` | `composer.json` |
| `requirements.txt` | (removed) |
| `styles/globals.css` | (inline styles in `index.php`) |

## Migration Checklist

- [x] Create PHP API endpoint with SSE support
- [x] Create HTML page with vanilla JavaScript
- [x] Replace ReactMarkdown with marked.js
- [x] Replace Tailwind build with CDN
- [x] Set up Composer dependencies
- [x] Configure output buffering for streaming
- [x] Test SSE connection
- [x] Test markdown rendering
- [x] Create documentation

## Next Steps

1. Install dependencies: `composer install`
2. Set `OPENAI_API_KEY` environment variable
3. Start PHP server: `php -S localhost:8000`
4. Test the application
5. Deploy to your PHP hosting environment

---

## Installed PHP Packages

Install command:
```bash
composer require openai-php/client symfony/http-client nyholm/psr7 vlucas/phpdotenv
```

Or simply:
```bash
composer install
```
(using `composer.json` which was already configured)

### Direct Dependencies

| Package | Version | Purpose |
|---------|---------|---------|
| `openai-php/client` | 0.19.0 | Official community PHP SDK for OpenAI API |
| `symfony/http-client` | 8.0.5 | PSR-18 HTTP client (required by openai-php) |
| `nyholm/psr7` | 1.8.2 | PSR-7 HTTP message implementation (required by openai-php) |
| `vlucas/phpdotenv` | 5.6.3 | Loads `OPENAI_API_KEY` from `.env` file |

### Transitive Dependencies (auto-installed)

| Package | Version | Purpose |
|---------|---------|---------|
| `graham-campbell/result-type` | 1.1.4 | Result type used by phpdotenv |
| `php-http/discovery` | 1.20.0 | Auto-discovers PSR-17/18 implementations |
| `php-http/multipart-stream-builder` | 1.4.2 | Multipart stream support for HTTP client |
| `phpoption/phpoption` | 1.9.5 | Option type used by phpdotenv |
| `psr/container` | 2.0.2 | PSR-11 container interface |
| `psr/http-client` | 1.0.3 | PSR-18 HTTP client interface |
| `psr/http-factory` | 1.1.0 | PSR-17 HTTP factory interface |
| `psr/http-message` | 2.0 | PSR-7 HTTP message interface |
| `psr/log` | 3.0.2 | PSR-3 logging interface |
| `symfony/deprecation-contracts` | 3.6.0 | Symfony deprecation helpers |
| `symfony/http-client-contracts` | 3.6.0 | Symfony HTTP client interfaces |
| `symfony/polyfill-ctype` | 1.33.0 | Backport of PHP ctype functions |
| `symfony/polyfill-mbstring` | 1.33.0 | Backport of PHP mbstring functions |
| `symfony/polyfill-php80` | 1.33.0 | Backport of PHP 8.0 features |
| `symfony/service-contracts` | 3.6.1 | Symfony service abstractions |

### Frontend Libraries (loaded via CDN, no install required)

| Library | Purpose |
|---------|---------|
| Tailwind CSS (CDN) | Utility-first CSS styling |
| marked.js (CDN) | Markdown → HTML rendering (replaces ReactMarkdown) |
