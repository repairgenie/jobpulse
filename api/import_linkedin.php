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

Security::checkCsrf();

// Check both POST and php://input
$profileUrl = $_POST['linkedin_url'] ?? '';

if (empty($profileUrl)) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (is_array($input)) {
        $profileUrl = $input['profile_url'] ?? $input['linkedin_url'] ?? '';
    }
}

if (empty(trim($profileUrl))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Profile URL is required.']);
    die();
}

// Mock LinkedIn Import
sleep(2);
$mockWorkHistory = "Software Engineer at Tech Corp for 5 years.\nBuilt React frontends and Node.js backends.\n\nJunior Developer at Startup Inc for 2 years.\nMaintained legacy PHP applications.";

$user = new User();
if ($user->updateWorkHistory($_SESSION['user_id'], $mockWorkHistory)) {
    echo json_encode(['success' => true, 'work_history' => $mockWorkHistory]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save work history.']);
}