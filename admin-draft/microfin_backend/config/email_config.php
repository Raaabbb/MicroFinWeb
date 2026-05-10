<?php
/**
 * microfin_backend/config/email_config.php
 * Centralized email configuration and utility for MicroFin.
 * Handles Brevo API integration for both web and mobile contexts.
 */

if (!function_exists('mf_get_email_config')) {
    function mf_get_email_config(): array
    {
        static $emailConfig = null;
        if ($emailConfig !== null) {
            return $emailConfig;
        }

        // Try to load from the mobile_api local_config if available, 
        // as it often contains the actual live keys.
        $mobileLocalConfig = [];
        $mobileConfigPath = dirname(__DIR__) . '/mobile_api/local_config.php';
        if (file_exists($mobileConfigPath)) {
            $loaded = include $mobileConfigPath;
            if (is_array($loaded)) {
                $mobileLocalConfig = $loaded;
            }
        }

        // Helper to resolve config from ENV or local_config
        $resolve = function($key, $default) use ($mobileLocalConfig) {
            $env = getenv($key);
            if ($env !== false && $env !== '') return $env;
            if (isset($_ENV[$key]) && $_ENV[$key] !== '') return $_ENV[$key];
            if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') return $_SERVER[$key];
            return $mobileLocalConfig[$key] ?? $default;
        };

        $emailConfig = [
            'api_key'      => $resolve('BREVO_API_KEY', 'YOUR_BREVO_API_KEY'),
            'sender_email' => $resolve('BREVO_SENDER_EMAIL', 'microfin.statements@gmail.com'),
            'sender_name'  => $resolve('BREVO_SENDER_NAME', 'MicroFin'),
            'sandbox'      => filter_var($resolve('BREVO_SANDBOX_MODE', false), FILTER_VALIDATE_BOOLEAN),
            'api_url'      => 'https://api.brevo.com/v3/smtp/email',
        ];

        return $emailConfig;
    }
}

// Ensure global constants are defined for legacy support
$mf_email_settings = mf_get_email_config();
if (!defined('BREVO_API_KEY')) {
    define('BREVO_API_KEY', $mf_email_settings['api_key']);
}
if (!defined('BREVO_SENDER_EMAIL')) {
    define('BREVO_SENDER_EMAIL', $mf_email_settings['sender_email']);
}
if (!defined('BREVO_SENDER_NAME')) {
    define('BREVO_SENDER_NAME', $mf_email_settings['sender_name']);
}

/**
 * Renders the standard MicroFin email layout.
 */
if (!function_exists('mf_render_email_layout')) {
    function mf_render_email_layout(string $preheader, string $headline, string $bodyHtml, string $accentColor = '#0F766E'): string
    {
        $config = mf_get_email_config();
        $appUrl = 'https://microfinwebb-production.up.railway.app'; // Fallback
        
        $safePreheader = htmlspecialchars($preheader, ENT_QUOTES, 'UTF-8');
        $safeHeadline = htmlspecialchars($headline, ENT_QUOTES, 'UTF-8');
        $safeAccent = htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8');
        $safeAppUrl = htmlspecialchars($appUrl, ENT_QUOTES, 'UTF-8');

        return '<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>' . $safeHeadline . '</title>
</head>
<body style="margin:0;padding:24px;background:#f3f4f6;font-family:Arial,sans-serif;color:#111827;">
  <div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . $safePreheader . '</div>
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;margin:0 auto;background:#ffffff;border-radius:24px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,0.05);">
    <tr>
      <td style="background:' . $safeAccent . ';padding:28px 32px;color:#ffffff;">
        <div style="font-size:13px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;opacity:0.8;">MicroFin</div>
        <div style="font-size:28px;font-weight:700;margin-top:8px;">' . $safeHeadline . '</div>
      </td>
    </tr>
    <tr>
      <td style="padding:32px;line-height:1.6;font-size:16px;">' . $bodyHtml . '</td>
    </tr>
    <tr>
      <td style="padding:0 32px 32px;color:#6b7280;font-size:12px;line-height:1.6;">
        This is an automated message from MicroFin. Visit
        <a href="' . $safeAppUrl . '" style="color:' . $safeAccent . ';text-decoration:none;font-weight:bold;">' . $safeAppUrl . '</a>
        for more details.
      </td>
    </tr>
  </table>
</body>
</html>';
    }
}

/**
 * Sends an email using Brevo API v3.
 * 
 * @param string $toEmail
 * @param string $subject
 * @param string $htmlContent
 * @return string Status message (empty if success, error message otherwise)
 */
if (!function_exists('mf_send_brevo_email')) {
    function mf_send_brevo_email($toEmail, $subject, $htmlContent)
    {
        $config = mf_get_email_config();
        $recipient = trim((string) $toEmail);
        
        if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return 'Invalid recipient email address.';
        }

        $apiKey = trim((string) $config['api_key']);
        if ($apiKey === '' || stripos($apiKey, 'YOUR_BREVO_API_KEY') !== false) {
            return 'Brevo API key is not configured.';
        }

        $payload = json_encode([
            'sender' => [
                'name' => (string) $config['sender_name'],
                'email' => (string) $config['sender_email'],
            ],
            'to' => [['email' => $recipient]],
            'subject' => (string) $subject,
            'htmlContent' => (string) $htmlContent,
        ], JSON_UNESCAPED_SLASHES);

        if ($payload === false) {
            return 'Failed to encode Brevo payload.';
        }

        $ch = curl_init($config['api_url']);
        if ($ch === false) {
            return 'Failed to initialize cURL for Brevo.';
        }

        $headers = [
            'api-key: ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        
        // Anti-Suspension Protection: Always ensure strict headers
        if ($config['sandbox']) {
            $headers[] = 'X-Sib-Sandbox: drop';
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $payload,
        ]);

        $result = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($result === false) {
            return 'cURL Error: ' . $curlError;
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            return ''; // Success
        }

        $resp = json_decode($result, true);
        return $resp['message'] ?? 'Brevo API returned error code ' . $httpCode;
    }
}
