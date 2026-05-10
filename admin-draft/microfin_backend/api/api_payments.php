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

$tenant_id       = (string) ($_SESSION['tenant_id'] ?? '');
$session_user_id = (int)    ($_SESSION['user_id']   ?? 0);

if ($tenant_id === '') {
    echo json_encode(['status' => 'error', 'message' => 'Missing tenant context.']);
    exit;
}

$perm_stmt = $pdo->prepare('
    SELECT p.permission_code 
    FROM role_permissions rp 
    JOIN permissions p ON rp.permission_id = p.permission_id 
    JOIN users u ON u.role_id = rp.role_id
    WHERE u.user_id = ?
');
$perm_stmt->execute([$session_user_id]);
$permissions = $perm_stmt->fetchAll(PDO::FETCH_COLUMN);
function has_perm($code) { global $permissions; return in_array($code, $permissions); }

$action = strtolower(trim((string) ($_GET['action'] ?? $_POST['action'] ?? '')));
$method = $_SERVER['REQUEST_METHOD'];

// ─── GET: list payments ───────────────────────────────────────────────────────
if ($method === 'GET' && ($action === 'list' || $action === '')) {
    if (!has_perm('PROCESS_PAYMENTS')) {
        echo json_encode(['status' => 'error', 'message' => 'Permission denied.']);
        exit;
    }

    $date_filter = trim((string) ($_GET['date'] ?? ''));
    $params = [$tenant_id];
    $where_extra = '';

    if ($date_filter !== '') {
        $where_extra = ' AND DATE(p.payment_date) = ?';
        $params[] = $date_filter;
    }

    $where_extra_t = str_replace('p.payment_date', 't.payment_date', $where_extra);
    $params = array_merge($params, $params);

    $stmt = $pdo->prepare("
        SELECT * FROM (
            SELECT
                p.payment_id, p.payment_reference, p.payment_date, p.payment_amount,
                p.principal_paid, p.interest_paid, p.penalty_paid, p.advance_payment,
                p.payment_method, p.payment_status, p.official_receipt_number,
                p.payment_reference_number, p.remarks, p.created_at,
                l.loan_number, l.loan_status, l.remaining_balance,
                c.first_name, c.last_name, c.contact_number
            FROM payments p
            JOIN loans l ON p.loan_id = l.loan_id
            JOIN clients c ON p.client_id = c.client_id
            WHERE p.tenant_id = ? $where_extra

            UNION ALL

            SELECT
                t.transaction_id AS payment_id, t.transaction_ref AS payment_reference, t.payment_date, t.amount AS payment_amount,
                0 AS principal_paid, 0 AS interest_paid, 0 AS penalty_paid, 0 AS advance_payment,
                t.payment_method, t.status AS payment_status, '' AS official_receipt_number,
                t.source_id AS payment_reference_number, '' AS remarks, t.created_at,
                l.loan_number, l.loan_status, l.remaining_balance,
                c.first_name, c.last_name, c.contact_number
            FROM payment_transactions t
            JOIN loans l ON t.loan_id = l.loan_id
            JOIN clients c ON t.client_id = c.client_id
            WHERE t.tenant_id = ? $where_extra_t
        ) AS combined
        ORDER BY created_at DESC
        LIMIT 500
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Today's total
    $today_stmt = $pdo->prepare("
        SELECT 
            (SELECT COALESCE(SUM(payment_amount), 0) FROM payments WHERE tenant_id = ? AND DATE(payment_date) = CURDATE() AND payment_status != 'Cancelled') +
            (SELECT COALESCE(SUM(amount), 0) FROM payment_transactions WHERE tenant_id = ? AND DATE(payment_date) = CURDATE() AND status != 'Cancelled')
    ");
    $today_stmt->execute([$tenant_id, $tenant_id]);
    $todays_total = (float) $today_stmt->fetchColumn();

    echo json_encode(['status' => 'success', 'data' => $rows, 'todays_total' => $todays_total]);
    exit;
}

// ─── GET: loans eligible for payment (active, non fully paid) ─────────────────
if ($method === 'GET' && $action === 'active_loans') {
    if (!has_perm('PROCESS_PAYMENTS')) {
        echo json_encode(['status' => 'error', 'message' => 'Permission denied.']);
        exit;
    }

    $client_id = (int) ($_GET['client_id'] ?? 0);
    $params = [$tenant_id];
    $where_extra = '';
    if ($client_id > 0) {
        $where_extra = ' AND l.client_id = ?';
        $params[] = $client_id;
    }

    $stmt = $pdo->prepare("
        SELECT l.loan_id, l.loan_number, l.remaining_balance, l.monthly_amortization,
               l.next_payment_due, l.loan_status, l.outstanding_penalty,
               c.first_name, c.last_name
        FROM loans l
        JOIN clients c ON l.client_id = c.client_id
        WHERE l.tenant_id = ? AND l.loan_status IN ('Active', 'Overdue') $where_extra
        ORDER BY l.next_payment_due ASC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'data' => $rows]);
    exit;
}

// ─── POST: post a payment ──────────────────────────────────────────────────────
if ($method === 'POST' && ($action === 'post' || $action === '')) {
    if (!has_perm('PROCESS_PAYMENTS')) {
        echo json_encode(['status' => 'error', 'message' => 'Permission denied.']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    if (!is_array($payload)) $payload = $_POST;

    $loan_id            = (int)    ($payload['loan_id']            ?? 0);
    $payment_amount     = (float)  ($payload['payment_amount']     ?? 0);
    $payment_method     = trim((string) ($payload['payment_method'] ?? 'Cash'));
    $payment_date       = trim((string) ($payload['payment_date']   ?? date('Y-m-d')));
    $or_number          = trim((string) ($payload['or_number']      ?? ''));
    $payment_ref_number = trim((string) ($payload['payment_ref_number'] ?? ''));
    $bank_name          = trim((string) ($payload['bank_name']      ?? ''));
    $remarks            = trim((string) ($payload['remarks']        ?? ''));

    if ($loan_id <= 0 || $payment_amount <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Loan and payment amount are required.']);
        exit;
    }

    // Fetch loan
    $loan_stmt = $pdo->prepare("
        SELECT l.*, lp.interest_rate, lp.early_settlement_fee_type, lp.early_settlement_fee_value, lp.grace_period_days
        FROM loans l
        JOIN loan_products lp ON l.product_id = lp.product_id
        WHERE l.loan_id = ? AND l.tenant_id = ? AND l.loan_status IN ('Active', 'Overdue')
        LIMIT 1
    ");
    $loan_stmt->execute([$loan_id, $tenant_id]);
    $loan = $loan_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$loan) {
        echo json_encode(['status' => 'error', 'message' => 'Active loan not found.']);
        exit;
    }

    // Get employee_id
    $emp_stmt = $pdo->prepare('SELECT employee_id FROM employees WHERE user_id = ? AND tenant_id = ? LIMIT 1');
    $emp_stmt->execute([$session_user_id, $tenant_id]);
    $employee_id = $emp_stmt->fetchColumn();
    if (!$employee_id) {
        echo json_encode(['status' => 'error', 'message' => 'Only employees can post payments.']);
        exit;
    }
    $employee_id = (int) $employee_id;

    // Find the earliest pending/overdue amortization entry
    $sched_stmt = $pdo->prepare("
        SELECT * FROM amortization_schedule
        WHERE loan_id = ? AND payment_status IN ('Pending', 'Overdue', 'Partially Paid')
        ORDER BY payment_number ASC
        LIMIT 1
    ");
    $sched_stmt->execute([$loan_id]);
    $sched_entry = $sched_stmt->fetch(PDO::FETCH_ASSOC);

    // Compute penalty if overdue
    $penalty = (float) ($loan['outstanding_penalty'] ?? 0);
    
    // Allocate payment: penalty first, then interest, then principal
    $payment_left = $payment_amount;
    $penalty_paid = min($payment_left, $penalty);
    $payment_left -= $penalty_paid;

    // Expected monthly interest (interest_rate is already per month)
    $monthly_rate  = (float) $loan['interest_rate'] / 100;
    $outstanding_principal = (float) $loan['outstanding_principal'];
    $interest_portion = $sched_entry ? (float) $sched_entry['interest_amount'] : ($outstanding_principal * $monthly_rate);
    
    $interest_paid  = min($payment_left, $interest_portion);
    $payment_left  -= $interest_paid;
    $principal_paid = min($payment_left, $outstanding_principal);
    $advance_payment = max(0, $payment_left - $principal_paid);

    // Generate payment reference
    $payment_ref = 'PAY-' . date('YmdHis') . '-' . rand(1000, 9999);

    $new_remaining_balance     = max(0, (float)$loan['remaining_balance']    - $payment_amount);
    $new_outstanding_principal = max(0, $outstanding_principal               - $principal_paid);
    $new_outstanding_interest  = max(0, (float)$loan['outstanding_interest'] - $interest_paid);
    $new_outstanding_penalty   = max(0, $penalty                              - $penalty_paid);
    $new_total_paid            = (float)$loan['total_paid'] + $payment_amount;
    $new_principal_paid        = (float)$loan['principal_paid'] + $principal_paid;
    $new_interest_paid         = (float)$loan['interest_paid']  + $interest_paid;
    $new_penalty_paid_total    = (float)$loan['penalty_paid']   + $penalty_paid;

    $new_loan_status = ($new_remaining_balance <= 0.01) ? 'Fully Paid' : $loan['loan_status'];

    // Next payment due
    $next_payment_due = $loan['next_payment_due'];
    if ($sched_entry && (float)$sched_entry['total_payment'] <= ($payment_amount + 0.01)) {
        // Full installment paid – advance next payment
        $next_dt = new DateTime($loan['next_payment_due'] ?? date('Y-m-d'));
        $next_dt->modify('+1 month');
        $next_payment_due = $next_dt->format('Y-m-d');
    }

    try {
        $pdo->beginTransaction();

        // Insert payment record
        $pdo->prepare("
            INSERT INTO payments (
                payment_reference, loan_id, client_id, tenant_id,
                payment_date, payment_amount, principal_paid, interest_paid,
                penalty_paid, advance_payment, payment_method, payment_reference_number,
                bank_name, official_receipt_number,
                received_by, payment_status, remarks
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Posted', ?)
        ")->execute([
            $payment_ref,
            $loan_id,
            (int) $loan['client_id'],
            $tenant_id,
            $payment_date,
            $payment_amount,
            round($principal_paid, 2),
            round($interest_paid, 2),
            round($penalty_paid, 2),
            round($advance_payment, 2),
            $payment_method,
            $payment_ref_number ?: null,
            $bank_name ?: null,
            $or_number ?: null,
            $employee_id,
            $remarks ?: null
        ]);

        $payment_id = (int) $pdo->lastInsertId();

        // Update loan balances
        $pdo->prepare("
            UPDATE loans SET
                remaining_balance = ?, outstanding_principal = ?, outstanding_interest = ?,
                outstanding_penalty = ?, total_paid = ?, principal_paid = ?,
                interest_paid = ?, penalty_paid = ?,
                last_payment_date = ?, next_payment_due = ?,
                loan_status = ?, updated_at = NOW()
            WHERE loan_id = ? AND tenant_id = ?
        ")->execute([
            round($new_remaining_balance, 2),
            round($new_outstanding_principal, 2),
            round($new_outstanding_interest, 2),
            round($new_outstanding_penalty, 2),
            round($new_total_paid, 2),
            round($new_principal_paid, 2),
            round($new_interest_paid, 2),
            round($new_penalty_paid_total, 2),
            $payment_date,
            $next_payment_due,
            $new_loan_status,
            $loan_id,
            $tenant_id
        ]);

        // Update amortization_schedule entry
        if ($sched_entry) {
            $sched_status = ($new_remaining_balance <= 0.01) ? 'Paid' : (
                ((float)$sched_entry['total_payment'] <= ($payment_amount + 0.01)) ? 'Paid' : 'Partially Paid'
            );
            $pdo->prepare("
                UPDATE amortization_schedule
                SET payment_status = ?, amount_paid = ?, payment_date = ?
                WHERE schedule_id = ?
            ")->execute([
                $sched_status,
                $payment_amount,
                $payment_date,
                (int) $sched_entry['schedule_id']
            ]);
        }

        mf_sync_client_credit_profile($pdo, $tenant_id, (int) $loan['client_id']);

        // Audit
        $pdo->prepare("INSERT INTO audit_logs (user_id, tenant_id, action_type, entity_type, entity_id, description) VALUES (?, ?, 'PAYMENT_POSTED', 'payment', ?, ?)")
            ->execute([$session_user_id, $tenant_id, $payment_id, "Payment of ₱{$payment_amount} posted for loan {$loan['loan_number']}"]);

        $pdo->commit();

        echo json_encode([
            'status'             => 'success',
            'message'            => "Payment of ₱" . number_format($payment_amount, 2) . " posted successfully.",
            'payment_id'         => $payment_id,
            'payment_reference'  => $payment_ref,
            'new_balance'        => $new_remaining_balance,
            'loan_status'        => $new_loan_status,
        ]);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
