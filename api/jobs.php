<?php

use App\JobRepository;

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$jobRepo = new JobRepository();

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                $job = $jobRepo->getById((int)$_GET['id']);
                if ($job) echo json_encode($job);
                else { http_response_code(404); echo json_encode(['error' => 'Not found']); }
            } else {
                echo json_encode($jobRepo->getAll());
            }
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid JSON input']);
                exit;
            }
            $id = $jobRepo->create($input);
            http_response_code(201);
            echo json_encode(['id' => $id, 'message' => 'Job created']);
            break;

        case 'PUT':
            // Parse ID from query string or body
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $_GET['id'] ?? $input['id'] ?? null;
            
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'ID is required']);
                exit;
            }
            
            $jobRepo->update((int)$id, $input);
            echo json_encode(['message' => 'Job updated']);
            break;

        case 'DELETE':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'ID is required']);
                exit;
            }
            $jobRepo->delete((int)$id);
            echo json_encode(['message' => 'Job deleted']);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
