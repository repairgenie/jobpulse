<?php
session_start();

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../src/AiClient.php';

use App\AiClient;

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
    \"reply\": \"Your conversational response, encouragement, and explanation to the user. (Markdown supported)\",
    \"new_text\": \"If and ONLY IF the user asked you to rewrite/reformat text and you feel the user wants their resume directly modified by you, provide the complete, updated text here. The UI will automatically completely overwrite their editor with this payload. If you are just answering a question, set this to null or omit it.\"
}

Remember: 
- If you supply `new_text`, you must supply the ENTIRE updated resume or section, in valid markdown, because it will replace their editor's contents.
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

try {
    $aiClient = new AiClient();
    $rawResponse = $aiClient->generateContent($prompt, $systemPrompt, $history);
    
    // Strip codeblock syntax if the AI mistakenly wrapps it
    $cleanJson = preg_replace('/```json\s*/', '', $rawResponse);
    $cleanJson = preg_replace('/```\s*/', '', $cleanJson);

    $parsed = json_decode(trim($cleanJson), true);

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
            'reply' => $rawResponse, // Send the raw response in the chat
            'new_text' => null
        ]);
    }

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
