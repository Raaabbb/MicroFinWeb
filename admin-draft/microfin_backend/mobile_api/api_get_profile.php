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
    $cStmt = $conn->prepare("
        SELECT c.*, u.username, u.email,
            cs.credit_score,
            cs.credit_rating
        FROM clients c 
        LEFT JOIN users u ON c.user_id = u.user_id
        LEFT JOIN credit_scores cs ON cs.client_id = c.client_id 
            AND cs.score_id = (SELECT MAX(score_id) FROM credit_scores WHERE client_id = c.client_id)
        WHERE c.user_id = ? AND c.tenant_id = ? AND c.deleted_at IS NULL LIMIT 1
    ");
    $cStmt->bind_param('is', $userId, $tenantId);
    $cStmt->execute();
    $cRes = $cStmt->get_result();
    $client = $cRes->fetch_assoc();
    $cStmt->close();

    if (!$client) {
        // Just return user details if client profile isn't fully set up yet
        $uStmt = $conn->prepare("SELECT user_id, username, email, first_name, last_name, phone_number FROM users WHERE user_id = ? AND tenant_id = ?");
        $uStmt->bind_param('is', $userId, $tenantId);
        $uStmt->execute();
        $client = $uStmt->get_result()->fetch_assoc();
        $uStmt->close();
    }

    if (!$client) {
        $client = [];
    }

    $memberSince = '';
    $registrationDate = trim((string) ($client['registration_date'] ?? ''));
    if ($registrationDate !== '') {
        $timestamp = strtotime($registrationDate);
        if ($timestamp !== false) {
            $memberSince = date('Y', $timestamp);
        }
    }
    $client['member_since'] = $memberSince;
    $client['policy_metadata'] = json_decode((string)($client['policy_metadata'] ?? '{}'), true) ?: null;

    echo json_encode(['success' => true, 'profile' => $client]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
