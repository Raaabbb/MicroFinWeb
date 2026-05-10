<?php
require_once __DIR__ . '/db_connect.php';
try {
    $stmt = $pdo->query('DESCRIBE client_documents');
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($cols, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo $e->getMessage();
}
