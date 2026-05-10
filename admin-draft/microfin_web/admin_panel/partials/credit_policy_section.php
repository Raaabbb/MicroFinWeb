<section id="credit_settings" class="view-section <?php echo $active_view === 'credit_settings' ? 'active' : ''; ?>">
    <div class="policy-console-shell">
        <div class="policy-console-stage">
            <div class="credit-policy-tab-panels">
                <div class="credit-policy-tab-panel" data-credit-policy-tab-panel="overview" <?php echo $credit_policy_subtab === 'overview' ? '' : 'hidden'; ?>>
                    <?php require __DIR__ . '/policy_console/overview_tab.php'; ?>
                </div>

                <div class="credit-policy-tab-panel" data-credit-policy-tab-panel="credit_limits" <?php echo $credit_policy_subtab === 'credit_limits' ? '' : 'hidden'; ?>>
                    <?php require __DIR__ . '/policy_console/credit_limits_tab.php'; ?>
                </div>

                <div class="credit-policy-tab-panel" data-credit-policy-tab-panel="decision_rules" <?php echo $credit_policy_subtab === 'decision_rules' ? '' : 'hidden'; ?>>
                    <?php require __DIR__ . '/policy_console/decision_rules_tab.php'; ?>
                </div>

                <div class="credit-policy-tab-panel" data-credit-policy-tab-panel="compliance_documents" <?php echo $credit_policy_subtab === 'compliance_documents' ? '' : 'hidden'; ?>>
                    <?php require __DIR__ . '/policy_console/compliance_documents_tab.php'; ?>
                </div>
            </div>
        </div>

        <!-- Global Policy Simulator -->
        <?php require __DIR__ . '/policy_console/simulator_sidebar.php'; ?>

        <!-- Unsaved Changes Manager -->
        <?php require __DIR__ . '/policy_console/unsaved_changes_manager.php'; ?>

    </div>
</section>
