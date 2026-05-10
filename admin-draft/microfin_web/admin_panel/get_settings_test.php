<?php
require "c:/xampp/htdocs/admin-draft-withmobile/admin-draft/microfin_platform/backend/db_connect.php";
$stmt = $pdo->query("SELECT tenant_id, setting_key, setting_value FROM system_settings WHERE setting_key IN ('policy_console_credit_limits', 'policy_console_decision_rules', 'policy_console_compliance_documents') LIMIT 3");
while($r = $stmt->fetch(PDO::FETCH_ASSOC)){
    echo "Tenant: " . $r['tenant_id'] . "\n";
    echo "Key: " . $r['setting_key'] . "\n";
    print_r(json_decode($r['setting_value'], true));
    echo "\n------------------\n";
}
