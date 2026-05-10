<?php

function mf_billing_get_setting(PDO $pdo, string $tenantId, string $settingKey, string $default = ''): string
{
    $stmt = $pdo->prepare('SELECT setting_value FROM system_settings WHERE tenant_id = ? AND setting_key = ? LIMIT 1');
    $stmt->execute([$tenantId, $settingKey]);
    $value = $stmt->fetchColumn();
    return $value !== false ? trim((string)$value) : $default;
}

function mf_billing_set_setting(PDO $pdo, string $tenantId, string $settingKey, string $settingValue): void
{
    $stmt = $pdo->prepare("
        INSERT INTO system_settings (tenant_id, setting_key, setting_value, setting_category, data_type)
        VALUES (?, ?, ?, 'Billing', 'String')
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$tenantId, $settingKey, $settingValue]);
}

function mf_billing_get_contact(PDO $pdo, string $tenantId): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            t.tenant_name,
            u.user_id,
            u.email,
            u.username,
            u.first_name,
            u.last_name,
            COALESCE(u.can_manage_billing, 0) AS can_manage_billing,
            COALESCE(u.user_type, '') AS user_type,
            COALESCE(u.status, '') AS user_status
        FROM users u
        INNER JOIN tenants t ON t.tenant_id = u.tenant_id
        WHERE u.tenant_id = ?
          AND u.deleted_at IS NULL
          AND TRIM(COALESCE(u.email, '')) <> ''
        ORDER BY
            COALESCE(u.can_manage_billing, 0) DESC,
            CASE WHEN u.user_type = 'Admin' THEN 0 ELSE 1 END,
            CASE WHEN u.status = 'Active' THEN 0 ELSE 1 END,
            u.user_id ASC
        LIMIT 1
    ");
    $stmt->execute([$tenantId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function mf_billing_contact_name(array $contact): string
{
    $fullName = trim((string)($contact['first_name'] ?? '') . ' ' . (string)($contact['last_name'] ?? ''));
    if ($fullName !== '') {
        return $fullName;
    }

    $username = trim((string)($contact['username'] ?? ''));
    if ($username !== '') {
        return $username;
    }

    return 'Customer';
}

function mf_billing_money(float $amount): string
{
    return '&#8369;' . number_format($amount, 2);
}

function mf_billing_date_label(string $dateValue, string $format = 'F j, Y', string $default = 'N/A'): string
{
    $trimmed = trim($dateValue);
    if ($trimmed === '') {
        return $default;
    }

    $timestamp = strtotime($trimmed);
    if ($timestamp === false) {
        return $default;
    }

    return date($format, $timestamp);
}

function mf_billing_send_due_soon_email(PDO $pdo, string $tenantId, array $details): string
{
    if (!function_exists('mf_send_brevo_email')) {
        return 'Brevo email helper is unavailable.';
    }

    $contact = mf_billing_get_contact($pdo, $tenantId);
    if (!$contact || trim((string)($contact['email'] ?? '')) === '') {
        return 'No tenant billing contact email found.';
    }

    $tenantName = htmlspecialchars((string)($contact['tenant_name'] ?? 'MicroFin Tenant'), ENT_QUOTES, 'UTF-8');
    $recipientName = htmlspecialchars(mf_billing_contact_name($contact), ENT_QUOTES, 'UTF-8');
    $planTier = htmlspecialchars((string)($details['plan_tier'] ?? 'Subscription'), ENT_QUOTES, 'UTF-8');
    $dueDate = mf_billing_date_label((string)($details['due_date'] ?? ''), 'F j, Y');
    $amountText = mf_billing_money((float)($details['amount'] ?? 0));

    $html = mf_email_template([
        'accent' => '#1d4ed8',
        'eyebrow' => 'Billing Reminder',
        'title' => 'Upcoming Subscription Payment',
        'preheader' => "Upcoming payment reminder for {$tenantName}.",
        'intro_html' => "
            <p style=\"margin: 0 0 14px; font-family: Arial, sans-serif; font-size: 15px; line-height: 1.7; color: #334155;\">
                Hello {$recipientName},
            </p>
            <p style=\"margin: 0 0 14px; font-family: Arial, sans-serif; font-size: 15px; line-height: 1.7; color: #334155;\">
                This is a reminder that your <strong>{$tenantName}</strong> subscription payment is coming up soon.
            </p>
        ",
        'body_html' => mf_email_panel(
            'Payment Summary',
            mf_email_detail_table([
                ['label' => 'Plan', 'value' => $planTier, 'html' => true],
                ['label' => 'Amount', 'value' => $amountText, 'html' => true],
                ['label' => 'Scheduled charge date', 'value' => $dueDate],
            ]),
            'info'
        ) . "
            <p style=\"margin: 0; font-family: Arial, sans-serif; font-size: 15px; line-height: 1.7; color: #334155;\">
                Please make sure your saved billing method is up to date so your renewal can be processed smoothly.
            </p>
        ",
        'footer_html' => "
            <p style=\"margin: 0; font-family: Arial, sans-serif; font-size: 12px; line-height: 1.7; color: #64748b;\">
                Sent by MicroFin Billing for {$tenantName}. If you need help with your subscription, reply to this email and our billing team will assist you.
            </p>
        ",
    ]);

    return mf_send_brevo_email((string)$contact['email'], "{$tenantName} - Upcoming Subscription Payment", $html);
}

function mf_billing_send_receipt_email(PDO $pdo, string $tenantId, array $details): string
{
    if (!function_exists('mf_send_brevo_email')) {
        return 'Brevo email helper is unavailable.';
    }

    $contact = mf_billing_get_contact($pdo, $tenantId);
    if (!$contact || trim((string)($contact['email'] ?? '')) === '') {
        return 'No tenant billing contact email found.';
    }

    $tenantName = htmlspecialchars((string)($contact['tenant_name'] ?? 'MicroFin Tenant'), ENT_QUOTES, 'UTF-8');
    $recipientName = htmlspecialchars(mf_billing_contact_name($contact), ENT_QUOTES, 'UTF-8');
    $planTier = htmlspecialchars((string)($details['plan_tier'] ?? 'Subscription'), ENT_QUOTES, 'UTF-8');
    $amountText = mf_billing_money((float)($details['amount'] ?? 0));
    $paymentDate = mf_billing_date_label((string)($details['payment_date'] ?? ''), 'F j, Y g:i A');
    $periodStart = mf_billing_date_label((string)($details['period_start'] ?? ''));
    $periodEnd = mf_billing_date_label((string)($details['period_end'] ?? ''));
    $nextBillingDate = mf_billing_date_label((string)($details['next_billing_date'] ?? ''));
    $invoiceNumber = htmlspecialchars((string)($details['invoice_number'] ?? 'N/A'), ENT_QUOTES, 'UTF-8');
    $paymentReference = htmlspecialchars((string)($details['payment_reference'] ?? 'N/A'), ENT_QUOTES, 'UTF-8');
    $paymentMethod = htmlspecialchars((string)($details['payment_method'] ?? 'Saved payment method'), ENT_QUOTES, 'UTF-8');

    $html = mf_email_template([
        'accent' => '#0f8a5f',
        'eyebrow' => 'Payment Receipt',
        'title' => 'Subscription Payment Received',
        'preheader' => "Your payment receipt for {$tenantName} is ready.",
        'intro_html' => "
            <p style=\"margin: 0 0 14px; font-family: Arial, sans-serif; font-size: 15px; line-height: 1.7; color: #334155;\">
                Hello {$recipientName},
            </p>
            <p style=\"margin: 0 0 14px; font-family: Arial, sans-serif; font-size: 15px; line-height: 1.7; color: #334155;\">
                Your payment for <strong>{$tenantName}</strong> has been successfully recorded. This email serves as your receipt.
            </p>
        ",
        'body_html' => mf_email_panel(
            'Receipt Details',
            mf_email_detail_table([
                ['label' => 'Plan', 'value' => $planTier, 'html' => true],
                ['label' => 'Amount paid', 'value' => $amountText, 'html' => true],
                ['label' => 'Payment date', 'value' => $paymentDate],
                ['label' => 'Payment reference', 'value' => $paymentReference],
                ['label' => 'Invoice number', 'value' => $invoiceNumber],
                ['label' => 'Payment method', 'value' => $paymentMethod],
                ['label' => 'Coverage period', 'value' => $periodStart . ' to ' . $periodEnd],
                ['label' => 'Next scheduled payment', 'value' => $nextBillingDate],
            ]),
            'success'
        ),
        'footer_html' => "
            <p style=\"margin: 0; font-family: Arial, sans-serif; font-size: 12px; line-height: 1.7; color: #64748b;\">
                Keep this email for your records. If you need billing assistance, reply to this message and the MicroFin Billing team will help you.
            </p>
        ",
    ]);

    return mf_send_brevo_email((string)$contact['email'], "{$tenantName} - Payment Receipt {$invoiceNumber}", $html);
}
