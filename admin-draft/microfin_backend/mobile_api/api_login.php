<?php
require_once __DIR__ . '/api_utils.php';
microfin_api_bootstrap();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth_identity.php';

microfin_require_post();

function microfin_login_normalize_status(?string $status, ?string $documentStatus): string
{
    $status = trim((string) ($status ?? ''));
    if (in_array($status, ['Approved', 'Verified', 'Pending', 'Rejected', 'Unverified'], true)) {
        return $status;
    }

    $documentStatus = trim((string) ($documentStatus ?? ''));
    return match ($documentStatus) {
        'Approved', 'Verified', 'Rejected' => $documentStatus,
        default => 'Unverified',
    };
}

function microfin_login_has_client_column(mysqli $conn, string $column): bool
{
    static $cache = [];

    if (array_key_exists($column, $cache)) {
        return $cache[$column];
    }

    $stmt = $conn->prepare("
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'clients'
          AND COLUMN_NAME = ?
        LIMIT 1
    ");

    if (!$stmt) {
        $cache[$column] = false;
        return false;
    }

    $stmt->bind_param('s', $column);
    $stmt->execute();
    $cache[$column] = $stmt->get_result()->num_rows === 1;
    $stmt->close();

    return $cache[$column];
}

$data = microfin_read_json_input();
$password = (string) ($data['password'] ?? $data['pin'] ?? '');

if ($password === '') {
    microfin_json_response(['success' => false, 'message' => 'Password is required.'], 422);
}

$context = microfin_identity_resolve_login_context($conn, $data);
if (!is_array($context) || !is_array($context['tenant'] ?? null)) {
    microfin_json_response(['success' => false, 'message' => 'A valid login username is required.'], 422);
}

$tenant = $context['tenant'];
$tenantId = (string) ($tenant['tenant_id'] ?? '');
$tenantSlug = (string) ($tenant['tenant_slug'] ?? '');
$baseUsername = trim((string) ($context['base_username'] ?? ''));
$canonicalLoginUsername = mf_mobile_identity_build_login_username($baseUsername, $tenantSlug);
$isLegacyRequest = trim((string) ($data['login_username'] ?? '')) === '';

$verificationColumnExists = microfin_login_has_client_column($conn, 'verification_status');
$policyMetadataColumnExists = microfin_login_has_client_column($conn, 'policy_metadata');

$selectColumns = [
    'c.client_id',
    'u.user_id',
    'u.username',
    'u.password_hash',
    'u.status',
    'u.force_password_change',
    'u.first_name AS user_first_name',
    'u.last_name AS user_last_name',
    'u.email',
    'c.client_status',
    'c.first_name AS client_first_name',
    'c.last_name AS client_last_name',
    'c.document_verification_status',
    'c.credit_limit',
];
if ($verificationColumnExists) {
    $selectColumns[] = 'c.verification_status';
}
if ($policyMetadataColumnExists) {
    $selectColumns[] = 'c.policy_metadata';
}

if ($isLegacyRequest) {
    $legacyIdentifier = $baseUsername;
    $stmt = $conn->prepare("
        SELECT " . implode(', ', $selectColumns) . "
        FROM users u
        INNER JOIN clients c
            ON c.user_id = u.user_id
           AND c.tenant_id = u.tenant_id
        WHERE (u.username = ? OR u.email = ?)
          AND u.tenant_id = ?
          AND u.user_type = 'Client'
        LIMIT 1
    ");
    $stmt->bind_param('sss', $legacyIdentifier, $legacyIdentifier, $tenantId);
} else {
    $stmt = $conn->prepare("
        SELECT " . implode(', ', $selectColumns) . "
        FROM users u
        INNER JOIN clients c
            ON c.user_id = u.user_id
           AND c.tenant_id = u.tenant_id
        WHERE u.username = ?
          AND u.tenant_id = ?
          AND u.user_type = 'Client'
        LIMIT 1
    ");
    $stmt->bind_param('ss', $baseUsername, $tenantId);
}

$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user || !password_verify($password, (string) ($user['password_hash'] ?? ''))) {
    microfin_json_response(['success' => false, 'message' => 'Invalid login username or password.'], 401);
}

if (($user['status'] ?? '') !== 'Active') {
    microfin_json_response(['success' => false, 'message' => 'Account is not active.'], 403);
}

if (($user['client_status'] ?? '') !== 'Active') {
    microfin_json_response(['success' => false, 'message' => 'Client profile is not active.'], 403);
}

$firstName = trim((string) ($user['user_first_name'] ?? $user['client_first_name'] ?? ''));
$lastName = trim((string) ($user['user_last_name'] ?? $user['client_last_name'] ?? ''));
$verificationStatus = microfin_login_normalize_status(
    $user['verification_status'] ?? null,
    $user['document_verification_status'] ?? null
);

$currentLimit = (float) ($user['credit_limit'] ?? 0);
$clientId = (int) ($user['client_id'] ?? 0);
if ($clientId > 0) {
    try {
        global $dbConfig;
        $pdo = new PDO(
            "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset=utf8mb4",
            $dbConfig['username'],
            $dbConfig['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        require_once __DIR__ . '/../../microfin_backend/credit_policy.php';
        $profileSync = mf_sync_client_credit_profile($pdo, $tenantId, $clientId);
        if (isset($profileSync['client']['credit_limit'])) {
            $currentLimit = (float) $profileSync['client']['credit_limit'];
        }
    } catch (\Throwable $pe) {
        error_log('Failed syncing profile limit in login API: ' . $pe->getMessage());
    }
    // Fallback: if active credit_limit is 0, use potential_limit from policy_metadata
    if ($currentLimit <= 0 && $policyMetadataColumnExists) {
        $rawMeta = $user['policy_metadata'] ?? '{}';
        $policyMeta = is_string($rawMeta) ? (json_decode($rawMeta, true) ?: []) : ($rawMeta ?: []);
        $potentialLimit = (float) ($policyMeta['potential_limit'] ?? 0);
        if ($potentialLimit > 0) {
            $currentLimit = $potentialLimit;
        }
    }
}

microfin_json_response([
    'success' => true,
    'message' => 'Login successful!',
    'user_id' => (int) ($user['user_id'] ?? 0),
    'client_id' => $clientId,
    'first_name' => $firstName,
    'last_name' => $lastName,
    'email' => (string) ($user['email'] ?? ''),
    'force_password_change' => (int) ($user['force_password_change'] ?? 0) === 1,
    'verification_status' => $verificationStatus,
    'credit_limit' => $currentLimit,
    'login_username' => mf_mobile_identity_build_login_username((string) ($user['username'] ?? ''), $tenantSlug),
    'tenant' => microfin_identity_branding_payload($tenant),
    'policy_metadata' => json_decode($user['policy_metadata'] ?? '{}', true) ?: null,
]);
