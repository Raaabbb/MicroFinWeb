<?php
require_once '../../microfin_backend/auth/session_auth.php';
mf_start_backend_session();
require_once __DIR__ . '/../../microfin_backend/config/db_connect.php';
header('Content-Type: text/plain; charset=UTF-8');
mf_require_super_admin_session($pdo, [
    'response' => 'die',
    'status' => 403,
    'message' => '403 Forbidden',
]);

if (!empty($_SESSION['super_admin_force_password_change'])) {
    http_response_code(403);
    echo 'Password change required';
    exit;
}

if (!empty($_SESSION['super_admin_onboarding_required'])) {
    http_response_code(403);
    echo 'Profile onboarding required';
    exit;
}
require_once __DIR__ . '/report_data.php';

function mf_report_pdf_abort($statusCode, $message)
{
    http_response_code((int) $statusCode);
    header('Content-Type: text/plain; charset=UTF-8');
    echo (string) $message;
    exit;
}

function mf_report_pdf_clean_text($value, $fallback = '-')
{
    $text = trim((string) $value);
    return $text !== '' ? $text : $fallback;
}

function mf_report_pdf_truncate($value, $maxLength = 30)
{
    $text = trim((string) $value);
    if ($text === '') {
        return '-';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }
        return rtrim(mb_substr($text, 0, max(1, $maxLength - 3))) . '...';
    }

    if (strlen($text) <= $maxLength) {
        return $text;
    }

    return rtrim(substr($text, 0, max(1, $maxLength - 3))) . '...';
}

function mf_report_pdf_write_wrapped(MF_SimplePdf $pdf, $pageIndex, $x, $y, $text, $size = 10, $font = 'F1', $maxChars = 92, $lineHeight = 13)
{
    $safeText = trim((string) $text);
    if ($safeText === '') {
        return $y;
    }

    $wrapped = wordwrap($safeText, $maxChars, "\n", true);
    foreach (explode("\n", $wrapped) as $line) {
        $pdf->text($pageIndex, $x, $y, $line, $size, $font);
        $y -= $lineHeight;
    }

    return $y;
}

class MF_SimplePdf
{
    private $pages = [];

    public function addPage()
    {
        $this->pages[] = '';
        return count($this->pages) - 1;
    }

    public function text($pageIndex, $x, $y, $text, $size = 12, $font = 'F1')
    {
        if (!isset($this->pages[$pageIndex])) {
            return;
        }

        $safeText = $this->escapeText($text);
        $this->pages[$pageIndex] .= 'BT /' . $font . ' ' . $this->formatNumber($size) . ' Tf '
            . $this->formatNumber($x) . ' ' . $this->formatNumber($y) . ' Td (' . $safeText . ") Tj ET\n";
    }

    public function line($pageIndex, $x1, $y1, $x2, $y2, $width = 0.6)
    {
        if (!isset($this->pages[$pageIndex])) {
            return;
        }

        $this->pages[$pageIndex] .= $this->formatNumber($width) . ' w '
            . $this->formatNumber($x1) . ' ' . $this->formatNumber($y1) . ' m '
            . $this->formatNumber($x2) . ' ' . $this->formatNumber($y2) . " l S\n";
    }

    public function outputInline($filename)
    {
        if (empty($this->pages)) {
            $this->addPage();
        }

        $objects = [];
        $pageObjectIds = [];
        $contentObjectIds = [];

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';

        $nextObjectId = 5;
        foreach ($this->pages as $pageContent) {
            $pageObjectIds[] = $nextObjectId++;
            $contentObjectIds[] = $nextObjectId++;
        }

        $kids = [];
        foreach ($pageObjectIds as $pageObjectId) {
            $kids[] = $pageObjectId . ' 0 R';
        }
        $objects[2] = '<< /Type /Pages /Count ' . count($pageObjectIds) . ' /Kids [ ' . implode(' ', $kids) . ' ] >>';

        foreach ($this->pages as $index => $pageContent) {
            $pageObjectId = $pageObjectIds[$index];
            $contentObjectId = $contentObjectIds[$index];

            $objects[$contentObjectId] = '<< /Length ' . strlen($pageContent) . " >>\nstream\n" . $pageContent . "endstream";
            $objects[$pageObjectId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $contentObjectId . ' 0 R >>';
        }

        ksort($objects);
        $maxObjectId = max(array_keys($objects));

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];

        for ($objectId = 1; $objectId <= $maxObjectId; $objectId++) {
            if (!isset($objects[$objectId])) {
                continue;
            }
            $offsets[$objectId] = strlen($pdf);
            $pdf .= $objectId . " 0 obj\n" . $objects[$objectId] . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n";
        $pdf .= '0 ' . ($maxObjectId + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($objectId = 1; $objectId <= $maxObjectId; $objectId++) {
            $offset = isset($offsets[$objectId]) ? $offsets[$objectId] : 0;
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer\n";
        $pdf .= '<< /Size ' . ($maxObjectId + 1) . ' /Root 1 0 R >>' . "\n";
        $pdf .= "startxref\n";
        $pdf .= $xrefOffset . "\n";
        $pdf .= '%%EOF';

        $safeFilename = preg_replace('/[^A-Za-z0-9._-]/', '-', (string) $filename);
        if ($safeFilename === '' || $safeFilename === null) {
            $safeFilename = 'analytics-report.pdf';
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $safeFilename . '"');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        echo $pdf;
        exit;
    }

    private function formatNumber($number)
    {
        $formatted = number_format((float) $number, 2, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');
        return $formatted === '' ? '0' : $formatted;
    }

    private function escapeText($text)
    {
        $value = (string) $text;
        $value = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $value);

        if (function_exists('iconv')) {
            $encoded = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value);
            if ($encoded !== false) {
                $value = $encoded;
            }
        }

        return strtr($value, [
            '\\' => '\\\\',
            '(' => '\\(',
            ')' => '\\)',
        ]);
    }
}

$filters = mf_sa_report_normalize_filters($_GET);
$data = mf_sa_report_fetch_data($pdo, $filters);
$summary = $data['summary'] ?? [];
$scopeLabel = trim((string) ($data['filters']['scope_label'] ?? ''));
if ($scopeLabel === '') {
    $scopeLabel = trim((string) ($filters['tenant_id'] ?? '')) !== ''
        ? 'Tenant ' . trim((string) $filters['tenant_id'])
        : 'All tenants';
}

$rangeLabel = mf_sa_report_safe_date_label($filters['date_from'], 'F j, Y')
    . ' to '
    . mf_sa_report_safe_date_label($filters['date_to'], 'F j, Y');
$generatedAtLabel = mf_sa_report_safe_date_label(date('Y-m-d H:i:s'), 'M j, Y g:i A');

$pdf = new MF_SimplePdf();
$page = null;
$y = 0;

$drawHeader = function ($title) use (&$pdf, &$page, &$y, $scopeLabel, $rangeLabel, $generatedAtLabel) {
    $page = $pdf->addPage();
    $pdf->text($page, 40, 804, $title, 18, 'F2');
    $pdf->text($page, 40, 786, 'System-generated platform analytics report', 10, 'F1');
    $pdf->line($page, 40, 778, 555, 778, 0.8);

    $pdf->text($page, 40, 756, 'Scope:', 10, 'F2');
    $pdf->text($page, 90, 756, $scopeLabel, 10, 'F1');
    $pdf->text($page, 320, 756, 'Generated:', 10, 'F2');
    $pdf->text($page, 392, 756, $generatedAtLabel, 10, 'F1');

    $pdf->text($page, 40, 738, 'Date Range:', 10, 'F2');
    $pdf->text($page, 120, 738, $rangeLabel, 10, 'F1');
    $pdf->line($page, 40, 726, 555, 726, 0.6);

    $y = 708;
};

$ensureSpace = function ($requiredHeight) use (&$y, &$drawHeader) {
    if ($y - $requiredHeight < 60) {
        $drawHeader('MicroFin Analytics Report (continued)');
    }
};

$renderTable = function ($title, array $columns, array $rows, $emptyMessage) use (&$pdf, &$page, &$y, &$ensureSpace) {
    $renderHeader = function () use (&$pdf, &$page, &$y, $title, $columns) {
        $pdf->text($page, 40, $y, $title, 12, 'F2');
        $y -= 16;
        foreach ($columns as $column) {
            $pdf->text($page, $column['x'], $y, $column['label'], 9.5, 'F2');
        }
        $pdf->line($page, 40, $y - 6, 555, $y - 6, 0.5);
        $y -= 18;
    };

    $ensureSpace(50);
    $renderHeader();

    if (empty($rows)) {
        $pdf->text($page, 40, $y, $emptyMessage, 9.5, 'F1');
        $y -= 22;
        return;
    }

    foreach ($rows as $row) {
        if ($y < 72) {
            $ensureSpace(999);
            $renderHeader();
        }

        foreach ($columns as $column) {
            $value = isset($column['value']) && is_callable($column['value'])
                ? $column['value']($row)
                : ($row[$column['key']] ?? '-');
            $pdf->text(
                $page,
                $column['x'],
                $y,
                mf_report_pdf_truncate($value, $column['max'] ?? 24),
                9.2,
                'F1'
            );
        }
        $y -= 16;
    }

    $y -= 10;
};

$drawHeader('MicroFin Analytics Report');

$pdf->text($page, 40, $y, 'Executive Snapshot', 12, 'F2');
$y -= 18;

$leftColumn = [
    ['label' => 'Total Tenants', 'value' => (string) ((int) ($summary['total_tenants'] ?? 0))],
    ['label' => 'Active Tenants', 'value' => (string) ((int) ($summary['active_tenants'] ?? 0))],
    ['label' => 'Active Super Admins', 'value' => (string) ((int) ($summary['active_super_admin_accounts'] ?? 0))],
    ['label' => 'Pending Applications', 'value' => (string) ((int) ($summary['pending_applications'] ?? 0))],
];
$rightColumn = [
    ['label' => 'Open Inquiries', 'value' => (string) ((int) ($summary['open_inquiries'] ?? 0))],
    ['label' => 'Current MRR', 'value' => mf_sa_report_currency($summary['current_mrr'] ?? 0)],
    ['label' => 'Revenue in Range', 'value' => mf_sa_report_currency($summary['range_revenue'] ?? 0)],
    ['label' => 'Transactions in Range', 'value' => (string) ((int) ($summary['range_transactions'] ?? 0))],
];

foreach ($leftColumn as $index => $row) {
    $rowY = $y - ($index * 16);
    $pdf->text($page, 40, $rowY, $row['label'] . ':', 10, 'F2');
    $pdf->text($page, 165, $rowY, $row['value'], 10, 'F1');
}
foreach ($rightColumn as $index => $row) {
    $rowY = $y - ($index * 16);
    $pdf->text($page, 300, $rowY, $row['label'] . ':', 10, 'F2');
    $pdf->text($page, 430, $rowY, $row['value'], 10, 'F1');
}
$y -= 84;

$pdf->line($page, 40, $y + 6, 555, $y + 6, 0.6);
$y -= 10;

$pdf->text($page, 40, $y, 'Analytics Summary', 12, 'F2');
$y -= 16;
$y = mf_report_pdf_write_wrapped(
    $pdf,
    $page,
    40,
    $y,
    (string) ($data['analytics_summary'] ?? 'No analytics summary available for the selected range.'),
    10,
    'F1',
    92,
    13
);
$y -= 8;

$ensureSpace(110);
$pdf->text($page, 40, $y, 'Key Insights', 12, 'F2');
$y -= 18;

$insights = $data['insights'] ?? [];
if (empty($insights)) {
    $pdf->text($page, 40, $y, 'No derived insights are available yet.', 10, 'F1');
    $y -= 18;
} else {
    foreach ($insights as $insight) {
        $ensureSpace(44);
        $headline = mf_report_pdf_clean_text($insight['title'] ?? 'Insight') . ': '
            . mf_report_pdf_clean_text($insight['value'] ?? '-');
        $pdf->text($page, 40, $y, $headline, 10, 'F2');
        $y -= 14;
        $y = mf_report_pdf_write_wrapped(
            $pdf,
            $page,
            52,
            $y,
            mf_report_pdf_clean_text($insight['detail'] ?? ''),
            9.5,
            'F1',
            84,
            12
        );
        $y -= 8;
    }
}

$renderTable(
    'Tenant Activity',
    [
        ['label' => 'Tenant', 'x' => 40, 'max' => 24, 'value' => function ($row) { return $row['tenant_name'] ?? '-'; }],
        ['label' => 'Status', 'x' => 210, 'max' => 12, 'value' => function ($row) { return $row['status'] ?? '-'; }],
        ['label' => 'Legend', 'x' => 290, 'max' => 18, 'value' => function ($row) { return $row['status_legend'] ?? '-'; }],
        ['label' => 'Plan', 'x' => 405, 'max' => 14, 'value' => function ($row) { return $row['plan_tier'] ?? 'Unassigned'; }],
        ['label' => 'Created', 'x' => 485, 'max' => 12, 'value' => function ($row) { return mf_sa_report_safe_date_label($row['created_at'] ?? '', 'M j, Y'); }],
    ],
    $data['tenant_activity'] ?? [],
    'No tenant activity matched the selected filters.'
);

$renderTable(
    'Inquiry Activity',
    [
        ['label' => 'Tenant', 'x' => 40, 'max' => 28, 'value' => function ($row) { return $row['tenant_name'] ?? '-'; }],
        ['label' => 'Status', 'x' => 250, 'max' => 12, 'value' => function ($row) { return $row['status'] ?? '-'; }],
        ['label' => 'Stage', 'x' => 340, 'max' => 12, 'value' => function ($row) { return $row['inquiry_stage'] ?? '-'; }],
        ['label' => 'Created', 'x' => 455, 'max' => 12, 'value' => function ($row) { return mf_sa_report_safe_date_label($row['created_at'] ?? '', 'M j, Y'); }],
    ],
    $data['inquiry_activity'] ?? [],
    'No inquiry activity matched the selected filters.'
);

$renderTable(
    'Plan Summary',
    [
        ['label' => 'Plan', 'x' => 40, 'max' => 18, 'value' => function ($row) {
            $plan = trim((string) ($row['plan_tier'] ?? ''));
            return $plan !== '' ? $plan : 'Unassigned';
        }],
        ['label' => 'Tenants', 'x' => 185, 'max' => 8, 'value' => function ($row) { return (string) ((int) ($row['total_tenants'] ?? 0)); }],
        ['label' => 'Active', 'x' => 255, 'max' => 8, 'value' => function ($row) { return (string) ((int) ($row['active_tenants'] ?? 0)); }],
        ['label' => 'MRR', 'x' => 335, 'max' => 18, 'value' => function ($row) { return mf_sa_report_currency($row['total_mrr'] ?? 0); }],
        ['label' => 'Users', 'x' => 485, 'max' => 10, 'value' => function ($row) { return (string) ((int) ($row['total_users'] ?? 0)); }],
    ],
    $data['plan_summary'] ?? [],
    'No plan summary is available for the current scope.'
);

$renderTable(
    'Billing Summary',
    [
        ['label' => 'Tenant', 'x' => 40, 'max' => 24, 'value' => function ($row) { return $row['tenant_name'] ?? '-'; }],
        ['label' => 'Plan', 'x' => 220, 'max' => 14, 'value' => function ($row) {
            $plan = trim((string) ($row['plan_tier'] ?? ''));
            return $plan !== '' ? $plan : 'Unassigned';
        }],
        ['label' => 'Revenue', 'x' => 320, 'max' => 16, 'value' => function ($row) { return mf_sa_report_currency($row['total_revenue'] ?? 0); }],
        ['label' => 'Txns', 'x' => 430, 'max' => 8, 'value' => function ($row) { return (string) ((int) ($row['transaction_count'] ?? 0)); }],
        ['label' => 'Latest', 'x' => 480, 'max' => 12, 'value' => function ($row) {
            return mf_sa_report_safe_date_label($row['latest_payment'] ?? '', 'M j, Y', '-');
        }],
    ],
    $data['billing_summary'] ?? [],
    'No paid billing activity matched the selected filters.'
);

$scopeToken = trim((string) ($filters['tenant_id'] ?? '')) !== ''
    ? preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $filters['tenant_id'])
    : 'all-tenants';
$fileName = 'analytics-report-' . $scopeToken . '-' . $filters['date_from'] . '-to-' . $filters['date_to'] . '.pdf';

$pdf->outputInline($fileName);

