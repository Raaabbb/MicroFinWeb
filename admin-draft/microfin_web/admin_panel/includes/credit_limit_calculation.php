<?php
/**
 * Credit Limit Calculation Engine
 * 
 * Demonstrates the architecture where:
 * 1. Rules & Requirements sit as a preliminary "Gatekeeper".
 * 2. Credit Limits Engine calculates the baseline limits.
 * 3. Exposure configurations cap limits.
 */

// We assume system defaults or database configurations are passed or available globally.
// Adjust the require path as needed if used outside of admin context.
require_once __DIR__ . '/policy_console_system_defaults.php';

if (!function_exists('calculate_credit_limit')) {
    /**
     * Calculates the dynamically assigned credit limit based on institutional policy controls.
     *
     * @param float $monthlyIncome Stated or verified user's monthly income
     * @param bool $isFirstTimeBorrower Whether the user currently has no previous loan history
     * @param float $creditScore The user's internal calculated credit score
     * @param float $userTotalDebt The user's active concurrent debts across other institutions
     * @return array [ 'status' => 'approved'|'rejected', 'limit' => float, 'reason' => string ]
     */
    function calculate_credit_limit(float $monthlyIncome, bool $isFirstTimeBorrower, float $creditScore, float $userTotalDebt = 0.0): array
    {
        // 1. Fetch system defaults & configs (In a real system, this fetches from DB)
        $systemSettings = policy_console_credit_limits_system_defaults();
        
        $limitAssignment = $systemSettings['limit_assignment'] ?? [];
        $rulesAffordability = $systemSettings['decision_rules']['decision_rules']['affordability'] ?? [];
        $rulesExposure = $systemSettings['decision_rules']['decision_rules']['exposure'] ?? [];
        $rulesGuardrails = $systemSettings['decision_rules']['decision_rules']['guardrails'] ?? [];
        
        // Configuration Variables
        $initialLimitPercent = (float)($limitAssignment['initial_limit_percent_of_income'] ?? 45);
        $minimumScoreRequired = (float)($rulesGuardrails['auto_reject_floor'] ?? 250); 
        $useDefaultLendingCap = !empty($limitAssignment['use_default_lending_cap']);
        $defaultLendingCapAmount = (float)($limitAssignment['default_lending_cap_amount'] ?? 0);
        
        $dtiEnabled = !empty($rulesAffordability['max_dti_enabled']);
        $maxDtiAllowed = (float)($rulesAffordability['max_dti_percentage'] ?? 40); // e.g., 40%
        
        // 2. GATEKEEPER: RULES & REQUIREMENTS
        // Rejection 1: Credit Score Floor
        if ($creditScore < $minimumScoreRequired) {
            return [
                'status' => 'rejected',
                'limit' => 0.00,
                'reason' => 'Credit score falls below minimum required threshold.'
            ];
        }
        
        // Rejection 2: DTI (Debt-to-Income)
        // Ensure that existing debt + expected new monthly burden isn't too high.
        // We use the simpler version here where existing debt / income > DTI = reject.
        if ($dtiEnabled && $monthlyIncome > 0) {
            $userDti = ($userTotalDebt / $monthlyIncome) * 100;
            if ($userDti > $maxDtiAllowed) {
                return [
                    'status' => 'rejected',
                    'limit' => 0.00,
                    'reason' => 'User DTI (' . round($userDti, 1) . '%) exceeds the strict maximum allowance (' . $maxDtiAllowed . '%).'
                ];
            }
        }
        
        // 3. CALCULATOR: CREDIT LIMITS BASE (How much do they actually get?)
        $proposedLimit = 0.0;
        if ($isFirstTimeBorrower) {
            // First-time users get an initial baseline of their income
            $proposedLimit = $monthlyIncome * ($initialLimitPercent / 100);
        } else {
            // For returning users, use Score Bands matrix configuration
            // (Placeholder limit for returning good users)
            $proposedLimit = $monthlyIncome * 0.70; 
        }
        
        // 4. GLOBAL CEILINGS (Applies to everyone)
        if ($useDefaultLendingCap && $defaultLendingCapAmount > 0 && $proposedLimit > $defaultLendingCapAmount) {
            $proposedLimit = $defaultLendingCapAmount;
        }
        
        return [
            'status' => 'approved',
            'limit' => round($proposedLimit, 2),
            'reason' => 'Limit successfully calculated'
        ];
    }
}


