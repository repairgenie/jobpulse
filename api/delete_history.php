<?php
session_start();
require_once __DIR__ . '/../bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? '';

if (empty($id)) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Missing id']));
}

$file = HISTORY_FILE;
$userId = $_SESSION['user_id'];

$history = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
if (!is_array($history)) $history = [];

// Only allow deleting entries that belong to this user
$before = count($history);
$history = array_values(array_filter($history, function($item) use ($id, $userId) {
    return !($item['id'] === $id && $item['user_id'] === $userId);
}));

if (count($history) === $before) {
    http_response_code(404);
    exit(json_encode(['success' => false, 'error' => 'Entry not found']));
}

file_put_contents($file, json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
header('Content-Type: application/json');
echo json_encode(['success' => true]);
