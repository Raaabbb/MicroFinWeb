<?php
require_once __DIR__ . '/../config/db.php';

if (!isset($sql) || trim((string) $sql) === '') {
    exit("No tenant insert SQL was provided.\n");
}

if ($conn->multi_query($sql)) {
    do {
        if ($res = $conn->store_result()) {
            $res->free();
        }
    } while ($conn->more_results() && $conn->next_result());

    echo "All tenants inserted successfully!\n";
} else {
    echo "Error inserting tenants: " . $conn->error;
}
?>
