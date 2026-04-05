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

$input = json_decode(file_get_contents('php://input'), true);
$prefs = $input['preferences'] ?? $input;

$userObj = new User();
$users = json_decode(file_get_contents(USERS_FILE), true);
$updated = false;

foreach ($users as &$user) {
    if ($user['id'] === $_SESSION['user_id']) {
        if (isset($prefs['job_keywords'])) $user['job_keywords'] = $prefs['job_keywords'];
        if (isset($prefs['city'])) $user['city'] = $prefs['city'];
        if (isset($prefs['remote_only'])) $user['remote_only'] = (bool)$prefs['remote_only'];
        if (isset($prefs['auto_generate_docs'])) $user['auto_generate_docs'] = (bool)$prefs['auto_generate_docs'];
        $updated = true;
        break;
    }
}

if ($updated) {
    file_put_contents(USERS_FILE, json_encode($users, JSON_PRETTY_PRINT));
    echo json_encode(['success' => true, 'message' => 'Preferences updated.']);
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'User not found.']);
}
