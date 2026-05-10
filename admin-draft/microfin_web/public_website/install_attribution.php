<?php

require_once dirname(__DIR__, 2) . '/microfin_backend/auth/mobile_identity.php';

function mf_install_requested_route(): string
{
    $route = trim((string)($_GET['route'] ?? ''));
    if ($route !== '') {
        return trim($route, '/');
    }

    $pathInfo = trim((string)($_SERVER['PATH_INFO'] ?? ''));
    if ($pathInfo !== '') {
        return trim($pathInfo, '/');
    }

    $requestPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
    $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($requestPath !== '' && $scriptName !== '' && strpos($requestPath, $scriptName) === 0) {
        return trim(substr($requestPath, strlen($scriptName)), '/');
    }

    return '';
}

function mf_install_app_base_path(): string
{
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $base = dirname(dirname(dirname($script)));
    if ($base === '.' || $base === DIRECTORY_SEPARATOR) {
        return '';
    }

    return rtrim(str_replace('\\', '/', $base), '/');
}

function mf_install_is_https(): bool
{
    $https = strtolower((string)($_SERVER['HTTPS'] ?? ''));
    if ($https !== '' && $https !== 'off') {
        return true;
    }

    $forwardedProto = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    return $forwardedProto === 'https';
}

function mf_install_base_url(): string
{
    $scheme = mf_install_is_https() ? 'https' : 'http';
    $host = trim((string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
    return $scheme . '://' . $host . mf_install_app_base_path();
}

function mf_install_project_root(): string
{
    return dirname(__DIR__, 2);
}

function mf_install_slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9_-]+/', '_', $value) ?? '';
    return trim($value, '_');
}

function mf_install_download_filename(array $tenant): string
{
    return 'MicroFin.apk';
}

function mf_install_generic_apk_path(): string
{
    return mf_install_project_root() . DIRECTORY_SEPARATOR . 'microfin_mobile' . DIRECTORY_SEPARATOR . 'microfin_app.apk';
}

function mf_install_generic_apk_url(): string
{
    $override = trim((string)(getenv('MF_GENERIC_APK_URL') ?: getenv('MICROFIN_GENERIC_APK_URL') ?: ''));
    if ($override !== '') {
        return $override;
    }

    return mf_install_base_url() . '/microfin_mobile/microfin_app.apk';
}

function mf_install_tenant_apk_directory(): string
{
    $override = trim((string)(getenv('MF_TENANT_APK_DIR') ?: getenv('MICROFIN_TENANT_APK_DIR') ?: ''));
    if ($override !== '') {
        return rtrim($override, "\\/");
    }

    return mf_install_project_root() . DIRECTORY_SEPARATOR . 'microfin_mobile' . DIRECTORY_SEPARATOR . 'tenant_apks';
}

function mf_install_resolve_generic_apk_asset(): array
{
    $downloadFilename = mf_install_download_filename([]);
    $genericPath = mf_install_generic_apk_path();
    if (is_file($genericPath)) {
        return [
            'path' => $genericPath,
            'filename' => $downloadFilename,
            'variant' => 'shared',
        ];
    }

    $override = trim((string)(getenv('MF_GENERIC_APK_URL') ?: getenv('MICROFIN_GENERIC_APK_URL') ?: ''));
    if ($override !== '') {
        return [
            'url' => $override,
            'filename' => $downloadFilename,
            'variant' => 'remote-shared',
        ];
    }

    if (
        function_exists('mf_mobile_app_generic_apk_remote_url')
        && function_exists('mf_mobile_app_remote_asset_exists')
    ) {
        $remoteUrl = mf_mobile_app_generic_apk_remote_url();
        if ($remoteUrl !== '' && mf_mobile_app_remote_asset_exists($remoteUrl)) {
            return [
                'url' => $remoteUrl,
                'filename' => $downloadFilename,
                'variant' => 'github-shared',
            ];
        }
    }

    return [
        'url' => mf_install_generic_apk_url(),
        'filename' => $downloadFilename,
        'variant' => 'remote-shared',
    ];
}

function mf_install_resolve_tenant_apk_asset(array $tenant): ?array
{
    return null;
}

function mf_install_resolve_apk_asset(array $tenant, bool $allowGenericFallback = true): array
{
    return mf_install_resolve_generic_apk_asset();
}

function mf_install_stream_apk(string $path, string $filename): void
{
    if (!is_file($path) || !is_readable($path)) {
        http_response_code(404);
        exit;
    }

    if (function_exists('set_time_limit')) {
        @set_time_limit(0);
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/vnd.android.package-archive');
    header('Content-Length: ' . (string)filesize($path));
    header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
}

function mf_install_json_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function mf_install_client_ip(): string
{
    $candidates = [
        (string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''),
        (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''),
        (string)($_SERVER['REMOTE_ADDR'] ?? ''),
    ];

    foreach ($candidates as $candidate) {
        if ($candidate === '') {
            continue;
        }

        foreach (explode(',', $candidate) as $part) {
            $ip = trim($part);
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }

    return '';
}

function mf_install_user_agent(): string
{
    return substr(trim((string)($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 1000);
}

function mf_install_platform_hint_from_user_agent(string $userAgent): string
{
    $ua = strtolower($userAgent);
    if ($ua === '') {
        return 'unknown';
    }
    if (strpos($ua, 'android') !== false) {
        return 'android';
    }
    if (strpos($ua, 'iphone') !== false || strpos($ua, 'ipad') !== false || strpos($ua, 'ios') !== false) {
        return 'ios';
    }
    if (strpos($ua, 'windows') !== false) {
        return 'windows';
    }
    if (strpos($ua, 'mac os') !== false || strpos($ua, 'macintosh') !== false) {
        return 'macos';
    }
    if (strpos($ua, 'linux') !== false) {
        return 'linux';
    }
    if (strpos($ua, 'dart') !== false || strpos($ua, 'flutter') !== false) {
        return 'app';
    }

    return 'unknown';
}

function mf_install_normalize_platform(?string $value): string
{
    $platform = strtolower(trim((string)$value));
    if ($platform === '') {
        return 'unknown';
    }

    $aliases = [
        'iphoneos' => 'ios',
        'ipad' => 'ios',
        'iphone' => 'ios',
        'mac' => 'macos',
        'osx' => 'macos',
        'win' => 'windows',
    ];

    return $aliases[$platform] ?? $platform;
}

function mf_install_resolve_tenant(PDO $pdo, ?string $rawIdentifier): ?array
{
    $identifier = strtolower(trim((string)$rawIdentifier));
    if ($identifier === '') {
        return null;
    }

    $stmt = $pdo->prepare("
        SELECT
            t.tenant_id,
            t.tenant_slug,
            t.tenant_name,
            COALESCE(tb.logo_path, '') AS logo_path,
            COALESCE(tb.font_family, 'Inter') AS font_family,
            COALESCE(tb.theme_primary_color, '#1D4ED8') AS theme_primary_color,
            COALESCE(tb.theme_secondary_color, '#1E40AF') AS theme_secondary_color,
            COALESCE(tb.theme_text_main, '#0F172A') AS theme_text_main,
            COALESCE(tb.theme_text_muted, '#64748B') AS theme_text_muted,
            COALESCE(tb.theme_bg_body, '#F8FAFC') AS theme_bg_body,
            COALESCE(tb.theme_bg_card, '#FFFFFF') AS theme_bg_card,
            COALESCE(tb.theme_border_color, '#E2E8F0') AS theme_border_color,
            COALESCE(tb.card_border_width, '0') AS card_border_width,
            COALESCE(tb.card_shadow, 'none') AS card_shadow
        FROM tenants t
        LEFT JOIN tenant_branding tb
            ON tb.tenant_id COLLATE utf8mb4_unicode_ci = t.tenant_id COLLATE utf8mb4_unicode_ci
        WHERE t.status = 'Active'
          AND (
                LOWER(t.tenant_id) COLLATE utf8mb4_unicode_ci = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci
                OR LOWER(COALESCE(t.tenant_slug, '')) COLLATE utf8mb4_unicode_ci = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci
          )
        LIMIT 1
    ");
    $stmt->execute([$identifier, $identifier]);

    $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($tenant) ? $tenant : null;
}

function mf_install_format_tenant(array $tenant): array
{
    return [
        'id' => (string)($tenant['tenant_id'] ?? ''),
        'slug' => (string)($tenant['tenant_slug'] ?? ''),
        'appName' => (string)($tenant['tenant_name'] ?? 'MicroFin'),
        'logo_path' => (string)($tenant['logo_path'] ?? ''),
        'font_family' => (string)($tenant['font_family'] ?? 'Inter'),
        'theme_primary_color' => (string)($tenant['theme_primary_color'] ?? '#1D4ED8'),
        'theme_secondary_color' => (string)($tenant['theme_secondary_color'] ?? '#1E40AF'),
        'theme_text_main' => (string)($tenant['theme_text_main'] ?? '#0F172A'),
        'theme_text_muted' => (string)($tenant['theme_text_muted'] ?? '#64748B'),
        'theme_bg_body' => (string)($tenant['theme_bg_body'] ?? '#F8FAFC'),
        'theme_bg_card' => (string)($tenant['theme_bg_card'] ?? '#FFFFFF'),
        'theme_border_color' => (string)($tenant['theme_border_color'] ?? '#E2E8F0'),
        'card_border_width' => (string)($tenant['card_border_width'] ?? '0'),
        'card_shadow' => (string)($tenant['card_shadow'] ?? 'none'),
    ];
}

function mf_install_referral_code(array $tenant): string
{
    return mf_mobile_identity_normalize_slug((string) ($tenant['tenant_slug'] ?? $tenant['tenant_id'] ?? ''));
}

function mf_install_issue_tenant_reference_token(array $tenant, int $ttlSeconds = 604800): string
{
    return mf_mobile_identity_issue_token([
        'tenant_id' => (string) ($tenant['tenant_id'] ?? ''),
        'tenant_slug' => mf_install_referral_code($tenant),
        'tenant_name' => (string) ($tenant['tenant_name'] ?? ''),
    ], 'tenant-reference', $ttlSeconds);
}

function mf_install_tenant_reference_payload(array $tenant): string
{
    return mf_mobile_identity_format_qr_payload(
        mf_install_issue_tenant_reference_token($tenant)
    );
}

function mf_install_tenant_reference_qr_url(array $tenant, int $size = 220): string
{
    $payload = mf_install_tenant_reference_payload($tenant);
    $size = max(160, min(512, $size));

    return 'https://api.qrserver.com/v1/create-qr-code/?size='
        . rawurlencode($size . 'x' . $size)
        . '&margin=0&data='
        . rawurlencode($payload);
}

function mf_install_record_download(PDO $pdo, array $tenant, ?array $apkAsset = null): array
{
    $token = bin2hex(random_bytes(24));
    $ipAddress = mf_install_client_ip();
    $userAgent = mf_install_user_agent();
    $platformHint = mf_install_platform_hint_from_user_agent($userAgent);
    $expiresAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
        ->modify('+24 hours')
        ->format('Y-m-d H:i:s');
    $referrer = substr(trim((string)($_SERVER['HTTP_REFERER'] ?? '')), 0, 500);

    $insert = $pdo->prepare("
        INSERT INTO mobile_install_attributions (
            tracking_token,
            tenant_id,
            tenant_slug,
            ip_address,
            user_agent_hash,
            user_agent,
            platform_hint,
            referer_url,
            expires_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $insert->execute([
        $token,
        (string)$tenant['tenant_id'],
        (string)$tenant['tenant_slug'],
        $ipAddress,
        hash('sha256', $userAgent),
        $userAgent,
        $platformHint,
        $referrer,
        $expiresAt,
    ]);

    setcookie('mf_install_ref', $token, [
        'expires' => time() + 86400,
        'path' => '/',
        'secure' => mf_install_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    return [
        'tracking_token' => $token,
        'platform_hint' => $platformHint,
        'apk_url' => is_array($apkAsset) && !empty($apkAsset['url'])
            ? (string) $apkAsset['url']
            : mf_install_generic_apk_url(),
    ];
}

function mf_install_parse_request_body(): array
{
    $rawBody = file_get_contents('php://input');
    if (!is_string($rawBody) || trim($rawBody) === '') {
        return [];
    }

    $decoded = json_decode($rawBody, true);
    return is_array($decoded) ? $decoded : [];
}

function mf_install_mark_claimed(PDO $pdo, int $attributionId, string $ipAddress, string $platformHint, string $userAgent): void
{
    $update = $pdo->prepare("
        UPDATE mobile_install_attributions
        SET claimed_at = UTC_TIMESTAMP(),
            claimed_ip_address = ?,
            claimed_platform_hint = ?,
            claimed_user_agent = ?,
            last_seen_at = UTC_TIMESTAMP()
        WHERE id = ?
    ");
    $update->execute([
        $ipAddress,
        $platformHint,
        substr($userAgent, 0, 1000),
        $attributionId,
    ]);
}

function mf_install_claim_tenant(PDO $pdo, array $request): ?array
{
    $ipAddress = mf_install_client_ip();
    if ($ipAddress === '') {
        return null;
    }

    $tenantHint = strtolower(trim((string)($request['tenant_hint'] ?? '')));
    $requestedPlatform = mf_install_normalize_platform(
        $request['platform']
        ?? $_SERVER['HTTP_X_APP_PLATFORM']
        ?? mf_install_platform_hint_from_user_agent(mf_install_user_agent())
    );
    $installToken = trim((string)($request['install_token'] ?? $_COOKIE['mf_install_ref'] ?? ''));
    $claimedUserAgent = mf_install_user_agent();

    if ($installToken !== '') {
        $exact = $pdo->prepare("
            SELECT
                a.id,
                t.tenant_id,
                t.tenant_slug,
                t.tenant_name,
                COALESCE(tb.logo_path, '') AS logo_path,
                COALESCE(tb.font_family, 'Inter') AS font_family,
                COALESCE(tb.theme_primary_color, '#1D4ED8') AS theme_primary_color,
                COALESCE(tb.theme_secondary_color, '#1E40AF') AS theme_secondary_color,
                COALESCE(tb.theme_text_main, '#0F172A') AS theme_text_main,
                COALESCE(tb.theme_text_muted, '#64748B') AS theme_text_muted,
                COALESCE(tb.theme_bg_body, '#F8FAFC') AS theme_bg_body,
                COALESCE(tb.theme_bg_card, '#FFFFFF') AS theme_bg_card,
                COALESCE(tb.theme_border_color, '#E2E8F0') AS theme_border_color,
                COALESCE(tb.card_border_width, '0') AS card_border_width,
                COALESCE(tb.card_shadow, 'none') AS card_shadow
            FROM mobile_install_attributions a
            INNER JOIN tenants t
                ON t.tenant_id COLLATE utf8mb4_unicode_ci = a.tenant_id COLLATE utf8mb4_unicode_ci
            LEFT JOIN tenant_branding tb
                ON tb.tenant_id COLLATE utf8mb4_unicode_ci = t.tenant_id COLLATE utf8mb4_unicode_ci
            WHERE a.tracking_token COLLATE utf8mb4_unicode_ci = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci
              AND a.expires_at > UTC_TIMESTAMP()
              AND t.status = 'Active'
            LIMIT 1
        ");
        $exact->execute([$installToken]);
        $row = $exact->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            mf_install_mark_claimed($pdo, (int)$row['id'], $ipAddress, $requestedPlatform, $claimedUserAgent);
            return $row;
        }
    }

    $sql = "
        SELECT
            a.id,
            t.tenant_id,
            t.tenant_slug,
            t.tenant_name,
            COALESCE(tb.logo_path, '') AS logo_path,
            COALESCE(tb.font_family, 'Inter') AS font_family,
            COALESCE(tb.theme_primary_color, '#1D4ED8') AS theme_primary_color,
            COALESCE(tb.theme_secondary_color, '#1E40AF') AS theme_secondary_color,
            COALESCE(tb.theme_text_main, '#0F172A') AS theme_text_main,
            COALESCE(tb.theme_text_muted, '#64748B') AS theme_text_muted,
            COALESCE(tb.theme_bg_body, '#F8FAFC') AS theme_bg_body,
            COALESCE(tb.theme_bg_card, '#FFFFFF') AS theme_bg_card,
            COALESCE(tb.theme_border_color, '#E2E8F0') AS theme_border_color,
            COALESCE(tb.card_border_width, '0') AS card_border_width,
            COALESCE(tb.card_shadow, 'none') AS card_shadow
        FROM mobile_install_attributions a
        INNER JOIN tenants t
            ON t.tenant_id COLLATE utf8mb4_unicode_ci = a.tenant_id COLLATE utf8mb4_unicode_ci
        LEFT JOIN tenant_branding tb
            ON tb.tenant_id COLLATE utf8mb4_unicode_ci = t.tenant_id COLLATE utf8mb4_unicode_ci
        WHERE a.claimed_at IS NULL
          AND a.expires_at > UTC_TIMESTAMP()
          AND a.ip_address COLLATE utf8mb4_unicode_ci = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci
          AND t.status = 'Active'
    ";
    $params = [$ipAddress];

    if ($requestedPlatform !== '' && $requestedPlatform !== 'unknown') {
        $sql .= " AND (
            a.platform_hint COLLATE utf8mb4_unicode_ci = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci
            OR a.platform_hint = 'unknown'
        )";
        $params[] = $requestedPlatform;
    }

    if ($tenantHint !== '') {
        $sql .= " AND (
            LOWER(a.tenant_slug) COLLATE utf8mb4_unicode_ci = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci
            OR LOWER(a.tenant_id) COLLATE utf8mb4_unicode_ci = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci
        )";
        $params[] = $tenantHint;
        $params[] = $tenantHint;
    }

    $sql .= " ORDER BY a.created_at DESC LIMIT 1";
    $match = $pdo->prepare($sql);
    $match->execute($params);
    $row = $match->fetch(PDO::FETCH_ASSOC);
    if (is_array($row)) {
        mf_install_mark_claimed($pdo, (int)$row['id'], $ipAddress, $requestedPlatform, $claimedUserAgent);
        return $row;
    }

    if ($tenantHint !== '') {
        return mf_install_resolve_tenant($pdo, $tenantHint);
    }

    return null;
}
