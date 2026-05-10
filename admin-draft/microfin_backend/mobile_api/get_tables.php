<?php
require_once '../config/db.php';
$res = $conn->query("SHOW TABLES");
$rows = [];
while ($row = $res->fetch_array()) {
    $rows[] = $row[0];
}
echo json_encode($rows, JSON_PRETTY_PRINT);
?>
