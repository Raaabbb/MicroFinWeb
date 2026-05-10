<?php
require_once __DIR__ . '/api_utils.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/email_service.php';
require_once __DIR__ . '/auth_identity.php';

microfin_api_bootstrap();
microfin_require_post();

$data = microfin_read_json_input();
$email = microfin_clean_string($data['email'] ?? '');

if ($email === '') {
    microfin_json_response(['success' => false, 'message' => 'Email is required.'], 422);
}

try {
    $stmt = $conn->prepare("
        SELECT
            u.user_id,
            u.username,
            u.email,
            u.first_name,
            u.last_name,
            t.tenant_id,
            t.tenant_name,
            t.tenant_slug
        FROM users u
        INNER JOIN clients c
            ON c.user_id = u.user_id
           AND c.tenant_id = u.tenant_id
        INNER JOIN tenants t
            ON t.tenant_id = u.tenant_id
        WHERE u.email = ?
          AND u.user_type = 'Client'
          AND u.deleted_at IS NULL
          AND c.deleted_at IS NULL
          AND c.client_status = 'Active'
          AND t.deleted_at IS NULL
          AND LOWER(COALESCE(t.status, '')) = 'active'
        ORDER BY t.tenant_name ASC, u.username ASC
    ");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    $accounts = [];
    $recipientName = '';
    while ($row = $result->fetch_assoc()) {
        if ($recipientName === '') {
            $recipientName = trim(((string) ($row['first_name'] ?? '')) . ' ' . ((string) ($row['last_name'] ?? '')));
        }
        $accounts[] = [
            'tenant_id' => (string) ($row['tenant_id'] ?? ''),
            'tenant_name' => (string) ($row['tenant_name'] ?? 'MicroFin'),
            'tenant_slug' => (string) ($row['tenant_slug'] ?? ''),
            'login_username' => mf_mobile_identity_build_login_username((string) ($row['username'] ?? ''), (string) ($row['tenant_slug'] ?? '')),
        ];
    }
    $stmt->close();

    if (empty($accounts)) {
        microfin_json_response([
            'success' => true,
            'message' => 'If that email is linked to an active account, a recovery email will be sent shortly.',
            'accounts' => [],
        ]);
    }

    $emailResult = microfin_send_account_lookup_email($conn, [
        'to_email' => $email,
        'recipient_name' => $recipientName,
        'login_usernames' => array_column($accounts, 'login_username'),
    ]);

    if (!$emailResult['success']) {
        throw new RuntimeException($emailResult['message'] ?? 'Unable to send the account lookup email.');
    }

    microfin_json_response([
        'success' => true,
        'message' => 'If that email is linked to an active account, a recovery email will be sent shortly.',
        'accounts' => $accounts,
    ]);
} catch (Throwable $e) {
    microfin_json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
