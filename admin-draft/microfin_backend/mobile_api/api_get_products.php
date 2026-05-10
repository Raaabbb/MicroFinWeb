<?php
require_once __DIR__ . '/api_utils.php';
microfin_api_bootstrap();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/loan_application_rules.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    microfin_json_response(['success' => false, 'message' => 'Invalid request method.'], 405);
}

$tenantFilter = microfin_clean_string($_GET['tenant_id'] ?? $_GET['tenant'] ?? '');
if ($tenantFilter === '') {
    microfin_json_response(['success' => false, 'message' => 'tenant_id is required.'], 422);
}

$tenantSql = "
    SELECT tenant_id
    FROM tenants
    WHERE deleted_at IS NULL
      AND (
            LOWER(tenant_id) = LOWER(?)
            OR LOWER(COALESCE(tenant_slug, '')) = LOWER(?)
      )
    LIMIT 1
";

$tenantStmt = $conn->prepare($tenantSql);
if (!$tenantStmt) {
    microfin_json_response([
        'success' => false,
        'message' => 'Failed to prepare tenant lookup: ' . $conn->error,
    ], 500);
}

$tenantStmt->bind_param('ss', $tenantFilter, $tenantFilter);
$tenantStmt->execute();
$tenantRow = $tenantStmt->get_result()->fetch_assoc() ?: null;
$tenantStmt->close();

if (!$tenantRow || trim((string) ($tenantRow['tenant_id'] ?? '')) === '') {
    microfin_json_response(['success' => false, 'message' => 'Tenant not found.'], 404);
}

$tenantId = trim((string) $tenantRow['tenant_id']);
$userId = (int) ($_GET['user_id'] ?? 0);

$productSql = "
    SELECT
        product_id,
        product_id AS id,
        product_name,
        product_name AS name,
        'Loan Product' AS product_type,
        'Loan Product' AS type,
        '' AS description,
        min_amount,
        min_amount AS min,
        max_amount,
        max_amount AS max,
        interest_rate,
        interest_rate AS rate,
        COALESCE(interest_type, '') AS interest_type,
        min_term_months,
        min_term_months AS min_term,
        max_term_months,
        max_term_months AS max_term,
        COALESCE(processing_fee_percentage, 0) AS processing_fee_percentage,
        COALESCE(service_charge, 0) AS service_charge,
        COALESCE(documentary_stamp, 0) AS documentary_stamp,
        COALESCE(insurance_fee_percentage, 0) AS insurance_fee_percentage,
        COALESCE(early_settlement_fee_type, 'Percentage') AS early_settlement_fee_type,
        COALESCE(early_settlement_fee_value, 0) AS early_settlement_fee_value,
        COALESCE(billing_cycle, 'Monthly') AS billing_cycle,
        COALESCE(grace_period_days, 0) AS grace_period_days,
        CAST(COALESCE(is_active, 1) AS CHAR) AS is_active
    FROM loan_products
    WHERE tenant_id = ?
      AND COALESCE(is_active, 1) = 1
    ORDER BY product_name ASC, product_id DESC
";

$productStmt = $conn->prepare($productSql);
if (!$productStmt) {
    microfin_json_response([
        'success' => false,
        'message' => 'Failed to prepare product lookup: ' . $conn->error,
    ], 500);
}

$productStmt->bind_param('s', $tenantId);
$productStmt->execute();
$result = $productStmt->get_result();

$products = [];
while ($row = $result->fetch_assoc()) {
    $row['product_id'] = (int) ($row['product_id'] ?? 0);
    $row['id'] = (int) ($row['id'] ?? 0);
    $row['min_amount'] = (float) ($row['min_amount'] ?? 0);
    $row['min'] = (float) ($row['min'] ?? 0);
    $row['max_amount'] = (float) ($row['max_amount'] ?? 0);
    $row['max'] = (float) ($row['max'] ?? 0);
    $row['interest_rate'] = (float) ($row['interest_rate'] ?? 0);
    $row['rate'] = (float) ($row['rate'] ?? 0);
    $row['min_term_months'] = (int) ($row['min_term_months'] ?? 0);
    $row['min_term'] = (int) ($row['min_term'] ?? 0);
    $row['max_term_months'] = (int) ($row['max_term_months'] ?? 0);
    $row['max_term'] = (int) ($row['max_term'] ?? 0);
    $row['processing_fee_percentage'] = (float) ($row['processing_fee_percentage'] ?? 0);
    $row['service_charge'] = (float) ($row['service_charge'] ?? 0);
    $row['documentary_stamp'] = (float) ($row['documentary_stamp'] ?? 0);
    $row['insurance_fee_percentage'] = (float) ($row['insurance_fee_percentage'] ?? 0);
    $row['early_settlement_fee_type'] = (string) ($row['early_settlement_fee_type'] ?? 'Percentage');
    $row['early_settlement_fee_value'] = (float) ($row['early_settlement_fee_value'] ?? 0);
    $row['billing_cycle'] = (string) ($row['billing_cycle'] ?? 'Monthly');
    $row['grace_period_days'] = (int) ($row['grace_period_days'] ?? 0);
    $products[] = $row;
}

$productStmt->close();

$creditSummary = null;
$loanAccessState = null;

if ($userId > 0) {
    $clientProfile = microfin_find_client_loan_profile($conn, $userId, $tenantId);

    if ($clientProfile && $clientProfile['client_id'] > 0) {
        try {
            global $dbConfig;
            $pdo = new PDO(
                "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset=utf8mb4",
                $dbConfig['username'],
                $dbConfig['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            require_once __DIR__ . '/../../microfin_backend/credit_policy.php';
            $profileSync = mf_sync_client_credit_profile($pdo, $tenantId, (int) $clientProfile['client_id']);
            if (isset($profileSync['client']['credit_limit'])) {
                $clientProfile['credit_limit'] = (float) $profileSync['client']['credit_limit'];
            }
        } catch (\Throwable $pe) {
            error_log('Failed syncing profile limit in mobile API: ' . $pe->getMessage());
        }
        // Fallback: if active credit_limit is 0, use potential_limit from policy_metadata
        if ($clientProfile['credit_limit'] <= 0) {
            $rawMeta = $clientProfile['policy_metadata'] ?? '{}';
            $policyMeta = is_string($rawMeta) ? (json_decode($rawMeta, true) ?: []) : ($rawMeta ?: []);
            $potentialLimit = (float) ($policyMeta['potential_limit'] ?? 0);
            if ($potentialLimit > 0) {
                $clientProfile['credit_limit'] = $potentialLimit;
            }
        }
    }

    $creditSummary = $clientProfile
        ? microfin_build_client_loan_application_summary($conn, $clientProfile)
        : microfin_loan_rules_default_summary($tenantId);

    // Fetch tenant rules
    $rulesStmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE tenant_id = ? AND setting_key = 'policy_console_decision_rules'");
    $rulesStmt->bind_param('s', $tenantId);
    $rulesStmt->execute();
    $rulesRaw = json_decode($rulesStmt->get_result()->fetch_assoc()['setting_value'] ?? '{}', true) ?: [];
    $rulesStmt->close();

    require_once __DIR__ . '/../../microfin_web/admin_panel/includes/policy_console_system_defaults.php';
    require_once __DIR__ . '/../../microfin_web/admin_panel/includes/policy_console_decision_rules.php';
    $decisionRules = policy_console_decision_rules_normalize($rulesRaw, 850);
    $guardRules = $decisionRules['decision_rules']['guardrails'] ?? [];
    $expenseRules = $decisionRules['decision_rules']['exposure'] ?? [];
    $affordRules = $decisionRules['decision_rules']['affordability'] ?? [];

    $creditSummary['rules'] = [
        'multiple_active_loans_enabled' => !empty($expenseRules['multiple_active_loans_enabled']),
        'auto_reject_floor' => !empty($guardRules['score_thresholds_enabled']) ? (int)($guardRules['auto_reject_floor'] ?? 0) : 0,
        // we'll pass dti/pti down to frontend later
        'dti_enabled' => !empty($affordRules['dti_enabled']),
        'max_dti_percentage' => (float)($affordRules['max_dti_percentage'] ?? 45.0),
        'pti_enabled' => !empty($affordRules['pti_enabled']),
        'max_pti_percentage' => (float)($affordRules['max_pti_percentage'] ?? 30.0),
    ];

    $products = microfin_annotate_loan_products($products, $creditSummary);
    $loanAccessState = microfin_build_loan_access_state($products, $creditSummary);
}

$payload = [
    'success' => true,
    'tenant_id' => $tenantId,
    'products' => $products,
];

if ($creditSummary !== null) {
    $payload['credit_summary'] = $creditSummary;
    $payload['loan_access_state'] = $loanAccessState;
}

microfin_json_response($payload);

