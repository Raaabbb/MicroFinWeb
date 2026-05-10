<?php

require_once __DIR__ . '/../auth/mobile_identity.php';

function microfin_identity_query_active_tenant(mysqli $conn, string $identifier): ?array
{
    $normalized = trim($identifier);
    if ($normalized === '') {
        return null;
    }

    $stmt = $conn->prepare("
        SELECT
            t.tenant_id,
            t.tenant_name,
            t.tenant_slug,
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
        LEFT JOIN tenant_branding tb ON tb.tenant_id = t.tenant_id
        WHERE t.deleted_at IS NULL
          AND LOWER(COALESCE(t.status, '')) = 'active'
          AND (
                LOWER(t.tenant_id) = LOWER(?)
                OR LOWER(COALESCE(t.tenant_slug, '')) = LOWER(?)
          )
        LIMIT 1
    ");
    $stmt->bind_param('ss', $normalized, $normalized);
    $stmt->execute();
    $tenant = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    return is_array($tenant) ? $tenant : null;
}

function microfin_identity_query_active_tenant_by_id(mysqli $conn, string $tenantId): ?array
{
    return microfin_identity_query_active_tenant($conn, $tenantId);
}

function microfin_identity_branding_payload(array $tenant): array
{
    return [
        'tenant_id' => (string) ($tenant['tenant_id'] ?? ''),
        'tenant_name' => (string) ($tenant['tenant_name'] ?? 'MicroFin'),
        'tenant_slug' => (string) ($tenant['tenant_slug'] ?? ''),
        'logo_path' => (string) ($tenant['logo_path'] ?? ''),
        'font_family' => (string) ($tenant['font_family'] ?? 'Inter'),
        'theme_primary_color' => (string) ($tenant['theme_primary_color'] ?? '#1D4ED8'),
        'theme_secondary_color' => (string) ($tenant['theme_secondary_color'] ?? '#1E40AF'),
        'theme_text_main' => (string) ($tenant['theme_text_main'] ?? '#0F172A'),
        'theme_text_muted' => (string) ($tenant['theme_text_muted'] ?? '#64748B'),
        'theme_bg_body' => (string) ($tenant['theme_bg_body'] ?? '#F8FAFC'),
        'theme_bg_card' => (string) ($tenant['theme_bg_card'] ?? '#FFFFFF'),
        'theme_border_color' => (string) ($tenant['theme_border_color'] ?? '#E2E8F0'),
        'card_border_width' => (string) ($tenant['card_border_width'] ?? '0'),
        'card_shadow' => (string) ($tenant['card_shadow'] ?? 'none'),
    ];
}

function microfin_identity_issue_tenant_context_token(array $tenant): string
{
    return mf_mobile_identity_issue_token([
        'tenant_id' => (string) ($tenant['tenant_id'] ?? ''),
        'tenant_slug' => (string) ($tenant['tenant_slug'] ?? ''),
        'tenant_name' => (string) ($tenant['tenant_name'] ?? ''),
    ], 'tenant-context', 900);
}

function microfin_identity_resolve_tenant_context(mysqli $conn, array $input): ?array
{
    $tenantContextToken = trim((string) ($input['tenant_context_token'] ?? ''));
    if ($tenantContextToken !== '') {
        $claims = mf_mobile_identity_verify_token($tenantContextToken, 'tenant-context');
        if (!is_array($claims) || empty($claims['tenant_id'])) {
            return null;
        }

        return microfin_identity_query_active_tenant_by_id($conn, (string) $claims['tenant_id']);
    }

    $tenantId = trim((string) ($input['tenant_id'] ?? ''));
    if ($tenantId !== '') {
        return microfin_identity_query_active_tenant_by_id($conn, $tenantId);
    }

    $tenantSlug = trim((string) ($input['tenant_slug'] ?? ''));
    if ($tenantSlug !== '') {
        return microfin_identity_query_active_tenant($conn, $tenantSlug);
    }

    return null;
}

function microfin_identity_resolve_login_context(mysqli $conn, array $input): ?array
{
    $loginUsername = trim((string) ($input['login_username'] ?? ''));
    if ($loginUsername !== '') {
        $parsed = mf_mobile_identity_parse_login_username($loginUsername);
        if (!is_array($parsed)) {
            return null;
        }

        $tenant = microfin_identity_query_active_tenant($conn, (string) $parsed['tenant_slug']);
        if (!is_array($tenant)) {
            return null;
        }

        return [
            'tenant' => $tenant,
            'base_username' => (string) $parsed['base_username'],
            'login_username' => (string) $parsed['login_username'],
        ];
    }

    $legacyTenantId = trim((string) ($input['tenant_id'] ?? ''));
    if ($legacyTenantId === '') {
        return null;
    }

    $tenant = microfin_identity_query_active_tenant_by_id($conn, $legacyTenantId);
    if (!is_array($tenant)) {
        return null;
    }

    $legacyUsername = trim((string) ($input['username'] ?? $input['email'] ?? ''));
    if ($legacyUsername === '') {
        return null;
    }

    return [
        'tenant' => $tenant,
        'base_username' => $legacyUsername,
        'login_username' => '',
    ];
}
