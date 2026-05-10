<?php
$path = dirname(__DIR__, 2) . '/admin_panel/staff/dashboard.php';
$lines = explode("\n", file_get_contents($path));
$n = count($lines);
$changes = 0;

// Fix 1: Client "View Profile" button → call viewClient()
for ($i = 0; $i < $n; $i++) {
    if (strpos($lines[$i], "alert('View profile logic to be implemented')") !== false) {
        $client_id_line = '';
        // search nearby lines for client_id
        for ($k = $i - 20; $k < $i; $k++) {
            if (strpos($lines[$k] ?? '', 'foreach ($all_clients as $client)') !== false) {
                break;
            }
        }
        $lines[$i] = str_replace(
            "alert('View profile logic to be implemented')",
            "viewClient(<?php echo (int)\$client['client_id']; ?>)",
            $lines[$i]
        );
        $lines[$i] = str_replace('>View Profile<', '>View Profile<', $lines[$i]);
        $changes++;
        echo "Fix 1 applied at line $i\n";
    }
}

// Fix 2: Reports view - replace placeholder with real data container
$reports_start = -1; $reports_end = -1;
for ($i = 0; $i < $n; $i++) {
    if (strpos($lines[$i], "id=\"reports\" class=\"view-container\"") !== false) {
        $reports_start = $i;
    }
    if ($reports_start > 0 && strpos($lines[$i], '<?php endif; ?>') !== false && $i > $reports_start) {
        $reports_end = $i;
        break;
    }
}
echo "Reports block: $reports_start to $reports_end\n";

if ($reports_start > 0 && $reports_end > 0) {
    $new_reports = '        <div id="reports" class="view-container">
            <div class="welcome-card" style="margin-bottom:24px;">
                <div class="welcome-icon" style="background:rgba(168,85,247,.1);color:#a855f7;"><span class="material-symbols-rounded">analytics</span></div>
                <div class="welcome-text"><h1>Reports &amp; Analytics</h1><p>Financial overview and performance metrics.</p></div>
                <div style="display:flex;gap:8px;margin-left:auto;">
                    <button class="btn-walk-in" style="background:var(--bg-surface);color:var(--text-main);border:1px solid var(--border-color);" onclick="loadReports(\'week\')">This Week</button>
                    <button class="btn-walk-in" style="background:var(--bg-surface);color:var(--text-main);border:1px solid var(--border-color);" onclick="loadReports(\'month\')">This Month</button>
                    <button class="btn-walk-in" style="background:var(--bg-surface);color:var(--text-main);border:1px solid var(--border-color);" onclick="loadReports(\'year\')">This Year</button>
                </div>
            </div>
            <div id="reportsBody"><p style="color:var(--text-muted);padding:24px;">Loading report data...</p></div>
        </div>';
    $before = array_slice($lines, 0, $reports_start);
    $after  = array_slice($lines, $reports_end);
    $lines  = array_merge($before, explode("\n", $new_reports), $after);
    $n = count($lines);
    $changes++;
    echo "Fix 2 (Reports) applied\n";
}

file_put_contents($path, implode("\n", $lines));
echo "Done. Changes: $changes. Total lines: $n\n";
echo "viewClient in file: " . (strpos(file_get_contents($path), 'viewClient') !== false ? 'YES' : 'NO') . "\n";
echo "reportsBody in file: " . (strpos(file_get_contents($path), 'reportsBody') !== false ? 'YES' : 'NO') . "\n";
