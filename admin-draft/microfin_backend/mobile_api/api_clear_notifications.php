<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid Request']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$userId = (int) ($data['user_id'] ?? 0);
$tenantId = trim((string) ($data['tenant_id'] ?? ''));

if ($userId <= 0 || $tenantId === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Missing context.']);
    exit;
}

$stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND tenant_id = ?");
if ($stmt) {
    $stmt->bind_param('is', $userId, $tenantId);
    $stmt->execute();
    $stmt->close();
}

echo json_encode(['success' => true]);
?>
