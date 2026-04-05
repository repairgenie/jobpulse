<?php
ob_start();
session_start();
require_once __DIR__ . '/../bootstrap.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

$input   = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? '');
$history = $input['history'] ?? [];   // [{role, text}, ...]
$ctx     = $input['context'] ?? [];   // job_description, resume_text

// Note: For the very first message, $message might be empty if we want the AI to start the interview.
// But we can trigger the first message by having the frontend send a hidden system initialization prompt,
// OR just accept an empty message and let the system instruction kick it off.

$systemParts = "You are an elite, expert technical recruiter and hiring manager conducting a screening interview with a candidate.

You have been given the following context about the candidate's job application:

---
JOB POSTING:
" . ($ctx['job_description'] ?? '(not provided)') . "

---
CANDIDATE'S RESUME:
" . ($ctx['resume_text'] ?? '(not provided)') . "
---

Your role:
- Act entirely in character as the interviewer.
- Ask one concise, penetrating question at a time based on their resume and the job requirements.
- Never list multiple questions at once.
- Evaluate their previous answer naturally in your response, then pivot to the next question.
- Keep your responses under 3-4 sentences. This is a conversational voice/chat simulation. Do not use complex markdown that is hard to read aloud, just use plain, conversational text.
- If they ask for feedback at the end or try to break character, you can give them a brief critique of their interview performance.";

$contents = [];

// Inject prior history
foreach ($history as $turn) {
    if (empty(trim($turn['text']))) continue;
    $role = ($turn['role'] === 'user') ? 'user' : 'model';
    $contents[] = ['role' => $role, 'parts' => [['text' => $turn['text']]]];
}

// Add current user message
if (!empty($message)) {
    $contents[] = ['role' => 'user', 'parts' => [['text' => $message]]];
} else if (empty($history)) {
    // If no history and no message, we are starting the interview.
    // We send a hidden prompt to kick off the AI.
    $contents[] = ['role' => 'user', 'parts' => [['text' => "Hello, I am ready to start the interview. Please ask your first question."]]];
}

if (GEMINI_API_KEY === 'your_gemini_api_key_here' || empty(GEMINI_API_KEY)) {
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'reply'   => "This is a mock response because the Gemini API key is not configured. Please add it to config.php."
    ]);
    exit;
}

try {
    $payload = json_encode([
        'system_instruction' => ['parts' => [['text' => $systemParts]]],
        'contents'           => $contents,
        'generationConfig'   => ['temperature' => 0.6, 'maxOutputTokens' => 512]
    ]);

    $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/" . GEMINI_MODEL . ":generateContent?key=" . GEMINI_API_KEY);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $result   = curl_exec($ch);
    if (curl_errno($ch)) throw new Exception("cURL Error: " . curl_error($ch));
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) throw new Exception("Gemini API error (HTTP $httpCode): $result");

    $decoded = json_decode($result, true);
    $reply   = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;
    if (empty($reply)) throw new Exception("Empty response from Gemini.");

    ob_end_clean();
    echo json_encode(['success' => true, 'reply' => $reply]);

} catch (Exception $e) {
    if (ob_get_level() > 0) ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
