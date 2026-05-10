<?php

function mf_billing_access_setting_key($userId): string
{
    return 'billing_access_user_' . (int)$userId;
}

function mf_billing_setting_enabled($value): bool
{
    $normalized = strtolower(trim((string)$value));
    return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
}

function mf_user_can_manage_billing(PDO $pdo, string $tenantId, $userId): bool
{
    $userId = (int)$userId;
    if ($tenantId === '' || $userId <= 0) {
        return false;
    }

    $stmt = $pdo->prepare('SELECT setting_value FROM system_settings WHERE tenant_id = ? AND setting_key = ? LIMIT 1');
    $stmt->execute([$tenantId, mf_billing_access_setting_key($userId)]);
    $value = $stmt->fetchColumn();

    return $value !== false && mf_billing_setting_enabled($value);
}

function mf_get_billing_access_map(PDO $pdo, string $tenantId, array $userIds): array
{
    $map = [];
    $normalizedUserIds = [];

    foreach ($userIds as $userId) {
        $normalizedUserId = (int)$userId;
        if ($normalizedUserId <= 0) {
            continue;
        }
        $normalizedUserIds[$normalizedUserId] = $normalizedUserId;
        $map[$normalizedUserId] = false;
    }

    if ($tenantId === '' || empty($normalizedUserIds)) {
        return $map;
    }

    $settingKeys = [];
    foreach ($normalizedUserIds as $normalizedUserId) {
        $settingKeys[] = mf_billing_access_setting_key($normalizedUserId);
    }

    $placeholders = implode(',', array_fill(0, count($settingKeys), '?'));
    $params = array_merge([$tenantId], $settingKeys);
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE tenant_id = ? AND setting_key IN ($placeholders)");
    $stmt->execute($params);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settingKey = (string)($row['setting_key'] ?? '');
        if (strpos($settingKey, 'billing_access_user_') !== 0) {
            continue;
        }

        $userId = (int)substr($settingKey, strlen('billing_access_user_'));
        if ($userId > 0) {
            $map[$userId] = mf_billing_setting_enabled($row['setting_value'] ?? '');
        }
    }

    return $map;
}

function mf_set_user_billing_access(PDO $pdo, string $tenantId, $userId, bool $enabled): void
{
    $userId = (int)$userId;
    if ($tenantId === '' || $userId <= 0) {
        return;
    }

    $settingKey = mf_billing_access_setting_key($userId);

    if ($enabled) {
        $stmt = $pdo->prepare('
            INSERT INTO system_settings (tenant_id, setting_key, setting_value, setting_category, data_type)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                setting_category = VALUES(setting_category),
                data_type = VALUES(data_type),
                updated_at = CURRENT_TIMESTAMP
        ');
        $stmt->execute([$tenantId, $settingKey, '1', 'Billing', 'Boolean']);
        return;
    }

    $stmt = $pdo->prepare('DELETE FROM system_settings WHERE tenant_id = ? AND setting_key = ?');
    $stmt->execute([$tenantId, $settingKey]);
}
