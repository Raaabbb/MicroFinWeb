<?php
// Railway schema importer for the web platform.
// Usage: C:\xampp\php\php.exe microfin_platform\tools\maintenance\setup_remote_db.php

$host = 'centerbeam.proxy.rlwy.net';
$port = 52624;
$db = 'railway';
$user = 'root';
$pass = 'zVULvPIbSyHVavTRnPFAkMWGVmvRwInd';

$databaseUrl = getenv('DATABASE_URL');
if (is_string($databaseUrl) && trim($databaseUrl) !== '') {
    $parts = parse_url($databaseUrl);
    if ($parts !== false) {
        $host = $parts['host'] ?? $host;
        $port = (int)($parts['port'] ?? $port);
        $db = isset($parts['path']) ? ltrim((string)$parts['path'], '/') : $db;
        $user = isset($parts['user']) ? urldecode((string)$parts['user']) : $user;
        $pass = isset($parts['pass']) ? urldecode((string)$parts['pass']) : $pass;
    }
}

$schemaPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'database-schema.txt';

if (!file_exists($schemaPath)) {
    fwrite(STDERR, "Schema file not found: {$schemaPath}\n");
    exit(1);
}

try {
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 60,
    ]);

    $sql = file_get_contents($schemaPath);
    if ($sql === false) {
        throw new RuntimeException("Unable to read schema file: {$schemaPath}");
    }

    // Railway-safe normalization:
    // 1) Remove explicit DB creation/use from schema file.
    // 2) Remove DELIMITER-based event block (PDO cannot execute that format directly).
    $sql = preg_replace('/^\s*CREATE\s+DATABASE\s+IF\s+NOT\s+EXISTS\s+.+?;\s*$/im', '', $sql);
    $sql = preg_replace('/^\s*USE\s+.+?;\s*$/im', '', $sql);
    $sql = preg_replace('/DELIMITER\s*\/\/.*?DELIMITER\s*;/is', '', $sql);

    // Strip SQL comments so semicolons inside comments do not break splitting.
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

    // Force target DB explicitly.
    $sql = "USE `{$db}`;\n\n" . $sql;

    $statements = array_filter(array_map('trim', explode(';', $sql)));

    $count = 0;
    $skippedDuplicates = 0;
    foreach ($statements as $stmt) {
        // Skip pure comment chunks
        if ($stmt === '' || preg_match('/^(--|\/\*)/m', $stmt) && !preg_match('/\b(CREATE|ALTER|INSERT|UPDATE|DELETE|DROP|USE)\b/i', $stmt)) {
            continue;
        }

        try {
            $pdo->exec($stmt);
            $count++;
        } catch (PDOException $stmtError) {
            $mysqlCode = isset($stmtError->errorInfo[1]) ? (int)$stmtError->errorInfo[1] : 0;
            if ($mysqlCode === 1062) {
                $skippedDuplicates++;
                continue;
            }
            throw $stmtError;
        }
    }

    echo "Schema import successful.\n";
    echo "Executed {$count} SQL statements on {$host}:{$port}/{$db}\n";
    if ($skippedDuplicates > 0) {
        echo "Skipped {$skippedDuplicates} duplicate INSERT statements.\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, "Schema import failed: " . $e->getMessage() . "\n");
    exit(1);
}
