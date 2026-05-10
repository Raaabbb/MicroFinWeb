<?php
require_once __DIR__ . '/../config/email_config.php';
require_once __DIR__ . '/config.php';

function microfin_email_config(): array
{
    $config = mf_get_email_config();
    return [
        'provider' => 'brevo',
        'api_url' => $config['api_url'],
        'api_key' => $config['api_key'],
        'sender_email' => $config['sender_email'],
        'sender_name' => $config['sender_name'],
        'sandbox_mode' => $config['sandbox'],
        'app_base_url' => microfin_app_base_url(),
    ];
}

function microfin_email_is_configured(): bool
{
    $config = mf_get_email_config();
    return $config['api_key'] !== '' && stripos($config['api_key'], 'YOUR_BREVO_API_KEY') === false;
}

function microfin_generate_one_time_code(int $length = 6): string
{
    $maxValue = (10 ** $length) - 1;
    return str_pad((string) random_int(0, $maxValue), $length, '0', STR_PAD_LEFT);
}

function microfin_build_verification_token(string $code, int $ttlMinutes = 15): string
{
    return json_encode([
        'hash' => password_hash($code, PASSWORD_DEFAULT),
        'expires_at' => gmdate('Y-m-d\TH:i:s\Z', time() + ($ttlMinutes * 60)),
    ]);
}

function microfin_decode_verification_token(?string $storedToken): ?array
{
    if (!$storedToken) {
        return null;
    }

    $decoded = json_decode($storedToken, true);
    if (is_array($decoded) && isset($decoded['hash'])) {
        return $decoded;
    }

    return ['hash' => $storedToken, 'expires_at' => null];
}

function microfin_verification_token_is_expired(?string $storedToken): bool
{
    $payload = microfin_decode_verification_token($storedToken);
    if (!$payload || empty($payload['expires_at'])) {
        return false;
    }

    $expiresAt = strtotime((string) $payload['expires_at']);
    return $expiresAt !== false && $expiresAt < time();
}

function microfin_verify_verification_code(?string $storedToken, string $code): bool
{
    $payload = microfin_decode_verification_token($storedToken);
    if (!$payload || microfin_verification_token_is_expired($storedToken)) {
        return false;
    }

    return password_verify($code, (string) $payload['hash']);
}

function microfin_log_email_attempt(mysqli $conn, array $details): void
{
    try {
        $stmt = $conn->prepare("
            INSERT INTO email_delivery_logs (
                tenant_id,
                user_id,
                email_type,
                recipient_email,
                recipient_name,
                subject,
                provider,
                provider_message_id,
                status,
                error_message,
                request_payload,
                response_payload
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $tenantId = $details['tenant_id'] ?? null;
        $userId = isset($details['user_id']) ? (int) $details['user_id'] : null;
        $emailType = (string) ($details['email_type'] ?? 'generic');
        $recipientEmail = (string) ($details['recipient_email'] ?? '');
        $recipientName = $details['recipient_name'] ?? null;
        $subject = (string) ($details['subject'] ?? '');
        $provider = (string) ($details['provider'] ?? 'brevo');
        $providerMessageId = $details['provider_message_id'] ?? null;
        $status = (string) ($details['status'] ?? 'failed');
        $errorMessage = $details['error_message'] ?? null;
        $requestPayload = isset($details['request_payload']) ? json_encode($details['request_payload']) : null;
        $responsePayload = isset($details['response_payload']) ? json_encode($details['response_payload']) : null;

        $stmt->bind_param(
            'sissssssssss',
            $tenantId,
            $userId,
            $emailType,
            $recipientEmail,
            $recipientName,
            $subject,
            $provider,
            $providerMessageId,
            $status,
            $errorMessage,
            $requestPayload,
            $responsePayload
        );
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        // Email delivery should not fail because logging failed.
    }
}

function microfin_send_email(mysqli $conn, array $message): array
{
    $config = microfin_email_config();
    $recipientEmail = trim((string) ($message['to_email'] ?? ''));
    $recipientName = trim((string) ($message['to_name'] ?? ''));
    $subject = trim((string) ($message['subject'] ?? ''));
    $htmlContent = (string) ($message['html_content'] ?? '');
    $textContent = (string) ($message['text_content'] ?? '');
    $emailType = (string) ($message['email_type'] ?? 'generic');

    if ($recipientEmail === '' || $subject === '' || ($htmlContent === '' && $textContent === '')) {
        return ['success' => false, 'message' => 'Email payload is incomplete.'];
    }

    if (!microfin_email_is_configured()) {
        $result = ['success' => false, 'message' => 'Brevo is not configured.'];
        microfin_log_email_attempt($conn, [
            'tenant_id' => $message['tenant_id'] ?? null,
            'user_id' => $message['user_id'] ?? null,
            'email_type' => $emailType,
            'recipient_email' => $recipientEmail,
            'recipient_name' => $recipientName,
            'subject' => $subject,
            'status' => 'failed',
            'error_message' => $result['message'],
        ]);
        return $result;
    }

    // Use the CENTRALIZED sender function
    $error = mf_send_brevo_email($recipientEmail, $subject, $htmlContent);
    $success = ($error === '');

    $response = [
        'success' => $success,
        'message' => $success ? 'Email queued successfully.' : $error,
        'response' => $error ? ['error' => $error] : ['status' => 'sent']
    ];

    microfin_log_email_attempt($conn, [
        'tenant_id' => $message['tenant_id'] ?? null,
        'user_id' => $message['user_id'] ?? null,
        'email_type' => $emailType,
        'recipient_email' => $recipientEmail,
        'recipient_name' => $recipientName,
        'subject' => $subject,
        'status' => $success ? 'sent' : 'failed',
        'error_message' => $error ?: null,
        'request_payload' => ['to' => $recipientEmail, 'subject' => $subject],
        'response_payload' => $response['response'],
    ]);

    return $response;
}

function microfin_render_email_layout(string $preheader, string $headline, string $bodyHtml, string $accentColor = '#0F766E'): string
{
    return mf_render_email_layout($preheader, $headline, $bodyHtml, $accentColor);
}

function microfin_send_registration_otp_email(mysqli $conn, array $context): array
{
    $tenantName = trim((string) ($context['tenant_name'] ?? 'MicroFin'));
    $recipientName = trim((string) ($context['recipient_name'] ?? ''));
    $otp = (string) ($context['otp'] ?? '');
    $minutes = (int) ($context['ttl_minutes'] ?? 15);

    $bodyHtml = '
        <p style="margin:0 0 16px;font-size:16px;line-height:1.7;">Hello ' . htmlspecialchars($recipientName !== '' ? $recipientName : 'there', ENT_QUOTES, 'UTF-8') . ',</p>
        <p style="margin:0 0 20px;font-size:16px;line-height:1.7;">Use the verification code below to finish creating your account for <strong>' . htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') . '</strong>.</p>
        <div style="margin:0 0 20px;padding:18px 20px;border-radius:16px;background:#ecfeff;border:1px solid #a5f3fc;font-size:32px;font-weight:700;letter-spacing:0.32em;text-align:center;">' . htmlspecialchars($otp, ENT_QUOTES, 'UTF-8') . '</div>
        <p style="margin:0 0 12px;font-size:14px;line-height:1.7;">This code expires in ' . $minutes . ' minutes.</p>
        <p style="margin:0;font-size:14px;line-height:1.7;color:#6b7280;">If you did not request this, you can safely ignore this email.</p>';

    $textContent = "Hello " . ($recipientName !== '' ? $recipientName : 'there') . ",\n\nUse this verification code to finish creating your account for {$tenantName}: {$otp}\n\nThis code expires in {$minutes} minutes.";

    return microfin_send_email($conn, [
        'tenant_id' => $context['tenant_id'] ?? null,
        'user_id' => $context['user_id'] ?? null,
        'email_type' => 'registration_otp',
        'to_email' => $context['to_email'] ?? '',
        'to_name' => $recipientName,
        'subject' => $tenantName . ' verification code',
        'html_content' => microfin_render_email_layout(
            'Your MicroFin registration verification code is ready.',
            'Verify your email',
            $bodyHtml,
            '#0F766E'
        ),
        'text_content' => $textContent,
        'tags' => ['registration', 'otp'],
    ]);
}

function microfin_send_password_reset_email(mysqli $conn, array $context): array
{
    $tenantName = trim((string) ($context['tenant_name'] ?? 'MicroFin'));
    $recipientName = trim((string) ($context['recipient_name'] ?? ''));
    $otp = (string) ($context['otp'] ?? '');
    $minutes = (int) ($context['ttl_minutes'] ?? 15);
    $loginUsername = trim((string) ($context['login_username'] ?? ''));
    $loginUsernameHtml = $loginUsername !== ''
        ? '<p style="margin:0 0 20px;font-size:14px;line-height:1.7;">Login username: <strong>' . htmlspecialchars($loginUsername, ENT_QUOTES, 'UTF-8') . '</strong></p>'
        : '';
    $loginUsernameText = $loginUsername !== '' ? "\nLogin username: {$loginUsername}\n" : "\n";

    $bodyHtml = '
        <p style="margin:0 0 16px;font-size:16px;line-height:1.7;">Hello ' . htmlspecialchars($recipientName !== '' ? $recipientName : 'there', ENT_QUOTES, 'UTF-8') . ',</p>
        <p style="margin:0 0 20px;font-size:16px;line-height:1.7;">We received a request to reset the password for your <strong>' . htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') . '</strong> account.</p>
        ' . $loginUsernameHtml . '
        <div style="margin:0 0 20px;padding:18px 20px;border-radius:16px;background:#fff7ed;border:1px solid #fdba74;font-size:32px;font-weight:700;letter-spacing:0.32em;text-align:center;">' . htmlspecialchars($otp, ENT_QUOTES, 'UTF-8') . '</div>
        <p style="margin:0 0 12px;font-size:14px;line-height:1.7;">This reset code expires in ' . $minutes . ' minutes.</p>
        <p style="margin:0;font-size:14px;line-height:1.7;color:#6b7280;">If you did not request a password reset, you can ignore this message.</p>';

    $textContent = "Hello " . ($recipientName !== '' ? $recipientName : 'there') . ",\n\nUse this password reset code for {$tenantName}: {$otp}{$loginUsernameText}\nThis code expires in {$minutes} minutes.";

    return microfin_send_email($conn, [
        'tenant_id' => $context['tenant_id'] ?? null,
        'user_id' => $context['user_id'] ?? null,
        'email_type' => 'password_reset_otp',
        'to_email' => $context['to_email'] ?? '',
        'to_name' => $recipientName,
        'subject' => $tenantName . ' password reset code',
        'html_content' => microfin_render_email_layout(
            'Your MicroFin password reset code is ready.',
            'Reset your password',
            $bodyHtml,
            '#B45309'
        ),
        'text_content' => $textContent,
        'tags' => ['password-reset', 'otp'],
    ]);
}

function microfin_send_account_lookup_email(mysqli $conn, array $context): array
{
    $recipientName = trim((string) ($context['recipient_name'] ?? ''));
    $usernames = array_values(array_filter(array_map(static function ($value): string {
        return trim((string) $value);
    }, (array) ($context['login_usernames'] ?? []))));

    if (empty($usernames)) {
        return ['success' => false, 'message' => 'No login usernames were provided.'];
    }

    $listItemsHtml = implode('', array_map(static function (string $username): string {
        return '<li style="margin:0 0 10px;"><strong>' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . '</strong></li>';
    }, $usernames));

    $bodyHtml = '
        <p style="margin:0 0 16px;font-size:16px;line-height:1.7;">Hello ' . htmlspecialchars($recipientName !== '' ? $recipientName : 'there', ENT_QUOTES, 'UTF-8') . ',</p>
        <p style="margin:0 0 20px;font-size:16px;line-height:1.7;">Here are the login usernames linked to this email address in the shared MicroFin mobile app.</p>
        <ul style="margin:0 0 20px;padding-left:20px;font-size:16px;line-height:1.7;">' . $listItemsHtml . '</ul>
        <p style="margin:0;font-size:14px;line-height:1.7;color:#6b7280;">Use the exact username above when signing in or resetting your password.</p>';

    $textContent = "Hello " . ($recipientName !== '' ? $recipientName : 'there') . ",\n\nHere are your MicroFin login usernames:\n- " . implode("\n- ", $usernames) . "\n\nUse the exact username above when signing in or resetting your password.";

    return microfin_send_email($conn, [
        'tenant_id' => $context['tenant_id'] ?? null,
        'user_id' => $context['user_id'] ?? null,
        'email_type' => 'account_lookup',
        'to_email' => $context['to_email'] ?? '',
        'to_name' => $recipientName,
        'subject' => 'Your MicroFin login usernames',
        'html_content' => microfin_render_email_layout(
            'Your MicroFin login usernames are ready.',
            'Find your account',
            $bodyHtml,
            '#1D4ED8'
        ),
        'text_content' => $textContent,
        'tags' => ['account-lookup', 'username-recovery'],
    ]);
}

function microfin_send_receipt_email(mysqli $conn, array $context): array
{
    $tenantName = trim((string) ($context['tenant_name'] ?? 'MicroFin'));
    $recipientName = trim((string) ($context['client_name'] ?? ''));
    $paymentReference = trim((string) ($context['payment_reference'] ?? ''));
    $loanNumber = trim((string) ($context['loan_number'] ?? ''));
    $paymentMethod = trim((string) ($context['payment_method'] ?? 'Payment'));
    $paymentDate = trim((string) ($context['payment_date'] ?? ''));
    $amount = number_format((float) ($context['amount'] ?? 0), 2);

    $bodyHtml = '
        <p style="margin:0 0 16px;font-size:16px;line-height:1.7;">Hello ' . htmlspecialchars($recipientName !== '' ? $recipientName : 'there', ENT_QUOTES, 'UTF-8') . ',</p>
        <p style="margin:0 0 20px;font-size:16px;line-height:1.7;">Your payment has been posted successfully. Here is your receipt summary from <strong>' . htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') . '</strong>.</p>
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;background:#f9fafb;border:1px solid #e5e7eb;border-radius:18px;overflow:hidden;">
          <tr><td style="padding:14px 18px;font-size:14px;border-bottom:1px solid #e5e7eb;"><strong>Reference</strong></td><td style="padding:14px 18px;font-size:14px;border-bottom:1px solid #e5e7eb;text-align:right;">' . htmlspecialchars($paymentReference, ENT_QUOTES, 'UTF-8') . '</td></tr>
          <tr><td style="padding:14px 18px;font-size:14px;border-bottom:1px solid #e5e7eb;"><strong>Loan Number</strong></td><td style="padding:14px 18px;font-size:14px;border-bottom:1px solid #e5e7eb;text-align:right;">' . htmlspecialchars($loanNumber, ENT_QUOTES, 'UTF-8') . '</td></tr>
          <tr><td style="padding:14px 18px;font-size:14px;border-bottom:1px solid #e5e7eb;"><strong>Payment Method</strong></td><td style="padding:14px 18px;font-size:14px;border-bottom:1px solid #e5e7eb;text-align:right;">' . htmlspecialchars($paymentMethod, ENT_QUOTES, 'UTF-8') . '</td></tr>
          <tr><td style="padding:14px 18px;font-size:14px;border-bottom:1px solid #e5e7eb;"><strong>Payment Date</strong></td><td style="padding:14px 18px;font-size:14px;border-bottom:1px solid #e5e7eb;text-align:right;">' . htmlspecialchars($paymentDate, ENT_QUOTES, 'UTF-8') . '</td></tr>
          <tr><td style="padding:16px 18px;font-size:15px;"><strong>Total Paid</strong></td><td style="padding:16px 18px;font-size:24px;font-weight:700;text-align:right;color:#0F766E;">PHP ' . $amount . '</td></tr>
        </table>
        <p style="margin:20px 0 0;font-size:14px;line-height:1.7;color:#6b7280;">Keep this email for your records.</p>';

    $textContent = "Hello " . ($recipientName !== '' ? $recipientName : 'there') . ",\n\nYour payment receipt from {$tenantName}\nReference: {$paymentReference}\nLoan Number: {$loanNumber}\nPayment Method: {$paymentMethod}\nPayment Date: {$paymentDate}\nTotal Paid: PHP {$amount}";

    return microfin_send_email($conn, [
        'tenant_id' => $context['tenant_id'] ?? null,
        'user_id' => $context['user_id'] ?? null,
        'email_type' => 'payment_receipt',
        'to_email' => $context['client_email'] ?? '',
        'to_name' => $recipientName,
        'subject' => 'Payment receipt ' . $paymentReference,
        'html_content' => microfin_render_email_layout(
            'Your MicroFin payment receipt is ready.',
            'Payment received',
            $bodyHtml,
            '#0F766E'
        ),
        'text_content' => $textContent,
        'tags' => ['receipt', 'payment'],
    ]);
}
