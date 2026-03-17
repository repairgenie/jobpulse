<?php

$configFile = __DIR__ . '/config.php';

if (!file_exists($configFile)) {
    displayError(["config.php file is missing. Please copy config.php.new to config.php and fill in the values."]);
}

$config = require $configFile;

$errors = [];
$requiredKeys = ['GEMINI_API_KEY'];

foreach ($requiredKeys as $key) {
    if (!isset($config[$key]) || $config[$key] === 'PLACEHOLDER' || empty(trim($config[$key]))) {
        $errors[] = $key;
    }
}

if (!empty($errors)) {
    displayError($errors);
}

// Map associative array to constants for backward compatibility with the rest of the application
if (!defined('GEMINI_API_KEY')) define('GEMINI_API_KEY', $config['GEMINI_API_KEY']);
if (!defined('GEMINI_MODEL')) define('GEMINI_MODEL', 'gemini-3.1-flash-lite-preview');
if (!defined('ADZUNA_APP_ID')) define('ADZUNA_APP_ID', $config['ADZUNA_APP_ID']);
if (!defined('ADZUNA_APP_KEY')) define('ADZUNA_APP_KEY', $config['ADZUNA_APP_KEY']);
if (!defined('REGISTRATION_MODE')) define('REGISTRATION_MODE', $config['REGISTRATION_MODE'] ?? 'admin_approval');
if (!defined('REQUIRE_EMAIL_CONFIRMATION')) define('REQUIRE_EMAIL_CONFIRMATION', false);

// Directory Constants
if (!defined('DATA_DIR')) define('DATA_DIR', __DIR__ . '/data');
if (!defined('USERS_FILE')) define('USERS_FILE', DATA_DIR . '/users.json');
if (!defined('HISTORY_FILE')) define('HISTORY_FILE', DATA_DIR . '/history.json');
if (!defined('UPLOADS_DIR')) define('UPLOADS_DIR', __DIR__ . '/uploads');

function displayError(array $missingKeys) {
    ?>
    <!DOCTYPE html>
    <html lang="en" class="dark">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Configuration Error</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = {
                darkMode: 'class',
                theme: {
                    extend: {
                        colors: { 
                            darkbg: '#0f172a',    
                            card: '#1e293b'   
                        }
                    }
                }
            }
        </script>
    </head>
    <body class="bg-darkbg text-slate-300 font-sans antialiased min-h-screen flex items-center justify-center p-6">
        <div class="max-w-md w-full bg-card/80 backdrop-blur-xl py-8 px-6 shadow-2xl rounded-3xl border border-red-500/50">
            <div class="flex items-center justify-center w-12 h-12 rounded-full bg-red-500/20 mb-4 mx-auto">
                <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            </div>
            <h2 class="text-2xl font-extrabold text-white text-center mb-2">Configuration Required</h2>
            <p class="text-sm text-slate-400 text-center mb-6">Your application is missing critical API keys.</p>
            
            <div class="bg-darkbg border border-red-500/30 rounded-xl p-4 mb-6">
                <p class="text-xs font-bold text-red-400 uppercase tracking-widest mb-3">Missing or Placeholder Values:</p>
                <ul class="space-y-2">
                    <?php foreach ($missingKeys as $key): ?>
                    <li class="flex items-center text-sm font-medium text-slate-300">
                        <span class="w-1.5 h-1.5 rounded-full bg-red-500 mr-2"></span>
                        <?= htmlspecialchars($key) ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <p class="text-xs text-slate-500 text-center">Please update your <code class="text-slate-300 bg-slate-800 px-1 rounded">config.php</code> file in the root directory and refresh the page.</p>
        </div>
    </body>
    </html>
    <?php
    exit;
} ?>
