<?php
ob_start();
session_start();
require_once __DIR__ . '/../bootstrap.php';

// Log for debugging
$logFile = __DIR__ . '/../data/download_log.txt';
function logMsg($msg) {
    global $logFile;
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $msg . "\n", FILE_APPEND);
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

$token = $_GET['token'] ?? '';
if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
    http_response_code(400);
    exit('Invalid Token');
}

$dir = __DIR__ . '/../data/downloads/';
$metaPath = $dir . $token . '.meta';
$dataPath = $dir . $token . '.data';

if (!file_exists($metaPath) || !file_exists($dataPath)) {
    logMsg("Token $token: Not found");
    http_response_code(404);
    exit('File Expired or Not Found');
}

$meta = json_decode(file_get_contents($metaPath), true);
$filename = $meta['filename'] ?? 'download.bin';
$mime = $meta['mime'] ?? 'application/octet-stream';

logMsg("Token $token: Delivering $filename ($mime)");

ob_clean();

// Detect OS
$isWindows = stristr(PHP_OS, 'WIN');

// Ultra-robust headers
header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);

if ($isWindows) {
    // Windows is picky. Use the simplest, most compatible header first.
    // We avoid the filename* syntax which can sometimes confuse Windows-based browsers/servers
    header('Content-Disposition: attachment; filename="' . $filename . '"');
} else {
    // Linux/Unix can handle the full RFC 5987 spec
    header('Content-Disposition: attachment; filename="' . $filename . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
}

header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Length: ' . filesize($dataPath));
header('Connection: close');

readfile($dataPath);

// Cleanup after delivery
unlink($metaPath);
unlink($dataPath);

exit;
