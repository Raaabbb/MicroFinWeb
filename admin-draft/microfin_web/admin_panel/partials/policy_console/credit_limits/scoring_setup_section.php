<?php
// Retrieve the pristine system defaults for comparison
$system_defaults = policy_console_credit_limits_system_defaults();
$default_scoring_setup = $system_defaults['scoring_setup'] ?? [];

// Check if the current section perfectly matches the defaults
$is_scoring_default = (($policy_console_credit_limits_safe['scoring_setup'] ?? []) == $default_scoring_setup);
?>
<div class="policy-blueprint-panel" id="policy-credit-limits-scoring">
    <div class="policy-blueprint-panel-head" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <span class="policy-blueprint-panel-kicker">Core Setup</span>
            <h5 style="margin-bottom: 0;">Scoring Setup</h5>
        </div>
        <div>
            <?php if ($is_scoring_default): ?>
                <span style="font-size: 12px; padding: 4px 8px; border-radius: 12px; background: var(--bg-surface-secondary); color: var(--text-muted); border: 1px solid var(--border-color);">
                    System Default
                </span>
            <?php else: ?>
                <span style="font-size: 12px; padding: 4px 8px; border-radius: 12px; background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe;">
                    Modified
                </span>
            <?php endif; ?>
        </div>
    </div>

    <div style="margin-bottom: 16px; background: rgba(59, 130, 246, 0.1); color: var(--text-muted); font-size: 13px; padding: 8px 12px; border-radius: 6px; display: flex; align-items: center; gap: 8px; border: 1px solid rgba(59, 130, 246, 0.2);">
        <span style="display: inline-flex; align-items: center; justify-content: center; width: 16px; height: 16px; border-radius: 50%; background: #3b82f6; color: #ffffff; font-size: 11px; font-weight: bold; line-height: 1;">!</span>
        <span style="color: var(--text-muted);"><strong style="color: var(--text-color, inherit);">CS</strong> stands for Credit Score</span>
    </div>

    <div class="policy-blueprint-grid policy-blueprint-grid--three">
            <label class="policy-field">
                <span class="policy-field-label">Starting Credit Score <?php echo $policy_console_help('Default score assigned before repayment behavior moves the borrower up or down.'); ?></span>
                <input type="number" class="form-control" name="pcc_core_starting_credit_score" min="0" max="<?php echo (int)$credit_policy_score_ceiling; ?>" value="<?php echo htmlspecialchars((string)($policy_console_core_setup['starting_credit_score'] ?? 320)); ?>">
            </label>

            <!-- Dynamic Injection Containers -->
            <!-- Upgrade Rules (Bonus) -->
            <label class="policy-field" id="sync-container-upgrade-success" style="display: none;">
                <span class="policy-field-label">Successful Repayment CS Increase</span>
                <input type="number" class="form-control" id="sync-input-upgrade-success" name="pcc_upgrade_successful_repayment_points_sync" min="0" max="1000">
            </label>

            <label class="policy-field" id="sync-container-upgrade-late" style="display: none;">
                <span class="policy-field-label">Max Late Payments CS Increase</span>
                <input type="number" class="form-control" id="sync-input-upgrade-late" name="pcc_upgrade_late_payments_points_sync" min="0" max="1000">
            </label>

            <label class="policy-field" id="sync-container-upgrade-no-overdue" style="display: none;">
                <span class="policy-field-label">No Active Overdue CS Increase</span>
                <input type="number" class="form-control" id="sync-input-upgrade-no-overdue" name="pcc_upgrade_no_active_overdue_points_sync" min="0" max="1000">
            </label>

            <!-- Downgrade Rules (Penalty) -->
            <label class="policy-field" id="sync-container-downgrade-late" style="display: none;">
                <span class="policy-field-label">Late Payments Count CS Deduction</span>
                <input type="number" class="form-control" id="sync-input-downgrade-late" name="pcc_downgrade_late_payments_points_sync" min="0" max="1000">
            </label>

            <label class="policy-field" id="sync-container-downgrade-overdue" style="display: none;">
                <span class="policy-field-label">Overdue Days CS Deduction</span>
                <input type="number" class="form-control" id="sync-input-downgrade-overdue" name="pcc_downgrade_overdue_days_points_sync" min="0" max="1000">
            </label>
        </div>

        <div class="policy-field" style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border-color); display: none;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span class="policy-field-label" style="margin-bottom: 0;">
                    Apply Score Changes Immediately <?php echo $policy_console_help('If enabled, credit score upgrades/downgrades automatically recalculate and apply to active credit limits instead of waiting for the next manual review cycle.'); ?>
                </span>
                <div class="policy-decision-rule-switch">
                    <input
                        type="hidden"
                        name="pcc_limit_apply_score_changes_immediately"
                        value="<?php echo !empty($policy_console_limit_assignment['apply_score_changes_immediately']) ? '1' : '0'; ?>"
                        data-policy-toggle-input="pcc_limit_apply_score_changes_immediately"
                    >
                    <button
                        type="button"
                        class="policy-toggle-button <?php echo !empty($policy_console_limit_assignment['apply_score_changes_immediately']) ? 'is-on' : ''; ?>"
                        data-policy-toggle-button="pcc_limit_apply_score_changes_immediately"
                        aria-pressed="<?php echo !empty($policy_console_limit_assignment['apply_score_changes_immediately']) ? 'true' : 'false'; ?>"
                        aria-label="Apply Score Changes Immediately"
                    >
                        <span class="policy-toggle-button__track"><span class="policy-toggle-button__thumb"></span></span>
                        <span class="policy-toggle-button__label" data-policy-toggle-label><?php echo !empty($policy_console_limit_assignment['apply_score_changes_immediately']) ? 'On' : 'Off'; ?></span>
                    </button>
                </div>
            </div>
        </div>

        <div class="policy-blueprint-panel-actions" style="display: flex;">
            <button
                type="button"
                class="btn btn-outline"
                data-policy-toggle-panel="policy-lifecycle-panel"
                data-panel-open-label="View Advanced Rules"
                data-panel-close-label="Close"
            >View Advanced Rules</button>
        </div>
    </div>

    <div class="policy-blueprint-detail" id="policy-lifecycle-panel" hidden>
        <div class="policy-blueprint-detail-head">
            <div>
                <span class="policy-blueprint-panel-kicker">Detailed Rules</span>
                <h5>Lifecycle Eligibility</h5>
                <p class="text-muted">Use lifecycle toggles to define the conditions that make a borrower eligible for upgrade or downgrade review.</p>
            </div>
        </div>

        <div class="policy-lifecycle-columns">
            <section class="policy-blueprint-panel">
                <div class="policy-blueprint-panel-head">
                    <div>
                        <h5>Upgrade Eligibility</h5>
                        <p class="text-muted">Enable only the conditions that should count toward upgrade candidacy.</p>
                    </div>
                </div>

                <div class="policy-lifecycle-rule-grid">
                    <?php $upgrade_success_cycles = $policy_console_upgrade_rules['successful_repayment_cycles'] ?? []; ?>
                    <article class="policy-lifecycle-rule <?php echo empty($upgrade_success_cycles['enabled']) ? 'is-off' : ''; ?>" data-policy-rule-card>
                        <div class="policy-lifecycle-rule-head">
                            <div>
                                <strong>Successful Repayment Cycles</strong>
                                <?php echo $policy_console_help('Borrower must complete at least this many successful repayment cycles before upgrade candidacy can begin.'); ?>
                            </div>
                            <div class="policy-decision-rule-switch" style="display: none;">
                                <input
                                    type="hidden"
                                    name="pcc_upgrade_successful_repayment_enabled"
                                    value="<?php echo !empty($upgrade_success_cycles['enabled']) ? '1' : '0'; ?>"
                                    data-policy-rule-toggle
                                    data-policy-toggle-input="pcc_upgrade_successful_repayment_enabled"
                                >
                                <button
                                    type="button"
                                    class="policy-toggle-button <?php echo !empty($upgrade_success_cycles['enabled']) ? 'is-on' : ''; ?>"
                                    data-policy-toggle-button="pcc_upgrade_successful_repayment_enabled"
                                    aria-pressed="<?php echo !empty($upgrade_success_cycles['enabled']) ? 'true' : 'false'; ?>"
                                    aria-label="Enable Successful Repayment Cycles"
                                >
                                    <span class="policy-toggle-button__track"><span class="policy-toggle-button__thumb"></span></span>
                                    <span class="policy-toggle-button__label" data-policy-toggle-label><?php echo !empty($upgrade_success_cycles['enabled']) ? 'On' : 'Off'; ?></span>
                                </button>
                            </div>
                        </div>
                        <div class="policy-blueprint-grid policy-blueprint-grid--two">
                            <label class="policy-field">
                                <span class="policy-field-label">Cycles Required</span>
                                <input type="number" class="form-control" name="pcc_upgrade_successful_repayment_cycles" min="1" max="999" value="<?php echo htmlspecialchars((string)($upgrade_success_cycles['required_cycles'] ?? 3)); ?>">
                            </label>
                            <label class="policy-field">
                                <span class="policy-field-label">CS Increase</span>
                                <input type="number" class="form-control" name="pcc_upgrade_successful_repayment_points" min="0" max="1000" value="<?php echo htmlspecialchars((string)($upgrade_success_cycles['score_points'] ?? 5)); ?>">
                            </label>
                        </div>
                    </article>

                    <?php $upgrade_late_payments = $policy_console_upgrade_rules['maximum_late_payments_review'] ?? []; ?>
                    <article class="policy-lifecycle-rule <?php echo empty($upgrade_late_payments['enabled']) ? 'is-off' : ''; ?>" data-policy-rule-card>
                        <div class="policy-lifecycle-rule-head">
                            <div>
                                <strong>Maximum Late Payments Allowed Within Review Period</strong>
                                <?php echo $policy_console_help('Upgrade passes when late payments inside the selected review period stay within this maximum.'); ?>
                            </div>
                            <div class="policy-decision-rule-switch" style="display: none;">
                                <input
                                    type="hidden"
                                    name="pcc_upgrade_late_payments_enabled"
                                    value="<?php echo !empty($upgrade_late_payments['enabled']) ? '1' : '0'; ?>"
                                    data-policy-rule-toggle
                                    data-policy-toggle-input="pcc_upgrade_late_payments_enabled"
                                >
                                <button
                                    type="button"
                                    class="policy-toggle-button <?php echo !empty($upgrade_late_payments['enabled']) ? 'is-on' : ''; ?>"
                                    data-policy-toggle-button="pcc_upgrade_late_payments_enabled"
                                    aria-pressed="<?php echo !empty($upgrade_late_payments['enabled']) ? 'true' : 'false'; ?>"
                                    aria-label="Enable Maximum Late Payments Allowed Within Review Period"
                                >
                                    <span class="policy-toggle-button__track"><span class="policy-toggle-button__thumb"></span></span>
                                    <span class="policy-toggle-button__label" data-policy-toggle-label><?php echo !empty($upgrade_late_payments['enabled']) ? 'On' : 'Off'; ?></span>
                                </button>
                            </div>
                        </div>
                        <div class="policy-blueprint-grid policy-blueprint-grid--three">
                            <label class="policy-field">
                                <span class="policy-field-label">Maximum Allowed</span>
                                <input type="number" class="form-control" name="pcc_upgrade_late_payments_max" min="0" max="365" value="<?php echo htmlspecialchars((string)($upgrade_late_payments['maximum_allowed'] ?? 1)); ?>">
                            </label>
                            <label class="policy-field">
                                <span class="policy-field-label">Review Period <?php echo $policy_console_help('Lookback window used to check recent borrower activity, for example last 30, 60, or 90 days.'); ?></span>
                                <select class="form-control" name="pcc_upgrade_late_payments_review_days">
                                    <?php $review_days = $upgrade_late_payments['review_period_days'] ?? 90; ?>
                                    <option value="0" <?php echo $review_days == 0 ? 'selected' : ''; ?>>All Time</option>
                                    <option value="30" <?php echo $review_days == 30 ? 'selected' : ''; ?>>30 Days</option>
                                    <option value="60" <?php echo $review_days == 60 ? 'selected' : ''; ?>>60 Days</option>
                                    <option value="90" <?php echo $review_days == 90 ? 'selected' : ''; ?>>90 Days</option>
                                    <option value="120" <?php echo $review_days == 120 ? 'selected' : ''; ?>>120 Days</option>
                                    <option value="180" <?php echo $review_days == 180 ? 'selected' : ''; ?>>180 Days</option>
                                    <option value="365" <?php echo $review_days == 365 ? 'selected' : ''; ?>>1 Year</option>
                                </select>
                            </label>
                            <label class="policy-field">
                                <span class="policy-field-label">CS Increase</span>
                                <input type="number" class="form-control" name="pcc_upgrade_late_payments_points" min="0" max="1000" value="<?php echo htmlspecialchars((string)($upgrade_late_payments['score_points'] ?? 5)); ?>">
                            </label>
                        </div>
                    </article>

                    <?php $upgrade_no_overdue = $policy_console_upgrade_rules['no_active_overdue'] ?? []; ?>
                    <article class="policy-lifecycle-rule <?php echo empty($upgrade_no_overdue['enabled']) ? 'is-off' : ''; ?>" data-policy-rule-card>
                        <div class="policy-lifecycle-rule-head">
                            <div>
                                <strong>No Active Overdue</strong>
                                <?php echo $policy_console_help('Borrower must have no current overdue balance for this upgrade rule to pass.'); ?>
                            </div>
                            <div class="policy-decision-rule-switch" style="display: none;">
                                <input
                                    type="hidden"
                                    name="pcc_upgrade_no_active_overdue_enabled"
                                    value="<?php echo !empty($upgrade_no_overdue['enabled']) ? '1' : '0'; ?>"
                                    data-policy-rule-toggle
                                    data-policy-toggle-input="pcc_upgrade_no_active_overdue_enabled"
                                >
                                <button
                                    type="button"
                                    class="policy-toggle-button <?php echo !empty($upgrade_no_overdue['enabled']) ? 'is-on' : ''; ?>"
                                    data-policy-toggle-button="pcc_upgrade_no_active_overdue_enabled"
                                    aria-pressed="<?php echo !empty($upgrade_no_overdue['enabled']) ? 'true' : 'false'; ?>"
                                    aria-label="Enable No Active Overdue"
                                >
                                    <span class="policy-toggle-button__track"><span class="policy-toggle-button__thumb"></span></span>
                                    <span class="policy-toggle-button__label" data-policy-toggle-label><?php echo !empty($upgrade_no_overdue['enabled']) ? 'On' : 'Off'; ?></span>
                                </button>
                            </div>
                        </div>
                        <div class="policy-blueprint-grid policy-blueprint-grid--two">
                            <label class="policy-field">
                                <span class="policy-field-label">Review Period <?php echo $policy_console_help('Lookback window used to check recent borrower activity, for example last 30, 60, or 90 days.'); ?></span>
                                <select class="form-control" name="pcc_upgrade_no_active_overdue_review_days">
                                    <?php $no_overdue_days = $upgrade_no_overdue['review_period_days'] ?? 0; ?>
                                    <option value="0" <?php echo $no_overdue_days == 0 ? 'selected' : ''; ?>>All Time</option>
                                    <option value="30" <?php echo $no_overdue_days == 30 ? 'selected' : ''; ?>>30 Days</option>
                                    <option value="60" <?php echo $no_overdue_days == 60 ? 'selected' : ''; ?>>60 Days</option>
                                    <option value="90" <?php echo $no_overdue_days == 90 ? 'selected' : ''; ?>>90 Days</option>
                                    <option value="120" <?php echo $no_overdue_days == 120 ? 'selected' : ''; ?>>120 Days</option>
                                    <option value="180" <?php echo $no_overdue_days == 180 ? 'selected' : ''; ?>>180 Days</option>
                                    <option value="365" <?php echo $no_overdue_days == 365 ? 'selected' : ''; ?>>1 Year</option>
                                </select>
                            </label>
                            <label class="policy-field">
                                <span class="policy-field-label">CS Increase</span>
                                <input type="number" class="form-control" name="pcc_upgrade_no_active_overdue_points" min="0" max="1000" value="<?php echo htmlspecialchars((string)($upgrade_no_overdue['score_points'] ?? 5)); ?>">
                            </label>
                        </div>
                    </article>
                </div>
            </section>

            <section class="policy-blueprint-panel">
                <div class="policy-blueprint-panel-head">
                    <div>
                        <h5>Downgrade Eligibility</h5>
                        <p class="text-muted">Enable only the conditions that should trigger downward reassessment.</p>
                    </div>
                </div>

                <div class="policy-lifecycle-rule-grid">
                    <?php $downgrade_late_payments = $policy_console_downgrade_rules['late_payments_review'] ?? []; ?>
                    <article class="policy-lifecycle-rule <?php echo empty($downgrade_late_payments['enabled']) ? 'is-off' : ''; ?>" data-policy-rule-card>
                        <div class="policy-lifecycle-rule-head">
                            <div>
                                <strong>Late Payments Count Within Review Period</strong>
                                <?php echo $policy_console_help('Downgrade review is triggered when late payments reach at least this count within the selected review period.'); ?>
                            </div>
                            <div class="policy-decision-rule-switch" style="display: none;">
                                <input
                                    type="hidden"
                                    name="pcc_downgrade_late_payments_enabled"
                                    value="<?php echo !empty($downgrade_late_payments['enabled']) ? '1' : '0'; ?>"
                                    data-policy-rule-toggle
                                    data-policy-toggle-input="pcc_downgrade_late_payments_enabled"
                                >
                                <button
                                    type="button"
                                    class="policy-toggle-button <?php echo !empty($downgrade_late_payments['enabled']) ? 'is-on' : ''; ?>"
                                    data-policy-toggle-button="pcc_downgrade_late_payments_enabled"
                                    aria-pressed="<?php echo !empty($downgrade_late_payments['enabled']) ? 'true' : 'false'; ?>"
                                    aria-label="Enable Late Payments Count Within Review Period"
                                >
                                    <span class="policy-toggle-button__track"><span class="policy-toggle-button__thumb"></span></span>
                                    <span class="policy-toggle-button__label" data-policy-toggle-label><?php echo !empty($downgrade_late_payments['enabled']) ? 'On' : 'Off'; ?></span>
                                </button>
                            </div>
                        </div>
                        <div class="policy-blueprint-grid policy-blueprint-grid--three">
                            <label class="policy-field">
                                <span class="policy-field-label">Trigger At</span>
                                <input type="number" class="form-control" name="pcc_downgrade_late_payments_trigger" min="1" max="365" value="<?php echo htmlspecialchars((string)($downgrade_late_payments['trigger_count'] ?? 2)); ?>">
                            </label>
                            <label class="policy-field">
                                <span class="policy-field-label">Review Period <?php echo $policy_console_help('Lookback window used to check recent borrower activity, for example last 30, 60, or 90 days.'); ?></span>
                                <select class="form-control" name="pcc_downgrade_late_payments_review_days">
                                    <?php $dw_review_days = $downgrade_late_payments['review_period_days'] ?? 90; ?>
                                    <option value="0" <?php echo $dw_review_days == 0 ? 'selected' : ''; ?>>All Time</option>
                                    <option value="30" <?php echo $dw_review_days == 30 ? 'selected' : ''; ?>>30 Days</option>
                                    <option value="60" <?php echo $dw_review_days == 60 ? 'selected' : ''; ?>>60 Days</option>
                                    <option value="90" <?php echo $dw_review_days == 90 ? 'selected' : ''; ?>>90 Days</option>
                                    <option value="120" <?php echo $dw_review_days == 120 ? 'selected' : ''; ?>>120 Days</option>
                                    <option value="180" <?php echo $dw_review_days == 180 ? 'selected' : ''; ?>>180 Days</option>
                                    <option value="365" <?php echo $dw_review_days == 365 ? 'selected' : ''; ?>>1 Year</option>
                                </select>
                            </label>
                            <label class="policy-field">
                                <span class="policy-field-label">CS Deduction</span>
                                <input type="number" class="form-control" name="pcc_downgrade_late_payments_points" min="0" max="1000" value="<?php echo htmlspecialchars((string)($downgrade_late_payments['score_points'] ?? 12)); ?>">
                            </label>
                        </div>
                    </article>

                    <?php $downgrade_overdue = $policy_console_downgrade_rules['overdue_days_threshold'] ?? []; ?>
                    <article class="policy-lifecycle-rule <?php echo empty($downgrade_overdue['enabled']) ? 'is-off' : ''; ?>" data-policy-rule-card>
                        <div class="policy-lifecycle-rule-head">
                            <div>
                                <strong>Overdue Days Threshold</strong>
                                <?php echo $policy_console_help('This is different from grace period. It measures how long the borrower stays overdue after already becoming overdue.'); ?>
                            </div>
                            <div class="policy-decision-rule-switch" style="display: none;">
                                <input
                                    type="hidden"
                                    name="pcc_downgrade_overdue_days_enabled"
                                    value="<?php echo !empty($downgrade_overdue['enabled']) ? '1' : '0'; ?>"
                                    data-policy-rule-toggle
                                    data-policy-toggle-input="pcc_downgrade_overdue_days_enabled"
                                >
                                <button
                                    type="button"
                                    class="policy-toggle-button <?php echo !empty($downgrade_overdue['enabled']) ? 'is-on' : ''; ?>"
                                    data-policy-toggle-button="pcc_downgrade_overdue_days_enabled"
                                    aria-pressed="<?php echo !empty($downgrade_overdue['enabled']) ? 'true' : 'false'; ?>"
                                    aria-label="Enable Overdue Days Threshold"
                                >
                                    <span class="policy-toggle-button__track"><span class="policy-toggle-button__thumb"></span></span>
                                    <span class="policy-toggle-button__label" data-policy-toggle-label><?php echo !empty($downgrade_overdue['enabled']) ? 'On' : 'Off'; ?></span>
                                </button>
                            </div>
                        </div>
                        <div class="policy-blueprint-grid policy-blueprint-grid--two">
                            <label class="policy-field">
                                <span class="policy-field-label">Days Overdue</span>
                                <input type="number" class="form-control" name="pcc_downgrade_overdue_days_threshold" min="1" max="3650" value="<?php echo htmlspecialchars((string)($downgrade_overdue['days'] ?? 15)); ?>">
                            </label>
                            <label class="policy-field">
                                <span class="policy-field-label">CS Deduction</span>
                                <input type="number" class="form-control" name="pcc_downgrade_overdue_days_points" min="0" max="1000" value="<?php echo htmlspecialchars((string)($downgrade_overdue['score_points'] ?? 25)); ?>">
                            </label>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Rule mapping definitions
    const syncRules = [
        {
            toggleSelector: 'input[name="pcc_upgrade_successful_repayment_enabled"]',
            inputSelector: 'input[name="pcc_upgrade_successful_repayment_points"]',
            containerId: 'sync-container-upgrade-success',
            syncInputId: 'sync-input-upgrade-success'
        },
        {
            toggleSelector: 'input[name="pcc_upgrade_late_payments_enabled"]',
            inputSelector: 'input[name="pcc_upgrade_late_payments_points"]',
            containerId: 'sync-container-upgrade-late',
            syncInputId: 'sync-input-upgrade-late'
        },
        {
            toggleSelector: 'input[name="pcc_upgrade_no_active_overdue_enabled"]',
            inputSelector: 'input[name="pcc_upgrade_no_active_overdue_points"]',
            containerId: 'sync-container-upgrade-no-overdue',
            syncInputId: 'sync-input-upgrade-no-overdue'
        },
        {
            toggleSelector: 'input[name="pcc_downgrade_late_payments_enabled"]',
            inputSelector: 'input[name="pcc_downgrade_late_payments_points"]',
            containerId: 'sync-container-downgrade-late',
            syncInputId: 'sync-input-downgrade-late'
        },
        {
            toggleSelector: 'input[name="pcc_downgrade_overdue_days_enabled"]',
            inputSelector: 'input[name="pcc_downgrade_overdue_days_points"]',
            containerId: 'sync-container-downgrade-overdue',
            syncInputId: 'sync-input-downgrade-overdue'
        }
    ];

    // Set up bidirectional syncs
    syncRules.forEach(rule => {
        const sourceInput = document.querySelector(rule.inputSelector);
        const syncInput = document.getElementById(rule.syncInputId);
        
        if (sourceInput && syncInput) {
            syncInput.value = sourceInput.value;
            sourceInput.addEventListener('input', () => { syncInput.value = sourceInput.value; });
            syncInput.addEventListener('input', () => { sourceInput.value = syncInput.value; });
        }
    });
    
    function syncDynamicFields() {
        syncRules.forEach(rule => {
            const toggle = document.querySelector(rule.toggleSelector);
            const container = document.getElementById(rule.containerId);
            
            if (toggle && container) {
                container.style.display = toggle.value === '1' ? 'block' : 'none';
            }
        });
    }

    // Listen for toggle clicks
    const toggleButtons = document.querySelectorAll('.policy-toggle-button');
    toggleButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            setTimeout(syncDynamicFields, 50); // slight delay to let toggle hidden input update
        });
    });

    // Run on load
    syncDynamicFields();
});
</script>
