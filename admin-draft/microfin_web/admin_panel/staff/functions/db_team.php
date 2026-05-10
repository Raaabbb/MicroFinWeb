<?php
/**
 * functions/db_team.php
 * Fetches staff members (Admins and Employees) for the current tenant
 */

function get_tenant_staff($pdo, $tenant_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                u.user_id,
                u.email,
                u.status,
                u.user_type,
                u.role_id,
                ur.role_name,
                COALESCE(NULLIF(TRIM(CONCAT(
                    COALESCE(u.last_name, ''), 
                    IF(u.last_name IS NOT NULL AND u.first_name IS NOT NULL, ', ', ''), 
                    COALESCE(u.first_name, ''), 
                    COALESCE(CONCAT(' ', u.middle_name), ''), 
                    COALESCE(CONCAT(' ', u.suffix), '')
                )), ''), u.username, u.email) AS full_name
            FROM users u
            LEFT JOIN user_roles ur ON u.role_id = ur.role_id
            WHERE u.tenant_id = ? 
              AND u.user_type IN ('Staff', 'Employee', 'Admin', 'Tenant Admin')
            ORDER BY u.last_name ASC
        ");
        $stmt->execute([$tenant_id]);
        
        return [
            'status' => 'success',
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
    } catch (PDOException $e) {
        error_log("DB Team Fetch Error: " . $e->getMessage());
        return ['status' => 'error', 'data' => []];
    }
}

/**
 * Get available roles for the current tenant 
 * (Employees and Admins shouldn't be assigned "Super Admin" or Client roles).
 */
function get_tenant_roles($pdo, $tenant_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT role_id, role_name 
            FROM user_roles 
            WHERE tenant_id = ? AND role_name NOT IN ('Client', 'Super Admin')
            ORDER BY role_name ASC
        ");
        $stmt->execute([$tenant_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("DB Roles Fetch Error: " . $e->getMessage());
        return [];
    }
}
