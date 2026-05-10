<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') { exit; }

require_once __DIR__ . '/../config/db.php';

$userId = (int)($_GET['user_id'] ?? 0);
$tenantId = trim((string)($_GET['tenant_id'] ?? ''));

if ($userId <= 0 || $tenantId === '') {
    echo json_encode(['success' => false, 'message' => 'Missing user or tenant context.']);
    exit;
}

try {
    $cStmt = $conn->prepare("SELECT client_id FROM clients WHERE user_id = ? AND tenant_id = ? AND deleted_at IS NULL LIMIT 1");
    $cStmt->bind_param('is', $userId, $tenantId);
    $cStmt->execute();
    $cRes = $cStmt->get_result();
    $client = $cRes->fetch_assoc();
    $cStmt->close();

    if (!$client) {
        echo json_encode(['success' => true, 'transactions' => []]);
        exit;
    }
    
    $clientId = $client['client_id'];

    $tStmt = $conn->prepare("
        (SELECT 
            p.payment_id AS transaction_id, p.loan_id, 
            p.payment_amount, p.payment_date,
            p.payment_method, p.payment_status, p.payment_reference,
            p.principal_paid, p.interest_paid,
            l.loan_number, l.remaining_balance,
            CONCAT(c.first_name, ' ', c.last_name) AS client_name
        FROM payments p
        JOIN loans l ON p.loan_id = l.loan_id
        JOIN clients c ON p.client_id = c.client_id
        WHERE p.client_id = ? AND p.tenant_id = ?)
        UNION ALL
        (SELECT 
            t.transaction_id, t.loan_id, 
            t.amount AS payment_amount, t.payment_date,
            t.payment_method, t.status AS payment_status, t.transaction_ref AS payment_reference,
            0 AS principal_paid, 0 AS interest_paid,
            l.loan_number, l.remaining_balance,
            CONCAT(c.first_name, ' ', c.last_name) AS client_name
        FROM payment_transactions t
        JOIN loans l ON t.loan_id = l.loan_id
        JOIN clients c ON t.client_id = c.client_id
        WHERE t.client_id = ? AND t.tenant_id = ?)
        ORDER BY payment_date DESC
    ");
    $tStmt->bind_param('isis', $clientId, $tenantId, $clientId, $tenantId);
    $tStmt->execute();
    $res = $tStmt->get_result();
    
    $transactions = [];
    while ($row = $res->fetch_assoc()) {
        $transactions[] = $row;
    }
    $tStmt->close();

    echo json_encode(['success' => true, 'transactions' => $transactions]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
