<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../src/User.php';
require_once __DIR__ . '/../src/JobScraper.php';
require_once __DIR__ . '/../src/JobRepository.php';
require_once __DIR__ . '/../src/ResumeOptimizer.php';

use App\User;
use App\JobScraper;
use App\JobRepository;
use App\ResumeOptimizer;

$usersFile = USERS_FILE;

if (!file_exists($usersFile)) {
    echo "No users found.\n";
    die();
}

$users = json_decode(file_get_contents($usersFile), true) ?: [];

$scraper = new JobScraper();
$optimizer = new ResumeOptimizer();

foreach ($users as $user) {
    if (empty($user['job_keywords']) || $user['is_active'] != 1 || empty($user['auto_generate_docs'])) {
        continue;
    }

    echo "Processing daily jobs for: " . $user['email'] . "\n";
    $query = $user['job_keywords'];
    $location = $user['city'] . ($user['state'] ? ', ' . $user['state'] : '');

    $isRemote = $user['remote_only'] ?? false;
    $isHybrid = false;

    try {
        $jobs = $scraper->searchJobs($query, $location, $isRemote, $isHybrid, 1);

        $jobRepo = new JobRepository($user['id']);
        foreach($jobs as $job) {

            $jobRepo->create([
                'company' => $job['company']['display_name'] ?? 'Unknown Company',
                'title' => $job['title'] ?? 'Unknown Position',
                'status' => 'Pending',
                'date' => date('Y-m-d'),
                'notes' => 'Sourced via Auto-Job Scraper'
            ]);


            // Auto generate resume and cover letter
            try {
                $optimizer->optimize($user['id'], $job['description']);
                echo "Generated application docs for job: " . $job['title'] . "\n";
            } catch (\Exception $e) {
                echo "Failed to generate docs for job " . $job['title'] . ": " . $e->getMessage() . "\n";
            }
        }
        echo "Saved " . count($jobs) . " jobs.\n";

    } catch (\Exception $e) {
        echo "Error fetching jobs: " . $e->getMessage() . "\n";
    }
}
