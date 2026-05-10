<?php
require_once '../auth/session_auth.php';
mf_start_backend_session();
require_once '../config/db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$theme = strtolower(trim((string)($payload['theme'] ?? '')));
if (!in_array($theme, ['light', 'dark'], true)) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Invalid theme value']);
    exit;
}

$user_id = 0;
$role_context = strtolower(trim((string)($payload['role'] ?? '')));

if ($role_context === 'super_admin') {
    if (mf_validate_backend_session($pdo, 'super_admin')) {
        $user_id = (int) ($_SESSION['super_admin_id'] ?? 0);
    }
} elseif ($role_context === 'tenant') {
    if (mf_validate_backend_session($pdo, 'tenant')) {
        $user_id = (int) ($_SESSION['user_id'] ?? 0);
        if ($user_id <= 0 && mf_backend_session_is_impersonation()) {
            $user_id = (int) ($_SESSION['super_admin_id'] ?? 0);
        }
    }
} else {
    if (mf_validate_backend_session($pdo, 'tenant')) {
        $user_id = (int) ($_SESSION['user_id'] ?? 0);
        if ($user_id <= 0 && mf_backend_session_is_impersonation()) {
            $user_id = (int) ($_SESSION['super_admin_id'] ?? 0);
        }
    } elseif (mf_validate_backend_session($pdo, 'super_admin')) {
        $user_id = (int) ($_SESSION['super_admin_id'] ?? 0);
    }
}

if ($user_id <= 0) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    $stmt = $pdo->prepare('UPDATE users SET ui_theme = ? WHERE user_id = ?');
    $stmt->execute([$theme, $user_id]);

    $_SESSION['ui_theme'] = $theme;

    echo json_encode(['status' => 'success', 'theme' => $theme]);
} catch (PDOException $ex) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to save theme preference']);
}
