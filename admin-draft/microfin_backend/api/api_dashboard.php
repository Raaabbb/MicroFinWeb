<?php
header('Content-Type: application/json');
require_once '../auth/session_auth.php';
mf_start_backend_session();
require_once '../config/db_connect.php';
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

$action = strtolower(trim((string) ($_GET['action'] ?? $_POST['action'] ?? '')));
$method = $_SERVER['REQUEST_METHOD'];

// ─── GET: dashboard stats ─────────────────────────────────────────────────────
if ($method === 'GET' && ($action === 'stats' || $action === '')) {

    $stats = [];

    // Pending applications count
    if (has_perm('VIEW_APPLICATIONS') || has_perm('MANAGE_APPLICATIONS')) {
        $s = $pdo->prepare("SELECT COUNT(*) FROM loan_applications WHERE tenant_id = ? AND application_status NOT IN ('Approved','Rejected','Cancelled','Withdrawn')");
        $s->execute([$tenant_id]);
        $stats['pending_applications'] = (int) $s->fetchColumn();
    }

    // Today's collections
    if (has_perm('PROCESS_PAYMENTS')) {
        $s = $pdo->prepare("SELECT 
            (SELECT COALESCE(SUM(payment_amount), 0) FROM payments WHERE tenant_id = ? AND DATE(payment_date) = CURDATE() AND payment_status != 'Cancelled') +
            (SELECT COALESCE(SUM(amount), 0) FROM payment_transactions WHERE tenant_id = ? AND DATE(payment_date) = CURDATE() AND status != 'Cancelled')");
        $s->execute([$tenant_id, $tenant_id]);
        $stats['todays_collections'] = (float) $s->fetchColumn();
    }

    // Active clients
    if (has_perm('VIEW_CLIENTS') || has_perm('CREATE_CLIENTS')) {
        $s = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE tenant_id = ? AND client_status = 'Active' AND deleted_at IS NULL");
        $s->execute([$tenant_id]);
        $stats['active_clients'] = (int) $s->fetchColumn();
    }

    // Active loans
    if (has_perm('VIEW_LOANS')) {
        $s = $pdo->prepare("SELECT COUNT(*) FROM loans WHERE tenant_id = ? AND loan_status = 'Active'");
        $s->execute([$tenant_id]);
        $stats['active_loans'] = (int) $s->fetchColumn();

        $s2 = $pdo->prepare("SELECT COALESCE(SUM(remaining_balance), 0) FROM loans WHERE tenant_id = ? AND loan_status IN ('Active','Overdue')");
        $s2->execute([$tenant_id]);
        $stats['total_portfolio'] = (float) $s2->fetchColumn();

        $s3 = $pdo->prepare("SELECT COUNT(*) FROM loans WHERE tenant_id = ? AND loan_status = 'Overdue'");
        $s3->execute([$tenant_id]);
        $stats['overdue_loans'] = (int) $s3->fetchColumn();
    }

    // Recent audit log entries (last 10)
    $s = $pdo->prepare("SELECT action_type, description, created_at FROM audit_logs WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 10");
    $s->execute([$tenant_id]);
    $stats['recent_activity'] = $s->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'data' => $stats]);
    exit;
}

// ─── GET: reports overview ─────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'reports') {
    if (!has_perm('VIEW_REPORTS')) {
        echo json_encode(['status' => 'error', 'message' => 'Permission denied.']);
        exit;
    }

    $period = trim((string) ($_GET['period'] ?? 'month'));

    $today = new DateTimeImmutable('today');
    $date_from = match ($period) {
        'week'  => $today->modify('-6 days')->format('Y-m-d'),
        'month' => $today->format('Y-m-01'),
        'year'  => $today->format('Y-01-01'),
        default => $today->format('Y-m-01'),
    };
    $date_to = $today->format('Y-m-d');
    $date_to_exclusive = $today->modify('+1 day')->format('Y-m-d');

    try {
        $data = [
            'period' => $period,
            'date_from' => $date_from,
            'date_to' => $date_to,
            'range_label' => date('M j, Y', strtotime($date_from)) . ' to ' . date('M j, Y', strtotime($date_to)),
            'summary_note' => 'Successful transactions only. This report combines staff-posted collections and client-app payments for the selected period.',
        ];

        $summaryStmt = $pdo->prepare("
            SELECT
                COUNT(*) AS total_transactions,
                COALESCE(SUM(amount), 0) AS total_amount,
                SUM(CASE WHEN source_key = 'staff' THEN 1 ELSE 0 END) AS staff_transactions,
                COALESCE(SUM(CASE WHEN source_key = 'staff' THEN amount ELSE 0 END), 0) AS staff_amount,
                SUM(CASE WHEN source_key = 'client' THEN 1 ELSE 0 END) AS client_transactions,
                COALESCE(SUM(CASE WHEN source_key = 'client' THEN amount ELSE 0 END), 0) AS client_amount,
                COUNT(DISTINCT client_id) AS unique_clients,
                COUNT(DISTINCT CASE WHEN source_key = 'staff' THEN actor_id END) AS active_staff
            FROM (
                SELECT
                    'staff' AS source_key,
                    p.payment_amount AS amount,
                    p.client_id AS client_id,
                    p.received_by AS actor_id
                FROM payments p
                WHERE p.tenant_id = ?
                  AND p.payment_date >= ?
                  AND p.payment_date < ?
                  AND p.payment_status IN ('Posted', 'Verified')

                UNION ALL

                SELECT
                    'client' AS source_key,
                    t.amount AS amount,
                    t.client_id AS client_id,
                    NULL AS actor_id
                FROM payment_transactions t
                WHERE t.tenant_id = ?
                  AND COALESCE(t.payment_date, t.created_at) >= ?
                  AND COALESCE(t.payment_date, t.created_at) < ?
                  AND LOWER(COALESCE(t.status, '')) = 'completed'
            ) report_transactions
        ");
        $summaryStmt->execute([$tenant_id, $date_from, $date_to_exclusive, $tenant_id, $date_from, $date_to_exclusive]);
        $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $data['summary'] = [
            'total_transactions' => (int) ($summary['total_transactions'] ?? 0),
            'total_amount' => (float) ($summary['total_amount'] ?? 0),
            'staff_transactions' => (int) ($summary['staff_transactions'] ?? 0),
            'staff_amount' => (float) ($summary['staff_amount'] ?? 0),
            'client_transactions' => (int) ($summary['client_transactions'] ?? 0),
            'client_amount' => (float) ($summary['client_amount'] ?? 0),
            'unique_clients' => (int) ($summary['unique_clients'] ?? 0),
            'active_staff' => (int) ($summary['active_staff'] ?? 0),
        ];

        $data['source_breakdown'] = [
            [
                'source_key' => 'staff',
                'source_label' => 'Staff Posted',
                'transaction_count' => (int) ($summary['staff_transactions'] ?? 0),
                'total_amount' => (float) ($summary['staff_amount'] ?? 0),
            ],
            [
                'source_key' => 'client',
                'source_label' => 'Client App',
                'transaction_count' => (int) ($summary['client_transactions'] ?? 0),
                'total_amount' => (float) ($summary['client_amount'] ?? 0),
            ],
        ];

        $dailyStmt = $pdo->prepare("
            SELECT
                transaction_day,
                COUNT(*) AS total_transactions,
                COALESCE(SUM(amount), 0) AS total_amount,
                SUM(CASE WHEN source_key = 'staff' THEN 1 ELSE 0 END) AS staff_transactions,
                COALESCE(SUM(CASE WHEN source_key = 'staff' THEN amount ELSE 0 END), 0) AS staff_amount,
                SUM(CASE WHEN source_key = 'client' THEN 1 ELSE 0 END) AS client_transactions,
                COALESCE(SUM(CASE WHEN source_key = 'client' THEN amount ELSE 0 END), 0) AS client_amount
            FROM (
                SELECT
                    DATE(p.payment_date) AS transaction_day,
                    p.payment_amount AS amount,
                    'staff' AS source_key
                FROM payments p
                WHERE p.tenant_id = ?
                  AND p.payment_date >= ?
                  AND p.payment_date < ?
                  AND p.payment_status IN ('Posted', 'Verified')

                UNION ALL

                SELECT
                    DATE(COALESCE(t.payment_date, t.created_at)) AS transaction_day,
                    t.amount AS amount,
                    'client' AS source_key
                FROM payment_transactions t
                WHERE t.tenant_id = ?
                  AND COALESCE(t.payment_date, t.created_at) >= ?
                  AND COALESCE(t.payment_date, t.created_at) < ?
                  AND LOWER(COALESCE(t.status, '')) = 'completed'
            ) daily_transactions
            GROUP BY transaction_day
            ORDER BY transaction_day ASC
        ");
        $dailyStmt->execute([$tenant_id, $date_from, $date_to_exclusive, $tenant_id, $date_from, $date_to_exclusive]);
        $data['daily_summary'] = $dailyStmt->fetchAll(PDO::FETCH_ASSOC);

        $methodStmt = $pdo->prepare("
            SELECT
                payment_method,
                COUNT(*) AS transaction_count,
                COALESCE(SUM(amount), 0) AS total_amount,
                SUM(CASE WHEN source_key = 'staff' THEN 1 ELSE 0 END) AS staff_transactions,
                SUM(CASE WHEN source_key = 'client' THEN 1 ELSE 0 END) AS client_transactions
            FROM (
                SELECT
                    p.payment_method AS payment_method,
                    p.payment_amount AS amount,
                    'staff' AS source_key
                FROM payments p
                WHERE p.tenant_id = ?
                  AND p.payment_date >= ?
                  AND p.payment_date < ?
                  AND p.payment_status IN ('Posted', 'Verified')

                UNION ALL

                SELECT
                    t.payment_method AS payment_method,
                    t.amount AS amount,
                    'client' AS source_key
                FROM payment_transactions t
                WHERE t.tenant_id = ?
                  AND COALESCE(t.payment_date, t.created_at) >= ?
                  AND COALESCE(t.payment_date, t.created_at) < ?
                  AND LOWER(COALESCE(t.status, '')) = 'completed'
            ) method_transactions
            GROUP BY payment_method
            ORDER BY total_amount DESC, transaction_count DESC, payment_method ASC
        ");
        $methodStmt->execute([$tenant_id, $date_from, $date_to_exclusive, $tenant_id, $date_from, $date_to_exclusive]);
        $data['method_breakdown'] = $methodStmt->fetchAll(PDO::FETCH_ASSOC);

        $staffStmt = $pdo->prepare("
            SELECT
                p.received_by AS staff_id,
                COALESCE(
                    NULLIF(TRIM(CONCAT(COALESCE(e.first_name, ''), ' ', COALESCE(e.last_name, ''))), ''),
                    NULLIF(TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))), ''),
                    CONCAT('Staff #', p.received_by)
                ) AS staff_name,
                COALESCE(NULLIF(TRIM(e.position), ''), NULLIF(TRIM(e.department), ''), 'Staff') AS staff_role,
                COUNT(*) AS transaction_count,
                COALESCE(SUM(p.payment_amount), 0) AS total_amount,
                COUNT(DISTINCT p.client_id) AS unique_clients,
                MAX(p.payment_date) AS last_transaction
            FROM payments p
            LEFT JOIN employees e ON p.received_by = e.employee_id
            LEFT JOIN users u ON e.user_id = u.user_id
            WHERE p.tenant_id = ?
              AND p.payment_date >= ?
              AND p.payment_date < ?
              AND p.payment_status IN ('Posted', 'Verified')
            GROUP BY p.received_by, staff_name, staff_role
            ORDER BY total_amount DESC, transaction_count DESC, staff_name ASC
        ");
        $staffStmt->execute([$tenant_id, $date_from, $date_to_exclusive]);
        $data['staff_summary'] = $staffStmt->fetchAll(PDO::FETCH_ASSOC);

        $clientStmt = $pdo->prepare("
            SELECT
                client_id,
                client_name,
                COUNT(*) AS transaction_count,
                COALESCE(SUM(amount), 0) AS total_amount,
                SUM(CASE WHEN source_key = 'staff' THEN 1 ELSE 0 END) AS staff_transactions,
                SUM(CASE WHEN source_key = 'client' THEN 1 ELSE 0 END) AS client_transactions,
                MAX(transaction_date) AS last_transaction
            FROM (
                SELECT
                    p.client_id AS client_id,
                    COALESCE(
                        NULLIF(TRIM(CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, ''))), ''),
                        CONCAT('Client #', p.client_id)
                    ) AS client_name,
                    p.payment_amount AS amount,
                    p.payment_date AS transaction_date,
                    'staff' AS source_key
                FROM payments p
                JOIN clients c ON p.client_id = c.client_id
                WHERE p.tenant_id = ?
                  AND p.payment_date >= ?
                  AND p.payment_date < ?
                  AND p.payment_status IN ('Posted', 'Verified')

                UNION ALL

                SELECT
                    t.client_id AS client_id,
                    COALESCE(
                        NULLIF(TRIM(CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, ''))), ''),
                        CONCAT('Client #', t.client_id)
                    ) AS client_name,
                    t.amount AS amount,
                    COALESCE(t.payment_date, t.created_at) AS transaction_date,
                    'client' AS source_key
                FROM payment_transactions t
                JOIN clients c ON t.client_id = c.client_id
                WHERE t.tenant_id = ?
                  AND COALESCE(t.payment_date, t.created_at) >= ?
                  AND COALESCE(t.payment_date, t.created_at) < ?
                  AND LOWER(COALESCE(t.status, '')) = 'completed'
            ) client_transactions
            GROUP BY client_id, client_name
            ORDER BY total_amount DESC, transaction_count DESC, client_name ASC
            LIMIT 12
        ");
        $clientStmt->execute([$tenant_id, $date_from, $date_to_exclusive, $tenant_id, $date_from, $date_to_exclusive]);
        $data['client_summary'] = $clientStmt->fetchAll(PDO::FETCH_ASSOC);

        $recentStmt = $pdo->prepare("
            SELECT *
            FROM (
                SELECT
                    p.payment_id AS record_id,
                    'staff' AS source_key,
                    'Staff Posted' AS source_label,
                    p.payment_reference AS reference_no,
                    p.payment_date AS transaction_date,
                    COALESCE(p.created_at, p.payment_date) AS sort_date,
                    p.payment_amount AS amount,
                    p.payment_method AS payment_method,
                    p.payment_status AS transaction_status,
                    COALESCE(
                        NULLIF(TRIM(CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, ''))), ''),
                        CONCAT('Client #', p.client_id)
                    ) AS client_name,
                    COALESCE(NULLIF(TRIM(COALESCE(l.loan_number, '')), ''), '—') AS loan_number,
                    COALESCE(
                        NULLIF(TRIM(CONCAT(COALESCE(e.first_name, ''), ' ', COALESCE(e.last_name, ''))), ''),
                        NULLIF(TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))), ''),
                        CONCAT('Staff #', p.received_by)
                    ) AS actor_name,
                    COALESCE(NULLIF(TRIM(e.position), ''), NULLIF(TRIM(e.department), ''), 'Staff') AS actor_role,
                    COALESCE(NULLIF(TRIM(p.official_receipt_number), ''), '—') AS receipt_number
                FROM payments p
                JOIN clients c ON p.client_id = c.client_id
                LEFT JOIN loans l ON p.loan_id = l.loan_id
                LEFT JOIN employees e ON p.received_by = e.employee_id
                LEFT JOIN users u ON e.user_id = u.user_id
                WHERE p.tenant_id = ?
                  AND p.payment_date >= ?
                  AND p.payment_date < ?
                  AND p.payment_status IN ('Posted', 'Verified')

                UNION ALL

                SELECT
                    t.transaction_id AS record_id,
                    'client' AS source_key,
                    'Client App' AS source_label,
                    t.transaction_ref AS reference_no,
                    COALESCE(t.payment_date, t.created_at) AS transaction_date,
                    COALESCE(t.payment_date, t.created_at) AS sort_date,
                    t.amount AS amount,
                    t.payment_method AS payment_method,
                    CASE LOWER(COALESCE(t.status, ''))
                        WHEN 'completed' THEN 'Completed'
                        WHEN 'pending' THEN 'Pending'
                        WHEN 'failed' THEN 'Failed'
                        WHEN 'cancelled' THEN 'Cancelled'
                        ELSE COALESCE(t.status, 'Unknown')
                    END AS transaction_status,
                    COALESCE(
                        NULLIF(TRIM(CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, ''))), ''),
                        CONCAT('Client #', t.client_id)
                    ) AS client_name,
                    COALESCE(NULLIF(TRIM(COALESCE(l.loan_number, '')), ''), '—') AS loan_number,
                    'Client Self-Service' AS actor_name,
                    'Mobile Payment' AS actor_role,
                    COALESCE(NULLIF(TRIM(t.source_id), ''), '—') AS receipt_number
                FROM payment_transactions t
                JOIN clients c ON t.client_id = c.client_id
                LEFT JOIN loans l ON t.loan_id = l.loan_id
                WHERE t.tenant_id = ?
                  AND COALESCE(t.payment_date, t.created_at) >= ?
                  AND COALESCE(t.payment_date, t.created_at) < ?
                  AND LOWER(COALESCE(t.status, '')) = 'completed'
            ) report_ledger
            ORDER BY sort_date DESC, record_id DESC
            LIMIT 25
        ");
        $recentStmt->execute([$tenant_id, $date_from, $date_to_exclusive, $tenant_id, $date_from, $date_to_exclusive]);
        $data['recent_transactions'] = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['status' => 'success', 'data' => $data]);
        exit;
    } catch (Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => 'Could not load transaction report data.']);
        exit;
    }
}

echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
