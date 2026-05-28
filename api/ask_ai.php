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
$ctx     = $input['context'] ?? [];   // job_description, resume_text, cover_letter

if (empty($message)) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Message is required.']));
}

// Build system instruction
$systemInstruction = "You are a helpful AI career assistant embedded in JobPulse, an AI-powered job application tool.
You have been given the following context about the user's current job application. Use it to answer questions accurately and helpfully.
---
JOB POSTING:
" . ($ctx['job_description'] ?? '(not provided)') . "
---
CANDIDATE'S OPTIMIZED RESUME:
" . ($ctx['resume_text'] ?? '(not provided)') . "
---
GENERATED COVER LETTER:
" . ($ctx['cover_letter'] ?? '(not provided)') . "---

Your role:
- Help the candidate understand the job requirements
- Answer application questions (e.g. 'How should I answer X screening question?')
- Provide interview prep tips based on the job description
- Suggest how to position their experience for this specific role
- Be concise, specific, and encouraging
- Never make up experience the candidate doesn't have
Respond in a conversational, helpful tone.";

// Build messages array for multi-provider support
$messages = [];
foreach ($history as $turn) {
    $role = ($turn['role'] === 'user') ? 'user' : 'assistant';
    $messages[] = ['role' => $role, 'content' => $turn['text']];
}
$messages[] = ['role' => 'user', 'content' => $message];

// Mock mode for Ollama with no key, or placeholder key
$needsMock = (
    LLM_PROVIDER === 'ollama'
    || LLM_PROVIDER === 'lmstudio'
    || (LLM_API_KEY === '' || LLM_API_KEY === 'PLACEHOLDER' || LLM_API_KEY === 'your_gemini_api_key_here')
);

if ($needsMock) {
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'reply'   => "This is a mock AI response. Configure your LLM provider to enable real responses."
    ]);
    exit;
}

try {
    $llm = new LLMProvider();
    $reply = $llm->chat($systemInstruction, $messages, 'text');

    ob_end_clean();
    echo json_encode(['success' => true, 'reply' => $reply]);

} catch (Exception $e) {
    if (ob_get_level() > 0) ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
