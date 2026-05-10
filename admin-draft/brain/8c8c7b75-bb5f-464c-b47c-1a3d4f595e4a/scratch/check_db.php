<?php
require_once '../../../microfin_backend/config/db_connect.php';
try {
    echo "USERS COLUMNS:\n";
    $users = $pdo->query('DESCRIBE users')->fetchAll(PDO::FETCH_COLUMN);
    print_r($users);
    echo "\nTENANTS COLUMNS:\n";
    $tenants = $pdo->query('DESCRIBE tenants')->fetchAll(PDO::FETCH_COLUMN);
    print_r($tenants);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
