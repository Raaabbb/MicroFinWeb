<?php
/**
 * Credit Score Engine
 * 
 * A tenant-aware, dynamic credit score calculator that reads ALL configuration
 * from the system_settings table. Zero hardcoded values.
 * 
 * Responsibilities:
 *   1. getStartingScore()           — Returns the tenant's initial credit score for new borrowers
 *   2. calculateRepaymentBonus()    — Score increase after a successful repayment cycle
 *   3. calculateLatePenalty()       — Score decrease after a late payment
 *   4. evaluateUpgradeBonuses()     — Milestone bonuses (clean streaks, cycle counts)
 *   5. evaluateDowngradePenalties() — Penalty triggers (overdue thresholds)
 * 
 * This engine is "Logic Only" — it returns score deltas but does NOT save to the database.
 * The calling API file is responsible for persisting the results.
 */

class CreditScoreEngine
{
    private array $core = [];
    private array $upgradeRules = [];
    private array $downgradeRules = [];
    private string $tenantId;

    /**
     * Initialize the engine by loading the tenant's scoring settings from system_settings.
     *
     * @param PDO|mysqli $db          Database connection (supports both PDO and mysqli)
     * @param string     $tenantId    The tenant whose settings to load
     * @throws RuntimeException       If no settings are found for this tenant
     */
    public function __construct($db, string $tenantId)
    {
        $this->tenantId = $tenantId;

        $settingsJson = $this->fetchSettingValue($db, $tenantId, 'policy_console_credit_limits');

        if ($settingsJson === null || $settingsJson === '') {
            throw new RuntimeException(
                "CreditScoreEngine: No 'policy_console_credit_limits' settings found for tenant '{$tenantId}'. "
                . "Please configure scoring in the Admin Panel → Policy Console → Credit & Limits tab."
            );
        }

        $settings = json_decode($settingsJson, true);
        if (!is_array($settings)) {
            throw new RuntimeException(
                "CreditScoreEngine: Invalid JSON in 'policy_console_credit_limits' for tenant '{$tenantId}'."
            );
        }

        $scoringSetup           = $settings['scoring_setup'] ?? [];
        $this->core             = $scoringSetup['core'] ?? [];
        $this->upgradeRules     = $scoringSetup['detailed_rules']['upgrade'] ?? [];
        $this->downgradeRules   = $scoringSetup['detailed_rules']['downgrade'] ?? [];
    }

    // ─── PUBLIC API ──────────────────────────────────────────────────────────────

    /**
     * Get the tenant's starting credit score for new borrowers.
     *
     * @return int
     */
    public function getStartingScore(): int
    {
        return (int) ($this->core['starting_credit_score'] ?? 0);
    }

    /**
     * Calculate the score bonus for a successful repayment cycle.
     *
     * @return array {
     *     'points'  => int,  // Points to add
     *     'reason'  => string,
     * }
     */
    public function calculateRepaymentBonus(): array
    {
        $bonus = (int) ($this->core['repayment_score_bonus'] ?? 0);

        return [
            'points' => $bonus,
            'reason' => "Successful repayment cycle: +{$bonus} points",
        ];
    }

    /**
     * Calculate the score penalty for a late payment.
     *
     * @return array {
     *     'points'  => int,  // Points to subtract (returned as positive number)
     *     'reason'  => string,
     * }
     */
    public function calculateLatePenalty(): array
    {
        $penalty = (int) ($this->core['late_payment_score_penalty'] ?? 0);

        return [
            'points' => $penalty,
            'reason' => "Late payment penalty: -{$penalty} points",
        ];
    }

    /**
     * Evaluate milestone-based upgrade bonuses.
     * 
     * Checks the borrower's repayment history against the tenant's upgrade rules
     * and returns all applicable bonuses.
     *
     * @param int $successfulCycles     Total successful repayment cycles completed
     * @param int $latePaymentsInPeriod Late payments within the review period
     * @param bool $hasActiveOverdue    Whether the borrower currently has overdue loans
     * @return array List of applicable bonuses, each with 'rule', 'points', 'reason'
     */
    public function evaluateUpgradeBonuses(
        int $successfulCycles,
        int $latePaymentsInPeriod,
        bool $hasActiveOverdue
    ): array {
        $bonuses = [];

        // Rule 1: Successful Repayment Cycles milestone
        $cycleRule = $this->upgradeRules['successful_repayment_cycles'] ?? [];
        if (!empty($cycleRule['enabled'])) {
            $requiredCycles = (int) ($cycleRule['required_cycles'] ?? 3);
            $scorePoints    = (int) ($cycleRule['score_points'] ?? 0);

            if ($successfulCycles >= $requiredCycles) {
                $bonuses[] = [
                    'rule'   => 'successful_repayment_cycles',
                    'points' => $scorePoints,
                    'reason' => "Completed {$successfulCycles}/{$requiredCycles} required cycles: +{$scorePoints} points",
                ];
            }
        }

        // Rule 2: Maximum Late Payments Review (clean streak)
        $lateReviewRule = $this->upgradeRules['maximum_late_payments_review'] ?? [];
        if (!empty($lateReviewRule['enabled'])) {
            $maxAllowed  = (int) ($lateReviewRule['maximum_allowed'] ?? 1);
            $scorePoints = (int) ($lateReviewRule['score_points'] ?? 0);

            if ($latePaymentsInPeriod <= $maxAllowed) {
                $bonuses[] = [
                    'rule'   => 'maximum_late_payments_review',
                    'points' => $scorePoints,
                    'reason' => "Late payments ({$latePaymentsInPeriod}) within allowed maximum ({$maxAllowed}): +{$scorePoints} points",
                ];
            }
        }

        // Rule 3: No Active Overdue
        $overdueRule = $this->upgradeRules['no_active_overdue'] ?? [];
        if (!empty($overdueRule['enabled'])) {
            $scorePoints = (int) ($overdueRule['score_points'] ?? 0);

            if (!$hasActiveOverdue) {
                $bonuses[] = [
                    'rule'   => 'no_active_overdue',
                    'points' => $scorePoints,
                    'reason' => "No active overdue loans: +{$scorePoints} points",
                ];
            }
        }

        return $bonuses;
    }

    /**
     * Evaluate penalty triggers for score downgrades.
     *
     * @param int $latePaymentsInPeriod Late payments within the review period
     * @param int $maxOverdueDays       Maximum consecutive overdue days
     * @return array List of applicable penalties, each with 'rule', 'points', 'reason'
     */
    public function evaluateDowngradePenalties(
        int $latePaymentsInPeriod,
        int $maxOverdueDays
    ): array {
        $penalties = [];

        // Rule 1: Late Payments Review (too many late payments)
        $lateRule = $this->downgradeRules['late_payments_review'] ?? [];
        if (!empty($lateRule['enabled'])) {
            $triggerCount = (int) ($lateRule['trigger_count'] ?? 2);
            $scorePoints  = (int) ($lateRule['score_points'] ?? 0);

            if ($latePaymentsInPeriod >= $triggerCount) {
                $penalties[] = [
                    'rule'   => 'late_payments_review',
                    'points' => $scorePoints,
                    'reason' => "Late payments ({$latePaymentsInPeriod}) hit trigger ({$triggerCount}): -{$scorePoints} points",
                ];
            }
        }

        // Rule 2: Overdue Days Threshold
        $overdueRule = $this->downgradeRules['overdue_days_threshold'] ?? [];
        if (!empty($overdueRule['enabled'])) {
            $dayThreshold = (int) ($overdueRule['days'] ?? 15);
            $scorePoints  = (int) ($overdueRule['score_points'] ?? 0);

            if ($maxOverdueDays >= $dayThreshold) {
                $penalties[] = [
                    'rule'   => 'overdue_days_threshold',
                    'points' => $scorePoints,
                    'reason' => "Overdue {$maxOverdueDays} days (threshold: {$dayThreshold}): -{$scorePoints} points",
                ];
            }
        }

        return $penalties;
    }

    /**
     * Calculate the net score change given a borrower's current behavior metrics.
     * This is a convenience method that combines all upgrade and downgrade evaluations.
     *
     * @param int  $successfulCycles
     * @param int  $latePaymentsInPeriod
     * @param bool $hasActiveOverdue
     * @param int  $maxOverdueDays
     * @return array {
     *     'net_change' => int,
     *     'bonuses'    => array,
     *     'penalties'  => array,
     *     'breakdown'  => string,
     * }
     */
    public function calculateNetScoreChange(
        int $successfulCycles,
        int $latePaymentsInPeriod,
        bool $hasActiveOverdue,
        int $maxOverdueDays
    ): array {
        $bonuses   = $this->evaluateUpgradeBonuses($successfulCycles, $latePaymentsInPeriod, $hasActiveOverdue);
        $penalties = $this->evaluateDowngradePenalties($latePaymentsInPeriod, $maxOverdueDays);

        $totalBonus   = array_sum(array_column($bonuses, 'points'));
        $totalPenalty = array_sum(array_column($penalties, 'points'));
        $netChange    = $totalBonus - $totalPenalty;

        $parts = [];
        foreach ($bonuses as $b) {
            $parts[] = "+{$b['points']} ({$b['rule']})";
        }
        foreach ($penalties as $p) {
            $parts[] = "-{$p['points']} ({$p['rule']})";
        }

        return [
            'net_change' => $netChange,
            'bonuses'    => $bonuses,
            'penalties'  => $penalties,
            'breakdown'  => implode(', ', $parts) ?: 'No score changes triggered.',
        ];
    }

    /**
     * Get the full loaded configuration (for audit/snapshot purposes).
     *
     * @return array
     */
    public function getConfigSnapshot(): array
    {
        return [
            'tenant_id'       => $this->tenantId,
            'core'            => $this->core,
            'upgrade_rules'   => $this->upgradeRules,
            'downgrade_rules' => $this->downgradeRules,
            'snapshot_time'   => date('Y-m-d H:i:s'),
        ];
    }

    // ─── PRIVATE HELPERS ─────────────────────────────────────────────────────────

    /**
     * Fetch a single setting_value from system_settings.
     * Supports both PDO and mysqli connections.
     */
    private function fetchSettingValue($db, string $tenantId, string $key): ?string
    {
        if ($db instanceof \PDO) {
            $stmt = $db->prepare('SELECT setting_value FROM system_settings WHERE tenant_id = ? AND setting_key = ? LIMIT 1');
            $stmt->execute([$tenantId, $key]);
            $val = $stmt->fetchColumn();
            return $val !== false ? (string) $val : null;
        }

        if ($db instanceof \mysqli) {
            $stmt = $db->prepare('SELECT setting_value FROM system_settings WHERE tenant_id = ? AND setting_key = ? LIMIT 1');
            $stmt->bind_param('ss', $tenantId, $key);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
            return $row ? (string) $row['setting_value'] : null;
        }

        throw new RuntimeException('CreditScoreEngine: Unsupported database connection type.');
    }
}
