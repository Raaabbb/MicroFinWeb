<?php
require_once __DIR__ . "/../../microfin_backend/config/db_connect.php";

$columns_to_add = [
    'hero_badge_text' => 'VARCHAR(255) DEFAULT NULL',
    'stats_heading' => 'VARCHAR(255) DEFAULT NULL',
    'stats_subheading' => 'VARCHAR(255) DEFAULT NULL',
    'stats_image_path' => 'VARCHAR(500) DEFAULT NULL',
    'footer_description' => 'TEXT DEFAULT NULL'
];

foreach ($columns_to_add as $col => $def) {
    try {
        $pdo->exec("ALTER TABLE tenant_website_content ADD COLUMN $col $def");
        echo "Added column $col successfully.\n";
    } catch (PDOException $e) {
        if ($e->getCode() == '42S21') {
            echo "Column $col already exists.\n";
        } else {
            echo "Error adding column $col: " . $e->getMessage() . "\n";
        }
    }
}
echo "Done.\n";

