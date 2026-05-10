<?php
require_once "../../../microfin_backend/auth/session_auth.php";
mf_start_backend_session();
require_once "../../../microfin_backend/config/db_connect.php";
mf_require_tenant_session($pdo, [
    'response' => 'redirect',
    'redirect' => '../../tenant_login/login.php',
    'append_tenant_slug' => true,
]);

// Only Employees who need to change password should be here
if ($_SESSION['user_type'] !== 'Employee') {
    header("Location: ../admin.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$tenant_id = $_SESSION['tenant_id'];

// Fetch current user state
$stmt = $pdo->prepare("SELECT force_password_change, first_name, last_name, middle_name, suffix, phone_number, date_of_birth, username, email, user_type FROM users WHERE user_id = ? AND tenant_id = ?");
$stmt->execute([$user_id, $tenant_id]);
$user = $stmt->fetch();

if (!$user || !(bool)$user["force_password_change"]) {
    header('Location: dashboard.php');
    exit;
}

// Determine if profile fields are needed (if First/Last name are empty)
$needs_profile = empty($user['first_name']) || empty($user['last_name']);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Profile fields
    $first_name = trim($_POST["first_name"] ?? $user['first_name'] ?? "");
    $last_name = trim($_POST["last_name"] ?? $user['last_name'] ?? "");
    $middle_name = trim($_POST["middle_name"] ?? $user['middle_name'] ?? "");
    $suffix = trim($_POST["suffix"] ?? $user['suffix'] ?? "");
    $phone_number = trim($_POST["phone_number"] ?? $user['phone_number'] ?? "");
    $date_of_birth = trim($_POST["date_of_birth"] ?? $user['date_of_birth'] ?? "");
    $custom_username = trim($_POST["username"] ?? "");

    if (empty($new_password) || empty($confirm_password)) {
        $error = 'Both password fields are required.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($new_password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($needs_profile && (empty($first_name) || empty($last_name) || empty($phone_number) || empty($date_of_birth))) {
        $error = "First Name, Last Name, Phone Number, and Date of Birth are required.";
    } elseif ($needs_profile && !empty($date_of_birth) && (new DateTime($date_of_birth))->diff(new DateTime())->y < 18) {
        $error = "You must be 18 years or older.";
    } else {
        try {
            $pdo->beginTransaction();

            // Generate/Update Username if it was auto-generated or they want a custom one
            $final_username = $user['username'];
            if ($needs_profile) {
                $base_username = '';
                if (!empty($custom_username)) {
                    $base_username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $custom_username));
                } else {
                    $names = explode(' ', $first_name);
                    $base_username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $names[0]));
                }
                
                if ($base_username === '') $base_username = 'user';

                // Check for uniqueness if changed
                if ($final_username !== $base_username || !empty($custom_username)) {
                    $final_username = $base_username;
                    $counter = 1;
                    while (true) {
                        $check_username = $pdo->prepare('SELECT COUNT(*) FROM users WHERE tenant_id = ? AND username = ? AND user_id != ?');
                        $check_username->execute([$tenant_id, $final_username, $user_id]);
                        if ($check_username->fetchColumn() == 0) break;
                        $final_username = $base_username . $counter++;
                    }
                }
            }

            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update Users Table
            $update_stmt = $pdo->prepare('UPDATE users SET password_hash = ?, force_password_change = FALSE, first_name = ?, last_name = ?, middle_name = ?, suffix = ?, phone_number = ?, date_of_birth = ?, username = ? WHERE user_id = ? AND tenant_id = ?');
            $update_stmt->execute([$hashed_password, $first_name, $last_name, empty($middle_name) ? null : $middle_name, empty($suffix) ? null : $suffix, $phone_number, $date_of_birth, $final_username, $user_id, $tenant_id]);
            
            // Update Employees Table
            $emp_update = $pdo->prepare("UPDATE employees SET first_name = ?, last_name = ?, middle_name = ?, suffix = ?, contact_number = ? WHERE user_id = ? AND tenant_id = ?");
            $emp_update->execute([$first_name, $last_name, empty($middle_name) ? null : $middle_name, empty($suffix) ? null : $suffix, $phone_number, $user_id, $tenant_id]);

            $pdo->commit();

            // Refresh session username if changed
            $_SESSION['username'] = $final_username;

            $success = 'Setup completed successfully! Redirecting to dashboard...';
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'An error occurred during setup: ' . $e->getMessage();
        }
    }
}

// Fetch tenant branding
$brand_stmt = $pdo->prepare('SELECT theme_primary_color, theme_text_main, theme_text_muted, theme_bg_body, theme_bg_card, font_family, logo_path FROM tenant_branding WHERE tenant_id = ?');
$brand_stmt->execute([$tenant_id]);
$tenant_brand = $brand_stmt->fetch(PDO::FETCH_ASSOC);

$theme_color = ($tenant_brand && $tenant_brand['theme_primary_color']) ? $tenant_brand['theme_primary_color'] : ($_SESSION['theme'] ?? '#0f172a');
$theme_text_main = ($tenant_brand && $tenant_brand['theme_text_main']) ? $tenant_brand['theme_text_main'] : '#0f172a';
$theme_text_muted = ($tenant_brand && $tenant_brand['theme_text_muted']) ? $tenant_brand['theme_text_muted'] : '#64748b';
$theme_bg_body = ($tenant_brand && $tenant_brand['theme_bg_body']) ? $tenant_brand['theme_bg_body'] : '#f8fafc';
$theme_bg_card = ($tenant_brand && $tenant_brand['theme_bg_card']) ? $tenant_brand['theme_bg_card'] : '#ffffff';
$theme_font = ($tenant_brand && $tenant_brand['font_family']) ? $tenant_brand['font_family'] : 'Inter';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Setup Wizard</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=<?php echo urlencode($theme_font); ?>:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Material Symbols -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="../../assets/password-toggle.css">
    <style>
        :root {
            --brand-color: <?php echo htmlspecialchars($theme_color); ?>;
            --brand-bg: <?php echo htmlspecialchars($theme_bg_body); ?>;
            --text-main: <?php echo htmlspecialchars($theme_text_main); ?>;
            --text-muted: <?php echo htmlspecialchars($theme_text_muted); ?>;
            --card-bg: <?php echo htmlspecialchars($theme_bg_card); ?>;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: '<?php echo htmlspecialchars($theme_font); ?>', sans-serif; }

        body {
            background-color: var(--brand-bg);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        .setup-container {
            background: var(--card-bg);
            width: 100%;
            max-width: 500px;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);
            padding: 40px;
            text-align: center;
        }

        .logo-circle {
            width: 64px; height: 64px;
            background: var(--brand-color);
            color: white;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 24px;
        }
        
        .logo-circle .material-symbols-rounded { font-size: 32px; }

        h1 { color: var(--text-main); font-size: 1.5rem; margin-bottom: 8px; letter-spacing: -0.5px; }

        p.subtitle { color: var(--text-muted); font-size: 0.95rem; margin-bottom: 32px; line-height: 1.5; }

        .form-section-title {
            text-align: left;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-main);
            margin: 24px 0 16px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e2e8f0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group { margin-bottom: 20px; text-align: left; }
        .form-group label { display: block; margin-bottom: 8px; font-size: 0.85rem; font-weight: 500; color: #475569; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

        .form-control {
            width: 100%; padding: 12px 16px; border: 1px solid #cbd5e1; border-radius: 10px;
            font-size: 0.95rem; color: var(--text-main); transition: all 0.2s;
        }

        .form-control:focus { outline: none; border-color: var(--brand-color); box-shadow: 0 0 0 3px rgba(15, 23, 42, 0.1); }

        .btn-submit {
            width: 100%; padding: 14px; background: var(--brand-color); color: white; border: none; border-radius: 10px;
            font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.2s;
            display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 12px;
        }

        .btn-submit:hover { filter: brightness(1.1); transform: translateY(-1px); }

        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 24px; font-size: 0.9rem; font-weight: 500; text-align: left; }
        .alert-error { background-color: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
        .alert-success { background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        
        .help-text { font-weight: normal; color: #94a3b8; font-size: 0.8rem; }
        .required-star { color: #ef4444; }
    </style>
</head>
<body>

    <div class="setup-container">
        <div class="logo-circle">
            <span class="material-symbols-rounded">shield_person</span>
        </div>
        <h1>Welcome!</h1>
        <p class="subtitle">Please complete your account setup and change your temporary password to continue.</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <span class="material-symbols-rounded" style="font-size: 18px; vertical-align: middle; margin-right: 4px;">check_circle</span>
                <?php echo htmlspecialchars($success); ?>
            </div>
            <script>setTimeout(() => { window.location.href = 'dashboard.php'; }, 2000);</script>
        <?php else: ?>
            <form method="POST" action="">
                <div class="form-section-title" style="margin-top: 0;">Security</div>
                <div class="form-row">
                    <div class="form-group">
                        <label>New Password <span class="required-star">*</span></label>
                        <input type="password" name="new_password" class="form-control" placeholder="••••••••" required minlength="8">
                    </div>
                    <div class="form-group">
                        <label>Confirm <span class="required-star">*</span></label>
                        <input type="password" name="confirm_password" class="form-control" placeholder="••••••••" required minlength="8">
                    </div>
                </div>

                <?php if ($needs_profile): ?>
                    <div class="form-section-title">Personal Information</div>
                    
                    <div class="form-group">
                        <label>Username <span class="help-text">(Optional. Defaults to first name)</span></label>
                        <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($_POST['username'] ?? $user['username'] ?? ''); ?>" placeholder="e.g. Maria">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>First Name <span class="required-star">*</span></label>
                            <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Last Name <span class="required-star">*</span></label>
                            <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Middle Name <span class="help-text">(Optional)</span></label>
                            <input type="text" name="middle_name" class="form-control" value="<?php echo htmlspecialchars($_POST['middle_name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Suffix <span class="help-text">(Optional)</span></label>
                            <input type="text" name="suffix" class="form-control" value="<?php echo htmlspecialchars($_POST['suffix'] ?? ''); ?>" placeholder="Jr, Sr">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Phone Number <span class="required-star">*</span></label>
                            <input type="text" name="phone_number" class="form-control" value="<?php echo htmlspecialchars($_POST['phone_number'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Date of Birth <span class="required-star">*</span></label>
                            <input type="date" name="date_of_birth" class="form-control" value="<?php echo htmlspecialchars($_POST['date_of_birth'] ?? ''); ?>" max="<?php echo date('Y-m-d', strtotime('-18 years')); ?>" required>
                        </div>
                    </div>
                <?php endif; ?>

                <button type="submit" class="btn-submit">
                    Complete Setup <span class="material-symbols-rounded" style="font-size: 18px;">arrow_forward</span>
                </button>
            </form>
        <?php endif; ?>
    </div>

    <script src="../../assets/password-toggle.js"></script>
</body>
</html>
