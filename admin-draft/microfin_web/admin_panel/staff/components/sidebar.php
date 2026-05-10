<?php
$current_tab = $_GET['tab'] ?? 'home';

function is_active_tab($tab_name, $current_tab) {
    return $tab_name === $current_tab ? 'active' : '';
}
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div class="logo-mark">
            <?php if ($logo_path): ?>
                <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="Logo">
            <?php else: ?>
                <span class="material-symbols-rounded ms">account_balance</span>
            <?php endif; ?>
        </div>
        <div class="logo-text">
            <h2><?php echo htmlspecialchars($_SESSION['tenant_name']); ?></h2>
            <p>Employee Portal</p>
        </div>
    </div>

    <nav style="flex:1; padding: 8px 10px;">
        <div class="nav-section">
            <div class="nav-label">Workspace</div>
            <a class="nav-item <?php echo is_active_tab('home', $current_tab); ?>" data-target="home" data-title="Home" data-subtitle="Dashboard" href="?tab=home">
                <span class="material-symbols-rounded ms">home</span> Home
            </a>

            <?php if (has_permission('VIEW_CLIENTS') || has_permission('CREATE_CLIENTS')): ?>
                <a class="nav-item <?php echo is_active_tab('clients', $current_tab); ?>" data-title="Client Management" data-subtitle="Profiles" href="?tab=clients">
                    <span class="material-symbols-rounded ms">group</span> Client Management
                </a>
            <?php endif; ?>

            <?php if (has_permission('VIEW_CREDIT_ACCOUNTS') || has_permission('VIEW_CLIENTS') || has_permission('CREATE_CLIENTS')): ?>
                <a class="nav-item <?php echo is_active_tab('credit-accounts', $current_tab); ?>" data-target="credit-accounts" data-title="Credit Accounts Management" data-subtitle="Limits & Growth" href="?tab=credit-accounts">
                    <span class="material-symbols-rounded ms">credit_card</span> Credit Accounts Management
                </a>
            <?php endif; ?>

            <?php if (has_permission('VIEW_APPLICATIONS') || has_permission('MANAGE_APPLICATIONS')): ?>
                <a class="nav-item <?php echo is_active_tab('applications', $current_tab); ?>" data-target="applications" data-title="Loan Applications" data-subtitle="Pipeline" href="?tab=applications">
                    <span class="material-symbols-rounded ms">description</span> Loan Applications
                    <span class="nav-badge" id="navPendingAppsBadge" style="<?php echo count($pending_applications) > 0 ? '' : 'display:none;'; ?>">
                        <?php echo count($pending_applications); ?>
                    </span>
                </a>
            <?php endif; ?>

            <?php if (has_permission('VIEW_LOANS') || has_permission('CREATE_LOANS') || has_permission('APPROVE_LOANS')): ?>
                <a class="nav-item <?php echo is_active_tab('loans', $current_tab); ?>" data-target="loans" data-title="Loans Management" data-subtitle="Servicing" href="?tab=loans">
                    <span class="material-symbols-rounded ms">real_estate_agent</span> Loans Management
                </a>
            <?php endif; ?>

            <?php if (has_permission('PROCESS_PAYMENTS')): ?>
                <a class="nav-item <?php echo is_active_tab('payments', $current_tab); ?>" data-target="payments" data-title="Receipts & Transactions" data-subtitle="Collections" href="?tab=payments">
                    <span class="material-symbols-rounded ms">receipt_long</span> Receipts & Transactions
                </a>
            <?php endif; ?>

            <?php if (has_permission('VIEW_USERS')): ?>
                <a class="nav-item <?php echo is_active_tab('team', $current_tab); ?>" data-target="team" data-title="Team Directory" data-subtitle="Staff" href="?tab=team">
                    <span class="material-symbols-rounded ms">badge</span> Team Directory
                </a>
            <?php endif; ?>
        </div>

        <div class="nav-section" style="margin-top:8px;">
            <div class="nav-label">Insights & Settings</div>
            <?php if (has_permission('VIEW_REPORTS')): ?>
                <a class="nav-item <?php echo is_active_tab('reports', $current_tab); ?>" data-target="reports" data-title="Reports & Analytics" data-subtitle="Insights" href="?tab=reports">
                    <span class="material-symbols-rounded ms">bar_chart</span> Reports & Analytics
                </a>
            <?php endif; ?>
            <a class="nav-item <?php echo is_active_tab('profile', $current_tab); ?>" data-title="My Profile" data-subtitle="Account" href="?tab=profile">
                <span class="material-symbols-rounded ms">manage_accounts</span> My Profile
            </a>
        </div>
    </nav>

    <div class="sidebar-footer">
        <a class="nav-item" href="../../tenant_login/logout.php" style="color:#f87171;">
            <span class="material-symbols-rounded ms" style="color:#f87171;">logout</span> Sign Out
        </a>
    </div>
</aside>
