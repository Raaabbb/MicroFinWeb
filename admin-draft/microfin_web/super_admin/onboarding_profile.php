<?php
require_once '../../microfin_backend/auth/session_auth.php';
mf_start_backend_session();
require_once '../../microfin_backend/config/db_connect.php';
require_once __DIR__ . '/super_admin_auth.php';
mf_require_super_admin_session($pdo, [
    'response' => 'redirect',
    'redirect' => 'login.php',
]);

$superAdminId = (int)($_SESSION['super_admin_id'] ?? 0);
if ($superAdminId <= 0) {
    header('Location: login.php');
    exit;
}

$superAdmin = sa_load_super_admin_state($pdo, $superAdminId);
if (!$superAdmin) {
    mf_destroy_backend_session($pdo);
    header('Location: login.php');
    exit;
}

sa_sync_super_admin_session_from_state($superAdmin);

if (!empty($_SESSION['super_admin_force_password_change'])) {
    header('Location: force_change_password.php');
    exit;
}

if (!sa_super_admin_requires_onboarding($superAdmin)) {
    header('Location: super_admin.php');
    exit;
}

$platformLogoFile = __DIR__ . '/logo/MicroFin-logo-transparent-temp.png';
$platformLogoUrl = '../public_website/logo/MicroFin-logo-transparent-temp.png?v=' . urlencode((string) @filemtime($platformLogoFile));

$provisionalUsername = sa_generate_unique_platform_username(
    $pdo,
    '',
    (string)($superAdmin['email'] ?? ''),
    '',
    '',
    $superAdminId
);
$initialUsername = (string)($superAdmin['username'] ?? '');
if ($initialUsername === $provisionalUsername) {
    $initialUsername = '';
}

function sa_onboarding_is_valid_date(string $value): bool
{
    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date instanceof DateTime && $date->format('Y-m-d') === $value;
}

$form = [
    'username' => $initialUsername,
    'first_name' => (string)($superAdmin['first_name'] ?? ''),
    'last_name' => (string)($superAdmin['last_name'] ?? ''),
    'middle_name' => (string)($superAdmin['middle_name'] ?? ''),
    'suffix' => (string)($superAdmin['suffix'] ?? ''),
    'phone_number' => (string)($superAdmin['phone_number'] ?? ''),
    'date_of_birth' => (string)($superAdmin['date_of_birth'] ?? ''),
];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['username'] = trim((string)($_POST['username'] ?? ''));
    $form['first_name'] = trim((string)($_POST['first_name'] ?? ''));
    $form['last_name'] = trim((string)($_POST['last_name'] ?? ''));
    $form['middle_name'] = trim((string)($_POST['middle_name'] ?? ''));
    $form['suffix'] = trim((string)($_POST['suffix'] ?? ''));
    $form['phone_number'] = trim((string)($_POST['phone_number'] ?? ''));
    $form['date_of_birth'] = trim((string)($_POST['date_of_birth'] ?? ''));

    $requestedUsername = $form['username'];
    $sanitizedUsername = $requestedUsername === '' ? '' : sa_sanitize_platform_username($requestedUsername);

    if ($form['first_name'] === '' || $form['last_name'] === '' || $form['phone_number'] === '' || $form['date_of_birth'] === '') {
        $error = 'First name, last name, phone number, and date of birth are required.';
    } elseif ($requestedUsername !== '' && $sanitizedUsername === '') {
        $error = 'Username can only contain letters, numbers, dots, underscores, or hyphens.';
    } elseif (!sa_onboarding_is_valid_date($form['date_of_birth'])) {
        $error = 'Please provide a valid date of birth.';
    } elseif ($sanitizedUsername !== '' && sa_platform_username_exists($pdo, $sanitizedUsername, $superAdminId)) {
        $error = 'That username is already being used by another platform admin.';
    } else {
        $resolvedUsername = $sanitizedUsername !== ''
            ? $sanitizedUsername
            : sa_generate_unique_platform_username(
                $pdo,
                '',
                (string)($superAdmin['email'] ?? ''),
                $form['first_name'],
                $form['last_name'],
                $superAdminId
            );

        $updateStmt = $pdo->prepare("
            UPDATE users
            SET username = ?,
                first_name = ?,
                last_name = ?,
                middle_name = ?,
                suffix = ?,
                phone_number = ?,
                date_of_birth = ?,
                status = 'Active'
            WHERE user_id = ?
        ");

        if ($updateStmt->execute([
            $resolvedUsername,
            $form['first_name'],
            $form['last_name'],
            $form['middle_name'] !== '' ? $form['middle_name'] : null,
            $form['suffix'] !== '' ? $form['suffix'] : null,
            $form['phone_number'],
            $form['date_of_birth'],
            $superAdminId,
        ])) {
            $logStmt = $pdo->prepare("
                INSERT INTO audit_logs (user_id, action_type, entity_type, description)
                VALUES (?, 'SUPER_ADMIN_ONBOARDING_COMPLETED', 'user', ?)
            ");
            $logStmt->execute([$superAdminId, 'Super admin completed initial profile onboarding']);

            $superAdmin = sa_load_super_admin_state($pdo, $superAdminId);
            if ($superAdmin) {
                sa_sync_super_admin_session_from_state($superAdmin);
            } else {
                $_SESSION['super_admin_onboarding_required'] = false;
                $_SESSION['super_admin_username'] = $resolvedUsername;
            }

            header('Location: super_admin.php');
            exit;
        }

        $error = 'Unable to save your onboarding details. Please try again.';
    }
}

?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars((string)($_SESSION['ui_theme'] ?? 'light'), ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MicroFin | Complete Super Admin Profile</title>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($platformLogoUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($platformLogoUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="super_admin_theme.css">
    <link rel="stylesheet" href="super_admin_auth.css">
</head>
<body class="platform-auth auth-wide">
    <button type="button" class="auth-theme-toggle" id="auth-theme-toggle" aria-label="Switch to dark mode">Dark mode</button>
    <div class="panel">
        <div class="eyebrow">Final Onboarding Step</div>
        <h1>Complete Your Admin Profile</h1>
        <p>
            Your password is already secured. Finish these account details to activate your super admin access and continue to the dashboard.
        </p>

        <?php if ($error !== ''): ?>
            <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="grid">
                <div class="field field-full">
                    <label for="email_display">Email Address</label>
                    <input type="email" id="email_display" value="<?php echo htmlspecialchars((string)($superAdmin['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" readonly>
                    <div class="hint">This login email was already assigned when your account was created.</div>
                </div>

                <div class="field">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($form['username'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Optional">
                    <div class="hint">Optional. Leave it blank to use the first word of your first name as the username.</div>
                </div>

                <div class="field">
                    <label for="phone_number">Phone Number<span class="required-mark">*</span></label>
                    <input type="text" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($form['phone_number'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <div class="field">
                    <label for="first_name">First Name<span class="required-mark">*</span></label>
                    <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($form['first_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <div class="field">
                    <label for="last_name">Last Name<span class="required-mark">*</span></label>
                    <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($form['last_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <div class="field">
                    <label for="middle_name">Middle Name</label>
                    <input type="text" id="middle_name" name="middle_name" value="<?php echo htmlspecialchars($form['middle_name'], ENT_QUOTES, 'UTF-8'); ?>">
                </div>

                <div class="field">
                    <label for="suffix">Suffix</label>
                    <input type="text" id="suffix" name="suffix" value="<?php echo htmlspecialchars($form['suffix'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Optional">
                </div>

                <div class="field">
                    <label for="date_of_birth">Date of Birth<span class="required-mark">*</span></label>
                    <input type="date" id="date_of_birth" name="date_of_birth" value="<?php echo htmlspecialchars($form['date_of_birth'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
            </div>

            <div class="actions">
                <button type="submit">Activate Account</button>
            </div>
        </form>
    </div>
    <script src="login.js"></script>
</body>
</html>

