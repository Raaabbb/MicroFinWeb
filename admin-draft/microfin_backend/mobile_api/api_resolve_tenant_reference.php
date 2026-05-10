<?php
require_once __DIR__ . '/api_utils.php';
microfin_api_bootstrap();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth_identity.php';

microfin_require_post();

$data = microfin_read_json_input();

$referralCode = trim((string) ($data['referral_code'] ?? ''));
$qrPayload = trim((string) ($data['qr_payload'] ?? ''));

if ($referralCode === '' && $qrPayload === '') {
    microfin_json_response([
        'success' => false,
        'message' => 'Provide a referral code or QR payload first.',
    ], 422);
}

$tenantIdentifier = '';
if ($qrPayload !== '') {
    $referenceClaims = mf_mobile_identity_parse_reference_payload($qrPayload);
    if (!is_array($referenceClaims)) {
        microfin_json_response([
            'success' => false,
            'message' => 'The QR code is invalid or has expired.',
        ], 422);
    }

    $tenantIdentifier = trim((string) ($referenceClaims['tenant_slug'] ?? $referenceClaims['tenant_id'] ?? ''));
} else {
    $tenantIdentifier = mf_mobile_identity_normalize_slug($referralCode);
}

if ($tenantIdentifier === '') {
    microfin_json_response([
        'success' => false,
        'message' => 'A valid tenant reference is required.',
    ], 422);
}

$tenant = microfin_identity_query_active_tenant($conn, $tenantIdentifier);
if (!is_array($tenant)) {
    microfin_json_response([
        'success' => false,
        'message' => 'We could not verify that tenant reference.',
    ], 404);
}

microfin_json_response([
    'success' => true,
    'message' => 'Tenant reference verified.',
    'tenant_context_token' => microfin_identity_issue_tenant_context_token($tenant),
    'tenant' => microfin_identity_branding_payload($tenant),
]);
