<?php
header('Content-Type: application/json');
require_once '../auth/session_auth.php';
mf_start_backend_session();
require_once '../config/db_connect.php';
require_once '../engines/credit_policy.php';

mf_require_tenant_session($pdo, [
    'response' => 'json',
    'status' => 401,
    'message' => 'Unauthorized access.',
]);

if (($_SESSION['user_type'] ?? '') !== 'Employee') {
    echo json_encode(['status' => 'error', 'message' => 'Only staff members can perform this action.']);
    exit;
}

$tenant_id = (string) ($_SESSION['tenant_id'] ?? '');
$session_user_id = (int) ($_SESSION['user_id'] ?? 0);

$content_type = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
$is_json_payload = strpos($content_type, 'application/json') !== false;

if ($is_json_payload) {
    $raw_data = file_get_contents('php://input');
    $data = json_decode($raw_data, true) ?: [];
} else {
    $data = $_POST;
}

require_once '../auth/otp_handler.php';

$walk_in_action = strtolower(trim((string) ($data['walk_in_action'] ?? 'draft')));

// ---------------------------------------------------------
// NEW OTP & EMAIL ACTIONS
// ---------------------------------------------------------
if (in_array($walk_in_action, ['check_email', 'send_otp', 'verify_otp'], true)) {
    $email = strtolower(trim((string) ($data['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Please provide a valid email address.']);
        exit;
    }

    if ($walk_in_action === 'check_email') {
        $dup_stmt = $pdo->prepare('SELECT 1 FROM users WHERE email = ? AND tenant_id = ? LIMIT 1');
        $dup_stmt->execute([$email, $tenant_id]);
        if ($dup_stmt->fetchColumn()) {
            echo json_encode(['status' => 'error', 'message' => 'Email is already registered.']);
        } else {
            echo json_encode(['status' => 'success', 'message' => 'Email is available.']);
        }
        exit;
    }

    if ($walk_in_action === 'send_otp') {
        $dup_stmt = $pdo->prepare('SELECT 1 FROM users WHERE email = ? AND tenant_id = ? LIMIT 1');
        $dup_stmt->execute([$email, $tenant_id]);
        if ($dup_stmt->fetchColumn()) {
            echo json_encode(['status' => 'error', 'message' => 'Email is already registered.']);
            exit;
        }

        if (!mf_otp_can_send($email)) {
            $remaining = mf_otp_get_remaining_seconds($email);
            echo json_encode(['status' => 'error', 'message' => "Please wait {$remaining} seconds before requesting another code."]);
            exit;
        }
        
        $otp = mf_otp_save($email);
        $tenant_name = (string) ($_SESSION['tenant_name'] ?? 'MicroFin');
        $subject = htmlspecialchars($tenant_name) . " verification code";
        
        $bodyHtml = '
            <p style="margin:0 0 16px;font-size:16px;line-height:1.7;">Hello,</p>
            <p style="margin:0 0 20px;font-size:16px;line-height:1.7;">Use the verification code below to finish your client registration at <strong>' . htmlspecialchars($tenant_name, ENT_QUOTES, 'UTF-8') . '</strong>.</p>
            <div style="margin:0 0 20px;padding:18px 20px;border-radius:16px;background:#ecfeff;border:1px solid #a5f3fc;font-size:32px;font-weight:700;letter-spacing:0.32em;text-align:center;">' . htmlspecialchars($otp, ENT_QUOTES, 'UTF-8') . '</div>
            <p style="margin:0 0 12px;font-size:14px;line-height:1.7;">This code expires in 15 minutes.</p>
            <p style="margin:0;font-size:14px;line-height:1.7;color:#6b7280;">If you did not request this, you can safely ignore this email.</p>';
            
        if (function_exists('mf_email_template')) {
            $htmlContent = mf_email_template([
                'accent' => '#0F766E',
                'title' => 'Verify your email',
                'preheader' => 'Your verification code is ready.',
                'body_html' => $bodyHtml
            ]);
        } else {
            $htmlContent = $bodyHtml;
        }

        if (function_exists('mf_send_brevo_email')) {
            mf_send_brevo_email($email, $subject, $htmlContent);
        }
        
        echo json_encode(['status' => 'success', 'message' => 'Verification code sent.']);
        exit;
    }

    if ($walk_in_action === 'verify_otp') {
        $otp_input = trim((string) ($data['otp_input'] ?? ''));
        if (mf_otp_verify($email, $otp_input)) {
            echo json_encode(['status' => 'success', 'message' => 'OTP verified successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid or expired verification code.']);
        }
        exit;
    }
}

$first_name = trim((string) ($data['first_name'] ?? ''));
$last_name = trim((string) ($data['last_name'] ?? ''));
$email = trim((string) ($data['email'] ?? ''));
$phone = trim((string) ($data['phone_number'] ?? ''));
$dob = trim((string) ($data['date_of_birth'] ?? ''));
$gender = trim((string) ($data['gender'] ?? ''));
$civil_status = trim((string) ($data['civil_status'] ?? ''));
$employment_status = trim((string) ($data['employment_status'] ?? ''));
$occupation = trim((string) ($data['occupation'] ?? ''));
$employer_name = trim((string) ($data['employer_name'] ?? ''));
$employer_contact = trim((string) ($data['employer_contact'] ?? ''));
$id_type = trim((string) ($data['id_type'] ?? ''));
$address = trim((string) ($data['address'] ?? ''));
$house_no = trim((string) ($data['house_no'] ?? ''));
$street = trim((string) ($data['street'] ?? ''));
$barangay = trim((string) ($data['barangay'] ?? ''));
$city = trim((string) ($data['city'] ?? ''));
$province = trim((string) ($data['province'] ?? ''));
$postal_code = trim((string) ($data['postal_code'] ?? $data['postal'] ?? ''));
$same_as_present = ((string) ($data['same_as_present'] ?? $data['same_as_permanent'] ?? '0')) === '1';
$perm_house_no = trim((string) ($data['perm_house_no'] ?? ''));
$perm_street = trim((string) ($data['perm_street'] ?? ''));
$perm_barangay = trim((string) ($data['perm_barangay'] ?? ''));
$perm_city = trim((string) ($data['perm_city'] ?? ''));
$perm_province = trim((string) ($data['perm_province'] ?? ''));
$perm_postal_code = trim((string) ($data['perm_postal_code'] ?? $data['perm_postal'] ?? ''));
$monthly_income_raw = trim((string) ($data['monthly_income'] ?? ''));


if ($street === '' && $address !== '') {
    $street = $address;
}

if ($same_as_present) {
    $perm_house_no = $house_no;
    $perm_street = $street;
    $perm_barangay = $barangay;
    $perm_city = $city;
    $perm_province = $province;
    $perm_postal_code = $postal_code;
}

if ($first_name === '' || $last_name === '' || $email === '' || $phone === '' || $dob === '' || $id_type === '' || $monthly_income_raw === '') {
    echo json_encode(['status' => 'error', 'message' => 'Please complete the required walk-in registration fields.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Please provide a valid email address.']);
    exit;
}

$monthly_income = (float) str_replace([',', ' '], '', $monthly_income_raw);
if ($monthly_income <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Monthly income must be greater than zero.']);
    exit;
}

function walk_in_client_table_has_column(PDO $pdo, string $column_name): bool
{
    static $cache = [];

    $cache_key = strtolower($column_name);
    if (array_key_exists($cache_key, $cache)) {
        return $cache[$cache_key];
    }

    $stmt = $pdo->prepare("
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'clients'
          AND COLUMN_NAME = ?
        LIMIT 1
    ");
    $stmt->execute([$column_name]);
    $cache[$cache_key] = (bool) $stmt->fetchColumn();

    return $cache[$cache_key];
}

function generateUniqueUsername(PDO $pdo, string $tenant_id, string $first_name, string $last_name): string {
    $stmtSlug = $pdo->prepare("SELECT tenant_slug FROM tenants WHERE tenant_id = ? LIMIT 1");
    $stmtSlug->execute([$tenant_id]);
    $tenant_slug = $stmtSlug->fetchColumn();
    // Default to 'client' if no slug is returned for some reason
    if (!$tenant_slug) {
        $tenant_slug = "client";
    }

    $base = strtolower(trim($first_name . '.' . $last_name));
    $base = preg_replace('/[^a-z0-9.]+/', '', $base);
    $base = trim($base, '.');
    if ($base === '') $base = 'client';

    for ($i = 0; $i < 20; $i++) {
        $candidate = $base . ($i > 0 ? random_int(100, 9999) : '') . '@' . $tenant_slug;
        $stmt = $pdo->prepare('SELECT 1 FROM users WHERE tenant_id = ? AND username = ? LIMIT 1');
        $stmt->execute([$tenant_id, $candidate]);
        if (!$stmt->fetchColumn()) return $candidate;
    }
    return $base . random_int(10000, 99999) . '@' . $tenant_slug;
}

try {
    $pdo->beginTransaction();

    $dup_stmt = $pdo->prepare('SELECT 1 FROM users WHERE email = ? AND tenant_id = ? LIMIT 1');
    $dup_stmt->execute([$email, $tenant_id]);
    if ($dup_stmt->fetchColumn()) {
        throw new Exception('Email is already registered in this branch/company.');
    }

    $role_stmt = $pdo->prepare("SELECT role_id FROM user_roles WHERE role_name = 'Client' AND tenant_id = ? LIMIT 1");
    $role_stmt->execute([$tenant_id]);
    $role_id = (int) $role_stmt->fetchColumn();

    if ($role_id <= 0) {
        $insert_role = $pdo->prepare("INSERT INTO user_roles (tenant_id, role_name, role_description, is_system_role) VALUES (?, 'Client', 'Client app access', 1)");
        $insert_role->execute([$tenant_id]);
        $role_id = (int) $pdo->lastInsertId();
    }

    $username = generateUniqueUsername($pdo, $tenant_id, $first_name, $last_name);
    
    // Generate a secure temporary password and force password change on next mobile app login
    $temp_password_plain = bin2hex(random_bytes(6));
    $password_hash = password_hash($temp_password_plain, PASSWORD_DEFAULT);

    $user_insert = $pdo->prepare('
        INSERT INTO users (
            tenant_id, username, email, phone_number, password_hash, email_verified,
            first_name, last_name, date_of_birth, force_password_change,
            role_id, user_type, status
        ) VALUES (
            ?, ?, ?, ?, ?, 1,
            ?, ?, ?, 1,
            ?, \'Client\', \'Active\'
        )
    ');
    $user_insert->execute([
        $tenant_id, $username, $email, ($phone !== '' ? $phone : null), $password_hash,
        $first_name, $last_name, $dob, $role_id
    ]);

    $new_user_id = (int) $pdo->lastInsertId();

    $employee_stmt = $pdo->prepare('SELECT employee_id FROM employees WHERE user_id = ? AND tenant_id = ? LIMIT 1');
    $employee_stmt->execute([$session_user_id, $tenant_id]);
    $registered_by = $employee_stmt->fetchColumn() ?: null;

    $id_metadata = [];
    foreach ($data as $key => $val) {
        if (strpos($key, 'id_meta_') === 0 && trim((string)$val) !== '') {
            $meta_key = substr($key, 8); // remove 'id_meta_'
            $id_metadata[$meta_key] = trim((string)$val);
        }
    }
    
    $policyMetadataArr = ['identity' => $id_metadata];
    $potentialLimit = 0.0;
    $assignedCreditScore = 0;
    $assignedCreditRating = null;
    $scoringMetadata = null;

    try {
        require_once __DIR__ . '/engines/credit_limit_engine.php';
        require_once __DIR__ . '/engines/credit_score_engine.php';

        $limitEngine = new CreditLimitEngine($pdo, $tenant_id);
        $scoreEngine = new CreditScoreEngine($pdo, $tenant_id);

        // 1. Initial Score from tenant settings
        $assignedCreditScore = $scoreEngine->getStartingScore();

        // 2. Initial Limit from tenant settings (stored as potential_limit, NOT active)
        $limitResult = $limitEngine->calculateInitialLimit((float) $monthly_income);
        $potentialLimit = (float) ($limitResult['limit'] ?? 0);

        // 3. Band Mapping
        $band = $limitEngine->identifyScoreBand($assignedCreditScore);
        $assignedCreditRating = $band ? ($band['label'] ?? 'Standard') : 'Standard';

        // 4. Build policy_metadata with potential_limit (the "Scratchpad")
        $policyMetadataArr['potential_limit'] = $potentialLimit;
        $policyMetadataArr['starting_score'] = $assignedCreditScore;
        $policyMetadataArr['score_band'] = $assignedCreditRating;
        $policyMetadataArr['limit_calculation'] = $limitResult;
        $policyMetadataArr['config_snapshot'] = $limitEngine->getConfigSnapshot();
        $policyMetadataArr['scoring_snapshot'] = $scoreEngine->getConfigSnapshot();
        $policyMetadataArr['basis'] = 'Initial Assessment via CreditLimitEngine (Walk-in)';
        $policyMetadataArr['income_at_submission'] = (float) $monthly_income;
        $policyMetadataArr['timestamp'] = date('Y-m-d H:i:s');

        $scoringMetadata = json_encode([
            'basis' => 'Initial Assessment (Walk-in)',
            'income_at_submission' => (float) $monthly_income,
            'config_percent' => $limitResult['initial_limit_percent'] ?? 0,
            'potential_limit' => $potentialLimit,
            'engine_reason' => $limitResult['reason'] ?? '',
            'timestamp' => date('Y-m-d H:i:s'),
        ]);

    } catch (\Throwable $engineErr) {
        $policyMetadataArr['engine_error'] = $engineErr->getMessage();
        $policyMetadataArr['fallback'] = true;
        $policyMetadataArr['timestamp'] = date('Y-m-d H:i:s');
        
        $scoringMetadata = json_encode([
            'basis' => 'Engine unavailable (Walk-in) — settings not configured',
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }
    $initial_policy_metadata = json_encode($policyMetadataArr);
    $client_has_policy_metadata = walk_in_client_table_has_column($pdo, 'policy_metadata');

    $client_insert_sql = '
        INSERT INTO clients (
            tenant_id, user_id, first_name, last_name,
            date_of_birth, gender, civil_status, contact_number, email_address,
            present_house_no, present_street, present_barangay, present_city, present_province, present_postal_code,
            permanent_house_no, permanent_street, permanent_barangay, permanent_city, permanent_province, permanent_postal_code,
            same_as_present, employment_status, occupation, employer_name, employer_contact, monthly_income, id_type,
            registration_date, registered_by, client_status, document_verification_status
    ';

    if ($client_has_policy_metadata) {
        $client_insert_sql .= ', policy_metadata';
    }

    $client_insert_sql .= ', credit_limit, last_seen_credit_limit';

    if ($client_has_verification_status) {
        $client_insert_sql .= ', verification_status';
    }

    $client_insert_sql .= '
        ) VALUES (
            ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?,
            CURDATE(), ?, ?, ?
    ';

    if ($client_has_policy_metadata) {
        $client_insert_sql .= ', ?';
    }

    $client_insert_sql .= ', ?, ?';

    if ($client_has_verification_status) {
        $client_insert_sql .= ', ?';
    }

    $client_insert_sql .= '
        )
    ';

    $client_insert = $pdo->prepare($client_insert_sql);
    $client_params = [
        $tenant_id,
        $new_user_id,
        $first_name,
        $last_name,
        $dob,
        ($gender !== '' ? $gender : null),
        ($civil_status !== '' ? $civil_status : null),
        $phone,
        $email,
        ($house_no !== '' ? $house_no : null),
        ($street !== '' ? $street : null),
        ($barangay !== '' ? $barangay : null),
        ($city !== '' ? $city : null),
        ($province !== '' ? $province : null),
        ($postal_code !== '' ? $postal_code : null),
        ($perm_house_no !== '' ? $perm_house_no : null),
        ($perm_street !== '' ? $perm_street : null),
        ($perm_barangay !== '' ? $perm_barangay : null),
        ($perm_city !== '' ? $perm_city : null),
        ($perm_province !== '' ? $perm_province : null),
        ($perm_postal_code !== '' ? $perm_postal_code : null),
        $same_as_present ? 1 : 0,
        ($employment_status !== '' ? $employment_status : null),
        ($occupation !== '' ? $occupation : null),
        ($employer_name !== '' ? $employer_name : null),
        ($employer_contact !== '' ? $employer_contact : null),
        $monthly_income,
        $id_type,
        $registered_by,
        'Active',
        'Approved',
        $potentialLimit,
        $potentialLimit
    ];

    if ($client_has_policy_metadata) {
        array_splice($client_params, 31, 0, [$initial_policy_metadata]);
    }

    if ($client_has_verification_status) {
        $client_params[] = 'Approved';
    }

    $client_insert->execute($client_params);
    
    $new_client_id = (int) $pdo->lastInsertId();

    // Handle Documents if any (simplified)
    $uploaded_count = 0;
    $tenant_upload_key = preg_replace('/[^A-Za-z0-9_-]+/', '_', $tenant_id);
    $upload_dir = __DIR__ . '/../uploads/client_documents/' . $tenant_upload_key . '/' . date('Y/m');
    if (!is_dir($upload_dir)) @mkdir($upload_dir, 0775, true);

    $expected_docs = [
        'doc_id_front' => ['id' => 1, 'type' => 'id_front'],
        'doc_id_back' => ['id' => 2, 'type' => 'id_back'],
        'doc_proof_of_income' => ['id' => 3, 'type' => 'proof_of_income'],
        'doc_proof_of_billing' => ['id' => 4, 'type' => 'proof_of_billing'],
        'doc_proof_of_legitimacy' => ['id' => 5, 'type' => 'proof_of_legitimacy'],
    ];

    foreach ($expected_docs as $field_name => $meta) {
        if (!isset($_FILES[$field_name]) || $_FILES[$field_name]['error'] !== UPLOAD_ERR_OK) {
            continue;
        }

        $original_name = $_FILES[$field_name]['name'];
        $tmp_name = $_FILES[$field_name]['tmp_name'];
        $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        
        if (in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'])) {
            $stored_name = rtrim($tenant_id) . '_' . (int)$new_client_id . '_' . $meta['type'] . '.' . $ext;
            $dest_path = $upload_dir . '/' . $stored_name;
            
            if (move_uploaded_file($tmp_name, $dest_path)) {
                $rel_path = 'uploads/client_documents/' . $tenant_upload_key . '/' . date('Y/m') . '/' . $stored_name;
                $doc_stmt = $pdo->prepare('INSERT INTO client_documents (client_id, tenant_id, document_type_id, file_name, file_path, verification_status) VALUES (?, ?, ?, ?, ?, \'Pending\')');
                $doc_stmt->execute([$new_client_id, $tenant_id, $meta['id'], $original_name, $rel_path]);
                $uploaded_count++;
            }
        }
    }

    $audit_description = 'Walk-in client registered, verified, and activated by staff.';



    $audit_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, tenant_id, action_type, entity_type, entity_id, description) VALUES (?, ?, 'WALK_IN_REGISTERED', 'client', ?, ?)");
    $audit_stmt->execute([$session_user_id > 0 ? $session_user_id : null, $tenant_id, $new_client_id, $audit_description]);

    // Insert Initial Credit Score
    $scoreInsertStmt = $pdo->prepare("
        INSERT INTO credit_scores (
            client_id, 
            tenant_id, 
            credit_score, 
            credit_rating, 
            max_loan_amount, 
            notes, 
            score_metadata,
            computation_date
        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    if ($scoreInsertStmt) {
        $initialNote = "Automated initial score and limit assignment via Tenant Policy Engine (Walk-in).";
        // Walk-in gets the active limit immediately
        $assignedCreditLimit = $potentialLimit; 
        $scoreInsertStmt->execute([
            $new_client_id,
            $tenant_id,
            $assignedCreditScore,
            $assignedCreditRating,
            $assignedCreditLimit,
            $initialNote,
            $scoringMetadata
        ]);
    }

    $pdo->commit();

    // The active limit is already calculated properly via the engine instance
    $computed_credit_limit = $potentialLimit;
    
    $subject = "Welcome to " . $tenant_name;
    $safeFirstName = htmlspecialchars($first_name, ENT_QUOTES, 'UTF-8');
    $safeUsername = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    $safePassword = htmlspecialchars($temp_password_plain, ENT_QUOTES, 'UTF-8');

    $htmlContent = mf_email_template([
        'accent' => '#2563eb',
        'eyebrow' => $tenant_name,
        'title' => 'Welcome to ' . $tenant_name,
        'preheader' => "Your {$tenant_name} account is ready.",
        'intro_html' => "
            <p style='margin: 0 0 14px; font-family: Arial, sans-serif; font-size: 15px; line-height: 1.7; color: #334155;'>
                Hi {$safeFirstName},
            </p>
            <p style='margin: 0 0 14px; font-family: Arial, sans-serif; font-size: 15px; line-height: 1.7; color: #334155;'>
                Your walk-in registration has been approved. You can now log into the Microfin Mobile App using the credentials below:
            </p>
        ",
        'body_html' => mf_email_panel(
                'Your Temporary Login Details',
                mf_email_detail_table([
                    ['label' => 'Username', 'value' => $safeUsername],
                    ['label' => 'Password', 'value' => $safePassword],
                ]),
                'success'
            ),
        'footer_html' => "
            <p style='margin: 0; font-family: Arial, sans-serif; font-size: 12px; line-height: 1.7; color: #64748b;'>
                For your security, you will be required to change your password immediately upon your first login.
            </p>
        ",
    ]);

    $email_result = '';
    if (function_exists('mf_send_brevo_email') && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email_result = mf_send_brevo_email($email, $subject, $htmlContent);
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Walk-in client registered and verified successfully. A password setup email has been sent to ' . htmlspecialchars($email) . '.',
        'client_id' => $new_client_id,
        'client_status' => 'Active',
        'verification_status' => 'Verified',
        'credit_limit' => $computed_credit_limit,
        'uploaded_document_count' => $uploaded_count,
        'email_status' => $email_result
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
