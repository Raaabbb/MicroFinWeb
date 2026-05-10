<?php
require_once __DIR__ . '/api_utils.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth_identity.php';

microfin_api_bootstrap();
microfin_require_post();

$data = microfin_read_json_input();

$loginUsername = microfin_clean_string($data['login_username'] ?? '');
$email = microfin_clean_string($data['email'] ?? '');
$resetCode = microfin_clean_string($data['reset_code'] ?? '');

if ($resetCode === '' || ($loginUsername === '' && ($email === '' || microfin_clean_string($data['tenant_id'] ?? '') === ''))) {
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
                u.username,
                u.reset_token,
                u.reset_token_expiry
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
                u.username,
                u.reset_token,
                u.reset_token_expiry
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

    if (!$user || empty($user['reset_token'])) {
        microfin_json_response(['success' => false, 'message' => 'No active reset request was found for this account.'], 404);
    }

    $expiresAt = strtotime((string) ($user['reset_token_expiry'] ?? ''));
    if ($expiresAt === false || $expiresAt < time()) {
        microfin_json_response(['success' => false, 'message' => 'This reset code has expired. Please request a new one.'], 422);
    }

    if (!password_verify($resetCode, (string) $user['reset_token'])) {
        microfin_json_response(['success' => false, 'message' => 'The reset code you entered is invalid.'], 422);
    }

    microfin_json_response([
        'success' => true,
        'message' => 'Reset code verified.',
        'login_username' => mf_mobile_identity_build_login_username((string) ($user['username'] ?? $baseUsername), $tenantSlug),
    ]);
} catch (Throwable $e) {
    microfin_json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
