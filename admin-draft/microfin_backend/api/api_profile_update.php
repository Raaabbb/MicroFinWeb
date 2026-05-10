<?php
require_once '../auth/session_auth.php';
mf_start_backend_session();
require_once '../config/db_connect.php';

header('Content-Type: application/json');

function profile_update_respond(string $status, string $message, int $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode(['status' => $status, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    profile_update_respond('error', 'Method not allowed.', 405);
}

mf_require_tenant_session($pdo, [
    'response' => 'json',
    'message' => 'Unauthorized.',
]);

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$tenantId = trim((string)($_SESSION['tenant_id'] ?? ''));

if ($userId <= 0 || $tenantId === '') {
    profile_update_respond('error', 'Unauthorized.', 401);
}

$firstName = trim((string)($payload['first_name'] ?? ''));
$lastName = trim((string)($payload['last_name'] ?? ''));
$username = trim((string)($payload['username'] ?? ''));
$email = trim((string)($payload['email'] ?? ''));
$phoneNumber = trim((string)($payload['phone_number'] ?? ''));

if ($firstName === '' || $lastName === '' || $username === '' || $email === '') {
    profile_update_respond('error', 'First name, last name, username, and email are required.', 422);
}

// Check for duplicate username
$dupUserCheck = $pdo->prepare('SELECT COUNT(*) FROM users WHERE tenant_id = ? AND username = ? AND user_id != ?');
$dupUserCheck->execute([$tenantId, $username, $userId]);
if ($dupUserCheck->fetchColumn() > 0) {
    profile_update_respond('error', 'That username is already taken by another account.', 422);
}

// Fetch current user
$stmt = $pdo->prepare('SELECT email FROM users WHERE user_id = ? AND tenant_id = ?');
$stmt->execute([$userId, $tenantId]);
$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$currentUser) {
    profile_update_respond('error', 'Profile not found.', 404);
}

$currentEmail = $currentUser['email'];
$updateEmail = false;

if (strcasecmp($email, $currentEmail) !== 0) {
    // Determine if it was verified through OTP
    $verifiedEmail = trim((string)($_SESSION['admin_profile_email_change_verified_email'] ?? ''));
    $verifiedUserId = (int)($_SESSION['admin_profile_email_change_verified_user_id'] ?? 0);
    $verifiedTenantId = trim((string)($_SESSION['admin_profile_email_change_verified_tenant_id'] ?? ''));

    if (
        $verifiedEmail === '' || 
        strcasecmp($verifiedEmail, $email) !== 0 || 
        $verifiedUserId !== $userId || 
        $verifiedTenantId !== $tenantId
    ) {
        profile_update_respond('error', 'Please verify your new email address with an OTP before saving.', 422);
    }
    
    // Safety check again
    $dupCheck = $pdo->prepare('SELECT COUNT(*) FROM users WHERE tenant_id = ? AND email = ? AND user_id != ?');
    $dupCheck->execute([$tenantId, $email, $userId]);
    if ($dupCheck->fetchColumn() > 0) {
        profile_update_respond('error', 'Email is already used by another account.', 422);
    }
    $updateEmail = true;
}

try {
    $pdo->beginTransaction();
    
    $updateQuery = "UPDATE users SET first_name = ?, last_name = ?, username = ?, phone_number = ?";
    $params = [$firstName, $lastName, $username, $phoneNumber];
    
    if ($updateEmail) {
        $updateQuery .= ", email = ?";
        $params[] = $email;
    }
    
    $updateQuery .= " WHERE user_id = ? AND tenant_id = ?";
    $params[] = $userId;
    $params[] = $tenantId;
    
    $pdo->prepare($updateQuery)->execute($params);
    
    $pdo->prepare("UPDATE employees SET first_name = ?, last_name = ?, contact_number = ? WHERE user_id = ? AND tenant_id = ?")->execute([$firstName, $lastName, $phoneNumber, $userId, $tenantId]);
    
    $pdo->commit();
    $_SESSION['username'] = $username; // Update session username to reflect quickly everywhere
    
    if ($updateEmail) {
        unset($_SESSION['admin_profile_email_change_verified_email']);
        unset($_SESSION['admin_profile_email_change_verified_user_id']);
        unset($_SESSION['admin_profile_email_change_verified_tenant_id']);
    }
    
    profile_update_respond('success', 'Profile updated successfully.');
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Profile Update Error: ' . $e->getMessage());
    profile_update_respond('error', 'Failed to update profile.', 500);
}
