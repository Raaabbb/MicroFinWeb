<?php
require_once "../../microfin_backend/auth/session_auth.php";
mf_start_backend_session();
require_once "../../microfin_backend/config/db_connect.php";
require_once "../../microfin_backend/auth/login_activity.php";
require_once "../../microfin_backend/auth/tenant_identity.php";

function mf_extract_site_slug_from_query(array $query)
{
    // Preferred keys first.
    foreach (['s', 'tenant', 'site', 'slug'] as $key) {
        if (isset($query[$key]) && is_scalar($query[$key])) {
            $value = trim((string)$query[$key]);
            if ($value !== '') {
                return $value;
            }
        }
    }

    // Some email clients can mangle keys as "amp;s" when copied from HTML.
    foreach ($query as $rawKey => $rawValue) {
        if (!is_scalar($rawValue)) {
            continue;
        }
        $key = strtolower(trim((string)$rawKey));
        while (strpos($key, 'amp;') === 0) {
            $key = substr($key, 4);
        }
        if (in_array($key, ['s', 'tenant', 'site', 'slug'], true)) {
            $value = trim((string)$rawValue);
            if ($value !== '') {
                return $value;
            }
        }
    }

    return '';
}

function mf_query_flag_is_enabled(array $query, string $key): bool
{
    if (!isset($query[$key]) || !is_scalar($query[$key])) {
        return false;
    }

    $value = strtolower(trim((string)$query[$key]));
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

function mf_tenant_admin_dashboard_url(): string
{
    return '../admin_panel/admin.php#dashboard';
}

$allowManualTenantLogin = $_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['auth']) || isset($_GET['switch']);

if (!$allowManualTenantLogin && mf_refresh_backend_session_state($pdo, 'tenant') && isset($_SESSION["user_logged_in"]) && $_SESSION["user_logged_in"] === true) {
    // Re-check force_password_change in case user closed browser during password reset
    if (!empty($_SESSION['user_id'])) {
        $fpc_stmt = $pdo->prepare('SELECT force_password_change FROM users WHERE user_id = ?');
        $fpc_stmt->execute([$_SESSION['user_id']]);
        $fpc_row = $fpc_stmt->fetch(PDO::FETCH_ASSOC);
        if ($fpc_row && (bool)$fpc_row['force_password_change']) {
            header('Location: force_change_password.php');
            exit;
        }
    }
    $sessionUserType = (string) ($_SESSION['user_type'] ?? '');
    $sessionRole = (string) ($_SESSION['role'] ?? $_SESSION['role_name'] ?? '');
    $isAdminSession = $sessionUserType === 'Employee'
        && (stripos($sessionRole, 'Admin') !== false || (bool) ($_SESSION['super_admin_logged_in'] ?? false));

    if ($isAdminSession) {
        header('Location: ' . mf_tenant_admin_dashboard_url());
        exit;
    }

    if ($sessionUserType === 'Employee') {
        header('Location: ../admin_panel/staff/dashboard.php#dashboard');
        exit;
    }

    header("Location: ../admin_panel/admin.php");
    exit;
}

// URL format: ?s=<slug>  — no key required
// Also supports legacy aliases: ?tenant=, ?site=, ?slug= and tenant IDs.
// Impersonate: ?s=<slug>&impersonate=1  (super admin, session-protected)
$site_slug = mf_extract_site_slug_from_query($_GET);
$site_slug = trim(urldecode(urldecode($site_slug)));
$tenant = null;
$tenant_error = '';
$login_error = '';
$tenant_public_website_ready = false;
$came_from_site = mf_query_flag_is_enabled($_GET, 'from_site');
$back_to_site_href = '';
$active_browser_session = mf_get_active_browser_backend_session($pdo);
$browser_session_block_message = $active_browser_session
    ? 'This browser already has an active session. Please log out of the current account before signing in again.'
    : '';

// Check for Super Admin Impersonation (uses ?s=slug&impersonate=1)
if ($site_slug !== '' && isset($_GET['impersonate']) && $_GET['impersonate'] == '1' && isset($_SESSION['super_admin_logged_in']) && $_SESSION['super_admin_logged_in'] === true) {
    $tenant_stmt = $pdo->prepare('SELECT t.tenant_id, t.tenant_name, t.tenant_slug, b.theme_primary_color, b.theme_secondary_color, b.theme_text_main, b.theme_text_muted, b.theme_bg_body, b.theme_bg_card, b.font_family, b.logo_path FROM tenants t LEFT JOIN tenant_branding b ON t.tenant_id = b.tenant_id WHERE t.tenant_slug = ?');
    $tenant_stmt->execute([strtolower($site_slug)]);
    $tenant = $tenant_stmt->fetch();
    if ($tenant) {
        $_SESSION['user_logged_in'] = true;
        $_SESSION['tenant_id'] = $tenant['tenant_id'];
        $_SESSION['tenant_slug'] = $tenant['tenant_slug'];
        $_SESSION['tenant_name'] = $tenant['tenant_name'];
        $_SESSION['user_id'] = 0;
        $_SESSION['username'] = 'Super Admin (Ghost)';
        $_SESSION['role_name'] = 'Super Admin';
        $_SESSION['ui_theme'] = (($_SESSION['ui_theme'] ?? 'light') === 'dark') ? 'dark' : 'light';

        $log = $pdo->prepare("INSERT INTO audit_logs (action_type, entity_type, description, tenant_id) VALUES ('IMPERSONATION', 'user', 'Super Admin initiated impersonation session', ?)");
        $log->execute([$tenant['tenant_id']]);

        header('Location: ' . mf_tenant_admin_dashboard_url());
        exit;
    }
}

// Regular access — only ?s=<slug> is required
if ($site_slug !== '') {
    $normalized_slug = mf_normalize_tenant_slug($site_slug);
    $tenant_stmt = $pdo->prepare('SELECT t.tenant_id, t.tenant_name, t.tenant_slug, b.theme_primary_color, b.theme_secondary_color, b.theme_text_main, b.theme_text_muted, b.theme_bg_body, b.theme_bg_card, b.font_family, b.logo_path, t.status, t.setup_completed, t.setup_current_step, t.onboarding_deadline FROM tenants t LEFT JOIN tenant_branding b ON t.tenant_id = b.tenant_id WHERE t.tenant_slug = ? OR LOWER(t.tenant_slug) = ? OR t.tenant_id = ? LIMIT 1');
    $tenant_stmt->execute([$site_slug, $normalized_slug, $site_slug]);
    $tenant = $tenant_stmt->fetch();
}

if (!$tenant) {
    // Fallback for local/single-tenant deployments where links may lose query params.
    $single_stmt = $pdo->prepare("SELECT t.tenant_id, t.tenant_name, t.tenant_slug, b.theme_primary_color, b.theme_secondary_color, b.theme_text_main, b.theme_text_muted, b.theme_bg_body, b.theme_bg_card, b.font_family, b.logo_path, t.status, t.setup_completed, t.setup_current_step, t.onboarding_deadline FROM tenants t LEFT JOIN tenant_branding b ON t.tenant_id = b.tenant_id WHERE t.status = 'Active' LIMIT 2");
    $single_stmt->execute();
    $single_tenants = $single_stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($single_tenants) === 1) {
        $tenant = $single_tenants[0];
        $site_slug = (string)$tenant['tenant_slug'];
    }
}

if (!$tenant) {
    $tenant_error = ($site_slug === '')
        ? 'Missing site identifier. Please use the login link provided to you.'
        : 'Invalid login link. Please contact your administrator.';
} else {
    // Canonicalize the URL to ?s=<tenant_slug> so subsequent form posts are stable.
    if (!isset($_GET['impersonate']) && strcasecmp($site_slug, (string)$tenant['tenant_slug']) !== 0) {
        $query_params = ['s' => (string)$tenant['tenant_slug']];
        if (isset($_GET['auth'])) {
            $query_params['auth'] = '1';
        }
        if ($came_from_site) {
            $query_params['from_site'] = '1';
        }
        header('Location: login.php?' . http_build_query($query_params));
        exit;
    }

    // Enforce 30-day onboarding deadline
    if ($tenant['status'] === 'Active' && !(bool)$tenant['setup_completed'] && $tenant['onboarding_deadline']) {
        $deadline = new DateTime($tenant['onboarding_deadline']);
        $now = new DateTime();
        if ($now > $deadline) {
            $pdo->prepare("UPDATE tenants SET status = 'Suspended' WHERE tenant_id = ?")->execute([$tenant['tenant_id']]);
            $pdo->prepare("INSERT INTO audit_logs (action_type, entity_type, description, tenant_id) VALUES ('DEADLINE_EXPIRED', 'tenant', 'Tenant suspended due to 30-day onboarding deadline expiration', ?)")->execute([$tenant['tenant_id']]);
            $tenant['status'] = 'Suspended';
        }
    }

    if ($tenant['status'] !== 'Active') {
        $tenant_error = 'This workspace is currently inactive or suspended. Please contact support.';
        $tenant = null;
    } else {
        $tenant_public_website_ready = mf_tenant_public_website_is_ready($pdo, (string)$tenant['tenant_id']);
        if ($came_from_site) {
            $back_to_site_href = '../public_website/site.php?site=' . urlencode((string)$tenant['tenant_slug']);
        }
    }
}

if ($tenant && $tenant_public_website_ready && !empty($tenant['setup_completed']) && !isset($_GET['auth']) && !isset($_GET['impersonate']) && !isset($_GET['from_site']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../public_website/site.php?site=" . urlencode($tenant['tenant_slug']));
    exit;
}

if ($tenant && $login_error === '' && $browser_session_block_message !== '') {
    $login_error = $browser_session_block_message;
}

if ($tenant && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($browser_session_block_message !== '') {
        $login_error = $browser_session_block_message;
    } elseif ($email === '' || $password === '') {
        $login_error = 'Email and password are required.';
    } else {
        $user_stmt = $pdo->prepare('SELECT u.user_id, u.username, u.password_hash, u.force_password_change, u.role_id, u.user_type, u.status, u.ui_theme, r.role_name, r.is_system_role FROM users u JOIN user_roles r ON u.role_id = r.role_id WHERE u.email = ? AND u.tenant_id = ?');
        $user_stmt->execute([$email, $tenant['tenant_id']]);
        $user = $user_stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $login_error = 'Invalid email or password.';
        } elseif ($user['status'] !== 'Active') {
            $login_error = 'Account is suspended. Please contact your administrator.';
        } else {
            mf_update_user_last_login($pdo, (int) $user['user_id']);

            unset(
                $_SESSION['super_admin_logged_in'],
                $_SESSION['super_admin_id'],
                $_SESSION['super_admin_username'],
                $_SESSION['super_admin_force_password_change'],
                $_SESSION['super_admin_onboarding_required']
            );

            $_SESSION['user_logged_in'] = true;
            $_SESSION['user_id'] = (int) $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['tenant_id'] = $tenant['tenant_id'];
            $_SESSION['tenant_name'] = $tenant['tenant_name'];
            $_SESSION['tenant_slug'] = $tenant['tenant_slug'];
            $_SESSION['role'] = $user['role_name'];
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['theme'] = $tenant['theme_primary_color'] ?: '#0f172a';
            $_SESSION['ui_theme'] = (($user['ui_theme'] ?? 'light') === 'dark') ? 'dark' : 'light';

            mf_create_backend_session($pdo, (int) $user['user_id'], (string) $tenant['tenant_id'], 'tenant');

            $pdo->prepare("INSERT INTO audit_logs (user_id, tenant_id, action_type, entity_type, description) VALUES (?, ?, 'STAFF_LOGIN', 'user', 'Staff logged into the system')")->execute([$user['user_id'], $tenant['tenant_id']]);

            if ($user['user_type'] === 'Employee') {
                // Differentiate Admin vs Staff based on is_system_role or role_name
                $is_admin = ((bool)$user['is_system_role'] || stripos($user['role_name'], 'Admin') !== false);

                if ($is_admin) {
                    // Admin routing: password → billing → dashboard
                    if (isset($user['force_password_change']) && (bool)$user['force_password_change']) {
                        header('Location: force_change_password.php');
                        exit;
                    }

                    if (!(bool)$tenant['setup_completed']) {
                        $setup_step = (int)($tenant['setup_current_step'] ?? 0);
                        if ($setup_step < 5) {
                            $pdo->prepare('UPDATE tenants SET setup_current_step = 5 WHERE tenant_id = ?')->execute([$tenant['tenant_id']]);
                        }
                        header('Location: setup_billing.php');
                        exit;
                    }

                    header('Location: ' . mf_tenant_admin_dashboard_url());
                    exit;
                } else {
                    // Regular Staff routing
                    if (isset($user['force_password_change']) && (bool)$user['force_password_change']) {
                        header('Location: ../admin_panel/staff/setup_wizard.php');
                        exit;
                    }
                    header('Location: ../admin_panel/staff/dashboard.php');
                    exit;
                }
            } else {
                // Client routing (placeholder, or keep current fallback)
                header('Location: ../admin_panel/admin.php');
                exit;
            }
        }
    }
}


$theme_color = $tenant['theme_primary_color'] ?? '#0f172a';
$theme_text_main = $tenant['theme_text_main'] ?? '#0f172a';
$theme_text_muted = $tenant['theme_text_muted'] ?? '#64748b';
$theme_bg_body = $tenant['theme_bg_body'] ?? '#f8fafc';
$theme_bg_card = $tenant['theme_bg_card'] ?? '#ffffff';
$theme_font = $tenant['font_family'] ?? 'Inter';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($tenant['tenant_name'] ?? 'Login'); ?> - Portal</title>
    
    <?php if (!empty($tenant['logo_path'])): ?>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($tenant['logo_path'], ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=<?php echo urlencode($theme_font); ?>:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Material Symbols -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="../assets/password-toggle.css">
    
    <style>
        :root {
            --brand-color: <?php echo htmlspecialchars($theme_color); ?>;
            --brand-bg: <?php echo htmlspecialchars($theme_bg_body); ?>;
            --card-bg: <?php echo htmlspecialchars($theme_bg_card); ?>;
            --text-main: <?php echo htmlspecialchars($theme_text_main); ?>;
            --text-muted: <?php echo htmlspecialchars($theme_text_muted); ?>;
            --font-family: '<?php echo htmlspecialchars($theme_font); ?>', sans-serif;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: var(--font-family);
        }

        body {
            background-color: var(--brand-bg);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            transition: background-color 0.5s ease;
        }

        .login-container {
            background: var(--card-bg);
            width: 100%;
            max-width: 440px;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);
            padding: 48px 40px;
            position: relative;
            overflow: hidden;
            transition: background 0.5s ease;
        }

        /* Top brand accent bar */
        .login-container::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 6px;
            background: var(--brand-color);
            transition: background 0.5s ease;
        }

        .brand-header {
            text-align: center;
            margin-bottom: 32px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
        }

        .top-actions {
            display: flex;
            justify-content: flex-start;
            margin-bottom: 20px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 999px;
            color: var(--text-main);
            text-decoration: none;
            font-size: 0.92rem;
            font-weight: 600;
            background: rgba(148, 163, 184, 0.12);
            border: 1px solid rgba(148, 163, 184, 0.18);
            transition: transform 0.2s ease, background-color 0.2s ease, border-color 0.2s ease;
        }

        .back-link:hover {
            transform: translateX(-2px);
            background: rgba(148, 163, 184, 0.18);
            border-color: rgba(148, 163, 184, 0.28);
        }

        .brand-logo {
            width: 64px;
            height: 64px;
            background: var(--brand-color);
            color: #ffffff;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.5s ease;
            background-size: cover;
            background-position: center;
        }
        
        .brand-logo .material-symbols-rounded {
            font-size: 32px;
        }

        #company-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-main);
            letter-spacing: -0.5px;
            transition: color 0.5s ease;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-muted);
            transition: color 0.5s ease;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 1rem;
            color: var(--text-main);
            transition: all 0.2s;
            background: rgba(255, 255, 255, 0.5); /* subtle transparency over custom card-bg */
        }

        .form-control:focus {
            outline: none;
            border-color: var(--brand-color);
            box-shadow: 0 0 0 3px rgba(15, 23, 42, 0.1);
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background: var(--brand-color);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 16px;
        }

        .btn-login:hover {
            filter: brightness(1.1);
            transform: translateY(-1px);
        }

        .btn-login:disabled,
        .form-control:disabled {
            cursor: not-allowed;
            opacity: 0.65;
            filter: none;
            transform: none;
        }

        .footer-text {
            text-align: center;
            margin-top: 32px;
            font-size: 0.85rem;
            color: var(--text-muted);
            transition: color 0.5s ease;
        }
        
        .footer-text a {
            color: var(--brand-color);
            text-decoration: none;
            font-weight: 500;
        }

        /* Loading overlay */
        .overlay {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s;
        }

        .overlay.active {
            opacity: 1;
            pointer-events: all;
        }
        
        @keyframes spin { 100% { transform: rotate(360deg); } }
        
        /* Error State for invalid/missing ?tid */
        .error-state {
            display: none;
            text-align: center;
            padding: 20px 0;
        }
        
        .error-state.visible {
            display: block;
        }
        
        .login-form {
            display: block;
        }
        
        .login-form.hidden {
            display: none;
        }
    </style>
</head>
<body>

    <div class="login-container">
        <?php if ($tenant_error !== ''): ?>
        <div id="error-view" class="error-state visible">
            <span class="material-symbols-rounded" style="font-size: 64px; color: #ef4444; margin-bottom: 16px;">gpp_bad</span>
            <h2 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 8px; color: #0f172a;">Invalid Access Link</h2>
            <p style="color: #64748b; font-size: 0.95rem; margin-bottom: 24px;">This login portal requires a secure, Private URL provided by your administrator.</p>
            <p style="color: #94a3b8; font-size: 0.85rem;"><?php echo htmlspecialchars($tenant_error); ?></p>
        </div>
        <?php else: ?>

        <div id="form-view" class="login-form">
            <?php if (isset($_GET['auth']) && $_GET['auth'] == '1' && $login_error === ''): ?>
            <div style="background: #fef2f2; color: #b91c1c; padding: 14px 18px; border-radius: 12px; border: 1px solid #fecaca; margin-bottom: 24px; font-size: 0.92rem; font-weight: 600; display: flex; align-items: center; gap: 12px; box-shadow: 0 2px 4px rgba(185, 28, 28, 0.05);">
                <span class="material-symbols-rounded" style="font-size: 22px;">lock_person</span>
                <span>Please sign in first to access the requested page.</span>
            </div>
            <?php endif; ?>

            <?php if ($back_to_site_href !== ''): ?>
            <div class="top-actions">
                <a href="<?php echo htmlspecialchars($back_to_site_href, ENT_QUOTES, 'UTF-8'); ?>" class="back-link">
                    <span class="material-symbols-rounded" style="font-size: 18px;">arrow_back</span>
                    Back to site
                </a>
            </div>
            <?php endif; ?>

            <div class="brand-header">
                <?php
                // Check local logo based on tenant_id
                $tenant_id_clean = preg_replace('/[^A-Za-z0-9_-]+/', '_', $tenant['tenant_id']);
                $found_logo = false;
                $local_logo_path = '';
                $base_upload_dir = dirname(__DIR__) . '/uploads';
                
                if ($tenant_id_clean !== '') {
                    $possible_logo_exts = ['png', 'jpg', 'jpeg', 'webp', 'svg'];
                    foreach ($possible_logo_exts as $ext) {
                        if (file_exists($base_upload_dir . '/tenant_logos/' . $tenant_id_clean . 'logo.' . $ext)) {
                            $local_logo_path = '../uploads/tenant_logos/' . $tenant_id_clean . 'logo.' . $ext;
                            $found_logo = true;
                            break;
                        }
                    }
                }
                $final_logo_path = $found_logo ? $local_logo_path : ($tenant['logo_path'] ?? '');
                
                if (!empty($final_logo_path)): ?>
                <div class="brand-logo" id="logo-icon-container" style="background-image: url('<?php echo htmlspecialchars($final_logo_path); ?>'); background-color: transparent;">
                </div>
                <?php else: ?>
                <div class="brand-logo" id="logo-icon-container">
                    <span class="material-symbols-rounded" id="logo-icon">account_balance</span>
                </div>
                <?php endif; ?>
                <h1 id="company-name"><?php echo htmlspecialchars($tenant['tenant_name']); ?> Workspace</h1>
            </div>

            <form id="login-form" method="POST" action="login.php?s=<?php echo urlencode($site_slug); ?><?php echo isset($_GET['auth']) ? '&auth=1' : ''; ?><?php echo $came_from_site ? '&from_site=1' : ''; ?>"<?php echo $browser_session_block_message !== '' ? ' onsubmit="return false;"' : ''; ?>>
                <?php if ($login_error !== ''): ?>
                <div style="background-color: #fee2e2; color: #b91c1c; padding: 0.75rem; border-radius: 8px; font-size: 0.875rem; margin-bottom: 1rem; text-align: left;">
                    <?php echo htmlspecialchars($login_error); ?>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label>Staff Email</label>
                    <input type="email" name="email" class="form-control" placeholder="employee@company.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"<?php echo $browser_session_block_message !== '' ? ' disabled' : ''; ?> required>
                </div>
                <div class="form-group">
                    <div style="display: flex; justify-content: space-between;">
                        <label>Password</label>
                        <a href="forgot_password.php?s=<?= htmlspecialchars($site_slug) ?>" style="font-size: 0.85rem; color: #64748b; text-decoration: none;">Forgot?</a>
                    </div>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>

                <button type="submit" class="btn-login" id="submit-btn"<?php echo $browser_session_block_message !== '' ? ' disabled' : ''; ?>>
                    Access Dashboard <span class="material-symbols-rounded" style="font-size: 18px;">arrow_forward</span>
                </button>
            </form>
        </div>
        <?php endif; ?>

        <div class="footer-text">
            Powered securely by <a href="../public_website/index.php">MicroFin</a>
        </div>

        <!-- Loader -->
        <div class="overlay" id="loader">
            <span class="material-symbols-rounded" style="font-size: 40px; color: var(--brand-color); animation: spin 1s linear infinite;">sync</span>
        </div>
    </div>

    <script src="login.js"></script>
    <script src="../assets/password-toggle.js"></script>
</body>
</html>




