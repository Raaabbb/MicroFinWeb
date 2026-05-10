<?php
require_once "../../microfin_backend/auth/session_auth.php";
mf_start_backend_session();
require_once "../../microfin_backend/config/db_connect.php";
require_once "../../microfin_backend/billing/billing_notifications.php";
mf_require_tenant_session($pdo, [
    'response' => 'redirect',
    'redirect' => 'login.php',
    'append_tenant_slug' => true,
]);

function billing_add_30_days(string $dateString): string
{
    $source = DateTimeImmutable::createFromFormat('Y-m-d', $dateString) ?: new DateTimeImmutable($dateString);
    return $source->add(new DateInterval('P30D'))->format('Y-m-d');
}

function billing_column_exists(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $safe_column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE '{$safe_column}'");
    $stmt->execute();
    $cache[$key] = (bool)$stmt->fetch();

    return $cache[$key];
}

$tenant_id = $_SESSION['tenant_id'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

// Check current setup step — this page is step 5 (billing - final)
$step_stmt = $pdo->prepare('SELECT setup_current_step, setup_completed, plan_tier, max_clients, max_users, mrr FROM tenants WHERE tenant_id = ?');
$step_stmt->execute([$tenant_id]);
$step_data = $step_stmt->fetch(PDO::FETCH_ASSOC);
$current_step = (int)($step_data['setup_current_step'] ?? 0);

$plan_catalog = [
    'Starter' => [
        'price' => 4999,
        'max_clients' => 2000,
        'max_users' => 1000,
        'description' => 'Best for newly launched microfinance teams.',
        'inclusions' => [
            '2,000 Max Clients',
            '1,000 Max Staff Accounts',
            'Core Lending System',
            'Basic Financial Reports',
            'Email Notifications'
        ]
    ],
    'Enterprise' => [
        'price' => 14999,
        'max_clients' => -1,
        'max_users' => -1,
        'description' => 'Unlimited capacity for established enterprises.',
        'inclusions' => [
            'Unlimited Clients',
            'Unlimited Staff Accounts',
            'Full Enterprise Suite',
            'Advanced Custom Reports',
            'Priority Support 24/7',
            'Whitelabel Branding'
        ]
    ]
];

$plan_aliases = [
    'Professional' => 'Starter',
    'Pro' => 'Starter',
    'Elite' => 'Enterprise',
    'Unlimited' => 'Enterprise'
];

$legacy_plan_catalog = [
    'Growth' => [
        'price' => 9999,
        'max_clients' => 2500,
        'max_users' => 750,
        'description' => 'Legacy plan from an earlier application.'
    ]
];

$application_plan_tier = trim((string)($step_data['plan_tier'] ?? 'Starter'));
if (isset($plan_aliases[$application_plan_tier])) {
    $application_plan_tier = $plan_aliases[$application_plan_tier];
}

$application_plan_meta = $plan_catalog[$application_plan_tier] ?? ($legacy_plan_catalog[$application_plan_tier] ?? null);
$application_plan_is_available = isset($plan_catalog[$application_plan_tier]);
$current_plan_tier = $application_plan_is_available ? $application_plan_tier : 'Starter';
$selected_plan_tier = $current_plan_tier;
$monthly_price = (float)($plan_catalog[$selected_plan_tier]['price'] ?? ($step_data['mrr'] ?? 0));
$tenants_has_billing_cycle = billing_column_exists($pdo, 'tenants', 'billing_cycle');
$tenants_has_next_billing_date = billing_column_exists($pdo, 'tenants', 'next_billing_date');

if ($step_data && (bool)$step_data['setup_completed']) {
    header('Location: ../admin_panel/admin.php');
    exit;
}

if ($current_step !== 5) {
    if ($current_step > 0 && $current_step < 5) {
        // Billing is now the first onboarding gate after password reset.
        $pdo->prepare('UPDATE tenants SET setup_current_step = 5 WHERE tenant_id = ?')->execute([$tenant_id]);
        $current_step = 5;
    } else {
        $setup_routes = [0 => 'force_change_password.php'];
        if (isset($setup_routes[$current_step])) {
            header('Location: ' . $setup_routes[$current_step]);
        } else {
            header('Location: ../admin_panel/admin.php');
        }
        exit;
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $billing_company_name = trim($_POST['billing_company_name'] ?? '');
    $cardholder_name = $billing_company_name; // Repurpose for DB compatibility as "Institution Card"

    $card_number = trim($_POST['card_number'] ?? '');
    $exp_month = (int) ($_POST['exp_month'] ?? 0);
    $exp_year = (int) ($_POST['exp_year'] ?? 0);
    $card_brand = trim($_POST['card_brand'] ?? '');
    $selected_plan_tier = trim((string)($_POST['subscription_plan'] ?? $current_plan_tier));

    if (isset($plan_aliases[$selected_plan_tier])) {
        $selected_plan_tier = $plan_aliases[$selected_plan_tier];
    }
    if (!isset($plan_catalog[$selected_plan_tier])) {
        $selected_plan_tier = $current_plan_tier;
    }
    $selected_plan = $plan_catalog[$selected_plan_tier];
    $monthly_price = (float)$selected_plan['price'];

    // Validate
    $card_clean = preg_replace('/\s+/', '', $card_number);
    if ($billing_company_name === '' || $card_clean === '') {
        $error = 'Company name and card number are required.';
    } elseif (strlen($card_clean) < 13 || strlen($card_clean) > 19 || !ctype_digit($card_clean)) {
        $error = 'Please enter a valid card number (13-19 digits).';
    } elseif ($exp_month < 1 || $exp_month > 12) {
        $error = 'Please select a valid expiration month.';
    } elseif ($exp_year < (int) date('Y')) {
        $error = 'Expiration year cannot be in the past.';
    } else {
        $last_four = substr($card_clean, -4);

        // Encrypt the full card number with AES-256
        $encryption_key = defined('ENCRYPTION_KEY') ? constant('ENCRYPTION_KEY') : 'microfin_default_encryption_key_32b';
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($card_clean, 'aes-256-cbc', $encryption_key, 0, $iv);
        $encrypted_with_iv = base64_encode($iv . '::' . base64_decode($encrypted));

        // Auto-detect card brand
        if ($card_brand === '') {
            $first_digit = $card_clean[0];
            $first_two = substr($card_clean, 0, 2);
            if ($first_digit === '4') $card_brand = 'Visa';
            elseif (in_array($first_two, ['51','52','53','54','55'])) $card_brand = 'Mastercard';
            elseif (in_array($first_two, ['34','37'])) $card_brand = 'Amex';
            else $card_brand = 'Other';
        }

        $receipt_email_details = null;

        try {
            $pdo->beginTransaction();

            $pdo->prepare('UPDATE tenant_billing_payment_methods SET is_default = FALSE WHERE tenant_id = ?')
                ->execute([$tenant_id]);
            $stmt = $pdo->prepare('INSERT INTO tenant_billing_payment_methods (tenant_id, last_four_digits, card_brand, cardholder_name, exp_month, exp_year, card_number_encrypted, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, TRUE)');
            $stmt->execute([$tenant_id, $last_four, $card_brand, $cardholder_name, $exp_month, $exp_year, $encrypted_with_iv]);

            $billing_cycle_enum = 'Monthly';
            $charge_timestamp = date('Y-m-d H:i:s');
            $activation_date = date('Y-m-d', strtotime($charge_timestamp));
            $next_billing = billing_add_30_days($activation_date);
            $payment_method_desc = $card_brand . ' ending in ' . $last_four;

            $tenant_update_parts = [
                'plan_tier = ?',
                'mrr = ?',
                'max_clients = ?',
                'max_users = ?',
                'setup_current_step = 6',
                'setup_completed = TRUE'
            ];
            $tenant_update_params = [
                $selected_plan_tier,
                $monthly_price,
                (int)$selected_plan['max_clients'],
                (int)$selected_plan['max_users']
            ];
            if ($tenants_has_billing_cycle) {
                $tenant_update_parts[] = 'billing_cycle = ?';
                $tenant_update_params[] = $billing_cycle_enum;
            }
            if ($tenants_has_next_billing_date) {
                $tenant_update_parts[] = 'next_billing_date = ?';
                $tenant_update_params[] = $next_billing;
            }
            $tenant_update_params[] = $tenant_id;
            $upd = $pdo->prepare('UPDATE tenants SET ' . implode(', ', $tenant_update_parts) . ' WHERE tenant_id = ? AND setup_current_step = 5');
            $upd->execute($tenant_update_params);

            $upsert_setting = $pdo->prepare("
                INSERT INTO system_settings (tenant_id, setting_key, setting_value, setting_category, data_type)
                VALUES (?, ?, ?, 'Billing', 'String')
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_category = VALUES(setting_category), data_type = VALUES(data_type), updated_at = CURRENT_TIMESTAMP
            ");
            $upsert_setting->execute([$tenant_id, 'next_billing_date', $next_billing]);
            $upsert_setting->execute([$tenant_id, 'billing_company_name', $billing_company_name]);

            $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, description, tenant_id) VALUES (?, 'BILLING_SETUP', 'tenant', 'Payment method added during onboarding', ?)");
            $log->execute([$user_id, $tenant_id]);

            if ($selected_plan_tier !== $application_plan_tier) {
                $plan_log = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, description, tenant_id) VALUES (?, 'SUBSCRIPTION_UPDATE', 'tenant', ?, ?)");
                $plan_log->execute([$user_id, "Subscription plan changed during onboarding from {$application_plan_tier} to {$selected_plan_tier}", $tenant_id]);
            }

            if ($monthly_price > 0) {
                $charged_amount = $monthly_price;
                $reference_suffix = strtoupper(substr(hash('sha256', $tenant_id . $charge_timestamp . random_int(1000, 9999)), 0, 10));
                $invoice_number = 'INV-' . date('Ymd') . '-' . substr($reference_suffix, 0, 6);
                $payment_reference = 'SUB-' . $reference_suffix;
                $period_start = $activation_date;
                $period_end = date('Y-m-d', strtotime($next_billing . ' -1 day'));

                $inv_stmt = $pdo->prepare("
                    INSERT INTO tenant_billing_invoices 
                    (tenant_id, invoice_number, amount, billing_period_start, billing_period_end, due_date, status, paid_at) 
                    VALUES (?, ?, ?, ?, ?, ?, 'Paid', NOW())
                ");
                $inv_stmt->execute([
                    $tenant_id,
                    $invoice_number,
                    $charged_amount,
                    $period_start,
                    $period_end,
                    $period_start
                ]);

                $log2 = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, description, tenant_id) VALUES (?, 'BILLING_ACTIVATION', 'invoice', ?, ?)");
                $log2->execute([$user_id, "Generated initial activation billing records {$invoice_number} / {$payment_reference}. Amount: {$charged_amount}. Next billing date: {$next_billing}.", $tenant_id]);

                $receipt_email_details = [
                    'plan_tier' => $selected_plan_tier,
                    'amount' => $charged_amount,
                    'payment_date' => $charge_timestamp,
                    'payment_reference' => $payment_reference,
                    'invoice_number' => $invoice_number,
                    'payment_method' => $payment_method_desc,
                    'period_start' => $period_start,
                    'period_end' => $period_end,
                    'next_billing_date' => $next_billing,
                ];
            }

            $pdo->commit();

            if (is_array($receipt_email_details)) {
                $email_result = mf_billing_send_receipt_email($pdo, (string)$tenant_id, $receipt_email_details);
                if ($email_result !== 'Email sent successfully.') {
                    error_log('setup_billing receipt email failed for tenant ' . $tenant_id . ': ' . $email_result);
                }
            }

            $_SESSION['admin_flash'] = "Subscription activated on the {$selected_plan_tier} plan. You can now use your dashboard and finish your website and branding from the setup checklist.";
            header('Location: ../admin_panel/admin.php');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('setup_billing activation error for tenant ' . $tenant_id . ': ' . $e->getMessage());
            $error = 'Unable to activate the subscription right now. Please try again.';
        }
    }
}

// Fetch branding for styling
$brand_stmt = $pdo->prepare('SELECT theme_primary_color, theme_text_main, theme_text_muted, theme_bg_body, theme_bg_card, font_family FROM tenant_branding WHERE tenant_id = ?');
$brand_stmt->execute([$tenant_id]);
$brand = $brand_stmt->fetch(PDO::FETCH_ASSOC);
$accent = ($brand && $brand['theme_primary_color']) ? $brand['theme_primary_color'] : '#0284c7';
$t_text = ($brand && $brand['theme_text_main']) ? $brand['theme_text_main'] : '#0f172a';
$t_muted = ($brand && $brand['theme_text_muted']) ? $brand['theme_text_muted'] : '#64748b';
$t_bg = ($brand && $brand['theme_bg_body']) ? $brand['theme_bg_body'] : '#f1f5f9';
$t_card = ($brand && $brand['theme_bg_card']) ? $brand['theme_bg_card'] : '#ffffff';
$t_font = ($brand && $brand['font_family']) ? $brand['font_family'] : 'Inter';

$tenant_name = $_SESSION['tenant_name'] ?? 'Your Organization';
$current_year = (int) date('Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Billing - MicroFin</title>
    <link href="https://fonts.googleapis.com/css2?family=<?php echo urlencode($t_font); ?>:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: '<?php echo htmlspecialchars($t_font); ?>', sans-serif; background: <?php echo htmlspecialchars($t_bg); ?>; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .wizard-card { background: <?php echo htmlspecialchars($t_card); ?>; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 10px 15px -3px rgba(0,0,0,0.05); width: 100%; max-width: 1120px; overflow: hidden; }
        .wizard-header { background: linear-gradient(135deg, <?php echo htmlspecialchars($accent); ?>, #8b5cf6); padding: 32px; color: white; }
        .wizard-header h1 { font-size: 1.5rem; font-weight: 700; margin-bottom: 4px; }
        .wizard-header p { opacity: 0.85; font-size: 0.9rem; }
        .wizard-body { padding: 32px; }
        .wizard-layout { display: grid; grid-template-columns: minmax(0, 1.15fr) minmax(360px, 0.85fr); gap: 28px; align-items: start; }
        .plans-panel h2,
        .payment-panel h2 { font-size: 1.1rem; color: <?php echo htmlspecialchars($t_text); ?>; margin-bottom: 8px; }
        .section-copy { color: <?php echo htmlspecialchars($t_muted); ?>; font-size: 0.9rem; line-height: 1.6; margin-bottom: 18px; }
        .plan-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        .plan-option { position: relative; }
        .plan-option input { position: absolute; opacity: 0; pointer-events: none; }
        .plan-card { display: block; height: 100%; border: 1px solid #dbe7f3; border-radius: 18px; padding: 18px; background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%); cursor: pointer; transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease; position: relative; box-shadow: 0 6px 14px rgba(15, 23, 42, 0.04); }
        .plan-card:hover { transform: translateY(-2px); border-color: rgba(2,132,199,0.32); box-shadow: 0 14px 24px rgba(15, 23, 42, 0.06); }
        .plan-option input:focus + .plan-card { border-color: <?php echo htmlspecialchars($accent); ?>; box-shadow: 0 0 0 3px rgba(2,132,199,0.1); }
        .plan-option input:checked + .plan-card { border-color: <?php echo htmlspecialchars($accent); ?>; box-shadow: 0 16px 28px rgba(15, 23, 42, 0.06); background: linear-gradient(180deg, rgba(2,132,199,0.06) 0%, rgba(255,255,255,1) 100%); }
        .plan-card-current { border-color: rgba(245, 158, 11, 0.45); box-shadow: inset 0 0 0 1px rgba(245, 158, 11, 0.16); }
        .plan-card-header { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; margin-bottom: 14px; }
        .plan-name { font-size: 1.05rem; font-weight: 700; color: <?php echo htmlspecialchars($t_text); ?>; }
        .plan-description { margin-top: 6px; color: <?php echo htmlspecialchars($t_muted); ?>; font-size: 0.82rem; line-height: 1.5; }
        .plan-popular-badge { display: inline-flex; align-items: center; justify-content: center; padding: 6px 10px; border-radius: 999px; background: rgba(245, 158, 11, 0.14); color: #b45309; font-size: 0.72rem; font-weight: 700; white-space: nowrap; }
        .plan-current-badge { display: inline-flex; align-items: center; justify-content: center; padding: 6px 10px; border-radius: 999px; background: rgba(2,132,199,0.1); color: #0369a1; font-size: 0.72rem; font-weight: 700; white-space: nowrap; }
        .plan-price { display: flex; align-items: baseline; gap: 4px; color: <?php echo htmlspecialchars($t_text); ?>; margin-bottom: 14px; }
        .plan-price strong { font-size: 1.8rem; line-height: 1; }
        .plan-price span { font-size: 0.92rem; color: <?php echo htmlspecialchars($t_muted); ?>; }
        .plan-feature-list { display: grid; gap: 10px; }
        .plan-feature { display: flex; align-items: center; gap: 10px; color: #0f172a; font-size: 0.92rem; }
        .plan-feature .material-symbols-rounded { color: #16a34a; font-size: 20px; }
        .application-plan-banner { padding: 14px 16px; border-radius: 14px; border: 1px solid rgba(2,132,199,0.18); background: rgba(2,132,199,0.08); margin-bottom: 18px; }
        .application-plan-banner strong { display: block; color: <?php echo htmlspecialchars($t_text); ?>; margin-bottom: 4px; font-size: 0.96rem; }
        .application-plan-banner p { margin: 0; color: <?php echo htmlspecialchars($t_muted); ?>; font-size: 0.84rem; line-height: 1.55; }
        .selected-plan-summary { padding: 16px 18px; background: linear-gradient(135deg, rgba(2,132,199,0.08), rgba(14,165,233,0.14)); border: 1px solid rgba(2,132,199,0.18); border-radius: 14px; margin-bottom: 20px; }
        .selected-plan-summary strong { display: block; color: <?php echo htmlspecialchars($t_text); ?>; font-size: 1rem; margin-bottom: 4px; }
        .selected-plan-summary p { margin: 0; color: <?php echo htmlspecialchars($t_muted); ?>; font-size: 0.85rem; line-height: 1.55; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 500; margin-bottom: 8px; color: #475569; font-size: 0.9rem; }
        .form-control { width: 100%; padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 8px; font-family: inherit; font-size: 0.95rem; color: #0f172a; transition: border-color 0.2s; }
        .form-control:focus { outline: none; border-color: <?php echo htmlspecialchars($accent); ?>; box-shadow: 0 0 0 3px rgba(2,132,199,0.1); }
        .row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .card-preview { background: linear-gradient(135deg, #1e293b, #334155); border-radius: 12px; padding: 24px; color: white; margin-bottom: 24px; position: relative; overflow: hidden; }
        .card-preview::after { content: ''; position: absolute; top: -30px; right: -30px; width: 100px; height: 100px; background: rgba(255,255,255,0.05); border-radius: 50%; }
        .card-preview .card-number { font-size: 1.3rem; letter-spacing: 3px; font-weight: 600; margin: 20px 0 16px; font-family: monospace; }
        .card-preview .card-name { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; opacity: 0.8; }
        .card-preview .card-expiry { font-size: 0.85rem; opacity: 0.8; position: absolute; bottom: 24px; right: 24px; }
        .card-preview .card-brand-display { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; opacity: 0.6; }
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 12px 28px; border: none; border-radius: 10px; font-weight: 600; cursor: pointer; font-family: inherit; font-size: 0.95rem; transition: all 0.2s; }
        .btn-primary { background: <?php echo htmlspecialchars($accent); ?>; color: white; width: 100%; justify-content: center; }
        .btn-primary:hover { filter: brightness(0.9); }
        .error { color: #ef4444; background: #fef2f2; border: 1px solid #fecaca; padding: 10px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; }
        .security-note { display: flex; align-items: center; gap: 8px; padding: 12px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; margin-bottom: 24px; font-size: 0.85rem; color: #166534; }
        .confirm-backdrop { display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.62); z-index: 9999; padding: 24px; align-items: center; justify-content: center; }
        .confirm-backdrop.is-open { display: flex; }
        .confirm-modal { width: 100%; max-width: 460px; background: #ffffff; border-radius: 18px; padding: 28px; box-shadow: 0 24px 60px rgba(15, 23, 42, 0.28); border: 1px solid rgba(226,232,240,0.95); }
        .confirm-modal-badge { display: inline-flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 999px; background: rgba(2,132,199,0.08); color: #0369a1; font-size: 0.76rem; font-weight: 700; margin-bottom: 14px; }
        .confirm-modal h3 { color: <?php echo htmlspecialchars($t_text); ?>; font-size: 1.15rem; margin-bottom: 10px; }
        .confirm-modal p { color: <?php echo htmlspecialchars($t_muted); ?>; font-size: 0.9rem; line-height: 1.6; margin-bottom: 18px; }
        .confirm-modal-summary { padding: 14px 16px; border-radius: 14px; background: #f8fafc; border: 1px solid #e2e8f0; color: #334155; font-size: 0.86rem; line-height: 1.6; margin-bottom: 20px; }
        .confirm-modal-actions { display: flex; justify-content: flex-end; gap: 12px; flex-wrap: wrap; }
        .btn-outline-muted { background: #ffffff; color: #475569; border: 1px solid #cbd5e1; }
        .btn-outline-muted:hover { background: #f8fafc; border-color: #94a3b8; }
        small { color: #94a3b8; font-size: 0.8rem; }
        @media (max-width: 980px) {
            .wizard-layout { grid-template-columns: 1fr; }
            .plan-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 640px) {
            .wizard-body { padding: 24px; }
            .row-2 { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="wizard-card">
        <div class="wizard-header">
            <h1>Activate Your Subscription</h1>
            <p>Make your first payment for <?php echo htmlspecialchars($tenant_name); ?> to activate the subscription and enter the dashboard right away.</p>
        </div>
        <div class="wizard-body">
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="wizard-layout">
                    <section class="plans-panel">
                        <h2>Choose Your Subscription Plan</h2>
                        <p class="section-copy">Pick the plan that fits your current operations. Your selected subscription will be activated as soon as the payment method is authorized.</p>

                        <div class="application-plan-banner">
                            <strong>Current application plan: <?php echo htmlspecialchars($application_plan_tier); ?></strong>
                            <p>
                                <?php if ($application_plan_is_available): ?>
                                    This is the plan submitted during your application. You can keep it, or switch plans before activation and we will ask you to confirm the change.
                                <?php else: ?>
                                    This plan came from an older application and is no longer offered. Please choose one of the available plans below before activation.
                                <?php endif; ?>
                            </p>
                        </div>

                        <div class="plan-grid">
                            <?php foreach ($plan_catalog as $plan_name => $plan_meta): ?>
                                <?php
                                $plan_id = 'plan_' . strtolower(preg_replace('/[^a-z0-9]+/i', '_', $plan_name));
                                $clients_label = ((int)$plan_meta['max_clients'] < 0)
                                    ? 'Unlimited Clients'
                                    : number_format((int)$plan_meta['max_clients']) . ' Max Clients';
                                $users_label = ((int)$plan_meta['max_users'] < 0)
                                    ? 'Unlimited Users'
                                    : number_format((int)$plan_meta['max_users']) . ' Max Users';
                                $is_application_plan = $plan_name === $application_plan_tier;
                                ?>
                                <div class="plan-option">
                                    <input
                                        type="radio"
                                        name="subscription_plan"
                                        id="<?php echo htmlspecialchars($plan_id); ?>"
                                        value="<?php echo htmlspecialchars($plan_name); ?>"
                                        <?php echo $selected_plan_tier === $plan_name ? 'checked' : ''; ?>
                                    >
                                    <label class="plan-card <?php echo $is_application_plan ? 'plan-card-current' : ''; ?>" for="<?php echo htmlspecialchars($plan_id); ?>">
                                        <div class="plan-card-header">
                                            <div>
                                                <div class="plan-name"><?php echo htmlspecialchars($plan_name); ?></div>
                                                <p class="plan-description"><?php echo htmlspecialchars($plan_meta['description']); ?></p>
                                            </div>
                                            <?php if ($is_application_plan): ?>
                                                <span class="plan-current-badge">Current Application Plan</span>
                                            <?php elseif (!empty($plan_meta['popular'])): ?>
                                                <span class="plan-popular-badge">Most Popular</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="plan-price">
                                            <strong>₱<?php echo number_format((float)$plan_meta['price'], 0); ?></strong>
                                            <span>/mo</span>
                                        </div>
                                        <div class="plan-feature-list">
                                            <?php if (!empty($plan_meta['inclusions'])): ?>
                                                <?php foreach (array_slice($plan_meta['inclusions'], 0, 5) as $inc): ?>
                                                    <div class="plan-feature">
                                                        <span class="material-symbols-rounded">check_circle</span>
                                                        <span><?php echo htmlspecialchars($inc); ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="plan-feature">
                                                    <span class="material-symbols-rounded">check_circle</span>
                                                    <span><?php echo htmlspecialchars($clients_label); ?></span>
                                                </div>
                                                <div class="plan-feature">
                                                    <span class="material-symbols-rounded">check_circle</span>
                                                    <span><?php echo htmlspecialchars($users_label); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section class="payment-panel">
                        <h2>Company Billing Details</h2>
                        <p class="section-copy">Provide your organization's billing information and the authorized payment method for this subscription.</p>

                        <div class="selected-plan-summary" id="selected-plan-summary">
                            <strong id="selected-plan-name"><?php echo htmlspecialchars($selected_plan_tier); ?> Plan</strong>
                            <p id="selected-plan-description">Your selected plan includes the current monthly price and usage limits shown on the left.</p>
                        </div>

                        <div class="card-preview">
                            <div class="card-brand-display" id="preview-brand">VISA</div>
                            <div class="card-number" id="preview-number">&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;</div>
                            <div class="card-name" id="preview-name"><?php echo strtoupper(htmlspecialchars($tenant_name)); ?></div>
                            <div class="card-expiry" id="preview-expiry">MM/YY</div>
                        </div>

                        <div class="security-note">
                            <span class="material-symbols-rounded" style="font-size: 18px;">lock</span>
                            Your card details are encrypted with AES-256. We never store your CVC.
                        </div>

                        <div style="margin-bottom: 24px; padding-bottom: 24px; border-bottom: 1px solid #e2e8f0;">
                            <h3 style="font-size: 0.95rem; font-weight: 700; color: #1e293b; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                                <span class="material-symbols-rounded" style="font-size: 20px; color: <?php echo htmlspecialchars($accent); ?>;">business</span>
                                Company Section
                            </h3>
                            
                            <div class="form-group">
                                <label>Company / Billing Entity Name</label>
                                <input type="text" class="form-control" name="billing_company_name" id="billing_company_name" value="<?php echo htmlspecialchars($tenant_name); ?>" placeholder="ABC Lending Corporation" required oninput="updateCardPreview();">
                            </div>
                        </div>

                        <h3 style="font-size: 0.95rem; font-weight: 700; color: #1e293b; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                            <span class="material-symbols-rounded" style="font-size: 20px; color: <?php echo htmlspecialchars($accent); ?>;">credit_card</span>
                            Payment Instrument
                        </h3>

                        <div class="form-group">
                            <label>Company Card Number</label>
                            <input type="text" class="form-control" name="card_number" id="card_number" placeholder="4242 4242 4242 4242" maxlength="24" required oninput="formatCardNumber(this); updateCardPreview();">
                        </div>

                        <input type="hidden" name="card_brand" id="card_brand" value="">

                        <div class="row-2" style="grid-template-columns: 1fr 1fr 1fr;">
                            <div class="form-group">
                                <label>Expiration Month</label>
                                <select class="form-control" name="exp_month" id="exp_month" required onchange="updateCardPreview();">
                                    <option value="">Month</option>
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?php echo $m; ?>"><?php echo str_pad($m, 2, '0', STR_PAD_LEFT) . ' - ' . date('F', mktime(0, 0, 0, $m, 1)); ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Expiration Year</label>
                                <select class="form-control" name="exp_year" id="exp_year" required onchange="updateCardPreview();">
                                    <option value="">Year</option>
                                    <?php for ($y = $current_year; $y <= $current_year + 15; $y++): ?>
                                    <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>CVV / CVC</label>
                                <input type="password" class="form-control" name="cvv" id="cvv" placeholder="123" maxlength="4" required style="letter-spacing: 2px;">
                            </div>
                        </div>

                        <div id="checkout-summary" style="display: none; padding: 14px; background: #e0f2fe; border: 1px solid #7dd3fc; border-radius: 8px; margin-top: 10px; margin-bottom: 24px;">
                            <h4 style="margin: 0 0 6px 0; color: #0369a1; font-size: 0.95rem;">&#128274; Checkout Summary</h4>
                            <p id="checkout-text" style="margin: 0; color: #0c4a6e; font-size: 0.85rem; line-height: 1.5;"></p>
                        </div>

                        <div class="form-group" style="padding: 12px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; margin-top: 4px;">
                            <label style="display: flex; align-items: flex-start; gap: 8px; margin: 0; cursor: pointer; font-size: 0.85rem; font-weight: 500; color: #166534;">
                                <input type="checkbox" name="agree_billing" id="agree_billing" required style="margin-top: 3px; accent-color: <?php echo htmlspecialchars($accent); ?>;">
            <span style="line-height: 1.4;">I authorize MicroFin to save this payment method, charge the selected monthly plan immediately, and continue recurring billing every 30 days starting from this activation payment. I agree to the <a href="#" id="open-billing-tos" style="color: #0369a1; text-decoration: underline; font-weight: 600;">Billing Terms &amp; No-Refund Policy</a>.</span>
                            </label>
                        </div>

                        <button type="button" id="btn-pay-submit" class="btn btn-primary" style="display:flex; align-items:center; gap:8px; width:100%; justify-content:center; margin-top:16px; font-size:1rem; padding:12px;">
                            <span class="material-symbols-rounded" style="font-size:1.2rem;">lock</span> Authorize &amp; Activate Subscription
                        </button>
                        <input type="submit" id="real-submit" style="display:none;">
                    </section>
                </div>
            </form>
        </div>
    </div>

    <!-- Payment Processing Overlay -->
    <div id="payment-overlay" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.92); z-index:9998; align-items:center; justify-content:center; flex-direction:column;">
        <div style="background:#1e293b; border:1px solid rgba(147,197,253,0.2); border-radius:16px; padding:40px 48px; text-align:center; max-width:400px; width:90%; position:relative;">
            <div id="pay-spinner" style="width:52px; height:52px; border:4px solid rgba(147,197,253,0.2); border-top-color:<?php echo htmlspecialchars($accent); ?>; border-radius:50%; animation:spin 0.8s linear infinite; margin:0 auto 20px;"></div>
            <h3 id="pay-status-title" style="color:#f8fbff; font-size:1.1rem; margin:0 0 8px;">Authorizing Payment...</h3>
            <p id="pay-status-sub" style="color:#94a3b8; font-size:0.85rem; margin:0;">Please wait while we securely process your charge.</p>
            <div id="pay-steps" style="margin-top:20px; text-align:left; display:flex; flex-direction:column; gap:6px;">
                <div id="pstep-1" style="display:flex; align-items:center; gap:8px; color:#64748b; font-size:0.85rem; transition:color 0.3s;"><span style="font-size:16px;">&#9675;</span> Encrypting card details</div>
                <div id="pstep-2" style="display:flex; align-items:center; gap:8px; color:#64748b; font-size:0.85rem; transition:color 0.3s;"><span style="font-size:16px;">&#9675;</span> Validating payment method</div>
                <div id="pstep-3" style="display:flex; align-items:center; gap:8px; color:#64748b; font-size:0.85rem; transition:color 0.3s;"><span style="font-size:16px;">&#9675;</span> Processing initial charge</div>
                <div id="pstep-4" style="display:flex; align-items:center; gap:8px; color:#64748b; font-size:0.85rem; transition:color 0.3s;"><span style="font-size:16px;">&#9675;</span> Activating subscription</div>
            </div>
        </div>
    </div>
    <style>@keyframes spin { 100% { transform: rotate(360deg); } }</style>

    <!-- Billing ToS Modal -->
    <div id="billing-tos-backdrop" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:9999; overflow-y:auto; padding:40px 20px;">
        <div style="background:#fff; border-radius:14px; max-width:620px; margin:0 auto; padding:32px; color:#1e293b; line-height:1.7; box-shadow:0 20px 60px rgba(0,0,0,0.35);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
                <h2 style="margin:0; font-size:1.15rem;">Billing Terms &amp; Conditions</h2>
                <button type="button" id="close-billing-tos" style="background:none; border:none; cursor:pointer; font-size:1.5rem; color:#64748b; line-height:1;">&times;</button>
            </div>
            <p style="font-size:0.78rem; color:#64748b; margin-bottom:14px;">Effective Date: <?php echo date('F d, Y'); ?> &mdash; MicroFin Platform</p>
            <h3 style="color:#0369a1; font-size:0.87rem; margin:14px 0 5px;">1. Initial Activation Charge</h3>
            <p style="font-size:0.83rem;">You will be charged the full monthly amount for your selected plan today. Your subscription becomes active as soon as the payment is accepted.</p>
                                <h3 style="color:#0369a1; font-size:0.87rem; margin:14px 0 5px;">2. Recurring Monthly Billing</h3>
                                <p style="font-size:0.83rem;">Your subscription renews automatically 30 days after your activation payment, and every 30 days after each successful renewal. The full monthly fee is deducted from your payment method.</p>
            <h3 style="color:#b91c1c; font-size:0.87rem; margin:14px 0 5px;">3. No-Refund Policy</h3>
            <p style="font-size:0.83rem; background:#fef2f2; padding:10px 12px; border-radius:6px; border-left:3px solid #f87171;"><strong>All fees are strictly non-refundable.</strong> This includes the activation charge, monthly fees, and any fees incurred before cancellation. No exceptions are made for partial usage.</p>
            <div style="margin-top:22px; text-align:right;">
                <button type="button" id="close-billing-tos-btn" style="background:<?php echo htmlspecialchars($accent); ?>; color:#fff; border:none; border-radius:8px; padding:9px 22px; font-weight:600; cursor:pointer;">Got it &mdash; I Agree</button>
            </div>
        </div>
    </div>

    <div id="plan-change-backdrop" class="confirm-backdrop" aria-hidden="true">
        <div class="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="plan-change-title">
            <div class="confirm-modal-badge">
                <span class="material-symbols-rounded" style="font-size: 18px;">swap_horiz</span>
                Plan Change Confirmation
            </div>
            <h3 id="plan-change-title">Confirm your subscription change</h3>
            <p id="plan-change-copy">You are changing the plan you selected during your application.</p>
            <div class="confirm-modal-summary" id="plan-change-summary"></div>
            <div class="confirm-modal-actions">
                <button type="button" id="plan-change-cancel" class="btn btn-outline-muted">Keep Current Plan</button>
                <button type="button" id="plan-change-confirm" class="btn btn-primary" style="width:auto;">Yes, Change Plan</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const tosBtn = document.getElementById('open-billing-tos');
            const tosModal = document.getElementById('billing-tos-backdrop');
            const closeTos1 = document.getElementById('close-billing-tos');
            const closeTos2 = document.getElementById('close-billing-tos-btn');
            const payBtn = document.getElementById('btn-pay-submit');
            const realSubmit = document.getElementById('real-submit');
            const overlay = document.getElementById('payment-overlay');
            const form = document.querySelector('form');
            const planChangeBackdrop = document.getElementById('plan-change-backdrop');
            const planChangeCopy = document.getElementById('plan-change-copy');
            const planChangeSummary = document.getElementById('plan-change-summary');
            const planChangeCancel = document.getElementById('plan-change-cancel');
            const planChangeConfirm = document.getElementById('plan-change-confirm');
            const applicationPlanName = <?php echo json_encode($application_plan_tier, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
            const applicationPlanAvailable = <?php echo $application_plan_is_available ? 'true' : 'false'; ?>;
            
            if (tosBtn) tosBtn.addEventListener('click', e => { e.preventDefault(); tosModal.style.display = 'block'; });
            if (closeTos1) closeTos1.addEventListener('click', () => tosModal.style.display = 'none');
            if (closeTos2) closeTos2.addEventListener('click', () => tosModal.style.display = 'none');
            if (tosModal) tosModal.addEventListener('click', e => { if (e.target === tosModal) tosModal.style.display = 'none'; });

            function selectSubscriptionPlan(planName) {
                if (!planName) {
                    return false;
                }

                const planInput = document.querySelector(`input[name="subscription_plan"][value="${planName}"]`);
                if (!planInput) {
                    return false;
                }

                planInput.checked = true;
                if (typeof updateSelectedPlanSummary === 'function') {
                    updateSelectedPlanSummary();
                }
                if (typeof updateCheckoutSummary === 'function') {
                    updateCheckoutSummary();
                }

                return true;
            }

            function showPlanChangeModal(currentPlanName, selectedPlanName) {
                return new Promise((resolve) => {
                    if (!planChangeBackdrop || !planChangeCopy || !planChangeSummary || !planChangeCancel || !planChangeConfirm) {
                        resolve('change-plan');
                        return;
                    }

                    const close = (result) => {
                        planChangeBackdrop.classList.remove('is-open');
                        planChangeBackdrop.setAttribute('aria-hidden', 'true');
                        document.body.style.overflow = '';
                        planChangeCancel.removeEventListener('click', onCancel);
                        planChangeConfirm.removeEventListener('click', onConfirm);
                        planChangeBackdrop.removeEventListener('click', onBackdropClick);
                        document.removeEventListener('keydown', onEscape);
                        resolve(result);
                    };

                    const onCancel = () => close('keep-current');
                    const onConfirm = () => close('change-plan');
                    const onBackdropClick = (event) => {
                        if (event.target === planChangeBackdrop) {
                            close('dismiss');
                        }
                    };
                    const onEscape = (event) => {
                        if (event.key === 'Escape') {
                            close('dismiss');
                        }
                    };

                    planChangeCopy.textContent = `You originally applied for the ${currentPlanName} plan, and you are about to activate the ${selectedPlanName} plan instead.`;
                    planChangeSummary.innerHTML = `<strong>Current application plan:</strong> ${currentPlanName}<br><strong>New activation plan:</strong> ${selectedPlanName}`;
                    planChangeBackdrop.classList.add('is-open');
                    planChangeBackdrop.setAttribute('aria-hidden', 'false');
                    document.body.style.overflow = 'hidden';

                    planChangeCancel.addEventListener('click', onCancel);
                    planChangeConfirm.addEventListener('click', onConfirm);
                    planChangeBackdrop.addEventListener('click', onBackdropClick);
                    document.addEventListener('keydown', onEscape);
                });
            }

            if (payBtn) payBtn.addEventListener('click', async (e) => {
                if (!form.reportValidity()) return;
                e.preventDefault();

                const selectedPlanName = getSelectedPlanName();
                if (applicationPlanAvailable && applicationPlanName && selectedPlanName !== applicationPlanName) {
                    const planDecision = await showPlanChangeModal(applicationPlanName, selectedPlanName);
                    if (planDecision === 'keep-current') {
                        selectSubscriptionPlan(applicationPlanName);
                    } else if (planDecision !== 'change-plan') {
                        return;
                    }
                }

                overlay.style.display = 'flex';
                
                const steps = [
                    document.getElementById('pstep-1'),
                    document.getElementById('pstep-2'),
                    document.getElementById('pstep-3'),
                    document.getElementById('pstep-4')
                ];
                
                for(let i=0; i<steps.length; i++) {
                    await new Promise(r => setTimeout(r, 600 + Math.random()*400));
                    steps[i].style.color = '#34d399';
                    steps[i].innerHTML = '<span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;">check_circle</span>' + steps[i].innerHTML.substring(steps[i].innerHTML.indexOf('</span>') + 7);
                }
                await new Promise(r => setTimeout(r, 400));
                document.getElementById('pay-spinner').style.borderColor = '#10b981';
                document.getElementById('pay-status-title').textContent = 'Payment Successful';
                document.getElementById('pay-status-title').style.color = '#10b981';
                document.getElementById('pay-status-sub').textContent = 'Redirecting to your dashboard...';
                await new Promise(r => setTimeout(r, 800));
                realSubmit.click();
            });
        });

        function formatCardNumber(input) {
            let v = input.value.replace(/\D/g, '');
            let formatted = v.match(/.{1,4}/g)?.join(' ') || v;
            input.value = formatted;
        }

        function updateCardPreview() {
            const name = document.getElementById('billing_company_name').value.toUpperCase() || 'CARDHOLDER NAME';
            const number = document.getElementById('card_number').value.replace(/\D/g, '');
            const month = document.getElementById('exp_month').value;
            const year = document.getElementById('exp_year').value;
            
            document.getElementById('preview-name').textContent = name;

            // Format card number for display
            let display = '';
            for (let i = 0; i < 16; i++) {
                if (i > 0 && i % 4 === 0) display += ' ';
                display += i < number.length ? number[i] : '\u2022';
            }
            document.getElementById('preview-number').textContent = display;

            // Expiry
            const mm = month ? month.toString().padStart(2, '0') : 'MM';
            const yy = year ? year.toString().slice(-2) : 'YY';
            document.getElementById('preview-expiry').textContent = mm + '/' + yy;

            // Auto-detect brand
            let brand = 'CARD';
            if (number.length > 0) {
                const first = number[0];
                const firstTwo = number.substring(0, 2);
                if (first === '4') brand = 'VISA';
                else if (['51','52','53','54','55'].includes(firstTwo)) brand = 'MASTERCARD';
                else if (['34','37'].includes(firstTwo)) brand = 'AMEX';
                else if (firstTwo === '36' || firstTwo === '38') brand = 'DINERS';
            }
            document.getElementById('preview-brand').textContent = brand;
            document.getElementById('card_brand').value = brand;
        }

        const planCatalog = <?php echo json_encode($plan_catalog, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        const selectedPlanInputs = Array.from(document.querySelectorAll('input[name="subscription_plan"]'));
        const applicationPlanName = <?php echo json_encode($application_plan_tier, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        const applicationPlanAvailable = <?php echo $application_plan_is_available ? 'true' : 'false'; ?>;

        function getSelectedPlanName() {
            const selectedInput = selectedPlanInputs.find((input) => input.checked);
            return selectedInput ? selectedInput.value : <?php echo json_encode($selected_plan_tier); ?>;
        }

        function formatPlanLimit(limitValue, singularLabel, pluralLabel) {
            if (Number(limitValue) < 0) {
                return `Unlimited ${pluralLabel}`;
            }

            return `${Number(limitValue).toLocaleString('en-US')} ${Number(limitValue) === 1 ? singularLabel : pluralLabel}`;
        }

        function updateSelectedPlanSummary() {
            const selectedPlanName = getSelectedPlanName();
            const selectedPlan = planCatalog[selectedPlanName];
            const planNameEl = document.getElementById('selected-plan-name');
            const planDescriptionEl = document.getElementById('selected-plan-description');

            if (!selectedPlan || !planNameEl || !planDescriptionEl) {
                return;
            }

            const monthlyPrice = Number(selectedPlan.price || 0).toLocaleString('en-US', {
                style: 'currency',
                currency: 'PHP'
            });
            const clientsText = formatPlanLimit(selectedPlan.max_clients, 'Client', 'Clients');
            const usersText = formatPlanLimit(selectedPlan.max_users, 'User', 'Users');

            planNameEl.textContent = `${selectedPlanName} Plan`;
            let description = `${monthlyPrice} per month. `;
            
            if (selectedPlanName === applicationPlanName && applicationPlanAvailable) {
                description += `This matches the plan from your application.`;
            } else if (selectedPlanName !== applicationPlanName && applicationPlanName) {
                description += `You originally applied for ${applicationPlanName}, and you are switching to ${selectedPlanName}.`;
            } else {
                description += `Your selected plan includes the following:`;
            }
            
            if (selectedPlan.inclusions && selectedPlan.inclusions.length > 0) {
                description += `<div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid rgba(2,132,199,0.1);"><strong style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; color: #0369a1;">Plan Inclusions:</strong><ul style="margin: 6px 0 0 0; padding-left: 20px; display: grid; grid-template-columns: 1fr 1fr; gap: 4px;">`;
                selectedPlan.inclusions.forEach(inc => {
                    description += `<li style="font-size: 0.8rem; color: #475569;">${inc}</li>`;
                });
                description += `</ul></div>`;
            }
            
            planDescriptionEl.innerHTML = description;
        }

        function addThirtyDays(date) {
            const nextDate = new Date(date);
            nextDate.setDate(nextDate.getDate() + 30);
            return nextDate;
        }

        function updateCheckoutSummary() {
            const summaryDiv = document.getElementById('checkout-summary');
            const summaryText = document.getElementById('checkout-text');
            const selectedPlanName = getSelectedPlanName();
            const selectedPlan = planCatalog[selectedPlanName];
            const mrr = Number(selectedPlan?.price || 0);

            if (!mrr || mrr <= 0) {
                summaryDiv.style.display = 'none';
                return;
            }

            const formatNextDate = (d) => d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
            const today = new Date();
            const nextBillingDate = addThirtyDays(today);
            const amountFormatted = mrr.toLocaleString('en-US', { style: 'currency', currency: 'PHP' });
            const mrrFormatted = mrr.toLocaleString('en-US', { style: 'currency', currency: 'PHP' });

            summaryDiv.style.display = 'block';
            summaryText.innerHTML = `You selected the <strong>${selectedPlanName}</strong> plan. You will be charged <strong>${amountFormatted}</strong> today to activate your account. Your recurring billing of <strong>${mrrFormatted}</strong> will renew on <strong>${formatNextDate(nextBillingDate)}</strong> and continue every 30 days after each successful charge.`;
        }

        selectedPlanInputs.forEach((input) => {
            input.addEventListener('change', () => {
                updateSelectedPlanSummary();
                updateCheckoutSummary();
            });
        });

        updateCheckoutSummary();
        updateSelectedPlanSummary();
    </script>
</body>
</html>

