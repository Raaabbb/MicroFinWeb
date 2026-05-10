<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') { exit; }
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid Request']);
    exit;
}

require_once __DIR__ . '/../config/db.php';
$tenantId = trim((string) ($_GET['tenant_id'] ?? ''));

if ($tenantId === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Missing tenant_id.']);
    exit;
}

try {
    $pdo = new PDO("mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset=utf8mb4", $dbConfig['username'], $dbConfig['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    require_once __DIR__ . '/../../microfin_web/admin_panel/includes/policy_console_system_defaults.php';
    require_once __DIR__ . '/../../microfin_web/admin_panel/includes/policy_console_limit_assignment.php';
    require_once __DIR__ . '/../../microfin_web/admin_panel/includes/policy_console_decision_rules.php';
    require_once __DIR__ . '/../../microfin_web/admin_panel/includes/policy_console_compliance_documents.php';
    require_once __DIR__ . '/../../microfin_web/admin_panel/includes/policy_console_credit_limits.php';
    require_once __DIR__ . '/../../microfin_web/admin_panel/includes/credit_policy_workspace.php';
    require_once __DIR__ . '/../../microfin_backend/credit_policy.php';

    $scoreCeiling = 850;

    $fetchSetting = function(string $key) use ($pdo, $tenantId) {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE tenant_id = ? AND setting_key = ?");
        $stmt->execute([$tenantId, $key]);
        return json_decode($stmt->fetchColumn() ?: '{}', true) ?: [];
    };

    // Legacy Policies (Needed for normalization fallbacks)
    $creditPolicyRaw = $fetchSetting('credit_policy_settings');
    $creditPolicyOrig = mf_credit_policy_normalize($creditPolicyRaw);
    
    $limitRulesRaw = $fetchSetting('credit_limit_rules');
    $creditLimitRulesOrig = normalize_credit_limit_rules($limitRulesRaw);

    // Decision Rules
    $decisionRaw = $fetchSetting('policy_console_decision_rules');
    $decisionRules = policy_console_decision_rules_normalize($decisionRaw, $scoreCeiling);

    // Compliance Docs
    $catalog = policy_console_compliance_documents_catalog($pdo);
    $complianceRaw = $fetchSetting('policy_console_compliance_documents');
    $complianceDocs = policy_console_compliance_documents_normalize($complianceRaw, $catalog);

    // Credit Limits
    $creditLimitsRaw = $fetchSetting('policy_console_credit_limits');
    $creditLimits = policy_console_credit_limits_normalize($creditLimitsRaw, $creditPolicyOrig, $creditLimitRulesOrig, $scoreCeiling);

    // Combine them into a single response
    echo json_encode([
        'success' => true,
        'policy' => [
            'credit_limits' => $creditLimits,
            'decision_rules' => $decisionRules,
            'compliance_documents' => $complianceDocs,
        ],
        // Keep this for backwards compatibility if the front end is still using it before updating
        'allowed_employment_statuses' => $decisionRules['decision_rules']['demographics']['eligible_statuses'] ?? ['Employed', 'Self-Employed']
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
