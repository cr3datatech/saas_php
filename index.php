<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Idea Generator</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <style>
        .markdown-content h1 {
            font-size: 2em;
            font-weight: bold;
            margin: 0.67em 0;
        }
        .markdown-content h2 {
            font-size: 1.5em;
            font-weight: bold;
            margin: 0.83em 0;
        }
        .markdown-content h3 {
            font-size: 1.17em;
            font-weight: bold;
            margin: 1em 0;
        }
        .markdown-content h4 {
            font-size: 1em;
            font-weight: bold;
            margin: 1.33em 0;
        }
        .markdown-content h5 {
            font-size: 0.83em;
            font-weight: bold;
            margin: 1.67em 0;
        }
        .markdown-content h6 {
            font-size: 0.67em;
            font-weight: bold;
            margin: 2.33em 0;
        }
        .markdown-content p {
            margin: 1em 0;
        }
        .markdown-content ul {
            list-style-type: disc;
            padding-left: 2em;
            margin: 1em 0;
        }
        .markdown-content ol {
            list-style-type: decimal;
            padding-left: 2em;
            margin: 1em 0;
        }
        .markdown-content li {
            margin: 0.25em 0;
        }
        .markdown-content strong {
            font-weight: bold;
        }
        .markdown-content em {
            font-style: italic;
        }
        .markdown-content hr {
            border: 0;
            border-top: 1px solid #e5e7eb;
            margin: 2em 0;
        }
        .markdown-content br {
            display: block;
            content: "";
            margin-top: 0.5em;
        }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 dark:from-gray-900 dark:to-gray-800">
    <main class="container mx-auto px-4 py-12">
        <!-- Header -->
        <header class="text-center mb-12">
            <h1 class="text-5xl font-bold bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent mb-4">
                Business Idea Generator
            </h1>
            <p class="text-gray-600 dark:text-gray-400 text-lg">
                AI-powered innovation at your fingertips
            </p>
        </header>

        <!-- Content Card -->
        <div class="max-w-3xl mx-auto">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-8 backdrop-blur-lg bg-opacity-95">
                <div id="loading" class="flex items-center justify-center py-12">
                    <div class="animate-pulse text-gray-400">
                        Generating your business idea...
                    </div>
                </div>
                <div id="content" class="markdown-content text-gray-700 dark:text-gray-300" style="display: none;"></div>
            </div>
        </div>
    </main>

    <script>
        // Initialize state
        let buffer = '';
        const loadingEl = document.getElementById('loading');
        const contentEl = document.getElementById('content');

        // Create EventSource connection to PHP API
        const evt = new EventSource('api.php');

        // Handle incoming SSE messages
        evt.onmessage = function(e) {
            // Hide loading indicator on first message
            if (loadingEl.style.display !== 'none') {
                loadingEl.style.display = 'none';
                contentEl.style.display = 'block';
            }

            // Accumulate chunks in buffer
            buffer += e.data;
            
            // Render markdown using marked.js
            if (typeof marked !== 'undefined') {
                // Configure marked to handle breaks
                marked.setOptions({
                    breaks: true,
                    gfm: true
                });
                contentEl.innerHTML = marked.parse(buffer);
            } else {
                // Fallback: just display as plain text if marked.js fails to load
                contentEl.textContent = buffer;
            }
        };

        // Handle errors
        evt.onerror = function() {
            console.error('SSE error, closing');
            evt.close();
            loadingEl.innerHTML = '<div class="text-red-500">Error: Connection failed. Please refresh the page.</div>';
        };

        // Cleanup on page unload
        window.addEventListener('beforeunload', function() {
            evt.close();
        });
    </script>
</body>
</html>
