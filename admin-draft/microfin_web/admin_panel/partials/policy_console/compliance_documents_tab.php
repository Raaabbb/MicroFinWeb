<?php
$policy_console_compliance_config = isset($policy_console_compliance_documents) && is_array($policy_console_compliance_documents)
    ? $policy_console_compliance_documents
    : policy_console_compliance_documents_system_defaults();
$policy_console_compliance_rows = $policy_console_compliance_config['document_requirements'] ?? [];
$policy_console_required_count = count(array_filter(
    $policy_console_compliance_rows,
    static fn(array $row): bool => ($row['requirement'] ?? 'not_needed') === 'required'
));
$policy_console_accepted_count = 0;
foreach ($policy_console_compliance_rows as $policy_console_row) {
    foreach ((array)($policy_console_row['document_options'] ?? []) as $policy_console_option) {
        if (!empty($policy_console_option['is_accepted'])) {
            $policy_console_accepted_count++;
        }
    }
}
$policy_console_help = static function (string $text, string ...$label): string {
    $labelText = $label[0] ?? 'More info';
    return '<span class="policy-help" tabindex="0" role="button" aria-label="'
        . htmlspecialchars($labelText, ENT_QUOTES, 'UTF-8')
        . '" data-help="'
        . htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
        . '">!</span>';
};
?>
<style>
.policy-governance-options {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.policy-governance-pill {
    cursor: pointer;
    margin: 0;
}
.policy-governance-pill input[type="checkbox"] {
    display: none;
}
.policy-governance-pill__label {
    display: inline-block;
    padding: 6px 12px;
    border: 1px solid var(--input-border, #4a5568);
    background-color: transparent;
    color: var(--text-muted, #a0aec0);
    border-radius: 4px;
    font-size: 13px;
    font-weight: 500;
    user-select: none;
    transition: all 0.2s ease;
}
.policy-governance-pill input[type="checkbox"]:checked + .policy-governance-pill__label {
    background-color: var(--primary-color, #0d6efd);
    border-color: var(--primary-color, #0d6efd);
    color: #ffffff;
}
.policy-governance-pill input[type="checkbox"]:disabled + .policy-governance-pill__label {
    opacity: 0.4;
    cursor: not-allowed;
}
</style>

<form method="POST" action="admin.php" class="policy-tab-form">
    <input type="hidden" name="action" value="save_policy_console_compliance_documents">
    <input type="hidden" name="credit_policy_tab" value="compliance_documents">

    <div class="policy-compact-stack">
        <section class="policy-compact-card">
            <div class="policy-save-row">
                <div class="policy-compact-toolbar-copy">
                    <h3>Required Documents</h3>
                    <p class="text-muted">Keep document governance, accepted submission types cleanly separated from scoring and collections.</p>
                </div>
            </div>

            <div class="policy-metric-grid">
                <div class="policy-metric-card">
                    <span>Governed Categories</span>
                    <strong><?php echo number_format(count($policy_console_compliance_rows)); ?></strong>
                    <small>Rows currently present in the governance matrix.</small>
                </div>
                <div class="policy-metric-card">
                    <span>Required Categories</span>
                    <strong><?php echo number_format($policy_console_required_count); ?></strong>
                    <small>Categories that must be submitted by default.</small>
                </div>
                <div class="policy-metric-card">
                    <span>Accepted Document Types</span>
                    <strong><?php echo number_format($policy_console_accepted_count); ?></strong>
                    <small>Accepted document options across all configured rows.</small>
                </div>
            </div>
        </section>

        <section class="policy-compact-card">
            <div class="policy-compact-card-head">
                <div class="policy-compact-card-title">
                    <h4>Governance Matrix Editor</h4>
                    <p class="text-muted">Edit requirement status and accepted document types from one table instead of scattering document rules across multiple tabs.</p>
                </div>
            </div>

            <div class="policy-console-table-wrap">
                <table class="policy-console-table policy-governance-table">
                    <colgroup>
                        <col class="policy-governance-col-category">
                        <col class="policy-governance-col-documents">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Category <?php echo $policy_console_help('Document category controlled by this governance row.'); ?></th>
                            <th>Accepted Document Types <?php echo $policy_console_help('Mark which uploaded document types count as valid submissions for the selected category.'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($policy_console_compliance_rows as $policy_console_row): ?>
                            <?php
                            $policy_console_category_key = (string)($policy_console_row['category_key'] ?? '');
                            $policy_console_row_disabled = false; // Requirement logic removed, identity is always active
                            ?>
                            <tr class="<?php echo $policy_console_row_disabled ? 'policy-governance-row-muted' : ''; ?>">
                                <td>
                                    <div class="policy-table-title"><?php echo htmlspecialchars((string)($policy_console_row['label'] ?? 'Document Category')); ?></div>
                                    <div class="policy-table-subtext"><?php echo $policy_console_row_disabled ? 'Currently excluded from the default required-document set.' : 'Active in the tenant document-governance baseline.'; ?></div>
                                </td>
                                <td>
                                    <div class="policy-governance-options">
                                        <?php foreach ((array)($policy_console_row['document_options'] ?? []) as $policy_console_option): ?>
                                            <label class="policy-governance-pill <?php echo $policy_console_row_disabled ? 'is-disabled' : ''; ?>">
                                                <input
                                                    type="checkbox"
                                                    class="compliance-doc-checkbox"
                                                    name="pcd_docs[<?php echo htmlspecialchars($policy_console_category_key); ?>][]"
                                                    value="<?php echo htmlspecialchars((string)($policy_console_option['document_name'] ?? '')); ?>"
                                                    <?php echo !empty($policy_console_option['is_accepted']) ? 'checked' : ''; ?>
                                                    <?php echo $policy_console_row_disabled ? 'disabled' : ''; ?>
                                                >
                                                <span class="policy-governance-pill__label"><?php echo htmlspecialchars((string)($policy_console_option['document_name'] ?? 'Document Type')); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Floating save removed in favor of global saving action -->
    </div>
</form>

<script>
// Empty script block - Requirement logic removed
</script>
