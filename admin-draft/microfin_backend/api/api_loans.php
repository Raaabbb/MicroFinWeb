<?php
header('Content-Type: application/json');
require_once '../auth/session_auth.php';
mf_start_backend_session();
require_once '../config/db_connect.php';
require_once '../engines/credit_policy.php';
mf_require_tenant_session($pdo, [
    'response' => 'json',
    'status' => 401,
    'message' => 'Unauthorized.',
]);

$tenant_id = (string) ($_SESSION['tenant_id'] ?? '');
$session_user_id = (int) ($_SESSION['user_id'] ?? 0);

if ($tenant_id === '') {
    echo json_encode(['status' => 'error', 'message' => 'Missing tenant context.']);
    exit;
}

// ─── Load permissions ─────────────────────────────────────────────────────────
$perm_stmt = $pdo->prepare('
    SELECT p.permission_code 
    FROM role_permissions rp 
    JOIN permissions p ON rp.permission_id = p.permission_id 
    JOIN users u ON u.role_id = rp.role_id
    WHERE u.user_id = ?
');
$perm_stmt->execute([$session_user_id]);
$permissions = $perm_stmt->fetchAll(PDO::FETCH_COLUMN);
function has_perm($code)
{
    global $permissions;
    return in_array($code, $permissions);
}
function can_access_loans_module()
{
    return has_perm('VIEW_LOANS') || has_perm('CREATE_LOANS') || has_perm('APPROVE_LOANS');
}

$action = strtolower(trim((string) ($_GET['action'] ?? $_POST['action'] ?? '')));
$method = $_SERVER['REQUEST_METHOD'];

// ─── GET: list loans ──────────────────────────────────────────────────────────
if ($method === 'GET' && ($action === 'list' || $action === '')) {
    if (!can_access_loans_module()) {
        echo json_encode(['status' => 'error', 'message' => 'Permission denied.']);
        exit;
    }

    $status_filter = trim((string) ($_GET['status'] ?? ''));
    $where_extra = '';
    $params = [$tenant_id];

    if ($status_filter !== '' && $status_filter !== 'all') {
        $where_extra = ' AND l.loan_status = ?';
        $params[] = $status_filter;
    }

    $stmt = $pdo->prepare("
        SELECT
            l.loan_id, l.loan_number, l.loan_status, l.principal_amount,
            l.total_loan_amount, l.remaining_balance, l.monthly_amortization,
            l.release_date, l.maturity_date, l.next_payment_due,
            l.days_overdue, l.payment_frequency, l.total_paid,
            l.loan_term_months, l.interest_rate,
            c.client_id, c.first_name, c.last_name, c.contact_number, c.email_address,
              lp.product_name,
              la.application_data,
              JSON_UNQUOTE(JSON_EXTRACT(la.application_data, '$.product_type')) AS product_type
          FROM loans l
          JOIN clients c ON l.client_id = c.client_id
          JOIN loan_products lp ON l.product_id = lp.product_id
          LEFT JOIN loan_applications la ON l.application_id = la.application_id
        WHERE l.tenant_id = ? $where_extra
        ORDER BY l.release_date DESC
        LIMIT 200
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'data' => $rows]);
    exit;
}

// ─── GET: amortization schedule ───────────────────────────────────────────────
if ($method === 'GET' && $action === 'approved_applications') {
    if (!can_access_loans_module()) {
        echo json_encode(['status' => 'error', 'message' => 'Permission denied.']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT
            la.application_id, la.application_number, la.application_status,
            la.requested_amount, la.approved_amount, la.approval_date, la.submitted_date,
            la.loan_term_months, la.interest_rate, la.application_data,
            c.client_id, c.first_name, c.last_name, c.contact_number,
              lp.product_name,
              la.application_data,
              JSON_UNQUOTE(JSON_EXTRACT(la.application_data, '$.product_type')) AS product_type
        FROM loan_applications la
        JOIN clients c ON la.client_id = c.client_id
        JOIN loan_products lp ON la.product_id = lp.product_id
        LEFT JOIN loans l ON l.application_id = la.application_id
        WHERE la.tenant_id = ?
          AND la.application_status = 'Approved'
          AND l.loan_id IS NULL
        ORDER BY COALESCE(la.approval_date, la.updated_at, la.submitted_date, la.created_at) DESC
        LIMIT 200
    ");
    $stmt->execute([$tenant_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'data' => $rows]);
    exit;
}

if ($method === 'GET' && $action === 'schedule') {
    if (!can_access_loans_module()) {
        echo json_encode(['status' => 'error', 'message' => 'Permission denied.']);
        exit;
    }

    $loan_id = (int) ($_GET['loan_id'] ?? 0);
    if ($loan_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid loan ID.']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT schedule_id, payment_number, due_date, beginning_balance,
               principal_amount, interest_amount, total_payment, ending_balance,
               payment_status, amount_paid, payment_date, days_late, penalty_amount
        FROM amortization_schedule
        WHERE loan_id = ? AND tenant_id = ?
        ORDER BY payment_number ASC
    ");
    $stmt->execute([$loan_id, $tenant_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'data' => $rows]);
    exit;
}

// ─── GET: view single loan ────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'view') {
    if (!can_access_loans_module()) {
        echo json_encode(['status' => 'error', 'message' => 'Permission denied.']);
        exit;
    }

    $loan_id = (int) ($_GET['loan_id'] ?? 0);
    if ($loan_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid loan ID.']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT l.*, 
               c.first_name, c.last_name, c.contact_number, c.email_address,
               lp.product_name,
               la.application_data,
               JSON_UNQUOTE(JSON_EXTRACT(la.application_data, '$.product_type')) AS product_type
        FROM loans l
        JOIN clients c ON l.client_id = c.client_id
        JOIN loan_products lp ON l.product_id = lp.product_id
        LEFT JOIN loan_applications la ON l.application_id = la.application_id
        WHERE l.loan_id = ? AND l.tenant_id = ?
        LIMIT 1
    ");
    $stmt->execute([$loan_id, $tenant_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['status' => 'error', 'message' => 'Loan not found.']);
        exit;
    }

    echo json_encode(['status' => 'success', 'data' => $row]);
    exit;
}

function loan_release_generate_reference(int $application_id, string $release_date): string
{
    $date_part = preg_replace('/[^0-9]/', '', $release_date);
    if ($date_part === null || strlen($date_part) !== 8) {
        $date_part = date('Ymd');
    }

    return 'DISB-' . $date_part . '-' . str_pad((string) max(0, $application_id), 6, '0', STR_PAD_LEFT);
}

function loan_release_extract_application_data(array $application): array
{
    $raw = $application['application_data'] ?? null;
    if (is_array($raw)) {
        return $raw;
    }

    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        if (isset($decoded[0]) && is_string($decoded[0])) {
            $nested = json_decode((string) $decoded[0], true);
            return is_array($nested) ? $nested : $decoded;
        }
        return $decoded;
    }

    if (is_string($decoded)) {
        $nested = json_decode($decoded, true);
        return is_array($nested) ? $nested : [];
    }

    return [];
}

function loan_release_default_payment_frequency(array $application): string
{
    $data = loan_release_extract_application_data($application);
    $candidate = trim((string) ($data['payment_frequency'] ?? $data['repayment_frequency'] ?? $data['frequency'] ?? 'Monthly'));
    $allowed = ['Daily', 'Weekly', 'Bi-Weekly', 'Monthly'];
    return in_array($candidate, $allowed, true) ? $candidate : 'Monthly';
}

function loan_release_default_disbursement_method(array $application): string
{
    $data = loan_release_extract_application_data($application);
    $candidate = trim((string) ($data['disbursement_method'] ?? $data['release_method'] ?? 'Cash'));
    $allowed = ['Cash', 'Check', 'Bank Transfer', 'GCash'];
    return in_array($candidate, $allowed, true) ? $candidate : 'Cash';
}

// ─── POST: release/disburse loan from approved application ────────────────────
if ($method === 'POST' && $action === 'release') {
    if (!has_perm('APPROVE_LOANS')) {
        echo json_encode(['status' => 'error', 'message' => 'Permission denied.']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    if (!is_array($payload))
        $payload = $_POST;

    $application_id = (int) ($payload['application_id'] ?? 0);
    $approved_amount = (float) ($payload['approved_amount'] ?? 0);
    $disbursement_method = trim((string) ($payload['disbursement_method'] ?? ''));
    $disbursement_ref = trim((string) ($payload['disbursement_reference'] ?? ''));
    $release_date = trim((string) ($payload['release_date'] ?? date('Y-m-d')));
    $payment_frequency = trim((string) ($payload['payment_frequency'] ?? ''));
    $notes = trim((string) ($payload['notes'] ?? ''));

    if ($application_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Application ID is required.']);
        exit;
    }

    // Fetch the approved application
    $app_stmt = $pdo->prepare("
        SELECT la.*, lp.interest_rate,
               JSON_UNQUOTE(JSON_EXTRACT(la.application_data, '$.interest_type')) AS interest_type,
               JSON_UNQUOTE(JSON_EXTRACT(la.application_data, '$.processing_fee_percentage')) AS processing_fee_percentage,
               JSON_UNQUOTE(JSON_EXTRACT(la.application_data, '$.service_charge')) AS service_charge,
               JSON_UNQUOTE(JSON_EXTRACT(la.application_data, '$.documentary_stamp')) AS documentary_stamp,
               JSON_UNQUOTE(JSON_EXTRACT(la.application_data, '$.insurance_fee_percentage')) AS insurance_fee_percentage
        FROM loan_applications la
        JOIN loan_products lp ON la.product_id = lp.product_id
        WHERE la.application_id = ? AND la.tenant_id = ? AND la.application_status = 'Approved'
        LIMIT 1
    ");
    $app_stmt->execute([$application_id, $tenant_id]);
    $app = $app_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$app) {
        echo json_encode(['status' => 'error', 'message' => 'Approved application not found.']);
        exit;
    }

    // Check no loan already released for this application
    $dup_stmt = $pdo->prepare('SELECT 1 FROM loans WHERE application_id = ? LIMIT 1');
    $dup_stmt->execute([$application_id]);
    if ($dup_stmt->fetchColumn()) {
        echo json_encode(['status' => 'error', 'message' => 'A loan has already been released for this application.']);
        exit;
    }

    // Get employee_id
    $emp_stmt = $pdo->prepare('SELECT employee_id FROM employees WHERE user_id = ? AND tenant_id = ? LIMIT 1');
    $emp_stmt->execute([$session_user_id, $tenant_id]);
    $employee_id = $emp_stmt->fetchColumn();
    if (!$employee_id) {
        echo json_encode(['status' => 'error', 'message' => 'Only employees can release loans.']);
        exit;
    }
    $employee_id = (int) $employee_id;

    $approved_amount = $approved_amount > 0
        ? $approved_amount
        : ((float) ($app['approved_amount'] ?? 0) > 0
            ? (float) $app['approved_amount']
            : (float) ($app['requested_amount'] ?? 0));

    if ($approved_amount <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Approved amount could not be resolved for this application.']);
        exit;
    }

    if ($release_date === '') {
        $release_date = date('Y-m-d');
    }

    $disbursement_method = $disbursement_method !== ''
        ? $disbursement_method
        : loan_release_default_disbursement_method($app);
    $payment_frequency = $payment_frequency !== ''
        ? $payment_frequency
        : loan_release_default_payment_frequency($app);
    $allowed_methods = ['Cash', 'Check', 'Bank Transfer', 'GCash'];
    if (!in_array($disbursement_method, $allowed_methods, true)) {
        $disbursement_method = loan_release_default_disbursement_method($app);
    }
    $allowed_frequencies = ['Daily', 'Weekly', 'Bi-Weekly', 'Monthly'];
    if (!in_array($payment_frequency, $allowed_frequencies, true)) {
        $payment_frequency = loan_release_default_payment_frequency($app);
    }
    $disbursement_ref = $disbursement_ref !== ''
        ? $disbursement_ref
        : loan_release_generate_reference($application_id, $release_date);

    $principal = $approved_amount;
    $interest_rate = (float) $app['interest_rate'];
    $loan_term = (int) $app['loan_term_months'];
    $interest_type = $app['interest_type'] ?? 'Declining Balance';
    if ($interest_type === 'Diminishing') $interest_type = 'Declining Balance';
    if ($interest_type === 'Fixed') $interest_type = 'Flat';

    // Calculate interest based on type
    // Note: interest_rate in Microfin is stored and displayed as % PER MONTH.
    $monthly_rate = $interest_rate / 100;

    if ($interest_type === 'Flat' || $monthly_rate == 0) {
        $interest_amount = $principal * ($interest_rate / 100) * $loan_term;
        $monthly_payment = ($principal + $interest_amount) / $loan_term;
    } else {
        // Declining Balance
        $monthly_payment = $principal * ($monthly_rate * pow(1 + $monthly_rate, $loan_term)) / (pow(1 + $monthly_rate, $loan_term) - 1);
        $interest_amount = ($monthly_payment * $loan_term) - $principal;
    }

    $total_loan_amount = $principal + $interest_amount;

    // Fees
    $processing_fee = $principal * ((float) ($app['processing_fee_percentage'] ?? 0) / 100);
    $service_charge = (float) ($app['service_charge'] ?? 0);
    $doc_stamp = (float) ($app['documentary_stamp'] ?? 0);
    $insurance_fee = $principal * ((float) ($app['insurance_fee_percentage'] ?? 0) / 100);
    $total_deductions = $processing_fee + $service_charge + $doc_stamp + $insurance_fee;
    $net_proceeds = $principal - $total_deductions;

    // Payment frequency
    $freq_months = match ($payment_frequency) {
        'Weekly' => 0.25,
        'Bi-Weekly' => 0.5,
        'Daily' => 1 / 30,
        default => 1,  // Monthly
    };

    $number_of_payments = match ($payment_frequency) {
        'Weekly' => $loan_term * 4,
        'Bi-Weekly' => $loan_term * 2,
        'Daily' => $loan_term * 30,
        default => $loan_term,
    };

    // Dates
    $release_dt = new DateTime($release_date);
    $first_payment_dt = clone $release_dt;
    $first_payment_dt->modify('+1 month');
    $maturity_dt = clone $release_dt;
    $maturity_dt->modify("+$loan_term months");

    $first_payment_date = $first_payment_dt->format('Y-m-d');
    $maturity_date = $maturity_dt->format('Y-m-d');

    // Generate unique loan number
    function generateLoanNumber(PDO $pdo): string
    {
        for ($i = 0; $i < 20; $i++) {
            $candidate = 'LN-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
            $check = $pdo->prepare('SELECT 1 FROM loans WHERE loan_number = ? LIMIT 1');
            $check->execute([$candidate]);
            if (!$check->fetchColumn())
                return $candidate;
        }
        return 'LN-' . date('YmdHis') . '-' . rand(1000, 9999);
    }

    try {
        $pdo->beginTransaction();

        $loan_number = generateLoanNumber($pdo);

        $pdo->prepare("
            INSERT INTO loans (
                loan_number, application_id, client_id, tenant_id, product_id,
                principal_amount, interest_amount, total_loan_amount,
                processing_fee, service_charge, documentary_stamp, insurance_fee,
                total_deductions, net_proceeds, interest_rate, loan_term_months,
                monthly_amortization, payment_frequency, number_of_payments,
                release_date, first_payment_date, maturity_date,
                remaining_balance, outstanding_principal, outstanding_interest,
                loan_status, released_by, disbursement_method, disbursement_reference, notes
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                'Active', ?, ?, ?, ?
            )
        ")->execute([
                    $loan_number,
                    $application_id,
                    (int) $app['client_id'],
                    $tenant_id,
                    (int) $app['product_id'],
                    $principal,
                    round($interest_amount, 2),
                    round($total_loan_amount, 2),
                    round($processing_fee, 2),
                    round($service_charge, 2),
                    round($doc_stamp, 2),
                    round($insurance_fee, 2),
                    round($total_deductions, 2),
                    round($net_proceeds, 2),
                    $interest_rate,
                    $loan_term,
                    round($monthly_payment, 2),
                    $payment_frequency,
                    $number_of_payments,
                    $release_date,
                    $first_payment_date,
                    $maturity_date,
                    round($total_loan_amount, 2),
                    round($principal, 2),
                    round($interest_amount, 2),
                    $employee_id,
                    $disbursement_method,
                    $disbursement_ref ?: null,
                    $notes ?: null
                ]);

        $loan_id = (int) $pdo->lastInsertId();

        // Build amortization schedule
        $balance = $principal;
        $sched_insert = $pdo->prepare("
            INSERT INTO amortization_schedule
            (loan_id, tenant_id, payment_number, due_date, beginning_balance,
             principal_amount, interest_amount, total_payment, ending_balance)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        for ($n = 1; $n <= $loan_term; $n++) {
            $due_dt = clone $release_dt;
            $due_dt->modify("+{$n} months");

            if ($interest_type === 'Flat') {
                $int_portion = $interest_amount / $loan_term;
                $prin_portion = $principal / $loan_term;
            } else {
                $int_portion = $balance * $monthly_rate;
                $prin_portion = $monthly_payment - $int_portion;
            }

            if ($n === $loan_term) {
                $prin_portion = $balance; // last payment adjusts remainder
            }

            $ending = max(0, $balance - $prin_portion);

            $sched_insert->execute([
                $loan_id,
                $tenant_id,
                $n,
                $due_dt->format('Y-m-d'),
                round($balance, 2),
                round($prin_portion, 2),
                round($int_portion, 2),
                round($prin_portion + $int_portion, 2),
                round($ending, 2)
            ]);

            $balance = $ending;
        }



        // Update application to reflect loan released
        $pdo->prepare("UPDATE loan_applications SET updated_at = NOW() WHERE application_id = ?")
            ->execute([$application_id]);

        mf_sync_client_credit_profile($pdo, $tenant_id, (int) $app['client_id']);

        // Audit
        $pdo->prepare("INSERT INTO audit_logs (user_id, tenant_id, action_type, entity_type, entity_id, description) VALUES (?, ?, 'LOAN_RELEASED', 'loan', ?, ?)")
            ->execute([$session_user_id, $tenant_id, $loan_id, "Loan $loan_number released for application #$application_id. Net proceeds: $net_proceeds"]);

        $pdo->commit();

        echo json_encode([
            'status' => 'success',
            'message' => "Loan $loan_number successfully released.",
            'loan_id' => $loan_id,
            'loan_number' => $loan_number,
        ]);

    } catch (Throwable $e) {
        if ($pdo->inTransaction())
            $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
