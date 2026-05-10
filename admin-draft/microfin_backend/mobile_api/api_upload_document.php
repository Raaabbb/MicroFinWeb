<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$uploadDebugEnabled = true;

function upload_debug_payload(array $extra = []): array
{
    global $tenantId, $clientId, $userId, $rawFileCategory, $fileCategory, $uploadedFile;

    $postKeys = array_keys($_POST ?? []);
    sort($postKeys);

    $fileInfo = null;
    if (isset($uploadedFile) && is_array($uploadedFile)) {
        $fileInfo = [
            'name' => $uploadedFile['name'] ?? null,
            'type' => $uploadedFile['type'] ?? null,
            'size' => $uploadedFile['size'] ?? null,
            'error' => $uploadedFile['error'] ?? null,
            'tmp_name_present' => !empty($uploadedFile['tmp_name'] ?? ''),
        ];
    } elseif (isset($_FILES['file']) && is_array($_FILES['file'])) {
        $fileInfo = [
            'name' => $_FILES['file']['name'] ?? null,
            'type' => $_FILES['file']['type'] ?? null,
            'size' => $_FILES['file']['size'] ?? null,
            'error' => $_FILES['file']['error'] ?? null,
            'tmp_name_present' => !empty($_FILES['file']['tmp_name'] ?? ''),
        ];
    }

    return array_merge([
        'post_keys' => $postKeys,
        'parsed' => [
            'tenant_id' => $tenantId ?? null,
            'client_id' => $clientId ?? null,
            'user_id' => $userId ?? null,
            'raw_file_category' => $rawFileCategory ?? null,
            'file_category' => $fileCategory ?? null,
        ],
        'files_present' => array_keys($_FILES ?? []),
        'file_info' => $fileInfo,
    ], $extra);
}

function upload_fail(int $status, string $message, array $extra = []): void
{
    global $uploadDebugEnabled;

    http_response_code($status);
    $payload = ['success' => false, 'message' => $message];
    if ($uploadDebugEnabled) {
        $payload['debug'] = upload_debug_payload($extra);
    }
    echo json_encode($payload);
    exit;
}

$tenantId = trim((string) ($_POST['tenant_id'] ?? $_POST['tenant'] ?? ''));
$clientId = (int) ($_POST['client_id'] ?? $_POST['borrower_id'] ?? 0);
$userId = (int) ($_POST['user_id'] ?? 0);
$rawFileCategory = $_POST['file_category']
    ?? $_POST['document_type_id']
    ?? $_POST['category']
    ?? $_POST['type']
    ?? '';
$fileCategory = trim(strtolower((string) $rawFileCategory));

$categoryMap = [
    '1' => 'scanned_id',
    '2' => 'id_back',
    '3' => 'proof_of_income',
    '4' => 'proof_of_billing',
    '5' => 'proof_of_legitimacy',
    'id_front' => 'scanned_id',
];

if ($fileCategory !== '' && isset($categoryMap[$fileCategory])) {
    $fileCategory = $categoryMap[$fileCategory];
}

if ($tenantId === '') {
    upload_fail(422, 'Missing tenant ID.');
}

if ($clientId <= 0 && $userId > 0) {
    $clientLookupStmt = $conn->prepare("
        SELECT client_id
        FROM clients
        WHERE user_id = ?
          AND tenant_id = ?
          AND deleted_at IS NULL
        LIMIT 1
    ");

    if ($clientLookupStmt) {
        $clientLookupStmt->bind_param('is', $userId, $tenantId);
        $clientLookupStmt->execute();
        $clientLookupStmt->bind_result($resolvedClientId);
        if ($clientLookupStmt->fetch()) {
            $clientId = (int) $resolvedClientId;
        }
        $clientLookupStmt->close();
    }
}

if (($clientId <= 0 && $userId <= 0) || $fileCategory === '') {
    upload_fail(422, 'Missing client or user ID, or file category.', [
        'client_lookup_attempted' => $userId > 0,
    ]);
}

$tenantStmt = $conn->prepare("
    SELECT tenant_id
    FROM tenants
    WHERE tenant_id = ?
      AND deleted_at IS NULL
    LIMIT 1
");

if (!$tenantStmt) {
    upload_fail(500, 'Failed to prepare tenant lookup.');
}

$tenantStmt->bind_param('s', $tenantId);
$tenantStmt->execute();
$tenantExists = $tenantStmt->get_result()->num_rows === 1;
$tenantStmt->close();

if (!$tenantExists) {
    upload_fail(404, 'Tenant not found.');
}

if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
    upload_fail(422, 'No file was uploaded.');
}

$uploadedFile = $_FILES['file'];
$errorCode = (int) ($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE);
if ($errorCode !== UPLOAD_ERR_OK) {
    $message = 'File upload failed.';
    if ($errorCode === UPLOAD_ERR_INI_SIZE || $errorCode === UPLOAD_ERR_FORM_SIZE) {
        $message = 'Uploaded file is too large.';
    } elseif ($errorCode === UPLOAD_ERR_NO_FILE) {
        $message = 'No file was uploaded.';
    }

    upload_fail(422, $message, ['upload_error_code' => $errorCode]);
}

$originalName = basename((string) ($uploadedFile['name'] ?? 'upload.bin'));
$tmpName = (string) ($uploadedFile['tmp_name'] ?? '');
$sizeBytes = (int) ($uploadedFile['size'] ?? 0);
$extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
$allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
$allowedMimeTypes = [
    'jpg' => ['image/jpeg', 'image/pjpeg'],
    'jpeg' => ['image/jpeg', 'image/pjpeg'],
    'png' => ['image/png'],
    'pdf' => ['application/pdf'],
];

if ($tmpName === '' || !is_uploaded_file($tmpName)) {
    upload_fail(422, 'Uploaded file source is invalid.');
}

if ($sizeBytes <= 0) {
    upload_fail(422, 'Uploaded file is empty.');
}

if ($sizeBytes > 10 * 1024 * 1024) {
    upload_fail(422, 'Uploaded file must be 10MB or smaller.');
}

if (!in_array($extension, $allowedExtensions, true)) {
    upload_fail(422, 'Invalid file type. Allowed types: JPG, JPEG, PNG, PDF.', [
        'extension' => $extension,
    ]);
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = $finfo ? (string) finfo_file($finfo, $tmpName) : '';
if ($finfo) {
    finfo_close($finfo);
}

if ($mimeType !== '' && isset($allowedMimeTypes[$extension]) && !in_array($mimeType, $allowedMimeTypes[$extension], true)) {
    upload_fail(422, 'Uploaded file content does not match the selected file type.', [
        'extension' => $extension,
        'mime_type' => $mimeType,
    ]);
}

$tenantKey = preg_replace('/[^A-Za-z0-9_-]+/', '_', $tenantId);
if (!is_string($tenantKey) || $tenantKey === '') {
    $tenantKey = 'tenant';
}

$uploadRelativeDir = 'uploads/client_documents/' . $tenantKey . '/' . date('Y') . '/' . date('m');
$uploadAbsoluteDir = dirname(__DIR__, 2) . '/uploads/client_documents/' . $tenantKey . '/' . date('Y') . '/' . date('m');

if (!is_dir($uploadAbsoluteDir) && !mkdir($uploadAbsoluteDir, 0775, true) && !is_dir($uploadAbsoluteDir)) {
    upload_fail(500, 'Unable to prepare the upload folder.', [
        'upload_absolute_dir' => $uploadAbsoluteDir,
    ]);
}

$safeOriginalName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $originalName);
$safeCategory = preg_replace('/[^A-Za-z0-9_-]+/', '_', $fileCategory);

$identifier = $clientId > 0 ? (int)$clientId : 'u' . (int)$userId;
$timestamp = time();
$storedName = rtrim($tenantId) . '_' . $identifier . '_' . $safeCategory . '_' . $timestamp . '.' . $extension;
$destinationPath = rtrim($uploadAbsoluteDir, '/\\') . DIRECTORY_SEPARATOR . $storedName;

if (!move_uploaded_file($tmpName, $destinationPath)) {
    upload_fail(500, 'Failed to save the uploaded file.', [
        'destination_path' => $destinationPath,
    ]);
}

$relativeFilePath = $uploadRelativeDir . '/' . $storedName;

echo json_encode([
    'success' => true,
    'message' => 'File uploaded successfully.',
    'file_name' => $safeOriginalName,
    'stored_name' => $storedName,
    'file_path' => $relativeFilePath,
    'file_size' => $sizeBytes,
    'file_type' => $mimeType !== '' ? $mimeType : mime_content_type($destinationPath),
    'debug' => $uploadDebugEnabled ? upload_debug_payload([
        'destination_path' => $destinationPath,
    ]) : null,
]);
