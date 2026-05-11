<?php
// backend/db_connect.php
// Centralized, secure database connection wrapper using PDO.

$charset = 'utf8mb4';

// ---------------------------------------------------------------
// Primary (local) DB defaults — Localhost (XAMPP/WAMP) credentials.
// ---------------------------------------------------------------
$host = 'localhost';
$port = 3306;
$db = 'microfin_db';
$user = 'root';
$pass = '';

// (Old local fallback variables removed as the primary is now localhost)

function mf_env_first(array $keys)
{
    foreach ($keys as $key) {
        $value = getenv($key);
        if ($value !== false && trim((string) $value) !== '') {
            return (string) $value;
        }
        if (isset($_ENV[$key]) && trim((string) $_ENV[$key]) !== '') {
            return (string) $_ENV[$key];
        }
        if (isset($_SERVER[$key]) && trim((string) $_SERVER[$key]) !== '') {
            return (string) $_SERVER[$key];
        }
    }

    return null;
}

function mf_db_target_signature(array $target): string
{
    $hasPass = !empty($target['pass']) ? 'YES' : 'NO';
    return implode('|', [
        (string) ($target['host'] ?? ''),
        (string) ($target['port'] ?? ''),
        (string) ($target['db'] ?? ''),
        (string) ($target['user'] ?? ''),
        "PASS:{$hasPass}"
    ]);
}

$mf_local_mail_config = [];
$mf_local_mail_config_path = __DIR__ . DIRECTORY_SEPARATOR . 'local_mail_config.php';
if (is_file($mf_local_mail_config_path)) {
    $loaded_cfg = require $mf_local_mail_config_path;
    if (is_array($loaded_cfg)) {
        $mf_local_mail_config = $loaded_cfg;
    }
}

function mf_local_config_value(string $key, string $default = ''): string
{
    global $mf_local_mail_config;
    if (isset($mf_local_mail_config[$key])) {
        $value = trim((string) $mf_local_mail_config[$key]);
        if ($value !== '') {
            return $value;
        }
    }
    return $default;
}

// Override defaults with env-var URL (Railway DATABASE_URL, etc.).
$databaseUrl = mf_env_first(['DATABASE_URL', 'MYSQL_URL', 'MYSQL_PUBLIC_URL', 'MYSQL_PRIVATE_URL']);
if ($databaseUrl !== null) {
    $parts = parse_url($databaseUrl);
    if ($parts !== false) {
        if (!empty($parts['host'])) {
            $host = (string) $parts['host'];
        }
        if (!empty($parts['port'])) {
            $port = (int) $parts['port'];
        }
        if (array_key_exists('user', $parts)) {
            $user = urldecode((string) $parts['user']);
        }
        if (array_key_exists('pass', $parts)) {
            $pass = urldecode((string) $parts['pass']);
        }
        if (!empty($parts['path'])) {
            $db = ltrim((string) $parts['path'], '/');
        }
    }
}

// Override further with Railway plugin-style discrete variables
// (these win over DATABASE_URL when explicitly set).
$envHost = mf_env_first(['MYSQLHOST', 'DB_HOST']);
if ($envHost !== null) {
    $host = $envHost;
}
$envPort = mf_env_first(['MYSQLPORT', 'DB_PORT']);
if ($envPort !== null) {
    $port = (int) $envPort;
}
$envDb = mf_env_first(['MYSQLDATABASE', 'DB_NAME']);
if ($envDb !== null) {
    $db = $envDb;
}
$envUser = mf_env_first(['MYSQLUSER', 'DB_USER']);
if ($envUser !== null) {
    $user = $envUser;
}
$envPass = mf_env_first(['MYSQLPASSWORD', 'DB_PASSWORD']);
if ($envPass !== null) {
    $pass = $envPass;
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'db_runtime.php';

$mf_db_runtime = mf_resolve_db_targets();
$mf_db_targets = $mf_db_runtime['targets'] ?? [];
$mf_db_mode = (string) ($mf_db_runtime['mode'] ?? 'local');

$host = (string) ($mf_db_targets[0]['host'] ?? 'localhost');
$port = (int) ($mf_db_targets[0]['port'] ?? 3306);
$db = (string) ($mf_db_targets[0]['db'] ?? 'microfin_db');
$user = (string) ($mf_db_targets[0]['user'] ?? 'root');
$pass = (string) ($mf_db_targets[0]['pass'] ?? '');
require_once __DIR__ . DIRECTORY_SEPARATOR . 'email_config.php';

function mf_email_escape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function mf_email_button(string $label, string $url, string $accent = '#0f8a5f'): string
{
    $safeLabel = mf_email_escape($label);
    $safeUrl = mf_email_escape($url);

    return "
        <table role=\"presentation\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"margin: 24px 0 12px;\">
            <tr>
                <td align=\"center\" bgcolor=\"{$accent}\" style=\"border-radius: 14px;\">
                    <a href=\"{$safeUrl}\" style=\"display: inline-block; padding: 14px 22px; font-family: Arial, sans-serif; font-size: 15px; font-weight: 700; line-height: 1; color: #ffffff; text-decoration: none; border-radius: 14px;\">
                        {$safeLabel}
                    </a>
                </td>
            </tr>
        </table>
    ";
}

function mf_email_detail_table(array $rows): string
{
    $html = '';

    foreach ($rows as $row) {
        $label = trim((string) ($row['label'] ?? ''));
        $value = $row['value'] ?? '';
        $allowHtml = !empty($row['html']);

        if ($label === '' || trim(strip_tags((string) $value)) === '') {
            continue;
        }

        $safeLabel = mf_email_escape($label);
        $safeValue = $allowHtml ? (string) $value : mf_email_escape($value);

        $html .= "
            <tr>
                <td valign=\"top\" style=\"padding: 0 0 12px; font-family: Arial, sans-serif; font-size: 12px; font-weight: 700; line-height: 1.4; letter-spacing: 0.08em; text-transform: uppercase; color: #64748b;\">
                    {$safeLabel}
                </td>
            </tr>
            <tr>
                <td valign=\"top\" style=\"padding: 0 0 14px; font-family: Arial, sans-serif; font-size: 15px; line-height: 1.65; color: #0f172a; border-bottom: 1px solid #e2e8f0;\">
                    {$safeValue}
                </td>
            </tr>
        ";
    }

    if ($html === '') {
        return '';
    }

    return "
        <table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"border-collapse: collapse;\">
            {$html}
        </table>
    ";
}

function mf_email_panel(string $title, string $contentHtml, string $tone = 'neutral'): string
{
    $palettes = [
        'brand' => ['bg' => '#f0fdf4', 'border' => '#bbf7d0', 'title' => '#166534'],
        'info' => ['bg' => '#eff6ff', 'border' => '#bfdbfe', 'title' => '#1d4ed8'],
        'success' => ['bg' => '#ecfdf5', 'border' => '#a7f3d0', 'title' => '#047857'],
        'warning' => ['bg' => '#fff7ed', 'border' => '#fed7aa', 'title' => '#c2410c'],
        'danger' => ['bg' => '#fef2f2', 'border' => '#fecaca', 'title' => '#b91c1c'],
        'neutral' => ['bg' => '#f8fafc', 'border' => '#e2e8f0', 'title' => '#0f172a'],
    ];

    $palette = $palettes[$tone] ?? $palettes['neutral'];
    $titleHtml = trim($title) === ''
        ? ''
        : '<p style="margin: 0 0 14px; font-family: Arial, sans-serif; font-size: 16px; font-weight: 700; line-height: 1.4; color: ' . $palette['title'] . ';">' . mf_email_escape($title) . '</p>';

    return "
        <table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"margin: 20px 0; border-collapse: separate;\">
            <tr>
                <td style=\"padding: 20px 22px; background: {$palette['bg']}; border: 1px solid {$palette['border']}; border-radius: 18px;\">
                    {$titleHtml}
                    {$contentHtml}
                </td>
            </tr>
        </table>
    ";
}

function mf_email_template(array $options): string
{
    $accent = trim((string) ($options['accent'] ?? '#0f8a5f'));
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $accent)) {
        $accent = '#0f8a5f';
    }

    $brandLabel = mf_email_escape($options['brand_label'] ?? (defined('BREVO_SENDER_NAME') ? BREVO_SENDER_NAME : 'MicroFin'));
    $eyebrow = trim((string) ($options['eyebrow'] ?? ''));
    $title = mf_email_escape($options['title'] ?? 'MicroFin Update');
    $preheader = mf_email_escape($options['preheader'] ?? $options['title'] ?? 'MicroFin update');
    $introHtml = (string) ($options['intro_html'] ?? '');
    $bodyHtml = (string) ($options['body_html'] ?? '');
    $footerHtml = (string) ($options['footer_html'] ?? '');

    $eyebrowHtml = $eyebrow === ''
        ? ''
        : '<p style="margin: 0 0 10px; font-family: Arial, sans-serif; font-size: 12px; font-weight: 700; line-height: 1.2; letter-spacing: 0.12em; text-transform: uppercase; color: #64748b;">' . mf_email_escape($eyebrow) . '</p>';

    if ($footerHtml === '') {
        $footerHtml = '
            <p style="margin: 0; font-family: Arial, sans-serif; font-size: 12px; line-height: 1.7; color: #64748b;">
                This is an automated message from MicroFin. If you need help, reply to this email and our team will assist you.
            </p>
        ';
    }

    return "<!DOCTYPE html>
<html lang=\"en\">
<body style=\"margin: 0; padding: 0; background: #eef3f8;\">
    <div style=\"display: none; max-height: 0; overflow: hidden; opacity: 0; mso-hide: all;\">
        {$preheader}
    </div>
    <table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"background: #eef3f8;\">
        <tr>
            <td align=\"center\" style=\"padding: 28px 16px;\">
                <table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"max-width: 680px; background: #ffffff; border: 1px solid #dce5ee; border-radius: 24px;\">
                    <tr>
                        <td style=\"height: 8px; font-size: 0; line-height: 0; background: {$accent}; border-radius: 24px 24px 0 0;\">&nbsp;</td>
                    </tr>
                    <tr>
                        <td style=\"padding: 28px 32px 20px;\">
                            <span style=\"display: inline-block; padding: 8px 12px; background: #eff6ff; border: 1px solid #dbeafe; border-radius: 999px; font-family: Arial, sans-serif; font-size: 12px; font-weight: 700; line-height: 1; letter-spacing: 0.08em; text-transform: uppercase; color: #1d4ed8;\">
                                {$brandLabel}
                            </span>
                            <div style=\"height: 18px; line-height: 18px; font-size: 18px;\">&nbsp;</div>
                            {$eyebrowHtml}
                            <h1 style=\"margin: 0 0 14px; font-family: Arial, sans-serif; font-size: 30px; font-weight: 800; line-height: 1.2; letter-spacing: -0.02em; color: #0f172a;\">
                                {$title}
                            </h1>
                            {$introHtml}
                            {$bodyHtml}
                        </td>
                    </tr>
                    <tr>
                        <td style=\"padding: 0 32px 28px;\">
                            <div style=\"height: 1px; background: #e2e8f0; margin-bottom: 18px;\"></div>
                            {$footerHtml}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>";
}

function mf_db_is_retryable_disconnect(\Throwable $error): bool
{
    $message = (string) $error->getMessage();

    foreach ([
        'SQLSTATE[HY000] [2006]',
        'SQLSTATE[HY000] [2013]',
        'MySQL server has gone away',
        'Lost connection to MySQL server',
    ] as $needle) {
        if (stripos($message, $needle) !== false) {
            return true;
        }
    }

    return false;
}

function mf_db_should_expose_debug(): bool
{
    // Always expose debug info on Railway for setup troubleshooting
    if (defined('MF_DB_MODE_RAILWAY') || mf_env_first(['RAILWAY_SERVICE_ID'])) {
        return true;
    }

    $flag = mf_env_first(['MF_DB_DEBUG']);
    if ($flag === null) {
        return PHP_SAPI === 'cli';
    }

    return in_array(strtolower(trim($flag)), ['1', 'true', 'yes', 'on'], true);
}

function mf_db_request_context(): array
{
    return [
        'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? PHP_SAPI),
        'uri' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
        'remote_addr' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
    ];
}

function mf_db_log_connection_failure(string $errorId, \Throwable $error, array $context = []): void
{
    $payload = array_merge(mf_db_request_context(), $context, [
        'error_id' => $errorId,
        'message' => $error->getMessage(),
    ]);

    $encodedPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);
    if ($encodedPayload === false) {
        $encodedPayload = $error->getMessage();
    }

    error_log('Database Connection Failed: ' . $encodedPayload);
}

function mf_db_connect_target(array $candidateTarget, string $charset, array $options): array
{
    $targetDsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        (string) $candidateTarget['host'],
        (int) $candidateTarget['port'],
        (string) $candidateTarget['db'],
        $charset
    );

    $attempt = 0;
    do {
        $attempt++;

        try {
            $pdo = new PDO(
                $targetDsn,
                (string) $candidateTarget['user'],
                (string) $candidateTarget['pass'],
                $options
            );

            return [
                'pdo' => $pdo,
                'attempts' => $attempt,
                'dsn' => $targetDsn,
            ];
        } catch (\Throwable $connectionError) {
            if ($attempt >= 2 || !mf_db_is_retryable_disconnect($connectionError)) {
                throw $connectionError;
            }

            usleep(250000);
        }
    } while ($attempt < 2);

    throw new RuntimeException('Unable to establish database connection.');
}

// PDO Options
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_PERSISTENT => false,
    PDO::ATTR_TIMEOUT => 2,
];

try {
    $lastConnectionError = null;
    $lastConnectionTarget = null;
    $connectedTargetIndex = null;
    $connectedTargetAttempts = 1;
    $connectionFailures = [];

    $targetsToTry = $mf_db_targets;
    // If the first target is localhost with a password, ensure we try it without password quickly too
    if (isset($targetsToTry[0]) && ($targetsToTry[0]['host'] === 'localhost' || $targetsToTry[0]['host'] === '127.0.0.1') && !empty($targetsToTry[0]['pass'])) {
        $noPassTarget = $targetsToTry[0];
        $noPassTarget['pass'] = '';
        // Insert no-pass attempt as second target
        array_splice($targetsToTry, 1, 0, [$noPassTarget]);
    }

    foreach ($targetsToTry as $index => $candidateTarget) {
        try {
            $connectionResult = mf_db_connect_target($candidateTarget, $charset, $options);
            $pdo = $connectionResult['pdo'];

            $host = (string) $candidateTarget['host'];
            $port = (int) $candidateTarget['port'];
            $db = (string) $candidateTarget['db'];
            $user = (string) $candidateTarget['user'];
            $pass = (string) $candidateTarget['pass'];
            $connectedTargetIndex = $index;
            $connectedTargetAttempts = (int) ($connectionResult['attempts'] ?? 1);
            break;
        } catch (\Throwable $connectionError) {
            $lastConnectionError = $connectionError;
            $lastConnectionTarget = $candidateTarget;
            $connectionFailures[] = [
                'target' => mf_db_target_signature($candidateTarget),
                'message' => $connectionError->getMessage(),
            ];
        }
    }

    if (!isset($pdo)) {
        throw $lastConnectionError ?? new RuntimeException('Unable to establish database connection.');
    }

    if ($mf_db_mode !== 'railway' && $connectedTargetIndex !== null && $connectedTargetIndex > 0) {
        error_log('Primary localhost DB connection failed; using alternate local credentials.');
    }

    if ($connectedTargetAttempts > 1) {
        error_log(sprintf(
            'Recovered transient DB connection after %d attempts for %s',
            $connectedTargetAttempts,
            mf_db_target_signature($mf_db_targets[$connectedTargetIndex] ?? [])
        ));
    }
} catch (\Throwable $e) {
    $errorId = bin2hex(random_bytes(4));

    mf_db_log_connection_failure($errorId, $e, [
        'db_mode' => $mf_db_mode ?? 'unknown',
        'target' => isset($lastConnectionTarget) && is_array($lastConnectionTarget)
            ? mf_db_target_signature($lastConnectionTarget)
            : '',
        'attempted_targets' => $connectionFailures ?? [],
    ]);

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'Critical System Error [' . $errorId . ']: Unable to establish database connection.' . PHP_EOL);
        if (mf_db_should_expose_debug()) {
            fwrite(STDERR, $e->getMessage() . PHP_EOL);
        }
        exit(1);
    }

    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(500);
    }

    $response = [
        'status' => 'error',
        'message' => 'Critical System Error: Unable to establish database connection.',
        'error_id' => $errorId,
    ];

    if (mf_db_should_expose_debug()) {
        $response['debug'] = $e->getMessage();
        $response['dsn'] = $connectionResult['dsn'] ?? 'N/A';
        $response['context'] = [
            'host' => (string)$host,
            'port' => (int)$port,
            'db'   => (string)$db,
            'user' => (string)$user,
            'mode' => (string)$mf_db_mode,
            'failures' => $connectionFailures
        ];
    }

    echo json_encode($response);
    exit;
}
