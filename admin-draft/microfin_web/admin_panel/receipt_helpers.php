<?php

function admin_receipt_period_options(): array
{
    return [
        'all' => 'All receipts',
        'month' => 'By month',
        'year' => 'By year',
    ];
}

function admin_receipt_month_options(): array
{
    return [
        '01' => 'January',
        '02' => 'February',
        '03' => 'March',
        '04' => 'April',
        '05' => 'May',
        '06' => 'June',
        '07' => 'July',
        '08' => 'August',
        '09' => 'September',
        '10' => 'October',
        '11' => 'November',
        '12' => 'December',
    ];
}

function admin_receipt_collect_filters(array $source): array
{
    $periodOptions = admin_receipt_period_options();
    $monthOptions = admin_receipt_month_options();

    $period = trim((string)($source['receipt_period'] ?? 'all'));
    $month = trim((string)($source['receipt_month'] ?? ''));
    $year = trim((string)($source['receipt_year'] ?? ''));

    if (!array_key_exists($period, $periodOptions)) {
        $period = 'all';
    }
    if (!array_key_exists($month, $monthOptions)) {
        $month = '';
    }
    if (!preg_match('/^\d{4}$/', $year)) {
        $year = '';
    }

    if ($period === 'month' && $month === '') {
        $period = 'all';
    }
    if ($period === 'year' && $year === '') {
        $period = 'all';
    }

    return [
        'receipt_period' => $period,
        'receipt_month' => $month,
        'receipt_year' => $year,
    ];
}

function admin_receipt_build_query_parts(string $tenantId, array $filters): array
{
    $conditions = ['i.tenant_id = ?'];
    $params = [$tenantId];
    $dateExpression = 'COALESCE(i.paid_at, i.created_at)';

    $conditions[] = 'i.status = ?';
    $params[] = 'Paid';

    if (($filters['receipt_period'] ?? 'all') === 'month' && !empty($filters['receipt_month'])) {
        $conditions[] = "MONTH({$dateExpression}) = ?";
        $params[] = (int)$filters['receipt_month'];
    }
    if (($filters['receipt_period'] ?? 'all') === 'year' && !empty($filters['receipt_year'])) {
        $conditions[] = "YEAR({$dateExpression}) = ?";
        $params[] = $filters['receipt_year'];
    }

    return [implode(' AND ', $conditions), $params];
}

function admin_receipt_has_filters(array $filters): bool
{
    if (($filters['receipt_period'] ?? 'all') !== 'all') {
        return true;
    }

    return false;
}

function admin_receipt_build_query_string(array $filters, array $extra = []): string
{
    $query = [];

    foreach (array_merge($filters, $extra) as $key => $value) {
        if ($value === '' || $value === null) {
            continue;
        }
        $query[$key] = $value;
    }

    return http_build_query($query);
}
