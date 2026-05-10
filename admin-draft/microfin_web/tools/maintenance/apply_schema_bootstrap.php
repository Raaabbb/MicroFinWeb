<?php
require_once __DIR__ . '/../bootstrap.php';
require_once mf_platform_path('backend/db_connect.php');
require_once mf_platform_path('backend/db_schema.php');

$summary = mf_apply_db_schema_bootstrap($pdo);

foreach (['applied', 'skipped', 'warnings'] as $bucket) {
    foreach ($summary[$bucket] as $message) {
        echo strtoupper($bucket) . ': ' . $message . PHP_EOL;
    }
}

echo PHP_EOL . 'Schema bootstrap complete.' . PHP_EOL;
