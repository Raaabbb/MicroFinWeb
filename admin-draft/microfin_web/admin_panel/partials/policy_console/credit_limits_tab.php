<?php
$policy_console_credit_limits_safe = isset($policy_console_credit_limits) && is_array($policy_console_credit_limits)
    ? $policy_console_credit_limits
    : policy_console_credit_limits_defaults(
        isset($credit_policy) && is_array($credit_policy) ? $credit_policy : [],
        isset($credit_limit_rules) && is_array($credit_limit_rules) ? $credit_limit_rules : [],
        (int)($credit_policy_score_ceiling ?? 1000)
    );

$policy_console_core_setup = $policy_console_credit_limits_safe['scoring_setup']['core'] ?? [];
$policy_console_detailed_rules = $policy_console_credit_limits_safe['scoring_setup']['detailed_rules'] ?? [];
$policy_console_upgrade_rules = $policy_console_detailed_rules['upgrade'] ?? [];
$policy_console_downgrade_rules = $policy_console_detailed_rules['downgrade'] ?? [];
$policy_console_score_band_rows = $policy_console_credit_limits_safe['score_bands']['rows'] ?? [];
$policy_console_limit_assignment = $policy_console_credit_limits_safe['limit_assignment'] ?? [];
$policy_console_row_index = 0;
$policy_console_workspace_name = strtoupper(trim((string)($settings['company_name'] ?? $tenant_name ?? 'Tenant Workspace')));

$policy_console_help = static function (string $text, string ...$label): string {
    $labelText = $label[0] ?? 'More info';
    return '<span class="policy-help" tabindex="0" role="button" aria-label="'
        . htmlspecialchars($labelText, ENT_QUOTES, 'UTF-8')
        . '" data-help="'
        . htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
        . '">!</span>';
};

$policy_console_rule_state_badge = static function (array $rules): string {
    foreach ($rules as $rule) {
        if (!empty($rule['enabled'])) {
            return 'Configured';
        }
    }
    return 'Pending';
};

$policy_console_upgrade_status = $policy_console_rule_state_badge($policy_console_upgrade_rules);
$policy_console_downgrade_status = $policy_console_rule_state_badge($policy_console_downgrade_rules);
$policy_console_scoring_status = ($policy_console_upgrade_status === 'Configured' || $policy_console_downgrade_status === 'Configured')
    ? 'Configured'
    : 'Pending';
$policy_console_band_status = count($policy_console_score_band_rows) > 0 ? 'Configured' : 'Pending';
$policy_console_assignment_status = 'Configured';
?>
<form method="POST" action="admin.php" class="policy-blueprint-form" id="policy-console-credit-limits-form">
    <input type="hidden" name="action" value="save_policy_console_credit_limits">
    <input type="hidden" name="credit_policy_tab" value="credit_limits">

    <div class="policy-blueprint-header">
        <div class="policy-blueprint-header-copy">
            <span class="policy-blueprint-kicker"><?php echo htmlspecialchars($policy_console_workspace_name); ?></span>
            <h3>Credit &amp; Limits</h3>
            <p class="text-muted">Configure scoring setup, score bands, and default limit assignment in one tenant-ready flow.</p>
        </div>
    </div>

    <!-- REMOVED policy-blueprint-flow CARDS -->

    <div class="policy-blueprint-stack">
        <?php require __DIR__ . '/credit_limits/scoring_setup_section.php'; ?>
        <?php require __DIR__ . '/credit_limits/score_band_matrix_section.php'; ?>

        <section class="policy-blueprint-card" id="policy-credit-limits-assignment">
            <?php require __DIR__ . '/credit_limits/limit_assignment_section.php'; ?>
        </section>
    </div>

    <!-- Floating save removed in favor of global saving action -->

    <!-- Custom Modals -->
    <div class="policy-blueprint-modal" id="policy-unsaved-modal" hidden>
        <div class="policy-blueprint-modal-dialog">
            <div class="policy-blueprint-modal-header">
                <h4>Unsaved Changes</h4>
                <p>You have unsaved changes on this page.</p>
            </div>
            <div class="policy-blueprint-modal-body">
                <p>If you leave now, your recent edits to the Credit &amp; Limits configuration will be lost. Do you want to save or discard your changes?</p>
            </div>
            <div class="policy-blueprint-modal-footer" style="display: flex; gap: 8px; justify-content: flex-end;">
                <button type="button" class="btn btn-outline" data-modal-dismiss>Cancel</button>
                <button type="button" class="btn btn-ghost-danger" id="policy-unsaved-discard-btn">Discard</button>
                <button type="button" class="btn btn-primary" id="policy-unsaved-save-btn">Save</button>
            </div>
        </div>
    </div>

    <div class="policy-blueprint-modal" id="policy-delete-row-modal" hidden>
        <div class="policy-blueprint-modal-dialog">
            <div class="policy-blueprint-modal-header">
                <h4>Delete Score Band</h4>
                <p>Are you sure you want to remove this band?</p>
            </div>
            <div class="policy-blueprint-modal-body">
                <p>Removing this score band row will permanently delete it upon saving the form.</p>
            </div>
            <div class="policy-blueprint-modal-footer">
                <button type="button" class="btn btn-outline" data-modal-dismiss>Cancel</button>
                <button type="button" class="btn btn-ghost-danger" id="policy-delete-row-confirm-btn">Delete Band</button>
            </div>
        </div>
    </div>

    <template id="policy-console-score-band-row-template">
        <tr class="policy-band-row" data-policy-score-band-row data-policy-row-index="__INDEX__">
            <td>
                <input type="hidden" name="pcc_score_band_id[]" value="band___INDEX__">
                <input type="text" class="form-control" name="pcc_score_band_label[]" value="" maxlength="60" required>
            </td>
            <td><input type="number" class="form-control" name="pcc_score_band_min[]" min="0" value="0" required></td>
            <td><input type="number" class="form-control" name="pcc_score_band_max[]" min="0" value="" placeholder="850+"></td>
            <td><input type="number" class="form-control" name="pcc_score_band_base_growth[]" min="0" max="100" step="0.001" value="0" required></td>
            <td><input type="number" class="form-control" name="pcc_score_band_micro_growth[]" min="0" max="10" step="0.001" value="0" required></td>
            <td class="policy-band-actions">
                <button type="button" class="btn btn-ghost-danger" data-policy-score-band-delete>
                    <span class="material-symbols-rounded">close</span>
                </button>
            </td>
        </tr>
    </template>
</form>
