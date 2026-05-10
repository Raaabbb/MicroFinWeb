<?php
header('Content-Type: application/json');
ob_start();

$mf_api_applications_responded = false;

function api_applications_respond($payload, int $status = 200): void
{
    global $mf_api_applications_responded;

    $mf_api_applications_responded = true;

    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json');
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    echo json_encode($payload);
    exit;
}

set_exception_handler(static function (Throwable $e): void {
    error_log('api_applications exception: ' . $e->getMessage());
    api_applications_respond([
        'status' => 'error',
        'message' => 'The applications service failed to process the request.',
    ], 500);
});

register_shutdown_function(static function (): void {
    global $mf_api_applications_responded;

    if ($mf_api_applications_responded) {
        return;
    }

    $error = error_get_last();
    if (!$error) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR];
    if (!in_array((int) ($error['type'] ?? 0), $fatalTypes, true)) {
        return;
    }

    error_log('api_applications fatal: ' . ($error['message'] ?? 'Unknown fatal error'));

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json');
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    echo json_encode([
        'status' => 'error',
        'message' => 'The applications service failed to process the request.',
    ]);
});
require_once '../auth/session_auth.php';
mf_start_backend_session();
require_once '../config/db_connect.php';
require_once '../engines/credit_policy.php';
require_once '../documents/document_access.php';
mf_require_tenant_session($pdo, [
    'response' => 'json',
    'status' => 401,
    'message' => 'Unauthorized.',
]);

$tenant_id      = (string) ($_SESSION['tenant_id'] ?? '');
$session_user_id = (int) ($_SESSION['user_id'] ?? 0);

if ($tenant_id === '') {
    api_applications_respond(['status' => 'error', 'message' => 'Missing tenant context.'], 400);
}

// Load permissions
$perm_stmt = $pdo->prepare('
    SELECT p.permission_code 
    FROM role_permissions rp 
    JOIN permissions p ON rp.permission_id = p.permission_id 
    JOIN users u ON u.role_id = rp.role_id
    WHERE u.user_id = ?
');
$perm_stmt->execute([$session_user_id]);
$permissions = $perm_stmt->fetchAll(PDO::FETCH_COLUMN);

function has_perm($code) { global $permissions; return in_array($code, $permissions); }

$action = strtolower(trim((string) ($_GET['action'] ?? $_POST['action'] ?? '')));
$method = $_SERVER['REQUEST_METHOD'];

// ─── GET: list ───────────────────────────────────────────────────────────────
if ($method === 'GET' && ($action === 'list' || $action === '')) {
    if (!has_perm('VIEW_APPLICATIONS') && !has_perm('MANAGE_APPLICATIONS')) {
        api_applications_respond(['status' => 'error', 'message' => 'Permission denied.'], 403);
    }

    $status_filter = trim((string) ($_GET['status'] ?? ''));
    $where_extra   = '';
    $params        = [$tenant_id];

    if ($status_filter !== '' && $status_filter !== 'all') {
        $where_extra = ' AND la.application_status = ?';
        $params[]    = $status_filter;
    } else {
        $where_extra = " AND la.application_status != 'Approved'";
    }

    $stmt = $pdo->prepare("
        SELECT
            la.application_id, la.application_number, la.application_status,
            la.requested_amount, la.approved_amount, la.loan_term_months,
            la.interest_rate, la.loan_purpose, la.submitted_date, la.created_at,
            la.review_notes, la.approval_notes, la.rejection_reason,
              la.application_data,
              JSON_UNQUOTE(JSON_EXTRACT(la.application_data, '$.product_type')) AS product_type,
              c.client_id, c.first_name, c.last_name, c.contact_number, c.email_address,
              lp.product_name
        FROM loan_applications la
        JOIN clients c ON la.client_id = c.client_id
        JOIN loan_products lp ON la.product_id = lp.product_id
        WHERE la.tenant_id = ? $where_extra
        ORDER BY COALESCE(la.submitted_date, la.created_at) DESC
        LIMIT 200
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    api_applications_respond(['status' => 'success', 'data' => $rows]);
}

// ─── GET: view single ────────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'view') {
    if (!has_perm('VIEW_APPLICATIONS') && !has_perm('MANAGE_APPLICATIONS')) {
        api_applications_respond(['status' => 'error', 'message' => 'Permission denied.'], 403);
    }

    $application_id = (int) ($_GET['id'] ?? 0);
    if ($application_id <= 0) {
        api_applications_respond(['status' => 'error', 'message' => 'Invalid application ID.'], 400);
    }

    $stmt = $pdo->prepare("
        SELECT
            la.*,
            c.first_name, c.last_name, c.contact_number, c.email_address,
            c.date_of_birth, c.civil_status, c.occupation, c.employer_name,
            c.monthly_income, c.present_street, c.present_barangay, c.present_city,
            c.present_province, c.credit_limit, c.client_status, c.employment_status, c.document_verification_status,
              la.application_data,
              JSON_UNQUOTE(JSON_EXTRACT(la.application_data, '$.product_type')) AS product_type,
              JSON_UNQUOTE(JSON_EXTRACT(la.application_data, '$.interest_type')) AS interest_type,
              JSON_UNQUOTE(JSON_EXTRACT(la.application_data, '$.processing_fee_percentage')) AS processing_fee_percentage,
              JSON_UNQUOTE(JSON_EXTRACT(la.application_data, '$.service_charge')) AS service_charge,
              JSON_UNQUOTE(JSON_EXTRACT(la.application_data, '$.documentary_stamp')) AS documentary_stamp,
              JSON_UNQUOTE(JSON_EXTRACT(la.application_data, '$.insurance_fee_percentage')) AS insurance_fee_percentage,
              JSON_UNQUOTE(JSON_EXTRACT(la.application_data, '$.early_settlement_fee_type')) AS early_settlement_fee_type,
              JSON_UNQUOTE(JSON_EXTRACT(la.application_data, '$.early_settlement_fee_value')) AS early_settlement_fee_value,
              JSON_UNQUOTE(JSON_EXTRACT(la.application_data, '$.grace_period_days')) AS grace_period_days,
              lp.product_name, lp.min_amount, lp.max_amount,
              lp.interest_rate AS product_interest_rate,
              lp.min_term_months, lp.max_term_months,
            (
                SELECT cs.credit_score
                FROM credit_scores cs
                WHERE cs.client_id = la.client_id AND cs.tenant_id = la.tenant_id
                ORDER BY cs.computation_date DESC, cs.score_id DESC
                LIMIT 1
            ) AS latest_credit_score,
            (
                SELECT cs.credit_rating
                FROM credit_scores cs
                WHERE cs.client_id = la.client_id AND cs.tenant_id = la.tenant_id
                ORDER BY cs.computation_date DESC, cs.score_id DESC
                LIMIT 1
            ) AS latest_credit_rating,
            (
                SELECT cs.max_loan_amount
                FROM credit_scores cs
                WHERE cs.client_id = la.client_id AND cs.tenant_id = la.tenant_id
                ORDER BY cs.computation_date DESC, cs.score_id DESC
                LIMIT 1
            ) AS latest_max_loan_amount,
            (
                SELECT ci.recommendation
                FROM credit_investigations ci
                WHERE ci.client_id = la.client_id AND ci.tenant_id = la.tenant_id AND ci.status = 'Completed'
                ORDER BY COALESCE(ci.completed_at, ci.investigation_date, ci.created_at) DESC, ci.ci_id DESC
                LIMIT 1
            ) AS latest_ci_recommendation,
            (
                SELECT ci.status
                FROM credit_investigations ci
                WHERE ci.client_id = la.client_id AND ci.tenant_id = la.tenant_id
                ORDER BY COALESCE(ci.completed_at, ci.investigation_date, ci.created_at) DESC, ci.ci_id DESC
                LIMIT 1
            ) AS latest_ci_status
        FROM loan_applications la
        JOIN clients c ON la.client_id = c.client_id
        JOIN loan_products lp ON la.product_id = lp.product_id
        WHERE la.application_id = ? AND la.tenant_id = ?
        LIMIT 1
    ");
    $stmt->execute([$application_id, $tenant_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        api_applications_respond(['status' => 'error', 'message' => 'Application not found.'], 404);
    }

    // Decode JSON data
    if (!empty($row['application_data']) && is_string($row['application_data'])) {
        $row['application_data'] = json_decode($row['application_data'], true) ?? [];
    }

    $application_docs_stmt = $pdo->prepare("
        SELECT
            ad.app_document_id, ad.document_type_id, ad.file_name, ad.file_path, ad.upload_date,
            dt.document_name
        FROM application_documents ad
        LEFT JOIN document_types dt ON dt.document_type_id = ad.document_type_id
        WHERE ad.application_id = ? AND ad.tenant_id = ?
        ORDER BY ad.upload_date DESC, ad.app_document_id DESC
    ");
    $application_docs_stmt->execute([$application_id, $tenant_id]);
    $row['application_documents'] = array_map(
        static fn(array $document): array => mf_document_attach_url($document),
        $application_docs_stmt->fetchAll(PDO::FETCH_ASSOC)
    );

    $client_docs_stmt = $pdo->prepare("
        SELECT
            cd.client_document_id, cd.document_type_id, cd.file_name, cd.file_path, cd.upload_date,
            cd.verification_status, dt.document_name, dt.is_required
        FROM client_documents cd
        LEFT JOIN document_types dt ON dt.document_type_id = cd.document_type_id
        WHERE cd.client_id = ? AND cd.tenant_id = ?
        ORDER BY dt.is_required DESC, cd.upload_date DESC, cd.client_document_id DESC
    ");
    $client_docs_stmt->execute([(int) ($row['client_id'] ?? 0), $tenant_id]);
    $row['client_documents'] = array_map(
        static fn(array $document): array => mf_document_attach_url($document),
        $client_docs_stmt->fetchAll(PDO::FETCH_ASSOC)
    );

    $credit_limit_rules = mf_get_tenant_credit_limit_rules($pdo, $tenant_id);
    $upgrade_metrics = mf_credit_policy_fetch_upgrade_metrics($pdo, $tenant_id, (int) ($row['client_id'] ?? 0));
    $row['credit_upgrade'] = mf_credit_policy_compute_upgrade_snapshot($credit_limit_rules, $row, $upgrade_metrics);

    api_applications_respond(['status' => 'success', 'data' => $row]);
}

// ─── POST: update status ─────────────────────────────────────────────────────
if ($method === 'POST') {
    if (!has_perm('MANAGE_APPLICATIONS')) {
        api_applications_respond(['status' => 'error', 'message' => 'Permission denied.'], 403);
    }

    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    $application_id = (int) ($payload['application_id'] ?? 0);
    $new_action     = strtolower(trim((string) ($payload['action'] ?? '')));
    $notes          = trim((string) ($payload['notes'] ?? ''));
    $approved_amount = isset($payload['approved_amount']) ? (float) $payload['approved_amount'] : null;

    if ($application_id <= 0) {
        api_applications_respond(['status' => 'error', 'message' => 'Invalid application ID.'], 400);
    }

    // Fetch current app
    $cur_stmt = $pdo->prepare("
        SELECT
            la.application_status, la.client_id, la.product_id, la.requested_amount, la.approved_amount,
            la.loan_term_months, lp.min_amount, lp.max_amount, lp.min_term_months, lp.max_term_months
        FROM loan_applications la
        JOIN loan_products lp ON lp.product_id = la.product_id
        WHERE la.application_id = ? AND la.tenant_id = ? AND lp.tenant_id = la.tenant_id
        LIMIT 1
    ");
    $cur_stmt->execute([$application_id, $tenant_id]);
    $current = $cur_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$current) {
        api_applications_respond(['status' => 'error', 'message' => 'Application not found.'], 404);
    }

    // Get employee_id for the logged-in user
    $emp_stmt = $pdo->prepare('SELECT employee_id FROM employees WHERE user_id = ? AND tenant_id = ? LIMIT 1');
    $emp_stmt->execute([$session_user_id, $tenant_id]);
    $employee_id = $emp_stmt->fetchColumn();
    $employee_id = $employee_id !== false ? (int) $employee_id : null;

    $now = date('Y-m-d H:i:s');
    $cur_status = $current['application_status'];

    if ($new_action === 'evaluate_policy') {
        if (in_array($cur_status, ['Approved', 'Rejected', 'Cancelled', 'Withdrawn'], true)) {
            api_applications_respond(['status' => 'error', 'message' => "Cannot run credit policy on application with status '$cur_status'."], 400);
        }

        try {
            $pdo->beginTransaction();
            $policyResult = mf_apply_application_policy($pdo, $tenant_id, $application_id, [
                'employee_id' => $employee_id,
                'session_user_id' => $session_user_id,
            ]);
            $pdo->commit();

            api_applications_respond($policyResult);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            api_applications_respond(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    $allowed_transitions = [
        'submit'          => ['Draft'          => 'Submitted'],
        'start_review'    => ['Submitted'       => 'Under Review', 'Pending Review' => 'Under Review'],
        'verify_docs'     => ['Under Review'    => 'Document Verification'],
        'credit_inv'      => ['Document Verification' => 'Credit Investigation'],
        'for_approval'    => ['Credit Investigation' => 'For Approval'],
        'approve'         => [
            'Submitted' => 'Approved',
            'Pending Review' => 'Approved',
            'Under Review' => 'Approved',
            'Document Verification' => 'Approved',
            'Credit Investigation' => 'Approved',
            'For Approval' => 'Approved'
        ],
        'reject'          => ['Submitted' => 'Rejected', 'Under Review' => 'Rejected', 'Pending Review' => 'Rejected', 'For Approval' => 'Rejected', 'Document Verification' => 'Rejected', 'Credit Investigation' => 'Rejected'],
        'cancel'          => ['Draft' => 'Cancelled', 'Submitted' => 'Cancelled'],
    ];

    if (!isset($allowed_transitions[$new_action])) {
        api_applications_respond(['status' => 'error', 'message' => 'Unknown action: ' . $new_action], 400);
    }

    if (!isset($allowed_transitions[$new_action][$cur_status])) {
        api_applications_respond(['status' => 'error', 'message' => "Cannot perform '$new_action' on application with status '$cur_status'."], 400);
    }

    $new_status = $allowed_transitions[$new_action][$cur_status];

    try {
        $pdo->beginTransaction();
        $responseMessage = "Application status updated to '$new_status'.";
        $skipDefaultAudit = false;

        if ($new_action === 'approve') {
            $finalApprovedAmount = $approved_amount !== null && $approved_amount > 0
                ? $approved_amount
                : ((float) ($current['approved_amount'] ?? 0) > 0
                    ? (float) $current['approved_amount']
                    : (float) ($current['requested_amount'] ?? 0));

            if ($finalApprovedAmount <= 0) {
                throw new Exception('Approved amount is required.');
            }

            $productMinAmount = (float) ($current['min_amount'] ?? 0);
            $productMaxAmount = (float) ($current['max_amount'] ?? 0);
            if ($productMinAmount > 0 && $finalApprovedAmount < $productMinAmount) {
                throw new Exception('Approved amount is below the minimum allowed for this loan product.');
            }
            if ($productMaxAmount > 0 && $finalApprovedAmount > $productMaxAmount) {
                throw new Exception('Approved amount exceeds the maximum allowed for this loan product.');
            }

            $pdo->prepare("
                UPDATE loan_applications
                SET application_status = ?, approved_amount = ?, approved_by = ?,
                    approval_date = ?, approval_notes = ?, updated_at = ?
                WHERE application_id = ? AND tenant_id = ?
            ")->execute([
                $new_status,
                $finalApprovedAmount,
                $employee_id,
                $now, $notes, $now,
                $application_id, $tenant_id
            ]);

            $responseMessage = 'Loan application approved. It is now ready for disbursement in Loans Management.';

        } elseif ($new_action === 'reject') {
            if ($notes === '') {
                throw new Exception('Rejection reason is required.');
            }
            $pdo->prepare("
                UPDATE loan_applications
                SET application_status = ?, rejected_by = ?,
                    rejection_date = ?, rejection_reason = ?, updated_at = ?
                WHERE application_id = ? AND tenant_id = ?
            ")->execute([
                $new_status,
                $employee_id,
                $now, $notes, $now,
                $application_id, $tenant_id
            ]);

        } elseif (in_array($new_action, ['start_review', 'verify_docs', 'credit_inv', 'for_approval'])) {
            $pdo->prepare("
                UPDATE loan_applications
                SET application_status = ?, reviewed_by = ?,
                    review_date = ?, review_notes = ?, updated_at = ?
                WHERE application_id = ? AND tenant_id = ?
            ")->execute([
                $new_status,
                $employee_id,
                $now, $notes, $now,
                $application_id, $tenant_id
            ]);

        } else {
            // submit or cancel
            $extra_date = ($new_action === 'submit') ? ", submitted_date = '$now'" : '';
            $pdo->prepare("
                UPDATE loan_applications
                SET application_status = ?, updated_at = ? $extra_date
                WHERE application_id = ? AND tenant_id = ?
            ")->execute([$new_status, $now, $application_id, $tenant_id]);

            if ($new_action === 'submit') {
                $policyResult = mf_apply_application_policy($pdo, $tenant_id, $application_id, [
                    'employee_id' => $employee_id,
                    'session_user_id' => $session_user_id,
                ]);
                $new_status = $policyResult['new_status'] ?? $new_status;
                $responseMessage = $policyResult['message'] ?? $responseMessage;
                $skipDefaultAudit = true;
            }
        }

        if (!$skipDefaultAudit) {
            $pdo->prepare("INSERT INTO audit_logs (user_id, tenant_id, action_type, entity_type, entity_id, description) VALUES (?, ?, ?, 'loan_application', ?, ?)")
                ->execute([$session_user_id, $tenant_id, 'APP_STATUS_' . strtoupper($new_action), $application_id, "Status changed from '$cur_status' to '$new_status'. Notes: $notes"]);
        }

        $pdo->commit();

        // -- Create notification for the client (after commit so main flow is safe) --
        try {
            $clientUserStmt = $pdo->prepare("
                SELECT c.user_id, la.application_number
                FROM loan_applications la
                JOIN clients c ON c.client_id = la.client_id
                WHERE la.application_id = ? AND la.tenant_id = ?
                LIMIT 1
            ");
            $clientUserStmt->execute([$application_id, $tenant_id]);
            $clientInfo = $clientUserStmt->fetch(PDO::FETCH_ASSOC);

            if ($clientInfo && !empty($clientInfo['user_id'])) {
                $clientUserId = (int) $clientInfo['user_id'];
                $appNumber = $clientInfo['application_number'] ?? '';
                $notifType = 'General';
                $notifTitle = 'Application Update';
                $notifMessage = "Your loan application #{$appNumber} status has been updated to: {$new_status}.";
                $notifPriority = 'Medium';

                if ($new_action === 'approve') {
                    $notifType = 'Loan Approved';
                    $notifTitle = 'Congratulations! Loan Approved';
                    $approvedAmt = number_format($finalApprovedAmount ?? 0, 2);
                    $notifMessage = "Your application #{$appNumber} for PHP {$approvedAmt} has been approved!";
                    $notifPriority = 'High';
                } elseif ($new_action === 'reject') {
                    $notifType = 'Loan Rejected';
                    $notifTitle = 'Application Rejected';
                    $notifMessage = "Your application #{$appNumber} has been rejected. Reason: {$notes}";
                    $notifPriority = 'High';
                }

                $nStmt = $pdo->prepare("INSERT INTO notifications (user_id, tenant_id, notification_type, title, message, priority) VALUES (?, ?, ?, ?, ?, ?)");
                $nStmt->execute([$clientUserId, $tenant_id, $notifType, $notifTitle, $notifMessage, $notifPriority]);
            }
        } catch (Throwable $notifErr) {
            error_log("Notification insert failed: " . $notifErr->getMessage());
        }
        api_applications_respond(['status' => 'success', 'message' => $responseMessage, 'new_status' => $new_status]);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        api_applications_respond(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
}

api_applications_respond(['status' => 'error', 'message' => 'Invalid request.'], 400);
