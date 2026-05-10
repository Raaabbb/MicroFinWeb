<?php
require_once __DIR__ . '/api/config.php';
$config = microfin_database_config();
try {
    $pdo = new PDO("mysql:host={$config['host']};port={$config['port']};dbname={$config['database']}", $config['username'], $config['password']);
    $stmt = $pdo->query("DESCRIBE document_types");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($results, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo $e->getMessage();
}
