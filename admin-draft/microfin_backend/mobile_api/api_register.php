<?php
require_once __DIR__ . '/api_utils.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/email_service.php';
require_once __DIR__ . '/auth_identity.php';

microfin_api_bootstrap();
microfin_require_post();

$data = microfin_read_json_input();

$baseUsernameInput = microfin_clean_string($data['base_username'] ?? $data['username'] ?? '');
$email = microfin_clean_string($data['email'] ?? '');
$password = (string) ($data['password'] ?? '');
$firstName = microfin_clean_string($data['first_name'] ?? '');
$middleName = microfin_clean_string($data['middle_name'] ?? '');
$lastName = microfin_clean_string($data['last_name'] ?? '');
$suffix = microfin_clean_string($data['suffix'] ?? '');
$tenantContextToken = microfin_clean_string($data['tenant_context_token'] ?? '');

if ($baseUsernameInput === '' || $email === '' || $password === '' || $firstName === '' || $lastName === '') {
    microfin_json_response(['success' => false, 'message' => 'Required fields are missing'], 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    microfin_json_response(['success' => false, 'message' => 'Please enter a valid email address.'], 422);
}

try {
    $tenant = microfin_identity_resolve_tenant_context($conn, $data);
    if (!is_array($tenant)) {
        microfin_json_response(['success' => false, 'message' => 'A valid tenant reference is required before registration.'], 422);
    }

    $tenantId = (string) ($tenant['tenant_id'] ?? '');
    $tenantSlug = (string) ($tenant['tenant_slug'] ?? '');

    $baseUsername = mf_mobile_identity_normalize_username_base($baseUsernameInput);
    if (!mf_mobile_identity_is_valid_username_base($baseUsername)) {
        microfin_json_response([
            'success' => false,
            'message' => 'Choose a username using 3-50 letters, numbers, dots, hyphens, or underscores only.',
        ], 422);
    }

    $loginUsername = mf_mobile_identity_build_login_username($baseUsername, $tenantSlug);
    if ($loginUsername === '') {
        microfin_json_response(['success' => false, 'message' => 'Unable to build the final login username for this tenant.'], 422);
    }

    $existingStmt = $conn->prepare("
        SELECT user_id
        FROM users
        WHERE tenant_id = ?
          AND (username = ? OR email = ?)
          AND deleted_at IS NULL
        LIMIT 1
    ");
    $existingStmt->bind_param('sss', $tenantId, $baseUsername, $email);
    $existingStmt->execute();
    $existingFound = $existingStmt->get_result()->num_rows > 0;
    $existingStmt->close();

    if ($existingFound) {
        microfin_json_response(['success' => false, 'message' => 'Username or email already exists for this tenant.'], 409);
    }

    // Generate OTP and verification token (NOT saving to DB yet)
    $verificationCode = microfin_generate_one_time_code();
    $verificationToken = microfin_build_verification_token($verificationCode, 15);

    // Build a signed pending-registration payload so we can create the account
    // only after OTP verification succeeds.
    $pendingRegistration = json_encode([
        'tenant_id'      => $tenantId,
        'tenant_slug'    => $tenantSlug,
        'base_username'  => $baseUsername,
        'email'          => $email,
        'password'       => $password,
        'first_name'     => $firstName,
        'middle_name'    => $middleName,
        'last_name'      => $lastName,
        'suffix'         => $suffix,
        'verification'   => $verificationToken,
        'created_at'     => gmdate('Y-m-d\TH:i:s\Z'),
    ]);

    // HMAC-sign the payload to prevent tampering
    $hmacKey = microfin_config('APP_SECRET_KEY', 'microfin-default-secret-key-change-me');
    $signature = hash_hmac('sha256', $pendingRegistration, $hmacKey);
    $pendingToken = base64_encode($pendingRegistration) . '.' . $signature;

    // Send the OTP email (no user_id yet since account is not created)
    $emailResult = microfin_send_registration_otp_email($conn, [
        'tenant_id'      => $tenantId,
        'tenant_name'    => $tenant['tenant_name'],
        'user_id'        => null,
        'to_email'       => $email,
        'recipient_name' => trim($firstName . ' ' . $lastName),
        'otp'            => $verificationCode,
        'ttl_minutes'    => 15,
    ]);

    if (!$emailResult['success']) {
        throw new RuntimeException('Unable to send verification email: ' . ($emailResult['message'] ?? 'Unknown email error.'));
    }

    microfin_json_response([
        'success'              => true,
        'requires_otp'         => true,
        'message'              => 'Verification code sent to your email. Please verify before your account is created.',
        'login_username'       => $loginUsername,
        'pending_token'        => $pendingToken,
        'tenant_context_token' => $tenantContextToken !== '' ? $tenantContextToken : microfin_identity_issue_tenant_context_token($tenant),
    ]);
} catch (Throwable $e) {
    microfin_json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
