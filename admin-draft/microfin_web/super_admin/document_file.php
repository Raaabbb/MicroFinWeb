<?php
require_once '../../microfin_backend/auth/session_auth.php';
mf_start_backend_session();
require_once '../../microfin_backend/config/db_connect.php';
require_once __DIR__ . '/super_admin_auth.php';

// 1. Strict Super Admin Authentication
mf_require_super_admin_session($pdo, [
    'response' => 'die',
    'message' => 'Unauthorized access. Please log in as a Super Admin.',
]);

require_once '../../microfin_backend/documents/document_access.php';

// 2. Get and Sanitize Path
$path = $_GET['path'] ?? '';
if ($path === '') {
    http_response_code(400);
    die('Missing file path.');
}

// 3. Resolve Absolute Path
$absolutePath = mf_document_resolve_absolute_path($path);

if (!$absolutePath || !is_file($absolutePath)) {
    http_response_code(404);
    die('File not found.');
}

// 4. Validate that the file is inside the project's uploads directory for security
$repoRoot = mf_document_repo_root();
$realRepoRoot = realpath($repoRoot);
$realFilePath = realpath($absolutePath);

if ($realRepoRoot === false || $realFilePath === false || !str_starts_with($realFilePath, $realRepoRoot)) {
    http_response_code(403);
    die('Access denied.');
}

// Additional check: Ensure it's inside an 'uploads' directory
if (!str_contains($realFilePath, DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR)) {
     http_response_code(403);
     die('Forbidden directory.');
}

// 5. Serve the File
$mimeType = mime_content_type($absolutePath);
$fileName = basename($absolutePath);

header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline; filename="' . $fileName . '"');
header('Content-Length: ' . filesize($absolutePath));
header('Cache-Control: private, max-age=3600');
header('Pragma: public');

readfile($absolutePath);
exit;
