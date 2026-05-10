<?php
/**
 * Lazy Billing Resolver
 * Simulates a cron job by checking and processing pending tenant subscription 
 * charges when an admin accesses the dashboard.
 *
 * Uses next_billing_date to process tenant renewals when an admin session is active.
 */
require_once __DIR__ . '/billing_notifications.php';

function resolve_billing_next_due_date(string $dateString): string {
    $source = DateTimeImmutable::createFromFormat('Y-m-d', $dateString) ?: new DateTimeImmutable($dateString);
    return $source->add(new DateInterval('P30D'))->format('Y-m-d');
}

function resolve_tenant_billing($pdo) {
    if (!$pdo) return;

    $today = date('Y-m-d');

    // 1. Find all active tenants with an MRR > 0
    $stmt = $pdo->prepare("
        SELECT t.tenant_id, t.plan_tier, t.mrr
        FROM tenants t
        WHERE t.deleted_at IS NULL 
          AND t.status = 'Active' 
          AND t.mrr > 0
    ");
    $stmt->execute();
    $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($tenants)) return;

    foreach ($tenants as $t) {
        $tenant_id = $t['tenant_id'];

        try {
            $pdo->beginTransaction();
            $reminder_email_details = null;
            $receipt_email_details = null;

            // Lock the billing cursor so a due charge is only processed once at a time.
            $nbd_stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE tenant_id = ? AND setting_key = 'next_billing_date' LIMIT 1 FOR UPDATE");
            $nbd_stmt->execute([$tenant_id]);
            $next_billing_date = trim((string)$nbd_stmt->fetchColumn());

            if ($next_billing_date === '') {
                $pdo->commit();
                continue;
            }

            if ($next_billing_date > $today) {
                $days_until = (int)((strtotime($next_billing_date) - strtotime($today)) / 86400);
                if ($days_until >= 1 && $days_until <= 7) {
                    $reminder_stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE tenant_id = ? AND setting_key = 'billing_reminder_sent_for' LIMIT 1 FOR UPDATE");
                    $reminder_stmt->execute([$tenant_id]);
                    $last_reminder_for = trim((string)$reminder_stmt->fetchColumn());

                    if ($last_reminder_for !== $next_billing_date) {
                        mf_billing_set_setting($pdo, (string)$tenant_id, 'billing_reminder_sent_for', $next_billing_date);
                        $reminder_email_details = [
                            'plan_tier' => (string)($t['plan_tier'] ?? 'Subscription'),
                            'amount' => (float)$t['mrr'],
                            'due_date' => $next_billing_date,
                        ];
                    }
                }

                $pdo->commit();

                if (is_array($reminder_email_details)) {
                    $email_result = mf_billing_send_due_soon_email($pdo, (string)$tenant_id, $reminder_email_details);
                    if ($email_result !== 'Email sent successfully.') {
                        error_log('billing reminder email failed for tenant ' . $tenant_id . ': ' . $email_result);
                    }
                }

                continue;
            }

            $current_mrr = (float) $t['mrr'];
            $charge_timestamp = date('Y-m-d H:i:s');
            $charge_date = date('Y-m-d', strtotime($charge_timestamp));
            $new_next = resolve_billing_next_due_date($charge_date);
            $period_end = date('Y-m-d', strtotime($new_next . ' -1 day'));

            $pm_stmt = $pdo->prepare("SELECT card_brand, last_four_digits FROM tenant_billing_payment_methods WHERE tenant_id = ? AND is_default = 1 LIMIT 1");
            $pm_stmt->execute([$tenant_id]);
            $pm = $pm_stmt->fetch(PDO::FETCH_ASSOC);

            $payment_method_desc = 'System Auto-Billing';
            if ($pm) {
                $payment_method_desc = $pm['card_brand'] . ' ending in ' . $pm['last_four_digits'];
            }

            if ($current_mrr > 0) {
                $reference_suffix = strtoupper(substr(hash('sha256', $tenant_id . $charge_timestamp . random_int(1000, 9999)), 0, 10));
                $payment_reference = 'SUB-' . $reference_suffix;
                $invoice_number = 'INV-' . date('Ymd') . '-' . substr($reference_suffix, 0, 6);

                $ins_pay = $pdo->prepare("
                    INSERT INTO payments (tenant_id, payment_amount, payment_status, payment_date, payment_reference, payment_method)
                    VALUES (?, ?, 'Posted', ?, ?, ?)
                ");
                $ins_pay->execute([$tenant_id, $current_mrr, $charge_timestamp, $payment_reference, $payment_method_desc]);

                $inv_stmt = $pdo->prepare("
                    INSERT INTO tenant_billing_invoices
                    (tenant_id, invoice_number, amount, billing_period_start, billing_period_end, due_date, status, paid_at)
                    VALUES (?, ?, ?, ?, ?, ?, 'Paid', NOW())
                ");
                $inv_stmt->execute([
                    $tenant_id,
                    $invoice_number,
                    $current_mrr,
                    $charge_date,
                    $period_end,
                    $charge_date
                ]);

                $receipt_email_details = [
                    'plan_tier' => (string)($t['plan_tier'] ?? 'Subscription'),
                    'amount' => $current_mrr,
                    'payment_date' => $charge_timestamp,
                    'payment_reference' => $payment_reference,
                    'invoice_number' => $invoice_number,
                    'payment_method' => $payment_method_desc,
                    'period_start' => $charge_date,
                    'period_end' => $period_end,
                    'next_billing_date' => $new_next,
                ];
            }

            $upd = $pdo->prepare("
                INSERT INTO system_settings (tenant_id, setting_key, setting_value, setting_category, data_type) 
                VALUES (?, 'next_billing_date', ?, 'Billing', 'String') 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            $upd->execute([$tenant_id, $new_next]);

            try {
                $tenant_upd = $pdo->prepare("UPDATE tenants SET next_billing_date = ? WHERE tenant_id = ?");
                $tenant_upd->execute([$new_next, $tenant_id]);
            } catch (Throwable $ignore) {
            }

            $pdo->commit();

            if (is_array($receipt_email_details)) {
                $email_result = mf_billing_send_receipt_email($pdo, (string)$tenant_id, $receipt_email_details);
                if ($email_result !== 'Email sent successfully.') {
                    error_log('billing receipt email failed for tenant ' . $tenant_id . ': ' . $email_result);
                }
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Lazy billing resolver error for tenant {$tenant_id}: " . $e->getMessage());
        }
    }
}
