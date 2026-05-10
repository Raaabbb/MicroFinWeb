
        const API = {
            applications: '../../../microfin_backend/api/api_applications.php',
            loans: '../../../microfin_backend/api/api_loans.php',
            payments: '../../../microfin_backend/api/api_payments.php',
            clients: '../../../microfin_backend/api/api_clients.php',
            dashboard: '../../../microfin_backend/api/api_dashboard.php',
            walk_in: '../../../microfin_backend/api/api_walk_in.php',
            theme: '../../../microfin_backend/api/api_theme_preference.php',
        };

        let activeLoanId = null;
        let pendingDisbursementApps = {};
        let _debounceTimer = null;

        // ── Utilities ──────────────────────────────────────────────
        function debounce(fn, ms) { return () => { clearTimeout(_debounceTimer); _debounceTimer = setTimeout(fn, ms); }; }
        function fmt(n) { return '₱' + parseFloat(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
        function fmtDate(d) { if (!d) return '—'; const dt = new Date(d); return isNaN(dt.getTime()) ? d : dt.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: '2-digit' }); }
        function todayIsoDate() { return new Date().toISOString().slice(0, 10); }

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, char => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            })[char]);
        }
        function isBlank(value) { return value === null || value === undefined || String(value).trim() === ''; }
        function parseJsonObject(value) {
            if (!value) return {};
            if (typeof value === 'object') return value;
            try {
                const parsed = JSON.parse(value);
                if (typeof parsed === 'string') return parseJsonObject(parsed);
                return parsed && typeof parsed === 'object' ? parsed : {};
            } catch (_) {
                return {};
            }
        }
        function buildDisbursementReference(appId, releaseDate = '') {
            const datePart = String(releaseDate || todayIsoDate()).replace(/[^0-9]/g, '');
            const safeDate = datePart.length === 8 ? datePart : todayIsoDate().replace(/-/g, '');
            const safeId = String(parseInt(appId || 0, 10) || 0).padStart(6, '0');
            return `DISB-${safeDate}-${safeId}`;
        }
        function resolveApprovedDisbursementAmount(app = {}) {
            const approved = parseFloat(app.approved_amount || 0);
            const requested = parseFloat(app.requested_amount || 0);
            return approved > 0 ? approved : requested;
        }
        function resolveReleaseMethod(app = {}) {
            const data = parseJsonObject(app.application_data);
            const preferred = String(data.disbursement_method || data.release_method || 'Cash').trim();
            return ['Cash', 'Check', 'Bank Transfer', 'GCash'].includes(preferred) ? preferred : 'Cash';
        }
        function resolveReleasePaymentFrequency(app = {}) {
            const data = parseJsonObject(app.application_data);
            const preferred = String(data.payment_frequency || data.repayment_frequency || data.frequency || 'Monthly').trim();
            return ['Daily', 'Weekly', 'Bi-Weekly', 'Monthly'].includes(preferred) ? preferred : 'Monthly';
        }
        function formatTextValue(value, emptyLabel = 'Not provided') {
            if (isBlank(value)) return `<span class="detail-value is-empty">${escapeHtml(emptyLabel)}</span>`;
            return escapeHtml(value);
        }
        function formatMoneyValue(value, emptyLabel = 'Not provided') {
            const amount = parseFloat(value);
            if (!Number.isFinite(amount) || amount <= 0) return `<span class="detail-value is-empty">${escapeHtml(emptyLabel)}</span>`;
            return fmt(amount);
        }
        function formatDateValue(value, emptyLabel = 'Not provided') {
            if (isBlank(value) || value === '1990-01-01') return `<span class="detail-value is-empty">${escapeHtml(emptyLabel)}</span>`;
            return escapeHtml(fmtDate(value));
        }
        function documentHref(doc = {}) {
            const directUrl = String(doc?.file_url || '').trim();
            if (directUrl !== '') {
                return directUrl;
            }

            const filePath = String(doc?.file_path || '').replace(/^\/+/, '').trim();
            return filePath ? `../../../${filePath}` : '';
        }
        function renderDetailItem(label, valueHtml, full = false) {
            return `<div class="detail-item${full ? ' detail-item-full' : ''}"><div class="detail-label">${escapeHtml(label)}</div><div class="detail-value">${valueHtml}</div></div>`;
        }
        function joinAddress(parts) {
            return parts.map(part => String(part ?? '').trim()).filter(Boolean).join(', ');
        }
        function sourceBadge(userType) {
            return userType === 'Client'
                ? '<span class="badge badge-blue">Mobile App</span>'
                : '<span class="badge badge-gray">Walk-in / Staff</span>';
        }
        function applicationMonitorState(status = '') {
            const map = {
                'Draft': 'Draft',
                'Submitted': 'Under Review',
                'Pending Review': 'Under Review',
                'Under Review': 'Under Review',
                'Document Verification': 'Under Review',
                'Credit Investigation': 'Under Review',
                'For Approval': 'Under Review',
                'Reviewed': 'Under Review',
                'Approved': 'Approved',
                'Rejected': 'Rejected',
                'Cancelled': 'Rejected',
                'Withdrawn': 'Rejected'
            };
            return map[status] || status || 'Under Review';
        }
        function applicationMonitorBadge(status = '') {
            const monitor = applicationMonitorState(status);
            return `<div class="status-stack">${badge(monitor)}</div>`;
        }
        function matchesApplicationFilter(rawStatus, filter = 'all') {
            if (!filter || filter === 'all') return true;
            return applicationMonitorState(rawStatus) === filter;
        }
        function getActiveAppFilter() {
            return document.querySelector('#appFilterTabs .filter-tab.active')?.dataset?.status || 'all';
        }

        function getActiveLoanFilter() {
            return document.querySelector('#loanFilterTabs .filter-tab.active')?.dataset?.status || 'all';
        }

        function getActiveClientFilter() {
            return document.querySelector('#clientFilterTabs .filter-tab.active')?.dataset?.clientFilter || 'all';
        }

        function matchesClientFilter(c, filter) {
            if (!filter || filter === 'all') return true;
            if (filter === 'app') return c.user_type === 'Client';
            if (filter === 'walkin') return c.user_type !== 'Client';
            // Status-based — derive display status the same way the badge does
            const dispStatus = (c.document_verification_status !== 'Verified' && c.document_verification_status !== 'Approved' && c.client_status === 'Active') ? 'Inactive' : c.client_status;
            return dispStatus === filter;
        }

        function getRequestErrorMessage(error, fallback = 'Something went wrong.') {
            if (error && typeof error.message === 'string' && error.message.trim() !== '') {
                return error.message.trim();
            }
            return fallback;
        }

        async function fetchJsonStrict(url, options = {}) {
            const response = await fetch(url, options);
            const raw = await response.text();
            const normalizedRaw = raw.replace(/^\uFEFF/, '');
            let payload = {};

            if (normalizedRaw.trim() !== '') {
                try {
                    payload = JSON.parse(normalizedRaw);
                } catch (_) {
                    throw new Error('The server returned an invalid response. Please refresh and try again.');
                }
            }

            if (!response.ok) {
                throw new Error(payload.message || `Request failed with status ${response.status}.`);
            }

            if (!payload || typeof payload !== 'object') {
                throw new Error('The server returned an empty response. Please refresh and try again.');
            }

            return payload;
        }

        function getCreditAccountFilter() {
            return 'all';
        }

        function getCreditAccountScoreFilter() {
            return document.querySelector('#creditAccountsScoreFilterTabs .filter-tab.active')?.dataset?.scoreFilter || 'all';
        }

        function badge(s) {
            const map = {
                'Active': 'badge-green',
                'Approved': 'badge-green',
                'Posted': 'badge-green',
                'Verified': 'badge-green',
                'Fully Paid': 'badge-blue',
                'Under Review': 'badge-blue',
                'For Approval': 'badge-purple',
                'Credit Investigation': 'badge-purple',
                'Document Verification': 'badge-purple',
                'Overdue': 'badge-red',
                'Rejected': 'badge-red',
                'Bounced': 'badge-red',
                'Blacklisted': 'badge-red',
                'Cancelled': 'badge-gray',
                'Withdrawn': 'badge-gray',
                'Inactive': 'badge-gray',
                'Suspended': 'badge-gray',
                'Draft': 'badge-amber',
                'Submitted': 'badge-amber',
                'Pending Review': 'badge-amber',
                'Pending': 'badge-amber',
                'Partially Paid': 'badge-amber',
            };
            const cls = map[s] || 'badge-gray';
            return `<span class="badge ${cls}">${s}</span>`;
        }

        function upgradeStatusBadge(upgrade) {
            const status = String(upgrade?.status || '');
            const label = String(upgrade?.status_label || 'Upgrade Status');
            let cls = 'badge-gray';
            if (status === 'eligible') cls = 'badge-green';
            else if (status === 'not_yet_eligible') cls = 'badge-amber';
            else if (status === 'at_max_limit') cls = 'badge-purple';
            return `<span class="badge ${cls}">${escapeHtml(label)}</span>`;
        }

        function formatUpgradeLimit(value, emptyLabel = '—') {
            const amount = parseFloat(value);
            if (!Number.isFinite(amount) || amount < 0) {
                return `<span class="detail-value is-empty">${escapeHtml(emptyLabel)}</span>`;
            }
            return fmt(amount);
        }

        function scoreCategoryBadge(snapshot) {
            const group = String(snapshot?.recommendation_group || '');
            const label = String(snapshot?.recommendation_label || 'No score profile');
            let cls = 'badge-gray';
            if (group === 'approve') cls = 'badge-green';
            else if (group === 'review') cls = 'badge-blue';
            else if (group === 'reject') cls = 'badge-red';
            return `<span class="badge ${cls}">${escapeHtml(label)}</span>`;
        }

        function renderCreditAccountBorrower(account) {
            const fullName = `${escapeHtml(account.first_name || '')} ${escapeHtml(account.last_name || '')}`.trim();
            const email = account.email_address && account.email_address.trim() ? escapeHtml(account.email_address) : 'No email';
            const phone = account.contact_number && account.contact_number.trim() ? escapeHtml(account.contact_number) : 'No phone';
            return `
        <div style="display:flex;flex-direction:column;gap:6px;">
            <div class="td-bold">${fullName || 'Unknown Borrower'}</div>
            <div class="td-muted" style="font-size:.78rem;">${email} · ${phone}</div>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                ${sourceBadge(account.user_type)}
                ${badge((account.document_verification_status !== 'Verified' && account.document_verification_status !== 'Approved' && account.client_status === 'Active') ? 'Inactive' : (account.client_status || 'Inactive'))}
            </div>
        </div>`;
        }

        function renderCreditAccountScore(account) {
            if (account.verification_status !== 'Approved' && account.client_status !== 'Verified') {
                return `
            <div style="display:flex;flex-direction:column;gap:6px;">
                <span class="detail-value" style="color:var(--muted); font-size: 0.9rem;">No Score</span>
                <div class="td-muted" style="font-size:.78rem;">Account not verified.</div>
            </div>`;
            }

            const snapshot = account?.limit_snapshot || {};
            const effectiveScore = parseFloat(snapshot?.effective_score);
            const hasEffectiveScore = Number.isFinite(effectiveScore);
            let subLabel = 'No recorded credit score yet.';
            if (snapshot?.used_default_score) {
                subLabel = `Using tenant default score${hasEffectiveScore ? ` of ${Math.round(effectiveScore).toLocaleString('en-PH')}` : ''}.`;
            } else if (hasEffectiveScore) {
                subLabel = `Latest recorded score: ${Math.round(effectiveScore).toLocaleString('en-PH')}.`;
            }

            return `
        <div style="display:flex;flex-direction:column;gap:6px;">
            ${scoreCategoryBadge(snapshot)}
            <div class="td-muted" style="font-size:.78rem;">${escapeHtml(subLabel)}</div>
        </div>`;
        }

        function renderCreditAccountRuleProgress(upgrade) {
            const completedLoans = parseInt(upgrade?.completed_loans || 0, 10);
            const minCompletedLoans = parseInt(upgrade?.min_completed_loans || 0, 10);
            const latePayments = parseInt(upgrade?.late_payments || 0, 10);
            const maxLatePayments = parseInt(upgrade?.max_allowed_late_payments || 0, 10);
            return `
        <div style="display:flex;flex-direction:column;gap:6px;">
            <div class="td-muted" style="font-size:.78rem;">Completed loans: <strong style="color:var(--text);">${completedLoans}</strong> / ${minCompletedLoans}</div>
            <div class="td-muted" style="font-size:.78rem;">Late payments: <strong style="color:var(--text);">${latePayments}</strong> / ${maxLatePayments}</div>
        </div>`;
        }

        function onCreditAccountSearchInput() {
            const input = document.getElementById('creditAccountSearch');
            debounce(() => loadCreditAccounts(getCreditAccountFilter(), getCreditAccountScoreFilter(), null, input?.value || ''), 350)();
        }

        function syncWalkInPermanentFields() {
            const sameAsPresent = document.getElementById('walkInSameAsPresent');
            const permanentFields = document.getElementById('walkInPermanentFields');
            if (!sameAsPresent || !permanentFields) return;

            const hideFields = sameAsPresent.checked;
            permanentFields.style.display = hideFields ? 'none' : 'grid';
            permanentFields.querySelectorAll('input, select, textarea').forEach(field => {
                field.disabled = hideFields;
            });
        }

        function resetWalkInForm() {
            const form = document.getElementById('walkInForm');
            if (!form) return;

            form.reset();
            const actionInput = document.getElementById('walkInAction');
            if (actionInput) actionInput.value = 'draft';
            syncWalkInPermanentFields();
        }

        function openModal(id) {
            const modal = document.getElementById(id);
            if (!modal) return;
            if (id === 'walkInModal') resetWalkInForm();
            modal.classList.add('open');
        }

        function closeModal(id) {
            const modal = document.getElementById(id);
            if (!modal) return;
            modal.classList.remove('open');
            if (id === 'walkInModal') resetWalkInForm();
        }

        let dashboardPopupResolver = null;
        let dashboardPopupState = {
            requiresInput: false,
            trimInput: true,
            inputValue: ''
        };

        function dashboardPopupVariantConfig(variant = 'info') {
            const map = {
                info: { icon: 'info', color: 'var(--brand)', buttonClass: 'btn btn-brand', title: 'Notice' },
                success: { icon: 'check_circle', color: '#16a34a', buttonClass: 'btn btn-success', title: 'Success' },
                warning: { icon: 'warning', color: '#b45309', buttonClass: 'btn btn-brand', title: 'Please Review' },
                danger: { icon: 'error', color: '#991b1b', buttonClass: 'btn btn-danger', title: 'Action Needed' }
            };
            return map[variant] || map.info;
        }

        function getDashboardPopupElements() {
            return {
                modal: document.getElementById('dashboardPopupModal'),
                icon: document.getElementById('dashboardPopupIcon'),
                title: document.getElementById('dashboardPopupTitle'),
                message: document.getElementById('dashboardPopupMessage'),
                inputWrap: document.getElementById('dashboardPopupInputWrap'),
                inputLabel: document.getElementById('dashboardPopupInputLabel'),
                input: document.getElementById('dashboardPopupInput'),
                inputError: document.getElementById('dashboardPopupInputError'),
                cancel: document.getElementById('dashboardPopupCancel'),
                confirm: document.getElementById('dashboardPopupConfirm')
            };
        }

        function dismissDashboardPopup() {
            resolveDashboardPopup(false);
        }

        function resolveDashboardPopup(confirmed) {
            const els = getDashboardPopupElements();
            if (!els.modal) return;

            const rawValue = els.input ? els.input.value : '';
            const resolvedValue = dashboardPopupState.trimInput === false ? rawValue : rawValue.trim();

            if (confirmed && dashboardPopupState.requiresInput && resolvedValue === '') {
                if (els.inputError) {
                    els.inputError.textContent = dashboardPopupState.requiredMessage || 'This field is required.';
                    els.inputError.style.display = 'block';
                }
                if (els.input) els.input.focus();
                return;
            }

            closeModal('dashboardPopupModal');

            const resolver = dashboardPopupResolver;
            dashboardPopupResolver = null;
            dashboardPopupState = { requiresInput: false, trimInput: true, inputValue: '' };

            if (els.input) {
                els.input.value = '';
            }
            if (els.inputError) {
                els.inputError.style.display = 'none';
                els.inputError.textContent = 'This field is required.';
            }

            if (typeof resolver === 'function') {
                resolver({ confirmed, value: resolvedValue });
            }
        }

        function showDashboardPopup({
            title = 'Notice',
            message = '',
            variant = 'info',
            confirmText = 'OK',
            cancelText = 'Cancel',
            showCancel = false,
            requireInput = false,
            inputLabel = 'Details',
            inputPlaceholder = '',
            inputValue = '',
            requiredMessage = 'This field is required.'
        } = {}) {
            const els = getDashboardPopupElements();
            const variantConfig = dashboardPopupVariantConfig(variant);

            if (dashboardPopupResolver) {
                resolveDashboardPopup(false);
            }

            dashboardPopupState = {
                requiresInput: requireInput === true,
                trimInput: true,
                inputValue,
                requiredMessage
            };

            if (els.icon) {
                els.icon.textContent = variantConfig.icon;
                els.icon.style.color = variantConfig.color;
            }
            if (els.title) {
                els.title.textContent = title || variantConfig.title;
            }
            if (els.message) {
                els.message.textContent = message;
            }
            if (els.confirm) {
                els.confirm.textContent = confirmText;
                els.confirm.className = variantConfig.buttonClass;
            }
            if (els.cancel) {
                els.cancel.textContent = cancelText;
                els.cancel.style.display = showCancel ? '' : 'none';
            }
            if (els.inputWrap) {
                els.inputWrap.style.display = requireInput ? '' : 'none';
            }
            if (els.inputLabel) {
                els.inputLabel.textContent = inputLabel;
            }
            if (els.input) {
                els.input.placeholder = inputPlaceholder;
                els.input.value = inputValue || '';
            }
            if (els.inputError) {
                els.inputError.style.display = 'none';
                els.inputError.textContent = requiredMessage;
            }

            openModal('dashboardPopupModal');

            if (requireInput && els.input) {
                requestAnimationFrame(() => els.input.focus());
            } else if (els.confirm) {
                requestAnimationFrame(() => els.confirm.focus());
            }

            return new Promise(resolve => {
                dashboardPopupResolver = resolve;
            });
        }

        async function showAlertPopup(message, options = {}) {
            return showDashboardPopup({
                title: options.title || 'Notice',
                message,
                variant: options.variant || 'info',
                confirmText: options.confirmText || 'OK',
                showCancel: false
            });
        }

        async function showConfirmPopup(message, options = {}) {
            const result = await showDashboardPopup({
                title: options.title || 'Please Confirm',
                message,
                variant: options.variant || 'warning',
                confirmText: options.confirmText || 'Continue',
                cancelText: options.cancelText || 'Cancel',
                showCancel: true
            });
            return result.confirmed === true;
        }

        async function showPromptPopup(message, options = {}) {
            return showDashboardPopup({
                title: options.title || 'Action Needed',
                message,
                variant: options.variant || 'danger',
                confirmText: options.confirmText || 'Submit',
                cancelText: options.cancelText || 'Cancel',
                showCancel: true,
                requireInput: true,
                inputLabel: options.inputLabel || 'Details',
                inputPlaceholder: options.inputPlaceholder || '',
                inputValue: options.inputValue || '',
                requiredMessage: options.requiredMessage || 'This field is required.'
            });
        }

        function setActiveTab(el) {
            el.closest('.page-header-actions').querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
            el.classList.add('active');
        }

        function navTo(target) {
            document.querySelectorAll('.nav-item[data-target]').forEach(n => {
                if (n.dataset.target === target) n.click();
            });
        }

        // ── Navigation ─────────────────────────────────────────────
        document.addEventListener('DOMContentLoaded', () => {
            const navItems = document.querySelectorAll('.nav-item[data-target]');
            const views = document.querySelectorAll('.view');
            const title = document.getElementById('pageTitle');

            navItems.forEach(item => {
                item.addEventListener('click', e => {
                    const tid = item.dataset.target;
                    const tv = document.getElementById(tid);
                    
                    // If target view isn't in DOM (migrated), let browser navigate normally to ?tab=...
                    if (!tv) return; 

                    e.preventDefault();
                    navItems.forEach(n => n.classList.remove('active'));
                    item.classList.add('active');
                    views.forEach(v => v.classList.remove('active'));
                    
                    tv.classList.add('active');
                    const titleText = item.dataset.title || item.textContent.trim();
                    const subtitleText = item.dataset.subtitle || tid.charAt(0).toUpperCase() + tid.slice(1);
                    title.innerHTML = `${escapeHtml(titleText)} <span>${escapeHtml(subtitleText)}</span>`;
                    history.pushState(null, '', `#${tid}`);
                    
                    // Lazy load on first visit
                    if (tid === 'credit-accounts') loadCreditAccounts(getCreditAccountFilter(), getCreditAccountScoreFilter());
                    if (tid === 'applications') loadApps('all');
                    if (tid === 'loans') loadLoans('all');
                    if (tid === 'payments') loadPayments();
                    if (tid === 'users') loadUsers();
                    if (tid === 'reports') loadReports('month');
                });
            });

            // Handle hash or current tab loading
            let hashTab = location.hash.replace('#', '');
            let urlTab = new URLSearchParams(window.location.search).get('tab') || 'home';
            
            // If there is a hash, and it's different from current url tab, simulate click.
            // Otherwise, do not simulate click to avoid infinite refresh loops on migrated tabs.
            if (hashTab && hashTab !== urlTab) {
                const n = document.querySelector(`.nav-item[data-target="${hashTab}"]`); 
                if (n) setTimeout(() => n.click(), 10); 
            }

            // Theme toggle
            const themeBtn = document.getElementById('themeToggle');
            const html = document.documentElement;
            themeBtn.addEventListener('click', () => {
                const nt = html.dataset.theme === 'dark' ? 'light' : 'dark';
                html.dataset.theme = nt;
                themeBtn.querySelector('.ms').textContent = nt === 'dark' ? 'light_mode' : 'dark_mode';
                fetch(API.theme, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ theme: nt }) }).catch(() => { });
            });

            // Date defaults
            const today = new Date().toISOString().slice(0, 10);
            document.getElementById('payDate') && (document.getElementById('payDate').value = today);
            document.getElementById('releaseDate') && (document.getElementById('releaseDate').value = today);
            const legacyPaymentsHint = document.getElementById('todayTotal');
            if (legacyPaymentsHint && legacyPaymentsHint.parentElement) {
                legacyPaymentsHint.parentElement.textContent = 'Review posted receipts, transaction references, and collection activity.';
            }

            // Doc upload sync
            document.querySelectorAll('.document-upload-input').forEach(inp => {
                inp.addEventListener('change', () => {
                    const cb = document.querySelector(`.doc-collected-checkbox[data-doc-id="${inp.dataset.docId}"]`);
                    if (cb && inp.files.length > 0) cb.checked = true;
                });
            });
            document.querySelectorAll('.doc-collected-checkbox').forEach(cb => {
                cb.addEventListener('change', () => {
                    if (!cb.checked) {
                        const inp = document.querySelector(`.document-upload-input[data-doc-id="${cb.dataset.docId}"]`);
                        if (inp) inp.value = '';
                    }
                });
            });
            const walkInSameAsPresent = document.getElementById('walkInSameAsPresent');
            if (walkInSameAsPresent) {
                walkInSameAsPresent.addEventListener('change', syncWalkInPermanentFields);
            }
            syncWalkInPermanentFields();

            loadDashboardStats();
        });

        // ── Dashboard Stats ─────────────────────────────────────────
        async function loadDashboardStats() {
            try {
                const r = await fetch(API.dashboard + '?action=stats');
                const d = await r.json();
                if (d.status !== 'success') return;
                const s = d.data;
                if (s.pending_applications !== undefined) {
                    setText('statPendingApps', s.pending_applications);
                    const pendingBadge = document.getElementById('navPendingAppsBadge');
                    if (pendingBadge) {
                        pendingBadge.textContent = s.pending_applications;
                        pendingBadge.style.display = s.pending_applications > 0 ? 'inline-flex' : 'none';
                    }
                }
                if (s.active_clients !== undefined) setText('statActiveClients', s.active_clients);
                if (s.active_loans !== undefined) setText('statActiveLoans', s.active_loans);
                if (s.overdue_loans !== undefined) setText('statOverdueLoans', s.overdue_loans);
                if (s.todays_collections !== undefined) {
                    setText('statTodayCollections', fmt(s.todays_collections));
                    setText('receiptTodayTotal', fmt(s.todays_collections));
                }
            } catch (_) { }
        }
        function setText(id, val) { const el = document.getElementById(id); if (el) el.textContent = val; }

        // ── App Filter (live API) ─────────────────────────────────────
        async function loadApps(status = 'all', btn = null) {
            const filterTabs = Array.from(document.querySelectorAll('#appFilterTabs .filter-tab'));
            const activeBtn = btn || filterTabs.find(tab => tab.dataset.status === status);
            if (activeBtn) {
                filterTabs.forEach(tab => tab.classList.remove('active'));
                activeBtn.classList.add('active');
            }
            const tbody = document.getElementById('appsTbody');
            tbody.innerHTML = '<tr class="loading-row"><td colspan="7"><span class="spinner"></span></td></tr>';
            try {
                const d = await fetchJsonStrict(API.applications + '?action=list');
                if (d.status !== 'success') {
                    throw new Error(d.message || 'Could not load applications.');
                }

                const rows = (d.data || []).filter(application => matchesApplicationFilter(application.application_status, status));
                if (!rows.length) {
                    tbody.innerHTML = '<tr class="empty-row"><td colspan="7">No applications found for this filter.</td></tr>';
                    return;
                }

                tbody.innerHTML = rows.map(a => `<tr>
        <td class="td-mono td-bold">${escapeHtml(a.application_number)}</td>
        <td class="td-bold">${escapeHtml(a.first_name)} ${escapeHtml(a.last_name)}</td>
        <td class="td-muted">${escapeHtml(a.product_name)}</td>
        <td class="td-bold" style="color:var(--brand);">${fmt(a.requested_amount)}</td>
        <td class="td-muted">${fmtDate(a.submitted_date || a.created_at)}</td>
        <td>${applicationMonitorBadge(a.application_status)}</td>
        <td><button class="icon-btn table-icon-btn" onclick="viewApplication(${a.application_id})" title="Open application" aria-label="Open application"><span class="material-symbols-rounded ms">visibility</span></button></td>
    </tr>`).join('');
            } catch (error) {
                tbody.innerHTML = `<tr class="empty-row"><td colspan="7">${escapeHtml(getRequestErrorMessage(error, 'Could not load applications.'))}</td></tr>`;
            }
        }

        function filterApps(status, btn) { loadApps(status, btn); }

        async function collectApplicationActionNotes(action) {
            const notesField = document.getElementById('appActionNotes');
            const currentNotes = ((notesField && notesField.value) || '').trim();

            if (action === 'reject') {
                const popup = await showPromptPopup('Please enter the rejection reason for this loan application.', {
                    title: 'Reject Loan Application',
                    variant: 'danger',
                    confirmText: 'Reject Loan',
                    inputLabel: 'Rejection Reason',
                    inputPlaceholder: 'State the reason for rejecting this application...',
                    inputValue: currentNotes,
                    requiredMessage: 'Rejection reason is required.'
                });

                if (!popup.confirmed) {
                    return null;
                }

                return popup.value;
            }

            return currentNotes;
        }

        // ── View Application ────────────────────────────────────────
        async function viewApplication(id) {
            openModal('appReviewModal');
            document.getElementById('appModalBody').innerHTML = '<div style="text-align:center;padding:32px;"><span class="spinner"></span></div>';
            document.getElementById('appModalFooter').innerHTML = '<button class="btn btn-outline" onclick="closeModal(\'appReviewModal\')">Close</button>';
            const r = await fetch(API.applications + `?action=view&id=${id}`);
            const d = await r.json();
            if (d.status !== 'success') { document.getElementById('appModalBody').innerHTML = `<p style="color:#ef4444;">${d.message}</p>`; return; }
            const a = d.data;
            document.getElementById('appModalTitle').textContent = 'App: ' + a.application_number;

            document.getElementById('appModalBody').innerHTML = `
        <div class="form-grid" style="margin-bottom:18px;">
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Client</p><p style="font-weight:600;">${a.first_name} ${a.last_name}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Status</p>${badge(a.application_status)}</div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Product</p><p>${a.product_name} (${a.product_type})</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Requested Amount</p><p style="font-weight:700;color:var(--brand);font-size:1.05rem;">${fmt(a.requested_amount)}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Term</p><p>${a.loan_term_months} months</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Interest Rate</p><p>${a.interest_rate}% / month</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Contact</p><p>${a.contact_number || '—'}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Submitted</p><p>${fmtDate(a.submitted_date || a.created_at)}</p></div>
            ${a.loan_purpose ? `<div style="grid-column:1/-1;"><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Loan Purpose</p><p>${a.loan_purpose}</p></div>` : ''}
        </div>
        ${a.review_notes ? `<div style="background:var(--body-bg);border:1px solid var(--border);border-radius:var(--radius-sm);padding:12px;margin-bottom:12px;"><p style="font-size:.72rem;color:var(--muted);margin-bottom:4px;">Review Notes</p><p style="font-size:.85rem;">${a.review_notes}</p></div>` : ''}
        ${a.rejection_reason ? `<div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:var(--radius-sm);padding:12px;margin-bottom:12px;"><p style="font-size:.72rem;color:#991b1b;margin-bottom:4px;">Rejection Reason</p><p style="font-size:.85rem;color:#7f1d1d;">${a.rejection_reason}</p></div>` : ''}
        ${a.application_status === 'Approved' ? `<div class="form-group" style="margin-bottom:14px;"><label>Approved Amount (PHP)</label><input type="number" id="approvedAmountInput" value="${a.approved_amount || a.requested_amount}" style="padding:8px 11px;border:1px solid var(--border);border-radius:var(--radius-sm);background:var(--body-bg);color:var(--text);width:100%;font-family:var(--font);font-size:.85rem;"></div>` : ''}
        <div class="form-group"><label>Action Notes (optional)</label><textarea id="appActionNotes" placeholder="Add optional review or approval notes..." style="padding:8px 11px;border:1px solid var(--border);border-radius:var(--radius-sm);background:var(--body-bg);color:var(--text);width:100%;font-family:var(--font);font-size:.85rem;min-height:70px;resize:vertical;outline:none;"></textarea></div>`;

            const footer = document.getElementById('appModalFooter');
            footer.innerHTML = '<button class="btn btn-outline" onclick="closeModal(\'appReviewModal\')">Close</button>';
            const s = a.application_status;
            if (s === 'Submitted' || s === 'Pending Review') {
                footer.innerHTML += `<button class="btn btn-brand" onclick="appAction(${a.application_id},'start_review')">Start Review</button>
                             <button class="btn btn-danger" onclick="appAction(${a.application_id},'reject')">Reject</button>`;
            } else if (s === 'Under Review') {
                footer.innerHTML += `<button class="btn btn-outline" onclick="appAction(${a.application_id},'verify_docs')"><span class="material-symbols-rounded ms">verified</span> Verify Docs</button>
                             <button class="btn btn-success" onclick="appAction(${a.application_id},'approve',true)"><span class="material-symbols-rounded ms">check_circle</span> Approve</button>
                             <button class="btn btn-danger" onclick="appAction(${a.application_id},'reject')">Reject</button>`;
            } else if (s === 'Document Verification') {
                footer.innerHTML += `<button class="btn btn-brand" onclick="appAction(${a.application_id},'credit_inv')">Credit Investigation</button>
                             <button class="btn btn-danger" onclick="appAction(${a.application_id},'reject')">Reject</button>`;
            } else if (s === 'Credit Investigation') {
                footer.innerHTML += `<button class="btn btn-brand" onclick="appAction(${a.application_id},'for_approval')">For Approval</button>
                             <button class="btn btn-danger" onclick="appAction(${a.application_id},'reject')">Reject</button>`;
            } else if (s === 'For Approval') {
                footer.innerHTML += `<button class="btn btn-success" onclick="appAction(${a.application_id},'approve',true)"><span class="material-symbols-rounded ms">check_circle</span> Approve</button>
                             <button class="btn btn-danger" onclick="appAction(${a.application_id},'reject')">Reject</button>`;
            } else if (s === 'Draft') {
                footer.innerHTML += `<button class="btn btn-brand" onclick="appAction(${a.application_id},'submit')">Submit Application</button>`;
            }
        }

        async function appAction(id, action, needsAmount = false) {
            const notes = await collectApplicationActionNotes(action);
            const approved = needsAmount ? parseFloat((document.getElementById('approvedAmountInput') || {}).value || 0) : null;
            if (notes === null) return;
            const payload = { application_id: id, action, notes };
            if (approved) payload.approved_amount = approved;
            const r = await fetch(API.applications, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            const d = await r.json();
            await showAlertPopup(d.message || 'Application updated.', {
                title: d.status === 'success' ? 'Success' : 'Unable to Update Application',
                variant: d.status === 'success' ? 'success' : 'danger'
            });
            if (d.status === 'success') {
                closeModal('appReviewModal');
                loadApps(getActiveAppFilter());
                loadDashboardStats();
            }
        }

        // ── Loans ────────────────────────────────────────────────────
        // Credit policy modal override
        async function viewApplication(id) {
            openModal('appReviewModal');
            document.getElementById('appModalBody').innerHTML = '<div style="text-align:center;padding:32px;"><span class="spinner"></span></div>';
            document.getElementById('appModalFooter').innerHTML = '<button class="btn btn-outline" onclick="closeModal(\'appReviewModal\')">Close</button>';
            let d;
            try {
                d = await fetchJsonStrict(API.applications + `?action=view&id=${id}`);
            } catch (error) {
                document.getElementById('appModalBody').innerHTML = `<p style="color:#ef4444;">${escapeHtml(getRequestErrorMessage(error, 'Could not load this application.'))}</p>`;
                return;
            }
            if (d.status !== 'success') {
                document.getElementById('appModalBody').innerHTML = `<p style="color:#ef4444;">${escapeHtml(d.message || 'Could not load this application.')}</p>`;
                return;
            }

            const a = d.data;
            const reviewableStatuses = ['Submitted', 'Pending Review', 'Under Review', 'Document Verification', 'Credit Investigation', 'For Approval'];
            const showApprovedAmountInput = reviewableStatuses.includes(a.application_status);
            const safe = value => String(value ?? '').replace(/[&<>"']/g, char => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            })[char]);
            const latestScore = a.latest_credit_score !== null && a.latest_credit_score !== undefined && a.latest_credit_score !== ''
                ? `${safe(a.latest_credit_score)}${a.latest_credit_rating ? ` <span style="color:var(--muted);font-size:.82rem;">(${safe(a.latest_credit_rating)})</span>` : ''}`
                : '<span class="detail-value is-empty">Not available</span>';
            const approvedAmountValue = a.approved_amount || a.requested_amount || '';
            const productRange = parseFloat(a.min_amount || 0) > 0 || parseFloat(a.max_amount || 0) > 0
                ? `${fmt(a.min_amount || 0)} to ${fmt(a.max_amount || 0)}`
                : '<span class="detail-value is-empty">Not configured</span>';
            const termRange = parseInt(a.min_term_months || 0, 10) > 0 || parseInt(a.max_term_months || 0, 10) > 0
                ? `${safe(a.min_term_months || 0)} to ${safe(a.max_term_months || 0)} months`
                : '<span class="detail-value is-empty">Not configured</span>';
            const clientDocs = Array.isArray(a.client_documents) ? a.client_documents : [];
            const applicationDocs = Array.isArray(a.application_documents) ? a.application_documents : [];
            const clientDocsRows = clientDocs.length ? clientDocs.map(doc => {
                const href = documentHref(doc);
                const fileHtml = href
                    ? `<a href="${safe(href)}" target="_blank" class="btn btn-sm btn-outline"><span class="material-symbols-rounded ms" style="font-size:16px;">visibility</span> View</a>`
                    : '<span class="td-muted">Not uploaded</span>';
                return `<tr>
            <td class="td-bold">${safe(doc.document_name || doc.file_name || 'Client document')}</td>
            <td>${fileHtml}</td>
            <td class="td-muted">${fmtDate(doc.upload_date)}</td>
            <td>${badge(doc.verification_status || 'Pending')}</td>
        </tr>`;
            }).join('') : '<tr class="empty-row"><td colspan="4">No client verification documents found.</td></tr>';
            const applicationDocsRows = applicationDocs.length ? applicationDocs.map(doc => {
                const href = documentHref(doc);
                const fileHtml = href
                    ? `<a href="${safe(href)}" target="_blank" class="btn btn-sm btn-outline"><span class="material-symbols-rounded ms" style="font-size:16px;">visibility</span> View</a>`
                    : '<span class="td-muted">File unavailable</span>';
                return `<tr>
            <td class="td-bold">${safe(doc.document_name || doc.file_name || 'Application attachment')}</td>
            <td>${fileHtml}</td>
            <td class="td-muted">${fmtDate(doc.upload_date)}</td>
        </tr>`;
            }).join('') : '<tr class="empty-row"><td colspan="3">No application attachments were submitted.</td></tr>';

            document.getElementById('appModalTitle').textContent = 'App: ' + a.application_number;
            document.getElementById('appModalBody').innerHTML = `
        <div class="form-grid" style="margin-bottom:18px;">
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Client</p><p style="font-weight:600;">${safe(a.first_name)} ${safe(a.last_name)}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Review Status</p>${applicationMonitorBadge(a.application_status)}</div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Product</p><p>${safe(a.product_name)} (${safe(a.product_type)})</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Requested Amount</p><p style="font-weight:700;color:var(--brand);font-size:1.05rem;">${fmt(a.requested_amount)}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Requested Term</p><p>${safe(a.loan_term_months)} months</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Interest Rate</p><p>${safe(a.interest_rate)}% / month</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Contact</p><p>${safe(a.contact_number || '-')}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Submitted</p><p>${fmtDate(a.submitted_date || a.created_at)}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Credit Limit</p><p>${parseFloat(a.credit_limit || 0) > 0 ? fmt(a.credit_limit) : '<span class="detail-value is-empty">Not set</span>'}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Credit Score</p><p>${latestScore}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Document Status</p><p>${safe(a.document_verification_status || 'Unverified')}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Product Amount Range</p><p>${productRange}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Allowed Term Range</p><p>${termRange}</p></div>
            ${a.loan_purpose ? `<div style="grid-column:1/-1;"><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Loan Purpose</p><p>${safe(a.loan_purpose)}</p></div>` : ''}
        </div>
        <div style="background:var(--body-bg);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px;margin-bottom:12px;">
            <p style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:10px;">Client Verification Documents</p>
            <div style="overflow-x:auto;">
                <table>
                    <thead><tr style="background:var(--panel);">
                        <th>Document</th><th>File</th><th>Uploaded</th><th>Status</th>
                    </tr></thead>
                    <tbody>${clientDocsRows}</tbody>
                </table>
            </div>
        </div>
        ${applicationDocs.length ? `
        <div style="background:var(--body-bg);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px;margin-bottom:12px;">
            <p style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:10px;">Application Attachments</p>
            <div style="overflow-x:auto;">
                <table>
                    <thead><tr style="background:var(--panel);">
                        <th>Document</th><th>File</th><th>Uploaded</th>
                    </tr></thead>
                    <tbody>${applicationDocsRows}</tbody>
                </table>
            </div>
        </div>` : ''}
        ${a.approval_notes ? `<div style="background:#ecfdf5;border:1px solid #86efac;border-radius:var(--radius-sm);padding:12px;margin-bottom:12px;"><p style="font-size:.72rem;color:#166534;margin-bottom:4px;">Approval Notes</p><p style="font-size:.85rem;color:#14532d;">${safe(a.approval_notes)}</p></div>` : ''}
        ${a.review_notes ? `<div style="background:var(--body-bg);border:1px solid var(--border);border-radius:var(--radius-sm);padding:12px;margin-bottom:12px;"><p style="font-size:.72rem;color:var(--muted);margin-bottom:4px;">Review Notes</p><p style="font-size:.85rem;">${safe(a.review_notes)}</p></div>` : ''}
        ${a.rejection_reason ? `<div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:var(--radius-sm);padding:12px;margin-bottom:12px;"><p style="font-size:.72rem;color:#991b1b;margin-bottom:4px;">Rejection Reason</p><p style="font-size:.85rem;color:#7f1d1d;">${safe(a.rejection_reason)}</p></div>` : ''}
        ${showApprovedAmountInput ? `<div class="form-group" style="margin-bottom:14px;"><label>Approved Amount (PHP)</label><input type="number" id="approvedAmountInput" value="${approvedAmountValue}" min="0" step="0.01" style="padding:8px 11px;border:1px solid var(--border);border-radius:var(--radius-sm);background:var(--body-bg);color:var(--text);width:100%;font-family:var(--font);font-size:.85rem;"></div>` : ''}
        <div class="form-group"><label>Action Notes (optional)</label><textarea id="appActionNotes" placeholder="Add optional approval notes..." style="padding:8px 11px;border:1px solid var(--border);border-radius:var(--radius-sm);background:var(--body-bg);color:var(--text);width:100%;font-family:var(--font);font-size:.85rem;min-height:70px;resize:vertical;outline:none;"></textarea></div>`;

            const footer = document.getElementById('appModalFooter');
            footer.innerHTML = '<button class="btn btn-outline" onclick="closeModal(\'appReviewModal\')">Close</button>';
            if (showApprovedAmountInput) {
                footer.innerHTML += `<button class="btn btn-success" onclick="appAction(${a.application_id},'approve',true)"><span class="material-symbols-rounded ms">check_circle</span> Approve Loan</button>
                             <button class="btn btn-danger" onclick="appAction(${a.application_id},'reject')">Reject Loan</button>`;
            }
        }

        async function appAction(id, action, needsAmount = false) {
            const notes = await collectApplicationActionNotes(action);
            const approved = needsAmount ? parseFloat((document.getElementById('approvedAmountInput') || {}).value || 0) : null;

            if (notes === null) {
                return;
            }
            if (needsAmount && !(approved > 0)) {
                await showAlertPopup('Please enter an approved amount.', {
                    title: 'Approved Amount Required',
                    variant: 'warning'
                });
                return;
            }

            const payload = { application_id: id, action, notes };
            if (needsAmount) {
                payload.approved_amount = approved;
            }

            try {
                const d = await fetchJsonStrict(API.applications, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                if (d.status === 'success') {
                    closeModal('appReviewModal');
                    await showAlertPopup(d.message || 'Application updated.', {
                        title: 'Success',
                        variant: 'success'
                    });
                    loadApps(getActiveAppFilter());
                    loadDashboardStats();
                    return;
                }
                await showAlertPopup(d.message || 'Could not update the application.', {
                    title: 'Unable to Update Application',
                    variant: 'danger'
                });
            } catch (error) {
                await showAlertPopup(getRequestErrorMessage(error, 'Could not update the application.'), {
                    title: 'Unable to Update Application',
                    variant: 'danger'
                });
            }
        }

        async function loadPendingDisbursements() {
            const tbody = document.getElementById('loanDisbursementTbody');
            if (!tbody) return;
            tbody.innerHTML = '<tr class="loading-row"><td colspan="6"><span class="spinner"></span></td></tr>';
            pendingDisbursementApps = {};

            try {
                const response = await fetch(API.loans + '?action=approved_applications');
                const result = await response.json();
                const rows = result.data || [];

                if (result.status !== 'success') {
                    tbody.innerHTML = `<tr class="empty-row"><td colspan="6">${escapeHtml(result.message || 'Could not load pending disbursements.')}</td></tr>`;
                    return;
                }

                if (!rows.length) {
                    tbody.innerHTML = '<tr class="empty-row"><td colspan="6">No approved applications are waiting for disbursement.</td></tr>';
                    return;
                }

                tbody.innerHTML = rows.map(app => {
                    pendingDisbursementApps[String(app.application_id)] = app;
                    const approvedAmount = resolveApprovedDisbursementAmount(app);
                    const approvedDate = app.approval_date || app.submitted_date;
                    const actionHtml = `<?php if (has_permission('APPROVE_LOANS')): ?><button class="btn btn-sm btn-brand" onclick="openLoanRelease(${Number(app.application_id)})"><span class="material-symbols-rounded ms" style="font-size:16px;">payments</span> Release</button><?php else: ?><span class="td-muted">View only</span><?php endif; ?>`;

                    return `<tr>
                <td class="td-mono td-bold">${escapeHtml(app.application_number)}</td>
                <td class="td-bold">${escapeHtml(app.first_name)} ${escapeHtml(app.last_name)}</td>
                <td class="td-muted">${escapeHtml(app.product_name)}</td>
                <td class="td-bold" style="color:var(--brand);">${fmt(approvedAmount)}</td>
                <td class="td-muted">${fmtDate(approvedDate)}</td>
                <td>${actionHtml}</td>
            </tr>`;
                }).join('');
            } catch (error) {
                console.error(error);
                tbody.innerHTML = '<tr class="empty-row"><td colspan="6">Could not load pending disbursements right now.</td></tr>';
            }
        }

        async function loadLoans(status = 'all', btn = null) {
            if (btn) {
                btn.closest('.filter-tabs').querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
                btn.classList.add('active');
            }
            loadPendingDisbursements();
            const tbody = document.getElementById('loansTbody');
            if (!tbody) return;
            tbody.innerHTML = '<tr class="loading-row"><td colspan="8"><span class="spinner"></span></td></tr>';
            const r = await fetch(API.loans + '?action=list&status=' + encodeURIComponent(status));
            const d = await r.json();
            if (!d.data || !d.data.length) { tbody.innerHTML = '<tr class="empty-row"><td colspan="8">No loans found.</td></tr>'; return; }
            tbody.innerHTML = d.data.map(l => `
        <tr>
            <td class="td-mono td-bold">${l.loan_number}</td>
            <td>${l.first_name} ${l.last_name}</td>
            <td class="td-muted">${l.product_name}</td>
            <td class="td-bold">${fmt(l.principal_amount)}</td>
            <td class="td-bold" style="color:${parseFloat(l.remaining_balance) > 0 ? 'var(--brand)' : '#22c55e'};">${fmt(l.remaining_balance)}</td>
            <td class="td-muted" style="color:${l.days_overdue > 0 ? '#ef4444' : ''};">${fmtDate(l.next_payment_due)}</td>
            <td>${badge(l.loan_status)}</td>
            <td><button class="btn btn-sm btn-outline" onclick="viewLoan(${l.loan_id})">View</button></td>
        </tr>`).join('');
        }

        async function viewLoan(id) {
            activeLoanId = id;
            openModal('loanDetailModal');
            document.getElementById('loanDetailBody').innerHTML = '<div style="text-align:center;padding:32px;"><span class="spinner"></span></div>';
            const [lr, sr] = await Promise.all([
                fetch(API.loans + `?action=view&loan_id=${id}`),
                fetch(API.loans + `?action=schedule&loan_id=${id}`)
            ]);
            const ld = await lr.json(); const sd = await sr.json();
            if (ld.status !== 'success') { document.getElementById('loanDetailBody').innerHTML = `<p style="color:#ef4444;">${ld.message}</p>`; return; }
            const l = ld.data;
            document.getElementById('loanDetailTitle').textContent = l.loan_number;
            const sched = (sd.data || []).map(s => `<tr>
        <td style="text-align:center;">#${s.payment_number}</td>
        <td>${fmtDate(s.due_date)}</td>
        <td>${fmt(s.beginning_balance)}</td>
        <td>${fmt(s.principal_amount)}</td>
        <td>${fmt(s.interest_amount)}</td>
        <td class="td-bold">${fmt(s.total_payment)}</td>
        <td>${badge(s.payment_status)}</td>
    </tr>`).join('');

            document.getElementById('loanDetailBody').innerHTML = `
        <div class="form-grid" style="margin-bottom:20px;">
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Client</p><p style="font-weight:600;">${l.first_name} ${l.last_name}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Status</p>${badge(l.loan_status)}</div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Principal</p><p style="font-weight:700;">${fmt(l.principal_amount)}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Remaining Balance</p><p style="font-weight:700;color:var(--brand);">${fmt(l.remaining_balance)}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Monthly Amortization</p><p>${fmt(l.monthly_amortization)}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Next Due</p><p style="color:${l.days_overdue > 0 ? '#ef4444' : ''};">${fmtDate(l.next_payment_due)}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Release Date</p><p>${fmtDate(l.release_date)}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Maturity Date</p><p>${fmtDate(l.maturity_date)}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Payment Frequency</p><p>${escapeHtml(l.payment_frequency || 'Monthly')}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Disbursement Method</p><p>${escapeHtml(l.disbursement_method || 'Cash')}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Total Paid</p><p style="color:#22c55e;font-weight:600;">${fmt(l.total_paid)}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Interest Rate</p><p>${l.interest_rate}% / month</p></div>
            <div style="grid-column:1 / -1;"><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Disbursement Reference</p><p>${escapeHtml(l.disbursement_reference || 'Auto-generated on release')}</p></div>
        </div>
        <p style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:10px;">Amortization Schedule</p>
        <div style="overflow-x:auto;"><table class="sched-table">
            <thead><tr style="background:var(--body-bg);">
                <th style="text-align:center;">#</th><th>Due Date</th><th>Beg. Balance</th>
                <th>Principal</th><th>Interest</th><th>Total</th><th>Status</th>
            </tr></thead>
            <tbody>${sched || '<tr class="empty-row"><td colspan="7">No schedule found.</td></tr>'}</tbody>
        </table></div>`;
        }

        function openLoanRelease(appId, amount) {
            closeModal('appReviewModal');
            const app = pendingDisbursementApps[String(appId)] || {};
            const releaseDate = todayIsoDate();
            const approvedAmount = resolveApprovedDisbursementAmount(app) || parseFloat(amount || 0) || 0;
            const releaseMethod = resolveReleaseMethod(app);
            const paymentFrequency = resolveReleasePaymentFrequency(app);
            const releaseReference = buildDisbursementReference(appId, releaseDate);

            document.getElementById('releaseAppId').value = appId;
            document.getElementById('releaseAppNumber').value = app.application_number || `Application #${appId}`;
            document.getElementById('releaseAmount').value = approvedAmount;
            document.getElementById('releaseAmountPreview').value = fmt(approvedAmount);
            document.getElementById('releaseDate').value = releaseDate;
            document.getElementById('releaseDatePreview').value = fmtDate(releaseDate);
            document.getElementById('releaseMethod').value = releaseMethod;
            document.getElementById('releaseMethodPreview').value = releaseMethod;
            document.getElementById('releaseFreq').value = paymentFrequency;
            document.getElementById('releaseFreqPreview').value = paymentFrequency;
            document.getElementById('releaseRef').value = releaseReference;
            document.getElementById('releaseRefPreview').value = releaseReference;
            document.getElementById('releaseNotes').value = '';
            openModal('loanReleaseModal');
        }

        async function submitLoanRelease() {
            const payload = {
                application_id: parseInt(document.getElementById('releaseAppId').value),
                approved_amount: parseFloat(document.getElementById('releaseAmount').value),
                disbursement_method: document.getElementById('releaseMethod').value,
                release_date: document.getElementById('releaseDate').value,
                payment_frequency: document.getElementById('releaseFreq').value,
                disbursement_reference: document.getElementById('releaseRef').value,
                notes: document.getElementById('releaseNotes').value,
            };
            if (!payload.application_id) {
                await showAlertPopup('Missing approved application details.', {
                    title: 'Loan Release Unavailable',
                    variant: 'danger'
                });
                return;
            }
            const r = await fetch(API.loans + '?action=release', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            const d = await r.json();
            if (d.status === 'success') {
                closeModal('loanReleaseModal');
                await showAlertPopup(d.message || 'Loan released successfully.', {
                    title: 'Success',
                    variant: 'success'
                });
                loadLoans(getActiveLoanFilter(), document.querySelector('#loanFilterTabs .filter-tab.active'));
                loadDashboardStats();
                return;
            }
            await showAlertPopup(d.message || 'Could not release this loan.', {
                title: 'Unable to Release Loan',
                variant: 'danger'
            });
        }

        // ── Clients ──────────────────────────────────────────────────
        // NOTE: api_clients.php?action=list must also use ORDER BY registration_date DESC
        // (not created_at — clients table has no created_at column).
        // Also JOIN users ON c.user_id = u.user_id and SELECT u.user_type so the Source badge works.
        async function loadCreditAccounts(filter = 'all', scoreFilter = 'all', btn = null, search = null) {
            const tbody = document.getElementById('creditAccountsTbody');
            if (!tbody) return;

            if (btn && btn.closest('.filter-tabs')) {
                btn.closest('.filter-tabs').querySelectorAll('.filter-tab').forEach(tab => tab.classList.remove('active'));
                btn.classList.add('active');
            }

            const query = typeof search === 'string'
                ? search
                : (document.getElementById('creditAccountSearch')?.value || '');

            tbody.innerHTML = '<tr class="loading-row"><td colspan="8"><span class="spinner"></span></td></tr>';

            const url = API.clients
                + '?action=credit_accounts'
                + '&filter=' + encodeURIComponent(filter || 'all')
                + '&score_filter=' + encodeURIComponent(scoreFilter || 'all')
                + (query ? '&search=' + encodeURIComponent(query) : '');

            try {
                const response = await fetch(url);
                const result = await response.json();

                if (result.status !== 'success') {
                    tbody.innerHTML = `<tr class="empty-row"><td colspan="8">${escapeHtml(result.message || 'The credit accounts list is unavailable right now.')}</td></tr>`;
                    return;
                }

                const rows = result.data || [];
                if (!rows.length) {
                    tbody.innerHTML = '<tr class="empty-row"><td colspan="8">No credit accounts matched these filters.</td></tr>';
                    return;
                }

                tbody.innerHTML = rows.map(account => {
                    const upgrade = account.credit_upgrade || {};
                    const note = escapeHtml(upgrade.status_note || upgrade.next_limit_note || 'No upgrade note available.');
                    const currentLimit = parseFloat(upgrade.current_limit || 0);
                    const isEligible = upgrade.status === 'eligible' && (parseFloat(upgrade.potential_upgraded_limit || 0) > currentLimit);
                    const checkboxHtml = isEligible 
                        ? `<input type="checkbox" class="eligible-checkbox" value="${account.client_id}" onclick="updateUpgradeButtonState()" />` 
                        : `<input type="checkbox" disabled />`;
                    return `<tr>
                <td style="text-align: center;">${checkboxHtml}</td>
                <td>${renderCreditAccountBorrower(account)}</td>
                <td>${renderCreditAccountScore(account)}</td>
                <td>${currentLimit > 0 ? formatUpgradeLimit(currentLimit) : '<span class="detail-value is-empty">No active limit</span>'}</td>
                <td>
                    <div style="display:flex;flex-direction:column;gap:6px;">
                        <div style="font-weight:600;">${formatUpgradeLimit(upgrade.potential_upgraded_limit, 'Not available yet')}</div>
                        <div class="td-muted" style="font-size:.78rem;">${escapeHtml(upgrade.next_limit_note || 'Shows the next recommended limit after review.')}</div>
                    </div>
                </td>
                <td>
                    <div style="display:flex;flex-direction:column;gap:6px;">
                        ${upgradeStatusBadge(upgrade)}
                        <div class="td-muted" style="font-size:.78rem;">${note}</div>
                    </div>
                </td>
                <td>${renderCreditAccountRuleProgress(upgrade)}</td>
                <td>
                    <div style="display:flex; flex-direction:column; gap:4px;">
                        <button class="btn btn-sm btn-outline" onclick="viewClient(${account.client_id}, 'credit-accounts')">View Profile</button>
                        ${isEligible ? `<button class="btn btn-sm btn-brand" onclick="upgradeSingleClient(${account.client_id})">Upgrade</button>` : ''}
                    </div>
                </td>
            </tr>`;
                }).join('');
                document.getElementById('selectAllEligible').checked = false;
                updateUpgradeButtonState();
            } catch (error) {
                console.error(error);
                tbody.innerHTML = '<tr class="empty-row"><td colspan="8">Could not load credit accounts right now.</td></tr>';
            }
        }

        function toggleSelectAllEligible(source) {
            const checkboxes = document.querySelectorAll('.eligible-checkbox');
            checkboxes.forEach(cb => cb.checked = source.checked);
            updateUpgradeButtonState();
        }

        function updateUpgradeButtonState() {
            const anyChecked = document.querySelectorAll('.eligible-checkbox:checked').length > 0;
            const btn = document.getElementById('upgradeSelectedBtn');
            if (btn) {
                btn.style.display = anyChecked ? 'inline-flex' : 'none';
            }
        }

        async function upgradeSingleClient(clientId) {
            await upgradeClientsList([clientId]);
        }

        async function upgradeSelectedClients() {
            const checkboxes = document.querySelectorAll('.eligible-checkbox:checked');
            const clientIds = Array.from(checkboxes).map(cb => cb.value);
            if (!clientIds.length) return;
            await upgradeClientsList(clientIds);
        }

        async function upgradeClientsList(clientIds) {
            const confirmed = await showConfirmPopup(`Are you sure you want to approve the upgrade for ${clientIds.length} borrower(s)?`, {
                title: 'Confirm Upgrades',
                variant: 'brand',
                confirmText: 'Approve & Upgrade'
            });
            if (!confirmed) return;

            try {
                const response = await fetch(API.clients + '?action=approve_upgrade', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ client_ids: clientIds })
                });
                const result = await response.json();
                
                await showAlertPopup(result.message || 'Operation complete.', {
                    title: result.status === 'success' ? 'Success' : (result.status === 'info' ? 'Update Info' : 'Upgrade Failed'),
                    variant: result.status === 'success' ? 'success' : (result.status === 'info' ? 'warning' : 'danger')
                });

                if (result.status === 'success' || result.status === 'info') {
                    loadCreditAccounts(getCreditAccountFilter(), getCreditAccountScoreFilter());
                }
            } catch (err) {
                await showAlertPopup('A network error occurred.', {
                    title: 'Upgrade Failed',
                    variant: 'danger'
                });
            }
        }

        async function loadClients(search = '', filter = null, btn = null) {
            const activeFilter = filter !== null ? filter : getActiveClientFilter();
            const filterTabs = Array.from(document.querySelectorAll('#clientFilterTabs .filter-tab'));
            const activeBtn = btn || filterTabs.find(t => t.dataset.clientFilter === activeFilter);
            if (activeBtn) {
                filterTabs.forEach(t => t.classList.remove('active'));
                activeBtn.classList.add('active');
            }
            const tbody = document.getElementById('clientsTbody');
            if (!tbody) return;
            tbody.innerHTML = '<tr class="loading-row"><td colspan="7"><span class="spinner"></span></td></tr>';
            const r = await fetch(API.clients + '?action=list' + (search ? '&search=' + encodeURIComponent(search) : ''));
            const d = await r.json();
            const rows = (d.data || []).filter(c => matchesClientFilter(c, activeFilter));
            if (!rows.length) { tbody.innerHTML = '<tr class="empty-row"><td colspan="7">No clients found for this filter.</td></tr>'; return; }
            tbody.innerHTML = rows.map(c => `<tr>
        <td class="td-bold">${escapeHtml(c.first_name)} ${escapeHtml(c.last_name)}</td>
        <td class="td-muted">${c.email_address && c.email_address.trim() ? escapeHtml(c.email_address) : '—'}</td>
        <td class="td-muted">${c.contact_number && c.contact_number.trim() ? escapeHtml(c.contact_number) : '—'}</td>
        <td class="td-muted">${fmtDate(c.registration_date)}</td>
        <td>${c.user_type === 'Client' ? '<span class="badge badge-blue">📱 App</span>' : '<span class="badge badge-gray">🏢 Walk-in</span>'}</td>
        <td>${badge((c.document_verification_status !== 'Verified' && c.document_verification_status !== 'Approved' && c.client_status === 'Active') ? 'Inactive' : c.client_status)}</td>
        <td><button class="btn btn-sm btn-outline" onclick="viewClient(${c.client_id})">View User</button></td>
    </tr>`).join('');
        }

        async function viewClient(id, source = null) {
            openModal('clientDetailModal');
            document.getElementById('clientDetailBody').innerHTML = '<div style="text-align:center;padding:32px;"><span class="spinner"></span></div>';
            try {
                const r = await fetch(API.clients + `?action=view&client_id=${id}`);
                const raw = await r.text();
                let d;
                try {
                    d = JSON.parse(raw);
                } catch (_) {
                    document.getElementById('clientDetailBody').innerHTML = `<p style="color:#ef4444;">Server returned an invalid response. This usually means a PHP error occurred.<br><small style="color:#999;">${raw.substring(0, 200)}</small></p>`;
                    return;
                }
                if (d.status !== 'success') {
                    document.getElementById('clientDetailBody').innerHTML = `<p style="color:#ef4444;">${escapeHtml(d.message)}</p>`;
                    return;
                }

            const c = d.data;
            const fullName = [c.first_name, c.middle_name, c.last_name, c.suffix].map(part => String(part ?? '').trim()).filter(Boolean).join(' ');
            const presentAddress = joinAddress([c.present_street, c.present_barangay, c.present_city, c.present_province, c.present_postal_code]);
            const permanentAddress = joinAddress([c.permanent_street, c.permanent_barangay, c.permanent_city, c.permanent_province, c.permanent_postal_code]);
            const coMakerAddress = joinAddress([c.comaker_house_no, c.comaker_street, c.comaker_barangay, c.comaker_city, c.comaker_province, c.comaker_postal_code]);
            const verificationStatus = c.verification_status || c.document_verification_status || 'Pending';
            const verificationBadge = badge(verificationStatus);
            const accountEmail = !isBlank(c.email_address) ? escapeHtml(c.email_address) : formatTextValue(c.user_email);
            const upgrade = c.credit_upgrade || null;

            document.getElementById('clientDetailTitle').textContent = fullName || `${c.first_name || ''} ${c.last_name || ''}`.trim();

            const footer = document.getElementById('clientDetailFooter');
            if (footer) {
                let footerHtml = `<button class="btn btn-outline" onclick="closeModal('clientDetailModal')">Close</button>`;
                if (verificationStatus === 'Approved') {
                    if (c.client_status === 'Inactive') {
                        footerHtml += `<button class="btn btn-brand" onclick="updateClientStatus(${c.client_id}, 'Active')"><span class="material-symbols-rounded ms">toggle_on</span> Activate Client</button>`;
                    }
                } else if (verificationStatus === 'Verified') {
                    footerHtml += `<button class="btn btn-brand" onclick="approveClientFully(${c.client_id})"><span class="material-symbols-rounded ms">how_to_reg</span> Approve Client</button>`;
                } else if (source !== 'credit-accounts') {
                    footerHtml += `<button class="btn btn-success" onclick="verifyClientFully(${c.client_id})"><span class="material-symbols-rounded ms">verified</span> Verify Documents</button>`;
                }
                footer.innerHTML = footerHtml;
            }

            const applicationsHtml = (c.applications || []).length ? c.applications.map(app => `<tr>
        <td class="td-mono td-bold">${escapeHtml(app.application_number)}</td>
        <td>${escapeHtml(app.product_name)}</td>
        <td>${fmt(app.requested_amount)}</td>
        <td class="td-muted">${fmtDate(app.submitted_date || app.created_at)}</td>
        <td>${applicationMonitorBadge(app.application_status)}</td>
    </tr>`).join('') : '<tr class="empty-row"><td colspan="5">No applications found.</td></tr>';

            const docsHtml = (c.documents || []).length ? c.documents.map(doc => `<tr>
        <td class="td-bold">
            ${escapeHtml(doc.document_name)} ${doc.is_required ? '<span class="badge badge-amber" style="font-size:.65rem;padding:2px 6px;">Req</span>' : ''}
            ${doc.verification_status === 'Rejected' && doc.verification_notes ? `<div style="font-size:0.75rem;color:#b91c1c;margin-top:4px;"><b>Reason:</b> ${escapeHtml(doc.verification_notes)}</div>` : ''}
        </td>
        <td>${documentHref(doc) ? `<a href="${escapeHtml(documentHref(doc))}" target="_blank" class="btn btn-sm btn-outline"><span class="material-symbols-rounded ms" style="font-size:16px;">visibility</span> View</a>` : '<span class="td-muted">Not uploaded</span>'}</td>
        <td class="td-muted">${fmtDate(doc.upload_date)}</td>
        <td>${badge(doc.verification_status || 'Pending')}</td>
        <td style="white-space:nowrap;">${doc.file_path ? (
            doc.verification_status === 'Verified'
                ? '<span class="td-muted">&mdash;</span>'
                : `<button class="btn btn-sm" style="background:#dcfce7;color:#166534;border:none;margin-right:4px;" onclick="approveDoc(${doc.client_document_id}, ${c.client_id})">Approve</button><button class="btn btn-sm" style="background:#fee2e2;color:#991b1b;border:none;" onclick="rejectDoc(${doc.client_document_id}, ${c.client_id})">Reject</button>`
        ) : '<span class="td-muted">&mdash;</span>'}</td>
    </tr>`).join('') : '<tr class="empty-row"><td colspan="5">No documents submitted.</td></tr>';

            const loansHtml = (c.loans || []).length ? c.loans.map(loan => `<tr>
        <td class="td-mono">${escapeHtml(loan.loan_number)}</td>
        <td>${escapeHtml(loan.product_name)}</td>
        <td>${fmt(loan.principal_amount)}</td>
        <td style="color:var(--brand);font-weight:600;">${fmt(loan.remaining_balance)}</td>
        <td>${fmtDate(loan.next_payment_due)}</td>
        <td>${badge(loan.loan_status)}</td>
    </tr>`).join('') : '<tr class="empty-row"><td colspan="6">No loans found.</td></tr>';

            document.getElementById('clientDetailBody').innerHTML = `
        <div class="detail-sections">
            <section class="detail-section">
                <div class="detail-section-header">
                    <span class="material-symbols-rounded ms">badge</span>
                    <div class="detail-section-title">Personal Information</div>
                </div>
                <div class="detail-grid">
                    ${renderDetailItem('Full Name', formatTextValue(fullName))}
                    ${renderDetailItem('Email Address', accountEmail)}
                    ${renderDetailItem('Phone Number', formatTextValue(c.contact_number))}
                    ${renderDetailItem('Date of Birth', formatDateValue(c.date_of_birth))}
                    ${renderDetailItem('Gender', formatTextValue(c.gender))}
                    ${renderDetailItem('Civil Status', formatTextValue(c.civil_status))}
                    ${renderDetailItem('Nationality', formatTextValue(c.nationality))}
                    ${renderDetailItem('Source', sourceBadge(c.user_type))}
                    ${renderDetailItem('Client Status', badge(c.client_status))}
                    ${renderDetailItem('Verification Status', verificationBadge)}
                    ${renderDetailItem('Registered', formatDateValue(c.registration_date))}
                    ${renderDetailItem('Last Login', formatDateValue(c.last_login, 'Never logged in'))}
                </div>
            </section>

            <section class="detail-section">
                <div class="detail-section-header">
                    <span class="material-symbols-rounded ms">work</span>
                    <div class="detail-section-title">Employment & Financial Profile</div>
                </div>
                <div class="detail-grid">
                    ${renderDetailItem('Employment Status', formatTextValue(c.employment_status))}
                    ${renderDetailItem('Occupation', formatTextValue(c.occupation))}
                    ${renderDetailItem('Employer Name', formatTextValue(c.employer_name))}
                    ${renderDetailItem('Monthly Income', formatMoneyValue(c.monthly_income))}
                    ${(() => {
                        const activeLimit = parseFloat(c.credit_limit || 0);
                        let meta = {};
                        try {
                            meta = typeof c.policy_metadata === 'string' ? JSON.parse(c.policy_metadata) : (c.policy_metadata || {});
                        } catch(e) {}
                        const potentialLimit = parseFloat(meta.potential_limit || meta.approved_limit || 0);
                        
                        if (activeLimit > 0) {
                            return renderDetailItem('Credit Limit', formatMoneyValue(activeLimit));
                        } else if (potentialLimit > 0) {
                            return renderDetailItem('Credit Limit', `<span style="color:#b45309;font-weight:700;">${formatMoneyValue(potentialLimit)}</span> <small style="display:block;font-weight:normal;color:var(--text-muted);">(Pending Approval)</small>`);
                        } else {
                            return renderDetailItem('Credit Limit', formatMoneyValue(activeLimit));
                        }
                    })()}
                    ${renderDetailItem('Last Seen Credit Limit', formatMoneyValue(c.last_seen_credit_limit))}
                </div>
            </section>

            ${upgrade ? `
            <section class="detail-section">
                <div class="detail-section-header">
                    <span class="material-symbols-rounded ms">trending_up</span>
                    <div class="detail-section-title">Credit Growth & Upgrade</div>
                </div>
                <div class="detail-grid">

                    ${renderDetailItem('Upgrade Status', upgradeStatusBadge(upgrade))}
                    ${renderDetailItem('Potential Upgraded Limit', formatUpgradeLimit(upgrade.potential_upgraded_limit))}
                    ${renderDetailItem('Completed Loans', escapeHtml(`${upgrade.completed_loans} / ${upgrade.min_completed_loans} required`))}
                    ${renderDetailItem('Late Payments', escapeHtml(`${upgrade.late_payments} / ${upgrade.max_allowed_late_payments} allowed`))}
                    ${renderDetailItem('Maximum Limit', formatMoneyValue(upgrade.absolute_max_limit, 'No maximum set'))}
                    ${renderDetailItem('Upgrade Note', escapeHtml(upgrade.status_note || upgrade.next_limit_note || 'No upgrade note available.'), true)}
                </div>
            </section>` : ''}

            <section class="detail-section">
                <div class="detail-section-header">
                    <span class="material-symbols-rounded ms">home_pin</span>
                    <div class="detail-section-title">Address Details</div>
                </div>
                <div class="detail-grid">
                    ${renderDetailItem('Present Address', formatTextValue(presentAddress), true)}
                    ${renderDetailItem('Permanent Address', formatTextValue(permanentAddress), true)}
                </div>
            </section>

            <section class="detail-section">
                <div class="detail-section-header">
                    <span class="material-symbols-rounded ms">groups</span>
                    <div class="detail-section-title">Co-Maker Information</div>
                </div>
                <div class="detail-grid">
                    ${renderDetailItem('Co-Maker Name', formatTextValue(c.comaker_name))}
                    ${renderDetailItem('Relationship', formatTextValue(c.comaker_relationship))}
                    ${renderDetailItem('Contact Number', formatTextValue(c.comaker_contact))}
                    ${renderDetailItem('Monthly Income', formatMoneyValue(c.comaker_income))}
                    ${renderDetailItem('Address', formatTextValue(coMakerAddress), true)}
                </div>
            </section>

            <section class="detail-section">
                <div class="detail-section-header">
                    <span class="material-symbols-rounded ms">description</span>
                    <div class="detail-section-title">Application History</div>
                </div>
                <div class="detail-table">
                    <table>
                        <thead><tr style="background:var(--body-bg);">
                            <th>App #</th><th>Product</th><th>Requested</th><th>Submitted</th><th>Status</th>
                        </tr></thead>
                        <tbody>${applicationsHtml}</tbody>
                    </table>
                </div>
            </section>

            <section class="detail-section">
                <div class="detail-section-header">
                    <span class="material-symbols-rounded ms">folder_open</span>
                    <div class="detail-section-title">Submitted Documents</div>
                </div>
                <div class="detail-table">
                    <table>
                        <thead><tr style="background:var(--body-bg);">
                            <th>Document Requirement</th><th>File</th><th>Uploaded</th><th>Status</th><th>Action</th>
                        </tr></thead>
                        <tbody>${docsHtml}</tbody>
                    </table>
                </div>
            </section>

            <section class="detail-section">
                <div class="detail-section-header">
                    <span class="material-symbols-rounded ms">account_balance_wallet</span>
                    <div class="detail-section-title">Loan History</div>
                </div>
                <div class="detail-table">
                    <table>
                        <thead><tr style="background:var(--body-bg);">
                            <th>Loan #</th><th>Product</th><th>Principal</th><th>Balance</th><th>Next Due</th><th>Status</th>
                        </tr></thead>
                        <tbody>${loansHtml}</tbody>
                    </table>
                </div>
            </section>
        </div>`;
            } catch (err) {
                console.error('viewClient error:', err);
                document.getElementById('clientDetailBody').innerHTML = `<p style="color:#ef4444;">Unable to load client profile. Please try again.</p>`;
            }
        }

        async function approveDoc(doc_id, client_id) {
            if (!hasPermission('CREATE_CLIENTS')) {
                await showAlertPopup('You do not have permission to approve documents.', { title: 'Permission Denied', variant: 'danger' });
                return;
            }
            const confirmed = await showConfirmPopup('Mark this document as Verified?', {
                title: 'Approve Document',
                variant: 'success',
                confirmText: 'Approve'
            });
            if (!confirmed) return;
            try {
                const payload = { document_id: doc_id, status: 'Verified' };
                const res = await fetch(API.clients + '?action=verify_document', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const raw = await res.text();
                let result;
                try { result = JSON.parse(raw); } catch (_) {
                    result = { status: 'error', message: `Approval failed (HTTP ${res.status}).` };
                }
                await showAlertPopup(result.message, {
                    title: result.status === 'success' ? 'Success' : 'Unable to Approve Document',
                    variant: result.status === 'success' ? 'success' : 'danger'
                });
                if (result.status === 'success') { viewClient(client_id); loadClients(); }
            } catch (err) {
                await showAlertPopup('An error occurred.', { title: 'Unable to Approve Document', variant: 'danger' });
            }
        }

        async function rejectDoc(doc_id, client_id) {
            if (!hasPermission('CREATE_CLIENTS')) {
                await showAlertPopup('You do not have permission to reject documents.', { title: 'Permission Denied', variant: 'danger' });
                return;
            }

            // Use a custom inline prompt instead of browser prompt()
            const reason = await new Promise(resolve => {
                const overlay = document.createElement('div');
                overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;display:flex;align-items:center;justify-content:center;';
                overlay.innerHTML = `
                    <div style="background:var(--card,#fff);border-radius:12px;padding:28px;width:440px;max-width:90vw;box-shadow:0 8px 32px rgba(0,0,0,.2);">
                        <h3 style="margin:0 0 6px;font-size:1rem;font-weight:700;">Reject Document</h3>
                        <p style="margin:0 0 16px;font-size:.875rem;color:var(--muted,#64748b);">Please enter the reason for rejecting this document. The client will see this message.</p>
                        <textarea id="_rejectReasonInput" rows="3" style="width:100%;padding:10px 12px;border:1.5px solid var(--border,#e2e8f0);border-radius:8px;font-size:.875rem;resize:vertical;background:var(--bg,#f8fafc);color:inherit;box-sizing:border-box;" placeholder="e.g. The document is blurry or unreadable."></textarea>
                        <div style="display:flex;gap:10px;margin-top:16px;justify-content:flex-end;">
                            <button id="_rejectCancelBtn" class="btn btn-outline" type="button">Cancel</button>
                            <button id="_rejectConfirmBtn" class="btn" style="background:#ef4444;color:#fff;" type="button">Reject Document</button>
                        </div>
                    </div>`;
                document.body.appendChild(overlay);
                const input = overlay.querySelector('#_rejectReasonInput');
                input.focus();
                overlay.querySelector('#_rejectCancelBtn').onclick = () => { overlay.remove(); resolve(null); };
                overlay.querySelector('#_rejectConfirmBtn').onclick = () => {
                    const val = input.value.trim();
                    if (!val) { input.style.borderColor = '#ef4444'; return; }
                    overlay.remove();
                    resolve(val);
                };
            });

            if (!reason) return;

            try {
                const payload = { document_id: doc_id, status: 'Rejected', rejection_reason: reason };
                const res = await fetch(API.clients + '?action=verify_document', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const raw = await res.text();
                let result;
                try {
                    result = JSON.parse(raw);
                } catch (_) {
                    result = {
                        status: 'error',
                        message: raw && !raw.trim().startsWith('<')
                            ? raw.trim()
                            : `Document rejection failed (HTTP ${res.status}).`
                    };
                }
                
                await showAlertPopup(result.message, {
                    title: result.status === 'success' ? 'Success' : 'Unable to Reject Document',
                    variant: result.status === 'success' ? 'success' : 'danger'
                });
                
                if (result.status === 'success') {
                    viewClient(client_id);
                    loadClients();
                }
            } catch (err) {
                await showAlertPopup('An error occurred.', {
                    title: 'Unable to Reject Document',
                    variant: 'danger'
                });
            }
        }

        async function verifyClientFully(client_id) {
            const confirmed = await showConfirmPopup('Are you sure you want to mark all documents for this client as Verified? This will prepare them for final approval.', {
                title: 'Verify Client Documents',
                variant: 'info',
                confirmText: 'Verify Documents'
            });
            if (!confirmed) return;
            try {
                const payload = { client_id: client_id };
                const res = await fetch(API.clients + '?action=verify_client_fully', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const raw = await res.text();
                let result;
                try {
                    result = JSON.parse(raw);
                } catch (_) {
                    result = {
                        status: 'error',
                        message: raw && !raw.trim().startsWith('<')
                            ? raw.trim()
                            : `Client verification failed (HTTP ${res.status}).`
                    };
                }
                await showAlertPopup(result.message, {
                    title: result.status === 'success' ? 'Success' : 'Unable to Verify Client',
                    variant: result.status === 'success' ? 'success' : 'danger'
                });
                if (result.status === 'success') {
                    viewClient(client_id);
                    loadClients();
                }
            } catch (err) {
                await showAlertPopup('An error occurred automatically verifying the client.', {
                    title: 'Unable to Verify Client',
                    variant: 'danger'
                });
            }
        }

        async function approveClientFully(client_id) {
            if (!hasPermission('CREATE_CLIENTS')) {
                await showAlertPopup('You do not have permission to approve clients.', { title: 'Permission Denied', variant: 'danger' });
                return;
            }
            const confirmed = await showConfirmPopup('Are you sure you want to approve this client? This will activate their account and generate their initial credit limit.', {
                title: 'Approve Client',
                variant: 'success',
                confirmText: 'Approve Client'
            });
            if (!confirmed) return;
            
            try {
                const payload = { client_id: client_id };
                const res = await fetch(API.clients + '?action=approve_client', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const raw = await res.text();
                let result;
                try {
                    result = JSON.parse(raw);
                } catch (_) {
                    result = {
                        status: 'error',
                        message: raw && !raw.trim().startsWith('<') ? raw.trim() : `Client approval failed (HTTP ${res.status}).`
                    };
                }
                
                await showAlertPopup(result.message, {
                    title: result.status === 'success' ? 'Success' : 'Unable to Approve Client',
                    variant: result.status === 'success' ? 'success' : 'danger'
                });
                
                if (result.status === 'success') {
                    openModal('clientDetailModal');
                    document.getElementById('clientDetailBody').innerHTML = '<div style="text-align:center;padding:32px;"><span class="spinner"></span> <p style="margin-top:12px;color:var(--muted);">Syncing credit profile...</p></div>';
                    setTimeout(() => { viewClient(client_id); loadClients(); }, 500); 
                }
            } catch (err) {
                await showAlertPopup('An error occurred automatically approving the client.', {
                    title: 'Unable to Approve Client',
                    variant: 'danger'
                });
            }
        }

        // ── Payments ──────────────────────────────────────────────────
        async function updateClientStatus(client_id, status) {
            const actionLabel = status === 'Active' ? 'activate' : `set to ${status.toLowerCase()}`;
            const confirmed = await showConfirmPopup(`Are you sure you want to ${actionLabel} this client?`, {
                title: 'Update Client Status',
                variant: status === 'Blacklisted' ? 'danger' : 'warning',
                confirmText: status === 'Active' ? 'Activate Client' : 'Confirm'
            });
            if (!confirmed) return;

            try {
                const payload = { client_id: client_id, status: status };
                const res = await fetch(API.clients + '?action=update_status', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const raw = await res.text();
                let result;
                try {
                    result = JSON.parse(raw);
                } catch (_) {
                    result = {
                        status: 'error',
                        message: raw && !raw.trim().startsWith('<')
                            ? raw.trim()
                            : `Unable to update client status (HTTP ${res.status}).`
                    };
                }
                await showAlertPopup(result.message, {
                    title: result.status === 'success' ? 'Success' : 'Unable to Update Client Status',
                    variant: result.status === 'success' ? 'success' : 'danger'
                });
                if (result.status === 'success') {
                    viewClient(client_id);
                    loadClients();
                }
            } catch (err) {
                await showAlertPopup('An error occurred while updating the client status.', {
                    title: 'Unable to Update Client Status',
                    variant: 'danger'
                });
            }
        }

        async function loadPayments() {
            const tbody = document.getElementById('paymentsTbody');
            if (!tbody) return;
            tbody.innerHTML = '<tr class="loading-row"><td colspan="8"><span class="spinner"></span></td></tr>';
            const r = await fetch(API.payments + '?action=list');
            const d = await r.json();
            const rows = d.data || [];
            if (d.todays_total !== undefined) setText('receiptTodayTotal', fmt(d.todays_total));
            const todayString = new Date().toISOString().slice(0, 10);
            const todaysCount = rows.filter(p => String(p.payment_date || '').slice(0, 10) === todayString && p.payment_status !== 'Cancelled').length;
            setText('receiptTodayCount', todaysCount);
            setText('receiptLatestPosted', rows.length ? fmtDate(rows[0].payment_date || rows[0].created_at) : 'â€”');
            if (!rows.length) { tbody.innerHTML = '<tr class="empty-row"><td colspan="8">No transaction records found.</td></tr>'; return; }
            tbody.innerHTML = rows.map(p => `<tr>
        <td class="td-mono td-bold">${escapeHtml(p.official_receipt_number || p.payment_reference || '-')}</td>
        <td class="td-mono td-muted">${escapeHtml(p.payment_reference_number || p.payment_reference || '-')}</td>
        <td>${escapeHtml(p.first_name)} ${escapeHtml(p.last_name)}</td>
        <td class="td-mono td-muted">${escapeHtml(p.loan_number)}</td>
        <td class="td-bold" style="color:#10b981;">${fmt(p.payment_amount)}</td>
        <td class="td-muted">${escapeHtml(p.payment_method)}</td>
        <td class="td-muted">${fmtDate(p.payment_date)}</td>
        <td>${badge(p.payment_status)}</td>
    </tr>`).join('');
        }

        async function loadPayments() {
            const tbody = document.getElementById('paymentsTbody');
            if (!tbody) return;
            tbody.innerHTML = '<tr class="loading-row"><td colspan="8"><span class="spinner"></span></td></tr>';
            const r = await fetch(API.payments + '?action=list');
            const d = await r.json();
            const rows = d.data || [];
            if (d.todays_total !== undefined) setText('receiptTodayTotal', fmt(d.todays_total));
            const todayString = new Date().toISOString().slice(0, 10);
            const todaysCount = rows.filter(p => String(p.payment_date || '').slice(0, 10) === todayString && p.payment_status !== 'Cancelled').length;
            setText('receiptTodayCount', todaysCount);
            setText('receiptLatestPosted', rows.length ? fmtDate(rows[0].payment_date || rows[0].created_at) : '-');
            if (!rows.length) {
                tbody.innerHTML = '<tr class="empty-row"><td colspan="8">No transaction records found.</td></tr>';
                return;
            }
            tbody.innerHTML = rows.map(p => `<tr>
        <td class="td-mono td-bold">${escapeHtml(p.official_receipt_number || p.payment_reference || '-')}</td>
        <td class="td-mono td-muted">${escapeHtml(p.payment_reference_number || p.payment_reference || '-')}</td>
        <td>${escapeHtml(p.first_name)} ${escapeHtml(p.last_name)}</td>
        <td class="td-mono td-muted">${escapeHtml(p.loan_number)}</td>
        <td class="td-bold" style="color:#10b981;">${fmt(p.payment_amount)}</td>
        <td class="td-muted">${escapeHtml(p.payment_method)}</td>
        <td class="td-muted">${fmtDate(p.payment_date)}</td>
        <td>${badge(p.payment_status)}</td>
    </tr>`).join('');
        }

        async function loadPaymentLoans() {
            const sel = document.getElementById('payLoanId');
            if (!sel) return;
            const r = await fetch(API.payments + '?action=active_loans');
            const d = await r.json();
            if (!d.data) return;
            sel.innerHTML = '<option value="">— Select a loan —</option>' +
                d.data.map(l => `<option value="${l.loan_id}" data-balance="${l.remaining_balance}" data-amort="${l.monthly_amortization}" data-due="${l.next_payment_due}">
            ${l.first_name} ${l.last_name} — ${l.loan_number} (Bal: ${fmt(l.remaining_balance)})
        </option>`).join('');
        }

        function onPayLoanChange() {
            const sel = document.getElementById('payLoanId');
            const opt = sel.selectedOptions[0];
            const info = document.getElementById('payLoanInfo');
            if (!opt || !opt.value) { info.textContent = ''; return; }
            info.textContent = `Balance: ${fmt(opt.dataset.balance)} · Monthly: ${fmt(opt.dataset.amort)} · Next Due: ${fmtDate(opt.dataset.due)}`;
            document.getElementById('payAmount').value = opt.dataset.amort;
        }

        function openPaymentFromLoan() {
            const sel = document.getElementById('payLoanId');
            if (sel && activeLoanId) { sel.value = activeLoanId; onPayLoanChange(); }
            closeModal('loanDetailModal');
            openModal('paymentModal');
        }

        async function submitPayment() {
            const payload = {
                loan_id: parseInt(document.getElementById('payLoanId').value),
                payment_amount: parseFloat(document.getElementById('payAmount').value),
                payment_method: document.getElementById('payMethod').value,
                payment_date: document.getElementById('payDate').value,
                or_number: document.getElementById('payOR').value,
                payment_ref_number: document.getElementById('payRef').value,
                remarks: document.getElementById('payRemarks').value,
            };
            if (!payload.loan_id || !payload.payment_amount) {
                await showAlertPopup('Please select a loan and enter an amount.', {
                    title: 'Payment Details Required',
                    variant: 'warning'
                });
                return;
            }
            const r = await fetch(API.payments + '?action=post', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            const d = await r.json();
            if (d.status === 'success') {
                closeModal('paymentModal');
                await showAlertPopup(d.message || 'Payment posted successfully.', {
                    title: 'Success',
                    variant: 'success'
                });
                loadPayments();
                loadDashboardStats();
                loadPaymentLoans();
                return;
            }
            await showAlertPopup(d.message || 'Could not post this payment.', {
                title: 'Unable to Post Payment',
                variant: 'danger'
            });
        }

        // ── Reports ───────────────────────────────────────────────────
        async function loadReports(period = 'month') {
            const body = document.getElementById('reportsBody');
            if (!body) return;
            body.innerHTML = '<div style="text-align:center;padding:40px;"><span class="spinner"></span></div>';
            try {
                const r = await fetch(API.dashboard + `?action=reports&period=${period}`);
                const d = await r.json();
                if (d.status !== 'success') { body.innerHTML = '<p style="color:var(--muted);padding:24px;">Could not load report data.</p>'; return; }
                const rpt = d.data;
                const sm = rpt.summary || {};
                const daily = rpt.daily_summary || [];
                const methods = rpt.method_breakdown || [];
                const sources = rpt.source_breakdown || [];
                const staff = rpt.staff_summary || [];
                const clients = rpt.client_summary || [];
                const recent = rpt.recent_transactions || [];

                // — Bar chart helper (pure CSS) —
                function miniBar(items, labelKey, valueKey, colorFn) {
                    if (!items.length) return '<p style="padding:20px;color:var(--muted);font-size:.85rem;">No data for this period.</p>';
                    const max = Math.max(...items.map(i => parseFloat(i[valueKey]) || 0), 1);
                    return items.map(i => {
                        const val = parseFloat(i[valueKey]) || 0;
                        const pct = Math.round((val / max) * 100);
                        const color = typeof colorFn === 'function' ? colorFn(i) : 'var(--brand)';
                        return `<div style="padding:10px 20px;border-top:1px solid var(--border);">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                                <span style="font-size:.83rem;font-weight:500;">${escapeHtml(i[labelKey] || 'Unknown')}</span>
                                <strong style="font-size:.83rem;">${fmt(val)}</strong>
                            </div>
                            <div style="height:6px;border-radius:99px;background:var(--border);overflow:hidden;">
                                <div style="height:100%;width:${pct}%;background:${color};border-radius:99px;transition:width .4s ease;"></div>
                            </div>
                        </div>`;
                    }).join('');
                }

                // — Daily trend sparkline (pure CSS) —
                function dailyTrend(days) {
                    if (!days.length) return '<p style="padding:20px;color:var(--muted);font-size:.85rem;">No daily data available.</p>';
                    const max = Math.max(...days.map(d => parseFloat(d.total_amount) || 0), 1);
                    return `<div style="display:flex;align-items:flex-end;gap:3px;height:120px;padding:14px 20px 10px;">
                        ${days.map(d => {
                            const val = parseFloat(d.total_amount) || 0;
                            const pct = Math.max(Math.round((val / max) * 100), 3);
                            const label = d.transaction_day ? new Date(d.transaction_day).toLocaleDateString('en-PH', {month:'short', day:'numeric'}) : '?';
                            return `<div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;min-width:0;" title="${label}: ${fmt(val)}">
                                <div style="font-size:.8rem;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%;">${fmt(val)}</div>
                                <div style="width:100%;max-width:36px;height:${pct}%;background:var(--brand);border-radius:4px 4px 0 0;min-height:3px;transition:height .4s ease;"></div>
                                <div style="font-size:.75rem;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%;">${escapeHtml(label)}</div>
                            </div>`;
                        }).join('')}
                    </div>`;
                }

                body.innerHTML = `
            <!-- Range label -->
            <div style="margin-bottom:16px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                <span class="badge badge-blue" style="font-size:.78rem;padding:4px 10px;">${escapeHtml(rpt.range_label || '')}</span>
                <span style="font-size:.78rem;color:var(--muted);">${escapeHtml(rpt.summary_note || '')}</span>
            </div>

            <!-- KPI Cards -->
            <div class="reports-kpi">
                <div class="kpi-card">
                    <div class="kpi-label">Total Collections</div>
                    <div class="kpi-val" style="color:var(--brand);">${fmt(sm.total_amount)}</div>
                    <div style="font-size:.72rem;color:var(--muted);margin-top:4px;">${sm.total_transactions} transaction${sm.total_transactions !== 1 ? 's' : ''}</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Staff Collections</div>
                    <div class="kpi-val">${fmt(sm.staff_amount)}</div>
                    <div style="font-size:.72rem;color:var(--muted);margin-top:4px;">${sm.staff_transactions} posted by staff</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Client App Payments</div>
                    <div class="kpi-val">${fmt(sm.client_amount)}</div>
                    <div style="font-size:.72rem;color:var(--muted);margin-top:4px;">${sm.client_transactions} via mobile</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Unique Clients</div>
                    <div class="kpi-val">${sm.unique_clients}</div>
                    <div style="font-size:.72rem;color:var(--muted);margin-top:4px;">${sm.active_staff} active staff</div>
                </div>
            </div>

            <!-- Daily Collections Trend -->
            <div class="card" style="margin-bottom:16px;">
                <div class="card-header">
                    <span class="material-symbols-rounded ms">show_chart</span>
                    <h3>Daily Collections Trend</h3>
                </div>
                ${dailyTrend(daily)}
            </div>

            <!-- Source & Method Breakdown -->
            <div class="two-col" style="margin-bottom:16px;">
                <div class="card">
                    <div class="card-header">
                        <span class="material-symbols-rounded ms">compare_arrows</span>
                        <h3>By Collection Source</h3>
                    </div>
                    <div style="padding:4px 0;">
                        ${sources.map(s => `
                            <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 20px;border-top:1px solid var(--border);">
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <span class="material-symbols-rounded ms" style="font-size:18px;color:${s.source_key === 'staff' ? '#6366f1' : '#10b981'};">${s.source_key === 'staff' ? 'badge' : 'phone_android'}</span>
                                    <div>
                                        <div style="font-size:.85rem;font-weight:600;">${escapeHtml(s.source_label)}</div>
                                        <div style="font-size:.72rem;color:var(--muted);">${s.transaction_count} transaction${s.transaction_count !== 1 ? 's' : ''}</div>
                                    </div>
                                </div>
                                <strong style="color:var(--brand);">${fmt(s.total_amount)}</strong>
                            </div>`).join('') || '<p style="padding:20px;color:var(--muted);font-size:.85rem;">No data.</p>'}
                    </div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <span class="material-symbols-rounded ms">account_balance_wallet</span>
                        <h3>By Payment Method</h3>
                    </div>
                    <div style="padding:4px 0;">
                        ${miniBar(methods, 'payment_method', 'total_amount', m => {
                            const method = String(m.payment_method || '').toLowerCase();
                            if (method.includes('cash')) return '#22c55e';
                            if (method.includes('gcash') || method.includes('mobile')) return '#3b82f6';
                            if (method.includes('bank') || method.includes('transfer')) return '#6366f1';
                            if (method.includes('check')) return '#f59e0b';
                            return 'var(--brand)';
                        })}
                    </div>
                </div>
            </div>

            <!-- Staff & Client Breakdown -->
            <div class="two-col" style="margin-bottom:16px;">
                <div class="card">
                    <div class="card-header">
                        <span class="material-symbols-rounded ms">groups</span>
                        <h3>Staff Performance</h3>
                    </div>
                    <div style="padding:4px 0;">
                        ${staff.length ? staff.map(s => `
                            <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 20px;border-top:1px solid var(--border);">
                                <div>
                                    <div style="font-size:.85rem;font-weight:600;">${escapeHtml(s.staff_name)}</div>
                                    <div style="font-size:.72rem;color:var(--muted);">${escapeHtml(s.staff_role)} · ${s.transaction_count} txn · ${s.unique_clients} client${s.unique_clients != 1 ? 's' : ''}</div>
                                </div>
                                <strong style="color:var(--brand);">${fmt(s.total_amount)}</strong>
                            </div>`).join('') : '<p style="padding:20px;color:var(--muted);font-size:.85rem;">No staff collections this period.</p>'}
                    </div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <span class="material-symbols-rounded ms">person</span>
                        <h3>Top Paying Clients</h3>
                    </div>
                    <div style="padding:4px 0;">
                        ${clients.length ? clients.map((c, i) => `
                            <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 20px;border-top:1px solid var(--border);">
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <span style="width:22px;height:22px;border-radius:50%;background:${i < 3 ? 'var(--brand)' : 'var(--border)'};color:${i < 3 ? '#fff' : 'var(--muted)'};display:flex;align-items:center;justify-content:center;font-size:.65rem;font-weight:700;flex-shrink:0;">${i + 1}</span>
                                    <div>
                                        <div style="font-size:.83rem;font-weight:500;">${escapeHtml(c.client_name)}</div>
                                        <div style="font-size:.72rem;color:var(--muted);">${c.transaction_count} payment${c.transaction_count != 1 ? 's' : ''}</div>
                                    </div>
                                </div>
                                <strong style="font-size:.85rem;">${fmt(c.total_amount)}</strong>
                            </div>`).join('') : '<p style="padding:20px;color:var(--muted);font-size:.85rem;">No client payments this period.</p>'}
                    </div>
                </div>
            </div>

            <!-- Recent Transactions Ledger -->
            <div class="card">
                <div class="card-header">
                    <span class="material-symbols-rounded ms">receipt_long</span>
                    <h3>Recent Transactions</h3>
                    <span class="badge badge-gray" style="font-size:.7rem;">${recent.length} shown</span>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead><tr>
                            <th>Reference</th><th>Client</th><th>Loan #</th>
                            <th>Amount</th><th>Method</th><th>Source</th><th>Date</th><th>Status</th>
                        </tr></thead>
                        <tbody>
                            ${recent.length ? recent.map(t => `<tr>
                                <td class="td-mono" style="font-size:.78rem;">${escapeHtml(t.reference_no || t.receipt_number || '—')}</td>
                                <td class="td-bold">${escapeHtml(t.client_name)}</td>
                                <td class="td-muted">${escapeHtml(t.loan_number || '—')}</td>
                                <td class="td-bold" style="color:var(--brand);">${fmt(t.amount)}</td>
                                <td class="td-muted">${escapeHtml(t.payment_method || '—')}</td>
                                <td>${t.source_key === 'staff'
                                    ? '<span class="badge badge-blue" style="font-size:.68rem;">Staff</span>'
                                    : '<span class="badge badge-green" style="font-size:.68rem;">App</span>'}</td>
                                <td class="td-muted">${fmtDate(t.transaction_date)}</td>
                                <td>${badge(t.transaction_status)}</td>
                            </tr>`).join('') : '<tr class="empty-row"><td colspan="8">No transactions found for this period.</td></tr>'}
                        </tbody>
                    </table>
                </div>
            </div>`;
            } catch (error) {
                console.error(error);
                body.innerHTML = '<p style="color:var(--muted);padding:24px;">Could not load report data.</p>';
            }
        }

        function exportReportsPDF() {
            const element = document.getElementById('reportsBody');
            if (!element) return;
            
            const opt = {
                margin:       [10, 10, 10, 10],
                filename:     'Reports_Analytics.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            
            html2pdf().set(opt).from(element).save();
        }

        // ── Users ──────────────────────────────────────────────────────
        async function loadUsers() {
            const tbody = document.getElementById('usersTbody');
            if (!tbody) return;
            const r = await fetch('../../../microfin_backend/api/api_auth.php?action=list_users');
            const d = await r.json();
            if (d.status !== 'success') {
                tbody.innerHTML = '<tr class="empty-row"><td colspan="5">The team directory is unavailable right now.</td></tr>';
                return;
            }
            const rows = d.data || [];
            if (!rows.length) {
                tbody.innerHTML = '<tr class="empty-row"><td colspan="5">No staff accounts are available for this tenant.</td></tr>';
                return;
            }
            tbody.innerHTML = rows.map(u => `<tr>
        <td class="td-bold">${u.first_name || ''} ${u.last_name || ''} <span style="font-size:.78rem;color:var(--muted);">(${u.username})</span></td>
        <td class="td-muted">${u.email || '—'}</td>
        <td class="td-muted">${u.department || '—'}</td>
        <td class="td-muted">${u.position || u.role_name || '—'}</td>
        <td>${badge(u.status)}</td>
    </tr>`).join('');
        }

        // ── Walk-In ────────────────────────────────────────────────────
        const idMetadataRules = {
            'National ID (PhilID/ePhilID)': ['PCN'],
            'Passport': ['Passport Number', 'Expiry Date'],
            'Driver\'s License': ['License Number', 'Expiry Date'],
            'UMID': ['CRN'],
            'SSS ID': ['SSS Number'],
            'PRC ID': ['PRC License Number', 'Expiry Date'],
            'Postal ID': ['PRN'],
            'Voter\'s ID': ['VIN'],
        };

        function handleWalkInIdTypeChange(sel) {
            const val = sel.value;
            const container = document.getElementById('walkInIdFields');
            container.innerHTML = '';
            
            // Toggle ID Back visibility based on one-sided IDs
            const oneSidedIds = ['Passport', 'NBI Clearance', 'Police Clearance', 'TIN ID', 'Seaman\'s Book / SIRB'];
            const backContainer = document.getElementById('docIdBackContainer');
            const backInput = document.getElementById('docIdBackInput');
            if (oneSidedIds.includes(val)) {
                backContainer.style.display = 'none';
                backInput.removeAttribute('required');
            } else {
                backContainer.style.display = 'block';
                backInput.setAttribute('required', 'required');
            }
            
            if (val === '') return;
            
            const fields = idMetadataRules[val] || ['ID Number'];
            
            fields.forEach(f => {
                const type = f.toLowerCase().includes('date') ? 'date' : 'text';
                const name = f.toLowerCase().replace(/[^a-z0-9]/g, '_');
                container.innerHTML += `
                    <div class="form-group" style="margin-bottom:12px;">
                        <label>${f} *</label>
                        <input type="${type}" name="id_meta_${name}" required>
                    </div>
                `;
            });
        }

        let otpCooldownInterval = null;

        document.getElementById('walkInOtp')?.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length === 6) {
                verifyWalkInOtp(this.value);
            }
        });

        async function sendWalkInOtp() {
            const emailInput = document.getElementById('walkInEmail');
            const email = emailInput.value.trim();
            if (!email || !emailInput.checkValidity()) {
                emailInput.reportValidity();
                return;
            }

            const btn = document.getElementById('btnSendOtp');
            btn.disabled = true;
            btn.innerHTML = `<span class="spinner" style="width:16px;height:16px;border-width:2px;display:inline-block;vertical-align:middle;margin-right:6px;"></span> Sending...`;

            try {
                const fd = new FormData();
                fd.append('walk_in_action', 'send_otp');
                fd.append('email', email);
                const r = await fetch(API.walk_in, { method: 'POST', body: fd });
                const d = await r.json();

                if (d.status === 'success') {
                    document.getElementById('otpInputContainer').style.display = 'flex';
                    emailInput.readOnly = true;
                    btn.style.display = 'none';
                    startOtpTimer(300); // 5 minutes
                } else {
                    btn.disabled = false;
                    btn.innerHTML = 'Send OTP';
                    await showAlertPopup(d.message, { title: 'Hold On', variant: 'warning' });
                }
            } catch (err) {
                console.error(err);
                btn.disabled = false;
                btn.innerHTML = 'Send OTP';
            }
        }

        async function verifyWalkInOtp(otp) {
            const email = document.getElementById('walkInEmail').value.trim();
            const statusEl = document.getElementById('otpStatus');
            statusEl.innerHTML = `<span class="spinner" style="width:14px;height:14px;border-width:2px;display:inline-block;"></span>`;
            statusEl.style.color = 'var(--text)';

            try {
                const fd = new FormData();
                fd.append('walk_in_action', 'verify_otp');
                fd.append('email', email);
                fd.append('otp_input', otp);
                const r = await fetch(API.walk_in, { method: 'POST', body: fd });
                const d = await r.json();

                if (d.status === 'success') {
                    statusEl.innerHTML = '<span class="material-symbols-rounded ms" style="font-size:18px;vertical-align:middle;">check_circle</span> Verified';
                    statusEl.style.color = '#059669';
                    document.getElementById('otpVerifiedFlag').value = '1';
                    document.getElementById('walkInOtp').readOnly = true;
                    if (otpCooldownInterval) clearInterval(otpCooldownInterval);
                    document.getElementById('otpTimer').innerHTML = '';
                } else {
                    statusEl.innerHTML = 'Invalid code';
                    statusEl.style.color = '#dc2626';
                    document.getElementById('walkInOtp').value = '';
                }
            } catch (err) {
                statusEl.innerHTML = 'Error';
            }
        }

        function startOtpTimer(seconds) {
            const timerEl = document.getElementById('otpTimer');
            if (otpCooldownInterval) clearInterval(otpCooldownInterval);
            let remaining = seconds;
            
            otpCooldownInterval = setInterval(() => {
                remaining--;
                if (remaining <= 0) {
                    clearInterval(otpCooldownInterval);
                    timerEl.innerHTML = '';
                    const btn = document.getElementById('btnSendOtp');
                    btn.style.display = 'inline-flex';
                    btn.disabled = false;
                    btn.innerHTML = 'Resend OTP';
                } else {
                    const m = Math.floor(remaining / 60);
                    const s = remaining % 60;
                    timerEl.innerHTML = `Resend in ${m}:${s.toString().padStart(2, '0')}`;
                }
            }, 1000);
        }

        async function submitWalkIn(action) {
            const form = document.getElementById('walkInForm');
            if (!form.checkValidity()) { form.reportValidity(); return; }

            if (action === 'submit' && document.getElementById('otpVerifiedFlag').value !== '1') {
                await showAlertPopup('Please verify the client\'s email address with an OTP before submitting.', {
                    title: 'Email Verification Required',
                    variant: 'warning'
                });
                return;
            }

            const formData = new FormData(form);
            formData.set('walk_in_action', 'submit');

            try {
                const res = await fetch(API.walk_in, { method: 'POST', body: formData });
                const result = await res.json();
                if (result.status === 'success') {
                    closeModal('walkInModal');
                    await showAlertPopup(result.message || 'Walk-in registration saved.', {
                        title: 'Success',
                        variant: 'success'
                    });
                    form.reset();
                    location.reload();
                } else {
                    await showAlertPopup('Error: ' + result.message, {
                        title: 'Unable to Save Walk-In',
                        variant: 'danger'
                    });
                }
            } catch (err) {
                console.error(err);
                await showAlertPopup('An error occurred. Please try again.', {
                    title: 'Unable to Save Walk-In',
                    variant: 'danger'
                });
            }
        }

        // ── Close on backdrop click ─────────────────────────────────────
        document.querySelectorAll('.modal-backdrop').forEach(bd => {
            bd.addEventListener('click', e => {
                if (e.target !== bd) return;
                if (bd.id === 'dashboardPopupModal') {
                    dismissDashboardPopup();
                    return;
                }
                closeModal(bd.id);
            });
        });
    

