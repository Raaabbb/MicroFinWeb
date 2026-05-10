<?php
$f = 'c:\xampp\htdocs\admin-draft-withmobile\admin-draft\microfin_platform\backend\api_dashboard.php';
$c = file_get_contents($f);

// 1. Today's collections
$old1 = '    if (has_perm(\'PROCESS_PAYMENTS\')) {
        $s = $pdo->prepare("SELECT COALESCE(SUM(payment_amount), 0) FROM payments WHERE tenant_id = ? AND DATE(payment_date) = CURDATE() AND payment_status != \'Cancelled\'");
        $s->execute([$tenant_id]);
        $stats[\'todays_collections\'] = (float) $s->fetchColumn();
    }';

$new1 = '    if (has_perm(\'PROCESS_PAYMENTS\')) {
        $s = $pdo->prepare("SELECT 
            (SELECT COALESCE(SUM(payment_amount), 0) FROM payments WHERE tenant_id = ? AND DATE(payment_date) = CURDATE() AND payment_status != \'Cancelled\') +
            (SELECT COALESCE(SUM(amount), 0) FROM payment_transactions WHERE tenant_id = ? AND DATE(payment_date) = CURDATE() AND status != \'Cancelled\')");
        $s->execute([$tenant_id, $tenant_id]);
        $stats[\'todays_collections\'] = (float) $s->fetchColumn();
    }';

$c = str_replace(str_replace("\r", "", $old1), str_replace("\r", "", $new1), str_replace("\r", "", $c));


// 2. Collections by day (for chart)
$old2 = '    $s = $pdo->prepare("
        SELECT DATE(payment_date) as day, SUM(payment_amount) as total
        FROM payments
        WHERE tenant_id = ? AND payment_date >= ? AND payment_status != \'Cancelled\'
        GROUP BY DATE(payment_date)
        ORDER BY day ASC
    ");';

$new2 = '    $s = $pdo->prepare("
        SELECT day, SUM(total) as total FROM (
            SELECT DATE(payment_date) as day, SUM(payment_amount) as total
            FROM payments
            WHERE tenant_id = ? AND payment_date >= ? AND payment_status != \'Cancelled\'
            GROUP BY DATE(payment_date)
            UNION ALL
            SELECT DATE(payment_date) as day, SUM(amount) as total
            FROM payment_transactions
            WHERE tenant_id = ? AND payment_date >= ? AND status != \'Cancelled\'
            GROUP BY DATE(payment_date)
        ) combined_collections
        GROUP BY day
        ORDER BY day ASC
    ");';

$c = str_replace(str_replace("\r", "", $old2), str_replace("\r", "", $new2), str_replace("\r", "", $c));


// 3. Summary (total collections within period)
$old3 = '    $s = $pdo->prepare("SELECT COALESCE(SUM(payment_amount), 0) FROM payments WHERE tenant_id = ? AND payment_date >= ? AND payment_status != \'Cancelled\'");
    $s->execute([$tenant_id, $date_from]);
    $data[\'total_collections\'] = (float) $s->fetchColumn();';

$new3 = '    $s = $pdo->prepare("SELECT 
        (SELECT COALESCE(SUM(payment_amount), 0) FROM payments WHERE tenant_id = ? AND payment_date >= ? AND payment_status != \'Cancelled\') +
        (SELECT COALESCE(SUM(amount), 0) FROM payment_transactions WHERE tenant_id = ? AND payment_date >= ? AND status != \'Cancelled\')");
    $s->execute([$tenant_id, $date_from, $tenant_id, $date_from]);
    $data[\'total_collections\'] = (float) $s->fetchColumn();';

$c = str_replace(str_replace("\r", "", $old3), str_replace("\r", "", $new3), str_replace("\r", "", $c));


// Update Line 110 execute for the chart query which now takes 4 params instead of 2
$c = str_replace('$s->execute([$tenant_id, $date_from]);'."\n".'    $data[\'collections_by_day\']', 
                 '$s->execute([$tenant_id, $date_from, $tenant_id, $date_from]);'."\n".'    $data[\'collections_by_day\']', $c);

file_put_contents($f, $c);
echo "Replaced";
?>
