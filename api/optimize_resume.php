<?php
session_start();

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../src/ResumeManager.php';

use App\ResumeManager;

header('Content-Type: application/json');
ob_start(); // Buffer all output to catch stray warnings

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
$jobDescription = trim($input['job_description'] ?? '');
$targetResumeId = $input['resume_id'] ?? null;
$jobHash = md5($jobDescription);

if (empty(trim($jobDescription))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Job description is required.']);
    exit;
}

$resumeMgr = new ResumeManager();
$userId = $_SESSION['user_id'];

if (!$targetResumeId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Active resume ID is missing.']);
    exit;
}

$resumeData = $resumeMgr->getResumeFull($userId, $targetResumeId);
if (!$resumeData || empty($resumeData['extracted_text'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Corrupt or missing active resume. Please re-select.']);
    exit;
}

$resumeText = $resumeData['extracted_text'];

$systemInstruction = "You are an expert technical recruiter and resume writer. Your task is to optimize a candidate's resume for a specific job description.

Follow these strict rules:
1. Compare: Identify critical skills and keywords in the Job Description that are explicitly or implicitly present in the Resume. These are 'Perfect Matches'.
2. Optimize: Rewrite and enhance the bullet points of the Resume to prominently feature the keywords from the Job Description, BUT ONLY if the user undeniably possesses that skill based on their original text. Make the bullet points punchy and impact-driven.
3. Honesty Check: DO NOT hallucinate or add skills the candidate does not have. If the Job Description requires a skill that is completely absent from the Resume, add it to the 'missing_skills' array.
4. Cover Letter: Generate a cover letter using the OPTIMIZED resume and the Job Description. The tone must be professional yet energetic (suitable for a tech/IT role). Explicitly highlight the 'Perfect Matches' found during the analysis to prove fit.
5. CRITICAL FORMATTING: ALL bullet points in the `optimized_resume_text` MUST use the `*` markdown format (unordered list). NEVER use numbered lists (`1.`, `2.`, etc.). Every work experience entry must have substantive bullet points starting with `*`. You MUST NEVER generate empty bullet points (e.g. `* ` with no text) or bullet points that contain only filler characters. Every bullet point must describe a real skill, achievement, or responsibility. If you have no meaningful content for a bullet, do NOT include it.
6. PRESERVE METADATA: You MUST preserve all dates, company names, and locations exactly as they appear in the original resume. ONLY optimize the bullet points and summary text. Do NOT alter, strip, or summarize employment dates or titles. Every company and duration from the original resume MUST be present in the optimized version.
7. NAME EXTRACTION: You MUST extract the candidate's full name from the original resume and return it in the `candidate_name` field.
8. BLANK BULLET PREVENTION: You MUST NOT generate any bullet points that are empty or consist only of punctuation/whitespace. Every `*` must be followed by substantive content.

Return ONLY a valid JSON object matching this exact structure:
{
  \"candidate_name\": \"The candidate's full name from the resume, e.g. John Doe\",
  \"job_title\": \"The exact job title from the posting, e.g. Premium Support Specialist\",
  \"job_company\": \"The exact company name from the posting, e.g. Harvey\",
  \"optimized_resume_text\": \"Fully re-written markdown text of the resume...\",
  \"missing_skills\": [\"List\", \"of\", \"absent\", \"skills\"],
  \"perfect_matches\": [\"List\", \"of\", \"skills\", \"present\", \"in\", \"both\"],
  \"cover_letter\": \"Dear Hiring Manager,...\"
}

For job_title and job_company: extract these directly from the job posting text. If the company name is genuinely not mentioned, use null. If the job title is not explicitly stated, infer it from context or use null.";

$prompt = "Job Description:\n" . $jobDescription . "\n\nOriginal Candidate Resume:\n" . $resumeText;

try {
    if (GEMINI_API_KEY === 'your_gemini_api_key_here' || empty(GEMINI_API_KEY)) {
        // Mock Response
        sleep(2);
        $aiResponse = [
            'candidate_name' => 'Jane Doe',
            'optimized_resume_text' => "# Jane Doe - Senior Developer\n\n* Optimized bullet point highlighting Cloudflare experience which was already on the resume.\n* Another strong, metric-driven bullet point showing impact.",
            'missing_skills' => ['Python', 'AWS DynamoDB'],
            'perfect_matches' => ['React', 'Node.js', 'Cloudflare'],
            'cover_letter' => "Dear Hiring Manager,\n\nI am thrilled to apply for this role. My background aligns perfectly with your requirements, specifically regarding React, Node.js, and Cloudflare. I'm highly energetic about building robust tech!\n\nBest regards,\nJane Doe"
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
                "temperature" => 0.3, // Lower temp for more predictable structural JSON
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
            
            // Normalize encoding to handle special characters and smart quotes
            $jsonText = mb_convert_encoding($jsonText, 'UTF-8', 'UTF-8');

            // Balanced Brace Parser: Extract the first complete JSON object found
            $startPos = strpos($jsonText, '{');
            if ($startPos !== false) {
                $braceCount = 0;
                $endPos = false;
                for ($i = $startPos; $i < strlen($jsonText); $i++) {
                    if ($jsonText[$i] === '{') $braceCount++;
                    if ($jsonText[$i] === '}') {
                        $braceCount--;
                        if ($braceCount === 0) {
                            $endPos = $i;
                            break;
                        }
                    }
                }
                if ($endPos !== false) {
                    $jsonText = substr($jsonText, $startPos, $endPos - $startPos + 1);
                }
            }
            
            // Fix unescaped newlines within JSON strings that cause syntax errors
            $aiResponse = json_decode($jsonText, true);
            
            if (!$aiResponse) {
                // If it fails, try to aggressively escape literal newlines within quotes
                $cleanedJson = preg_replace_callback('/"(.*?)"\s*(:|\]|\}|,)/s', function($m) {
                    $inner = $m[1];
                    $inner = str_replace(["\n", "\r"], ["\\n", ""], $inner);
                    return '"' . $inner . '"' . $m[2];
                }, $jsonText);
                $aiResponse = json_decode($cleanedJson, true);
            }

            if (!$aiResponse || !isset($aiResponse['optimized_resume_text'])) {
                $jsonError = json_last_error_msg();
                file_put_contents(__DIR__ . '/../data/last_json_error.txt', "Error: $jsonError\nRaw:\n" . $jsonText);
                throw new Exception("Failed to parse Gemini JSON output correctly. Error: $jsonError.");
            }
        } else {
            throw new Exception("Unexpected response format from Gemini.");
        }
    }

    // Prepare History Object
    // Load existing history
    $history = [];
    if (file_exists(HISTORY_FILE)) {
        $histData = json_decode(file_get_contents(HISTORY_FILE), true);
        if (is_array($histData)) $history = $histData;
    }
    
    // Normalize perfect_matches
    $perfectMatchesCount = 0;
    if (isset($aiResponse['perfect_matches'])) {
        if (is_array($aiResponse['perfect_matches'])) {
            $perfectMatchesCount = count($aiResponse['perfect_matches']);
        } elseif (is_string($aiResponse['perfect_matches'])) {
            $perfectMatchesCount = 1;
            $aiResponse['perfect_matches'] = [$aiResponse['perfect_matches']];
        }
    }

    // Ratio-based score: perfect / (perfect + missing) * 100
    // Missing skills now reduce score proportionally (honest match %)
    $missingCount = isset($aiResponse['missing_skills']) && is_array($aiResponse['missing_skills'])
        ? count($aiResponse['missing_skills']) : 0;
    $totalSkills = $perfectMatchesCount + $missingCount;
    $score = $totalSkills > 0
        ? (int) round(($perfectMatchesCount / $totalSkills) * 100)
        : 70; // neutral fallback if AI returned no skill data
    

    $resultObj = [
        'id' => uniqid('opt_'),
        'user_id' => $_SESSION['user_id'],
        'resume_id' => $resumeData['id'],
        'resume_name' => $resumeData['filename'],
        'resume_category' => $resumeData['category'],
        'job_title'   => !empty($aiResponse['job_title'])   ? $aiResponse['job_title']   : '(Untitled)',
        'job_company' => !empty($aiResponse['job_company']) ? $aiResponse['job_company'] : '(Unknown Company)',
        'job_description' => $jobDescription,
        'job_url' => '',
        'job_hash' => $jobHash,
        'timestamp' => time(),
        'score' => $score,
        'keywords' => (isset($aiResponse['missing_skills']) && is_array($aiResponse['missing_skills'])) ? $aiResponse['missing_skills'] : [],
        'strategy' => "AI analyzed constraints. Added " . $perfectMatchesCount . " direct alignments.",
        'perfect_matches' => (isset($aiResponse['perfect_matches']) && is_array($aiResponse['perfect_matches'])) ? $aiResponse['perfect_matches'] : [],
        'cover_letter' => $aiResponse['cover_letter'] ?? null,
        'optimized_resume_text' => $aiResponse['optimized_resume_text'],
        'notes' => [
            ['type' => 'System', 'text' => 'Generated AI Application.', 'timestamp' => time()]
        ]
    ];
    
    // Deduplication Logic by Description Hash
    $isDuplicate = false;
    foreach ($history as $key => $existingJob) {
        if (isset($existingJob['job_hash']) && $existingJob['job_hash'] === $jobHash) {
            
            // Keep existing ID, Notes, and manually-set URL
            $resultObj['id']      = $existingJob['id'];
            $resultObj['notes']   = array_merge($existingJob['notes'] ?? [], $resultObj['notes']);
            $resultObj['job_url'] = $existingJob['job_url'] ?? '';

            // Only restore title/company from history if they are real values (not stale fallbacks).
            // Prefer the AI-extracted values from the current run.
            $staleTitle   = in_array($existingJob['job_title']   ?? '', ['(Untitled)',        '', null], true);
            $staleCompany = in_array($existingJob['job_company'] ?? '', ['(Unknown Company)', '', null], true);
            if (!$staleTitle)   $resultObj['job_title']   = $existingJob['job_title'];
            if (!$staleCompany) $resultObj['job_company'] = $existingJob['job_company'];
            
            unset($history[$key]); // Remove old position
            $isDuplicate = true;
            break;
        }
    }
    
    array_unshift($history, $resultObj); // Add to top either way
    
    // Use robust JSON encoding flags to prevent data loss on complex characters
    file_put_contents(HISTORY_FILE, json_encode(array_values($history), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));

    ob_end_clean(); // Discard any warnings/output that might have occurred
    echo json_encode([
        'success' => true,
        'result' => $resultObj
    ]);

} catch (Exception $e) {
    if (ob_get_level() > 0) ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

