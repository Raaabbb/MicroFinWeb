<?php
require_once "../../microfin_backend/auth/session_auth.php";
mf_start_backend_session();
require_once "../../microfin_backend/config/db_connect.php";
require_once "../../microfin_backend/auth/login_activity.php";
require_once "../../microfin_backend/auth/tenant_identity.php";

$tenant_slug = $_SESSION['tenant_slug'] ?? '';
$tenant_id = $_SESSION['tenant_id'] ?? '';
$tenant_key = $_SESSION['tenant_key'] ?? '';

if ($tenant_slug === '' && isset($_GET['s'])) {
    $tenant_slug = trim($_GET['s']);
}

if ($tenant_slug === '' && !empty($_COOKIE['mf_backend_session_token'])) {
    try {
        $stmt = $pdo->prepare('
            SELECT t.tenant_slug, t.tenant_id 
            FROM user_sessions us 
            JOIN tenants t ON us.tenant_id = t.tenant_id 
            WHERE us.session_token = ?
            LIMIT 1
        ');
        $stmt->execute([$_COOKIE['mf_backend_session_token']]);
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tenant_slug = $row['tenant_slug'];
            if ($tenant_id === '') $tenant_id = $row['tenant_id'];
        }
    } catch (Throwable $e) {}
}

if (!empty($_SESSION['user_id']) && !empty($_SESSION['tenant_id']) && empty($_SESSION['super_admin_logged_in'])) {
    try {
        $pdo->prepare("INSERT INTO audit_logs (user_id, tenant_id, action_type, entity_type, description) VALUES (?, ?, 'STAFF_LOGOUT', 'user', 'Staff logged out of the system')")->execute([$_SESSION['user_id'], $_SESSION['tenant_id']]);
    } catch (PDOException $e) {
        // Log error to PHP error log but allow logout to proceed
        error_log("Logout audit log failed: " . $e->getMessage());
    }
}

if (!empty($_SESSION['user_id'])) {
    mf_update_user_last_login($pdo, (int)$_SESSION['user_id']);
}

mf_destroy_backend_session($pdo);

$redirect = 'login.php';
if ($tenant_slug !== '' && $tenant_id !== '' && mf_tenant_public_website_is_ready($pdo, (string)$tenant_id)) {
    $redirect = '../public_website/site.php?site=' . urlencode($tenant_slug);
} elseif ($tenant_slug !== '') {
	$redirect = 'login.php?s=' . urlencode($tenant_slug);
}

header('Location: ' . $redirect);
exit;
?>

