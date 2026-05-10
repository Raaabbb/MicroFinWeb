<?php
$policy_console_limit_assignment = isset($policy_console_limit_assignment) && is_array($policy_console_limit_assignment)
    ? $policy_console_limit_assignment
    : policy_console_limit_assignment_defaults();

$system_defaults = policy_console_credit_limits_system_defaults();
$default_limit_assignment = $system_defaults['limit_assignment'] ?? [];
$is_limit_assignment_default = ($policy_console_limit_assignment == $default_limit_assignment);
?>
<div class="policy-blueprint-card-head" style="display: flex; justify-content: space-between; align-items: flex-start;">
    <div class="policy-blueprint-card-title">
        <span class="policy-blueprint-kicker" style="margin: 0; padding: 0 0 6px 0;">Onboarding Phase</span>
        <h4 style="margin-bottom: 0;">Initial credit limit</h4>
        <p class="text-muted" style="margin-top: 4px;">For first-time users only: Initial Credit Limit = Monthly Income x Configured Percentage.</p>
    </div>
    <div>
        <?php if ($is_limit_assignment_default): ?>
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

<div class="policy-blueprint-grid policy-blueprint-grid--two">
    <label class="policy-field">
        <span class="policy-field-label">Initial Limit Percentage of Monthly Income <?php echo $policy_console_help('This defines the maximum approved loan percentage compared to the applicant\'s stated monthly income for their very first loan.'); ?></span>
        <input type="number" class="form-control" id="pcc_limit_initial_percent_of_income" name="pcc_limit_initial_percent_of_income" min="0" max="100" step="0.01" value="<?php echo htmlspecialchars((string)($policy_console_limit_assignment['initial_limit_percent_of_income'] ?? 40)); ?>">

        <div id="limit_dti_mapping_warning" style="display: none; background: rgba(var(--danger-rgb, 220, 38, 38), 0.1); border-left: 3px solid #dc2626; padding: 12px; border-radius: 4px; font-size: 13px; color: var(--text-muted); line-height: 1.4; margin-top: 12px;">
            <strong style="color: #dc2626;">/!\ Warning:</strong> This percentage is higher than your Max DTI Ratio (<span class="limit_warning_current_dti">X</span>%) rule. Onboarding users may be assigned an initial limit that the strict DTI rule will instantly reject them from borrowing.
        </div>
    </label>
</div>

<input
    type="hidden"
    name="pcc_limit_use_default_lending_cap"
    value="<?php echo !empty($policy_console_limit_assignment['use_default_lending_cap']) ? '1' : '0'; ?>"
>
<input
    type="hidden"
    name="pcc_limit_default_lending_cap_amount"
    value="<?php echo htmlspecialchars((string)($policy_console_limit_assignment['default_lending_cap_amount'] ?? 0)); ?>"
>
