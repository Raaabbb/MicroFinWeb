<?php

function sa_load_super_admin_state(PDO $pdo, int $userId): ?array
{
    if ($userId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare("
        SELECT user_id,
               username,
               email,
               ui_theme,
               force_password_change,
               status,
               created_at,
               last_login,
               first_name,
               last_name,
               middle_name,
               suffix,
               phone_number,
               date_of_birth
        FROM users
        WHERE user_id = ?
          AND user_type = 'Super Admin'
          AND deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

function sa_super_admin_theme(array $superAdmin): string
{
    $theme = strtolower(trim((string) ($superAdmin['ui_theme'] ?? '')));
    return in_array($theme, ['light', 'dark'], true) ? $theme : 'light';
}

function sa_super_admin_profile_is_complete(array $superAdmin): bool
{
    foreach (['first_name', 'last_name', 'phone_number', 'date_of_birth'] as $field) {
        if (trim((string) ($superAdmin[$field] ?? '')) === '') {
            return false;
        }
    }

    return true;
}

function sa_super_admin_requires_onboarding(array $superAdmin): bool
{
    return trim((string) ($superAdmin['status'] ?? '')) === 'Inactive'
        && empty($superAdmin['force_password_change']);
}

function sa_sync_super_admin_session_from_state(array $superAdmin): void
{
    $_SESSION['super_admin_username'] = (string) ($superAdmin['username'] ?? 'Super Admin');
    $_SESSION['ui_theme'] = sa_super_admin_theme($superAdmin);
    $_SESSION['super_admin_force_password_change'] = !empty($superAdmin['force_password_change']);
    $_SESSION['super_admin_onboarding_required'] = sa_super_admin_requires_onboarding($superAdmin);
}

function sa_sanitize_platform_username(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9._-]+/', '', $value);
    $value = trim((string) $value, '._-');

    return substr((string) $value, 0, 50);
}

function sa_first_name_username_seed(string $firstName): string
{
    $firstName = trim($firstName);
    if ($firstName === '') {
        return '';
    }

    $parts = preg_split('/\s+/', $firstName);
    $firstWord = is_array($parts) ? (string) ($parts[0] ?? '') : '';

    return sa_sanitize_platform_username($firstWord);
}

function sa_platform_username_exists(PDO $pdo, string $username, int $excludeUserId = 0): bool
{
    if ($username === '') {
        return false;
    }

    $sql = "
        SELECT 1
        FROM users
        WHERE tenant_id IS NULL
          AND deleted_at IS NULL
          AND username = ?
    ";
    $params = [$username];

    if ($excludeUserId > 0) {
        $sql .= ' AND user_id <> ?';
        $params[] = $excludeUserId;
    }

    $sql .= ' LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (bool) $stmt->fetchColumn();
}

function sa_generate_unique_platform_username(
    PDO $pdo,
    string $preferred = '',
    string $email = '',
    string $firstName = '',
    string $lastName = '',
    int $excludeUserId = 0
): string {
    $emailLocalPart = '';
    if ($email !== '') {
        $emailParts = explode('@', $email, 2);
        $emailLocalPart = $emailParts[0] ?? '';
    }

    $firstNameSeed = sa_first_name_username_seed($firstName);
    $nameSeed = trim($firstName . '.' . $lastName, '.');
    $candidates = [
        sa_sanitize_platform_username($preferred),
        $firstNameSeed,
        sa_sanitize_platform_username($nameSeed),
        sa_sanitize_platform_username($emailLocalPart),
        'platformadmin',
    ];

    $base = 'platformadmin';
    foreach ($candidates as $candidate) {
        if ($candidate !== '') {
            $base = $candidate;
            break;
        }
    }

    $username = $base;
    $counter = 2;

    while (sa_platform_username_exists($pdo, $username, $excludeUserId)) {
        $suffix = (string) $counter;
        $username = substr($base, 0, max(1, 50 - strlen($suffix))) . $suffix;
        $counter++;
    }

    return $username;
}
