<?php
require_once '../auth/session_auth.php';
mf_start_backend_session();
require_once '../config/db_connect.php';

header('Content-Type: application/json');

function profile_email_change_respond(string $status, string $message, int $httpCode = 200, array $extra = []): void
{
    http_response_code($httpCode);
    echo json_encode(array_merge([
        'status' => $status,
        'message' => $message,
    ], $extra));
    exit;
}

function profile_email_change_clear_state(): void
{
    unset(
        $_SESSION['admin_profile_email_change_pending_email'],
        $_SESSION['admin_profile_email_change_pending_user_id'],
        $_SESSION['admin_profile_email_change_pending_tenant_id'],
        $_SESSION['admin_profile_email_change_verified_email'],
        $_SESSION['admin_profile_email_change_verified_user_id'],
        $_SESSION['admin_profile_email_change_verified_tenant_id']
    );
}

function profile_email_change_validate_target_email(PDO $pdo, string $tenantId, int $userId, string $currentEmail, string $email): ?array
{
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['message' => 'Please enter a valid email address.', 'code' => 'invalid_email'];
    }

    if ($currentEmail !== '' && strcasecmp($email, $currentEmail) === 0) {
        return ['message' => 'Please enter a different email address from your current one.', 'code' => 'same_email'];
    }

    $duplicateEmailStmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE tenant_id = ? AND email = ? AND user_id != ?');
    $duplicateEmailStmt->execute([$tenantId, $email, $userId]);
    if ((int)$duplicateEmailStmt->fetchColumn() > 0) {
        return ['message' => 'That email address is already being used by another account in this tenant.', 'code' => 'duplicate_email'];
    }

    return null;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    profile_email_change_respond('error', 'Method not allowed.', 405);
}

mf_require_tenant_session($pdo, [
    'response' => 'json',
    'message' => 'Unauthorized.',
]);

$rawPayload = file_get_contents('php://input');
$payload = json_decode($rawPayload, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$action = trim((string)($payload['action'] ?? ''));
$userId = (int)($_SESSION['user_id'] ?? 0);
$tenantId = trim((string)($_SESSION['tenant_id'] ?? ''));
$tenantName = trim((string)($_SESSION['tenant_name'] ?? 'MicroFin'));

if ($userId <= 0 || $tenantId === '') {
    profile_email_change_respond('error', 'Unauthorized.', 401);
}

$currentProfileStmt = $pdo->prepare('SELECT email FROM users WHERE user_id = ? AND tenant_id = ? LIMIT 1');
$currentProfileStmt->execute([$userId, $tenantId]);
$currentProfile = $currentProfileStmt->fetch(PDO::FETCH_ASSOC);

if (!$currentProfile) {
    profile_email_change_respond('error', 'Unable to load your profile.', 404);
}

$currentEmail = trim((string)($currentProfile['email'] ?? ''));

if ($action === 'clear_state') {
    profile_email_change_clear_state();
    profile_email_change_respond('success', 'Email change state cleared.');
}

if ($action === 'check_email') {
    $email = trim((string)($payload['email'] ?? ''));
    $validationError = profile_email_change_validate_target_email($pdo, $tenantId, $userId, $currentEmail, $email);
    if ($validationError !== null) {
        profile_email_change_respond('error', $validationError['message'], 422, ['code' => $validationError['code']]);
    }

    profile_email_change_respond('success', 'This email address is available in your workspace.', 200, [
        'code' => 'available_email',
    ]);
}

if ($action === 'send_otp') {
    $email = trim((string)($payload['email'] ?? ''));
    profile_email_change_clear_state();

    $validationError = profile_email_change_validate_target_email($pdo, $tenantId, $userId, $currentEmail, $email);
    if ($validationError !== null) {
        profile_email_change_respond('error', $validationError['message'], 422, ['code' => $validationError['code']]);
    }

    $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    try {
        $pdo->prepare("UPDATE otp_verifications SET status = 'Expired' WHERE email = ? AND status = 'Pending'")->execute([$email]);

        $insertOtpStmt = $pdo->prepare("INSERT INTO otp_verifications (email, otp_code, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 5 MINUTE))");
        $insertOtpStmt->execute([$email, $otp]);

        $otpHtml = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');
        $tenantLabel = $tenantName !== '' ? $tenantName : 'MicroFin';
        $emailBody = mf_email_template([
            'accent' => '#2563eb',
            'eyebrow' => 'Profile Security',
            'title' => 'Verify your new email address',
            'preheader' => "Use {$otp} to confirm your new email address for {$tenantLabel}.",
            'intro_html' => '
                <p style="margin: 0 0 14px; font-family: Arial, sans-serif; font-size: 15px; line-height: 1.7; color: #334155;">
                    A request was made to change the email address on your admin profile. Use the verification code below to confirm this new email address.
                </p>
            ',
            'body_html' => mf_email_panel(
                'One-Time Password',
                '
                    <div style="padding: 6px 0 2px; text-align: center;">
                        <div style="display: inline-block; padding: 14px 20px; background: #ffffff; border: 1px dashed #93c5fd; border-radius: 16px; font-family: Arial, sans-serif; font-size: 30px; font-weight: 800; letter-spacing: 0.28em; color: #1d4ed8;">
                            ' . $otpHtml . '
                        </div>
                    </div>
                    <p style="margin: 16px 0 0; font-family: Arial, sans-serif; font-size: 14px; line-height: 1.7; color: #334155; text-align: center;">
                        This code will expire in <strong>5 minutes</strong>.
                    </p>
                ',
                'info'
            ),
            'footer_html' => '
                <p style="margin: 0; font-family: Arial, sans-serif; font-size: 12px; line-height: 1.7; color: #64748b;">
                    Do not share this verification code with anyone. If you did not request this change, you can ignore this email.
                </p>
            ',
        ]);

        $emailResult = mf_send_brevo_email($email, $tenantLabel . ' - Verify your new email address', $emailBody);

        if ($emailResult !== 'Email sent successfully.') {
            $pdo->prepare("UPDATE otp_verifications SET status = 'Expired' WHERE email = ? AND otp_code = ? AND status = 'Pending'")->execute([$email, $otp]);
            profile_email_change_respond('error', 'Unable to send the OTP email right now. Please try again later.', 500, ['code' => 'email_send_failed']);
        }

        $_SESSION['admin_profile_email_change_pending_email'] = $email;
        $_SESSION['admin_profile_email_change_pending_user_id'] = $userId;
        $_SESSION['admin_profile_email_change_pending_tenant_id'] = $tenantId;

        profile_email_change_respond('success', 'OTP sent. Check your new email address for the 6-digit code.');
    } catch (Throwable $e) {
        profile_email_change_respond('error', 'Unable to generate an OTP right now. Please try again later.', 500, ['code' => 'otp_generation_failed']);
    }
}

if ($action === 'verify_otp') {
    $email = trim((string)($payload['email'] ?? ''));
    $otpCode = preg_replace('/\D+/', '', (string)($payload['otp_code'] ?? ''));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        profile_email_change_respond('error', 'Please enter the email address you are verifying.', 422, ['code' => 'missing_email']);
    }

    if (strlen($otpCode) !== 6) {
        profile_email_change_respond('error', 'Please enter the complete 6-digit OTP.', 422, ['code' => 'invalid_otp']);
    }

    $pendingEmail = trim((string)($_SESSION['admin_profile_email_change_pending_email'] ?? ''));
    $pendingUserId = (int)($_SESSION['admin_profile_email_change_pending_user_id'] ?? 0);
    $pendingTenantId = trim((string)($_SESSION['admin_profile_email_change_pending_tenant_id'] ?? ''));

    if (
        $pendingEmail === ''
        || strcasecmp($pendingEmail, $email) !== 0
        || $pendingUserId !== $userId
        || strcasecmp($pendingTenantId, $tenantId) !== 0
    ) {
        profile_email_change_respond('error', 'Please request a fresh OTP for this email address first.', 422, ['code' => 'otp_not_requested']);
    }

    $validationError = profile_email_change_validate_target_email($pdo, $tenantId, $userId, $currentEmail, $email);
    if ($validationError !== null) {
        profile_email_change_clear_state();
        profile_email_change_respond('error', $validationError['message'], 422, ['code' => $validationError['code']]);
    }

    $verifyStmt = $pdo->prepare("SELECT otp_id, (expires_at < NOW()) AS is_expired FROM otp_verifications WHERE email = ? AND otp_code = ? AND status = 'Pending' ORDER BY otp_id DESC LIMIT 1");
    $verifyStmt->execute([$email, $otpCode]);
    $otpRecord = $verifyStmt->fetch(PDO::FETCH_ASSOC);

    if (!$otpRecord) {
        profile_email_change_respond('error', 'Invalid OTP or email address.', 422, ['code' => 'otp_mismatch']);
    }

    if (!empty($otpRecord['is_expired'])) {
        $pdo->prepare("UPDATE otp_verifications SET status = 'Expired' WHERE otp_id = ?")->execute([(int)$otpRecord['otp_id']]);
        profile_email_change_respond('error', 'OTP has expired. Please request a new one.', 422, ['code' => 'otp_expired']);
    }

    $pdo->prepare("UPDATE otp_verifications SET status = 'Verified' WHERE otp_id = ?")->execute([(int)$otpRecord['otp_id']]);
    $pdo->prepare("UPDATE otp_verifications SET status = 'Expired' WHERE email = ? AND status = 'Pending' AND otp_id != ?")->execute([$email, (int)$otpRecord['otp_id']]);

    $_SESSION['admin_profile_email_change_verified_email'] = $email;
    $_SESSION['admin_profile_email_change_verified_user_id'] = $userId;
    $_SESSION['admin_profile_email_change_verified_tenant_id'] = $tenantId;

    profile_email_change_respond('success', 'Email verified successfully. You can now save your profile changes.');
}

profile_email_change_respond('error', 'Invalid request.', 422);
