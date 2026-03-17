<?php
session_start();
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$file = HISTORY_FILE;

$allHistory = file_exists($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];

// Filter for current user
$history = array_filter($allHistory, function($item) use ($userId) {
    return isset($item['user_id']) && $item['user_id'] === $userId;
});

// Reset keys for JSON array
$history = array_values($history);

echo json_encode(['success' => true, 'history' => $history]);
