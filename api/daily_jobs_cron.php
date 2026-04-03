<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../src/User.php';
require_once __DIR__ . '/../src/JobScraper.php';

use App\User;
use App\JobScraper;

// Ensure this script is run from CLI (Cron)
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

echo "Starting Daily Job Recommendations...\n";

// Example mock DB interaction to get users with their preferences
$usersFile = DATA_DIR . '/users.json';
if (!file_exists($usersFile)) {
    die("No users found.\n");
}

$users = json_decode(file_get_contents($usersFile), true) ?: [];
$proxyUrl = defined('PROXY_URL') ? PROXY_URL : null;
$scraper = new JobScraper($proxyUrl);

foreach ($users as $index => $user) {
    if (empty($user['email']) || empty($user['id'])) continue;

    $userId = $user['id'];

    echo "Processing for User: {$user['email']}...\n";

    // Simulate getting user preferences (e.g. keywords and location)
    $keywords = $user['job_keywords'] ?? 'Software Developer';
    $location = $user['city'] ?? 'Remote';
    $remote = $user['remote_only'] ?? true;

    try {
        $jobs = $scraper->searchJobs($keywords, $location, $remote, false);

        echo "Found " . count($jobs) . " jobs.\n";

        $autoGen = $user['auto_generate_docs'] ?? false;

        if ($autoGen && count($jobs) > 0) {
            echo "Auto-generating cover letter and resume for highest matching job...\n";

            require_once __DIR__ . '/../src/ResumeOptimizer.php';
            $optimizer = new \App\ResumeOptimizer();

            try {
                $result = $optimizer->optimize($userId, $jobs[0]['description']);
                if ($result) {
                    echo "Successfully generated docs for job ID: {$jobs[0]['id']}\n";
                }
            } catch (\Exception $e) {
                echo "Failed to generate docs for job ID: {$jobs[0]['id']}. Error: " . $e->getMessage() . "\n";
            }
        }

    } catch (\Exception $e) {
        echo "Error for user {$user['email']}: " . $e->getMessage() . "\n";
    }
}

echo "Cron completed.\n";
