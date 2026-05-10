<?php

require_once __DIR__ . '/policy_console_credit_limits.php';
require_once __DIR__ . '/policy_console_decision_rules.php';
require_once __DIR__ . '/policy_console_compliance_documents.php';

if (!function_exists('credit_limit_rule_defaults')) {
    function credit_limit_rule_defaults(): array
    {
        return [
            'workflow' => [
                'approval_mode' => 'semi',
            ],
            'initial_limits' => [
                'base_limit_default' => 5000,
                'custom_categories' => [],
            ],
            'upgrade_eligibility' => [
                'min_completed_loans' => 2,
                'max_allowed_late_payments' => 0,
            ],
            'increase_rules' => [
                'increase_type' => 'percentage',
                'increase_value' => 20,
                'absolute_max_limit' => 50000,
            ],
        ];
    }
}

if (!function_exists('normalize_credit_limit_rules')) {
    function normalize_credit_limit_rules($payload): array
    {
        $defaults = credit_limit_rule_defaults();
        $rules = is_array($payload) ? array_replace_recursive($defaults, $payload) : $defaults;

        $base_limit_default = max(0, (float)($rules['initial_limits']['base_limit_default'] ?? $defaults['initial_limits']['base_limit_default']));
        $base_limit_default = round($base_limit_default, 2);

        $custom_categories = [];
        if (!empty($rules['initial_limits']['custom_categories']) && is_array($rules['initial_limits']['custom_categories'])) {
            foreach ($rules['initial_limits']['custom_categories'] as $categoryRule) {
                if (!is_array($categoryRule)) {
                    continue;
                }

                $category_name = trim((string)($categoryRule['category_name'] ?? ''));
                $limit_type = (string)($categoryRule['limit_type'] ?? 'fixed');
                $value = round(max(0, (float)($categoryRule['value'] ?? 0)), 2);

                if ($category_name === '') {
                    continue;
                }
                if ($limit_type === 'multiplier') {
                    $value = round($base_limit_default * $value, 2);
                    $limit_type = 'fixed';
                }
                if (!in_array($limit_type, ['fixed', 'income_percent'], true)) {
                    $limit_type = 'fixed';
                }

                $custom_categories[] = [
                    'category_name' => substr($category_name, 0, 80),
                    'limit_type' => $limit_type,
                    'value' => $value,
                ];
            }
        }

        $min_completed_loans = max(0, (int)($rules['upgrade_eligibility']['min_completed_loans'] ?? $defaults['upgrade_eligibility']['min_completed_loans']));
        $max_allowed_late_payments = max(0, (int)($rules['upgrade_eligibility']['max_allowed_late_payments'] ?? $defaults['upgrade_eligibility']['max_allowed_late_payments']));

        $increase_type = (string)($rules['increase_rules']['increase_type'] ?? $defaults['increase_rules']['increase_type']);
        if (!in_array($increase_type, ['percentage', 'fixed'], true)) {
            $increase_type = $defaults['increase_rules']['increase_type'];
        }

        $increase_value = round(max(0, (float)($rules['increase_rules']['increase_value'] ?? $defaults['increase_rules']['increase_value'])), 2);
        $absolute_max_limit = round(max(0, (float)($rules['increase_rules']['absolute_max_limit'] ?? $defaults['increase_rules']['absolute_max_limit'])), 2);

        return [
            'workflow' => [
                // Upgrade processing is fixed to semi-auto so staff always confirms increases.
                'approval_mode' => 'semi',
            ],
            'initial_limits' => [
                'base_limit_default' => $base_limit_default,
                'custom_categories' => $custom_categories,
            ],
            'upgrade_eligibility' => [
                'min_completed_loans' => $min_completed_loans,
                'max_allowed_late_payments' => $max_allowed_late_payments,
            ],
            'increase_rules' => [
                'increase_type' => $increase_type,
                'increase_value' => $increase_value,
                'absolute_max_limit' => $absolute_max_limit,
            ],
        ];
    }
}

if (!function_exists('build_credit_limit_rules_from_post')) {
    function build_credit_limit_rules_from_post(array $source): array
    {
        $defaults = credit_limit_rule_defaults();

        $rules = [
            'workflow' => [
                'approval_mode' => 'semi',
            ],
            'initial_limits' => [
                'base_limit_default' => (float)($source['credit_base_limit'] ?? $defaults['initial_limits']['base_limit_default']),
                'custom_categories' => [],
            ],
            'upgrade_eligibility' => [
                'min_completed_loans' => (int)($source['credit_min_completed_loans'] ?? $defaults['upgrade_eligibility']['min_completed_loans']),
                'max_allowed_late_payments' => (int)($source['credit_max_late_payments'] ?? $defaults['upgrade_eligibility']['max_allowed_late_payments']),
            ],
            'increase_rules' => [
                'increase_type' => (string)($source['credit_increase_type'] ?? $defaults['increase_rules']['increase_type']),
                'increase_value' => (float)($source['credit_increase_value'] ?? $defaults['increase_rules']['increase_value']),
                'absolute_max_limit' => (float)($source['credit_absolute_max_limit'] ?? $defaults['increase_rules']['absolute_max_limit']),
            ],
        ];

        $selectedCategories = isset($source['credit_category_select']) && is_array($source['credit_category_select'])
            ? $source['credit_category_select']
            : [];
        $customCategories = isset($source['credit_category_custom']) && is_array($source['credit_category_custom'])
            ? $source['credit_category_custom']
            : [];
        $limitTypes = isset($source['credit_category_type']) && is_array($source['credit_category_type'])
            ? $source['credit_category_type']
            : [];
        $limitValues = isset($source['credit_category_value']) && is_array($source['credit_category_value'])
            ? $source['credit_category_value']
            : [];

        $rowCount = max(count($selectedCategories), count($customCategories), count($limitTypes), count($limitValues));
        for ($index = 0; $index < $rowCount; $index++) {
            $selectedCategory = trim((string)($selectedCategories[$index] ?? ''));
            $categoryName = $selectedCategory === 'Others'
                ? trim((string)($customCategories[$index] ?? ''))
                : $selectedCategory;

            if ($categoryName === '') {
                continue;
            }

            $rules['initial_limits']['custom_categories'][] = [
                'category_name' => $categoryName,
                'limit_type' => (string)($limitTypes[$index] ?? 'fixed'),
                'value' => (float)($limitValues[$index] ?? 0),
            ];
        }

        return $rules;
    }
}

if (!function_exists('admin_build_credit_policy_workspace_state')) {
    function admin_build_credit_policy_workspace_state(PDO $pdo, string $tenantId): array
    {
        $credit_policy = mf_get_tenant_credit_policy($pdo, $tenantId);
        $credit_limit_rules = mf_get_tenant_credit_limit_rules($pdo, $tenantId);
        $credit_policy_defaults = mf_credit_policy_defaults();
        $credit_policy_score_ceiling = mf_credit_policy_score_ceiling();
        $credit_policy_employment_options = mf_credit_policy_employment_options();
        $credit_policy_ci_options = mf_credit_policy_ci_recommendation_options();
        $credit_policy_ci_configurable_options = array_values(array_filter(
            $credit_policy_ci_options,
            static fn($option) => $option !== 'Not Recommended'
        ));

        $credit_policy_not_recommended_from = (int)($credit_policy['score_thresholds']['not_recommended_min_score'] ?? 200);
        $credit_policy_conditional_from = (int)($credit_policy['score_thresholds']['conditional_min_score'] ?? ($credit_policy['decision_routing']['manual_review_from_score'] ?? 400));
        $credit_policy_recommended_from = (int)($credit_policy['score_thresholds']['recommended_min_score'] ?? ($credit_policy['decision_routing']['approval_candidate_from_score'] ?? 600));
        $credit_policy_highly_recommended_from = (int)($credit_policy['score_thresholds']['highly_recommended_min_score'] ?? 800);

        $credit_policy_at_risk_end = max(0, $credit_policy_not_recommended_from - 1);
        $credit_policy_not_recommended_end = max($credit_policy_not_recommended_from, $credit_policy_conditional_from - 1);
        $credit_policy_conditional_end = max($credit_policy_conditional_from, $credit_policy_recommended_from - 1);
        $credit_policy_recommended_end = max($credit_policy_recommended_from, $credit_policy_highly_recommended_from - 1);

        $credit_policy_reject_below = $credit_policy_conditional_from;
        $credit_policy_review_band_end = $credit_policy_conditional_end;
        $credit_policy_approve_from = $credit_policy_recommended_from;

        $credit_policy_auto_ci_values = array_values(array_filter(
            $credit_policy_ci_configurable_options,
            static fn($option) => in_array($option, (array)($credit_policy['ci_rules']['auto_approve_ci_values'] ?? []), true)
        ));

        $credit_policy_review_ci_values = array_values(array_filter(
            $credit_policy_ci_configurable_options,
            static fn($option) => in_array($option, (array)($credit_policy['ci_rules']['review_ci_values'] ?? []), true)
        ));

        $credit_policy_allowed_employment_values = (array)($credit_policy['eligibility']['allowed_employment_statuses'] ?? []);
        $credit_policy_allowed_employment_count = count($credit_policy_allowed_employment_values);
        $credit_policy_auto_ci_count = count($credit_policy_auto_ci_values);
        $credit_policy_review_ci_count = count($credit_policy_review_ci_values);
        $credit_policy_product_checks_enabled = (int)!empty($credit_policy['product_checks']['use_product_minimum_credit_score'])
            + (int)!empty($credit_policy['product_checks']['use_product_min_amount'])
            + (int)!empty($credit_policy['product_checks']['use_product_max_amount']);
        $credit_policy_ci_mode_label = !empty($credit_policy['ci_rules']['require_ci'])
            ? 'Always required'
            : 'Optional';
        $credit_policy_defaults_json = htmlspecialchars(
            (string)json_encode($credit_policy_defaults, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ENT_QUOTES,
            'UTF-8'
        );
        $credit_limit_rules_json = (string)json_encode($credit_limit_rules, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $policy_console_credit_limits = policy_console_credit_limits_load(
            $pdo,
            $tenantId,
            $credit_policy,
            $credit_limit_rules,
            $credit_policy_score_ceiling
        );
        $policy_console_decision_rules = policy_console_decision_rules_load(
            $pdo,
            $tenantId,
            $credit_policy_score_ceiling,
            $credit_policy_ci_configurable_options
        );
        $policy_console_compliance_documents = policy_console_compliance_documents_load($pdo, $tenantId);

        return [
            'credit_policy' => $credit_policy,
            'credit_limit_rules' => $credit_limit_rules,
            'credit_limit_rules_json' => $credit_limit_rules_json,
            'credit_policy_defaults' => $credit_policy_defaults,
            'credit_policy_score_ceiling' => $credit_policy_score_ceiling,
            'credit_policy_employment_options' => $credit_policy_employment_options,
            'credit_policy_ci_options' => $credit_policy_ci_options,
            'credit_policy_ci_configurable_options' => $credit_policy_ci_configurable_options,
            'credit_policy_not_recommended_from' => $credit_policy_not_recommended_from,
            'credit_policy_conditional_from' => $credit_policy_conditional_from,
            'credit_policy_recommended_from' => $credit_policy_recommended_from,
            'credit_policy_highly_recommended_from' => $credit_policy_highly_recommended_from,
            'credit_policy_at_risk_end' => $credit_policy_at_risk_end,
            'credit_policy_not_recommended_end' => $credit_policy_not_recommended_end,
            'credit_policy_conditional_end' => $credit_policy_conditional_end,
            'credit_policy_recommended_end' => $credit_policy_recommended_end,
            'credit_policy_reject_below' => $credit_policy_reject_below,
            'credit_policy_review_band_end' => $credit_policy_review_band_end,
            'credit_policy_approve_from' => $credit_policy_approve_from,
            'credit_policy_auto_ci_values' => $credit_policy_auto_ci_values,
            'credit_policy_review_ci_values' => $credit_policy_review_ci_values,
            'credit_policy_allowed_employment_values' => $credit_policy_allowed_employment_values,
            'credit_policy_allowed_employment_count' => $credit_policy_allowed_employment_count,
            'credit_policy_auto_ci_count' => $credit_policy_auto_ci_count,
            'credit_policy_review_ci_count' => $credit_policy_review_ci_count,
            'credit_policy_product_checks_enabled' => $credit_policy_product_checks_enabled,
            'credit_policy_ci_mode_label' => $credit_policy_ci_mode_label,
            'credit_policy_defaults_json' => $credit_policy_defaults_json,
            'policy_console_credit_limits' => $policy_console_credit_limits,
            'policy_console_scoring_setup' => $policy_console_credit_limits['scoring_setup'] ?? [],
            'policy_console_core_setup' => $policy_console_credit_limits['scoring_setup']['core'] ?? [],
            'policy_console_upgrade_rules' => $policy_console_credit_limits['scoring_setup']['detailed_rules']['upgrade'] ?? [],
            'policy_console_downgrade_rules' => $policy_console_credit_limits['scoring_setup']['detailed_rules']['downgrade'] ?? [],
            'policy_console_limit_assignment' => $policy_console_credit_limits['limit_assignment'] ?? [],
            'policy_console_decision_rules' => $policy_console_decision_rules,
            'policy_console_compliance_documents' => $policy_console_compliance_documents,
        ];
    }
}
