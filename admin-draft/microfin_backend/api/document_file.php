<?php
/**
 * document_file.php
 * Secure gateway for viewing uploaded client documents.
 */

require_once __DIR__ . '/../../microfin_backend/auth/session_auth.php';
mf_start_backend_session();

// Check if user is logged in
if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    die("Forbidden: Authentication required.");
}

require_once __DIR__ . '/../../microfin_backend/config/db_connect.php';
require_once __DIR__ . '/../../microfin_backend/documents/document_access.php';

$path = $_GET['path'] ?? '';
if ($path === '') {
    http_response_code(400);
    die("Bad Request: Path missing.");
}

// Resolve the absolute path on the server
$absolutePath = mf_document_resolve_absolute_path($path);

if (!$absolutePath || !is_file($absolutePath)) {
    http_response_code(404);
    die("File not found.");
}

// Security: Basic check to ensure it's inside the repo root
$repoRoot = realpath(mf_document_repo_root());
$realFile = realpath($absolutePath);

if (!$realFile || !str_starts_with($realFile, $repoRoot)) {
    http_response_code(403);
    die("Forbidden: Access denied.");
}

// Optional: You could add tenant-level check here by parsing the path 
// and comparing it to $_SESSION['tenant_id'] for extra security.

// Get MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $realFile);
finfo_close($finfo);

// Serve the file
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($realFile));
header('Cache-Control: private, max-age=86400');

// Use 'inline' to show in browser, 'attachment' to force download
$disposition = 'inline'; 
header('Content-Disposition: ' . $disposition . '; filename="' . basename($realFile) . '"');

readfile($realFile);
exit;
