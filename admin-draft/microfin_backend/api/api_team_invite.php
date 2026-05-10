<?php
require_once __DIR__ . '/session_auth.php';
mf_start_backend_session();
require_once __DIR__ . '/config/db_connect.php';

header('Content-Type: application/json');

$tenant_id = $_SESSION['tenant_id'] ?? null;
$user_id = $_SESSION['user_id'] ?? null;

if (!$tenant_id || !$user_id) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated.']);
    exit;
}

// Security Check
$perm_stmt = $pdo->prepare('
    SELECT COUNT(*) FROM role_permissions rp 
    JOIN permissions p ON rp.permission_id = p.permission_id 
    JOIN users u ON u.role_id = rp.role_id
    WHERE u.user_id = ? AND p.permission_code = "CREATE_USERS"
');
$perm_stmt->execute([$user_id]);
if ($perm_stmt->fetchColumn() == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized to create users.']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!$payload) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON payload.']);
    exit;
}

$firstName = trim($payload['first_name'] ?? '');
$lastName = trim($payload['last_name'] ?? '');
$email = trim($payload['email'] ?? '');
$roleId = (int)($payload['role_id'] ?? 0);

if (!$firstName || !$lastName || !$email || !$roleId) {
    echo json_encode(['status' => 'error', 'message' => 'All fields (First Name, Last Name, Email, Role) are strictly required.']);
    exit;
}

// 1. Verify Email is unique in this tenant
$dup_check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND tenant_id = ?");
$dup_check->execute([$email, $tenant_id]);
if ($dup_check->fetchColumn() > 0) {
    echo json_encode(['status' => 'error', 'message' => 'This email is already in use by another account in your organization.']);
    exit;
}

// 2. Security Check on Role (Make sure the role belongs to this tenant and acts as an Employee)
$role_check = $pdo->prepare("SELECT role_name FROM user_roles WHERE role_id = ? AND tenant_id = ?");
$role_check->execute([$roleId, $tenant_id]);
$roleData = $role_check->fetch(PDO::FETCH_ASSOC);

if (!$roleData || in_array($roleData['role_name'], ['Client', 'Super Admin'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid role assignment.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 3. Generate details
    // Create simple username based on email explicitly (e.g. jdoe + random)
    $emailParts = explode('@', $email);
    $baseUsername = preg_replace('/[^a-zA-Z0-9]/', '', $emailParts[0]);
    $username = substr($baseUsername, 0, 20) . mt_rand(1000, 9999);

    // Generate secure random string for password
    $tempPassword = bin2hex(random_bytes(6)); // 12 character hex string
    $passwordHash = password_hash($tempPassword, PASSWORD_ARGON2ID);

    // First, dynamically checking what the valid ENUM is for user_type since there are two schema versions
    $stmtEnum = $pdo->query("SHOW COLUMNS FROM users LIKE 'user_type'");
    $enumRow = $stmtEnum->fetch(PDO::FETCH_ASSOC);
    $enumStr = $enumRow['Type'] ?? '';
    $userType = (strpos((string)$enumStr, "'Employee'") !== false) ? 'Employee' : 'Staff';

    // 4. Insert User
    $stmtUser = $pdo->prepare("
        INSERT INTO users (tenant_id, username, email, password_hash, first_name, last_name, role_id, user_type, status, force_password_change)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Active', 1)
    ");
    $stmtUser->execute([
        $tenant_id,
        $username,
        $email,
        $passwordHash,
        $firstName,
        $lastName,
        $roleId,
        $userType
    ]);
    
    $newUserId = $pdo->lastInsertId();

    // 5. Insert Employee record tied to User
    $stmtEmp = $pdo->prepare("
        INSERT INTO employees (user_id, tenant_id, first_name, last_name, employment_status, hire_date)
        VALUES (?, ?, ?, ?, 'Active', CURDATE())
    ");
    $stmtEmp->execute([
        $newUserId,
        $tenant_id,
        $firstName,
        $lastName,
    ]);

    $pdo->commit();

    // Fire off the dispatch email using the native Brevo API tool
    // Fetch tenant slug to generate login URL
    $stmtSlug = $pdo->prepare("SELECT tenant_slug FROM tenants WHERE tenant_id = ?");
    $stmtSlug->execute([$tenant_id]);
    $tenantSlug = $stmtSlug->fetchColumn();

    $tenantName = $_SESSION['tenant_name'] ?? 'MicroFin Platform';
    $tenant_name_email = htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8');
    $greet_name = htmlspecialchars(!empty($firstName) ? $firstName : 'Team Member', ENT_QUOTES, 'UTF-8');
    $safe_temp_password = htmlspecialchars($tempPassword, ENT_QUOTES, 'UTF-8');
    $safe_username = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');

    // Dynamically build the login URL for this environment
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $path = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
    $basePath = str_replace('/backend', '', $path);
    $login_url = $protocol . $host . $basePath . "/tenant_login/login.php?s=" . urlencode($tenantSlug ?? '');
    $safe_login_url = htmlspecialchars($login_url, ENT_QUOTES, 'UTF-8');

    $subject = "You have been invited to $tenantName";
    
    $htmlContent = mf_email_template([
        'accent' => '#2563eb',
        'eyebrow' => $tenant_name_email,
        'title' => 'Your Employee Portal Account Is Ready',
        'preheader' => "{$tenant_name_email} created an employee portal account for you.",
        'intro_html' => "
            <p style='margin: 0 0 14px; font-family: Arial, sans-serif; font-size: 15px; line-height: 1.7; color: #334155;'>
                Hello {$greet_name},
            </p>
            <p style='margin: 0 0 14px; font-family: Arial, sans-serif; font-size: 15px; line-height: 1.7; color: #334155;'>
                An employee portal account has been created for you at <strong>{$tenant_name_email}</strong>.
            </p>
        ",
        'body_html' => mf_email_panel(
            'Access Details',
            mf_email_detail_table([
                ['label' => 'Login URL', 'value' => "<a href='{$safe_login_url}' style='color: #1d4ed8; text-decoration: none;'>{$safe_login_url}</a>", 'html' => true],
                ['label' => 'Username', 'value' => "<code style='display: inline-block; padding: 4px 8px; background: #f8fafc; border: 1px solid #dbe4ee; border-radius: 8px; font-size: 15px; color: #0f172a;'>{$safe_username}</code>", 'html' => true],
                ['label' => 'Temporary password', 'value' => "<code style='display: inline-block; padding: 4px 8px; background: #f8fafc; border: 1px solid #dbe4ee; border-radius: 8px; font-size: 15px; color: #0f172a;'>{$safe_temp_password}</code>", 'html' => true],
            ]),
            'info'
        ) . "
            <p style='margin: 0; font-family: Arial, sans-serif; font-size: 15px; line-height: 1.7; color: #334155;'>
                You will be required to change this password on your first login.
            </p>
        ",
    ]);
    
    $mailResult = mf_send_brevo_email($email, $subject, $htmlContent);

    echo json_encode([
        'status' => 'success', 
        'message' => 'Staff member successfully invited! An email has been sent ' . ($mailResult === 'Email sent successfully.' ? 'successfully.' : 'but encountered an issue: ' . $mailResult)
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Staff Invite Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
