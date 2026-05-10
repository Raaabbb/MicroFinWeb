<?php

function mf_mobile_identity_env(string $key, string $default = ''): string
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

function mf_mobile_identity_secret(): string
{
    static $secret = null;
    if ($secret !== null) {
        return $secret;
    }

    $candidates = [
        'MF_MOBILE_IDENTITY_SECRET',
        'MICROFIN_MOBILE_IDENTITY_SECRET',
        'MF_APP_IDENTITY_SECRET',
        'MICROFIN_APP_IDENTITY_SECRET',
        'APP_KEY',
        'MICROFIN_APP_KEY',
    ];

    foreach ($candidates as $candidate) {
        $value = mf_mobile_identity_env($candidate, '');
        if ($value !== '') {
            $secret = $value;
            return $secret;
        }
    }

    $fallbackSeed = implode('|', [
        dirname(__DIR__, 2),
        mf_mobile_identity_env('BREVO_SENDER_EMAIL', 'microfin@example.com'),
        php_uname('n'),
    ]);

    $secret = hash('sha256', $fallbackSeed);
    return $secret;
}

function mf_mobile_identity_base64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function mf_mobile_identity_base64url_decode(string $value): ?string
{
    $normalized = strtr($value, '-_', '+/');
    $padding = strlen($normalized) % 4;
    if ($padding > 0) {
        $normalized .= str_repeat('=', 4 - $padding);
    }

    $decoded = base64_decode($normalized, true);
    return $decoded === false ? null : $decoded;
}

function mf_mobile_identity_issue_token(array $claims, string $purpose, int $ttlSeconds = 900): string
{
    $now = time();
    $payload = [
        'purpose' => trim($purpose),
        'iat' => $now,
        'exp' => $now + max(60, $ttlSeconds),
        'claims' => $claims,
    ];

    $encodedPayload = mf_mobile_identity_base64url_encode(
        json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    );
    $signature = hash_hmac('sha256', $encodedPayload, mf_mobile_identity_secret(), true);

    return $encodedPayload . '.' . mf_mobile_identity_base64url_encode($signature);
}

function mf_mobile_identity_verify_token(string $token, string $purpose): ?array
{
    $token = trim($token);
    if ($token === '' || strpos($token, '.') === false) {
        return null;
    }

    [$encodedPayload, $encodedSignature] = explode('.', $token, 2);
    $payloadJson = mf_mobile_identity_base64url_decode($encodedPayload);
    $providedSignature = mf_mobile_identity_base64url_decode($encodedSignature);
    if ($payloadJson === null || $providedSignature === null) {
        return null;
    }

    $expectedSignature = hash_hmac('sha256', $encodedPayload, mf_mobile_identity_secret(), true);
    if (!hash_equals($expectedSignature, $providedSignature)) {
        return null;
    }

    $payload = json_decode($payloadJson, true);
    if (!is_array($payload)) {
        return null;
    }

    if (trim((string) ($payload['purpose'] ?? '')) !== trim($purpose)) {
        return null;
    }

    $expiresAt = (int) ($payload['exp'] ?? 0);
    if ($expiresAt > 0 && $expiresAt < time()) {
        return null;
    }

    $claims = $payload['claims'] ?? [];
    return is_array($claims) ? $claims : null;
}

function mf_mobile_identity_normalize_slug(string $rawValue): string
{
    if (function_exists('mf_normalize_tenant_slug')) {
        return (string) mf_normalize_tenant_slug($rawValue);
    }

    $slug = strtolower(trim($rawValue));
    $slug = preg_replace('/[^a-z0-9]+/', '', $slug) ?? '';

    return $slug;
}

function mf_mobile_identity_normalize_username_base(string $rawValue): string
{
    $value = strtolower(trim($rawValue));
    $value = preg_replace('/[^a-z0-9._-]+/', '', $value) ?? '';
    $value = trim($value, '._-');

    return $value;
}

function mf_mobile_identity_is_valid_username_base(string $rawValue): bool
{
    $value = trim($rawValue);
    if ($value === '' || strpos($value, '@') !== false || preg_match('/\s/', $value) === 1) {
        return false;
    }

    return preg_match('/^[A-Za-z0-9._-]{3,50}$/', $value) === 1;
}

function mf_mobile_identity_build_login_username(string $baseUsername, string $tenantSlug): string
{
    $base = mf_mobile_identity_normalize_username_base($baseUsername);
    $slug = mf_mobile_identity_normalize_slug($tenantSlug);
    if ($base === '' || $slug === '') {
        return '';
    }

    return $base . '@' . $slug;
}

function mf_mobile_identity_parse_login_username(string $rawValue): ?array
{
    $value = trim($rawValue);
    if ($value === '') {
        return null;
    }

    $separatorPos = strrpos($value, '@');
    if ($separatorPos === false || $separatorPos === 0 || $separatorPos === strlen($value) - 1) {
        return null;
    }

    $baseUsername = substr($value, 0, $separatorPos);
    $tenantSlug = substr($value, $separatorPos + 1);
    if (!mf_mobile_identity_is_valid_username_base($baseUsername)) {
        return null;
    }

    $normalizedSlug = mf_mobile_identity_normalize_slug($tenantSlug);
    if ($normalizedSlug === '') {
        return null;
    }

    return [
        'base_username' => mf_mobile_identity_normalize_username_base($baseUsername),
        'tenant_slug' => $normalizedSlug,
        'login_username' => mf_mobile_identity_build_login_username($baseUsername, $normalizedSlug),
    ];
}

function mf_mobile_identity_format_qr_payload(string $tenantReferenceToken): string
{
    return 'MFREF:' . trim($tenantReferenceToken);
}

function mf_mobile_identity_parse_reference_payload(string $rawPayload): ?array
{
    $payload = trim($rawPayload);
    if ($payload === '') {
        return null;
    }

    if (stripos($payload, 'MFREF:') === 0) {
        $token = trim(substr($payload, 6));
        return mf_mobile_identity_verify_token($token, 'tenant-reference');
    }

    if (filter_var($payload, FILTER_VALIDATE_URL)) {
        $query = [];
        parse_str((string) parse_url($payload, PHP_URL_QUERY), $query);

        if (!empty($query['tenant_ref'])) {
            return mf_mobile_identity_verify_token((string) $query['tenant_ref'], 'tenant-reference');
        }

        if (!empty($query['referral_code'])) {
            return ['tenant_slug' => mf_mobile_identity_normalize_slug((string) $query['referral_code'])];
        }
    }

    return null;
}
