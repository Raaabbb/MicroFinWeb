<?php
require_once __DIR__ . '/api_utils.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/email_service.php';
require_once __DIR__ . '/auth_identity.php';

microfin_api_bootstrap();
microfin_require_post();

$data = microfin_read_json_input();

$email = microfin_clean_string($data['email'] ?? '');
$loginUsername = microfin_clean_string($data['login_username'] ?? '');
$otp = microfin_clean_string($data['otp'] ?? '');
$pendingToken = microfin_clean_string($data['pending_token'] ?? '');

if ($email === '' || $otp === '') {
    microfin_json_response(['success' => false, 'message' => 'Required fields are missing'], 422);
}

// ── New flow: pending_token present → OTP-first registration ──
if ($pendingToken !== '') {
    try {
        // Decode and verify the signed pending registration token
        $dotPos = strrpos($pendingToken, '.');
        if ($dotPos === false) {
            microfin_json_response(['success' => false, 'message' => 'Invalid registration token.'], 422);
        }

        $encodedPayload = substr($pendingToken, 0, $dotPos);
        $signature = substr($pendingToken, $dotPos + 1);
        $rawPayload = base64_decode($encodedPayload, true);

        if ($rawPayload === false) {
            microfin_json_response(['success' => false, 'message' => 'Invalid registration token encoding.'], 422);
        }

        $hmacKey = microfin_config('APP_SECRET_KEY', 'microfin-default-secret-key-change-me');
        $expectedSignature = hash_hmac('sha256', $rawPayload, $hmacKey);

        if (!hash_equals($expectedSignature, $signature)) {
            microfin_json_response(['success' => false, 'message' => 'Registration token has been tampered with.'], 422);
        }

        $pending = json_decode($rawPayload, true);
        if (!is_array($pending) || empty($pending['email']) || empty($pending['verification'])) {
            microfin_json_response(['success' => false, 'message' => 'Registration token is malformed.'], 422);
        }

        // Ensure the email in the token matches the email submitted
        if (strcasecmp(trim($pending['email']), $email) !== 0) {
            microfin_json_response(['success' => false, 'message' => 'Email mismatch with the registration token.'], 422);
        }

        $verificationToken = $pending['verification'];

        // Check expiry
        if (microfin_verification_token_is_expired($verificationToken)) {
            microfin_json_response(['success' => false, 'message' => 'This verification code has expired. Please register again.'], 422);
        }

        // Verify the OTP
        if (!microfin_verify_verification_code($verificationToken, $otp)) {
            microfin_json_response(['success' => false, 'message' => 'The verification code you entered is invalid.'], 422);
        }

        // ── OTP verified — now create the account ──
        $tenantId     = (string) ($pending['tenant_id'] ?? '');
        $tenantSlug   = (string) ($pending['tenant_slug'] ?? '');
        $baseUsername  = (string) ($pending['base_username'] ?? '');
        $firstName    = (string) ($pending['first_name'] ?? '');
        $middleName   = (string) ($pending['middle_name'] ?? '');
        $lastName     = (string) ($pending['last_name'] ?? '');
        $suffix       = (string) ($pending['suffix'] ?? '');
        $password     = (string) ($pending['password'] ?? '');

        if ($tenantId === '' || $baseUsername === '' || $password === '') {
            microfin_json_response(['success' => false, 'message' => 'Registration token is missing required data.'], 422);
        }

        // Re-validate that username/email are still available (race condition guard)
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

        $conn->begin_transaction();

        // Get or create the Client role
        $roleStmt = $conn->prepare("
            SELECT role_id
            FROM user_roles
            WHERE role_name = 'Client'
              AND tenant_id = ?
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $roleStmt->bind_param('s', $tenantId);
        $roleStmt->execute();
        $roleResult = $roleStmt->get_result();

        if ($roleResult->num_rows === 0) {
            $insertRoleStmt = $conn->prepare("
                INSERT INTO user_roles (tenant_id, role_name, role_description)
                VALUES (?, 'Client', 'Default Client Role')
            ");
            $insertRoleStmt->bind_param('s', $tenantId);
            $insertRoleStmt->execute();
            $roleId = $insertRoleStmt->insert_id;
            $insertRoleStmt->close();
        } else {
            $roleId = (int) $roleResult->fetch_assoc()['role_id'];
        }
        $roleStmt->close();

        $passwordHash = password_hash($password, PASSWORD_ARGON2ID);

        // Create the user with email already verified
        $userStmt = $conn->prepare("
            INSERT INTO users (
                tenant_id,
                username,
                email,
                password_hash,
                email_verified,
                first_name,
                last_name,
                middle_name,
                suffix,
                role_id,
                user_type,
                status,
                verification_token
            ) VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?, ?, 'Client', 'Active', NULL)
        ");
        $userStmt->bind_param(
            'ssssssssi',
            $tenantId,
            $baseUsername,
            $email,
            $passwordHash,
            $firstName,
            $lastName,
            $middleName,
            $suffix,
            $roleId
        );
        $userStmt->execute();
        $userId = $conn->insert_id;
        $userStmt->close();

        $clientCode = 'CLT' . date('Y') . '-' . str_pad((string) $userId, 5, '0', STR_PAD_LEFT);
        $clientStmt = $conn->prepare("
            INSERT INTO clients (
                user_id,
                tenant_id,
                client_code,
                first_name,
                middle_name,
                last_name,
                suffix,
                date_of_birth,
                contact_number,
                email_address,
                client_status,
                registration_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, '1990-01-01', '', ?, 'Active', CURDATE())
        ");
        $clientStmt->bind_param(
            'isssssss',
            $userId,
            $tenantId,
            $clientCode,
            $firstName,
            $middleName,
            $lastName,
            $suffix,
            $email
        );
        $clientStmt->execute();
        $clientStmt->close();

        $conn->commit();

        $finalLoginUsername = mf_mobile_identity_build_login_username($baseUsername, $tenantSlug);

        microfin_json_response([
            'success'        => true,
            'message'        => 'Email verified and account created successfully.',
            'login_username' => $finalLoginUsername,
        ]);
    } catch (Throwable $e) {
        if ($conn->connect_errno === 0) {
            try {
                $conn->rollback();
            } catch (Throwable $rollbackError) {
            }
        }

        microfin_json_response(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

// ── Legacy flow: no pending_token → verify an already-created user row ──
try {
    $tenant = null;
    if ($loginUsername !== '') {
        $parsedLogin = mf_mobile_identity_parse_login_username($loginUsername);
        if (!is_array($parsedLogin)) {
            microfin_json_response(['success' => false, 'message' => 'The login username format is invalid.'], 422);
        }
        $tenant = microfin_identity_query_active_tenant($conn, (string) $parsedLogin['tenant_slug']);
    } else {
        $tenant = microfin_identity_resolve_tenant_context($conn, $data);
    }

    if (!is_array($tenant)) {
        microfin_json_response(['success' => false, 'message' => 'Unable to verify the tenant for this registration.'], 422);
    }

    $tenantId = (string) ($tenant['tenant_id'] ?? '');
    $tenantSlug = (string) ($tenant['tenant_slug'] ?? '');

    $userStmt = $conn->prepare("
        SELECT user_id, username, email_verified, verification_token
        FROM users
        WHERE email = ?
          AND tenant_id = ?
          AND user_type = 'Client'
          AND deleted_at IS NULL
        ORDER BY user_id DESC
        LIMIT 1
    ");
    $userStmt->bind_param('ss', $email, $tenantId);
    $userStmt->execute();
    $user = $userStmt->get_result()->fetch_assoc();
    $userStmt->close();

    if (!$user) {
        microfin_json_response(['success' => false, 'message' => 'No pending registration was found for this email.'], 404);
    }

    if ((int) ($user['email_verified'] ?? 0) === 1) {
        microfin_json_response(['success' => true, 'message' => 'Email is already verified.']);
    }

    if (microfin_verification_token_is_expired($user['verification_token'] ?? null)) {
        microfin_json_response(['success' => false, 'message' => 'This verification code has expired. Please register again.'], 422);
    }

    if (!microfin_verify_verification_code($user['verification_token'] ?? null, $otp)) {
        microfin_json_response(['success' => false, 'message' => 'The verification code you entered is invalid.'], 422);
    }

    $updateStmt = $conn->prepare("
        UPDATE users
        SET email_verified = 1, verification_token = NULL
        WHERE user_id = ?
          AND tenant_id = ?
          AND deleted_at IS NULL
        LIMIT 1
    ");
    $updateStmt->bind_param('is', $user['user_id'], $tenantId);
    $updateStmt->execute();
    $updateStmt->close();

    microfin_json_response([
        'success' => true,
        'message' => 'Email verified successfully.',
        'login_username' => mf_mobile_identity_build_login_username((string) ($user['username'] ?? ''), $tenantSlug),
    ]);
} catch (Throwable $e) {
    microfin_json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
