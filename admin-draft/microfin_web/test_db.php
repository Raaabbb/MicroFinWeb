<?php
try {
    require_once __DIR__ . "/../microfin_backend/config/db_connect.php";
    require_once __DIR__ . "/admin_panel/staff/functions/db_profile.php";
    
    $stmt = $pdo->prepare("SELECT first_name, last_name, email, phone_number, user_type, created_at FROM users LIMIT 1");
    if (!$stmt->execute()) {
        file_put_contents('output.txt', json_encode($stmt->errorInfo()));
    } else {
        file_put_contents('output.txt', json_encode($stmt->fetch(PDO::FETCH_ASSOC)));
    }
} catch (Exception $e) {
    file_put_contents('output.txt', "Exception: " . $e->getMessage());
}
