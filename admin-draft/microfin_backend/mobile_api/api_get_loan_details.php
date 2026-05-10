<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') { exit; }
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid Request']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

$loanNum = $_GET['loan_number'] ?? '';
$tenantId = $_GET['tenant_id'] ?? '';

if ($loanNum === '' || $tenantId === '') {
    echo json_encode(['success' => false, 'message' => 'Missing parameter.']);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT 
            l.*, 
            COALESCE(lp.product_name, 'Term Loan') AS product_name,
            COALESCE(lp.early_settlement_fee_type, 'Percentage') AS early_settlement_fee_type,
            COALESCE(lp.early_settlement_fee_value, 0) AS early_settlement_fee_value
        FROM loans l
        LEFT JOIN loan_products lp ON l.product_id = lp.product_id
        WHERE l.loan_number = ? AND l.tenant_id = ?
        LIMIT 1
    ");
    $stmt->bind_param('ss', $loanNum, $tenantId);
    $stmt->execute();
    $res = $stmt->get_result();
    $loan = $res->fetch_assoc();
    $stmt->close();

    if (!$loan) {
        echo json_encode(['success' => false, 'message' => 'Loan not found.']);
        exit;
    }

    $total = floatval($loan['total_loan_amount']);
    $paid = floatval($loan['total_paid']);
    $progress = $total > 0 ? min(1, $paid / $total) : 0;

    // Fetch schedules
    $schedStmt = $conn->prepare("SELECT * FROM amortization_schedule WHERE loan_id = ? ORDER BY due_date ASC");
    $schedStmt->bind_param('i', $loan['loan_id']);
    $schedStmt->execute();
    $schedRes = $schedStmt->get_result();
    $schedules = [];
    while ($r = $schedRes->fetch_assoc()) {
        $schedules[] = $r;
    }
    $schedStmt->close();

    // Fetch transactions (from both admin-entered payments and mobile gateway transactions)
    $txStmt = $conn->prepare("
        (SELECT payment_id AS transaction_id, payment_date, payment_amount AS amount_paid, payment_method, payment_status AS status FROM payments WHERE loan_id = ? AND tenant_id = ?)
        UNION ALL
        (SELECT transaction_id, payment_date, amount AS amount_paid, payment_method, status FROM payment_transactions WHERE loan_id = ? AND tenant_id = ?)
        ORDER BY payment_date DESC
    ");
    $txStmt->bind_param('isis', $loan['loan_id'], $tenantId, $loan['loan_id'], $tenantId);
    $txStmt->execute();
    $txRes = $txStmt->get_result();
    $transactions = [];
    while ($r = $txRes->fetch_assoc()) {
        $transactions[] = $r;
    }
    $txStmt->close();

    // Compute a fallback for next_payment_due if it happens to be missing in the DB
    if (empty($loan['next_payment_due']) && !empty($schedules)) {
        foreach ($schedules as $s) {
            if ($s['payment_status'] !== 'Paid' && $s['payment_status'] !== 'Fully Paid') {
                $loan['next_payment_due'] = $s['due_date'];
                break;
            }
        }
    }

    // Pass the full loan row directly — the Dart code expects raw column names
    // (e.g. total_loan_amount, loan_status, interest_rate, release_date, etc.)
    $loan['product_name'] = $loan['product_name'] ?? 'Term Loan';
    $loan['progress'] = round($progress, 4);
    $loan['number_of_payments'] = count($schedules);
    $loan['transactions'] = $transactions;

    $response = [
        'success' => true,
        'loan' => $loan,
        'schedule' => $schedules,
    ];

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
