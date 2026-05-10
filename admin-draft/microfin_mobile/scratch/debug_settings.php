<?php
require_once __DIR__ . '/../api/config.php';
$baseUrl = microfin_app_base_url();

$ch = curl_init();
// Try getting tenant config for tenant_id 1
curl_setopt($ch, CURLOPT_URL, "{$baseUrl}/microfin_mobile/api/api_get_tenant_config.php?tenant_id=1");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$output = curl_exec($ch);
curl_close($ch);

echo "Response from API (Tenant 1):\n";
echo json_encode(json_decode($output), JSON_PRETTY_PRINT) . "\n";
