<?php
/**
 * Credit Limit Engine
 * 
 * A tenant-aware, dynamic credit limit calculator that reads ALL configuration
 * from the system_settings table. Zero hardcoded values.
 * 
 * Responsibilities:
 *   1. calculateInitialLimit()     — Onboarding: Income × Initial Limit %
 *   2. calculateProgressiveGrowth() — Returning: Base Growth + (Micro % × Points Above Band)
 *   3. identifyScoreBand()         — Finds which band a score falls into
 * 
 * This engine is "Logic Only" — it returns numbers but does NOT save to the database.
 * The calling API file is responsible for persisting the results.
 */

class CreditLimitEngine
{
    private array $scoreBands = [];
    private array $limitAssignment = [];
    private array $scoringSetup = [];
    private string $tenantId;

    /**
     * Initialize the engine by loading the tenant's credit settings from system_settings.
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
                "CreditLimitEngine: No 'policy_console_credit_limits' settings found for tenant '{$tenantId}'. "
                . "Please configure credit limits in the Admin Panel → Policy Console → Credit & Limits tab."
            );
        }

        $settings = json_decode($settingsJson, true);
        if (!is_array($settings)) {
            throw new RuntimeException(
                "CreditLimitEngine: Invalid JSON in 'policy_console_credit_limits' for tenant '{$tenantId}'."
            );
        }

        $this->scoreBands      = $settings['score_bands']['rows'] ?? [];
        $this->limitAssignment  = $settings['limit_assignment'] ?? [];
        $this->scoringSetup     = $settings['scoring_setup'] ?? [];
    }

    // ─── PUBLIC API ──────────────────────────────────────────────────────────────

    /**
     * Calculate the initial credit limit for a brand-new borrower.
     * 
     * Formula: Monthly Income × (Initial Limit % / 100)
     *
     * @param float $monthlyIncome  The borrower's verified monthly income
     * @return array {
     *     'limit'                => float,   // The calculated limit
     *     'initial_limit_percent' => float,  // The % used (for audit trail)
     *     'starting_score'       => int,     // The tenant's starting credit score
     *     'score_band'           => array,   // The band this score falls into
     * }
     */
    public function calculateInitialLimit(float $monthlyIncome): array
    {
        $initialPercent = (float) ($this->limitAssignment['initial_limit_percent_of_income'] ?? 0);
        $startingScore  = (int) ($this->scoringSetup['core']['starting_credit_score'] ?? 0);

        if ($initialPercent <= 0) {
            return [
                'limit'                 => 0.0,
                'initial_limit_percent' => 0.0,
                'starting_score'        => $startingScore,
                'score_band'            => null,
                'reason'                => 'Initial limit percentage is not configured or is zero.',
            ];
        }

        if ($monthlyIncome <= 0) {
            return [
                'limit'                 => 0.0,
                'initial_limit_percent' => $initialPercent,
                'starting_score'        => $startingScore,
                'score_band'            => null,
                'reason'                => 'Monthly income is zero or negative.',
            ];
        }

        $rawLimit = $monthlyIncome * ($initialPercent / 100);

        // Apply lending cap if enabled
        $rawLimit = $this->applyLendingCap($rawLimit);

        $band = $this->identifyScoreBand($startingScore);

        return [
            'limit'                 => round($rawLimit, 2),
            'initial_limit_percent' => $initialPercent,
            'starting_score'        => $startingScore,
            'score_band'            => $band,
            'reason'                => "Income ₱" . number_format($monthlyIncome, 2) 
                                     . " × {$initialPercent}% = ₱" . number_format($rawLimit, 2),
        ];
    }

    /**
     * Calculate the progressive growth for a returning borrower.
     * 
     * Formula: 
     *   Total Growth % = Base Growth % + (Points Above Band Min × Micro % Per Point)
     *   New Limit = Current Limit × (1 + Total Growth % / 100)
     *
     * @param float $currentLimit   The borrower's current active credit limit
     * @param float $creditScore    The borrower's current credit score
     * @return array {
     *     'new_limit'        => float,
     *     'growth_percent'   => float,
     *     'base_growth'      => float,
     *     'micro_bonus'      => float,
     *     'points_above_min' => int,
     *     'score_band'       => array|null,
     * }
     */
    public function calculateProgressiveGrowth(float $currentLimit, float $creditScore): array
    {
        $band = $this->identifyScoreBand($creditScore);

        if ($band === null) {
            return [
                'new_limit'        => $currentLimit,
                'growth_percent'   => 0.0,
                'base_growth'      => 0.0,
                'micro_bonus'      => 0.0,
                'points_above_min' => 0,
                'score_band'       => null,
                'reason'           => "Score {$creditScore} does not fall into any configured band.",
            ];
        }

        $baseGrowth     = (float) ($band['base_growth_percent'] ?? 0);
        $microPercent   = (float) ($band['micro_percent_per_point'] ?? 0);
        $bandMin        = (float) ($band['min_score'] ?? 0);
        $pointsAbove    = max(0, $creditScore - $bandMin);
        $microBonus     = $pointsAbove * $microPercent;
        $totalGrowth    = $baseGrowth + $microBonus;

        $newLimit = $currentLimit * (1 + ($totalGrowth / 100));

        // Apply lending cap
        $newLimit = $this->applyLendingCap($newLimit);

        return [
            'new_limit'        => round($newLimit, 2),
            'growth_percent'   => round($totalGrowth, 4),
            'base_growth'      => $baseGrowth,
            'micro_bonus'      => round($microBonus, 4),
            'points_above_min' => (int) $pointsAbove,
            'score_band'       => $band,
            'reason'           => "Band '{$band['label']}': {$baseGrowth}% base + ({$pointsAbove} pts × {$microPercent}%) = " 
                                . round($totalGrowth, 2) . "% total growth",
        ];
    }

    /**
     * Identify which score band a given credit score falls into.
     *
     * @param float $score
     * @return array|null The matching band row, or null if none match
     */
    public function identifyScoreBand(float $score): ?array
    {
        foreach ($this->scoreBands as $band) {
            $min = (float) ($band['min_score'] ?? 0);
            $max = $band['max_score'] !== null ? (float) $band['max_score'] : PHP_FLOAT_MAX;

            if ($score >= $min && $score <= $max) {
                return $band;
            }
        }

        return null;
    }

    /**
     * Get the tenant's starting credit score.
     *
     * @return int
     */
    public function getStartingCreditScore(): int
    {
        return (int) ($this->scoringSetup['core']['starting_credit_score'] ?? 0);
    }

    /**
     * Get the full loaded configuration (for storing as a snapshot in policy_metadata).
     *
     * @return array
     */
    public function getConfigSnapshot(): array
    {
        return [
            'tenant_id'        => $this->tenantId,
            'score_bands'      => $this->scoreBands,
            'limit_assignment'  => $this->limitAssignment,
            'scoring_setup'     => $this->scoringSetup,
            'snapshot_time'     => date('Y-m-d H:i:s'),
        ];
    }

    // ─── PRIVATE HELPERS ─────────────────────────────────────────────────────────

    /**
     * Apply the default lending cap if it is enabled in the tenant's settings.
     */
    private function applyLendingCap(float $limit): float
    {
        $useCap    = !empty($this->limitAssignment['use_default_lending_cap']);
        $capAmount = (float) ($this->limitAssignment['default_lending_cap_amount'] ?? 0);

        if ($useCap && $capAmount > 0 && $limit > $capAmount) {
            return $capAmount;
        }

        return $limit;
    }

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

        throw new RuntimeException('CreditLimitEngine: Unsupported database connection type.');
    }
}
