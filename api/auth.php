<?php

session_start();

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../src/User.php';

use App\User;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

require_once __DIR__ . '/../src/Security.php';
use App\Security;

// Apply Rate Limiting to Auth Endpoints
if (!Security::rateLimit($_SERVER['REMOTE_ADDR'], 5, 60)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many requests. Please try again later.']);
    exit;
}

$action = $_GET['action'] ?? '';
$userObj = new User();

switch ($action) {
    case 'login':
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Email and password required.']);
            exit;
        }
        
        $result = $userObj->login($email, $password);
        
        if ($result['success']) {
            $_SESSION['user_id'] = $result['user']['id'];
            $_SESSION['email'] = $result['user']['email'];
            $_SESSION['role'] = $result['user']['role'];
            $_SESSION['first_name'] = $result['user']['first_name'] ?? '';
            $_SESSION['last_name'] = $result['user']['last_name'] ?? '';
        }
        
        echo json_encode($result);
        break;

    case 'register':
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $first_name = $_POST['first_name'] ?? '';
        $last_name = $_POST['last_name'] ?? '';
        $city = $_POST['city'] ?? '';
        $state = $_POST['state'] ?? '';
        $zip_code = $_POST['zip_code'] ?? '';
        
        if (empty($email) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Email and password required.']);
            exit;
        }
        
        $result = $userObj->register($email, $password, 'user', [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'city' => $city,
            'state' => $state,
            'zip_code' => $zip_code
        ]);
        
        // If registration was completely open and active immediately, auto-login.
        // But logic is handled inside User.php (returns success if active or pending)
        // Let's just return the message to the frontend.
        echo json_encode($result);
        break;

    case 'forgot_password':
        $email = $_POST['email'] ?? '';
        if (empty($email)) {
            echo json_encode(['success' => false, 'message' => 'Email required.']);
            exit;
        }
        $token = bin2hex(random_bytes(32)); $userObj->setResetToken($email, $token);
        if ($token) {
            // Mock sending email
            echo json_encode(['success' => true, 'message' => 'Password reset email sent.', 'mock_token' => $token]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Email not found.']);
        }
        break;

    case 'reset_password':
        $email = $_POST['email'] ?? '';
        $token = $_POST['token'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        if (empty($email) || empty($token) || empty($newPassword)) {
            echo json_encode(['success' => false, 'message' => 'Email, token, and new password required.']);
            exit;
        }
        $success = $userObj->resetPassword($email, $token, $newPassword);
        if ($success) {
            echo json_encode(['success' => true, 'message' => 'Password reset successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid token or email.']);
        }
        break;

    case 'verify_email':
        $email = $_POST['email'] ?? '';
        $token = $_POST['token'] ?? '';
        if (empty($email) || empty($token)) {
            echo json_encode(['success' => false, 'message' => 'Email and token required.']);
            exit;
        }
        $success = $userObj->verifyEmail($email, $token);
        if ($success) {
            echo json_encode(['success' => true, 'message' => 'Email verified successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid token or email.']);
        }
        break;

    case 'logout':
        session_unset();
        session_destroy();
        echo json_encode(['success' => true, 'message' => 'Logged out successfully.']);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);
        break;
}

