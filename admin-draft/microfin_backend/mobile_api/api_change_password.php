<?php
header('Content-Type: application/json');
require_once '../config/db.php';

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);

if (!isset($payload['user_id']) || !isset($payload['tenant_id']) || !isset($payload['current_password']) || !isset($payload['new_password'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

$userId = (int) $payload['user_id'];
$tenantId = (string) $payload['tenant_id'];
$currentPassword = (string) $payload['current_password'];
$newPassword = (string) $payload['new_password'];

try {
    // Verify user and tenant
    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ? AND tenant_id = ?");
    $stmt->bind_param('is', $userId, $tenantId);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();
    $stmt->close();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit;
    }

    if (!password_verify($currentPassword, $user['password_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Incorrect current password.']);
        exit;
    }

    // Update with new password
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $uStmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
    $uStmt->bind_param('si', $newHash, $userId);
    $uStmt->execute();
    
    if ($uStmt->affected_rows > 0 || $uStmt->errno == 0) {
        // Record audit if applicable
        $aStmt = $conn->prepare("INSERT INTO audit_logs (user_id, tenant_id, action_type, entity_type, entity_id, description) VALUES (?, ?, 'PASSWORD_CHANGE', 'user', ?, 'Client changed their password via mobile app')");
        $aStmt->bind_param('isi', $userId, $tenantId, $userId);
        $aStmt->execute();
        $aStmt->close();

        echo json_encode(['success' => true, 'message' => 'Password updated.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update password.']);
    }
    $uStmt->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
