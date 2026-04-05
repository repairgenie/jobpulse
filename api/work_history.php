<?php
session_start();
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../src/User.php';
require_once __DIR__ . '/../src/Security.php';

use App\User;
use App\Security;

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    die();
}

$user = new User();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $history = $user->getWorkHistory($_SESSION['user_id']);
    echo json_encode(['success' => true, 'work_history' => $history]);
    die();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::checkCsrf();

    $input = json_decode(file_get_contents('php://input'), true);
    $history = $input['work_history'] ?? '';

    if ($user->updateWorkHistory($_SESSION['user_id'], $history)) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to save work history.']);
    }
    die();
}
