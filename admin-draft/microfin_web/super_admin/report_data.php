<?php

function mf_sa_report_safe_date_label($value, $format = 'M j, Y', $fallback = '-')
{
    $text = trim((string) $value);
    if ($text === '' || $text === '0000-00-00' || $text === '0000-00-00 00:00:00') {
        return $fallback;
    }

    $timestamp = strtotime($text);
    if ($timestamp === false) {
        return $fallback;
    }

    return date($format, $timestamp);
}

function mf_sa_report_currency($amount): string
{
    return 'PHP ' . number_format((float) $amount, 2);
}

function mf_sa_report_format_percentage($value, $precision = 1): string
{
    return number_format((float) $value, $precision) . '%';
}

function mf_sa_report_normalize_filters(array $source): array
{
    $dateFrom = trim((string) ($source['date_from'] ?? date('Y-01-01')));
    $dateTo = trim((string) ($source['date_to'] ?? date('Y-m-d')));
    $tenantId = trim((string) ($source['tenant_id'] ?? ''));

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) !== 1) {
        $dateFrom = date('Y-01-01');
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) !== 1) {
        $dateTo = date('Y-m-d');
    }
    if ($dateFrom > $dateTo) {
        $tmp = $dateFrom;
        $dateFrom = $dateTo;
        $dateTo = $tmp;
    }

    return [
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'tenant_id' => $tenantId,
    ];
}

function mf_sa_report_build_insight_bundle(array $filters, array $summary, array $planSummary, array $billingSummary): array
{
    $totalTenants = (int) ($summary['total_tenants'] ?? 0);
    $activeTenants = (int) ($summary['active_tenants'] ?? 0);
    $rangeTransactions = (int) ($summary['range_transactions'] ?? 0);
    $rangeRevenue = (float) ($summary['range_revenue'] ?? 0);
    $currentMrr = (float) ($summary['current_mrr'] ?? 0);

    $activationRate = $totalTenants > 0 ? ($activeTenants / $totalTenants) * 100 : 0;
    $averageRevenuePerTransaction = $rangeTransactions > 0 ? ($rangeRevenue / $rangeTransactions) : 0;

    $totalUsers = 0;
    $topPlan = null;
    foreach ($planSummary as $row) {
        $totalUsers += (int) ($row['total_users'] ?? 0);
        if ($topPlan === null) {
            $topPlan = $row;
            continue;
        }

        $rowTenantCount = (int) ($row['total_tenants'] ?? 0);
        $topPlanTenantCount = (int) ($topPlan['total_tenants'] ?? 0);
        $rowActiveCount = (int) ($row['active_tenants'] ?? 0);
        $topPlanActiveCount = (int) ($topPlan['active_tenants'] ?? 0);
        if (
            $rowTenantCount > $topPlanTenantCount
            || ($rowTenantCount === $topPlanTenantCount && $rowActiveCount > $topPlanActiveCount)
        ) {
            $topPlan = $row;
        }
    }

    $averageUsersPerTenant = $totalTenants > 0 ? ($totalUsers / $totalTenants) : 0;

    $topBillingTenant = null;
    foreach ($billingSummary as $row) {
        if ($topBillingTenant === null) {
            $topBillingTenant = $row;
            continue;
        }

        $rowRevenue = (float) ($row['total_revenue'] ?? 0);
        $topRevenue = (float) ($topBillingTenant['total_revenue'] ?? 0);
        $rowTransactions = (int) ($row['transaction_count'] ?? 0);
        $topTransactions = (int) ($topBillingTenant['transaction_count'] ?? 0);
        if (
            $rowRevenue > $topRevenue
            || ($rowRevenue === $topRevenue && $rowTransactions > $topTransactions)
        ) {
            $topBillingTenant = $row;
        }
    }

    $insights = [
        [
            'title' => 'Tenant Activation Rate',
            'value' => mf_sa_report_format_percentage($activationRate, 1),
            'detail' => $totalTenants > 0
                ? $activeTenants . ' of ' . $totalTenants . ' onboarded tenants are active right now.'
                : 'No onboarded tenants matched the current report scope.',
        ],
        [
            'title' => 'Avg Revenue per Transaction',
            'value' => mf_sa_report_currency($averageRevenuePerTransaction),
            'detail' => $rangeTransactions > 0
                ? 'Based on ' . $rangeTransactions . ' paid transaction(s) within the selected range.'
                : 'No paid transactions were found inside the selected range.',
        ],
        [
            'title' => 'Avg Users per Tenant',
            'value' => number_format($averageUsersPerTenant, 1) . ' users',
            'detail' => $totalTenants > 0
                ? number_format($totalUsers) . ' total user account(s) spread across the onboarded tenant base.'
                : 'User footprint will appear here once tenant accounts are onboarded.',
        ],
    ];

    if ($topPlan !== null && (int) ($topPlan['total_tenants'] ?? 0) > 0) {
        $planLabel = trim((string) ($topPlan['plan_tier'] ?? ''));
        if ($planLabel === '') {
            $planLabel = 'Unassigned';
        }
        $insights[] = [
            'title' => 'Most Adopted Plan',
            'value' => $planLabel,
            'detail' => (int) ($topPlan['total_tenants'] ?? 0) . ' tenant(s), '
                . (int) ($topPlan['active_tenants'] ?? 0) . ' active, '
                . number_format((int) ($topPlan['total_users'] ?? 0)) . ' user account(s).',
        ];
    } else {
        $insights[] = [
            'title' => 'Most Adopted Plan',
            'value' => 'No plan data',
            'detail' => 'Plan adoption insights will appear after tenant subscriptions are provisioned.',
        ];
    }

    if ($topBillingTenant !== null && (float) ($topBillingTenant['total_revenue'] ?? 0) > 0) {
        $topBillingTenantName = trim((string) ($topBillingTenant['tenant_name'] ?? ''));
        if ($topBillingTenantName === '') {
            $topBillingTenantName = 'Unknown tenant';
        }
        $insights[] = [
            'title' => 'Top Revenue Tenant',
            'value' => $topBillingTenantName,
            'detail' => mf_sa_report_currency($topBillingTenant['total_revenue'] ?? 0)
                . ' from ' . (int) ($topBillingTenant['transaction_count'] ?? 0) . ' paid transaction(s).',
        ];
    } else {
        $insights[] = [
            'title' => 'Top Revenue Tenant',
            'value' => 'No paid activity',
            'detail' => 'A revenue leader will appear here once paid transactions exist in the selected range.',
        ];
    }

    $scopeLabel = trim((string) ($filters['scope_label'] ?? ''));
    if ($scopeLabel === '') {
        $scopeLabel = 'all tenants';
    }

    $summaryParts = [
        'This report covers ' . mf_sa_report_safe_date_label($filters['date_from'], 'M j, Y')
            . ' to ' . mf_sa_report_safe_date_label($filters['date_to'], 'M j, Y')
            . ' for ' . $scopeLabel . '.',
    ];

    if ($totalTenants > 0) {
        $summaryParts[] = mf_sa_report_format_percentage($activationRate, 1) . ' of onboarded tenants are currently active.';
    } else {
        $summaryParts[] = 'No onboarded tenants matched the current report scope.';
    }

    $summaryParts[] = 'Current MRR is ' . mf_sa_report_currency($currentMrr)
        . ', while billed revenue in range is ' . mf_sa_report_currency($rangeRevenue) . '.';

    $summaryParts[] = (int) ($summary['pending_applications'] ?? 0) . ' pending application(s) and '
        . (int) ($summary['open_inquiries'] ?? 0) . ' open inquiry request(s) are included in this report scope.';

    if ($topPlan !== null && (int) ($topPlan['total_tenants'] ?? 0) > 0) {
        $planLabel = trim((string) ($topPlan['plan_tier'] ?? ''));
        if ($planLabel === '') {
            $planLabel = 'Unassigned';
        }
        $summaryParts[] = $planLabel . ' is the most adopted plan in the current snapshot.';
    }

    if ($topBillingTenant !== null && (float) ($topBillingTenant['total_revenue'] ?? 0) > 0) {
        $topBillingTenantName = trim((string) ($topBillingTenant['tenant_name'] ?? ''));
        if ($topBillingTenantName === '') {
            $topBillingTenantName = 'The leading tenant';
        }
        $summaryParts[] = $topBillingTenantName . ' generated the highest billed revenue in the selected range.';
    }

    return [
        'insights' => $insights,
        'summary_text' => implode(' ', $summaryParts),
    ];
}

function mf_sa_report_fetch_data(PDO $pdo, array $filters): array
{
    $dateFrom = $filters['date_from'];
    $dateTo = $filters['date_to'];
    $tenantId = $filters['tenant_id'];

    $selectedTenantName = '';
    if ($tenantId !== '') {
        $tenantLookupStmt = $pdo->prepare('SELECT tenant_name FROM tenants WHERE tenant_id = ? LIMIT 1');
        $tenantLookupStmt->execute([$tenantId]);
        $selectedTenantName = (string) ($tenantLookupStmt->fetchColumn() ?: '');
    }

    $scopeLabel = $selectedTenantName !== ''
        ? $selectedTenantName
        : ($tenantId !== '' ? 'Tenant ' . $tenantId : 'All tenants');

    $data = [
        'filters' => [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'tenant_id' => $tenantId,
            'tenant_name' => $selectedTenantName,
            'scope_label' => $scopeLabel,
        ],
    ];

    $summarySql = "
        SELECT
            SUM(CASE WHEN (t.request_type = 'tenant_application' OR t.request_type IS NULL) THEN 1 ELSE 0 END) AS total_tenants,
            SUM(CASE WHEN (t.request_type = 'tenant_application' OR t.request_type IS NULL) AND t.status = 'Active' THEN 1 ELSE 0 END) AS active_tenants,
            SUM(CASE WHEN (t.request_type = 'tenant_application' OR t.request_type IS NULL) AND t.status IN ('Draft', 'CONSIDER', 'Pending', 'Contacted', 'Accepted', 'New', 'In Contact') THEN 1 ELSE 0 END) AS pending_applications,
            SUM(CASE WHEN t.request_type = 'talk_to_expert' AND t.status IN ('Pending', 'Contacted', 'New', 'In Contact') THEN 1 ELSE 0 END) AS open_inquiries,
            COALESCE(SUM(CASE WHEN (t.request_type = 'tenant_application' OR t.request_type IS NULL) AND t.status = 'Active' THEN t.mrr ELSE 0 END), 0) AS current_mrr
        FROM tenants t
        WHERE t.deleted_at IS NULL
    ";
    $summaryParams = [];
    if ($tenantId !== '') {
        $summarySql .= " AND t.tenant_id = ?";
        $summaryParams[] = $tenantId;
    }
    $summaryStmt = $pdo->prepare($summarySql);
    $summaryStmt->execute($summaryParams);
    $summaryRow = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $saStmt = $pdo->query("SELECT COUNT(*) AS cnt FROM users WHERE user_type = 'Super Admin' AND status = 'Active' AND deleted_at IS NULL");
    $activeSuperAdminAccounts = (int) ($saStmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);

    $rangeRevenueSql = "
        SELECT COALESCE(SUM(amount), 0) AS total_revenue, COUNT(*) AS total_transactions
        FROM tenant_billing_invoices
        WHERE status = 'Paid'
          AND DATE(created_at) BETWEEN ? AND ?
    ";
    $rangeRevenueParams = [$dateFrom, $dateTo];
    if ($tenantId !== '') {
        $rangeRevenueSql .= " AND tenant_id = ?";
        $rangeRevenueParams[] = $tenantId;
    }
    $rangeRevenueStmt = $pdo->prepare($rangeRevenueSql);
    $rangeRevenueStmt->execute($rangeRevenueParams);
    $rangeRevenueRow = $rangeRevenueStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $data['summary'] = [
        'total_tenants' => (int) ($summaryRow['total_tenants'] ?? 0),
        'active_tenants' => (int) ($summaryRow['active_tenants'] ?? 0),
        'active_super_admin_accounts' => $activeSuperAdminAccounts,
        'pending_applications' => (int) ($summaryRow['pending_applications'] ?? 0),
        'open_inquiries' => (int) ($summaryRow['open_inquiries'] ?? 0),
        'current_mrr' => (float) ($summaryRow['current_mrr'] ?? 0),
        'range_revenue' => (float) ($rangeRevenueRow['total_revenue'] ?? 0),
        'range_transactions' => (int) ($rangeRevenueRow['total_transactions'] ?? 0),
    ];

    $tenantActivitySql = "
        SELECT t.tenant_id, t.tenant_name, t.status, t.plan_tier, t.created_at,
            CASE
                WHEN t.status = 'Active' THEN 'Active'
                WHEN t.status IN ('Draft', 'CONSIDER', 'Pending', 'Contacted', 'Accepted', 'New', 'In Contact') THEN 'Pending Application'
                ELSE 'Inactive'
            END AS status_legend
        FROM tenants t
        WHERE t.deleted_at IS NULL
          AND (t.request_type = 'tenant_application' OR t.request_type IS NULL)
          AND DATE(t.created_at) BETWEEN ? AND ?
    ";
    $tenantActivityParams = [$dateFrom, $dateTo];
    if ($tenantId !== '') {
        $tenantActivitySql .= " AND t.tenant_id = ?";
        $tenantActivityParams[] = $tenantId;
    }
    $tenantActivitySql .= " ORDER BY t.created_at DESC";
    $stmt = $pdo->prepare($tenantActivitySql);
    $stmt->execute($tenantActivityParams);
    $data['tenant_activity'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $inquirySql = "
        SELECT t.tenant_id, t.tenant_name, t.status, t.created_at,
            CASE
                WHEN t.status IN ('Pending', 'Contacted', 'New', 'In Contact') THEN 'Open'
                WHEN t.status IN ('Closed', 'Resolved') THEN 'Closed'
                ELSE 'Inactive'
            END AS inquiry_stage
        FROM tenants t
        WHERE t.deleted_at IS NULL
          AND t.request_type = 'talk_to_expert'
          AND DATE(t.created_at) BETWEEN ? AND ?
    ";
    $inquiryParams = [$dateFrom, $dateTo];
    if ($tenantId !== '') {
        $inquirySql .= " AND t.tenant_id = ?";
        $inquiryParams[] = $tenantId;
    }
    $inquirySql .= " ORDER BY t.created_at DESC";
    $stmt = $pdo->prepare($inquirySql);
    $stmt->execute($inquiryParams);
    $data['inquiry_activity'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $planSummarySql = "
        SELECT
            t.plan_tier,
            COUNT(*) AS total_tenants,
            SUM(CASE WHEN t.status = 'Active' THEN 1 ELSE 0 END) AS active_tenants,
            COALESCE(SUM(CASE WHEN t.status = 'Active' THEN t.mrr ELSE 0 END), 0) AS total_mrr,
            COALESCE(SUM(COALESCE(u_stats.total_users, 0)), 0) AS total_users
        FROM tenants t
        LEFT JOIN (
            SELECT tenant_id, COUNT(*) AS total_users
            FROM users
            WHERE tenant_id IS NOT NULL AND deleted_at IS NULL
            GROUP BY tenant_id
        ) u_stats ON u_stats.tenant_id = t.tenant_id
        WHERE t.deleted_at IS NULL
          AND (t.request_type = 'tenant_application' OR t.request_type IS NULL)
    ";
    $planSummaryParams = [];
    if ($tenantId !== '') {
        $planSummarySql .= " AND t.tenant_id = ?";
        $planSummaryParams[] = $tenantId;
    }
    $planSummarySql .= "
        GROUP BY t.plan_tier
        ORDER BY active_tenants DESC, total_tenants DESC, t.plan_tier ASC
    ";
    $stmt = $pdo->prepare($planSummarySql);
    $stmt->execute($planSummaryParams);
    $data['plan_summary'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $billingSummarySql = "
        SELECT
            t.tenant_id,
            t.tenant_name,
            t.plan_tier,
            COALESCE(SUM(p.amount), 0) AS total_revenue,
            COUNT(p.invoice_id) AS transaction_count,
            MAX(p.created_at) AS latest_payment
        FROM tenants t
        LEFT JOIN tenant_billing_invoices p
          ON p.tenant_id = t.tenant_id
         AND p.status = 'Paid'
         AND DATE(p.created_at) BETWEEN ? AND ?
        WHERE t.deleted_at IS NULL
          AND (t.request_type = 'tenant_application' OR t.request_type IS NULL)
    ";
    $billingSummaryParams = [$dateFrom, $dateTo];
    if ($tenantId !== '') {
        $billingSummarySql .= " AND t.tenant_id = ?";
        $billingSummaryParams[] = $tenantId;
    }
    $billingSummarySql .= "
        GROUP BY t.tenant_id, t.tenant_name, t.plan_tier
    ";
    if ($tenantId === '') {
        $billingSummarySql .= "
        HAVING transaction_count > 0 OR total_revenue > 0
        ";
    }
    $billingSummarySql .= "
        ORDER BY total_revenue DESC, transaction_count DESC, t.tenant_name ASC
        LIMIT 10
    ";
    $stmt = $pdo->prepare($billingSummarySql);
    $stmt->execute($billingSummaryParams);
    $data['billing_summary'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $insightBundle = mf_sa_report_build_insight_bundle(
        $data['filters'],
        $data['summary'],
        $data['plan_summary'],
        $data['billing_summary']
    );
    $data['insights'] = $insightBundle['insights'];
    $data['analytics_summary'] = $insightBundle['summary_text'];

    return $data;
}
