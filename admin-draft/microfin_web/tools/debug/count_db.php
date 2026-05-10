<?php
require_once __DIR__ . '/../bootstrap.php';
require_once mf_platform_path('backend/db_connect.php');

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$total = 0;
foreach ($tables as $t) {
    $count = $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
    if ($count > 0 && $t !== 'document_types' && $t !== 'permissions') {
        echo "$t: $count\n";
        $total += $count;
    }
}
if ($total === 0) echo "Empty DB!\n";
