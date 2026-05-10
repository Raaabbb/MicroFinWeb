<?php
require_once __DIR__ . '/api_utils.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/email_service.php';

microfin_api_bootstrap();
microfin_require_post();

$data = microfin_read_json_input();

$tenantId = microfin_clean_string($data['tenant_id'] ?? '');
$tenantName = microfin_clean_string($data['tenant_name'] ?? '');
$clientEmail = microfin_clean_string($data['client_email'] ?? '');
$clientName = microfin_clean_string($data['client_name'] ?? '');
$paymentReference = microfin_clean_string($data['payment_reference'] ?? '');
$paymentMethod = microfin_clean_string($data['payment_method'] ?? '');
$loanNumber = microfin_clean_string($data['loan_number'] ?? '');
$paymentDate = microfin_clean_string($data['payment_date'] ?? '');
$amount = (float) ($data['amount'] ?? 0);
$userId = isset($data['user_id']) ? (int) $data['user_id'] : null;

if ($clientEmail === '' || $paymentReference === '' || $loanNumber === '' || $paymentMethod === '' || $paymentDate === '') {
    microfin_json_response(['success' => false, 'message' => 'Required receipt fields are missing'], 422);
}

try {
    if ($tenantName === '' && $tenantId !== '') {
        $tenantStmt = $conn->prepare("
            SELECT tenant_name
            FROM tenants
            WHERE tenant_id = ?
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $tenantStmt->bind_param('s', $tenantId);
        $tenantStmt->execute();
        $tenant = $tenantStmt->get_result()->fetch_assoc();
        $tenantStmt->close();
        $tenantName = $tenant['tenant_name'] ?? '';
    }

    $emailResult = microfin_send_receipt_email($conn, [
        'tenant_id' => $tenantId !== '' ? $tenantId : null,
        'tenant_name' => $tenantName !== '' ? $tenantName : 'MicroFin',
        'user_id' => $userId,
        'client_email' => $clientEmail,
        'client_name' => $clientName,
        'payment_reference' => $paymentReference,
        'payment_method' => $paymentMethod,
        'loan_number' => $loanNumber,
        'payment_date' => $paymentDate,
        'amount' => $amount,
    ]);

    if (!$emailResult['success']) {
        microfin_json_response(['success' => false, 'message' => $emailResult['message'] ?? 'Unable to send receipt email.'], 500);
    }

    microfin_json_response(['success' => true, 'message' => 'Receipt email queued successfully.']);
} catch (Throwable $e) {
    microfin_json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
