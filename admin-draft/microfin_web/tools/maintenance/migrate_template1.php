<?php
require_once __DIR__ . '/../bootstrap.php';
require_once mf_platform_path('backend/db_connect.php');

$queries = [
    "ALTER TABLE tenant_website_content ADD COLUMN IF NOT EXISTS stats_json LONGTEXT DEFAULT NULL AFTER services_json",
    "ALTER TABLE tenant_website_content ADD COLUMN IF NOT EXISTS hero_badge_text VARCHAR(100) DEFAULT NULL AFTER hero_image_path",
    "ALTER TABLE tenant_website_content ADD COLUMN IF NOT EXISTS stats_heading VARCHAR(255) DEFAULT NULL AFTER stats_json",
    "ALTER TABLE tenant_website_content ADD COLUMN IF NOT EXISTS stats_subheading VARCHAR(255) DEFAULT NULL AFTER stats_heading",
    "ALTER TABLE tenant_website_content ADD COLUMN IF NOT EXISTS stats_image_path VARCHAR(500) DEFAULT NULL AFTER stats_subheading",
    "ALTER TABLE tenant_website_content ADD COLUMN IF NOT EXISTS footer_description TEXT DEFAULT NULL AFTER contact_hours",
];

foreach ($queries as $q) {
    try {
        $pdo->exec($q);
        echo "OK: " . substr($q, 0, 70) . "...\n";
    } catch (PDOException $ex) {
        if (strpos($ex->getMessage(), 'Duplicate column') !== false) {
            echo "SKIP: " . substr($q, 0, 70) . "...\n";
        } else {
            echo "ERR: " . $ex->getMessage() . "\n";
        }
    }
}

echo "\nMigration complete!\n";
