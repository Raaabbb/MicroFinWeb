<?php
require_once __DIR__ . '/../config/db.php';
$r = $conn->query('SELECT loan_number, next_payment_due FROM loans WHERE loan_number = "LN-000001" OR loan_number = "L-0000001" LIMIT 1');
while($row = $r->fetch_assoc()) {
    echo "Loan: " . $row['loan_number'] . " | Next Due: " . ($row['next_payment_due'] ?? 'NULL') . "\n";
}
?>
