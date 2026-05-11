<?php
require_once 'c:/xampp/htdocs/admin-draft-withmobile/admin-draft/microfin_backend/config/db_connect.php';
$stmt = $pdo->query('DESCRIBE tenants');
while($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $r['Field'] . ' (' . $r['Type'] . ")\n";
}
