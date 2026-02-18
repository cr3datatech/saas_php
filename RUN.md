# How to Run the PHP Application

## Quick Start

### Step 1: Install Dependencies

Navigate to the `saas_php` directory and install PHP dependencies:

```bash
cd saas_php
composer install
```

This will install:
- `openai-php/client` - OpenAI API client
- `vlucas/phpdotenv` - For loading .env file
- Required HTTP client dependencies

### Step 2: Verify .env File

Make sure you have a `.env` file in the `saas_php` directory with your OpenAI API key:

```
OPENAI_API_KEY=your-api-key-here
```

(You already have this file set up!)

### Step 3: Start the PHP Development Server

Run the built-in PHP server **with opcache disabled** (required to prevent class redeclaration errors):

```bash
php -d opcache.enable=0 -S localhost:8000
```

This starts a development server on `http://localhost:8000`

**Important:** The `-d opcache.enable=0` flag is necessary because PHP's opcode cache can cause "Cannot redeclare class" errors with the built-in server.

### Step 4: Open in Browser

Open your browser and navigate to:

```
http://localhost:8000
```

The page will automatically connect to the API and start streaming the business idea!

## Alternative: Using Apache/Nginx

If you prefer using Apache or Nginx:

### Apache
1. Point DocumentRoot to the `saas_php` directory
2. Ensure mod_rewrite is enabled
3. Access via your configured domain/port

### Nginx
1. Configure PHP-FPM
2. Point root to the `saas_php` directory
3. Ensure PHP files are processed correctly

## Troubleshooting

### "Class 'Dotenv\Dotenv' not found"
- Run `composer install` to install dependencies
- Make sure `vendor/autoload.php` exists

### "OPENAI_API_KEY environment variable is not set"
- Check that `.env` file exists in `saas_php` directory
- Verify the file contains `OPENAI_API_KEY=your-key`
- Make sure there are no extra spaces or quotes

### SSE Not Working / No Streaming
- Check PHP version: `php -v` (needs 8.2+)
- Verify output buffering is disabled (already handled in api.php)
- Check browser console for errors
- Ensure `api.php` is accessible at `http://localhost:8000/api.php`

### Port Already in Use
If port 8000 is busy, use a different port:
```bash
php -d opcache.enable=0 -S localhost:8080
```
Then access at `http://localhost:8080`

### Opcache Issues
If you see "Cannot redeclare class" errors, make sure to start the server with opcache disabled:
```bash
php -d opcache.enable=0 -S localhost:8000
```
This is required because PHP's opcode cache can persist class definitions between requests in the built-in server.

## File Structure

```
saas_php/
├── index.php          # Main page (open this in browser)
├── api.php            # API endpoint (called automatically)
├── .env               # Your API key (already configured)
├── composer.json      # Dependencies
└── vendor/            # Installed packages (after composer install)
```

## What Happens When You Run It

1. **Open `index.php`** → HTML page loads
2. **JavaScript connects** → EventSource connects to `api.php`
3. **PHP API runs** → `api.php` calls OpenAI API
4. **Streaming starts** → Chunks stream via SSE
5. **Content appears** → Markdown renders in real-time

That's it! The application should work immediately after `composer install`.
