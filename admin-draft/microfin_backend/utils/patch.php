<?php
$f = 'c:\xampp\htdocs\admin-draft-withmobile\admin-draft\microfin_platform\backend\api_payments.php';
$c = file_get_contents($f);

$old_query = <<<'EOD'
    $stmt = $pdo->prepare("
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
        ORDER BY p.created_at DESC
        LIMIT 500
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Today's total
    $today_stmt = $pdo->prepare("SELECT COALESCE(SUM(payment_amount), 0) FROM payments WHERE tenant_id = ? AND DATE(payment_date) = CURDATE() AND payment_status != 'Cancelled'");
    $today_stmt->execute([$tenant_id]);
    $todays_total = (float) $today_stmt->fetchColumn();
EOD;

$new_query = <<<'EOD'
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
EOD;

$c = str_replace(str_replace("\r", "", $old_query), str_replace("\r", "", $new_query), str_replace("\r", "", $c));
file_put_contents($f, $c);
echo "Replaced";
?>
