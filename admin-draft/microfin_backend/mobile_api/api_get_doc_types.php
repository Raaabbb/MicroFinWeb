<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$sql = "
    SELECT
        document_type_id,
        document_name,
        COALESCE(description, '') AS description,
        CAST(is_required AS CHAR) AS is_required,
        CAST(is_active AS CHAR) AS is_active,
        COALESCE(loan_purpose, '') AS loan_purpose
    FROM document_types
    WHERE is_active = 1
    ORDER BY is_required DESC, document_name ASC
";

$result = $conn->query($sql);
if (!$result) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load document types: ' . $conn->error]);
    exit;
}

$documentTypes = [];
while ($row = $result->fetch_assoc()) {
    $documentTypes[] = $row;
}

echo json_encode([
    'success' => true,
    'document_types' => $documentTypes,
]);
