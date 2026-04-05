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
$resumeText = $input['resume_text'] ?? '';

if (empty(trim($resumeText))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Resume text is required.']);
    die();
}

// Mock AI Suggestions
sleep(1);
$suggestions = [
    [
        'section' => 'General',
        'suggestion' => 'Consider using stronger action verbs to start your bullet points.'
    ],
    [
        'section' => 'Experience',
        'suggestion' => 'Quantify your achievements with metrics where possible.'
    ]
];

echo json_encode(['success' => true, 'suggestions' => $suggestions]);
