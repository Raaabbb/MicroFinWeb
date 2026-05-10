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

function mf_statement_pdf_abort($statusCode, $message)
{
    http_response_code((int) $statusCode);
    header('Content-Type: text/plain; charset=UTF-8');
    echo (string) $message;
    exit;
}

function mf_statement_pdf_clean_text($value, $fallback = '-')
{
    $text = trim((string) $value);
    return $text !== '' ? $text : $fallback;
}

function mf_statement_pdf_safe_date($value, $format = 'M j, Y', $fallback = '-')
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

function mf_statement_pdf_currency($amount)
{
    return 'PHP ' . number_format((float) $amount, 2);
}

function mf_statement_pdf_period_label($period, $year, $monthNumber)
{
    if ($period === 'yearly') {
        return (string) $year;
    }

    $timestamp = strtotime(sprintf('%04d-%02d-01', $year, $monthNumber));
    if ($timestamp === false) {
        return (string) $year;
    }

    return date('F Y', $timestamp);
}

function mf_statement_pdf_date_range_label($startDate, $endDate)
{
    return mf_statement_pdf_safe_date($startDate, 'F j, Y') . ' to ' . mf_statement_pdf_safe_date($endDate, 'F j, Y');
}

function mf_statement_pdf_invoice_period_label(array $invoice)
{
    $startText = trim((string) ($invoice['billing_period_start'] ?? ''));
    $endText = trim((string) ($invoice['billing_period_end'] ?? ''));
    $startTs = strtotime($startText);
    $endTs = strtotime($endText);

    if ($startTs === false && $endTs === false) {
        return '-';
    }

    if ($startTs !== false && $endTs !== false && date('Y-m', $startTs) === date('Y-m', $endTs)) {
        return date('M Y', $startTs);
    }

    if ($startTs !== false && $endTs !== false) {
        return date('M Y', $startTs) . ' - ' . date('M Y', $endTs);
    }

    return $startTs !== false ? date('M Y', $startTs) : date('M Y', $endTs);
}

function mf_statement_pdf_status_summary(array $invoices)
{
    if (empty($invoices)) {
        return 'No invoices';
    }

    $counts = [];
    foreach ($invoices as $invoice) {
        $status = mf_statement_pdf_clean_text($invoice['status'] ?? 'Unknown', 'Unknown');
        if (!isset($counts[$status])) {
            $counts[$status] = 0;
        }
        $counts[$status]++;
    }

    $parts = [];
    foreach ($counts as $status => $count) {
        $parts[] = $status . ': ' . $count;
    }

    return implode(', ', $parts);
}

function mf_statement_pdf_parse_filters(array $source)
{
    $period = trim((string) ($source['statement_period'] ?? 'monthly'));
    if (!in_array($period, ['monthly', 'yearly'], true)) {
        $period = 'monthly';
    }

    $year = (int) date('Y');
    $monthNumber = (int) date('n');
    $legacyMonth = trim((string) ($source['statement_month'] ?? ''));

    if (preg_match('/^\d{4}-\d{2}$/', $legacyMonth) === 1) {
        $parts = array_map('intval', explode('-', $legacyMonth));
        if (count($parts) === 2) {
            $legacyYear = (int) $parts[0];
            $legacyMonthNumber = (int) $parts[1];
            if ($legacyYear >= 2000 && $legacyYear <= 9999 && $legacyMonthNumber >= 1 && $legacyMonthNumber <= 12) {
                $year = $legacyYear;
                $monthNumber = $legacyMonthNumber;
            }
        }
    }

    $requestedMonthNumber = (int) ($source['statement_month_num'] ?? $monthNumber);
    if ($requestedMonthNumber >= 1 && $requestedMonthNumber <= 12) {
        $monthNumber = $requestedMonthNumber;
    }

    $requestedYear = trim((string) ($source['statement_year'] ?? ''));
    if (preg_match('/^\d{4}$/', $requestedYear) === 1) {
        $year = (int) $requestedYear;
    }

    if ($period === 'yearly') {
        $startDate = sprintf('%04d-01-01', $year);
        $endDate = sprintf('%04d-12-31', $year);
    } else {
        $startDate = sprintf('%04d-%02d-01', $year, $monthNumber);
        $endDate = date('Y-m-t', strtotime($startDate));
    }

    return [
        'period' => $period,
        'year' => $year,
        'month_number' => $monthNumber,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'period_label' => mf_statement_pdf_period_label($period, $year, $monthNumber),
    ];
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
        $pdf .= 'xref' . "\n";
        $pdf .= '0 ' . ($maxObjectId + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($objectId = 1; $objectId <= $maxObjectId; $objectId++) {
            $offset = isset($offsets[$objectId]) ? $offsets[$objectId] : 0;
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= 'trailer' . "\n";
        $pdf .= '<< /Size ' . ($maxObjectId + 1) . ' /Root 1 0 R >>' . "\n";
        $pdf .= 'startxref' . "\n";
        $pdf .= $xrefOffset . "\n";
        $pdf .= '%%EOF';

        $safeFilename = preg_replace('/[^A-Za-z0-9._-]/', '-', (string) $filename);
        if ($safeFilename === '' || $safeFilename === null) {
            $safeFilename = 'statement.pdf';
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

$tenantId = trim((string) ($_GET['tenant_id'] ?? ''));
if ($tenantId === '') {
    mf_statement_pdf_abort(400, 'Tenant ID is required.');
}

$filters = mf_statement_pdf_parse_filters($_GET);

$tenantStmt = $pdo->prepare("
    SELECT tenant_id, tenant_name, plan_tier, company_address, billing_cycle, next_billing_date
    FROM tenants
    WHERE tenant_id = ?
    LIMIT 1
");
$tenantStmt->execute([$tenantId]);
$tenant = $tenantStmt->fetch(PDO::FETCH_ASSOC);

if (!$tenant) {
    mf_statement_pdf_abort(404, 'Tenant not found.');
}

$invoiceStmt = $pdo->prepare("
    SELECT invoice_number, amount, billing_period_start, billing_period_end, due_date, status, created_at, paid_at
    FROM tenant_billing_invoices
    WHERE tenant_id = ?
      AND DATE(created_at) BETWEEN ? AND ?
    ORDER BY billing_period_start ASC, created_at ASC, invoice_id ASC
");
$invoiceStmt->execute([$tenantId, $filters['start_date'], $filters['end_date']]);
$invoices = $invoiceStmt->fetchAll(PDO::FETCH_ASSOC);

$invoiceCount = count($invoices);
$totalAmount = 0.0;
foreach ($invoices as $invoice) {
    $totalAmount += (float) ($invoice['amount'] ?? 0);
}

$statementHeading = ucfirst($filters['period']) . ' Receipt - ' . $filters['period_label'];
$generatedAtLabel = mf_statement_pdf_safe_date(date('Y-m-d H:i:s'), 'M j, Y g:i A');
$statusSummary = mf_statement_pdf_status_summary($invoices);

$pdf = new MF_SimplePdf();
$page = $pdf->addPage();

$pdf->text($page, 40, 804, 'MicroFin Billing Receipt', 18, 'F2');
$pdf->text($page, 40, 786, 'System-generated tenant billing receipt', 10, 'F1');
$pdf->line($page, 40, 778, 555, 778, 0.8);

$pdf->text($page, 40, 754, 'Tenant:', 10, 'F2');
$pdf->text($page, 95, 754, mf_statement_pdf_clean_text($tenant['tenant_name'] ?? $tenantId), 10, 'F1');
$pdf->text($page, 320, 754, 'Receipt:', 10, 'F2');
$pdf->text($page, 392, 754, $statementHeading, 10, 'F1');

$pdf->text($page, 40, 736, 'Tenant ID:', 10, 'F2');
$pdf->text($page, 95, 736, $tenantId, 10, 'F1');
$pdf->text($page, 320, 736, 'Date Range:', 10, 'F2');
$pdf->text($page, 392, 736, mf_statement_pdf_date_range_label($filters['start_date'], $filters['end_date']), 10, 'F1');

$pdf->text($page, 40, 718, 'Plan:', 10, 'F2');
$pdf->text($page, 95, 718, mf_statement_pdf_clean_text($tenant['plan_tier'] ?? ''), 10, 'F1');
$pdf->text($page, 320, 718, 'Billing Cycle:', 10, 'F2');
$pdf->text($page, 392, 718, mf_statement_pdf_clean_text($tenant['billing_cycle'] ?? 'Monthly'), 10, 'F1');

$pdf->text($page, 40, 700, 'Invoices:', 10, 'F2');
$pdf->text($page, 95, 700, (string) $invoiceCount, 10, 'F1');
$pdf->text($page, 320, 700, 'Total Amount:', 10, 'F2');
$pdf->text($page, 392, 700, mf_statement_pdf_currency($totalAmount), 10, 'F1');

$pdf->text($page, 40, 682, 'Status Mix:', 10, 'F2');
$pdf->text($page, 95, 682, $statusSummary, 10, 'F1');
$pdf->text($page, 320, 682, 'Generated:', 10, 'F2');
$pdf->text($page, 392, 682, $generatedAtLabel, 10, 'F1');

$pdf->line($page, 40, 666, 555, 666, 0.8);

$renderTableHeader = function ($pageIndex, $y) use ($pdf) {
    $pdf->text($pageIndex, 40, $y, 'Invoice #', 10, 'F2');
    $pdf->text($pageIndex, 165, $y, 'Period', 10, 'F2');
    $pdf->text($pageIndex, 255, $y, 'Issued', 10, 'F2');
    $pdf->text($pageIndex, 330, $y, 'Due', 10, 'F2');
    $pdf->text($pageIndex, 405, $y, 'Status', 10, 'F2');
    $pdf->text($pageIndex, 470, $y, 'Amount', 10, 'F2');
    $pdf->line($pageIndex, 40, $y - 6, 555, $y - 6, 0.5);
};

$y = 644;
$renderTableHeader($page, $y);
$y -= 22;

if (empty($invoices)) {
    $pdf->text($page, 40, $y, 'No invoices matched the selected statement filter.', 10, 'F1');
} else {
    foreach ($invoices as $invoice) {
        if ($y < 70) {
            $page = $pdf->addPage();
            $pdf->text($page, 40, 804, 'Billing Receipt (continued)', 15, 'F2');
            $pdf->text($page, 40, 786, mf_statement_pdf_clean_text($tenant['tenant_name'] ?? $tenantId) . ' - ' . $statementHeading, 10, 'F1');
            $pdf->line($page, 40, 778, 555, 778, 0.8);
            $y = 752;
            $renderTableHeader($page, $y);
            $y -= 22;
        }

        $pdf->text($page, 40, $y, mf_statement_pdf_clean_text($invoice['invoice_number'] ?? ''), 9.5, 'F1');
        $pdf->text($page, 165, $y, mf_statement_pdf_invoice_period_label($invoice), 9.5, 'F1');
        $pdf->text($page, 255, $y, mf_statement_pdf_safe_date($invoice['created_at'] ?? '', 'M j, Y'), 9.5, 'F1');
        $pdf->text($page, 330, $y, mf_statement_pdf_safe_date($invoice['due_date'] ?? '', 'M j, Y'), 9.5, 'F1');
        $pdf->text($page, 405, $y, mf_statement_pdf_clean_text($invoice['status'] ?? ''), 9.5, 'F1');
        $pdf->text($page, 470, $y, mf_statement_pdf_currency($invoice['amount'] ?? 0), 9.5, 'F1');

        $y -= 18;
    }
}

$safePeriodToken = $filters['period'] === 'yearly'
    ? (string) $filters['year']
    : sprintf('%04d-%02d', $filters['year'], $filters['month_number']);
$fileName = 'receipt-' . preg_replace('/[^A-Za-z0-9_-]/', '-', $tenantId) . '-' . $safePeriodToken . '.pdf';

$pdf->outputInline($fileName);

