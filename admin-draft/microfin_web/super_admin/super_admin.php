<?php
require_once '../../microfin_backend/auth/session_auth.php';
mf_start_backend_session();
require_once '../../microfin_backend/config/db_connect.php';
require_once '../../microfin_backend/billing/billing_access.php';
require_once '../../microfin_backend/billing/lazy_billing_resolver.php';
require_once '../../microfin_backend/billing/billing_notifications.php';
require_once '../../microfin_backend/auth/login_activity.php';
require_once __DIR__ . '/super_admin_auth.php';
mf_require_super_admin_session($pdo, [
    'response' => 'redirect',
    'redirect' => 'login.php',
]);

require_once '../../microfin_backend/auth/tenant_identity.php';

// Resolve any pending tenant subscriptions automagically!
resolve_tenant_billing($pdo);

$superAdminState = sa_load_super_admin_state($pdo, (int)($_SESSION['super_admin_id'] ?? 0));
if (!$superAdminState) {
    mf_destroy_backend_session($pdo);
    header('Location: login.php');
    exit;
}

sa_sync_super_admin_session_from_state($superAdminState);
$avatarBackground = '1F8A5A';

if (!empty($_SESSION['super_admin_force_password_change'])) {
    header('Location: force_change_password.php');
    exit;
}

if (!empty($_SESSION['super_admin_onboarding_required'])) {
    header('Location: onboarding_profile.php');
    exit;
}

function sa_column_exists(PDO $pdo, $table, $column)
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $safe_column = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $column);
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE '{$safe_column}'");
    $stmt->execute();
    $cache[$key] = (bool) $stmt->fetch();
    return $cache[$key];
}

function sa_plan_rank(string $planTier): int
{
    static $planRankMap = [
        'Starter' => 1,
        'Enterprise' => 2,
    ];

    return $planRankMap[$planTier] ?? 0;
}

function sa_normalize_billing_cycle(string $billingCycle): string
{
    $normalized = trim($billingCycle);
    if (!in_array($normalized, ['Monthly', 'Quarterly', 'Yearly'], true)) {
        return 'Monthly';
    }
    return $normalized;
}

function sa_compute_next_billing_date(string $billingCycle, ?string $baseDate = null): string
{
    $cycle = sa_normalize_billing_cycle($billingCycle);
    $base = null;

    if ($baseDate !== null && trim($baseDate) !== '') {
        $parsed = DateTime::createFromFormat('Y-m-d', $baseDate);
        if ($parsed instanceof DateTime) {
            $base = $parsed;
        }
    }

    if (!$base) {
        $base = new DateTime('today');
    }

    if ($cycle === 'Yearly') {
        $base->modify('+1 year');
    } elseif ($cycle === 'Quarterly') {
        $base->modify('+3 months');
    } else {
        $base->modify('+30 days');
    }

    return $base->format('Y-m-d');
}

function sa_ensure_platform_role(PDO $pdo, string $roleName, string $roleDescription): int
{
    $activeRoleStmt = $pdo->prepare("
        SELECT role_id
        FROM user_roles
        WHERE tenant_id IS NULL
          AND role_name = ?
          AND deleted_at IS NULL
        ORDER BY role_id ASC
        LIMIT 1
    ");
    $activeRoleStmt->execute([$roleName]);
    $activeRoleId = (int) $activeRoleStmt->fetchColumn();
    if ($activeRoleId > 0) {
        return $activeRoleId;
    }

    $anyRoleStmt = $pdo->prepare("
        SELECT role_id
        FROM user_roles
        WHERE tenant_id IS NULL
          AND role_name = ?
        ORDER BY role_id ASC
        LIMIT 1
    ");
    $anyRoleStmt->execute([$roleName]);
    $existingRoleId = (int) $anyRoleStmt->fetchColumn();
    if ($existingRoleId > 0) {
        $restoreRoleStmt = $pdo->prepare("
            UPDATE user_roles
            SET role_description = ?,
                is_system_role = TRUE,
                deleted_at = NULL,
                deleted_by = NULL
            WHERE role_id = ?
        ");
        $restoreRoleStmt->execute([$roleDescription, $existingRoleId]);
        return $existingRoleId;
    }

    $insertRoleStmt = $pdo->prepare("
        INSERT INTO user_roles (tenant_id, role_name, role_description, is_system_role)
        VALUES (NULL, ?, ?, TRUE)
    ");
    $insertRoleStmt->execute([$roleName, $roleDescription]);
    return (int) $pdo->lastInsertId();
}

function sa_generate_temporary_password(int $length = 12): string
{
    $length = max(8, $length);
    $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $lower = 'abcdefghijkmnopqrstuvwxyz';
    $digits = '23456789';
    $symbols = '!@#$%^&*_-+=?';
    $all = $upper . $lower . $digits . $symbols;

    $passwordChars = [
        $upper[random_int(0, strlen($upper) - 1)],
        $lower[random_int(0, strlen($lower) - 1)],
        $digits[random_int(0, strlen($digits) - 1)],
        $symbols[random_int(0, strlen($symbols) - 1)],
    ];

    while (count($passwordChars) < $length) {
        $passwordChars[] = $all[random_int(0, strlen($all) - 1)];
    }

    for ($i = count($passwordChars) - 1; $i > 0; $i--) {
        $swapIndex = random_int(0, $i);
        [$passwordChars[$i], $passwordChars[$swapIndex]] = [$passwordChars[$swapIndex], $passwordChars[$i]];
    }

    return implode('', $passwordChars);
}

function sa_is_railway_runtime(): bool
{
    $keys = [
        'RAILWAY_ENVIRONMENT',
        'RAILWAY_PROJECT_ID',
        'RAILWAY_SERVICE_ID',
        'RAILWAY_PUBLIC_DOMAIN',
        'RAILWAY_STATIC_URL',
    ];
    foreach ($keys as $key) {
        $value = getenv($key);
        if ($value !== false && trim((string)$value) !== '') {
            return true;
        }
    }
    return false;
}

function sa_normalize_app_base_url(string $baseUrl): string
{
    $baseUrl = rtrim(trim($baseUrl), '/');
    if ($baseUrl === '') {
        return '';
    }

    $path = trim((string) (parse_url($baseUrl, PHP_URL_PATH) ?? ''));
    if ($path === '' || $path === '/') {
        return $baseUrl . '/admin-draft/microfin_web';
    }

    if (!preg_match('~(?:^|/)admin-draft/microfin_web/?$~i', $path)) {
        return $baseUrl . '/admin-draft/microfin_web';
    }

    return $baseUrl;
}

function sa_resolve_app_base_url(): string
{
    $defaultScript = '/admin-draft-withmobile/admin-draft/microfin_web/super_admin/super_admin.php';
    $basePath = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['PHP_SELF'] ?? $defaultScript))), '/\\');
    $explicitBase = trim((string) (getenv('APP_BASE_URL') ?: getenv('PUBLIC_BASE_URL') ?: ''));

    if (sa_is_railway_runtime()) {
        $railwayBase = trim((string) (getenv('RAILWAY_STATIC_URL') ?: getenv('RAILWAY_PUBLIC_DOMAIN') ?: ''));
        if ($railwayBase !== '') {
            if (!preg_match('~^https?://~i', $railwayBase)) {
                $railwayBase = 'https://' . $railwayBase;
            }
            return sa_normalize_app_base_url($railwayBase);
        }

        return 'https://microfinweb-production.up.railway.app/admin-draft/microfin_web';
    }

    if ($explicitBase !== '') {
        return sa_normalize_app_base_url($explicitBase);
    }

    $requestHost = trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    return 'http://' . $requestHost . $basePath;
}

function sa_build_tenant_login_url(string $tenantSlug): string
{
    $tenantSlug = trim($tenantSlug);
    $safeSlug = urlencode($tenantSlug);
    return sa_resolve_app_base_url() . '/tenant_login/login.php?s=' . $safeSlug;
}

function sa_build_super_admin_login_url(): string
{
    return sa_resolve_app_base_url() . '/super_admin/login.php';
}

function sa_get_tenant_contact(PDO $pdo, string $tenantId): ?array
{
    if (function_exists('mf_billing_get_contact')) {
        $contact = mf_billing_get_contact($pdo, $tenantId);
        if (is_array($contact) && trim((string)($contact['email'] ?? '')) !== '') {
            return $contact;
        }
    }

    $stmt = $pdo->prepare("
        SELECT
            t.tenant_name,
            u.email,
            u.username,
            u.first_name,
            u.last_name
        FROM tenants t
        LEFT JOIN users u
            ON u.tenant_id = t.tenant_id
           AND u.deleted_at IS NULL
           AND TRIM(COALESCE(u.email, '')) <> ''
        WHERE t.tenant_id = ?
          AND t.deleted_at IS NULL
        ORDER BY
            CASE WHEN u.user_type = 'Admin' THEN 0 ELSE 1 END,
            CASE WHEN u.status = 'Active' THEN 0 ELSE 1 END,
            u.user_id ASC
        LIMIT 1
    ");
    $stmt->execute([$tenantId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function sa_send_tenant_deactivation_email(PDO $pdo, string $tenantId, string $reason): string
{
    if (!function_exists('mf_send_brevo_email')) {
        return 'Brevo email helper is unavailable.';
    }

    $contact = sa_get_tenant_contact($pdo, $tenantId);
    if (!$contact || trim((string)($contact['email'] ?? '')) === '') {
        return 'No tenant contact email found.';
    }

    $tenantNameRaw = trim((string)($contact['tenant_name'] ?? 'MicroFin Tenant'));
    $tenantName = htmlspecialchars($tenantNameRaw, ENT_QUOTES, 'UTF-8');
    $recipientName = function_exists('mf_billing_contact_name')
        ? mf_billing_contact_name($contact)
        : trim((string)($contact['first_name'] ?? '') . ' ' . (string)($contact['last_name'] ?? ''));
    $recipientName = $recipientName !== '' ? $recipientName : 'Customer';
    $recipientName = htmlspecialchars($recipientName, ENT_QUOTES, 'UTF-8');
    $reasonHtml = nl2br(htmlspecialchars($reason, ENT_QUOTES, 'UTF-8'));
    $noticeDate = htmlspecialchars(date('F j, Y g:i A'), ENT_QUOTES, 'UTF-8');

    $html = mf_email_template([
        'accent' => '#b91c1c',
        'eyebrow' => 'Account Status Update',
        'title' => 'Workspace Temporarily Deactivated',
        'preheader' => "{$tenantNameRaw} has been temporarily deactivated.",
        'intro_html' => "
            <p style=\"margin: 0 0 14px; font-family: Arial, sans-serif; font-size: 15px; line-height: 1.7; color: #334155;\">
                Hello {$recipientName},
            </p>
            <p style=\"margin: 0 0 14px; font-family: Arial, sans-serif; font-size: 15px; line-height: 1.7; color: #334155;\">
                Your <strong>{$tenantName}</strong> workspace has been temporarily deactivated by the MicroFin platform team.
            </p>
        ",
        'body_html' => mf_email_panel(
            'Deactivation Details',
            mf_email_detail_table([
                ['label' => 'Status', 'value' => 'Suspended'],
                ['label' => 'Effective date', 'value' => $noticeDate],
                ['label' => 'Reason provided', 'value' => "<div style=\"padding: 12px 14px; background: #ffffff; border: 1px solid #fecaca; border-radius: 12px;\">{$reasonHtml}</div>", 'html' => true],
            ]),
            'danger'
        ) . "
            <p style=\"margin: 0 0 10px; font-family: Arial, sans-serif; font-size: 15px; line-height: 1.7; color: #334155;\">
                Dashboard access will remain unavailable until your workspace is reactivated.
            </p>
            <p style=\"margin: 0; font-family: Arial, sans-serif; font-size: 15px; line-height: 1.7; color: #334155;\">
                If you need help or believe this was done in error, you may reply to this email.
            </p>
        ",
    ]);

    return mf_send_brevo_email((string)$contact['email'], "{$tenantNameRaw} - Account Deactivation Notice", $html);
}

$provision_success = '';
$provision_error = '';

// Read flash messages from session (PRG pattern)
if (isset($_SESSION['sa_flash'])) {
    $provision_success = $_SESSION['sa_flash'];
    unset($_SESSION['sa_flash']);
}
if (isset($_SESSION['sa_error'])) {
    $provision_error = $_SESSION['sa_error'];
    unset($_SESSION['sa_error']);
}

$settings_tab = trim((string)($_GET['settings_tab'] ?? 'settings-accounts'));
if (!in_array($settings_tab, ['settings-accounts', 'settings-profile'], true)) {
    $settings_tab = 'settings-accounts';
}

$profile_form = [
    'username' => trim((string)($superAdminState['username'] ?? '')),
    'email' => trim((string)($superAdminState['email'] ?? '')),
    'first_name' => trim((string)($superAdminState['first_name'] ?? '')),
    'last_name' => trim((string)($superAdminState['last_name'] ?? '')),
    'middle_name' => trim((string)($superAdminState['middle_name'] ?? '')),
    'suffix' => trim((string)($superAdminState['suffix'] ?? '')),
    'phone_number' => trim((string)($superAdminState['phone_number'] ?? '')),
    'date_of_birth' => trim((string)($superAdminState['date_of_birth'] ?? '')),
];

if (isset($_SESSION['sa_profile_form']) && is_array($_SESSION['sa_profile_form'])) {
    foreach ($profile_form as $key => $defaultValue) {
        if (array_key_exists($key, $_SESSION['sa_profile_form'])) {
            $profile_form[$key] = trim((string)$_SESSION['sa_profile_form'][$key]);
        }
    }
    unset($_SESSION['sa_profile_form']);
    $settings_tab = 'settings-profile';
}

$profile_display_parts = array_filter([
    $profile_form['first_name'],
    $profile_form['middle_name'],
    $profile_form['last_name'],
    $profile_form['suffix'],
], static fn ($value) => trim((string)$value) !== '');
$profile_display_name = trim(implode(' ', $profile_display_parts));
if ($profile_display_name === '') {
    $profile_display_name = $profile_form['username'] !== '' ? $profile_form['username'] : 'Super Admin';
}

$profile_initial_parts = [];
foreach ([$profile_form['first_name'], $profile_form['last_name']] as $namePart) {
    $cleanNamePart = trim((string)$namePart);
    if ($cleanNamePart !== '') {
        $profile_initial_parts[] = strtoupper(substr($cleanNamePart, 0, 1));
    }
}
if (empty($profile_initial_parts)) {
    $profile_username_seed = trim((string)$profile_form['username']);
    $profile_initial_parts[] = $profile_username_seed !== ''
        ? strtoupper(substr($profile_username_seed, 0, 1))
        : 'S';
    $profile_initial_parts[] = $profile_username_seed !== '' && strlen($profile_username_seed) > 1
        ? strtoupper(substr($profile_username_seed, 1, 1))
        : 'A';
}
$profile_initials = implode('', array_slice($profile_initial_parts, 0, 2));
$profile_username_badge = $profile_form['username'] !== ''
    ? '@' . ltrim($profile_form['username'], '@')
    : 'No username set';
$profile_email_badge = $profile_form['email'] !== ''
    ? $profile_form['email']
    : 'No sign-in email set';

$plan_pricing_map = [
    'Starter' => 4999.00,
    'Enterprise' => 14999.00,
];

$plan_limits_map = [
    'Starter' => ['clients' => 2000, 'users' => 1000],
    'Enterprise' => ['clients' => -1, 'users' => -1],
];

function sa_super_admin_profile_date_is_valid(string $value): bool
{
    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date instanceof DateTime && $date->format('Y-m-d') === $value;
}

try {
    $pdo->exec("ALTER TABLE tenants ADD COLUMN request_type ENUM('tenant_application', 'talk_to_expert') NOT NULL DEFAULT 'tenant_application' AFTER status");
} catch (Throwable $e) {
}
try {
    $pdo->exec("ALTER TABLE tenants ADD COLUMN assigned_expert_user_id INT NULL AFTER request_type");
} catch (Throwable $e) {
}
try {
    $pdo->exec("ALTER TABLE tenants ADD INDEX idx_request_type_status (request_type, status)");
} catch (Throwable $e) {
}
try {
    $pdo->exec("ALTER TABLE tenants ADD INDEX idx_request_type_created_at (request_type, created_at)");
} catch (Throwable $e) {
}
try {
    $pdo->exec("ALTER TABLE tenants ADD INDEX idx_assigned_expert_user (assigned_expert_user_id)");
} catch (Throwable $e) {
}
try {
    $pdo->exec("ALTER TABLE tenants ADD COLUMN billing_cycle ENUM('Monthly', 'Quarterly', 'Yearly') DEFAULT 'Monthly' AFTER mrr");
} catch (Throwable $e) {
}
try {
    $pdo->exec("ALTER TABLE tenants ADD COLUMN next_billing_date DATE NULL AFTER billing_cycle");
} catch (Throwable $e) {
}
try {
    $pdo->exec("ALTER TABLE tenants ADD COLUMN scheduled_plan_tier VARCHAR(50) NULL AFTER plan_tier");
} catch (Throwable $e) {
}
try {
    $pdo->exec("ALTER TABLE tenants ADD COLUMN scheduled_plan_effective_date DATE NULL AFTER scheduled_plan_tier");
} catch (Throwable $e) {
}
try {
    $pdo->exec("ALTER TABLE tenants ADD COLUMN concern_category VARCHAR(150) NULL AFTER request_type");
} catch (Throwable $e) {
}

try {
    $pdo->exec("
        UPDATE tenants
        SET next_billing_date = CASE billing_cycle
            WHEN 'Yearly' THEN DATE_ADD(CURDATE(), INTERVAL 1 YEAR)
            WHEN 'Quarterly' THEN DATE_ADD(CURDATE(), INTERVAL 3 MONTH)
            ELSE DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        END
        WHERE deleted_at IS NULL
          AND (next_billing_date IS NULL OR next_billing_date = '0000-00-00')
    ");
} catch (Throwable $e) {
}

// Apply any scheduled plan changes that are due today.
try {
    $dueChangesStmt = $pdo->query("
        SELECT tenant_id, tenant_name, billing_cycle, scheduled_plan_tier, scheduled_plan_effective_date
        FROM tenants
        WHERE deleted_at IS NULL
          AND scheduled_plan_tier IS NOT NULL
          AND scheduled_plan_effective_date IS NOT NULL
          AND scheduled_plan_effective_date <= CURDATE()
    ");
    $dueChanges = $dueChangesStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($dueChanges)) {
        $applyStmt = $pdo->prepare("
            UPDATE tenants
            SET plan_tier = ?,
                mrr = ?,
                max_clients = ?,
                max_users = ?,
                next_billing_date = ?,
                scheduled_plan_tier = NULL,
                scheduled_plan_effective_date = NULL
            WHERE tenant_id = ?
        ");
        $logStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, description, tenant_id) VALUES (?, 'TENANT_PLAN_APPLIED', 'tenant', ?, ?)");

        foreach ($dueChanges as $changeRow) {
            $scheduledPlan = trim((string)($changeRow['scheduled_plan_tier'] ?? ''));
            if ($scheduledPlan === '' || !isset($plan_pricing_map[$scheduledPlan], $plan_limits_map[$scheduledPlan])) {
                continue;
            }

            $billingCycle = sa_normalize_billing_cycle((string)($changeRow['billing_cycle'] ?? 'Monthly'));
            $effectiveDate = (string)($changeRow['scheduled_plan_effective_date'] ?? '');
            $nextBillingDate = sa_compute_next_billing_date($billingCycle, $effectiveDate);
            $limits = $plan_limits_map[$scheduledPlan];

            $applyStmt->execute([
                $scheduledPlan,
                $plan_pricing_map[$scheduledPlan],
                $limits['clients'],
                $limits['users'],
                $nextBillingDate,
                (string)$changeRow['tenant_id'],
            ]);

            $actorId = (int)($_SESSION['super_admin_id'] ?? 0);
            $logStmt->execute([
                $actorId > 0 ? $actorId : null,
                "Scheduled plan applied: {$scheduledPlan} (effective {$effectiveDate})",
                (string)$changeRow['tenant_id'],
            ]);
        }
    }
} catch (Throwable $e) {
    error_log('Subscription schedule apply warning: ' . $e->getMessage());
}

if (isset($_GET['action']) && $_GET['action'] === 'get_chat_messages') {
    $tenant_id = $_GET['tenant_id'] ?? '';
    header('Content-Type: application/json');
    if ($tenant_id) {
        $stmt = $pdo->prepare("SELECT * FROM chat_messages WHERE tenant_id = ? ORDER BY created_at ASC");
        $stmt->execute([$tenant_id]);
        
        // Mark as read when super admin opens it
        $update = $pdo->prepare("UPDATE chat_messages SET is_read = 1 WHERE tenant_id = ? AND receiver_id = ?");
        $update->execute([$tenant_id, $_SESSION['super_admin_id'] ?? 0]);
        
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } else {
        echo json_encode([]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_super_admin_profile') {
        $superAdminId = (int)($_SESSION['super_admin_id'] ?? 0);
        $profileFormInput = [
            'username' => trim((string)($_POST['profile_username'] ?? '')),
            'email' => trim((string)($_POST['profile_email'] ?? '')),
            'first_name' => trim((string)($_POST['profile_first_name'] ?? '')),
            'last_name' => trim((string)($_POST['profile_last_name'] ?? '')),
            'middle_name' => trim((string)($_POST['profile_middle_name'] ?? '')),
            'suffix' => trim((string)($_POST['profile_suffix'] ?? '')),
            'phone_number' => trim((string)($_POST['profile_phone_number'] ?? '')),
            'date_of_birth' => trim((string)($_POST['profile_date_of_birth'] ?? '')),
        ];

        $_SESSION['sa_profile_form'] = $profileFormInput;

        $normalizedUsername = strtolower($profileFormInput['username']);
        $normalizedEmail = strtolower($profileFormInput['email']);

        if ($superAdminId <= 0) {
            $_SESSION['sa_error'] = 'Unable to determine which super admin profile to update.';
        } elseif ($normalizedUsername === '') {
            $_SESSION['sa_error'] = 'Username is required.';
        } elseif (!preg_match('/^[a-zA-Z0-9._@-]+$/', $normalizedUsername)) {
            $_SESSION['sa_error'] = 'Username can only contain letters, numbers, dots, underscores, hyphens, or @.';
        } elseif (strlen($normalizedUsername) > 50) {
            $_SESSION['sa_error'] = 'Username must be 50 characters or fewer.';
        } elseif ($normalizedEmail === '' || !filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['sa_error'] = 'Please provide a valid email address.';
        } elseif ($profileFormInput['first_name'] === '' || $profileFormInput['last_name'] === '' || $profileFormInput['phone_number'] === '' || $profileFormInput['date_of_birth'] === '') {
            $_SESSION['sa_error'] = 'First name, last name, phone number, and date of birth are required.';
        } elseif (!sa_super_admin_profile_date_is_valid($profileFormInput['date_of_birth'])) {
            $_SESSION['sa_error'] = 'Please provide a valid date of birth.';
        } elseif (sa_platform_username_exists($pdo, $normalizedUsername, $superAdminId)) {
            $_SESSION['sa_error'] = 'That username is already being used by another platform admin.';
        } else {
            $emailCheckStmt = $pdo->prepare("
                SELECT 1
                FROM users
                WHERE tenant_id IS NULL
                  AND deleted_at IS NULL
                  AND email = ?
                  AND user_id <> ?
                LIMIT 1
            ");
            $emailCheckStmt->execute([$normalizedEmail, $superAdminId]);

            if ($emailCheckStmt->fetchColumn()) {
                $_SESSION['sa_error'] = 'That email address is already being used by another platform admin.';
            } else {
                try {
                    $updateProfileStmt = $pdo->prepare("
                        UPDATE users
                        SET username = ?,
                            email = ?,
                            first_name = ?,
                            last_name = ?,
                            middle_name = ?,
                            suffix = ?,
                            phone_number = ?,
                            date_of_birth = ?
                        WHERE user_id = ?
                          AND user_type = 'Super Admin'
                          AND deleted_at IS NULL
                    ");
                    $updateProfileStmt->execute([
                        $normalizedUsername,
                        $normalizedEmail,
                        $profileFormInput['first_name'],
                        $profileFormInput['last_name'],
                        $profileFormInput['middle_name'] !== '' ? $profileFormInput['middle_name'] : null,
                        $profileFormInput['suffix'] !== '' ? $profileFormInput['suffix'] : null,
                        $profileFormInput['phone_number'],
                        $profileFormInput['date_of_birth'],
                        $superAdminId,
                    ]);

                    $profileLogStmt = $pdo->prepare("
                        INSERT INTO audit_logs (user_id, action_type, entity_type, description)
                        VALUES (?, 'SUPER_ADMIN_PROFILE_UPDATED', 'user', ?)
                    ");
                    $profileLogStmt->execute([
                        $superAdminId,
                        'Updated personal profile details from the Super Admin settings page',
                    ]);

                    $updatedSuperAdminState = sa_load_super_admin_state($pdo, $superAdminId);
                    if ($updatedSuperAdminState) {
                        sa_sync_super_admin_session_from_state($updatedSuperAdminState);
                    }

                    unset($_SESSION['sa_profile_form']);
                    $_SESSION['sa_flash'] = 'Personal profile updated successfully.';
                } catch (Throwable $e) {
                    $_SESSION['sa_error'] = 'Unable to update your personal profile right now.';
                }
            }
        }

        header('Location: super_admin.php?section=settings&settings_tab=settings-profile');
        exit;
    } elseif ($action === 'provision_tenant') {
        if (isset($_POST['tenant_id']) && trim((string) $_POST['tenant_id']) !== '') {
            $_SESSION['sa_error'] = 'Tenant ID is system-generated and cannot be set manually.';
            header('Location: super_admin.php?section=tenants');
            exit;
        }

        $tenant_name = trim($_POST['tenant_name'] ?? '');
        $admin_email = trim($_POST['admin_email'] ?? '');
        $custom_slug = trim($_POST['custom_slug'] ?? '');
        $request_type = trim((string)($_POST['request_type'] ?? 'tenant_application'));
        if (!in_array($request_type, ['tenant_application', 'talk_to_expert'], true)) {
            $request_type = 'tenant_application';
        }
        $plan_tier = trim($_POST['plan_tier'] ?? 'Starter');
        if (!array_key_exists($plan_tier, $plan_pricing_map)) {
            $plan_tier = 'Starter';
        }

        $max_c = $plan_limits_map[$plan_tier]['clients'];
        $max_u = $plan_limits_map[$plan_tier]['users'];
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $mi = trim($_POST['mi'] ?? '');
        $suffix = trim($_POST['suffix'] ?? '');
        $company_address = trim($_POST['company_address'] ?? '');

        $slug_source = $custom_slug !== '' ? $custom_slug : $tenant_name;
        $base_tenant_slug = mf_normalize_tenant_slug($slug_source);
        if ($base_tenant_slug === '') {
            $base_tenant_slug = 'tenant';
        }

        $mrr = $plan_pricing_map[$plan_tier];

        if ($tenant_name === '' || $admin_email === '') {
            $_SESSION['sa_error'] = 'Institution name and admin email are required.';
            header('Location: super_admin.php?section=tenants');
            exit;
        } else {
            $base_admin_username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string)$first_name)) . 'Admin';
            if ($base_admin_username === 'Admin') {
                $base_admin_username = 'tenantadmin';
            }

            $existing_check = $pdo->prepare("SELECT tenant_id, request_type FROM tenants WHERE tenant_name = ? AND status IN ('Pending', 'Contacted', 'New', 'In Contact') LIMIT 1");
            $existing_check->execute([$tenant_name]);
            $existing = $existing_check->fetch();

            if ($existing) {
                $tenant_id = (string) $existing['tenant_id'];
                $existing_request_type = (string)($existing['request_type'] ?? 'tenant_application');
                $tenant_slug = mf_generate_unique_tenant_slug($pdo, $base_tenant_slug, $tenant_id);
                $update = $pdo->prepare("UPDATE tenants SET tenant_slug = ?, company_address = ?, status = 'Active', plan_tier = ?, mrr = ?, max_clients = ?, max_users = ?, onboarding_deadline = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE tenant_id = ?");
            $update->execute([$tenant_slug, $company_address, $plan_tier, $mrr, $max_c, $max_u, $tenant_id]);
            } else {
                $existing_request_type = $request_type;
                $tenant_id = mf_generate_tenant_id($pdo, 10);
                $tenant_slug = mf_generate_unique_tenant_slug($pdo, $base_tenant_slug);
                $insert = $pdo->prepare("INSERT INTO tenants (tenant_id, tenant_name, tenant_slug, company_address, status, request_type, plan_tier, mrr, max_clients, max_users, onboarding_deadline) VALUES (?, ?, ?, ?, 'Active', ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))");
                $insert->execute([$tenant_id, $tenant_name, $tenant_slug, $company_address, $request_type, $plan_tier, $mrr, $max_c, $max_u]);
            }

            $existing_role_stmt = $pdo->prepare("SELECT role_id FROM user_roles WHERE tenant_id = ? AND role_name = 'Admin' LIMIT 1");
            $existing_role_stmt->execute([$tenant_id]);
            $new_role_id = (int)$existing_role_stmt->fetchColumn();
            if ($new_role_id <= 0) {
                $role_insert = $pdo->prepare("INSERT INTO user_roles (tenant_id, role_name, role_description, is_system_role) VALUES (?, 'Admin', 'Default system administrator', TRUE)");
                $role_insert->execute([$tenant_id]);
                $new_role_id = (int)$pdo->lastInsertId();
            }

            $temp_password = sa_generate_temporary_password(12);
            $password_hash = password_hash($temp_password, PASSWORD_DEFAULT);

            $admin_username = $base_admin_username;
            $username_counter = 2;
            while (true) {
                $username_check = $pdo->prepare('SELECT 1 FROM users WHERE tenant_id = ? AND username = ? LIMIT 1');
                $username_check->execute([$tenant_id, $admin_username]);
                if (!$username_check->fetchColumn()) {
                    break;
                }
                $admin_username = $base_admin_username . $username_counter;
                $username_counter++;
            }

            $existing_admin_user_stmt = $pdo->prepare("SELECT user_id FROM users WHERE tenant_id = ? AND email = ? LIMIT 1");
            $existing_admin_user_stmt->execute([$tenant_id, $admin_email]);
            $existing_admin_user_id = (int)$existing_admin_user_stmt->fetchColumn();
            $is_new_user = ($existing_admin_user_id === 0);
            $users_has_billing_column = sa_column_exists($pdo, 'users', 'can_manage_billing');

            if ($existing_admin_user_id > 0) {
                if ($users_has_billing_column) {
                    $user_update = $pdo->prepare("UPDATE users SET password_hash = ?, force_password_change = TRUE, role_id = ?, user_type = 'Admin', status = 'Active', can_manage_billing = 1, deleted_at = NULL WHERE user_id = ?");
                } else {
                    $user_update = $pdo->prepare("UPDATE users SET password_hash = ?, force_password_change = TRUE, role_id = ?, user_type = 'Admin', status = 'Active', deleted_at = NULL WHERE user_id = ?");
                }
                $user_update->execute([
                    $password_hash,
                    $new_role_id,
                    $existing_admin_user_id
                ]);
                mf_set_user_billing_access($pdo, (string)$tenant_id, $existing_admin_user_id, true);
            } else {
                if ($users_has_billing_column) {
                    $user_insert = $pdo->prepare("INSERT INTO users (tenant_id, username, email, password_hash, force_password_change, role_id, user_type, status, can_manage_billing, first_name, last_name, middle_name, suffix) VALUES (?, ?, ?, ?, TRUE, ?, 'Admin', 'Active', 1, ?, ?, ?, ?)");
                } else {
                    $user_insert = $pdo->prepare("INSERT INTO users (tenant_id, username, email, password_hash, force_password_change, role_id, user_type, status, first_name, last_name, middle_name, suffix) VALUES (?, ?, ?, ?, TRUE, ?, 'Admin', 'Active', ?, ?, ?, ?)");
                }
                $user_insert->execute([$tenant_id, $admin_username, $admin_email, $password_hash, $new_role_id, $first_name !== '' ? $first_name : null, $last_name !== '' ? $last_name : null, $mi !== '' ? $mi : null, $suffix !== '' ? $suffix : null]);
                mf_set_user_billing_access($pdo, (string)$tenant_id, (int)$pdo->lastInsertId(), true);
            }

            $admin_name = (string)($_SESSION['super_admin_username'] ?? 'super_admin');
            $provision_action_type = ($existing_request_type === 'talk_to_expert') ? 'LEAD_PROVISIONED' : 'TENANT_PROVISIONED';
            $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, description, tenant_id) VALUES (?, ?, 'tenant', ?, ?)");
            $log->execute([$_SESSION['super_admin_id'], $provision_action_type, "{$admin_name} had provisioned {$tenant_name} (ID: {$tenant_id}, Slug: {$tenant_slug}, Plan: {$plan_tier})", $tenant_id]);
            
            // Initialize Default Policies
            try {
                require_once __DIR__ . '/../admin_panel/includes/policy_console_system_defaults.php';
                
                $policy_map = [
                    'policy_console_decision_rules' => policy_console_decision_rules_system_defaults(),
                    'policy_console_credit_limits' => policy_console_credit_limits_system_defaults(),
                    'policy_console_compliance_documents' => policy_console_compliance_documents_system_defaults()
                ];
                
                $upsert_stmt = $pdo->prepare("INSERT INTO system_settings (tenant_id, setting_key, setting_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                foreach ($policy_map as $key => $value) {
                    $upsert_stmt->execute([$tenant_id, $key, json_encode($value)]);
                }
            } catch (Throwable $e) {
                error_log("Failed to initialize policies for tenant {$tenant_id}: " . $e->getMessage());
            }

            $private_url = sa_build_tenant_login_url($tenant_slug);

            $safeTenantName = htmlspecialchars($tenant_name, ENT_QUOTES, 'UTF-8');
            $safePlanTier = htmlspecialchars($plan_tier, ENT_QUOTES, 'UTF-8');
            $safeAdminEmail = htmlspecialchars($admin_email, ENT_QUOTES, 'UTF-8');
            $safePrivateUrl = htmlspecialchars($private_url, ENT_QUOTES, 'UTF-8');
            $safeTempPassword = htmlspecialchars($temp_password, ENT_QUOTES, 'UTF-8');
            $message = mf_email_template([
                'accent' => '#0f8a5f',
                'eyebrow' => 'Instance Provisioned',
                'title' => "Welcome to MicroFin, {$safeTenantName}!",
                'preheader' => "{$tenant_name} is ready to log in and complete setup.",
                'intro_html' => "
                    <p style='margin: 0 0 14px; font-family: Arial, sans-serif; font-size: 15px; line-height: 1.7; color: #334155;'>
                        Your request has been approved and your MicroFin instance is now provisioned.
                    </p>
                ",
                'body_html' => mf_email_panel(
                    'Access Details',
                    mf_email_detail_table([
                        ['label' => 'Plan', 'value' => $safePlanTier, 'html' => true],
                        ['label' => 'Sign-in email', 'value' => $safeAdminEmail, 'html' => true],
                        ['label' => 'Login URL', 'value' => "<a href='{$safePrivateUrl}' style='color: #1d4ed8; text-decoration: none;'>{$safePrivateUrl}</a>", 'html' => true],
                        ['label' => 'Temporary password', 'value' => "<code style='display: inline-block; padding: 4px 8px; background: #f8fafc; border: 1px solid #dbe4ee; border-radius: 8px; font-size: 15px; color: #0f172a;'>{$safeTempPassword}</code>", 'html' => true],
                    ]),
                    'success'
                ) . mf_email_panel(
                    'Next Steps',
                    "
                        <p style='margin: 0 0 10px; font-family: Arial, sans-serif; font-size: 14px; line-height: 1.7; color: #334155;'>
                            Sign in using the email address you originally registered with and the temporary password above.
                        </p>
                        <p style='margin: 0 0 10px; font-family: Arial, sans-serif; font-size: 14px; line-height: 1.7; color: #334155;'>
                            On first login, you will be required to change your password and complete the First-Time Setup Wizard.
                        </p>
                        <p style='margin: 0; font-family: Arial, sans-serif; font-size: 14px; line-height: 1.7; color: #334155;'>
                            Please complete your initial setup within <strong>30 days</strong> to avoid your account being drafted or suspended.
                        </p>
                    ",
                    'info'
                ),
            ]);

            $result_msg = mf_send_brevo_email($admin_email, 'MicroFin - Your Instance is Ready!', $message);
            if ($result_msg === '') {
                $email_status = ' Credentials have been sent via email.';
            } else {
                $email_status = ' (Note: Email could not be delivered)';
            }

            $_SESSION['sa_flash'] = 'Tenant provisioned successfully.' . $email_status;
            header('Location: super_admin.php?section=tenants');
            exit;
        }
    } elseif ($action === 'send_chat_message') {
        $tenant_id = $_POST['tenant_id'] ?? '';
        $message = trim($_POST['message'] ?? '');
        $sender_id = $_SESSION['super_admin_id'] ?? 0;
        
        // Find the primary owner of the tenant to act as the receiver
        $owner_stmt = $pdo->prepare("SELECT user_id FROM users WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY user_id ASC LIMIT 1");
        $owner_stmt->execute([$tenant_id]);
        $receiver_id = $owner_stmt->fetchColumn() ?: 0;
        
        if ($tenant_id !== '' && $message !== '') {
            $stmt = $pdo->prepare("INSERT INTO chat_messages (sender_id, receiver_id, tenant_id, message, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
            $stmt->execute([$sender_id, $receiver_id, $tenant_id, $message]);
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Empty message or tenant ID']);
        }
        exit;
    } elseif ($action === 'send_talk_email') {
        $tenant_id = trim((string)($_POST['tenant_id'] ?? ''));
        if ($tenant_id === '') {
            $_SESSION['sa_error'] = 'Missing lead tenant ID.';
            header('Location: super_admin.php?section=tenants');
            exit;
        }

        $lead_stmt = $pdo->prepare("SELECT t.tenant_id, t.tenant_name, t.request_type, owner.owner_email AS email, owner.owner_first_name AS first_name, owner.owner_last_name AS last_name
            FROM tenants t
            LEFT JOIN (
                SELECT u.tenant_id,
                       SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(u.first_name, '') ORDER BY u.user_id ASC SEPARATOR '||'), '||', 1) AS owner_first_name,
                       SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(u.last_name, '') ORDER BY u.user_id ASC SEPARATOR '||'), '||', 1) AS owner_last_name,
                       SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(u.email, '') ORDER BY u.user_id ASC SEPARATOR '||'), '||', 1) AS owner_email
                FROM users u
                WHERE u.tenant_id IS NOT NULL AND u.deleted_at IS NULL
                GROUP BY u.tenant_id
            ) owner ON owner.tenant_id = t.tenant_id
            WHERE t.tenant_id = ? AND t.deleted_at IS NULL LIMIT 1");
        $lead_stmt->execute([$tenant_id]);
        $lead = $lead_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$lead) {
            $_SESSION['sa_error'] = 'Lead not found.';
            header('Location: super_admin.php?section=tenants');
            exit;
        }

        if (($lead['request_type'] ?? '') !== 'talk_to_expert') {
            $_SESSION['sa_error'] = 'Email action is for Talk to an Expert leads only.';
            header('Location: super_admin.php?section=tenants');
            exit;
        }

        if (empty($lead['email'])) {
            $_SESSION['sa_error'] = 'No admin email found for this lead.';
            header('Location: super_admin.php?section=tenants');
            exit;
        }

        $super_admin_email_stmt = $pdo->prepare("SELECT email FROM users WHERE user_id = ? AND user_type = 'Super Admin' LIMIT 1");
        $super_admin_email_stmt->execute([(int)($_SESSION['super_admin_id'] ?? 0)]);
        $super_admin_email = (string)($super_admin_email_stmt->fetchColumn() ?: '');
        if ($super_admin_email === '') {
            $_SESSION['sa_error'] = 'Unable to determine the super admin email for this action.';
            header('Location: super_admin.php?section=tenants');
            exit;
        }

        $contact_name = trim(((string)($lead['first_name'] ?? '')) . ' ' . ((string)($lead['last_name'] ?? '')));
        $contact_name = $contact_name !== '' ? $contact_name : ((string)($lead['tenant_name'] ?? 'there'));
        $subject = 'MicroFin Consultation Follow-up for ' . (string)($lead['tenant_name'] ?? 'your institution');
        $safeContactName = htmlspecialchars($contact_name, ENT_QUOTES, 'UTF-8');
        $safeLeadName = htmlspecialchars((string)($lead['tenant_name'] ?? 'your institution'), ENT_QUOTES, 'UTF-8');
        $safeSuperAdminEmail = htmlspecialchars($super_admin_email, ENT_QUOTES, 'UTF-8');
        $body = mf_email_template([
            'accent' => '#2563eb',
            'eyebrow' => 'Consultation Follow-up',
            'title' => 'Thanks for Reaching Out to MicroFin',
            'preheader' => 'A MicroFin representative is following up on your inquiry.',
            'intro_html' => "
                <p style='margin: 0 0 14px; font-family: Arial, sans-serif; font-size: 15px; line-height: 1.7; color: #334155;'>
                    Hi {$safeContactName},
                </p>
                <p style='margin: 0 0 14px; font-family: Arial, sans-serif; font-size: 15px; line-height: 1.7; color: #334155;'>
                    Thank you for your inquiry about <strong>{$safeLeadName}</strong>. We are reviewing your questions and preparing the best next steps for you.
                </p>
            ",
            'body_html' => mf_email_panel(
                'Direct Contact',
                "
                    <p style='margin: 0; font-family: Arial, sans-serif; font-size: 14px; line-height: 1.7; color: #334155;'>
                        For any additional concerns, you may reply directly to this email or contact us at
                        <a href='mailto:{$safeSuperAdminEmail}' style='color: #1d4ed8; text-decoration: none;'>{$safeSuperAdminEmail}</a>.
                    </p>
                ",
                'info'
            ),
        ]);

        $result_msg = mf_send_brevo_email((string)$lead['email'], $subject, $body);

        if ($result_msg === '') {
            $upd = $pdo->prepare("UPDATE tenants SET status = 'In Contact' WHERE tenant_id = ?");
            $upd->execute([$tenant_id]);

            $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, description, tenant_id) VALUES (?, 'LEAD_EMAIL_SENT', 'tenant', ?, ?)");
            $log->execute([$_SESSION['super_admin_id'], "Inquiry email sent to {$lead['tenant_name']} ({$lead['email']}) with contact {$super_admin_email}", $tenant_id]);

            $_SESSION['sa_flash'] = 'Consultation email sent successfully.';
        } else {
            $_SESSION['sa_error'] = 'Failed to send consultation email: ' . $result_msg;
        }

        header('Location: super_admin.php?section=tenants');
        exit;
    } elseif ($action === 'close_inquiry') {
        $tenant_id = trim((string)($_POST['tenant_id'] ?? ''));
        if ($tenant_id === '') {
            $_SESSION['sa_error'] = 'Missing lead tenant ID.';
            header('Location: super_admin.php?section=tenants');
            exit;
        }
        $tenant_stmt = $pdo->prepare("SELECT tenant_name, request_type FROM tenants WHERE tenant_id = ? LIMIT 1");
        $tenant_stmt->execute([$tenant_id]);
        $tenant_row = $tenant_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$tenant_row) {
            $_SESSION['sa_error'] = 'Lead not found.';
            header('Location: super_admin.php?section=tenants');
            exit;
        }
        if (($tenant_row['request_type'] ?? '') !== 'talk_to_expert') {
            $_SESSION['sa_error'] = 'Close action is for inquiries only.';
            header('Location: super_admin.php?section=tenants');
            exit;
        }
        $tenant_name = (string)($tenant_row['tenant_name'] ?? $tenant_id);

        $upd = $pdo->prepare("UPDATE tenants SET status = 'Archived' WHERE tenant_id = ?");
        $upd->execute([$tenant_id]);

        $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, description, tenant_id) VALUES (?, 'LEAD_CLOSED', 'tenant', ?, ?)");
        $log->execute([$_SESSION['super_admin_id'], "Inquiry closed for {$tenant_name}", $tenant_id]);
        $_SESSION['sa_flash'] = 'Inquiry closed.';
        header('Location: super_admin.php?section=tenants');
        exit;
    } elseif ($action === 'update_subscription_plan') {
        $tenant_id = trim((string)($_POST['tenant_id'] ?? ''));
        $target_plan_tier = trim((string)($_POST['target_plan_tier'] ?? ''));
        $change_timing = trim((string)($_POST['change_timing'] ?? 'next_cycle'));

        if ($tenant_id === '' || $target_plan_tier === '') {
            $_SESSION['sa_error'] = 'Tenant and target plan are required.';
            header('Location: super_admin.php?section=subscriptions');
            exit;
        }
        if (!isset($plan_pricing_map[$target_plan_tier], $plan_limits_map[$target_plan_tier])) {
            $_SESSION['sa_error'] = 'Invalid plan tier selected.';
            header('Location: super_admin.php?section=subscriptions');
            exit;
        }
        if (!in_array($change_timing, ['immediate', 'next_cycle'], true)) {
            $change_timing = 'next_cycle';
        }

        $tenantSubscriptionStmt = $pdo->prepare("
            SELECT tenant_id, tenant_name, plan_tier, billing_cycle, status, next_billing_date, scheduled_plan_tier
            FROM tenants
            WHERE tenant_id = ? AND deleted_at IS NULL
            LIMIT 1
        ");
        $tenantSubscriptionStmt->execute([$tenant_id]);
        $tenantSubscription = $tenantSubscriptionStmt->fetch(PDO::FETCH_ASSOC);

        if (!$tenantSubscription) {
            $_SESSION['sa_error'] = 'Tenant not found for subscription update.';
            header('Location: super_admin.php?section=subscriptions');
            exit;
        }

        $currentPlanTier = (string)($tenantSubscription['plan_tier'] ?? 'Starter');
        if (!isset($plan_pricing_map[$currentPlanTier], $plan_limits_map[$currentPlanTier])) {
            $currentPlanTier = 'Starter';
        }
        if ($currentPlanTier === $target_plan_tier) {
            $_SESSION['sa_flash'] = 'Selected plan is already active for this tenant.';
            header('Location: super_admin.php?section=subscriptions');
            exit;
        }

        $currentRank = sa_plan_rank($currentPlanTier);
        $targetRank = sa_plan_rank($target_plan_tier);
        $isUpgrade = $targetRank > $currentRank;
        $isDowngrade = $targetRank < $currentRank;
        $billingCycle = sa_normalize_billing_cycle((string)($tenantSubscription['billing_cycle'] ?? 'Monthly'));
        $today = (new DateTime('today'))->format('Y-m-d');
        $existingNextBillingDate = trim((string)($tenantSubscription['next_billing_date'] ?? ''));

        $nextBillingDateObj = DateTime::createFromFormat('Y-m-d', $existingNextBillingDate);
        $nextBillingDate = ($nextBillingDateObj instanceof DateTime)
            ? $nextBillingDateObj->format('Y-m-d')
            : '';

        if ($nextBillingDate === '' || $nextBillingDate < $today) {
            $nextBillingDate = sa_compute_next_billing_date($billingCycle);
        }

        $tenantName = (string)($tenantSubscription['tenant_name'] ?? $tenant_id);

        if ($isUpgrade && $change_timing === 'immediate') {
            $targetLimits = $plan_limits_map[$target_plan_tier];
            $applyNowStmt = $pdo->prepare("
                UPDATE tenants
                SET plan_tier = ?,
                    mrr = ?,
                    max_clients = ?,
                    max_users = ?,
                    billing_cycle = ?,
                    next_billing_date = ?,
                    scheduled_plan_tier = NULL,
                    scheduled_plan_effective_date = NULL
                WHERE tenant_id = ?
            ");
            $applyNowStmt->execute([
                $target_plan_tier,
                $plan_pricing_map[$target_plan_tier],
                $targetLimits['clients'],
                $targetLimits['users'],
                $billingCycle,
                $nextBillingDate,
                $tenant_id,
            ]);

            $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, description, tenant_id) VALUES (?, 'TENANT_PLAN_UPGRADE_IMMEDIATE', 'tenant', ?, ?)");
            $log->execute([$_SESSION['super_admin_id'], "Plan upgraded immediately for {$tenantName}: {$currentPlanTier} -> {$target_plan_tier}", $tenant_id]);

            $_SESSION['sa_flash'] = "Upgrade applied immediately: {$tenantName} is now on {$target_plan_tier}.";
        } else {
            // Downgrades are always scheduled; upgrades can also be scheduled.
            $effectiveDate = $nextBillingDate;
            $scheduleStmt = $pdo->prepare("
                UPDATE tenants
                SET billing_cycle = ?,
                    next_billing_date = ?,
                    scheduled_plan_tier = ?,
                    scheduled_plan_effective_date = ?
                WHERE tenant_id = ?
            ");
            $scheduleStmt->execute([
                $billingCycle,
                $nextBillingDate,
                $target_plan_tier,
                $effectiveDate,
                $tenant_id,
            ]);

            $actionType = $isDowngrade ? 'TENANT_PLAN_DOWNGRADE_SCHEDULED' : 'TENANT_PLAN_UPGRADE_SCHEDULED';
            $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, description, tenant_id) VALUES (?, ?, 'tenant', ?, ?)");
            $log->execute([
                $_SESSION['super_admin_id'],
                $actionType,
                "Plan change scheduled for {$tenantName}: {$currentPlanTier} -> {$target_plan_tier} on {$effectiveDate}",
                $tenant_id
            ]);

            if ($isDowngrade) {
                $_SESSION['sa_flash'] = "Downgrade scheduled for next billing date ({$effectiveDate}). Reduced limits will apply then.";
            } else {
                $_SESSION['sa_flash'] = "Upgrade scheduled for next billing date ({$effectiveDate}).";
            }
        }

        header('Location: super_admin.php?section=subscriptions');
        exit;
    } elseif ($action === 'toggle_status') {
        $tenant_id = trim((string)($_POST['tenant_id'] ?? ''));
        $new_status = trim((string)($_POST['new_status'] ?? 'Active'));
        $deactivation_reason = trim((string)($_POST['deactivation_reason'] ?? ''));

        if ($tenant_id === '') {
            $_SESSION['sa_error'] = 'Tenant ID is required.';
            header('Location: super_admin.php?section=tenants');
            exit;
        }

        if (!in_array($new_status, ['Active', 'Suspended'], true)) {
            $_SESSION['sa_error'] = 'Invalid tenant status update requested.';
            header('Location: super_admin.php?section=tenants');
            exit;
        }

        if ($new_status === 'Suspended') {
            $deactivation_reason = str_replace(["\r\n", "\r"], "\n", $deactivation_reason);
            $deactivation_reason = trim($deactivation_reason);
            if ($deactivation_reason === '') {
                $_SESSION['sa_error'] = 'Please provide a reason before deactivating this tenant.';
                header('Location: super_admin.php?section=tenants');
                exit;
            }
            if (strlen($deactivation_reason) > 1000) {
                $deactivation_reason = substr($deactivation_reason, 0, 1000);
            }
        }

        $tenantStmt = $pdo->prepare("SELECT tenant_name, status FROM tenants WHERE tenant_id = ? AND deleted_at IS NULL LIMIT 1");
        $tenantStmt->execute([$tenant_id]);
        $tenantRow = $tenantStmt->fetch(PDO::FETCH_ASSOC);
        if (!$tenantRow) {
            $_SESSION['sa_error'] = 'Tenant not found.';
            header('Location: super_admin.php?section=tenants');
            exit;
        }

        $tenantName = (string)($tenantRow['tenant_name'] ?? $tenant_id);
        $currentStatus = trim((string)($tenantRow['status'] ?? ''));

        if ($currentStatus === $new_status) {
            $_SESSION['sa_flash'] = "{$tenantName} is already marked as {$new_status}.";
            header('Location: super_admin.php?section=tenants');
            exit;
        }

        $actionType = $new_status === 'Suspended' ? 'TENANT_DEACTIVATED' : 'TENANT_REACTIVATED';
        $logDescription = $new_status === 'Suspended'
            ? "Tenant {$tenantName} was deactivated. Reason: " . preg_replace('/\s+/', ' ', $deactivation_reason)
            : "Tenant {$tenantName} was reactivated.";

        $pdo->beginTransaction();

        try {
            $update = $pdo->prepare("UPDATE tenants SET status = ? WHERE tenant_id = ?");
            $update->execute([$new_status, $tenant_id]);

            $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, description, tenant_id) VALUES (?, ?, 'tenant', ?, ?)");
            $log->execute([$_SESSION['super_admin_id'], $actionType, $logDescription, $tenant_id]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['sa_error'] = 'Unable to update tenant status right now.';
            header('Location: super_admin.php?section=tenants');
            exit;
        }

        $emailStatus = '';
        if ($new_status === 'Suspended') {
            $emailResult = sa_send_tenant_deactivation_email($pdo, $tenant_id, $deactivation_reason);
            if ($emailResult === 'Email sent successfully.') {
                $emailStatus = ' A deactivation email was sent to the tenant.';
            } else {
                $emailStatus = ' Deactivation email failed: ' . $emailResult;
            }
        }

        $_SESSION['sa_flash'] = "Tenant status updated to {$new_status}." . $emailStatus;
        header('Location: super_admin.php?section=tenants');
        exit;
    } elseif ($action === 'reject_tenant') {
        $tenant_id = $_POST['tenant_id'] ?? '';

        $update = $pdo->prepare("UPDATE tenants SET status = 'Rejected' WHERE tenant_id = ?");
        $update->execute([$tenant_id]);

        $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, description, tenant_id) VALUES (?, 'TENANT_REJECTED', 'tenant', 'Tenant application rejected', ?)");
        $log->execute([$_SESSION['super_admin_id'], $tenant_id]);

        $_SESSION['sa_flash'] = "Tenant has been rejected.";
        header('Location: super_admin.php?section=tenants');
        exit;
    } elseif ($action === 'update_tenant_slug') {
        $tenant_id = trim((string) ($_POST['tenant_id'] ?? ''));
        $requested_slug = trim((string) ($_POST['tenant_slug'] ?? ''));

        if ($tenant_id === '' || $requested_slug === '') {
            $_SESSION['sa_error'] = 'Tenant ID and new slug are required.';
            header('Location: super_admin.php?section=tenants');
            exit;
        }

        $new_slug = mf_normalize_tenant_slug($requested_slug);
        if ($new_slug === '') {
            $_SESSION['sa_error'] = 'Please provide a valid slug using letters, numbers, or hyphens.';
            header('Location: super_admin.php?section=tenants');
            exit;
        }

        $tenant_stmt = $pdo->prepare('SELECT tenant_name, tenant_slug FROM tenants WHERE tenant_id = ? LIMIT 1');
        $tenant_stmt->execute([$tenant_id]);
        $tenant_row = $tenant_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tenant_row) {
            $_SESSION['sa_error'] = 'Tenant not found.';
            header('Location: super_admin.php?section=tenants');
            exit;
        }

        $old_slug = (string) ($tenant_row['tenant_slug'] ?? '');
        if ($old_slug === $new_slug) {
            $_SESSION['sa_flash'] = 'Tenant slug is unchanged.';
            header('Location: super_admin.php?section=tenants');
            exit;
        }

        if (mf_tenant_slug_exists($pdo, $new_slug, $tenant_id)) {
            $_SESSION['sa_error'] = 'Slug is already in use by another tenant.';
            header('Location: super_admin.php?section=tenants');
            exit;
        }

        $update_slug = $pdo->prepare('UPDATE tenants SET tenant_slug = ? WHERE tenant_id = ?');
        $update_slug->execute([$new_slug, $tenant_id]);

        $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, description, tenant_id) VALUES (?, 'TENANT_SLUG_UPDATED', 'tenant', ?, ?)");
        $log->execute([$_SESSION['super_admin_id'], "Tenant slug changed from {$old_slug} to {$new_slug}", $tenant_id]);

        $_SESSION['sa_flash'] = 'Tenant slug updated successfully.';
        header('Location: super_admin.php?section=tenants');
        exit;
    } elseif ($action === 'create_super_admin') {
        $profileMode = trim((string)($_POST['profile_mode'] ?? 'onboarding'));
        if (!in_array($profileMode, ['onboarding', 'fill_now'], true)) {
            $profileMode = 'onboarding';
        }

        $saUsernameInput = trim((string)($_POST['sa_username'] ?? ''));
        $saEmail = trim((string)($_POST['sa_email'] ?? ''));
        $saFirstName = trim((string)($_POST['sa_first_name'] ?? ''));
        $saLastName = trim((string)($_POST['sa_last_name'] ?? ''));
        $saMiddleName = trim((string)($_POST['sa_middle_name'] ?? ''));
        $saSuffix = trim((string)($_POST['sa_suffix'] ?? ''));
        $saPhoneNumber = trim((string)($_POST['sa_phone_number'] ?? ''));
        $saDateOfBirth = trim((string)($_POST['sa_date_of_birth'] ?? ''));
        $saUsername = $saUsernameInput === '' ? '' : sa_sanitize_platform_username($saUsernameInput);

        $dateOfBirthIsValid = false;
        if ($saDateOfBirth !== '') {
            $parsedDob = DateTime::createFromFormat('Y-m-d', $saDateOfBirth);
            $dateOfBirthIsValid = $parsedDob instanceof DateTime && $parsedDob->format('Y-m-d') === $saDateOfBirth;
        }

        if ($saEmail === '') {
            $_SESSION['sa_error'] = 'Email is required to create a super admin.';
        } elseif ($saUsernameInput !== '' && $saUsername === '') {
            $_SESSION['sa_error'] = 'Username can only contain letters, numbers, dots, underscores, or hyphens.';
        } elseif (!filter_var($saEmail, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['sa_error'] = 'Please provide a valid email address.';
        } elseif ($profileMode === 'fill_now' && ($saFirstName === '' || $saLastName === '' || $saPhoneNumber === '' || $saDateOfBirth === '')) {
            $_SESSION['sa_error'] = 'First name, last name, phone number, and date of birth are required when filling the profile now.';
        } elseif ($profileMode === 'fill_now' && !$dateOfBirthIsValid) {
            $_SESSION['sa_error'] = 'Please provide a valid date of birth.';
        } else {
            $resolvedUsername = $saUsername !== ''
                ? $saUsername
                : sa_generate_unique_platform_username($pdo, '', $saEmail, $saFirstName, $saLastName);

            $profileModeLabel = $profileMode === 'fill_now' ? 'Profile was filled during account creation.' : 'Profile will be completed during onboarding.';
            try {
                $superAdminRoleId = sa_ensure_platform_role($pdo, 'Super Admin', 'Master Platform Administrator');

                $check = $pdo->prepare("
                    SELECT user_id
                    FROM users
                    WHERE user_type = 'Super Admin'
                      AND deleted_at IS NULL
                      AND (email = ? OR username = ?)
                    LIMIT 1
                ");
                $check->execute([$saEmail, $resolvedUsername]);

                if ($check->fetchColumn()) {
                    $_SESSION['sa_error'] = 'Email or username already exists for a platform admin.';
                } else {
                    $temporaryPassword = sa_generate_temporary_password();
                    $hash = password_hash($temporaryPassword, PASSWORD_DEFAULT);
                    $loginUrl = sa_build_super_admin_login_url();
                    $safeLoginUrl = htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8');
                    $safeEmail = htmlspecialchars($saEmail, ENT_QUOTES, 'UTF-8');
                    $safeTemporaryPassword = htmlspecialchars($temporaryPassword, ENT_QUOTES, 'UTF-8');
                    $safeProfileMode = htmlspecialchars($profileModeLabel, ENT_QUOTES, 'UTF-8');
                    $message = mf_email_template([
                        'accent' => '#0f8a5f',
                        'eyebrow' => 'Platform Admin Access',
                        'title' => 'Your MicroFin Super Admin Account Is Ready',
                        'preheader' => 'Your new MicroFin super admin credentials are ready.',
                        'intro_html' => "
                            <p style='margin: 0 0 14px; font-family: Arial, sans-serif; font-size: 15px; line-height: 1.7; color: #334155;'>
                                A platform administrator account has been created for you.
                            </p>
                        ",
                        'body_html' => mf_email_panel(
                            'Access Details',
                            mf_email_detail_table([
                                ['label' => 'Login URL', 'value' => "<a href='{$safeLoginUrl}' style='color: #1d4ed8; text-decoration: none;'>{$safeLoginUrl}</a>", 'html' => true],
                                ['label' => 'Sign-in email', 'value' => $safeEmail, 'html' => true],
                                ['label' => 'Temporary password', 'value' => "<code style='display: inline-block; padding: 4px 8px; background: #f8fafc; border: 1px solid #dbe4ee; border-radius: 8px; font-size: 15px; color: #0f172a;'>{$safeTemporaryPassword}</code>", 'html' => true],
                                ['label' => 'Profile setup', 'value' => $safeProfileMode, 'html' => true],
                            ]),
                            'success'
                        ) . mf_email_panel(
                            'Important',
                            "
                                <p style='margin: 0 0 10px; font-family: Arial, sans-serif; font-size: 14px; line-height: 1.7; color: #334155;'>
                                    Please keep this password secure. It was generated automatically by the system.
                                </p>
                                <p style='margin: 0; font-family: Arial, sans-serif; font-size: 14px; line-height: 1.7; color: #334155;'>
                                    On first login, you will be required to reset this temporary password.
                                </p>
                            ",
                            'info'
                        ),
                    ]);

                    $pdo->beginTransaction();
                    try {
                        $insert = $pdo->prepare("
                            INSERT INTO users (
                                tenant_id,
                                username,
                                email,
                                phone_number,
                                password_hash,
                                force_password_change,
                                role_id,
                                user_type,
                                status,
                                first_name,
                                last_name,
                                middle_name,
                                suffix,
                                date_of_birth
                            )
                            VALUES (NULL, ?, ?, ?, ?, TRUE, ?, 'Super Admin', 'Inactive', ?, ?, ?, ?, ?)
                        ");
                        $insert->execute([
                            $resolvedUsername,
                            $saEmail,
                            $saPhoneNumber !== '' ? $saPhoneNumber : null,
                            $hash,
                            $superAdminRoleId,
                            $saFirstName !== '' ? $saFirstName : null,
                            $saLastName !== '' ? $saLastName : null,
                            $saMiddleName !== '' ? $saMiddleName : null,
                            $saSuffix !== '' ? $saSuffix : null,
                            $saDateOfBirth !== '' ? $saDateOfBirth : null,
                        ]);

                        $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, description) VALUES (?, 'SUPER_ADMIN_CREATED', 'user', ?)");
                        $log->execute([$_SESSION['super_admin_id'], "Created new super admin account: {$resolvedUsername}. {$profileModeLabel}"]);

                        $result_msg = mf_send_brevo_email($saEmail, 'MicroFin - Super Admin Access', $message);
                        if ($result_msg !== '') {
                            throw new RuntimeException('Credential email could not be delivered.');
                        }

                        $pdo->commit();
                        $_SESSION['sa_flash'] = "Super admin account created successfully. Credentials were sent to {$saEmail}. {$profileModeLabel}";
                    } catch (Throwable $transactionError) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        throw $transactionError;
                    }
                }
            } catch (Throwable $e) {
                $_SESSION['sa_error'] = 'Failed to create super admin account: ' . $e->getMessage();
            }
        }
        header('Location: super_admin.php?section=settings');
        exit;
    }
}

// ============================================================
// PHP QUERIES FOR ALL SECTIONS
// ============================================================

// Dashboard stat cards
$stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM tenants WHERE status = 'Active' AND deleted_at IS NULL");
$active_tenants = (int) $stmt->fetch()['cnt'];

$stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM users WHERE status = 'Active' AND deleted_at IS NULL");
$active_users = (int) $stmt->fetch()['cnt'];

$stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM users WHERE user_type = 'Super Admin' AND status = 'Active' AND deleted_at IS NULL");
$active_super_admin_accounts = (int) $stmt->fetch()['cnt'];

$stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM users WHERE status = 'Inactive' AND deleted_at IS NULL");
$inactive_users = (int) $stmt->fetch()['cnt'];

$stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM tenants WHERE request_type = 'tenant_application' AND status = 'Pending' AND deleted_at IS NULL");
$pending_applications = (int) $stmt->fetch()['cnt'];

$stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM tenants WHERE request_type = 'talk_to_expert' AND status IN ('Pending', 'Contacted') AND deleted_at IS NULL");
$pending_inquiries = (int) $stmt->fetch()['cnt'];

$stmt = $pdo->query("SELECT COALESCE(SUM(mrr), 0) AS total_mrr FROM tenants WHERE status = 'Active' AND deleted_at IS NULL");
$total_mrr = number_format((float) $stmt->fetch()['total_mrr'], 2);

// Revenue stats from actual paid invoices
$rev_stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) AS total_revenue, COUNT(*) AS total_transactions FROM tenant_billing_invoices WHERE status = 'Paid'");
$rev_data = $rev_stmt->fetch(PDO::FETCH_ASSOC);
$total_revenue = number_format((float)$rev_data['total_revenue'], 2);
$total_transactions = (int)$rev_data['total_transactions'];
$avg_transaction = $total_transactions > 0 ? number_format((float)$rev_data['total_revenue'] / $total_transactions, 2) : '0.00';

$pdo->exec("CREATE TABLE IF NOT EXISTS tenant_legitimacy_documents (
    document_id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id VARCHAR(50) NOT NULL,
    original_file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(tenant_id) ON DELETE CASCADE,
    INDEX idx_tenant (tenant_id)
)");

// Tenant Management: all tenants
$tenant_rows_stmt = $pdo->query("
    SELECT t.tenant_id, t.tenant_name, t.tenant_slug, t.company_address, t.status, t.request_type, t.concern_category, t.plan_tier, t.mrr, t.created_at, t.setup_completed,
        owner.owner_username,
        owner.owner_first_name,
        owner.owner_last_name,
        owner.owner_middle_name,
        owner.owner_suffix,
        owner.owner_email,
        owner.owner_phone,
           COALESCE(doc_summary.document_count, 0) AS legitimacy_document_count,
           doc_summary.document_paths AS legitimacy_document_paths
    FROM tenants t
    LEFT JOIN (
     SELECT u.tenant_id,
         MIN(u.user_id) AS owner_user_id,
         SUBSTRING_INDEX(GROUP_CONCAT(u.username ORDER BY u.user_id ASC SEPARATOR '||'), '||', 1) AS owner_username,
         SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(u.first_name, '') ORDER BY u.user_id ASC SEPARATOR '||'), '||', 1) AS owner_first_name,
         SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(u.last_name, '') ORDER BY u.user_id ASC SEPARATOR '||'), '||', 1) AS owner_last_name,
         SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(u.middle_name, '') ORDER BY u.user_id ASC SEPARATOR '||'), '||', 1) AS owner_middle_name,
         SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(u.suffix, '') ORDER BY u.user_id ASC SEPARATOR '||'), '||', 1) AS owner_suffix,
         SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(u.email, '') ORDER BY u.user_id ASC SEPARATOR '||'), '||', 1) AS owner_email,
         SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(u.phone_number, '') ORDER BY u.user_id ASC SEPARATOR '||'), '||', 1) AS owner_phone
     FROM users u
    WHERE u.tenant_id IS NOT NULL AND u.deleted_at IS NULL
     GROUP BY u.tenant_id
    ) owner ON owner.tenant_id = t.tenant_id
    LEFT JOIN (
        SELECT tenant_id,
               COUNT(*) AS document_count,
               GROUP_CONCAT(file_path ORDER BY document_id SEPARATOR '||') AS document_paths
        FROM tenant_legitimacy_documents
        GROUP BY tenant_id
    ) doc_summary ON doc_summary.tenant_id = t.tenant_id
    WHERE t.deleted_at IS NULL
    ORDER BY t.created_at DESC
");
$tenant_rows = $tenant_rows_stmt->fetchAll();

// Audit Logs: initial 100 + distinct action types (Only for Super Admins)
$logs_stmt = $pdo->query("
    SELECT al.log_id, al.action_type, al.entity_type, al.entity_id,
           al.description, al.ip_address, al.created_at,
           u.username, u.email AS user_email,
           t.tenant_name
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.user_id
    LEFT JOIN tenants t ON al.tenant_id = t.tenant_id
    WHERE u.user_type = 'Super Admin'
    ORDER BY al.log_id DESC LIMIT 100
");
$audit_logs = $logs_stmt->fetchAll();

$action_types_stmt = $pdo->query("
    SELECT DISTINCT al.action_type 
    FROM audit_logs al
    JOIN users u ON al.user_id = u.user_id 
    WHERE u.user_type = 'Super Admin' 
    ORDER BY al.action_type
");
$action_types = $action_types_stmt->fetchAll(PDO::FETCH_COLUMN);

// Settings: Registered admin accounts
$super_admins_stmt = $pdo->query("
    SELECT u.user_id,
           u.username,
           u.email,
           u.status,
           u.first_name,
           u.last_name,
           u.created_at,
           u.last_login,
           NOW() AS db_now,
           active_sessions.session_user_id,
           COALESCE(active_sessions.active_session_count, 0) AS active_session_count
    FROM users u
    LEFT JOIN (
        SELECT s.user_id AS session_user_id,
               SUM(CASE WHEN s.expires_at > NOW() THEN 1 ELSE 0 END) AS active_session_count
        FROM user_sessions s
        INNER JOIN users session_users
            ON session_users.user_id = s.user_id
           AND session_users.deleted_at IS NULL
        GROUP BY s.user_id
    ) active_sessions ON active_sessions.session_user_id = u.user_id
    WHERE u.user_type = 'Super Admin' AND u.deleted_at IS NULL
    ORDER BY u.created_at DESC
");
$super_admins_list = $super_admins_stmt->fetchAll();

// Active tenants for filter dropdowns
$active_tenants_list_stmt = $pdo->query("SELECT tenant_id, tenant_name FROM tenants WHERE deleted_at IS NULL ORDER BY tenant_name");
$tenants_for_filter = $active_tenants_list_stmt->fetchAll();

// Recent 5 actual tenant applications for dashboard quick-glance
$recent_tenants_stmt = $pdo->query("
        SELECT tenant_name, status, plan_tier, created_at
        FROM tenants
        WHERE deleted_at IS NULL
            AND (request_type = 'tenant_application' OR request_type IS NULL)
        ORDER BY created_at DESC
        LIMIT 5
");
$recent_tenants = $recent_tenants_stmt->fetchAll();

// Recent 5 Talk to an Expert inquiries in a separate dashboard card
$recent_inquiries_stmt = $pdo->query("
        SELECT tenant_name, status, created_at
        FROM tenants
        WHERE deleted_at IS NULL
            AND request_type = 'talk_to_expert'
        ORDER BY created_at DESC
        LIMIT 5
");
$recent_inquiries = $recent_inquiries_stmt->fetchAll();

// Tenant Subscriptions: per-tenant usage + limits comparison
$tenant_subscriptions_stmt = $pdo->query("
    SELECT
        t.tenant_id,
        t.tenant_name,
        t.plan_tier,
        t.billing_cycle,
        t.status,
        t.next_billing_date,
        t.max_users,
        t.max_clients,
        t.scheduled_plan_tier,
        t.scheduled_plan_effective_date,
        COALESCE(u_stats.staff_accounts, 0) AS staff_accounts,
        COALESCE(u_stats.client_accounts, 0) AS client_accounts,
        COALESCE(u_stats.active_users, 0) AS active_users,
        COALESCE(u_stats.total_users, 0) AS total_users
    FROM tenants t
    LEFT JOIN (
        SELECT
            u.tenant_id,
            SUM(CASE WHEN u.user_type IN ('Employee', 'Admin') THEN 1 ELSE 0 END) AS staff_accounts,
            SUM(CASE WHEN u.user_type = 'Client' THEN 1 ELSE 0 END) AS client_accounts,
            SUM(CASE WHEN u.status = 'Active' THEN 1 ELSE 0 END) AS active_users,
            COUNT(*) AS total_users
        FROM users u
        WHERE u.tenant_id IS NOT NULL AND u.deleted_at IS NULL
        GROUP BY u.tenant_id
    ) u_stats ON u_stats.tenant_id = t.tenant_id
    WHERE t.deleted_at IS NULL AND t.status NOT IN ('Pending', 'New', 'In Contact', 'Rejected', 'Closed')
    ORDER BY t.tenant_name ASC
");
$tenant_subscriptions = $tenant_subscriptions_stmt->fetchAll(PDO::FETCH_ASSOC);

$subscription_total_tenants = count($tenant_subscriptions);
$subscription_total_users = 0;
$subscription_total_active_users = 0;
$subscription_plan_distribution = [];

foreach ($tenant_subscriptions as $subscriptionRow) {
    $subscription_total_users += (int)($subscriptionRow['total_users'] ?? 0);
    $subscription_total_active_users += (int)($subscriptionRow['active_users'] ?? 0);
    $rowPlan = (string)($subscriptionRow['plan_tier'] ?? 'Starter');
    if (!isset($subscription_plan_distribution[$rowPlan])) {
        $subscription_plan_distribution[$rowPlan] = 0;
    }
    $subscription_plan_distribution[$rowPlan]++;
}

$platformLogoFile = __DIR__ . '/logo/MicroFin-logo-transparent-temp.png';
$platformLogoUrl = '../public_website/logo/MicroFin-logo-transparent-temp.png?v=' . urlencode((string) @filemtime($platformLogoFile));
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars((string)($_SESSION['ui_theme'] ?? 'light'), ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MicroFin | Platform Admin</title>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($platformLogoUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($platformLogoUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="super_admin_theme.css">
    <link rel="stylesheet" href="super_admin.css">
    <style>
        @media (min-width: 1024px) {
            body {
                zoom: 0.9;
            }

            .app-container {
                min-height: calc(100vh / 0.9);
            }

            .sidebar {
                width: calc(var(--sidebar-width) / 0.9);
                height: calc(100vh / 0.9);
            }

            .main-content {
                margin-left: calc(var(--sidebar-width) / 0.9);
                min-height: calc(100vh / 0.9);
            }
        }
    </style>
</head>
<body>

    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo-circle">
                    <img src="<?php echo htmlspecialchars($platformLogoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="MicroFin logo" class="logo-circle-mark">
                </div>
                <div class="brand-text">
                    <h2>MicroFin</h2>
                    <span>Super Admin</span>
                </div>
            </div>

            <nav class="sidebar-nav">
                <div class="nav-section-label">Overview</div>
                <a href="#dashboard" class="nav-item active" data-target="dashboard">
                    <span class="material-symbols-rounded">space_dashboard</span>
                    <span>Dashboard</span>
                </a>
                <a href="#sales" class="nav-item" data-target="sales">
                    <span class="material-symbols-rounded">point_of_sale</span>
                    <span>Revenue</span>
                </a>

                <div class="nav-section-label">Management</div>
                <a href="#tenants" class="nav-item" data-target="tenants">
                    <span class="material-symbols-rounded">domain</span>
                    <span>Tenants</span>
                    <span class="nav-badge" id="sidebar-pending-badge" <?php if ($pending_applications === 0) echo 'style="display:none;"'; ?>><?php echo $pending_applications; ?></span>
                </a>
                <!--
                <a href="#inquiries" class="nav-item" data-target="inquiries">
                    <span class="material-symbols-rounded">support_agent</span>
                    <span>Talk to an Agent</span>
                    <span class="nav-badge" id="sidebar-inquiry-badge" <?php if ($pending_inquiries === 0) echo 'style="display:none;"'; ?>><?php echo $pending_inquiries; ?></span>
                </a>
                -->
                <a href="#subscriptions" class="nav-item" data-target="subscriptions">
                    <span class="material-symbols-rounded">credit_card</span>
                    <span>Tenant Subscriptions</span>
                </a>
                <a href="#receipts" class="nav-item" data-target="receipts">
                    <span class="material-symbols-rounded">account_balance_wallet</span>
                    <span>Receipts</span>
                </a>

                <div class="nav-section-label">System</div>
                <a href="#reports" class="nav-item" data-target="reports">
                    <span class="material-symbols-rounded">monitoring</span>
                    <span>Reports</span>
                </a>
                <a href="#backup" class="nav-item" data-target="backup">
                    <span class="material-symbols-rounded">cloud_upload</span>
                    <span>Backup</span>
                </a>
                <a href="#settings" class="nav-item" data-target="settings">
                    <span class="material-symbols-rounded">settings</span>
                    <span>Accounts</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <a href="logout.php" class="nav-item">
                    <span class="material-symbols-rounded">logout</span>
                    <span>Logout</span>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="top-header">
                <div class="header-left">
                    <h1 id="page-title">Dashboard</h1>
                </div>
                <div class="header-right">
                    <button type="button" class="icon-btn" id="theme-toggle" aria-label="Switch to dark mode" title="Switch to dark mode">
                        <span class="material-symbols-rounded" id="theme-toggle-icon">dark_mode</span>
                    </button>
                    <?php
                        $f = trim($profile_form['first_name'] ?? '');
                        $l = trim($profile_form['last_name'] ?? '');
                        $adminDisplay = (!empty($f) || !empty($l)) ? trim("$f $l") : ($_SESSION['super_admin_username'] ?? 'Admin');
                        $avF = !empty($f) ? mb_substr($f, 0, 1) : mb_substr($adminDisplay, 0, 1);
                        $avL = !empty($l) ? mb_substr($l, -1) : mb_substr($adminDisplay, -1);
                        $avatarName = urlencode(mb_strtoupper($avF . $avL));
                    ?>
                    <div class="admin-profile">
                        <img src="https://ui-avatars.com/api/?name=<?php echo $avatarName; ?>&background=<?php echo $avatarBackground; ?>&color=fff" alt="Admin Avatar" class="avatar">
                        <div class="admin-info">
                            <span class="admin-name"><?php echo htmlspecialchars($adminDisplay); ?></span>
                            <span class="admin-role">Admin</span>
                        </div>
                    </div>
                </div>
            </header>

            <?php if ($provision_error !== ''): ?>
            <div class="site-alert site-alert-error">
                <?php echo htmlspecialchars($provision_error); ?>
            </div>
            <?php endif; ?>

            <?php if ($provision_success !== ''): ?>
            <div class="site-alert site-alert-success">
                <?php echo htmlspecialchars($provision_success); ?>
            </div>
            <?php endif; ?>

            <!-- Views Container -->
            <div class="views-container">

                <!-- ============================================================ -->
                <!-- SECTION 1: DASHBOARD (Analytics) -->
                <!-- ============================================================ -->
                <section id="dashboard" class="view-section active">
                    <!-- Welcome Banner -->
                    <div class="welcome-banner">
                        <div class="welcome-text">
                            <h2>Welcome back, <?php echo htmlspecialchars($_SESSION['super_admin_username'] ?? 'Admin'); ?></h2>
                            <p>Here's what's happening across the platform today.</p>
                        </div>
                        <div class="welcome-actions">
                            <button class="btn btn-primary" onclick="document.querySelector('.nav-item[data-target=tenants]').click();">
                                <span class="material-symbols-rounded">manage_accounts</span> Manage Tenants
                            </button>
                        </div>
                    </div>

                    <!-- Stat Cards -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon bg-blue">
                                <span class="material-symbols-rounded">corporate_fare</span>
                            </div>
                            <div class="stat-details">
                                <p>Active Tenants</p>
                                <h3 id="stat-active-tenants"><?php echo $active_tenants; ?></h3>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon bg-green">
                                <span class="material-symbols-rounded">group</span>
                            </div>
                            <div class="stat-details">
                                <p>Super Admin Accounts</p>
                                <h3 id="stat-super-admin-accounts"><?php echo $active_super_admin_accounts; ?></h3>
                            </div>
                        </div>
                        <div class="stat-card stat-card-alert <?php echo $pending_applications > 0 ? 'has-pending' : ''; ?>">
                            <div class="stat-icon bg-amber">
                                <span class="material-symbols-rounded">pending_actions</span>
                            </div>
                            <div class="stat-details">
                                <p>Pending Applications</p>
                                <h3 id="stat-pending-apps"><?php echo $pending_applications; ?></h3>
                            </div>
                            <?php if ($pending_applications > 0): ?>
                            <a href="#tenants" class="stat-link" onclick="document.querySelector('.nav-item[data-target=tenants]').click(); setTimeout(()=>document.querySelector('.tenant-intake-tab[data-view=applications]').click(), 100);">
                                Review <span class="material-symbols-rounded" style="font-size:16px;">arrow_forward</span>
                            </a>
                            <?php endif; ?>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon bg-purple">
                                <span class="material-symbols-rounded">payments</span>
                            </div>
                            <div class="stat-details">
                                <p>Monthly MRR</p>
                                <h3 id="stat-total-mrr">₱<?php echo $total_mrr; ?></h3>
                            </div>
                        </div>
                    </div>

                    <!-- Dashboard Bottom Grid: Charts + Recent Tenants -->
                    <div class="dashboard-bottom-grid">
                        <!-- Charts Column -->
                        <div class="dashboard-charts-col">
                            <div class="card" style="margin-bottom: 24px;">
                                <h3>Audit Trail</h3>
                                <div class="filter-row" style="margin-bottom: 16px; padding-bottom: 16px; border-bottom: 1px solid var(--border-color);">
                                    <div class="form-group">
                                        <label>Action Type</label>
                                        <select id="audit-action-filter" class="form-control">
                                            <option value="">All Actions</option>
                                            <?php foreach ($action_types as $at): ?>
                                            <option value="<?php echo htmlspecialchars($at); ?>"><?php echo htmlspecialchars($at); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Tenant</label>
                                        <select id="audit-tenant-filter" class="form-control">
                                            <option value="">All Tenants</option>
                                            <?php foreach ($tenants_for_filter as $tf): ?>
                                            <option value="<?php echo htmlspecialchars($tf['tenant_id']); ?>"><?php echo htmlspecialchars($tf['tenant_name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Date From</label>
                                        <input type="date" id="audit-date-from" class="form-control">
                                    </div>
                                    <div class="form-group">
                                        <label>Date To</label>
                                        <input type="date" id="audit-date-to" class="form-control">
                                    </div>
                                </div>
                                <div class="table-responsive audit-table-wrap">
                                    <table class="admin-table" id="audit-logs-table">
                                        <thead>
                                            <tr>
                                                <th>Timestamp</th>
                                                <th>Username</th>
                                                <th>User / Email</th>
                                                <th>Tenant</th>
                                                <th>Action</th>
                                                <th>Entity</th>
                                                <th>Details</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($audit_logs) === 0): ?>
                                            <tr><td colspan="7" class="text-muted" style="text-align:center; padding:2rem;">No audit logs available.</td></tr>
                                            <?php else: ?>
                                            <?php foreach ($audit_logs as $log): ?>
                                            <tr>
                                                <td><small><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></small></td>
                                                <td><span style="font-family: monospace;"><?php echo htmlspecialchars($log['username'] ?? '—'); ?></span></td>
                                                <td><?php echo htmlspecialchars($log['user_email'] ?? 'System'); ?></td>
                                                <td><?php echo htmlspecialchars($log['tenant_name'] ?? 'Platform'); ?></td>
                                                <td><span class="badge badge-blue"><?php echo htmlspecialchars($log['action_type']); ?></span></td>
                                                <td><?php echo htmlspecialchars($log['entity_type'] ?? '—'); ?></td>
                                                <td>
                                                    <button
                                                        type="button"
                                                        class="btn btn-outline btn-sm audit-detail-btn"
                                                        data-created-at="<?php echo htmlspecialchars((string)($log['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-username="<?php echo htmlspecialchars((string)($log['username'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-user-email="<?php echo htmlspecialchars((string)($log['user_email'] ?? 'System'), ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-tenant-name="<?php echo htmlspecialchars((string)($log['tenant_name'] ?? 'Platform'), ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-action-type="<?php echo htmlspecialchars((string)($log['action_type'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-entity-type="<?php echo htmlspecialchars((string)($log['entity_type'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-description="<?php echo htmlspecialchars((string)($log['description'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?>"
                                                    >
                                                        <span class="material-symbols-rounded" style="font-size:16px;">visibility</span> View
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="charts-grid">
                                <div class="card">
                                    <div class="card-header-flex" style="margin-bottom: 12px;">
                                        <h3 style="margin: 0;">User Growth</h3>
                                        <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                                            <input type="date" id="user-growth-date-from" class="form-control" style="width: 150px;">
                                            <input type="date" id="user-growth-date-to" class="form-control" style="width: 150px;">
                                            <button type="button" id="btn-apply-user-growth-filter" class="btn btn-outline btn-sm">Apply</button>
                                        </div>
                                    </div>
                                    <div class="chart-container">
                                        <canvas id="chart-user-growth"></canvas>
                                    </div>
                                </div>
                                <div class="card">
                                    <h3>Tenant Activity (Status Breakdown)</h3>
                                    <div class="chart-container">
                                        <canvas id="chart-tenant-activity"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Tenants Sidebar -->
                        <div class="dashboard-sidebar-col">
                            <div class="card">
                                <div class="card-header-flex" style="margin-bottom: 16px;">
                                    <h3 style="margin-bottom: 0;">Recent Tenants</h3>
                                    <a href="#tenants" class="btn-text" onclick="document.querySelector('.nav-item[data-target=tenants]').click();">View All</a>
                                </div>
                                <div class="recent-tenants-list">
                                    <?php if (count($recent_tenants) === 0): ?>
                                    <div class="empty-state-mini">
                                        <span class="material-symbols-rounded">domain_add</span>
                                        <p>No tenants yet</p>
                                    </div>
                                    <?php else: ?>
                                    <?php foreach ($recent_tenants as $rt): ?>
                                    <div class="recent-tenant-item">
                                        <div class="recent-tenant-avatar">
                                            <?php echo strtoupper(substr($rt['tenant_name'], 0, 2)); ?>
                                        </div>
                                        <div class="recent-tenant-info">
                                            <span class="recent-tenant-name"><?php echo htmlspecialchars($rt['tenant_name']); ?></span>
                                            <span class="recent-tenant-meta">
                                                <?php echo htmlspecialchars($rt['plan_tier'] ?? 'Starter'); ?> &middot;
                                                <?php echo date('M d', strtotime($rt['created_at'])); ?>
                                            </span>
                                        </div>
                                        <?php
                                        $rt_status = $rt['status'];
                                        $rt_badge = '';
                                        switch ($rt_status) {
                                            case 'Active': $rt_badge = 'badge-green'; break;
                                            case 'Pending': $rt_badge = 'badge-amber'; break;
                                            case 'New': $rt_badge = 'badge-blue'; break;
                                            case 'In Contact': $rt_badge = 'badge-blue'; break;
                                            case 'Suspended': $rt_badge = 'badge-red'; break;
                                            default: $rt_badge = ''; break;
                                        }
                                        ?>
                                        <span class="badge badge-sm <?php echo $rt_badge; ?>"><?php echo htmlspecialchars($rt_status); ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-header-flex" style="margin-bottom: 16px;">
                                    <h3 style="margin-bottom: 0;">Recent Agent Requests</h3>
                                    <a href="#inquiries" class="btn-text" onclick="document.querySelector('.nav-item[data-target=inquiries]').click();">View All Requests</a>
                                </div>
                                <div class="recent-tenants-list">
                                    <?php if (count($recent_inquiries) === 0): ?>
                                    <div class="empty-state-mini">
                                        <span class="material-symbols-rounded">support_agent</span>
                                        <p>No inquiries yet</p>
                                    </div>
                                    <?php else: ?>
                                    <?php foreach ($recent_inquiries as $ri): ?>
                                    <div class="recent-tenant-item">
                                        <div class="recent-tenant-avatar">
                                            <?php echo strtoupper(substr($ri['tenant_name'], 0, 2)); ?>
                                        </div>
                                        <div class="recent-tenant-info">
                                            <span class="recent-tenant-name"><?php echo htmlspecialchars($ri['tenant_name']); ?></span>
                                            <span class="recent-tenant-meta">
                                                Talk to Expert &middot; <?php echo date('M d', strtotime($ri['created_at'])); ?>
                                            </span>
                                        </div>
                                        <?php
                                        $ri_status_raw = (string)($ri['status'] ?? 'Pending');
                                        if (in_array($ri_status_raw, ['Pending', 'New'], true)) {
                                            $ri_status = 'New';
                                            $ri_badge = 'badge-blue';
                                        } elseif (in_array($ri_status_raw, ['Contacted', 'In Contact'], true)) {
                                            $ri_status = 'In Contact';
                                            $ri_badge = 'badge-amber';
                                        } else {
                                            $ri_status = 'Closed';
                                            $ri_badge = 'badge-red';
                                        }
                                        ?>
                                        <span class="badge badge-sm <?php echo $ri_badge; ?>"><?php echo htmlspecialchars($ri_status); ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                        </div>
                    </div>
                </section>

                <!-- ============================================================ -->
                <!-- SECTION 2: TENANT MANAGEMENT -->
                <!-- ============================================================ -->
                <section id="tenants" class="view-section">
                    <div class="settings-tabs" style="margin-bottom: 16px;">
                        <button class="settings-tab tenant-intake-tab active" data-view="tenants">Tenants</button>
                        <button class="settings-tab tenant-intake-tab" data-view="applications">
                            Applications
                            <?php if ($pending_applications > 0): ?>
                            <span class="tab-badge" id="tab-badge-applications"><?php echo $pending_applications; ?></span>
                            <?php else: ?>
                            <span class="tab-badge" id="tab-badge-applications" style="display:none;">0</span>
                            <?php endif; ?>
                        </button>
                    </div>
                    <div class="card">
                        <div class="card-header-flex mb-4">
                            <div>
                                <h3>Tenant Management</h3>
                                <p class="text-muted">Manage all tenant organizations and applications.</p>
                            </div>
                            <div class="actions-flex">
                                <select id="tenant-status-filter" class="form-control" style="width: 200px;">
                                    <option value="all">All Statuses</option>
                                    <option value="active">Active</option>
                                    <option value="suspended">Suspended</option>
                                </select>
                                <select id="application-status-filter" class="form-control" style="width: 200px; display: none;">
                                    <option value="all">All Application Statuses</option>
                                    <option value="pending">Pending</option>
                                    <option value="rejected">Rejected</option>
                                </select>
                                <div class="search-box">
                                    <span class="material-symbols-rounded">search</span>
                                    <input type="text" id="tenant-search" placeholder="Search tenants...">
                                </div>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="admin-table" id="tenants-table">
                                <thead>
                                    <tr>
                                        <th>Tenant Name</th>
                                        <th>Owner / Contact</th>
                                        <th>Status</th>
                                        <th>Plan</th>
                                        <th>MRR</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($tenant_rows) === 0): ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                                            <span class="material-symbols-rounded" style="font-size: 48px; display: block; margin-bottom: 0.5rem;">domain_add</span>
                                            No tenants found.
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($tenant_rows as $t): ?>
                                    <tr data-status="<?php echo htmlspecialchars($t['status']); ?>" data-request-type="<?php echo htmlspecialchars($t['request_type'] ?? 'tenant_application'); ?>">
                                        <td>
                                            <?php echo htmlspecialchars($t['tenant_name']); ?><br>
                                            <small class="text-muted">ID: <?php echo htmlspecialchars($t['tenant_id'] ?? '—'); ?></small>
                                            <?php if (($t['request_type'] ?? '') === 'talk_to_expert' && !empty($t['concern_category'])): ?>
                                                <br><small class="concern-note">Concern: <?php echo htmlspecialchars($t['concern_category']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $owner_name = trim((string)($t['owner_first_name'] ?? '') . ' ' . (string)($t['owner_last_name'] ?? ''));
                                            $owner_username = trim((string)($t['owner_username'] ?? ''));
                                            echo htmlspecialchars($owner_name !== '' ? $owner_name : ($owner_username !== '' ? $owner_username : '—'));
                                            ?><br>
                                            <small class="text-muted">@<?php echo htmlspecialchars($owner_username !== '' ? $owner_username : '—'); ?></small><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($t['owner_email'] ?? '—'); ?></small><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($t['owner_phone'] ?? '—'); ?></small>
                                            <br>
                                            <?php $doc_count = (int)($t['legitimacy_document_count'] ?? 0); ?>
                                            <?php if ($doc_count > 0): ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $status = $t['status'];
                                            $request_type = (string)($t['request_type'] ?? 'tenant_application');
                                            $badge_class = '';
                                            $normalized_status = $status;
                                            if ($request_type === 'talk_to_expert') {
                                                if (in_array($status, ['Pending', 'New'], true)) {
                                                    $normalized_status = 'New';
                                                } elseif (in_array($status, ['Contacted', 'In Contact'], true)) {
                                                    $normalized_status = 'In Contact';
                                                } else {
                                                    $normalized_status = 'Closed';
                                                }
                                            } else {
                                                if (in_array($status, ['Active'], true)) {
                                                    $normalized_status = 'Active';
                                                } elseif (in_array($status, ['Suspended'], true)) {
                                                    $normalized_status = 'Suspended';
                                                } elseif (in_array($status, ['Rejected'], true)) {
                                                    $normalized_status = 'Rejected';
                                                } else {
                                                    $normalized_status = 'Pending';
                                                }
                                            }

                                            switch ($normalized_status) {
                                                case 'Active':
                                                    $badge_class = 'badge-green';
                                                    break;
                                                case 'Suspended':
                                                    $badge_class = 'badge-red';
                                                    break;
                                                case 'Rejected':
                                                    $badge_class = 'badge-red';
                                                    break;
                                                case 'New':
                                                    $badge_class = 'badge-amber';
                                                    break;
                                                case 'In Contact':
                                                    $badge_class = 'status-badge-contact';
                                                    break;
                                                case 'Closed':
                                                    $badge_class = 'status-badge-closed';
                                                    break;
                                                default:
                                                    $badge_class = 'status-badge-pending';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?>">
                                                <?php echo htmlspecialchars($normalized_status); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($t['plan_tier'] ?? '—'); ?></td>
                                        <td>₱<?php echo number_format((float)($t['mrr'] ?? 0), 2); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($t['created_at'])); ?></td>
                                        <td>
                                            <div style="display:flex; gap:0.5rem; flex-wrap: wrap;">
                                                <?php
                                                // Scan directory for new manual naming conventions (tenant_id+originalfilename)
                                                $tenant_id_clean = preg_replace('/[^A-Za-z0-9_-]+/', '_', $t['tenant_id']);
                                                $permits_dir = dirname(dirname(__DIR__)) . '/uploads/business_permits';
                                                $valid_doc_paths = [];
                                                if ($tenant_id_clean !== '' && is_dir($permits_dir)) {
                                                    // 1. Check tenant subfolder (New format)
                                                    $subfolder = $permits_dir . '/' . $t['tenant_id'];
                                                    if (is_dir($subfolder)) {
                                                        $sub_files = glob($subfolder . '/' . $t['tenant_id'] . '*.*');
                                                        if ($sub_files) {
                                                            foreach ($sub_files as $file) {
                                                                $file_name = basename($file);
                                                                $valid_doc_paths[] = '../../uploads/business_permits/' . $t['tenant_id'] . '/' . str_replace('%2E', '.', rawurlencode($file_name));
                                                            }
                                                        }
                                                    }

                                                    // 2. Check root permits directory (Legacy compatibility)
                                                    $root_files = glob($permits_dir . '/' . $t['tenant_id'] . '*.*');
                                                    if ($root_files) {
                                                        foreach ($root_files as $file) {
                                                            $file_name = basename($file);
                                                            $url_path = '../../uploads/business_permits/' . str_replace('%2E', '.', rawurlencode($file_name));
                                                            if (!in_array($url_path, $valid_doc_paths)) {
                                                                $valid_doc_paths[] = $url_path;
                                                            }
                                                        }
                                                    }
                                                }

                                                $doc_paths_json = [];
                                                foreach ($valid_doc_paths as $doc_path) {
                                                    $f_name = basename($doc_path);
                                                    $label = 'Doc';
                                                    if (stripos($f_name, 'DTI_SEC') !== false) $label = 'DTI/SEC';
                                                    elseif (stripos($f_name, 'BIR_2303') !== false) $label = 'BIR 2303';
                                                    elseif (stripos($f_name, 'BUSINESS_PERMIT') !== false) $label = 'Permit';
                                                    $doc_paths_json[] = ['path' => $doc_path, 'label' => $label];
                                                }
                                                ?>
                                                <button type="button" class="btn btn-outline btn-sm btn-view-tenant-profile" 
                                                    data-tenant-id="<?php echo htmlspecialchars($t['tenant_id']); ?>"
                                                    data-tenant-name="<?php echo htmlspecialchars($t['tenant_name']); ?>"
                                                    data-status="<?php echo htmlspecialchars($normalized_status); ?>"
                                                    data-plan="<?php echo htmlspecialchars($t['plan_tier'] ?? '—'); ?>"
                                                    data-mrr="<?php echo number_format((float)($t['mrr'] ?? 0), 2); ?>"
                                                    data-created="<?php echo date('M d, Y', strtotime($t['created_at'])); ?>"
                                                    data-owner-name="<?php echo htmlspecialchars($owner_name !== '' ? $owner_name : ($owner_username !== '' ? $owner_username : '—')); ?>"
                                                    data-owner-email="<?php echo htmlspecialchars($t['owner_email'] ?? '—'); ?>"
                                                    data-owner-phone="<?php echo htmlspecialchars($t['owner_phone'] ?? '—'); ?>"
                                                    data-first-name="<?php echo htmlspecialchars($t['owner_first_name'] ?? ''); ?>"
                                                    data-last-name="<?php echo htmlspecialchars($t['owner_last_name'] ?? ''); ?>"
                                                    data-mi="<?php echo htmlspecialchars($t['owner_middle_name'] ?? ''); ?>"
                                                    data-suffix="<?php echo htmlspecialchars($t['owner_suffix'] ?? ''); ?>"
                                                    data-company-address="<?php echo htmlspecialchars($t['company_address'] ?? ''); ?>"
                                                    data-request-type="<?php echo htmlspecialchars($t['request_type'] ?? 'tenant_application'); ?>"
                                                    data-docs='<?php echo htmlspecialchars(json_encode($doc_paths_json), ENT_QUOTES, 'UTF-8'); ?>'
                                                    title="View Tenant Profile">
                                                    <span class="material-symbols-rounded" style="font-size:16px;">visibility</span> View Profile
                                                </button>

                                                <?php if (empty($valid_doc_paths) && !empty($t['legitimacy_document_paths'])): ?>
                                                    <span class="badge bg-secondary" title="File not found on server">Lost on deploy</span>
                                                <?php endif; ?>




                                                <?php if (($t['request_type'] ?? 'tenant_application') === 'talk_to_expert' && in_array($status, ['Pending', 'Contacted', 'New', 'In Contact'])): ?>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="action" value="send_talk_email">
                                                        <input type="hidden" name="tenant_id" value="<?php echo htmlspecialchars($t['tenant_id']); ?>">
                                                        <button type="submit" class="btn btn-outline btn-sm" title="Send Consultation Email">
                                                            <span class="material-symbols-rounded" style="font-size:16px;">mail</span> Email
                                                        </button>
                                                    </form>

                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="action" value="close_inquiry">
                                                        <input type="hidden" name="tenant_id" value="<?php echo htmlspecialchars($t['tenant_id']); ?>">
                                                        <button type="submit" class="btn btn-outline btn-sm" title="Close Inquiry">
                                                            <span class="material-symbols-rounded" style="font-size:16px;">task_alt</span> Close
                                                        </button>
                                                    </form>


                                                <?php elseif ($status === 'Active'): ?>
                                                    <!-- View Website -->
                                                    <?php $site_url = sa_build_tenant_login_url((string)$t['tenant_slug']); ?>
                                                    <?php if (!empty($t['setup_completed'])): ?>
                                                        <a href="<?php echo htmlspecialchars($site_url); ?>" target="_blank" class="btn btn-outline btn-sm btn-outline-primary" style="text-decoration:none;" title="View Website">
                                                            <span class="material-symbols-rounded" style="font-size:16px;">language</span> View Site
                                                        </a>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-outline btn-sm btn-disabled" title="Setup Incomplete">
                                                            <span class="material-symbols-rounded" style="font-size:16px;">language</span> View Site
                                                        </button>
                                                    <?php endif; ?>

                                                    <!-- Suspend -->
                                                    <button type="button"
                                                        class="btn btn-outline btn-sm btn-outline-danger btn-tenant-deactivate"
                                                        title="Deactivate Tenant"
                                                        data-tenant-id="<?php echo htmlspecialchars($t['tenant_id']); ?>"
                                                        data-tenant-name="<?php echo htmlspecialchars($t['tenant_name']); ?>">
                                                            <span class="material-symbols-rounded" style="font-size:16px;">block</span>
                                                        </button>
                                                <?php elseif ($status === 'Suspended'): ?>
                                                    <!-- Reactivate -->
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="action" value="toggle_status">
                                                        <input type="hidden" name="tenant_id" value="<?php echo htmlspecialchars($t['tenant_id']); ?>">
                                                        <input type="hidden" name="new_status" value="Active">
                                                        <button type="submit" class="btn btn-outline btn-sm btn-outline-success" title="Reactivate Tenant">
                                                            <span class="material-symbols-rounded" style="font-size:16px;">check_circle</span> Reactivate
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- ============================================================ -->
                <!-- SECTION: INQUIRIES (Talk to an Expert) -->
                <!-- ============================================================ -->
                <section id="inquiries" class="view-section">
                    <div class="card">
                        <div class="card-header-flex mb-4">
                            <div>
                                <h3>Talk to an Agent</h3>
                                <p class="text-muted">Manage all incoming Talk to an Agent requests.</p>
                            </div>
                            <div class="actions-flex">
                                <select id="inquiry-status-filter" class="form-control" style="width: 200px;">
                                    <option value="all">All Statuses</option>
                                    <option value="new">New</option>
                                    <option value="in_contact">In Contact</option>
                                    <option value="closed">Closed</option>
                                </select>
                                <div class="search-box">
                                    <span class="material-symbols-rounded">search</span>
                                    <input type="text" id="inquiry-search" placeholder="Search requests...">
                                </div>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="admin-table" id="inquiries-table">
                                <thead>
                                    <tr>
                                        <th>Institution</th>
                                        <th>Contact</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $inquiry_rows = array_filter($tenant_rows, function ($t) {
                                        return ($t['request_type'] ?? 'tenant_application') === 'talk_to_expert';
                                    });
                                    ?>
                                    <?php if (count($inquiry_rows) === 0): ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                                            <span class="material-symbols-rounded" style="font-size:40px; display:block; margin-bottom:0.5rem;">support_agent</span>
                                            No 'Talk to an Agent' requests found.
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($inquiry_rows as $iq):
                                        $iq_status = (string)($iq['status'] ?? 'Pending');
                                        if (in_array($iq_status, ['Pending', 'New'], true)) {
                                            $iq_normalized = 'New';
                                            $iq_badge_class = 'badge-amber';
                                        } elseif (in_array($iq_status, ['Contacted', 'In Contact'], true)) {
                                            $iq_normalized = 'In Contact';
                                            $iq_badge_class = 'status-badge-contact';
                                        } else {
                                            $iq_normalized = 'Closed';
                                            $iq_badge_class = 'status-badge-closed';
                                        }
                                        $iq_data_status = $iq_normalized === 'New' ? 'new' : ($iq_normalized === 'In Contact' ? 'in_contact' : 'closed');
                                    ?>
                                    <tr data-inquiry-status="<?php echo $iq_data_status; ?>">
                                        <td>
                                            <?php echo htmlspecialchars($iq['tenant_name']); ?><br>
                                            <small class="text-muted">ID: <?php echo htmlspecialchars($iq['tenant_id'] ?? '—'); ?></small>
                                        </td>
                                        <td>
                                            <?php
                                            $iq_owner_name = trim((string)($iq['owner_first_name'] ?? '') . ' ' . (string)($iq['owner_last_name'] ?? ''));
                                            echo htmlspecialchars($iq_owner_name !== '' ? $iq_owner_name : '—');
                                            ?><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($iq['owner_email'] ?? '—'); ?></small><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($iq['owner_phone'] ?? '—'); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $iq_badge_class; ?>">
                                                <?php echo htmlspecialchars($iq_normalized); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($iq['created_at'])); ?></td>
                                        <td>
                                            <div style="display:flex; gap:0.5rem; flex-wrap: wrap;">
                                                <button type="button" class="btn btn-outline btn-sm" onclick="openAgentChat('<?php echo htmlspecialchars($iq['tenant_id']); ?>', '<?php echo htmlspecialchars($iq['tenant_name'], ENT_QUOTES); ?>')">
                                                    <span class="material-symbols-rounded" style="font-size:16px;">chat</span> Chat
                                                </button>
                                                <?php if (in_array($iq_status, ['Pending', 'Contacted', 'New', 'In Contact'])): ?>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="action" value="send_talk_email">
                                                        <input type="hidden" name="tenant_id" value="<?php echo htmlspecialchars($iq['tenant_id']); ?>">
                                                        <button type="submit" class="btn btn-outline btn-sm" title="Send Consultation Email">
                                                            <span class="material-symbols-rounded" style="font-size:16px;">email</span> Email
                                                        </button>
                                                    </form>

                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="action" value="close_inquiry">
                                                        <input type="hidden" name="tenant_id" value="<?php echo htmlspecialchars($iq['tenant_id']); ?>">
                                                        <button type="submit" class="btn btn-outline btn-sm" title="Close Inquiry">
                                                            <span class="material-symbols-rounded" style="font-size:16px;">task_alt</span> Close
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-muted" style="font-size: 0.85rem;">No actions available</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- ============================================================ -->
                <!-- SECTION 3: TENANT SUBSCRIPTIONS -->
                <!-- ============================================================ -->
                <section id="subscriptions" class="view-section">
                    <div class="card subscription-hero-card">
                        <div class="card-header-flex mb-4">
                            <div>
                                <h3>Tenant Subscriptions</h3>
                                <p class="text-muted">Centralized subscription command center with tenant-isolated usage metrics and safe plan controls.</p>
                            </div>
                        </div>

                        <div class="subscription-overview-grid">
                            <div class="subscription-overview-card">
                                <div class="subscription-overview-icon"><span class="material-symbols-rounded">domain</span></div>
                                <p>Total Tenants</p>
                                <h3><?php echo number_format($subscription_total_tenants); ?></h3>
                            </div>
                            <div class="subscription-overview-card">
                                <div class="subscription-overview-icon"><span class="material-symbols-rounded">group</span></div>
                                <p>Total Users</p>
                                <h3><?php echo number_format($subscription_total_users); ?></h3>
                            </div>
                            <div class="subscription-overview-card">
                                <div class="subscription-overview-icon"><span class="material-symbols-rounded">how_to_reg</span></div>
                                <p>Total Active Users</p>
                                <h3><?php echo number_format($subscription_total_active_users); ?></h3>
                            </div>
                        </div>

                        <div class="subscription-plan-distribution">
                            <span class="distribution-label">Plan Distribution:</span>
                            <?php if (empty($subscription_plan_distribution)): ?>
                                <span class="badge">No tenants yet</span>
                            <?php else: ?>
                                <?php foreach ($subscription_plan_distribution as $planName => $planCount): ?>
                                    <span class="badge badge-blue subscription-plan-chip"><?php echo htmlspecialchars($planName); ?> <strong><?php echo (int)$planCount; ?></strong></span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header-flex mb-4">
                            <div>
                                <h3>Per-Tenant Subscription Matrix</h3>
                                <p class="text-muted">Usage metrics and plan limits are tenant-scoped and isolated by tenant_id.</p>
                            </div>
                            <div class="actions-flex">
                                <div class="search-box">
                                    <span class="material-symbols-rounded">search</span>
                                    <input type="text" id="subscription-search" placeholder="Search tenant ID or name...">
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="admin-table subscription-table" id="tenant-subscriptions-table">
                                <thead>
                                    <tr>
                                        <th>Tenant</th>
                                        <th>Plan</th>
                                        <th>Billing Cycle</th>
                                        <th>Status</th>
                                        <th>Next Billing</th>
                                        <th>Staff Usage</th>
                                        <th>Client Usage</th>
                                        <th>Active Users</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($tenant_subscriptions)): ?>
                                        <tr>
                                            <td colspan="8" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                                No tenant subscription records found.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($tenant_subscriptions as $subscription): ?>
                                            <?php
                                            $tenantId = (string)($subscription['tenant_id'] ?? '');
                                            $tenantName = (string)($subscription['tenant_name'] ?? '-');
                                            $planTier = (string)($subscription['plan_tier'] ?? 'Starter');
                                            if (!isset($plan_pricing_map[$planTier])) {
                                                $planTier = 'Starter';
                                            }
                                            $billingCycle = sa_normalize_billing_cycle((string)($subscription['billing_cycle'] ?? 'Monthly'));
                                            $statusLabel = (string)($subscription['status'] ?? 'Pending');
                                            $nextBillingDate = trim((string)($subscription['next_billing_date'] ?? ''));
                                            $scheduledPlanTier = trim((string)($subscription['scheduled_plan_tier'] ?? ''));
                                            $scheduledEffectiveDate = trim((string)($subscription['scheduled_plan_effective_date'] ?? ''));

                                            $staffCurrent = (int)($subscription['staff_accounts'] ?? 0);
                                            $clientCurrent = (int)($subscription['client_accounts'] ?? 0);
                                            $activeUsersCurrent = (int)($subscription['active_users'] ?? 0);
                                            $totalUsersCurrent = (int)($subscription['total_users'] ?? 0);
                                            $staffLimit = (int)($subscription['max_users'] ?? 0);
                                            $clientLimit = (int)($subscription['max_clients'] ?? 0);
                                            $staffLimitDisplay = $staffLimit < 0 ? 'Unlimited' : number_format($staffLimit);
                                            $clientLimitDisplay = $clientLimit < 0 ? 'Unlimited' : number_format($clientLimit);
                                            $staffUsagePercent = $staffLimit > 0 ? (int)min(100, round(($staffCurrent / max($staffLimit, 1)) * 100)) : null;
                                            $clientUsagePercent = $clientLimit > 0 ? (int)min(100, round(($clientCurrent / max($clientLimit, 1)) * 100)) : null;
                                            $activeUsagePercent = $totalUsersCurrent > 0 ? (int)min(100, round(($activeUsersCurrent / max($totalUsersCurrent, 1)) * 100)) : null;
                                            $planPriceLabel = 'PHP ' . number_format((float)$plan_pricing_map[$planTier], 2) . '/mo';

                                            $staffUsageClass = 'usage-meter usage-meter-safe';
                                            if ($staffUsagePercent !== null && $staffUsagePercent >= 90) {
                                                $staffUsageClass = 'usage-meter usage-meter-critical';
                                            } elseif ($staffUsagePercent !== null && $staffUsagePercent >= 70) {
                                                $staffUsageClass = 'usage-meter usage-meter-warn';
                                            }

                                            $clientUsageClass = 'usage-meter usage-meter-safe';
                                            if ($clientUsagePercent !== null && $clientUsagePercent >= 90) {
                                                $clientUsageClass = 'usage-meter usage-meter-critical';
                                            } elseif ($clientUsagePercent !== null && $clientUsagePercent >= 70) {
                                                $clientUsageClass = 'usage-meter usage-meter-warn';
                                            }

                                            $activeUsageClass = 'usage-meter usage-meter-safe';
                                            if ($activeUsagePercent !== null && $activeUsagePercent >= 90) {
                                                $activeUsageClass = 'usage-meter usage-meter-critical';
                                            } elseif ($activeUsagePercent !== null && $activeUsagePercent >= 70) {
                                                $activeUsageClass = 'usage-meter usage-meter-warn';
                                            }

                                            $statusClass = 'badge-amber';
                                            if (in_array($statusLabel, ['Active'], true)) {
                                                $statusClass = 'badge-green';
                                            } elseif (in_array($statusLabel, ['Suspended', 'Rejected', 'Archived'], true)) {
                                                $statusClass = 'badge-red';
                                            } elseif (in_array($statusLabel, ['In Contact'], true)) {
                                                $statusClass = 'badge-blue';
                                            }

                                            $planClass = 'plan-pill plan-starter';
                                            if ($planTier === 'Enterprise') {
                                                $planClass = 'plan-pill plan-enterprise';
                                            }

                                            $nextBillingLabel = '-';
                                            $nextBillingMeta = 'Billing date not available';
                                            if ($nextBillingDate !== '' && strtotime($nextBillingDate) !== false) {
                                                $nextBillingTimestamp = (int)strtotime($nextBillingDate);
                                                $nextBillingLabel = date('M d, Y', $nextBillingTimestamp);
                                                $daysUntil = (int)floor(($nextBillingTimestamp - strtotime('today')) / 86400);
                                                if ($daysUntil > 0) {
                                                    $nextBillingMeta = 'in ' . $daysUntil . ' day' . ($daysUntil === 1 ? '' : 's');
                                                } elseif ($daysUntil === 0) {
                                                    $nextBillingMeta = 'due today';
                                                } else {
                                                    $nextBillingMeta = abs($daysUntil) . ' day' . (abs($daysUntil) === 1 ? '' : 's') . ' overdue';
                                                }
                                            }
                                            ?>
                                            <tr data-subscription-row="1">
                                                <td>
                                                    <div class="subscription-tenant-cell">
                                                        <span class="subscription-tenant-name"><?php echo htmlspecialchars($tenantName); ?></span>
                                                        
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="subscription-plan-cell">
                                                        <span class="<?php echo htmlspecialchars($planClass); ?>"><?php echo htmlspecialchars($planTier); ?></span>
                                                        <small class="subscription-plan-price"><?php echo htmlspecialchars($planPriceLabel); ?></small>
                                                        <?php if ($scheduledPlanTier !== ''): ?>
                                                            <small class="subscription-plan-scheduled">Scheduled: <?php echo htmlspecialchars($scheduledPlanTier); ?> (<?php echo htmlspecialchars($scheduledEffectiveDate !== '' ? date('M d, Y', strtotime($scheduledEffectiveDate)) : 'Next cycle'); ?>)</small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="subscription-cycle-chip"><?php echo htmlspecialchars($billingCycle); ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo htmlspecialchars($statusClass); ?> subscription-status-badge">
                                                        <span class="subscription-status-dot"></span>
                                                        <?php echo htmlspecialchars($statusLabel); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="subscription-next-billing">
                                                        <strong><?php echo htmlspecialchars($nextBillingLabel); ?></strong>
                                                        <small class="text-muted"><?php echo htmlspecialchars($nextBillingMeta); ?></small>
                                                    </div>
                                                </td>
                                                <td class="usage-cell">
                                                    <div class="usage-metric"><?php echo number_format($staffCurrent); ?> / <?php echo htmlspecialchars($staffLimitDisplay); ?></div>
                                                    <?php if ($staffUsagePercent !== null): ?>
                                                        <div class="<?php echo htmlspecialchars($staffUsageClass); ?>"><span style="width: <?php echo $staffUsagePercent; ?>%;"></span></div>
                                                    <?php else: ?>
                                                        <small class="usage-unlimited">Unlimited cap</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="usage-cell">
                                                    <div class="usage-metric"><?php echo number_format($clientCurrent); ?> / <?php echo htmlspecialchars($clientLimitDisplay); ?></div>
                                                    <?php if ($clientUsagePercent !== null): ?>
                                                        <div class="<?php echo htmlspecialchars($clientUsageClass); ?>"><span style="width: <?php echo $clientUsagePercent; ?>%;"></span></div>
                                                    <?php else: ?>
                                                        <small class="usage-unlimited">Unlimited cap</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="usage-cell">
                                                    <div class="usage-metric"><?php echo number_format($activeUsersCurrent); ?> / <?php echo number_format($totalUsersCurrent); ?></div>
                                                    <?php if ($activeUsagePercent !== null): ?>
                                                        <div class="<?php echo htmlspecialchars($activeUsageClass); ?>"><span style="width: <?php echo $activeUsagePercent; ?>%;"></span></div>
                                                    <?php else: ?>
                                                        <small class="usage-unlimited">No users yet</small>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- ============================================================ -->
                <!-- SECTION 4: REPORTS -->
                <!-- ============================================================ -->
                <section id="reports" class="view-section">
                    <div class="card filter-bar">
                        <div class="filter-row">
                            <div class="form-group">
                                <label>Date From</label>
                                <input type="date" id="report-date-from" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Date To</label>
                                <input type="date" id="report-date-to" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Tenant</label>
                                <select id="report-tenant-filter" class="form-control">
                                    <option value="">All Tenants</option>
                                    <?php foreach ($tenants_for_filter as $tf): ?>
                                    <option value="<?php echo htmlspecialchars($tf['tenant_id']); ?>"><?php echo htmlspecialchars($tf['tenant_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="card" style="margin-bottom: 24px;">
                        <div class="card-header-flex mb-4">
                            <div>
                                <h3 style="margin-bottom: 8px;">Report Analytics</h3>
                                <p id="report-analytics-summary" class="text-muted" style="margin: 0;">Building analytics snapshot...</p>
                            </div>
                            <a
                                href="report_pdf.php"
                                id="btn-export-report-pdf"
                                target="_blank"
                                rel="noopener"
                                class="btn btn-outline"
                            >
                                <span class="material-symbols-rounded">download</span> Export PDF
                            </a>
                        </div>
                        <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); margin-top: 20px;">
                            <div class="stat-card" style="margin-bottom: 0;">
                                <div class="stat-icon bg-blue">
                                    <span class="material-symbols-rounded">domain</span>
                                </div>
                                <div class="stat-details">
                                    <p>Total Tenants</p>
                                    <h3 id="report-stat-total-tenants">--</h3>
                                </div>
                            </div>
                            <div class="stat-card" style="margin-bottom: 0;">
                                <div class="stat-icon bg-purple">
                                    <span class="material-symbols-rounded">payments</span>
                                </div>
                                <div class="stat-details">
                                    <p>Current MRR</p>
                                    <h3 id="report-stat-current-mrr">--</h3>
                                </div>
                            </div>
                            <div class="stat-card" style="margin-bottom: 0;">
                                <div class="stat-icon bg-green">
                                    <span class="material-symbols-rounded">account_balance_wallet</span>
                                </div>
                                <div class="stat-details">
                                    <p>Revenue in Range</p>
                                    <h3 id="report-stat-range-revenue">--</h3>
                                </div>
                            </div>
                            <div class="stat-card" style="margin-bottom: 0;">
                                <div class="stat-icon bg-blue">
                                    <span class="material-symbols-rounded">receipt_long</span>
                                </div>
                                <div class="stat-details">
                                    <p>Transactions in Range</p>
                                    <h3 id="report-stat-range-transactions">--</h3>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="dashboard-grid-2">
                        <div class="card">
                            <h3>Tenant Activity</h3>
                            <div class="table-responsive">
                                <table class="admin-table" id="report-tenant-activity">
                                    <thead>
                                        <tr>
                                            <th>Tenant Name</th>
                                            <th>Status</th>
                                            <th>Legend</th>
                                            <th>Plan</th>
                                            <th>Created</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr><td colspan="5" class="text-muted" style="text-align:center; padding:2rem;">Loading tenant activity...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="card">
                            <h3>Inquiry Activity</h3>
                            <div class="table-responsive">
                                <table class="admin-table" id="report-inquiry-activity">
                                    <thead>
                                        <tr>
                                            <th>Tenant Name</th>
                                            <th>Status</th>
                                            <th>Stage</th>
                                            <th>Created</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr><td colspan="4" class="text-muted" style="text-align:center; padding:2rem;">Loading inquiry activity...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="dashboard-grid-2" style="margin-top: 24px;">
                        <div class="card">
                            <h3>Current Plan Summary</h3>
                            <p class="text-muted" style="margin: 0 0 16px 0;">Snapshot of tenant subscriptions and user footprint by plan.</p>
                            <div class="table-responsive">
                                <table class="admin-table" id="report-plan-summary">
                                    <thead>
                                        <tr>
                                            <th>Plan</th>
                                            <th>Tenants</th>
                                            <th>Active</th>
                                            <th>Current MRR</th>
                                            <th>Total Users</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr><td colspan="5" class="text-muted" style="text-align:center; padding:2rem;">Loading plan summary...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="card">
                            <h3>Billing Summary</h3>
                            <p class="text-muted" style="margin: 0 0 16px 0;">Revenue and billing activity for the selected report range.</p>
                            <div class="table-responsive">
                                <table class="admin-table" id="report-billing-summary">
                                    <thead>
                                        <tr>
                                            <th>Tenant</th>
                                            <th>Plan</th>
                                            <th>Revenue</th>
                                            <th>Transactions</th>
                                            <th>Latest Payment</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr><td colspan="5" class="text-muted" style="text-align:center; padding:2rem;">Loading billing summary...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ============================================================ -->
                <!-- SECTION 4: SALES REPORT -->
                <!-- ============================================================ -->
                <section id="sales" class="view-section">
                    <!-- Revenue Overview (Consolidated) -->
                    <div class="stats-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 24px;">
                        
                        <div class="stat-card">
                            <div class="stat-icon bg-purple">
                                <span class="material-symbols-rounded">payments</span>
                            </div>
                            <div class="stat-details">
                                <p>Total Revenue</p>
                                <h3 id="stat-revenue-total">₱<?php echo htmlspecialchars($total_revenue); ?></h3>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon bg-blue">
                                <span class="material-symbols-rounded">receipt_long</span>
                            </div>
                            <div class="stat-details">
                                <p>Transactions</p>
                                <h3 id="stat-revenue-transactions"><?php echo $total_transactions; ?></h3>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon bg-amber">
                                <span class="material-symbols-rounded">analytics</span>
                            </div>
                            <div class="stat-details">
                                <p>Avg. Transaction</p>
                                <h3 id="stat-revenue-avg-trans">₱<?php echo htmlspecialchars($avg_transaction); ?></h3>
                            </div>
                        </div>
                    </div>

                    <!-- Top Tenants + Revenue Chart -->
                    <div class="dashboard-grid-2">
                        <div class="card">
                            <h3>Top Performing Tenants</h3>
                            <div class="table-responsive">
                                <table class="admin-table" id="top-tenants-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Tenant</th>
                                            <th>Plan</th>
                                            <th>Revenue</th>
                                            <th>Transactions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr><td colspan="5" class="text-muted" style="text-align:center; padding:2rem;">Click "Apply" to load sales data.</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                                <h3 style="margin: 0;">Revenue</h3>
                                <select id="revenue-period-filter" class="form-control" style="width: auto;">
                                    <option value="monthly">Monthly</option>
                                    <option value="yearly">Yearly</option>
                                </select>
                            </div>
                            <div class="chart-container">
                                <canvas id="chart-revenue"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Transaction History -->
                    <div class="card">
                        <h3>Transaction History</h3>
                        <p class="text-muted" style="margin-bottom: 16px;">Payment history is consolidated here.</p>
                        <div class="table-responsive">
                            <table class="admin-table" id="sales-transactions-table">
                                <thead>
                                    <tr>
                                        <th>Reference</th>
                                        <th>Tenant</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td colspan="6" class="text-muted" style="text-align:center; padding:2rem;">Click "Apply" to load transaction data.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </section>



                <!-- ============================================================ -->
                <!-- SECTION 6: BACKUP (Coming Soon) -->
                <!-- ============================================================ -->
                <section id="backup" class="view-section">
                    <div class="stats-grid" style="grid-template-columns: repeat(4, 1fr); margin-bottom: 24px;">
                        <div class="stat-card">
                            <div class="stat-icon bg-blue"><span class="material-symbols-rounded">cloud_done</span></div>
                            <div class="stat-details"><p>Total Backups</p><h3 id="backup-stat-total">0</h3></div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon bg-green"><span class="material-symbols-rounded">schedule</span></div>
                            <div class="stat-details"><p>Last Backup</p><h3 id="backup-stat-last" style="font-size:.95rem;">Never</h3></div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon bg-purple"><span class="material-symbols-rounded">database</span></div>
                            <div class="stat-details"><p>Database Size</p><h3 id="backup-stat-dbsize">&mdash;</h3></div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon bg-amber"><span class="material-symbols-rounded">table_chart</span></div>
                            <div class="stat-details"><p>Total Tables</p><h3 id="backup-stat-tables">&mdash;</h3></div>
                        </div>
                    </div>
                    <div class="card" style="margin-bottom:24px;">
                        <div class="card-header-flex mb-4">
                            <div><h3>Create Backup</h3><p class="text-muted">Generate a downloadable SQL backup of the database.</p></div>
                        </div>
                        <div style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
                            <button class="btn btn-primary" id="btn-backup-full" style="min-width:200px;">
                                <span class="material-symbols-rounded">cloud_download</span> Full Database Backup
                            </button>
                            <div style="display:flex; gap:8px; align-items:flex-end;">
                                <div class="form-group" style="margin:0; min-width:200px;">
                                    <label style="font-size:.8rem;">Tenant-Specific Export</label>
                                    <select class="form-control" id="backup-tenant-select">
                                        <option value="">Select tenant...</option>
                                        <?php foreach ($tenants_for_filter as $tf): ?>
                                        <option value="<?php echo htmlspecialchars($tf['tenant_id']); ?>"><?php echo htmlspecialchars($tf['tenant_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button class="btn btn-outline" id="btn-backup-tenant" disabled>
                                    <span class="material-symbols-rounded">download</span> Export Tenant
                                </button>
                            </div>
                        </div>
                        <div id="backup-progress" style="display:none; margin-top:16px; padding:12px 16px; background:rgba(2,132,199,0.08); border-radius:8px;">
                            <div style="display:flex; align-items:center; gap:8px;">
                                <span class="material-symbols-rounded" style="animation:spin 1s linear infinite; color:var(--primary);">sync</span>
                                <span style="font-weight:500;" id="backup-progress-text">Generating backup...</span>
                            </div>
                        </div>
                    </div>
                    <div class="card" style="margin-bottom:24px;">
                        <div class="card-header-flex mb-4">
                            <div><h3>Backup History</h3><p class="text-muted">Recent backup operations and their status.</p></div>
                        </div>
                        <div class="table-responsive">
                            <table class="admin-table" id="backup-history-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>File Name</th>
                                        <th>Size</th>
                                        <th>Status</th>
                                        <th>Initiated By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td colspan="6" class="text-muted" style="text-align:center; padding:2rem;">
                                        <span class="material-symbols-rounded" style="font-size:40px; display:block; margin-bottom:.5rem;">cloud_off</span>
                                        No backups have been created yet.
                                    </td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card backup-tip-card">
                        <div style="display:flex; gap:12px; align-items:flex-start;">
                            <span class="material-symbols-rounded backup-tip-icon">info</span>
                            <div>
                                <h3 style="margin:0 0 4px; font-size:1rem;">How to Restore a Backup</h3>
                                <p class="text-muted" style="margin:0; line-height:1.6;">
                                    Download a backup file using the button above, then import it using one of these methods:<br>
                                    <strong>phpMyAdmin:</strong> Go to Import tab, select the <code>.sql</code> file, click Go<br>
                                    <strong>MySQL CLI:</strong> <code>mysql -u root -p microfin_db &lt; backup_file.sql</code><br>
                                    <strong>Railway:</strong> Use the Railway CLI or connect via external MySQL client to import.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>


                <!-- ============================================================ -->
                <!-- SECTION 7: SETTINGS -->
                <!-- ============================================================ -->
                <section id="settings" class="view-section">
                    <div class="settings-tabs" style="margin-bottom: 16px;">
                        <button class="settings-tab <?php echo $settings_tab === 'settings-accounts' ? 'active' : ''; ?>" data-settings-target="settings-accounts">Accounts</button>
                        <button class="settings-tab <?php echo $settings_tab === 'settings-profile' ? 'active' : ''; ?>" data-settings-target="settings-profile">Personal Profile</button>
                    </div>
                    <?php if (false): ?>
                    <!-- Sub-section: Tenant Limits -->
                    <div id="settings-limits" class="settings-panel active">
                        <div class="card">
                            <h3>Default Tenant Limits by Plan Tier</h3>
                            <p class="text-muted" style="margin-bottom: 24px;">Default resource limits applied when provisioning new tenants.</p>
                            <div class="table-responsive">
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th>Plan Tier</th>
                                            <th>Max Clients</th>
                                            <th>Max Users</th>
                                            <th>Storage (GB)</th>
                                            <th>MRR</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><span class="badge" style="background:rgba(16,185,129,0.15); color:#10b981;">Starter</span></td>
                                            <td>2,000</td>
                                            <td>1,000</td>
                                            <td>25.00 GB</td>
                                            <td>₱4,999.00</td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge badge-purple">Enterprise</span></td>
                                            <td>Unlimited</td>
                                            <td>Unlimited</td>
                                            <td>Unlimited</td>
                                            <td>₱14,999.00</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div id="settings-profile" class="settings-panel <?php echo $settings_tab === 'settings-profile' ? 'active' : ''; ?>">
                        <div class="card">
                            <div class="card-header-flex mb-4">
                                <div>
                                    <h3>Personal Profile</h3>
                                    <p class="text-muted">Update the details for your currently logged-in platform account.</p>
                                </div>
                            </div>
                            <div class="profile-layout">
                                <aside class="profile-summary-card">
                                    <span class="profile-summary-eyebrow">Platform Identity</span>
                                    <div class="profile-summary-avatar"><?php echo htmlspecialchars($profile_initials, ENT_QUOTES, 'UTF-8'); ?></div>
                                    <h4><?php echo htmlspecialchars($profile_display_name, ENT_QUOTES, 'UTF-8'); ?></h4>
                                    <p class="text-muted">These details are used across your super admin account and sign-in experience.</p>

                                    <div class="profile-summary-list">
                                        <div class="profile-summary-item">
                                            <span class="material-symbols-rounded">alternate_email</span>
                                            <div>
                                                <strong>Username</strong>
                                                <small><?php echo htmlspecialchars($profile_username_badge, ENT_QUOTES, 'UTF-8'); ?></small>
                                            </div>
                                        </div>
                                        <div class="profile-summary-item">
                                            <span class="material-symbols-rounded">mail</span>
                                            <div>
                                                <strong>Sign-in Email</strong>
                                                <small><?php echo htmlspecialchars($profile_email_badge, ENT_QUOTES, 'UTF-8'); ?></small>
                                            </div>
                                        </div>
                                        <div class="profile-summary-item">
                                            <span class="material-symbols-rounded">verified_user</span>
                                            <div>
                                                <strong>Role</strong>
                                                <small>Super Admin</small>
                                            </div>
                                        </div>
                                    </div>
                                </aside>

                                <form method="POST" class="profile-form-shell">
                                    <input type="hidden" name="action" value="update_super_admin_profile">

                                    <div class="profile-form-block">
                                        <div class="profile-block-heading">
                                            <h4>Basic Information</h4>
                                            <p class="text-muted">Keep your name and contact details current.</p>
                                        </div>
                                        <div class="profile-form-grid">
                                            <div class="form-group profile-form-group">
                                                <label for="profile_first_name">First Name</label>
                                                <input id="profile_first_name" type="text" class="form-control" name="profile_first_name" value="<?php echo htmlspecialchars($profile_form['first_name'], ENT_QUOTES, 'UTF-8'); ?>" autocomplete="given-name" required>
                                            </div>
                                            <div class="form-group profile-form-group">
                                                <label for="profile_last_name">Last Name</label>
                                                <input id="profile_last_name" type="text" class="form-control" name="profile_last_name" value="<?php echo htmlspecialchars($profile_form['last_name'], ENT_QUOTES, 'UTF-8'); ?>" autocomplete="family-name" required>
                                            </div>
                                            <div class="form-group profile-form-group">
                                                <label for="profile_middle_name">Middle Name</label>
                                                <input id="profile_middle_name" type="text" class="form-control" name="profile_middle_name" value="<?php echo htmlspecialchars($profile_form['middle_name'], ENT_QUOTES, 'UTF-8'); ?>" autocomplete="additional-name">
                                            </div>
                                            <div class="form-group profile-form-group">
                                                <label for="profile_suffix">Suffix</label>
                                                <input id="profile_suffix" type="text" class="form-control" name="profile_suffix" value="<?php echo htmlspecialchars($profile_form['suffix'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Optional" autocomplete="honorific-suffix">
                                            </div>
                                            <div class="form-group profile-form-group">
                                                <label for="profile_phone_number">Phone Number</label>
                                                <input id="profile_phone_number" type="text" class="form-control" name="profile_phone_number" value="<?php echo htmlspecialchars($profile_form['phone_number'], ENT_QUOTES, 'UTF-8'); ?>" autocomplete="tel" required>
                                            </div>
                                            <div class="form-group profile-form-group">
                                                <label for="profile_date_of_birth">Date of Birth</label>
                                                <input id="profile_date_of_birth" type="date" class="form-control" name="profile_date_of_birth" value="<?php echo htmlspecialchars($profile_form['date_of_birth'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="profile-form-block">
                                        <div class="profile-block-heading">
                                            <h4>Account Access</h4>
                                            <p class="text-muted">Your username and email are used when signing in.</p>
                                        </div>
                                        <div class="profile-form-grid">
                                            <div class="form-group profile-form-group">
                                                <label for="profile_username">Username</label>
                                                <input id="profile_username" type="text" class="form-control" name="profile_username" value="<?php echo htmlspecialchars($profile_form['username'], ENT_QUOTES, 'UTF-8'); ?>" autocomplete="username" required>
                                                <span class="profile-field-note">Letters, numbers, dots, underscores, hyphens, and @ are allowed.</span>
                                            </div>
                                            <div class="form-group profile-form-group">
                                                <label for="profile_email">Email Address</label>
                                                <input id="profile_email" type="email" class="form-control" name="profile_email" value="<?php echo htmlspecialchars($profile_form['email'], ENT_QUOTES, 'UTF-8'); ?>" autocomplete="email" required>
                                                <span class="profile-field-note">This is the email address used for your super admin login.</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="profile-form-actions">
                                        <button type="reset" class="btn btn-outline">Reset Changes</button>
                                        <button type="submit" class="btn btn-primary">
                                            <span class="material-symbols-rounded">save</span> Save Profile
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Sub-section: Super Admin Accounts -->
                    <div id="settings-accounts" class="settings-panel <?php echo $settings_tab === 'settings-accounts' ? 'active' : ''; ?>">
                        <div class="card">
                            <div class="card-header-flex mb-4">
                                <div>
                                    <h3>Super Admin Accounts</h3>
                                    <p class="text-muted">Master platform accounts with full administrative access.</p>
                                </div>
                                <button class="btn btn-primary" id="btn-create-super-admin">
                                    <span class="material-symbols-rounded">person_add</span> Create New Admin
                                </button>
                            </div>
                            <div class="table-responsive">
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Username</th>
                                            <th>Email Address</th>
                                            <th>Account Status</th>
                                            <th>Activity</th>
                                            <th>Registration Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($super_admins_list) === 0): ?>
                                        <tr>
                                            <td colspan="6" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                                                No system accounts found.
                                            </td>
                                        </tr>
                                        <?php else: ?>
                                        <?php foreach ($super_admins_list as $admin): ?>
                                        <tr>
                                            <td>#<?php echo htmlspecialchars($admin['user_id']); ?></td>
                                            <td style="font-weight: 500;">
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <div class="activity-icon bg-blue" style="width: 28px; height: 28px; min-width: 28px;">
                                                        <span class="material-symbols-rounded" style="font-size: 16px;">person</span>
                                                    </div>
                                                    <?php echo htmlspecialchars($admin['username']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <a href="mailto:<?php echo htmlspecialchars($admin['email']); ?>"><?php echo htmlspecialchars($admin['email']); ?></a>
                                            </td>
                                            <td>
                                                <?php
                                                $account_status = (string)($admin['status'] ?? 'Inactive');
                                                $account_status_badge = $account_status === 'Active' ? 'badge-green' : 'badge-amber';
                                                ?>
                                                <span class="badge <?php echo $account_status_badge; ?>"><?php echo htmlspecialchars($account_status); ?></span>
                                            </td>
                                            <td>
                                                <?php
                                                $displayUserId = (int)($admin['user_id'] ?? 0);
                                                $sessionUserId = (int)($admin['session_user_id'] ?? 0);
                                                $isActiveNow = $displayUserId > 0
                                                    && $displayUserId === $sessionUserId
                                                    && (int)($admin['active_session_count'] ?? 0) > 0;
                                                $lastLoginLabel = mf_humanize_last_login_words($admin['last_login'] ?? null, $isActiveNow, 'Never', $admin['db_now'] ?? null);
                                                $lastLoginBadge = $isActiveNow ? 'badge-green' : 'badge-blue';
                                                ?>
                                                <span class="badge <?php echo $lastLoginBadge; ?>"><?php echo htmlspecialchars($lastLoginLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($admin['created_at'])); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ============================================================ -->
                <!-- SECTION: RECEIPTS GLOBAL LEDGER -->
                <!-- ============================================================ -->
                <section id="receipts" class="view-section">
                    <div class="card">
                        <div class="card-header-flex mb-4">
                            <div>
                                <h3>Receipt Ledger</h3>
                                <p class="text-muted">Platform receipt ledger for all tenant subscription billing transactions.</p>
                            </div>
                            <?php
                            $stmt_filter_period = trim((string) ($_GET['statement_period'] ?? 'monthly'));
                            if (!in_array($stmt_filter_period, ['monthly', 'yearly'], true)) {
                                $stmt_filter_period = 'monthly';
                            }
                            $stmt_filter_year = (int) date('Y');
                            $stmt_filter_month_num = (int) date('n');
                            $legacy_stmt_filter_month = trim((string) ($_GET['statement_month'] ?? ''));

                            if (preg_match('/^\d{4}-\d{2}$/', $legacy_stmt_filter_month) === 1) {
                                [$legacy_year, $legacy_month_num] = array_map('intval', explode('-', $legacy_stmt_filter_month));
                                if ($legacy_year >= 2000 && $legacy_year <= 9999 && $legacy_month_num >= 1 && $legacy_month_num <= 12) {
                                    $stmt_filter_year = $legacy_year;
                                    $stmt_filter_month_num = $legacy_month_num;
                                }
                            }

                            $requested_stmt_month_num = (int) ($_GET['statement_month_num'] ?? $stmt_filter_month_num);
                            if ($requested_stmt_month_num >= 1 && $requested_stmt_month_num <= 12) {
                                $stmt_filter_month_num = $requested_stmt_month_num;
                            }

                            $requested_stmt_year = trim((string) ($_GET['statement_year'] ?? ''));
                            if (preg_match('/^\d{4}$/', $requested_stmt_year) === 1) {
                                $stmt_filter_year = (int) $requested_stmt_year;
                            }

                            $stmt_filter_month = sprintf('%04d-%02d', $stmt_filter_year, $stmt_filter_month_num);
                            $statement_month_labels = [
                                1 => 'January',
                                2 => 'February',
                                3 => 'March',
                                4 => 'April',
                                5 => 'May',
                                6 => 'June',
                                7 => 'July',
                                8 => 'August',
                                9 => 'September',
                                10 => 'October',
                                11 => 'November',
                                12 => 'December',
                            ];
                            ?>
                            <form method="GET" id="receipt-filter-form" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
                                <input type="hidden" name="section" value="receipts">
                                <div class="form-group" style="margin:0; min-width:160px;">
                                    <label style="font-size:.8rem;">Receipt Type</label>
                                    <select name="statement_period" class="form-control">
                                        <option value="monthly" <?php echo $stmt_filter_period === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                        <option value="yearly" <?php echo $stmt_filter_period === 'yearly' ? 'selected' : ''; ?>>Yearly</option>
                                    </select>
                                </div>
                                <div class="form-group" style="margin:0; min-width:180px;">
                                    <label style="font-size:.8rem;">Month (Monthly only)</label>
                                    <select name="statement_month_num" class="form-control">
                                        <?php foreach ($statement_month_labels as $month_number => $month_label): ?>
                                            <option value="<?php echo $month_number; ?>" <?php echo $month_number === $stmt_filter_month_num ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($month_label, ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group" style="margin:0; min-width:130px;">
                                    <label style="font-size:.8rem;">Year</label>
                                    <input
                                        type="number"
                                        name="statement_year"
                                        class="form-control"
                                        min="2000"
                                        max="9999"
                                        step="1"
                                        value="<?php echo htmlspecialchars((string) $stmt_filter_year, ENT_QUOTES, 'UTF-8'); ?>"
                                    >
                                </div>
                            </form>
                        </div>
                        <?php
                        if ($stmt_filter_period === 'yearly') {
                            $stmt_start = sprintf('%04d-01-01', $stmt_filter_year);
                            $stmt_end = sprintf('%04d-12-31', $stmt_filter_year);
                            $stmt_period_label = (string) $stmt_filter_year;
                        } else {
                            $stmt_start = $stmt_filter_month . '-01';
                            $stmt_end = date('Y-m-t', strtotime($stmt_start));
                            $stmt_period_label = date('F Y', strtotime($stmt_start));
                        }
                        $all_statements_stmt = $pdo->prepare("
                            SELECT t.tenant_id,
                                   t.tenant_name, 
                                   t.plan_tier,
                                   SUM(p.amount) AS total_amount,
                                   COUNT(p.invoice_id) AS invoice_count,
                                   MAX(p.created_at) AS latest_date,
                                   GROUP_CONCAT(p.status) AS statuses
                            FROM tenant_billing_invoices p
                            JOIN tenants t ON p.tenant_id = t.tenant_id
                            WHERE DATE(p.created_at) BETWEEN ? AND ?
                            GROUP BY t.tenant_id, t.tenant_name, t.plan_tier
                            ORDER BY t.tenant_name ASC
                        ");
                        $all_statements_stmt->execute([$stmt_start, $stmt_end]);
                        $all_statements = $all_statements_stmt->fetchAll(PDO::FETCH_ASSOC);
                        $stmt_month_total = 0;
                        $total_tenant_statements = count($all_statements);
                        foreach ($all_statements as $sp) { $stmt_month_total += (float)$sp['total_amount']; }
                        ?>
                        <div style="display:flex; gap:24px; margin-bottom:16px; padding:12px 16px; background:rgba(2,132,199,0.06); border-radius:8px;">
                            <div><span class="text-muted" style="font-size:.82rem;">Revenue for <?php echo htmlspecialchars($stmt_period_label, ENT_QUOTES, 'UTF-8'); ?></span><br><strong style="font-size:1.1rem;">&#8369;<?php echo number_format($stmt_month_total, 2); ?></strong></div>
                            <div><span class="text-muted" style="font-size:.82rem;">Tenant Receipts</span><br><strong style="font-size:1.1rem;"><?php echo $total_tenant_statements; ?></strong></div>
                        </div>
                        <div class="table-responsive">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Tenant</th>
                                        <th>Plan</th>
                                        <th>Receipt Period</th>
                                        <th>Invoices</th>
                                        <th>Total Amount</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($all_statements)): ?>
                                    <tr>
                                        <td colspan="7" style="text-align:center; padding:3rem; color:var(--text-muted);">
                                            <span class="material-symbols-rounded" style="font-size:40px; display:block; margin-bottom:0.5rem;">receipt_long</span>
                                            No tenant receipts available for <?php echo htmlspecialchars($stmt_period_label, ENT_QUOTES, 'UTF-8'); ?>.
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($all_statements as $pay): 
                                            $sts = explode(',', $pay['statuses']);
                                            $is_paid = true;
                                            foreach($sts as $s) { if(strtolower(trim($s)) !== 'paid') { $is_paid = false; break; } }
                                            $disp_status = $is_paid ? 'Paid' : 'Unpaid';
                                            $badge_class = $is_paid ? 'badge-green' : 'badge-red';
                                            $statement_pdf_url = 'statement_pdf.php?' . http_build_query([
                                                'tenant_id' => (string) ($pay['tenant_id'] ?? ''),
                                                'statement_period' => $stmt_filter_period,
                                                'statement_month_num' => $stmt_filter_month_num,
                                                'statement_year' => $stmt_filter_year,
                                            ]);
                                        ?>
                                        <tr>
                                            <td style="font-weight:500;"><?php echo htmlspecialchars($pay['tenant_name'] ?? '—'); ?></td>
                                            <td><?php echo htmlspecialchars($pay['plan_tier'] ?? '—'); ?></td>
                                            <td class="text-muted"><?php echo htmlspecialchars($stmt_period_label, ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td style="font-weight:500;"><?php echo $pay['invoice_count']; ?></td>
                                            <td style="font-weight:600;">&#8369;<?php echo number_format((float)$pay['total_amount'], 2); ?></td>
                                            <td><span class="badge <?php echo $badge_class; ?>"><?php echo $disp_status; ?></span></td>
                                            <td><a href="<?php echo htmlspecialchars($statement_pdf_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" class="btn btn-outline" style="padding:4px 8px; font-size:0.75rem;">View Receipt PDF</a></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

            </div>
        </main>
    </div>


    <!-- Create Super Admin Modal -->
    <div id="modal-sa-backdrop" class="modal-backdrop">
        <div class="modal">
            <div class="modal-header">
                <h2>Create Super Admin</h2>
                <button class="icon-btn" id="close-sa-modal"><span class="material-symbols-rounded">close</span></button>
            </div>
            <form class="modal-body" method="POST" action="">
                <input type="hidden" name="action" value="create_super_admin">
                <div class="form-group">
                    <label>Profile Setup</label>
                    <div class="profile-mode-toggle" id="sa-profile-mode-toggle">
                        <span class="profile-mode-side active" data-mode="onboarding">During Onboarding</span>
                        <label class="switch profile-mode-switch" aria-label="Toggle profile setup mode">
                            <input type="checkbox" id="sa-profile-mode" name="profile_mode" value="fill_now">
                            <span class="slider round"></span>
                        </label>
                        <span class="profile-mode-side" data-mode="fill_now">Fill It Now</span>
                    </div>
                    <div class="profile-mode-summary">
                        <strong id="sa-profile-mode-title">Complete During Onboarding</strong>
                        <span id="sa-profile-mode-description">Only the login account is created now. The admin finishes their profile after first login.</span>
                    </div>
                </div>
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" class="form-control" name="sa_username" placeholder="Optional">
                    <small class="form-hint">Optional. Defaults to the admin’s first name (e.g., Maria Clara Santos → Maria).</small>
                </div>
                <div class="form-group">
                    <label>Email Address<span class="required-mark">*</span></label>
                    <input type="email" class="form-control" name="sa_email" placeholder="superadmin@microfin.com" required>
                </div>
                <div id="sa-fill-now-fields" class="is-hidden">
                    <div class="row-2-equal">
                        <div class="form-group">
                            <label>First Name<span class="required-mark">*</span></label>
                            <input type="text" class="form-control" name="sa_first_name" data-fill-now-required="true">
                        </div>
                        <div class="form-group">
                            <label>Last Name<span class="required-mark">*</span></label>
                            <input type="text" class="form-control" name="sa_last_name" data-fill-now-required="true">
                        </div>
                    </div>
                    <div class="row-2-equal">
                        <div class="form-group">
                            <label>Middle Name</label>
                            <input type="text" class="form-control" name="sa_middle_name">
                        </div>
                        <div class="form-group">
                            <label>Suffix</label>
                            <input type="text" class="form-control" name="sa_suffix" placeholder="Optional">
                        </div>
                    </div>
                    <div class="row-2-equal">
                        <div class="form-group">
                            <label>Phone Number<span class="required-mark">*</span></label>
                            <input type="text" class="form-control" name="sa_phone_number" data-fill-now-required="true">
                        </div>
                        <div class="form-group">
                            <label>Date of Birth<span class="required-mark">*</span></label>
                            <input type="date" class="form-control" name="sa_date_of_birth" data-fill-now-required="true">
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="margin-top:24px;">
                    <button type="button" class="btn btn-outline" id="cancel-sa-modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Account</button>
                </div>
            </form>
        </div>
    </div>
    <!-- Create Tenant Modal Wizard -->
    <div id="modal-backdrop" class="modal-backdrop">
        <div class="modal">
            <div class="modal-header">
                <h2>Provision New Tenant</h2>
                <button class="icon-btn" id="close-modal"><span class="material-symbols-rounded">close</span></button>
            </div>
            <form class="modal-body" method="POST" action="">
                <input type="hidden" name="action" value="provision_tenant">
                <input type="hidden" name="request_type" value="tenant_application">
                <div class="form-group">
                    <label>Company / Institution Name</label>
                    <input type="text" class="form-control" name="tenant_name" placeholder="e.g. Village Microfinance" required>
                    <small class="text-muted">The system auto-generates a unique 10-character tenant ID.</small>
                </div>
                <div class="form-group">
                    <label>Custom Site Slug (Optional)</label>
                    <input type="text" class="form-control" name="custom_slug" placeholder="e.g. village-microfinance">
                    <small class="text-muted">Used in login URL: .../login.php?s=<strong>slug</strong>. Tenant ID is system-generated and immutable.</small>
                </div>
                <input type="hidden" name="first_name" value="">
                <input type="hidden" name="last_name" value="">
                <input type="hidden" name="mi" value="">
                <input type="hidden" name="suffix" value="">
                <div class="form-group row-2">
                    <div>
                        <label>Tenant CEO / Primary Admin Email</label>
                        <input type="email" class="form-control" name="admin_email" placeholder="ceo@village.com" required>
                        <small class="text-muted">A secure, private login link will be emailed to this address.</small>
                    </div>
                    <div>
                        <label>Company Address</label>
                        <input type="text" class="form-control" name="company_address" placeholder="e.g. Marilao, Bulacan">
                    </div>
                    <div>
                        <label>Plan Tier</label>
                        <select class="form-control" name="plan_tier">
                            <option value="Starter">Starter (₱4,999/mo)</option>
                            <option value="Enterprise">Enterprise (₱14,999/mo)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" id="cancel-modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submit-tenant"><span class="material-symbols-rounded">rocket_launch</span> Provision Instance</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tenant Deactivation Modal -->
    <div id="modal-tenant-status-backdrop" class="modal-backdrop">
        <div class="modal">
            <div class="modal-header">
                <h2>Deactivate Tenant</h2>
                <button class="icon-btn" id="close-tenant-status-modal"><span class="material-symbols-rounded">close</span></button>
            </div>
            <form class="modal-body" method="POST" action="">
                <input type="hidden" name="action" value="toggle_status">
                <input type="hidden" name="tenant_id" id="tenant-status-tenant-id" value="">
                <input type="hidden" name="new_status" value="Suspended">
                <div class="form-group">
                    <label>Tenant</label>
                    <input type="text" id="tenant-status-tenant-name" class="form-control" readonly>
                </div>
                <div class="form-group">
                    <label>Reason for Deactivation<span class="required-mark">*</span></label>
                    <textarea id="tenant-status-reason" class="form-control" name="deactivation_reason" rows="5" maxlength="1000" placeholder="Tell the tenant why their account is being deactivated." required></textarea>
                    <small class="form-hint">This reason will be recorded in the audit log and emailed to the tenant contact.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" id="cancel-tenant-status-modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Deactivate Tenant</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tenant Profile Modal -->
    <div id="modal-tenant-profile-backdrop" class="modal-backdrop">
        <div class="modal" style="max-width: 600px;">
            <div class="modal-header">
                <h2>Tenant Profile</h2>
                <button class="icon-btn" id="close-tenant-profile-modal"><span class="material-symbols-rounded">close</span></button>
            </div>
            <div class="modal-body">
                <div class="profile-summary-card" style="margin-bottom: 20px; border: 1px solid var(--border-color); padding: 16px; border-radius: 12px; background: var(--bg-level-1);">
                    <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 12px;">
                        <div class="profile-summary-avatar" id="tenant-profile-initials" style="width: 48px; height: 48px; font-size: 1.2rem;">TP</div>
                        <div>
                            <h3 id="tenant-profile-name" style="margin: 0;">Tenant Name</h3>
                            <small class="text-muted" id="tenant-profile-id">ID: —</small>
                        </div>
                    </div>
                    <div class="profile-summary-list" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="profile-summary-item">
                            <span class="material-symbols-rounded" style="font-size: 18px;">verified_user</span>
                            <div>
                                <strong>Status</strong>
                                <small id="tenant-profile-status">—</small>
                            </div>
                        </div>
                        <div class="profile-summary-item">
                            <span class="material-symbols-rounded" style="font-size: 18px;">payments</span>
                            <div>
                                <strong>Plan</strong>
                                <small id="tenant-profile-plan">—</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group-block" style="margin-bottom: 20px;">
                    <h4 style="margin-bottom: 12px; font-size: 0.9rem; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px;">Owner Information</h4>
                    <div class="profile-summary-list">
                        <div class="profile-summary-item" style="margin-bottom: 8px;">
                            <span class="material-symbols-rounded">person</span>
                            <div>
                                <strong>Primary Contact</strong>
                                <small id="tenant-profile-owner-name">—</small>
                            </div>
                        </div>
                        <div class="profile-summary-item" style="margin-bottom: 8px;">
                            <span class="material-symbols-rounded">mail</span>
                            <div>
                                <strong>Email Address</strong>
                                <small id="tenant-profile-owner-email">—</small>
                            </div>
                        </div>
                        <div class="profile-summary-item" style="margin-bottom: 8px;">
                            <span class="material-symbols-rounded">phone</span>
                            <div>
                                <strong>Phone Number</strong>
                                <small id="tenant-profile-owner-phone">—</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group-block">
                    <h4 style="margin-bottom: 12px; font-size: 0.9rem; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px;">Legitimacy Documents</h4>
                    <div id="tenant-profile-docs-list" style="display: flex; flex-direction: column; gap: 8px;">
                        <!-- Documents will be injected here -->
                    </div>
                    <p id="tenant-profile-no-docs" class="text-muted" style="display: none; padding: 12px; background: var(--bg-level-1); border-radius: 8px; text-align: center; font-size: 0.9rem;">
                        No documents available for this tenant.
                    </p>
                </div>
            </div>
            <div class="modal-footer" style="display: flex; gap: 8px; justify-content: flex-end;">
                <div id="modal-tenant-profile-actions" style="display: none; gap: 8px;">
                    <form method="POST" id="modal-reject-tenant-form" style="display:inline;">
                        <input type="hidden" name="action" value="reject_tenant">
                        <input type="hidden" name="tenant_id" id="modal-reject-tenant-id" value="">
                        <button type="submit" class="btn btn-outline-danger">
                            <span class="material-symbols-rounded" style="font-size:18px;">close</span> Reject
                        </button>
                    </form>
                    <button type="button" class="btn btn-primary btn-provision-from-demo" id="modal-provision-tenant-btn">
                        <span class="material-symbols-rounded" style="font-size:18px;">rocket_launch</span> Provision
                    </button>
                </div>
                <button type="button" class="btn btn-outline" id="cancel-tenant-profile-modal">Close</button>
            </div>
        </div>
    </div>

    <!-- Audit Details Modal -->
    <div id="modal-audit-backdrop" class="modal-backdrop">
        <div class="modal">
            <div class="modal-header">
                <h2>Audit Log Details</h2>
                <button class="icon-btn" id="close-audit-modal"><span class="material-symbols-rounded">close</span></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Timestamp</label>
                    <input type="text" id="audit-detail-created-at" class="form-control" readonly>
                </div>
                <div class="form-group row-2">
                    <div>
                        <label>Username</label>
                        <input type="text" id="audit-detail-username" class="form-control" readonly>
                    </div>
                    <div>
                        <label>User / Email</label>
                        <input type="text" id="audit-detail-user-email" class="form-control" readonly>
                    </div>
                </div>
                <div class="form-group row-2">
                    <div>
                        <label>Tenant</label>
                        <input type="text" id="audit-detail-tenant-name" class="form-control" readonly>
                    </div>
                    <div>
                        <label>Action</label>
                        <input type="text" id="audit-detail-action-type" class="form-control" readonly>
                    </div>
                </div>
                <div class="form-group">
                    <label>Entity</label>
                    <input type="text" id="audit-detail-entity-type" class="form-control" readonly>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea id="audit-detail-description" class="form-control" rows="6" readonly></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" id="close-audit-modal-footer">Close</button>
            </div>
        </div>
    </div>

    <!-- Agent Chat Modal -->
    <div id="agent-chat-modal" class="modal-backdrop" style="display: none;">
        <div class="modal-card" style="max-width: 500px; display: flex; flex-direction: column; height: 60vh; background: #fff; border-radius: 12px; padding: 20px;">
            <div class="modal-header d-flex justify-content-between align-items-center mb-3" style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                <h3 id="chat-modal-title" style="margin: 0;">Chat with Institution</h3>
                <button type="button" class="icon-btn" onclick="closeAgentChat()">
                    <span class="material-symbols-rounded">close</span>
                </button>
            </div>
            
            <div id="chat-messages-container" style="flex: 1; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 8px; padding: 1rem; margin-bottom: 1rem; background: var(--bg-level-1);">
                <!-- Messages will be injected here via JS -->
            </div>

            <form id="chat-reply-form" style="display: flex; gap: 0.5rem;" onsubmit="sendAgentMessage(event)">
                <input type="hidden" id="chat-tenant-id" value="">
                <input type="text" id="chat-message-input" class="form-control" placeholder="Type your message..." style="flex: 1;" required>
                <button type="submit" class="btn btn-primary">
                    <span class="material-symbols-rounded" style="font-size:18px;">send</span> Send
                </button>
            </form>
        </div>
    </div>

    <script src="super_admin.js?v=<?php echo time(); ?>"></script>
    <script>
        let currentChatInterval = null;
        const superAdminId = <?php echo json_encode($_SESSION['super_admin_id'] ?? 0); ?>;

        function openAgentChat(tenantId, tenantName) {
            document.getElementById('agent-chat-modal').style.display = 'flex';
            document.getElementById('chat-modal-title').textContent = 'Chat: ' + tenantName;
            document.getElementById('chat-tenant-id').value = tenantId;
            
            // Fetch immediately, then poll every 3 seconds
            fetchChatMessages(tenantId);
            currentChatInterval = setInterval(() => fetchChatMessages(tenantId), 3000);
        }

        function closeAgentChat() {
            document.getElementById('agent-chat-modal').style.display = 'none';
            clearInterval(currentChatInterval);
        }

        async function fetchChatMessages(tenantId) {
            try {
                const response = await fetch(`super_admin.php?action=get_chat_messages&tenant_id=${encodeURIComponent(tenantId)}`);
                const messages = await response.json();
                
                const container = document.getElementById('chat-messages-container');
                container.innerHTML = '';
                
                messages.forEach(msg => {
                    const isMe = parseInt(msg.sender_id) === superAdminId;
                    const align = isMe ? 'right' : 'left';
                    const bg = isMe ? 'var(--accent-blue)' : '#fff';
                    const color = isMe ? '#fff' : 'inherit';
                    const border = isMe ? 'none' : '1px solid var(--border-color)';
                    
                    const msgDiv = document.createElement('div');
                    msgDiv.style.cssText = `text-align: ${align}; margin-bottom: 10px;`;
                    msgDiv.innerHTML = `
                        <div style="display: inline-block; background: ${bg}; color: ${color}; border: ${border}; padding: 8px 12px; border-radius: 6px; max-width: 80%; text-align: left; word-break: break-word;">
                            ${msg.message.replace(/</g, "&lt;").replace(/>/g, "&gt;")}
                        </div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">
                            ${new Date(msg.created_at).toLocaleString()}
                        </div>
                    `;
                    container.appendChild(msgDiv);
                });
                
                // Auto-scroll to bottom
                container.scrollTop = container.scrollHeight;
            } catch (e) {
                console.error('Error fetching chats:', e);
            }
        }

        async function sendAgentMessage(e) {
            e.preventDefault();
            const tenantId = document.getElementById('chat-tenant-id').value;
            const input = document.getElementById('chat-message-input');
            const message = input.value;
            
            if (!message.trim()) return;
            
            const formData = new URLSearchParams();
            formData.append('action', 'send_chat_message');
            formData.append('tenant_id', tenantId);
            formData.append('message', message);
            
            input.value = ''; // clear input immediately
            
            await fetch('super_admin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData.toString()
            });
            
            fetchChatMessages(tenantId); // Refresh immediately after sending
        }

        document.querySelectorAll('.site-alert').forEach(function(el) {
            setTimeout(function() {
                el.style.transition = 'opacity 0.4s ease, margin 0.4s ease, padding 0.4s ease, max-height 0.4s ease';
                el.style.opacity = '0';
                el.style.maxHeight = el.offsetHeight + 'px';
                requestAnimationFrame(function() {
                    el.style.maxHeight = '0';
                    el.style.padding = '0 1rem';
                    el.style.margin = '0 2rem';
                });
                setTimeout(function() { el.remove(); }, 450);
            }, 5000);
        });
    </script>
</body>
</html>

