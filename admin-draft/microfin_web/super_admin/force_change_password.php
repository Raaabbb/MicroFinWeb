<?php
require_once '../../microfin_backend/auth/session_auth.php';
mf_start_backend_session();
require_once '../../microfin_backend/config/db_connect.php';
require_once __DIR__ . '/super_admin_auth.php';
mf_require_super_admin_session($pdo, [
    'response' => 'redirect',
    'redirect' => 'login.php',
]);

$superAdminId = (int) ($_SESSION['super_admin_id'] ?? 0);
if ($superAdminId <= 0) {
    header('Location: login.php');
    exit;
}

$superAdmin = sa_load_super_admin_state($pdo, $superAdminId);

if (!$superAdmin) {
    mf_destroy_backend_session($pdo);
    header('Location: login.php');
    exit;
}

sa_sync_super_admin_session_from_state($superAdmin);

if (!$_SESSION['super_admin_force_password_change']) {
    $destination = !empty($_SESSION['super_admin_onboarding_required'])
        ? 'onboarding_profile.php'
        : 'super_admin.php';
    header('Location: ' . $destination);
    exit;
}

$platformLogoFile = __DIR__ . '/logo/MicroFin-logo-transparent-temp.png';
$platformLogoUrl = '../public_website/logo/MicroFin-logo-transparent-temp.png?v=' . urlencode((string) @filemtime($platformLogoFile));

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($newPassword === '' || $confirmPassword === '') {
        $error = 'Both password fields are required.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (strlen($newPassword) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $profileComplete = sa_super_admin_profile_is_complete($superAdmin);
        $nextStatus = $profileComplete ? 'Active' : 'Inactive';
        $updateStmt = $pdo->prepare('UPDATE users SET password_hash = ?, force_password_change = 0, status = ? WHERE user_id = ?');

        if ($updateStmt->execute([$hashedPassword, $nextStatus, $superAdminId])) {
            $_SESSION['super_admin_force_password_change'] = false;
            $_SESSION['super_admin_onboarding_required'] = !$profileComplete;

            $logStmt = $pdo->prepare("
                INSERT INTO audit_logs (user_id, action_type, entity_type, description)
                VALUES (?, 'PASSWORD_CHANGED', 'user', ?)
            ");
            $logStmt->execute([$superAdminId, 'Super admin completed forced password reset']);

            header('Location: ' . ($profileComplete ? 'super_admin.php' : 'onboarding_profile.php'));
            exit;
        }

        $error = 'Failed to update password. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars((string)($_SESSION['ui_theme'] ?? 'light'), ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MicroFin | Super Admin Password Reset</title>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($platformLogoUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($platformLogoUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="super_admin_theme.css">
    <link rel="stylesheet" href="super_admin_auth.css">
    <link rel="stylesheet" href="../assets/password-toggle.css">
</head>
<body class="platform-auth auth-compact">
    <button type="button" class="auth-theme-toggle" id="auth-theme-toggle" aria-label="Switch to dark mode">Dark mode</button>
    <div class="panel">
        <div class="eyebrow">First-Time Security Step</div>
        <h1>Reset Your Password</h1>
        <p>
            Welcome, <?php echo htmlspecialchars((string) ($superAdmin['username'] ?? 'Super Admin'), ENT_QUOTES, 'UTF-8'); ?>.
            Before accessing the super admin dashboard, you need to replace your temporary password with a new one.
            <?php if (!sa_super_admin_profile_is_complete($superAdmin)): ?>
            Your profile details will be completed right after this step.
            <?php endif; ?>
        </p>

        <?php if ($error !== ''): ?>
            <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="field">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" required minlength="8" autocomplete="new-password">
                <div class="hint">Use at least 8 characters.</div>
            </div>

            <div class="field">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">
            </div>

            <button type="submit">Update Password</button>
        </form>
    </div>
    <script src="login.js"></script>
    <script src="../assets/password-toggle.js"></script>
</body>
</html>

