<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    exit;
}

require_once __DIR__ . '/../config/db.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid Request']);
    exit;
}

$userId = (int) ($_GET['user_id'] ?? 0);
$tenantId = trim((string) ($_GET['tenant_id'] ?? ''));

if ($userId <= 0 || $tenantId === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Missing user or tenant context.']);
    exit;
}

function microfin_dashboard_has_client_column(mysqli $conn, string $column): bool
{
    static $cache = [];

    if (array_key_exists($column, $cache)) {
        return $cache[$column];
    }

    $stmt = $conn->prepare("
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'clients'
          AND COLUMN_NAME = ?
        LIMIT 1
    ");

    if (!$stmt) {
        $cache[$column] = false;
        return false;
    }

    $stmt->bind_param('s', $column);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows === 1;
    $stmt->close();

    $cache[$column] = $exists;
    return $exists;
}

function microfin_dashboard_normalize_status(string $status): string
{
    $normalized = trim($status);
    if ($normalized === '') {
        return 'Unverified';
    }

    return match ($normalized) {
        'Approved', 'Verified', 'Pending', 'Rejected', 'Unverified' => $normalized,
        default => 'Unverified',
    };
}

function microfin_dashboard_resolve_status(mysqli $conn, array $client): string
{
    $rawVerificationStatus = trim((string) ($client['verification_status'] ?? ''));
    if ($rawVerificationStatus !== '') {
        return microfin_dashboard_normalize_status($rawVerificationStatus);
    }

    $rawDocumentStatus = trim((string) ($client['document_verification_status'] ?? ''));
    if (in_array($rawDocumentStatus, ['Approved', 'Verified', 'Rejected'], true)) {
        return $rawDocumentStatus;
    }

    $clientId = (int) ($client['client_id'] ?? 0);
    $tenantId = trim((string) ($client['tenant_id'] ?? ''));
    if ($clientId <= 0 || $tenantId === '') {
        return 'Unverified';
    }

    $stmt = $conn->prepare("
        SELECT
            COUNT(*) AS total_count,
            SUM(CASE WHEN verification_status = 'Rejected' THEN 1 ELSE 0 END) AS rejected_count,
            SUM(CASE WHEN verification_status IN ('Pending', 'Uploaded', 'CONSIDER') THEN 1 ELSE 0 END) AS pending_count,
            SUM(CASE WHEN verification_status = 'Verified' THEN 1 ELSE 0 END) AS verified_count
        FROM client_documents
        WHERE client_id = ?
          AND tenant_id = ?
    ");

    if (!$stmt) {
        return 'Unverified';
    }

    $stmt->bind_param('is', $clientId, $tenantId);
    $stmt->execute();
    $counts = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();

    $totalCount = (int) ($counts['total_count'] ?? 0);
    $rejectedCount = (int) ($counts['rejected_count'] ?? 0);
    $pendingCount = (int) ($counts['pending_count'] ?? 0);
    $verifiedCount = (int) ($counts['verified_count'] ?? 0);

    if ($rejectedCount > 0) {
        return 'Rejected';
    }

    if ($pendingCount > 0) {
        return 'Pending';
    }

    if ($totalCount > 0 && $verifiedCount === $totalCount) {
        return 'Verified';
    }

    return 'Unverified';
}

try {
    $clientColumns = [
        'c.client_id',
        'c.user_id',
        'c.tenant_id',
        'c.client_code',
        'c.first_name',
        'c.last_name',
        'c.date_of_birth',
        'c.contact_number',
        'c.email_address',
        'c.id_type',
        'c.present_city',
        'c.present_province',
        'c.credit_limit',
        'c.document_verification_status',
        'c.monthly_income',
        'u.username',
        'u.first_name AS user_first_name',
        'u.last_name AS user_last_name'
    ];

    if (microfin_dashboard_has_client_column($conn, 'verification_status')) {
        $clientColumns[] = 'c.verification_status';
    }
    if (microfin_dashboard_has_client_column($conn, 'policy_metadata')) {
        $clientColumns[] = 'c.policy_metadata';
    }

    $clientSql = "
        SELECT " . implode(",\n            ", $clientColumns) . ",
            cs.credit_score,
            cs.credit_rating
        FROM clients c
        INNER JOIN users u
            ON u.user_id = c.user_id
           AND u.tenant_id = c.tenant_id
        LEFT JOIN credit_scores cs ON cs.client_id = c.client_id 
            AND cs.score_id = (SELECT MAX(score_id) FROM credit_scores WHERE client_id = c.client_id)
        WHERE c.user_id = ?
          AND c.tenant_id = ?
          AND c.deleted_at IS NULL
          AND u.deleted_at IS NULL
        LIMIT 1
    ";

    $clientStmt = $conn->prepare($clientSql);
    if (!$clientStmt) {
        throw new RuntimeException('Failed to prepare dashboard client lookup.');
    }

    $clientStmt->bind_param('is', $userId, $tenantId);
    $clientStmt->execute();
    $client = $clientStmt->get_result()->fetch_assoc();
    $clientStmt->close();

    if (!$client) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Client dashboard profile not found.']);
        exit;
    }

    $verificationStatus = microfin_dashboard_resolve_status($conn, $client);

    $loanStmt = $conn->prepare("
        SELECT
            l.loan_id,
            l.loan_number,
            l.loan_status,
            l.total_loan_amount,
            l.remaining_balance,
            l.total_paid,
            l.monthly_amortization,
            COALESCE(
                CASE WHEN l.next_payment_due IN ('', '0000-00-00') THEN NULL ELSE l.next_payment_due END,
                (SELECT due_date FROM amortization_schedule WHERE loan_id = l.loan_id AND payment_status NOT IN ('Paid', 'Fully Paid') ORDER BY due_date ASC LIMIT 1)
            ) AS next_payment_due,
            COALESCE(lp.product_name, 'Loan') AS product_name
        FROM loans l
        LEFT JOIN loan_products lp
            ON lp.product_id = l.product_id
        WHERE l.client_id = ?
          AND l.tenant_id = ?
          AND l.loan_status IN ('Active', 'Overdue')
        ORDER BY
            CASE WHEN l.loan_status = 'Active' THEN 0 ELSE 1 END,
            COALESCE(l.updated_at, l.created_at) DESC,
            l.loan_id DESC
        LIMIT 1
    ");

    if (!$loanStmt) {
        throw new RuntimeException('Failed to prepare active loan lookup.');
    }

    $clientId = (int) $client['client_id'];
    $loanStmt->bind_param('is', $clientId, $tenantId);
    $loanStmt->execute();
    $activeLoan = $loanStmt->get_result()->fetch_assoc() ?: null;
    $loanStmt->close();

    if ($activeLoan) {
        $totalLoanAmount = (float) ($activeLoan['total_loan_amount'] ?? 0);
        $totalPaid = (float) ($activeLoan['total_paid'] ?? 0);
        $progress = $totalLoanAmount > 0 ? max(0, min(1, $totalPaid / $totalLoanAmount)) : 0;
        $activeLoan['progress'] = round($progress, 4);
    }

    $notifications = [];
    $notifStmt = $conn->prepare("
        SELECT
            notification_id,
            notification_type,
            title,
            message,
            is_read,
            priority,
            created_at
        FROM notifications
        WHERE user_id = ?
          AND tenant_id = ?
        ORDER BY created_at DESC, notification_id DESC
        LIMIT 10
    ");

    if ($notifStmt) {
        $notifStmt->bind_param('is', $userId, $tenantId);
        $notifStmt->execute();
        $notifications = $notifStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $notifStmt->close();
    }

    $featuredProducts = [];
    $productsStmt = $conn->prepare("
        SELECT
            product_id,
            product_name,
            'Loan Product' AS product_type,
            '' AS description,
            min_amount,
            max_amount,
            interest_rate,
            min_term_months,
            max_term_months,
            early_settlement_fee_type,
            early_settlement_fee_value,
            billing_cycle
        FROM loan_products
        WHERE tenant_id = ?
          AND is_active = 1
        ORDER BY COALESCE(updated_at, created_at) DESC, product_id DESC
        LIMIT 5
    ");

    if ($productsStmt) {
        $productsStmt->bind_param('s', $tenantId);
        $productsStmt->execute();
        $featuredProducts = $productsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $productsStmt->close();
    }

    $userName = trim((string) ($client['user_first_name'] ?? ''));
    if ($userName === '') {
        $userName = trim((string) ($client['first_name'] ?? ''));
    }
    if ($userName === '') {
        $userName = trim((string) ($client['username'] ?? 'User'));
    }

    $hasBasicProfile = trim((string) ($client['first_name'] ?? '')) !== ''
        && trim((string) ($client['last_name'] ?? '')) !== ''
        && trim((string) ($client['date_of_birth'] ?? '')) !== ''
        && trim((string) ($client['contact_number'] ?? '')) !== ''
        && trim((string) ($client['id_type'] ?? '')) !== ''
        && trim((string) ($client['present_city'] ?? '')) !== ''
        && trim((string) ($client['present_province'] ?? '')) !== '';

    $computedLimit = (float) ($client['credit_limit'] ?? 0);
    try {
        $pdo = new PDO(
            "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset=utf8mb4",
            $dbConfig['username'],
            $dbConfig['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        require_once __DIR__ . '/../../microfin_backend/credit_policy.php';

        $profile = mf_sync_client_credit_profile($pdo, $tenantId, (int) $client['client_id']);
        $computedLimit = (float) ($profile['client']['credit_limit'] ?? $computedLimit);
        if ($computedLimit <= 0) {
            $policyMetadata = json_decode($client['policy_metadata'] ?? '{}', true) ?: [];
            $potentialLimit = (float) ($policyMetadata['potential_limit'] ?? 0);
            if ($potentialLimit > 0) {
                $computedLimit = $potentialLimit;
            }
        }
    } catch (Throwable $pe) {
        error_log("Failed to compute dynamic credit limit: " . $pe->getMessage());
    }

    echo json_encode([
        'success' => true,
        'user_name' => $userName,
        'client_code' => $client['client_code'] ?? '',
        'is_profile_complete' => $hasBasicProfile,
        'verification_status' => $verificationStatus,
        'document_verification_status' => $client['document_verification_status'] ?? 'Unverified',
        'credit_limit' => $computedLimit,
        'credit_score' => $client['credit_score'] ?? 0,
        'credit_rating' => $client['credit_rating'] ?? 'Unverified',
        'active_loan' => $activeLoan,
        'notifications' => $notifications,
        'featured_products' => $featuredProducts,
        'policy_metadata' => json_decode($client['policy_metadata'] ?? '{}', true) ?: null,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to load dashboard: ' . $e->getMessage(),
    ]);
}

