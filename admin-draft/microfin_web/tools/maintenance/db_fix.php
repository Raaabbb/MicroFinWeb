<?php
require_once __DIR__ . '/../bootstrap.php';
require_once mf_platform_path('backend/db_connect.php');

try {
    $pdo->exec("ALTER TABLE tenants ADD COLUMN cancel_at_period_end TINYINT(1) DEFAULT 0");
    echo "Added cancel_at_period_end column.\n";
} catch (PDOException $e) {
    echo "Column cancel_at_period_end might already exist.\n";
}

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN can_manage_billing TINYINT(1) DEFAULT 0");
    echo "Added can_manage_billing column.\n";
} catch (PDOException $e) {
    echo "Column can_manage_billing might already exist.\n";
}

echo "Database modifications finished.\n";
