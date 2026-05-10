<?php
require_once __DIR__ . '/../bootstrap.php';
require_once mf_platform_path('backend/db_connect.php');

$pdo->exec("UPDATE users SET can_manage_billing = 1 WHERE user_type = 'Tenant_Admin' OR role_id IN (SELECT role_id FROM user_roles WHERE role_name = 'Admin')");
echo "Updated existing admins.\n";
