<?php

require_once __DIR__ . '/policy_console_system_defaults.php';

if (!function_exists('policy_console_limit_assignment_defaults')) {
    function policy_console_limit_assignment_defaults(): array
    {
        $defaults = policy_console_credit_limits_system_defaults();
        $limitAssignment = $defaults['limit_assignment'] ?? [];

        return [
            'initial_limit_percent_of_income' => (float)($limitAssignment['initial_limit_percent_of_income'] ?? 40),
            'use_default_lending_cap' => !empty($limitAssignment['use_default_lending_cap']),
            'default_lending_cap_amount' => (float)($limitAssignment['default_lending_cap_amount'] ?? 0),
            'apply_score_changes_immediately' => array_key_exists('apply_score_changes_immediately', $limitAssignment)
                ? !empty($limitAssignment['apply_score_changes_immediately'])
                : true,
        ];
    }
}

if (!function_exists('policy_console_limit_assignment_from_live')) {
    function policy_console_limit_assignment_from_live(array $creditPolicy = [], array $creditLimitRules = []): array
    {
        return policy_console_limit_assignment_defaults();
    }
}

if (!function_exists('policy_console_limit_assignment_normalize')) {
    function policy_console_limit_assignment_normalize($payload, ?array $fallback = null): array
    {
        $defaults = $fallback && is_array($fallback)
            ? array_replace(policy_console_limit_assignment_defaults(), $fallback)
            : policy_console_limit_assignment_defaults();
        $input = is_array($payload) ? array_replace($defaults, $payload) : $defaults;

        $initialLimitPercent = $input['initial_limit_percent_of_income'] ?? $input['new_borrower_utilization_percent'] ?? $defaults['initial_limit_percent_of_income'];
        $useDefaultLendingCap = array_key_exists('use_default_lending_cap', $input)
            ? !empty($input['use_default_lending_cap'])
            : (!empty($input['max_absolute_exposure']) && (float)$input['max_absolute_exposure'] > 0);
        $defaultLendingCapAmount = $input['default_lending_cap_amount'] ?? $input['max_absolute_exposure'] ?? $defaults['default_lending_cap_amount'];

        return [
            'initial_limit_percent_of_income' => round(min(100, max(0, (float)$initialLimitPercent)), 2),
            'use_default_lending_cap' => $useDefaultLendingCap,
            'default_lending_cap_amount' => round(max(0, (float)$defaultLendingCapAmount), 2),
            'apply_score_changes_immediately' => array_key_exists('apply_score_changes_immediately', $input)
                ? !empty($input['apply_score_changes_immediately'])
                : $defaults['apply_score_changes_immediately'],
        ];
    }
}
