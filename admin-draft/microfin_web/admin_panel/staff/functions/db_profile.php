<?php
/**
 * functions/db_profile.php
 * Fetches user profile data for the active session
 */

function get_user_profile($pdo, $user_id, $tenant_id) {
    try {
        // Handle Super Admin who has NULL tenant_id in users table
        if (empty($tenant_id) || $_SESSION['user_type'] === 'Super Admin') {
            $stmt = $pdo->prepare("
                SELECT u.first_name, u.last_name, u.username, u.email, u.phone_number, u.user_type, u.created_at, 
                       ur.role_name, NULL as position, NULL as department, NULL as hire_date
                FROM users u
                LEFT JOIN user_roles ur ON u.role_id = ur.role_id
                WHERE u.user_id = ?
            ");
            $stmt->execute([$user_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        $stmt = $pdo->prepare("
            SELECT u.first_name, u.last_name, u.username, u.email, 
                   COALESCE(e.contact_number, u.phone_number) AS phone_number, 
                   u.user_type, u.created_at, ur.role_name,
                   e.position, e.department, e.hire_date
            FROM users u
            LEFT JOIN employees e ON u.user_id = e.user_id AND u.tenant_id = e.tenant_id
            LEFT JOIN user_roles ur ON u.role_id = ur.role_id
            WHERE u.user_id = ? AND u.tenant_id = ?
        ");
        $stmt->execute([$user_id, $tenant_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (\PDOException $e) {
        error_log("Profile Fetch Error: " . $e->getMessage());
        return null;
    }
}
