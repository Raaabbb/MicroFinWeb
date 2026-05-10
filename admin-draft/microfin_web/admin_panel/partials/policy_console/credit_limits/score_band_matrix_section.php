<section id="policy-credit-limits-bands">
    <div class="policy-blueprint-panel policy-score-band-panel">
        <?php
        $default_score_bands = $system_defaults['score_bands'] ?? [];
        $is_score_bands_default = (($policy_console_credit_limits_safe['score_bands'] ?? []) == $default_score_bands);
        ?>
        <div class="policy-blueprint-panel-head" style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <span class="policy-blueprint-panel-kicker">Score Band Matrix</span>
                <h5 style="margin-bottom: 0;">Ordered score bands</h5>
                <p class="text-muted" style="margin-top: 4px;">Define score bands and the growth settings used after onboarding. Leave the top-band max blank to keep it open-ended, like 850+.</p>
            </div>
            
            <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 8px;">
                <div>
                    <?php if ($is_score_bands_default): ?>
                        <span style="font-size: 12px; padding: 4px 8px; border-radius: 12px; background: var(--bg-surface-secondary); color: var(--text-muted); border: 1px solid var(--border-color);">
                            System Default
                        </span>
                    <?php else: ?>
                        <span style="font-size: 12px; padding: 4px 8px; border-radius: 12px; background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe;">
                            Modified
                        </span>
                    <?php endif; ?>
                </div>
                <div class="policy-score-band-toolbar" style="margin-top: 0;">
                    <button type="button" class="btn btn-outline" id="policy-score-band-cancel-btn" style="display: none;">Cancel</button>
                    <!-- <button type="button" class="btn btn-outline" id="policy-score-band-customize-btn">Customize</button> -->
                    <button type="button" class="btn btn-outline" data-policy-score-band-add style="display: none;">
                        <span class="material-symbols-rounded">add</span>
                        Add Score Band
                    </button>
                </div>
            </div>
        </div>

        <style>
            #policy-score-band-table tbody tr:hover {
                cursor: pointer;
                background-color: rgba(var(--primary-rgb), 0.02);
            }
        </style>

        <div class="policy-band-table-wrap" data-policy-score-band-wrap data-next-index="<?php echo count($policy_console_score_band_rows); ?>">
            <table class="policy-band-table" id="policy-score-band-table">
                <thead>
                    <tr>
                        <th>Band <?php echo $policy_console_help('Display label for the score band.'); ?></th>
                        <th>Min <?php echo $policy_console_help('Lowest score included in the band.'); ?></th>
                        <th>Max <?php echo $policy_console_help('Highest score included in the band. Leave blank for an open-ended top band such as 850+.'); ?></th>
                        <th>Base Growth % <?php echo $policy_console_help('Default per-cycle growth rate before score-position sensitivity is added.'); ?></th>
                        <th>Micro % / Point <?php echo $policy_console_help('Additional growth sensitivity applied for every point above the band minimum.'); ?></th>
                        <th class="policy-band-col-actions" style="display: none;"></th>
                    </tr>
                </thead>
                <tbody data-policy-score-band-body>
                    <?php foreach ($policy_console_score_band_rows as $policy_console_row): ?>
                        <tr class="policy-band-row" data-policy-score-band-row data-policy-row-index="<?php echo $policy_console_row_index; ?>">
                            <td>
                                <input type="hidden" name="pcc_score_band_id[]" value="<?php echo htmlspecialchars((string)($policy_console_row['id'] ?? ('band_' . ($policy_console_row_index + 1)))); ?>">
                                <input type="text" class="form-control" name="pcc_score_band_label[]" value="<?php echo htmlspecialchars((string)($policy_console_row['label'] ?? '')); ?>" maxlength="60" required readonly>
                            </td>
                            <td><input type="number" class="form-control" name="pcc_score_band_min[]" min="0" value="<?php echo htmlspecialchars((string)($policy_console_row['min_score'] ?? 0)); ?>" required readonly></td>
                            <td><input type="number" class="form-control" name="pcc_score_band_max[]" min="0" value="<?php echo htmlspecialchars((string)($policy_console_row['max_score'] ?? '')); ?>" placeholder="850+" readonly></td>
                            <td><input type="number" class="form-control" name="pcc_score_band_base_growth[]" min="0" max="100" step="0.001" value="<?php echo htmlspecialchars((string)($policy_console_row['base_growth_percent'] ?? 0)); ?>" required readonly></td>
                            <td><input type="number" class="form-control" name="pcc_score_band_micro_growth[]" min="0" max="10" step="0.001" value="<?php echo htmlspecialchars((string)($policy_console_row['micro_percent_per_point'] ?? 0)); ?>" required readonly></td>
                            <td class="policy-band-actions policy-band-col-actions" style="display: none;">
                                <button type="button" class="btn btn-ghost-danger" data-policy-score-band-delete aria-label="Delete score band">
                                    <span class="material-symbols-rounded">close</span>
                                </button>
                            </td>
                        </tr>
                        <?php $policy_console_row_index++; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="policy-empty-note" data-policy-score-band-empty <?php echo count($policy_console_score_band_rows) > 0 ? 'hidden' : ''; ?>>No score bands added yet.</p>
        </div>
    </div>
</section>
