<?php
session_start();

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../src/LLMProvider.php';
require_once __DIR__ . '/../src/ResumeManager.php';

use App\LLMProvider;
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
4. A 'cover_letter' (a single string containing exactly 3 paragraphs separated by blank lines. Paragraph 1: Hook - specific role + company, 1 standout achievement with metrics. Paragraph 2: 2-3 accomplishments matching key job requirements, each with a metric. Paragraph 3: Closing enthusiasm + call to action. Be specific to this candidate and job - no generic filler.)

Return ONLY a valid JSON object matching this structure exactly:
{
  \"match_score\": 85,
  \"missing_keywords\": [\"Docker\", \"Kubernetes\"],
  \"strategy\": \"Your strategy here...\",
  \"cover_letter\": \"Dear Hiring Manager,...\"
}";

// Wrap all LLM logic in try/catch so exceptions are handled properly
try {
    $llm = new LLMProvider();

    // For LM Studio / Ollama with large prompts (resume + job description),
    // we must chunk to fit within the model's context window (4096 tokens).
    // Each chunk: resume section + job description, leaving 1024 tokens for response.
    $maxContext = 3072; // conservative for 4096 context window
    $jobLen = strlen($jobDescription);
    $resumeLen = strlen($resumeText);
    $combined = $jobLen + $resumeLen;

    // Rough token estimate: 1 char ≈ 0.25 tokens
    $combinedTokens = (int)(($combined) / 4);

    if ($combinedTokens <= $maxContext) {
        // Fits in one chunk
        $prompt = "Job Description:\n" . $jobDescription . "\n\nCandidate Resume:\n" . $resumeText;
        $chunks = [['prompt' => $prompt, 'section' => 'full']];
    } else {
        // Chunk the resume by sections, keep job description with each chunk
        $sections = preg_split('/\n(?=(?:PROFESSIONAL EXPERIENCE|TECHNICAL SKILLS|EDUCATION|PROJECT|PROFILE)/i', $resumeText);
        $chunks = [];
        $currentChunk = '';
        $currentTokens = (int)($jobLen / 4); // job desc tokens

        foreach ($sections as $section) {
            $sectionTokens = (int)(strlen($section) / 4);
            if ($currentTokens + $sectionTokens > $maxContext && $currentChunk !== '') {
                $chunks[] = ['prompt' => "Job Description:\n" . $jobDescription . "\n\nCandidate Resume Section:\n" . $currentChunk, 'section' => count($chunks) + 1];
                $currentChunk = $section;
                $currentTokens = $sectionTokens + (int)($jobLen / 4);
            } else {
                $currentChunk .= "\n" . $section;
                $currentTokens += $sectionTokens;
            }
        }
        if ($currentChunk) {
            $chunks[] = ['prompt' => "Job Description:\n" . $jobDescription . "\n\nCandidate Resume Section:\n" . $currentChunk, 'section' => count($chunks) + 1];
        }
    }

    $aiResponse = null;

    if (GEMINI_API_KEY === 'your_gemini_api_key_here' || empty(GEMINI_API_KEY)) {
        // No Gemini key → use LM Studio via chunked request (actual LLM call)
        $allResponses = [];
        foreach ($chunks as $chunk) {
            $response = $llm->request($systemInstruction, $chunk['prompt'], 0.7);
            $allResponses[] = $response;
        }
        $matchScores = array_column($allResponses, 'match_score');
        $allKeywords = [];
        foreach ($allResponses as $r) {
            if (!empty($r['missing_keywords'])) {
                $allKeywords = array_merge($allKeywords, $r['missing_keywords']);
            }
        }
        $allKeywords = array_unique($allKeywords);
        $strategies = array_filter(array_column($allResponses, 'strategy'));
        $coverLetters = array_filter(array_column($allResponses, 'cover_letter'));
        $aiResponse = [
            'match_score' => $matchScores ? (int)array_sum($matchScores) / count($matchScores) : 75,
            'missing_keywords' => array_slice($allKeywords, 0, 15),
            'strategy' => implode(' ', $strategies) ?: 'Review the missing keywords and tailor your resume accordingly.',
            'cover_letter' => implode("\n---\n", $coverLetters)
        ];
    } else {
        // Gemini available — single-pass request (Gemini handles context internally)
        $aiResponse = $llm->request($systemInstruction, $prompt, 0.7);
    }

    if ($aiResponse === null) {
        throw new Exception('Analysis failed: no response from LLM.');
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

