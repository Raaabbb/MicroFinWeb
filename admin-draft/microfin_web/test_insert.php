<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'backend/db_connect.php';

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmtUser = $pdo->prepare("
        INSERT INTO users (tenant_id, username, email, password_hash, first_name, last_name, role_id, user_type, status, force_password_change)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'Employee', 'Active', 1)
    ");
    $stmtUser->execute([1,'t1test','test2@test.com','hash','John','Doe',1]);
    
    $newUserId = $pdo->lastInsertId();

    $stmtEmp = $pdo->prepare("
        INSERT INTO employees (user_id, tenant_id, first_name, last_name, employment_status)
        VALUES (?, ?, ?, ?, 'Active')
    ");
    $stmtEmp->execute([$newUserId, 1, 'John', 'Doe']);

    echo "Success!";
} catch(Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
