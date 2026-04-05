<?php
session_start();
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../src/Security.php';

use App\Security;

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    die();
}

Security::checkCsrf();

$input = json_decode(file_get_contents('php://input'), true);
$jobId = $input['job_id'] ?? '';

if (empty($jobId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Job ID is required.']);
    die();
}

// Mock AI Interview Prep Data
sleep(1);
$prepData = [
    'success' => true,
    'questions' => [
        "Can you walk me through a time when you had to optimize a complex system?",
        "How do you approach learning a new technology stack on the job?",
        "Describe a situation where you disagreed with a team member on a technical approach. How did you resolve it?",
        "What is your experience with CI/CD pipelines and deployment strategies?",
        "Explain a difficult technical problem you solved recently."
    ],
    'tips' => [
        "Highlight your experience with scalable architectures based on your work history.",
        "The job emphasizes teamwork; be prepared to discuss collaborative projects.",
        "Review your knowledge of cloud deployment platforms as they are a key requirement."
    ]
];

echo json_encode($prepData);
