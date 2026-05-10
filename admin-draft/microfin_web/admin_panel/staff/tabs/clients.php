<?php
if (!has_permission('VIEW_CLIENTS') && !has_permission('CREATE_CLIENTS')) {
    echo "<div style='padding: 32px;text-align:center;'><h2 style='color:#f87171;'>Access Denied</h2><p>You do not have permission to view clients.</p></div>";
    return;
}

require_once __DIR__ . '/../functions/db_clients.php';
$clientData = get_all_tenant_clients($pdo, $_SESSION['tenant_id']);
$all_clients = $clientData['data'];
$_client_debug = $clientData['debug'];
$_client_debug['has_perm'] = true; // Since we passed the gate above

// Ensure statusBadgePHP is available, might be in dashboard.php but let's be safe:
if (!function_exists('statusBadgePHP')) {
    function statusBadgePHP($val) {
        $val = trim((string)$val);
        $st = strtolower($val);
        $bg = ''; $fg = '';
        if (in_array($st, ['active', 'verified', 'approved'])) { $bg = '#dcfce7'; $fg = '#166534'; }
        elseif (in_array($st, ['pending', 'in review', 'processing'])) { $bg = '#fef3c7'; $fg = '#92400e'; }
        elseif (in_array($st, ['rejected', 'cancelled', 'inactive'])) { $bg = '#fee2e2'; $fg = '#991b1b'; }
        else { $bg = '#f1f5f9'; $fg = '#475569'; }
        return "<span style='display:inline-flex;align-items:center;padding:3px 10px;border-radius:99px;font-size:.7rem;font-weight:600;background:{$bg};color:{$fg};'>" 
               . htmlspecialchars($val ?: 'None') . "</span>";
    }
}
?>

<!-- ── CLIENTS TAB ── -->
<div class="page-header">
    <div class="page-icon" style="background:rgba(16,185,129,.1);color:#10b981;">
        <span class="material-symbols-rounded ms" style="font-size:22px;">group</span>
    </div>
    <div>
        <h1>Client Management</h1>
        <p>View, search, and manage all registered borrowers.</p>
    </div>
    <div class="page-header-actions">
        <?php if (has_permission('CREATE_CLIENTS')): ?>
            <!-- Hiding the New Client button temporarily -->
            <!--
            <button class="btn-primary" onclick="openModal('walkInModal')">
                <span class="material-symbols-rounded ms">person_add</span> New Client
            </button>
            -->
        <?php endif; ?>
    </div>
</div>

<div class="search-bar">
    <div class="search-input-wrap">
        <span class="material-symbols-rounded ms">search</span>
        <input type="text" id="clientSearch" placeholder="Search by name, email, phone…"
            oninput="debounce(() => loadClients(document.getElementById('clientSearch').value), 350)()">
    </div>
</div>

<div class="filter-tabs" id="clientFilterTabs">
    <button class="filter-tab active" data-client-filter="all" onclick="loadClients(document.getElementById('clientSearch').value, 'all', this)">All</button>
    <button class="filter-tab" data-client-filter="Active" onclick="loadClients(document.getElementById('clientSearch').value, 'Active', this)">Active</button>
    <button class="filter-tab" data-client-filter="Inactive" onclick="loadClients(document.getElementById('clientSearch').value, 'Inactive', this)">Inactive</button>
    <button class="filter-tab" data-client-filter="Pending" onclick="loadClients(document.getElementById('clientSearch').value, 'Pending', this)">Pending</button>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Client Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Registered</th>
                    <th>Source</th>
                    <th>Status</th>
                    <th>View User</th>
                </tr>
            </thead>
            <tbody id="clientsTbody">
                <?php if (empty($all_clients)): ?>
                    <tr class="empty-row">
                        <td colspan="7">No clients registered yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($all_clients as $c): ?>
                        <tr>
                            <td class="td-bold">
                                <?php echo htmlspecialchars($c['first_name'] . ' ' . $c['last_name']); ?></td>
                            <td class="td-muted">
                                <?php echo htmlspecialchars(!empty($c['email_address']) ? $c['email_address'] : '—'); ?>
                            </td>
                            <td class="td-muted">
                                <?php echo htmlspecialchars(!empty($c['contact_number']) ? $c['contact_number'] : '—'); ?>
                            </td>
                            <td class="td-muted">
                                <?php echo date('M d, Y', strtotime($c['registration_date'])); ?></td>
                            <td>
                                <?php if (($c['user_type'] ?? '') === 'Client'): ?>
                                    <span class="badge badge-blue">App</span>
                                <?php else: ?>
                                    <span class="badge badge-gray">Walk-in</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo statusBadgePHP($c['effective_status'] ?? 'Unverified'); ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline" onclick="viewClient(<?php echo (int) ($c['client_id'] ?? 0); ?>)">View User</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
