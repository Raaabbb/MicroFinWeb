<?php
header('Content-Type: application/json');
require_once '../auth/session_auth.php';
mf_start_backend_session();
require_once '../config/db_connect.php';
require_once '../engines/credit_policy.php';
require_once '../documents/document_access.php';
mf_require_tenant_session($pdo, [
    'response' => 'json',
    'status' => 401,
    'message' => 'Unauthorized.',
]);

$tenant_id       = (string) ($_SESSION['tenant_id'] ?? '');
$session_user_id = (int)    ($_SESSION['user_id']   ?? 0);

if ($tenant_id === '') {
    echo json_encode(['status' => 'error', 'message' => 'Missing tenant context.']);
    exit;
}

$perm_stmt = $pdo->prepare('
    SELECT p.permission_code 
    FROM role_permissions rp 
    JOIN permissions p ON rp.permission_id = p.permission_id 
    JOIN users u ON u.role_id = rp.role_id
    WHERE u.user_id = ?
');
$perm_stmt->execute([$session_user_id]);
$permissions = $perm_stmt->fetchAll(PDO::FETCH_COLUMN);
function has_perm($code) { global $permissions; return in_array($code, $permissions); }
function client_table_has_column(PDO $pdo, string $column_name): bool {
    static $cache = [];

    $cache_key = strtolower($column_name);
    if (array_key_exists($cache_key, $cache)) {
        return $cache[$cache_key];
    }

    $stmt = $pdo->prepare("
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'clients'
          AND COLUMN_NAME = ?
        LIMIT 1
    ");
    $stmt->execute([$column_name]);
    $cache[$cache_key] = (bool) $stmt->fetchColumn();

    return $cache[$cache_key];
}

function client_effective_verification_sql(PDO $pdo, string $alias = 'c'): string {
    if (client_table_has_column($pdo, 'verification_status')) {
        return "{$alias}.verification_status";
    }

    return "
        CASE
            WHEN {$alias}.document_verification_status = 'Approved' THEN 'Approved'
            WHEN {$alias}.document_verification_status = 'Verified' THEN 'Verified'
            WHEN {$alias}.document_verification_status = 'Rejected' THEN 'Rejected'
            WHEN {$alias}.document_verification_status = 'Pending' THEN 'Pending'
            ELSE 'Unverified'
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
               c.contact_number, c.client_status, c.document_verification_status, c.registration_date,
               c.credit_limit, c.last_seen_credit_limit, c.monthly_income,
               c.occupation, c.employment_status, u.user_type
        FROM clients c
        LEFT JOIN users u ON c.user_id = u.user_id
        WHERE c.tenant_id = ? AND c.deleted_at IS NULL AND c.document_verification_status IN ('Pending', 'Verified', 'Approved') $where_extra
        ORDER BY c.registration_date DESC
        LIMIT 200
    ");
    $stmt->execute($params);
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    require_once __DIR__ . '/engines/credit_limit_engine.php';
    require_once __DIR__ . '/engines/credit_score_engine.php';

    $limitEngine = new CreditLimitEngine($pdo, $tenant_id);
    $scoreEngine = new CreditScoreEngine($pdo, $tenant_id);
    $scoreConfig = $scoreEngine->getConfigSnapshot();

    $statusOrder = [
        'eligible_upgrade' => 0,
        'eligible_downgrade' => 1,
        'not_yet_eligible' => 2,
        'no_active_limit' => 3,
        'at_max_limit' => 4,
    ];

    $matchesFilter = static function (array $upgrade, string $selectedFilter): bool {
        if ($selectedFilter === 'all') {
            return true;
        }

        if ($selectedFilter === 'eligible_upgrade') {
            return ($upgrade['is_eligible_upgrade'] ?? false) === true;
        }

        if ($selectedFilter === 'eligible_downgrade') {
            return ($upgrade['is_eligible_downgrade'] ?? false) === true;
        }

        return (string) ($upgrade['status'] ?? '') === $selectedFilter;
    };

    $matchesScoreFilter = static function (array $limitSnapshot, string $selectedScoreFilter): bool {
        if ($selectedScoreFilter === 'all') {
            return true;
        }

        $labelId = strtolower(trim((string) ($limitSnapshot['band_id'] ?? '')));
        return $labelId === strtolower($selectedScoreFilter);
    };

    $rows = [];
    foreach ($clients as $client) {
        $client_id = (int) ($client['client_id'] ?? 0);
        if ($client_id <= 0) {
            continue;
        }

        // READ-ONLY: Fetch exact current score from history or use starting default
        $scoreLog = mf_credit_policy_fetch_latest_score($pdo, $tenant_id, $client_id);
        $effectiveScore = $scoreLog ? (float) ($scoreLog['total_score'] ?? 0) : (float) $scoreEngine->getStartingScore();

        $upgrade_metrics = mf_credit_policy_fetch_upgrade_metrics($pdo, $tenant_id, $client_id);
        $completedLoans = (int) ($upgrade_metrics['completed_loans'] ?? 0);
        $latePayments = (int) ($upgrade_metrics['late_payments'] ?? 0);
        // Note: For full accuracy, you need these tracked on the borrower.
        // If not available exactly in metrics, we approximate from existing data.
        $hasActiveOverdue = ($upgrade_metrics['has_active_overdue'] ?? false) === true; 
        $maxOverdueDays = (int) ($upgrade_metrics['max_overdue_days'] ?? 0);

        $currentLimit = (float) ($client['credit_limit'] ?? 0);

        $limitBand = $limitEngine->identifyScoreBand($effectiveScore) ?: [];
        $bandLabel = $limitBand['label'] ?? 'Unknown';
        $bandId = $limitBand['id'] ?? 'unknown';

        $potentialLimitInfo = $limitEngine->calculateProgressiveGrowth($currentLimit, $effectiveScore);

        $limit_snapshot = [
            'effective_score' => $effectiveScore,
            'used_default_score' => !$scoreLog,
            'computed_limit' => $potentialLimitInfo['new_limit'] ?? 0,
            'can_compute_limit' => $currentLimit > 0,
            'recommendation_label' => $bandLabel,
            'band_id' => $bandId,
        ];

        // Build Upgrade Progress
        $upgrade_progress = [];
        $is_eligible_upgrade = false;

        $cylRule = $scoreConfig['upgrade_rules']['successful_repayment_cycles'] ?? [];
        if (!empty($cylRule['enabled'])) {
            $reqCyc = (int) ($cylRule['required_cycles'] ?? 3);
            $met = $completedLoans >= $reqCyc;
            if ($met) $is_eligible_upgrade = true;
            $upgrade_progress[] = [
                'label' => 'Successful Repayment Cycles',
                'current' => $completedLoans,
                'target' => $reqCyc,
                'met' => $met,
                'points_amount' => (int) ($cylRule['score_points'] ?? 0)
            ];
        }

        $lateRevRule = $scoreConfig['upgrade_rules']['maximum_late_payments_review'] ?? [];
        if (!empty($lateRevRule['enabled'])) {
            $maxAll = (int) ($lateRevRule['maximum_allowed'] ?? 1);
            $met = $latePayments <= $maxAll && $completedLoans > 0; // Need at least some activity
            if ($met) $is_eligible_upgrade = true;
            $upgrade_progress[] = [
                'label' => 'Maximum Late Payments (Allowed)',
                'current' => $latePayments,
                'target' => $maxAll,
                'met' => $met,
                'points_amount' => (int) ($lateRevRule['score_points'] ?? 0)
            ];
        }

        $noActvRule = $scoreConfig['upgrade_rules']['no_active_overdue'] ?? [];
        if (!empty($noActvRule['enabled'])) {
            $met = !$hasActiveOverdue;
            if ($met) $is_eligible_upgrade = true;
            $upgrade_progress[] = [
                'label' => 'No Active Overdue',
                'current' => $hasActiveOverdue ? 1 : 0,
                'target' => 0,
                'met' => $met,
                'points_amount' => (int) ($noActvRule['score_points'] ?? 0)
            ];
        }

        // Build Downgrade Progress
        $downgrade_progress = [];
        $is_eligible_downgrade = false;

        $downLateRule = $scoreConfig['downgrade_rules']['late_payments_review'] ?? [];
        if (!empty($downLateRule['enabled'])) {
            $trigCount = (int) ($downLateRule['trigger_count'] ?? 2);
            $met = $latePayments >= $trigCount;
            if ($met) $is_eligible_downgrade = true;
            $downgrade_progress[] = [
                'label' => 'Late Payments Trigger',
                'current' => $latePayments,
                'target' => $trigCount,
                'met' => $met,
                'points_amount' => (int) ($downLateRule['score_points'] ?? 0)
            ];
        }

        $downOverdueRule = $scoreConfig['downgrade_rules']['overdue_days_threshold'] ?? [];
        if (!empty($downOverdueRule['enabled'])) {
            $daysThr = (int) ($downOverdueRule['days'] ?? 15);
            $met = $maxOverdueDays >= $daysThr;
            if ($met) $is_eligible_downgrade = true;
            $downgrade_progress[] = [
                'label' => 'Max Consecutive Overdue Days',
                'current' => $maxOverdueDays,
                'target' => $daysThr,
                'met' => $met,
                'points_amount' => (int) ($downOverdueRule['score_points'] ?? 0)
            ];
        }

        $has_met_active_upgrade = false;
        foreach ($upgrade_progress as $rule) {
            if ($rule['met'] && $rule['target'] > 0) {
                $has_met_active_upgrade = true;
                break;
            }
        }
        // Only allow upgrade if they actually met an active progress rule (e.g. 3/3 cycles). 
        // Passive rules (like No Active Overdue 0/0) cannot trigger an upgrade alone.
        if ($is_eligible_upgrade && !$has_met_active_upgrade) {
             $is_eligible_upgrade = false; 
        }
        
        $potential_new_limit = $potentialLimitInfo['new_limit'] ?? 0;
        if ($is_eligible_upgrade && $potential_new_limit <= $currentLimit) {
            $is_eligible_upgrade = false;
        }

        $has_met_active_downgrade = false;
        foreach ($downgrade_progress as $rule) {
            if ($rule['met'] && $rule['target'] > 0) {
                $has_met_active_downgrade = true;
                break;
            }
        }
        if ($is_eligible_downgrade && !$has_met_active_downgrade) {
             $is_eligible_downgrade = false; 
        }

        $status = 'not_yet_eligible';
        if ($currentLimit <= 0) {
            $status = 'no_active_limit';
        } elseif ($is_eligible_upgrade) {
            $status = 'eligible_upgrade';
        } elseif ($is_eligible_downgrade) {
            $status = 'eligible_downgrade';
        }

        $client['credit_upgrade'] = [
            'status' => $status,
            'is_eligible_upgrade' => $is_eligible_upgrade,
            'is_eligible_downgrade' => $is_eligible_downgrade,
            'current_limit' => $currentLimit,
            'potential_upgraded_limit' => $potentialLimitInfo['new_limit'] ?? 0,
            'upgrade_progress' => $upgrade_progress,
            'downgrade_progress' => $downgrade_progress,
        ];
        
        $client['limit_snapshot'] = $limit_snapshot;
        $client['latest_score'] = $scoreLog ?: null;

        if (!$matchesFilter($client['credit_upgrade'], $filter)) {
            continue;
        }

        if (!$matchesScoreFilter($limit_snapshot, $score_filter)) {
            continue;
        }

        $rows[] = $client;
    }

    usort($rows, static function (array $a, array $b) use ($statusOrder): int {
        $aStatus = (string) ($a['credit_upgrade']['status'] ?? '');
        $bStatus = (string) ($b['credit_upgrade']['status'] ?? '');
        $aRank = $statusOrder[$aStatus] ?? 99;
        $bRank = $statusOrder[$bStatus] ?? 99;

        if ($aRank !== $bRank) {
            return $aRank <=> $bRank;
        }

        return strcmp((string) ($b['registration_date'] ?? ''), (string) ($a['registration_date'] ?? ''));
    });

    echo json_encode(['status' => 'success', 'data' => $rows]);
    exit;
}

if ($method === 'GET' && $action === 'view') {
    if (!has_perm('VIEW_CLIENTS') && !has_perm('CREATE_CLIENTS') && !has_perm('VIEW_CREDIT_ACCOUNTS')) {
        echo json_encode(['status' => 'error', 'message' => 'Permission denied.']);
        exit;
    }

    $client_id = (int) ($_GET['client_id'] ?? 0);
    if ($client_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid client ID.']);
        exit;
    }

    $verification_status_sql = client_effective_verification_sql($pdo, 'c');

    $stmt = $pdo->prepare("
        SELECT c.*, {$verification_status_sql} AS verification_status, u.email AS user_email, u.username, u.status AS user_status, u.last_login, u.user_type
        FROM clients c
        JOIN users u ON c.user_id = u.user_id
        WHERE c.client_id = ? AND c.tenant_id = ?
        LIMIT 1
    ");
    $stmt->execute([$client_id, $tenant_id]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$client) {
        echo json_encode(['status' => 'error', 'message' => 'Client not found.']);
        exit;
    }

    // load their loans
    $loans_stmt = $pdo->prepare("
        SELECT l.loan_id, l.loan_number, l.loan_status, l.principal_amount,
               l.remaining_balance, l.monthly_amortization, l.release_date,
               l.maturity_date, l.next_payment_due, l.total_paid,
               lp.product_name
        FROM loans l
        JOIN loan_products lp ON l.product_id = lp.product_id
        WHERE l.client_id = ? AND l.tenant_id = ?
        ORDER BY l.release_date DESC
    ");
    $loans_stmt->execute([$client_id, $tenant_id]);
    $client['loans'] = $loans_stmt->fetchAll(PDO::FETCH_ASSOC);

    // load applications
    $apps_stmt = $pdo->prepare("
        SELECT la.application_id, la.application_number, la.application_status,
               la.requested_amount, la.submitted_date, la.created_at,
               la.loan_term_months, la.application_data,
               JSON_UNQUOTE(JSON_EXTRACT(la.application_data, '$.product_type')) AS product_type,
               lp.product_name
        FROM loan_applications la
        JOIN loan_products lp ON la.product_id = lp.product_id
        WHERE la.client_id = ? AND la.tenant_id = ?
        ORDER BY la.created_at DESC
    ");
    $apps_stmt->execute([$client_id, $tenant_id]);
    $client['applications'] = $apps_stmt->fetchAll(PDO::FETCH_ASSOC);

    // load documents
    $docs_stmt = $pdo->prepare("
        SELECT cd.client_document_id, cd.document_type_id, cd.file_path, cd.verification_status, cd.verification_notes, cd.upload_date,
               dt.document_name, dt.is_required
        FROM client_documents cd
        JOIN document_types dt ON cd.document_type_id = dt.document_type_id
        WHERE cd.client_id = ? AND cd.tenant_id = ?
        ORDER BY dt.is_required DESC, cd.upload_date DESC
    ");
    $docs_stmt->execute([$client_id, $tenant_id]);
    $client['documents'] = array_map(
        static fn(array $document): array => mf_document_attach_url($document),
        $docs_stmt->fetchAll(PDO::FETCH_ASSOC)
    );

    // Credit profile enrichment — READ-ONLY from what the mobile app / walk-in already inserted.
    // No recalculation. The staff panel is a viewer, not a calculator.
    try {
        // 1. Read the latest score row (now includes score_metadata from the engine)
        $latest_score = mf_credit_policy_fetch_latest_score($pdo, $tenant_id, $client_id);
        $client['latest_score'] = $latest_score;

        // 2. Parse the engine data from score_metadata if available
        if ($latest_score && !empty($latest_score['_engine_data'])) {
            $client['engine_data'] = $latest_score['_engine_data'];
        }

        // 3. Upgrade metrics (read-only from loan history)
        $credit_limit_rules = mf_get_tenant_credit_limit_rules($pdo, $tenant_id);
        $upgrade_metrics = mf_credit_policy_fetch_upgrade_metrics($pdo, $tenant_id, $client_id);
        $client['credit_upgrade'] = mf_credit_policy_compute_upgrade_snapshot($credit_limit_rules, $client, $upgrade_metrics);

        // 4. Limit snapshot — read from the score row, not recalculated
        $client['limit_snapshot'] = null;
        if ($latest_score) {
            $client['limit_snapshot'] = [
                'computed_limit' => (float) ($latest_score['max_loan_amount'] ?? 0),
                'can_compute_limit' => ((float) ($client['credit_limit'] ?? 0)) > 0,
                'blocked_reason' => ((float) ($client['credit_limit'] ?? 0)) <= 0
                    ? mf_credit_policy_limit_block_reason($client)
                    : '',
            ];
        }
    } catch (\Throwable $creditErr) {
        // Log the error but still return the client data
        $client['credit_upgrade'] = null;
        $client['latest_score'] = null;
        $client['limit_snapshot'] = null;
        $client['_credit_policy_error'] = $creditErr->getMessage();
    }

    echo json_encode(['status' => 'success', 'data' => $client]);
    exit;
}

// ─── POST: update client status ───────────────────────────────────────────────
if ($method === 'POST' && $action === 'update_status') {
    if (!has_perm('CREATE_CLIENTS')) {
        echo json_encode(['status' => 'error', 'message' => 'Permission denied.']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    if (!is_array($payload)) $payload = $_POST;

    $client_id  = (int)    ($payload['client_id']  ?? 0);
    $new_status = trim((string) ($payload['status'] ?? ''));

    $allowed = ['Active', 'Inactive', 'Blacklisted'];
    if ($client_id <= 0 || !in_array($new_status, $allowed)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid client ID or status.']);
        exit;
    }

    $pdo->prepare("UPDATE clients SET client_status = ?, updated_at = NOW() WHERE client_id = ? AND tenant_id = ?")
        ->execute([$new_status, $client_id, $tenant_id]);

    mf_sync_client_credit_profile($pdo, $tenant_id, $client_id);

    $pdo->prepare("INSERT INTO audit_logs (user_id, tenant_id, action_type, entity_type, entity_id, description) VALUES (?, ?, 'CLIENT_STATUS_CHANGE', 'client', ?, ?)")
        ->execute([$session_user_id, $tenant_id, $client_id, "Client status updated to $new_status"]);

    echo json_encode(['status' => 'success', 'message' => "Client status updated to $new_status."]);
    exit;
}

// ─── POST: verify document ──────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'verify_document') {
    if (!has_perm('CREATE_CLIENTS')) {
        echo json_encode(['status' => 'error', 'message' => 'Permission denied.']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    if (!is_array($payload)) $payload = $_POST;

    $doc_id = (int)($payload['document_id'] ?? 0);
    $status = trim((string)($payload['status'] ?? ''));
    $rejection_reason = trim((string)($payload['rejection_reason'] ?? ''));

    if ($doc_id <= 0 || !in_array($status, ['Verified', 'Rejected'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid input.']);
        exit;
    }
    
    if ($status === 'Rejected' && $rejection_reason === '') {
        echo json_encode(['status' => 'error', 'message' => 'A rejection reason is required when rejecting a document.']);
        exit;
    }
    
    if ($status === 'Verified') {
        $rejection_reason = null;
    }

    try {
        $pdo->beginTransaction();

        // Resolve employee_id from user_id (verified_by is a FK to employees)
        $emp_row = $pdo->prepare("SELECT employee_id FROM employees WHERE user_id = ? AND tenant_id = ? LIMIT 1");
        $emp_row->execute([$session_user_id, $tenant_id]);
        $emp = $emp_row->fetch(PDO::FETCH_ASSOC);
        $verified_by = $emp ? $emp['employee_id'] : null;

        $pdo->prepare("UPDATE client_documents SET verification_status = ?, verification_notes = ?, verified_by = ?, verification_date = NOW() WHERE client_document_id = ? AND tenant_id = ?")
            ->execute([$status, $rejection_reason, $verified_by, $doc_id, $tenant_id]);

        // Cascade update to clients table (document_verification_status)
        $pdo->prepare("
            UPDATE clients c
            SET document_verification_status = (
                CASE
                    WHEN (SELECT COUNT(*) FROM client_documents WHERE client_id = c.client_id AND tenant_id = c.tenant_id AND verification_status = 'Rejected') > 0 THEN 'Rejected'
                    WHEN (SELECT COUNT(*) FROM client_documents WHERE client_id = c.client_id AND tenant_id = c.tenant_id AND verification_status IN ('Pending', 'Uploaded', 'CONSIDER')) > 0 THEN 'Pending'
                    ELSE 'Verified'
                END
            )
            WHERE client_id = (SELECT client_id FROM client_documents WHERE client_document_id = ? LIMIT 1)
        ")->execute([$doc_id]);

        $target_client_stmt = $pdo->prepare("SELECT client_id FROM client_documents WHERE client_document_id = ? LIMIT 1");
        $target_client_stmt->execute([$doc_id]);
        $target_client_id = (int) $target_client_stmt->fetchColumn();

        if (client_table_has_column($pdo, 'verification_status')) {
            $pdo->prepare("
                UPDATE clients
                SET verification_status = (
                    CASE
                        WHEN document_verification_status IN ('Verified', 'Approved') THEN 'Approved'
                        WHEN document_verification_status = 'Rejected' THEN 'Rejected'
                        ELSE 'Pending'
                    END
                ),
                updated_at = NOW()
                WHERE client_id = ? AND tenant_id = ?
            ")->execute([$target_client_id, $tenant_id]);
        }

        if ($target_client_id > 0) {
            mf_sync_client_credit_profile($pdo, $tenant_id, $target_client_id);
        }

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => "Document marked as $status."]);
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['status' => 'error', 'message' => 'Unable to verify document: ' . $e->getMessage()]);
    }
    exit;
}

// ─── POST: verify entire client ──────────────────────────────────────────────
if ($method === 'POST' && $action === 'verify_client_fully') {
    if (!has_perm('CREATE_CLIENTS')) {
        echo json_encode(['status' => 'error', 'message' => 'Permission denied.']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    if (!is_array($payload)) $payload = $_POST;

    $client_id = (int)($payload['client_id'] ?? 0);

    if ($client_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid client ID.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $client_status_stmt = $pdo->prepare("SELECT client_status FROM clients WHERE client_id = ? AND tenant_id = ? LIMIT 1");
        $client_status_stmt->execute([$client_id, $tenant_id]);
        $current_client_status = (string) $client_status_stmt->fetchColumn();

        // Resolve employee_id from user_id (verified_by is a FK to employees)
        $emp_row2 = $pdo->prepare("SELECT employee_id FROM employees WHERE user_id = ? AND tenant_id = ? LIMIT 1");
        $emp_row2->execute([$session_user_id, $tenant_id]);
        $emp2 = $emp_row2->fetch(PDO::FETCH_ASSOC);
        $verified_by2 = $emp2 ? $emp2['employee_id'] : null;

        // Force ALL documents to be verified and clear rejection notes
        $pdo->prepare("UPDATE client_documents SET verification_status = 'Verified', verification_notes = NULL, verified_by = ?, verification_date = NOW() WHERE client_id = ? AND tenant_id = ? AND verification_status != 'Verified'")
            ->execute([$verified_by2, $client_id, $tenant_id]);

        $client_verify_sql = "
            UPDATE clients
            SET document_verification_status = 'Verified',
                updated_at = NOW()
            WHERE client_id = ? AND tenant_id = ?
        ";

        if (client_table_has_column($pdo, 'verification_status')) {
            $client_verify_sql = "
                UPDATE clients
                SET document_verification_status = 'Verified',
                    verification_status = 'Verified',
                    updated_at = NOW()
                WHERE client_id = ? AND tenant_id = ?
            ";
        }

        $pdo->prepare($client_verify_sql)->execute([$client_id, $tenant_id]);
        mf_sync_client_credit_profile($pdo, $tenant_id, $client_id);
        $pdo->prepare("INSERT INTO audit_logs (user_id, tenant_id, action_type, entity_type, entity_id, description) VALUES (?, ?, 'DOCUMENTS_VERIFIED', 'client', ?, 'Admin verified all submitted documents')")
            ->execute([$session_user_id, $tenant_id, $client_id]);

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'Documents successfully verified. Awaiting final approval.']);
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['status' => 'error', 'message' => 'Unable to verify client documents: ' . $e->getMessage()]);
    }
    exit;
}

// ─── POST: apply final approval to client ────────────────────────────────────
if ($method === 'POST' && $action === 'approve_client') {
    if (!has_perm('CREATE_CLIENTS')) {
        echo json_encode(['status' => 'error', 'message' => 'Permission denied.']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    if (!is_array($payload)) $payload = $_POST;

    $client_id = (int)($payload['client_id'] ?? 0);

    if ($client_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid client ID.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $client_status_stmt = $pdo->prepare("SELECT client_status, document_verification_status FROM clients WHERE client_id = ? AND tenant_id = ? LIMIT 1");
        $client_status_stmt->execute([$client_id, $tenant_id]);
        $client_current = $client_status_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$client_current) {
             throw new Exception("Client not found.");
        }
        
        if ($client_current['document_verification_status'] !== 'Verified') {
             throw new Exception("Client must be marked as Verified before they can be Approved.");
        }

        $client_approve_sql = "
            UPDATE clients
            SET document_verification_status = 'Approved',
                client_status = CASE WHEN client_status = 'Inactive' THEN 'Active' ELSE client_status END,
                updated_at = NOW()
            WHERE client_id = ? AND tenant_id = ?
        ";

        if (client_table_has_column($pdo, 'verification_status')) {
            $client_approve_sql = "
                UPDATE clients
                SET document_verification_status = 'Approved',
                    client_status = CASE WHEN client_status = 'Inactive' THEN 'Active' ELSE client_status END,
                    verification_status = 'Approved',
                    updated_at = NOW()
                WHERE client_id = ? AND tenant_id = ?
            ";
        }

        $pdo->prepare($client_approve_sql)->execute([$client_id, $tenant_id]);
        mf_sync_client_credit_profile($pdo, $tenant_id, $client_id);

        $hasPolicyMetadataColumn = client_table_has_column($pdo, 'policy_metadata');
        $clientMetaFields = $hasPolicyMetadataColumn
            ? 'policy_metadata, monthly_income, credit_limit'
            : 'monthly_income, credit_limit';

        // Read the client current data for the engine
        $meta_stmt = $pdo->prepare("SELECT {$clientMetaFields} FROM clients WHERE client_id = ? AND tenant_id = ? LIMIT 1");
        $meta_stmt->execute([$client_id, $tenant_id]);
        $client_row = $meta_stmt->fetch(PDO::FETCH_ASSOC);

        if ($client_row) {
            $policyMeta    = $hasPolicyMetadataColumn
                ? (json_decode((string) ($client_row['policy_metadata'] ?? ''), true) ?: [])
                : [];
            $monthlyIncome = (float) str_replace(',', '', (string) ($client_row['monthly_income'] ?? 0));

            // ── ALWAYS recalculate from the CURRENT admin policy at approval time ──
            // This guarantees credit_limit matches exactly what admin/staff panel shows:
            //   Income x initial_limit_percent from live policy_console_credit_limits.
            $promotedLimit = 0.0;

            if ($monthlyIncome > 0) {
                try {
                    require_once __DIR__ . '/../engines/credit_limit_engine.php';
                    require_once __DIR__ . '/../engines/credit_score_engine.php';

                    $limitEngine = new CreditLimitEngine($pdo, $tenant_id);
                    $scoreEngine = new CreditScoreEngine($pdo, $tenant_id);

                    $limitResult   = $limitEngine->calculateInitialLimit($monthlyIncome);
                    $promotedLimit = (float) ($limitResult['limit'] ?? 0);

                    $startingScore = $scoreEngine->getStartingScore();
                    $band          = $limitEngine->identifyScoreBand($startingScore);

                    // Upsert a credit_scores row so the staff Credit Accounts tab
                    // immediately reflects the engine-calculated score and limit.
                    $pdo->prepare("
                        INSERT INTO credit_scores
                            (client_id, tenant_id, credit_score, credit_rating, max_loan_amount, notes, score_metadata, computation_date)
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                    ")->execute([
                        $client_id,
                        $tenant_id,
                        $startingScore,
                        $band['label'] ?? 'Standard',
                        $promotedLimit,
                        'Auto-assigned on admin approval via CreditLimitEngine.',
                        json_encode([
                            'basis'                  => 'Admin approval recalculation',
                            'income_at_approval'     => $monthlyIncome,
                            'config_percent'         => $limitResult['initial_limit_percent'] ?? 0,
                            'engine_reason'          => $limitResult['reason'] ?? '',
                            'approved_by_user_id'    => $session_user_id,
                            'approved_at'            => date('Y-m-d H:i:s'),
                        ]),
                    ]);

                    // Update policy_metadata audit trail
                    $policyMeta['approved_limit']          = $promotedLimit;
                    $policyMeta['approved_starting_score'] = $startingScore;
                    $policyMeta['approved_score_band']     = $band['label'] ?? 'Standard';
                    $policyMeta['approved_by_user_id']     = $session_user_id;
                    $policyMeta['approved_at']             = date('Y-m-d H:i:s');
                    $policyMeta['approval_engine_snapshot']= $limitResult;

                } catch (\Throwable $engineErr) {
                    $policyMeta['approval_engine_error'] = $engineErr->getMessage();
                }
            }

            // Always write the calculated credit_limit (0 if income missing / engine failed)
            if ($hasPolicyMetadataColumn) {
                $pdo->prepare("UPDATE clients SET credit_limit = ?, policy_metadata = ?, updated_at = NOW() WHERE client_id = ? AND tenant_id = ?")
                    ->execute([$promotedLimit, json_encode($policyMeta), $client_id, $tenant_id]);
            } else {
                $pdo->prepare("UPDATE clients SET credit_limit = ?, updated_at = NOW() WHERE client_id = ? AND tenant_id = ?")
                    ->execute([$promotedLimit, $client_id, $tenant_id]);
            }
        }

        $pdo->prepare("INSERT INTO audit_logs (user_id, tenant_id, action_type, entity_type, entity_id, description) VALUES (?, ?, 'CLIENT_APPROVED', 'client', ?, 'Admin manually approved and activated client')")
            ->execute([$session_user_id, $tenant_id, $client_id]);

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'Client has been fully Approved and Activated.']);
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['status' => 'error', 'message' => 'Unable to approve client: ' . $e->getMessage()]);
    }
    exit;
}

// ─── POST: process_credit_action ────────────────────────────────────────────────
if ($method === 'POST' && $action === 'process_credit_action') {
    if (!has_perm('APPROVE_LOANS') && !has_perm('CREATE_CLIENTS') && !has_perm('VIEW_CREDIT_ACCOUNTS')) {
        echo json_encode(['status' => 'error', 'message' => 'Permission denied. You do not have sufficient permissions to modify credit limits.']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    $client_id = (int) ($payload['client_id'] ?? 0);
    $type = trim((string) ($payload['type'] ?? ''));

    if ($client_id <= 0 || !in_array($type, ['upgrade', 'downgrade'], true)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid client ID or action type.']);
        exit;
    }

    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("SELECT * FROM clients WHERE client_id = ? AND tenant_id = ? LIMIT 1");
        $stmt->execute([$client_id, $tenant_id]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$client) {
            throw new Exception("Client not found.");
        }

        $currentLimit = (float)($client['credit_limit'] ?? 0);
        if ($currentLimit <= 0) {
            throw new Exception("Client has no active credit limit.");
        }

        require_once __DIR__ . '/engines/credit_limit_engine.php';
        require_once __DIR__ . '/engines/credit_score_engine.php';

        $limitEngine = new CreditLimitEngine($pdo, $tenant_id);
        $scoreEngine = new CreditScoreEngine($pdo, $tenant_id);
        
        $scoreLog = mf_credit_policy_fetch_latest_score($pdo, $tenant_id, $client_id);
        $effectiveScore = $scoreLog ? (float) ($scoreLog['total_score'] ?? 0) : (float) $scoreEngine->getStartingScore();

        // Fetch borrower behaviour metrics needed by calculateNetScoreChange()
        $upgrade_metrics   = mf_credit_policy_fetch_upgrade_metrics($pdo, $tenant_id, $client_id);
        $completedLoans    = (int) ($upgrade_metrics['completed_loans']  ?? 0);
        $latePayments      = (int) ($upgrade_metrics['late_payments']    ?? 0);
        $hasActiveOverdue  = ($upgrade_metrics['has_active_overdue']     ?? false) === true;
        $maxOverdueDays    = (int) ($upgrade_metrics['max_overdue_days'] ?? 0);

        if ($type === 'upgrade') {
            $potentialLimitInfo = $limitEngine->calculateProgressiveGrowth($currentLimit, $effectiveScore);
            $new_limit = round(max($currentLimit, $potentialLimitInfo['new_limit'] ?? 0), 2);

            if ($new_limit <= $currentLimit + 0.01) {
                throw new Exception("Recommended upgraded limit matches or is lower than current limit.");
            }

            $pdo->prepare("UPDATE clients SET credit_limit = ?, updated_at = NOW() WHERE client_id = ? AND tenant_id = ?")
                ->execute([$new_limit, $client_id, $tenant_id]);

            $pdo->prepare("INSERT INTO audit_logs (user_id, tenant_id, action_type, entity_type, entity_id, description) VALUES (?, ?, 'CREDIT_LIMIT_UPGRADED', 'client', ?, ?)")
                ->execute([$session_user_id, $tenant_id, $client_id, "Credit limit upgraded to {$new_limit} from {$currentLimit}"]);
                
            $scoreEngine->calculateNetScoreChange($completedLoans, $latePayments, $hasActiveOverdue, $maxOverdueDays);
            $msg = "Successfully upgraded borrower's credit limit to " . number_format($new_limit, 2) . ".";
        } else {
            // Downgrade: DECREASE the limit utilizing the opposite math of the micro-percentage matrix
            $limitBand = $limitEngine->identifyScoreBand($effectiveScore) ?: [];
            $base_growth_percent = (float) ($limitBand['base_growth_percent'] ?? 0.05); // Default to 5% if none
            $micro_percent_per_point = (float) ($limitBand['micro_percent_per_point'] ?? 0);
            
            $deduction = $base_growth_percent + ($effectiveScore * $micro_percent_per_point);
            if ($deduction <= 0) $deduction = 0.10; // minimum 10% downgrade penalty
            if ($deduction > 0.50) $deduction = 0.50; // clamp max downgrade penalty to 50%
            
            $new_limit = round(max(0, $currentLimit * (1 - $deduction)), 2);

            $pdo->prepare("UPDATE clients SET credit_limit = ?, updated_at = NOW() WHERE client_id = ? AND tenant_id = ?")
                ->execute([$new_limit, $client_id, $tenant_id]);

            $pdo->prepare("INSERT INTO audit_logs (user_id, tenant_id, action_type, entity_type, entity_id, description) VALUES (?, ?, 'CREDIT_LIMIT_DOWNGRADED', 'client', ?, ?)")
                ->execute([$session_user_id, $tenant_id, $client_id, "Credit limit downgraded to {$new_limit} from {$currentLimit}"]);
                
            $scoreEngine->calculateNetScoreChange($completedLoans, $latePayments, $hasActiveOverdue, $maxOverdueDays);
            $msg = "Successfully downgraded borrower's credit limit to " . number_format($new_limit, 2) . ".";
        }

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => $msg]);
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['status' => 'error', 'message' => 'Unable to process action: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
