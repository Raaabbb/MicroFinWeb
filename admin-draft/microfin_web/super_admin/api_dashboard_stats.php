<?php
require_once '../../microfin_backend/auth/session_auth.php';
mf_start_backend_session();
require_once '../../microfin_backend/config/db_connect.php';
mf_require_super_admin_session($pdo, [
    'response' => 'json',
    'status' => 401,
    'message' => 'Unauthorized',
]);

if (!empty($_SESSION['super_admin_force_password_change'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Password change required']);
    exit;
}

if (!empty($_SESSION['super_admin_onboarding_required'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Profile onboarding required']);
    exit;
}
require_once __DIR__ . '/report_data.php';

header('Content-Type: application/json');

function mf_sa_sales_period_labels(string $period, string $dateFrom, string $dateTo): array
{
    $labels = [];

    if ($period === 'yearly') {
        $cursor = new DateTimeImmutable(date('Y-01-01', strtotime($dateFrom)));
        $end = new DateTimeImmutable(date('Y-01-01', strtotime($dateTo)));
        while ($cursor <= $end) {
            $labels[] = $cursor->format('Y');
            $cursor = $cursor->modify('+1 year');
        }
        return $labels;
    }

    $cursor = new DateTimeImmutable(date('Y-m-01', strtotime($dateFrom)));
    $end = new DateTimeImmutable(date('Y-m-01', strtotime($dateTo)));
    while ($cursor <= $end) {
        $labels[] = $cursor->format('Y-m');
        $cursor = $cursor->modify('+1 month');
    }

    return $labels;
}

function mf_sa_fill_sales_periods(array $labels, array $rows): array
{
    $mapped = [];
    foreach ($rows as $row) {
        $mapped[(string)($row['period_label'] ?? '')] = (float)($row['total'] ?? 0);
    }

    $filled = [];
    foreach ($labels as $label) {
        $filled[] = [
            'period_label' => $label,
            'total' => $mapped[$label] ?? 0,
        ];
    }

    return $filled;
}

$action = $_GET['action'] ?? 'dashboard';

try {
    switch ($action) {

        // ============================================================
        // DEFAULT: Dashboard stats + chart data (polled every 5s)
        // ============================================================
        case 'dashboard':
            $data = [];
            $date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
            $date_to = $_GET['date_to'] ?? date('Y-m-d');

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$date_from)) {
                $date_from = date('Y-m-d', strtotime('-7 days'));
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$date_to)) {
                $date_to = date('Y-m-d');
            }
            if ($date_from > $date_to) {
                $tmp = $date_from;
                $date_from = $date_to;
                $date_to = $tmp;
            }

            // Stat cards
            $stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM tenants WHERE status = 'Active' AND deleted_at IS NULL");
            $data['active_tenants'] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

            $stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM users WHERE status = 'Active' AND deleted_at IS NULL");
            $data['active_users'] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

            $stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM users WHERE user_type = 'Super Admin' AND status = 'Active' AND deleted_at IS NULL");
            $data['active_super_admin_accounts'] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

            $stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM users WHERE status = 'Inactive' AND deleted_at IS NULL");
            $data['inactive_users'] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

            $stmt = $pdo->query("SELECT COALESCE(SUM(mrr), 0) AS total_mrr FROM tenants WHERE status = 'Active' AND deleted_at IS NULL");
            $data['total_mrr'] = number_format((float) $stmt->fetch(PDO::FETCH_ASSOC)['total_mrr'], 2);

            $stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM tenants WHERE request_type = 'tenant_application' AND status IN ('Pending', 'Contacted') AND deleted_at IS NULL");
            $data['pending_applications'] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

            $stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM tenants WHERE request_type = 'talk_to_expert' AND status IN ('Pending', 'Contacted') AND deleted_at IS NULL");
            $data['pending_inquiries'] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

            // Chart: User growth by tenant (daily line series)
            $labels = [];
            $cursor = strtotime($date_from);
            $end = strtotime($date_to);
            while ($cursor <= $end) {
                $labels[] = date('Y-m-d', $cursor);
                $cursor = strtotime('+1 day', $cursor);
            }

            $labelIndexMap = [];
            foreach ($labels as $idx => $label) {
                $labelIndexMap[$label] = $idx;
            }

            $stmt = $pdo->prepare("
                SELECT
                    t.tenant_id,
                    t.tenant_name,
                    DATE(u.created_at) AS day_label,
                    COUNT(u.user_id) AS total_users
                FROM users u
                INNER JOIN tenants t ON t.tenant_id = u.tenant_id
                WHERE u.deleted_at IS NULL
                  AND u.user_type IN ('Client', 'Employee', 'Admin')
                  AND DATE(u.created_at) BETWEEN ? AND ?
                  AND t.deleted_at IS NULL
                  AND t.status = 'Active'
                  AND (t.request_type = 'tenant_application' OR t.request_type IS NULL)
                GROUP BY t.tenant_id, t.tenant_name, DATE(u.created_at)
                ORDER BY t.tenant_name ASC, day_label ASC
            ");
            $stmt->execute([$date_from, $date_to]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $series = [];
            foreach ($rows as $row) {
                $tenantKey = (string)$row['tenant_id'];
                if (!isset($series[$tenantKey])) {
                    $series[$tenantKey] = [
                        'tenant_name' => (string)$row['tenant_name'],
                        'points' => array_fill(0, count($labels), 0),
                    ];
                }

                $dayLabel = (string)$row['day_label'];
                if (isset($labelIndexMap[$dayLabel])) {
                    $series[$tenantKey]['points'][(int)$labelIndexMap[$dayLabel]] = (int)$row['total_users'];
                }
            }

            $data['user_growth_chart'] = [
                'labels' => $labels,
                'series' => array_values($series),
            ];
            $data['user_growth_date_from'] = $date_from;
            $data['user_growth_date_to'] = $date_to;

            // Chart: Tenant activity by status (precise monthly breakdown)
            $stmt = $pdo->query("
                SELECT DATE_FORMAT(created_at, '%Y-%m') AS month,
                    SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) AS active_count,
                    SUM(CASE WHEN status IN ('Draft', 'CONSIDER', 'Pending', 'Contacted', 'Accepted', 'Rejected') THEN 1 ELSE 0 END) AS pending_count,
                    SUM(CASE WHEN status NOT IN ('Active', 'Draft', 'CONSIDER', 'Pending', 'Contacted', 'Accepted', 'Rejected') THEN 1 ELSE 0 END) AS inactive_count
                FROM tenants
                WHERE deleted_at IS NULL
                  AND (request_type = 'tenant_application' OR request_type IS NULL)
                GROUP BY month ORDER BY month ASC
                LIMIT 12
            ");
            $data['tenant_activity_chart'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Chart: Sales trends (payment totals by month)
            $stmt = $pdo->query("
                SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COALESCE(SUM(amount), 0) AS total
                FROM tenant_billing_invoices WHERE status = 'Paid'
                GROUP BY month ORDER BY month ASC
                LIMIT 12
            ");
            $data['sales_trends_chart'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode($data);
            break;

        // ============================================================
        // REPORTS: Privacy-safe tenant activity summary
        // ============================================================
        case 'reports':
            $filters = mf_sa_report_normalize_filters($_GET);
            echo json_encode(mf_sa_report_fetch_data($pdo, $filters));
            break;

        // ============================================================
        // SALES: Revenue, top tenants, transactions
        // ============================================================
        case 'sales':
            $period = $_GET['period'] ?? 'monthly';
            
            // Auto-calculate date range based on period since filter UI was removed
            if ($period === 'yearly') {
                $date_from = date('Y-01-01', strtotime('-4 years')); // Last 5 years including current
                $date_to = date('Y-12-31');
            } else {
                // monthly
                $date_from = date('Y-m-01', strtotime('-11 months')); // Last 12 months including current
                $date_to = date('Y-m-t');
            }
            
            $data = [];

            // Total revenue
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(amount), 0) AS total_revenue,
                       COUNT(*) AS total_transactions
                FROM tenant_billing_invoices
                WHERE status = 'Paid'
                AND DATE(created_at) BETWEEN ? AND ?
            ");
            $stmt->execute([$date_from, $date_to]);
            $rev = $stmt->fetch(PDO::FETCH_ASSOC);
            $data['total_revenue'] = number_format((float) $rev['total_revenue'], 2);
            $data['total_transactions'] = (int) $rev['total_transactions'];
            $data['avg_transaction'] = $rev['total_transactions'] > 0
                ? number_format((float) $rev['total_revenue'] / $rev['total_transactions'], 2)
                : '0.00';

            // Top performing tenants
            $stmt = $pdo->prepare("
                SELECT t.tenant_name, t.plan_tier,
                    COALESCE(SUM(p.amount), 0) AS total_sales,
                    COUNT(p.invoice_id) AS transaction_count
                FROM tenants t
                LEFT JOIN tenant_billing_invoices p ON p.tenant_id = t.tenant_id
                    AND p.status = 'Paid'
                    AND DATE(p.created_at) BETWEEN ? AND ?
                WHERE t.status = 'Active' AND t.deleted_at IS NULL
                GROUP BY t.tenant_id
                ORDER BY total_sales DESC
                LIMIT 5
            ");
            $stmt->execute([$date_from, $date_to]);
            $data['top_tenants'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Revenue chart by period
            $date_format = '%Y-%m'; // monthly
            if ($period === 'yearly') {
                $date_format = '%Y';
            }

            $stmt = $pdo->prepare("
                SELECT DATE_FORMAT(created_at, ?) AS period_label,
                       COALESCE(SUM(amount), 0) AS total
                FROM tenant_billing_invoices
                WHERE status = 'Paid'
                AND DATE(created_at) BETWEEN ? AND ?
                GROUP BY period_label
                ORDER BY period_label ASC
            ");
            $stmt->execute([$date_format, $date_from, $date_to]);
            $revenueRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $periodLabels = mf_sa_sales_period_labels($period, $date_from, $date_to);
            $data['revenue_chart'] = mf_sa_fill_sales_periods($periodLabels, $revenueRows);

            // Recent transactions
            $stmt = $pdo->prepare("
                SELECT p.invoice_number AS payment_reference, t.tenant_name, p.amount AS payment_amount,
                       'System Auto-Billing' AS payment_method, p.status AS payment_status, p.created_at AS payment_date
                FROM tenant_billing_invoices p
                LEFT JOIN tenants t ON p.tenant_id = t.tenant_id
                WHERE DATE(p.created_at) BETWEEN ? AND ?
                ORDER BY p.created_at DESC
                LIMIT 50
            ");
            $stmt->execute([$date_from, $date_to]);
            $data['transactions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode($data);
            break;

        // ============================================================
        // AUDIT LOGS: Filtered audit trail
        // ============================================================
        case 'audit_logs':
            $action_type = $_GET['action_type'] ?? '';
            $tenant_id = $_GET['tenant_id'] ?? '';
            $date_from = $_GET['date_from'] ?? '';
            $date_to = $_GET['date_to'] ?? '';

            $sql = "
                SELECT al.log_id, al.action_type, al.entity_type, al.entity_id,
                       al.description, al.ip_address, al.created_at,
                       u.username, u.email AS user_email,
                       t.tenant_name
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.user_id
                LEFT JOIN tenants t ON al.tenant_id = t.tenant_id
                  WHERE u.user_type = 'Super Admin'
            ";
            $params = [];

            if ($action_type !== '') {
                $sql .= " AND al.action_type = ?";
                $params[] = $action_type;
            }
            if ($tenant_id !== '') {
                $sql .= " AND al.tenant_id = ?";
                $params[] = $tenant_id;
            }
            if ($date_from !== '') {
                $sql .= " AND al.created_at >= ?";
                $params[] = $date_from;
            }
            if ($date_to !== '') {
                $sql .= " AND al.created_at <= DATE_ADD(?, INTERVAL 1 DAY)";
                $params[] = $date_to;
            }

            $sql .= " ORDER BY al.log_id DESC LIMIT 200";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            echo json_encode(['logs' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

