<?php
require_once __DIR__ . '/../bootstrap.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');
$_GET['site'] = 'fundline';
ob_start();
include mf_platform_path('public_website/site.php');
$html = ob_get_clean();
if (preg_match_all('/(Warning|Fatal|Notice|Error|Undefined).*on line \d+/i', $html, $matches)) {
    echo "ERRORS:\n";
    foreach ($matches[0] as $m) echo "  - $m\n";
} else {
    echo "OK - " . strlen($html) . " bytes\n";
    $checks = ['Landbank', 'loan-calculator', 'HERO', 'SERVICES', 'FOOTER', 'template1', 'template2'];
    foreach ($checks as $c) echo "  '$c': " . (stripos($html, $c) !== false ? 'YES' : 'NO') . "\n";
    preg_match('/<title>(.*?)<\/title>/', $html, $m);
    echo "  Title: " . ($m[1] ?? '(none)') . "\n";
}
