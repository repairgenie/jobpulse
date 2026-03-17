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
$status = $input['status'] ?? 'Custom';
$text = trim($input['text'] ?? '');

if (!$jobId || (empty($status) && empty($text))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

try {
    if (!file_exists(HISTORY_FILE)) {
        throw new Exception("History file not found.");
    }

    $history = json_decode(file_get_contents(HISTORY_FILE), true);
    if (!is_array($history)) throw new Exception("Invalid history data.");

    $updated = false;
    $newNote = [
        'type' => htmlspecialchars($status),
        'text' => htmlspecialchars($text),
        'timestamp' => time()
    ];

    foreach ($history as &$job) {
        if ($job['id'] === $jobId && $job['user_id'] === $_SESSION['user_id']) {
            if (!isset($job['notes']) || !is_array($job['notes'])) {
                $job['notes'] = [];
            }
            // Add note to the beginning of the notes array so newest is first, or end. Let's do end so it's chronological.
            $job['notes'][] = $newNote;
            $updated = true;
            break;
        }
    }

    if (!$updated) {
        throw new Exception("Job not found or unauthorized.");
    }

    file_put_contents(HISTORY_FILE, json_encode($history, JSON_PRETTY_PRINT));

    echo json_encode(['success' => true, 'note' => $newNote]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
