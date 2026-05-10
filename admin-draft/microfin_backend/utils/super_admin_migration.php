<?php
// backend/super_admin_migration.php
require_once '../config/db_connect.php';

try {
    // Modify tenants table for billing and limitations
    $pdo->exec("ALTER TABLE tenants 
                ADD COLUMN IF NOT EXISTS plan_tier VARCHAR(50) DEFAULT 'Starter',
                ADD COLUMN IF NOT EXISTS max_users INT DEFAULT 250,
                ADD COLUMN IF NOT EXISTS storage_limit_gb DECIMAL(5,2) DEFAULT 5.00,
                ADD COLUMN IF NOT EXISTS modules_enabled JSON,
                ADD COLUMN IF NOT EXISTS mrr DECIMAL(10,2) DEFAULT 0.00");

    // We'll populate some default modules enabled
    $pdo->exec("UPDATE tenants SET modules_enabled = '{\"sms\": false, \"analytics\": false}' WHERE modules_enabled IS NULL");
    
    // Normalize legacy tier names and set MRR based on current plan pricing.
    $pdo->exec("UPDATE tenants SET plan_tier = 'Starter' WHERE plan_tier = 'Basic'");
    $pdo->exec("UPDATE tenants SET plan_tier = 'Pro' WHERE plan_tier = 'Growth'");
    $pdo->exec("UPDATE tenants SET mrr = 4999.00 WHERE plan_tier = 'Starter' AND mrr = 0.00");
    $pdo->exec("UPDATE tenants SET mrr = 14999.00 WHERE plan_tier = 'Pro' AND mrr = 0.00");
    $pdo->exec("UPDATE tenants SET mrr = 19999.00 WHERE plan_tier = 'Enterprise' AND mrr = 0.00");
    $pdo->exec("UPDATE tenants SET mrr = 29999.00 WHERE plan_tier = 'Unlimited' AND mrr = 0.00");

    echo "Database successfully modified for Super Admin features.";
} catch (PDOException $e) {
    echo "Error modifying database: " . $e->getMessage();
}
?>
