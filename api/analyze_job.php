<?php
ob_start();
session_start();
require_once __DIR__ . '/../bootstrap.php';
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) return false;
    if (ob_get_level() > 0) ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => "PHP Error [$errno]: $errstr in $errfile on line $errline"]);
    exit;
});

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

$input          = json_decode(file_get_contents('php://input'), true);
$jobDescription = $input['job_description'] ?? '';
$resumeText     = $input['resume_text']     ?? '';
$coverLetter    = $input['cover_letter']    ?? '';
$jobCompanyHint = $input['job_company_hint'] ?? '';
$jobTitleHint   = $input['job_title_hint']   ?? '';
$isHtml         = $input['is_html']         ?? false;

if ($isHtml) {
    // If it's HTML (from Tiptap), strip tags to give the AI clean text
    $resumeText  = strip_tags(str_replace(['<br>', '</div>', '</p>'], "\n", $resumeText));
    $coverLetter = strip_tags(str_replace(['<br>', '</div>', '</p>'], "\n", $coverLetter));
}

if (empty(trim($jobDescription))) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Job description is required.']));
}

$systemInstruction = <<<EOT
You are an elite career strategist and executive coach. You produce deep, insightful tactical intelligence reports for job candidates preparing for high-stakes applications and interviews.

You will be given:
- A job posting
- The candidate's optimized resume
- Their cover letter

Your job is to produce a comprehensive JSON report with EXACTLY these fields, all written in rich markdown:

1. **company_problems**: Analyze the job posting for signals about what problems the company is trying to solve. What pain points prompted this hire? What is broken or missing on their team? Be specific, insightful, and sharp — not generic. Use bullet points for each identified problem with 2-3 sentences of analysis per point.

2. **company_goals**: Infer the company's strategic goals based on the posting. What is the business trying to achieve in the next 6-24 months with this role? What does success look like for the team and this hire? Include analysis of the company's competitive positioning if inferable.

3. **candidate_alignment**: Map the candidate's specific background to the company's problems and goals. For each major company problem identified, explain exactly how the candidate's experience addresses it. Frame this as interview intelligence — "When they ask about X, use Y from your background." Be specific, not generic.

4. **interview_prep**: Generate a massive, high-quality interview preparation guide. You MUST provide EXACTLY 15 questions in EACH of the following three categories (45 questions total):
   - **Category A: Behavioral & Cultural Fit** (Soft skills, team collaboration, conflict resolution, values alignment).
   - **Category B: Technical & Role-Specific Skills** (Direct experience with tools, methodologies, and core competencies mentioned in the posting).
   - **Category C: Situational, Problem-Solving & Leadership** (Hypothetical "What would you do if..." scenarios, strategic thinking, and taking initiative).
   
   **CRITICAL STRUCTURE RULE FOR INTERVIEW PREP:** For EACH of the 45 questions, you MUST provide these 3 distinct parts in markdown:
   1. The Question itself.
   2. **Why they are likely to ask it**: A deep strategic reason (2-3 sentences).
   3. **Strategic Answer Framework**: A detailed 3-4 sentence guide on how to answer, specifically linking to the candidate's actual experience from their resume.
   Do NOT use one-line summaries. Do NOT skip the explanation for any question.

5. **cheat_sheet**: A condensed 1-page quick-reference document. Include: company snapshot, 3-5 key things they are looking for, the candidate's top 5 power talking points, 3-5 questions to ask the interviewer, and key phrases/keywords to drop naturally.

6. **questions_to_ask**: A comprehensive list of questions the candidate should ask the employer. You MUST provide EXACTLY 15 questions in EACH of the following two sections (30 questions total):
   - **Part A — Universal Questions:** Cover compensation philosophy, pay bands, bonus/equity, detailed benefits, PTO, remote/hybrid conditions, performance review cadence, promotion criteria, onboarding, team management style, and what success looks like in 90 days.
   - **Part B — Role-Specific Questions:** Strategic questions based on the job posting, company industry, and technical challenges inferred from the description.
   
   **CRITICAL STRUCTURE RULE FOR QUESTIONS TO ASK:** For EACH of the 30 questions, you MUST provide:
   1. The Question itself.
   2. **Why it matters**: A 1-2 sentence note on what this reveals about the company or how it protects the candidate's interests.

Return ONLY a valid JSON object with EXACTLY these eight fields:
{
  "extracted_company": "The short, clean company name (max 5 words). NO sentences or explanations. If not found, use a 1-2 word inference or 'Unknown'.",
  "extracted_role": "The clean job title (max 5 words). NO sentences or explanations.",
  "company_problems": "(markdown)",
  "company_goals": "(markdown)",
  "candidate_alignment": "(markdown)",
  "interview_prep": "(markdown)",
  "cheat_sheet": "(markdown)",
  "questions_to_ask": "(markdown)"
}
All markdown values must be thorough. For extracted_company and extracted_role: use ONLY the name/title (max 5 words). Do not include 'The company is...' or any other narrative. E.g. 'Google' or 'ACME Corp'.
EOT;

// Build the prompt, providing hints if they are not placeholders
$placeholders = ['(Unknown Company)', '(Untitled)', 'Unknown Company', 'Untitled', ''];
$hasCompanyHint = !in_array(trim($jobCompanyHint), $placeholders, true);
$hasTitleHint   = !in_array(trim($jobTitleHint),   $placeholders, true);

$prompt  = "=== JOB POSTING ===\n" . $jobDescription . "\n\n";
$prompt .= "=== CANDIDATE RESUME ===\n" . $resumeText . "\n\n";
$prompt .= "=== COVER LETTER ===\n" . $coverLetter . "\n\n";

if ($hasCompanyHint || $hasTitleHint) {
    $prompt .= "CONTEXT HINTS:\n";
    if ($hasCompanyHint) $prompt .= "- Established Company Name: $jobCompanyHint\n";
    if ($hasTitleHint)   $prompt .= "- Established Job Title: $jobTitleHint\n";
    $prompt .= "If the hints above are specific, use them. If they are generic or if the posting clearly states something else, extract the correct values from the JOB POSTING text above (do not use narrative sentences).\n\n";
} else {
    $prompt .= "Extract the company name and job title from the JOB POSTING text above (do not rely on any external hint). Then produce the full 5-section analysis.\n\n";
}


try {
    if (GEMINI_API_KEY === 'your_gemini_api_key_here' || GEMINI_API_KEY === 'PLACEHOLDER' || empty(GEMINI_API_KEY)) {
        $mock = [
            'company_problems'    => "## Company Pain Points\n\n* **Scaling support infrastructure** — The posting emphasizes \"enterprise-grade\" and \"high-stakes accounts\" suggesting current support tooling is straining under growth.\n* **Knowledge silos** — Multiple references to documentation and playbooks indicate institutional knowledge loss risk.\n* **AI integration gap** — Explicit mention of AI-driven workflows suggests the team lacks someone who can bridge traditional IT and modern AI tooling.",
            'company_goals'       => "## Strategic Goals\n\n* **Build a world-class support org** within 12 months — the language around \"playbooks\" and \"escalation management\" suggests a team in formalization mode.\n* **Retain enterprise accounts** — High-touch support emphasis signals churn risk in the customer base is a live concern.\n* **Embed AI into support workflows** — This hire is likely the first or early mover on an internal AI-augmentation initiative.",
            'candidate_alignment' => "## Your Strategic Edge\n\n**Problem: Scaling support → Your answer:** 20+ years of enterprise infrastructure means you've seen these scaling inflection points before. When asked, lead with the time you triaged [X] escalations during [Y] incident.\n\n**Problem: AI integration gap → Your answer:** Your RAG/LLM experience is directly applicable. Frame it as: \"I've already built the workflows they're trying to implement.\"",
            'interview_prep'      => "## Interview Prep\n\n### 1. \"Tell me about a time you managed a critical escalation.\"\n**Why they'll ask:** They need proof you can stay calm under pressure.\n**Answer framework:** Use the [incident] → [your role] → [resolution] → [process improvement] arc.\n\n### 2. \"How do you approach building support playbooks?\"\n**Why they'll ask:** They're formalizing their team and need a builder, not just an executor.\n**Answer framework:** Describe your methodology: audit → identify gaps → draft → iterate with team → measure.",
            'cheat_sheet'         => "# Interview Cheat Sheet\n\n## Company Snapshot\nGrowing enterprise SaaS, scaling support org, early AI integration initiative.\n\n## Top 3 Things They Want\n* Proven escalation management under pressure\n* Someone who can build and document, not just react\n* A bridge between traditional IT and AI-augmented workflows\n\n## Your Power Talking Points\n1. 20+ years enterprise IT — I've seen this scale problem before\n2. AI workflow builder — I build what they're trying to figure out\n3. Escalation handler — I'm the person you call when it matters\n\n## Questions to Ask Them\n* \"What does a successful first 90 days look like for this role?\"\n* \"How is the team currently using AI in your support workflows?\"\n* \"What's the biggest gap you're hoping this hire fills?\"\n\n## Key Phrases to Drop\n`escalation management` · `root cause analysis` · `playbook development` · `AI-driven workflows` · `enterprise stakeholder management`"
        ];
        ob_end_clean();
        echo json_encode(['success' => true, 'analysis' => $mock]);
        exit;
    }

    if (!function_exists('curl_init')) {
        throw new Exception("cURL extension is not enabled on this server. Please enable it in your php.ini.");
    }
    
    $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/" . GEMINI_MODEL . ":generateContent?key=" . GEMINI_API_KEY);
    $payload = json_encode([
        'system_instruction' => ['parts' => [['text' => $systemInstruction]]],
        'contents'           => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
        'generationConfig'   => ['temperature' => 0.4, 'maxOutputTokens' => 8192]
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);

    $result   = curl_exec($ch);
    if (curl_errno($ch)) throw new Exception("cURL Error: " . curl_error($ch));
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) throw new Exception("Gemini API error (HTTP $httpCode).");

    $decoded  = json_decode($result, true);
    $rawText  = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if (empty($rawText)) throw new Exception("Empty response from Gemini.");

    // Strip markdown code fences if present
    $jsonText = preg_replace('/^```(?:json)?\s*/i', '', trim($rawText));
    $jsonText = preg_replace('/\s*```$/', '', $jsonText);

    // Extract first JSON object
    $start = strpos($jsonText, '{');
    if ($start !== false) {
        $depth = 0; $end = false;
        for ($i = $start; $i < strlen($jsonText); $i++) {
            if ($jsonText[$i] === '{') $depth++;
            if ($jsonText[$i] === '}') { $depth--; if ($depth === 0) { $end = $i; break; } }
        }
        if ($end !== false) $jsonText = substr($jsonText, $start, $end - $start + 1);
    }

    $analysis = json_decode($jsonText, true);
    if (!$analysis || !isset($analysis['company_problems'])) {
        throw new Exception("Failed to parse analysis JSON. Raw: " . substr($jsonText, 0, 300));
    }

    ob_end_clean();
    echo json_encode([
        'success'  => true,
        'analysis' => $analysis,
        // Surface extracted identity fields separately so the frontend can backfill finalResultObj
        'extracted_company' => $analysis['extracted_company'] ?? null,
        'extracted_role'    => $analysis['extracted_role']    ?? null,
    ]);

} catch (Exception $e) {
    if (ob_get_level() > 0) ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
