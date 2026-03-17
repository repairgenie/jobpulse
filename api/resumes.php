<?php
session_start();

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../src/ResumeManager.php';

use App\ResumeManager;

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$resumeMgr = new ResumeManager();
$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $resumes = $resumeMgr->getResumes($userId);
        echo json_encode(['success' => true, 'resumes' => $resumes]);
        break;

    case 'POST':
        // For setting primary or deleting since standard html forms handle POST easier than PUT/DELETE sometimes,
        // but we can parse JSON body.
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        $resumeId = $input['resume_id'] ?? '';

        if (empty($resumeId)) {
            echo json_encode(['success' => false, 'error' => 'Resume ID missing.']);
            exit;
        }

        if ($action === 'set_primary') {
            $success = $resumeMgr->setPrimaryResume($userId, $resumeId);
            echo json_encode(['success' => $success]);
        } 
        elseif ($action === 'delete') {
            $success = $resumeMgr->deleteResume($userId, $resumeId);
            echo json_encode(['success' => $success]);
        } 
        else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action.']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
        break;
}

