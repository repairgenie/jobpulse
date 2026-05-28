<?php
namespace App;

require_once __DIR__ . '/ResumeManager.php';
require_once __DIR__ . '/LLMProvider.php';

class ResumeOptimizer {

    private $llm;

    public function __construct() {
        $this->llm = new LLMProvider();
    }

    /**
     * Thin wrapper — delegates to LLMProvider.request() which already handles
     * LM Studio's token-limit retry loop (finish_reason === 'length').
     */
    private function callLLM(string $systemInstruction, string $prompt): array {
        return $this->llm->request($systemInstruction, $prompt);
    }
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

        // ── CHUNK 1: Extract compact profile from resume ─────────────────────
        $extractInstruction = "You are a precise resume parser. Extract ONLY these fields from the resume text below. Return ONLY valid JSON — no explanation, no markdown, no commentary.

Return this exact JSON structure:
{
  \"candidate_name\": \"Full name from resume header\",
  \"job_title\": \"Primary professional title or role\",
  \"years_experience\": \"Total years of experience (number or rough estimate)\",
  \"key_skills\": [\"skill1\", \"skill2\", \"skill3\", ...] — only the most important 8-15 skills/tools/technologies
}

Rules:
- key_skills: list technical skills, tools, frameworks, and methodologies ONLY
- Do not invent skills. Only include skills that appear in the resume.
- If you cannot determine a field, use null (not empty string).";

        $extractPrompt = "Resume Text:\n" . $resumeText;
        $compactProfile = $this->callLLM($extractInstruction, $extractPrompt);

        // ── CHUNK 2: Generate cover letter, missing/perfect skills ───────────
        $systemInstruction = "You are an expert ATS (Applicant Tracking System) optimizer and technical recruiter. Your primary goal is to rewrite the candidate's resume to EXACTLY match the provided job description while maintaining perfect honesty.

CRITICAL — DO NOT HALLUCINATE:
- You must reuse the EXACT job titles, company names, dates, and bullet points from the 'Candidate Full Resume Text' provided below.
- Do NOT replace any company name with another company name.
- Do NOT invent a university name. If education information is not present in the resume, omit the education section.
- If the resume does not contain a specific job title or company, do not create one.
- Every bullet point in the output must be a rephrasing of an existing bullet point from the resume.

Follow these strict constraints:
1. ONLY REWORD existing bullet points to prominently feature keywords and phrases found in the job description, keeping the underlying accomplishment identical.
2. ADOPT the terminology used in the job description (e.g., if the resume says \"AWS\" and the job says \"Amazon Web Services\", change it to \"Amazon Web Services\").
3. HIGHLIGHT overlapping tools, frameworks, and methodologies.
4. REMOVE or DE-EMPHASIZE bullet points from the original resume that are entirely irrelevant to this specific job description, to keep the resume concise and targeted.
5. The final output must be in professional Markdown format, structurally resembling a standard resume (Header, Summary, Experience, Skills, Education).
6. You must NOT generate any bullet points that are empty or consist only of punctuation/whitespace. Every bullet must be substantive.

Return ONLY a valid JSON object matching this exact structure:
{
  \"candidate_name\": \"The candidate's full name from the resume\",
  \"job_title\": \"The exact job title from the posting\",
  \"job_company\": \"The exact company name from the posting\",
  \"optimized_resume_text\": \"The complete rewritten resume in markdown — include header, all actual job titles and companies, all reworded bullet points\",
  \"missing_skills\": [\"List\", \"of\", \"absent\", \"skills\"],
  \"perfect_matches\": [\"List\", \"of\", \"skills\", \"present\", \"in\", \"both\"],
  \"cover_letter\": \"Single string - all 3 paragraphs concatenated with blank lines between them. Paragraph 1: Hook - specific role + company, 1 standout achievement with metrics. Paragraph 2: 2-3 accomplishments matching key job requirements, each with a metric. Paragraph 3: Closing enthusiasm + call to action. Be specific to this candidate and job - no generic filler.\"
}

For job_title and job_company: extract these directly from the job posting text. If the company name is genuinely not mentioned, use null. If the job title is not explicitly stated, infer it from context or use null.";

        // Pass the FULL resume text — not a compact profile — so the model can faithfully
        // rephrase actual bullet points instead of hallucinating job titles and education.
        // The "do not invent" rule is unenforceable if the model never sees what was written.
        $generatePrompt = "Job Description:\n" . $jobDescription . "\n\nCandidate Full Resume Text:\n" . $resumeText . "\n\nInstructions: Rewrite the resume above to match the job description. Keep all actual job titles, companies, dates, and bullet points — only rephrase wording to highlight relevant keywords. Do NOT invent new job titles, companies, or education institutions. Output the complete rewritten resume.";
        $aiResponse = $this->callLLM($systemInstruction, $generatePrompt);

        // Merge extracted name back into response (overrides whatever model says)
        $aiResponse['candidate_name'] = $compactProfile['candidate_name'] ?? $aiResponse['candidate_name'] ?? '';

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
