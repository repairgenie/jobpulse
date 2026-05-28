<?php
ob_start();
session_start();
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../src/LLMProvider.php';

use App\LLMProvider;

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

$input   = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? '');
$history = $input['history'] ?? [];   // [{role, text}, ...]
$ctx     = $input['context'] ?? [];   // job_description, resume_text

$systemInstruction = "You are an elite, expert technical recruiter and hiring manager conducting a screening interview with a candidate.
You have been given the following context about the candidate's job application:
---
JOB POSTING:
" . ($ctx['job_description'] ?? '(not provided)') . "
---
CANDIDATE'S RESUME:
" . ($ctx['resume_text'] ?? '(not provided)') . "---

Your role:
- Act entirely in character as the interviewer.
- Ask one concise, penetrating question at a time based on their resume and the job requirements.
- Never list multiple questions at once.
- Evaluate their previous answer naturally in your response, then pivot to the next question.
- Keep your responses under 3-4 sentences. This is a conversational voice/chat simulation. Do not use complex markdown that is hard to read aloud, just use plain, conversational text.
- If they ask for feedback at the end or try to break character, you can give them a brief critique of their interview performance.";

// Build messages array for multi-provider chat
$messages = [];
foreach ($history as $turn) {
    if (empty(trim($turn['text']))) continue;
    $messages[] = ['role' => ($turn['role'] === 'user' ? 'user' : 'assistant'), 'content' => $turn['text']];
}

if (!empty($message)) {
    $messages[] = ['role' => 'user', 'content' => $message];
} else if (empty($history)) {
    $messages[] = ['role' => 'user', 'content' => "Hello, I am ready to start the interview. Please ask your first question."];
}

$needsMock = (
    LLM_PROVIDER === 'ollama'
    || LLM_PROVIDER === 'lmstudio'
    || (LLM_API_KEY === '' || LLM_API_KEY === 'PLACEHOLDER' || LLM_API_KEY === 'your_gemini_api_key_here')
);

if ($needsMock) {
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'reply'   => "This is a mock interview response. Configure your LLM provider in config.php to enable real responses."
    ]);
    exit;
}

try {
    $llm = new LLMProvider();
    $reply = $llm->chat($systemInstruction, $messages, 'text', 0.6);

    ob_end_clean();
    echo json_encode(['success' => true, 'reply' => $reply]);

} catch (Exception $e) {
    if (ob_get_level() > 0) ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
