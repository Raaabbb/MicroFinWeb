<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid Request']);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/loan_application_rules.php';

$data = json_decode(file_get_contents('php://input'), true) ?: [];

$user_id = (int) ($data['user_id'] ?? 0);
$tenant_id = trim((string) ($data['tenant_id'] ?? ''));
$product_id = (int) ($data['product_id'] ?? 0);
$amount = (float) ($data['amount'] ?? 0);
$term = (int) ($data['term'] ?? 0);
$category = trim((string) ($data['purpose_category'] ?? ''));
$purpose = trim((string) ($data['purpose'] ?? ''));
$documents = is_array($data['documents'] ?? null) ? $data['documents'] : [];
$app_data = $data['app_data'] ?? '{}';

if ($user_id <= 0 || $tenant_id === '' || $product_id <= 0 || $amount <= 0 || $term <= 0) {
    echo json_encode(['success' => false, 'message' => 'Required application details are missing']);
    exit;
}

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("
        SELECT
            client_id,
            document_verification_status,
            credit_limit,
            comaker_name,
            comaker_relationship,
            comaker_contact,
            comaker_income,
            comaker_house_no,
            comaker_street,
            comaker_barangay,
            comaker_city,
            comaker_province,
            comaker_postal_code,
            policy_metadata
        FROM clients
        WHERE user_id = ?
          AND tenant_id = ?
        LIMIT 1
    ");
    $stmt->bind_param('is', $user_id, $tenant_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        throw new Exception('Profile not verified. Please complete verification first.');
    }

    $client = $res->fetch_assoc();
    $stmt->close();

    $client_id = (int) ($client['client_id'] ?? 0);
    $verificationStatus = trim((string) ($client['document_verification_status'] ?? ''));
    if ($verificationStatus !== 'Approved' && $verificationStatus !== 'Verified') {
        throw new Exception('Your profile must be Approved or Verified before applying for a loan.');
    }

    $credit_limit = (float) ($client['credit_limit'] ?? 0);
    $policyMetadata = json_decode($client['policy_metadata'] ?? '{}', true) ?: [];
    $potentialLimit = (float) ($policyMetadata['potential_limit'] ?? 0);
    if ($credit_limit <= 0 && $potentialLimit > 0) {
        $credit_limit = $potentialLimit;
    }
    $creditSummary = microfin_build_client_loan_application_summary($conn, [
        'client_id' => $client_id,
        'tenant_id' => $tenant_id,
        'document_verification_status' => $verificationStatus,
        'credit_limit' => $credit_limit,
    ]);

    $available_credit = (float) ($creditSummary['remaining_credit'] ?? 0);
    if ($credit_limit <= 0) {
        throw new Exception('No credit limit is currently available for your account.');
    }
    if ($amount > $available_credit) {
        throw new Exception(
            'Requested amount (PHP ' . number_format($amount, 2) .
            ') exceeds your remaining credit limit of PHP ' . number_format(max(0, $available_credit), 2) . '.'
        );
    }

    $pStmt = $conn->prepare("
        SELECT
            interest_rate,
            'Loan Product' AS product_type,
            min_amount,
            max_amount,
            min_term_months,
            max_term_months,
            early_settlement_fee_type,
            early_settlement_fee_value,
            billing_cycle,
            processing_fee_percentage,
            service_charge,
            documentary_stamp,
            insurance_fee_percentage,
            grace_period_days,
            interest_type
        FROM loan_products
        WHERE product_id = ?
          AND tenant_id = ?
          AND is_active = 1
        LIMIT 1
    ");
    $pStmt->bind_param('is', $product_id, $tenant_id);
    $pStmt->execute();
    $pRes = $pStmt->get_result();
    if ($pRes->num_rows === 0) {
        throw new Exception('Selected loan product not found.');
    }

    $pRow = $pRes->fetch_assoc();
    $pStmt->close();

    $interest_rate = (float) ($pRow['interest_rate'] ?? 0);
    $product_type = trim((string) ($pRow['product_type'] ?? 'Loan Product'));
    $product_min_amount = (float) ($pRow['min_amount'] ?? 0);
    $product_max_amount = (float) ($pRow['max_amount'] ?? 0);
    $product_min_term = (int) ($pRow['min_term_months'] ?? 0);
    $product_max_term = (int) ($pRow['max_term_months'] ?? 0);

    $annotatedProducts = microfin_annotate_loan_products([[
        'product_id' => $product_id,
        'min_amount' => $product_min_amount,
        'max_amount' => $product_max_amount,
    ]], $creditSummary);
    $productState = $annotatedProducts[0] ?? [];
    $productIsAvailable = !empty($productState['is_available']);
    $effectiveMaxAmount = (float) ($productState['effective_max_amount'] ?? 0);
    $productReason = trim((string) ($productState['availability_reason'] ?? ''));

    if (!$productIsAvailable) {
        throw new Exception($productReason !== '' ? $productReason : 'This loan product is not available right now.');
    }
    if ($product_min_amount > 0 && $amount < $product_min_amount) {
        throw new Exception('Requested amount must be at least PHP ' . number_format($product_min_amount, 2) . ' for this loan product.');
    }
    if ($effectiveMaxAmount > 0 && $amount > $effectiveMaxAmount) {
        throw new Exception('Requested amount cannot exceed your remaining availability of PHP ' . number_format($effectiveMaxAmount, 2) . ' for this product.');
    }
    if ($product_max_amount > 0 && $amount > $product_max_amount) {
        throw new Exception('Requested amount cannot exceed PHP ' . number_format($product_max_amount, 2) . ' for this loan product.');
    }
    if ($product_min_term > 0 && $term < $product_min_term) {
        throw new Exception('Loan term must be at least ' . number_format($product_min_term) . ' month(s) for this loan product.');
    }
    if ($product_max_term > 0 && $term > $product_max_term) {
        throw new Exception('Loan term cannot exceed ' . number_format($product_max_term) . ' month(s) for this loan product.');
    }

    $activeLoanStmt = $conn->prepare("
        SELECT loan_id
        FROM loans
        WHERE client_id = ?
          AND tenant_id = ?
          AND product_id = ?
          AND loan_status IN ('Active', 'Overdue', 'Restructured')
        LIMIT 1
    ");
    $activeLoanStmt->bind_param('isi', $client_id, $tenant_id, $product_id);
    $activeLoanStmt->execute();
    if ($activeLoanStmt->get_result()->num_rows > 0) {
        $activeLoanStmt->close();
        throw new Exception('You already have an active loan for this product.');
    }
    $activeLoanStmt->close();

    $dupStmt = $conn->prepare("
        SELECT la.application_id
        FROM loan_applications la
        LEFT JOIN loans linked_loan
            ON linked_loan.application_id = la.application_id
           AND linked_loan.tenant_id = la.tenant_id
        WHERE la.client_id = ?
          AND la.tenant_id = ?
          AND la.product_id = ?
          AND la.application_status IN ('Submitted', 'Pending', 'Pending Review', 'Under Review', 'Document Verification', 'Credit Investigation', 'For Approval', 'Approved')
          AND linked_loan.loan_id IS NULL
        LIMIT 1
    ");
    $dupStmt->bind_param('isi', $client_id, $tenant_id, $product_id);
    $dupStmt->execute();
    if ($dupStmt->get_result()->num_rows > 0) {
        $dupStmt->close();
        throw new Exception('You already have a pending application for this product.');
    }
    $dupStmt->close();

    $app_number = strtoupper($tenant_id) . '-' . date('YmdHi') . '-' . str_pad((string) $client_id, 4, '0', STR_PAD_LEFT);

    $co_name = trim((string) ($client['comaker_name'] ?? ''));
    $has_co = $co_name !== '' ? 1 : 0;
    $co_address = trim(
        trim((string) ($client['comaker_house_no'] ?? '')) . ' ' .
        trim((string) ($client['comaker_street'] ?? '')) . ' ' .
        trim((string) ($client['comaker_barangay'] ?? '')) . ' ' .
        trim((string) ($client['comaker_city'] ?? ''))
    );

    $app_data_decoded = json_decode($app_data, true) ?: [];
    $app_data_decoded['product_snapshot'] = [
        'processing_fee_percentage' => (float) ($pRow['processing_fee_percentage'] ?? 0),
        'service_charge' => (float) ($pRow['service_charge'] ?? 0),
        'documentary_stamp' => (float) ($pRow['documentary_stamp'] ?? 0),
        'insurance_fee_percentage' => (float) ($pRow['insurance_fee_percentage'] ?? 0),
        'early_settlement_fee_type' => trim((string) ($pRow['early_settlement_fee_type'] ?? '')),
        'early_settlement_fee_value' => (float) ($pRow['early_settlement_fee_value'] ?? 0),
        'billing_cycle' => trim((string) ($pRow['billing_cycle'] ?? '')),
        'grace_period_days' => (int) ($pRow['grace_period_days'] ?? 0),
        'interest_type' => trim((string) ($pRow['interest_type'] ?? 'Fixed')),
        'product_type' => trim((string) ($pRow['product_type'] ?? 'Loan Product')),
    ];
    $final_application_data = json_encode($app_data_decoded, JSON_UNESCAPED_UNICODE);

    $iaStmt = $conn->prepare("
        INSERT INTO loan_applications (
            application_number,
            client_id,
            tenant_id,
            product_id,
            requested_amount,
            loan_term_months,
            interest_rate,
            purpose_category,
            loan_purpose,
            application_data,
            application_status,
            submitted_date,
            has_comaker,
            comaker_name,
            comaker_relationship,
            comaker_contact,
            comaker_address,
            comaker_income
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Submitted', NOW(), ?, ?, ?, ?, ?, ?
        )
    ");
    $iaStmt->bind_param(
        'sisidisssssisssd',
        $app_number,
        $client_id,
        $tenant_id,
        $product_id,
        $amount,
        $term,
        $interest_rate,
        $category,
        $purpose,
        $final_application_data,
        $has_co,
        $co_name,
        $client['comaker_relationship'],
        $client['comaker_contact'],
        $co_address,
        $client['comaker_income']
    );
    if (!$iaStmt->execute()) {
        throw new Exception('Failed to save loan application: ' . $iaStmt->error);
    }

    $application_id = $conn->insert_id;
    $iaStmt->close();

    if (!empty($documents)) {
        $dStmt = $conn->prepare("
            INSERT INTO application_documents (
                application_id,
                tenant_id,
                document_type_id,
                file_name,
                file_path
            ) VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($documents as $doc) {
            $doc_type_id = (int) ($doc['document_type_id'] ?? 0);
            $file_name = trim((string) ($doc['file_name'] ?? ''));
            $file_path = trim((string) ($doc['file_path'] ?? ''));
            $dStmt->bind_param('isiss', $application_id, $tenant_id, $doc_type_id, $file_name, $file_path);
            $dStmt->execute();
        }

        $dStmt->close();
    }

    $notif_title = 'Loan Application Submitted';
    $notif_message = "Your application $app_number for $product_type has been submitted and is under review.";
    $nStmt = $conn->prepare("
        INSERT INTO notifications (user_id, tenant_id, notification_type, title, message, priority)
        VALUES (?, ?, 'General', ?, ?, 'High')
    ");
    $nStmt->bind_param('isss', $user_id, $tenant_id, $notif_title, $notif_message);
    $nStmt->execute();
    $nStmt->close();

    $conn->commit();
    echo json_encode([
        'success' => true,
        'message' => 'Loan application submitted successfully!',
        'application_id' => $application_id,
        'application_number' => $app_number,
    ]);
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $ignore) {
    }

    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

