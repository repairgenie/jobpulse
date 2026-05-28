<?php
namespace App;

/**
 * LLMProvider — Multi-provider LLM adapter
 * Supports: Gemini, LM Studio (local GPT-OSS-20b), Ollama, OpenAI, MiniMax
 * All API callers should use this instead of raw curl to Google Gemini.
 */
class LLMProvider {

    /** @var string Current provider key */
    private $provider;

    /** @var string|null Active API key or null for local providers */
    private $apiKey;

    /** @var string|null Custom base URL (e.g. Ollama, OpenAI-compatible proxy) */
    private $baseUrl;

    /** @var string Model identifier */
    private $model;

    public function __construct() {
        $this->provider = LLM_PROVIDER;
        $this->apiKey  = LLM_API_KEY;
        $this->baseUrl = defined('LLM_BASE_URL') ? LLM_BASE_URL : null;
        $this->model   = LLM_MODEL;
    }

    /**
     * Free-form chat (used by ask_ai / conversational AI)
     * @param string      $systemInstruction
     * @param array      $messages          [{role:'user'|'assistant', content:'...' }, ...]
     * @param string     $responseType      'text' or 'json'
     * @param float|null $temperature
     * @return string response text
     */
    public function chat(string $systemInstruction, array $messages, string $responseType = 'text', ?float $temperature = null): string {
        switch ($this->provider) {
            case 'lmstudio':
                return $this->lmstudioChat($systemInstruction, $messages, $temperature ?? 0.5);
            case 'ollama':
                return $this->ollamaChat($systemInstruction, $messages, $temperature ?? 0.5);
            case 'openai':
            case 'openai-oauth':
                return $this->openaiChat($systemInstruction, $messages, $temperature ?? 0.5, $responseType);
            case 'minimax':
                return $this->minimaxChat($systemInstruction, $messages, $temperature ?? 0.5, $responseType);
            case 'gemini':
            default:
                return $this->geminiChat($systemInstruction, $messages, $temperature ?? 0.5);
        }
    }

    // ── LM Studio chat (OpenAI-compatible endpoint) ───────────────────────────

    private function lmstudioChat(string $systemInstruction, array $messages, float $temperature): string {
        $url = rtrim($this->baseUrl ?: 'http://192.168.8.147:1234', '/') . '/v1/chat/completions';

        $allMessages = [];
        if ($systemInstruction) {
            $allMessages[] = ['role' => 'system', 'content' => $systemInstruction];
        }
        foreach ($messages as $m) {
            $allMessages[] = ['role' => $m['role'], 'content' => $m['content']];
        }

        $body = [
            'model'       => $this->model ?: 'llama-3.2-3b-instruct',
            'messages'    => $allMessages,
            'temperature' => $temperature,
            'stream'      => false,
            'max_tokens'  => 4096,
            'num_thread'  => 4,
        ];

        $headers = ['Content-Type: application/json'];
        if ($this->apiKey) {
$headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);

        $result = curl_exec($ch);
        if (curl_errno($ch)) throw new \Exception('LM Studio cURL error: ' . curl_error($ch));
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) throw new \Exception("LM Studio error (HTTP $httpCode): $result");

        $decoded = json_decode($result, true);
        $content = $decoded['choices'][0]['message']['content'] ?? '';
        if (!$content) throw new \Exception('LM Studio returned no content');
        return $content;
    }

    // ── Ollama chat ───────────────────────────────────────────────────────

    private function ollamaChat(string $systemInstruction, array $messages, float $temperature): string {
        $url = rtrim($this->baseUrl ?: 'http://localhost:11434', '/') . '/api/chat';

        $allMessages = [];
        if ($systemInstruction) {
            $allMessages[] = ['role' => 'system', 'content' => $systemInstruction];
        }
        foreach ($messages as $m) {
            $allMessages[] = ['role' => $m['role'], 'content' => $m['content']];
        }

        $postData = json_encode([
            'model'       => $this->model ?: 'llama3.2',
            'messages'    => $allMessages,
            'temperature' => $temperature,
            'stream'      => false,
            'options'     => [
                'num_predict' => 2048, // leave headroom for 4096 context window models
            ],
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $result = curl_exec($ch);
        if (curl_errno($ch)) throw new \Exception('Ollama cURL error: ' . curl_error($ch));
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) throw new \Exception("Ollama error (HTTP $httpCode): $result");

        $decoded = json_decode($result, true);
        return $decoded['message']['content'] ?? '';
    }

    // ── OpenAI / OpenAI OAuth chat ────────────────────────────────────────

    private function openaiChat(string $systemInstruction, array $messages, float $temperature, string $responseType): string {
        $url = rtrim($this->baseUrl ?: 'https://api.openai.com/v1', '/') . '/chat/completions';

        $allMessages = [];
        if ($systemInstruction) {
            $allMessages[] = ['role' => 'system', 'content' => $systemInstruction];
        }
        foreach ($messages as $m) {
            $allMessages[] = ['role' => $m['role'], 'content' => $m['content']];
        }

        $body = [
            'model'       => $this->model ?: 'gpt-4o',
            'messages'    => $allMessages,
            'temperature' => $temperature,
        ];
        if ($responseType === 'json') {
            $body['response_format'] = ['type' => 'json_object'];
        }

        $headers = ['Content-Type: application/json'];
        if ($this->apiKey) {
$headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        if (curl_errno($ch)) throw new \Exception('OpenAI cURL error: ' . curl_error($ch));
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) throw new \Exception("OpenAI error (HTTP $httpCode): $result");

        $decoded = json_decode($result, true);
        return $decoded['choices'][0]['message']['content'] ?? '';
    }

    // ── MiniMax chat ───────────────────────────────────────────────────────

    private function minimaxChat(string $systemInstruction, array $messages, float $temperature, string $responseType): string {
        $url = 'https://api.minimax.chat/v1/chat/completions';

        $allMessages = [];
        if ($systemInstruction) {
            $allMessages[] = ['role' => 'system', 'content' => $systemInstruction];
        }
        foreach ($messages as $m) {
            $allMessages[] = ['role' => $m['role'], 'content' => $m['content']];
        }

        $body = [
            'model'       => $this->model ?: 'abab6.5s-chat',
            'messages'    => $allMessages,
            'temperature' => $temperature,
        ];
        if ($responseType === 'json') {
            $body['response_format'] = ['type' => 'json_object'];
        }

        $headers = ['Content-Type: application/json'];
        if ($this->apiKey) {
$headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        if (curl_errno($ch)) throw new \Exception('MiniMax cURL error: ' . curl_error($ch));
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) throw new \Exception("MiniMax error (HTTP $httpCode): $result");

        $decoded = json_decode($result, true);
        return $decoded['choices'][0]['message']['content'] ?? '';
    }

    // ── Gemini chat ──────────────────────────────────────────────────────

    private function geminiChat(string $systemInstruction, array $messages, float $temperature): string {
        $apiKey = $this->apiKey;
        if (!$apiKey) throw new \Exception('GEMINI_API_KEY is not configured');

        $modelName = $this->model ?: 'gemini-3.1-flash-lite-preview';
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key={$apiKey}";

        $contents = [];
        foreach ($messages as $m) {
            $role = ($m['role'] === 'user') ? 'user' : 'model';
            $contents[] = ['role' => $role, 'parts' => [['text' => $m['content']]]];
        }

        $postData = json_encode([
            'system_instruction' => ['parts' => [['text' => $systemInstruction]]],
            'contents'           => $contents,
            'generationConfig'   => [
                'temperature' => $temperature,
                'maxOutputTokens' => 2048,
            ]
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $result = curl_exec($ch);
        if (curl_errno($ch)) throw new \Exception('Gemini cURL error: ' . curl_error($ch));
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) throw new \Exception("Gemini API error (HTTP $httpCode): $result");

        $decoded = json_decode($result, true);
        return $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
    }

    /**
     * Structured JSON generation (used by optimize_resume, analyze, etc.)
     */
    public function request(string $systemInstruction, string $prompt, float $temperature = 0.5, string $responseMimeType = 'application/json'): array {
        switch ($this->provider) {
            case 'lmstudio':
                return $this->lmstudioRequest($systemInstruction, $prompt, $temperature, $responseMimeType);
            case 'ollama':
                return $this->ollamaRequest($systemInstruction, $prompt, $temperature, $responseMimeType);
            case 'openai':
            case 'openai-oauth':
                return $this->openaiRequest($systemInstruction, $prompt, $temperature, $responseMimeType);
            case 'minimax':
                return $this->minimaxRequest($systemInstruction, $prompt, $temperature, $responseMimeType);
            case 'gemini':
            default:
                return $this->geminiRequest($systemInstruction, $prompt, $temperature, $responseMimeType);
        }
    }

    // ── LM Studio (OpenAI-compatible endpoint) ───────────────────────────────

    private function lmstudioRequest(string $systemInstruction, string $prompt, float $temperature, string $responseMimeType): array {
        $url = rtrim($this->baseUrl ?: 'http://192.168.8.147:1234', '/') . '/v1/chat/completions';

        $messages = [];
        if ($systemInstruction) {
            $messages[] = ['role' => 'system', 'content' => $systemInstruction];
        }
        $messages[] = ['role' => 'user', 'content' => $prompt];

        // No stop token — let model finish the JSON naturally.
        // <|channel|> causes truncation with some models, producing broken JSON.
        $body = [
            'model'       => $this->model ?: 'llama-3.2-3b-instruct',
            'messages'    => $messages,
            'temperature' => $temperature,
            'max_tokens'  => 16384,
        ];

        $headers = ['Content-Type: application/json'];
        if ($this->apiKey) {
$headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }

        $postData = json_encode($body);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        // ── First request ─────────────────────────────────────────────────────
        $result = curl_exec($ch);
        if (curl_errno($ch)) throw new \Exception('LM Studio cURL error: ' . curl_error($ch));
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode >= 400) throw new \Exception("LM Studio error (HTTP $httpCode): $result");
        $decoded = json_decode($result, true);
        $content = $decoded['choices'][0]['message']['content'] ?? null;
        if (!$content) throw new \Exception('LM Studio returned no content');
        $finishReason = $decoded['choices'][0]['finish_reason'] ?? null;

        // ── Strip response artifacts before any parsing or retry logic ──────────
        $content = trim($content);
        $content = preg_replace('/^```json\s*/im', '', $content);
        $content = preg_replace('/^```\s*/im', '', $content);
        $content = preg_replace('/\s*```$/im', '', $content);
        $content = preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', '', $content);
        $content = trim($content);
        $jsonStart = strpos($content, '{');
        if ($jsonStart !== false && $jsonStart > 0) {
            $content = substr($content, $jsonStart);
        }

        // ── Truncation check BEFORE json_decode ─────────────────────────────────
        // Detect truncation even when finish_reason='stop' (model self-terminates
        // mid-output). Catches: finish_reason=length, [truncated] marker, or
        // content ending mid-word (no terminal punctuation).
        $hasTruncationMarker = (strpos($content, '[truncated]') !== false);
        $endsWithBrace = (substr(trim($content), -1) === '}');
        $looksComplete = $endsWithBrace && !$hasTruncationMarker
            && !preg_match('/[a-z0-9]{15}$/i', trim($content));

        // ── Retry loop: runs if truncated on first attempt ────────────────────
        $maxTokensAttempt = 16384;
        $retryAttempts = 0;
        while (!$looksComplete && $retryAttempts < 3) {
            $retryAttempts++;
            $maxTokensAttempt = $maxTokensAttempt * 2; // 16384 → 32768 → 65536
            $body['max_tokens'] = $maxTokensAttempt;
            $postData = json_encode($body);
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);
            $result = curl_exec($ch);
            if (curl_errno($ch)) throw new \Exception('LM Studio cURL error: ' . curl_error($ch));
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpCode >= 400) throw new \Exception("LM Studio error (HTTP $httpCode): $result");
            $decoded = json_decode($result, true);
            $content = $decoded['choices'][0]['message']['content'] ?? null;
            if (!$content) throw new \Exception('LM Studio returned no content on retry');
            $finishReason = $decoded['choices'][0]['finish_reason'] ?? null;

            // Strip artifacts
            $content = trim($content);
            $content = preg_replace('/^```json\s*/im', '', $content);
            $content = preg_replace('/^```\s*/im', '', $content);
            $content = preg_replace('/\s*```$/im', '', $content);
            $content = preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', '', $content);
            $content = trim($content);
            $jsonStart = strpos($content, '{');
            if ($jsonStart !== false && $jsonStart > 0) {
                $content = substr($content, $jsonStart);
            }

            $hasTruncationMarker = (strpos($content, '[truncated]') !== false);
            $endsWithBrace = (substr(trim($content), -1) === '}');
            $looksComplete = $endsWithBrace && !$hasTruncationMarker
                && !preg_match('/[a-z0-9]{15}$/i', trim($content));
        }

        // ── Log the FINAL raw response (after all retries) ───────────────────
        // Must log AFTER the retry loop so response_raw reflects what was actually parsed.
        $logEntry['timestamp'] = date('Y-m-d\TH:i:s');
        $logEntry['provider'] = 'lmstudio';
        $logEntry['model'] = $this->model ?: 'llama-3.2-3b-instruct';
        $logEntry['max_tokens_requested'] = $body['max_tokens'];
        $logEntry['finish_reason'] = $finishReason ?? null;
        $logEntry['retry_attempts'] = $retryAttempts;
        $logEntry['response_raw'] = $content; // this is the final content after retries
        $logEntry['response_raw_len'] = strlen($content);
        file_put_contents(LLM_LOG_FILE, json_encode($logEntry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n----\n", FILE_APPEND);

        // ── Parse JSON ──────────────────────────────────────────────────────────
        $aiResponse = json_decode($content, true);
        if (!$aiResponse) {
            $sample = substr(preg_replace('/[^\x20-\x7E\n\r\t]/', '', $content), 0, 300);
            throw new \Exception('LM Studio JSON parse error: ' . json_last_error_msg() . ' | Content: ' . $sample);
        }

        // ── Log parsed field sizes for quick triage ────────────────────────────
        $logEntryFinal = [
            'timestamp' => date('Y-m-d\TH:i:s'),
            'provider' => 'lmstudio',
            'model' => $this->model ?: 'llama-3.2-3b-instruct',
            'note' => 'final_parsed_response',
            'retry_attempts' => $retryAttempts,
            'response_parsed_candidates' => [
                'optimized_resume_text_len' => isset($aiResponse['optimized_resume_text']) ? strlen($aiResponse['optimized_resume_text']) : 0,
                'optimized_resume_text_first200' => isset($aiResponse['optimized_resume_text']) ? substr($aiResponse['optimized_resume_text'], 0, 200) : null,
                'optimized_resume_text_last200' => isset($aiResponse['optimized_resume_text']) ? substr($aiResponse['optimized_resume_text'], -200) : null,
                'cover_letter_len' => isset($aiResponse['cover_letter']) ? strlen($aiResponse['cover_letter']) : 0,
                'cover_letter_first200' => isset($aiResponse['cover_letter']) ? substr($aiResponse['cover_letter'], 0, 200) : null,
            ],
        ];
        file_put_contents(LLM_LOG_FILE, json_encode($logEntryFinal, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n====\n", FILE_APPEND);

        return $aiResponse;
    }

    // ── Ollama (local, no auth) ─────────────────────────────────────────────

    private function ollamaRequest(string $systemInstruction, string $prompt, float $temperature, string $responseMimeType): array {
        $url = rtrim($this->baseUrl ?: 'http://localhost:11434', '/') . '/api/chat';

        $messages = [];
        if ($systemInstruction) {
            $messages[] = ['role' => 'system', 'content' => $systemInstruction];
        }
        $messages[] = ['role' => 'user', 'content' => $prompt];

$postData = json_encode([
                'model'       => $this->model ?: 'llama3.2',
                'messages'    => $messages,
                'temperature' => $temperature,
                'format'      => 'json',
                'stream'      => false,
                'options'     => [
                    'num_predict' => 2048, // leave headroom for 4096 context window models
                ],
            ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $result = curl_exec($ch);
        if (curl_errno($ch)) throw new \Exception('Ollama cURL error: ' . curl_error($ch));
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            throw new \Exception("Ollama error (HTTP $httpCode): $result");
        }

        $decoded = json_decode($result, true);
        $content = $decoded['message']['content'] ?? null;
        if (!$content) throw new \Exception('Ollama returned no content');

        $aiResponse = json_decode($content, true);
        if (!$aiResponse) throw new \Exception('Ollama JSON parse error: ' . json_last_error_msg());
        return $aiResponse;
    }

    // ── OpenAI / OpenAI OAuth ───────────────────────────────────────────────

    private function openaiRequest(string $systemInstruction, string $prompt, float $temperature, string $responseMimeType): array {
        $url = rtrim($this->baseUrl ?: 'https://api.openai.com/v1', '/') . '/chat/completions';

        $messages = [];
        if ($systemInstruction) {
            $messages[] = ['role' => 'system', 'content' => $systemInstruction];
        }
        $messages[] = ['role' => 'user', 'content' => $prompt];

        $body = [
            'model'       => $this->model ?: 'gpt-4o',
            'messages'    => $messages,
            'temperature' => $temperature,
            'response_format' => ['type' => 'json_object'],
        ];

        $headers = ['Content-Type: application/json'];
        if ($this->apiKey) {
$headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }

        $postData = json_encode($body);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        if (curl_errno($ch)) throw new \Exception('OpenAI cURL error: ' . curl_error($ch));
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            throw new \Exception("OpenAI error (HTTP $httpCode): $result");
        }

        $decoded = json_decode($result, true);
        $content = $decoded['choices'][0]['message']['content'] ?? null;
        if (!$content) throw new \Exception('OpenAI returned no content');

        $aiResponse = json_decode($content, true);
        if (!$aiResponse) throw new \Exception('OpenAI JSON parse error: ' . json_last_error_msg());
        return $aiResponse;
    }

    // ── MiniMax ──────────────────────────────────────────────────────────────

    private function minimaxRequest(string $systemInstruction, string $prompt, float $temperature, string $responseMimeType): array {
        // MiniMax GLMA API: chat completions endpoint
        $url = 'https://api.minimax.chat/v1/chat/completions';

        $messages = [];
        if ($systemInstruction) {
            $messages[] = ['role' => 'system', 'content' => $systemInstruction];
        }
        $messages[] = ['role' => 'user', 'content' => $prompt];

        $body = [
            'model'       => $this->model ?: 'abab6.5s-chat',
            'messages'    => $messages,
            'temperature' => $temperature,
            'response_format' => ['type' => 'json_object'],
        ];

        $headers = ['Content-Type: application/json'];
        if ($this->apiKey) {
$headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }

        $postData = json_encode($body);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        if (curl_errno($ch)) throw new \Exception('MiniMax cURL error: ' . curl_error($ch));
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            throw new \Exception("MiniMax error (HTTP $httpCode): $result");
        }

        $decoded = json_decode($result, true);
        $content = $decoded['choices'][0]['message']['content'] ?? null;
        if (!$content) throw new \Exception('MiniMax returned no content');

        $aiResponse = json_decode($content, true);
        if (!$aiResponse) throw new \Exception('MiniMax JSON parse error: ' . json_last_error_msg());
        return $aiResponse;
    }

    // ── Google Gemini (default) ─────────────────────────────────────────────

    private function geminiRequest(string $systemInstruction, string $prompt, float $temperature, string $responseMimeType): array {
        $apiKey = $this->apiKey;
        if (!$apiKey) throw new \Exception('GEMINI_API_KEY is not configured');

        $modelName = $this->model ?: 'gemini-3.1-flash-lite-preview';
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key={$apiKey}";

        $postData = json_encode([
            'system_instruction' => [
                'parts' => [['text' => $systemInstruction]]
            ],
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [['text' => $prompt]]
                ]
            ],
            'generationConfig' => [
                'temperature' => $temperature,
                'responseMimeType' => $responseMimeType,
            ]
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $result = curl_exec($ch);
        if (curl_errno($ch)) throw new \Exception('Gemini cURL error: ' . curl_error($ch));
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            throw new \Exception("Gemini API error (HTTP $httpCode): $result");
        }

        $decoded = json_decode($result, true);
        $jsonText = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (!$jsonText) throw new \Exception('Gemini returned no content');

        // Normalize encoding
        $jsonText = mb_convert_encoding($jsonText, 'UTF-8', 'UTF-8');

        // Balanced Brace Parser — extract first complete JSON object
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

        $aiResponse = json_decode($jsonText, true);
        if (!$aiResponse) {
            // Aggressive fix for unescaped newlines within JSON strings
            $cleaned = preg_replace_callback('/\"(.*?)\"\s*(:|\]|\}|,)/s', function($m) {
                $inner = str_replace(["\n", "\r"], ["\\n", ""], $m[1]);
                return '"' . $inner . '"' . $m[2];
            }, $jsonText);
            $aiResponse = json_decode($cleaned, true);
        }
        if (!$aiResponse) {
            throw new \Exception('Gemini JSON parse error: ' . json_last_error_msg());
        }
        return $aiResponse;
    }

    // ══════════════════════════════════════════════════════════════════════
    // VISION CHAT
    // Sends a text + images prompt to a vision-capable model.
    // Dispatches to the correct provider format automatically.
    //
    // @param string $prompt          Text instruction
    // @param array  $base64Images    Array of base64-encoded PNG image strings (no prefix)
    // @param string $modelOverride   Override the configured model name
    // @return string                 Markdown/text response from the model
    // ══════════════════════════════════════════════════════════════════════

    public function visionChat(string $prompt, array $base64Images, string $modelOverride = ''): string {
        $model = $modelOverride ?: $this->model;

        // Detect provider from model name or configured provider
        if (strpos($model, 'gemini') !== false) {
            return $this->geminiVision($prompt, $base64Images, $model);
        }
        if (strpos($model, 'gpt-oss-20b') !== false || $this->provider === 'lmstudio') {
            return $this->lmstudioVision($prompt, $base64Images, $model);
        }
        if ($this->provider === 'ollama') {
            return $this->ollamaVision($prompt, $base64Images, $model);
        }
        if ($this->provider === 'openai' || $this->provider === 'openai-oauth') {
            return $this->openaiVision($prompt, $base64Images, $model);
        }

        // Default: try OpenAI-compatible vision format
        return $this->lmstudioVision($prompt, $base64Images, $model);
    }

    // ── LM Studio / OpenAI-compatible vision ───────────────────────────────
    // Uses the OpenAI vision message format (content array with image_url parts)

    private function lmstudioVision(string $prompt, array $base64Images, string $model): string {
        $url = rtrim($this->baseUrl ?: 'http://192.168.8.147:1234', '/') . '/v1/chat/completions';

        $content = [['type' => 'text', 'text' => $prompt]];
        foreach ($base64Images as $img) {
            $content[] = [
                'type' => 'image_url',
                'image_url' => ['url' => 'data:image/png;base64,' . $img],
            ];
        }

        $body = [
            'model'       => $model ?: 'openai/gpt-oss-20b',
            'messages'    => [['role' => 'user', 'content' => $content]],
            'temperature' => 0.4,
            'stream'      => false,
            'max_tokens'  => 2048, // leave headroom for 4096 context window models
        ];

        $headers = ['Content-Type: application/json'];
        if ($this->apiKey) {
$headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);

        $result = curl_exec($ch);
        if (curl_errno($ch)) throw new \Exception('LM Studio vision cURL error: ' . curl_error($ch));
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) throw new \Exception("LM Studio vision error (HTTP $httpCode): $result");

        $decoded = json_decode($result, true);
        return $decoded['choices'][0]['message']['content'] ?? '';
    }

    // ── Ollama vision ───────────────────────────────────────────────────────
    // Ollama uses the same /api/chat endpoint with a messages array;
    // images are passed as base64 data URIs inside message content.

    private function ollamaVision(string $prompt, array $base64Images, string $model): string {
        $url = rtrim($this->baseUrl ?: 'http://localhost:11434', '/') . '/api/chat';

        $contentParts = [['text' => $prompt]];
        foreach ($base64Images as $img) {
            $contentParts[] = [
                'type'     => 'image',
                'image_url' => 'data:image/png;base64,' . $img,
            ];
        }

        $postData = json_encode([
            'model'    => $model ?: 'llava',
            'messages' => [['role' => 'user', 'content' => $contentParts]],
            'stream'   => false,
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);

        $result = curl_exec($ch);
        if (curl_errno($ch)) throw new \Exception('Ollama vision cURL error: ' . curl_error($ch));
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) throw new \Exception("Ollama vision error (HTTP $httpCode): $result");

        $decoded = json_decode($result, true);
        return $decoded['message']['content'] ?? '';
    }

    // ── OpenAI vision ───────────────────────────────────────────────────────
    // Standard OpenAI GPT-4 Vision format (identical to LM Studio here,
    // but uses the official OpenAI API endpoint + API key).

    private function openaiVision(string $prompt, array $base64Images, string $model): string {
        $url = 'https://api.openai.com/v1/chat/completions';

        $content = [['type' => 'text', 'text' => $prompt]];
        foreach ($base64Images as $img) {
            $content[] = [
                'type' => 'image_url',
                'image_url' => ['url' => 'data:image/png;base64,' . $img],
            ];
        }

        $body = [
            'model'       => $model ?: 'gpt-4o',
            'messages'    => [['role' => 'user', 'content' => $content]],
            'temperature' => 0.4,
        ];

        $headers = [
            'Content-Type: application/json',
'Authorization: Bearer ' . ($this->apiKey ?: ''),
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);

        $result = curl_exec($ch);
        if (curl_errno($ch)) throw new \Exception('OpenAI vision cURL error: ' . curl_error($ch));
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) throw new \Exception("OpenAI vision error (HTTP $httpCode): $result");

        $decoded = json_decode($result, true);
        return $decoded['choices'][0]['message']['content'] ?? '';
    }

    // ── Google Gemini vision ─────────────────────────────────────────────────
    // Gemini uses inline ImageParts with base64-encoded data (no data URL prefix).

    private function geminiVision(string $prompt, array $base64Images, string $model): string {
        $apiKey = $this->apiKey;
        if (!$apiKey) throw new \Exception('GEMINI_API_KEY is not configured');

        $modelName = preg_replace('#^gemini[_-]?#i', '', $model);
        if (empty($modelName)) $modelName = 'gemini-1.5-flash';
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key={$apiKey}";

        $parts = [['text' => $prompt]];
        foreach ($base64Images as $img) {
            $parts[] = [
                'inlineData' => [
                    'mimeType' => 'image/png',
                    'data'      => $img,
                ],
            ];
        }

        $postData = json_encode([
            'contents' => [['parts' => $parts]],
            'generationConfig' => [
                'temperature'    => 0.4,
                'maxOutputTokens' => 2048,
            ],
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);

        $result = curl_exec($ch);
        if (curl_errno($ch)) throw new \Exception('Gemini vision cURL error: ' . curl_error($ch));
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) throw new \Exception("Gemini vision error (HTTP $httpCode): $result");

        $decoded = json_decode($result, true);
        return $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
    }

    // ══════════════════════════════════════════════════════════════════════
    // STATIC VISION CAPABILITY CHECK
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Check if a given model supports vision (multimodal image input).
     * Queries the provider's /v1/models or /api/tags endpoint directly.
     *
     * @param string $modelName   Model identifier (e.g. 'openai/gpt-oss-20b')
     * @param string $baseUrl     Provider base URL (e.g. 'http://192.168.8.147:1234')
     * @return bool                True if vision is supported
     */
    public static function supportsVision(string $modelName, string $baseUrl = ''): bool {
        $baseUrl = rtrim($baseUrl ?: LLM_BASE_URL, '/');

        // Detect provider from model name or configured default
        if (strpos($modelName, 'gemini') !== false || strpos($baseUrl, 'generativelanguage') !== false) {
            // Gemini is always multimodal for supported models
            return true;
        }

        // LM Studio / OpenAI-compatible: check against known vision model map
        if (empty($baseUrl) || strpos($baseUrl, '192.168') !== false || strpos($baseUrl, 'localhost') !== false) {
            $visionModels = [
                'openai/gpt-oss-20b'       => false, // text-only, reasoning-heavy
                'qwen/qwen3.5-9b'          => false, // text-only, reasoning-heavy
                'qwen/qwen2.5-vl-7b'       => true,
                'qwen/qwen2.5-vl-14b'      => true,
                'llava'                    => true,
                'llava-llama3'             => true,
                'google/gemma-4-e4b'       => false, // text-only
                'llama-3.2-3b-instruct'   => false, // text-only
            ];
            if (isset($visionModels[$modelName])) return true;

            // Try to probe the model's details via OpenAI-compatible endpoint
            $ch = curl_init($baseUrl . '/v1/models/' . urlencode($modelName));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code === 200) {
                $data = json_decode($resp, true);
                // Some providers expose capabilities in the model object
                if (!empty($data['capabilities']['vision'])) return true;
            }

            // Fallback: models with "vl" (vision-language) in name are vision-capable
            return strpos($modelName, 'vl') !== false
                || strpos($modelName, 'vision') !== false
                || strpos($modelName, 'llava') !== false;
        }

        // Ollama: check via /api/show
        if (strpos($baseUrl, '11434') !== false || strpos($baseUrl, 'ollama') !== false) {
            $ch = curl_init($baseUrl . '/api/show');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['name' => $modelName]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);

            $resp = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $data = json_decode($resp, true);
                if (isset($data['details']['supports_vision']) && $data['details']['supports_vision'] === true) {
                    return true;
                }
            }

            // llava and vision-named models are vision-capable in Ollama
            return strpos($modelName, 'llava') !== false
                || strpos($modelName, 'vision') !== false;
        }

        return false;
    }
}