<?php
ob_start();
session_start();

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../src/User.php';
require_once __DIR__ . '/../src/JobScraper.php';

use App\User;
use App\JobScraper;

ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) return false;
    error_log("PHP Error [$errno]: $errstr in $errfile on line $errline");
    if (ob_get_level() > 0) ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => "An internal server error occurred."]);
    exit;
});

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Please log in.']);
    exit;
}

$query = $_GET['q'] ?? '';
$location = $_GET['l'] ?? '';
$remote = filter_var($_GET['remote'] ?? false, FILTER_VALIDATE_BOOLEAN);
$hybrid = filter_var($_GET['hybrid'] ?? false, FILTER_VALIDATE_BOOLEAN);

$userObj = new User();

// Fallback to user Zip Code if location is empty
if (empty(trim($location))) {
    $userLocation = $userObj->getUserLocation($_SESSION['user_id']);
    if ($userLocation && !empty($userLocation['zip_code'])) {
        $location = $userLocation['zip_code'];
    } elseif ($userLocation && !empty($userLocation['city'])) {
        $location = $userLocation['city'] . ', ' . $userLocation['state'];
    }
}

// Construct Adzuna keywords
$searchKeywords = array_filter(array_map('trim', explode(' ', $query)));
if ($remote) $searchKeywords[] = 'remote';
if ($hybrid) $searchKeywords[] = 'hybrid';

$keywordString = implode(' ', $searchKeywords);

// Initialize Scraper using proxy if configured
$proxyUrl = defined('PROXY_URL') ? PROXY_URL : null;
$scraper = new JobScraper($proxyUrl);

if (ADZUNA_APP_ID === 'your_adzuna_app_id' || ADZUNA_APP_ID === 'PLACEHOLDER' || empty(ADZUNA_APP_ID)) {
    // Generate Scraper / Mock Response
    sleep(1); // simulate network delay
    
    try {
        $scrapedJobs = $scraper->searchJobs($keywordString, $location, $remote, $hybrid);
        echo json_encode([
            'success' => true,
            'results' => $scrapedJobs,
            'filters_applied' => ['q' => $keywordString, 'l' => $location, 'scraped' => true]
        ]);
        exit;
    } catch (\Exception $e) {
        error_log("Job Scraper Search Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'An error occurred while searching for jobs.']);
        exit;
    }
}

// Live Adzuna API Call
$baseUrl = "https://api.adzuna.com/v1/api/jobs/us/search/1";
$queryParams = [
    'app_id' => ADZUNA_APP_ID,
    'app_key' => ADZUNA_APP_KEY,
    'results_per_page' => 15,
    'what' => $keywordString,
    'where' => $location,
    'sort_by' => 'date'
];

$url = $baseUrl . '?' . http_build_query($queryParams);

try {
    if (!function_exists('curl_init')) {
        throw new Exception("cURL extension is not enabled on this server. Please enable it in your php.ini.");
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        throw new Exception("cURL Error: " . curl_error($ch));
    }
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (ob_get_level() > 0) ob_end_clean();
    
    if (ob_get_level() > 0) ob_end_clean();
    
    if ($httpCode >= 400) {
        throw new Exception("Adzuna API Error (HTTP {$httpCode})");
    }
    
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Failed to parse Adzuna JSON response.");
    }
    
    echo json_encode([
        'success' => true,
        'results' => $data['results'] ?? [],
        'filters_applied' => ['q' => $keywordString, 'l' => $location]
    ]);

} catch (Exception $e) {
    error_log("Adzuna API Search Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'An error occurred while searching for jobs via Adzuna API.']);
}

