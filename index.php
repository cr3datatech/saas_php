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
    <title>IdeaGen Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 dark:from-gray-900 dark:to-gray-800">
    <main class="container mx-auto px-4 py-12">

        <!-- Navigation -->
        <nav class="flex justify-between items-center mb-12">
            <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-200">IdeaGen Pro</h1>
            <div id="nav-auth">
                <!-- Populated by Clerk.js below -->
            </div>
        </nav>

        <!-- Hero Section -->
        <div class="text-center py-24">
            <h2 class="text-6xl font-bold bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent mb-6">
                Generate Your Next<br>Big Business Idea
            </h2>
            <p class="text-xl text-gray-600 dark:text-gray-400 mb-8 max-w-2xl mx-auto">
                Harness the power of AI to discover innovative business opportunities tailored for the AI agent economy
            </p>

            <!-- Pricing Preview -->
            <div class="bg-white/80 dark:bg-gray-800/80 backdrop-blur-lg rounded-xl p-6 max-w-sm mx-auto mb-8">
                <h3 class="text-2xl font-bold mb-2">Premium Subscription</h3>
                <p class="text-4xl font-bold text-blue-600 mb-2">$10<span class="text-lg text-gray-600">/month</span></p>
                <ul class="text-left text-gray-600 dark:text-gray-400 mb-6">
                    <li class="mb-2">✓ Unlimited idea generation</li>
                    <li class="mb-2">✓ Advanced AI models</li>
                    <li class="mb-2">✓ Priority support</li>
                </ul>
            </div>

            <div id="hero-cta">
                <!-- Populated by Clerk.js below -->
            </div>
        </div>
    </main>

    <!-- Clerk.js -->
    <script>
        window.__clerk_publishable_key = <?= json_encode($clerkPublishableKey) ?>;
    </script>
    <script src="https://cdn.jsdelivr.net/npm/@clerk/clerk-js@5/dist/clerk.browser.js" type="text/javascript"></script>
    <script>
        const navAuth = document.getElementById('nav-auth');
        const heroCta = document.getElementById('hero-cta');

        if (!window.__clerk_publishable_key) {
            navAuth.innerHTML = '<span class="text-red-500 text-sm">Config error: missing publishable key</span>';
        } else if (typeof window.Clerk === 'undefined') {
            navAuth.innerHTML = '<span class="text-red-500 text-sm">Clerk.js failed to load — check console</span>';
        } else {
            // v5: window.Clerk is a singleton instance, not a constructor
            const clerk = window.Clerk;

            // showSignInButtons is defined here so it closes over `clerk`
            function showSignInButtons() {
                navAuth.innerHTML = `
                    <button id="sign-in-nav"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-lg transition-colors">
                        Sign In
                    </button>
                `;
                document.getElementById('sign-in-nav').addEventListener('click', () => clerk.openSignIn());

                heroCta.innerHTML = `
                    <button id="sign-in-cta"
                        class="bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-bold py-4 px-8 rounded-xl text-lg transition-all transform hover:scale-105">
                        Start Your Free Trial
                    </button>
                `;
                document.getElementById('sign-in-cta').addEventListener('click', () => clerk.openSignIn());
            }

            clerk.load({ publishableKey: window.__clerk_publishable_key }).then(() => {
                if (clerk.user) {
                    // Signed in — show "Go to App" + user button
                    navAuth.innerHTML = `
                        <div id="user-btn-mount" class="flex items-center gap-4">
                            <a href="product.php"
                               class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-lg transition-colors">
                                Go to App
                            </a>
                        </div>
                    `;
                    clerk.mountUserButton(document.getElementById('user-btn-mount'), { showName: true });

                    heroCta.innerHTML = `
                        <a href="product.php">
                            <button class="bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-bold py-4 px-8 rounded-xl text-lg transition-all transform hover:scale-105">
                                Access Premium Features
                            </button>
                        </a>
                    `;
                } else {
                    showSignInButtons();
                }
            }).catch((err) => {
                console.error('Clerk load error:', err);
                navAuth.innerHTML = `<span class="text-red-500 text-sm">Auth error: ${err.message}</span>`;
                showSignInButtons();
            });
        }
    </script>
</body>
</html>
