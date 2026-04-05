<?php
namespace App;

require_once __DIR__ . '/ResumeManager.php';

class ResumeOptimizer {
    public function optimize($userId, $jobDescription, $targetResumeId = null) {
        $jobHash = md5($jobDescription);
        $resumeMgr = new ResumeManager();

        if (!$targetResumeId) {
            // Find active resume
            $resumes = $resumeMgr->getResumes($userId);
            foreach ($resumes as $res) {
                if (!empty($res['is_primary'])) {
                    $targetResumeId = $res['id'];
                    break;
                }
            }
        }

        if (!$targetResumeId) {
            throw new \Exception("No active resume found. Please upload and activate a base resume first.");
        }

        $resumeData = $resumeMgr->getResumeFull($userId, $targetResumeId);

        if (!$resumeData || empty($resumeData['extracted_text'])) {
            throw new \Exception("Active resume text could not be found.");
        }
        $resumeText = $resumeData['extracted_text'];

        $systemInstruction = "You are an expert ATS (Applicant Tracking System) optimizer and technical recruiter. Your primary goal is to rewrite the candidate's resume to EXACTLY match the provided job description while maintaining perfect honesty.

Follow these strict constraints:
1. ONLY USE skills, experiences, and metrics that ALREADY EXIST in the user's original resume. NEVER invent, assume, or hallucinate new skills or experiences.
2. REWRITE existing bullet points to prominently feature keywords and phrases found in the job description, provided the original context supports it.
3. ADOPT the terminology used in the job description (e.g., if the user says \"AWS\" and the job says \"Amazon Web Services\", change it to \"Amazon Web Services\").
4. HIGHLIGHT overlapping tools, frameworks, and methodologies.
5. REMOVE or DE-EMPHASIZE skills and bullet points from the original resume that are entirely irrelevant to this specific job description, to keep the resume concise and targeted.
6. The final output must be in professional Markdown format, structurally resembling a standard resume (Header, Summary, Experience, Skills, Education).
7. You must NOT generate any bullet points that are empty or consist only of punctuation/whitespace. Every `*` must be followed by substantive content.

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

        if (GEMINI_API_KEY === 'your_gemini_api_key_here' || GEMINI_API_KEY === 'PLACEHOLDER' || GEMINI_API_KEY === 'test_key' || empty(GEMINI_API_KEY)) {
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

            if (curl_errno($ch)) throw new \Exception("cURL Error: " . curl_error($ch));

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode >= 400) throw new \Exception("Gemini API Error (HTTP " . $httpCode . "): " . $result);

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
                    throw new \Exception("Failed to parse Gemini JSON output correctly. Error: $jsonError.");
                }
            } else {
                throw new \Exception("Unexpected response format from Gemini.");
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
            'user_id' => $userId,
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

        return $resultObj;
    }
}