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
if (!$user->isAdmin($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    die();
}

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::checkCsrf();

    // Check both $_POST and php://input for JSON
    $targetUserId = $_POST['user_id'] ?? '';

    if (empty($targetUserId)) {
        $input = json_decode(file_get_contents('php://input'), true);
        if (is_array($input)) {
            $targetUserId = $input['user_id'] ?? '';
        }
    }

    // If action is passed as a GET param for a POST request
    if ($action === 'approve_user') {
        $result = $user->approveUser($_SESSION['user_id'], $targetUserId);
        echo json_encode($result);
        die();
    }
}

if ($action === 'list_users') {
    $users = json_decode(file_get_contents(USERS_FILE), true);
    // Remove password hashes
    foreach ($users as &$u) {
        unset($u['password']);
    }
    echo json_encode(['success' => true, 'users' => array_values($users)]);
    die();
}

if ($action === 'system_stats') {
    $users = json_decode(file_get_contents(USERS_FILE), true);
    $totalUsers = count($users);
    $pendingUsers = count(array_filter($users, function($u) { return !$u['is_approved']; }));

    $jobsFile = __DIR__ . '/../data/jobs.json';
    $totalJobs = 0;
    if (file_exists($jobsFile)) {
        $totalJobs = count(json_decode(file_get_contents($jobsFile), true) ?: []);
    }

    echo json_encode([
        'success' => true,
        'stats' => [
            'total_users' => $totalUsers,
            'pending_users' => $pendingUsers,
            'total_jobs' => $totalJobs
        ]
    ]);
    die();
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
