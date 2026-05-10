<?php
// Simulator for Policy Console Test Cases
?>
<!-- Floating Action Buttons -->
<div class="policy-fabs-container">
    <button type="button" id="policy-simulator-fab" class="policy-fab policy-fab-secondary" aria-label="Open Policy Simulator">
        <svg style="width: 18px; height: 18px; fill: currentColor;" viewBox="0 0 24 24"><path d="M7 14c-1.66 0-3 1.34-3 3 0 1.31-1.16 2-2 2 .92 1.22 2.49 2 4 2 2.21 0 4-1.79 4-4 0-1.66-1.34-3-3-3zm13.71-9.37l-1.34-1.34a2 2 0 0 0-2.83 0L2 19.83V23h3.17l14.54-14.54a2 2 0 0 0 0-2.83z"></path></svg>
        <span>Test Simulator</span>
    </button>
    <button type="button" id="global-save-policy-btn" class="policy-fab policy-fab-primary" aria-label="Save Current Tab">
        <svg style="width: 18px; height: 18px; fill: currentColor;" viewBox="0 0 24 24"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"></path></svg>
        <span>Save Changes</span>
    </button>
</div>

<!-- Simulator Backdrop -->
<div id="policy-simulator-backdrop" class="simulator-backdrop"></div>

<!-- Slide-out Simulator Panel -->
<div id="policy-simulator-panel" class="simulator-panel">
    <div class="simulator-header">
        <h3 style="margin: 0; font-size: 16px; color: var(--text-main);">Live Policy Evaluator</h3>
        <button type="button" id="close-simulator" class="close-btn">&times;</button>
    </div>
    <div class="simulator-body">
        <p class="text-muted" style="font-size: 13px; margin-bottom: 20px;">
            Enter testing parameters to see if the <strong>unsaved</strong> active rules in the console will Approve, Reject, or Flag this mock applicant.
        </p>
        
        <div style="background: var(--bg-body); padding: 12px; border-radius: 8px; border: 1px solid var(--border-color); margin-bottom: 16px;">
            <strong style="display: block; font-size: 11px; text-transform: uppercase; color: var(--text-muted); margin-bottom: 12px;">Applicant Profile</strong>
            <div class="simulator-field">
                <label>Age (Years)</label>
                <input type="number" id="sim_age" class="form-control" value="25">
            </div>
            <div class="simulator-field">
                <label>Credit Score</label>
                <input type="number" id="sim_score" class="form-control" value="650">
            </div>
            <div class="simulator-field">
                <label>Monthly Gross Income (?)</label>
                <input type="number" id="sim_income" class="form-control" value="25000">
            </div>
            <div class="simulator-field">
                <label>Existing Monthly Debt (?)</label>
                <input type="number" id="sim_debt" class="form-control" value="5000">
            </div>
        </div>

        <div style="background: var(--bg-body); padding: 12px; border-radius: 8px; border: 1px solid var(--border-color); margin-bottom: 16px;">
            <strong style="display: block; font-size: 11px; text-transform: uppercase; color: var(--text-muted); margin-bottom: 12px;">Loan Request</strong>
            <div class="simulator-field">
                <label>Active Loans Held</label>
                <input type="number" id="sim_active_loans" class="form-control" value="0">
            </div>
            <div class="simulator-field">
                <label>Requested Installment (?)</label>
                <input type="number" id="sim_installment" class="form-control" value="2000">
            </div>
        </div>

        <button type="button" id="run-simulation-btn" class="btn btn-primary" style="width: 100%; justify-content: center;">
            <svg style="width: 16px; height: 16px; margin-right: 6px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;" viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
            Run Evaluation
        </button>

        <!-- Results Box -->
        <div id="simulation-results" style="display: none; margin-top: 20px; padding: 15px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-card);">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                <h4 id="sim-outcome" style="margin: 0; font-size: 15px;">Pending</h4>
            </div>
            <ul id="sim-log" style="font-size: 13px; padding-left: 20px; margin: 0; color: var(--text-main); line-height: 1.5;"></ul>
        </div>
    </div>
</div>

<style>
.policy-fabs-container {
    position: fixed;
    bottom: 30px;
    right: 30px;
    display: flex;
    gap: 12px;
    z-index: 1000;
}
.policy-fab {
    border: none;
    border-radius: 50px;
    padding: 12px 24px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: transform 0.2s, box-shadow 0.2s, background-color 0.2s;
}
.policy-fab-secondary {
    background-color: var(--bg-card, #ffffff);
    color: var(--text-main, #1e293b);
    border: 1px solid var(--border-color, #e2e8f0);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}
.policy-fab-secondary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
    background-color: var(--bg-body, #f8fafc);
    color: var(--primary-color, #3b82f6);
    border-color: var(--primary-color, #3b82f6);
}
.policy-fab-primary {
    background-color: var(--primary-color, #3b82f6);
    color: #ffffff;
}
.policy-fab-primary:hover {
    transform: translateY(-2px);
    filter: brightness(1.1);
}
.simulator-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(15, 23, 42, 0.4);
    backdrop-filter: blur(3px);
    -webkit-backdrop-filter: blur(3px);
    z-index: 999990;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s ease, visibility 0.3s ease;
}
.simulator-backdrop.is-active {
    opacity: 1;
    visibility: visible;
}
.simulator-panel {
    position: fixed;
    top: 0;
    right: -400px;
    width: 350px;
    height: 100vh;
    background-color: var(--bg-card, #ffffff);
    box-shadow: -4px 0 24px rgba(0,0,0,0.15);
    z-index: 999991;
    transition: right 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    flex-direction: column;
    border-left: 1px solid var(--border-color);
}
.simulator-panel.is-open {
    right: 0;
}
.simulator-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid var(--border-color);
    background: var(--bg-body);
}
.simulator-body {
    padding: 20px;
    flex-grow: 1;
    overflow-y: auto;
}
.close-btn {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: var(--text-muted);
    padding: 0;
    line-height: 1;
}
.close-btn:hover {
    color: var(--danger-color, #dc2626);
}
.simulator-field {
    margin-bottom: 12px;
}
.simulator-field:last-child {
    margin-bottom: 0;
}
.simulator-field label {
    display: block;
    font-size: 13px;
    margin-bottom: 4px;
    color: var(--text-main);
}
.simulator-field input {
    width: 100%;
    box-sizing: border-box;
}
.sim-log-item {
    margin-bottom: 6px;
}
.sim-log-success { color: #16a34a; }
.sim-log-error { color: #dc2626; }
.sim-log-info { color: #3b82f6; }
</style>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const fab = document.getElementById("policy-simulator-fab");
    const globalSaveBtn = document.getElementById("global-save-policy-btn");
    const panel = document.getElementById("policy-simulator-panel");
    const closeBtn = document.getElementById("close-simulator");
    const backdrop = document.getElementById("policy-simulator-backdrop");
    const runBtn = document.getElementById("run-simulation-btn");

    // Reparent elements to document.body to break free from any stacking contexts
    // This allows the enormous z-index to overlay EVERYTHING including sidebar & topnav.
    if (backdrop && backdrop.parentNode !== document.body) {
        document.body.appendChild(backdrop);
    }
    if (panel && panel.parentNode !== document.body) {
        document.body.appendChild(panel);
    }

    if (globalSaveBtn) {
        globalSaveBtn.addEventListener("click", function() {
            // Find the currently active tab panel form
            const activePanel = document.querySelector('.credit-policy-tab-panel:not([hidden])');
            if (activePanel) {
                const form = activePanel.querySelector('form');
                if (form) {
                    const unsavedManager = window.policyConsoleUnsavedManager;
                    if (unsavedManager && typeof unsavedManager.markSubmitting === 'function') {
                        unsavedManager.markSubmitting();
                    } else {
                        window._policyConsoleSubmitting = true;
                        window._isPolicyFormDirty = false;
                    }

                    const originalHTML = this.innerHTML;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin" style="margin-right: 6px;"></i><span>Saving...</span>';
                    this.style.pointerEvents = 'none';
                    this.style.opacity = '0.8';
                    form.submit();
                    setTimeout(() => {
                        this.innerHTML = originalHTML;
                        this.style.pointerEvents = 'auto';
                        this.style.opacity = '1';
                    }, 3000);
                } else {
                    alert('No form config to save in this tab.');
                }
            }
        });
    }

    if (!fab || !panel) return;

    function closeSimulator() {
        panel.classList.remove("is-open");
        if (backdrop) backdrop.classList.remove("is-active");
    }

    fab.addEventListener("click", () => {
        panel.classList.add("is-open");
        if (backdrop) backdrop.classList.add("is-active");
    });

    closeBtn.addEventListener("click", closeSimulator);
    if (backdrop) {
        backdrop.addEventListener("click", closeSimulator);
    }

    // Run Logic
    runBtn.addEventListener("click", () => {
        const mockAge = parseFloat(document.getElementById("sim_age").value) || 0;
        const mockScore = parseFloat(document.getElementById("sim_score").value) || 0;
        const mockIncome = parseFloat(document.getElementById("sim_income").value) || 0;
        const mockDebt = parseFloat(document.getElementById("sim_debt").value) || 0;
        const mockActiveLoans = parseInt(document.getElementById("sim_active_loans").value, 10) || 0;
        const mockInstallment = parseFloat(document.getElementById("sim_installment").value) || 0;

        const resultsBox = document.getElementById("simulation-results");
        const outcomeHeader = document.getElementById("sim-outcome");
        const logList = document.getElementById("sim-log");

        logList.innerHTML = "";
        resultsBox.style.display = "block";

        let isRejected = false;
        const logs = [];

        // Helper to grab value from console DOM
        const getVal = (name) => {
            const el = document.querySelector(`input[name="${name}"]`);
            return el ? el.value : null;
        };

        // --- EVALUATE DEMOGRAPHICS ---
        const ageEnabled = getVal("pcdr_age_enabled") === "1";
        if (ageEnabled) {
            const minAge = parseFloat(getVal("pcdr_min_age")) || 18;
            const maxAge = parseFloat(getVal("pcdr_max_age")) || 65;
            if (mockAge < minAge || mockAge > maxAge) {
                isRejected = true;
                logs.push(`<li class="sim-log-item sim-log-error">? <strong>Age limits:</strong> ${mockAge} is outside the allowed range (${minAge}-${maxAge}).</li>`);
            } else {
                logs.push(`<li class="sim-log-item sim-log-success">? <strong>Age limits:</strong> Passed.</li>`);
            }
        }

        // --- EVALUATE AFFORDABILITY ---
        const incomeEnabled = getVal("pcdr_income_enabled") === "1";
        if (incomeEnabled) {
            const minIncome = parseFloat(getVal("pcdr_min_monthly_income")) || 0;
            if (mockIncome < minIncome) {
                isRejected = true;
                logs.push(`<li class="sim-log-item sim-log-error">? <strong>Minimum Income:</strong> ?${mockIncome} is below required ?${minIncome}.</li>`);
            } else {
                logs.push(`<li class="sim-log-item sim-log-success">? <strong>Minimum Income:</strong> Passed.</li>`);
            }
        }

        const dtiEnabled = getVal("pcdr_dti_enabled") === "1";
        if (dtiEnabled && mockIncome > 0) {
            const maxDTI = parseFloat(getVal("pcdr_max_dti_percentage")) || 100;
            const projectedDTI = ((mockDebt + mockInstallment) / mockIncome) * 100;
            if (projectedDTI > maxDTI) {
                isRejected = true;
                logs.push(`<li class="sim-log-item sim-log-error">? <strong>DTI Check:</strong> Projected DTI ${projectedDTI.toFixed(1)}% exceeds max limit of ${maxDTI}%.</li>`);
            } else {
                logs.push(`<li class="sim-log-item sim-log-success">? <strong>DTI Check:</strong> Passed (${projectedDTI.toFixed(1)}%).</li>`);
            }
        }

        const ptiEnabled = getVal("pcdr_pti_enabled") === "1";
        if (ptiEnabled && mockIncome > 0) {
            const maxPTI = parseFloat(getVal("pcdr_max_pti_percentage")) || 100;
            const projectedPTI = (mockInstallment / mockIncome) * 100;
            if (projectedPTI > maxPTI) {
                isRejected = true;
                logs.push(`<li class="sim-log-item sim-log-error">? <strong>PTI Check:</strong> Installment PTI ${projectedPTI.toFixed(1)}% exceeds max limit of ${maxPTI}%.</li>`);
            } else {
                logs.push(`<li class="sim-log-item sim-log-success">? <strong>PTI Check:</strong> Passed (${projectedPTI.toFixed(1)}%).</li>`);
            }
        }

        // --- EVALUATE GUARDRAILS ---
        const scoresEnabled = getVal("pcdr_score_thresholds_enabled") === "1";
        if (scoresEnabled) {
            const minScore = parseFloat(getVal("pcdr_auto_reject_floor")) || 0;
            const hardApproveScore = parseFloat(getVal("pcdr_hard_approval_threshold")) || 9999;
            
            if (mockScore < minScore) {
                isRejected = true;
                logs.push(`<li class="sim-log-item sim-log-error">? <strong>Credit Score:</strong> ${mockScore} pts is below the automatic rejection floor (${minScore}).</li>`);
            } else if (mockScore >= hardApproveScore) {
                logs.push(`<li class="sim-log-item sim-log-info">? <strong>Credit Score:</strong> ${mockScore} pts triggers Hard Approval criteria!</li>`);
            } else {
                logs.push(`<li class="sim-log-item sim-log-success">? <strong>Credit Score:</strong> Eligible for standard processing.</li>`);
            }
        }

        // --- EVALUATE EXPOSURE ---
        const multipleLoansEnabled = getVal("pcdr_multiple_active_loans_enabled") === "1";
        if (!multipleLoansEnabled && mockActiveLoans > 0) {
            isRejected = true;
            logs.push(`<li class="sim-log-item sim-log-error">? <strong>Concurrent Borrowing:</strong> Requires exactly 0 active loans. User has ${mockActiveLoans}.</li>`);
        } else if (multipleLoansEnabled && mockActiveLoans > 0) {
            logs.push(`<li class="sim-log-item sim-log-info">? <strong>Concurrent Borrowing:</strong> Allowed to stack multiple loans. (Check credit limits).</li>`);
        }

        // Output Result
        if (isRejected) {
            outcomeHeader.innerText = "Application Rejected";
            outcomeHeader.style.color = "#dc2626";
            resultsBox.style.backgroundColor = "rgba(220, 38, 38, 0.05)";
            resultsBox.style.borderColor = "rgba(220, 38, 38, 0.2)";
        } else {
            outcomeHeader.innerText = "Application Approved";
            outcomeHeader.style.color = "#16a34a";
            resultsBox.style.backgroundColor = "rgba(22, 163, 74, 0.05)";
            resultsBox.style.borderColor = "rgba(22, 163, 74, 0.2)";
            if (logs.length === 0) {
                logs.push(`<li class="sim-log-item sim-log-success">? Passed all active validations (or no validations enabled).</li>`);
            }
        }

        logList.innerHTML = logs.join("");
    });
});
</script>
