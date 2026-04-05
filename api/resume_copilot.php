<?php
ob_start();
session_start();

require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$message = $input['message'] ?? '';
$history = $input['history'] ?? [];
$resumeContent = $input['resume_content'] ?? '';

if (empty($resumeContent)) {
    echo json_encode(['success' => false, 'error' => 'No resume content provided.']);
    exit;
}

$systemPrompt = "
You are a world-class executive resume writer and Career Coach Copilot.
You are embedded directly inside a rich text resume editor.
The user will chat with you to brainstorm variations, ask for reviews, or request rewrites of specific bullet points.

CRITICAL RESPONSIBILITIES AND CAPABILITIES:
1. Provide highly tactical, industry-standard advice.
2. If the user asks you to rewrite a section or improve its impact, you absolutely MUST output the suggested content in valid Markdown.
3. If they ask you to rewrite the entire resume or a large chunk, you can reply with the entire rewritten content. 

JSON PROTOCOL RESPONSE (VERY IMPORTANT):
Your output MUST be a valid JSON object matching exactly this schema:
{
    \"reply\": \"Your conversational response to the user. DO NOT put the rewritten resume in this field!\",
    \"new_text\": \"If the user wants their resume modified or rewritten, provide the ENTIRE updated text here in valid markdown. The UI will automatically parse this property and completely overwrite the active canvas. If you are just answering a question without making edits, set this to null.\"
}

Remember: 
- If you supply `new_text`, you must supply the ENTIRE updated resume or section, in valid markdown.
- NEVER PUT THE REWRITTEN RESUME IN THE `reply` FIELD!
- Do NOT output markdown codeblocks wrapping the JSON. Strictly return the raw JSON object string to avoid parse errors. 
";

$prompt = "
Here is the user's CURRENT RESUME CONTENT in Markdown:
```markdown
$resumeContent
```

User Request: \"$message\"

Return a valid JSON response.
";

$contents = [];
foreach ($history as $turn) {
    if (empty(trim($turn['parts'][0]['text']))) continue;
    $role = ($turn['role'] === 'user') ? 'user' : 'model';
    
    // Gemini rule: First turn must be user.
    if (empty($contents) && $role === 'model') {
        continue; 
    }
    
    // Gemini rule: No consecutive turns of the same role. Merge them.
    $lastIdx = count($contents) - 1;
    if ($lastIdx >= 0 && $contents[$lastIdx]['role'] === $role) {
        $contents[$lastIdx]['parts'][0]['text'] .= "\n\n" . $turn['parts'][0]['text'];
    } else {
        $contents[] = ['role' => $role, 'parts' => [['text' => $turn['parts'][0]['text']]]];
    }
}
$contents[] = ['role' => 'user', 'parts' => [['text' => $prompt]]];


if (GEMINI_API_KEY === 'your_gemini_api_key_here' || empty(GEMINI_API_KEY)) {
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'reply'   => "This is a mock response because the Gemini API key is configured incorrectly. Please add it to config.php.",
        'new_text' => null
    ]);
    exit;
}

try {
    $payload = json_encode([
        'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
        'contents'           => $contents,
        'generationConfig'   => [
            'temperature' => 0.6, 
            'maxOutputTokens' => 2048
        ]
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
    $rawResponse   = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;
    if (empty($rawResponse)) throw new Exception("Empty response from Gemini.");
    
    // Strip codeblock syntax if the AI mistakenly wraps it
    $cleanJson = preg_replace('/```json\s*/i', '', $rawResponse);
    $cleanJson = preg_replace('/```\s*/', '', $cleanJson);

    $parsed = json_decode(trim($cleanJson), true);

    ob_end_clean();

    if (json_last_error() === JSON_ERROR_NONE && isset($parsed['reply'])) {
        echo json_encode([
            'success' => true, 
            'reply' => $parsed['reply'],
            'new_text' => $parsed['new_text'] ?? null
        ]);
    } else {
        // Fallback if parsing fails
        echo json_encode([
            'success' => true,
            'reply' => "I had trouble formatting my response. Here is what I wanted to say:\n\n" . $rawResponse, 
            'new_text' => null
        ]);
    }

} catch (Exception $e) {
    if (ob_get_level() > 0) ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
