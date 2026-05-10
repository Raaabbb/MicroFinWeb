<?php

if (!function_exists('policy_console_system_defaults')) {
    function policy_console_system_defaults(): array
    {
        return [
            'credit_limits' => [
                'scoring_setup' => [
                    'core' => [
                        'starting_credit_score' => 320,
                        'repayment_score_bonus' => 5,
                        'late_payment_score_penalty' => 12,
                    ],
                    'detailed_rules' => [
                        'upgrade' => [
                            'successful_repayment_cycles' => [
                                'enabled' => true,
                                'required_cycles' => 3,
                                'score_points' => 5,
                            ],
                            'maximum_late_payments_review' => [
                                'enabled' => true,
                                'maximum_allowed' => 1,
                                'review_period_days' => 90,
                                'score_points' => 5,
                            ],
                            'no_active_overdue' => [
                                'enabled' => true,
                                'review_period_days' => 0,
                                'score_points' => 5,
                            ],
                        ],
                        'downgrade' => [
                            'late_payments_review' => [
                                'enabled' => true,
                                'trigger_count' => 2,
                                'review_period_days' => 90,
                                'score_points' => 12,
                            ],
                            'overdue_days_threshold' => [
                                'enabled' => true,
                                'days' => 15,
                                'score_points' => 25,
                            ],
                        ],
                    ],
                ],
                'score_bands' => [
                    'rows' => [
                        [
                            'id' => 'band_at_risk',
                            'label' => 'At-Risk',
                            'min_score' => 50,
                            'max_score' => 249,
                            'base_growth_percent' => 1.0,
                            'micro_percent_per_point' => 0.020,
                        ],
                        [
                            'id' => 'band_entry',
                            'label' => 'Entry',
                            'min_score' => 250,
                            'max_score' => 449,
                            'base_growth_percent' => 5.0,
                            'micro_percent_per_point' => 0.034,
                        ],
                        [
                            'id' => 'band_standard',
                            'label' => 'Standard',
                            'min_score' => 450,
                            'max_score' => 649,
                            'base_growth_percent' => 10.0,
                            'micro_percent_per_point' => 0.025,
                        ],
                        [
                            'id' => 'band_plus',
                            'label' => 'Plus',
                            'min_score' => 650,
                            'max_score' => 849,
                            'base_growth_percent' => 15.0,
                            'micro_percent_per_point' => 0.020,
                        ],
                        [
                            'id' => 'band_premium',
                            'label' => 'Premium',
                            'min_score' => 850,
                            'max_score' => null,
                            'base_growth_percent' => 18.0,
                            'micro_percent_per_point' => 0.010,
                        ],
                    ],
                ],
                'limit_assignment' => [
                    'initial_limit_percent_of_income' => 40,
                    'use_default_lending_cap' => false,
                    'default_lending_cap_amount' => 0,
                    'apply_score_changes_immediately' => true,
                ],
            ],
            'decision_rules' => [
                'workflow' => [
                    'approval_mode' => 'semi_automatic',
                ],
                'decision_rules' => [
                    'demographics' => [
                        'age_enabled' => true,
                        'min_age' => 21,
                        'max_age' => 65,
                        'employment_tenure_enabled' => false,
                        'min_employment_months' => 6,
                        'residency_tenure_enabled' => false,
                        'min_residency_months' => 6,
                        'employment_status_enabled' => true,
                        'eligible_statuses' => [
                            'full_time', 'part_time', 'contract', 'freelancer', 
                            'self_employed', 'casual', 'retired', 'student', 'unemployed'
                        ],
                    ],
                    'affordability' => [
                        'income_enabled' => false,
                        'min_monthly_income' => 10000,
                        'dti_enabled' => false,
                        'max_dti_percentage' => 45,
                        'pti_enabled' => false,
                        'max_pti_percentage' => 20,
                    ],
                    'guardrails' => [
                        'score_thresholds_enabled' => true,
                        'auto_reject_floor' => 50,
                        'hard_approval_threshold' => 900,
                        'cooling_period_enabled' => false,
                        'rejected_cooling_days' => 30,
                    ],
                    'exposure' => [
                        'multiple_active_loans_enabled' => true,
                        'guarantor_required_enabled' => false,
                        'guarantor_required_above_amount' => null,
                        'collateral_required_enabled' => false,
                        'collateral_required_above_amount' => null,
                    ],
                ],
            ],
            'compliance_documents' => [
                'document_requirements' => array_map(
                    static function (array $category): array {
                        return [
                            'category_key' => $category['category_key'],
                            'label' => $category['label'],
                            'requirement' => $category['default_requirement'],
                            'document_options' => [],
                        ];
                    },
                    policy_console_compliance_document_categories()
                ),
            ],
        ];
    }
}

if (!function_exists('policy_console_credit_limits_system_defaults')) {
    function policy_console_credit_limits_system_defaults(): array
    {
        $defaults = policy_console_system_defaults();
        return isset($defaults['credit_limits']) && is_array($defaults['credit_limits'])
            ? $defaults['credit_limits']
            : [];
    }
}

if (!function_exists('policy_console_decision_rules_system_defaults')) {
    function policy_console_decision_rules_system_defaults(): array
    {
        $defaults = policy_console_system_defaults();
        return isset($defaults['decision_rules']) && is_array($defaults['decision_rules'])
            ? $defaults['decision_rules']
            : [];
    }
}

if (!function_exists('policy_console_compliance_documents_system_defaults')) {
    function policy_console_compliance_documents_system_defaults(): array
    {
        $defaults = policy_console_system_defaults();
        return isset($defaults['compliance_documents']) && is_array($defaults['compliance_documents'])
            ? $defaults['compliance_documents']
            : [];
    }
}

if (!function_exists('policy_console_compliance_document_excluded_names')) {
    function policy_console_compliance_document_excluded_names(): array
    {
        return [
            'Valid ID Front',
            'Valid ID Back'
        ];
    }
}

if (!function_exists('policy_console_compliance_document_categories')) {
    function policy_console_compliance_document_categories(): array
    {
        return [
            [
                'category_key' => 'identity_document',
                'label' => 'Identity Document',
                'default_requirement' => 'required',
                'allowed_document_names' => [
                    'National ID (PhilID/ePhilID)',
                    'Passport',
                    'Driver\'s License',
                    'UMID',
                    'SSS ID',
                    'GSIS e-Card',
                    'PRC ID',
                    'Postal ID',
                    'Seaman\'s Book / SIRB',
                    'Senior Citizen ID',
                    'PWD ID',
                    'Voter\'s ID',
                    'NBI Clearance',
                    'Police Clearance',
                    'TIN ID',
                    'School ID',
                    'Company ID',
                    'Barangay ID',
                    'OFW ID',
                    'OWWA ID',
                    'IBP ID',
                    'Government Office / GOCC ID',
                ],
            ]
        ];
    }
}
