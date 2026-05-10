<?php
$file = 'c:/xampp/htdocs/admin-draft-withmobile/admin-draft/microfin_platform/admin_panel/staff/dashboard.php';
$lines = file($file);

// 1. Remove clients db block
$c_start = -1; $c_end = -1;
foreach ($lines as $i => $l) {
    if (strpos($l, '// Fetch Clients') !== false) { $c_start = $i; }
    if ($c_start !== -1 && $i > $c_start && strpos($l, '$loan_products = [];') !== false) {
        $c_end = $i; break;
    }
}
if ($c_start !== -1) {
    array_splice($lines, $c_start, $c_end - $c_start);
}

// 2. Remove clients html block
$h_start = -1; $h_end = -1;
foreach ($lines as $i => $l) {
    if (strpos($l, '<!-- ── CLIENTS ── -->') !== false) { $h_start = $i; }
    if ($h_start !== -1 && $i > $h_start && strpos($l, '<!-- ── APPLICATIONS ── -->') !== false) {
        // Wait, look closely: it's actually:
        // <!-- ── APPLICATIONS ── -->
        // <?php if (has_permission...
        // <section id="credit-accounts"...
        $h_end = $i; 
        break;
    }
}

if ($h_start !== -1) {
    array_splice($lines, $h_start, $h_end - $h_start);
    $comment = "            <!-- ── CLIENTS (Moved to ?tab=clients) ── -->\n\n";
    array_splice($lines, $h_start, 0, [$comment]);
}

file_put_contents($file, implode("", $lines));
echo "Refactor script finished.";
