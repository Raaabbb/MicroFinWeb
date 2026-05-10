<?php
require 'api/db.php';
$res = $conn->query("SHOW INDEXES FROM clients");
$indexes = [];
while($row = $res->fetch_assoc()) {
    $indexes[] = $row;
}
file_put_contents('indices_client.json', json_encode($indexes, JSON_PRETTY_PRINT));
