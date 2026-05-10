<?php
require_once __DIR__ . '/db_runtime.php';

$dbTargets = mf_resolve_db_targets();
$conn = null;
$lastError = '';

mysqli_report(MYSQLI_REPORT_OFF); // Disable strict reporting for the fallback loop

foreach ($dbTargets['targets'] as $target) {
    try {
        $conn = new mysqli(
            $target['host'],
            $target['user'],
            $target['pass'],
            $target['db'],
            $target['port']
        );
        
        if ($conn->connect_error) {
            $lastError = $conn->connect_error;
            $conn = null;
            continue;
        }
        
        $conn->set_charset('utf8mb4');
    break; // Success!
    } catch (Throwable $e) {
        $lastError = $e->getMessage();
        $conn = null;
    }
}

if (!$conn) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Database connection error: ' . $lastError,
    ]);
    exit;
}
