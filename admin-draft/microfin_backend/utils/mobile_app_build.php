<?php

function mf_mobile_app_env(string $key, string $default = ''): string
{
    $value = getenv($key);
    if ($value !== false && trim((string) $value) !== '') {
        return trim((string) $value);
    }

    if (isset($_ENV[$key]) && trim((string) $_ENV[$key]) !== '') {
        return trim((string) $_ENV[$key]);
    }

    if (isset($_SERVER[$key]) && trim((string) $_SERVER[$key]) !== '') {
        return trim((string) $_SERVER[$key]);
    }

    if (function_exists('mf_local_config_value')) {
        $localValue = trim((string) mf_local_config_value($key, ''));
        if ($localValue !== '') {
            return $localValue;
        }
    }

    return $default;
}

function mf_mobile_app_normalize_slug(string $rawValue): string
{
    if (function_exists('mf_normalize_tenant_slug')) {
        return (string) mf_normalize_tenant_slug($rawValue);
    }

    $slug = strtolower(trim($rawValue));
    $slug = preg_replace('/[^a-z0-9]+/', '', $slug);
    return (string) $slug;
}

function mf_mobile_app_project_root(): string
{
    return dirname(__DIR__, 2);
}

function mf_mobile_app_tenant_apk_path(string $tenantSlug): string
{
    return mf_mobile_app_project_root()
        . DIRECTORY_SEPARATOR . 'microfin_mobile'
        . DIRECTORY_SEPARATOR . 'tenant_apks'
        . DIRECTORY_SEPARATOR . $tenantSlug . '.apk';
}

function mf_mobile_app_github_raw_url(string $relativePath): string
{
    $owner = mf_mobile_app_env('GITHUB_ACTIONS_REPO_OWNER', 'Kaizer6969');
    $repo = mf_mobile_app_env('GITHUB_ACTIONS_REPO_NAME', 'MicroFinWebb');
    $ref = mf_mobile_app_env('GITHUB_ACTIONS_REF', 'main');

    if ($owner === '' || $repo === '' || $ref === '') {
        return '';
    }

    $segments = array_values(array_filter(explode('/', str_replace('\\', '/', trim($relativePath))), 'strlen'));
    if ($segments === []) {
        return '';
    }

    $encodedPath = implode('/', array_map('rawurlencode', $segments));

    return sprintf(
        'https://raw.githubusercontent.com/%s/%s/%s/%s',
        rawurlencode($owner),
        rawurlencode($repo),
        rawurlencode($ref),
        $encodedPath
    );
}

function mf_mobile_app_generic_apk_remote_url(): string
{
    $override = mf_mobile_app_env(
        'MF_GENERIC_APK_REMOTE_URL',
        mf_mobile_app_env('MICROFIN_GENERIC_APK_REMOTE_URL', '')
    );
    if ($override !== '') {
        return $override;
    }

    return mf_mobile_app_github_raw_url('microfin_mobile/microfin_app.apk');
}

function mf_mobile_app_tenant_apk_remote_url(string $tenantSlug): string
{
    $normalizedSlug = mf_mobile_app_normalize_slug($tenantSlug);
    if ($normalizedSlug === '') {
        return '';
    }

    $baseOverride = rtrim(
        mf_mobile_app_env(
            'MF_TENANT_APK_REMOTE_BASE_URL',
            mf_mobile_app_env('MICROFIN_TENANT_APK_REMOTE_BASE_URL', '')
        ),
        "/\\"
    );
    if ($baseOverride !== '') {
        return $baseOverride . '/' . rawurlencode($normalizedSlug) . '.apk';
    }

    return mf_mobile_app_github_raw_url('microfin_mobile/tenant_apks/' . $normalizedSlug . '.apk');
}

function mf_mobile_app_remote_asset_exists(string $url): bool
{
    $url = trim($url);
    if ($url === '') {
        return false;
    }

    static $cache = [];
    if (array_key_exists($url, $cache)) {
        return $cache[$url];
    }

    $exists = false;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch !== false) {
            curl_setopt_array($ch, [
                CURLOPT_NOBODY => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_USERAGENT => 'MicroFinPlatform-APKLookup',
            ]);

            curl_exec($ch);
            if (curl_errno($ch) === 0) {
                $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $exists = $httpCode >= 200 && $httpCode < 400;
            }

            curl_close($ch);
        }
    } elseif (function_exists('get_headers')) {
        $headers = @get_headers($url);
        if (is_array($headers) && isset($headers[0])) {
            $statusLine = is_array($headers[0]) ? (string) end($headers[0]) : (string) $headers[0];
            $exists = preg_match('/\s(200|301|302|307|308)\b/', $statusLine) === 1;
        }
    }

    $cache[$url] = $exists;
    return $exists;
}

function mf_mobile_app_upsert_setting(PDO $pdo, string $tenantId, string $key, string $value, string $dataType = 'String'): void
{
    $stmt = $pdo->prepare('
        INSERT INTO system_settings (tenant_id, setting_key, setting_value, setting_category, data_type)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            setting_category = VALUES(setting_category),
            data_type = VALUES(data_type),
            updated_at = CURRENT_TIMESTAMP
    ');
    $stmt->execute([$tenantId, $key, $value, 'Mobile App', $dataType]);
}

function mf_mobile_app_set_build_state(PDO $pdo, string $tenantId, array $state): void
{
    $status = trim((string) ($state['status'] ?? 'unknown'));
    $message = trim((string) ($state['message'] ?? ''));
    $slug = trim((string) ($state['tenant_slug'] ?? ''));
    $appName = trim((string) ($state['app_name'] ?? ''));
    $timestamp = trim((string) ($state['timestamp'] ?? gmdate('c')));

    try {
        mf_mobile_app_upsert_setting($pdo, $tenantId, 'mobile_app_build_status', $status);
        mf_mobile_app_upsert_setting($pdo, $tenantId, 'mobile_app_build_message', $message);
        mf_mobile_app_upsert_setting($pdo, $tenantId, 'mobile_app_build_requested_at', $timestamp);
        if ($slug !== '') {
            mf_mobile_app_upsert_setting($pdo, $tenantId, 'mobile_app_build_slug', $slug);
        }
        if ($appName !== '') {
            mf_mobile_app_upsert_setting($pdo, $tenantId, 'mobile_app_build_app_name', $appName);
        }
    } catch (Throwable $ignore) {
    }
}

function mf_mobile_app_get_build_state(PDO $pdo, string $tenantId): array
{
    try {
        $stmt = $pdo->prepare("
            SELECT setting_key, setting_value
            FROM system_settings
            WHERE tenant_id = ?
              AND setting_key IN (
                'mobile_app_build_status',
                'mobile_app_build_message',
                'mobile_app_build_requested_at',
                'mobile_app_build_slug',
                'mobile_app_build_app_name'
              )
        ");
        $stmt->execute([$tenantId]);
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

        return [
            'status' => trim((string) ($rows['mobile_app_build_status'] ?? '')),
            'message' => trim((string) ($rows['mobile_app_build_message'] ?? '')),
            'requested_at' => trim((string) ($rows['mobile_app_build_requested_at'] ?? '')),
            'tenant_slug' => trim((string) ($rows['mobile_app_build_slug'] ?? '')),
            'app_name' => trim((string) ($rows['mobile_app_build_app_name'] ?? '')),
        ];
    } catch (Throwable $ignore) {
        return [];
    }
}

function mf_mobile_app_resolve_tenant(PDO $pdo, string $tenantId, string $tenantSlug = '', string $appName = ''): array
{
    $resolved = [
        'tenant_id' => $tenantId,
        'tenant_slug' => mf_mobile_app_normalize_slug($tenantSlug),
        'app_name' => trim($appName),
    ];

    if ($resolved['tenant_slug'] !== '' && $resolved['app_name'] !== '') {
        return $resolved;
    }

    $stmt = $pdo->prepare('SELECT tenant_slug, tenant_name FROM tenants WHERE tenant_id = ? LIMIT 1');
    $stmt->execute([$tenantId]);
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    if ($resolved['tenant_slug'] === '') {
        $resolved['tenant_slug'] = mf_mobile_app_normalize_slug((string) ($tenant['tenant_slug'] ?? ''));
    }
    if ($resolved['app_name'] === '') {
        $resolved['app_name'] = trim((string) ($tenant['tenant_name'] ?? ''));
    }

    if ($resolved['app_name'] === '') {
        $resolved['app_name'] = strtoupper($resolved['tenant_slug']);
    }

    return $resolved;
}

function mf_mobile_app_dispatch_tenant_build(PDO $pdo, string $tenantId, string $tenantSlug = '', string $appName = ''): array
{
    $tenant = mf_mobile_app_resolve_tenant($pdo, $tenantId, $tenantSlug, $appName);
    $result = [
        'ok' => true,
        'status' => 'shared_ready',
        'message' => 'The shared company mobile app is already the only supported APK. No tenant-specific build is required.',
        'tenant_slug' => $tenant['tenant_slug'],
        'app_name' => $tenant['app_name'],
        'dispatched' => false,
        'shared_apk_url' => mf_mobile_app_generic_apk_remote_url(),
    ];
    mf_mobile_app_set_build_state($pdo, $tenantId, $result);
    return $result;
}
