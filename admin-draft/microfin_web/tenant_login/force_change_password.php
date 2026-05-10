<?php
require_once "../../microfin_backend/auth/session_auth.php";
mf_start_backend_session();
require_once "../../microfin_backend/config/db_connect.php";
mf_require_tenant_session($pdo, [
    'response' => 'redirect',
    'redirect' => 'login.php',
    'append_tenant_slug' => true,
]);

$user_id = $_SESSION["user_id"];

// Check if user actually needs to change password
$stmt = $pdo->prepare("SELECT force_password_change, first_name, last_name, middle_name, suffix, phone_number, date_of_birth, username, email, user_type FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user || !(bool)$user["force_password_change"]) {
    // Password already changed - billing is now the only onboarding gate before dashboard access
    $tenant_id = $_SESSION['tenant_id'] ?? '';
    $step_stmt = $pdo->prepare('SELECT setup_current_step, setup_completed FROM tenants WHERE tenant_id = ?');
    $step_stmt->execute([$tenant_id]);
    $step_data = $step_stmt->fetch(PDO::FETCH_ASSOC);

    if ($step_data && !(bool)$step_data['setup_completed']) {
        $setup_step = (int)($step_data['setup_current_step'] ?? 0);
        if ($setup_step < 5) {
            $pdo->prepare('UPDATE tenants SET setup_current_step = 5 WHERE tenant_id = ?')->execute([$tenant_id]);
        }
        header('Location: setup_billing.php');
        exit;
    }
    header("Location: ../admin_panel/admin.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $new_password = $_POST["new_password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";
    
    $first_name = trim($_POST["first_name"] ?? "");
    $last_name = trim($_POST["last_name"] ?? "");
    $middle_name = trim($_POST["middle_name"] ?? "");
    $suffix = trim($_POST["suffix"] ?? "");
    $phone_number = trim($_POST["phone_number"] ?? "");
    $date_of_birth = trim($_POST["date_of_birth"] ?? "");
    $custom_username = trim($_POST["username"] ?? "");

    if (empty($new_password) || empty($confirm_password)) {
        $error = "Both password fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif (empty($first_name) || empty($last_name) || empty($phone_number) || empty($date_of_birth)) {
        $error = "First Name, Last Name, Phone Number, and Date of Birth are required.";
    } elseif (!empty($date_of_birth) && (new DateTime($date_of_birth))->diff(new DateTime())->y < 18) {
        $error = "You must be 18 years or older.";
    } else {
        $tenant_id = $_SESSION['tenant_id'] ?? '';

        // Generate Username Logic
        $base_username = '';
        if (!empty($custom_username)) {
            $base_username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $custom_username));
        } elseif (!empty($first_name)) {
            $names = explode(' ', $first_name);
            $role_suffix = ($user['user_type'] === 'Admin') ? 'Admin' : '';
            $base_username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $names[0])) . $role_suffix;
        } else {
            $email_parts = explode('@', $user['email']);
            $role_suffix = ($user['user_type'] === 'Admin') ? 'Admin' : '';
            $base_username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $email_parts[0])) . $role_suffix;
        }
        if ($base_username === '') {
            $base_username = 'user';
        }

        // If username changed, ensure uniqueness
        $final_username = $user['username'];
        if ($final_username !== $custom_username && !empty($custom_username)) {
            $final_username = $base_username;
            $counter = 1;
            while (true) {
                // Ignore current user_id from uniqueness constraint
                $check_username = $pdo->prepare('SELECT COUNT(*) FROM users WHERE tenant_id = ? AND username = ? AND user_id != ?');
                $check_username->execute([$tenant_id, $final_username, $user_id]);
                if ($check_username->fetchColumn() == 0) break;
                $final_username = $base_username . $counter++;
            }
        } elseif (empty($user['first_name']) && empty($custom_username)) {
            // Generate username if they didn't have a first name initially and didn't provide custom username
            $final_username = $base_username;
            $counter = 1;
            while (true) {
                $check_username = $pdo->prepare('SELECT COUNT(*) FROM users WHERE tenant_id = ? AND username = ? AND user_id != ?');
                $check_username->execute([$tenant_id, $final_username, $user_id]);
                if ($check_username->fetchColumn() == 0) break;
                $final_username = $base_username . $counter++;
            }
        }

        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, force_password_change = 0, first_name = ?, last_name = ?, middle_name = ?, suffix = ?, phone_number = ?, date_of_birth = ?, username = ? WHERE user_id = ?");
        
        if ($stmt->execute([$hashed_password, $first_name, $last_name, empty($middle_name) ? null : $middle_name, empty($suffix) ? null : $suffix, $phone_number, $date_of_birth, $final_username, $user_id])) {
            
            // Also update the associated employees table record if it exists
            $emp_update = $pdo->prepare("UPDATE employees SET first_name = ?, last_name = ?, middle_name = ?, suffix = ?, contact_number = ? WHERE user_id = ?");
            $emp_update->execute([$first_name, $last_name, empty($middle_name) ? null : $middle_name, empty($suffix) ? null : $suffix, $phone_number, $user_id]);

            $log = $pdo->prepare("INSERT INTO audit_logs (action_type, entity_type, description, tenant_id) VALUES (?, ?, ?, ?)");
            $log->execute(["PASSWORD_CHANGED", "user", "User completed forced password reset and profile setup", $tenant_id]);

            $onboarding_stmt = $pdo->prepare('SELECT setup_completed FROM tenants WHERE tenant_id = ?');
            $onboarding_stmt->execute([$tenant_id]);
            $setup_completed = (bool)$onboarding_stmt->fetchColumn();

            if (!$setup_completed) {
                $pdo->prepare('UPDATE tenants SET setup_current_step = 5 WHERE tenant_id = ?')->execute([$tenant_id]);
                header('Location: setup_billing.php');
                exit;
            }

            header('Location: ../admin_panel/admin.php');
            exit;
        } else {
            $error = "Failed to update profile. Please try again.";
        }
    }
}

// Fetch tenant branding
$tenant_id = $_SESSION['tenant_id'] ?? 0;
$brand_stmt = $pdo->prepare('SELECT theme_primary_color, theme_text_main, theme_text_muted, theme_bg_body, theme_bg_card, font_family FROM tenant_branding WHERE tenant_id = ?');
$brand_stmt->execute([$tenant_id]);
$tenant_brand = $brand_stmt->fetch(PDO::FETCH_ASSOC);

$t_primary = ($tenant_brand && $tenant_brand['theme_primary_color']) ? $tenant_brand['theme_primary_color'] : '#0f172a';
$t_text = ($tenant_brand && $tenant_brand['theme_text_main']) ? $tenant_brand['theme_text_main'] : '#0f172a';
$t_muted = ($tenant_brand && $tenant_brand['theme_text_muted']) ? $tenant_brand['theme_text_muted'] : '#475569';
$t_bg = ($tenant_brand && $tenant_brand['theme_bg_body']) ? $tenant_brand['theme_bg_body'] : '#f8fafc';
$t_card = ($tenant_brand && $tenant_brand['theme_bg_card']) ? $tenant_brand['theme_bg_card'] : '#ffffff';
$t_font = ($tenant_brand && $tenant_brand['font_family']) ? $tenant_brand['font_family'] : 'Inter';

$is_onboarding = false;
if ($tenant_id) {
    $onb_chk = $pdo->prepare('SELECT setup_completed FROM tenants WHERE tenant_id = ?');
    $onb_chk->execute([$tenant_id]);
    $is_onboarding = !(bool)$onb_chk->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Profile - MicroFin</title>
    <link href="https://fonts.googleapis.com/css2?family=<?php echo urlencode($t_font); ?>:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/password-toggle.css">
    <style>
        body { font-family: '<?php echo htmlspecialchars($t_font); ?>', sans-serif; background: <?php echo htmlspecialchars($t_bg); ?>; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 40px 20px; box-sizing: border-box; }
        .container { background: <?php echo htmlspecialchars($t_card); ?>; padding: 40px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); width: 100%; max-width: 500px; }
        h2 { margin-top: 0; color: <?php echo htmlspecialchars($t_text); ?>; font-size: 24px; margin-bottom: 8px; }
        p { color: <?php echo htmlspecialchars($t_muted); ?>; margin-bottom: 32px; font-size: 14px; line-height: 1.5; }
        .form-section-title { font-size: 16px; font-weight: 600; color: <?php echo htmlspecialchars($t_text); ?>; margin-bottom: 16px; margin-top: 24px; padding-bottom: 8px; border-bottom: 1px solid #e2e8f0; }
        .form-group { margin-bottom: 20px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        label { display: block; margin-bottom: 8px; color: <?php echo htmlspecialchars($t_muted); ?>; font-weight: 500; font-size: 13px; }
        input[type="password"], input[type="text"], input[type="date"] { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; font-family: inherit; color: <?php echo htmlspecialchars($t_text); ?>; transition: all 0.2s; }
        input[type="password"]:focus, input[type="text"]:focus, input[type="date"]:focus { outline: none; border-color: <?php echo htmlspecialchars($t_primary); ?>; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        button[type="submit"] { width: 100%; padding: 12px; background: <?php echo htmlspecialchars($t_primary); ?>; color: white; border: none; border-radius: 6px; font-weight: 600; font-size: 15px; cursor: pointer; transition: background 0.2s; margin-top: 16px; }
        button[type="submit"]:hover { filter: brightness(0.9); }
        .error { color: #ef4444; background: #fef2f2; border: 1px solid #fecaca; padding: 12px; border-radius: 6px; margin-bottom: 24px; font-size: 14px; }
        .step-indicator { display: flex; gap: 8px; margin-bottom: 24px; justify-content: center; }
        .step { width: 44px; height: 4px; border-radius: 2px; background: #cbd5e1; }
        .step.active { background: <?php echo htmlspecialchars($t_primary); ?>; }
        .help-text { font-weight: normal; color: #64748b; font-size: 12px; }
        .required-star { color: #ef4444; }
    </style>
</head>
<body>
    <div class="container">
        <?php if($is_onboarding): ?>
            <div class="step-indicator">
                <div class="step active"></div>
                <div class="step"></div>
            </div>
        <?php endif; ?>
        <h2>Complete Your Profile</h2>
        <p>Welcome! Before accessing your dashboard, please change your temporary password and complete your personal information.</p>
        
        <?php if(!empty($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" id="profile-form">
            <div class="form-section-title" style="margin-top: 0;">Account Security</div>
            <div class="form-row">
                <div class="form-group">
                    <label for="new_password">New Password <span class="required-star">*</span></label>
                    <input type="password" id="new_password" name="new_password" required minlength="8">
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password <span class="required-star">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                </div>
            </div>

            <div class="form-section-title">Personal Information</div>
            
            <div class="form-group">
                <label for="username">Username <span class="help-text">(Optional. Defaults to first name)</span></label>
                <!-- Only pre-fill username if it doesn't look like an auto-generated one from email, or let them see the auto-gen one -->
                <input type="text" id="username" name="username" value="<?= htmlspecialchars($_POST['username'] ?? $user['username'] ?? '') ?>" placeholder="e.g. Maria">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="first_name">First Name <span class="required-star">*</span></label>
                    <input type="text" id="first_name" name="first_name" value="<?= htmlspecialchars($_POST['first_name'] ?? $user['first_name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label for="last_name">Last Name <span class="required-star">*</span></label>
                    <input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars($_POST['last_name'] ?? $user['last_name'] ?? '') ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="middle_name">Middle Name <span class="help-text">(Optional)</span></label>
                    <input type="text" id="middle_name" name="middle_name" value="<?= htmlspecialchars($_POST['middle_name'] ?? $user['middle_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="suffix">Suffix <span class="help-text">(Optional)</span></label>
                    <input type="text" id="suffix" name="suffix" value="<?= htmlspecialchars($_POST['suffix'] ?? $user['suffix'] ?? '') ?>" placeholder="e.g. Jr, Sr">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="phone_number">Phone Number <span class="required-star">*</span></label>
                    <input type="text" id="phone_number" name="phone_number" value="<?= htmlspecialchars($_POST['phone_number'] ?? $user['phone_number'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label for="date_of_birth">Date of Birth <span class="required-star">*</span></label>
                    <input type="date" id="date_of_birth" name="date_of_birth" value="<?= htmlspecialchars($_POST['date_of_birth'] ?? $user['date_of_birth'] ?? '') ?>" max="<?= date('Y-m-d', strtotime('-18 years')) ?>" required>
                </div>
            </div>
            
            <button type="submit">Save Profile & Continue</button>
        </form>
    </div>
    <script src="../assets/password-toggle.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const p1 = document.getElementById('new_password');
            const p2 = document.getElementById('confirm_password');
            if (p1 && p2) {
                const btn1 = p1.nextElementSibling;
                const btn2 = p2.nextElementSibling;
                if (btn1 && btn1.classList.contains('password-toggle-btn') && btn2 && btn2.classList.contains('password-toggle-btn')) {
                    btn1.addEventListener('click', (e) => { 
                        if (e.isTrusted && p1.type !== p2.type) btn2.click(); 
                    });
                    btn2.addEventListener('click', (e) => { 
                        if (e.isTrusted && p2.type !== p1.type) btn1.click(); 
                    });
                }
            }
        });
    </script>
</body>
</html>

