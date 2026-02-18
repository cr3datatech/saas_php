# Business Idea Generator - PHP Version

This is a PHP-only version of the Business Idea Generator, converted from Next.js + FastAPI.

## Architecture

- **Frontend**: PHP-rendered HTML with vanilla JavaScript
- **Backend**: PHP API endpoint with Server-Sent Events (SSE)
- **LLM**: OpenAI API (via openai-php/client)
- **Styling**: Tailwind CSS (via CDN)
- **Markdown Rendering**: marked.js (via CDN)

## Requirements

- PHP 8.1 or higher
- Composer
- OpenAI API Key

## Setup Instructions

1. **Install PHP dependencies:**
   ```bash
   composer install
   ```

2. **Set up environment variables:**
   Create a `.env` file or set the `OPENAI_API_KEY` environment variable:
   ```bash
   export OPENAI_API_KEY="your-api-key-here"
   ```
   
   Or create `.env` file:
   ```
   OPENAI_API_KEY=your-api-key-here
   ```
   
   Note: You may need to modify `api.php` to load from `.env` file if you prefer that approach.

3. **Configure your web server:**
   
   **Option A: PHP Built-in Server (Development)**
   ```bash
   php -S localhost:8000
   ```
   Then visit: http://localhost:8000
   
   **Option B: Apache**
   - Ensure mod_rewrite is enabled
   - Point DocumentRoot to this directory
   - Ensure `.htaccess` allows PHP execution
   
   **Option C: Nginx**
   - Configure PHP-FPM
   - Point root to this directory
   - Ensure PHP files are processed

4. **Access the application:**
   - Open `index.php` in your browser
   - The page will automatically connect to `api.php` via SSE
   - Business ideas will stream in real-time

## File Structure

```
saas_php/
├── index.php          # Main HTML page (replaces Next.js frontend)
├── api.php            # API endpoint (replaces FastAPI backend)
├── composer.json      # PHP dependencies
├── composer.lock      # Lock file (generated)
├── vendor/            # Composer dependencies (generated)
└── README.md          # This file
```

## Key Differences from Next.js + FastAPI Version

### Frontend Changes:
- ✅ Replaced React components with vanilla JavaScript
- ✅ Replaced ReactMarkdown with marked.js (CDN)
- ✅ Replaced Tailwind CSS build process with CDN
- ✅ Kept SSE functionality (EventSource API)
- ✅ Maintained same UI/UX

### Backend Changes:
- ✅ Replaced FastAPI with PHP
- ✅ Replaced Python OpenAI SDK with PHP OpenAI SDK
- ✅ Maintained SSE streaming functionality
- ✅ Same prompt and model configuration

## Troubleshooting

### SSE Not Working
- Ensure your web server supports streaming (disable output buffering)
- Check PHP `max_execution_time` settings
- Verify `api.php` headers are being sent correctly

### OpenAI API Errors
- Verify `OPENAI_API_KEY` is set correctly
- Check API key permissions and billing
- Ensure PHP has network access

### Markdown Not Rendering
- Check browser console for marked.js loading errors
- Verify CDN is accessible
- Check content is being received via SSE

## Development Notes

- The PHP built-in server works well for development
- For production, use a proper web server (Apache/Nginx) with PHP-FPM
- Consider adding error handling and logging for production use
- You may want to add a `.env` loader library (like `vlucas/phpdotenv`) for easier environment variable management
