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
        echo json_encode(['success' => true, 'applications' => []]);
        exit;
    }
    
    $clientId = $client['client_id'];

    $aStmt = $conn->prepare("
        SELECT 
            la.application_id, la.application_number, la.requested_amount, la.loan_term_months, la.application_status, la.submitted_date,
            COALESCE(lp.product_name, 'Term Loan') AS product_name
        FROM loan_applications la
        LEFT JOIN loan_products lp ON la.product_id = lp.product_id
        WHERE la.client_id = ? AND la.tenant_id = ?
        ORDER BY la.submitted_date DESC
    ");
    $aStmt->bind_param('is', $clientId, $tenantId);
    $aStmt->execute();
    $res = $aStmt->get_result();
    
    $applications = [];
    while ($row = $res->fetch_assoc()) {
        $applications[] = $row;
    }
    $aStmt->close();

    echo json_encode(['success' => true, 'applications' => $applications]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
