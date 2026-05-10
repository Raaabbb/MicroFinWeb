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

$userId = (int)($_GET['user_id'] ?? 0);
$tenantId = trim((string)($_GET['tenant_id'] ?? ''));

if ($userId <= 0 || $tenantId === '') {
    echo json_encode(['success' => false, 'message' => 'Missing user or tenant context.']);
    exit;
}

try {
    // 1. Get client ID
    $cStmt = $conn->prepare("SELECT client_id FROM clients WHERE user_id = ? AND tenant_id = ? AND deleted_at IS NULL LIMIT 1");
    $cStmt->bind_param('is', $userId, $tenantId);
    $cStmt->execute();
    $cRes = $cStmt->get_result();
    $client = $cRes->fetch_assoc();
    $cStmt->close();

    if (!$client) {
        echo json_encode(['success' => true, 'loans' => []]);
        exit;
    }
    $clientId = $client['client_id'];

    // 2. Get active and past loans
    $lStmt = $conn->prepare("
        SELECT 
            l.loan_id, l.loan_number, l.loan_status, l.principal_amount, l.total_loan_amount,
            l.remaining_balance, l.total_paid, l.monthly_amortization, 
            COALESCE(
                CASE WHEN l.next_payment_due IN ('', '0000-00-00') THEN NULL ELSE l.next_payment_due END,
                (SELECT due_date FROM amortization_schedule WHERE loan_id = l.loan_id AND payment_status NOT IN ('Paid', 'Fully Paid') ORDER BY due_date ASC LIMIT 1)
            ) AS next_payment_due,
            COALESCE(lp.product_name, 'Term Loan') AS product_name
        FROM loans l
        LEFT JOIN loan_products lp ON l.product_id = lp.product_id
        WHERE l.client_id = ? AND l.tenant_id = ? AND l.loan_status != 'Draft'
        ORDER BY 
            CASE WHEN l.loan_status = 'Active' THEN 0 ELSE 1 END,
            l.created_at DESC
    ");
    $lStmt->bind_param('is', $clientId, $tenantId);
    $lStmt->execute();
    $res = $lStmt->get_result();
    
    $loans = [];
    while ($row = $res->fetch_assoc()) {
        $total = floatval($row['total_loan_amount']);
        $paid = floatval($row['total_paid']);
        $progress = $total > 0 ? min(1, $paid / $total) : 0;
        $row['progress'] = round($progress, 4);
        $loans[] = $row;
    }
    $lStmt->close();

    echo json_encode(['success' => true, 'loans' => $loans]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
