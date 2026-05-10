<?php

if (!function_exists('mf_backend_session_timeout_seconds')) {
    function mf_backend_session_timeout_seconds(): int
    {
        return 1800;
    }
}

if (!function_exists('mf_backend_session_timeout_minutes')) {
    function mf_backend_session_timeout_minutes(): int
    {
        return 30;
    }
}

if (!function_exists('mf_backend_session_cookie_name')) {
    function mf_backend_session_cookie_name(): string
    {
        return 'mf_backend_session_token';
    }
}

if (!function_exists('mf_backend_session_cookie_options')) {
    function mf_backend_session_cookie_options(int $expiresAt): array
    {
        $isSecure = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';

        return [
            'expires' => $expiresAt,
            'path' => '/',
            'domain' => '',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }
}

if (!function_exists('mf_set_backend_session_cookie')) {
    function mf_set_backend_session_cookie(string $token, ?int $expiresAt = null): void
    {
        if (headers_sent()) {
            return;
        }

        $value = trim($token);
        if ($value === '') {
            return;
        }

        $ttl = $expiresAt ?? (time() + mf_backend_session_timeout_seconds());
        setcookie(mf_backend_session_cookie_name(), $value, mf_backend_session_cookie_options($ttl));
        $_COOKIE[mf_backend_session_cookie_name()] = $value;
    }
}

if (!function_exists('mf_clear_backend_session_cookie')) {
    function mf_clear_backend_session_cookie(): void
    {
        if (!headers_sent()) {
            setcookie(mf_backend_session_cookie_name(), '', mf_backend_session_cookie_options(time() - 3600));
        }

        unset($_COOKIE[mf_backend_session_cookie_name()]);
    }
}

if (!function_exists('mf_start_backend_session')) {
    function mf_start_backend_session(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $timeout = mf_backend_session_timeout_seconds();

        if (!headers_sent()) {
            ini_set('session.gc_maxlifetime', (string) $timeout);
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_samesite', 'Lax');

            if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
                ini_set('session.cookie_secure', '1');
            }
        }

        session_start();
    }
}

if (!function_exists('mf_backend_session_now')) {
    function mf_backend_session_now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now');
    }
}

if (!function_exists('mf_backend_session_expiry_string')) {
    function mf_backend_session_expiry_string(?DateTimeImmutable $base = null): string
    {
        $anchor = $base ?? mf_backend_session_now();
        return $anchor
            ->add(new DateInterval('PT' . mf_backend_session_timeout_seconds() . 'S'))
            ->format('Y-m-d H:i:s');
    }
}

if (!function_exists('mf_backend_session_ip')) {
    function mf_backend_session_ip(): ?string
    {
        // Temporarily disabled by request.
        return null;
    }
}

if (!function_exists('mf_backend_session_user_agent')) {
    function mf_backend_session_user_agent(): ?string
    {
        // Temporarily disabled by request.
        return null;
    }
}

if (!function_exists('mf_backend_session_snapshot')) {
    function mf_backend_session_snapshot(): array
    {
        return [
            'token' => trim((string) ($_SESSION['backend_session_token'] ?? '')),
            'cookie_token' => trim((string) ($_COOKIE[mf_backend_session_cookie_name()] ?? '')),
            'user_id' => (int) ($_SESSION['backend_session_user_id'] ?? 0),
            'context' => trim((string) ($_SESSION['backend_session_context'] ?? '')),
            'tenant_id' => isset($_SESSION['tenant_id']) ? trim((string) $_SESSION['tenant_id']) : '',
            'tenant_slug' => isset($_SESSION['tenant_slug']) ? trim((string) $_SESSION['tenant_slug']) : '',
            'super_admin_id' => (int) ($_SESSION['super_admin_id'] ?? 0),
            'user_id_session' => (int) ($_SESSION['user_id'] ?? 0),
            'user_logged_in' => !empty($_SESSION['user_logged_in']),
            'super_admin_logged_in' => !empty($_SESSION['super_admin_logged_in']),
        ];
    }
}

if (!function_exists('mf_get_active_browser_backend_session')) {
    function mf_get_active_browser_backend_session(PDO $pdo): ?array
    {
        $snapshot = mf_backend_session_snapshot();
        if ($snapshot['token'] === '' || $snapshot['user_id'] <= 0) {
            return null;
        }

        try {
            $stmt = $pdo->prepare('
                SELECT session_id, user_id, tenant_id, last_activity_at, expires_at
                FROM user_sessions
                WHERE session_token = ?
                  AND user_id = ?
                  AND expires_at > NOW()
                LIMIT 1
            ');
            $stmt->execute([$snapshot['token'], $snapshot['user_id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('Unable to inspect active browser session: ' . $e->getMessage());
            return null;
        }

        if (!$row) {
            return null;
        }

        return [
            'session_id' => (int) ($row['session_id'] ?? 0),
            'user_id' => (int) ($row['user_id'] ?? 0),
            'tenant_id' => isset($row['tenant_id']) ? trim((string) $row['tenant_id']) : '',
            'last_activity_at' => $row['last_activity_at'] ?? null,
            'expires_at' => $row['expires_at'] ?? null,
            'context' => trim((string) ($snapshot['context'] ?? '')) !== '' ? trim((string) $snapshot['context']) : ((isset($row['tenant_id']) && $row['tenant_id'] !== null && trim((string) $row['tenant_id']) !== '') ? 'tenant' : 'super_admin'),
            'super_admin_logged_in' => !empty($snapshot['super_admin_logged_in']),
            'user_logged_in' => !empty($snapshot['user_logged_in']),
        ];
    }
}

if (!function_exists('mf_browser_has_active_backend_session')) {
    function mf_browser_has_active_backend_session(PDO $pdo): bool
    {
        return mf_get_active_browser_backend_session($pdo) !== null;
    }
}

if (!function_exists('mf_backend_session_matches_expected_context')) {
    function mf_backend_session_matches_expected_context(array $snapshot, string $expectedContext): bool
    {
        $normalizedContext = $expectedContext === 'super_admin' ? 'super_admin' : 'tenant';

        if ($normalizedContext === 'tenant' && !empty($snapshot['super_admin_logged_in']) && !empty($snapshot['user_logged_in']) && (int) ($snapshot['user_id_session'] ?? 0) === 0) {
            return true;
        }

        return trim((string) ($snapshot['context'] ?? '')) === $normalizedContext;
    }
}

if (!function_exists('mf_backend_session_is_impersonation')) {
    function mf_backend_session_is_impersonation(): bool
    {
        return !empty($_SESSION['super_admin_logged_in'])
            && !empty($_SESSION['user_logged_in'])
            && (int) ($_SESSION['user_id'] ?? -1) === 0
            && !empty($_SESSION['tenant_id'])
            && !empty($_SESSION['super_admin_id']);
    }
}

if (!function_exists('mf_backend_session_destroy_php_session')) {
    function mf_backend_session_destroy_php_session(): void
    {
        mf_clear_backend_session_cookie();
        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE && ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'] ?? '/',
                $params['domain'] ?? '',
                !empty($params['secure']),
                !empty($params['httponly'])
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}

if (!function_exists('mf_clear_backend_session_pointer')) {
    function mf_clear_backend_session_pointer(): void
    {
        unset(
            $_SESSION['backend_session_token'],
            $_SESSION['backend_session_user_id'],
            $_SESSION['backend_session_context'],
            $_SESSION['backend_session_expires_at']
        );
    }
}

if (!function_exists('mf_expire_backend_session_record')) {
    function mf_expire_backend_session_record(PDO $pdo, string $token): void
    {
        $token = trim($token);
        if ($token === '') {
            return;
        }

        try {
            $stmt = $pdo->prepare('
                UPDATE user_sessions
                SET last_activity_at = NOW(),
                    expires_at = NOW()
                WHERE session_token = ?
            ');
            $stmt->execute([$token]);
        } catch (Throwable $e) {
            error_log('Unable to expire backend session: ' . $e->getMessage());
        }
    }
}

if (!function_exists('mf_destroy_backend_session')) {
    function mf_destroy_backend_session(PDO $pdo, bool $destroyPhpSession = true): void
    {
        $token = trim((string) ($_SESSION['backend_session_token'] ?? ''));

        if ($token !== '') {
            mf_expire_backend_session_record($pdo, $token);
        }

        if ($destroyPhpSession) {
            mf_backend_session_destroy_php_session();
        } else {
            mf_clear_backend_session_pointer();
        }
    }
}

if (!function_exists('mf_create_backend_session')) {
    function mf_create_backend_session(PDO $pdo, int $authUserId, ?string $tenantId, string $context): ?string
    {
        if ($authUserId <= 0) {
            return null;
        }

        $normalizedContext = $context === 'super_admin' ? 'super_admin' : 'tenant';
        $normalizedTenantId = $tenantId !== null && trim($tenantId) !== '' ? trim($tenantId) : null;

        if ($normalizedContext === 'super_admin') {
            $normalizedTenantId = null;
        }

        if (!empty($_SESSION['backend_session_token'])) {
            mf_clear_backend_session_pointer();
        }

        session_regenerate_id(true);

        $token = bin2hex(random_bytes(32));

        $stmt = $pdo->prepare('
            INSERT INTO user_sessions (user_id, tenant_id, session_token, ip_address, user_agent, last_activity_at, expires_at)
            VALUES (?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 30 MINUTE))
        ');
        $stmt->execute([
            $authUserId,
            $normalizedTenantId,
            $token,
            mf_backend_session_ip(),
            mf_backend_session_user_agent(),
        ]);

        $_SESSION['backend_session_token'] = $token;
        $_SESSION['backend_session_user_id'] = $authUserId;
        $_SESSION['backend_session_context'] = $normalizedContext;
        $_SESSION['backend_session_expires_at'] = null;
        mf_set_backend_session_cookie($token);

        return $token;
    }
}

if (!function_exists('mf_backend_session_needs_restore')) {
    function mf_backend_session_needs_restore(array $snapshot, string $expectedContext): bool
    {
        if ($snapshot['token'] === '' || $snapshot['user_id'] <= 0) {
            return true;
        }

        if ($expectedContext === 'super_admin') {
            return empty($snapshot['super_admin_logged_in']) || $snapshot['super_admin_id'] <= 0;
        }

        return empty($snapshot['user_logged_in']) || $snapshot['tenant_id'] === '';
    }
}

if (!function_exists('mf_restore_backend_session_from_cookie')) {
    function mf_restore_backend_session_from_cookie(PDO $pdo, string $expectedContext): bool
    {
        $token = trim((string) ($_COOKIE[mf_backend_session_cookie_name()] ?? ''));
        if ($token === '') {
            return false;
        }

        try {
            $stmt = $pdo->prepare('
                SELECT
                    us.session_id,
                    us.session_token,
                    us.user_id,
                    us.tenant_id,
                    us.expires_at,
                    u.username,
                    u.user_type,
                    u.ui_theme,
                    u.status AS user_status,
                    u.force_password_change,
                    r.role_name,
                    r.is_system_role,
                    t.tenant_name,
                    t.tenant_slug,
                    b.theme_primary_color
                FROM user_sessions us
                JOIN users u ON u.user_id = us.user_id
                LEFT JOIN user_roles r ON r.role_id = u.role_id
                LEFT JOIN tenants t ON t.tenant_id = us.tenant_id
                LEFT JOIN tenant_branding b ON b.tenant_id = t.tenant_id
                WHERE us.session_token = ?
                  AND us.expires_at > NOW()
                LIMIT 1
            ');
            $stmt->execute([$token]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('Unable to restore backend session from cookie: ' . $e->getMessage());
            return false;
        }

        if (!$row || (string) ($row['user_status'] ?? '') !== 'Active') {
            mf_clear_backend_session_cookie();
            return false;
        }

        $tenantId = trim((string) ($row['tenant_id'] ?? ''));
        $context = $tenantId !== '' ? 'tenant' : 'super_admin';
        if ($expectedContext === 'tenant' && $context !== 'tenant') {
            return false;
        }
        if ($expectedContext === 'super_admin' && $context !== 'super_admin') {
            return false;
        }

        $_SESSION['backend_session_token'] = (string) ($row['session_token'] ?? $token);
        $_SESSION['backend_session_user_id'] = (int) ($row['user_id'] ?? 0);
        $_SESSION['backend_session_context'] = $context;
        $_SESSION['backend_session_expires_at'] = null;

        if ($context === 'tenant') {
            unset(
                $_SESSION['super_admin_logged_in'],
                $_SESSION['super_admin_id'],
                $_SESSION['super_admin_username'],
                $_SESSION['super_admin_force_password_change'],
                $_SESSION['super_admin_onboarding_required']
            );

            $_SESSION['user_logged_in'] = true;
            $_SESSION['user_id'] = (int) ($row['user_id'] ?? 0);
            $_SESSION['username'] = (string) ($row['username'] ?? '');
            $_SESSION['tenant_id'] = $tenantId;
            $_SESSION['tenant_name'] = (string) ($row['tenant_name'] ?? '');
            $_SESSION['tenant_slug'] = (string) ($row['tenant_slug'] ?? '');
            $_SESSION['role'] = (string) ($row['role_name'] ?? '');
            $_SESSION['user_type'] = (string) ($row['user_type'] ?? '');
            $_SESSION['theme'] = (string) ($row['theme_primary_color'] ?? '#0f172a');
            $_SESSION['ui_theme'] = ((string) ($row['ui_theme'] ?? 'light') === 'dark') ? 'dark' : 'light';
        } else {
            unset(
                $_SESSION['user_logged_in'],
                $_SESSION['user_id'],
                $_SESSION['username'],
                $_SESSION['tenant_id'],
                $_SESSION['tenant_name'],
                $_SESSION['tenant_slug'],
                $_SESSION['role'],
                $_SESSION['user_type'],
                $_SESSION['theme']
            );

            $_SESSION['super_admin_logged_in'] = true;
            $_SESSION['super_admin_id'] = (int) ($row['user_id'] ?? 0);
            $_SESSION['super_admin_username'] = (string) ($row['username'] ?? '');
            $_SESSION['ui_theme'] = ((string) ($row['ui_theme'] ?? 'light') === 'dark') ? 'dark' : 'light';
            $_SESSION['super_admin_force_password_change'] = !empty($row['force_password_change']);
            $_SESSION['super_admin_onboarding_required'] = false;
        }

        return true;
    }
}

if (!function_exists('mf_validate_backend_session')) {
    function mf_validate_backend_session(PDO $pdo, string $expectedContext): bool
    {
        $snapshot = mf_backend_session_snapshot();
        if (mf_backend_session_needs_restore($snapshot, $expectedContext)) {
            if (!mf_restore_backend_session_from_cookie($pdo, $expectedContext)) {
                return false;
            }
            $snapshot = mf_backend_session_snapshot();
        }

        $normalizedContext = $expectedContext === 'super_admin' ? 'super_admin' : 'tenant';
        $params = [$snapshot['token'], $snapshot['user_id']];
        $sql = '
            SELECT session_id, user_id, tenant_id, expires_at
            FROM user_sessions
            WHERE session_token = ?
              AND user_id = ?
              AND expires_at > NOW()
        ';

        if ($normalizedContext === 'super_admin') {
            if (!$snapshot['super_admin_logged_in'] || $snapshot['super_admin_id'] <= 0 || $snapshot['context'] !== 'super_admin') {
                return false;
            }

            $sql .= ' AND tenant_id IS NULL';
        } else {
            if (!$snapshot['user_logged_in']) {
                return false;
            }

            if (mf_backend_session_is_impersonation()) {
                if ($snapshot['context'] !== 'super_admin' || $snapshot['super_admin_id'] <= 0) {
                    return false;
                }

                $sql .= ' AND tenant_id IS NULL';
            } else {
                if ($snapshot['tenant_id'] === '' || $snapshot['context'] !== 'tenant') {
                    return false;
                }

                $sql .= ' AND tenant_id = ?';
                $params[] = $snapshot['tenant_id'];
            }
        }

        $sql .= ' LIMIT 1';

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('Unable to validate backend session: ' . $e->getMessage());
            return false;
        }

        if (!$row) {
            return false;
        }

        try {
            $touchStmt = $pdo->prepare('
                UPDATE user_sessions
                SET ip_address = ?, user_agent = ?, last_activity_at = NOW(), expires_at = DATE_ADD(NOW(), INTERVAL 30 MINUTE)
                WHERE session_id = ?
            ');
            $touchStmt->execute([
                mf_backend_session_ip(),
                mf_backend_session_user_agent(),
                (int) $row['session_id'],
            ]);
        } catch (Throwable $e) {
            error_log('Unable to refresh backend session expiry: ' . $e->getMessage());
            return false;
        }

        $_SESSION['backend_session_expires_at'] = null;
        mf_set_backend_session_cookie($snapshot['token']);

        return true;
    }
}

if (!function_exists('mf_refresh_backend_session_state')) {
    function mf_refresh_backend_session_state(PDO $pdo, string $expectedContext): bool
    {
        if (!mf_validate_backend_session($pdo, $expectedContext)) {
            $snapshot = mf_backend_session_snapshot();
            if ($snapshot['token'] !== '' && mf_backend_session_matches_expected_context($snapshot, $expectedContext)) {
                mf_destroy_backend_session($pdo);
            } elseif (!empty($_SESSION['user_logged_in']) || !empty($_SESSION['super_admin_logged_in'])) {
                if ($snapshot['token'] === '') {
                    mf_backend_session_destroy_php_session();
                }
            }
            return false;
        }

        return true;
    }
}

if (!function_exists('mf_require_backend_session')) {
    function mf_require_backend_session(PDO $pdo, string $expectedContext, array $options = []): void
    {
        if (mf_validate_backend_session($pdo, $expectedContext)) {
            return;
        }

        $snapshot = mf_backend_session_snapshot();
        if ($snapshot['token'] !== '' && mf_backend_session_matches_expected_context($snapshot, $expectedContext)) {
            mf_destroy_backend_session($pdo);
        } elseif ($snapshot['token'] === '') {
            mf_backend_session_destroy_php_session();
        }

        $response = (string) ($options['response'] ?? 'redirect');
        $status = (int) ($options['status'] ?? ($response === 'json' ? 401 : 302));
        $message = (string) ($options['message'] ?? 'Unauthorized.');

        if ($response === 'json') {
            http_response_code($status);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $message]);
            exit;
        }

        if ($response === 'die') {
            http_response_code($status > 0 ? $status : 403);
            exit($message);
        }

        $redirect = trim((string) ($options['redirect'] ?? ''));
        if ($redirect !== '') {
            $separator = strpos($redirect, '?') === false ? '?' : '&';
            
            // Always append auth=1 to trigger the "Please Login" alert
            $redirect .= $separator . 'auth=1';
            
            // Append tenant slug if requested and available
            if ($expectedContext === 'tenant' && !empty($options['append_tenant_slug']) && $snapshot['tenant_slug'] !== '') {
                $redirect .= '&s=' . urlencode($snapshot['tenant_slug']);
            }
            
            header('Location: ' . $redirect);
            exit;
        }

        http_response_code($status > 0 ? $status : 401);
        exit($message);
    }
}

if (!function_exists('mf_require_tenant_session')) {
    function mf_require_tenant_session(PDO $pdo, array $options = []): void
    {
        mf_require_backend_session($pdo, 'tenant', $options);
    }
}

if (!function_exists('mf_require_super_admin_session')) {
    function mf_require_super_admin_session(PDO $pdo, array $options = []): void
    {
        mf_require_backend_session($pdo, 'super_admin', $options);
    }
}
