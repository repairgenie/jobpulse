<?php
/**
 * List available models from LM Studio or Ollama.
 * Returns model objects with {id, name, vision} instead of flat strings.
 *
 * GET /api/list_models.php?baseUrl=http://...&provider=lmstudio
 * GET /api/list_models.php?baseUrl=http://...&provider=ollama
 */

header('Content-Type: application/json');

$baseUrl  = trim($_GET['baseUrl']  ?? '');
$provider = trim($_GET['provider'] ?? '');

if (!in_array($provider, ['lmstudio', 'ollama'], true)) {
    echo json_encode([]);
    exit;
}

$baseUrl = rtrim($baseUrl, '/');

// ── LM Studio ─────────────────────────────────────────────────────────────
if ($provider === 'lmstudio') {

    $url = $baseUrl . '/v1/models';
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || $resp === false) {
        echo json_encode([]);
        exit;
    }

    $data = json_decode($resp, true);
    if (!isset($data['data']) || !is_array($data['data'])) {
        echo json_encode([]);
        exit;
    }

    // Known-vision models for LM Studio (OpenAI-compatible API — no capability field)
    // Update this map as you add vision-capable models to LM Studio.
    $visionModels = [
        'openai/gpt-oss-20b'         => true,
        'qwen/qwen3.5-9b'            => true,   // Qwen2-VL based
        'qwen/qwen2.5-7b-instruct'   => false,
        'qwen/qwen2.5-14b-instruct'  => false,
        'google/gemma-4-e4b'         => false,   // Gemma 3 — check if vision-enabled
        'llama-3.2-3b-instruct'      => false,
        'text-embedding-nomic-embed-text-v1.5' => false,
    ];

    $result = [];
    foreach ($data['data'] as $model) {
        $id = $model['id'] ?? '';
        if (empty($id)) continue;

        // Human-readable display name: strip org prefix, clean up
        $name = preg_replace('#^(openai/|qwen/|google/|anthropic/|meta/|)#', '', $id);
        $name = str_replace(['-instruct', '-chat', 'Instruct'], [' (Instruct)', ' (Chat)', ''], $name);

        $result[] = [
            'id'    => $id,
            'name'  => $name,
            'vision' => $visionModels[$id] ?? false,
        ];
    }

    // Sort by name
    usort($result, fn($a, $b) => strcmp($a['name'], $b['name']));
    echo json_encode($result);
    exit;
}

// ── Ollama ─────────────────────────────────────────────────────────────────
if ($provider === 'ollama') {

    // Step 1: get model list
    $url = $baseUrl . '/api/tags';
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || $resp === false) {
        echo json_encode([]);
        exit;
    }

    $tagsData = json_decode($resp, true);
    $modelNames = $tagsData['models'] ?? [];

    if (empty($modelNames)) {
        echo json_encode([]);
        exit;
    }

    // Collect all model names
    $names = array_map(fn($m) => $m['name'], $modelNames);

    // Step 2: check each model's capabilities via /api/show
    $visionCache = [];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    foreach ($names as $name) {
        $showUrl = $baseUrl . '/api/show';
        $postData = json_encode(['name' => $name]);

        curl_setopt($ch, CURLOPT_URL, $showUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $showResp = curl_exec($ch);
        $showCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($showCode === 200 && $showResp !== false) {
            $showData = json_decode($showResp, true);
            $details = $showData['details'] ?? [];
            // Ollama exposes vision capability in details -> supports_vision or ModelInfo
            $visionCache[$name] = $details['supports_vision'] ?? false;
        } else {
            $visionCache[$name] = false;
        }
    }
    curl_close($ch);

    // Build result
    $result = [];
    foreach ($modelNames as $m) {
        $id   = $m['name'];
        $name = $id;

        // Strip common prefixes for display
        $displayName = preg_replace('#^(llama|qwen|mistral|gemma):#', '', $id);

        $result[] = [
            'id'    => $id,
            'name'  => $displayName ?: $id,
            'vision' => $visionCache[$id] ?? false,
        ];
    }

    usort($result, fn($a, $b) => strcmp($a['name'], $b['name']));
    echo json_encode($result);
    exit;
}

// Fallback
echo json_encode([]);