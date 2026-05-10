<?php
require_once '../../microfin_backend/auth/session_auth.php';
mf_start_backend_session();
require_once '../../microfin_backend/config/db_connect.php';
mf_require_tenant_session($pdo, [
    'response' => 'redirect',
    'redirect' => '../tenant_login/login.php',
    'append_tenant_slug' => true,
    'status' => 302,
]);

$tenant_id = $_SESSION['tenant_id'];

// Prevent access if already completed
$tenant_stmt = $pdo->prepare('SELECT setup_completed, setup_current_step, tenant_name FROM tenants WHERE tenant_id = ?');
$tenant_stmt->execute([$tenant_id]);
$tenant_data = $tenant_stmt->fetch(PDO::FETCH_ASSOC);

if ($tenant_data && (bool)$tenant_data['setup_completed']) {
    header('Location: admin.php');
    exit;
}

// This page is deprecated. Billing is now the first onboarding gate after password reset.
$current_step = (int)($tenant_data['setup_current_step'] ?? 0);
if ($current_step < 6) {
    if ($current_step < 5) {
        $pdo->prepare('UPDATE tenants SET setup_current_step = 5 WHERE tenant_id = ?')->execute([$tenant_id]);
    }
    header('Location: ../tenant_login/setup_billing.php');
    exit;
}

$error_msg = '';
$success_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect Configuration Payload
    $currency_symbol = trim($_POST['currency_symbol'] ?? '₱');
    $timezone = trim($_POST['timezone'] ?? 'Asia/Manila');
    
    // Core Loan Logic Payload
    $product_name = trim($_POST['product_name'] ?? 'Standard MicroLoan');
    $interest_rate = floatval($_POST['interest_rate'] ?? 0);
    $interest_type = trim($_POST['interest_type'] ?? 'Diminishing');
    $min_term = intval($_POST['min_term'] ?? 1);
    $max_term = intval($_POST['max_term'] ?? 12);
    $processing_fee = floatval($_POST['processing_fee'] ?? 0);
    $early_settlement_fee = floatval($_POST['early_settlement_fee'] ?? 0);
    $grace_period = intval($_POST['grace_period'] ?? 0);
    $doc_stamp = floatval($_POST['doc_stamp'] ?? 0);

    try {
        $pdo->beginTransaction();

        // 1. Save Core Settings into `system_settings`
        $insert_setting = $pdo->prepare("INSERT INTO system_settings (tenant_id, setting_key, setting_value, setting_category, data_type) VALUES (?, ?, ?, 'General', 'String') ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        
        $insert_setting->execute([$tenant_id, 'currency_symbol', $currency_symbol]);
        $insert_setting->execute([$tenant_id, 'timezone', $timezone]);

        // 2. Create the Initial Loan Product
        $insert_product = $pdo->prepare("
            INSERT INTO loan_products (
                tenant_id, product_name, product_type, min_amount, max_amount, 
                interest_rate, interest_type, min_term_months, max_term_months,
                processing_fee_percentage, documentary_stamp, early_settlement_fee_type, early_settlement_fee_value, 
                grace_period_days
            ) VALUES (?, ?, 'Personal Loan', 1000, 50000, ?, ?, ?, ?, ?, ?, 'Percentage', ?, ?)
        ");
        $insert_product->execute([
            $tenant_id, 
            $product_name, 
            $interest_rate, 
            $interest_type, 
            $min_term, 
            $max_term,
            $processing_fee,
            $doc_stamp,
            $early_settlement_fee,
            $grace_period
        ]);

        // 3. Mark Setup as Complete
        $complete_stmt = $pdo->prepare("UPDATE tenants SET setup_completed = TRUE, setup_current_step = 4 WHERE tenant_id = ?");
        $complete_stmt->execute([$tenant_id]);

        $pdo->commit();
        
        // Log action
        $log = $pdo->prepare("INSERT INTO audit_logs (action_type, entity_type, description, tenant_id) VALUES ('SETUP_COMPLETED', 'tenant', 'Onboarding setup completed successfully', ?)");
        $log->execute([$tenant_id]);

        header("Location: admin.php?welcome=1");
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        $error_msg = "Database error: " . $e->getMessage();
    }
}

// Fetch tenant branding
$brand_stmt = $pdo->prepare('SELECT theme_primary_color FROM tenant_branding WHERE tenant_id = ?');
$brand_stmt->execute([$tenant_id]);
$tenant_brand = $brand_stmt->fetch(PDO::FETCH_ASSOC);

$t_primary = ($tenant_brand && $tenant_brand['theme_primary_color']) ? $tenant_brand['theme_primary_color'] : '#4f46e5';
$t_text = '#0f172a';
$t_muted = '#64748b';
$t_bg = '#f8fafc';
$t_card = '#ffffff';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>First-Time Setup | MicroFin</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin.css">
    <!-- Inline styles to overwrite standard admin panel structural rules for the wizard -->
    <style>
        body {
            background-color: <?php echo htmlspecialchars($t_bg); ?>;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            font-family: 'Outfit', sans-serif;
            color: <?php echo htmlspecialchars($t_text); ?>;
        }
        .setup-container {
            background: <?php echo htmlspecialchars($t_card); ?>;
            width: 100%;
            max-width: 800px;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -2px rgba(0,0,0,0.1);
        }
        h1 {
            font-size: 1.5rem;
            margin-bottom: 20px;
            color: <?php echo htmlspecialchars($t_text); ?>;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 15px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .form-group.full-width {
            grid-column: span 2;
        }
        .btn-wizard {
            background: <?php echo htmlspecialchars($t_primary); ?>;
            color: white;
            padding: 12px 24px;
            border-radius: 6px;
            border: none;
            font-weight: 500;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.2s;
            width: 100%;
            margin-top: 20px;
        }
        .btn-wizard:hover {
            filter: brightness(0.9);
        }
        .section-title {
            grid-column: span 2;
            font-size: 1.1rem;
            font-weight: 600;
            margin-top: 20px;
            color: <?php echo htmlspecialchars($t_muted); ?>;
            margin-bottom: 10px;
        }
        label { margin-bottom: 4px; display: block; font-weight: 500; font-size:0.9rem; color: <?php echo htmlspecialchars($t_muted); ?>; }
        input, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-family: inherit;
            color: <?php echo htmlspecialchars($t_text); ?>;
        }
        .error { color: #dc2626; background: #fee2e2; padding: 10px; border-radius: 4px; margin-bottom: 20px; font-size: 0.9rem;}
    </style>
</head>
<body>
    <div class="setup-container">
        <h1>Welcome to MicroFin, <?php echo htmlspecialchars($tenant_data['tenant_name'] ?? 'Admin'); ?>!</h1>
        <p style="color: #64748b; margin-bottom: 30px; font-size: 0.95rem;">Please complete your core institution setup. This establishes your default lending rules.</p>
        
        <?php if($error_msg): ?>
            <div class="error"><?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-grid">
                
                <div class="section-title">General Preferences</div>
                
                <div class="form-group">
                    <label>Currency Symbol</label>
                    <select name="currency_symbol">
                        <option value="₱">PHP (₱)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Timezone</label>
                    <select name="timezone">
                        <option value="Asia/Manila">Asia/Manila (PHT)</option>
                        <option value="America/New_York">America/New_York (EST)</option>
                        <option value="Europe/London">Europe/London (GMT)</option>
                    </select>
                </div>

                <div class="section-title">Your First Default Loan Product</div>

                <div class="form-group full-width">
                    <label>Product Name</label>
                    <input type="text" name="product_name" value="Standard MicroLoan" required>
                </div>

                <div class="form-group">
                    <label>Base Interest Rate (%)</label>
                    <input type="number" step="0.01" name="interest_rate" value="5.00" required>
                </div>

                <div class="form-group">
                    <label>Interest Computation</label>
                    <select name="interest_type">
                        <option value="Diminishing">Diminishing (Declining Balance)</option>
                        <option value="Fixed">Fixed (Flat Rate)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Minimum Term (Months)</label>
                    <input type="number" name="min_term" value="3" required>
                </div>

                <div class="form-group">
                    <label>Maximum Term (Months)</label>
                    <input type="number" name="max_term" value="12" required>
                </div>

                <div class="form-group">
                    <label>Processing Fee (%)</label>
                    <input type="number" step="0.01" name="processing_fee" value="2.00">
                </div>

                <div class="form-group">
                    <label>Documentary Stamp (Fixed Amt)</label>
                    <input type="number" step="0.01" name="doc_stamp" value="100.00">
                </div>

                <div class="form-group">
                    <label>Early Settlement Fee (%)</label>
                    <input type="number" step="0.01" name="early_settlement_fee" value="0.00">
                </div>

                <div class="form-group">
                    <label>Grace Period (Days)</label>
                    <input type="number" name="grace_period" value="3">
                </div>

            </div>

            <button type="submit" class="btn-wizard">Complete Setup & Enter Platform</button>
        </form>
    </div>
</body>
</html>
