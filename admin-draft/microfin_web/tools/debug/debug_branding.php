<?php
require_once __DIR__ . '/../bootstrap.php';
require_once mf_platform_path('backend/db_connect.php');

echo "=== tenant_branding ===\n";
$stmt = $pdo->query('SELECT * FROM tenant_branding');
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);

echo "\n=== tenants (setup status) ===\n";
$stmt2 = $pdo->query('SELECT tenant_id, tenant_name, setup_completed, setup_current_step FROM tenants');
$rows2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
print_r($rows2);

echo "\n=== DESCRIBE tenant_branding ===\n";
$desc = $pdo->query('DESCRIBE tenant_branding');
while ($col = $desc->fetch(PDO::FETCH_ASSOC)) {
    echo $col['Field'] . ' | ' . $col['Type'] . ' | Default: ' . ($col['Default'] ?? 'NULL') . "\n";
}
