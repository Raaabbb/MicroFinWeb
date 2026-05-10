<?php
require_once __DIR__ . '/api_utils.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/email_service.php';
require_once __DIR__ . '/auth_identity.php';

microfin_api_bootstrap();
microfin_require_post();

$data = microfin_read_json_input();

$loginUsername = microfin_clean_string($data['login_username'] ?? '');
$email = microfin_clean_string($data['email'] ?? '');

if ($loginUsername === '' && ($email === '' || microfin_clean_string($data['tenant_id'] ?? '') === '')) {
    microfin_json_response(['success' => false, 'message' => 'Required fields are missing'], 422);
}

try {
    $tenant = null;
    $baseUsername = '';
    if ($loginUsername !== '') {
        $parsedLogin = mf_mobile_identity_parse_login_username($loginUsername);
        if (!is_array($parsedLogin)) {
            microfin_json_response(['success' => false, 'message' => 'Enter the exact login username in the format username@tenant.'], 422);
        }

        $tenant = microfin_identity_query_active_tenant($conn, (string) $parsedLogin['tenant_slug']);
        $baseUsername = (string) $parsedLogin['base_username'];
    } else {
        $tenant = microfin_identity_resolve_tenant_context($conn, $data);
    }

    if (!is_array($tenant)) {
        microfin_json_response(['success' => false, 'message' => 'Tenant was not found or is inactive.'], 404);
    }

    $tenantId = (string) ($tenant['tenant_id'] ?? '');
    $tenantSlug = (string) ($tenant['tenant_slug'] ?? '');

    if ($loginUsername !== '') {
        $userStmt = $conn->prepare("
            SELECT
                u.user_id,
                u.username,
                u.first_name,
                u.last_name,
                u.email
            FROM users u
            INNER JOIN clients c
                ON c.user_id = u.user_id
               AND c.tenant_id = u.tenant_id
            WHERE u.username = ?
              AND u.tenant_id = ?
              AND u.user_type = 'Client'
              AND u.deleted_at IS NULL
              AND c.deleted_at IS NULL
              AND c.client_status = 'Active'
            LIMIT 1
        ");
        $userStmt->bind_param('ss', $baseUsername, $tenantId);
    } else {
        $userStmt = $conn->prepare("
            SELECT
                u.user_id,
                u.username,
                u.first_name,
                u.last_name,
                u.email
            FROM users u
            INNER JOIN clients c
                ON c.user_id = u.user_id
               AND c.tenant_id = u.tenant_id
            WHERE u.email = ?
              AND u.tenant_id = ?
              AND u.user_type = 'Client'
              AND u.deleted_at IS NULL
              AND c.deleted_at IS NULL
              AND c.client_status = 'Active'
            LIMIT 1
        ");
        $userStmt->bind_param('ss', $email, $tenantId);
    }
    $userStmt->execute();
    $user = $userStmt->get_result()->fetch_assoc();
    $userStmt->close();

    if (!$user) {
        microfin_json_response(['success' => false, 'message' => 'No active account matches that recovery request.'], 404);
    }

    $resetCode = microfin_generate_one_time_code();
    $resetToken = password_hash($resetCode, PASSWORD_DEFAULT);
    $resetExpiry = date('Y-m-d H:i:s', time() + (15 * 60));

    $conn->begin_transaction();

    $updateStmt = $conn->prepare("
        UPDATE users
        SET reset_token = ?, reset_token_expiry = ?
        WHERE user_id = ?
          AND tenant_id = ?
          AND deleted_at IS NULL
        LIMIT 1
    ");
    $updateStmt->bind_param('ssis', $resetToken, $resetExpiry, $user['user_id'], $tenantId);
    $updateStmt->execute();
    $updateStmt->close();

    $emailResult = microfin_send_password_reset_email($conn, [
        'tenant_id' => $tenantId,
        'tenant_name' => $tenant['tenant_name'],
        'user_id' => $user['user_id'],
        'to_email' => (string) ($user['email'] ?? ''),
        'recipient_name' => trim(((string) ($user['first_name'] ?? '')) . ' ' . ((string) ($user['last_name'] ?? ''))),
        'otp' => $resetCode,
        'ttl_minutes' => 15,
        'login_username' => mf_mobile_identity_build_login_username((string) ($user['username'] ?? ''), $tenantSlug),
    ]);

    if (!$emailResult['success']) {
        throw new RuntimeException('Unable to send reset email: ' . ($emailResult['message'] ?? 'Unknown email error.'));
    }

    $conn->commit();

    microfin_json_response([
        'success' => true,
        'message' => 'Reset code sent to the email linked to that login username.',
        'login_username' => mf_mobile_identity_build_login_username((string) ($user['username'] ?? ''), $tenantSlug),
    ]);
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $rollbackError) {
    }

    microfin_json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
