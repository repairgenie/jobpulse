<?php
session_start();
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? '';
$jobDescription = $input['job_description'] ?? '';

if (empty($id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID is required']);
    exit;
}

$file = HISTORY_FILE;
$userId = $_SESSION['user_id'];

$history = file_exists($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];

$found = false;
foreach ($history as &$item) {
    if ($item['id'] === $id && $item['user_id'] === $userId) {
        $item['job_description'] = $jobDescription;
        $found = true;
        break;
    }
}

if (!$found) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'History item not found']);
    exit;
}

file_put_contents($file, json_encode(array_values($history), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));

echo json_encode(['success' => true]);
