<?php
require_once __DIR__ . '/../config/db.php';

echo "Successfully connected to MySQL!\n";

$result = $conn->query("SELECT DATABASE() AS current_database");
$row = $result->fetch_assoc();

echo "Selected database: " . ($row['current_database'] ?? 'unknown') . "\n";
?>
