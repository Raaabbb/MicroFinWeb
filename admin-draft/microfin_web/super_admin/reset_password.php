<?php
session_start();
require_once '../../microfin_backend/config/db_connect.php';

$token = trim((string)($_GET['token'] ?? ''));
$message = '';
$message_type = '';
$user_id = null;
$platformLogoFile = __DIR__ . '/logo/MicroFin-logo-transparent-temp.png';
$platformLogoUrl = '../public_website/logo/MicroFin-logo-transparent-temp.png?v=' . urlencode((string) @filemtime($platformLogoFile));

if ($token !== '') {
    $stmt = $pdo->prepare("
        SELECT user_id, status
        FROM users
        WHERE reset_token = ?
          AND reset_token_expiry > NOW()
          AND user_type = 'Super Admin'
          AND deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $superAdmin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$superAdmin) {
        $message_type = 'error';
        $message = 'This password reset link is invalid or has expired. Please request a new one.';
    } elseif (!in_array(trim((string)($superAdmin['status'] ?? '')), ['Active', 'Inactive'], true)) {
        $message_type = 'error';
        $message = 'This account is not eligible for password reset.';
    } else {
        $user_id = (int)$superAdmin['user_id'];
    }
} else {
    $message_type = 'error';
    $message = 'No reset token was provided.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_id) {
    $new_password = (string)($_POST['new_password'] ?? '');
    $confirm_password = (string)($_POST['confirm_password'] ?? '');

    if (strlen($new_password) < 8) {
        $message_type = 'error';
        $message = 'Password must be at least 8 characters long.';
    } elseif ($new_password !== $confirm_password) {
        $message_type = 'error';
        $message = 'Passwords do not match.';
    } else {
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $update = $pdo->prepare("
            UPDATE users
            SET password_hash = ?, reset_token = NULL, reset_token_expiry = NULL, force_password_change = 0
            WHERE user_id = ?
        ");

        if ($update->execute([$password_hash, $user_id])) {
            $message_type = 'success';
            $message = 'Your password has been successfully reset. You can now sign in.';
            $user_id = null;
        } else {
            $message_type = 'error';
            $message = 'A system error occurred. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars((string)($_SESSION['ui_theme'] ?? 'light'), ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MicroFin | Reset Password</title>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($platformLogoUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($platformLogoUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="super_admin_theme.css">
    <link rel="stylesheet" href="super_admin_auth.css">
    <link rel="stylesheet" href="../assets/password-toggle.css">
</head>
<body class="platform-auth auth-compact">
    <button type="button" class="auth-theme-toggle" id="auth-theme-toggle" aria-label="Switch to dark mode">Dark mode</button>
    <div class="card">
        <h1>Set New Password</h1>
        <p>Choose a new password for your super admin account.</p>

        <?php if ($message !== ''): ?>
        <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <?php if ($user_id): ?>
        <form method="POST">
            <label for="new_password">New Password</label>
            <input type="password" id="new_password" name="new_password" minlength="8" required>

            <label for="confirm_password">Confirm New Password</label>
            <input type="password" id="confirm_password" name="confirm_password" minlength="8" required>

            <button type="submit" class="btn-submit">Reset Password</button>
        </form>
        <?php endif; ?>

        <?php if ($message_type === 'success' || !$user_id): ?>
        <a href="login.php" class="login-link">Return to Login</a>
        <?php endif; ?>
    </div>
    <script src="login.js"></script>
    <script src="../assets/password-toggle.js"></script>
</body>
</html>

