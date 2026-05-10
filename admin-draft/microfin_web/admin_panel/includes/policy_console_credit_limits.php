<?php

require_once __DIR__ . '/policy_console_system_defaults.php';
require_once __DIR__ . '/policy_console_limit_assignment.php';

if (!function_exists('policy_console_credit_limits_setting_key')) {
    function policy_console_credit_limits_setting_key(): string
    {
        return 'policy_console_credit_limits';
    }
}

if (!function_exists('policy_console_credit_limits_defaults')) {
    function policy_console_credit_limits_defaults(array $creditPolicy, array $creditLimitRules, int $scoreCeiling): array
    {
        $defaults = policy_console_credit_limits_system_defaults();
        return policy_console_credit_limits_normalize($defaults, $creditPolicy, $creditLimitRules, $scoreCeiling);
    }
}

if (!function_exists('policy_console_credit_limits_normalize')) {
    function policy_console_credit_limits_normalize($payload, array $creditPolicy, array $creditLimitRules, int $scoreCeiling): array
    {
        $defaults = policy_console_credit_limits_system_defaults();
        $input = is_array($payload) ? array_replace_recursive($defaults, $payload) : $defaults;

        $normalizeToggle = static fn($value): bool => !empty($value) && !in_array($value, ['0', 0, false, 'false'], true);
        $normalizeScore = static function ($value, $fallback) use ($scoreCeiling): int {
            $score = is_numeric($value) ? (float)$value : (float)$fallback;
            return (int)round(min($scoreCeiling, max(0, $score)));
        };
        $normalizeNullableMaxScore = static function ($value, $fallback = null) use ($scoreCeiling): ?int {
            if ($value === '' || $value === null) {
                return null;
            }

            if (!is_numeric($value)) {
                if ($fallback === '' || $fallback === null || !is_numeric($fallback)) {
                    return null;
                }
                $value = $fallback;
            }

            $score = (float)$value;
            return (int)round(max(0, $score));
        };
        $normalizeDays = static function ($value, $fallback): int {
            $days = is_numeric($value) ? (float)$value : (float)$fallback;
            return $days <= 0 ? 0 : (int)round(min(3650, max(1, $days)));
        };
        $normalizeInt = static function ($value, $fallback, int $min = 0, int $max = 1000): int {
            $number = is_numeric($value) ? (float)$value : (float)$fallback;
            return (int)round(min($max, max($min, $number)));
        };
        $normalizeDecimal = static function ($value, $fallback, float $min = 0.0, float $max = 100.0, int $precision = 3): float {
            $number = is_numeric($value) ? (float)$value : (float)$fallback;
            return round(min($max, max($min, $number)), $precision);
        };

        $scoreBandRows = [];
        if (!empty($input['score_bands']['rows']) && is_array($input['score_bands']['rows'])) {
            foreach (array_values($input['score_bands']['rows']) as $index => $row) {
                if (!is_array($row)) {
                    continue;
                }

                $label = trim((string)($row['label'] ?? ''));
                $minScore = $normalizeScore($row['min_score'] ?? 0, 0);
                $maxScore = $normalizeNullableMaxScore($row['max_score'] ?? null, $row['max_score'] ?? null);
                if ($maxScore !== null && $maxScore < $minScore) {
                    $maxScore = $minScore;
                }

                if ($label === '' && $minScore === 0 && ($maxScore === null || $maxScore === 0)) {
                    continue;
                }

                $scoreBandRows[] = [
                    'id' => preg_replace('/[^a-z0-9_\-]/i', '_', trim((string)($row['id'] ?? 'band_' . ($index + 1)))),
                    'label' => substr($label !== '' ? $label : 'Band ' . ($index + 1), 0, 60),
                    'min_score' => $minScore,
                    'max_score' => $maxScore,
                    'base_growth_percent' => $normalizeDecimal(
                        $row['base_growth_percent'] ?? null,
                        $defaults['score_bands']['rows'][$index]['base_growth_percent'] ?? 0
                    ),
                    'micro_percent_per_point' => $normalizeDecimal(
                        $row['micro_percent_per_point'] ?? null,
                        $defaults['score_bands']['rows'][$index]['micro_percent_per_point'] ?? 0,
                        0.0,
                        10.0,
                        3
                    ),
                ];
            }
        }
        if ($scoreBandRows === []) {
            $scoreBandRows = $defaults['score_bands']['rows'];
        } else {
            $openEndedIndex = null;
            $highestMinScore = -1;
            foreach ($scoreBandRows as $index => $row) {
                $rowMinScore = (int)($row['min_score'] ?? 0);
                $rowMaxScore = $row['max_score'] ?? null;
                if (($rowMaxScore === null || $rowMaxScore === '' || (is_numeric($rowMaxScore) && (int)$rowMaxScore >= $scoreCeiling)) && $rowMinScore >= $highestMinScore) {
                    $highestMinScore = $rowMinScore;
                    $openEndedIndex = $index;
                }
            }

            if ($openEndedIndex !== null) {
                $scoreBandRows[$openEndedIndex]['max_score'] = null;
            }
        }

        $core = $input['scoring_setup']['core'] ?? [];
        $detailed = $input['scoring_setup']['detailed_rules'] ?? [];

        return [
            'scoring_setup' => [
                'core' => [
                    'starting_credit_score' => $normalizeScore($core['starting_credit_score'] ?? null, $defaults['scoring_setup']['core']['starting_credit_score']),
                    'repayment_score_bonus' => $normalizeInt($core['repayment_score_bonus'] ?? null, $defaults['scoring_setup']['core']['repayment_score_bonus']),
                    'late_payment_score_penalty' => $normalizeInt($core['late_payment_score_penalty'] ?? null, $defaults['scoring_setup']['core']['late_payment_score_penalty']),
                ],
                'detailed_rules' => [
                    'upgrade' => [
                        'successful_repayment_cycles' => [
                            'enabled' => $normalizeToggle($detailed['upgrade']['successful_repayment_cycles']['enabled'] ?? $defaults['scoring_setup']['detailed_rules']['upgrade']['successful_repayment_cycles']['enabled']),
                            'required_cycles' => $normalizeInt($detailed['upgrade']['successful_repayment_cycles']['required_cycles'] ?? null, $defaults['scoring_setup']['detailed_rules']['upgrade']['successful_repayment_cycles']['required_cycles'], 1, 999),
                            'score_points' => $normalizeInt($detailed['upgrade']['successful_repayment_cycles']['score_points'] ?? null, $defaults['scoring_setup']['detailed_rules']['upgrade']['successful_repayment_cycles']['score_points']),
                        ],
                        'maximum_late_payments_review' => [
                            'enabled' => $normalizeToggle($detailed['upgrade']['maximum_late_payments_review']['enabled'] ?? $defaults['scoring_setup']['detailed_rules']['upgrade']['maximum_late_payments_review']['enabled']),
                            'maximum_allowed' => $normalizeInt($detailed['upgrade']['maximum_late_payments_review']['maximum_allowed'] ?? null, $defaults['scoring_setup']['detailed_rules']['upgrade']['maximum_late_payments_review']['maximum_allowed'], 0, 365),
                            'review_period_days' => $normalizeDays($detailed['upgrade']['maximum_late_payments_review']['review_period_days'] ?? null, $defaults['scoring_setup']['detailed_rules']['upgrade']['maximum_late_payments_review']['review_period_days']),
                            'score_points' => $normalizeInt($detailed['upgrade']['maximum_late_payments_review']['score_points'] ?? null, $defaults['scoring_setup']['detailed_rules']['upgrade']['maximum_late_payments_review']['score_points']),
                        ],
                        'no_active_overdue' => [
                            'enabled' => $normalizeToggle($detailed['upgrade']['no_active_overdue']['enabled'] ?? $defaults['scoring_setup']['detailed_rules']['upgrade']['no_active_overdue']['enabled']),
                            'review_period_days' => $normalizeDays($detailed['upgrade']['no_active_overdue']['review_period_days'] ?? null, $defaults['scoring_setup']['detailed_rules']['upgrade']['no_active_overdue']['review_period_days']),
                            'score_points' => $normalizeInt($detailed['upgrade']['no_active_overdue']['score_points'] ?? null, $defaults['scoring_setup']['detailed_rules']['upgrade']['no_active_overdue']['score_points']),
                        ],
                    ],
                    'downgrade' => [
                        'late_payments_review' => [
                            'enabled' => $normalizeToggle($detailed['downgrade']['late_payments_review']['enabled'] ?? $defaults['scoring_setup']['detailed_rules']['downgrade']['late_payments_review']['enabled']),
                            'trigger_count' => $normalizeInt($detailed['downgrade']['late_payments_review']['trigger_count'] ?? null, $defaults['scoring_setup']['detailed_rules']['downgrade']['late_payments_review']['trigger_count'], 1, 365),
                            'review_period_days' => $normalizeDays($detailed['downgrade']['late_payments_review']['review_period_days'] ?? null, $defaults['scoring_setup']['detailed_rules']['downgrade']['late_payments_review']['review_period_days']),
                            'score_points' => $normalizeInt($detailed['downgrade']['late_payments_review']['score_points'] ?? null, $defaults['scoring_setup']['detailed_rules']['downgrade']['late_payments_review']['score_points']),
                        ],
                        'overdue_days_threshold' => [
                            'enabled' => $normalizeToggle($detailed['downgrade']['overdue_days_threshold']['enabled'] ?? $defaults['scoring_setup']['detailed_rules']['downgrade']['overdue_days_threshold']['enabled']),
                            'days' => $normalizeDays($detailed['downgrade']['overdue_days_threshold']['days'] ?? null, $defaults['scoring_setup']['detailed_rules']['downgrade']['overdue_days_threshold']['days']),
                            'score_points' => $normalizeInt($detailed['downgrade']['overdue_days_threshold']['score_points'] ?? null, $defaults['scoring_setup']['detailed_rules']['downgrade']['overdue_days_threshold']['score_points']),
                        ],
                    ],
                ],
            ],
            'score_bands' => [
                'rows' => $scoreBandRows,
            ],
            'limit_assignment' => policy_console_limit_assignment_normalize(
                $input['limit_assignment'] ?? [],
                policy_console_limit_assignment_from_live($creditPolicy, $creditLimitRules)
            ),
        ];
    }
}

if (!function_exists('policy_console_credit_limits_load')) {
    function policy_console_credit_limits_load(PDO $pdo, string $tenantId, array $creditPolicy, array $creditLimitRules, int $scoreCeiling): array
    {
        $raw = admin_get_system_setting($pdo, $tenantId, policy_console_credit_limits_setting_key(), '');
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return policy_console_credit_limits_normalize($decoded, $creditPolicy, $creditLimitRules, $scoreCeiling);
            }
        }

        return policy_console_credit_limits_defaults($creditPolicy, $creditLimitRules, $scoreCeiling);
    }
}

if (!function_exists('policy_console_credit_limits_build_from_post')) {
    function policy_console_credit_limits_build_from_post(array $source, array $creditPolicy, array $creditLimitRules, int $scoreCeiling): array
    {
        $rows = [];
        $labels = isset($source['pcc_score_band_label']) && is_array($source['pcc_score_band_label']) ? $source['pcc_score_band_label'] : [];
        $ids = isset($source['pcc_score_band_id']) && is_array($source['pcc_score_band_id']) ? $source['pcc_score_band_id'] : [];
        $mins = isset($source['pcc_score_band_min']) && is_array($source['pcc_score_band_min']) ? $source['pcc_score_band_min'] : [];
        $maxes = isset($source['pcc_score_band_max']) && is_array($source['pcc_score_band_max']) ? $source['pcc_score_band_max'] : [];
        $baseGrowths = isset($source['pcc_score_band_base_growth']) && is_array($source['pcc_score_band_base_growth']) ? $source['pcc_score_band_base_growth'] : [];
        $microGrowths = isset($source['pcc_score_band_micro_growth']) && is_array($source['pcc_score_band_micro_growth']) ? $source['pcc_score_band_micro_growth'] : [];

        $rowCount = max(count($labels), count($ids), count($mins), count($maxes), count($baseGrowths), count($microGrowths));
        for ($index = 0; $index < $rowCount; $index++) {
            $rows[] = [
                'id' => $ids[$index] ?? ('band_' . ($index + 1)),
                'label' => $labels[$index] ?? '',
                'min_score' => $mins[$index] ?? 0,
                'max_score' => $maxes[$index] ?? null,
                'base_growth_percent' => $baseGrowths[$index] ?? 0,
                'micro_percent_per_point' => $microGrowths[$index] ?? 0,
            ];
        }

        $payload = [
            'scoring_setup' => [
                'core' => [
                    'starting_credit_score' => $source['pcc_core_starting_credit_score'] ?? null,
                    'repayment_score_bonus' => $source['pcc_upgrade_successful_repayment_points'] ?? $source['pcc_upgrade_successful_repayment_points_sync'] ?? null,
                    'late_payment_score_penalty' => $source['pcc_downgrade_late_payments_points'] ?? $source['pcc_downgrade_late_payments_points_sync'] ?? null,
                ],
                'detailed_rules' => [
                    'upgrade' => [
                        'successful_repayment_cycles' => [
                            'enabled' => $source['pcc_upgrade_successful_repayment_enabled'] ?? 0,
                            'required_cycles' => $source['pcc_upgrade_successful_repayment_cycles'] ?? null,
                            'score_points' => $source['pcc_upgrade_successful_repayment_points'] ?? $source['pcc_upgrade_successful_repayment_points_sync'] ?? null,
                        ],
                        'maximum_late_payments_review' => [
                            'enabled' => $source['pcc_upgrade_late_payments_enabled'] ?? 0,
                            'maximum_allowed' => $source['pcc_upgrade_late_payments_max'] ?? null,
                            'review_period_days' => $source['pcc_upgrade_late_payments_review_days'] ?? null,
                            'score_points' => $source['pcc_upgrade_late_payments_points'] ?? $source['pcc_upgrade_late_payments_points_sync'] ?? null,
                        ],
                        'no_active_overdue' => [
                            'enabled' => $source['pcc_upgrade_no_active_overdue_enabled'] ?? 0,
                            'review_period_days' => $source['pcc_upgrade_no_active_overdue_review_days'] ?? null,
                            'score_points' => $source['pcc_upgrade_no_active_overdue_points'] ?? $source['pcc_upgrade_no_active_overdue_points_sync'] ?? null,
                        ],
                    ],
                    'downgrade' => [
                        'late_payments_review' => [
                            'enabled' => $source['pcc_downgrade_late_payments_enabled'] ?? 0,
                            'trigger_count' => $source['pcc_downgrade_late_payments_trigger'] ?? null,
                            'review_period_days' => $source['pcc_downgrade_late_payments_review_days'] ?? null,
                            'score_points' => $source['pcc_downgrade_late_payments_points'] ?? $source['pcc_downgrade_late_payments_points_sync'] ?? null,
                        ],
                        'overdue_days_threshold' => [
                            'enabled' => $source['pcc_downgrade_overdue_days_enabled'] ?? 0,
                            'days' => $source['pcc_downgrade_overdue_days_threshold'] ?? null,
                            'score_points' => $source['pcc_downgrade_overdue_days_points'] ?? $source['pcc_downgrade_overdue_days_points_sync'] ?? null,
                        ],
                    ],
                ],
            ],
            'score_bands' => [
                'rows' => $rows,
            ],
            'limit_assignment' => [
                'initial_limit_percent_of_income' => $source['pcc_limit_initial_percent_of_income'] ?? null,
                'use_default_lending_cap' => $source['pcc_limit_use_default_lending_cap'] ?? 0,
                'default_lending_cap_amount' => $source['pcc_limit_default_lending_cap_amount'] ?? null,
                'apply_score_changes_immediately' => $source['pcc_limit_apply_score_changes_immediately'] ?? 0,
            ],
        ];

        return policy_console_credit_limits_normalize($payload, $creditPolicy, $creditLimitRules, $scoreCeiling);
    }
}
