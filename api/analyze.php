<?php
session_start();

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../src/ResumeManager.php';

use App\ResumeManager;

header('Content-Type: application/json');

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
$jobDescription = $input['job_description'] ?? '';
$targetResumeId = $input['resume_id'] ?? null;

if (empty(trim($jobDescription))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Job description is required.']);
    exit;
}

$resumeMgr = new ResumeManager();
$userId = $_SESSION['user_id'];
$resumes = $resumeMgr->getResumes($userId);

if (empty($resumes)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No resumes found on your profile. Please upload one first.']);
    exit;
}

// Determine which resume to use
$selectedResumeMeta = null;
if ($targetResumeId) {
    foreach ($resumes as $res) {
        if ($res['id'] === $targetResumeId) {
            $selectedResumeMeta = $res;
            break;
        }
    }
} else {
    // Fallback to Primary
    foreach ($resumes as $res) {
        if ($res['is_primary']) {
            $selectedResumeMeta = $res;
            break;
        }
    }
    // Deep fallback
    if (!$selectedResumeMeta) $selectedResumeMeta = $resumes[0];
}

if (!$selectedResumeMeta) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Could not identify the selected resume.']);
    exit;
}

// Fetch the full text payload
$resumeData = $resumeMgr->getResumeFull($userId, $selectedResumeMeta['id']);
if (!$resumeData || empty($resumeData['extracted_text'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Corrupt resume file, missing text. Please delete and re-upload.']);
    exit;
}

$resumeText = $resumeData['extracted_text'];

$systemInstruction = "You are an expert recruiter and career coach. Analyze the candidate's resume against the provided job description.
Provide the following in your analysis:
1. A 'match_score' (integer between 0 and 100).
2. A 'missing_keywords' array (list of important keywords from the JD missing in the resume).
3. A short 'strategy' (a paragraph summarizing interview strategy or resume tailoring advice).
4. A 'cover_letter' (a professional, compelling cover letter written from the candidate's perspective based on the fit).

Return ONLY a valid JSON object matching this structure exactly:
{
  \"match_score\": 85,
  \"missing_keywords\": [\"Docker\", \"Kubernetes\"],
  \"strategy\": \"Your strategy here...\",
  \"cover_letter\": \"Dear Hiring Manager,...\"
}";

$prompt = "Job Description:\n" . $jobDescription . "\n\nCandidate Resume:\n" . $resumeText;

try {
    if (GEMINI_API_KEY === 'your_gemini_api_key_here' || empty(GEMINI_API_KEY)) {
        // Mock Response
        sleep(2);
        $aiResponse = [
            'match_score' => rand(70, 95),
            'missing_keywords' => ['Cloudflare', 'GraphQL'],
            'strategy' => 'Focus heavily on your communication skills and past leadership roles, as they value autonomy.',
            'cover_letter' => "Dear Hiring Manager,\n\nI am thrilled to apply for this role. My background aligns perfectly with your requirements...\n\nBest regards,\nCandidate"
        ];
    } else {
        $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/" . GEMINI_MODEL . ":generateContent?key=" . GEMINI_API_KEY);
        
        $postData = json_encode([
            "system_instruction" => [
                "parts" => [
                    ["text" => $systemInstruction]
                ]
            ],
            "contents" => [
                [
                    "role" => "user",
                    "parts" => [
                        ["text" => $prompt]
                    ]
                ]
            ],
            "generationConfig" => [
                "temperature" => 0.7,
                "responseMimeType" => "application/json"
            ]
        ]);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);

        $result = curl_exec($ch);
        
        if (curl_errno($ch)) throw new Exception("cURL Error: " . curl_error($ch));
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 400) throw new Exception("Gemini API Error (HTTP " . $httpCode . "): " . $result);

        $decoded = json_decode($result, true);
        
        if (isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
            $jsonText = $decoded['candidates'][0]['content']['parts'][0]['text'];
            $aiResponse = json_decode($jsonText, true);
            if (!$aiResponse) throw new Exception("Failed to parse Gemini JSON output. Raw: " . $jsonText);
        } else {
            throw new Exception("Unexpected response format from Gemini.");
        }
    }

    // Save history
    $historyFile = __DIR__ . '/../data/history.json';
    if (!file_exists($historyFile)) file_put_contents($historyFile, json_encode([]));
    
    $history = json_decode(file_get_contents($historyFile), true) ?: [];
    
    $vibe = $aiResponse['match_score'] > 85 ? "Excellent Fit! High Vibes." : ($aiResponse['match_score'] > 75 ? "Good Potential. Solid Match." : "Needs Tailoring. Low Vibes.");
    
    $analysisResult = [
        'id' => uniqid(),
        'user_id' => $_SESSION['user_id'],
        'timestamp' => date('Y-m-d H:i:s'),
        'resume_name' => $selectedResumeMeta['filename'],
        'resume_category' => $selectedResumeMeta['category'],
        'job_title' => "Analyzed Job", 
        'job_description_snippet' => substr($jobDescription, 0, 100) . '...',
        'score' => $aiResponse['match_score'],
        'vibe' => $vibe,
        'keywords' => $aiResponse['missing_keywords'],
        'strategy' => $aiResponse['strategy'],
        'cover_letter' => $aiResponse['cover_letter']
    ];
    
    array_unshift($history, $analysisResult);
    if (count($history) > 20) $history = array_slice($history, 0, 20); // Keep last 20 overall
    file_put_contents($historyFile, json_encode($history, JSON_PRETTY_PRINT));

    echo json_encode([
        'success' => true,
        'result' => $analysisResult,
        'used_resume' => [
            'id' => $selectedResumeMeta['id'],
            'filename' => $selectedResumeMeta['filename']
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

