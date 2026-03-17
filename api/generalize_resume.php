<?php
ob_start();
session_start();
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../src/ResumeManager.php';

use App\ResumeManager;

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

$input = json_decode(file_get_contents('php://input'), true);
$resumeId  = $input['resume_id']  ?? '';
$direction = $input['direction']  ?? '';  // notes / job description target
$label     = $input['label']      ?? 'General';

if (empty($resumeId) || empty(trim($direction))) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Resume and direction are required.']));
}

$label = preg_replace('/[^a-zA-Z0-9\s\-]/', '', $label);
if (empty(trim($label))) $label = 'General';

$resumeMgr  = new ResumeManager();
$userId     = $_SESSION['user_id'];
$resumeData = $resumeMgr->getResumeFull($userId, $resumeId);

if (!$resumeData || empty($resumeData['extracted_text'])) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Could not load the selected resume.']));
}

$resumeText = $resumeData['extracted_text'];

$systemInstruction = "You are an expert resume writer. Your task is to rewrite a candidate's resume so it is optimized as a strong, generalized resume for a specific role type or industry.

Rules:
1. Rewrite the resume to best align with the target direction or job description provided.
2. Emphasize skills, experiences, and accomplishments most relevant to the target area.
3. Do NOT invent skills or experiences the candidate doesn't have. Only reframe existing experience.
4. Use strong, punchy bullet points starting with action verbs.
5. CRITICAL FORMATTING: ALL bullet points MUST use the `*` markdown format (unordered list). NEVER use numbered lists.
6. Output only the resume content in clean markdown — no commentary, no preamble.
7. Include: Name/Contact header, Professional Summary, Core Skills, Professional Experience (with bullets), any other sections present in the original.

Return ONLY the rewritten resume text as a plain markdown string. Do not wrap it in JSON.";

$prompt = "Target Direction / Reference Job Description:\n{$direction}\n\nOriginal Resume:\n{$resumeText}";

try {
    if (GEMINI_API_KEY === 'your_gemini_api_key_here' || empty(GEMINI_API_KEY)) {
        $generalizedText = "# Generalized Resume\n\n* This is a mock generalized resume.\n* It is optimized for the requested direction.\n";
    } else {
        $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/" . GEMINI_MODEL . ":generateContent?key=" . GEMINI_API_KEY);
        $postData = json_encode([
            "system_instruction" => ["parts" => [["text" => $systemInstruction]]],
            "contents"           => [["role" => "user", "parts" => [["text" => $prompt]]]],
            "generationConfig"   => ["temperature" => 0.3]
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $result   = curl_exec($ch);
        if (curl_errno($ch)) throw new Exception("cURL Error: " . curl_error($ch));
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) throw new Exception("Gemini API error (HTTP $httpCode).");

        $decoded = json_decode($result, true);
        $generalizedText = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (empty($generalizedText)) throw new Exception("Gemini returned an empty response.");
    }

    // Save as a new resume entry using the existing ResumeManager
    $newFilename = "Generalized_{$label}_" . date('Y-m-d') . ".txt";
    $savedData   = $resumeMgr->saveResume($userId, $newFilename, $generalizedText, $label);

    ob_end_clean();
    echo json_encode([
        'success'  => true,
        'resume'   => $savedData,
        'text'     => $generalizedText,
        'message'  => "Generalized resume saved as \"{$label}\"!"
    ]);

} catch (Exception $e) {
    if (ob_get_level() > 0) ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
