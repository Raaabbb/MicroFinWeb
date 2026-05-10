document.addEventListener('DOMContentLoaded', () => {
    if ('scrollRestoration' in window.history) {
        window.history.scrollRestoration = 'manual';
    }

    const htmlElement = document.documentElement;

    const companyNameInput = document.getElementById('company-name');
    const companyNameDisplay = document.querySelector('.company-name-display');
    const primaryColorInput = document.getElementById('picker-primary');

    const settingsForm = document.getElementById('settings-form');
    const saveBtn = document.getElementById('save-settings');

    const toggleBooking = document.getElementById('toggle-booking_system');
    const toggleRegistration = document.getElementById('toggle-user_registration');
    const toggleMaintenance = document.getElementById('toggle-maintenance_mode');
    const toggleEmails = document.getElementById('toggle-email_notifications');
    const toggleWebsite = document.getElementById('toggle-public_website_enabled');

    const navItems = Array.from(document.querySelectorAll('.sidebar-nav .nav-item[data-target]'));
    const viewSections = Array.from(document.querySelectorAll('.view-section'));
    const pageTitle = document.getElementById('page-title');
    const tabBtns = Array.from(document.querySelectorAll('.tab-btn'));
    const previewStage = document.getElementById('preview-stage');
    const previewButtons = Array.from(document.querySelectorAll('.preview-btn'));
    const previewScreens = Array.from(document.querySelectorAll('.preview-screen'));
    const logoInput = document.getElementById('logo_file');
    const extractPaletteBtn = document.getElementById('extract-palette-btn');
    const syncBtn = document.getElementById('sync-btn');
    const existingLogoPathInput = document.getElementById('existing-logo-path');
    const setupAlert = document.querySelector('.dashboard-setup-alert');
    const setupAlertToggle = document.querySelector('[data-setup-alert-toggle]');
    const loanProductsForm = document.getElementById('loan-products-form');
    const loanProductsModalBackdrop = document.querySelector('.loan-products-modal-backdrop');
    const loanProductsModalPanel = document.getElementById('loan-products-form-panel');
    const loanProductsModalShell = loanProductsModalPanel ? loanProductsModalPanel.querySelector('[data-loan-products-modal]') : null;
    const loanPreviewRoot = document.querySelector('[data-loan-preview]');
    const loanPreviewAmountInput = document.getElementById('loan-preview-amount');
    const loanPreviewTermInput = document.getElementById('loan-preview-term');
    const loanProductTypeSelect = document.getElementById('loan-product-type-select');
    const loanCustomProductTypeWrap = document.getElementById('loan-custom-product-type-wrap');
    const loanCustomProductTypeInput = document.getElementById('loan-custom-product-type');
    const viewsContainer = document.querySelector('.views-container');
    const receiptPeriodSelect = document.getElementById('receipt-period');
    const receiptPeriodFields = Array.from(document.querySelectorAll('[data-receipt-period-field]'));
    const creditLimitRulesForm = document.getElementById('credit-limit-rules-form');
    const creditLimitRulesSeed = document.getElementById('credit-limit-rules-seed');
    const creditLimitRulesPayload = document.getElementById('credit-limit-rules-payload');
    const creditLimitRulesContainer = document.getElementById('credit-category-rules');
    const creditLimitRulesAddButton = document.getElementById('credit-add-category-rule');
    const creditScoringForm = document.getElementById('credit-scoring-form');
    const creditMinimumScoreInput = document.getElementById('credit-minimum-score');
    const creditAutoRejectBelowInput = document.getElementById('credit-auto-reject-below');
    const creditRequireCiInput = document.getElementById('credit-require-ci');
    const creditPresetButtons = Array.from(document.querySelectorAll('[data-credit-preset]'));
    const creditWeightTotalBadge = document.getElementById('credit-weight-total-badge');
    const creditWeightTotalValue = document.getElementById('credit-weight-total-value');
    const creditWeightTotalMessage = document.getElementById('credit-weight-total-message');
    const creditSummaryMinScore = document.getElementById('credit-summary-min-score');
    const creditSummaryAutoReject = document.getElementById('credit-summary-auto-reject');
    const creditSummaryCi = document.getElementById('credit-summary-ci');
    const creditSummaryWeightStatus = document.getElementById('credit-summary-weight-status');
    const creditScoringPolicyNote = document.getElementById('credit-scoring-policy-note');
    const creditOverviewMinScore = document.getElementById('credit-overview-min-score');
    const creditOverviewApproval = document.getElementById('credit-overview-approval');
    const creditOverviewBaseLimit = document.getElementById('credit-overview-base-limit');
    const creditOverviewCi = document.getElementById('credit-overview-ci');
    const creditWorkflowInputs = Array.from(document.querySelectorAll('input[name="credit_approval_mode"]'));
    const creditBaseLimitInput = document.getElementById('credit-base-limit');
    const creditMinCompletedLoansInput = document.getElementById('credit-min-completed-loans');
    const creditMaxLatePaymentsInput = document.getElementById('credit-max-late-payments');
    const creditIncreaseTypeInput = document.getElementById('credit-increase-type');
    const creditIncreaseValueInput = document.getElementById('credit-increase-value');
    const creditAbsoluteMaxLimitInput = document.getElementById('credit-absolute-max-limit');
    const creditSummaryWorkflow = document.getElementById('credit-summary-workflow');
    const creditSummaryBaseLimit = document.getElementById('credit-summary-base-limit');
    const creditSummaryUpgrade = document.getElementById('credit-summary-upgrade');
    const creditSummaryIncrease = document.getElementById('credit-summary-increase');
    const creditSummaryInitialLogic = document.getElementById('credit-summary-initial-logic');
    const creditSummaryCategories = document.getElementById('credit-summary-categories');
    const creditPreviewCategoryInput = document.getElementById('credit-preview-category');
    const creditPreviewIncomeInput = document.getElementById('credit-preview-income');
    const creditPreviewIncomeDisplay = document.getElementById('credit-preview-income-display');
    const creditPreviewCompletedLoansInput = document.getElementById('credit-preview-completed-loans');
    const creditPreviewLatePaymentsInput = document.getElementById('credit-preview-late-payments');
    const creditPreviewLimitOutput = document.getElementById('credit-preview-limit-output');
    const creditPreviewLimitNote = document.getElementById('credit-preview-limit-note');
    const creditPreviewLimitFill = document.getElementById('credit-preview-limit-fill');
    const creditPreviewUpgradeStatus = document.getElementById('credit-preview-upgrade-status');
    const creditPreviewUpgradeNote = document.getElementById('credit-preview-upgrade-note');
    const creditPreviewNextLimitOutput = document.getElementById('credit-preview-next-limit-output');
    const creditPreviewNextLimitNote = document.getElementById('credit-preview-next-limit-note');
    const creditWeightInputs = {
        income: document.getElementById('credit-weight-income'),
        employment: document.getElementById('credit-weight-employment'),
        creditHistory: document.getElementById('credit-weight-credit-history'),
        collateral: document.getElementById('credit-weight-collateral'),
        character: document.getElementById('credit-weight-character'),
        business: document.getElementById('credit-weight-business'),
    };
    const creditWeightDisplays = {
        income: {
            value: document.getElementById('credit-weight-display-income'),
            bar: document.getElementById('credit-weight-bar-income'),
        },
        employment: {
            value: document.getElementById('credit-weight-display-employment'),
            bar: document.getElementById('credit-weight-bar-employment'),
        },
        creditHistory: {
            value: document.getElementById('credit-weight-display-credit-history'),
            bar: document.getElementById('credit-weight-bar-credit-history'),
        },
        collateral: {
            value: document.getElementById('credit-weight-display-collateral'),
            bar: document.getElementById('credit-weight-bar-collateral'),
        },
        character: {
            value: document.getElementById('credit-weight-display-character'),
            bar: document.getElementById('credit-weight-bar-character'),
        },
        business: {
            value: document.getElementById('credit-weight-display-business'),
            bar: document.getElementById('credit-weight-bar-business'),
        },
    };
    const personalProfileForm = document.getElementById('personal-profile-form');
    const personalPasswordInput = document.getElementById('personal-password');
    const personalPasswordConfirmInput = document.getElementById('personal-password-confirm');
    const personalPasswordPanel = document.querySelector('[data-personal-password-panel]');
    const personalPasswordStrengthFill = document.getElementById('personal-password-strength-fill');
    const personalPasswordStrengthLabel = document.getElementById('personal-password-strength-label');
    const personalPasswordMatch = document.getElementById('personal-password-match');
    const personalProfileSubmit = document.getElementById('personal-profile-submit');
    const personalPasswordRuleItems = Array.from(document.querySelectorAll('[data-personal-password-rule]'));
    const personalPasswordToggleButtons = Array.from(document.querySelectorAll('[data-password-toggle]'));
    const personalEmailInput = document.querySelector('[data-personal-email-input]');
    const personalEmailToggle = document.querySelector('[data-personal-email-toggle]');
    const personalEmailToggleText = personalEmailToggle ? personalEmailToggle.querySelector('[data-personal-email-toggle-text]') : null;
    const personalEmailChangeRequested = document.getElementById('personal-email-change-requested');
    const personalEmailHint = document.getElementById('personal-email-hint');
    const personalEmailActions = document.getElementById('personal-email-actions');
    const personalEmailCancelButton = document.getElementById('personal-email-cancel');
    const personalEmailStatus = document.getElementById('personal-email-status');
    const personalEmailOtpPanel = document.getElementById('personal-email-otp-panel');
    const personalEmailOtpCode = document.getElementById('personal-email-otp-code');
    const personalEmailVerifyOtpButton = document.getElementById('personal-email-verify-otp');
    const personalEmailOtpHint = document.getElementById('personal-email-otp-hint');
    const personalEmailOtpVerified = document.getElementById('personal-email-otp-verified');
    const personalEmailCurrentAddress = personalEmailInput ? (personalEmailInput.getAttribute('data-current-email') || personalEmailInput.value || personalEmailInput.getAttribute('placeholder') || '') : '';
    const personalEmailOriginalValue = personalEmailCurrentAddress;
    const personalEmailApiEndpoint = '../../microfin_backend/api/api_profile_email_change.php';
    let personalEmailVerifiedAddress = '';
    let personalEmailTrackedServerAddress = '';
    let personalEmailAvailabilityState = 'idle';
    let personalEmailAvailabilityRequestId = 0;
    let personalEmailAvailabilityMessage = '';
    let personalEmailCheckTimeoutId = null;
    let personalEmailSendingOtp = false;
    let personalEmailVerifyingOtp = false;

    const sectionDefaults = {
        staff: 'staff-list',
        billing: 'billing-overview',
    };

    const sectionRouteMap = {
        dashboard: { sectionId: 'dashboard' },
        staff: { sectionId: 'staff', subTabId: 'staff-list' },
        'staff-list': { sectionId: 'staff', subTabId: 'staff-list' },
        'roles-list': { sectionId: 'staff', subTabId: 'roles-list' },
        loan_products: { sectionId: 'loan_products' },
        credit_settings: { sectionId: 'credit_settings' },
        credit_control_policy: { sectionId: 'credit_settings' },
        credit_control_overview: { sectionId: 'credit_control_overview' },
        credit_control_policies: { sectionId: 'credit_control_policies' },
        credit_control_rules_terms: { sectionId: 'credit_control_rules_terms' },
        credit_control_approvals_holds: { sectionId: 'credit_control_approvals_holds' },
        credit_control_overrides: { sectionId: 'credit_control_overrides' },
        website: { sectionId: 'website' },
        features: { sectionId: 'features' },
        billing: { sectionId: 'billing', subTabId: 'billing-overview' },
        statements: { sectionId: 'statements' },
        settings: { sectionId: 'settings' },
        personal: { sectionId: 'personal' },
    };

    const billingSubtabMap = {
        payment: 'billing-payment',
        history: 'billing-history',
    };

    const loanProductsSection = document.getElementById('loan_products');
    const loanProductsModalOpen = Boolean(loanProductsModalPanel && loanProductsModalShell && loanProductsSection);

    if (loanProductsModalOpen) {
        if (loanProductsModalBackdrop && loanProductsModalBackdrop.parentElement !== document.body) {
            document.body.appendChild(loanProductsModalBackdrop);
        }

        if (loanProductsModalPanel.parentElement !== document.body) {
            document.body.appendChild(loanProductsModalPanel);
        }

        const focusableSelector = [
            'a[href]',
            'button:not([disabled])',
            'input:not([disabled]):not([type="hidden"])',
            'select:not([disabled])',
            'textarea:not([disabled])',
            '[tabindex]:not([tabindex="-1"])',
        ].join(', ');

        const inertTargets = [
            document.querySelector('.sidebar'),
            document.querySelector('.top-header'),
            ...viewSections.filter((section) => section !== loanProductsSection),
            loanProductsSection.querySelector('.section-intro'),
            loanProductsSection.querySelector('.loan-products-builder-header'),
            loanProductsSection.querySelector('.loan-products-tabs'),
            loanProductsSection.querySelector('#existing-loan-products'),
        ].filter((element) => element && !loanProductsModalPanel.contains(element));

        inertTargets.forEach((element) => {
            element.setAttribute('inert', '');
            element.setAttribute('aria-hidden', 'true');
        });

        const getFocusableElements = () => Array.from(loanProductsModalShell.querySelectorAll(focusableSelector))
            .filter((element) => !element.hasAttribute('disabled') && element.getAttribute('aria-hidden') !== 'true');

        window.requestAnimationFrame(() => {
            const focusableElements = getFocusableElements();
            if (focusableElements.length > 0) {
                focusableElements[0].focus();
            } else {
                loanProductsModalShell.focus();
            }
        });

        loanProductsModalPanel.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                window.location.href = 'admin.php?tab=loan_products';
                return;
            }

            if (event.key !== 'Tab') {
                return;
            }

            const focusableElements = getFocusableElements();
            if (focusableElements.length === 0) {
                event.preventDefault();
                loanProductsModalShell.focus();
                return;
            }

            const firstElement = focusableElements[0];
            const lastElement = focusableElements[focusableElements.length - 1];
            const activeElement = document.activeElement;

            if (event.shiftKey && activeElement === firstElement) {
                event.preventDefault();
                lastElement.focus();
            } else if (!event.shiftKey && activeElement === lastElement) {
                event.preventDefault();
                firstElement.focus();
            }
        });
    }

    function setPageTitleFromNav(item, fallbackTargetId) {
        if (!pageTitle) return;

        if (item) {
            const navTitle = item.getAttribute('data-title');
            const label = item.querySelector('span:nth-child(2)');
            pageTitle.textContent = navTitle || (label ? label.textContent : pageTitle.textContent);
            return;
        }

        const fallbackNav = document.querySelector(`.sidebar-nav .nav-item[data-target="${fallbackTargetId}"]`);
        if (fallbackNav) {
            const navTitle = fallbackNav.getAttribute('data-title');
            const label = fallbackNav.querySelector('span:nth-child(2)');
            pageTitle.textContent = navTitle || (label ? label.textContent : pageTitle.textContent);
        }
    }

    function activateTabInSection(sectionEl, tabId) {
        if (!sectionEl || !tabId) {
            return;
        }

        const scopedTabButtons = Array.from(sectionEl.querySelectorAll('.tab-btn'));
        const scopedTabContents = Array.from(sectionEl.querySelectorAll('.tab-content'));
        if (scopedTabButtons.length === 0 || scopedTabContents.length === 0) {
            return;
        }

        scopedTabButtons.forEach((btn) => {
            btn.classList.toggle('active', btn.getAttribute('data-tab') === tabId);
        });

        scopedTabContents.forEach((content) => {
            content.classList.toggle('active', content.id === tabId);
        });
    }

    function normalizeCreditPolicySubtab(tabId) {
        if (tabId === 'builder' || tabId === 'simulator') {
            return 'overview';
        }
        if (tabId === 'collections_safeguards') {
            return 'decision_rules';
        }
        if (tabId === 'eligibility' || tabId === 'score' || tabId === 'limit') {
            return 'credit_limits';
        }
        return tabId || '';
    }

    function isDynamicCreditPolicySubtab(tabId) {
        return ['overview', 'credit_limits', 'decision_rules', 'compliance_documents'].includes(normalizeCreditPolicySubtab(tabId));
    }

    function syncCreditPolicyPanelState(tabId, options = {}) {
        const normalizedTabId = normalizeCreditPolicySubtab(tabId);
        const panels = Array.from(document.querySelectorAll('[data-credit-policy-tab-panel]'));
        if (!normalizedTabId || panels.length === 0) {
            return '';
        }

        const availableTabs = panels
            .map((panel) => normalizeCreditPolicySubtab(panel.getAttribute('data-credit-policy-tab-panel') || ''))
            .filter(Boolean);

        const activeTab = availableTabs.includes(normalizedTabId)
            ? normalizedTabId
            : (availableTabs[0] || normalizedTabId);

        panels.forEach((panel) => {
            const panelTab = normalizeCreditPolicySubtab(panel.getAttribute('data-credit-policy-tab-panel') || '');
            panel.hidden = panelTab !== activeTab;
        });

        const activeTabInput = document.getElementById('credit-policy-active-tab-input');
        if (activeTabInput) {
            activeTabInput.value = activeTab;
        }

        const creditSettingsSection = document.getElementById('credit_settings');
        const isCreditSettingsActive = Boolean(creditSettingsSection && creditSettingsSection.classList.contains('active'));
        if (isCreditSettingsActive) {
            const activeNavItem = findPreferredNavItem('credit_settings', activeTab);

            document.querySelectorAll('.sidebar-nav .nav-item[data-target="credit_settings"][data-credit-policy-subtab]').forEach((item) => {
                const itemTab = normalizeCreditPolicySubtab(item.getAttribute('data-credit-policy-subtab') || '');
                item.classList.toggle('active', itemTab === activeTab);
            });

            if (activeNavItem) {
                setPageTitleFromNav(activeNavItem, 'credit_settings');
            }
        }

        if (options.syncUrl !== false && isCreditSettingsActive) {
            try {
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set('tab', 'credit_control_policy');
                currentUrl.searchParams.set('credit_policy_tab', activeTab);
                currentUrl.hash = 'credit_settings';
                window.history.replaceState(window.history.state, '', currentUrl.toString());
            } catch (error) {
                // Ignore URL sync issues and keep the panel switch working.
            }
        }

        return activeTab;
    }

    function activateCreditPolicySubtab(tabId) {
        tabId = normalizeCreditPolicySubtab(tabId);
        if (!tabId) {
            return;
        }

        if (typeof window.setCreditPolicyTab === 'function') {
            try {
                window.setCreditPolicyTab(tabId, { syncUrl: false });
            } catch (error) {
                // Fall back to the local panel switcher below.
            }
        }

        syncCreditPolicyPanelState(tabId);
    }

    function getCreditPolicySubtabFromItem(item, href = '') {
        if (!item) {
            return '';
        }

        const explicitTab = normalizeCreditPolicySubtab(item.getAttribute('data-credit-policy-subtab'));
        if (explicitTab) {
            return explicitTab;
        }

        if (!href) {
            return '';
        }

        try {
            const targetUrl = new URL(href, window.location.href);
            return normalizeCreditPolicySubtab(targetUrl.searchParams.get('credit_policy_tab') || '');
        } catch (error) {
            return '';
        }
    }

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-credit-policy-nav-action]');
        if (!trigger) {
            return;
        }

        const targetTab = normalizeCreditPolicySubtab(trigger.getAttribute('data-credit-policy-nav-action') || '');
        if (!targetTab) {
            return;
        }

        event.preventDefault();

        if (isDynamicCreditPolicySubtab(targetTab)) {
            activateCreditPolicySubtab(targetTab);
            return;
        }

        try {
            const targetUrl = new URL(window.location.href);
            targetUrl.searchParams.set('tab', 'credit_control_policy');
            targetUrl.searchParams.set('credit_policy_tab', targetTab);
            targetUrl.hash = 'credit_settings';
            window.location.href = targetUrl.toString();
        } catch (error) {
            window.location.href = `admin.php?tab=credit_control_policy&credit_policy_tab=${encodeURIComponent(targetTab)}#credit_settings`;
        }
    });

    function initPolicyConsoleOverview() {
        const simulator = document.querySelector('[data-policy-console-overview-simulator]');
        if (!simulator) {
            return;
        }

        let simulatorConfig = {};
        try {
            simulatorConfig = JSON.parse(simulator.getAttribute('data-simulator-config') || '{}');
        } catch (error) {
            simulatorConfig = {};
        }

        const scoreBands = Array.isArray(simulatorConfig.scoreBands) ? simulatorConfig.scoreBands.slice() : [];
        if (scoreBands.length === 0) {
            return;
        }

        scoreBands.sort((left, right) => Number(left.min_score || 0) - Number(right.min_score || 0));

        const scoreInput = simulator.querySelector('[data-simulator-score-input]');
        const limitInput = simulator.querySelector('[data-simulator-limit-input]');
        const scoreOutput = simulator.querySelector('[data-simulator-score-output]');
        const scoreCardOutput = simulator.querySelector('[data-simulator-score-card-output]');
        const limitBasisOutput = simulator.querySelector('[data-simulator-limit-basis-output]');
        const projectedLimitOutput = simulator.querySelector('[data-simulator-projected-limit]');
        const firstTimeLimitOutput = simulator.querySelector('[data-simulator-first-time-limit]');
        const bandOutput = simulator.querySelector('[data-simulator-band-output]');
        const noteOutput = simulator.querySelector('[data-simulator-note-output]');
        const firstTimeNoteOutput = simulator.querySelector('[data-simulator-first-time-note-output]');
        const limitCap = Number(simulatorConfig.limitCap || 0);
        const initialLimitPercent = Number(simulatorConfig.initialLimitPercent || 0);

        if (!scoreInput || !limitInput || !projectedLimitOutput || !bandOutput) {
            return;
        }

        const moneyFormatter = new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: 'PHP',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });

        const findBandForScore = (score) => {
            for (const band of scoreBands) {
                const minScore = Number(band.min_score || 0);
                const hasOpenMax = band.max_score === null || band.max_score === '';
                const maxScore = hasOpenMax ? Number.POSITIVE_INFINITY : Number(band.max_score || minScore);
                if (score >= minScore && score <= maxScore) {
                    return band;
                }
            }

            if (score < Number(scoreBands[0].min_score || 0)) {
                return scoreBands[0];
            }

            return scoreBands[scoreBands.length - 1];
        };

        const renderSimulator = () => {
            const score = Number.parseInt(scoreInput.value || '0', 10) || 0;
            const limitBasis = Number.parseFloat(limitInput.value || '0') || 0;
            const activeBand = findBandForScore(score);
            const bandMin = Number(activeBand.min_score || 0);
            const baseGrowth = Number(activeBand.base_growth_percent || 0);
            const microGrowth = Number(activeBand.micro_percent_per_point || 0);
            const growthPercent = Math.max(0, baseGrowth + (Math.max(0, score - bandMin) * microGrowth));
            let firstTimeLimit = limitBasis * (initialLimitPercent / 100);
            let firstTimeClamped = false;
            if (limitCap > 0 && firstTimeLimit > limitCap) {
                firstTimeLimit = limitCap;
                firstTimeClamped = true;
            }

            let projectedLimit = limitBasis * (1 + (growthPercent / 100));
            let wasClamped = false;
            if (limitCap > 0 && projectedLimit > limitCap) {
                projectedLimit = limitCap;
                wasClamped = true;
            }

            if (scoreOutput) {
                scoreOutput.textContent = score.toLocaleString('en-PH');
            }
            if (scoreCardOutput) {
                scoreCardOutput.textContent = score.toLocaleString('en-PH');
            }
            if (limitBasisOutput) {
                limitBasisOutput.textContent = moneyFormatter.format(limitBasis);
            }

            bandOutput.textContent = String(activeBand.label || 'Unassigned');
            projectedLimitOutput.textContent = moneyFormatter.format(projectedLimit);
            if (firstTimeLimitOutput) {
                firstTimeLimitOutput.textContent = moneyFormatter.format(firstTimeLimit);
            }

            if (noteOutput) {
                noteOutput.textContent = wasClamped
                    ? `Projected limit is capped at ${moneyFormatter.format(limitCap)} by the current tenant guard.`
                    : `Projected using ${growthPercent.toFixed(2)}% growth from the selected basis amount.`;
            }
            if (firstTimeNoteOutput) {
                firstTimeNoteOutput.textContent = firstTimeClamped
                    ? `Onboarding limit is capped at ${moneyFormatter.format(limitCap)} by the current tenant guard.`
                    : `Onboarding limit uses ${initialLimitPercent.toFixed(2)}% of the selected monthly income.`;
            }
        };

        [scoreInput, limitInput].forEach((input) => {
            input.addEventListener('input', renderSimulator);
            input.addEventListener('change', renderSimulator);
        });

        renderSimulator();
    }

    initPolicyConsoleOverview();

    function initPolicyConsoleCreditLimits() {
        const creditLimitsForm = document.getElementById('policy-console-credit-limits-form');
        if (!creditLimitsForm) {
            return;
        }

        let intendedNavigationUrl = null;
        let scoreBandOriginalState = null;
        let rowToDelete = null;

        const scoreBandWrap = creditLimitsForm.querySelector('[data-policy-score-band-wrap]');
        const scoreBandBody = creditLimitsForm.querySelector('[data-policy-score-band-body]');
        const scoreBandTemplate = document.getElementById('policy-console-score-band-row-template');
        const flowButtons = creditLimitsForm.querySelectorAll('[data-policy-section-jump]');
        
        // Modal Selectors
        const unsavedModal = document.getElementById('policy-unsaved-modal');
        const deleteRowModal = document.getElementById('policy-delete-row-modal');

        const getToggleInput = (key) => creditLimitsForm.querySelector(`[data-policy-toggle-input="${key}"]`);
        const getToggleButton = (key) => creditLimitsForm.querySelector(`[data-policy-toggle-button="${key}"]`);
        const getToggleValue = (input) => {
            if (!input) {
                return false;
            }

            if (input.type === 'checkbox') {
                return Boolean(input.checked);
            }

            return String(typeof input.value !== 'undefined' ? input.value : '0') === '1';
        };
        const notifyToggleChange = (input) => {
            if (!input) {
                return;
            }

            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        };

        const syncToggleButtonState = (key) => {
            const hiddenInput = getToggleInput(key);
            const button = getToggleButton(key);
            if (!hiddenInput || !button) {
                return;
            }

            const isOn = getToggleValue(hiddenInput) && !button.disabled;
            const toggleLabel = button.querySelector('[data-policy-toggle-label]');

            button.classList.toggle('is-on', isOn);
            button.setAttribute('aria-pressed', isOn ? 'true' : 'false');

            if (toggleLabel) {
                toggleLabel.textContent = isOn ? 'On' : 'Off';
            }
        };

        // Move modals to body to ensure fixed overlapping works correctly
        if (unsavedModal && unsavedModal.parentElement !== document.body) {
            document.body.appendChild(unsavedModal);
        }
        if (deleteRowModal && deleteRowModal.parentElement !== document.body) {
            document.body.appendChild(deleteRowModal);
        }

        const getPolicyUnsavedManager = () => {
            const manager = window.policyConsoleUnsavedManager;
            return manager && typeof manager.recompute === 'function' ? manager : null;
        };
        const dispatchSyntheticFormStateEvents = (formElement) => {
            if (!formElement) {
                return;
            }

            Array.from(formElement.elements || []).forEach((field) => {
                if (!(field instanceof HTMLElement) || !field.matches('input, select, textarea')) {
                    return;
                }

                field.dispatchEvent(new Event('input', { bubbles: true }));
                field.dispatchEvent(new Event('change', { bubbles: true }));
            });
        };
        const beginPolicySubmit = () => {
            const manager = getPolicyUnsavedManager();
            if (manager && typeof manager.markSubmitting === 'function') {
                manager.markSubmitting();
            } else {
                window._policyConsoleSubmitting = true;
                window._policyConsoleBypassBeforeUnload = false;
                window._isPolicyFormDirty = false;
            }
            isFormDirty = false;
        };

        let isFormDirty = false;
        let initialFormState = '';
        const initialScoreBandBodyHtml = scoreBandBody ? scoreBandBody.innerHTML : '';
        const initialScoreBandNextIndex = scoreBandWrap ? (scoreBandWrap.getAttribute('data-next-index') || '0') : '0';
        function checkFormDirty() {
            const manager = getPolicyUnsavedManager();
            if (manager) {
                isFormDirty = Boolean(manager.recompute());
                window._isPolicyFormDirty = isFormDirty;
                return isFormDirty;
            }

            const currentState = new URLSearchParams(new FormData(creditLimitsForm)).toString();
            isFormDirty = currentState !== initialFormState;
            window._isPolicyFormDirty = isFormDirty;
            return isFormDirty;
        }

        setTimeout(() => {
            initialFormState = new URLSearchParams(new FormData(creditLimitsForm)).toString();
            checkFormDirty();
        }, 0);

        // Global dirty state listeners
        creditLimitsForm.addEventListener('input', checkFormDirty);
        creditLimitsForm.addEventListener('change', checkFormDirty);

        const globalSaveBtn = document.getElementById('policy-global-save-btn');
        if (globalSaveBtn) {
            globalSaveBtn.addEventListener('click', () => {
                beginPolicySubmit();
                let activeForm = document.querySelector(".credit-policy-tab-panel:not([hidden]) form");
                if (activeForm) {
                    activeForm.submit();
                } else if (creditLimitsForm) {
                    creditLimitsForm.submit();
                }
            });
        }
        
        const forceSaveBtn = document.getElementById('policy-unsaved-save-btn');
        if (forceSaveBtn) {
            forceSaveBtn.addEventListener('click', () => {
                beginPolicySubmit();
                let activeForm = document.querySelector(".credit-policy-tab-panel:not([hidden]) form");
                if (activeForm) {
                    activeForm.submit();
                } else if (creditLimitsForm) {
                    creditLimitsForm.submit();
                }
            });
        }

        // Modal Dismissals
        document.querySelectorAll('[data-modal-dismiss]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const modal = e.target.closest('.policy-blueprint-modal');
                if (modal) modal.hidden = true;
                intendedNavigationUrl = null;
                rowToDelete = null;
                window._intendedPolicyNavigationUrl = null;
            });
        });

        const confirmDiscardBtn = document.getElementById('policy-unsaved-discard-btn');
        if (confirmDiscardBtn) {
            confirmDiscardBtn.addEventListener('click', () => {
                const pendingNavigation = window._intendedPolicyNavigation;
                const pendingNavigationUrl = window._intendedPolicyNavigationUrl
                    || (typeof intendedNavigationUrl === 'string' ? intendedNavigationUrl : '');
                const manager = getPolicyUnsavedManager();
                const unsavedModal = document.getElementById('policy-unsaved-modal');

                isFormDirty = false;
                window._isPolicyFormDirty = false;
                window._policyConsoleSubmitting = false;
                window._intendedPolicyNavigation = null;
                window._intendedPolicyNavigationUrl = null;
                rowToDelete = null;
                intendedNavigationUrl = null;

                if (unsavedModal) {
                    unsavedModal.hidden = true;
                }

                if (manager && typeof manager.restoreAllForms === 'function') {
                    manager.restoreAllForms();
                } else {
                    document.querySelectorAll(".credit-policy-tab-panel form").forEach((form) => form.reset());
                    if (creditLimitsForm) {
                        creditLimitsForm.reset();
                    }
                    checkFormDirty();
                }

                if (pendingNavigationUrl) {
                    if (manager && typeof manager.allowConfirmedNavigation === 'function') {
                        manager.allowConfirmedNavigation();
                    } else {
                        window._policyConsoleSubmitting = false;
                        window._policyConsoleBypassBeforeUnload = true;
                        window._isPolicyFormDirty = false;
                    }
                    window.location.href = pendingNavigationUrl;
                    return;
                }

                if (typeof pendingNavigation === 'function') {
                    pendingNavigation();
                    return;
                }
            });
        }

        const confirmDeleteBtn = document.getElementById('policy-delete-row-confirm-btn');
        if (confirmDeleteBtn) {
            confirmDeleteBtn.addEventListener('click', () => {
                if (rowToDelete) {
                    rowToDelete.remove();
                    syncScoreBandEmptyState();
                    checkFormDirty();
                }
                rowToDelete = null;
                deleteRowModal.hidden = true;
            });
        }

        // Native Before Unload
        window.addEventListener('beforeunload', (e) => {
            if (!window._policyConsoleSubmitting && !window._policyConsoleBypassBeforeUnload && checkFormDirty()) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        // Intercept internal clicks
        document.body.addEventListener('click', (e) => {
            if (!checkFormDirty()) return;
            const a = e.target.closest('a[href]');
            if (a) {
                // If it's a subtab inside policy console, do not prompt.
                let isCreditTarget = false;
                try {
                    const url = new URL(a.href, window.location.href);
                    const tabParam = url.searchParams.get('tab');
                    if (tabParam === 'credit_settings' || tabParam === 'credit_control_policy') {
                        isCreditTarget = true;
                    }
                } catch (err) {}

                if (a.getAttribute('data-target') === 'credit_settings' || a.hasAttribute('data-credit-policy-nav-action') || isCreditTarget) {
                    return; // Allow inner JS UI switching without blocking
                }

                const href = a.getAttribute('href');
                if (href && !href.startsWith('#') && !href.startsWith('javascript:')) {
                    e.preventDefault();
                    e.stopPropagation(); // prevent duplicate modals
                    intendedNavigationUrl = href;
                    window._intendedPolicyNavigationUrl = href;
                    if (unsavedModal) unsavedModal.hidden = false;
                }
            }
        });

        const scoreBandEmpty = creditLimitsForm.querySelector('[data-policy-score-band-empty]');
        // flowButtons already declared above

        function syncScoreBandEmptyState() {
            if (!scoreBandBody || !scoreBandEmpty) {
                return;
            }

            const hasRows = Boolean(scoreBandBody.querySelector('[data-policy-score-band-row]'));
            scoreBandEmpty.hidden = hasRows;
        }

        function syncRuleCardState(toggleInput) {
            const card = toggleInput ? toggleInput.closest('[data-policy-rule-card]') : null;
            if (!card) {
                return;
            }

            card.classList.toggle('is-off', !getToggleValue(toggleInput));
        }

        function nextScoreBandIndex() {
            if (!scoreBandWrap) {
                return Date.now();
            }

            const current = Number.parseInt(scoreBandWrap.getAttribute('data-next-index') || '0', 10);
            const next = Number.isNaN(current) ? 0 : current;
            scoreBandWrap.setAttribute('data-next-index', String(next + 1));
            return next;
        }

        function setActiveFlow(sectionId) {
            flowButtons.forEach((button) => {
                button.classList.toggle('is-active', button.getAttribute('data-policy-section-jump') === sectionId);
            });
        }

        function syncPanelToggleButtons(panelId, isOpen) {
            creditLimitsForm.querySelectorAll(`[data-policy-toggle-panel="${panelId}"]`).forEach((button) => {
                const openLabel = button.getAttribute('data-panel-open-label') || 'Open';
                const closeLabel = button.getAttribute('data-panel-close-label') || 'Close';
                button.textContent = isOpen ? closeLabel : openLabel;
            });
        }

        creditLimitsForm._policyConsoleRestoreOriginal = () => {
            creditLimitsForm.reset();

            if (scoreBandBody) {
                scoreBandBody.innerHTML = initialScoreBandBodyHtml;
            }
            if (scoreBandWrap) {
                scoreBandWrap.setAttribute('data-next-index', initialScoreBandNextIndex);
            }

            scoreBandOriginalState = null;
            rowToDelete = null;

            const customizeRealBtn = document.getElementById('policy-score-band-customize-btn');
            const cancelRealBtn = document.getElementById('policy-score-band-cancel-btn');
            const addBtn = creditLimitsForm.querySelector('[data-policy-score-band-add]');
            const table = document.getElementById('policy-score-band-table');
            const lifecyclePanel = document.getElementById('policy-lifecycle-panel');
            const limitAdvancedPanel = document.getElementById('policy-limit-assignment-advanced-panel');

            if (customizeRealBtn) {
                customizeRealBtn.textContent = 'Customize';
            }
            if (cancelRealBtn) {
                cancelRealBtn.style.display = 'none';
            }
            if (addBtn) {
                addBtn.style.display = 'none';
            }
            creditLimitsForm.querySelectorAll('.policy-band-col-actions').forEach((col) => {
                col.style.display = 'none';
            });
            if (table) {
                table.querySelectorAll('input.form-control').forEach((input) => {
                    input.setAttribute('readonly', 'readonly');
                });
            }
            if (lifecyclePanel) {
                lifecyclePanel.hidden = true;
            }
            if (limitAdvancedPanel) {
                limitAdvancedPanel.hidden = true;
            }
        };
        creditLimitsForm._policyConsoleRefreshUi = () => {
            creditLimitsForm.querySelectorAll('[data-policy-rule-toggle]').forEach((toggleInput) => {
                syncRuleCardState(toggleInput);
            });
            creditLimitsForm.querySelectorAll('[data-policy-toggle-button]').forEach((toggleButton) => {
                const toggleKey = toggleButton.getAttribute('data-policy-toggle-button');
                if (toggleKey) {
                    syncToggleButtonState(toggleKey);
                }
            });

            syncScoreBandEmptyState();

            const lifecyclePanel = document.getElementById('policy-lifecycle-panel');
            syncPanelToggleButtons('policy-lifecycle-panel', lifecyclePanel ? !lifecyclePanel.hidden : false);

            const limitAdvancedPanel = document.getElementById('policy-limit-assignment-advanced-panel');
            syncPanelToggleButtons('policy-limit-assignment-advanced-panel', limitAdvancedPanel ? !limitAdvancedPanel.hidden : false);

            dispatchSyntheticFormStateEvents(creditLimitsForm);
        };

        creditLimitsForm.addEventListener('click', (event) => {
            const toggleButton = event.target.closest('[data-policy-toggle-button]');
            if (toggleButton) {
                event.preventDefault();
                const toggleKey = toggleButton.getAttribute('data-policy-toggle-button') || '';
                const toggleInput = toggleKey ? getToggleInput(toggleKey) : null;
                if (!toggleKey || !toggleInput || toggleButton.disabled) {
                    return;
                }

                if (toggleInput.type === 'checkbox') {
                    toggleInput.checked = !toggleInput.checked;
                } else {
                    toggleInput.value = getToggleValue(toggleInput) ? '0' : '1';
                }

                notifyToggleChange(toggleInput);
                syncToggleButtonState(toggleKey);

                if (toggleInput.hasAttribute('data-policy-rule-toggle')) {
                    syncRuleCardState(toggleInput);
                }
                return;
            }

            const jumpTrigger = event.target.closest('[data-policy-section-jump]');
            if (jumpTrigger) {
                event.preventDefault();
                const sectionId = jumpTrigger.getAttribute('data-policy-section-jump') || '';
                const targetSection = sectionId ? document.getElementById(sectionId) : null;
                if (targetSection) {
                    setActiveFlow(sectionId);
                    targetSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
                return;
            }

            const panelToggleTrigger = event.target.closest('[data-policy-toggle-panel]');
            if (panelToggleTrigger) {
                event.preventDefault();
                const panelId = panelToggleTrigger.getAttribute('data-policy-toggle-panel') || '';
                const targetPanel = panelId ? document.getElementById(panelId) : null;
                if (targetPanel) {
                    targetPanel.hidden = !targetPanel.hidden;
                    syncPanelToggleButtons(panelId, !targetPanel.hidden);
                }
                return;
            }

            const addBandTrigger = event.target.closest('[data-policy-score-band-add]');
            if (addBandTrigger && scoreBandBody && scoreBandTemplate) {
                event.preventDefault();
                const nextIndex = nextScoreBandIndex();
                scoreBandBody.insertAdjacentHTML(
                    'beforeend',
                    scoreBandTemplate.innerHTML.replace(/__INDEX__/g, String(nextIndex))
                );
                syncScoreBandEmptyState();
                const newRow = scoreBandBody.querySelector(`[data-policy-row-index="${nextIndex}"] input[name="pcc_score_band_label[]"]`);
                if (newRow) {
                    newRow.focus();
                }
                checkFormDirty();
                return;
            }

            const deleteBandTrigger = event.target.closest('[data-policy-score-band-delete]');
            if (deleteBandTrigger) {
                event.preventDefault();
                const row = deleteBandTrigger.closest('[data-policy-score-band-row]');
                if (row) {
                    rowToDelete = row;
                    if (deleteRowModal) deleteRowModal.hidden = false;
                }
                return;
            }

            const customizeBtn = event.target.closest('#policy-score-band-customize-btn');
            const cancelBtn = event.target.closest('#policy-score-band-cancel-btn');

            if (customizeBtn || cancelBtn) {
                event.preventDefault();
                const customizeRealBtn = document.getElementById('policy-score-band-customize-btn');
                const cancelRealBtn = document.getElementById('policy-score-band-cancel-btn');
                
                const table = document.getElementById('policy-score-band-table');
                
                if (customizeBtn) {
                    const isEditing = customizeRealBtn.textContent.trim() === 'Done';
                    const nextIsEditing = !isEditing;
                    
                    if (nextIsEditing) {
                        // Entering Edit Mode: Save state
                        if (table && table.parentElement) {
                            scoreBandOriginalState = table.parentElement.innerHTML;
                        }
                    }

                    customizeRealBtn.textContent = nextIsEditing ? 'Done' : 'Customize';
                    if (cancelRealBtn) cancelRealBtn.style.display = nextIsEditing ? 'inline-flex' : 'none';
                    
                    const addBtn = creditLimitsForm.querySelector('[data-policy-score-band-add]');
                    if (addBtn) addBtn.style.display = nextIsEditing ? 'inline-flex' : 'none';
                    
                    const cols = creditLimitsForm.querySelectorAll('.policy-band-col-actions');
                    cols.forEach(col => col.style.display = nextIsEditing ? 'table-cell' : 'none');
                    
                    if (table) {
                        const inputs = table.querySelectorAll('input.form-control');
                        inputs.forEach(input => {
                            if (nextIsEditing) {
                                input.removeAttribute('readonly');
                            } else {
                                input.setAttribute('readonly', 'readonly');
                            }
                        });
                    }
                } else if (cancelBtn) {
                    // Revert to original
                    if (scoreBandOriginalState && table && table.parentElement) {
                        table.parentElement.innerHTML = scoreBandOriginalState;
                    }
                    
                    customizeRealBtn.textContent = 'Customize';
                    cancelRealBtn.style.display = 'none';
                    
                    const addBtn = creditLimitsForm.querySelector('[data-policy-score-band-add]');
                    if (addBtn) addBtn.style.display = 'none';
                    
                    const cols = creditLimitsForm.querySelectorAll('.policy-band-col-actions');
                    cols.forEach(col => col.style.display = 'none');
                    
                    if (table) {
                        const inputs = table.querySelectorAll('input.form-control');
                        inputs.forEach(input => {
                            input.setAttribute('readonly', 'readonly');
                        });
                    }

                    checkFormDirty();
                }
                return;
            }
        });

        creditLimitsForm.addEventListener('change', (event) => {
            const ruleToggle = event.target.closest('[data-policy-rule-toggle]');
            if (ruleToggle) {
                syncRuleCardState(ruleToggle);
            }
        });

        creditLimitsForm.querySelectorAll('[data-policy-rule-toggle]').forEach((toggleInput) => {
            syncRuleCardState(toggleInput);
        });
        creditLimitsForm.querySelectorAll('[data-policy-toggle-button]').forEach((toggleButton) => {
            const toggleKey = toggleButton.getAttribute('data-policy-toggle-button');
            if (toggleKey) {
                syncToggleButtonState(toggleKey);
            }
        });

        syncScoreBandEmptyState();
        if (flowButtons.length) {
            setActiveFlow(flowButtons[0].getAttribute('data-policy-section-jump') || '');
        }
        const lifecyclePanel = document.getElementById('policy-lifecycle-panel');
        syncPanelToggleButtons('policy-lifecycle-panel', lifecyclePanel ? !lifecyclePanel.hidden : false);
        const limitAdvancedPanel = document.getElementById('policy-limit-assignment-advanced-panel');
        syncPanelToggleButtons('policy-limit-assignment-advanced-panel', limitAdvancedPanel ? !limitAdvancedPanel.hidden : false);
    }

    initPolicyConsoleCreditLimits();

    function initPolicyConsoleDecisionRules() {
        const decisionRulesForm = document.getElementById('policy-console-decision-rules-form');
        if (!decisionRulesForm) {
            return;
        }

        const getToggleInput = (key) => decisionRulesForm.querySelector(`[data-policy-toggle-input="${key}"]`);
        const getToggleButton = (key) => decisionRulesForm.querySelector(`[data-policy-toggle-button="${key}"]`);
        const getToggleValue = (input) => String(input && typeof input.value !== 'undefined' ? input.value : '0') === '1';
        const notifyToggleChange = (input) => {
            if (!input) {
                return;
            }

            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        };

        const syncToggleButtonState = (key) => {
            const hiddenInput = getToggleInput(key);
            const button = getToggleButton(key);
            if (!hiddenInput || !button) {
                return;
            }

            const isOn = getToggleValue(hiddenInput) && !button.disabled;
            const toggleLabel = button.querySelector('[data-policy-toggle-label]');

            button.classList.toggle('is-on', isOn);
            button.setAttribute('aria-pressed', isOn ? 'true' : 'false');

            if (toggleLabel) {
                toggleLabel.textContent = isOn ? 'On' : 'Off';
            }
        };

        const syncRuleState = (toggleInput) => {
            if (!toggleInput) {
                return;
            }

            const ruleKey = toggleInput.getAttribute('data-decision-rule-toggle');
            if (!ruleKey) {
                return;
            }

            const item = toggleInput.closest('[data-decision-rule-item]');
            const content = decisionRulesForm.querySelector(`[data-decision-rule-content="${ruleKey}"]`);
            const relatedButton = getToggleButton(ruleKey);
            const isDisabled = Boolean(relatedButton && relatedButton.disabled);
            const isActive = getToggleValue(toggleInput) && !isDisabled;

            if (item) {
                item.classList.toggle('is-off', !isActive);
                item.classList.toggle('is-disabled', isDisabled);
            }

            if (content) {
                content.hidden = !isActive;
            }
        };
        const syncDecisionRulesUi = () => {
            decisionRulesForm.querySelectorAll('[data-policy-toggle-button]').forEach((toggleButton) => {
                const toggleKey = toggleButton.getAttribute('data-policy-toggle-button');
                if (toggleKey) {
                    syncToggleButtonState(toggleKey);
                }
            });

            decisionRulesForm.querySelectorAll('[data-decision-rule-toggle]').forEach((toggleInput) => {
                syncRuleState(toggleInput);
            });
        };

        decisionRulesForm._policyConsoleRestoreOriginal = () => {
            decisionRulesForm.reset();
        };
        decisionRulesForm._policyConsoleRefreshUi = () => {
            syncDecisionRulesUi();

            Array.from(decisionRulesForm.elements || []).forEach((field) => {
                if (!(field instanceof HTMLElement) || !field.matches('input, select, textarea')) {
                    return;
                }

                field.dispatchEvent(new Event('input', { bubbles: true }));
                field.dispatchEvent(new Event('change', { bubbles: true }));
            });
        };

        decisionRulesForm.querySelectorAll('[data-policy-toggle-button]').forEach((toggleButton) => {
            const toggleKey = toggleButton.getAttribute('data-policy-toggle-button');
            const toggleInput = toggleKey ? getToggleInput(toggleKey) : null;
            if (!toggleKey || !toggleInput) {
                return;
            }

            toggleButton.addEventListener('click', () => {
                if (toggleButton.disabled) {
                    return;
                }

                toggleInput.value = getToggleValue(toggleInput) ? '0' : '1';
                notifyToggleChange(toggleInput);
                syncToggleButtonState(toggleKey);

                if (toggleInput.hasAttribute('data-decision-rule-toggle')) {
                    syncRuleState(toggleInput);
                }
            });
        });

        syncDecisionRulesUi();

    }

    initPolicyConsoleDecisionRules();

    function findPreferredNavItem(targetId, subTabId = '') {
        subTabId = normalizeCreditPolicySubtab(subTabId);
        if (targetId === 'credit_settings' && subTabId !== '') {
            const creditPolicyNav = document.querySelector(`.sidebar-nav .nav-item[data-target="${targetId}"][data-credit-policy-subtab="${subTabId}"]`);
            if (creditPolicyNav) {
                return creditPolicyNav;
            }
        }

        if (subTabId !== '') {
            const exactNav = document.querySelector(`.sidebar-nav .nav-item[data-target="${targetId}"][data-subtab="${subTabId}"]`);
            if (exactNav) {
                return exactNav;
            }
        }

        return document.querySelector(`.sidebar-nav .nav-item[data-target="${targetId}"]:not([data-subtab]):not([data-credit-policy-subtab])`)
            || document.querySelector(`.sidebar-nav .nav-item[data-target="${targetId}"]`);
    }

    function resetWorkspaceScroll() {
        if (viewsContainer) {
            viewsContainer.scrollTop = 0;
        }
        if (document.documentElement) {
            document.documentElement.scrollTop = 0;
        }
        if (document.body) {
            document.body.scrollTop = 0;
        }
        window.scrollTo({ top: 0, left: 0, behavior: 'auto' });
    }

    function isSameAdminPageLink(href) {
        if (!href) {
            return true;
        }
        if (href.startsWith('#')) {
            return true;
        }

        try {
            const targetUrl = new URL(href, window.location.href);
            const currentUrl = new URL(window.location.href);
            return targetUrl.origin === currentUrl.origin
                && targetUrl.pathname === currentUrl.pathname;
        } catch (error) {
            return false;
        }
    }

    function replaceUrlForSection(targetId, subTabId = '', href = '') {
        if (!window.history || typeof window.history.replaceState !== 'function') {
            return;
        }

        if (isSameAdminPageLink(href) && href && !href.startsWith('#')) {
            const targetUrl = new URL(href, window.location.href);
            targetUrl.hash = targetId ? `#${targetId}` : '';
            window.history.replaceState({}, '', targetUrl);
            return;
        }

        const currentUrl = new URL(window.location.href);
        if (targetId === 'dashboard') {
            currentUrl.searchParams.delete('tab');
            currentUrl.searchParams.delete('sub');
        }
        if (subTabId && targetId === 'staff') {
            currentUrl.searchParams.set('tab', subTabId);
        } else if (targetId && targetId !== 'dashboard') {
            currentUrl.searchParams.set('tab', targetId);
            currentUrl.searchParams.delete('sub');
        }
        currentUrl.hash = targetId ? `#${targetId}` : '';
        window.history.replaceState({}, '', currentUrl);
    }

    function syncSidebarDropdownState() {
        const dropdowns = Array.from(document.querySelectorAll('.sidebar-nav-dropdown'));
        dropdowns.forEach((dropdown) => {
            const summary = dropdown.querySelector('.nav-item-toggle');
            const hasActiveChild = Boolean(dropdown.querySelector('.nav-item[data-target].active'));

            if (summary) {
                summary.classList.toggle('active', hasActiveChild);
            }

            if (hasActiveChild) {
                dropdown.open = true;
            }
        });
    }

    function getPersonalPasswordChecks(password) {
        return {
            length: password.length >= 8,
            mixedCase: /[a-z]/.test(password) && /[A-Z]/.test(password),
            number: /\d/.test(password),
            symbol: /[^A-Za-z0-9]/.test(password),
        };
    }

    function getPersonalPasswordStrength(password) {
        if (!password) {
            return { label: 'No new password entered', level: '', width: '0%' };
        }

        const checks = getPersonalPasswordChecks(password);
        const passedChecks = Object.values(checks).filter(Boolean).length;
        const hasBonusLength = password.length >= 12;
        const score = passedChecks + (hasBonusLength ? 1 : 0);

        if (password.length < 8) {
            return { label: 'Too short', level: 'is-weak', width: '22%' };
        }

        if (score <= 2) {
            return { label: 'Basic strength', level: 'is-weak', width: '38%' };
        }

        if (score === 3 || score === 4) {
            return { label: 'Good strength', level: 'is-fair', width: '72%' };
        }

        return { label: 'Strong password', level: 'is-strong', width: '100%' };
    }

    function setPersonalEmailStatus(message = '', state = '') {
        if (!personalEmailStatus) {
            return;
        }

        personalEmailStatus.textContent = message;
        personalEmailStatus.classList.toggle('is-success', state === 'success');
        personalEmailStatus.classList.toggle('is-error', state === 'error');
        personalEmailStatus.classList.toggle('is-info', state === 'info');
    }

    function normalizePersonalEmailValue(value = '') {
        return (value || '').trim().toLowerCase();
    }

    function getPersonalEmailDraftValue() {
        return personalEmailInput ? (personalEmailInput.value || '').trim() : '';
    }

    function setPersonalEmailAvailability(state = 'idle', message = '') {
        personalEmailAvailabilityState = state;
        personalEmailAvailabilityMessage = message;
    }

    function setPersonalEmailToggleAppearance(label, iconName, title) {
        if (personalEmailToggleText) {
            personalEmailToggleText.textContent = label;
        }

        if (!personalEmailToggle) {
            return;
        }

        personalEmailToggle.setAttribute('title', title);
        const icon = personalEmailToggle.querySelector('.material-symbols-rounded');
        if (icon) {
            icon.textContent = iconName;
        }
    }

    function syncPersonalEmailActionState() {
        if (!personalEmailToggle || !personalEmailInput) {
            return;
        }

        const isEditing = !personalEmailInput.readOnly;
        const draftValue = getPersonalEmailDraftValue();
        const normalizedDraftValue = normalizePersonalEmailValue(draftValue);
        const isVerifiedForCurrentDraft = normalizedDraftValue !== ''
            && normalizedDraftValue === normalizePersonalEmailValue(personalEmailVerifiedAddress)
            && personalEmailOtpVerified
            && personalEmailOtpVerified.value === '1';

        personalEmailToggle.disabled = false;

        if (!isEditing) {
            setPersonalEmailToggleAppearance('Change Email', 'edit', 'Change email address');
            return;
        }

        if (isVerifiedForCurrentDraft) {
            setPersonalEmailToggleAppearance('Verified', 'verified', 'Email verified');
            personalEmailToggle.disabled = true;
            return;
        }

        setPersonalEmailToggleAppearance('Change', 'forward_to_inbox', 'Send OTP to this email address');
        personalEmailToggle.disabled = personalEmailSendingOtp
            || personalEmailVerifyingOtp
            || personalEmailAvailabilityState !== 'available';
    }

    function resetPersonalEmailOtpState(options = {}) {
        const {
            clearStatus = true,
            hidePanel = true,
            clearOtpInput = true,
            clearVerification = true,
        } = options;

        if (clearOtpInput && personalEmailOtpCode) {
            personalEmailOtpCode.value = '';
        }

        if (hidePanel && personalEmailOtpPanel) {
            personalEmailOtpPanel.hidden = true;
        }

        if (clearVerification && personalEmailOtpVerified) {
            personalEmailOtpVerified.value = '0';
            personalEmailVerifiedAddress = '';
        }

        if (personalEmailOtpHint) {
            personalEmailOtpHint.textContent = 'Click Change after entering a new available email address, then verify the 6-digit OTP before saving.';
        }

        if (clearStatus) {
            setPersonalEmailStatus('', '');
        }

        syncPersonalEmailActionState();
    }

    async function postPersonalEmailChangeAction(action, payload = {}) {
        const response = await fetch(personalEmailApiEndpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ action, ...payload }),
        });

        let data = null;
        try {
            data = await response.json();
        } catch (error) {
            data = null;
        }

        if (!response.ok || !data || data.status !== 'success') {
            const error = new Error((data && data.message) ? data.message : 'Unable to complete the email verification request right now.');
            error.code = data && data.code ? data.code : '';
            throw error;
        }

        return data;
    }

    function setPersonalEmailEditable(isEditable) {
        if (!personalEmailInput) {
            return;
        }

        personalEmailInput.readOnly = !isEditable;
        personalEmailInput.setAttribute('aria-readonly', isEditable ? 'false' : 'true');
        personalEmailInput.classList.toggle('is-locked', !isEditable);
        personalEmailInput.classList.toggle('is-editing', isEditable);

        if (personalEmailChangeRequested) {
            personalEmailChangeRequested.value = isEditable ? '1' : '0';
        }

        if (personalEmailToggle) {
            personalEmailToggle.setAttribute('aria-pressed', isEditable ? 'true' : 'false');
        }

        if (personalEmailCancelButton) {
            personalEmailCancelButton.hidden = !isEditable;
            personalEmailCancelButton.setAttribute('aria-hidden', isEditable ? 'false' : 'true');
        }

        if (personalEmailActions) {
            personalEmailActions.hidden = !isEditable;
        }

        if (personalEmailHint) {
            personalEmailHint.textContent = isEditable
                ? 'Enter a new email address. We will check if it is already used in this workspace, then send the OTP when you click Change.'
                : 'This is your current sign-in email from the database. Click Change Email if you want to replace it.';
        }

        if (!isEditable) {
            setPersonalEmailAvailability('idle', '');
        }

        syncPersonalEmailActionState();
    }

    function clearTrackedPersonalEmailServerState() {
        if (personalEmailTrackedServerAddress === '') {
            return;
        }

        personalEmailTrackedServerAddress = '';
        void postPersonalEmailChangeAction('clear_state').catch(() => {});
    }

    function schedulePersonalEmailAvailabilityCheck(email) {
        if (!personalEmailInput || personalEmailInput.readOnly) {
            return;
        }

        if (personalEmailCheckTimeoutId) {
            window.clearTimeout(personalEmailCheckTimeoutId);
            personalEmailCheckTimeoutId = null;
        }

        const trimmedEmail = (email || '').trim();
        const normalizedEmail = normalizePersonalEmailValue(trimmedEmail);
        const normalizedCurrent = normalizePersonalEmailValue(personalEmailCurrentAddress);

        if (trimmedEmail === '') {
            setPersonalEmailAvailability('idle', '');
            setPersonalEmailStatus('', '');
            syncPersonalProfileSecurity();
            return;
        }

        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(trimmedEmail)) {
            setPersonalEmailAvailability('invalid', 'Please enter a valid email address.');
            setPersonalEmailStatus(personalEmailAvailabilityMessage, 'error');
            syncPersonalProfileSecurity();
            return;
        }

        if (normalizedEmail === normalizedCurrent) {
            setPersonalEmailAvailability('same', 'Please enter a different email address from your current one.');
            setPersonalEmailStatus(personalEmailAvailabilityMessage, 'error');
            syncPersonalProfileSecurity();
            return;
        }

        const requestId = ++personalEmailAvailabilityRequestId;
        setPersonalEmailAvailability('checking', 'Checking whether this email is already used in your workspace...');
        setPersonalEmailStatus(personalEmailAvailabilityMessage, 'info');
        syncPersonalProfileSecurity();

        personalEmailCheckTimeoutId = window.setTimeout(async () => {
            try {
                const data = await postPersonalEmailChangeAction('check_email', { email: trimmedEmail });
                if (requestId !== personalEmailAvailabilityRequestId || normalizePersonalEmailValue(getPersonalEmailDraftValue()) !== normalizedEmail) {
                    return;
                }

                setPersonalEmailAvailability('available', data.message || 'This email address is available in your workspace.');
                setPersonalEmailStatus(personalEmailAvailabilityMessage || 'This email address is available in your workspace.', 'success');
            } catch (error) {
                if (requestId !== personalEmailAvailabilityRequestId || normalizePersonalEmailValue(getPersonalEmailDraftValue()) !== normalizedEmail) {
                    return;
                }

                const nextState = error.code === 'duplicate_email' ? 'duplicate' : error.code === 'same_email' ? 'same' : error.code === 'invalid_email' ? 'invalid' : 'error';
                setPersonalEmailAvailability(nextState, error.message || 'Unable to check this email address right now.');
                setPersonalEmailStatus(personalEmailAvailabilityMessage, 'error');
            } finally {
                if (requestId === personalEmailAvailabilityRequestId) {
                    personalEmailCheckTimeoutId = null;
                    syncPersonalProfileSecurity();
                }
            }
        }, 320);
    }

    function syncPersonalProfileSecurity() {
        if (!personalProfileForm || !personalPasswordInput || !personalPasswordConfirmInput) {
            return;
        }

        const password = personalPasswordInput.value || '';
        const confirmation = personalPasswordConfirmInput.value || '';
        const hasInteraction = password !== '' || confirmation !== '';

        if (personalPasswordPanel) {
            personalPasswordPanel.hidden = !hasInteraction;
        }

        const checks = getPersonalPasswordChecks(password);
        personalPasswordRuleItems.forEach((item) => {
            const ruleName = item.getAttribute('data-personal-password-rule') || '';
            item.classList.toggle('is-met', Boolean(checks[ruleName]));
        });

        const strength = getPersonalPasswordStrength(password);
        if (personalPasswordStrengthFill) {
            personalPasswordStrengthFill.style.width = strength.width;
            personalPasswordStrengthFill.classList.remove('is-weak', 'is-fair', 'is-strong');
            if (strength.level) {
                personalPasswordStrengthFill.classList.add(strength.level);
            }
        }

        if (personalPasswordStrengthLabel) {
            personalPasswordStrengthLabel.textContent = strength.label;
        }

        let passwordError = '';
        let confirmationError = '';
        let emailChangeError = '';
        let feedbackMessage = '';
        let feedbackState = '';

        if (password !== '' && password.length < 8) {
            passwordError = 'New password must be at least 8 characters long.';
            feedbackMessage = passwordError;
            feedbackState = 'is-error';
        } else if (password === '' && confirmation !== '') {
            confirmationError = 'Enter a new password before confirming it.';
            feedbackMessage = confirmationError;
            feedbackState = 'is-error';
        } else if (password !== '' && confirmation === '') {
            feedbackMessage = 'Re-enter the new password to confirm it before saving.';
        } else if (password !== '' && confirmation !== '' && password !== confirmation) {
            confirmationError = 'Passwords do not match.';
            feedbackMessage = confirmationError;
            feedbackState = 'is-error';
        } else if (password !== '' && confirmation !== '' && password === confirmation) {
            feedbackMessage = 'Passwords match and are ready to save.';
            feedbackState = 'is-success';
        }

        const pendingEmail = getPersonalEmailDraftValue();
        const emailChangeActive = Boolean(personalEmailInput && !personalEmailInput.readOnly);
        if (emailChangeActive) {
            const emailLooksValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(pendingEmail);

            if (pendingEmail === '') {
                emailChangeError = 'Enter your new email address or cancel the email change.';
            } else if (!emailLooksValid) {
                emailChangeError = 'Please enter a valid email address.';
            } else if (personalEmailCurrentAddress && normalizePersonalEmailValue(pendingEmail) === normalizePersonalEmailValue(personalEmailCurrentAddress)) {
                emailChangeError = 'Please enter a different email address from your current one.';
            } else if (personalEmailAvailabilityState === 'checking') {
                emailChangeError = 'We are still checking that email address in your workspace.';
            } else if (personalEmailAvailabilityState === 'duplicate') {
                emailChangeError = personalEmailAvailabilityMessage || 'That email address is already being used by another account in your organization.';
            } else if (personalEmailAvailabilityState !== 'available') {
                emailChangeError = personalEmailAvailabilityMessage || 'Enter an available email address before continuing.';
            } else if (!personalEmailOtpVerified || personalEmailOtpVerified.value !== '1' || normalizePersonalEmailValue(pendingEmail) !== normalizePersonalEmailValue(personalEmailVerifiedAddress)) {
                emailChangeError = 'Verify your new email address with the OTP before saving.';
            }
        }

        personalPasswordInput.setCustomValidity(passwordError);
        personalPasswordConfirmInput.setCustomValidity(confirmationError);
        if (personalEmailInput) {
            personalEmailInput.setCustomValidity(emailChangeError);
        }

        if (personalPasswordMatch) {
            personalPasswordMatch.textContent = feedbackMessage;
            personalPasswordMatch.classList.toggle('is-error', feedbackState === 'is-error');
            personalPasswordMatch.classList.toggle('is-success', feedbackState === 'is-success');
        }

        syncPersonalEmailActionState();

        if (personalProfileSubmit) {
            personalProfileSubmit.disabled = Boolean(passwordError || confirmationError || emailChangeError);
        }
    }

    function activateSection(targetId, options = {}) {
        if (!targetId) {
            return;
        }

        const targetEl = document.getElementById(targetId);
        if (!targetEl) {
            return;
        }

        const requestedSubTabId = options.subTabId || '';
        const effectiveSubTabId = requestedSubTabId || sectionDefaults[targetId] || '';
        const navItem = options.navItem || findPreferredNavItem(targetId, requestedSubTabId);

        navItems.forEach((nav) => nav.classList.toggle('active', nav === navItem));
        viewSections.forEach((section) => section.classList.toggle('active', section.id === targetId));

        // Manage floating top controls visibility
        const globalTopControls = document.getElementById('global-top-controls');
        if (globalTopControls) {
            globalTopControls.style.display = (targetId === 'dashboard' || targetId === 'personal') ? 'none' : 'flex';
        }

        if (effectiveSubTabId !== '') {
            activateTabInSection(targetEl, effectiveSubTabId);
        }

        syncSidebarDropdownState();

        setPageTitleFromNav(navItem, targetId);
        resetWorkspaceScroll();

        replaceUrlForSection(targetId, requestedSubTabId || effectiveSubTabId);
    }

    navItems.forEach(item => {
        item.addEventListener('click', (e) => {
            const targetId = item.getAttribute('data-target');
            if(!targetId) return;
            const subTabId = item.getAttribute('data-subtab');
            const href = item.getAttribute('href') || '';
            const creditPolicySubtab = targetId === 'credit_settings' ? getCreditPolicySubtabFromItem(item, href) : '';

            if (!isSameAdminPageLink(href)) {
                return;
            }
            
            e.preventDefault();
            
            const processNavigation = () => {
                window._intendedPolicyNavigationUrl = null;
                activateSection(targetId, { navItem: item, subTabId: creditPolicySubtab || subTabId || '' });
                if (targetId === 'credit_settings' && creditPolicySubtab) {
                    activateCreditPolicySubtab(creditPolicySubtab);
                }
                replaceUrlForSection(targetId, creditPolicySubtab || subTabId || '', href);
            };

            const policyUnsavedManager = window.policyConsoleUnsavedManager;
            const hasPolicyChanges = policyUnsavedManager && typeof policyUnsavedManager.isDirty === 'function'
                ? policyUnsavedManager.isDirty()
                : Boolean(window._isPolicyFormDirty);

            if (hasPolicyChanges && targetId !== 'credit_settings') {
                const unsavedModal = document.getElementById('policy-unsaved-modal');
                if (unsavedModal) {
                    unsavedModal.hidden = false;
                    window._intendedPolicyNavigation = processNavigation;
                    window._intendedPolicyNavigationUrl = href || '';
                    return;
                }
            }

            processNavigation();
        });
    });

    // Auto-navigate if hash or query params target a specific section/sub-tab
    const urlParams = new URLSearchParams(window.location.search);
    const hashTarget = window.location.hash ? window.location.hash.substring(1) : '';
    const sectionParam = urlParams.get('section') || '';
    const tabParam = urlParams.get('tab') || '';
    const subParam = urlParams.get('sub') || '';
    const creditPolicyTabParam = normalizeCreditPolicySubtab(urlParams.get('credit_policy_tab') || '');

    let initialRoute = null;
    if (hashTarget && document.getElementById(hashTarget)) {
        initialRoute = {
            sectionId: hashTarget,
            subTabId: hashTarget === 'billing' && billingSubtabMap[subParam] ? billingSubtabMap[subParam] : '',
        };
    } else if (sectionParam && document.getElementById(sectionParam)) {
        initialRoute = {
            sectionId: sectionParam,
            subTabId: sectionParam === 'billing' && billingSubtabMap[subParam] ? billingSubtabMap[subParam] : '',
        };
    } else if (tabParam && sectionRouteMap[tabParam]) {
        initialRoute = {
            sectionId: sectionRouteMap[tabParam].sectionId,
            subTabId: sectionRouteMap[tabParam].sectionId === 'credit_settings'
                ? creditPolicyTabParam
                : tabParam === 'billing' && billingSubtabMap[subParam]
                ? billingSubtabMap[subParam]
                : (sectionRouteMap[tabParam].subTabId || ''),
        };
    } else if (subParam && billingSubtabMap[subParam]) {
        initialRoute = { sectionId: 'billing', subTabId: billingSubtabMap[subParam] };
    }

    if (loanProductsModalOpen) {
        activateSection('loan_products', { navItem: findPreferredNavItem('loan_products') });
    } else if (initialRoute && initialRoute.sectionId) {
        activateSection(initialRoute.sectionId, { subTabId: initialRoute.subTabId || '' });
        if (initialRoute.sectionId === 'credit_settings' && initialRoute.subTabId) {
            activateCreditPolicySubtab(initialRoute.subTabId);
        }
    }

    window.addEventListener('pageshow', () => {
        resetWorkspaceScroll();
    });

    function syncReceiptPeriodFields() {
        if (!receiptPeriodSelect || receiptPeriodFields.length === 0) {
            return;
        }

        const activePeriod = receiptPeriodSelect.value || 'all';
        receiptPeriodFields.forEach((field) => {
            const targetPeriod = field.getAttribute('data-receipt-period-field');
            const shouldShow = targetPeriod === activePeriod;
            field.classList.toggle('is-hidden', !shouldShow);

            const inputs = field.querySelectorAll('input, select');
            inputs.forEach((input) => {
                input.disabled = !shouldShow;
            });
        });
    }

    if (receiptPeriodSelect) {
        syncReceiptPeriodFields();
        receiptPeriodSelect.addEventListener('change', syncReceiptPeriodFields);
    }

    const CREDIT_STANDARD_CATEGORIES = [
        'Student',
        'Government Employee',
        'Private Employee',
        'Self-Employed',
        'Business Owner',
        'Freelancer',
        'OFW',
        'Farmer',
        'Driver',
        'Vendor',
        'Senior Citizen',
        'Unemployed',
    ];
    const CREDIT_WORKFLOW_LABELS = {
        auto: 'Fully Automatic',
        semi: 'Semi-Automatic',
        manual: 'Fully Manual',
    };
    const CREDIT_SCORE_CEILING = 1000;
    const CREDIT_SCORING_PRESETS = {
        balanced: {
            minimumScore: 500,
            autoRejectBelow: 300,
            requireCi: true,
            weights: {
                income: 25,
                employment: 20,
                creditHistory: 20,
                collateral: 10,
                character: 15,
                business: 10,
            },
        },
        conservative: {
            minimumScore: 650,
            autoRejectBelow: 400,
            requireCi: true,
            weights: {
                income: 18,
                employment: 15,
                creditHistory: 25,
                collateral: 20,
                character: 12,
                business: 10,
            },
        },
        growth: {
            minimumScore: 450,
            autoRejectBelow: 250,
            requireCi: false,
            weights: {
                income: 30,
                employment: 18,
                creditHistory: 15,
                collateral: 10,
                character: 12,
                business: 15,
            },
        },
    };

    function formatPeso(value) {
        const amount = Number.isFinite(Number(value)) ? Number(value) : 0;
        return `PHP ${amount.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function collectCreditScoringState() {
        const weights = {
            income: Number.parseInt(creditWeightInputs.income?.value || '0', 10) || 0,
            employment: Number.parseInt(creditWeightInputs.employment?.value || '0', 10) || 0,
            creditHistory: Number.parseInt(creditWeightInputs.creditHistory?.value || '0', 10) || 0,
            collateral: Number.parseInt(creditWeightInputs.collateral?.value || '0', 10) || 0,
            character: Number.parseInt(creditWeightInputs.character?.value || '0', 10) || 0,
            business: Number.parseInt(creditWeightInputs.business?.value || '0', 10) || 0,
        };

        return {
            minimumScore: Number.parseInt(creditMinimumScoreInput?.value || '0', 10) || 0,
            autoRejectBelow: Number.parseInt(creditAutoRejectBelowInput?.value || '0', 10) || 0,
            requireCi: Boolean(creditRequireCiInput?.checked),
            weights,
            totalWeight: Object.values(weights).reduce((sum, value) => sum + value, 0),
        };
    }

    function applyCreditPreset(presetKey) {
        const preset = CREDIT_SCORING_PRESETS[presetKey];
        if (!preset) {
            return;
        }

        if (creditMinimumScoreInput) {
            creditMinimumScoreInput.value = String(preset.minimumScore);
        }
        if (creditAutoRejectBelowInput) {
            creditAutoRejectBelowInput.value = String(preset.autoRejectBelow);
        }
        if (creditRequireCiInput) {
            creditRequireCiInput.checked = Boolean(preset.requireCi);
        }

        Object.entries(preset.weights).forEach(([key, value]) => {
            if (creditWeightInputs[key]) {
                creditWeightInputs[key].value = String(value);
            }
        });
    }

    function refreshCreditScoringSummary() {
        if (!creditScoringForm) {
            return;
        }

        const state = collectCreditScoringState();
        const totalIsValid = state.totalWeight === 100;
        const autoRejectIsValid = state.autoRejectBelow <= state.minimumScore;
        const activePresetKey = Object.entries(CREDIT_SCORING_PRESETS).find(([, preset]) => {
            return state.minimumScore === preset.minimumScore
                && state.autoRejectBelow === preset.autoRejectBelow
                && state.requireCi === preset.requireCi
                && Object.entries(preset.weights).every(([key, value]) => state.weights[key] === value);
        })?.[0] || '';

        if (creditWeightTotalValue) {
            creditWeightTotalValue.textContent = `${state.totalWeight}%`;
        }
        if (creditWeightTotalBadge) {
            creditWeightTotalBadge.classList.toggle('is-valid', totalIsValid && autoRejectIsValid);
            creditWeightTotalBadge.classList.toggle('is-invalid', !totalIsValid || !autoRejectIsValid);
        }
        if (creditWeightTotalMessage) {
            let helperText = `Weights are balanced at exactly ${state.totalWeight}%.`;
            if (!totalIsValid) {
                helperText = `Weights must total exactly 100%. Current total: ${state.totalWeight}%.`;
            } else if (!autoRejectIsValid) {
                helperText = 'Auto-reject cannot be higher than the minimum approval score.';
            } else {
                helperText = `Borrowers below ${state.autoRejectBelow} are declined automatically, while ${state.minimumScore}+ moves forward for review.`;
            }
            creditWeightTotalMessage.textContent = helperText;
            creditWeightTotalMessage.classList.toggle('is-valid', totalIsValid && autoRejectIsValid);
            creditWeightTotalMessage.classList.toggle('is-invalid', !totalIsValid || !autoRejectIsValid);
        }

        if (creditSummaryMinScore) {
            creditSummaryMinScore.textContent = `${state.minimumScore}/${CREDIT_SCORE_CEILING}`;
        }
        if (creditSummaryAutoReject) {
            creditSummaryAutoReject.textContent = `Below ${state.autoRejectBelow}`;
        }
        if (creditSummaryCi) {
            creditSummaryCi.textContent = state.requireCi ? 'Required' : 'Optional';
        }
        if (creditSummaryWeightStatus) {
            creditSummaryWeightStatus.textContent = `${state.totalWeight}% total`;
        }
        if (creditScoringPolicyNote) {
            const ciText = state.requireCi ? 'A credit investigation is required before final approval.' : 'Credit investigation stays optional for the reviewing team.';
            creditScoringPolicyNote.textContent = `Borrowers must reach ${state.minimumScore}/${CREDIT_SCORE_CEILING} to proceed, while scores below ${state.autoRejectBelow} are declined immediately. ${ciText}`;
        }
        if (creditOverviewMinScore) {
            creditOverviewMinScore.textContent = `${state.minimumScore}/${CREDIT_SCORE_CEILING}`;
        }
        if (creditOverviewCi) {
            creditOverviewCi.textContent = state.requireCi ? 'Required' : 'Optional';
        }

        creditPresetButtons.forEach((button) => {
            button.classList.toggle('is-active', button.getAttribute('data-credit-preset') === activePresetKey);
        });

        Object.entries(state.weights).forEach(([key, value]) => {
            if (creditWeightDisplays[key]?.value) {
                creditWeightDisplays[key].value.textContent = `${value}%`;
            }
            if (creditWeightDisplays[key]?.bar) {
                creditWeightDisplays[key].bar.style.width = `${Math.max(0, Math.min(100, value))}%`;
            }
        });
    }

    function parseCreditLimitRulesSeed() {
        if (!creditLimitRulesSeed) {
            return null;
        }

        try {
            return JSON.parse(creditLimitRulesSeed.textContent || '{}');
        } catch (error) {
            return null;
        }
    }

    function getCreditCategoryLabel(row) {
        const select = row.querySelector('[data-credit-category-select]');
        const custom = row.querySelector('[data-credit-category-custom]');
        if (!select) {
            return '';
        }

        if (select.value.trim() === '') {
            return '';
        }

        if (select.value === 'Others') {
            return custom ? custom.value.trim() : '';
        }

        return select.value.trim();
    }

    function updateCreditWorkflowSelection() {
        creditWorkflowInputs.forEach((input) => {
            const option = input.closest('.credit-workflow-option');
            if (option) {
                option.classList.toggle('is-active', input.checked);
            }
        });
    }

    function updateCreditCategoryPreview(row) {
        const preview = row.querySelector('[data-credit-category-preview]');
        const typeSelect = row.querySelector('[data-credit-category-type]');
        const valueInput = row.querySelector('[data-credit-category-value]');
        const customWrap = row.querySelector('[data-credit-category-custom-wrap]');
        const customInput = row.querySelector('[data-credit-category-custom]');
        const label = getCreditCategoryLabel(row);
        const numericValue = Number.parseFloat(valueInput?.value || '0') || 0;

        if (customWrap && customInput) {
            const isCustom = row.querySelector('[data-credit-category-select]')?.value === 'Others';
            customWrap.classList.toggle('is-visible', isCustom);
            customInput.disabled = false;
            if (!isCustom) {
                customInput.value = '';
            }
        }

        if (!preview || !typeSelect) {
            return;
        }

        if (label === '') {
            preview.textContent = 'Select a borrower category to preview this rule.';
            return;
        }

        if (typeSelect.value === 'fixed') {
            preview.textContent = `${label} starts at ${formatPeso(numericValue)}.`;
            return;
        }

        preview.textContent = `${label} starts at ${numericValue.toLocaleString('en-PH', { minimumFractionDigits: 0, maximumFractionDigits: 2 })}% of monthly income.`;
    }

    function collectCreditLimitRulesState() {
        const approvalMode = creditWorkflowInputs.find((input) => input.checked)?.value || 'semi';
        const baseLimit = Number.parseFloat(creditBaseLimitInput?.value || '0') || 0;
        const minCompletedLoans = Number.parseInt(creditMinCompletedLoansInput?.value || '0', 10) || 0;
        const maxLatePayments = Number.parseInt(creditMaxLatePaymentsInput?.value || '0', 10) || 0;
        const increaseType = creditIncreaseTypeInput?.value || 'percentage';
        const increaseValue = Number.parseFloat(creditIncreaseValueInput?.value || '0') || 0;
        const absoluteMaxLimit = Number.parseFloat(creditAbsoluteMaxLimitInput?.value || '0') || 0;
        const customCategories = [];

        if (creditLimitRulesContainer) {
            creditLimitRulesContainer.querySelectorAll('.credit-category-row').forEach((row) => {
                const typeSelect = row.querySelector('[data-credit-category-type]');
                const valueInput = row.querySelector('[data-credit-category-value]');
                const categoryName = getCreditCategoryLabel(row);
                const value = Number.parseFloat(valueInput?.value || '0') || 0;

                if (!categoryName || !typeSelect) {
                    return;
                }

                customCategories.push({
                    category_name: categoryName,
                    limit_type: typeSelect.value || 'fixed',
                    value,
                });
            });
        }

        return {
            workflow: { approval_mode: approvalMode },
            initial_limits: {
                base_limit_default: baseLimit,
                custom_categories: customCategories,
            },
            upgrade_eligibility: {
                min_completed_loans: minCompletedLoans,
                max_allowed_late_payments: maxLatePayments,
            },
            increase_rules: {
                increase_type: increaseType,
                increase_value: increaseValue,
                absolute_max_limit: absoluteMaxLimit,
            },
        };
    }

    function renderCreditInitialLimitLogic(state) {
        if (!creditSummaryInitialLogic) {
            return;
        }

        const baseLimit = state.initial_limits.base_limit_default;
        const defaultCard = `
            <div class="credit-initial-logic-card is-default">
                <div class="credit-initial-logic-top">
                    <strong>Standard borrower</strong>
                    <span>Base limit</span>
                </div>
                <div class="credit-initial-logic-amount">${escapeHtml(formatPeso(baseLimit))}</div>
                <p>Every borrower starts from this standard limit unless a category override applies.</p>
            </div>
        `;

        if (state.initial_limits.custom_categories.length === 0) {
            creditSummaryInitialLogic.innerHTML = `${defaultCard}
                <div class="credit-initial-logic-card">
                    <div class="credit-initial-logic-top">
                        <strong>No category overrides yet</strong>
                        <span>Uses base limit</span>
                    </div>
                    <p>All borrowers will use the standard starting limit until you add an initial-limit rule.</p>
                </div>`;
            return;
        }

        const categoryCards = state.initial_limits.custom_categories.map((rule) => {
            let amountLabel = '';
            let note = '';
            let badge = 'Custom rule';

            if (rule.limit_type === 'fixed') {
                amountLabel = formatPeso(rule.value);
                note = 'Uses a fixed starting limit for this borrower category.';
                badge = 'Fixed amount';
            } else {
                amountLabel = `${rule.value.toLocaleString('en-PH', { minimumFractionDigits: 0, maximumFractionDigits: 2 })}% of income`;
                note = 'Starting limit depends on the borrower\'s monthly income.';
                badge = 'Income percent';
            }

            return `
                <div class="credit-initial-logic-card">
                    <div class="credit-initial-logic-top">
                        <strong>${escapeHtml(rule.category_name)}</strong>
                        <span>${escapeHtml(badge)}</span>
                    </div>
                    <div class="credit-initial-logic-amount">${escapeHtml(amountLabel)}</div>
                    <p>${escapeHtml(note)}</p>
                </div>
            `;
        }).join('');

        creditSummaryInitialLogic.innerHTML = defaultCard + categoryCards;
    }

    function refreshCreditLimitCalculator(state) {
        if (!creditPreviewCategoryInput || !creditPreviewIncomeInput || !creditPreviewLimitOutput || !creditPreviewLimitNote) {
            return;
        }

        const currentValue = creditPreviewCategoryInput.value || '';
        const hasCustomRules = state.initial_limits.custom_categories.length > 0;
        const categoryOptions = [hasCustomRules
            ? '<option value="">Select a category rule</option>'
            : '<option value="">No category rules yet</option>']
            .concat(state.initial_limits.custom_categories.map((rule) => `<option value="${escapeHtml(rule.category_name)}">${escapeHtml(rule.category_name)}</option>`));
        creditPreviewCategoryInput.innerHTML = categoryOptions.join('');
        creditPreviewCategoryInput.disabled = !hasCustomRules;

        const nextValue = state.initial_limits.custom_categories.some((rule) => rule.category_name === currentValue)
            ? currentValue
            : '';
        creditPreviewCategoryInput.value = nextValue;

        const income = Number.parseFloat(creditPreviewIncomeInput.value || '0') || 0;
        const selectedRule = state.initial_limits.custom_categories.find((rule) => rule.category_name === creditPreviewCategoryInput.value);
        const usesIncome = Boolean(selectedRule && selectedRule.limit_type === 'income_percent');

        let amount = state.initial_limits.base_limit_default;
        let note = hasCustomRules
            ? 'Showing the standard starting limit. Select a category rule to preview its override.'
            : 'No category rule yet. Showing the standard starting limit.';

        if (selectedRule) {
            if (selectedRule.limit_type === 'fixed') {
                amount = selectedRule.value;
                note = `${selectedRule.category_name} uses a fixed starting limit. The income slider only affects income-based rules.`;
            } else {
                amount = income * (selectedRule.value / 100);
                note = `${selectedRule.category_name} starts at ${selectedRule.value}% of the borrower's monthly income.`;
            }
        }

        const maxLimit = Math.max(state.increase_rules.absolute_max_limit, amount, state.initial_limits.base_limit_default, 1);
        const fillWidth = Math.max(6, Math.min(100, (amount / maxLimit) * 100));

        if (creditPreviewIncomeDisplay) {
            creditPreviewIncomeDisplay.textContent = formatPeso(income);
        }
        creditPreviewIncomeInput.disabled = !usesIncome;
        creditPreviewLimitOutput.textContent = formatPeso(amount);
        creditPreviewLimitNote.textContent = note;
        if (creditPreviewLimitFill) {
            creditPreviewLimitFill.style.width = `${fillWidth}%`;
        }

        const completedLoans = Number.parseInt(creditPreviewCompletedLoansInput?.value || '0', 10) || 0;
        const latePayments = Number.parseInt(creditPreviewLatePaymentsInput?.value || '0', 10) || 0;
        const minCompletedLoans = state.upgrade_eligibility.min_completed_loans;
        const maxLatePayments = state.upgrade_eligibility.max_allowed_late_payments;
        const meetsCompletedLoanRule = completedLoans >= minCompletedLoans;
        const meetsLatePaymentRule = latePayments <= maxLatePayments;
        const upgradeEligible = meetsCompletedLoanRule && meetsLatePaymentRule;

        let projectedNextLimit = amount;
        if (state.increase_rules.increase_type === 'percentage') {
            projectedNextLimit = amount + (amount * (state.increase_rules.increase_value / 100));
        } else {
            projectedNextLimit = amount + state.increase_rules.increase_value;
        }
        if (state.increase_rules.absolute_max_limit > 0) {
            projectedNextLimit = Math.min(projectedNextLimit, state.increase_rules.absolute_max_limit);
        }

        if (creditPreviewUpgradeStatus) {
            creditPreviewUpgradeStatus.textContent = upgradeEligible ? 'Eligible for upgrade' : 'Not yet eligible';
        }

        if (creditPreviewUpgradeNote) {
            const blockers = [];
            if (!meetsCompletedLoanRule) {
                blockers.push(`Needs ${minCompletedLoans - completedLoans} more completed loan${minCompletedLoans - completedLoans === 1 ? '' : 's'}.`);
            }
            if (!meetsLatePaymentRule) {
                blockers.push(`Late payments must stay at ${maxLatePayments} or fewer.`);
            }
            creditPreviewUpgradeNote.textContent = upgradeEligible
                ? 'This borrower currently meets the upgrade history rules.'
                : blockers.join(' ');
        }

        if (creditPreviewNextLimitOutput) {
            creditPreviewNextLimitOutput.textContent = formatPeso(projectedNextLimit);
        }

        if (creditPreviewNextLimitNote) {
            if (amount <= 0) {
                creditPreviewNextLimitNote.textContent = 'Set a usable starting limit first before projecting the next upgrade.';
            } else if (upgradeEligible) {
                creditPreviewNextLimitNote.textContent = 'Uses the simulated starting limit as the current limit, then applies the current increase rule.';
            } else {
                creditPreviewNextLimitNote.textContent = 'Shows the next possible limit once the borrower satisfies the current upgrade rules.';
            }
        }
    }

    function refreshCreditLimitSummary() {
        if (!creditLimitRulesForm) {
            return;
        }

        updateCreditWorkflowSelection();

        if (creditLimitRulesContainer) {
            creditLimitRulesContainer.querySelectorAll('.credit-category-row').forEach((row) => {
                updateCreditCategoryPreview(row);
            });
        }

        const state = collectCreditLimitRulesState();
        const increaseValueLabel = state.increase_rules.increase_type === 'percentage'
            ? `${state.increase_rules.increase_value.toLocaleString('en-PH', { minimumFractionDigits: 0, maximumFractionDigits: 2 })}%`
            : formatPeso(state.increase_rules.increase_value);

        if (creditSummaryWorkflow) {
            creditSummaryWorkflow.textContent = CREDIT_WORKFLOW_LABELS[state.workflow.approval_mode] || 'Semi-Automatic';
        }
        if (creditOverviewApproval) {
            creditOverviewApproval.textContent = CREDIT_WORKFLOW_LABELS[state.workflow.approval_mode] || 'Semi-Automatic';
        }
        if (creditSummaryBaseLimit) {
            creditSummaryBaseLimit.textContent = formatPeso(state.initial_limits.base_limit_default);
        }
        if (creditOverviewBaseLimit) {
            creditOverviewBaseLimit.textContent = formatPeso(state.initial_limits.base_limit_default);
        }
        if (creditSummaryUpgrade) {
            creditSummaryUpgrade.textContent = `${state.upgrade_eligibility.min_completed_loans} completed loans, ${state.upgrade_eligibility.max_allowed_late_payments} late payments max`;
        }
        if (creditSummaryIncrease) {
            creditSummaryIncrease.textContent = `${increaseValueLabel} up to ${formatPeso(state.increase_rules.absolute_max_limit)}`;
        }
        renderCreditInitialLimitLogic(state);
        refreshCreditLimitCalculator(state);
        if (creditSummaryCategories) {
            if (state.initial_limits.custom_categories.length === 0) {
                creditSummaryCategories.innerHTML = '<div class=\"credit-summary-empty\">No category-specific limit rules yet.</div>';
            } else {
                creditSummaryCategories.innerHTML = state.initial_limits.custom_categories.map((rule) => {
                    let description = '';
                    if (rule.limit_type === 'fixed') {
                        description = `Starts at ${formatPeso(rule.value)}.`;
                    } else {
                        description = `Starts at ${rule.value.toLocaleString('en-PH', { minimumFractionDigits: 0, maximumFractionDigits: 2 })}% of monthly income.`;
                    }
                    return `<div class="credit-summary-category"><b>${escapeHtml(rule.category_name)}</b><br>${escapeHtml(description)}</div>`;
                }).join('');
            }
        }
        if (creditLimitRulesPayload) {
            creditLimitRulesPayload.value = JSON.stringify(state);
        }
    }

    function syncCreditCategoryRuleState() {
        if (!creditLimitRulesContainer) {
            return;
        }

        const rows = Array.from(creditLimitRulesContainer.querySelectorAll('.credit-category-row'));

        if (creditLimitRulesAddButton) {
            creditLimitRulesAddButton.style.display = rows.length === 0 ? 'none' : '';
        }

        if (rows.length > 0) {
            const emptyRow = creditLimitRulesContainer.querySelector('.credit-category-empty-row');
            if (emptyRow) {
                emptyRow.remove();
            }
            return;
        }

        creditLimitRulesContainer.innerHTML = `
            <div class="credit-category-empty-row">
                <div>
                    <strong>No initial limit rules yet</strong>
                    <p>Add your first category rule to define a custom starting limit.</p>
                </div>
                <button type="button" class="btn btn-sm btn-outline" data-credit-category-empty-add>
                    <span class="material-symbols-rounded">add</span>
                    Add Rule
                </button>
            </div>
        `;

        const emptyAddButton = creditLimitRulesContainer.querySelector('[data-credit-category-empty-add]');
        if (emptyAddButton) {
            emptyAddButton.addEventListener('click', () => {
                buildCreditCategoryRow({});
                refreshCreditLimitSummary();
            });
        }
    }

    function buildCreditCategoryRow(rule = {}) {
        if (!creditLimitRulesContainer) {
            return null;
        }

        const savedName = typeof rule.category_name === 'string' ? rule.category_name.trim() : '';
        const isCustomCategory = savedName !== '' && !CREDIT_STANDARD_CATEGORIES.includes(savedName);
        const selectedCategory = isCustomCategory ? 'Others' : (savedName !== '' ? savedName : '');
        const limitType = typeof rule.limit_type === 'string' ? rule.limit_type : 'fixed';
        const value = Number.isFinite(Number(rule.value)) ? Number(rule.value) : 0;

        const emptyRow = creditLimitRulesContainer.querySelector('.credit-category-empty-row');
        if (emptyRow) {
            emptyRow.remove();
        }

        const row = document.createElement('div');
        row.className = 'credit-category-row';
        row.innerHTML = `
            <div class="credit-category-row-grid">
                <div>
                    <label class="form-label">Borrower Category</label>
                    <select class="form-control" name="credit_category_select[]" data-credit-category-select>
                        <option value="" ${selectedCategory === '' ? 'selected' : ''}>Select category</option>
                        ${CREDIT_STANDARD_CATEGORIES.map((category) => `<option value="${category}" ${selectedCategory === category ? 'selected' : ''}>${category}</option>`).join('')}
                        <option value="Others" ${selectedCategory === 'Others' ? 'selected' : ''}>Others</option>
                    </select>
                    <div class="credit-category-custom-wrap ${selectedCategory === 'Others' ? 'is-visible' : ''}" data-credit-category-custom-wrap>
                        <input type="text" class="form-control" name="credit_category_custom[]" data-credit-category-custom placeholder="Custom category name" value="${isCustomCategory ? escapeHtml(savedName) : ''}">
                    </div>
                </div>
                <div>
                    <label class="form-label">Rule Type</label>
                    <select class="form-control" name="credit_category_type[]" data-credit-category-type>
                        <option value="fixed" ${limitType === 'fixed' ? 'selected' : ''}>Fixed amount</option>
                        <option value="income_percent" ${limitType === 'income_percent' ? 'selected' : ''}>Income percent</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Value</label>
                    <input type="number" class="form-control" name="credit_category_value[]" data-credit-category-value min="0" step="0.01" value="${value}">
                </div>
                <div style="display:flex; align-items:flex-end; height:100%;">
                    <button type="button" class="credit-category-remove" data-credit-category-remove title="Remove rule">
                        <span class="material-symbols-rounded">delete</span>
                    </button>
                </div>
            </div>
            <div class="credit-category-preview" data-credit-category-preview></div>
        `;

        const watchedInputs = row.querySelectorAll('select, input');
        watchedInputs.forEach((input) => {
            input.addEventListener('input', refreshCreditLimitSummary);
            input.addEventListener('change', refreshCreditLimitSummary);
        });

        const removeButton = row.querySelector('[data-credit-category-remove]');
        if (removeButton) {
            removeButton.addEventListener('click', () => {
                row.remove();
                syncCreditCategoryRuleState();
                refreshCreditLimitSummary();
            });
        }

        creditLimitRulesContainer.appendChild(row);
        syncCreditCategoryRuleState();
        updateCreditCategoryPreview(row);

        return row;
    }

    if (creditLimitRulesForm && creditLimitRulesContainer) {
        const seed = parseCreditLimitRulesSeed();
        const seededCategories = Array.isArray(seed?.initial_limits?.custom_categories) ? seed.initial_limits.custom_categories : [];

        if (seededCategories.length > 0) {
            seededCategories.forEach((rule) => buildCreditCategoryRow(rule));
        }
        syncCreditCategoryRuleState();

        [
            creditBaseLimitInput,
            creditMinCompletedLoansInput,
            creditMaxLatePaymentsInput,
            creditIncreaseTypeInput,
            creditIncreaseValueInput,
            creditAbsoluteMaxLimitInput,
            ...creditWorkflowInputs,
        ].filter(Boolean).forEach((input) => {
            input.addEventListener('input', refreshCreditLimitSummary);
            input.addEventListener('change', refreshCreditLimitSummary);
        });

        if (creditLimitRulesAddButton) {
            creditLimitRulesAddButton.addEventListener('click', () => {
                buildCreditCategoryRow({});
                refreshCreditLimitSummary();
            });
        }

        [
            creditPreviewCategoryInput,
            creditPreviewIncomeInput,
            creditPreviewCompletedLoansInput,
            creditPreviewLatePaymentsInput,
        ].filter(Boolean).forEach((input) => {
            input.addEventListener('input', refreshCreditLimitSummary);
            input.addEventListener('change', refreshCreditLimitSummary);
        });

        creditLimitRulesForm.addEventListener('submit', () => {
            refreshCreditLimitSummary();
        });

        refreshCreditLimitSummary();
    }

    if (creditScoringForm) {
        [
            creditMinimumScoreInput,
            creditAutoRejectBelowInput,
            creditRequireCiInput,
            ...Object.values(creditWeightInputs),
        ].filter(Boolean).forEach((input) => {
            input.addEventListener('input', refreshCreditScoringSummary);
            input.addEventListener('change', refreshCreditScoringSummary);
        });

        creditPresetButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const presetKey = button.getAttribute('data-credit-preset') || '';
                applyCreditPreset(presetKey);
                creditPresetButtons.forEach((item) => item.classList.toggle('is-active', item === button));
                refreshCreditScoringSummary();
            });
        });

        refreshCreditScoringSummary();
    }

    if (setupAlert && setupAlertToggle) {
        const storageKey = 'tenantAdminSetupAlertMinimized';
        const setupAlertIcon = setupAlertToggle.querySelector('.material-symbols-rounded');

        const applySetupAlertState = (isMinimized) => {
            setupAlert.classList.toggle('is-minimized', isMinimized);
            setupAlertToggle.setAttribute('aria-expanded', isMinimized ? 'false' : 'true');
            setupAlertToggle.setAttribute('title', isMinimized ? 'Expand setup alert' : 'Minimize setup alert');
            if (setupAlertIcon) {
                setupAlertIcon.textContent = isMinimized ? 'open_in_full' : 'remove';
            }
        };

        let isMinimized = false;
        try {
            isMinimized = window.localStorage.getItem(storageKey) === '1';
        } catch (error) {
            isMinimized = false;
        }
        applySetupAlertState(isMinimized);

        setupAlertToggle.addEventListener('click', () => {
            const nextState = !setupAlert.classList.contains('is-minimized');
            applySetupAlertState(nextState);
            try {
                window.localStorage.setItem(storageKey, nextState ? '1' : '0');
            } catch (error) {
                // Ignore storage failures and keep the UI responsive.
            }
        });
    }

    if (personalProfileForm && personalPasswordInput && personalPasswordConfirmInput) {
        [personalPasswordInput, personalPasswordConfirmInput].forEach((input) => {
            input.addEventListener('input', syncPersonalProfileSecurity);
            input.addEventListener('change', syncPersonalProfileSecurity);
        });

        if (personalPasswordToggleButtons.length) {
            const personalPasswordInputs = [personalPasswordInput, personalPasswordConfirmInput].filter(Boolean);

            const setPersonalPasswordVisibility = (isVisible) => {
                personalPasswordInputs.forEach((input) => {
                    input.type = isVisible ? 'text' : 'password';
                });

                personalPasswordToggleButtons.forEach((button) => {
                    const icon = button.querySelector('.material-symbols-rounded');
                    const label = button.querySelector('[data-password-toggle-text]');
                    button.setAttribute('aria-label', isVisible ? 'Hide passwords' : 'Show passwords');
                    if (icon) {
                        icon.textContent = isVisible ? 'visibility_off' : 'visibility';
                    }
                    if (label) {
                        label.textContent = isVisible ? 'Hide' : 'Show';
                    }
                });
            };

            setPersonalPasswordVisibility(false);

            personalPasswordToggleButtons.forEach((button) => {
                const targetId = button.getAttribute('data-password-toggle') || '';
                const targetInput = document.getElementById(targetId);

                button.addEventListener('click', () => {
                    const nextVisibleState = personalPasswordInput.type === 'password';
                    setPersonalPasswordVisibility(nextVisibleState);
                    if (targetInput) {
                        targetInput.focus();
                    }
                });
            });
        }

        if (personalEmailInput && personalEmailToggle) {
            setPersonalEmailEditable(false);
            personalEmailInput.value = personalEmailOriginalValue;
            personalEmailInput.placeholder = personalEmailCurrentAddress;
            resetPersonalEmailOtpState();
            setPersonalEmailAvailability('idle', '');

            personalEmailInput.addEventListener('input', () => {
                const nextValue = getPersonalEmailDraftValue();
                const normalizedNextValue = normalizePersonalEmailValue(nextValue);
                const normalizedTrackedAddress = normalizePersonalEmailValue(personalEmailTrackedServerAddress);
                const isVerifiedForCurrentDraft = personalEmailOtpVerified
                    && personalEmailOtpVerified.value === '1'
                    && normalizedNextValue !== ''
                    && normalizedNextValue === normalizePersonalEmailValue(personalEmailVerifiedAddress);

                if (normalizedTrackedAddress !== '' && normalizedTrackedAddress !== normalizedNextValue) {
                    clearTrackedPersonalEmailServerState();
                }

                if (!isVerifiedForCurrentDraft && ((personalEmailOtpVerified && personalEmailOtpVerified.value === '1') || (personalEmailOtpPanel && !personalEmailOtpPanel.hidden))) {
                    resetPersonalEmailOtpState();
                }

                schedulePersonalEmailAvailabilityCheck(nextValue);
                syncPersonalProfileSecurity();
            });

            personalEmailToggle.addEventListener('click', async () => {
                const willEnableEditing = personalEmailInput.readOnly;
                if (willEnableEditing) {
                    personalEmailInput.value = '';
                    personalEmailInput.placeholder = personalEmailCurrentAddress;
                    resetPersonalEmailOtpState();
                    setPersonalEmailAvailability('idle', '');
                    setPersonalEmailEditable(true);
                    setPersonalEmailStatus('', '');
                    personalEmailInput.focus();
                    syncPersonalProfileSecurity();
                    return;
                }

                const email = getPersonalEmailDraftValue();
                const emailLooksValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);

                if (email === '') {
                    setPersonalEmailAvailability('idle', 'Enter the new email address first.');
                    setPersonalEmailStatus(personalEmailAvailabilityMessage, 'error');
                    personalEmailInput.focus();
                    syncPersonalProfileSecurity();
                    return;
                }

                if (!emailLooksValid) {
                    setPersonalEmailAvailability('invalid', 'Please enter a valid email address before requesting an OTP.');
                    setPersonalEmailStatus(personalEmailAvailabilityMessage, 'error');
                    personalEmailInput.focus();
                    syncPersonalProfileSecurity();
                    return;
                }

                if (personalEmailCurrentAddress && normalizePersonalEmailValue(email) === normalizePersonalEmailValue(personalEmailCurrentAddress)) {
                    setPersonalEmailAvailability('same', 'Please enter a different email address from your current one.');
                    setPersonalEmailStatus(personalEmailAvailabilityMessage, 'error');
                    personalEmailInput.focus();
                    syncPersonalProfileSecurity();
                    return;
                }

                if (personalEmailAvailabilityState === 'checking') {
                    setPersonalEmailStatus('Please wait while we finish checking that email address.', 'info');
                    syncPersonalProfileSecurity();
                    return;
                }

                if (personalEmailAvailabilityState !== 'available') {
                    schedulePersonalEmailAvailabilityCheck(email);
                    if (personalEmailAvailabilityMessage !== '') {
                        setPersonalEmailStatus(personalEmailAvailabilityMessage, personalEmailAvailabilityState === 'available' ? 'success' : personalEmailAvailabilityState === 'checking' ? 'info' : 'error');
                    }
                    syncPersonalProfileSecurity();
                    return;
                }

                personalEmailSendingOtp = true;
                if (personalEmailVerifyOtpButton) {
                    personalEmailVerifyOtpButton.disabled = true;
                }
                setPersonalEmailStatus('Sending OTP to the new email address...', 'info');
                syncPersonalProfileSecurity();

                try {
                    const data = await postPersonalEmailChangeAction('send_otp', { email });
                    personalEmailTrackedServerAddress = email;
                    resetPersonalEmailOtpState({ clearStatus: false, hidePanel: false });
                    setPersonalEmailStatus(data.message || 'OTP sent successfully.', 'success');
                    if (personalEmailOtpHint) {
                        personalEmailOtpHint.textContent = 'Enter the 6-digit OTP sent to your new email address, then verify it before saving.';
                    }
                    if (personalEmailOtpCode) {
                        personalEmailOtpCode.focus();
                    }
                } catch (error) {
                    resetPersonalEmailOtpState({ clearStatus: false });
                    if (error.code === 'duplicate_email') {
                        setPersonalEmailAvailability('duplicate', error.message || 'That email address is already being used by another account in your organization.');
                    }
                    setPersonalEmailStatus(error.message || 'Unable to send OTP right now.', 'error');
                } finally {
                    personalEmailSendingOtp = false;
                    if (personalEmailVerifyOtpButton) {
                        personalEmailVerifyOtpButton.disabled = false;
                    }
                    syncPersonalProfileSecurity();
                }
            });

            if (personalEmailCancelButton) {
                personalEmailCancelButton.addEventListener('click', () => {
                    personalEmailInput.value = personalEmailOriginalValue;
                    personalEmailInput.placeholder = personalEmailCurrentAddress;
                    resetPersonalEmailOtpState();
                    setPersonalEmailEditable(false);
                    clearTrackedPersonalEmailServerState();
                    setPersonalEmailStatus('', '');
                    syncPersonalProfileSecurity();
                });
            }
        }

        if (personalEmailOtpCode) {
            personalEmailOtpCode.addEventListener('input', () => {
                personalEmailOtpCode.value = (personalEmailOtpCode.value || '').replace(/\D/g, '').slice(0, 6);
            });
        }

        if (personalEmailVerifyOtpButton && personalEmailInput && personalEmailOtpCode) {
            personalEmailVerifyOtpButton.addEventListener('click', async () => {
                const email = (personalEmailInput.value || '').trim();
                const otpCode = (personalEmailOtpCode.value || '').replace(/\D/g, '').slice(0, 6);

                if (email === '') {
                    setPersonalEmailStatus('Enter the new email address before verifying the OTP.', 'error');
                    personalEmailInput.focus();
                    return;
                }

                if (otpCode.length !== 6) {
                    setPersonalEmailStatus('Please enter the full 6-digit OTP.', 'error');
                    personalEmailOtpCode.focus();
                    return;
                }

                personalEmailVerifyingOtp = true;
                personalEmailVerifyOtpButton.disabled = true;
                setPersonalEmailStatus('Verifying OTP...', 'info');
                syncPersonalProfileSecurity();

                try {
                    const data = await postPersonalEmailChangeAction('verify_otp', { email, otp_code: otpCode });
                    if (personalEmailOtpVerified) {
                        personalEmailOtpVerified.value = '1';
                    }
                    personalEmailVerifiedAddress = email;
                    personalEmailTrackedServerAddress = email;
                    setPersonalEmailAvailability('available', 'This email address is ready to be saved after OTP verification.');
                    setPersonalEmailStatus(data.message || 'Email verified successfully.', 'success');
                    if (personalEmailOtpHint) {
                        personalEmailOtpHint.textContent = 'OTP verified. You can now save your profile to apply the new email address.';
                    }
                } catch (error) {
                    if (personalEmailOtpVerified) {
                        personalEmailOtpVerified.value = '0';
                    }
                    personalEmailVerifiedAddress = '';
                    if (error.code === 'duplicate_email') {
                        setPersonalEmailAvailability('duplicate', error.message || 'That email address is already being used by another account in your organization.');
                        clearTrackedPersonalEmailServerState();
                    }
                    setPersonalEmailStatus(error.message || 'Unable to verify OTP right now.', 'error');
                } finally {
                    personalEmailVerifyingOtp = false;
                    personalEmailVerifyOtpButton.disabled = false;
                    syncPersonalProfileSecurity();
                }
            });
        }

        personalProfileForm.addEventListener('reset', () => {
            window.setTimeout(() => {
                if (personalEmailInput) {
                    personalEmailInput.value = personalEmailOriginalValue;
                    personalEmailInput.placeholder = personalEmailCurrentAddress;
                    resetPersonalEmailOtpState();
                    setPersonalEmailEditable(false);
                }
                clearTrackedPersonalEmailServerState();
                syncPersonalProfileSecurity();
            }, 0);
        });

        personalProfileForm.addEventListener('submit', () => {
            syncPersonalProfileSecurity();
        });

        syncPersonalProfileSecurity();
    }

    tabBtns.forEach(btn => {
        btn.addEventListener('click', (event) => {
            const href = btn.getAttribute('href') || '';
            if (!isSameAdminPageLink(href)) {
                return;
            }
            event.preventDefault();
            const tabId = btn.getAttribute('data-tab');
            const sectionEl = btn.closest('.view-section');
            activateTabInSection(sectionEl, tabId);
            resetWorkspaceScroll();
            if (sectionEl && sectionEl.id) {
                replaceUrlForSection(sectionEl.id, tabId, href);
            }
        });
    });

    // Roles & Permissions Workspace Interactions
    const roleListItems = Array.from(document.querySelectorAll('.role-list-item'));
    const rolePanels = Array.from(document.querySelectorAll('.role-permissions-panel'));
    const roleFilterInput = document.getElementById('role-filter-input');
    const roleFilterEmpty = document.getElementById('role-filter-empty');

    const normalizeSearch = (value) => String(value || '').trim().toLowerCase();

    const updatePermissionSummary = (panel) => {
        if (!panel) {
            return;
        }

        const summaryEl = panel.querySelector('.permissions-selection-summary');
        if (!summaryEl) {
            return;
        }

        const checkboxes = Array.from(panel.querySelectorAll('input[name="permissions[]"]'));
        const selectedCount = checkboxes.filter((cb) => cb.checked).length;
        const totalCount = checkboxes.length;
        summaryEl.textContent = `${selectedCount} of ${totalCount} selected`;
    };

    const updateModuleSelectionCounts = (panel) => {
        if (!panel) {
            return;
        }

        const modules = panel.querySelectorAll('.permission-module');
        modules.forEach((module) => {
            const countEl = module.querySelector('.permission-module-visible-count');
            if (!countEl) {
                return;
            }

            const selectedCount = Array.from(module.querySelectorAll('input[name="permissions[]"]')).filter((cb) => cb.checked).length;
            countEl.textContent = String(selectedCount);
        });
    };

    const applyPermissionFilter = (panel, rawQuery) => {
        if (!panel) {
            return;
        }

        const query = normalizeSearch(rawQuery);
        const modules = panel.querySelectorAll('.permission-module');
        let hasVisibleItems = false;

        modules.forEach((module) => {
            const toggleItems = module.querySelectorAll('.toggle-item[data-permission-search]');
            let moduleVisibleCount = 0;

            toggleItems.forEach((item) => {
                const searchText = normalizeSearch(item.getAttribute('data-permission-search'));
                const isVisible = query === '' || searchText.includes(query);
                item.classList.toggle('is-filter-hidden', !isVisible);
                if (isVisible) {
                    moduleVisibleCount++;
                }
            });

            module.classList.toggle('is-module-hidden', moduleVisibleCount === 0);
            if (moduleVisibleCount > 0) {
                hasVisibleItems = true;
            }
        });

        const emptyState = panel.querySelector('.permissions-empty-search');
        if (emptyState) {
            emptyState.hidden = hasVisibleItems;
        }
    };

    const setVisiblePermissionsState = (panel, shouldCheck) => {
        if (!panel) {
            return;
        }

        const checkboxes = panel.querySelectorAll('input[name="permissions[]"]');
        checkboxes.forEach((checkbox) => {
            if (checkbox.disabled) {
                return;
            }

            const toggleItem = checkbox.closest('.toggle-item');
            const moduleCard = checkbox.closest('.permission-module');
            if ((toggleItem && toggleItem.classList.contains('is-filter-hidden')) || (moduleCard && moduleCard.classList.contains('is-module-hidden'))) {
                return;
            }

            checkbox.checked = shouldCheck;
        });

        updatePermissionSummary(panel);
        updateModuleSelectionCounts(panel);
    };

    const setModuleVisiblePermissionsState = (moduleCard, shouldCheck) => {
        if (!moduleCard || moduleCard.classList.contains('is-module-hidden')) {
            return;
        }

        const checkboxes = moduleCard.querySelectorAll('input[name="permissions[]"]');
        checkboxes.forEach((checkbox) => {
            if (checkbox.disabled) {
                return;
            }

            const toggleItem = checkbox.closest('.toggle-item');
            if (toggleItem && toggleItem.classList.contains('is-filter-hidden')) {
                return;
            }

            checkbox.checked = shouldCheck;
        });

        const panel = moduleCard.closest('.role-permissions-panel');
        updatePermissionSummary(panel);
        updateModuleSelectionCounts(panel);
    };

    const activateRolePanel = (roleId, shouldPushState) => {
        if (!roleId) {
            return;
        }

        roleListItems.forEach((item) => {
            item.classList.toggle('active', item.getAttribute('data-role-id') === roleId);
        });

        rolePanels.forEach((panel) => {
            panel.style.display = 'none';
        });

        const targetPanel = document.getElementById(`role-panel-${roleId}`);
        if (targetPanel) {
            targetPanel.style.display = 'block';
            const filterInput = targetPanel.querySelector('.permissions-filter-input');
            applyPermissionFilter(targetPanel, filterInput ? filterInput.value : '');
            updatePermissionSummary(targetPanel);
            updateModuleSelectionCounts(targetPanel);
        }

        if (shouldPushState) {
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('role_id', roleId);
            window.history.pushState({}, '', currentUrl);
        }
    };

    roleListItems.forEach((item) => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            const roleId = item.getAttribute('data-role-id');
            activateRolePanel(roleId, true);
        });
    });

    rolePanels.forEach((panel) => {
        const filterInput = panel.querySelector('.permissions-filter-input');
        const bulkButtons = panel.querySelectorAll('.permission-bulk-toggle');
        const moduleBulkButtons = panel.querySelectorAll('.permission-module-toggle');
        const checkboxes = panel.querySelectorAll('input[name="permissions[]"]');

        if (filterInput) {
            filterInput.addEventListener('input', () => {
                applyPermissionFilter(panel, filterInput.value);
            });
        }

        bulkButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const mode = button.getAttribute('data-bulk');
                setVisiblePermissionsState(panel, mode === 'all');
            });
        });

        moduleBulkButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const mode = button.getAttribute('data-bulk');
                const moduleCard = button.closest('.permission-module');
                setModuleVisiblePermissionsState(moduleCard, mode === 'all');
            });
        });

        checkboxes.forEach((checkbox) => {
            checkbox.addEventListener('change', () => {
                updatePermissionSummary(panel);
                updateModuleSelectionCounts(panel);
            });
        });

        applyPermissionFilter(panel, filterInput ? filterInput.value : '');
        updatePermissionSummary(panel);
        updateModuleSelectionCounts(panel);
    });

    const applyRoleFilter = (rawQuery) => {
        const query = normalizeSearch(rawQuery);
        let visibleCount = 0;

        roleListItems.forEach((item) => {
            const searchText = normalizeSearch(item.getAttribute('data-role-search'));
            const isVisible = query === '' || searchText.includes(query);
            item.style.display = isVisible ? '' : 'none';
            if (isVisible) {
                visibleCount++;
            }
        });

        if (roleFilterEmpty) {
            roleFilterEmpty.hidden = visibleCount !== 0;
        }

        if (visibleCount === 0) {
            rolePanels.forEach((panel) => {
                panel.style.display = 'none';
            });
            return;
        }

        const activeVisible = roleListItems.some((item) => item.classList.contains('active') && item.style.display !== 'none');
        if (!activeVisible) {
            const firstVisible = roleListItems.find((item) => item.style.display !== 'none');
            if (firstVisible) {
                activateRolePanel(firstVisible.getAttribute('data-role-id'), true);
            }
        }
    };

    if (roleFilterInput) {
        roleFilterInput.addEventListener('input', () => {
            applyRoleFilter(roleFilterInput.value);
        });
    }

    const initiallyActiveRole = roleListItems.find((item) => item.classList.contains('active')) || roleListItems[0];
    if (initiallyActiveRole) {
        activateRolePanel(initiallyActiveRole.getAttribute('data-role-id'), false);
    }

    const themeToggleBtns = document.querySelectorAll('.theme-toggle');

    function applyTheme(theme) {
        htmlElement.setAttribute('data-theme', theme);
        themeToggleBtns.forEach(btn => {
            const icon = btn.querySelector('span');
            if (icon) {
                icon.textContent = theme === 'light' ? 'dark_mode' : 'light_mode';
            }
        });
    }

    async function persistTheme(theme) {
        try {
            await fetch('../../microfin_backend/api/api_theme_preference.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ theme: theme, role: 'tenant' })
            });
        } catch (error) {
            // Ignore persistence errors to keep the toggle responsive.
        }
    }

    if (themeToggleBtns.length > 0) {
        applyTheme(htmlElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light');
        themeToggleBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const currentTheme = htmlElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
                const newTheme = currentTheme === 'light' ? 'dark' : 'light';
                applyTheme(newTheme);
                persistTheme(newTheme);
            });
        });
    }

    function hexToRgb(hex) {
        return {
            r: parseInt(hex.slice(1, 3), 16),
            g: parseInt(hex.slice(3, 5), 16),
            b: parseInt(hex.slice(5, 7), 16)
        };
    }

    function rgbToHex(r, g, b) {
        return '#' + [r, g, b]
            .map((value) => Math.max(0, Math.min(255, Math.round(value))).toString(16).padStart(2, '0'))
            .join('');
    }

    function luminance(hex) {
        const { r, g, b } = hexToRgb(hex);
        const [rs, gs, bs] = [r, g, b].map((channel) => {
            const normalized = channel / 255;
            return normalized <= 0.03928 ? normalized / 12.92 : Math.pow((normalized + 0.055) / 1.055, 2.4);
        });
        return 0.2126 * rs + 0.7152 * gs + 0.0722 * bs;
    }

    function contrastRatio(a, b) {
        const l1 = luminance(a);
        const l2 = luminance(b);
        return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);
    }

    function adjustBrightness(hex, pct) {
        const { r, g, b } = hexToRgb(hex);
        const factor = pct / 100;
        if (factor > 0) {
            return rgbToHex(r + (255 - r) * factor, g + (255 - g) * factor, b + (255 - b) * factor);
        }
        const darken = 1 + factor;
        return rgbToHex(r * darken, g * darken, b * darken);
    }

    function setGlobalPrimaryColor(hex) {
        if (!hex || !/^#[0-9a-fA-F]{6}$/.test(hex)) {
            return;
        }

        htmlElement.style.setProperty('--primary-color', hex);
        const { r, g, b } = hexToRgb(hex);
        htmlElement.style.setProperty('--primary-rgb', `${r}, ${g}, ${b}`);
    }

    function wireColorPair(pickerId, inputId, onChange) {
        const picker = document.getElementById(pickerId);
        const input = document.getElementById(inputId);
        if (!picker || !input) {
            return;
        }

        picker.addEventListener('input', () => {
            input.value = picker.value;
            onChange();
        });

        input.addEventListener('input', () => {
            if (/^#[0-9a-fA-F]{6}$/.test(input.value)) {
                picker.value = input.value;
            }
            onChange();
        });
    }

    function setColorField(pickerId, inputId, value) {
        const picker = document.getElementById(pickerId);
        const input = document.getElementById(inputId);
        if (picker) {
            picker.value = value;
        }
        if (input) {
            input.value = value;
        }
    }

    let autoSyncEnabled = false;

    function syncBrandingContrast() {
        const primary = document.getElementById('primary_color')?.value || '#4f46e5';
        const bgBody = document.getElementById('bg_body')?.value || '#f8fafc';
        const bgCard = document.getElementById('bg_card')?.value || '#ffffff';
        const isDarkCard = luminance(bgCard) < 0.18;

        const secondary = isDarkCard ? adjustBrightness(primary, 30) : adjustBrightness(primary, -25);
        const lightText = '#f1f5f9';
        const darkText = '#0f172a';
        const lightMuted = '#94a3b8';
        const darkMuted = '#64748b';
        const textMain = Math.min(contrastRatio(lightText, bgCard), contrastRatio(lightText, bgBody))
            > Math.min(contrastRatio(darkText, bgCard), contrastRatio(darkText, bgBody))
            ? lightText
            : darkText;
        const textMuted = Math.min(contrastRatio(lightMuted, bgCard), contrastRatio(lightMuted, bgBody))
            > Math.min(contrastRatio(darkMuted, bgCard), contrastRatio(darkMuted, bgBody))
            ? lightMuted
            : darkMuted;

        const secondaryInput = document.getElementById('secondary_color');
        if (secondaryInput) {
            secondaryInput.value = secondary;
        }
        setColorField('picker-text-main', 'text_main', textMain);
        setColorField('picker-text-muted', 'text_muted', textMuted);
        updateBrandingPreview();
    }

    function updateBrandingPreview() {
        const primary = document.getElementById('primary_color')?.value || '#4f46e5';
        const secondary = document.getElementById('secondary_color')?.value || '#991b1b';
        const textMain = document.getElementById('text_main')?.value || '#0f172a';
        const textMuted = document.getElementById('text_muted')?.value || '#64748b';
        const bgBody = document.getElementById('bg_body')?.value || '#f8fafc';
        const bgCard = document.getElementById('bg_card')?.value || '#ffffff';
        const borderColor = document.getElementById('border_color')?.value || '#e2e8f0';
        const borderWidth = document.getElementById('card_border_width')?.value || '1';
        const shadowValue = document.getElementById('card_shadow')?.value || 'sm';

        setGlobalPrimaryColor(primary);

        if (previewStage) {
            const shadowMap = {
                none: 'none',
                sm: '0 1px 3px rgba(0,0,0,0.08)',
                md: '0 4px 12px rgba(0,0,0,0.1)',
                lg: '0 8px 24px rgba(0,0,0,0.14)'
            };
            const autoBorder = luminance(bgCard) < 0.18 ? adjustBrightness(bgCard, 18) : adjustBrightness(bgCard, -8);
            const subtleBorder = luminance(bgCard) < 0.18 ? adjustBrightness(bgCard, 10) : adjustBrightness(bgCard, -4);
            const { r, g, b } = hexToRgb(primary);

            previewStage.style.setProperty('--theme-primary', primary);
            previewStage.style.setProperty('--theme-secondary', secondary);
            previewStage.style.setProperty('--theme-text-main', textMain);
            previewStage.style.setProperty('--theme-text-muted', textMuted);
            previewStage.style.setProperty('--theme-bg-body', bgBody);
            previewStage.style.setProperty('--theme-bg-card', bgCard);
            previewStage.style.setProperty('--theme-border-color', borderColor);
            previewStage.style.setProperty('--theme-card-border-width', `${borderWidth}px`);
            previewStage.style.setProperty('--theme-card-shadow', shadowMap[shadowValue] || shadowMap.sm);
            previewStage.style.setProperty('--theme-border', autoBorder);
            previewStage.style.setProperty('--theme-border-subtle', subtleBorder);
            previewStage.style.setProperty('--primary-r', r);
            previewStage.style.setProperty('--primary-g', g);
            previewStage.style.setProperty('--primary-b', b);
            const currentFont = document.getElementById('font_family')?.value;
            if (currentFont) {
                previewStage.style.fontFamily = `'${currentFont}', sans-serif`;
            }
        }

        const borderWidthLabel = document.getElementById('border-width-label');
        if (borderWidthLabel) {
            borderWidthLabel.textContent = `${borderWidth}px`;
        }
    }

    function updateLogoPreview() {
        const logoImages = document.querySelectorAll('.preview-logo-image');
        const iconFallbacks = document.querySelectorAll('.admin-sidebar-logo > .material-symbols-rounded, .staff-sidebar-logo > .material-symbols-rounded');
        const existingLogoPath = existingLogoPathInput?.value?.trim() || '';

        const applyLogo = (source) => {
            logoImages.forEach((img) => {
                img.src = source;
                img.style.display = 'block';
            });
            iconFallbacks.forEach((icon) => {
                icon.style.display = 'none';
            });
        };

        if (logoInput?.files && logoInput.files[0]) {
            const reader = new FileReader();
            reader.onload = (event) => applyLogo(event.target?.result || '');
            reader.readAsDataURL(logoInput.files[0]);
            return;
        }

        if (existingLogoPath !== '') {
            applyLogo(existingLogoPath);
            return;
        }

        logoImages.forEach((img) => {
            img.removeAttribute('src');
            img.style.display = 'none';
        });
        iconFallbacks.forEach((icon) => {
            icon.style.display = 'inline';
        });
    }

    function updateCompanyNamePreview(value) {
        const safeValue = value || 'Company Admin';
        if (companyNameDisplay) {
            companyNameDisplay.textContent = safeValue;
        }
        document.querySelectorAll('.preview-company-name').forEach((el) => {
            el.textContent = safeValue;
        });
    }

    function formatLoanPreviewCurrency(value) {
        const safeValue = Number.isFinite(value) ? value : 0;
        return new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: 'PHP',
            currencyDisplay: 'narrowSymbol',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(safeValue);
    }

    function normalizeLoanPreviewNumber(value, fallback = 0) {
        const parsed = parseFloat(value);
        return Number.isFinite(parsed) ? parsed : fallback;
    }

    function normalizeLoanPreviewInteger(value, fallback = 0) {
        const parsed = parseInt(value, 10);
        return Number.isFinite(parsed) ? parsed : fallback;
    }

    function loanPreviewClamp(value, min, max) {
        return Math.min(Math.max(value, min), max);
    }

    function getLoanPreviewBillingCycleMeta(cycle) {
        switch (cycle) {
        case 'Quarterly':
            return {
                months: 3,
                paymentLabelSingular: 'quarterly payment',
                paymentLabelPlural: 'quarterly payments',
                paymentDescriptor: 'quarterly'
            };
        case 'Semi-Annually':
            return {
                months: 6,
                paymentLabelSingular: 'semi-annual payment',
                paymentLabelPlural: 'semi-annual payments',
                paymentDescriptor: 'semi-annual'
            };
        case 'Yearly':
            return {
                months: 12,
                paymentLabelSingular: 'annual payment',
                paymentLabelPlural: 'annual payments',
                paymentDescriptor: 'annual'
            };
        case 'Monthly':
        default:
            return {
                months: 1,
                paymentLabelSingular: 'monthly payment',
                paymentLabelPlural: 'monthly payments',
                paymentDescriptor: 'monthly'
            };
        }
    }

    function updateLoanProductsPreview() {
        if (!loanProductsForm || !loanPreviewRoot) {
            return;
        }

        const getField = (name) => loanProductsForm.querySelector(`[name="${name}"]`);
        const setPreviewText = (key, value) => {
            loanPreviewRoot.querySelectorAll(`[data-loan-preview-bind="${key}"]`).forEach((element) => {
                element.textContent = value;
            });
        };

        const productName = getField('product_name')?.value.trim() || 'Personal Cash Loan';
        const selectedProductType = getField('product_type')?.value || 'Personal Loan';
        const customProductType = getField('custom_product_type')?.value.trim() || 'Custom Loan';
        const productType = selectedProductType === 'Others' ? customProductType : selectedProductType;
        const description = getField('description')?.value.trim() || 'Borrowers will see a short description explaining what this loan is best for.';

        const minAmount = Math.max(0, normalizeLoanPreviewNumber(getField('min_amount')?.value, 0));
        const maxAmount = Math.max(minAmount, normalizeLoanPreviewNumber(getField('max_amount')?.value, minAmount));
        const billingCycle = getField('billing_cycle')?.value || 'Monthly';
        const billingCycleMeta = getLoanPreviewBillingCycleMeta(billingCycle);
        const billingCycleMonths = billingCycleMeta.months;
        const rawMinTerm = Math.max(1, normalizeLoanPreviewInteger(getField('min_term_months')?.value, billingCycleMonths));
        const rawMaxTerm = Math.max(rawMinTerm, normalizeLoanPreviewInteger(getField('max_term_months')?.value, rawMinTerm));
        const minTerm = Math.max(billingCycleMonths, Math.ceil(rawMinTerm / billingCycleMonths) * billingCycleMonths);
        const maxTerm = Math.max(minTerm, Math.floor(rawMaxTerm / billingCycleMonths) * billingCycleMonths || minTerm);

        const interestRate = Math.max(0, normalizeLoanPreviewNumber(getField('interest_rate')?.value, 0));
        const interestType = getField('interest_type')?.value || 'Declining Balance';
        const earlySettlementFeeToggle = loanProductsForm.querySelector('#esf_master_toggle');
        const earlySettlementTypeSwitch = loanProductsForm.querySelector('#early_settlement_toggle_switch');
        const earlySettlementFeeEnabled = earlySettlementFeeToggle ? earlySettlementFeeToggle.checked : false;
        const earlySettlementFeeType = earlySettlementTypeSwitch
            ? (earlySettlementTypeSwitch.checked ? 'Fixed' : 'Percentage')
            : (getField('early_settlement_fee_type')?.value || 'Percentage');
        const earlySettlementFeeValue = Math.max(0, normalizeLoanPreviewNumber(getField('early_settlement_fee_value')?.value, 0));
        const gracePeriodDays = Math.max(0, normalizeLoanPreviewInteger(getField('grace_period_days')?.value, 0));

        const processingFeeRate = Math.max(0, normalizeLoanPreviewNumber(getField('processing_fee_percentage')?.value, 0));
        const insuranceFeeRate = Math.max(0, normalizeLoanPreviewNumber(getField('insurance_fee_percentage')?.value, 0));
        const serviceCharge = Math.max(0, normalizeLoanPreviewNumber(getField('service_charge')?.value, 0));
        const documentaryStamp = Math.max(0, normalizeLoanPreviewNumber(getField('documentary_stamp')?.value, 0));

        const amountSpan = Math.max(0, maxAmount - minAmount);
        const amountStep = amountSpan >= 1000000 ? 5000 : amountSpan >= 100000 ? 1000 : amountSpan >= 10000 ? 500 : 100;
        const defaultAmount = amountSpan > 0 ? minAmount + (amountSpan / 2) : minAmount;
        const midpointTerm = Math.max(minTerm, Math.round((minTerm + maxTerm) / 2));
        const defaultTerm = loanPreviewClamp(
            Math.round(midpointTerm / billingCycleMonths) * billingCycleMonths,
            minTerm,
            maxTerm
        );

        if (loanPreviewAmountInput) {
            loanPreviewAmountInput.min = String(minAmount);
            loanPreviewAmountInput.max = String(maxAmount);
            loanPreviewAmountInput.step = String(amountStep);
            loanPreviewAmountInput.disabled = maxAmount <= minAmount;
        }

        if (loanPreviewTermInput) {
            loanPreviewTermInput.min = String(minTerm);
            loanPreviewTermInput.max = String(maxTerm);
            loanPreviewTermInput.step = String(billingCycleMonths);
            loanPreviewTermInput.disabled = maxTerm <= minTerm;
        }

        const selectedAmount = loanPreviewAmountInput
            ? loanPreviewClamp(normalizeLoanPreviewNumber(loanPreviewAmountInput.value, defaultAmount), minAmount, maxAmount)
            : defaultAmount;
        const selectedTermValue = loanPreviewTermInput
            ? normalizeLoanPreviewInteger(loanPreviewTermInput.value, defaultTerm)
            : defaultTerm;
        const selectedTerm = loanPreviewTermInput
            ? loanPreviewClamp(
                Math.round(selectedTermValue / billingCycleMonths) * billingCycleMonths,
                minTerm,
                maxTerm
            )
            : defaultTerm;
        const paymentCount = Math.max(1, Math.round(selectedTerm / billingCycleMonths));
        const paymentLabel = paymentCount === 1
            ? billingCycleMeta.paymentLabelSingular
            : billingCycleMeta.paymentLabelPlural;

        if (loanPreviewAmountInput) {
            loanPreviewAmountInput.value = String(selectedAmount);
        }

        if (loanPreviewTermInput) {
            loanPreviewTermInput.value = String(selectedTerm);
        }

        let estimatedInstallment = paymentCount > 0 ? selectedAmount / paymentCount : selectedAmount;
        let totalInterest = 0;
        if (interestType === 'Declining Balance') {
            const periodicRate = (interestRate / 100) * (billingCycleMonths / 12);
            estimatedInstallment = periodicRate > 0
                ? (selectedAmount * periodicRate) / (1 - Math.pow(1 + periodicRate, -paymentCount))
                : (selectedAmount / paymentCount);
        } else {
            totalInterest = selectedAmount * (interestRate / 100) * (selectedTerm / 12);
            estimatedInstallment = (selectedAmount + totalInterest) / paymentCount;
        }

        const processingFeeValue = selectedAmount * (processingFeeRate / 100);
        const insuranceFeeValue = selectedAmount * (insuranceFeeRate / 100);
        const totalUpfrontCharges = processingFeeValue + insuranceFeeValue + serviceCharge + documentaryStamp;
        const cashRelease = Math.max(0, selectedAmount - totalUpfrontCharges);
        const totalRepayment = estimatedInstallment * paymentCount;
        totalInterest = interestType === 'Declining Balance'
            ? Math.max(0, totalRepayment - selectedAmount)
            : totalInterest;
        const earlySettlementFeeAmount = !earlySettlementFeeEnabled || earlySettlementFeeValue <= 0
            ? 0
            : (earlySettlementFeeType === 'Fixed'
                ? earlySettlementFeeValue
                : selectedAmount * (earlySettlementFeeValue / 100));
        const earlySettlementFeePreview = !earlySettlementFeeEnabled || earlySettlementFeeValue <= 0
            ? 'Not applied'
            : formatLoanPreviewCurrency(earlySettlementFeeAmount);
        const earlySettlementFeeChip = !earlySettlementFeeEnabled || earlySettlementFeeValue <= 0
            ? 'No early settlement fee'
            : (earlySettlementFeeType === 'Fixed'
                ? `${formatLoanPreviewCurrency(earlySettlementFeeValue)} early settlement fee`
                : `${earlySettlementFeeValue.toFixed(2)}% of sample loan (${formatLoanPreviewCurrency(earlySettlementFeeAmount)})`);

        setPreviewText('product-name', productName);
        setPreviewText('product-type', productType);
        setPreviewText('description', description);
        setPreviewText('interest-chip', `${interestRate.toFixed(2)}% ${interestType} | ${billingCycle}`);
        setPreviewText('grace-chip', gracePeriodDays > 0 ? `${gracePeriodDays} day${gracePeriodDays === 1 ? '' : 's'} grace period` : 'No grace period');
        setPreviewText('max-amount', formatLoanPreviewCurrency(maxAmount));
        setPreviewText('term-range', `${minTerm}-${maxTerm} months | ${billingCycle}`);

        setPreviewText('selected-amount', formatLoanPreviewCurrency(selectedAmount));
        setPreviewText('penalty', earlySettlementFeeChip);
        setPreviewText('selected-term', `${selectedTerm} months (${paymentCount} ${paymentLabel})`);
        setPreviewText('min-amount', formatLoanPreviewCurrency(minAmount));
        setPreviewText('max-amount-range', formatLoanPreviewCurrency(maxAmount));
        setPreviewText('min-term', `${minTerm} mo`);
        setPreviewText('max-term', `${maxTerm} mo`);
        setPreviewText('installment-label', `Estimated ${billingCycleMeta.paymentDescriptor} payment`);

        setPreviewText('estimated-installment', formatLoanPreviewCurrency(estimatedInstallment));
        setPreviewText('cash-release', formatLoanPreviewCurrency(cashRelease));
        setPreviewText('total-repayment', formatLoanPreviewCurrency(totalRepayment));
        setPreviewText('charges-total', formatLoanPreviewCurrency(totalUpfrontCharges));
        setPreviewText('processing-fee-value', formatLoanPreviewCurrency(processingFeeValue));
        setPreviewText('insurance-fee-value', formatLoanPreviewCurrency(insuranceFeeValue));
        setPreviewText('service-charge-value', formatLoanPreviewCurrency(serviceCharge));
        setPreviewText('doc-stamp-value', formatLoanPreviewCurrency(documentaryStamp));
        setPreviewText('early-settlement-fee-value', earlySettlementFeePreview);
    }

    function syncLoanProductTypeField() {
        if (!loanProductTypeSelect || !loanCustomProductTypeWrap || !loanCustomProductTypeInput) {
            return;
        }

        const isCustomType = loanProductTypeSelect.value === 'Others';
        loanCustomProductTypeWrap.classList.toggle('hidden-input', !isCustomType);
        loanCustomProductTypeInput.required = isCustomType;

        if (!isCustomType) {
            loanCustomProductTypeInput.value = '';
        }
    }

    function extractPaletteFromLogo() {
        if (!logoInput?.files || !logoInput.files[0] || logoInput.files[0].type === 'image/svg+xml') {
            return;
        }

        const reader = new FileReader();
        reader.onload = (event) => {
            const img = new Image();
            img.onload = () => {
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                const size = 64;
                canvas.width = size;
                canvas.height = size;
                ctx.drawImage(img, 0, 0, size, size);
                const data = ctx.getImageData(0, 0, size, size).data;
                const pixels = [];

                for (let i = 0; i < data.length; i += 4) {
                    const [r, g, b, a] = [data[i], data[i + 1], data[i + 2], data[i + 3]];
                    const lum = (0.299 * r) + (0.587 * g) + (0.114 * b);
                    if (a < 128 || lum > 245 || lum < 10) {
                        continue;
                    }
                    pixels.push([r, g, b]);
                }

                if (pixels.length < 5) {
                    return;
                }

                let centroids = pixels.filter((_, index) => index % Math.max(1, Math.floor(pixels.length / 3)) === 0).slice(0, 3);
                for (let iteration = 0; iteration < 10; iteration++) {
                    const clusters = centroids.map(() => []);
                    pixels.forEach((pixel) => {
                        let nearest = 0;
                        let bestDistance = Infinity;
                        centroids.forEach((centroid, idx) => {
                            const distance = (pixel[0] - centroid[0]) ** 2 + (pixel[1] - centroid[1]) ** 2 + (pixel[2] - centroid[2]) ** 2;
                            if (distance < bestDistance) {
                                bestDistance = distance;
                                nearest = idx;
                            }
                        });
                        clusters[nearest].push(pixel);
                    });
                    centroids = clusters.map((cluster, idx) => {
                        if (cluster.length === 0) {
                            return centroids[idx];
                        }
                        const avg = [0, 0, 0];
                        cluster.forEach((pixel) => {
                            avg[0] += pixel[0];
                            avg[1] += pixel[1];
                            avg[2] += pixel[2];
                        });
                        return avg.map((value) => Math.round(value / cluster.length));
                    });
                }

                centroids.sort((a, b) => {
                    const satA = Math.max(...a) === 0 ? 0 : (Math.max(...a) - Math.min(...a)) / Math.max(...a);
                    const satB = Math.max(...b) === 0 ? 0 : (Math.max(...b) - Math.min(...b)) / Math.max(...b);
                    return satB - satA;
                });

                const brandHex = rgbToHex(...centroids[0]);
                setColorField('picker-primary', 'primary_color', brandHex);
                setColorField('picker-border-color', 'border_color', centroids[1] ? rgbToHex(...centroids[1]) : '#e2e8f0');
                setColorField('picker-bg-body', 'bg_body', rgbToHex(...centroids[0].map((value) => value + (255 - value) * 0.92)));
                setColorField('picker-bg-card', 'bg_card', '#ffffff');

                if (autoSyncEnabled) {
                    syncBrandingContrast();
                } else {
                    updateBrandingPreview();
                }
            };
            img.src = event.target?.result || '';
        };
        reader.readAsDataURL(logoInput.files[0]);
    }

    if (companyNameInput) {
        updateCompanyNamePreview(companyNameInput.value);
        companyNameInput.addEventListener('input', (event) => updateCompanyNamePreview(event.target.value));
    }

    if (primaryColorInput) {
        setGlobalPrimaryColor(document.getElementById('primary_color')?.value || primaryColorInput.value);
    }

    wireColorPair('picker-primary', 'primary_color', () => {
        if (autoSyncEnabled) {
            syncBrandingContrast();
        } else {
            updateBrandingPreview();
        }
    });
    wireColorPair('picker-bg-body', 'bg_body', () => autoSyncEnabled ? syncBrandingContrast() : updateBrandingPreview());
    wireColorPair('picker-bg-card', 'bg_card', () => autoSyncEnabled ? syncBrandingContrast() : updateBrandingPreview());
    wireColorPair('picker-text-main', 'text_main', updateBrandingPreview);
    wireColorPair('picker-text-muted', 'text_muted', updateBrandingPreview);
    wireColorPair('picker-border-color', 'border_color', updateBrandingPreview);

    previewButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            const target = btn.getAttribute('data-view');
            previewButtons.forEach((item) => item.classList.toggle('active', item === btn));
            previewScreens.forEach((screen) => {
                screen.classList.toggle('active', screen.getAttribute('data-preview') === target);
            });
        });
    });

    if (logoInput) {
        logoInput.addEventListener('change', () => {
            if (extractPaletteBtn) {
                extractPaletteBtn.style.display = logoInput.files && logoInput.files[0] && logoInput.files[0].type !== 'image/svg+xml'
                    ? 'inline-flex'
                    : 'none';
            }
            updateLogoPreview();
        });
    }

    if (extractPaletteBtn) {
        extractPaletteBtn.addEventListener('click', extractPaletteFromLogo);
    }

    if (syncBtn) {
        syncBtn.addEventListener('click', () => {
            autoSyncEnabled = !autoSyncEnabled;
            syncBtn.classList.toggle('active', autoSyncEnabled);
            syncBtn.innerHTML = autoSyncEnabled
                ? '<span class="material-symbols-rounded">contrast</span> Smart Contrast Sync: On'
                : '<span class="material-symbols-rounded">contrast</span> Smart Contrast Sync: Off';
            if (autoSyncEnabled) {
                syncBrandingContrast();
            }
        });
    }

    document.querySelectorAll('.shadow-opt').forEach((button) => {
        button.addEventListener('click', () => {
            document.querySelectorAll('.shadow-opt').forEach((item) => item.classList.remove('active'));
            button.classList.add('active');
            const cardShadowInput = document.getElementById('card_shadow');
            if (cardShadowInput) {
                cardShadowInput.value = button.dataset.shadow || 'sm';
            }
            updateBrandingPreview();
        });
    });

    const borderWidthInput = document.getElementById('card_border_width');
    if (borderWidthInput) {
        borderWidthInput.addEventListener('input', updateBrandingPreview);
    }

    const fontFamilyInput = document.getElementById('font_family');
    if (fontFamilyInput) {
        fontFamilyInput.addEventListener('change', () => {
            if (previewStage) {
                previewStage.style.fontFamily = `'${fontFamilyInput.value}', sans-serif`;
            }
        });
    }

    updateBrandingPreview();
    updateLogoPreview();

    if (loanProductsForm && loanPreviewRoot) {
        const earlySettlementPreviewCardTemplate = document.getElementById('loan-preview-early-settlement-card-template');
        const loanPreviewFeesGrid = loanPreviewRoot.querySelector('.loan-preview-fees-grid');
        const loanPreviewFields = Array.from(loanProductsForm.querySelectorAll(
            '[name="product_name"], [name="product_type"], [name="custom_product_type"], [name="description"], [name="min_amount"], [name="max_amount"], [name="interest_rate"], [name="interest_type"], [name="min_term_months"], [name="max_term_months"], [name="billing_cycle"], [name="processing_fee_percentage"], [name="service_charge"], [name="documentary_stamp"], [name="insurance_fee_percentage"], [name="early_settlement_fee_type"], [name="early_settlement_fee_value"], [name="grace_period_days"]'
        ));
        const earlySettlementPreviewControls = [
            loanProductsForm.querySelector('#esf_master_toggle'),
            loanProductsForm.querySelector('#early_settlement_toggle_switch')
        ].filter(Boolean);

        if (
            earlySettlementPreviewCardTemplate
            && loanPreviewFeesGrid
            && !loanPreviewFeesGrid.querySelector('[data-loan-preview-bind="early-settlement-fee-value"]')
        ) {
            loanPreviewFeesGrid.insertAdjacentHTML('beforeend', earlySettlementPreviewCardTemplate.innerHTML.trim());
        }

        loanPreviewFields.forEach((field) => {
            field.addEventListener('input', updateLoanProductsPreview);
            field.addEventListener('change', updateLoanProductsPreview);
        });

        earlySettlementPreviewControls.forEach((field) => {
            field.addEventListener('input', updateLoanProductsPreview);
            field.addEventListener('change', updateLoanProductsPreview);
        });

        if (loanProductTypeSelect) {
            loanProductTypeSelect.addEventListener('change', () => {
                syncLoanProductTypeField();
                updateLoanProductsPreview();
            });
        }

        [loanPreviewAmountInput, loanPreviewTermInput].forEach((field) => {
            if (!field) {
                return;
            }
            field.addEventListener('input', updateLoanProductsPreview);
            field.addEventListener('change', updateLoanProductsPreview);
        });

        syncLoanProductTypeField();
        updateLoanProductsPreview();
    }

    function syncToggleHiddenFields() {
        const map = [
            { checkbox: toggleBooking, hidden: document.getElementById('hidden-toggle-booking') },
            { checkbox: toggleRegistration, hidden: document.getElementById('hidden-toggle-registration') },
            { checkbox: toggleMaintenance, hidden: document.getElementById('hidden-toggle-maintenance') },
            { checkbox: toggleEmails, hidden: document.getElementById('hidden-toggle-emails') },
            { checkbox: toggleWebsite, hidden: document.getElementById('hidden-toggle-website') }
        ];

        map.forEach((item) => {
            if (!item.checkbox || !item.hidden) {
                return;
            }

            item.hidden.disabled = !item.checkbox.checked;
        });
    }

    [toggleBooking, toggleRegistration, toggleMaintenance, toggleEmails, toggleWebsite].forEach((toggle) => {
        if (toggle) {
            toggle.addEventListener('change', syncToggleHiddenFields);
        }
    });

    syncToggleHiddenFields();

    if (settingsForm && saveBtn) {
        settingsForm.addEventListener('submit', () => {
            syncToggleHiddenFields();
            saveBtn.innerText = 'Saving...';
            saveBtn.style.opacity = '0.8';
        });
    }

    // Role Presets and Create Role Permission Workspace
    const rolePresetSelect = document.getElementById('role-preset');
    const roleNameInput = document.getElementById('create_role_name');
    const createRolePermissionsContainer = document.getElementById('create-role-permissions-container');
    const createRolePermissions = createRolePermissionsContainer
        ? Array.from(createRolePermissionsContainer.querySelectorAll('input[type="checkbox"]'))
        : [];
    const createRoleSearchInput = document.getElementById('create-role-permissions-search');
    const createRoleSummary = document.getElementById('create-role-selection-summary');
    const createRoleSelectVisibleBtn = document.getElementById('create-role-select-visible');
    const createRoleClearVisibleBtn = document.getElementById('create-role-clear-visible');
    const createRoleModuleToggleBtns = document.querySelectorAll('.create-role-module-toggle');
    const createRoleEmptyState = document.getElementById('create-role-permissions-empty');

    const updateCreateRoleModuleCounts = () => {
        if (!createRolePermissionsContainer) {
            return;
        }

        const modules = createRolePermissionsContainer.querySelectorAll('.permission-module');
        modules.forEach((module) => {
            const countEl = module.querySelector('.permission-module-visible-count');
            if (!countEl) {
                return;
            }

            const selectedCount = Array.from(module.querySelectorAll('input[type="checkbox"]')).filter((cb) => cb.checked).length;
            countEl.textContent = String(selectedCount);
        });
    };

    const updateCreateRoleSummary = () => {
        const selectedCount = createRolePermissions.filter((cb) => cb.checked).length;
        const totalCount = createRolePermissions.length;

        if (createRoleSummary) {
            createRoleSummary.textContent = `${selectedCount} of ${totalCount} selected`;
        }

        updateCreateRoleModuleCounts();
    };

    const applyCreateRoleFilter = (rawQuery) => {
        if (!createRolePermissionsContainer) {
            return;
        }

        const query = normalizeSearch(rawQuery);
        const modules = createRolePermissionsContainer.querySelectorAll('.permission-module');
        let hasVisibleItems = false;

        modules.forEach((module) => {
            const items = module.querySelectorAll('.toggle-item[data-permission-search]');
            let moduleVisibleCount = 0;

            items.forEach((item) => {
                const searchText = normalizeSearch(item.getAttribute('data-permission-search'));
                const isVisible = query === '' || searchText.includes(query);
                item.classList.toggle('is-filter-hidden', !isVisible);
                if (isVisible) {
                    moduleVisibleCount++;
                }
            });

            module.classList.toggle('is-module-hidden', moduleVisibleCount === 0);
            if (moduleVisibleCount > 0) {
                hasVisibleItems = true;
            }
        });

        if (createRoleEmptyState) {
            createRoleEmptyState.hidden = hasVisibleItems;
        }
    };

    const setCreateRoleVisibleState = (shouldCheck) => {
        createRolePermissions.forEach((checkbox) => {
            const item = checkbox.closest('.toggle-item');
            const module = checkbox.closest('.permission-module');
            if ((item && item.classList.contains('is-filter-hidden')) || (module && module.classList.contains('is-module-hidden'))) {
                return;
            }
            checkbox.checked = shouldCheck;
        });

        updateCreateRoleSummary();
    };

    if (createRolePermissionsContainer && createRolePermissions.length > 0) {
        createRolePermissions.forEach((checkbox) => {
            checkbox.addEventListener('change', updateCreateRoleSummary);
        });

        if (createRoleSearchInput) {
            createRoleSearchInput.addEventListener('input', () => {
                applyCreateRoleFilter(createRoleSearchInput.value);
            });
        }

        if (createRoleSelectVisibleBtn) {
            createRoleSelectVisibleBtn.addEventListener('click', () => {
                setCreateRoleVisibleState(true);
            });
        }

        if (createRoleClearVisibleBtn) {
            createRoleClearVisibleBtn.addEventListener('click', () => {
                setCreateRoleVisibleState(false);
            });
        }

        createRoleModuleToggleBtns.forEach((button) => {
            button.addEventListener('click', () => {
                const mode = button.getAttribute('data-bulk');
                const moduleCard = button.closest('.permission-module');
                if (!moduleCard || moduleCard.classList.contains('is-module-hidden')) {
                    return;
                }

                const checkboxes = moduleCard.querySelectorAll('input[type="checkbox"]');
                checkboxes.forEach((checkbox) => {
                    const item = checkbox.closest('.toggle-item');
                    if (item && item.classList.contains('is-filter-hidden')) {
                        return;
                    }
                    checkbox.checked = mode === 'all';
                });

                updateCreateRoleSummary();
            });
        });

        applyCreateRoleFilter(createRoleSearchInput ? createRoleSearchInput.value : '');
        updateCreateRoleSummary();
    }

    if (rolePresetSelect && roleNameInput && createRolePermissions.length > 0) {
        const presets = {
            'manager': {
                name: 'Manager',
                perms: ['CREATE_LOAN', 'APPROVE_LOAN', 'VIEW_REPORTS', 'MANAGE_USERS', 'VIEW_KPI', 'EXPORT_DATA']
            },
            'loan_officer': {
                name: 'Loan Officer',
                perms: ['CREATE_LOAN', 'VIEW_LOANS', 'EDIT_LOAN', 'VIEW_CLIENT_DOCS']
            },
            'teller': {
                name: 'Teller',
                perms: ['PROCESS_PAYMENT', 'VIEW_TRANSACTIONS', 'VIEW_CLIENT_BASIC']
            }
        };

        rolePresetSelect.addEventListener('change', (e) => {
            const val = e.target.value;

            if (val === 'custom') {
                createRolePermissions.forEach((cb) => {
                    cb.checked = false;
                });
                updateCreateRoleSummary();
                return;
            }

            if (val === 'manager') {
                const currentName = roleNameInput.value.trim();
                const isCurrentNameAPreset = Object.values(presets).some((p) => p.name === currentName) || currentName === '';
                if (isCurrentNameAPreset) {
                    roleNameInput.value = 'Manager';
                }

                createRolePermissions.forEach((cb) => {
                    // Manager gets all visible permissions by default
                    cb.checked = true;
                });

                updateCreateRoleSummary();
                updateCreateRoleModuleCounts();
                return;
            }

            const preset = presets[val];
            if (preset) {
                const currentName = roleNameInput.value.trim();
                const isCurrentNameAPreset = Object.values(presets).some((p) => p.name === currentName) || currentName === '';
                if (isCurrentNameAPreset) {
                    roleNameInput.value = preset.name;
                }

                createRolePermissions.forEach((cb) => {
                    cb.checked = preset.perms.includes(cb.value);
                });

                updateCreateRoleSummary();
            }
        });

        // Initialize counts on load since all toggles are now checked by default for Manager
        updateCreateRoleSummary();
        updateCreateRoleModuleCounts();
    }

    function initPolicyConsoleCrossTabWarnings() {
        const limitPercentInput = document.getElementById('pcc_limit_initial_percent_of_income');
        const dtiPercentInput = document.querySelector('input[name="pcdr_max_dti_percentage"]');
        const dtiEnabledToggle = document.querySelector('input[name="pcdr_dti_enabled"]');

        const limitWarningBox = document.getElementById('limit_dti_mapping_warning');
        const limitWarningDTIValue = document.querySelector('.limit_warning_current_dti');

        const dtiWarningBox = document.getElementById('dti_limit_mapping_warning');
        const dtiWarningLimitValue = document.querySelector('.dti_warning_current_limit');

        function checkLimtDTIOverlap() {
            if (!limitPercentInput || !dtiPercentInput || !limitWarningBox || !dtiWarningBox) return;

            const limitVal = parseFloat(limitPercentInput.value) || 0;
            const dtiVal = parseFloat(dtiPercentInput.value) || 0;
            const isDtiEnabled = dtiEnabledToggle ? dtiEnabledToggle.value === '1' : true;

            if (isDtiEnabled && limitVal > dtiVal && dtiVal > 0) {
                if (limitWarningDTIValue) limitWarningDTIValue.textContent = dtiVal.toFixed(2);
                if (dtiWarningLimitValue) dtiWarningLimitValue.textContent = limitVal.toFixed(2);
                
                limitWarningBox.style.display = 'block';
                dtiWarningBox.style.display = 'block';
            } else {
                limitWarningBox.style.display = 'none';
                dtiWarningBox.style.display = 'none';
            }
        }

        if (limitPercentInput) {
            limitPercentInput.addEventListener('input', checkLimtDTIOverlap);
            limitPercentInput.addEventListener('change', checkLimtDTIOverlap);
        }
        if (dtiPercentInput) {
            dtiPercentInput.addEventListener('input', checkLimtDTIOverlap);
            dtiPercentInput.addEventListener('change', checkLimtDTIOverlap);
        }
        if (dtiEnabledToggle) {
            // Also need to observe toggle changes. Toggle changes dispatch 'change' event usually.
            dtiEnabledToggle.addEventListener('change', checkLimtDTIOverlap);
        }

        // Run validation on load
        checkLimtDTIOverlap();
    }
    
    function initPolicyConsoleToggleDisableStates() {
        function bindDisableToggle(toggleName, inputId) {
            const toggleInput = document.querySelector(`input[name="${toggleName}"]`);
            const inputField = document.getElementById(inputId);
            
            if (toggleInput && inputField) {
                const updateState = () => {
                    const isEnabled = toggleInput.value === '1';
                    inputField.disabled = !isEnabled;
                    
                    const wrapper = inputField.parentElement;
                    if (wrapper) {
                        wrapper.classList.toggle('policy-field-disabled', !isEnabled);
                        wrapper.style.opacity = isEnabled ? '1' : '0.4';
                    }
                };
                
                toggleInput.addEventListener('change', updateState);
                toggleInput.addEventListener('input', updateState);
                updateState();
            }
        }
        
        bindDisableToggle('pcc_limit_use_default_lending_cap', 'pcc_limit_default_lending_cap_input');
        bindDisableToggle('pcdr_guarantor_required_enabled', 'pcdr_guarantor_amount_input');
        bindDisableToggle('pcdr_collateral_required_enabled', 'pcdr_collateral_amount_input');
    }
    
    initPolicyConsoleCrossTabWarnings();
    initPolicyConsoleToggleDisableStates();

});

