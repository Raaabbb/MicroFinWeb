<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid Request']);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/email_service.php';

function microfin_fetch_payment_receipt_context(mysqli $conn, string $tenantId, int $clientId, int $loanId): array
{
    $clientEmail = '';
    $clientName = '';
    $tenantName = 'MicroFin';
    $loanNumber = '';

    try {
        $clientStmt = $conn->prepare("
            SELECT email_address, first_name, last_name
            FROM clients
            WHERE client_id = ?
            LIMIT 1
        ");
        $clientStmt->bind_param('i', $clientId);
        $clientStmt->execute();
        $clientRow = $clientStmt->get_result()->fetch_assoc() ?: [];
        $clientStmt->close();

        $clientEmail = (string) ($clientRow['email_address'] ?? '');
        $clientName = trim((string) (($clientRow['first_name'] ?? '') . ' ' . ($clientRow['last_name'] ?? '')));
    } catch (Throwable $ignore) {
    }

    try {
        $tenantStmt = $conn->prepare("
            SELECT tenant_name
            FROM tenants
            WHERE tenant_id = ?
            LIMIT 1
        ");
        $tenantStmt->bind_param('s', $tenantId);
        $tenantStmt->execute();
        $tenantRow = $tenantStmt->get_result()->fetch_assoc() ?: [];
        $tenantStmt->close();

        $tenantName = trim((string) ($tenantRow['tenant_name'] ?? '')) ?: 'MicroFin';
    } catch (Throwable $ignore) {
    }

    try {
        $loanStmt = $conn->prepare("
            SELECT loan_number
            FROM loans
            WHERE loan_id = ?
            LIMIT 1
        ");
        $loanStmt->bind_param('i', $loanId);
        $loanStmt->execute();
        $loanRow = $loanStmt->get_result()->fetch_assoc() ?: [];
        $loanStmt->close();

        $loanNumber = (string) ($loanRow['loan_number'] ?? '');
    } catch (Throwable $ignore) {
    }

    return [
        'client_email' => $clientEmail,
        'client_name' => $clientName,
        'tenant_name' => $tenantName,
        'loan_number' => $loanNumber,
    ];
}

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$userId = (int) ($data['user_id'] ?? 0);
$tenantId = trim((string) ($data['tenant_id'] ?? ''));
$loanId = (int) ($data['loan_id'] ?? 0);
$amount = (float) ($data['amount'] ?? 0);
$method = trim((string) ($data['payment_method'] ?? 'Online'));
$refNum = trim((string) ($data['reference_number'] ?? ''));
if ($refNum === '') {
    $refNum = 'REF-' . time();
}

if ($userId <= 0 || $tenantId === '' || $loanId <= 0 || $amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid payment details.']);
    exit;
}

try {
    $existingStmt = $conn->prepare("
        SELECT transaction_id, client_id, source_id, payment_date
        FROM payment_transactions
        WHERE tenant_id = ?
          AND loan_id = ?
          AND source_id = ?
          AND LOWER(status) = 'completed'
        ORDER BY transaction_id DESC
        LIMIT 1
    ");
    $existingStmt->bind_param('sis', $tenantId, $loanId, $refNum);
    $existingStmt->execute();
    $existingPayment = $existingStmt->get_result()->fetch_assoc();
    $existingStmt->close();

    if (is_array($existingPayment)) {
        $receiptContext = microfin_fetch_payment_receipt_context(
            $conn,
            $tenantId,
            (int) ($existingPayment['client_id'] ?? 0),
            $loanId
        );

        echo json_encode([
            'success' => true,
            'message' => 'Payment was already posted successfully.',
            'payment_reference' => $refNum,
            'client_email' => $receiptContext['client_email'],
            'client_name' => $receiptContext['client_name'],
            'tenant_name' => $receiptContext['tenant_name'],
            'loan_number' => $receiptContext['loan_number'],
            'payment_date' => (string) ($existingPayment['payment_date'] ?? date('Y-m-d H:i:s')),
            'already_recorded' => true,
        ]);
        exit;
    }

    $conn->begin_transaction();

    $lStmt = $conn->prepare("
        SELECT client_id, remaining_balance, principal_amount, total_paid
        FROM loans
        WHERE loan_id = ?
          AND tenant_id = ?
        FOR UPDATE
    ");
    $lStmt->bind_param('is', $loanId, $tenantId);
    $lStmt->execute();
    $lRes = $lStmt->get_result();
    $loan = $lRes->fetch_assoc();
    $lStmt->close();

    if (!$loan) {
        throw new Exception('Loan not found.');
    }

    $newBalance = max(0, (float) $loan['remaining_balance'] - $amount);
    $newPaid = (float) $loan['total_paid'] + $amount;
    $newStatus = ($newBalance <= 0) ? 'Fully Paid' : 'Active';

    $uStmt = $conn->prepare("
        UPDATE loans
        SET remaining_balance = ?, total_paid = ?, loan_status = ?
        WHERE loan_id = ?
    ");
    $uStmt->bind_param('ddsi', $newBalance, $newPaid, $newStatus, $loanId);
    $uStmt->execute();
    $uStmt->close();

    $txRef = 'TXN-' . strtoupper(substr(md5(uniqid('', true)), 0, 8)) . '-' . time();
    $pStmt = $conn->prepare("
        INSERT INTO payment_transactions (
            transaction_ref,
            client_id,
            loan_id,
            tenant_id,
            source_id,
            amount,
            payment_method,
            payment_type,
            status,
            payment_date,
            created_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, 'regular', 'completed', NOW(), NOW()
        )
    ");
    $pStmt->bind_param('siissds', $txRef, $loan['client_id'], $loanId, $tenantId, $refNum, $amount, $method);
    $pStmt->execute();
    $pStmt->close();

    $sStmt = $conn->prepare("
        SELECT schedule_id, total_payment AS total_due, amount_paid
        FROM amortization_schedule
        WHERE loan_id = ?
          AND payment_status != 'Paid'
        ORDER BY due_date ASC
    ");
    $sStmt->bind_param('i', $loanId);
    $sStmt->execute();
    $sRes = $sStmt->get_result();

    $remainingPayment = $amount;
    $schedUpdates = [];
    while ($row = $sRes->fetch_assoc()) {
        if ($remainingPayment <= 0) {
            break;
        }

        $due = (float) ($row['total_due'] ?? 0);
        $paid = (float) ($row['amount_paid'] ?? 0);
        $unpaid = $due - $paid;

        if ($unpaid > 0) {
            $allocate = min($remainingPayment, $unpaid);
            $newSchedPaid = $paid + $allocate;
            $remainingPayment -= $allocate;
            $schedStatus = ($newSchedPaid >= $due) ? 'Paid' : 'Partially Paid';

            $schedUpdates[] = [
                'id' => (int) ($row['schedule_id'] ?? 0),
                'paid' => $newSchedPaid,
                'status' => $schedStatus,
            ];
        }
    }
    $sStmt->close();

    $suStmt = $conn->prepare("
        UPDATE amortization_schedule
        SET amount_paid = ?, payment_status = ?
        WHERE schedule_id = ?
    ");
    foreach ($schedUpdates as $schedule) {
        $suStmt->bind_param('dsi', $schedule['paid'], $schedule['status'], $schedule['id']);
        $suStmt->execute();
    }
    $suStmt->close();

    $conn->commit();

    // A notification problem should not turn a committed payment into a client-facing failure.
    try {
        $notifTitle = 'Payment Received';
        $notifMessage = 'Your payment of PHP ' . number_format($amount, 2) . ' has been posted successfully.';
        $nStmt = $conn->prepare("
            INSERT INTO notifications (user_id, tenant_id, notification_type, title, message)
            VALUES (?, ?, 'Payment Received', ?, ?)
        ");
        if ($nStmt) {
            $nStmt->bind_param('isss', $userId, $tenantId, $notifTitle, $notifMessage);
            $nStmt->execute();
            $nStmt->close();
        }
    } catch (Throwable $notificationError) {
        error_log('Payment notification insert failed: ' . $notificationError->getMessage());
    }

    $receiptContext = microfin_fetch_payment_receipt_context($conn, $tenantId, (int) $loan['client_id'], $loanId);

    $paymentDateFormatted = date('Y-m-d H:i:s');

    // Automatically send receipt email to client after every successful payment
    try {
        if ($receiptContext['client_email'] !== '') {
            microfin_send_receipt_email($conn, [
                'tenant_id'         => $tenantId,
                'tenant_name'       => $receiptContext['tenant_name'],
                'user_id'           => $userId > 0 ? $userId : null,
                'client_email'      => $receiptContext['client_email'],
                'client_name'       => $receiptContext['client_name'],
                'payment_reference' => $refNum,
                'payment_method'    => $method,
                'loan_number'       => $receiptContext['loan_number'],
                'payment_date'      => $paymentDateFormatted,
                'amount'            => $amount,
            ]);
        }
    } catch (Throwable $emailError) {
        error_log('Receipt email send failed: ' . $emailError->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'Payment posted successfully.',
        'payment_reference' => $refNum,
        'client_email' => $receiptContext['client_email'],
        'client_name' => $receiptContext['client_name'],
        'tenant_name' => $receiptContext['tenant_name'],
        'loan_number' => $receiptContext['loan_number'],
        'payment_date' => $paymentDateFormatted,
    ]);
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $ignore) {
    }

    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
