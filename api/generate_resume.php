<?php
session_start();
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../src/ResumeOptimizer.php';
require_once __DIR__ . '/../src/ResumeManager.php';
require_once __DIR__ . '/../src/Security.php';

use App\ResumeOptimizer;
use App\ResumeManager;
use App\Security;

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    die();
}

Security::checkCsrf();

$input = json_decode(file_get_contents('php://input'), true);
$workHistory = $input['work_history'] ?? '';
$jobDescription = $input['job_description'] ?? '';

if (empty(trim($workHistory))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Work history is required.']);
    die();
}

$ai = new ResumeOptimizer();
$prompt = "You are an expert resume writer. The user has provided their work history/skills below. ";
if (!empty(trim($jobDescription))) {
    $prompt .= "The user also provided a target job description. Generate a tailored, professional resume based ONLY on the user's work history, highlighting the aspects most relevant to the target job description. Do not add made-up information.\n\n";
    $prompt .= "--- TARGET JOB DESCRIPTION ---\n$jobDescription\n\n";
} else {
    $prompt .= "Generate a professional, well-formatted general resume based on this work history.\n\n";
}
$prompt .= "--- USER WORK HISTORY ---\n$workHistory\n\n";
$prompt .= "Output the resume in clean Markdown format.";

try {
    if (GEMINI_API_KEY === 'your_gemini_api_key_here' || GEMINI_API_KEY === 'PLACEHOLDER' || GEMINI_API_KEY === 'test_key' || empty(GEMINI_API_KEY)) {
        // Mock Response
        sleep(2);
        $generatedResume = "# AI Generated Resume\n\n";
        if (!empty(trim($jobDescription))) {
            $generatedResume .= "Targeting Job:\n" . substr($jobDescription, 0, 100) . "...\n\n";
        }
        $generatedResume .= "## Work History\n" . $workHistory;
    } else {
        // Actual Gemini API Call
        $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/" . GEMINI_MODEL . ":generateContent?key=" . GEMINI_API_KEY);

        $postData = json_encode([
            "contents" => [
                [
                    "role" => "user",
                    "parts" => [
                        ["text" => $prompt]
                    ]
                ]
            ]
        ]);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);

        $result = curl_exec($ch);

        if (curl_errno($ch)) throw new \Exception("cURL Error: " . curl_error($ch));

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) throw new \Exception("Gemini API Error (HTTP " . $httpCode . "): " . $result);

        $decoded = json_decode($result, true);

        if (isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
            $generatedResume = $decoded['candidates'][0]['content']['parts'][0]['text'];
            $generatedResume = mb_convert_encoding($generatedResume, 'UTF-8', 'UTF-8');
        } else {
            throw new \Exception("Unexpected response format from Gemini.");
        }
    }

    $resumeMgr = new ResumeManager();
    $name = "AI Generated " . date('Y-m-d H:i');
    $data = $resumeMgr->saveResume($_SESSION['user_id'], $name, $generatedResume, 'AI Generated');

    echo json_encode(['success' => true, 'resume' => $data]);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'AI Generation failed: ' . $e->getMessage()]);
}
