<?php
ob_start();
session_start();

if (!defined('DATA_DIR')) define('DATA_DIR', __DIR__ . '/../data');
$usersFile = DATA_DIR . '/users.json';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

$input = json_decode(file_get_contents('php://input'), true);

if (isset($input['auto_generate_docs'])) {
    if (file_exists($usersFile)) {
        $users = json_decode(file_get_contents($usersFile), true) ?: [];
        $userId = $_SESSION['user_id'];

        $found = false;
        foreach ($users as &$user) {
            if (isset($user['id']) && $user['id'] === $userId) {
                $user['auto_generate_docs'] = filter_var($input['auto_generate_docs'], FILTER_VALIDATE_BOOLEAN);
                $found = true;
                break;
            }
        }

        if ($found) {
            file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));
            echo json_encode(['success' => true]);
            exit;
        }
    }
}

echo json_encode(['success' => false, 'error' => 'Invalid request or user not found.']);
