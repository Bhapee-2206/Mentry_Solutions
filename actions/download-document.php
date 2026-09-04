<?php
// actions/download-document.php - Secure Document Downloader with Custom Formatted Filename
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$currentUser = getCurrentUser();
if (!$currentUser) {
    header("Location: /login.php");
    exit();
}

$url = $_GET['url'] ?? '';
$requestedFilename = trim($_GET['filename'] ?? '');

if (empty($url)) {
    http_response_code(400);
    die("Document URL is required.");
}

// Clean relative URL and prevent path traversal
$urlPath = parse_url($url, PHP_URL_PATH);
$urlPath = ltrim($urlPath, '/\\');

// Only allow files within public/uploads or public directory
$baseDir = realpath(__DIR__ . '/../');
$fullPath = realpath($baseDir . '/' . $urlPath);

if (!$fullPath || !file_exists($fullPath) || strpos($fullPath, $baseDir) !== 0) {
    http_response_code(404);
    die("File not found on server.");
}

$ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

// Fallback filename if none provided
if (empty($requestedFilename)) {
    $requestedFilename = basename($fullPath);
}

// Ensure the filename has the correct extension
$reqExt = strtolower(pathinfo($requestedFilename, PATHINFO_EXTENSION));
if ($reqExt !== $ext && !empty($ext)) {
    $requestedFilename = pathinfo($requestedFilename, PATHINFO_FILENAME) . '.' . $ext;
}

// Sanitize filename for safe HTTP header
$safeFilename = preg_replace('/[^a-zA-Z0-9_\-\. ]/', '_', $requestedFilename);

// Determine MIME type
$mimeTypes = [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'webp' => 'image/webp',
    'txt' => 'text/plain'
];
$mime = $mimeTypes[$ext] ?? 'application/octet-stream';

// Clear any output buffer
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $safeFilename . '"; filename*=UTF-8\'\'' . rawurlencode($safeFilename));
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Length: ' . filesize($fullPath));

readfile($fullPath);
exit();
