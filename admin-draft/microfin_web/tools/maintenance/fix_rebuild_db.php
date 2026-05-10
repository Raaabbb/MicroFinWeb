<?php
$file = dirname(__DIR__, 2) . '/docs/database-schema.txt';
$content = file_get_contents($file);

$content = str_replace('CONSIDER', 'Pending', $content);
$content = str_replace('EDITED', 'Approved', $content);
$content = str_replace('Approved_amount', 'approved_amount', $content);
$content = str_replace('Approved_by', 'approved_by', $content);

file_put_contents($file, $content);

require_once __DIR__ . '/../bootstrap.php';
require_once mf_platform_path('backend/db_connect.php');

$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $t) {
    if (strpos($t, 'schema_guard') !== false) continue;
    $pdo->exec("DROP TABLE IF EXISTS `$t`;");
}
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

echo "Schema file fixed and database dropped.\n";
