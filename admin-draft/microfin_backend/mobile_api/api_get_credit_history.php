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
    // 1. Get Client ID
    $cStmt = $conn->prepare("SELECT client_id FROM clients WHERE user_id = ? AND tenant_id = ? AND deleted_at IS NULL LIMIT 1");
    $cStmt->bind_param('is', $userId, $tenantId);
    $cStmt->execute();
    $client = $cStmt->get_result()->fetch_assoc();
    $cStmt->close();

    if (!$client) {
        echo json_encode(['success' => false, 'message' => 'Client profile not found.']);
        exit;
    }

    $clientId = (int)$client['client_id'];

    // 2. Fetch Score History
    $hStmt = $conn->prepare("
        SELECT 
            score_id,
            credit_score,
            credit_rating,
            max_loan_amount,
            computation_date,
            notes,
            score_metadata
        FROM credit_scores
        WHERE client_id = ? AND tenant_id = ?
        ORDER BY computation_date DESC, score_id DESC
    ");
    $hStmt->bind_param('is', $clientId, $tenantId);
    $hStmt->execute();
    $history = $hStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $hStmt->close();

    // Decode metadata for each entry
    foreach ($history as &$entry) {
        $entry['score_metadata'] = json_decode($entry['score_metadata'] ?? '{}', true) ?: null;
    }

    echo json_encode([
        'success' => true,
        'history' => $history
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
