<?php
require_once '../auth/session_auth.php';
mf_start_backend_session();
require_once '../config/db_connect.php';
require_once '../documents/document_access.php';

mf_require_tenant_session($pdo, [
    'response' => 'redirect',
    'redirect' => '../tenant_login/login.php',
    'append_tenant_slug' => true,
]);

$requestedPath = (string) ($_GET['path'] ?? '');
$normalizedPath = mf_document_normalize_path($requestedPath);
if ($normalizedPath === '') {
    http_response_code(400);
    exit('Invalid document path.');
}

$absolutePath = mf_document_resolve_absolute_path($normalizedPath);
if ($absolutePath === null) {
    http_response_code(404);
    exit('Document not found.');
}

$fileName = basename($absolutePath);
$extension = strtolower((string) pathinfo($fileName, PATHINFO_EXTENSION));
$inlineExtensions = ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'gif'];
$disposition = in_array($extension, $inlineExtensions, true) ? 'inline' : 'attachment';

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = $finfo ? (string) finfo_file($finfo, $absolutePath) : '';
if ($finfo) {
    finfo_close($finfo);
}
if ($mimeType === '') {
    $mimeType = 'application/octet-stream';
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . (string) filesize($absolutePath));
header('Content-Disposition: ' . $disposition . '; filename="' . rawurlencode($fileName) . '"');
header('X-Content-Type-Options: nosniff');
readfile($absolutePath);
exit;
