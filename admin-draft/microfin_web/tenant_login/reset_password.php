<?php
session_start();
require_once "../../microfin_backend/config/db_connect.php";

$token = trim($_GET['token'] ?? '');
$message = '';
$message_type = '';
$user_id = null;
$tenant_id = null;
$theme_color = '#2563eb';
$tenant_slug = '';
$user_type = '';

if ($token) {
    // Validate token and check if it's expired
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.tenant_id, u.status, u.user_type, b.theme_primary_color, b.theme_text_main, b.theme_text_muted, b.theme_bg_body, b.theme_bg_card, b.font_family, t.tenant_slug
        FROM users u
        JOIN tenants t ON u.tenant_id = t.tenant_id
        LEFT JOIN tenant_branding b ON t.tenant_id = b.tenant_id
        WHERE u.reset_token = ? AND u.reset_token_expiry > NOW()
          AND u.deleted_at IS NULL
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        if (trim((string)($user['status'] ?? '')) !== 'Active') {
            $message_type = 'error';
            $message = 'This account is no longer eligible for password reset.';
        } else {
            $user_id = $user['user_id'];
            $tenant_id = $user['tenant_id'];
            $user_type = $user['user_type'];
            $theme_color = $user['theme_primary_color'] ?: '#2563eb';
            $theme_text_main = $user['theme_text_main'] ?: '#0f172a';
            $theme_text_muted = $user['theme_text_muted'] ?: '#64748b';
            $theme_bg_body = $user['theme_bg_body'] ?: '#f8fafc';
            $theme_bg_card = $user['theme_bg_card'] ?: '#ffffff';
            $theme_font = $user['font_family'] ?: 'Inter';
            $tenant_slug = $user['tenant_slug'];
        }
    } else {
        $message_type = 'error';
        $message = 'This password reset link is invalid or has expired. Please request a new one.';
    }
} else {
    $message_type = 'error';
    $message = 'No token provided.';
}

// Handle Password Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_id) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (strlen($new_password) < 8) {
        $message_type = 'error';
        $message = 'Password must be at least 8 characters long.';
    } elseif ($new_password !== $confirm_password) {
        $message_type = 'error';
        $message = 'Passwords do not match.';
    } else {
        // Update the password and clear the tokens
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        
        $update = $pdo->prepare("
            UPDATE users 
            SET password_hash = ?, reset_token = NULL, reset_token_expiry = NULL, force_password_change = 0
            WHERE user_id = ?
        ");
        
        if ($update->execute([$password_hash, $user_id])) {
            $message_type = 'success';
            $message = 'Your password has been successfully reset!';
            // Fetch user type again to be sure
            $stmt_type = $pdo->prepare("SELECT user_type FROM users WHERE user_id = ?");
            $stmt_type->execute([$user_id]);
            $user_type = $stmt_type->fetchColumn();
            
            $user_id = null; // Hide the form on success
        } else {
            $message_type = 'error';
            $message = 'A system error occurred. Please try again.';
        }
    }
}
$theme_color = $theme_color ?? '#2563eb';
$theme_text_main = $theme_text_main ?? '#0f172a';
$theme_text_muted = $theme_text_muted ?? '#64748b';
$theme_bg_body = $theme_bg_body ?? '#f8fafc';
$theme_bg_card = $theme_bg_card ?? '#ffffff';
$theme_font = $theme_font ?? 'Inter';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link href="https://fonts.googleapis.com/css2?family=<?php echo urlencode($theme_font); ?>:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/password-toggle.css">
    <style>
        :root {
            --brand-color: <?php echo htmlspecialchars($theme_color); ?>;
            --brand-bg: <?php echo htmlspecialchars($theme_bg_body); ?>;
            --card-bg: <?php echo htmlspecialchars($theme_bg_card); ?>;
            --text-main: <?php echo htmlspecialchars($theme_text_main); ?>;
            --text-muted: <?php echo htmlspecialchars($theme_text_muted); ?>;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: '<?php echo htmlspecialchars($theme_font); ?>', sans-serif; }
        body { background-color: var(--brand-bg); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        
        .login-container { background: var(--card-bg); width: 100%; max-width: 440px; border-radius: 16px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1); padding: 48px 40px; }
        
        .header { text-align: center; margin-bottom: 32px; }
        .header h1 { font-size: 1.5rem; color: var(--text-main); margin-bottom: 8px; }
        .header p { color: var(--text-muted); font-size: 0.95rem; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 0.85rem; font-weight: 500; color: var(--text-main); margin-bottom: 8px; }
        .form-control { width: 100%; padding: 12px 16px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.95rem; color: var(--text-main); transition: all 0.2s ease; }
        .form-control:focus { outline: none; border-color: var(--brand-color); box-shadow: 0 0 0 3px color-mix(in srgb, var(--brand-color) 20%, transparent); }
        
        .btn-submit { width: 100%; padding: 12px 24px; background: var(--brand-color); color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: 500; cursor: pointer; transition: all 0.2s ease; margin-top: 12px; }
        .btn-submit:hover { filter: brightness(0.9); transform: translateY(-1px); }
        
        .alert { padding: 12px 16px; border-radius: 8px; font-size: 0.9rem; margin-bottom: 24px; }
        .alert-success { background-color: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; text-align: center; }
        .alert-error { background-color: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; text-align: center; }
        
        .login-link { display: inline-block; width: 100%; text-align: center; margin-top: 24px; font-weight: 500; color: var(--brand-color); text-decoration: none; border: 1px solid var(--brand-color); border-radius: 8px; padding: 10px; }
        .login-link:hover { background: color-mix(in srgb, var(--brand-color) 5%, transparent); }
    </style>
</head>
<body>

<div class="login-container">
    <div class="header">
        <h1>Set New Password</h1>
        <p>Please enter your new password below.</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($user_id): ?>
    <form method="POST">
        <div class="form-group">
            <label>New Password</label>
            <input type="password" name="new_password" class="form-control" placeholder="••••••••" required minlength="8">
        </div>

        <div class="form-group">
            <label>Confirm New Password</label>
            <input type="password" name="confirm_password" class="form-control" placeholder="••••••••" required minlength="8">
        </div>

        <button type="submit" class="btn-submit">Reset Password</button>
    </form>
    <?php endif; ?>

    <?php if ($message_type === 'success' || ($message && !$user_id)): ?>
        <?php if ($user_type === 'Client'): ?>
            <div style="text-align: center; margin-top: 24px; padding: 20px; border-radius: 12px; background: color-mix(in srgb, var(--brand-color) 5%, transparent); border: 1px dashed var(--brand-color);">
                <p style="color: var(--text-main); font-weight: 600; margin-bottom: 8px;">Account Ready!</p>
                <p style="color: var(--text-muted); font-size: 0.9rem;">You can now log in using the mobile app with your new password.</p>
            </div>
        <?php elseif ($user_type === 'Employee'): ?>
            <a href="login.php<?php echo $tenant_slug ? '?s='.urlencode($tenant_slug) : ''; ?>" class="login-link">
                Return to Login
            </a>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script src="../assets/password-toggle.js"></script>
</body>
</html>

