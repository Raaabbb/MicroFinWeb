<?php
// Function to get table structure
function get_table_struct($host, $user, $pass, $db, $port, $table) {
    try {
        $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $stmt = $pdo->query("DESCRIBE `$table`");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return "Error: " . $e->getMessage();
    }
}

// Railway Config from config.php
$r_host = 'centerbeam.proxy.rlwy.net';
$r_port = 52624;
$r_user = 'root';
$r_pass = 'zVULvPIbSyHVavTRnPFAkMWGVmvRwInd';
$r_db   = 'railway';

echo "--- RAILWAY STRUCTURE ---\n";
echo "Table: client_documents\n";
$res_docs = get_table_struct($r_host, $r_user, $r_pass, $r_db, $r_port, 'client_documents');
if (is_array($res_docs)) {
    foreach ($res_docs as $col) echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
} else {
    echo "  " . $res_docs . "\n";
}

echo "\nTable: clients\n";
$res_clients = get_table_struct($r_host, $r_user, $r_pass, $r_db, $r_port, 'clients');
if (is_array($res_clients)) {
    foreach ($res_clients as $col) echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
} else {
    echo "  " . $res_clients . "\n";
}
