<?php
/**
 * functions/db_clients.php
 * Extracts all client-related queries so they are out of the view layer.
 */

function staff_client_effective_verification_sql(PDO $pdo, string $alias = 'c'): string {
    return "
        CASE
            WHEN {$alias}.document_verification_status = 'Approved' THEN 'Approved'
            WHEN {$alias}.document_verification_status = 'Verified' THEN 'Verified'
            WHEN {$alias}.document_verification_status = 'Rejected' THEN 'Rejected'
            WHEN {$alias}.document_verification_status = 'Pending' THEN 'Pending'
            ELSE 'Unverified'
        END
    ";
}

function get_all_tenant_clients($pdo, $tenant_id) {
    $debug = [
        'tenant_id' => $tenant_id,
        'query_error' => null,
        'row_count' => 0,
        'raw_count_check' => null,
    ];
    $clients = [];

    try {
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE tenant_id = ?");
        $cnt->execute([$tenant_id]);
        $debug['raw_count_check'] = $cnt->fetchColumn();

        $effective_status_sql = staff_client_effective_verification_sql($pdo, 'c');

        $stmt = $pdo->prepare("
            SELECT c.client_id, c.first_name, c.last_name, c.email_address,
                   c.contact_number, c.client_status, c.document_verification_status, c.registration_date,
                   u.user_type,
                   ({$effective_status_sql}) as effective_status,
                   (SELECT COUNT(*) FROM loans l WHERE l.client_id = c.client_id AND l.tenant_id = c.tenant_id) as total_loans
            FROM clients c
            JOIN users u ON c.user_id = u.user_id
            WHERE c.tenant_id = ?
            ORDER BY c.registration_date DESC
        ");
        $stmt->execute([$tenant_id]);
        $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $debug['row_count'] = count($clients);
    } catch (\Throwable $e) {
        $debug['query_error'] = $e->getMessage();
    }

    return [
        'data' => $clients,
        'debug' => $debug
    ];
}
