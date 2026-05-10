<?php

require_once __DIR__ . '/policy_console_system_defaults.php';

if (!function_exists('policy_console_decision_rules_setting_key')) {
    function policy_console_decision_rules_setting_key(): string
    {
        return 'policy_console_decision_rules';
    }
}

if (!function_exists('policy_console_decision_rules_approval_modes')) {
    function policy_console_decision_rules_approval_modes(): array
    {
        return ['automatic', 'semi_automatic', 'manual'];
    }
}

if (!function_exists('policy_console_decision_rules_defaults')) {
    function policy_console_decision_rules_defaults(int $scoreCeiling, array $ciOptions = []): array
    {
        return policy_console_decision_rules_normalize(
            policy_console_decision_rules_system_defaults(),
            $scoreCeiling,
            $ciOptions
        );
    }
}

if (!function_exists('policy_console_decision_rules_normalize')) {
    function policy_console_decision_rules_normalize($payload, int $scoreCeiling, array $ciOptions = []): array
    {
        $defaults = policy_console_decision_rules_system_defaults();
        $input = is_array($payload) ? array_replace_recursive($defaults, $payload) : $defaults;

        $normalizeToggle = static fn($value): bool => !empty($value) && !in_array($value, ['0', 0, false, 'false'], true);
        $normalizeScore = static function ($value, $fallback) use ($scoreCeiling): int {
            $score = is_numeric($value) ? (float)$value : (float)$fallback;
            return (int)round(min($scoreCeiling, max(0, $score)));
        };
        $normalizeInt = static function ($value, $fallback, int $min = 0, int $max = 1000): int {
            $number = is_numeric($value) ? (float)$value : (float)$fallback;
            return (int)round(min($max, max($min, $number)));
        };
        $normalizeDecimal = static function ($value, $fallback, float $min = 0.0, float $max = 999999999.0): float {
            $number = is_numeric($value) ? (float)$value : (float)$fallback;
            return round(min($max, max($min, $number)), 2);
        };
        $normalizeArray = static function ($value, $fallback): array {
            return is_array($value) ? array_values(array_map('trim', array_filter($value))) : $fallback;
        };

        // Hardcoded: The system inherently uses a "system suggests, staff approves" model for the MVP.
        $approvalMode = 'semi_automatic';

        $rulesInput = is_array($input['decision_rules'] ?? null) ? $input['decision_rules'] : [];
        $d_demo = is_array($rulesInput['demographics'] ?? null) ? $rulesInput['demographics'] : [];
        $d_afford = is_array($rulesInput['affordability'] ?? null) ? $rulesInput['affordability'] : [];
        $d_guard = is_array($rulesInput['guardrails'] ?? null) ? $rulesInput['guardrails'] : [];
        $d_expo = is_array($rulesInput['exposure'] ?? null) ? $rulesInput['exposure'] : [];

        $def = $defaults['decision_rules'];

        $autoRejectFloor = $normalizeScore(
            $d_guard['auto_reject_floor'] ?? null,
            $def['guardrails']['auto_reject_floor']
        );
        $hardApprovalThreshold = $normalizeScore(
            $d_guard['hard_approval_threshold'] ?? null,
            $def['guardrails']['hard_approval_threshold']
        );
        if ($hardApprovalThreshold < $autoRejectFloor) {
            $hardApprovalThreshold = $autoRejectFloor;
        }

        return [
            'workflow' => [
                'approval_mode' => $approvalMode,
            ],
            'decision_rules' => [
                'demographics' => [
                    'age_enabled' => $normalizeToggle($d_demo['age_enabled'] ?? $def['demographics']['age_enabled']),
                    'min_age' => $normalizeInt($d_demo['min_age'] ?? null, $def['demographics']['min_age'], 18, 100),
                    'max_age' => $normalizeInt($d_demo['max_age'] ?? null, $def['demographics']['max_age'], 18, 100),
                    'residency_tenure_enabled' => $normalizeToggle($d_demo['residency_tenure_enabled'] ?? $def['demographics']['residency_tenure_enabled']),
                    'min_residency_months' => $normalizeInt($d_demo['min_residency_months'] ?? null, $def['demographics']['min_residency_months'], 0, 1200),
                    'employment_status_enabled' => $normalizeToggle($d_demo['employment_status_enabled'] ?? $def['demographics']['employment_status_enabled']),
                    'eligible_statuses' => $normalizeArray($d_demo['eligible_statuses'] ?? null, $def['demographics']['eligible_statuses']),
                ],
                'affordability' => [
                    'income_enabled' => $normalizeToggle($d_afford['income_enabled'] ?? $def['affordability']['income_enabled']),
                    'min_monthly_income' => $normalizeDecimal($d_afford['min_monthly_income'] ?? null, $def['affordability']['min_monthly_income']),
                    'dti_enabled' => $normalizeToggle($d_afford['dti_enabled'] ?? $def['affordability']['dti_enabled']),
                    'max_dti_percentage' => $normalizeDecimal($d_afford['max_dti_percentage'] ?? null, $def['affordability']['max_dti_percentage'], 0, 100),
                    'pti_enabled' => $normalizeToggle($d_afford['pti_enabled'] ?? $def['affordability']['pti_enabled']),
                    'max_pti_percentage' => $normalizeDecimal($d_afford['max_pti_percentage'] ?? null, $def['affordability']['max_pti_percentage'], 0, 100),
                ],
                'guardrails' => [
                    'score_thresholds_enabled' => $normalizeToggle($d_guard['score_thresholds_enabled'] ?? $def['guardrails']['score_thresholds_enabled']),
                    'auto_reject_floor' => $autoRejectFloor,
                    'hard_approval_threshold' => $hardApprovalThreshold,
                    'cooling_period_enabled' => $normalizeToggle($d_guard['cooling_period_enabled'] ?? $def['guardrails']['cooling_period_enabled']),
                    'rejected_cooling_days' => $normalizeInt($d_guard['rejected_cooling_days'] ?? null, $def['guardrails']['rejected_cooling_days'], 0, 3650),
                ],
                'exposure' => [
                    'multiple_active_loans_enabled' => $normalizeToggle($d_expo['multiple_active_loans_enabled'] ?? $def['exposure']['multiple_active_loans_enabled']),
                    'guarantor_required_enabled' => $normalizeToggle($d_expo['guarantor_required_enabled'] ?? $def['exposure']['guarantor_required_enabled']),
                    'guarantor_required_above_amount' => (isset($d_expo['guarantor_required_above_amount']) && $d_expo['guarantor_required_above_amount'] !== '') ? $normalizeDecimal($d_expo['guarantor_required_above_amount'], $def['exposure']['guarantor_required_above_amount']) : null,
                    'collateral_required_enabled' => $normalizeToggle($d_expo['collateral_required_enabled'] ?? $def['exposure']['collateral_required_enabled']),
                    'collateral_required_above_amount' => (isset($d_expo['collateral_required_above_amount']) && $d_expo['collateral_required_above_amount'] !== '') ? $normalizeDecimal($d_expo['collateral_required_above_amount'], $def['exposure']['collateral_required_above_amount'] ?? 0.0) : null,
                ],
                // Restored Legacy Keys for Onboarding & Credit Score Computation logic
                'score_thresholds' => [
                    'auto_reject_floor' => $autoRejectFloor,
                    'hard_approval_threshold' => $hardApprovalThreshold,
                ],
                'loan_capital' => [
                    'minimum_capital_requirement' => $normalizeDecimal($d_afford['min_monthly_income'] ?? null, 10000),
                ],
                'borrowing_access_rules' => [
                    'allow_multiple_active_loans_within_remaining_limit' => $normalizeToggle($d_expo['multiple_active_loans_enabled'] ?? true),
                ],
                'demographic_guardrails' => [
                    'min_age' => $normalizeInt($d_demo['min_age'] ?? null, 21, 18, 100),
                    'max_age' => $normalizeInt($d_demo['max_age'] ?? null, 65, 18, 100),
                ],
            ],
        ];
    }
}

if (!function_exists('policy_console_decision_rules_legacy_borrower_safeguards_load')) {
    function policy_console_decision_rules_legacy_borrower_safeguards_load(PDO $pdo, string $tenantId): array
    {
        $raw = admin_get_system_setting($pdo, $tenantId, 'policy_console_collections_safeguards', '');
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        $legacySafeguards = is_array($decoded['borrower_safeguards'] ?? null)
            ? $decoded['borrower_safeguards']
            : [];
        if ($legacySafeguards === []) {
            return [];
        }

        $guarantorAmount = is_numeric($legacySafeguards['guarantor_required_above_amount'] ?? null)
            ? round(max(0, (float)$legacySafeguards['guarantor_required_above_amount']), 2)
            : 0.0;
        $collateralAmount = is_numeric($legacySafeguards['collateral_required_above_amount'] ?? null)
            ? round(max(0, (float)$legacySafeguards['collateral_required_above_amount']), 2)
            : 0.0;

        return [
            'enabled' => ($guarantorAmount > 0 || $collateralAmount > 0),
            'guarantor_required_above_amount' => $guarantorAmount,
            'collateral_enabled' => ($collateralAmount > 0),
            'risk_based_security_requirements' => '',
        ];
    }
}

if (!function_exists('policy_console_decision_rules_load')) {
    function policy_console_decision_rules_load(PDO $pdo, string $tenantId, int $scoreCeiling, array $ciOptions = []): array
    {
        $legacyBorrowerSafeguards = policy_console_decision_rules_legacy_borrower_safeguards_load($pdo, $tenantId);
        $raw = admin_get_system_setting($pdo, $tenantId, policy_console_decision_rules_setting_key(), '');
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                if ($legacyBorrowerSafeguards !== []) {
                    $decoded['_legacy_borrower_safeguards'] = $legacyBorrowerSafeguards;
                }
                return policy_console_decision_rules_normalize($decoded, $scoreCeiling, $ciOptions);
            }
        }

        if ($legacyBorrowerSafeguards !== []) {
            return policy_console_decision_rules_normalize(
                ['_legacy_borrower_safeguards' => $legacyBorrowerSafeguards],
                $scoreCeiling,
                $ciOptions
            );
        }

        return policy_console_decision_rules_defaults($scoreCeiling, $ciOptions);
    }
}

if (!function_exists('policy_console_decision_rules_build_from_post')) {
    function policy_console_decision_rules_build_from_post(array $source, int $scoreCeiling, array $ciOptions = []): array
    {
        $payload = [
            'workflow' => [
                'approval_mode' => 'semi_automatic',
            ],
            'decision_rules' => [
                'demographics' => [
                    'age_enabled' => $source['pcdr_age_enabled'] ?? null,
                    'min_age' => $source['pcdr_min_age'] ?? null,
                    'max_age' => $source['pcdr_max_age'] ?? null,
                    'residency_tenure_enabled' => $source['pcdr_residency_tenure_enabled'] ?? null,
                    'min_residency_months' => $source['pcdr_min_residency_months'] ?? null,
                    'employment_status_enabled' => $source['pcdr_employment_status_enabled'] ?? null,
                    'eligible_statuses' => $source['pcdr_eligible_statuses'] ?? null,
                ],
                'affordability' => [
                    'income_enabled' => $source['pcdr_income_enabled'] ?? null,
                    'min_monthly_income' => $source['pcdr_min_monthly_income'] ?? null,
                    'dti_enabled' => $source['pcdr_dti_enabled'] ?? null,
                    'max_dti_percentage' => $source['pcdr_max_dti_percentage'] ?? null,
                    'pti_enabled' => $source['pcdr_pti_enabled'] ?? null,
                    'max_pti_percentage' => $source['pcdr_max_pti_percentage'] ?? null,
                ],
                'guardrails' => [
                    'score_thresholds_enabled' => $source['pcdr_score_thresholds_enabled'] ?? null,
                    'auto_reject_floor' => $source['pcdr_auto_reject_floor'] ?? null,
                    'hard_approval_threshold' => $source['pcdr_hard_approval_threshold'] ?? null,
                    'cooling_period_enabled' => $source['pcdr_cooling_period_enabled'] ?? null,
                    'rejected_cooling_days' => $source['pcdr_rejected_cooling_days'] ?? null,
                ],
                'exposure' => [
                    'multiple_active_loans_enabled' => $source['pcdr_multiple_active_loans_enabled'] ?? null,
                    'guarantor_required_enabled' => $source['pcdr_guarantor_required_enabled'] ?? null,
                    'guarantor_required_above_amount' => $source['pcdr_guarantor_required_above_amount'] ?? null,
                    'collateral_required_enabled' => $source['pcdr_collateral_required_enabled'] ?? null,
                    'collateral_required_above_amount' => $source['pcdr_collateral_required_above_amount'] ?? null,
                ],
            ],
        ];

        return policy_console_decision_rules_normalize($payload, $scoreCeiling, $ciOptions);
    }
}
