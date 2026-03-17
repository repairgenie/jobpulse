<?php
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$jobId = $input['job_id'] ?? null;
$title = trim($input['title'] ?? '');
$company = trim($input['company'] ?? '');
$url = trim($input['url'] ?? '');

if (!$jobId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Job ID is required.']);
    exit;
}

try {
    if (!file_exists(HISTORY_FILE)) {
        throw new Exception("History file not found.");
    }

    $history = json_decode(file_get_contents(HISTORY_FILE), true);
    if (!is_array($history)) throw new Exception("Invalid history data.");

    $updated = false;

    foreach ($history as &$job) {
        if ($job['id'] === $jobId && $job['user_id'] === $_SESSION['user_id']) {
            $job['job_title'] = $title ?: '(Untitled)';
            $job['job_company'] = $company ?: '(Unknown Company)';
            $job['job_url'] = $url;
            $updated = true;
            break;
        }
    }

    if (!$updated) {
        throw new Exception("Job not found or unauthorized.");
    }

    file_put_contents(HISTORY_FILE, json_encode($history, JSON_PRETTY_PRINT));

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
