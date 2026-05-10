<?php
/**
 * Super Admin Backup API
 * Handles database backups (full or tenant-scoped) and backup history.
 *
 * Actions:
 *   ?action=create           — Full database backup (streams .sql download)
 *   ?action=create&tenant_id=X — Tenant-scoped export (streams .sql download)
 *   ?action=history          — Returns JSON of backup_logs
 *   ?action=info             — Returns DB size and backup stats
 */
require_once '../../microfin_backend/auth/session_auth.php';
mf_start_backend_session();
require_once '../../microfin_backend/config/db_connect.php';
mf_require_super_admin_session($pdo, [
    'response' => 'json',
    'status' => 401,
    'message' => 'Unauthorized',
]);

if (!empty($_SESSION['super_admin_force_password_change'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Password change required']);
    exit;
}

if (!empty($_SESSION['super_admin_onboarding_required'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Profile onboarding required']);
    exit;
}
$action = $_GET['action'] ?? '';

// Ensure backup_logs table exists
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS backup_logs (
            log_id INT PRIMARY KEY AUTO_INCREMENT,
            initiated_by INT NULL,
            backup_type ENUM('full','tenant') DEFAULT 'full',
            file_name VARCHAR(255),
            file_size_bytes BIGINT DEFAULT 0,
            tenant_id VARCHAR(50) NULL,
            status ENUM('Success','Failed') DEFAULT 'Success',
            error_message TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (Throwable $e) {
    // Already exists
}

switch ($action) {

    // ==================================================================
    // CREATE BACKUP (Full or Tenant-Scoped)
    // ==================================================================
    case 'create':
        $tenant_id = isset($_GET['tenant_id']) && trim($_GET['tenant_id']) !== '' ? trim($_GET['tenant_id']) : null;
        $backup_type = $tenant_id ? 'tenant' : 'full';
        $initiated_by = $_SESSION['super_admin_id'] ?? null;

        $timestamp = date('Y-m-d_His');
        if ($tenant_id) {
            $file_name = "microfin_tenant_{$tenant_id}_{$timestamp}.sql";
        } else {
            $file_name = "microfin_full_backup_{$timestamp}.sql";
        }

        try {
            if ($tenant_id) {
                // Tenant-scoped: Export via PHP SELECT queries
                $output = generateTenantExport($pdo, $tenant_id, $db);
            } else {
                // Full backup: Use mysqldump or PHP fallback
                $output = generateFullBackup($pdo, $host, $port, $db, $user, $pass);
            }

            $file_size = strlen($output);

            // Log success
            $log_stmt = $pdo->prepare("INSERT INTO backup_logs (initiated_by, backup_type, file_name, file_size_bytes, tenant_id, status) VALUES (?, ?, ?, ?, ?, 'Success')");
            $log_stmt->execute([$initiated_by, $backup_type, $file_name, $file_size, $tenant_id]);

            // Stream as download
            header('Content-Type: application/sql');
            header('Content-Disposition: attachment; filename="' . $file_name . '"');
            header('Content-Length: ' . $file_size);
            header('Cache-Control: no-store');
            echo $output;
            exit;

        } catch (Throwable $e) {
            // Log failure
            $log_stmt = $pdo->prepare("INSERT INTO backup_logs (initiated_by, backup_type, file_name, tenant_id, status, error_message) VALUES (?, ?, ?, ?, 'Failed', ?)");
            $log_stmt->execute([$initiated_by, $backup_type, $file_name, $tenant_id, $e->getMessage()]);

            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['error' => 'Backup failed: ' . $e->getMessage()]);
            exit;
        }
        break;

    // ==================================================================
    // BACKUP HISTORY
    // ==================================================================
    case 'history':
        header('Content-Type: application/json');
        $stmt = $pdo->query("
            SELECT bl.log_id, bl.backup_type, bl.file_name, bl.file_size_bytes,
                   bl.tenant_id, bl.status, bl.error_message, bl.created_at,
                   u.username AS initiated_by_name
            FROM backup_logs bl
            LEFT JOIN users u ON bl.initiated_by = u.user_id
            ORDER BY bl.created_at DESC
            LIMIT 100
        ");
        echo json_encode(['logs' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    // ==================================================================
    // DATABASE INFO
    // ==================================================================
    case 'info':
        header('Content-Type: application/json');

        // DB size
        $size_stmt = $pdo->prepare("
            SELECT SUM(data_length + index_length) AS total_bytes,
                   COUNT(*) AS table_count
            FROM information_schema.tables
            WHERE table_schema = ?
        ");
        $size_stmt->execute([$db]);
        $db_info = $size_stmt->fetch(PDO::FETCH_ASSOC);

        // Backup stats
        $stats_stmt = $pdo->query("SELECT COUNT(*) AS total_backups FROM backup_logs WHERE status = 'Success'");
        $total_backups = (int)$stats_stmt->fetch(PDO::FETCH_ASSOC)['total_backups'];

        $last_stmt = $pdo->query("SELECT created_at FROM backup_logs WHERE status = 'Success' ORDER BY created_at DESC LIMIT 1");
        $last_row = $last_stmt->fetch(PDO::FETCH_ASSOC);
        $last_backup = $last_row ? $last_row['created_at'] : null;

        echo json_encode([
            'db_size_bytes' => (int)($db_info['total_bytes'] ?? 0),
            'table_count' => (int)($db_info['table_count'] ?? 0),
            'total_backups' => $total_backups,
            'last_backup' => $last_backup,
            'mysql_version' => $pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
            'php_version' => phpversion(),
        ]);
        break;

    // ==================================================================
    // IMPORT SQL FILE
    // ==================================================================
    case 'import':
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'POST method required']);
            exit;
        }

        if (!isset($_FILES['sql_file']) || $_FILES['sql_file']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['error' => 'No valid SQL file uploaded.']);
            exit;
        }

        $uploaded = $_FILES['sql_file'];
        $ext = strtolower(pathinfo($uploaded['name'], PATHINFO_EXTENSION));
        if ($ext !== 'sql') {
            http_response_code(400);
            echo json_encode(['error' => 'Only .sql files are allowed.']);
            exit;
        }

        // 50MB limit
        if ($uploaded['size'] > 50 * 1024 * 1024) {
            http_response_code(400);
            echo json_encode(['error' => 'File too large. Maximum 50MB.']);
            exit;
        }

        $sql_content = file_get_contents($uploaded['tmp_name']);
        if ($sql_content === false || trim($sql_content) === '') {
            http_response_code(400);
            echo json_encode(['error' => 'File is empty or could not be read.']);
            exit;
        }

        $initiated_by = $_SESSION['super_admin_id'] ?? null;
        $results = ['total_statements' => 0, 'executed' => 0, 'skipped_duplicates' => 0, 'errors' => [], 'mismatches' => []];

        try {
            // ── Schema Mismatch Detection ──────────────────────────────
            // Extract table names referenced in the SQL (CREATE TABLE, INSERT INTO, etc.)
            preg_match_all('/(?:CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?|INSERT\s+(?:IGNORE\s+)?INTO\s+)`?([a-zA-Z0-9_]+)`?/i', $sql_content, $tableMatches);
            $referencedTables = array_unique($tableMatches[1] ?? []);

            // Get all tables currently in the database
            $existingTablesStmt = $pdo->query("SHOW TABLES");
            $existingTables = array_map('strtolower', $existingTablesStmt->fetchAll(PDO::FETCH_COLUMN));

            foreach ($referencedTables as $tbl) {
                if (!in_array(strtolower($tbl), $existingTables, true)) {
                    $results['mismatches'][] = "Table `{$tbl}` from backup does not exist in the current database.";
                }
            }
            // ─────────────────────────────────────────────────────────

            // Replace INSERT INTO with INSERT IGNORE INTO to skip duplicates
            $sql_content = preg_replace('/\bINSERT\s+INTO\b/i', 'INSERT IGNORE INTO', $sql_content);

            // Split into individual statements
            $statements = array_filter(
                array_map('trim', preg_split('/;\s*$/m', $sql_content)),
                fn($s) => $s !== '' && !str_starts_with($s, '--')
            );

            $results['total_statements'] = count($statements);

            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");


            foreach ($statements as $idx => $stmt) {
                try {
                    $affected = $pdo->exec($stmt);
                    if ($affected === 0 && stripos($stmt, 'INSERT IGNORE') !== false) {
                        $results['skipped_duplicates']++;
                    }
                    $results['executed']++;
                } catch (PDOException $stmtErr) {
                    $errMsg = $stmtErr->getMessage();
                    // Skip duplicate entry errors
                    if (stripos($errMsg, 'Duplicate entry') !== false) {
                        $results['skipped_duplicates']++;
                        $results['executed']++;
                    } else {
                        $results['errors'][] = 'Statement ' . ($idx + 1) . ': ' . $errMsg;
                        if (count($results['errors']) >= 10) break; // Cap error reporting
                    }
                }
            }

            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

            $status = empty($results['errors']) ? 'Success' : 'Failed';
            $log_stmt = $pdo->prepare("INSERT INTO backup_logs (initiated_by, backup_type, file_name, file_size_bytes, status, error_message) VALUES (?, 'full', ?, ?, ?, ?)");
            $log_stmt->execute([
                $initiated_by,
                'IMPORT: ' . $uploaded['name'],
                $uploaded['size'],
                $status,
                empty($results['errors']) ? null : implode("\n", $results['errors'])
            ]);

            echo json_encode([
                'success' => empty($results['errors']),
                'message' => sprintf(
                    'Import complete: %d statements executed, %d duplicates skipped, %d errors.',
                    $results['executed'],
                    $results['skipped_duplicates'],
                    count($results['errors'])
                ),
                'details' => $results,
            ]);
        } catch (Throwable $e) {
            try { $pdo->exec("SET FOREIGN_KEY_CHECKS = 1"); } catch (Throwable $ignored) {}

            $log_stmt = $pdo->prepare("INSERT INTO backup_logs (initiated_by, backup_type, file_name, file_size_bytes, status, error_message) VALUES (?, 'full', ?, ?, 'Failed', ?)");
            $log_stmt->execute([$initiated_by, 'IMPORT: ' . $uploaded['name'], $uploaded['size'], $e->getMessage()]);

            http_response_code(500);
            echo json_encode(['error' => 'Import failed: ' . $e->getMessage()]);
        }
        break;

    default:
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action. Use: create, history, info, import']);
}

// ==================================================================
// HELPER: Full database backup
// ==================================================================
function generateFullBackup($pdo, $host, $port, $db, $user, $pass) {
    // Try mysqldump first
    $mysqldump = findMysqldump();
    if ($mysqldump) {
        $cmd = sprintf(
            '%s --host=%s --port=%d --user=%s --password=%s --single-transaction --routines --triggers --skip-lock-tables %s 2>&1',
            escapeshellarg($mysqldump),
            escapeshellarg($host),
            (int)$port,
            escapeshellarg($user),
            escapeshellarg($pass),
            escapeshellarg($db)
        );
        $output = shell_exec($cmd);
        if ($output && stripos($output, 'CREATE TABLE') !== false) {
            return "-- MicroFin Full Backup\n-- Generated: " . date('Y-m-d H:i:s') . "\n-- Method: mysqldump\n\n" . $output;
        }
    }

    // Fallback: PHP-based export
    return generatePhpExport($pdo, $db);
}

// ==================================================================
// HELPER: PHP-based full export (fallback when mysqldump unavailable)
// ==================================================================
function generatePhpExport($pdo, $db) {
    $output = "-- MicroFin Full Backup (PHP Export)\n";
    $output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $output .= "-- Method: PHP PDO Fallback\n\n";
    $output .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

    $tables_stmt = $pdo->query("SHOW TABLES");
    $tables = $tables_stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        // CREATE TABLE
        $create_stmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
        $create_row = $create_stmt->fetch(PDO::FETCH_NUM);
        $output .= "DROP TABLE IF EXISTS `{$table}`;\n";
        $output .= $create_row[1] . ";\n\n";

        // INSERT rows
        $rows_stmt = $pdo->query("SELECT * FROM `{$table}`");
        $rows = $rows_stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($rows)) {
            $columns = array_keys($rows[0]);
            $col_list = '`' . implode('`, `', $columns) . '`';

            foreach (array_chunk($rows, 100) as $chunk) {
                $values = [];
                foreach ($chunk as $row) {
                    $vals = [];
                    foreach ($row as $val) {
                        if ($val === null) {
                            $vals[] = 'NULL';
                        } else {
                            $vals[] = $pdo->quote($val);
                        }
                    }
                    $values[] = '(' . implode(', ', $vals) . ')';
                }
                $output .= "INSERT INTO `{$table}` ({$col_list}) VALUES\n" . implode(",\n", $values) . ";\n\n";
            }
        }
    }

    $output .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    return $output;
}

// ==================================================================
// HELPER: Tenant-scoped export
// ==================================================================
function generateTenantExport($pdo, $tenant_id, $db) {
    $output = "-- MicroFin Tenant Export\n";
    $output .= "-- Tenant ID: {$tenant_id}\n";
    $output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
    $output .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

    // Get all tables that have a tenant_id column
    $col_stmt = $pdo->prepare("
        SELECT DISTINCT table_name
        FROM information_schema.columns
        WHERE table_schema = ? AND column_name = 'tenant_id'
        ORDER BY table_name
    ");
    $col_stmt->execute([$db]);
    $tenant_tables = $col_stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tenant_tables as $table) {
        $stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE tenant_id = ?");
        $stmt->execute([$tenant_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) continue;

        $output .= "-- Table: {$table} (" . count($rows) . " rows)\n";
        $columns = array_keys($rows[0]);
        $col_list = '`' . implode('`, `', $columns) . '`';

        foreach (array_chunk($rows, 100) as $chunk) {
            $values = [];
            foreach ($chunk as $row) {
                $vals = [];
                foreach ($row as $val) {
                    if ($val === null) {
                        $vals[] = 'NULL';
                    } else {
                        $vals[] = $pdo->quote($val);
                    }
                }
                $values[] = '(' . implode(', ', $vals) . ')';
            }
            $output .= "INSERT INTO `{$table}` ({$col_list}) VALUES\n" . implode(",\n", $values) . ";\n\n";
        }
    }

    $output .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    return $output;
}

// ==================================================================
// HELPER: Find mysqldump executable
// ==================================================================
function findMysqldump() {
    $paths = [];
    if (PHP_OS_FAMILY === 'Windows') {
        $paths = [
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
            'C:\\Program Files\\MariaDB 10.5\\bin\\mysqldump.exe',
        ];
    } else {
        $paths = ['/usr/bin/mysqldump', '/usr/local/bin/mysqldump'];
    }

    foreach ($paths as $p) {
        if (file_exists($p)) return $p;
    }

    // Try PATH
    $which = PHP_OS_FAMILY === 'Windows' ? 'where mysqldump 2>NUL' : 'which mysqldump 2>/dev/null';
    $result = trim((string)shell_exec($which));
    if ($result && file_exists(explode("\n", $result)[0])) {
        return explode("\n", $result)[0];
    }

    return null;
}

