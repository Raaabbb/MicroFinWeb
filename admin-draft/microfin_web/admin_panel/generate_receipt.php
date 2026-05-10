<?php
require_once '../../microfin_backend/auth/session_auth.php';
mf_start_backend_session();
require_once '../../microfin_backend/config/db_connect.php';
mf_require_tenant_session($pdo, [
    'response' => 'die',
    'status' => 403,
    'message' => 'Access denied.',
]);
require_once '../../microfin_backend/billing/billing_access.php';
require_once __DIR__ . '/receipt_helpers.php';

function receipt_column_exists(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    $stmt->execute();
    $cache[$key] = (bool)$stmt->fetch();

    return $cache[$key];
}

function receipt_user_can_manage_billing(PDO $pdo, string $tenantId, int $userId): bool
{
    if (!empty($_SESSION['can_manage_billing'])) {
        return true;
    }

    if ($tenantId === '' || $userId <= 0) {
        return false;
    }

    if (receipt_column_exists($pdo, 'users', 'can_manage_billing')) {
        $stmt = $pdo->prepare('SELECT can_manage_billing FROM users WHERE user_id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$userId, $tenantId]);
        if ((bool)$stmt->fetchColumn()) {
            return true;
        }
    }

    return mf_user_can_manage_billing($pdo, $tenantId, $userId);
}

function receipt_safe_text(string $text): string
{
    $normalized = str_replace(["\r\n", "\r"], "\n", $text);
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $normalized);
        if ($converted !== false) {
            $normalized = $converted;
        }
    }

    return $normalized;
}

function receipt_money($amount): string
{
    return 'PHP ' . number_format((float)$amount, 2);
}

function receipt_date(?string $dateValue, string $format = 'M j, Y'): string
{
    if (empty($dateValue)) {
        return 'N/A';
    }

    $timestamp = strtotime($dateValue);
    if ($timestamp === false) {
        return 'N/A';
    }

    return date($format, $timestamp);
}

function receipt_compose_reference(array $receipt): string
{
    $reference = trim((string)($receipt['invoice_number'] ?? ''));
    if ($reference !== '') {
        return $reference;
    }

    return 'INV-' . str_pad((string)((int)($receipt['invoice_id'] ?? 0)), 6, '0', STR_PAD_LEFT);
}

function receipt_compose_period(array $receipt): string
{
    $start = receipt_date((string)($receipt['billing_period_start'] ?? ''));
    $end = receipt_date((string)($receipt['billing_period_end'] ?? ''));
    return $start . ' to ' . $end;
}

function receipt_compose_filter_summary(array $filters): string
{
    $parts = [];
    $monthOptions = admin_receipt_month_options();

    if (($filters['receipt_period'] ?? 'all') === 'month' && !empty($filters['receipt_month'])) {
        $parts[] = 'Month: ' . ($monthOptions[$filters['receipt_month']] ?? 'Selected month');
    } elseif (($filters['receipt_period'] ?? 'all') === 'year' && !empty($filters['receipt_year'])) {
        $parts[] = 'Year: ' . $filters['receipt_year'];
    } else {
        $parts[] = 'Period: All receipts';
    }

    return empty($parts) ? 'Filters: All receipts' : 'Filters: ' . implode(' | ', $parts);
}

function receipt_shorten_text(string $text, int $maxLength): string
{
    $text = trim(preg_replace('/\s+/', ' ', receipt_safe_text($text)));
    if (strlen($text) <= $maxLength) {
        return $text;
    }

    return rtrim(substr($text, 0, max(1, $maxLength - 3))) . '...';
}

class SimpleReceiptPdf
{
    private array $pages = [];
    private string $currentPage = '';
    private float $pageWidthMm = 210.0;
    private float $pageHeightMm = 297.0;

    public function addPage(): void
    {
        if ($this->currentPage !== '') {
            $this->pages[] = $this->currentPage;
        }
        $this->currentPage = '';
    }

    public function text(float $xMm, float $yMm, string $text, float $sizePt = 11.0, string $font = 'regular'): void
    {
        if ($this->currentPage === '' && empty($this->pages)) {
            $this->addPage();
        }

        $fontRef = $font === 'bold' ? 'F2' : 'F1';
        $xPt = $this->mmToPt($xMm);
        $yPt = $this->mmToPt($this->pageHeightMm - $yMm);
        $escaped = str_replace(
            ['\\', '(', ')', "\n"],
            ['\\\\', '\\(', '\\)', '\\n'],
            receipt_safe_text($text)
        );

        $this->currentPage .= sprintf(
            "BT /%s %.2F Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",
            $fontRef,
            $sizePt,
            $xPt,
            $yPt,
            $escaped
        );
    }

    public function line(float $x1Mm, float $y1Mm, float $x2Mm, float $y2Mm): void
    {
        if ($this->currentPage === '' && empty($this->pages)) {
            $this->addPage();
        }

        $this->currentPage .= sprintf(
            "%.2F %.2F m %.2F %.2F l S\n",
            $this->mmToPt($x1Mm),
            $this->mmToPt($this->pageHeightMm - $y1Mm),
            $this->mmToPt($x2Mm),
            $this->mmToPt($this->pageHeightMm - $y2Mm)
        );
    }

    public function writeWrapped(float $xMm, float $yMm, float $widthMm, string $text, float $sizePt = 11.0, string $font = 'regular', float $lineGapMm = 5.2): float
    {
        $lines = $this->wrapText($text, $widthMm, $sizePt);
        foreach ($lines as $line) {
            $this->text($xMm, $yMm, $line, $sizePt, $font);
            $yMm += $lineGapMm;
        }

        return $yMm;
    }

    public function output(string $filename, bool $download): void
    {
        if ($this->currentPage !== '') {
            $this->pages[] = $this->currentPage;
            $this->currentPage = '';
        }

        if (empty($this->pages)) {
            $this->addPage();
            $this->pages[] = $this->currentPage;
        }

        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';

        $pageObjectIds = [];
        $nextObjectId = 5;
        $pageWidthPt = $this->mmToPt($this->pageWidthMm);
        $pageHeightPt = $this->mmToPt($this->pageHeightMm);

        foreach ($this->pages as $pageContent) {
            $contentObjectId = $nextObjectId++;
            $pageObjectId = $nextObjectId++;
            $objects[$contentObjectId] = "<< /Length " . strlen($pageContent) . " >>\nstream\n" . $pageContent . "endstream";
            $objects[$pageObjectId] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>',
                $pageWidthPt,
                $pageHeightPt,
                $contentObjectId
            );
            $pageObjectIds[] = $pageObjectId . ' 0 R';
        }

        $objects[2] = '<< /Type /Pages /Count ' . count($pageObjectIds) . ' /Kids [' . implode(' ', $pageObjectIds) . '] >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $objectId => $objectBody) {
            $offsets[$objectId] = strlen($pdf);
            $pdf .= $objectId . " 0 obj\n" . $objectBody . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }

        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $xrefOffset . "\n%%EOF";

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/pdf');
        header('Content-Length: ' . strlen($pdf));
        header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . basename($filename) . '"');
        echo $pdf;
        exit;
    }

    private function wrapText(string $text, float $widthMm, float $sizePt): array
    {
        $text = trim(preg_replace('/\s+/', ' ', receipt_safe_text($text)));
        if ($text === '') {
            return [''];
        }

        $words = explode(' ', $text);
        $lines = [];
        $line = '';

        foreach ($words as $word) {
            $candidate = $line === '' ? $word : $line . ' ' . $word;
            if ($this->estimateTextWidthMm($candidate, $sizePt) <= $widthMm) {
                $line = $candidate;
                continue;
            }

            if ($line !== '') {
                $lines[] = $line;
            }

            if ($this->estimateTextWidthMm($word, $sizePt) <= $widthMm) {
                $line = $word;
                continue;
            }

            $chunks = str_split($word);
            $currentChunk = '';
            foreach ($chunks as $chunk) {
                $candidateChunk = $currentChunk . $chunk;
                if ($this->estimateTextWidthMm($candidateChunk, $sizePt) <= $widthMm) {
                    $currentChunk = $candidateChunk;
                    continue;
                }
                if ($currentChunk !== '') {
                    $lines[] = $currentChunk;
                }
                $currentChunk = $chunk;
            }
            $line = $currentChunk;
        }

        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines;
    }

    private function estimateTextWidthMm(string $text, float $sizePt): float
    {
        return strlen(receipt_safe_text($text)) * ($sizePt * 0.175);
    }

    private function mmToPt(float $mm): float
    {
        return $mm * 72 / 25.4;
    }
}

function receipt_render_export_header(SimpleReceiptPdf $pdf, string $companyName, string $title, string $subtitle, int $count): float
{
    $pdf->text(15, 18, $companyName, 17, 'bold');
    $pdf->text(15, 25, $title, 11.5, 'bold');
    $subtitleEndY = $pdf->writeWrapped(15, 31, 120, $subtitle, 9.5, 'regular', 4.2);
    $pdf->text(150, 18, 'Generated', 9, 'bold');
    $pdf->text(150, 24, date('M j, Y g:i A'), 10.5, 'regular');
    $pdf->text(150, 32, 'Receipt Count: ' . $count, 10, 'regular');
    $lineY = max(36.0, $subtitleEndY + 1.5);
    $pdf->line(15, $lineY, 195, $lineY);

    return $lineY + 8.0;
}

function receipt_render_label_value(SimpleReceiptPdf $pdf, float $x, float $y, float $width, string $label, string $value): float
{
    $pdf->text($x, $y, strtoupper($label), 8.5, 'bold');
    return $pdf->writeWrapped($x, $y + 5, $width, $value, 11, 'regular');
}

function receipt_render_single_receipt(SimpleReceiptPdf $pdf, string $companyName, array $receipt): void
{
    $pdf->addPage();
    $reference = receipt_compose_reference($receipt);
    $transactionDate = trim((string)($receipt['paid_at'] ?? '')) !== '' ? (string)$receipt['paid_at'] : (string)($receipt['created_at'] ?? '');

    $y = receipt_render_export_header($pdf, $companyName, 'Platform Subscription Receipt', 'Workspace billing transaction summary', 1);
    $pdf->text(15, $y, 'Invoice Number', 8.5, 'bold');
    $pdf->text(15, $y + 5, $reference, 13, 'bold');
    $pdf->text(120, $y, 'Receipt Type', 8.5, 'bold');
    $pdf->text(120, $y + 5, 'Paid Subscription Receipt', 11, 'regular');
    $pdf->text(150, $y, !empty($receipt['paid_at']) ? 'Paid Date' : 'Created Date', 8.5, 'bold');
    $pdf->text(150, $y + 5, receipt_date($transactionDate, 'M j, Y g:i A'), 11, 'regular');
    $pdf->line(15, $y + 12, 195, $y + 12);

    $y += 20;
    $leftY = receipt_render_label_value($pdf, 15, $y, 80, 'Workspace', $companyName);
    $rightY = receipt_render_label_value($pdf, 110, $y, 80, 'Billing Period', receipt_compose_period($receipt));
    $y = max($leftY, $rightY) + 6;

    $leftY = receipt_render_label_value($pdf, 15, $y, 80, 'Due Date', receipt_date((string)($receipt['due_date'] ?? '')));
    $rightY = receipt_render_label_value($pdf, 110, $y, 80, 'Record Type', !empty($receipt['paid_at']) ? 'Completed subscription payment' : 'Subscription invoice');
    $y = max($leftY, $rightY) + 6;

    $leftY = receipt_render_label_value($pdf, 15, $y, 80, 'Gateway Reference', (string)($receipt['stripe_invoice_id'] ?? 'N/A'));
    $rightY = receipt_render_label_value($pdf, 110, $y, 80, 'Original PDF URL', (string)($receipt['pdf_url'] ?? 'N/A'));
    $y = max($leftY, $rightY) + 10;

    $pdf->text(15, $y, 'Billing Breakdown', 10, 'bold');
    $pdf->line(15, $y + 4, 195, $y + 4);
    $y += 12;

    $amountRows = [
        ['Subscription Amount', receipt_money($receipt['amount'] ?? 0)],
        ['Billing Period Start', receipt_date((string)($receipt['billing_period_start'] ?? ''))],
        ['Billing Period End', receipt_date((string)($receipt['billing_period_end'] ?? ''))],
        ['Receipt Date', receipt_date($transactionDate, 'M j, Y g:i A')],
    ];

    foreach ($amountRows as $row) {
        $pdf->text(18, $y, $row[0], 10, $row[0] === 'Total Payment' ? 'bold' : 'regular');
        $pdf->text(150, $y, $row[1], 10, $row[0] === 'Total Payment' ? 'bold' : 'regular');
        $y += 7;
    }

    $y += 4;
    $pdf->line(15, $y, 195, $y);
    $y += 8;

    if (trim((string)($receipt['remarks'] ?? '')) !== '') {
        $pdf->text(15, $y, 'Remarks', 9.5, 'bold');
        $y = $pdf->writeWrapped(15, $y + 5, 180, (string)$receipt['remarks'], 10, 'regular');
        $y += 4;
    }

    $pdf->text(15, max($y + 14, 252), 'Generated from MicroFin Admin Panel', 8.5, 'regular');
    $pdf->text(15, max($y + 20, 258), 'This receipt reflects the recorded workspace billing transaction at the time of export.', 8.5, 'regular');
}

function receipt_render_report_table_header(SimpleReceiptPdf $pdf, float $y): float
{
    $headers = [
        [15, 'Date'],
        [42, 'Invoice #'],
        [82, 'Billing Period'],
        [140, 'Due Date'],
        [168, 'Amount'],
    ];

    foreach ($headers as [$x, $label]) {
        $pdf->text($x, $y, $label, 8.5, 'bold');
    }
    $pdf->line(15, $y + 4, 195, $y + 4);

    return $y + 10;
}

function receipt_render_bulk_export(SimpleReceiptPdf $pdf, string $companyName, array $filters, array $receipts): void
{
    $pdf->addPage();
    $y = receipt_render_export_header($pdf, $companyName, 'Receipts Export', receipt_compose_filter_summary($filters), count($receipts));
    $y = receipt_render_report_table_header($pdf, $y);

    foreach ($receipts as $receipt) {
        if ($y > 274) {
            $pdf->addPage();
            $y = receipt_render_export_header($pdf, $companyName, 'Receipts Export', receipt_compose_filter_summary($filters), count($receipts));
            $y = receipt_render_report_table_header($pdf, $y);
        }

        $rowDate = trim((string)($receipt['paid_at'] ?? '')) !== '' ? (string)$receipt['paid_at'] : (string)($receipt['created_at'] ?? '');
        $pdf->text(15, $y, receipt_date($rowDate), 8.8, 'regular');
        $pdf->text(42, $y, receipt_shorten_text(receipt_compose_reference($receipt), 22), 8.8, 'regular');
        $pdf->text(82, $y, receipt_shorten_text(receipt_compose_period($receipt), 33), 8.8, 'regular');
        $pdf->text(140, $y, receipt_shorten_text(receipt_date((string)($receipt['due_date'] ?? '')), 14), 8.8, 'regular');
        $pdf->text(168, $y, receipt_money($receipt['amount'] ?? 0), 8.8, 'regular');
        $pdf->line(15, $y + 5.5, 195, $y + 5.5);
        $y += 9;
    }
}

$tenantId = (string)$_SESSION['tenant_id'];
$userId = (int)($_SESSION['user_id'] ?? 0);

if (!receipt_user_can_manage_billing($pdo, $tenantId, $userId)) {
    http_response_code(403);
    exit('You are not allowed to access billing receipts.');
}

$tenantStmt = $pdo->prepare('SELECT tenant_name FROM tenants WHERE tenant_id = ? LIMIT 1');
$tenantStmt->execute([$tenantId]);
$companyName = trim((string)$tenantStmt->fetchColumn());
if ($companyName === '') {
    $companyName = 'MicroFin Workspace';
}

$download = isset($_GET['download']) && $_GET['download'] === '1';
$joinSql = ' FROM tenant_billing_invoices i ';

if (isset($_GET['invoice_id']) && ctype_digit((string)$_GET['invoice_id'])) {
    $invoiceId = (int)$_GET['invoice_id'];
    $stmt = $pdo->prepare(
        'SELECT i.*'
        . $joinSql
        . ' WHERE i.tenant_id = ? AND i.invoice_id = ? LIMIT 1'
    );
    $stmt->execute([$tenantId, $invoiceId]);
    $receipt = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$receipt) {
        http_response_code(404);
        exit('Receipt not found.');
    }

    $pdf = new SimpleReceiptPdf();
    receipt_render_single_receipt($pdf, $companyName, $receipt);
    $pdf->output('receipt-' . $invoiceId . '.pdf', $download);
}

if (isset($_GET['export']) && $_GET['export'] === 'all') {
    $filters = admin_receipt_collect_filters($_GET);
    [$whereSql, $params] = admin_receipt_build_query_parts($tenantId, $filters);
    $stmt = $pdo->prepare(
        'SELECT i.*'
        . $joinSql
        . ' WHERE ' . $whereSql
        . ' ORDER BY COALESCE(i.paid_at, i.created_at) DESC, i.invoice_id DESC'
    );
    $stmt->execute($params);
    $receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($receipts)) {
        http_response_code(404);
        exit('No receipts found for the current filters.');
    }

    $pdf = new SimpleReceiptPdf();
    receipt_render_bulk_export($pdf, $companyName, $filters, $receipts);
    $pdf->output('receipts-export-' . date('Ymd-His') . '.pdf', true);
}

http_response_code(400);
exit('Invalid receipt request.');

