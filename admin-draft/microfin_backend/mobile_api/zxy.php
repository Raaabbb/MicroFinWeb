<?php
require_once __DIR__ . '/../config/db.php';
$r = $conn->query('SHOW COLUMNS FROM loan_products');
while($x = $r->fetch_assoc()) echo $x['Field'] . "\n";
