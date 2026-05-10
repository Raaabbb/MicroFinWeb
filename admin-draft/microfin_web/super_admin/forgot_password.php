<?php
require_once '../../microfin_backend/auth/session_auth.php';
mf_start_backend_session();
require_once '../../microfin_backend/config/db_connect.php';

if (mf_refresh_backend_session_state($pdo, 'super_admin') && isset($_SESSION['super_admin_logged_in']) && $_SESSION['super_admin_logged_in'] === true) {
    $destination = !empty($_SESSION['super_admin_force_password_change'])
        ? 'force_change_password.php'
        : (!empty($_SESSION['super_admin_onboarding_required']) ? 'onboarding_profile.php' : 'super_admin.php');
    header('Location: ' . $destination);
    exit;
}

$platformLogoFile = __DIR__ . '/logo/MicroFin-logo-transparent-temp.png';
$platformLogoUrl = '../public_website/logo/MicroFin-logo-transparent-temp.png?v=' . urlencode((string) @filemtime($platformLogoFile));

function sa_password_reset_link(string $token): string
{
    $explicitBase = trim((string)(getenv('APP_BASE_URL') ?: getenv('PUBLIC_BASE_URL') ?: ''));
    if ($explicitBase !== '') {
        return rtrim($explicitBase, '/') . '/super_admin/reset_password.php?token=' . urlencode($token);
    }

    $forwardedProto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $forwardedProto === 'https';
    $protocol = $isHttps ? 'https://' : 'http://';
    $domainName = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/microfin_platform/super_admin/forgot_password.php'));
    $resetPath = rtrim($scriptDir, '/') . '/reset_password.php';

    return $protocol . $domainName . $resetPath . '?token=' . urlencode($token);
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));

    if ($email === '') {
        $message_type = 'error';
        $message = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message_type = 'error';
        $message = 'Please enter a valid email address.';
    } else {
        $stmt = $pdo->prepare("
            SELECT user_id, username, first_name, last_name, status
            FROM users
            WHERE email = ?
              AND user_type = 'Super Admin'
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $superAdmin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$superAdmin) {
            $message_type = 'error';
            $message = 'No super admin account found with that email address.';
        } else {
            $status = trim((string)($superAdmin['status'] ?? ''));
            if (!in_array($status, ['Active', 'Inactive'], true)) {
                $message_type = 'error';
                $message = 'This account is not eligible for password reset while its status is ' . strtolower($status) . '.';
            } else {
                $token = bin2hex(random_bytes(32));
                $update = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE user_id = ?");
                $update->execute([$token, $superAdmin['user_id']]);

                $name = trim((string)($superAdmin['first_name'] ?? '') . ' ' . (string)($superAdmin['last_name'] ?? ''));
                if ($name === '') {
                    $name = trim((string)($superAdmin['username'] ?? 'Super Admin'));
                }

                $resetLink = sa_password_reset_link($token);
                $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
                $safeResetLink = htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8');
                $htmlBody = mf_email_template([
                    'accent' => '#1f8a5a',
                    'eyebrow' => 'Platform Owner Access',
                    'title' => 'Reset Your Super Admin Password',
                    'preheader' => 'Password reset instructions for your MicroFin platform owner account.',
                    'intro_html' => "
                        <p style='margin: 0 0 14px; font-family: Arial, sans-serif; font-size: 15px; line-height: 1.7; color: #334155;'>
                            Hello {$safeName},
                        </p>
                        <p style='margin: 0 0 14px; font-family: Arial, sans-serif; font-size: 15px; line-height: 1.7; color: #334155;'>
                            We received a request to reset your MicroFin platform owner password.
                        </p>
                        <p style='margin: 0 0 14px; font-family: Arial, sans-serif; font-size: 15px; line-height: 1.7; color: #334155;'>
                            Use the button below to set a new password. This link will expire in 1 hour.
                        </p>
                    ",
                    'body_html' => mf_email_button('Reset Password', $resetLink, '#1f8a5a')
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
                            If you did not request this reset, you can safely ignore this message and your current password will remain unchanged.
                        </p>
                    ",
                ]);

                $result_msg = mf_send_brevo_email($email, 'MicroFin - Super Admin Password Reset', $htmlBody);
                if ($result_msg === 'Email sent successfully.') {
                    $message_type = 'success';
                    $message = 'A password reset link has been sent to that email address.';
                } else {
                    $message_type = 'error';
                    $message = 'Failed to send email. Error: ' . $result_msg;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars((string)($_SESSION['ui_theme'] ?? 'light'), ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MicroFin | Forgot Password</title>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($platformLogoUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($platformLogoUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="super_admin_theme.css">
    <link rel="stylesheet" href="super_admin_auth.css">
</head>
<body class="platform-auth auth-compact">
    <button type="button" class="auth-theme-toggle" id="auth-theme-toggle" aria-label="Switch to dark mode">Dark mode</button>
    <div class="card">
        <h1>Forgot Password?</h1>
        <p>Enter your super admin email address and we'll check the account, verify its status, and send you a reset link.</p>

        <?php if ($message !== ''): ?>
        <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <form method="POST">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" placeholder="superadmin@microfin.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
            <button type="submit" class="btn-submit">Send Reset Link</button>
        </form>

        <a href="login.php" class="back-link">&larr; Back to Login</a>
    </div>
    <script src="login.js"></script>
</body>
</html>

