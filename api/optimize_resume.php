<?php
session_start();

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../src/ResumeOptimizer.php';

use App\ResumeOptimizer;

header('Content-Type: application/json');
ob_start(); // Buffer all output to catch stray warnings

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Please log in.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$jobDescription = trim($input['job_description'] ?? '');
$targetResumeId = $input['resume_id'] ?? null;

if (empty(trim($jobDescription))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Job description is required.']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    $optimizer = new ResumeOptimizer();
    $resultObj = $optimizer->optimize($userId, $jobDescription, $targetResumeId);

    ob_end_clean(); // Discard any warnings/output that might have occurred
    echo json_encode([
        'success' => true,
        'result' => $resultObj
    ]);

} catch (Exception $e) {
    error_log("Optimize Resume Error: " . $e->getMessage());
    if (ob_get_level() > 0) ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'An error occurred while optimizing the resume.']);
}
