<?php
ob_start();
session_start();
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

// Log for debugging
$logFile = __DIR__ . '/../data/download_log.txt';
function logMsg($msg) {
    global $logFile;
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $msg . "\n", FILE_APPEND);
}

if (!isset($_SESSION['user_id'])) {
    logMsg("Prepare Request: Unauthorized");
    http_response_code(401);
    exit(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

$input = json_decode(file_get_contents('php://input'), true);
$content = $input['content'] ?? '';
$type = $input['type'] ?? 'document';
$format = $input['format'] ?? 'pdf';
$company = $input['company'] ?? '';
$role    = $input['role']    ?? '';
$name    = $input['name']    ?? '';
$isHtml  = $input['is_html']  ?? false;
logMsg("Prepare Request: format=$format type=$type isHtml=" . ($isHtml ? 'true' : 'false'));

// Strip placeholder fallback strings so they don't appear in filenames
$placeholders = ['(Unknown Company)', '(Untitled)', 'Unknown Company', 'Untitled'];
if (in_array(trim($company), $placeholders, true)) $company = '';
if (in_array(trim($role),    $placeholders, true)) $role    = '';

// Build a clean filename prefix: company_role
function slugify(string $s): string {
    $s = trim($s);
    $s = preg_replace('/[^a-zA-Z0-9\s\-]/', '', $s); // strip special chars, keep hyphens
    $s = preg_replace('/\s+/', '_', $s);               // spaces → underscores
    return substr($s, 0, 50);                         // truncate to 50 chars
}
$companySlug = slugify($company) ?: 'Company';
$roleSlug    = slugify($role)    ?: 'Position';
$passedNameSlug = slugify($name);
$userFName   = slugify($_SESSION['first_name'] ?? '');
$userLName   = slugify($_SESSION['last_name']  ?? '');

$dateStamp   = date('Y-m-d') . '-' . time();

$prefixParts = [$companySlug, $roleSlug];

// Prioritize passed name (e.g. extracted from AI), then session names
if ($passedNameSlug) {
    $prefixParts[] = $passedNameSlug;
} else {
    if ($userFName) $prefixParts[] = $userFName;
    if ($userLName) $prefixParts[] = $userLName;
}

$prefix      = implode('_', $prefixParts);

if (empty(trim($content))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Content is required.']);
    exit;
}

$filename = '';
$mime = '';
$binaryData = '';

function parseMarkdownToHTML($text) {
    // Filter out empty bullets or lines that are just whitespace asterisks
    $lines = explode("\n", $text);
    $lines = array_filter($lines, function($line) {
        // Strip zero-width spaces and other invisible characters before trimming
        $cleaned = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $line);
        $trimmed = trim($cleaned);
        
        // Aggressive regex: if the line is JUST a bullet marker (standalone)
        if ($trimmed && preg_match('/^[*\-+•·\x{2022}\x{2023}\x{2043}\x{204C}\x{204D}\x{25E6}\x{25AA}\x{25AB}\x{25CF}\x{25CB}]\s*$/u', $trimmed)) {
            return false;
        }
        
        // KEEP empty lines so \n\n paragraph parsing still works
        return true;
    });
    $text = implode("\n", $lines);

    $text = htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8');
    // Headers first
    $text = preg_replace('/^### (.*?)$/m', '<h3>$1</h3>', $text);
    $text = preg_replace('/^## (.*?)$/m', '<h2>$1</h2>', $text);
    $text = preg_replace('/^# (.*?)$/m', '<h1>$1</h1>', $text);
    // Bold (must come before italic)
    $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
    // Bullet points — MUST come before italic so `* item` isn't eaten by italic regex
    $text = preg_replace('/^\* (.+)$/m', '<li>$1</li>', $text);
    // Numbered list items (e.g. "5. text", "12. text") — convert to bullets
    $text = preg_replace('/^\d+\.\s+(.+)$/m', '<li>$1</li>', $text);
    // Now wrap consecutive <li> runs in <ul> (no /s flag — match per line group)
    $text = preg_replace_callback('/(<li>.*?<\/li>\n?)+/', function($m) {
        return '<ul>' . $m[0] . '</ul>';
    }, $text);
    // Italic (after bullets so it doesn't eat bullet asterisks)
    $text = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $text);
    // Paragraphs
    $text = preg_replace('/\n\n+/', '</p><p>', $text);
    $text = '<p>' . $text . '</p>';
    $text = str_replace('<p></p>', '', $text);
    // Fix tags wrapped in spurious <p>
    $text = preg_replace('/<\/ul>\s*<ul>/', '', $text);
    $text = preg_replace('/<\/ul><\/p>/', '</ul>', $text);
    $text = preg_replace('/<p><ul>/', '<ul>', $text);
    $text = preg_replace('/<p><h([1-3])>/', '<h$1>', $text);
    $text = preg_replace('/<\/h([1-3])><\/p>/', '</h$1>', $text);

    // FINAL AGGRESSIVE FILTER: Remove any LI tags that are effectively empty (whitespace, NBSP, or nothing)
    $text = preg_replace('/<li>(\s|&nbsp;|&#160;|&#xa0;)*<\/li>/i', '', $text);
    // Remove empty UL tags that result from the above
    $text = preg_replace('/<ul>\s*<\/ul>/i', '', $text);

    return $text;
}

if ($format === 'pdf') {
    $filename = "{$prefix}__{$type}_{$dateStamp}.pdf";
    $mime = 'application/pdf';

    $htmlContent = $isHtml ? $content : parseMarkdownToHTML($content);
    $css = 'body{font-family:Arial,sans-serif;font-size:11pt;color:#333;line-height:1.6;}h1,h2,h3{page-break-after:avoid;}li{page-break-inside:avoid;}h1{font-size:20pt;color:#111;border-bottom:2px solid #6366f1;padding-bottom:5px;margin-bottom:5px;}h2{font-size:14pt;color:#222;margin-top:15px;}h3{font-size:12pt;color:#444;margin-top:10px;}p{margin-bottom:10px;}ul{margin:5px 0 10px 0;padding-left:20px;}li{margin-bottom:4px;}';

    try {
        $mpdf = new \Mpdf\Mpdf(['margin_left' => 20, 'margin_right' => 20, 'margin_top' => 20, 'margin_bottom' => 20]);
        $mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($htmlContent, \Mpdf\HTMLParserMode::HTML_BODY);
        $binaryData = $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
        logMsg("PDF generated: " . strlen($binaryData) . " bytes");
    } catch (Exception $e) {
        logMsg("PDF Error: " . $e->getMessage());
        http_response_code(500);
        exit(json_encode(['success' => false, 'error' => 'PDF Error: ' . $e->getMessage()]));
    }
} elseif ($format === 'combined') {
    $filename = "{$prefix}__application_{$dateStamp}.pdf";
    $mime = 'application/pdf';
    $coverLetter = $input['cover_letter'] ?? '';

    try {
        $mpdf = new \Mpdf\Mpdf(['margin_left' => 20, 'margin_right' => 20, 'margin_top' => 20, 'margin_bottom' => 20]);
        $css = 'body{font-family:Arial,sans-serif;font-size:11pt;color:#333;line-height:1.6;}h1,h2,h3{page-break-after:avoid;}li{page-break-inside:avoid;}h1{font-size:20pt;color:#111;border-bottom:2px solid #6366f1;padding-bottom:5px;margin-bottom:5px;}h2{font-size:14pt;color:#222;margin-top:15px;}h3{font-size:12pt;color:#444;margin-top:10px;}p{margin-bottom:10px;}ul{margin:5px 0 10px 0;padding-left:20px;}li{margin-bottom:4px;}';
        $mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);

        // Page 1+: Cover Letter
        $cvHtml = $isHtml ? $coverLetter : parseMarkdownToHTML($coverLetter);
        $mpdf->WriteHTML($cvHtml, \Mpdf\HTMLParserMode::HTML_BODY);

        // Page break then Resume
        $mpdf->AddPage();
        $resHtml = $isHtml ? $content : parseMarkdownToHTML($content);
        $mpdf->WriteHTML($resHtml, \Mpdf\HTMLParserMode::HTML_BODY);

        $binaryData = $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
        logMsg("Combined PDF generated: " . strlen($binaryData) . " bytes");
    } catch (Exception $e) {
        logMsg("Combined PDF Error: " . $e->getMessage());
        http_response_code(500);
        exit(json_encode(['success' => false, 'error' => 'Combined PDF Error: ' . $e->getMessage()]));
    }
} else {
    $filename = "{$prefix}__application_{$dateStamp}.txt";
    $mime = 'text/plain';
    
    if ($isHtml) {
        $binaryData = strip_tags(str_replace(['<br>', '</div>', '</p>', '</li>'], "\n", $content));
    } else {
        $binaryData = $content;
    }
    logMsg("TXT prepared: " . strlen($binaryData) . " bytes");
}

ob_end_clean();
header('Content-Type: application/json');
// Return base64-encoded content directly — no token file needed
echo json_encode([
    'success'  => true,
    'filename' => $filename,
    'mime'     => $mime,
    'data'     => base64_encode($binaryData),
]);
