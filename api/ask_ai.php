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
$ctx     = $input['context'] ?? [];   // job_description, resume_text, cover_letter

if (empty($message)) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Message is required.']));
}

// Build system context
$systemParts = "You are a helpful AI career assistant embedded in JobPulse, an AI-powered job application tool.

You have been given the following context about the user's current job application. Use it to answer questions accurately and helpfully.

---
JOB POSTING:
" . ($ctx['job_description'] ?? '(not provided)') . "

---
CANDIDATE'S OPTIMIZED RESUME:
" . ($ctx['resume_text'] ?? '(not provided)') . "

---
GENERATED COVER LETTER:
" . ($ctx['cover_letter'] ?? '(not provided)') . "
---

Your role:
- Help the candidate understand the job requirements
- Answer application questions (e.g. 'How should I answer X screening question?')
- Provide interview prep tips based on the job description
- Suggest how to position their experience for this specific role
- Be concise, specific, and encouraging
- Never make up experience the candidate doesn't have

Respond in a conversational, helpful tone.";

// Build Gemini conversation contents array
$contents = [];

// Inject prior history
foreach ($history as $turn) {
    $role = ($turn['role'] === 'user') ? 'user' : 'model';
    $contents[] = ['role' => $role, 'parts' => [['text' => $turn['text']]]];
}

// Add current user message
$contents[] = ['role' => 'user', 'parts' => [['text' => $message]]];

if (GEMINI_API_KEY === 'your_gemini_api_key_here' || empty(GEMINI_API_KEY)) {
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'reply'   => "This is a mock AI response. Configure your Gemini API key to enable real responses."
    ]);
    exit;
}

try {
    $payload = json_encode([
        'system_instruction' => ['parts' => [['text' => $systemParts]]],
        'contents'           => $contents,
        'generationConfig'   => ['temperature' => 0.5, 'maxOutputTokens' => 1024]
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
