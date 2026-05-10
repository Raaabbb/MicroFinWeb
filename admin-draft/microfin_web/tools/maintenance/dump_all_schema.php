<?php
require_once __DIR__ . '/../bootstrap.php';
require_once mf_platform_path('backend/db_connect.php');

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$out = "";
foreach ($tables as $t) {
    if (strpos($t, 'schema_guard') !== false) continue;
    $stmt = $pdo->query("SHOW CREATE TABLE $t");
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    $out .= $res['Create Table'] . "\n\n";
}
file_put_contents(mf_exports_path('all_schemas.txt'), $out);
