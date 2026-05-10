<?php

$file = 'c:\\xampp\\htdocs\\admin-draft-withmobile\\admin-draft\\microfin_platform\\backend\\api_clients.php';
$content = file_get_contents($file);

$good_code = <<<'PHP'
function client_effective_verification_sql(PDO $pdo, string $alias = 'c'): string {
    if (client_table_has_column($pdo, 'verification_status')) {
        return "{$alias}.verification_status";
    }

    return "
        CASE
            WHEN {$alias}.document_verification_status IN ('Verified', 'Approved') THEN 'Approved'
            WHEN {$alias}.document_verification_status = 'Rejected' THEN 'Rejected'
            ELSE 'Pending'
        END
    ";
}

$action = strtolower(trim((string) ($_GET['action'] ?? $_POST['action'] ?? '')));
$method = $_SERVER['REQUEST_METHOD'];

// ─── GET: list clients ────────────────────────────────────────────────────────
if ($method === 'GET' && ($action === 'list' || $action === '')) {
    if (!has_perm('VIEW_CLIENTS') && !has_perm('CREATE_CLIENTS')) {
        echo json_encode(['status' => 'error', 'message' => 'Permission denied.']);
        exit;
    }

    $search = trim((string) ($_GET['search'] ?? ''));
    $params = [$tenant_id];
    $where_extra = '';
    if ($search !== '') {
        $q = '%' . $search . '%';
        $where_extra = ' AND (c.first_name LIKE ? OR c.last_name LIKE ? OR c.email_address LIKE ? OR c.contact_number LIKE ?)';
        $params[] = $q; $params[] = $q; $params[] = $q; $params[] = $q;
    }

    $stmt = $pdo->prepare("
        SELECT c.client_id, c.first_name, c.last_name, c.email_address,
               c.contact_number, c.client_status, c.document_verification_status, c.registration_date,
               c.credit_limit, c.date_of_birth, c.occupation, c.monthly_income,
               c.present_city, c.present_province, u.user_type,
               COUNT(DISTINCT la.application_id) AS total_applications,
               COUNT(DISTINCT l.loan_id) AS total_loans,
               COALESCE(SUM(CASE WHEN l.loan_status = 'Active' THEN l.remaining_balance END), 0) AS active_balance
        FROM clients c
        LEFT JOIN users u ON c.user_id = u.user_id
        LEFT JOIN loan_applications la ON la.client_id = c.client_id AND la.tenant_id = c.tenant_id
        LEFT JOIN loans l ON l.client_id = c.client_id AND l.tenant_id = c.tenant_id
        WHERE c.tenant_id = ? AND c.deleted_at IS NULL $where_extra
        GROUP BY c.client_id
        ORDER BY c.registration_date DESC
        LIMIT 200
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'data' => $rows]);
    exit;
}

// ─── GET: view single client with loans ───────────────────────────────────────
if ($method === 'GET' && $action === 'credit_accounts') {
    if (!has_perm('VIEW_CREDIT_ACCOUNTS') && !has_perm('VIEW_CLIENTS') && !has_perm('CREATE_CLIENTS')) {
        echo json_encode(['status' => 'error', 'message' => 'Permission denied.']);
        exit;
    }

    $search = trim((string) ($_GET['search'] ?? ''));
    $filter = strtolower(trim((string) ($_GET['filter'] ?? 'all')));
    $score_filter = strtolower(trim((string) ($_GET['score_filter'] ?? 'all')));
    $allowedFilters = ['all', 'eligible', 'not_yet_eligible', 'no_active_limit', 'at_max_limit'];
    if (!in_array($filter, $allowedFilters, true)) {
        $filter = 'all';
    }
    $allowedScoreFilters = ['all', 'high_credit', 'good_credit', 'standard_credit', 'fair_credit', 'at_risk_credit'];
    if (!in_array($score_filter, $allowedScoreFilters, true)) {
        $score_filter = 'all';
    }

    $params = [$tenant_id];
    $where_extra = '';
    if ($search !== '') {
        $q = '%' . $search . '%';
        $where_extra = ' AND (c.first_name LIKE ? OR c.last_name LIKE ? OR c.email_address LIKE ? OR c.contact_number LIKE ?)';
        $params[] = $q;
        $params[] = $q;
        $params[] = $q;
        $params[] = $q;
    }

    $stmt = $pdo->prepare("
        SELECT c.client_id, c.first_name, c.last_name, c.email_address,
               c.contact_number, c.client_status, c.registration_date,
               c.credit_limit, c.last_seen_credit_limit, c.monthly_income,
               c.occupation, c.employment_status, u.user_type
        FROM clients c
        LEFT JOIN users u ON c.user_id = u.user_id
        WHERE c.tenant_id = ? AND c.deleted_at IS NULL $where_extra
        ORDER BY c.registration_date DESC
        LIMIT 200
    ");
PHP;

// Find everything from `function client_effective_verification_sql` all the way down to `    $stmt->execute($params); \n    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);`

$pos_start = strpos($content, 'function client_effective_verification_sql');
$pos_end = strpos($content, '$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);', $pos_start);

if ($pos_start !== false && $pos_end !== false) {
    $content = substr($content, 0, $pos_start) . $good_code . substr($content, $pos_end);
    file_put_contents($file, $content);
    echo "Done replace";
} else {
    echo "Failed to find delimiters!";
}
