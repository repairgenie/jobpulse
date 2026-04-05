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
        $action = $_GET['action'] ?? 'list';
        if ($action === 'get_full') {
            $id = $_GET['id'] ?? '';
            if (!$id) {
                echo json_encode(['success' => false, 'error' => 'No ID provided.']);
                die();
            }
            $resume = $resumeMgr->getResumeFull($userId, $id);
            if ($resume) {
                echo json_encode(['success' => true, 'resume' => [
                    'id' => $resume['id'],
                    'filename' => $resume['filename'],
                    'category' => $resume['category'],
                    'text' => $resume['extracted_text']
                ]]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Resume not found.']);
            }
        } else {
            $resumes = $resumeMgr->getResumes($userId);
            echo json_encode(['success' => true, 'resumes' => $resumes]);
        }
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

        elseif ($action === 'update') {
            $filename = $input['filename'] ?? 'resume.txt';
            $category = $input['category'] ?? 'General';
            $extractedText = $input['extracted_text'] ?? '';

            if (empty($extractedText)) {
                echo json_encode(['success' => false, 'error' => 'Text cannot be empty.']);
                die();
            }

            $data = $resumeMgr->saveResume($userId, $filename, $extractedText, $category, $resumeId);
            echo json_encode(['success' => true, 'resume' => $data]);
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

