<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../../microfin_backend/config/db_connect.php';
require_once '../../../microfin_backend/auth/login_activity.php';
require_once '../../../microfin_backend/auth/mobile_identity.php';

$raw_data = file_get_contents('php://input');
$data = json_decode($raw_data, true);
if (!is_array($data)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON body.']);
    exit;
}

$login_username = trim((string) ($data['login_username'] ?? ''));
$email = trim((string) ($data['email'] ?? ''));
$password = trim((string) ($data['password'] ?? ''));

// Backward compatibility: accept legacy pin field if password is not provided.
if ($password === '' && isset($data['pin'])) {
    $password = trim((string) $data['pin']);
}

$tenant_id = trim((string) ($data['tenant_id'] ?? ''));
$tenant_slug = trim((string) ($data['tenant_slug'] ?? ''));
$username = trim((string) ($data['username'] ?? ''));

if ($password === '' || ($login_username === '' && $email === '' && $username === '')) {
    echo json_encode(['status' => 'error', 'message' => 'Login username and password are required.']);
    exit;
}

try {
    $isCanonicalLogin = $login_username !== '';
    $baseUsername = '';

    if ($isCanonicalLogin) {
        $parsedLogin = mf_mobile_identity_parse_login_username($login_username);
        if (!is_array($parsedLogin)) {
            echo json_encode(['status' => 'error', 'message' => 'Enter the exact login username in the format username@tenant.']);
            exit;
        }

        $tenant_slug = (string) ($parsedLogin['tenant_slug'] ?? '');
        $baseUsername = (string) ($parsedLogin['base_username'] ?? '');
    } else {
        $baseUsername = $username !== '' ? $username : $email;
    }

    if ($tenant_id === '') {
        if ($tenant_slug === '') {
            echo json_encode(['status' => 'error', 'message' => 'Tenant identifier is required.']);
            exit;
        }

        $tenant_lookup = $pdo->prepare('SELECT tenant_id, tenant_name, status FROM tenants WHERE tenant_slug = ? LIMIT 1');
        $tenant_lookup->execute([$tenant_slug]);
        $tenant_row = $tenant_lookup->fetch(PDO::FETCH_ASSOC);

        if (!$tenant_row) {
            echo json_encode(['status' => 'error', 'message' => 'Tenant not found.']);
            exit;
        }

        if (($tenant_row['status'] ?? '') !== 'Active') {
            echo json_encode(['status' => 'error', 'message' => 'Tenant is not active.']);
            exit;
        }

        $tenant_id = (string) $tenant_row['tenant_id'];
    }

    if ($isCanonicalLogin) {
        $stmt = $pdo->prepare('
            SELECT
                u.user_id, u.username, u.tenant_id, u.email, u.password_hash, u.status,
                c.client_id, c.first_name AS client_first_name, c.last_name AS client_last_name, c.client_status,
                t.tenant_name, t.tenant_slug,
                b.logo_path, b.theme_primary_color, b.theme_secondary_color, b.theme_text_main, b.theme_text_muted, b.theme_bg_body, b.theme_bg_card, b.font_family
            FROM users u
            JOIN clients c ON c.user_id = u.user_id AND c.tenant_id = u.tenant_id
            JOIN tenants t ON t.tenant_id = u.tenant_id
            LEFT JOIN tenant_branding b ON b.tenant_id = t.tenant_id
            WHERE u.tenant_id = ?
              AND u.username = ?
              AND u.user_type = "Client"
              AND t.status = "Active"
              AND u.deleted_at IS NULL
              AND c.deleted_at IS NULL
            LIMIT 1
        ');
        $stmt->execute([$tenant_id, $baseUsername]);
    } else {
        $stmt = $pdo->prepare('
            SELECT
                u.user_id, u.username, u.tenant_id, u.email, u.password_hash, u.status,
                c.client_id, c.first_name AS client_first_name, c.last_name AS client_last_name, c.client_status,
                t.tenant_name, t.tenant_slug,
                b.logo_path, b.theme_primary_color, b.theme_secondary_color, b.theme_text_main, b.theme_text_muted, b.theme_bg_body, b.theme_bg_card, b.font_family
            FROM users u
            JOIN clients c ON c.user_id = u.user_id AND c.tenant_id = u.tenant_id
            JOIN tenants t ON t.tenant_id = u.tenant_id
            LEFT JOIN tenant_branding b ON b.tenant_id = t.tenant_id
            WHERE u.tenant_id = ?
              AND (u.email = ? OR u.username = ?)
              AND u.user_type = "Client"
              AND t.status = "Active"
              AND u.deleted_at IS NULL
              AND c.deleted_at IS NULL
            LIMIT 1
        ');
        $stmt->execute([$tenant_id, $email, $baseUsername]);
    }
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, (string) ($user['password_hash'] ?? ''))) {
        echo json_encode(['status' => 'error', 'message' => $isCanonicalLogin ? 'Invalid login username or password.' : 'Invalid login credentials.']);
        exit;
    }

    if (($user['status'] ?? '') !== 'Active') {
        echo json_encode(['status' => 'error', 'message' => 'This account is not active.']);
        exit;
    }

    if (($user['client_status'] ?? '') !== 'Active') {
        echo json_encode(['status' => 'error', 'message' => 'Client profile is not active.']);
        exit;
    }

    mf_update_user_last_login($pdo, (int) $user['user_id']);

    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $payload = json_encode([
        'user_id' => (int) $user['user_id'],
        'tenant_id' => (string) $user['tenant_id'],
        'email' => (string) $user['email'],
        'exp' => time() + (86400 * 30)
    ]);
    $mock_token = base64_encode($header) . '.' . base64_encode($payload) . '.MockSignature';

    echo json_encode([
        'status' => 'success',
        'message' => 'Login successful',
        'login_username' => mf_mobile_identity_build_login_username((string) ($user['username'] ?? $baseUsername), (string) ($user['tenant_slug'] ?? $tenant_slug)),
        'auth_token' => $mock_token,
        'global_user' => [
            'app_user_id' => (int) $user['user_id'],
            'full_name' => trim((string) $user['client_first_name'] . ' ' . (string) $user['client_last_name']),
            'email' => (string) $user['email']
        ],
        'user' => [
            'user_id' => (int) $user['user_id'],
            'client_id' => (int) $user['client_id'],
            'full_name' => trim((string) $user['client_first_name'] . ' ' . (string) $user['client_last_name']),
            'email' => (string) $user['email']
        ],
        'tenant' => [
            'tenant_id' => (string) $user['tenant_id'],
            'tenant_name' => (string) $user['tenant_name'],
            'tenant_slug' => (string) $user['tenant_slug'],
            'logo_path' => $user['logo_path'],
            'theme_primary_color' => $user['theme_primary_color'],
            'theme_secondary_color' => $user['theme_secondary_color']
        ],
        // Preserved shape for older clients expecting tenant_profiles.
        'tenant_profiles' => [[
            'user_id' => (int) $user['user_id'],
            'tenant_id' => (string) $user['tenant_id'],
            'status' => (string) $user['status'],
            'client_id' => (int) $user['client_id'],
            'first_name' => (string) $user['client_first_name'],
            'last_name' => (string) $user['client_last_name'],
            'tenant_name' => (string) $user['tenant_name'],
            'logo_path' => $user['logo_path'],
            'theme_primary_color' => $user['theme_primary_color'],
            'theme_secondary_color' => $user['theme_secondary_color']
        ]]
    ]);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}

