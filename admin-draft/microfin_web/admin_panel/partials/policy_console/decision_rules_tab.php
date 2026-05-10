<?php
$policy_console_decision_config = isset($policy_console_decision_rules) && is_array($policy_console_decision_rules)
    ? $policy_console_decision_rules
    : policy_console_decision_rules_defaults(
        isset($credit_policy_score_ceiling) ? (int)$credit_policy_score_ceiling : 1000,
        isset($credit_policy_ci_configurable_options) && is_array($credit_policy_ci_configurable_options)
            ? $credit_policy_ci_configurable_options : []
    );

$policy_console_workflow = $policy_console_decision_config['workflow'] ?? [];
$policy_console_rule_groups = $policy_console_decision_config['decision_rules'] ?? [];
$policy_console_demographics = $policy_console_rule_groups['demographics'] ?? [];
$policy_console_affordability = $policy_console_rule_groups['affordability'] ?? [];
$policy_console_guardrails = $policy_console_rule_groups['guardrails'] ?? [];
$policy_console_exposure = $policy_console_rule_groups['exposure'] ?? [];

$policy_console_help = static function (string $text, string ...$label): string {
    $labelText = $label[0] ?? 'More info';
    return '<span class="policy-help" tabindex="0" role="button" aria-label="'
        . htmlspecialchars($labelText, ENT_QUOTES, 'UTF-8')
        . '" data-help="'
        . htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
        . '">i</span>';
};

function renderToggleHeader($label, $helpText, $name, $value) {
    global $policy_console_help;
    $isOn = !empty($value);
    $isOnClass = $isOn ? 'is-on' : '';
    $ariaPressed = $isOn ? 'true' : 'false';
    $labelState = $isOn ? 'On' : 'Off';
    return "
        <div class=\"policy-decision-rule-header\">
            <div class=\"policy-decision-rule-label\">
                <strong>" . htmlspecialchars($label) . "</strong>
                " . $policy_console_help($helpText) . "
            </div>
            <div class=\"policy-inline-toggle-row__control\" style=\"transform: scale(0.85); margin: 0;\">
                <input type=\"hidden\" name=\"{$name}\" value=\"" . ($isOn ? '1' : '0') . "\" data-policy-toggle-input=\"{$name}\">
                <button type=\"button\" class=\"policy-toggle-button {$isOnClass}\" data-policy-toggle-button=\"{$name}\" aria-pressed=\"{$ariaPressed}\" aria-label=\"{$label}\">
                    <span class=\"policy-toggle-button__track\"><span class=\"policy-toggle-button__thumb\"></span></span>
                    <span class=\"policy-toggle-button__label\" data-policy-toggle-label>{$labelState}</span>
                </button>
            </div>
        </div>
    ";
}
?>
<style>
.policy-decision-rule-list {
    display: flex;
    flex-direction: column;
}
.policy-decision-rule-item {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border-color);
    background-color: var(--bg-card);
}
.policy-decision-rule-item:last-child {
    border-bottom: none;
}
.policy-decision-rule-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}
.policy-decision-rule-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 15px;
}
.policy-decision-input-group {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    padding-top: 4px;
    padding-bottom: 4px;
    transition: opacity 0.2s ease;
}
.policy-decision-field {
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 8px;
}
.policy-decision-field-col {
    flex-direction: column;
    align-items: flex-start;
    width: 100%;
}
.policy-decision-field-label {
    font-size: 14px;
    font-weight: 400;
    color: var(--text-main);
    white-space: nowrap;
}
.policy-decision-field .form-control {
    background-color: var(--bg-body);
    border: 1px solid var(--border-color);
    color: var(--text-main);
    border-radius: 6px;
    padding: 6px 10px;
    font-size: 14px;
    font-weight: normal;
    width: auto;
    min-width: 100px;
    max-width: 180px;
    text-align: left;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
    box-shadow: inset 0 1px 2px rgba(0,0,0,0.02);
}
.policy-decision-field .form-control:focus {
    outline: none;
    border-color: var(--primary-color);
    background-color: var(--bg-card);
    box-shadow: 0 0 0 1px var(--primary-color);
}
/* Hide number arrows for a cleaner minimalist text look */
.policy-decision-field .form-control::-webkit-outer-spin-button,
.policy-decision-field .form-control::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}
.policy-decision-field .form-control[type=number] {
  -moz-appearance: textfield;
}

/* Pill Checkbox Styles */
.policy-pill-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 8px;
}
.policy-pill-label {
    cursor: pointer;
    display: inline-block;
}
.policy-pill-label input[type="checkbox"] {
    display: none;
}
.policy-pill-button {
    display: inline-block;
    padding: 8px 16px;
    border-radius: 20px;
    background-color: var(--bg-body);
    color: var(--text-muted);
    font-size: 13px;
    font-weight: 500;
    border: 1px solid var(--border-color);
    transition: all 0.2s ease;
    user-select: none;
    opacity: 0.8;
}
.policy-pill-label input[type="checkbox"]:checked + .policy-pill-button {
    background-color: var(--primary-color);
    color: #ffffff;
    border-color: var(--primary-color);
    opacity: 1;
}
.policy-pill-label:hover .policy-pill-button {
    opacity: 0.9;
}

.is-visually-disabled { opacity: 0.35; pointer-events: none; filter: grayscale(1); }

/* Accordion Header Styles */
.policy-decision-category-header {
    background-color: var(--bg-body);
    color: var(--text-muted);
    padding: 12px 20px;
    font-size: 13px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 1px solid var(--border-color);
    border-top: 1px solid var(--border-color);
    margin-top: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    cursor: pointer;
    user-select: none;
    transition: background-color 0.2s ease, color 0.2s ease;
}
.policy-decision-category-header:hover {
    background-color: rgba(var(--primary-rgb), 0.05);
    color: var(--text-main);
}
.policy-decision-category-header-title {
    display: flex;
    align-items: center;
    gap: 8px;
}
.policy-decision-category-header .chevron-icon {
    transition: transform 0.3s ease;
    width: 14px;
    height: 14px;
}
.policy-decision-category-header.is-open .chevron-icon {
    transform: rotate(180deg);
}
.policy-decision-category-header:first-child {
    border-top: none;
    border-top-left-radius: 8px;
    border-top-right-radius: 8px;
}

/* Accordion Body */
.policy-decision-category-body {
    display: none;
}
.policy-decision-category-body.is-open {
    display: block;
}
</style>

<form method="POST" action="admin.php" class="policy-tab-form" id="policy-console-decision-rules-form">
    <input type="hidden" name="action" value="save_policy_console_decision_rules">
    <input type="hidden" name="credit_policy_tab" value="decision_rules">

    <div class="policy-compact-stack">
        <section class="policy-compact-card" style="margin-bottom: 16px;">
            <div class="policy-save-row" style="display: flex; justify-content: space-between; align-items: center;">
                <div class="policy-compact-toolbar-copy">
                    <h3 style="margin-bottom: 4px;">Rules & Requirements</h3>
                    <p class="text-muted" style="font-size: 13px;">Manage fine-grained risk tolerances with independent master toggles.</p>
                </div>
            </div>
        </section>


        <section class="policy-compact-card">
            <div class="policy-decision-rule-list">
                
                <!-- Demographics Section -->
                <div class="policy-decision-category-header" onclick="this.classList.toggle('is-open'); this.nextElementSibling.classList.toggle('is-open');">
                    <div class="policy-decision-category-header-title">
                        <svg style="width: 16px; height: 16px; fill: currentColor;" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                        Demographics
                    </div>
                    <svg class="chevron-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </div>
                <div class="policy-decision-category-body">
                    <!-- // Age limits: (App Flow) Prevents the user from registering for the tenant if DOB is outside bounds. Minimum age requires ID verification. -->
                    <div class="policy-decision-rule-item">
                        <?php echo renderToggleHeader('Age Restrictions', 'Controls demographic age eligibility.', 'pcdr_age_enabled', $policy_console_demographics['age_enabled']); ?>
                        <div class="policy-decision-input-group toggle-group-pcdr_age_enabled">
                            <div class="policy-decision-field policy-decision-field-col">
                                <span class="policy-decision-field-label">Minimum Age</span>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <input type="number" class="form-control" name="pcdr_min_age" value="<?php echo htmlspecialchars((string)($policy_console_demographics['min_age'] ?? '')); ?>" placeholder="18">
                                    <span class="text-muted" style="font-size: 13px;">years</span>
                                </div>
                            </div>
                            <div class="policy-decision-field policy-decision-field-col">
                                <span class="policy-decision-field-label">Maximum Age</span>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <input type="number" class="form-control" name="pcdr_max_age" value="<?php echo htmlspecialchars((string)($policy_console_demographics['max_age'] ?? '')); ?>" placeholder="65">
                                    <span class="text-muted" style="font-size: 13px;">years</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Rule: allowed Demographics go here -->

                    <!-- // Residency Tenure: (App Flow) Rejects during verification if address tenure requirement isn't met. Requires proof of billing/ID. -->
                    <div class="policy-decision-rule-item">
                        <?php echo renderToggleHeader('Residency Tenure', 'Minimum required months of living at current residence.', 'pcdr_residency_tenure_enabled', $policy_console_demographics['residency_tenure_enabled']); ?>
                        <div class="policy-decision-input-group toggle-group-pcdr_residency_tenure_enabled">
                            <div class="policy-decision-field policy-decision-field-col">
                                <span class="policy-decision-field-label">Minimum Residency</span>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <input type="number" class="form-control" name="pcdr_min_residency_months" value="<?php echo htmlspecialchars((string)($policy_console_demographics['min_residency_months'] ?? '')); ?>" placeholder="6">
                                    <span class="text-muted" style="font-size: 13px;">months</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- // Employment Status: (App Flow) Rejects during verification if employment type doesn't match allowed statuses. Requires employment proof. -->
                    <div class="policy-decision-rule-item" style="border-bottom: 1px solid var(--border-color);">
                        <?php echo renderToggleHeader('Employment Status', 'Which employment statuses are allowed.', 'pcdr_employment_status_enabled', $policy_console_demographics['employment_status_enabled']); ?>
                        <div class="policy-decision-input-group toggle-group-pcdr_employment_status_enabled">
                            <div class="policy-decision-field policy-decision-field-col">
                                <span class="policy-decision-field-label">Eligible Statuses</span>
                                <?php 
                                    $selectedStatuses = is_array($policy_console_demographics['eligible_statuses'] ?? null) ? $policy_console_demographics['eligible_statuses'] : []; 
                                    $statusOptions = [
                                        'full_time' => 'Full Time',
                                        'part_time' => 'Part Time',
                                        'contract' => 'Contract',
                                        'freelancer' => 'Freelancer / Gig',
                                        'self_employed' => 'Self Employed',
                                        'casual' => 'Casual / Seasonal',
                                        'retired' => 'Retired / Pensioner',
                                        'student' => 'Student',
                                        'unemployed' => 'Unemployed'
                                    ];
                                ?>
                                <div class="policy-pill-list">
                                    <?php foreach($statusOptions as $val => $text): ?>
                                        <label class="policy-pill-label">
                                            <input type="checkbox" name="pcdr_eligible_statuses[]" value="<?php echo $val; ?>" <?php echo in_array($val, $selectedStatuses) ? 'checked' : ''; ?>>
                                            <span class="policy-pill-button"><?php echo $text; ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Affordability Section -->
                <div class="policy-decision-category-header" onclick="this.classList.toggle('is-open'); this.nextElementSibling.classList.toggle('is-open');">
                    <div class="policy-decision-category-header-title">
                        <svg style="width: 16px; height: 16px; fill: currentColor;" viewBox="0 0 24 24"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/></svg>
                        Affordability
                    </div>
                    <svg class="chevron-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </div>
                <div class="policy-decision-category-body">
                    <!-- // Minimum Income: (App Flow) Hidden from user. Rejects application submission immediately post-verification with a generic message if stated income is too low. -->
                    <div class="policy-decision-rule-item">
                        <?php echo renderToggleHeader('Minimum Income', 'Minimum gross monthly income requirement.', 'pcdr_income_enabled', $policy_console_affordability['income_enabled']); ?>
                        <div class="policy-decision-input-group toggle-group-pcdr_income_enabled">
                            <div class="policy-decision-field policy-decision-field-col">
                                <span class="policy-decision-field-label">Monthly Gross Income</span>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span class="text-muted" style="font-size: 14px;">$</span>
                                    <input type="number" step="0.01" class="form-control" name="pcdr_min_monthly_income" value="<?php echo htmlspecialchars((string)($policy_console_affordability['min_monthly_income'] ?? '')); ?>" placeholder="0.00" style="text-align: left;">
                                </div>
                                <div style="margin-top: 12px; background: rgba(59, 130, 246, 0.1); color: var(--text-muted); font-size: 13px; padding: 8px 12px; border-radius: 6px; display: flex; align-items: flex-start; gap: 8px; border: 1px solid rgba(59, 130, 246, 0.2);">
                                    <span style="display: inline-flex; align-items: center; justify-content: center; width: 16px; height: 16px; border-radius: 50%; background: #3b82f6; color: #ffffff; font-size: 11px; font-weight: bold; line-height: 1; flex-shrink: 0; margin-top: 2px;">!</span>
                                    <span style="color: var(--text-muted);">
                                        <strong>Automatic Income Filter:</strong> If an applicant's stated monthly income falls below this minimum requirement, their loan application is <strong>instantly declined</strong>. This serves as an early check to ensure basic affordability before further processing.
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- // Debt-to-Income (DTI): (App Flow) Calculated during loan application. UI instantly warns/blocks the user if requested loan pushes DTI over the limit. -->
                    <div class="policy-decision-rule-item">
                        <?php echo renderToggleHeader('Debt-to-Income (DTI)', 'Maximum DTI ratio percentage.', 'pcdr_dti_enabled', $policy_console_affordability['dti_enabled']); ?>
                        <div class="policy-decision-input-group toggle-group-pcdr_dti_enabled">
                            <div class="policy-decision-field policy-decision-field-col">
                                <span class="policy-decision-field-label">Maximum DTI Ratio</span>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <input type="number" step="0.01" class="form-control" name="pcdr_max_dti_percentage" value="<?php echo htmlspecialchars((string)($policy_console_affordability['max_dti_percentage'] ?? '')); ?>" placeholder="45.00" style="text-align: left;">
                                    <span class="text-muted" style="font-size: 14px;">%</span>
                                </div>
                                <div id="dti_limit_mapping_warning" style="display: none; background: rgba(var(--danger-rgb, 220, 38, 38), 0.1); border-left: 3px solid #dc2626; padding: 12px; border-radius: 4px; font-size: 13px; color: var(--text-muted); line-height: 1.4; margin-top: 12px; grid-column: 1 / -1;">
                                    <strong style="color: #dc2626;">/!\ Warning:</strong> Your <strong>Initial Limit Percentage</strong> (<span class="dti_warning_current_limit">X</span>%) in the Credit & Limits tab is higher than this strict DTI setting. Onboarding users may be assigned a limit they are instantly rejected from fully using.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- // Payment-to-Income (PTI): (App Flow) Calculated during loan application. UI instantly warns/blocks the user if expected installment exceeds PTI limit. -->
                    <div class="policy-decision-rule-item" style="border-bottom: 1px solid var(--border-color);">
                        <?php echo renderToggleHeader('Payment-to-Income (PTI)', 'Maximum PTI ratio percentage.', 'pcdr_pti_enabled', $policy_console_affordability['pti_enabled']); ?>
                        <div class="policy-decision-input-group toggle-group-pcdr_pti_enabled">
                            <div class="policy-decision-field policy-decision-field-col">
                                <span class="policy-decision-field-label">Maximum PTI Ratio</span>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <input type="number" step="0.01" class="form-control" name="pcdr_max_pti_percentage" value="<?php echo htmlspecialchars((string)($policy_console_affordability['max_pti_percentage'] ?? '')); ?>" placeholder="30.00" style="text-align: left;">
                                    <span class="text-muted" style="font-size: 14px;">%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Guardrails Section -->
                <div class="policy-decision-category-header" onclick="this.classList.toggle('is-open'); this.nextElementSibling.classList.toggle('is-open');">
                    <div class="policy-decision-category-header-title">
                        <svg style="width: 16px; height: 16px; fill: currentColor;" viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 2.18l7 3.12v4.7c0 4.67-3.13 8.94-7 10.02-3.87-1.08-7-5.35-7-10.02v-4.7l7-3.12zm-2 11.82l-3.5-3.5 1.41-1.41L10 12.17l6.59-6.59L18 7l-8 8z"/></svg>
                        Guardrails
                    </div>
                    <svg class="chevron-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </div>
                <div class="policy-decision-category-body">
                    <!-- // Score Thresholds: (App Flow) Lockout applied before loan application. UI shows "Not eligible due to low credit score" prior to formulation. -->
                    <div class="policy-decision-rule-item">
                        <?php echo renderToggleHeader('Score Thresholds', 'Reject and Hard Approval score limits.', 'pcdr_score_thresholds_enabled', $policy_console_guardrails['score_thresholds_enabled']); ?>
                        <div class="policy-decision-input-group toggle-group-pcdr_score_thresholds_enabled">
                            <div class="policy-decision-field policy-decision-field-col">
                                <span class="policy-decision-field-label">Auto-Reject Below</span>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <input type="number" class="form-control" name="pcdr_auto_reject_floor" value="<?php echo htmlspecialchars((string)($policy_console_guardrails['auto_reject_floor'] ?? '')); ?>" placeholder="300" style="text-align: left;" readonly>
                                    <span class="text-muted" style="font-size: 13px;">pts</span>
                                </div>
                            </div>
                            <div class="policy-decision-field policy-decision-field-col">
                                <span class="policy-decision-field-label">Auto-Approve Above</span>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <input type="number" class="form-control" name="pcdr_hard_approval_threshold" value="<?php echo htmlspecialchars((string)($policy_console_guardrails['hard_approval_threshold'] ?? '')); ?>" placeholder="700" style="text-align: left;" readonly>
                                    <span class="text-muted" style="font-size: 13px;">pts</span>
                                </div>
                            </div>                            
                            <div style="margin-top: 12px; background: rgba(59, 130, 246, 0.1); color: var(--text-muted); font-size: 13px; padding: 8px 12px; border-radius: 6px; display: flex; align-items: flex-start; gap: 8px; border: 1px solid rgba(59, 130, 246, 0.2); flex-basis: 100%;">
                                <span style="display: inline-flex; align-items: center; justify-content: center; width: 16px; height: 16px; border-radius: 50%; background: #3b82f6; color: #ffffff; font-size: 11px; font-weight: bold; line-height: 1; flex-shrink: 0; margin-top: 2px;">!</span>
                                <span style="color: var(--text-muted);">
                                    <strong>Score Limits Explained:</strong> Borrowers with a credit score below the <strong>Auto-Reject Below</strong> limit are restricted from applying for new loans. This ensures all applicants meet your baseline standard for creditworthiness.
                                </span>
                            </div>                        </div>
                    </div>

                    <!-- // Cooling Period: (App Flow) Blocks new loan submissions for X days after a rejection. (Note: Verification-specific cooling period not yet implemented). -->
                    <div class="policy-decision-rule-item" style="border-bottom: 1px solid var(--border-color);">
                        <?php echo renderToggleHeader('Cooling Period', 'Days required to wait after rejection.', 'pcdr_cooling_period_enabled', $policy_console_guardrails['cooling_period_enabled']); ?>
                        <div class="policy-decision-input-group toggle-group-pcdr_cooling_period_enabled">
                            <div class="policy-decision-field policy-decision-field-col">
                                <span class="policy-decision-field-label">Rejection Cooling Days</span>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <input type="number" class="form-control" name="pcdr_rejected_cooling_days" value="<?php echo htmlspecialchars((string)($policy_console_guardrails['rejected_cooling_days'] ?? '')); ?>" placeholder="30" style="text-align: left;">
                                    <span class="text-muted" style="font-size: 13px;">days</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Exposure Section -->
                <div class="policy-decision-category-header" onclick="this.classList.toggle('is-open'); this.nextElementSibling.classList.toggle('is-open');">
                    <div class="policy-decision-category-header-title">
                        <svg style="width: 16px; height: 16px; fill: currentColor;" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
                        Exposure
                    </div>
                    <svg class="chevron-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </div>
                <div class="policy-decision-category-body">
                    <!-- // Multiple Active Loans: (App Flow) If enabled, users can hold concurrent loans across products up to their total credit limit. If disabled, strictly 1 active/pending loan allowed. -->
                    <div class="policy-decision-rule-item">
                        <?php echo renderToggleHeader('Multiple Active Loans', 'Allow borrowers to have more than one active loan.', 'pcdr_multiple_active_loans_enabled', $policy_console_exposure['multiple_active_loans_enabled']); ?>
                        <div class="policy-decision-input-group toggle-group-pcdr_multiple_active_loans_enabled" style="display: block;">
                            <div style="margin-top: 12px; background: rgba(59, 130, 246, 0.1); color: var(--text-muted); font-size: 13px; padding: 8px 12px; border-radius: 6px; display: flex; align-items: flex-start; gap: 8px; border: 1px solid rgba(59, 130, 246, 0.2);">
                                <span style="display: inline-flex; align-items: center; justify-content: center; width: 16px; height: 16px; border-radius: 50%; background: #3b82f6; color: #ffffff; font-size: 11px; font-weight: bold; line-height: 1; flex-shrink: 0; margin-top: 2px;">!</span>
                                <span style="color: var(--text-muted);">
                                    <strong>Simultaneous Loans:</strong> If enabled, borrowers can have multiple loans open at the same time, provided the combined total amount doesn't exceed their approved <strong>Credit Limit</strong>. If disabled, borrowers are limited to <strong>only one active or pending loan</strong> at a time, and must completely pay it off before applying for another.
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- // Guarantor Required: (App Flow) Currently pending mobile implementation. Expected to mandate verified guarantor details for large loans. -->
                    <div class="policy-decision-rule-item" style="border-bottom: 1px solid var(--border-color);">
                        <?php echo renderToggleHeader('Guarantor Required', 'Require a guarantor for high-exposure loans.', 'pcdr_guarantor_required_enabled', $policy_console_exposure['guarantor_required_enabled']); ?>
                        <div class="policy-decision-input-group toggle-group-pcdr_guarantor_required_enabled">
                            <div class="policy-decision-field policy-decision-field-col">
                                <span class="policy-decision-field-label">Guarantor required with loans above:</span>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span class="text-muted" style="font-size: 14px;">₱</span>
                                    <input type="number" step="0.01" class="form-control" id="pcdr_guarantor_amount_input" name="pcdr_guarantor_required_above_amount" value="<?php echo htmlspecialchars((string)($policy_console_exposure['guarantor_required_above_amount'] ?? '')); ?>" placeholder="0.00" style="text-align: left;" <?php echo empty($policy_console_exposure['guarantor_required_enabled']) ? 'disabled' : ''; ?>>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- // Collateral Required: (App Flow) Currently pending mobile implementation. Expected to mandate collateral documentation uploads for large secured loans. -->
                    <div class="policy-decision-rule-item" style="border-bottom: 1px solid var(--border-color);">
                        <?php echo renderToggleHeader('Collateral Required', 'Require collateral documents for secured high-value loans.', 'pcdr_collateral_required_enabled', $policy_console_exposure['collateral_required_enabled']); ?>
                        <div class="policy-decision-input-group toggle-group-pcdr_collateral_required_enabled">
                            <div class="policy-decision-field policy-decision-field-col">
                                <span class="policy-decision-field-label">Collateral required with loans above:</span>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span class="text-muted" style="font-size: 14px;">₱</span>
                                    <input type="number" step="0.01" class="form-control" id="pcdr_collateral_amount_input" name="pcdr_collateral_required_above_amount" value="<?php echo htmlspecialchars((string)($policy_console_exposure['collateral_required_above_amount'] ?? '')); ?>" placeholder="0.00" style="text-align: left;" <?php echo empty($policy_console_exposure['collateral_required_enabled']) ? 'disabled' : ''; ?>>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <!-- New location for Save Action: Removed in favor of global saving action -->

    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('policy-console-decision-rules-form');
    if (!form) return;

    form.querySelectorAll('.policy-toggle-button').forEach(btn => {
        const toggleName = btn.getAttribute('data-policy-toggle-button');
        const targetGroup = form.querySelector(`.toggle-group-${toggleName}`);
        
        if (targetGroup) {
            const observer = new MutationObserver(() => {
                const isNowOff = !btn.classList.contains('is-on');
                targetGroup.classList.toggle('is-visually-disabled', isNowOff);
            });
            observer.observe(btn, { attributes: true, attributeFilter: ['class'] });

            if (!btn.classList.contains('is-on')) {
                targetGroup.classList.add('is-visually-disabled');
            }
        }
    });

    // Handle label clicks manually to prevent bubbling/stealing focus when inside .policy-pill-list rows if needed
});
</script>
