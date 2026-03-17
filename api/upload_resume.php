<?php
session_start();

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../src/ResumeManager.php';
require_once __DIR__ . '/../src/PdfParser.php';

use App\ResumeManager;
use App\PdfParserWrapper;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
    exit;
}

try {
    if (!isset($_FILES['resume']) || $_FILES['resume']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Please upload a valid resume PDF.");
    }

    $file = $_FILES['resume'];
    $category = $_POST['category'] ?? 'General';
    $category = preg_replace('/[^a-zA-Z0-9\s]/', '', $category); // Sanitize
    if (empty(trim($category))) $category = 'General';
    
    // Security check: Only allow PDFs
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if ($mimeType !== 'application/pdf') {
        throw new Exception("Invalid file type. Only PDF is allowed.");
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    if (strtolower($ext) !== 'pdf') {
        throw new Exception("Invalid file extension. Only PDF is allowed.");
    }

    $safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', basename($file['name']));
    $newFileName = uniqid('res_') . '_' . time() . '_' . $safeName;
    $targetPath = __DIR__ . '/../uploads/' . $newFileName;

    if (!is_dir(__DIR__ . '/../uploads/')) mkdir(__DIR__ . '/../uploads/', 0777, true);

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception("Failed to save the uploaded file.");
    }

    // Extract text
    $pdfParser = new PdfParserWrapper();
    $resumeText = $pdfParser->extractText($targetPath);

    if (empty(trim($resumeText))) {
        throw new Exception("The uploaded PDF appears to be empty or unreadable.");
    }

    // Save to Multi-Resume manager
    $resumeMgr = new ResumeManager();
    $savedData = $resumeMgr->saveResume($_SESSION['user_id'], $file['name'], $resumeText, $category);

    // Clean up temporary PDF
    if (file_exists($targetPath)) {
        unlink($targetPath);
    }

    echo json_encode([
        'success' => true, 
        'message' => 'Resume uploaded and parsed successfully!',
        'resume' => $savedData
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

