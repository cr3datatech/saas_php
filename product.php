<?php
// Load .env so we can pass the publishable key to the frontend
if (!defined('VENDOR_AUTOLOAD_LOADED') && file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    define('VENDOR_AUTOLOAD_LOADED', true);
}
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$clerkPublishableKey = $_ENV['NEXT_PUBLIC_CLERK_PUBLISHABLE_KEY'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IdeaGen Pro – Generate</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <style>
        .markdown-content h1 { font-size: 2em; font-weight: bold; margin: 0.67em 0; }
        .markdown-content h2 { font-size: 1.5em; font-weight: bold; margin: 0.83em 0; }
        .markdown-content h3 { font-size: 1.17em; font-weight: bold; margin: 1em 0; }
        .markdown-content p  { margin: 1em 0; }
        .markdown-content ul { list-style-type: disc; padding-left: 2em; margin: 1em 0; }
        .markdown-content ol { list-style-type: decimal; padding-left: 2em; margin: 1em 0; }
        .markdown-content li { margin: 0.25em 0; }
        .markdown-content strong { font-weight: bold; }
        .markdown-content em { font-style: italic; }
        .markdown-content hr { border: 0; border-top: 1px solid #e5e7eb; margin: 2em 0; }
        .markdown-content code { font-family: monospace; background: #f3f4f6; padding: 0.2em 0.4em; border-radius: 3px; }
        .markdown-content pre code { display: block; padding: 1em; overflow-x: auto; }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 dark:from-gray-900 dark:to-gray-800">

    <!-- Auth guard overlay (hidden once auth resolves) -->
    <div id="auth-loading" class="fixed inset-0 bg-white dark:bg-gray-900 flex items-center justify-center z-50">
        <div class="animate-pulse text-gray-400 text-lg">Loading…</div>
    </div>

    <!-- Pricing fallback (shown when signed in but no active plan) -->
    <div id="pricing-section" style="display:none;">
        <main class="container mx-auto px-4 py-12">
            <header class="text-center mb-12">
                <!-- User button top-right -->
                <div class="absolute top-4 right-4" id="user-button-pricing"></div>
                <h1 class="text-5xl font-bold bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent mb-4">
                    Choose Your Plan
                </h1>
                <p class="text-gray-600 dark:text-gray-400 text-lg mb-8">
                    Unlock unlimited AI-powered business ideas
                </p>
            </header>
            <div class="max-w-4xl mx-auto" id="clerk-pricing-table">
                <!-- Clerk PricingTable mounted here by JS -->
            </div>
        </main>
    </div>

    <!-- Protected app content (shown when signed in and subscribed) -->
    <div id="app-section" style="display:none;">
        <main class="container mx-auto px-4 py-12 relative">
            <!-- User button top-right -->
            <div class="absolute top-4 right-4" id="user-button-app"></div>

            <header class="text-center mb-12">
                <h1 class="text-5xl font-bold bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent mb-4">
                    Business Idea Generator
                </h1>
                <p class="text-gray-600 dark:text-gray-400 text-lg">
                    AI-powered innovation at your fingertips
                </p>
            </header>

            <div class="max-w-3xl mx-auto">
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-8 backdrop-blur-lg bg-opacity-95">
                    <div id="loading" class="flex items-center justify-center py-12">
                        <div class="animate-pulse text-gray-400">Generating your business idea…</div>
                    </div>
                    <div id="content" class="markdown-content text-gray-700 dark:text-gray-300" style="display:none;"></div>
                </div>
            </div>
        </main>
    </div>

    <!-- Clerk.js -->
    <script>
        window.__clerk_publishable_key = <?= json_encode($clerkPublishableKey) ?>;
    </script>
    <script src="https://cdn.jsdelivr.net/npm/@clerk/clerk-js@5/dist/clerk.browser.js" type="text/javascript"></script>
    <script type="module">
        import { fetchEventSource } from 'https://esm.sh/@microsoft/fetch-event-source@2.0.1';

        const authLoading    = document.getElementById('auth-loading');
        const pricingSection = document.getElementById('pricing-section');
        const appSection     = document.getElementById('app-section');

        function showError(msg) {
            authLoading.innerHTML = `<div class="text-red-500 text-center p-8">${msg}</div>`;
        }

        // Wrap in async IIFE — top-level return is a SyntaxError in ES modules
        (async () => {
            try {
                // v5: window.Clerk is a singleton instance, not a constructor
                const clerk = window.Clerk;
                await clerk.load({ publishableKey: window.__clerk_publishable_key });

                // ── Not signed in → redirect to landing page ──────────────────
                if (!clerk.user) {
                    window.location.href = 'index.php';
                    return;
                }

                // ── Decode JWT to read subscription plan (client-side UI gate) ─
                const rawToken = await clerk.session.getToken();
                let subscriptionPlan = 'free';
                if (rawToken) {
                    try {
                        const payload = JSON.parse(
                            atob(rawToken.split('.')[1].replace(/-/g, '+').replace(/_/g, '/'))
                        );
                        const pla = payload.pla ?? '';
                        subscriptionPlan = pla.replace(/^[uo]:/, '');
                    } catch (_) { /* stay 'free' */ }
                }

                const hasPlan = subscriptionPlan === 'pro_plan' ||
                                subscriptionPlan === 'premium_subscription';

                function mountUserButton(containerId) {
                    clerk.mountUserButton(document.getElementById(containerId), { showName: true });
                }

                if (!hasPlan) {
                    // ── No active subscription → show pricing table ───────────
                    authLoading.style.display = 'none';
                    pricingSection.style.display = 'block';
                    mountUserButton('user-button-pricing');

                    // Try Clerk's mountPricingTable; fall back to a subscribe button
                    const pricingEl = document.getElementById('clerk-pricing-table');
                    if (typeof clerk.mountPricingTable === 'function') {
                        clerk.mountPricingTable(pricingEl);
                    } else {
                        pricingEl.innerHTML = `
                            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-8 text-center max-w-sm mx-auto">
                                <h3 class="text-2xl font-bold mb-2">Premium Subscription</h3>
                                <p class="text-4xl font-bold text-blue-600 mb-4">$10<span class="text-lg text-gray-500">/month</span></p>
                                <ul class="text-left text-gray-600 dark:text-gray-400 mb-6 space-y-2">
                                    <li>✓ Unlimited idea generation</li>
                                    <li>✓ Advanced AI models</li>
                                    <li>✓ Priority support</li>
                                </ul>
                                <button id="subscribe-btn"
                                    class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-bold py-3 px-6 rounded-xl transition-all">
                                    Subscribe Now
                                </button>
                            </div>`;
                        document.getElementById('subscribe-btn').addEventListener('click', () => {
                            // Opens Clerk's user profile billing section
                            clerk.openUserProfile({ customPages: [] });
                        });
                    }
                    return;
                }

                // ── Has subscription → show idea generator ────────────────────
                authLoading.style.display = 'none';
                appSection.style.display = 'block';
                mountUserButton('user-button-app');

                // ── SSE streaming ─────────────────────────────────────────────
                const token = await clerk.session.getToken();

                marked.setOptions({ breaks: true, gfm: true });
                let buffer = '';
                const loadingEl = document.getElementById('loading');
                const contentEl = document.getElementById('content');

                const controller = new AbortController();

                fetchEventSource('api.php', {
                    method: 'GET',
                    signal: controller.signal,
                    headers: { 'Authorization': `Bearer ${token}` },

                    onopen(response) {
                        if (response.ok) {
                            loadingEl.style.display = 'none';
                            contentEl.style.display = 'block';
                        } else {
                            throw new Error(`Server returned ${response.status}`);
                        }
                    },

                    onmessage(event) {
                        buffer += event.data;
                        contentEl.innerHTML = marked.parse(buffer);
                    },

                    onerror(err) {
                        console.error('SSE error:', err);
                        loadingEl.innerHTML = '<div class="text-red-500">Error connecting to API. Please refresh the page.</div>';
                        controller.abort();
                        throw err;
                    },

                    onclose() { },
                });

                window.addEventListener('beforeunload', () => controller.abort());

            } catch (err) {
                console.error('Auth error:', err);
                showError(`Auth error: ${err.message}`);
            }
        })();
    </script>
</body>
</html>
