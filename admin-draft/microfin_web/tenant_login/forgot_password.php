<?php
session_start();
require_once "../../microfin_backend/config/db_connect.php";

$site_slug = trim($_GET["s"] ?? "");
$tenant = null;
$message = '';
$message_type = '';

// Load tenant branding
if ($site_slug !== '') {
    $tenant_stmt = $pdo->prepare("SELECT t.tenant_id, t.tenant_name, b.theme_primary_color, b.theme_text_main, b.theme_text_muted, b.theme_bg_body, b.theme_bg_card, b.font_family FROM tenants t LEFT JOIN tenant_branding b ON t.tenant_id = b.tenant_id WHERE t.tenant_slug = ? AND t.status IN ('Active', 'Compromised')");
    $tenant_stmt->execute([$site_slug]);
    $tenant = $tenant_stmt->fetch();
}

$theme_color = $tenant ? ($tenant['theme_primary_color'] ?: '#2563eb') : '#2563eb';
$theme_text_main = $tenant ? ($tenant['theme_text_main'] ?: '#0f172a') : '#0f172a';
$theme_text_muted = $tenant ? ($tenant['theme_text_muted'] ?: '#64748b') : '#64748b';
$theme_bg_body = $tenant ? ($tenant['theme_bg_body'] ?: '#f8fafc') : '#f8fafc';
$theme_bg_card = $tenant ? ($tenant['theme_bg_card'] ?: '#ffffff') : '#ffffff';
$theme_font = $tenant ? ($tenant['font_family'] ?: 'Inter') : 'Inter';
$tenant_name = $tenant ? $tenant['tenant_name'] : 'System';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email']);

    if ($tenant) {
        $stmt = $pdo->prepare("
            SELECT user_id, username, status
            FROM users
            WHERE email = ?
              AND tenant_id = ?
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$email, $tenant['tenant_id']]);
        $user = $stmt->fetch();

        if (!$user) {
            $message_type = 'error';
            $message = 'No account found with that email for this workspace.';
        } elseif (trim((string)($user['status'] ?? '')) !== 'Active') {
            $message_type = 'error';
            $message = 'This account is not eligible for password reset because it is currently ' . strtolower((string)$user['status']) . '.';
        } else {
            // Generate token
            $token = bin2hex(random_bytes(32));
            $update = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE user_id = ?");
            $update->execute([$token, $user['user_id']]);

            // Send Email
            $forwardedProto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $forwardedProto === 'https';
            $protocol = $isHttps ? "https://" : "http://";
            $domainName = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/microfin_platform/tenant_login/forgot_password.php'));
            $resetPath = rtrim($scriptDir, '/') . '/reset_password.php';
            $reset_link = $protocol . $domainName . $resetPath . '?token=' . urlencode($token);

            $tenantNameHtml = htmlspecialchars((string)$tenant['tenant_name'], ENT_QUOTES, 'UTF-8');
            $safeResetLink = htmlspecialchars($reset_link, ENT_QUOTES, 'UTF-8');
            $htmlBody = mf_email_template([
                'accent' => $theme_color,
                'eyebrow' => $tenantNameHtml,
                'title' => 'Reset Your Password',
                'preheader' => "Password reset instructions for {$tenant['tenant_name']}.",
                'intro_html' => "
                    <p style='margin: 0 0 14px; font-family: Arial, sans-serif; font-size: 15px; line-height: 1.7; color: #334155;'>
                        Hello,
                    </p>
                    <p style='margin: 0 0 14px; font-family: Arial, sans-serif; font-size: 15px; line-height: 1.7; color: #334155;'>
                        We received a request to reset the password for your account at <strong>{$tenantNameHtml}</strong>.
                    </p>
                    <p style='margin: 0 0 14px; font-family: Arial, sans-serif; font-size: 15px; line-height: 1.7; color: #334155;'>
                        Use the button below to set a new password. This link will expire in 1 hour.
                    </p>
                ",
                'body_html' => mf_email_button('Reset Password', $reset_link, $theme_color)
                    . mf_email_panel(
                        'Reset Link',
                        "
                            <p style='margin: 0 0 10px; font-family: Arial, sans-serif; font-size: 14px; line-height: 1.7; color: #334155;'>
                                If the button does not work, copy and paste this link into your browser:
                            </p>
                            <p style='margin: 0; font-family: Arial, sans-serif; font-size: 13px; line-height: 1.7; word-break: break-all; color: #1d4ed8;'>
                                <a href='{$safeResetLink}' style='color: #1d4ed8; text-decoration: none;'>{$safeResetLink}</a>
                            </p>
                        ",
                        'info'
                    ),
                'footer_html' => "
                    <p style='margin: 0; font-family: Arial, sans-serif; font-size: 12px; line-height: 1.7; color: #64748b;'>
                        If you did not request a password reset, you can safely ignore this email. Your current password will remain unchanged.
                    </p>
                ",
            ]);

            $result_msg = mf_send_brevo_email($email, 'Password Reset Request', $htmlBody);
            
            if ($result_msg !== 'Email sent successfully.') {
                $email_send_error = $result_msg;
            }

            if (isset($email_send_error)) {
                $message_type = 'error';
                $message = 'Failed to send email. Error: ' . $email_send_error;
            } else {
                $message_type = 'success';
                $message = 'A password reset link has been sent to that email address.';
            }
        }
    } else {
        $message_type = 'error';
        $message = 'Invalid tenant or workspace link.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link href="https://fonts.googleapis.com/css2?family=<?php echo urlencode($theme_font); ?>:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
        
        .login-container { background: var(--card-bg); width: 100%; max-width: 440px; border-radius: 16px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1); padding: 48px 40px; text-align: center; }
        
        .header h1 { font-size: 1.5rem; color: var(--text-main); margin-bottom: 8px; }
        .header p { color: var(--text-muted); font-size: 0.95rem; margin-bottom: 32px; line-height: 1.5; }
        
        .form-group { text-align: left; margin-bottom: 20px; }
        .form-group label { display: block; font-size: 0.85rem; font-weight: 500; color: var(--text-main); margin-bottom: 8px; }
        .form-control { width: 100%; padding: 12px 16px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.95rem; color: var(--text-main); transition: all 0.2s ease; }
        .form-control:focus { outline: none; border-color: var(--brand-color); box-shadow: 0 0 0 3px color-mix(in srgb, var(--brand-color) 20%, transparent); }
        
        .btn-submit { width: 100%; padding: 12px 24px; background: var(--brand-color); color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: 500; cursor: pointer; transition: all 0.2s ease; margin-top: 12px; }
        .btn-submit:hover { filter: brightness(0.9); transform: translateY(-1px); }
        
        .back-link { display: inline-block; margin-top: 24px; color: var(--text-muted); font-size: 0.9rem; text-decoration: none; font-weight: 500; }
        .back-link:hover { color: var(--text-main); }
        
        .alert { padding: 12px 16px; border-radius: 8px; font-size: 0.9rem; margin-bottom: 24px; text-align: left; }
        .alert-success { background-color: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
        .alert-error { background-color: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
    </style>
</head>
<body>

<div class="login-container">
    <div class="header">
        <h1>Forgot Password?</h1>
        <p>Enter the email address associated with your <?php echo htmlspecialchars($tenant_name); ?> account, and we'll send you a link to reset your password.</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" class="form-control" placeholder="staff@example.com" required>
        </div>

        <button type="submit" class="btn-submit">Send Reset Link</button>
    </form>

    <a href="login.php<?php echo $site_slug ? '?s='.urlencode($site_slug) : ''; ?>" class="back-link">
        &larr; Back to Login
    </a>
</div>

</body>
</html>

