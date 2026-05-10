<?php
$output = shell_exec('C:\xampp\php\php.exe c:\xampp\htdocs\admin-draft-withmobile\admin-draft\microfin_mobile\scratch\debug_settings.php');
file_put_contents('debug_output_utf8.json', $output);
