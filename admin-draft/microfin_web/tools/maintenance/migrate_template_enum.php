<?php
require_once __DIR__ . '/../bootstrap.php';
require_once mf_platform_path('backend/db_connect.php');

$pdo->exec("ALTER TABLE tenant_website_content MODIFY COLUMN layout_template ENUM('template1', 'template2', 'template3') DEFAULT 'template1'");
echo "ENUM column updated successfully.\n";

$count = $pdo->exec("UPDATE tenant_website_content SET layout_template = 'template1' WHERE layout_template NOT IN ('template1','template2','template3') OR layout_template IS NULL");
echo "Updated $count rows with old template values.\n";

$stmt = $pdo->query('SELECT tenant_id, layout_template FROM tenant_website_content');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['tenant_id'] . ' => ' . $row['layout_template'] . "\n";
}
echo "Done.\n";
