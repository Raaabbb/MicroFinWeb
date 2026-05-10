<?php
require_once __DIR__ . '/../bootstrap.php';
require_once mf_platform_path('backend/db_connect.php');

$stmt = $pdo->query("SHOW CREATE TABLE otp_verifications");
$res = $stmt->fetch(PDO::FETCH_ASSOC);
file_put_contents(mf_exports_path('schema_query.txt'), $res['Create Table']);
