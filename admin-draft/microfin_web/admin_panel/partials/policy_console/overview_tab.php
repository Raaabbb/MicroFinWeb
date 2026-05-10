<?php
$policy_console_credit_limits_overview = isset($policy_console_credit_limits) && is_array($policy_console_credit_limits)
    ? $policy_console_credit_limits
    : policy_console_credit_limits_system_defaults();
$policy_console_decision_overview = isset($policy_console_decision_rules) && is_array($policy_console_decision_rules)
    ? $policy_console_decision_rules
    : policy_console_decision_rules_system_defaults();
$policy_console_compliance_overview = isset($policy_console_compliance_documents) && is_array($policy_console_compliance_documents)
    ? $policy_console_compliance_documents
    : policy_console_compliance_documents_system_defaults();

$policy_console_overview_workflow_labels = [
    'automatic' => 'Automatic',
    'semi_automatic' => 'Semi-automatic',
    'manual' => 'Manual',
];

$policy_console_required_categories = count(array_filter(
    (array)($policy_console_compliance_overview['document_requirements'] ?? []),
    static fn(array $row): bool => ($row['requirement'] ?? 'not_needed') === 'required'
));

$policy_console_overview_score_bands = array_values(array_filter(
    (array)($policy_console_credit_limits_overview['score_bands']['rows'] ?? []),
    static fn($row): bool => is_array($row)
));
usort(
    $policy_console_overview_score_bands,
    static fn(array $left, array $right): int => ((int)($left['min_score'] ?? 0)) <=> ((int)($right['min_score'] ?? 0))
);

$policy_console_score_min = !empty($policy_console_overview_score_bands)
    ? (int)($policy_console_overview_score_bands[0]['min_score'] ?? 0)
    : 0;
$policy_console_finite_score_max = $policy_console_score_min;
$policy_console_open_band_min = null;
foreach ($policy_console_overview_score_bands as $policy_console_band_row) {
    $policy_console_band_min_score = (int)($policy_console_band_row['min_score'] ?? 0);
    $policy_console_band_max_score = $policy_console_band_row['max_score'] ?? null;
    if ($policy_console_band_max_score === null || $policy_console_band_max_score === '') {
        $policy_console_open_band_min = $policy_console_open_band_min === null
            ? $policy_console_band_min_score
            : max($policy_console_open_band_min, $policy_console_band_min_score);
        continue;
    }

    $policy_console_finite_score_max = max($policy_console_finite_score_max, (int)$policy_console_band_max_score);
}
$policy_console_score_max = $policy_console_open_band_min !== null
    ? max($policy_console_finite_score_max, $policy_console_open_band_min + 350)
    : $policy_console_finite_score_max;
$policy_console_score_max = max($policy_console_score_min, $policy_console_score_max);
$policy_console_starting_score = (int)($policy_console_credit_limits_overview['scoring_setup']['core']['starting_credit_score'] ?? $policy_console_score_min);
$policy_console_starting_score = max($policy_console_score_min, min($policy_console_score_max, $policy_console_starting_score));

$policy_console_limit_assignment = is_array($policy_console_credit_limits_overview['limit_assignment'] ?? null)
    ? $policy_console_credit_limits_overview['limit_assignment']
    : [];
$policy_console_initial_limit_percent = max(0, (float)($policy_console_limit_assignment['initial_limit_percent_of_income'] ?? 45));
$policy_console_limit_cap = !empty($policy_console_limit_assignment['use_default_lending_cap'])
    ? max(0, (float)($policy_console_limit_assignment['default_lending_cap_amount'] ?? 0))
    : 0.0;
$policy_console_limit_basis_min = 1000.0;
$policy_console_limit_basis_max = $policy_console_limit_cap > 0
    ? max($policy_console_limit_basis_min, $policy_console_limit_cap)
    : 150000.0;
$policy_console_limit_basis_default = $policy_console_limit_cap > 0
    ? min($policy_console_limit_basis_max, max($policy_console_limit_basis_min, round($policy_console_limit_cap * 0.25, -2)))
    : 25000.0;
$policy_console_limit_basis_step = $policy_console_limit_basis_max <= 50000 ? 500 : 1000;

$policy_console_find_band = static function (array $bands, int $score): array {
    if ($bands === []) {
        return [
            'label' => 'Unassigned',
            'min_score' => 0,
            'max_score' => null,
            'base_growth_percent' => 0,
            'micro_percent_per_point' => 0,
        ];
    }

    foreach ($bands as $band) {
        $minScore = (int)($band['min_score'] ?? 0);
        $maxScore = $band['max_score'] ?? null;
        if ($maxScore === null || $maxScore === '') {
            if ($score >= $minScore) {
                return $band;
            }
            continue;
        }
        if ($score >= $minScore && $score <= (int)$maxScore) {
            return $band;
        }
    }

    if ($score < (int)($bands[0]['min_score'] ?? 0)) {
        return $bands[0];
    }

    return $bands[count($bands) - 1];
};

$policy_console_simulated_band = $policy_console_find_band($policy_console_overview_score_bands, $policy_console_starting_score);
$policy_console_band_min = (int)($policy_console_simulated_band['min_score'] ?? 0);
$policy_console_growth_percent = max(
    0,
    (float)($policy_console_simulated_band['base_growth_percent'] ?? 0)
    + (max(0, $policy_console_starting_score - $policy_console_band_min) * (float)($policy_console_simulated_band['micro_percent_per_point'] ?? 0))
);
$policy_console_first_time_limit = $policy_console_limit_basis_default * ($policy_console_initial_limit_percent / 100);
$policy_console_first_time_limit_clamped = false;
if ($policy_console_limit_cap > 0 && $policy_console_first_time_limit > $policy_console_limit_cap) {
    $policy_console_first_time_limit = $policy_console_limit_cap;
    $policy_console_first_time_limit_clamped = true;
}
$policy_console_projected_limit = $policy_console_limit_basis_default * (1 + ($policy_console_growth_percent / 100));
$policy_console_limit_clamped = false;
if ($policy_console_limit_cap > 0 && $policy_console_projected_limit > $policy_console_limit_cap) {
    $policy_console_projected_limit = $policy_console_limit_cap;
    $policy_console_limit_clamped = true;
}

$policy_console_simulator_config = htmlspecialchars((string)json_encode([
    'scoreBands' => array_values(array_map(static function (array $row): array {
        return [
            'label' => (string)($row['label'] ?? 'Band'),
            'min_score' => (int)($row['min_score'] ?? 0),
            'max_score' => ($row['max_score'] === null || $row['max_score'] === '') ? null : (int)$row['max_score'],
            'base_growth_percent' => (float)($row['base_growth_percent'] ?? 0),
            'micro_percent_per_point' => (float)($row['micro_percent_per_point'] ?? 0),
        ];
    }, $policy_console_overview_score_bands)),
    'limitCap' => $policy_console_limit_cap,
    'initialLimitPercent' => $policy_console_initial_limit_percent,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
?>
<div class="policy-compact-stack">
    <section class="policy-compact-card">
        <div class="policy-compact-card-head">
            <div class="policy-compact-card-title">
                <h3>Overview</h3>
                <p class="text-muted">This page is read-only. It summarizes the tenant configs already saved by the active Policy Console workspaces.</p>
            </div>
        </div>

        <div class="policy-metric-grid">
            <div class="policy-metric-card">
                <span>Starting Credit Score</span>
                <strong><?php echo number_format((int)($policy_console_credit_limits_overview['scoring_setup']['core']['starting_credit_score'] ?? 0)); ?></strong>
                <small>Current starting-score fallback in Credit &amp; Limits.</small>
            </div>
            <div class="policy-metric-card">
                <span>Decision Workflow</span>
                <strong><?php echo htmlspecialchars($policy_console_overview_workflow_labels[$policy_console_decision_overview['workflow']['approval_mode'] ?? 'semi_automatic'] ?? 'Semi-automatic'); ?></strong>
                <small>Current approval routing posture.</small>
            </div>
            <div class="policy-metric-card">
                <span>Guarantor Trigger</span>
                <strong><?php 
                    if (!empty($policy_console_decision_overview['decision_rules']['exposure']['guarantor_required_enabled'])) {
                        echo 'PHP ' . number_format((float)($policy_console_decision_overview['decision_rules']['exposure']['guarantor_required_above_amount'] ?? 0), 2);
                    } else {
                        echo 'Disabled';
                    }
                ?></strong>
                <small>Borrower safeguard trigger inside Rules &amp; Requirements.</small>
            </div>
        </div>
    </section>

    <section class="policy-compact-card">
        <div class="policy-compact-card-head">
            <div class="policy-compact-card-title">
                <h3>Credit Playground</h3>
                <p class="text-muted">Simulator only. Move the sliders to preview how the current score-band and growth setup affects a sample credit limit.</p>
            </div>
            <span class="policy-console-chip">Simulator</span>
        </div>

        <div
            class="policy-playground-shell"
            data-policy-console-overview-simulator
            data-simulator-config="<?php echo $policy_console_simulator_config; ?>"
        >
            <div class="policy-playground-controls">
                <label class="policy-playground-control">
                    <div class="policy-playground-control-head">
                        <span>Credit Score</span>
                        <strong data-simulator-score-output><?php echo number_format($policy_console_starting_score); ?></strong>
                    </div>
                    <input
                        type="range"
                        min="<?php echo $policy_console_score_min; ?>"
                        max="<?php echo $policy_console_score_max; ?>"
                        step="1"
                        value="<?php echo $policy_console_starting_score; ?>"
                        data-simulator-score-input
                    >
                    <small>Uses the tenantâ€™s saved score bands to determine the current band level.</small>
                </label>

                <label class="policy-playground-control">
                    <div class="policy-playground-control-head">
                        <span>Monthly Income</span>
                        <strong data-simulator-limit-basis-output>PHP <?php echo number_format($policy_console_limit_basis_default, 2); ?></strong>
                    </div>
                    <input
                        type="range"
                        min="<?php echo (int)$policy_console_limit_basis_min; ?>"
                        max="<?php echo (int)$policy_console_limit_basis_max; ?>"
                        step="<?php echo (int)$policy_console_limit_basis_step; ?>"
                        value="<?php echo (int)$policy_console_limit_basis_default; ?>"
                        data-simulator-limit-input
                    >
                    <small>Used as the sample onboarding income for first-time clients and the playground basis for projected limit behavior.</small>
                </label>
            </div>

            <div class="policy-playground-result-stack">
                <div class="policy-playground-result-card">
                    <span class="policy-playground-result-kicker">Projected Limit</span>
                    <strong class="policy-playground-result-amount" data-simulator-projected-limit>
                        PHP <?php echo number_format($policy_console_projected_limit, 2); ?>
                    </strong>
                    <div class="policy-playground-result-meta">
                        <div>
                            <span>Band Level</span>
                            <strong data-simulator-band-output><?php echo htmlspecialchars((string)($policy_console_simulated_band['label'] ?? 'Unassigned')); ?></strong>
                        </div>
                        <div>
                            <span>Credit Score</span>
                            <strong data-simulator-score-card-output><?php echo number_format($policy_console_starting_score); ?></strong>
                        </div>
                    </div>
                    <p class="policy-playground-result-note" data-simulator-note-output>
                        <?php if ($policy_console_limit_clamped): ?>
                            Projected limit is capped at PHP <?php echo number_format($policy_console_limit_cap, 2); ?> by the current tenant guard.
                        <?php else: ?>
                            Projected using <?php echo number_format($policy_console_growth_percent, 2); ?>% growth from the selected basis amount.
                        <?php endif; ?>
                    </p>
                </div>

                <div class="policy-playground-secondary-card">
                    <span class="policy-playground-result-kicker">First-Time Client</span>
                    <strong class="policy-playground-secondary-amount" data-simulator-first-time-limit>
                        PHP <?php echo number_format($policy_console_first_time_limit, 2); ?>
                    </strong>
                    <p class="policy-playground-result-note" data-simulator-first-time-note-output>
                        <?php if ($policy_console_first_time_limit_clamped): ?>
                            Onboarding limit is capped at PHP <?php echo number_format($policy_console_limit_cap, 2); ?> by the current tenant guard.
                        <?php else: ?>
                            Onboarding limit uses <?php echo number_format($policy_console_initial_limit_percent, 2); ?>% of the selected monthly income.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section class="policy-compact-card">
        <div class="policy-console-module-grid">
            <div class="policy-console-module-card">
                <div class="policy-console-card-head">
                    <strong>Credit &amp; Limits</strong>
                    <span class="policy-console-chip"><?php echo number_format(count((array)($policy_console_credit_limits_overview['score_bands']['rows'] ?? []))); ?> bands</span>
                </div>
                <p class="text-muted">Scoring setup, lifecycle rules, score band matrix, and limit assignment defaults.</p>
                <button type="button" class="btn btn-outline" data-credit-policy-nav-action="credit_limits">Open Credit &amp; Limits</button>
            </div>
            <div class="policy-console-module-card">
                <div class="policy-console-card-head">
                    <strong>Rules &amp; Requirements</strong>
                    <span class="policy-console-chip"><?php echo number_format((int)($policy_console_decision_overview['decision_rules']['score_thresholds']['hard_approval_threshold'] ?? 0)); ?>+</span>
                </div>
                <p class="text-muted">Workflow mode, score guardrails, CI expectations, access rules, review overrides, and borrower safeguards.</p>
                <button type="button" class="btn btn-outline" data-credit-policy-nav-action="decision_rules">Open Rules &amp; Requirements</button>
            </div>
            <div class="policy-console-module-card">
                <div class="policy-console-card-head">
                    <strong>Required Documents</strong>
                    <span class="policy-console-chip"><?php echo number_format($policy_console_required_categories); ?> required</span>
                </div>
                <p class="text-muted">Document validity defaults and the Governance Matrix saved by document name.</p>
                <button type="button" class="btn btn-outline" data-credit-policy-nav-action="compliance_documents">Open Required Documents</button>
            </div>
        </div>
    </section>
</div>
