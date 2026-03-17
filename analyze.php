<?php

require_once __DIR__ . '/config.php';

// Quick Autoloader just for this endpoint (if it's called directly)
spl_autoload_register(function ($class) {
    if (strpos($class, 'App\\') === 0) {
        $file = __DIR__ . '/src/' . substr($class, 4) . '.php';
        if (file_exists($file)) require $file;
    }
});

header('Content-Type: application/json');

use App\PdfParserWrapper;
use App\JobRepository;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$jobDescription = $input['job_description'] ?? '';
$resumeId = $input['resume_id'] ?? null;
$companyName = $input['company_name'] ?? 'Unknown Company';
$jobTitle = $input['job_title'] ?? 'Unknown Role';

if (empty($jobDescription) || empty($resumeId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Job Description and Resume ID are required.']);
    exit;
}

try {
    // 1. Fetch Resume path
    $db = \App\Database::getConnection();
    $stmt = $db->prepare("SELECT filename FROM resumes WHERE id = :id");
    $stmt->execute(['id' => $resumeId]);
    $resume = $stmt->fetch();

    if (!$resume) {
        throw new Exception("Resume not found.");
    }

    $resumePath = RESUME_PATH . '/' . $resume['filename'];

    // 2. Extract Text from PDF
    $pdfParser = new PdfParserWrapper();
    $resumeText = $pdfParser->extractText($resumePath);

    // 3. Prepare AI Prompt
    $prompt = "You are an expert technical recruiter analyzing a candidate's fit for a job.
    
Job Description:
$jobDescription

Candidate Resume:
$resumeText

Analyze the candidate's fit for the job. Provide:
1. A Fit Score from 1 to 10.
2. Key strengths matching the job.
3. Missing skills or areas of concern.
Return the response strictly as a JSON object with keys: 'score', 'strengths', 'weaknesses', 'summary'.";

    // 4. Call AI (OpenAI API example via cURL)
    if (AI_API_KEY === 'your_api_key_here') {
        // Mock response if API key is missing
        $aiResponse = [
            'score' => 7,
            'strengths' => ['Mock Strength 1', 'Mock Strength 2'],
            'weaknesses' => ['Mock Weakness 1'],
            'summary' => 'This is a mocked AI response because the API key is not set.'
        ];
        sleep(2); // Simulate network delay
    } else {
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        
        $postData = json_encode([
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a JSON-only API. Only output valid JSON.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.7
        ]);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . AI_API_KEY
        ]);

        $result = curl_exec($ch);
        
        if (curl_errno($ch)) {
            throw new Exception("cURL Error: " . curl_error($ch));
        }
        
        curl_close($ch);
        
        $decoded = json_decode($result, true);
        if (isset($decoded['choices'][0]['message']['content'])) {
            $aiResponse = json_decode($decoded['choices'][0]['message']['content'], true);
            if (!$aiResponse) {
                throw new Exception("Failed to parse AI JSON response.");
            }
        } else {
            throw new Exception("Invalid response from AI provider.");
        }
    }

    // 5. Save Job and Analysis
    $jobRepo = new JobRepository();
    $savedJobId = $jobRepo->saveJobAnalysis([
        'company_name' => $companyName,
        'job_title' => $jobTitle,
        'status' => 'Analyzed',
        'notes' => "AI Score: " . $aiResponse['score'] . "\nSummary: " . $aiResponse['summary'],
        'ai_analysis' => json_encode($aiResponse)
    ]);

    echo json_encode([
        'success' => true,
        'job_id' => $savedJobId,
        'analysis' => $aiResponse
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
