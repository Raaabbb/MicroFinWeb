<?php

if (!function_exists('mf_credit_policy_employment_options')) {
    function mf_credit_policy_employment_options(): array
    {
        return [
            'Employed',
            'Self-Employed',
            'Freelancer',
            'Contractual',
            'Part-Time',
            'OFW',
            'Student',
            'Unemployed',
            'Retired',
        ];
    }
}

if (!function_exists('mf_credit_policy_ci_recommendation_options')) {
    function mf_credit_policy_ci_recommendation_options(): array
    {
        return ['Highly Recommended', 'Recommended', 'Conditional', 'Not Recommended'];
    }
}

if (!function_exists('mf_credit_policy_score_recommendation_options')) {
    function mf_credit_policy_score_recommendation_options(): array
    {
        return [
            'At-Risk Credit Score',
            'Fair Credit Score',
            'Standard Credit Score',
            'Good Credit Score',
            'High Credit Score',
        ];
    }
}

if (!function_exists('mf_credit_policy_score_ceiling')) {
    function mf_credit_policy_score_ceiling(): int
    {
        // Legacy 0-100 scores are still expanded against this reference,
        // but current policy thresholds are no longer hard-capped to it.
        return 1000;
    }
}

if (!function_exists('mf_credit_policy_normalize_score_value')) {
    function mf_credit_policy_normalize_score_value($value, $fallback = 0, bool $scaleLegacy = true): int
    {
        $score = is_numeric($value) ? (float) $value : (float) $fallback;
        $legacyScaleReference = mf_credit_policy_score_ceiling();

        if ($scaleLegacy && $score > 0 && $score <= 100 && $legacyScaleReference > 100) {
            $score *= $legacyScaleReference / 100;
        }

        return (int) round(max(0, $score));
    }
}

if (!function_exists('mf_credit_policy_defaults')) {
    function mf_credit_policy_defaults(): array
    {
        return [
            'eligibility' => [
                'allow_no_minimum_income' => true,
                'min_monthly_income' => 0,
                'allowed_employment_statuses' => ['Employed', 'Self-Employed', 'Retired'],
            ],
            'score_thresholds' => [
                'not_recommended_min_score' => 200,
                'conditional_min_score' => 400,
                'recommended_min_score' => 600,
                'highly_recommended_min_score' => 800,
                'new_client_default_score' => 500,
            ],
            'score_growth' => [
                'verified_documents_bonus' => 20,
                'completed_loan_bonus' => 30,
                'on_time_payment_bonus' => 8,
                'late_payment_penalty' => 18,
                'missed_payment_penalty' => 40,
                'active_loan_penalty' => 12,
            ],
            'decision_routing' => [
                'reject_below_score' => 400,
                'manual_review_from_score' => 400,
                'manual_review_to_score' => 599,
                'approval_candidate_from_score' => 600,
            ],
            'ci_rules' => [
                'require_ci' => false,
                'auto_approve_ci_values' => ['Highly Recommended', 'Recommended'],
                'review_ci_values' => ['Conditional'],
            ],
            'credit_limit' => [
                'income_multiplier' => 4,
                'approve_band_multiplier' => 1.10,
                'review_band_multiplier' => 1.00,
                'fair_band_multiplier' => 0.75,
                'at_risk_band_multiplier' => 0.50,
                'max_credit_limit_cap' => 200000,
                'round_to_nearest' => 500,
            ],
            'product_checks' => [
                'use_product_minimum_credit_score' => true,
                'use_product_min_amount' => true,
                'use_product_max_amount' => true,
            ],
        ];
    }
}

if (!function_exists('mf_credit_policy_build_decision_routing')) {
    function mf_credit_policy_build_decision_routing(int $conditionalMinScore, int $recommendedMinScore): array
    {
        $rejectBelowScore = max(0, $conditionalMinScore);
        $manualReviewFromScore = $rejectBelowScore;
        $manualReviewToScore = max($manualReviewFromScore, $recommendedMinScore - 1);
        $approvalCandidateFromScore = max($manualReviewToScore + 1, $recommendedMinScore);

        return [
            'reject_below_score' => $rejectBelowScore,
            'manual_review_from_score' => $manualReviewFromScore,
            'manual_review_to_score' => $manualReviewToScore,
            'approval_candidate_from_score' => $approvalCandidateFromScore,
        ];
    }
}

if (!function_exists('mf_credit_policy_truthy')) {
    function mf_credit_policy_truthy($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }
}

if (!function_exists('mf_credit_policy_normalize_list')) {
    function mf_credit_policy_normalize_list($values, array $allowed, array $fallback, bool $allowEmpty = false): array
    {
        if (!is_array($values)) {
            $values = [];
        }

        $normalized = [];
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value !== '' && in_array($value, $allowed, true)) {
                $normalized[$value] = $value;
            }
        }

        if (!empty($normalized)) {
            return array_values($normalized);
        }

        if ($allowEmpty) {
            return [];
        }

        return $fallback;
    }
}

if (!function_exists('mf_credit_policy_normalize')) {
    function mf_credit_policy_normalize($payload): array
    {
        $defaults = mf_credit_policy_defaults();
        $policy = is_array($payload) ? array_replace_recursive($defaults, $payload) : $defaults;
        $scoreThresholds = is_array($policy['score_thresholds'] ?? null) ? $policy['score_thresholds'] : [];

        $rawHighlyRecommendedMin = mf_credit_policy_normalize_score_value(
            $scoreThresholds['highly_recommended_min_score']
                ?? $scoreThresholds['approve_min_score']
                ?? $defaults['score_thresholds']['highly_recommended_min_score'],
            $defaults['score_thresholds']['highly_recommended_min_score']
        );
        $recommendedFallback = max(
            $defaults['score_thresholds']['conditional_min_score'] + 1,
            $rawHighlyRecommendedMin - 200
        );
        $rawRecommendedMin = mf_credit_policy_normalize_score_value(
            $scoreThresholds['recommended_min_score'] ?? $recommendedFallback,
            $defaults['score_thresholds']['recommended_min_score']
        );
        $conditionalFallback = isset($scoreThresholds['conditional_min_score'])
            ? $scoreThresholds['conditional_min_score']
            : (
                $policy['decision_routing']['manual_review_from_score']
                ?? $scoreThresholds['review_min_score']
                ?? max($defaults['score_thresholds']['not_recommended_min_score'] + 1, $rawRecommendedMin - 200)
            );
        $rawConditionalMin = mf_credit_policy_normalize_score_value(
            $conditionalFallback,
            $defaults['score_thresholds']['conditional_min_score']
        );
        $notRecommendedFallback = max(0, $rawConditionalMin - 200);
        $rawNotRecommendedMin = mf_credit_policy_normalize_score_value(
            $scoreThresholds['not_recommended_min_score'] ?? $notRecommendedFallback,
            $defaults['score_thresholds']['not_recommended_min_score']
        );

        $notRecommendedMinScore = max(0, $rawNotRecommendedMin);
        $conditionalMinScore = max($notRecommendedMinScore + 1, $rawConditionalMin);
        $recommendedMinScore = max($conditionalMinScore + 1, $rawRecommendedMin);
        $highlyRecommendedMinScore = max($recommendedMinScore + 1, $rawHighlyRecommendedMin);
        $decisionRouting = mf_credit_policy_build_decision_routing($conditionalMinScore, $recommendedMinScore);
        $eligibility = is_array($policy['eligibility'] ?? null) ? $policy['eligibility'] : [];
        $scoreGrowth = is_array($policy['score_growth'] ?? null) ? $policy['score_growth'] : [];
        $minMonthlyIncome = round(max(0, (float) ($eligibility['min_monthly_income'] ?? $defaults['eligibility']['min_monthly_income'])), 2);
        $allowNoMinimumIncome = array_key_exists('allow_no_minimum_income', $eligibility)
            ? mf_credit_policy_truthy($eligibility['allow_no_minimum_income'])
            : ($minMonthlyIncome <= 0);
        if ($allowNoMinimumIncome) {
            $minMonthlyIncome = 0.0;
        }

        $newClientDefaultScore = mf_credit_policy_normalize_score_value(
            $scoreThresholds['new_client_default_score'] ?? $defaults['score_thresholds']['new_client_default_score'],
            $defaults['score_thresholds']['new_client_default_score']
        );

        // Backward-compat: map old CI multiplier keys to new band multiplier keys
        $creditLimit = $policy['credit_limit'] ?? [];
        $hasNewBandKeys = isset($creditLimit['approve_band_multiplier'])
            || isset($creditLimit['review_band_multiplier'])
            || isset($creditLimit['fair_band_multiplier'])
            || isset($creditLimit['at_risk_band_multiplier'])
            || isset($creditLimit['reject_band_multiplier']);
        if (!$hasNewBandKeys) {
            if (isset($creditLimit['ci_multiplier_highly_recommended'])) {
                $creditLimit['approve_band_multiplier'] = $creditLimit['ci_multiplier_highly_recommended'];
            }
            if (isset($creditLimit['ci_multiplier_recommended'])) {
                $creditLimit['review_band_multiplier'] = $creditLimit['ci_multiplier_recommended'];
            }
            if (isset($creditLimit['ci_multiplier_conditional'])) {
                $creditLimit['fair_band_multiplier'] = $creditLimit['ci_multiplier_conditional'];
                $creditLimit['at_risk_band_multiplier'] = $creditLimit['ci_multiplier_conditional'];
            }
        }

        $legacyRejectBandMultiplier = $creditLimit['reject_band_multiplier'] ?? null;
        if (!isset($creditLimit['fair_band_multiplier']) && $legacyRejectBandMultiplier !== null) {
            $creditLimit['fair_band_multiplier'] = $legacyRejectBandMultiplier;
        }
        if (!isset($creditLimit['at_risk_band_multiplier']) && $legacyRejectBandMultiplier !== null) {
            $creditLimit['at_risk_band_multiplier'] = $legacyRejectBandMultiplier;
        }

        return [
            'eligibility' => [
                'allow_no_minimum_income' => $allowNoMinimumIncome,
                'min_monthly_income' => $minMonthlyIncome,
                'allowed_employment_statuses' => mf_credit_policy_normalize_list(
                    $eligibility['allowed_employment_statuses'] ?? [],
                    mf_credit_policy_employment_options(),
                    $defaults['eligibility']['allowed_employment_statuses'],
                    true
                ),
            ],
            'score_thresholds' => [
                'not_recommended_min_score' => $notRecommendedMinScore,
                'conditional_min_score' => $conditionalMinScore,
                'recommended_min_score' => $recommendedMinScore,
                'highly_recommended_min_score' => $highlyRecommendedMinScore,
                'new_client_default_score' => $newClientDefaultScore,
            ],
            'score_growth' => [
                'verified_documents_bonus' => (int) round(max(0, (float) ($scoreGrowth['verified_documents_bonus'] ?? $defaults['score_growth']['verified_documents_bonus']))),
                'completed_loan_bonus' => (int) round(max(0, (float) ($scoreGrowth['completed_loan_bonus'] ?? $defaults['score_growth']['completed_loan_bonus']))),
                'on_time_payment_bonus' => (int) round(max(0, (float) ($scoreGrowth['on_time_payment_bonus'] ?? $defaults['score_growth']['on_time_payment_bonus']))),
                'late_payment_penalty' => (int) round(max(0, (float) ($scoreGrowth['late_payment_penalty'] ?? $defaults['score_growth']['late_payment_penalty']))),
                'missed_payment_penalty' => (int) round(max(0, (float) ($scoreGrowth['missed_payment_penalty'] ?? $defaults['score_growth']['missed_payment_penalty']))),
                'active_loan_penalty' => (int) round(max(0, (float) ($scoreGrowth['active_loan_penalty'] ?? $defaults['score_growth']['active_loan_penalty']))),
            ],
            'decision_routing' => $decisionRouting,
            'ci_rules' => [
                'require_ci' => mf_credit_policy_truthy($policy['ci_rules']['require_ci'] ?? $defaults['ci_rules']['require_ci']),
                'auto_approve_ci_values' => mf_credit_policy_normalize_list(
                    $policy['ci_rules']['auto_approve_ci_values'] ?? [],
                    mf_credit_policy_ci_recommendation_options(),
                    $defaults['ci_rules']['auto_approve_ci_values']
                ),
                'review_ci_values' => mf_credit_policy_normalize_list(
                    $policy['ci_rules']['review_ci_values'] ?? [],
                    mf_credit_policy_ci_recommendation_options(),
                    $defaults['ci_rules']['review_ci_values']
                ),
            ],
            'credit_limit' => [
                'income_multiplier' => round(max(0, (float) ($creditLimit['income_multiplier'] ?? $defaults['credit_limit']['income_multiplier'])), 2),
                'approve_band_multiplier' => round(max(0, (float) ($creditLimit['approve_band_multiplier'] ?? $defaults['credit_limit']['approve_band_multiplier'])), 2),
                'review_band_multiplier' => round(max(0, (float) ($creditLimit['review_band_multiplier'] ?? $defaults['credit_limit']['review_band_multiplier'])), 2),
                'fair_band_multiplier' => round(max(0, (float) ($creditLimit['fair_band_multiplier'] ?? $defaults['credit_limit']['fair_band_multiplier'])), 2),
                'at_risk_band_multiplier' => round(max(0, (float) ($creditLimit['at_risk_band_multiplier'] ?? $defaults['credit_limit']['at_risk_band_multiplier'])), 2),
                'max_credit_limit_cap' => round(max(0, (float) ($creditLimit['max_credit_limit_cap'] ?? $defaults['credit_limit']['max_credit_limit_cap'])), 2),
                'round_to_nearest' => round(max(0, (float) ($creditLimit['round_to_nearest'] ?? $defaults['credit_limit']['round_to_nearest'])), 2),
            ],
            'product_checks' => [
                'use_product_minimum_credit_score' => mf_credit_policy_truthy($policy['product_checks']['use_product_minimum_credit_score'] ?? $defaults['product_checks']['use_product_minimum_credit_score']),
                'use_product_min_amount' => mf_credit_policy_truthy($policy['product_checks']['use_product_min_amount'] ?? $defaults['product_checks']['use_product_min_amount']),
                'use_product_max_amount' => mf_credit_policy_truthy($policy['product_checks']['use_product_max_amount'] ?? $defaults['product_checks']['use_product_max_amount']),
            ],
        ];
    }
}

if (!function_exists('mf_credit_policy_legacy_keys')) {
    function mf_credit_policy_legacy_keys(): array
    {
        return [
            'credit_policy',
            'minimum_credit_score',
            'require_credit_investigation',
            'auto_reject_below_score',
            'credit_limit_rules',
        ];
    }
}

if (!function_exists('mf_credit_policy_from_legacy_settings')) {
    function mf_credit_policy_from_legacy_settings(array $settings): array
    {
        $defaults = mf_credit_policy_defaults();
        $legacy = $defaults;
        $legacyConditionalMin = mf_credit_policy_normalize_score_value(
            $settings['auto_reject_below_score'] ?? $defaults['score_thresholds']['conditional_min_score'],
            $defaults['score_thresholds']['conditional_min_score']
        );
        $legacyRecommendedMin = mf_credit_policy_normalize_score_value(
            $settings['minimum_credit_score'] ?? $defaults['score_thresholds']['recommended_min_score'],
            $defaults['score_thresholds']['recommended_min_score']
        );
        $legacy['score_thresholds']['conditional_min_score'] = $legacyConditionalMin;
        $legacy['score_thresholds']['recommended_min_score'] = max($legacyConditionalMin + 1, $legacyRecommendedMin);
        $legacy['score_thresholds']['not_recommended_min_score'] = max(0, $legacyConditionalMin - 200);
        $legacy['score_thresholds']['highly_recommended_min_score'] = max(
            $legacy['score_thresholds']['recommended_min_score'] + 1,
            $legacy['score_thresholds']['recommended_min_score'] + 200
        );
        $legacy['ci_rules']['require_ci'] = mf_credit_policy_truthy($settings['require_credit_investigation'] ?? $defaults['ci_rules']['require_ci']);

        if (!empty($settings['credit_limit_rules'])) {
            $decoded = json_decode((string) $settings['credit_limit_rules'], true);
            if (is_array($decoded)) {
                $absoluteMaxLimit = $decoded['increase_rules']['absolute_max_limit'] ?? null;
                if (is_numeric($absoluteMaxLimit)) {
                    $legacy['credit_limit']['max_credit_limit_cap'] = (float) $absoluteMaxLimit;
                }
            }
        }

        return mf_credit_policy_normalize($legacy);
    }
}

if (!function_exists('mf_get_tenant_credit_policy')) {
    function mf_get_tenant_credit_policy(PDO $pdo, string $tenantId): array
    {
        $tenantId = trim($tenantId);
        if ($tenantId === '') {
            return mf_credit_policy_defaults();
        }

        $keys = mf_credit_policy_legacy_keys();
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $params = array_merge([$tenantId], $keys);

        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE tenant_id = ? AND setting_key IN ($placeholders)");
        $stmt->execute($params);

        $settings = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $settings[(string) $row['setting_key']] = $row['setting_value'];
        }

        if (!empty($settings['credit_policy'])) {
            $decoded = json_decode((string) $settings['credit_policy'], true);
            if (is_array($decoded)) {
                return mf_credit_policy_normalize($decoded);
            }
        }

        return mf_credit_policy_from_legacy_settings($settings);
    }
}

if (!function_exists('mf_credit_limit_rule_defaults')) {
    function mf_credit_limit_rule_defaults(): array
    {
        return [
            'workflow' => [
                // Upgrade approvals are fixed to semi-auto so staff always confirms the change.
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

if (!function_exists('mf_credit_limit_rules_normalize')) {
    function mf_credit_limit_rules_normalize($payload): array
    {
        $defaults = mf_credit_limit_rule_defaults();
        $rules = is_array($payload) ? array_replace_recursive($defaults, $payload) : $defaults;

        $baseLimitDefault = round(max(0, (float) ($rules['initial_limits']['base_limit_default'] ?? $defaults['initial_limits']['base_limit_default'])), 2);
        $customCategories = [];

        if (!empty($rules['initial_limits']['custom_categories']) && is_array($rules['initial_limits']['custom_categories'])) {
            foreach ($rules['initial_limits']['custom_categories'] as $categoryRule) {
                if (!is_array($categoryRule)) {
                    continue;
                }

                $categoryName = trim((string) ($categoryRule['category_name'] ?? ''));
                $limitType = (string) ($categoryRule['limit_type'] ?? 'fixed');
                $value = round(max(0, (float) ($categoryRule['value'] ?? 0)), 2);

                if ($categoryName === '') {
                    continue;
                }
                if ($limitType === 'multiplier') {
                    $value = round($baseLimitDefault * $value, 2);
                    $limitType = 'fixed';
                }
                if (!in_array($limitType, ['fixed', 'income_percent'], true)) {
                    $limitType = 'fixed';
                }

                $customCategories[] = [
                    'category_name' => substr($categoryName, 0, 80),
                    'limit_type' => $limitType,
                    'value' => $value,
                ];
            }
        }

        $increaseType = (string) ($rules['increase_rules']['increase_type'] ?? $defaults['increase_rules']['increase_type']);
        if (!in_array($increaseType, ['percentage', 'fixed'], true)) {
            $increaseType = $defaults['increase_rules']['increase_type'];
        }

        return [
            'workflow' => [
                'approval_mode' => 'semi',
            ],
            'initial_limits' => [
                'base_limit_default' => $baseLimitDefault,
                'custom_categories' => $customCategories,
            ],
            'upgrade_eligibility' => [
                'min_completed_loans' => max(0, (int) ($rules['upgrade_eligibility']['min_completed_loans'] ?? $defaults['upgrade_eligibility']['min_completed_loans'])),
                'max_allowed_late_payments' => max(0, (int) ($rules['upgrade_eligibility']['max_allowed_late_payments'] ?? $defaults['upgrade_eligibility']['max_allowed_late_payments'])),
            ],
            'increase_rules' => [
                'increase_type' => $increaseType,
                'increase_value' => round(max(0, (float) ($rules['increase_rules']['increase_value'] ?? $defaults['increase_rules']['increase_value'])), 2),
                'absolute_max_limit' => round(max(0, (float) ($rules['increase_rules']['absolute_max_limit'] ?? $defaults['increase_rules']['absolute_max_limit'])), 2),
            ],
        ];
    }
}

if (!function_exists('mf_get_tenant_credit_limit_rules')) {
    function mf_get_tenant_credit_limit_rules(PDO $pdo, string $tenantId): array
    {
        $tenantId = trim($tenantId);
        if ($tenantId === '') {
            return mf_credit_limit_rule_defaults();
        }

        $stmt = $pdo->prepare("
            SELECT setting_value
            FROM system_settings
            WHERE tenant_id = ? AND setting_key = 'credit_limit_rules'
            LIMIT 1
        ");
        $stmt->execute([$tenantId]);
        $raw = $stmt->fetchColumn();

        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return mf_credit_limit_rules_normalize($decoded);
            }
        }

        return mf_credit_limit_rule_defaults();
    }
}

if (!function_exists('mf_credit_policy_fetch_upgrade_metrics')) {
    function mf_credit_policy_fetch_upgrade_metrics(PDO $pdo, string $tenantId, int $clientId): array
    {
        // Find the last explicit upgrade for this borrower
        $lastUpgradeStmt = $pdo->prepare("
            SELECT MAX(created_at) 
            FROM audit_logs 
            WHERE tenant_id = ? AND entity_type = 'client' AND entity_id = ? AND action_type = 'CREDIT_LIMIT_UPGRADED'
        ");
        $lastUpgradeStmt->execute([$tenantId, $clientId]);
        $lastUpgradeDate = $lastUpgradeStmt->fetchColumn();

        // Count completed loans since the last upgrade
        $completedSql = "SELECT COUNT(*) FROM loans WHERE tenant_id = ? AND client_id = ? AND loan_status = 'Fully Paid'";
        $completedParams = [$tenantId, $clientId];
        if ($lastUpgradeDate) {
            $completedSql .= " AND updated_at > ?";
            $completedParams[] = $lastUpgradeDate;
        }
        $completedLoansStmt = $pdo->prepare($completedSql);
        $completedLoansStmt->execute($completedParams);
        $completedLoans = (int) $completedLoansStmt->fetchColumn();

        // Count late payments for loans generated after the last upgrade
        $lateSql = "
            SELECT COUNT(*)
            FROM amortization_schedule sched
            INNER JOIN loans l
                ON l.loan_id = sched.loan_id
               AND l.tenant_id = sched.tenant_id
            WHERE l.tenant_id = ?
              AND l.client_id = ?
              AND (COALESCE(sched.days_late, 0) > 0 OR sched.payment_status = 'Overdue')
        ";
        $lateParams = [$tenantId, $clientId];
        if ($lastUpgradeDate) {
            $lateSql .= " AND l.created_at >= ?";
            $lateParams[] = $lastUpgradeDate;
        }
        $latePaymentsStmt = $pdo->prepare($lateSql);
        $latePaymentsStmt->execute($lateParams);
        $latePayments = (int) $latePaymentsStmt->fetchColumn();

        // Check for active overdue loans
        $overdueStmt = $pdo->prepare("
            SELECT COUNT(*) FROM loans 
            WHERE tenant_id = ? AND client_id = ? AND loan_status = 'Overdue'
        ");
        $overdueStmt->execute([$tenantId, $clientId]);
        $hasActiveOverdue = ((int) $overdueStmt->fetchColumn()) > 0;

        // Find max overdue days
        $maxOverdueDaysStmt = $pdo->prepare("
            SELECT COALESCE(MAX(days_late), 0)
            FROM amortization_schedule sched
            INNER JOIN loans l ON l.loan_id = sched.loan_id AND l.tenant_id = sched.tenant_id
            WHERE l.tenant_id = ? AND l.client_id = ?
        ");
        $maxOverdueDaysStmt->execute([$tenantId, $clientId]);
        $maxOverdueDays = (int) $maxOverdueDaysStmt->fetchColumn();

        return [
            'completed_loans' => $completedLoans,
            'late_payments' => $latePayments,
            'has_active_overdue' => $hasActiveOverdue,
            'max_overdue_days' => $maxOverdueDays,
        ];
    }
}

if (!function_exists('mf_credit_policy_compute_upgrade_snapshot')) {
    function mf_credit_policy_compute_upgrade_snapshot(array $rules, array $client, array $metrics): array
    {
        $rules = mf_credit_limit_rules_normalize($rules);

        $currentLimit = round(max(0, (float) ($client['credit_limit'] ?? 0)), 2);
        $baseLimit = round(max(0, (float) ($rules['initial_limits']['base_limit_default'] ?? 0)), 2);
        $completedLoans = max(0, (int) ($metrics['completed_loans'] ?? 0));
        $latePayments = max(0, (int) ($metrics['late_payments'] ?? 0));
        $minCompletedLoans = max(0, (int) ($rules['upgrade_eligibility']['min_completed_loans'] ?? 0));
        $maxLatePayments = max(0, (int) ($rules['upgrade_eligibility']['max_allowed_late_payments'] ?? 0));
        $increaseType = (string) ($rules['increase_rules']['increase_type'] ?? 'percentage');
        $increaseValue = round(max(0, (float) ($rules['increase_rules']['increase_value'] ?? 0)), 2);
        $absoluteMaxLimit = round(max(0, (float) ($rules['increase_rules']['absolute_max_limit'] ?? 0)), 2);

        $meetsCompletedLoanRule = $completedLoans >= $minCompletedLoans;
        $meetsLatePaymentRule = $latePayments <= $maxLatePayments;
        $blockers = [];

        if (!$meetsCompletedLoanRule) {
            $missing = $minCompletedLoans - $completedLoans;
            $blockers[] = 'Needs ' . $missing . ' more completed loan' . ($missing === 1 ? '' : 's') . '.';
        }
        if (!$meetsLatePaymentRule) {
            $blockers[] = 'Late payments must stay at ' . $maxLatePayments . ' or fewer.';
        }

        $potentialUpgradedLimit = null;
        if ($currentLimit > 0) {
            $potentialUpgradedLimit = $increaseType === 'percentage'
                ? $currentLimit + ($currentLimit * ($increaseValue / 100))
                : $currentLimit + $increaseValue;

            if ($absoluteMaxLimit > 0) {
                $potentialUpgradedLimit = min($potentialUpgradedLimit, $absoluteMaxLimit);
            }

            $potentialUpgradedLimit = round(max(0, $potentialUpgradedLimit), 2);
        }

        $status = 'not_yet_eligible';
        $statusLabel = 'Not Yet Eligible';
        $statusNote = 'This borrower does not yet satisfy the current upgrade rules.';

        if ($currentLimit <= 0) {
            $status = 'no_active_limit';
            $statusLabel = 'No Active Credit Limit';
            $statusNote = 'A starting credit limit must be assigned before upgrade rules can be reviewed.';
        } elseif ($absoluteMaxLimit > 0 && ($currentLimit >= $absoluteMaxLimit - 0.009 || ($potentialUpgradedLimit !== null && $potentialUpgradedLimit <= $currentLimit + 0.009))) {
            $status = 'at_max_limit';
            $statusLabel = 'At Maximum Limit';
            $statusNote = 'Current credit limit already matches the configured maximum. No further increase is available.';
            $potentialUpgradedLimit = $currentLimit;
        } elseif ($meetsCompletedLoanRule && $meetsLatePaymentRule) {
            $status = 'eligible';
            $statusLabel = 'Eligible for Upgrade';
            $statusNote = 'This borrower currently meets the upgrade history rules and is ready for staff review.';
        } elseif (!empty($blockers)) {
            $statusNote = implode(' ', $blockers);
        }

        $nextLimitNote = 'Shows the next possible limit once the borrower satisfies the current upgrade rules.';
        if ($currentLimit <= 0) {
            $nextLimitNote = 'Assign a starting credit limit first before projecting the next upgrade.';
        } elseif ($status === 'at_max_limit') {
            $nextLimitNote = 'The current credit limit already meets the configured maximum.';
        } elseif ($status === 'eligible') {
            $nextLimitNote = 'This is the recommended next limit if staff approves the increase.';
        }

        return [
            'workflow_mode' => 'semi',
            'workflow_label' => 'Semi-Automatic',
            'current_limit' => $currentLimit,
            'base_limit_default' => $baseLimit,
            'completed_loans' => $completedLoans,
            'late_payments' => $latePayments,
            'min_completed_loans' => $minCompletedLoans,
            'max_allowed_late_payments' => $maxLatePayments,
            'increase_type' => $increaseType,
            'increase_value' => $increaseValue,
            'absolute_max_limit' => $absoluteMaxLimit,
            'eligible' => $status === 'eligible',
            'status' => $status,
            'status_label' => $statusLabel,
            'status_note' => $statusNote,
            'blockers' => $blockers,
            'potential_upgraded_limit' => $potentialUpgradedLimit,
            'next_limit_note' => $nextLimitNote,
        ];
    }
}

if (!function_exists('mf_credit_policy_fetch_client')) {
    function mf_credit_policy_fetch_client(PDO $pdo, string $tenantId, int $clientId): ?array
    {
        $stmt = $pdo->prepare("
            SELECT client_id, tenant_id, monthly_income, employment_status, client_status,
                   document_verification_status, credit_limit, last_seen_credit_limit
            FROM clients
            WHERE tenant_id = ? AND client_id = ?
            LIMIT 1
        ");
        $stmt->execute([$tenantId, $clientId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}

if (!function_exists('mf_credit_policy_fetch_latest_score')) {
    function mf_credit_policy_fetch_latest_score(PDO $pdo, string $tenantId, int $clientId): ?array
    {
        $stmt = $pdo->prepare("
            SELECT score_id, ci_id, credit_score AS total_score, credit_rating, max_loan_amount,
                   recommended_interest_rate, computation_date, notes, score_metadata
            FROM credit_scores
            WHERE tenant_id = ? AND client_id = ?
            ORDER BY computation_date DESC, score_id DESC
            LIMIT 1
        ");
        $stmt->execute([$tenantId, $clientId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && !empty($row['score_metadata'])) {
            $row['_engine_data'] = json_decode($row['score_metadata'], true);
        }

        return $row ?: null;
    }
}

if (!function_exists('mf_credit_policy_map_rating_label')) {
    function mf_credit_policy_map_rating_label(string $recommendationLabel): string
    {
        $label = trim($recommendationLabel);

        if ($label === 'High Credit Score') {
            return 'Excellent';
        }
        if ($label === 'Good Credit Score') {
            return 'Good';
        }
        if ($label === 'Standard Credit Score') {
            return 'Fair';
        }
        if ($label === 'Fair Credit Score') {
            return 'Poor';
        }

        return 'High Risk';
    }
}

if (!function_exists('mf_credit_policy_document_is_verified')) {
    function mf_credit_policy_document_is_verified($status): bool
    {
        $normalized = strtolower(trim((string) $status));
        return in_array($normalized, ['approved', 'verified'], true);
    }
}

if (!function_exists('mf_credit_policy_client_has_active_limit')) {
    function mf_credit_policy_client_has_active_limit(array $client): bool
    {
        $clientStatus = strtolower(trim((string) ($client['client_status'] ?? '')));
        $documentStatus = $client['document_verification_status'] ?? '';

        if (in_array($clientStatus, ['inactive', 'blacklisted', 'deceased', 'rejected'], true)) {
            return false;
        }

        return mf_credit_policy_document_is_verified($documentStatus);
    }
}

if (!function_exists('mf_credit_policy_limit_block_reason')) {
    function mf_credit_policy_limit_block_reason(array $client): string
    {
        $clientStatus = strtolower(trim((string) ($client['client_status'] ?? '')));
        if (in_array($clientStatus, ['inactive', 'blacklisted', 'deceased', 'rejected'], true)) {
            return 'Borrower status is not eligible for an active credit limit.';
        }

        if (!mf_credit_policy_document_is_verified($client['document_verification_status'] ?? '')) {
            return 'Borrower verification is not approved.';
        }

        return '';
    }
}

if (!function_exists('mf_credit_policy_fetch_score_growth_metrics')) {
    function mf_credit_policy_fetch_score_growth_metrics(PDO $pdo, string $tenantId, int $clientId, ?array $client = null): array
    {
        $client = $client ?? mf_credit_policy_fetch_client($pdo, $tenantId, $clientId);
        $isVerified = mf_credit_policy_document_is_verified($client['document_verification_status'] ?? '');

        $completedLoansStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM loans
            WHERE tenant_id = ? AND client_id = ? AND loan_status = 'Fully Paid'
        ");
        $completedLoansStmt->execute([$tenantId, $clientId]);

        $activeLoansStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM loans
            WHERE tenant_id = ? AND client_id = ? AND loan_status IN ('Active', 'Overdue')
        ");
        $activeLoansStmt->execute([$tenantId, $clientId]);

        $onTimePaymentsStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM amortization_schedule sched
            INNER JOIN loans l
                ON l.loan_id = sched.loan_id
               AND l.tenant_id = sched.tenant_id
            WHERE l.tenant_id = ?
              AND l.client_id = ?
              AND sched.payment_status = 'Paid'
              AND sched.payment_date IS NOT NULL
              AND sched.payment_date <= sched.due_date
              AND COALESCE(sched.days_late, 0) <= 0
        ");
        $onTimePaymentsStmt->execute([$tenantId, $clientId]);

        $latePaymentsStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM amortization_schedule sched
            INNER JOIN loans l
                ON l.loan_id = sched.loan_id
               AND l.tenant_id = sched.tenant_id
            WHERE l.tenant_id = ?
              AND l.client_id = ?
              AND sched.payment_date IS NOT NULL
              AND (
                    COALESCE(sched.days_late, 0) > 0
                    OR sched.payment_date > sched.due_date
              )
        ");
        $latePaymentsStmt->execute([$tenantId, $clientId]);

        $missedPaymentsStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM amortization_schedule sched
            INNER JOIN loans l
                ON l.loan_id = sched.loan_id
               AND l.tenant_id = sched.tenant_id
            WHERE l.tenant_id = ?
              AND l.client_id = ?
              AND sched.due_date < CURDATE()
              AND sched.payment_status IN ('Pending', 'Overdue', 'Partially Paid')
        ");
        $missedPaymentsStmt->execute([$tenantId, $clientId]);

        return [
            'verified_documents' => $isVerified,
            'completed_loans' => (int) $completedLoansStmt->fetchColumn(),
            'active_loans' => (int) $activeLoansStmt->fetchColumn(),
            'on_time_payments' => (int) $onTimePaymentsStmt->fetchColumn(),
            'late_payments' => (int) $latePaymentsStmt->fetchColumn(),
            'missed_payments' => (int) $missedPaymentsStmt->fetchColumn(),
        ];
    }
}

if (!function_exists('mf_credit_policy_compute_score_snapshot')) {
    function mf_credit_policy_compute_score_snapshot(array $policy, array $client, array $metrics): array
    {
        $scoreGrowth = is_array($policy['score_growth'] ?? null) ? $policy['score_growth'] : [];
        
        // Dynamically fetch starting score from the engine
        $engineStartingScore = 320; // safe fallback
        try {
            global $pdo;
            if (isset($pdo) && !empty($client['tenant_id'])) {
                require_once __DIR__ . '/engines/credit_score_engine.php';
                $scoreEngine = new CreditScoreEngine($pdo, $client['tenant_id']);
                $engineStartingScore = (int) $scoreEngine->getStartingScore();
            }
        } catch (\Throwable $e) {}
        
        $baseScore = max(0, $engineStartingScore);

        $verifiedDocumentsBonus = !empty($metrics['verified_documents'])
            ? max(0, (int) ($scoreGrowth['verified_documents_bonus'] ?? 0))
            : 0;
        $completedLoansCount = max(0, (int) ($metrics['completed_loans'] ?? 0));
        $onTimePaymentsCount = max(0, (int) ($metrics['on_time_payments'] ?? 0));
        $latePaymentsCount = max(0, (int) ($metrics['late_payments'] ?? 0));
        $missedPaymentsCount = max(0, (int) ($metrics['missed_payments'] ?? 0));
        $activeLoansCount = max(0, (int) ($metrics['active_loans'] ?? 0));

        $completedLoansBonus = $completedLoansCount * max(0, (int) ($scoreGrowth['completed_loan_bonus'] ?? 0));
        $onTimePaymentsBonus = $onTimePaymentsCount * max(0, (int) ($scoreGrowth['on_time_payment_bonus'] ?? 0));
        $latePaymentsPenalty = $latePaymentsCount * max(0, (int) ($scoreGrowth['late_payment_penalty'] ?? 0));
        $missedPaymentsPenalty = $missedPaymentsCount * max(0, (int) ($scoreGrowth['missed_payment_penalty'] ?? 0));
        $activeLoansPenalty = $activeLoansCount * max(0, (int) ($scoreGrowth['active_loan_penalty'] ?? 0));

        $totalScore = $baseScore
            + $verifiedDocumentsBonus
            + $completedLoansBonus
            + $onTimePaymentsBonus
            - $latePaymentsPenalty
            - $missedPaymentsPenalty
            - $activeLoansPenalty;
        $totalScore = max(0, (int) round($totalScore));

        $recommendation = mf_credit_policy_score_recommendation($policy, (float) $totalScore);
        $ratingLabel = mf_credit_policy_map_rating_label((string) ($recommendation['label'] ?? ''));

        return [
            'base_score' => $baseScore,
            'verified_documents_bonus' => $verifiedDocumentsBonus,
            'completed_loans_count' => $completedLoansCount,
            'completed_loans_bonus' => $completedLoansBonus,
            'on_time_payments_count' => $onTimePaymentsCount,
            'on_time_payments_bonus' => $onTimePaymentsBonus,
            'late_payments_count' => $latePaymentsCount,
            'late_payments_penalty' => $latePaymentsPenalty,
            'missed_payments_count' => $missedPaymentsCount,
            'missed_payments_penalty' => $missedPaymentsPenalty,
            'active_loans_count' => $activeLoansCount,
            'active_loans_penalty' => $activeLoansPenalty,
            'total_score' => $totalScore,
            'recommendation' => $recommendation,
            'rating_label' => $ratingLabel,
            'document_verified' => !empty($metrics['verified_documents']),
        ];
    }
}

if (!function_exists('mf_credit_policy_compose_score_sync_note')) {
    function mf_credit_policy_compose_score_sync_note(array $snapshot): string
    {
        $parts = [
            'Tenant credit policy score sync.',
            'Base ' . number_format((float) ($snapshot['base_score'] ?? 0), 0) . '.',
        ];

        if (!empty($snapshot['verified_documents_bonus'])) {
            $parts[] = '+' . number_format((float) $snapshot['verified_documents_bonus'], 0) . ' verified docs.';
        }
        if (!empty($snapshot['completed_loans_bonus'])) {
            $parts[] = '+' . number_format((float) $snapshot['completed_loans_bonus'], 0)
                . ' completed loans (' . (int) ($snapshot['completed_loans_count'] ?? 0) . ').';
        }
        if (!empty($snapshot['on_time_payments_bonus'])) {
            $parts[] = '+' . number_format((float) $snapshot['on_time_payments_bonus'], 0)
                . ' on-time payments (' . (int) ($snapshot['on_time_payments_count'] ?? 0) . ').';
        }
        if (!empty($snapshot['late_payments_penalty'])) {
            $parts[] = '-' . number_format((float) $snapshot['late_payments_penalty'], 0)
                . ' late payments (' . (int) ($snapshot['late_payments_count'] ?? 0) . ').';
        }
        if (!empty($snapshot['missed_payments_penalty'])) {
            $parts[] = '-' . number_format((float) $snapshot['missed_payments_penalty'], 0)
                . ' missed schedules (' . (int) ($snapshot['missed_payments_count'] ?? 0) . ').';
        }
        if (!empty($snapshot['active_loans_penalty'])) {
            $parts[] = '-' . number_format((float) $snapshot['active_loans_penalty'], 0)
                . ' active exposure (' . (int) ($snapshot['active_loans_count'] ?? 0) . ').';
        }

        $parts[] = 'Total ' . number_format((float) ($snapshot['total_score'] ?? 0), 0) . '.';

        return implode(' ', $parts);
    }
}

if (!function_exists('mf_credit_policy_store_score_snapshot')) {
    function mf_credit_policy_store_score_snapshot(
        PDO $pdo,
        string $tenantId,
        int $clientId,
        array $snapshot,
        ?array $existingScore = null,
        ?array $ci = null,
        ?int $computedBy = null
    ): ?array {
        $note = mf_credit_policy_compose_score_sync_note($snapshot);
        $ciId = isset($ci['ci_id']) && (int) ($ci['ci_id'] ?? 0) > 0 ? (int) $ci['ci_id'] : null;

        if ($existingScore && !empty($existingScore['score_id'])) {
            $stmt = $pdo->prepare("
                UPDATE credit_scores
                SET ci_id = ?,
                    credit_score = ?,
                    credit_rating = ?,
                    recommended_interest_rate = NULL,
                    computed_by = ?,
                    notes = ?,
                    computation_date = NOW()
                WHERE score_id = ? AND tenant_id = ?
            ");
            $stmt->execute([
                $ciId,
                (int) ($snapshot['total_score'] ?? 0),
                $snapshot['rating_label'] ?? null,
                $computedBy,
                $note,
                (int) $existingScore['score_id'],
                $tenantId,
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO credit_scores (
                    client_id,
                    tenant_id,
                    ci_id,
                    credit_score,
                    credit_rating,
                    max_loan_amount,
                    recommended_interest_rate,
                    computed_by,
                    notes,
                    computation_date
                ) VALUES (?, ?, ?, ?, ?, 0, NULL, ?, ?, NOW())
            ");
            $stmt->execute([
                $clientId,
                $tenantId,
                $ciId,
                (int) ($snapshot['total_score'] ?? 0),
                $snapshot['rating_label'] ?? null,
                $computedBy,
                $note,
            ]);
        }

        return mf_credit_policy_fetch_latest_score($pdo, $tenantId, $clientId);
    }
}

if (!function_exists('mf_credit_policy_ensure_default_score_record')) {
    function mf_credit_policy_ensure_default_score_record(
        PDO $pdo,
        string $tenantId,
        int $clientId,
        ?array $policy = null,
        ?array $client = null,
        ?int $computedBy = null
    ): ?array {
        $existing = mf_credit_policy_fetch_latest_score($pdo, $tenantId, $clientId);
        
        // Always recalculate and update the score to keep it in sync with the engine
        // instead of returning stale data

        $policy = $policy ?? mf_get_tenant_credit_policy($pdo, $tenantId);
        $client = $client ?? mf_credit_policy_fetch_client($pdo, $tenantId, $clientId);

        if (!$client) {
            return null;
        }

        if (!mf_credit_policy_document_is_verified($client['document_verification_status'] ?? '')) {
            return null;
        }

        $ci = mf_credit_policy_fetch_latest_ci($pdo, $tenantId, $clientId);
        $scoreSnapshot = mf_credit_policy_compute_score_snapshot(
            $policy,
            $client,
            mf_credit_policy_fetch_score_growth_metrics($pdo, $tenantId, $clientId, $client)
        );
        $snapshot = mf_credit_policy_compute_limit_snapshot($policy, $client, [
            'total_score' => (int) ($scoreSnapshot['total_score'] ?? 0),
        ], $ci);

        $insert = $pdo->prepare("
            INSERT INTO credit_scores (
                client_id,
                tenant_id,
                ci_id,
                credit_score,
                credit_rating,
                max_loan_amount,
                recommended_interest_rate,
                computed_by,
                notes,
                computation_date
            ) VALUES (?, ?, ?, ?, ?, ?, NULL, ?, ?, NOW())
        ");
        $insert->execute([
            $clientId,
            $tenantId,
            isset($ci['ci_id']) ? (int) $ci['ci_id'] : null,
            (int) ($scoreSnapshot['total_score'] ?? 0),
            $scoreSnapshot['rating_label'] ?? null,
            (float) ($snapshot['computed_limit'] ?? 0),
            $computedBy,
            mf_credit_policy_compose_score_sync_note($scoreSnapshot),
        ]);

        return mf_credit_policy_fetch_latest_score($pdo, $tenantId, $clientId);
    }
}

if (!function_exists('mf_credit_policy_fetch_latest_ci')) {
    function mf_credit_policy_fetch_latest_ci(PDO $pdo, string $tenantId, int $clientId): ?array
    {
        $stmt = $pdo->prepare("
            SELECT ci_id, recommendation, status, investigation_date, completed_at
            FROM credit_investigations
            WHERE tenant_id = ? AND client_id = ? AND status = 'Completed'
            ORDER BY COALESCE(completed_at, investigation_date, created_at) DESC, ci_id DESC
            LIMIT 1
        ");
        $stmt->execute([$tenantId, $clientId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}

if (!function_exists('mf_credit_policy_score_recommendation')) {
    function mf_credit_policy_score_recommendation(array $policy, float $effectiveScore): array
    {
        $effectiveScore = max(0, $effectiveScore);

        $notRecommendedMin = (float) ($policy['score_thresholds']['not_recommended_min_score'] ?? 200);
        $conditionalMin = (float) ($policy['score_thresholds']['conditional_min_score'] ?? 400);
        $recommendedMin = (float) ($policy['score_thresholds']['recommended_min_score'] ?? 600);
        $highlyRecommendedMin = (float) ($policy['score_thresholds']['highly_recommended_min_score'] ?? 800);

        if ($effectiveScore >= $highlyRecommendedMin) {
            return ['label' => 'High Credit Score', 'decision_group' => 'approve'];
        }
        if ($effectiveScore >= $recommendedMin) {
            return ['label' => 'Good Credit Score', 'decision_group' => 'approve'];
        }
        if ($effectiveScore >= $conditionalMin) {
            return ['label' => 'Standard Credit Score', 'decision_group' => 'review'];
        }
        if ($effectiveScore >= $notRecommendedMin) {
            return ['label' => 'Fair Credit Score', 'decision_group' => 'reject'];
        }

        return ['label' => 'At-Risk Credit Score', 'decision_group' => 'reject'];
    }
}

if (!function_exists('mf_credit_policy_score_strength_reference')) {
    function mf_credit_policy_score_strength_reference(array $policy): float
    {
        $topBandThreshold = (float) ($policy['score_thresholds']['highly_recommended_min_score'] ?? 800);
        return max(1.0, $topBandThreshold);
    }
}

if (!function_exists('mf_credit_policy_band_multiplier')) {
    function mf_credit_policy_band_multiplier(array $policy, float $effectiveScore): float
    {
        $recommendation = mf_credit_policy_score_recommendation($policy, $effectiveScore);

        if (($recommendation['decision_group'] ?? '') === 'approve') {
            return (float) ($policy['credit_limit']['approve_band_multiplier'] ?? 1.10);
        }
        if (($recommendation['decision_group'] ?? '') === 'review') {
            return (float) ($policy['credit_limit']['review_band_multiplier'] ?? 1.00);
        }
        if (($recommendation['label'] ?? '') === 'Fair Credit Score') {
            return (float) ($policy['credit_limit']['fair_band_multiplier'] ?? 0.75);
        }

        return (float) ($policy['credit_limit']['at_risk_band_multiplier'] ?? 0.50);
    }
}

if (!function_exists('mf_credit_policy_round_limit')) {
    function mf_credit_policy_round_limit(float $amount, float $roundTo): float
    {
        $amount = max(0, $amount);
        if ($roundTo <= 0) {
            return round($amount, 2);
        }

        $rounded = floor($amount / $roundTo) * $roundTo;
        return round(max(0, $rounded), 2);
    }
}

if (!function_exists('mf_credit_policy_compute_limit_snapshot')) {
    function mf_credit_policy_compute_limit_snapshot(array $policy, array $client, ?array $score, ?array $ci): array
    {
        $monthlyIncome = (float) ($client['monthly_income'] ?? 0);
        
        // Dynamically fetch from engine instead of hardcoding 500
        $defaultScore = 320; // safe fallback just in case
        $bandMultiplier = 1.0;
        $blockedReason = '';
        
        try {
            global $pdo; // Hack for legacy function signature
            if (isset($pdo) && !empty($client['tenant_id'])) {
                require_once __DIR__ . '/engines/credit_score_engine.php';
                require_once __DIR__ . '/engines/credit_limit_engine.php';
                $scoreEngine = new CreditScoreEngine($pdo, $client['tenant_id']);
                $limitEngine = new CreditLimitEngine($pdo, $client['tenant_id']);
                
                $defaultScore = (float) $scoreEngine->getStartingScore();
            }
        } catch (\Throwable $e) {
            // retain fallback if engine fails
        }

        $hasScore = $score !== null && isset($score['total_score']);
        $totalScore = $hasScore
            ? (float) mf_credit_policy_normalize_score_value($score['total_score'] ?? 0, 0, false)
            : $defaultScore;
        $effectiveScore = max(0, $totalScore);
        $scoreRecommendation = mf_credit_policy_score_recommendation($policy, $effectiveScore);
        $bandMultiplier = mf_credit_policy_band_multiplier($policy, $effectiveScore);
        $canComputeLimit = mf_credit_policy_client_has_active_limit($client);
        $blockedReason = $canComputeLimit ? '' : mf_credit_policy_limit_block_reason($client);

        if ($canComputeLimit && (($scoreRecommendation['decision_group'] ?? '') === 'reject')) {
            $canComputeLimit = false;
            $blockedReason = 'Borrower is currently in a reject credit score band.';
        }

        if (!$canComputeLimit) {
            return [
                'can_compute_limit' => false,
                'computed_limit' => 0.0,
                'applied_limit' => 0.0,
                'score_factor' => 1.0,
                'band_multiplier' => $bandMultiplier,
                'effective_score' => $effectiveScore,
                'recommendation_label' => $scoreRecommendation['label'] ?? null,
                'recommendation_group' => $scoreRecommendation['decision_group'] ?? null,
                'used_default_score' => !$hasScore,
                'blocked_reason' => $blockedReason,
            ];
        }

        $rawLimit = $monthlyIncome
            * (float) ($policy['credit_limit']['income_multiplier'] ?? 0)
            * $bandMultiplier;

        $cap = (float) ($policy['credit_limit']['max_credit_limit_cap'] ?? 0);
        if ($cap > 0) {
            $rawLimit = min($rawLimit, $cap);
        }

        $computedLimit = mf_credit_policy_round_limit(
            $rawLimit,
            (float) ($policy['credit_limit']['round_to_nearest'] ?? 0)
        );

        return [
            'can_compute_limit' => true,
            'computed_limit' => $computedLimit,
            'applied_limit' => $computedLimit,
            'score_factor' => 1.0,
            'band_multiplier' => $bandMultiplier,
            'effective_score' => $effectiveScore,
            'recommendation_label' => $scoreRecommendation['label'] ?? null,
            'recommendation_group' => $scoreRecommendation['decision_group'] ?? null,
            'used_default_score' => !$hasScore,
            'blocked_reason' => '',
        ];
    }
}

if (!function_exists('mf_sync_client_credit_profile')) {
    function mf_sync_client_credit_profile(PDO $pdo, string $tenantId, int $clientId): array
    {
        $policy = mf_get_tenant_credit_policy($pdo, $tenantId);
        $client = mf_credit_policy_fetch_client($pdo, $tenantId, $clientId);

        if (!$client) {
            throw new RuntimeException('Client not found for credit policy evaluation.');
        }

        $score = mf_credit_policy_fetch_latest_score($pdo, $tenantId, $clientId);
        $ci = mf_credit_policy_fetch_latest_ci($pdo, $tenantId, $clientId);
        $scoreSnapshot = mf_credit_policy_compute_score_snapshot(
            $policy,
            $client,
            mf_credit_policy_fetch_score_growth_metrics($pdo, $tenantId, $clientId, $client)
        );
        $shouldPersistScore = $score !== null || mf_credit_policy_document_is_verified($client['document_verification_status'] ?? '');

        if ($shouldPersistScore) {
            $storedScoreValue = $score !== null
                ? (int) mf_credit_policy_normalize_score_value($score['total_score'] ?? 0, 0, false)
                : null;
            $expectedScoreValue = (int) ($scoreSnapshot['total_score'] ?? 0);
            $storedRating = (string) ($score['credit_rating'] ?? '');
            $expectedRating = (string) ($scoreSnapshot['rating_label'] ?? '');

            if ($score === null || $storedScoreValue !== $expectedScoreValue || $storedRating !== $expectedRating) {
                $score = mf_credit_policy_store_score_snapshot(
                    $pdo,
                    $tenantId,
                    $clientId,
                    $scoreSnapshot,
                    $score,
                    $ci
                );
            }
        }
        $scoreForLimit = $score ?: [
            'total_score' => (int) ($scoreSnapshot['total_score'] ?? 0),
            'credit_rating' => $scoreSnapshot['rating_label'] ?? null,
        ];
        $snapshot = mf_credit_policy_compute_limit_snapshot($policy, $client, $scoreForLimit, $ci);

        $currentLimit = (float) ($client['credit_limit'] ?? 0);

        // We no longer automatically overwrite the active credit_limit here.
        // It strictly follows the Engines -> Potential Limit -> Approval flow.

        $client['last_seen_credit_limit'] = $currentLimit;
        $client['credit_limit'] = $currentLimit;

        return [
            'policy' => $policy,
            'client' => $client,
            'score' => $scoreForLimit,
            'ci' => $ci,
            'limit' => $snapshot,
        ];
    }
}

if (!function_exists('mf_credit_policy_sync_tenant_clients')) {
    function mf_credit_policy_sync_tenant_clients(PDO $pdo, string $tenantId): int
    {
        $stmt = $pdo->prepare("
            SELECT client_id
            FROM clients
            WHERE tenant_id = ? AND deleted_at IS NULL
            ORDER BY client_id ASC
        ");
        $stmt->execute([$tenantId]);
        $clientIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $count = 0;
        foreach ($clientIds as $clientId) {
            mf_sync_client_credit_profile($pdo, $tenantId, (int) $clientId);
            $count++;
        }

        return $count;
    }
}

if (!function_exists('mf_credit_policy_format_amount')) {
    function mf_credit_policy_format_amount($amount): string
    {
        return 'PHP ' . number_format((float) $amount, 2);
    }
}

if (!function_exists('mf_credit_policy_compose_note')) {
    function mf_credit_policy_compose_note(string $decision, array $reasons, array $evaluation): string
    {
        $limit = $evaluation['computed_credit_limit'];
        $amount = $evaluation['approved_amount'];
        $score = $evaluation['score']['total_score'];
        $ciRecommendation = $evaluation['ci']['recommendation'];

        $parts = [];
        if ($decision === 'approve') {
            $parts[] = 'Credit policy auto-approved this application.';
        } elseif ($decision === 'review') {
            $parts[] = 'Credit policy routed this application for manual review.';
        } else {
            $parts[] = 'Credit policy rejected this application.';
        }

        if ($amount !== null && $amount > 0) {
            $parts[] = 'Suggested amount: ' . mf_credit_policy_format_amount($amount) . '.';
        }
        if ($limit !== null && $limit > 0) {
            $parts[] = 'Computed credit limit: ' . mf_credit_policy_format_amount($limit) . '.';
        }
        if ($score !== null) {
            $parts[] = 'Latest score: ' . number_format((float) $score, 0) . '.';
        }
        if ($ciRecommendation !== null && $ciRecommendation !== '') {
            $parts[] = 'CI recommendation: ' . $ciRecommendation . '.';
        }
        if (!empty($reasons)) {
            $parts[] = 'Reasons: ' . implode('; ', $reasons) . '.';
        }

        return trim(implode(' ', $parts));
    }
}

if (!function_exists('mf_credit_policy_decode_application_data')) {
    function mf_credit_policy_decode_application_data($raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }
}

if (!function_exists('mf_evaluate_application_policy')) {
    function mf_evaluate_application_policy(PDO $pdo, string $tenantId, int $applicationId): array
    {
        $stmt = $pdo->prepare("
            SELECT la.application_id, la.application_number, la.application_status, la.requested_amount,
                   la.approved_amount, la.loan_term_months, la.application_data, la.client_id, la.product_id,
                   c.monthly_income, c.employment_status, c.client_status, c.document_verification_status,
                   c.credit_limit, c.last_seen_credit_limit,
                   lp.product_name, lp.min_amount, lp.max_amount, lp.minimum_credit_score
            FROM loan_applications la
            JOIN clients c ON c.client_id = la.client_id AND c.tenant_id = la.tenant_id
            JOIN loan_products lp ON lp.product_id = la.product_id AND lp.tenant_id = la.tenant_id
            WHERE la.tenant_id = ? AND la.application_id = ?
            LIMIT 1
        ");
        $stmt->execute([$tenantId, $applicationId]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$application) {
            throw new RuntimeException('Application not found for credit policy evaluation.');
        }

        $profile = mf_sync_client_credit_profile($pdo, $tenantId, (int) $application['client_id']);
        $policy = $profile['policy'];
        $client = $profile['client'];
        $score = $profile['score'];
        $ci = $profile['ci'];
        $limitSnapshot = $profile['limit'];

        $requestedAmount = round((float) ($application['requested_amount'] ?? 0), 2);
        $clientLimit = (float) ($limitSnapshot['applied_limit'] ?? 0);
        $productMinAmount = (float) ($application['min_amount'] ?? 0);
        $productMaxAmount = (float) ($application['max_amount'] ?? 0);
        $productMinimumScore = (float) mf_credit_policy_normalize_score_value($application['minimum_credit_score'] ?? 0);
        
        // Dynamically fetch from engine instead of hardcoding 500
        $defaultScore = 320;
        try {
            require_once __DIR__ . '/engines/credit_score_engine.php';
            $scoreEngine = new CreditScoreEngine($pdo, $tenantId);
            $defaultScore = (float) $scoreEngine->getStartingScore();
        } catch (\Throwable $e) {}

        $hasLatestScore = $score !== null && isset($score['total_score']);
        $totalScore = $hasLatestScore
            ? (float) mf_credit_policy_normalize_score_value($score['total_score'] ?? 0)
            : null;
        $effectiveScore = $totalScore !== null ? $totalScore : $defaultScore;
        $scoreRecommendation = mf_credit_policy_score_recommendation($policy, $effectiveScore);
        $ciRecommendation = trim((string) ($ci['recommendation'] ?? ''));

        $rejectReasons = [];
        $reviewReasons = [];

        if (empty($policy['eligibility']['allow_no_minimum_income'])
            && (float) ($policy['eligibility']['min_monthly_income'] ?? 0) > 0
            && (float) ($client['monthly_income'] ?? 0) < (float) ($policy['eligibility']['min_monthly_income'] ?? 0)
        ) {
            $rejectReasons[] = 'Monthly income is below the minimum requirement.';
        }

        $allowedEmployment = $policy['eligibility']['allowed_employment_statuses'] ?? [];
        $employmentStatus = trim((string) ($client['employment_status'] ?? ''));
        $documentStatus = trim((string) ($client['document_verification_status'] ?? ''));
        if (!empty($allowedEmployment) && !in_array($employmentStatus, $allowedEmployment, true)) {
            $rejectReasons[] = 'Employment status is not allowed by the current credit policy.';
        }

        if (!empty($policy['product_checks']['use_product_min_amount']) && $requestedAmount < $productMinAmount) {
            $rejectReasons[] = 'Requested amount is below the selected product minimum.';
        }
        if (!empty($policy['product_checks']['use_product_max_amount']) && $requestedAmount > $productMaxAmount) {
            $rejectReasons[] = 'Requested amount exceeds the selected product maximum.';
        }

        if (($scoreRecommendation['decision_group'] ?? '') === 'reject') {
            $rejectReasons[] = 'Credit score is classified as ' . (string) ($scoreRecommendation['label'] ?? 'At-Risk Credit Score') . ' by the current policy.';
        } elseif (($scoreRecommendation['decision_group'] ?? '') === 'review') {
            $reviewReasons[] = 'Credit score is classified as Standard Credit Score and requires manual review.';
        }

        if (!empty($policy['product_checks']['use_product_minimum_credit_score']) && $effectiveScore < $productMinimumScore) {
            $rejectReasons[] = 'Credit score is below the product minimum credit score.';
        }

        $ciRequired = !empty($policy['ci_rules']['require_ci']);

        if ($ciRequired && !$ci) {
            $reviewReasons[] = 'A completed credit investigation is required.';
        }

        if ($ci) {
            if ($ciRecommendation === 'Not Recommended') {
                $rejectReasons[] = 'Credit investigation recommends not proceeding.';
            } elseif (in_array($ciRecommendation, (array) ($policy['ci_rules']['review_ci_values'] ?? []), true)) {
                $reviewReasons[] = 'Credit investigation requires manual review.';
            } elseif ($ciRecommendation !== ''
                && !in_array($ciRecommendation, (array) ($policy['ci_rules']['auto_approve_ci_values'] ?? []), true)
            ) {
                $reviewReasons[] = 'Credit investigation result is outside the auto-approval list.';
            }
        }

        $approvedAmount = null;
        if ($clientLimit > 0) {
            $approvedAmount = min($requestedAmount, $clientLimit);
            if (!empty($policy['product_checks']['use_product_max_amount'])) {
                $approvedAmount = min($approvedAmount, $productMaxAmount);
            }
            $approvedAmount = round($approvedAmount, 2);
        }

        if ($approvedAmount !== null && !empty($policy['product_checks']['use_product_min_amount']) && $approvedAmount < $productMinAmount) {
            $rejectReasons[] = 'Computed eligible amount falls below the product minimum.';
        }

        if ($approvedAmount !== null && $requestedAmount > $approvedAmount) {
            $reviewReasons[] = 'Requested amount exceeds the computed credit limit.';
        }

        $decision = 'approve';
        $decisionReasons = [];
        if (!empty($rejectReasons)) {
            $decision = 'reject';
            $decisionReasons = array_values(array_unique($rejectReasons));
            $approvedAmount = null;
        } elseif (!empty($reviewReasons)) {
            $decision = 'review';
            $decisionReasons = array_values(array_unique($reviewReasons));
        }

        if ($decision === 'approve' && $approvedAmount === null) {
            $decision = 'review';
            $decisionReasons[] = 'Approved amount could not be computed yet.';
        }

        $newStatus = $decision === 'approve'
            ? 'Approved'
            : ($decision === 'review' ? 'Pending Review' : 'Rejected');

        $evaluation = [
            'decision' => $decision,
            'new_status' => $newStatus,
            'requested_amount' => $requestedAmount,
            'approved_amount' => $approvedAmount,
            'computed_credit_limit' => $clientLimit > 0 ? round($clientLimit, 2) : null,
            'reasons' => $decisionReasons,
            'policy' => $policy,
            'client' => [
                'client_id' => (int) ($application['client_id'] ?? 0),
                'monthly_income' => round((float) ($client['monthly_income'] ?? 0), 2),
                'employment_status' => $employmentStatus,
                'client_status' => (string) ($client['client_status'] ?? ''),
                'document_verification_status' => $documentStatus,
                'credit_limit' => round((float) ($client['credit_limit'] ?? 0), 2),
            ],
            'score' => [
                'score_id' => $score !== null ? (int) ($score['score_id'] ?? 0) : null,
                'total_score' => $totalScore,
                'effective_score' => $effectiveScore,
                'used_default_score' => !$hasLatestScore,
                'credit_rating' => $score['credit_rating'] ?? null,
                'policy_recommendation' => $scoreRecommendation['label'] ?? null,
            ],
            'ci' => [
                'ci_id' => $ci !== null ? (int) ($ci['ci_id'] ?? 0) : null,
                'status' => $ci['status'] ?? null,
                'recommendation' => $ciRecommendation !== '' ? $ciRecommendation : null,
            ],
            'product' => [
                'product_id' => (int) ($application['product_id'] ?? 0),
                'product_name' => (string) ($application['product_name'] ?? ''),
                'min_amount' => round($productMinAmount, 2),
                'max_amount' => round($productMaxAmount, 2),
                'minimum_credit_score' => round($productMinimumScore, 2),
            ],
            'evaluated_at' => date('Y-m-d H:i:s'),
        ];

        return [
            'application' => $application,
            'evaluation' => $evaluation,
        ];
    }
}

if (!function_exists('mf_apply_application_policy')) {
    function mf_apply_application_policy(PDO $pdo, string $tenantId, int $applicationId, array $options = []): array
    {
        $result = mf_evaluate_application_policy($pdo, $tenantId, $applicationId);
        $application = $result['application'];
        $evaluation = $result['evaluation'];
        $employeeId = isset($options['employee_id']) && $options['employee_id'] !== null ? (int) $options['employee_id'] : null;
        $sessionUserId = isset($options['session_user_id']) && $options['session_user_id'] !== null ? (int) $options['session_user_id'] : null;
        $now = date('Y-m-d H:i:s');

        $applicationData = mf_credit_policy_decode_application_data($application['application_data'] ?? null);
        $applicationData['credit_policy'] = $evaluation;
        $encodedData = json_encode($applicationData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encodedData === false) {
            $encodedData = '{}';
        }

        if ($evaluation['decision'] === 'approve') {
            $note = mf_credit_policy_compose_note('approve', $evaluation['reasons'], $evaluation);
            $stmt = $pdo->prepare("
                UPDATE loan_applications
                SET application_status = 'Approved',
                    approved_amount = ?,
                    approved_by = ?,
                    approval_date = ?,
                    approval_notes = ?,
                    reviewed_by = NULL,
                    review_date = NULL,
                    review_notes = NULL,
                    rejected_by = NULL,
                    rejection_date = NULL,
                    rejection_reason = NULL,
                    application_data = ?,
                    updated_at = NOW()
                WHERE tenant_id = ? AND application_id = ?
            ");
            $stmt->execute([
                $evaluation['approved_amount'],
                $employeeId,
                $now,
                $note,
                $encodedData,
                $tenantId,
                $applicationId,
            ]);
            $message = 'Credit policy approved the application.';
            $actionType = 'CREDIT_POLICY_APPROVED';
            $description = $note;
        } elseif ($evaluation['decision'] === 'review') {
            $note = mf_credit_policy_compose_note('review', $evaluation['reasons'], $evaluation);
            $stmt = $pdo->prepare("
                UPDATE loan_applications
                SET application_status = 'Pending Review',
                    approved_amount = ?,
                    reviewed_by = ?,
                    review_date = ?,
                    review_notes = ?,
                    approved_by = NULL,
                    approval_date = NULL,
                    approval_notes = NULL,
                    rejected_by = NULL,
                    rejection_date = NULL,
                    rejection_reason = NULL,
                    application_data = ?,
                    updated_at = NOW()
                WHERE tenant_id = ? AND application_id = ?
            ");
            $stmt->execute([
                $evaluation['approved_amount'],
                $employeeId,
                $now,
                $note,
                $encodedData,
                $tenantId,
                $applicationId,
            ]);
            $message = 'Credit policy sent the application to Pending Review.';
            $actionType = 'CREDIT_POLICY_REVIEW';
            $description = $note;
        } else {
            $note = mf_credit_policy_compose_note('reject', $evaluation['reasons'], $evaluation);
            $stmt = $pdo->prepare("
                UPDATE loan_applications
                SET application_status = 'Rejected',
                    approved_amount = NULL,
                    rejected_by = ?,
                    rejection_date = ?,
                    rejection_reason = ?,
                    approved_by = NULL,
                    approval_date = NULL,
                    approval_notes = NULL,
                    reviewed_by = NULL,
                    review_date = NULL,
                    review_notes = NULL,
                    application_data = ?,
                    updated_at = NOW()
                WHERE tenant_id = ? AND application_id = ?
            ");
            $stmt->execute([
                $employeeId,
                $now,
                $note,
                $encodedData,
                $tenantId,
                $applicationId,
            ]);
            $message = 'Credit policy rejected the application.';
            $actionType = 'CREDIT_POLICY_REJECTED';
            $description = $note;
        }

        $audit = $pdo->prepare("
            INSERT INTO audit_logs (user_id, tenant_id, action_type, entity_type, entity_id, description)
            VALUES (?, ?, ?, 'loan_application', ?, ?)
        ");
        $audit->execute([
            $sessionUserId ?: null,
            $tenantId,
            $actionType,
            $applicationId,
            $description,
        ]);

        return [
            'status' => 'success',
            'message' => $message,
            'new_status' => $evaluation['new_status'],
            'approved_amount' => $evaluation['approved_amount'],
            'evaluation' => $evaluation,
        ];
    }
}
