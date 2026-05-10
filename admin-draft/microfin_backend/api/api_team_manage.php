<?php
require_once __DIR__ . '/session_auth.php';
mf_start_backend_session();
require_once __DIR__ . '/config/db_connect.php';

header('Content-Type: application/json');

$tenant_id = $_SESSION['tenant_id'] ?? null;
$editor_user_id = $_SESSION['user_id'] ?? null;

if (!$tenant_id || !$editor_user_id) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated.']);
    exit;
}

// Security Check: Must have CREATE_USERS
$perm_stmt = $pdo->prepare('
    SELECT COUNT(*) FROM role_permissions rp 
    JOIN permissions p ON rp.permission_id = p.permission_id 
    JOIN users u ON u.role_id = rp.role_id
    WHERE u.user_id = ? AND p.permission_code = "CREATE_USERS"
');
$perm_stmt->execute([$editor_user_id]);
if ($perm_stmt->fetchColumn() == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized to manage staff.']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!$payload) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON payload.']);
    exit;
}

$target_user_id = (int)($payload['user_id'] ?? 0);
$role_id = (int)($payload['role_id'] ?? 0);
$status = trim($payload['status'] ?? '');

$valid_statuses = ['Active', 'Inactive', 'Locked', 'Suspended'];
if (!$target_user_id || !$role_id || !in_array($status, $valid_statuses)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing or invalid parameters.']);
    exit;
}

// Ensure the target user actually belongs to this tenant and is NOT a Client or Super Admin
$user_check = $pdo->prepare('
    SELECT u.user_type, u.role_id, r.role_name 
    FROM users u 
    LEFT JOIN user_roles r ON u.role_id = r.role_id
    WHERE u.user_id = ? AND u.tenant_id = ?
');
$user_check->execute([$target_user_id, $tenant_id]);
$targetData = $user_check->fetch(PDO::FETCH_ASSOC);

if (!$targetData) {
    echo json_encode(['status' => 'error', 'message' => 'Target staff member not found in your tenant.']);
    exit;
}

if (in_array($targetData['role_name'], ['Client', 'Super Admin']) || in_array($targetData['user_type'], ['Client', 'Super Admin'])) {
    echo json_encode(['status' => 'error', 'message' => 'Cannot modify this user.']);
    exit;
}

// Prevent modifying your own role/status from here (as a fallback, though usually UI prevents it)
if ($target_user_id === (int)$editor_user_id) {
    echo json_encode(['status' => 'error', 'message' => 'You cannot change your own role or status here.']);
    exit;
}

// Ensure the new role_id is valid for this tenant
$role_check = $pdo->prepare('SELECT role_name FROM user_roles WHERE role_id = ? AND (tenant_id = ? OR is_system_role = 1)');
$role_check->execute([$role_id, $tenant_id]);
$roleData = $role_check->fetch(PDO::FETCH_ASSOC);

if (!$roleData || in_array($roleData['role_name'], ['Client', 'Super Admin'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid role assignment.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('UPDATE users SET role_id = ?, status = ? WHERE user_id = ? AND tenant_id = ?');
    $stmt->execute([$role_id, $status, $target_user_id, $tenant_id]);

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'Staff profile updated successfully!']);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
