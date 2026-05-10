document.addEventListener('DOMContentLoaded', () => {

    // ============================================================
    // Tenant Profile Modal Logic (Moved to top for priority)
    // ============================================================
    const profileModalBackdrop = document.getElementById('modal-tenant-profile-backdrop');
    const btnCloseProfileModal = document.getElementById('close-tenant-profile-modal');
    const btnCancelProfileModal = document.getElementById('cancel-tenant-profile-modal');

    function closeTenantProfileModal() {
        if (profileModalBackdrop) profileModalBackdrop.classList.remove('show');
    }

    function openTenantProfileModal(btn) {
        if (!profileModalBackdrop || !btn) return;

        const data = btn.dataset;
        let docs = [];
        try {
            docs = JSON.parse(data.docs || '[]');
        } catch (e) {
            console.error('Error parsing tenant docs:', e);
            docs = [];
        }

        const setSafeText = (id, text) => {
            const el = document.getElementById(id);
            if (el) el.textContent = text || '—';
        };

        setSafeText('tenant-profile-name', data.tenantName);
        setSafeText('tenant-profile-id', 'ID: ' + (data.tenantId || '—'));
        setSafeText('tenant-profile-status', data.status);
        setSafeText('tenant-profile-plan', data.plan);
        setSafeText('tenant-profile-owner-name', data.ownerName);
        setSafeText('tenant-profile-owner-email', data.ownerEmail);
        setSafeText('tenant-profile-owner-phone', data.ownerPhone);

        // Initials
        const initialsEl = document.getElementById('tenant-profile-initials');
        if (initialsEl) {
            const initials = (data.tenantName || 'TP').substring(0, 2).toUpperCase();
            initialsEl.textContent = initials;
        }

        // Documents
        const docsList = document.getElementById('tenant-profile-docs-list');
        const noDocsMsg = document.getElementById('tenant-profile-no-docs');
        
        if (docsList) {
            docsList.innerHTML = '';
            if (Array.isArray(docs) && docs.length > 0) {
                if (noDocsMsg) noDocsMsg.style.display = 'none';
                docs.forEach(doc => {
                    const a = document.createElement('a');
                    a.href = doc.path;
                    a.target = '_blank';
                    a.rel = 'noopener';
                    a.className = 'btn btn-outline btn-sm';
                    a.style.justifyContent = 'flex-start';
                    a.innerHTML = `
                        <span class="material-symbols-rounded" style="font-size:18px;">description</span>
                        <span>View ${esc(doc.label)}</span>
                    `;
                    docsList.appendChild(a);
                });
            } else {
                if (noDocsMsg) noDocsMsg.style.display = 'block';
            }
        }

        profileModalBackdrop.classList.add('show');
    }

    if (btnCloseProfileModal) btnCloseProfileModal.addEventListener('click', closeTenantProfileModal);
    if (btnCancelProfileModal) btnCancelProfileModal.addEventListener('click', closeTenantProfileModal);
    if (profileModalBackdrop) {
        profileModalBackdrop.addEventListener('click', (e) => {
            if (e.target === profileModalBackdrop) closeTenantProfileModal();
        });
    }


    // ============================================================
    // SPA NAVIGATION (sidebar)
    // ============================================================
    const navItems = document.querySelectorAll('.sidebar-nav .nav-item');
    const viewSections = document.querySelectorAll('.view-section');
    const pageTitle = document.getElementById('page-title');

    navItems.forEach((item) => {
        item.addEventListener('click', (e) => {
            const targetId = item.getAttribute('data-target');
            if (!targetId) return;

            e.preventDefault();
            navItems.forEach((nav) => nav.classList.remove('active'));
            item.classList.add('active');

            viewSections.forEach((section) => section.classList.remove('active'));
            const targetSection = document.getElementById(targetId);
            if (targetSection) targetSection.classList.add('active');

            const label = item.querySelector('span:nth-child(2)');
            if (label) pageTitle.textContent = label.textContent;

            // Update URL hash without causing a page jump
            history.replaceState(null, null, `#${targetId}`);
        });
    });

    // Auto-navigate to section if hash exists or ?section= or ?tab= param exists
    const urlParams = new URLSearchParams(window.location.search);
    const rawTargetTab = window.location.hash
        ? window.location.hash.substring(1)
        : (urlParams.get('section') || urlParams.get('tab'));
    const targetTab = rawTargetTab === 'statements' ? 'receipts' : rawTargetTab;
    if (targetTab) {
        const targetNav = document.querySelector(`.nav-item[data-target="${targetTab}"]`);
        if (targetNav) targetNav.click();
    }

    let latestDashboardData = null;
    let latestSalesData = null;
    let chartUserGrowth = null;
    let chartTenantActivity = null;
    let chartSalesTrends = null;
    let chartRevenue = null;
    const rootElement = document.documentElement;
    const themeToggle = document.getElementById('theme-toggle');
    const themeToggleIcon = document.getElementById('theme-toggle-icon');
    const themeStorageKey = 'microfin_super_admin_theme';

    const normalizeTheme = (value) => value === 'dark' ? 'dark' : 'light';

    function syncThemeToggle(theme) {
        if (!themeToggle) {
            return;
        }

        const nextTheme = theme === 'dark' ? 'light' : 'dark';
        const label = `Switch to ${nextTheme} mode`;
        themeToggle.setAttribute('aria-label', label);
        themeToggle.setAttribute('title', label);
        if (themeToggleIcon) {
            themeToggleIcon.textContent = nextTheme === 'dark' ? 'dark_mode' : 'light_mode';
        }
    }

    function applyPlatformTheme(theme, persistLocal = true) {
        const resolvedTheme = normalizeTheme(theme);
        rootElement.setAttribute('data-theme', resolvedTheme);
        syncThemeToggle(resolvedTheme);

        if (persistLocal) {
            try {
                localStorage.setItem(themeStorageKey, resolvedTheme);
            } catch (error) {
                console.warn('Unable to store platform theme preference.', error);
            }
        }
    }

    try {
        const storedTheme = localStorage.getItem(themeStorageKey);
        if (storedTheme === 'light' || storedTheme === 'dark') {
            applyPlatformTheme(storedTheme, false);
        } else {
            applyPlatformTheme(rootElement.getAttribute('data-theme') || 'light', false);
        }
    } catch (error) {
        applyPlatformTheme(rootElement.getAttribute('data-theme') || 'light', false);
    }

    // ============================================================
    // MODAL (Provision Tenant)
    // ============================================================
    const btnCreateTenant = document.getElementById('btn-create-tenant');
    const modalBackdrop = document.getElementById('modal-backdrop');
    const btnCloseModal = document.getElementById('close-modal');
    const btnCancelModal = document.getElementById('cancel-modal');
    const modalForm = modalBackdrop ? modalBackdrop.querySelector('form') : null;
    const btnSubmitTenant = document.getElementById('submit-tenant');

    const resetModalFormReadOnly = () => {
        if (modalForm) {
            Array.from(modalForm.elements).forEach(el => {
                if (el.tagName !== 'BUTTON' && el.type !== 'hidden') {
                    el.removeAttribute('readonly');
                    el.style.pointerEvents = '';
                    el.style.backgroundColor = '';
                    el.style.opacity = '';
                    el.style.cursor = '';
                }
            });
        }
    };

    const closeModal = () => {
        if (modalBackdrop) {
            modalBackdrop.classList.remove('show');
            if (modalForm) {
                modalForm.reset();
                resetModalFormReadOnly();
            }
        }
    };

    if (btnCreateTenant && modalBackdrop) {
        btnCreateTenant.addEventListener('click', () => {
            resetModalFormReadOnly();
            modalBackdrop.classList.add('show');
        });
    }
    if (btnCloseModal) btnCloseModal.addEventListener('click', closeModal);
    if (btnCancelModal) btnCancelModal.addEventListener('click', closeModal);
    if (modalBackdrop) {
        modalBackdrop.addEventListener('click', (e) => {
            if (e.target === modalBackdrop) closeModal();
        });
    }

    if (modalForm && btnSubmitTenant) {
        modalForm.addEventListener('submit', () => {
            btnSubmitTenant.innerHTML = '<span class="material-symbols-rounded" style="animation: spin 1s linear infinite;">sync</span> Provisioning...';
            btnSubmitTenant.style.opacity = '0.8';
            btnSubmitTenant.disabled = true;
        });
        
        const nameInputGlobal = modalForm.querySelector('input[name="tenant_name"]');
        const slugInputGlobal = modalForm.querySelector('input[name="custom_slug"]');
        if (nameInputGlobal && slugInputGlobal) {
            nameInputGlobal.addEventListener('input', () => {
                if (!slugInputGlobal.dataset.manuallyEdited) {
                    slugInputGlobal.value = nameInputGlobal.value.toLowerCase().trim().replace(/[^a-z0-9-]+/g, '-').replace(/^-+|-+$/g, '');
                }
            });
            slugInputGlobal.addEventListener('input', () => {
                slugInputGlobal.dataset.manuallyEdited = ((slugInputGlobal.value.length > 0) ? 'true' : '');
            });
        }
    }

    // Create SA Modal
    const btnCreateSA = document.getElementById('btn-create-super-admin');
    const saModalBackdrop = document.getElementById('modal-sa-backdrop');
    const saModalForm = saModalBackdrop ? saModalBackdrop.querySelector('form') : null;
    const btnCloseSAModal = document.getElementById('close-sa-modal');
    const btnCancelSAModal = document.getElementById('cancel-sa-modal');
    const saProfileModeToggle = document.getElementById('sa-profile-mode');
    const saProfileModeSides = saModalForm ? Array.from(saModalForm.querySelectorAll('.profile-mode-side')) : [];
    const saProfileModeTitle = document.getElementById('sa-profile-mode-title');
    const saProfileModeDescription = document.getElementById('sa-profile-mode-description');
    const saFillNowFields = document.getElementById('sa-fill-now-fields');
    const saFillNowRequiredFields = saModalForm ? Array.from(saModalForm.querySelectorAll('[data-fill-now-required="true"]')) : [];

    function syncSuperAdminProfileMode() {
        const selectedMode = saProfileModeToggle && saProfileModeToggle.checked ? 'fill_now' : 'onboarding';

        if (saFillNowFields) {
            saFillNowFields.classList.toggle('is-hidden', selectedMode !== 'fill_now');
        }

        saProfileModeSides.forEach((side) => {
            side.classList.toggle('active', side.dataset.mode === selectedMode);
        });

        if (saProfileModeTitle) {
            saProfileModeTitle.textContent = selectedMode === 'fill_now'
                ? 'Fill It Now'
                : 'Complete During Onboarding';
        }

        if (saProfileModeDescription) {
            saProfileModeDescription.textContent = selectedMode === 'fill_now'
                ? 'You capture the profile details now, then the admin only resets the temporary password.'
                : 'Only the login account is created now. The admin finishes their profile after first login.';
        }

        saFillNowRequiredFields.forEach((field) => {
            field.required = selectedMode === 'fill_now';
        });
    }

    function closeSuperAdminModal() {
        if (!saModalBackdrop) {
            return;
        }

        saModalBackdrop.classList.remove('show');
        if (saModalForm) {
            saModalForm.reset();
            syncSuperAdminProfileMode();
        }
    }

    if (btnCreateSA && saModalBackdrop) {
        btnCreateSA.addEventListener('click', () => {
            if (saModalForm) {
                saModalForm.reset();
            }
            syncSuperAdminProfileMode();
            saModalBackdrop.classList.add('show');
        });
    }
    if (btnCloseSAModal) btnCloseSAModal.addEventListener('click', closeSuperAdminModal);
    if (btnCancelSAModal) btnCancelSAModal.addEventListener('click', closeSuperAdminModal);
    if (saModalBackdrop) {
        saModalBackdrop.addEventListener('click', (e) => {
            if (e.target === saModalBackdrop) {
                closeSuperAdminModal();
            }
        });
    }
    if (saProfileModeToggle) {
        saProfileModeToggle.addEventListener('change', syncSuperAdminProfileMode);
    }
    syncSuperAdminProfileMode();

    // Tenant Deactivation Modal
    const tenantStatusModalBackdrop = document.getElementById('modal-tenant-status-backdrop');
    const tenantStatusModalForm = tenantStatusModalBackdrop ? tenantStatusModalBackdrop.querySelector('form') : null;
    const btnCloseTenantStatusModal = document.getElementById('close-tenant-status-modal');
    const btnCancelTenantStatusModal = document.getElementById('cancel-tenant-status-modal');
    const tenantStatusTenantId = document.getElementById('tenant-status-tenant-id');
    const tenantStatusTenantName = document.getElementById('tenant-status-tenant-name');
    const tenantStatusReason = document.getElementById('tenant-status-reason');

    function closeTenantStatusModal() {
        if (!tenantStatusModalBackdrop) {
            return;
        }

        tenantStatusModalBackdrop.classList.remove('show');
        if (tenantStatusModalForm) {
            tenantStatusModalForm.reset();
        }
    }

    function openTenantStatusModal(buttonEl) {
        if (!tenantStatusModalBackdrop || !tenantStatusModalForm || !buttonEl) {
            return;
        }

        tenantStatusModalForm.reset();
        if (tenantStatusTenantId) {
            tenantStatusTenantId.value = buttonEl.dataset.tenantId || '';
        }
        if (tenantStatusTenantName) {
            tenantStatusTenantName.value = buttonEl.dataset.tenantName || '';
        }

        tenantStatusModalBackdrop.classList.add('show');
        if (tenantStatusReason) {
            tenantStatusReason.focus();
        }
    }

    if (btnCloseTenantStatusModal) btnCloseTenantStatusModal.addEventListener('click', closeTenantStatusModal);
    if (btnCancelTenantStatusModal) btnCancelTenantStatusModal.addEventListener('click', closeTenantStatusModal);
    if (tenantStatusModalBackdrop) {
        tenantStatusModalBackdrop.addEventListener('click', (e) => {
            if (e.target === tenantStatusModalBackdrop) {
                closeTenantStatusModal();
            }
        });
    }

    // Audit Details Modal
    const auditModalBackdrop = document.getElementById('modal-audit-backdrop');
    const btnCloseAuditModal = document.getElementById('close-audit-modal');
    const btnCloseAuditModalFooter = document.getElementById('close-audit-modal-footer');

    function closeAuditModal() {
        if (auditModalBackdrop) auditModalBackdrop.classList.remove('show');
    }

    function openAuditModalFromButton(buttonEl) {
        if (!auditModalBackdrop || !buttonEl) return;

        const setValue = (id, value) => {
            const field = document.getElementById(id);
            if (field) field.value = value || '—';
        };

        setValue('audit-detail-created-at', formatDateTime(buttonEl.dataset.createdAt));
        setValue('audit-detail-username', buttonEl.dataset.username || '—');
        setValue('audit-detail-user-email', buttonEl.dataset.userEmail || 'System');
        setValue('audit-detail-tenant-name', buttonEl.dataset.tenantName || 'Platform');
        setValue('audit-detail-action-type', buttonEl.dataset.actionType || '—');
        setValue('audit-detail-entity-type', buttonEl.dataset.entityType || '—');
        setValue('audit-detail-description', buttonEl.dataset.description || '—');

        auditModalBackdrop.classList.add('show');
    }

    if (btnCloseAuditModal) btnCloseAuditModal.addEventListener('click', closeAuditModal);
    if (btnCloseAuditModalFooter) btnCloseAuditModalFooter.addEventListener('click', closeAuditModal);
    if (auditModalBackdrop) {
        auditModalBackdrop.addEventListener('click', (e) => {
            if (e.target === auditModalBackdrop) closeAuditModal();
        });
    }

    document.addEventListener('click', (e) => {
        const deactivateTrigger = e.target.closest('.btn-tenant-deactivate');
        if (deactivateTrigger) {
            e.preventDefault();
            openTenantStatusModal(deactivateTrigger);
            return;
        }

        const viewProfileTrigger = e.target.closest('.btn-view-tenant-profile');

        if (viewProfileTrigger) {
            e.preventDefault();
            openTenantProfileModal(viewProfileTrigger);
            return;
        }

        const trigger = e.target.closest('.audit-detail-btn');
        if (!trigger) return;
        e.preventDefault();
        openAuditModalFromButton(trigger);
    });

    // Bind provision buttons (from tenant table rows)
    bindProvisionButtons();


    // ============================================================
    // DASHBOARD: Charts + Polling
    // ============================================================
    function readCssVar(name) {
        return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    }

    function rgbaFromVar(name, alpha) {
        return `rgba(${readCssVar(name)}, ${alpha})`;
    }

    function getChartTheme() {
        return {
            primary: readCssVar('--primary-color'),
            primaryLight: rgbaFromVar('--primary-rgb', 0.22),
            primaryAlt: readCssVar('--tone-primary-text'),
            success: readCssVar('--success-color'),
            successLight: rgbaFromVar('--success-rgb', 0.22),
            secondary: readCssVar('--secondary-color'),
            secondaryLight: rgbaFromVar('--secondary-rgb', 0.22),
            secondaryAlt: readCssVar('--tone-secondary-text'),
            warning: readCssVar('--warning-color'),
            warningLight: rgbaFromVar('--warning-rgb', 0.18),
            error: readCssVar('--error-color'),
            errorLight: rgbaFromVar('--error-rgb', 0.18),
            neutral: readCssVar('--text-muted'),
            grid: readCssVar('--chart-grid'),
            ticks: readCssVar('--chart-ticks')
        };
    }

    function buildTenantPalette(count) {
        const theme = getChartTheme();
        const base = [
            theme.primary,
            theme.secondary,
            theme.success,
            theme.warning,
            theme.error,
            theme.primaryAlt,
            theme.secondaryAlt,
            theme.neutral
        ];
        const colors = [];
        for (let i = 0; i < count; i++) {
            colors.push(base[i % base.length]);
        }
        return colors;
    }

    function buildChartDefaults() {
        const theme = getChartTheme();
        return {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false,
                    labels: { color: theme.ticks, font: { family: 'Outfit' } }
                }
            },
            scales: {
                x: {
                    grid: { color: theme.grid },
                    ticks: { color: theme.ticks, font: { family: 'Outfit' } }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: theme.grid },
                    ticks: { color: theme.ticks, font: { family: 'Outfit' } }
                }
            }
        };
    }

    function toYmd(dateObj) {
        const yyyy = dateObj.getFullYear();
        const mm = String(dateObj.getMonth() + 1).padStart(2, '0');
        const dd = String(dateObj.getDate()).padStart(2, '0');
        return `${yyyy}-${mm}-${dd}`;
    }

    function getUserGrowthDateRange() {
        const fromEl = document.getElementById('user-growth-date-from');
        const toEl = document.getElementById('user-growth-date-to');
        return {
            dateFrom: fromEl ? fromEl.value : '',
            dateTo: toEl ? toEl.value : ''
        };
    }

    function setUserGrowthDateDefaults() {
        const fromEl = document.getElementById('user-growth-date-from');
        const toEl = document.getElementById('user-growth-date-to');
        if (!fromEl || !toEl) return;

        const today = new Date();
        const sevenDaysBehind = new Date(today);
        sevenDaysBehind.setDate(today.getDate() - 7);

        if (!fromEl.value) fromEl.value = toYmd(sevenDaysBehind);
        if (!toEl.value) toEl.value = toYmd(today);
    }

    function buildDashboardQueryString() {
        const params = new URLSearchParams({ action: 'dashboard' });
        const { dateFrom, dateTo } = getUserGrowthDateRange();
        if (dateFrom) params.set('date_from', dateFrom);
        if (dateTo) params.set('date_to', dateTo);
        return params.toString();
    }

    function buildUserGrowthDatasets(userGrowthChartData) {
        const series = (userGrowthChartData && Array.isArray(userGrowthChartData.series)) ? userGrowthChartData.series : [];
        const colors = buildTenantPalette(series.length);

        return series.map((s, idx) => {
            const color = colors[idx];
            return {
                label: s.tenant_name || `Tenant ${idx + 1}`,
                data: Array.isArray(s.points) ? s.points.map(v => Number(v || 0)) : [],
                borderColor: color,
                backgroundColor: color + '33',
                fill: true,
                tension: 0.35,
                pointRadius: 3,
                pointHoverRadius: 5,
                pointBackgroundColor: color,
                borderWidth: 2
            };
        });
    }

    function initDashboardCharts(data) {
        const userGrowthCtx = document.getElementById('chart-user-growth');
        const tenantActivityCtx = document.getElementById('chart-tenant-activity');
        const salesTrendsCtx = document.getElementById('chart-sales-trends');
        const theme = getChartTheme();
        const defaultOptions = buildChartDefaults();

        if (chartUserGrowth) {
            chartUserGrowth.destroy();
            chartUserGrowth = null;
        }
        if (chartTenantActivity) {
            chartTenantActivity.destroy();
            chartTenantActivity = null;
        }
        if (chartSalesTrends) {
            chartSalesTrends.destroy();
            chartSalesTrends = null;
        }

        const integerTickFormatter = (value) => {
            const numeric = Number(value);
            if (!Number.isFinite(numeric) || !Number.isInteger(numeric)) {
                return '';
            }
            return String(numeric);
        };

        if (userGrowthCtx) {
            const userGrowthLabels = (data.user_growth_chart && Array.isArray(data.user_growth_chart.labels))
                ? data.user_growth_chart.labels
                : [];
            const userGrowthDatasets = buildUserGrowthDatasets(data.user_growth_chart);
            chartUserGrowth = new Chart(userGrowthCtx, {
                type: 'line',
                data: {
                    labels: userGrowthLabels,
                    datasets: userGrowthDatasets
                },
                options: {
                    ...defaultOptions,
                    plugins: {
                        legend: {
                            display: true,
                            labels: { color: theme.ticks, font: { family: 'Outfit' } }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const datasetLabel = context.dataset && context.dataset.label ? context.dataset.label + ': ' : '';
                                    const numericValue = Number(context.parsed && context.parsed.y);
                                    const safeValue = Number.isFinite(numericValue) ? Math.round(numericValue) : 0;
                                    return datasetLabel + safeValue;
                                }
                            }
                        }
                    },
                    scales: {
                        ...defaultOptions.scales,
                        y: {
                            ...defaultOptions.scales.y,
                            ticks: {
                                ...defaultOptions.scales.y.ticks,
                                callback: integerTickFormatter
                            }
                        }
                    }
                }
            });
        }

        if (tenantActivityCtx) {
            chartTenantActivity = new Chart(tenantActivityCtx, {
                type: 'bar',
                data: {
                    labels: (data.tenant_activity_chart || []).map(d => d.month),
                    datasets: [
                        {
                            label: 'Active',
                            data: (data.tenant_activity_chart || []).map(d => Number(d.active_count || 0)),
                            backgroundColor: theme.success,
                            borderRadius: 4,
                            maxBarThickness: 40
                        },
                        {
                            label: 'Pending Application',
                            data: (data.tenant_activity_chart || []).map(d => Number(d.pending_count || 0)),
                            backgroundColor: theme.warning,
                            borderRadius: 4,
                            maxBarThickness: 40
                        },
                        {
                            label: 'Inactive',
                            data: (data.tenant_activity_chart || []).map(d => Number(d.inactive_count || 0)),
                            backgroundColor: theme.error,
                            borderRadius: 4,
                            maxBarThickness: 40
                        }
                    ]
                },
                options: {
                    ...defaultOptions,
                    plugins: {
                        legend: {
                            display: true,
                            labels: { color: theme.ticks, font: { family: 'Outfit' } }
                        }
                    }
                }
            });
            chartTenantActivity.options.scales.x.stacked = true;
            chartTenantActivity.options.scales.y.stacked = true;
        }

        if (salesTrendsCtx) {
            chartSalesTrends = new Chart(salesTrendsCtx, {
                type: 'line',
                data: {
                    labels: (data.sales_trends_chart || []).map(d => d.month),
                    datasets: [{
                        label: 'Revenue',
                        data: (data.sales_trends_chart || []).map(d => parseFloat(d.total)),
                        borderColor: theme.primary,
                        backgroundColor: theme.primaryLight,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 4,
                        pointBackgroundColor: theme.primary
                    }]
                },
                options: defaultOptions
            });
        }
    }

    function updateDashboardStats(data) {
        const el = (id, val) => {
            const e = document.getElementById(id);
            if (e) e.textContent = val;
        };
        el('stat-active-tenants', data.active_tenants);
        el('stat-super-admin-accounts', data.active_super_admin_accounts);
        el('stat-inactive-users', data.inactive_users);
        el('stat-pending-apps', data.pending_applications ?? '0');
        el('stat-total-mrr', '₱' + data.total_mrr);
        el('stat-revenue-total', '₱' + (data.total_revenue || '0.00'));

        // Update sidebar pending badge (applications only)
        const badge = document.getElementById('sidebar-pending-badge');
        if (badge) {
            const count = parseInt(data.pending_applications ?? 0, 10);
            badge.textContent = count;
            badge.style.display = count > 0 ? '' : 'none';
        }

        // Update sidebar inquiry badge (inquiries only)
        const inquiryBadge = document.getElementById('sidebar-inquiry-badge');
        if (inquiryBadge) {
            const count = parseInt(data.pending_inquiries ?? 0, 10);
            inquiryBadge.textContent = count;
            inquiryBadge.style.display = count > 0 ? '' : 'none';
        }

        // Update Applications tab badge
        const appBadge = document.getElementById('tab-badge-applications');
        if (appBadge) {
            const c = parseInt(data.pending_applications ?? 0, 10);
            appBadge.textContent = c;
            appBadge.style.display = c > 0 ? '' : 'none';
        }

        // Update Inquiries tab badge
        const inqBadge = document.getElementById('tab-badge-inquiries');
        if (inqBadge) {
            const c = parseInt(data.pending_inquiries ?? 0, 10);
            inqBadge.textContent = c;
            inqBadge.style.display = c > 0 ? '' : 'none';
        }

        // Update charts
        if (chartUserGrowth && data.user_growth_chart) {
            chartUserGrowth.data.labels = Array.isArray(data.user_growth_chart.labels) ? data.user_growth_chart.labels : [];
            chartUserGrowth.data.datasets = buildUserGrowthDatasets(data.user_growth_chart);
            chartUserGrowth.update('none');
        }
        if (chartTenantActivity && data.tenant_activity_chart) {
            chartTenantActivity.data.labels = data.tenant_activity_chart.map(d => d.month);
            chartTenantActivity.data.datasets[0].data = data.tenant_activity_chart.map(d => Number(d.active_count || 0));
            chartTenantActivity.data.datasets[1].data = data.tenant_activity_chart.map(d => Number(d.pending_count || 0));
            chartTenantActivity.data.datasets[2].data = data.tenant_activity_chart.map(d => Number(d.inactive_count || 0));
            chartTenantActivity.update('none');
        }
        if (chartSalesTrends && data.sales_trends_chart) {
            chartSalesTrends.data.labels = data.sales_trends_chart.map(d => d.month);
            chartSalesTrends.data.datasets[0].data = data.sales_trends_chart.map(d => parseFloat(d.total));
            chartSalesTrends.update('none');
        }
    }

    async function loadDashboardStats(initCharts = false) {
        try {
            const res = await fetch('api_dashboard_stats.php?' + buildDashboardQueryString());
            if (!res.ok) return;
            const data = await res.json();
            latestDashboardData = data;

            const fromEl = document.getElementById('user-growth-date-from');
            const toEl = document.getElementById('user-growth-date-to');
            if (fromEl && data.user_growth_date_from && !fromEl.value) fromEl.value = data.user_growth_date_from;
            if (toEl && data.user_growth_date_to && !toEl.value) toEl.value = data.user_growth_date_to;

            if (initCharts) {
                initDashboardCharts(data);
            }
            updateDashboardStats(data);
        } catch (e) {
            console.error('Dashboard load error:', e);
        }
    }

    setUserGrowthDateDefaults();
    loadDashboardStats(true);

    const btnApplyUserGrowthFilter = document.getElementById('btn-apply-user-growth-filter');
    if (btnApplyUserGrowthFilter) {
        btnApplyUserGrowthFilter.addEventListener('click', async () => {
            const fromEl = document.getElementById('user-growth-date-from');
            const toEl = document.getElementById('user-growth-date-to');
            if (!fromEl || !toEl) return;

            if (fromEl.value && toEl.value && fromEl.value > toEl.value) {
                const tmp = fromEl.value;
                fromEl.value = toEl.value;
                toEl.value = tmp;
            }
            await loadDashboardStats(false);
        });
    }

    // Poll every 5 seconds
    setInterval(async () => {
        await loadDashboardStats(false);
    }, 5000);

    function refreshThemedCharts() {
        if (latestDashboardData) {
            initDashboardCharts(latestDashboardData);
        }
        if (latestSalesData) {
            renderSalesReport(latestSalesData);
        }
    }

    if (themeToggle) {
        themeToggle.addEventListener('click', async () => {
            const previousTheme = normalizeTheme(rootElement.getAttribute('data-theme'));
            const nextTheme = previousTheme === 'dark' ? 'light' : 'dark';
            applyPlatformTheme(nextTheme);
            refreshThemedCharts();

            try {
                const response = await fetch('../../microfin_backend/api/api_theme_preference.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        role: 'super_admin',
                        theme: nextTheme
                    })
                });

                const result = await response.json();
                if (!response.ok || result.status !== 'success') {
                    throw new Error(result.message || 'Failed to save theme preference.');
                }
            } catch (error) {
                console.error('Theme preference save failed:', error);
                applyPlatformTheme(previousTheme);
                refreshThemedCharts();
            }
        });
    }

    // ============================================================
    // TENANT MANAGEMENT: Filter + Search
    // ============================================================
    const tenantStatusFilter = document.getElementById('tenant-status-filter');
    const applicationStatusFilter = document.getElementById('application-status-filter');
    const inquiryStatusFilter = document.getElementById('inquiry-status-filter');
    const tenantSearch = document.getElementById('tenant-search');
    const tenantsTable = document.getElementById('tenants-table');
    const tenantIntakeTabs = document.querySelectorAll('.tenant-intake-tab');
    let activeTenantView = document.querySelector('.tenant-intake-tab.active')?.getAttribute('data-view') || 'tenants';

    function normalizeInquiryStatus(rowStatus, rowRequestType) {
        const rawStatus = String(rowStatus || '').toLowerCase();
        const requestType = String(rowRequestType || '').toLowerCase();

        if (requestType === 'talk_to_expert') {
            if (rawStatus === 'pending') return 'new';
            if (rawStatus === 'contacted') return 'in_contact';
            if (rawStatus === 'new') return 'new';
            if (rawStatus === 'in contact') return 'in_contact';
            return 'closed';
        }

        if (rawStatus === 'active') return 'active';
        if (rawStatus === 'suspended') return 'suspended';
        if (rawStatus === 'rejected') return 'rejected';
        return 'pending';
    }

    function updateTenantManagementFilterVisibility() {
        activeTenantView = document.querySelector('.tenant-intake-tab.active')?.getAttribute('data-view') || activeTenantView;

        if (activeTenantView === 'inquiries') {
            if (tenantStatusFilter) tenantStatusFilter.style.display = 'none';
            if (applicationStatusFilter) applicationStatusFilter.style.display = 'none';
            if (inquiryStatusFilter) inquiryStatusFilter.style.display = '';
        } else if (activeTenantView === 'applications') {
            if (tenantStatusFilter) tenantStatusFilter.style.display = 'none';
            if (applicationStatusFilter) applicationStatusFilter.style.display = '';
            if (inquiryStatusFilter) inquiryStatusFilter.style.display = 'none';
        } else {
            if (tenantStatusFilter) tenantStatusFilter.style.display = '';
            if (applicationStatusFilter) applicationStatusFilter.style.display = 'none';
            if (inquiryStatusFilter) inquiryStatusFilter.style.display = 'none';
        }
    }

    tenantIntakeTabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            tenantIntakeTabs.forEach((t) => t.classList.remove('active'));
            tab.classList.add('active');
            activeTenantView = tab.getAttribute('data-view') || 'all';
            updateTenantManagementFilterVisibility();
            filterTenantTable();
        });
    });

    function filterTenantTable() {
        if (!tenantsTable) return;
        const status = (activeTenantView === 'inquiries' && inquiryStatusFilter)
            ? inquiryStatusFilter.value
            : (activeTenantView === 'applications' && applicationStatusFilter)
                ? applicationStatusFilter.value
                : (tenantStatusFilter ? tenantStatusFilter.value : 'all');
        const search = tenantSearch ? tenantSearch.value.toLowerCase() : '';
        const rows = tenantsTable.querySelectorAll('tbody tr[data-status]');

        rows.forEach(row => {
            const rowStatus = row.getAttribute('data-status');
            const rowRequestType = row.getAttribute('data-request-type') || 'tenant_application';
            const normalizedStatus = normalizeInquiryStatus(rowStatus, rowRequestType);
            const rowText = row.textContent.toLowerCase();
            const statusMatch = status === 'all' || normalizedStatus === status;
            const isApplication = rowRequestType === 'tenant_application' && (normalizedStatus === 'pending' || normalizedStatus === 'rejected');
            const isTenant = rowRequestType === 'tenant_application' && (normalizedStatus === 'active' || normalizedStatus === 'suspended');
            const isInquiry = rowRequestType === 'talk_to_expert';

            let viewMatch = false;
            if (activeTenantView === 'all') viewMatch = true;
            if (activeTenantView === 'tenants') viewMatch = isTenant;
            if (activeTenantView === 'applications') viewMatch = isApplication;
            if (activeTenantView === 'inquiries') viewMatch = isInquiry;

            const searchMatch = search === '' || rowText.includes(search);
            row.style.display = statusMatch && viewMatch && searchMatch ? '' : 'none';
        });
    }

    if (tenantStatusFilter) tenantStatusFilter.addEventListener('change', filterTenantTable);
    if (applicationStatusFilter) applicationStatusFilter.addEventListener('change', filterTenantTable);
    if (inquiryStatusFilter) inquiryStatusFilter.addEventListener('change', filterTenantTable);
    if (tenantSearch) tenantSearch.addEventListener('input', filterTenantTable);
    updateTenantManagementFilterVisibility();
    filterTenantTable();

    // ============================================================
    // TENANT SUBSCRIPTIONS: Search + Plan Change Guardrails
    // ============================================================
    const subscriptionSearch = document.getElementById('subscription-search');
    const subscriptionTable = document.getElementById('tenant-subscriptions-table');
    const subscriptionForms = document.querySelectorAll('.subscription-change-form');
    const subscriptionPlanRank = {
        Starter: 1,
        Enterprise: 2
    };

    function getSubscriptionPlanRank(plan) {
        return subscriptionPlanRank[plan] || 0;
    }

    function filterSubscriptionTable() {
        if (!subscriptionTable || !subscriptionSearch) return;
        const searchText = subscriptionSearch.value.toLowerCase().trim();
        const rows = subscriptionTable.querySelectorAll('tbody tr[data-subscription-row]');
        rows.forEach((row) => {
            const rowText = row.textContent.toLowerCase();
            row.style.display = searchText === '' || rowText.includes(searchText) ? '' : 'none';
        });
    }

    if (subscriptionSearch) {
        subscriptionSearch.addEventListener('input', filterSubscriptionTable);
    }

    // ============================================================
    // REPORTS: Load via AJAX
    // ============================================================
    const reportDateFromInput = document.getElementById('report-date-from');
    const reportDateToInput = document.getElementById('report-date-to');
    const reportTenantFilter = document.getElementById('report-tenant-filter');
    const reportPdfButton = document.getElementById('btn-export-report-pdf');

    function initializeReportFilters() {
        if (!reportDateFromInput || !reportDateToInput) return;

        const today = new Date();
        const yearStart = new Date(today.getFullYear(), 0, 1);

        if (!reportDateFromInput.value) reportDateFromInput.value = toYmd(yearStart);
        if (!reportDateToInput.value) reportDateToInput.value = toYmd(today);
    }

    function loadReports() {
        const params = new URLSearchParams({ action: 'reports' });
        if (reportDateFromInput && reportDateFromInput.value) params.set('date_from', reportDateFromInput.value);
        if (reportDateToInput && reportDateToInput.value) params.set('date_to', reportDateToInput.value);
        if (reportTenantFilter && reportTenantFilter.value) params.set('tenant_id', reportTenantFilter.value);
        updateReportPdfLink();

        fetch('api_dashboard_stats.php?' + params.toString())
            .then(r => r.json())
            .then(data => renderReports(data))
            .catch(e => {
                console.error('Reports error:', e);
                renderReports({ tenant_activity: [] });
            });
    }

    function updateReportPdfLink(filters = null) {
        if (!reportPdfButton) return;

        const params = new URLSearchParams();
        const fromValue = filters && filters.date_from ? filters.date_from : (reportDateFromInput ? reportDateFromInput.value : '');
        const toValue = filters && filters.date_to ? filters.date_to : (reportDateToInput ? reportDateToInput.value : '');
        const tenantValue = filters && typeof filters.tenant_id !== 'undefined'
            ? filters.tenant_id
            : (reportTenantFilter ? reportTenantFilter.value : '');

        if (fromValue) params.set('date_from', fromValue);
        if (toValue) params.set('date_to', toValue);
        if (tenantValue) params.set('tenant_id', tenantValue);

        reportPdfButton.href = 'report_pdf.php' + (params.toString() ? `?${params.toString()}` : '');
    }

    [reportDateFromInput, reportDateToInput, reportTenantFilter].forEach((input) => {
        if (input) {
            input.addEventListener('change', () => {
                updateReportPdfLink();
                loadReports();
            });
        }
    });

    if (document.getElementById('reports')) {
        initializeReportFilters();
        updateReportPdfLink();
        loadReports();
    }

    function renderReports(data) {
        const summary = data.summary || {};
        const filters = data.filters || {};
        const setEl = (id, value) => {
            const node = document.getElementById(id);
            if (node) node.textContent = value;
        };

        setEl('report-stat-total-tenants', String(summary.total_tenants ?? 0));
        setEl('report-stat-current-mrr', formatCurrency(summary.current_mrr ?? 0));
        setEl('report-stat-range-revenue', formatCurrency(summary.range_revenue ?? 0));
        setEl('report-stat-range-transactions', String(summary.range_transactions ?? 0));
        updateReportPdfLink(filters);

        const analyticsSummary = document.getElementById('report-analytics-summary');
        if (analyticsSummary) {
            analyticsSummary.textContent = data.analytics_summary || 'Derived analytics from the current report scope will appear here.';
        }

        // Tenant Activity
        const taTbody = document.querySelector('#report-tenant-activity tbody');
        if (taTbody) {
            if (!data.tenant_activity || data.tenant_activity.length === 0) {
                taTbody.innerHTML = '<tr><td colspan="5" class="text-muted" style="text-align:center; padding:2rem;">No tenant activity found for the selected filters.</td></tr>';
            } else {
                taTbody.innerHTML = data.tenant_activity.map(t => `
                    <tr>
                        <td>${esc(t.tenant_name)}</td>
                        <td><span class="badge">${esc(t.status || 'Unknown')}</span></td>
                        <td><span class="badge ${t.status_legend === 'Active' ? 'badge-green' : (t.status_legend === 'Pending Application' ? 'badge-amber' : 'badge-red')}">${esc(t.status_legend || 'Inactive')}</span></td>
                        <td>${esc(t.plan_tier || '—')}</td>
                        <td>${formatDate(t.created_at)}</td>
                    </tr>
                `).join('');
            }
        }

        const inquiryTbody = document.querySelector('#report-inquiry-activity tbody');
        if (inquiryTbody) {
            if (!data.inquiry_activity || data.inquiry_activity.length === 0) {
                inquiryTbody.innerHTML = '<tr><td colspan="4" class="text-muted" style="text-align:center; padding:2rem;">No inquiry activity found for the selected filters.</td></tr>';
            } else {
                inquiryTbody.innerHTML = data.inquiry_activity.map(t => {
                    const stage = t.inquiry_stage || 'Inactive';
                    const stageClass = stage === 'Open' ? 'badge-amber' : (stage === 'Closed' ? 'badge-green' : 'badge-red');
                    return `
                    <tr>
                        <td>${esc(t.tenant_name)}</td>
                        <td><span class="badge">${esc(t.status || 'Unknown')}</span></td>
                        <td><span class="badge ${stageClass}">${esc(stage)}</span></td>
                        <td>${formatDate(t.created_at)}</td>
                    </tr>
                `;
                }).join('');
            }
        }

        const planTbody = document.querySelector('#report-plan-summary tbody');
        if (planTbody) {
            if (!data.plan_summary || data.plan_summary.length === 0) {
                planTbody.innerHTML = '<tr><td colspan="5" class="text-muted" style="text-align:center; padding:2rem;">No plan summary is available for the selected filters.</td></tr>';
            } else {
                planTbody.innerHTML = data.plan_summary.map(plan => `
                    <tr>
                        <td>${esc(plan.plan_tier || 'Unassigned')}</td>
                        <td>${Number(plan.total_tenants || 0)}</td>
                        <td>${Number(plan.active_tenants || 0)}</td>
                        <td>${formatCurrency(plan.total_mrr || 0)}</td>
                        <td>${Number(plan.total_users || 0)}</td>
                    </tr>
                `).join('');
            }
        }

        const billingTbody = document.querySelector('#report-billing-summary tbody');
        if (billingTbody) {
            if (!data.billing_summary || data.billing_summary.length === 0) {
                billingTbody.innerHTML = '<tr><td colspan="5" class="text-muted" style="text-align:center; padding:2rem;">No billing activity found for the selected filters.</td></tr>';
            } else {
                billingTbody.innerHTML = data.billing_summary.map(row => `
                    <tr>
                        <td>${esc(row.tenant_name)}</td>
                        <td>${esc(row.plan_tier || '—')}</td>
                        <td>${formatCurrency(row.total_revenue || 0)}</td>
                        <td>${Number(row.transaction_count || 0)}</td>
                        <td>${row.latest_payment ? formatDateTime(row.latest_payment) : '—'}</td>
                    </tr>
                `).join('');
            }
        }
    }

    // ============================================================
    // SALES REPORT: Load via AJAX
    // ============================================================
    const revenuePeriodFilter = document.getElementById('revenue-period-filter');
    
    function loadSalesData() {
        const period = revenuePeriodFilter ? revenuePeriodFilter.value : 'monthly';
        const params = new URLSearchParams({ action: 'sales', period: period });

        fetch('api_dashboard_stats.php?' + params.toString())
            .then(r => r.json())
            .then(data => renderSalesReport(data))
            .catch(e => console.error('Sales error:', e));
    }

    if (document.getElementById('sales')) {
        // Load initially
        loadSalesData();
        
        // Reload when filter changes
        if (revenuePeriodFilter) {
            revenuePeriodFilter.addEventListener('change', loadSalesData);
        }
    }

    function renderSalesReport(data) {
        latestSalesData = data;
        const theme = getChartTheme();

        // Update Stat Cards
        const el = (id, val) => {
            const e = document.getElementById(id);
            if (e) e.textContent = val;
        };
        el('stat-revenue-total', '₱' + (data.total_revenue || '0.00'));
        el('stat-revenue-transactions', data.total_transactions || '0');
        el('stat-revenue-avg-trans', '₱' + (data.avg_transaction || '0.00'));

        // Top tenants table
        const topTbody = document.querySelector('#top-tenants-table tbody');
        if (topTbody) {
            if (!data.top_tenants || data.top_tenants.length === 0) {
                topTbody.innerHTML = '<tr><td colspan="5" class="text-muted" style="text-align:center; padding:2rem;">No sales data found.</td></tr>';
            } else {
                topTbody.innerHTML = data.top_tenants.map((t, i) => `
                    <tr>
                        <td>${i + 1}</td>
                        <td>${esc(t.tenant_name)}</td>
                        <td>${esc(t.plan_tier)}</td>
                            <td>₱${parseFloat(t.total_sales).toLocaleString('en-PH', {minimumFractionDigits:2})}</td>
                        <td>${t.transaction_count}</td>
                    </tr>
                `).join('');
            }
        }

        // Revenue chart
        const revenueCtx = document.getElementById('chart-revenue');
        if (revenueCtx) {
            if (chartRevenue) chartRevenue.destroy();
            chartRevenue = new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: (data.revenue_chart || []).map(d => d.period_label),
                    datasets: [{
                        label: 'Revenue',
                        data: (data.revenue_chart || []).map(d => parseFloat(d.total)),
                        borderColor: theme.primary,
                        backgroundColor: theme.primaryLight,
                        borderWidth: 3,
                        showLine: true,
                        spanGaps: true,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        pointBackgroundColor: theme.primary
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { color: theme.grid }, ticks: { color: theme.ticks, font: { family: 'Outfit' } } },
                        y: { beginAtZero: true, grid: { color: theme.grid }, ticks: { color: theme.ticks, font: { family: 'Outfit' } } }
                    }
                }
            });
        }

        // Transaction history table
        const txTbody = document.querySelector('#sales-transactions-table tbody');
        if (txTbody) {
            if (!data.transactions || data.transactions.length === 0) {
                txTbody.innerHTML = '<tr><td colspan="6" class="text-muted" style="text-align:center; padding:2rem;">No transactions found.</td></tr>';
            } else {
                txTbody.innerHTML = data.transactions.map(tx => `
                    <tr>
                        <td><code>${esc(tx.payment_reference)}</code></td>
                        <td>${esc(tx.tenant_name || '—')}</td>
                        <td>₱${parseFloat(tx.payment_amount).toLocaleString('en-PH', {minimumFractionDigits:2})}</td>
                        <td>${esc(tx.payment_method)}</td>
                        <td><span class="badge">${esc(tx.payment_status)}</span></td>
                        <td>${formatDate(tx.payment_date)}</td>
                    </tr>
                `).join('');
            }
        }
    }

    // ============================================================
    // AUDIT LOGS: Load via AJAX
    const auditActionFilter = document.getElementById('audit-action-filter');
    const auditTenantFilter = document.getElementById('audit-tenant-filter');
    const auditDateFromInput = document.getElementById('audit-date-from');
    const auditDateToInput = document.getElementById('audit-date-to');

    function loadAuditLogs() {
        if (!document.getElementById('audit-logs-table')) {
            return;
        }

        const params = new URLSearchParams({ action: 'audit_logs' });
        if (auditActionFilter && auditActionFilter.value) params.set('action_type', auditActionFilter.value);
        if (auditTenantFilter && auditTenantFilter.value) params.set('tenant_id', auditTenantFilter.value);
        if (auditDateFromInput && auditDateFromInput.value) params.set('date_from', auditDateFromInput.value);
        if (auditDateToInput && auditDateToInput.value) params.set('date_to', auditDateToInput.value);

        fetch('api_dashboard_stats.php?' + params.toString())
            .then((response) => response.json())
            .then((data) => renderAuditLogs(data.logs || []))
            .catch((error) => console.error('Audit logs error:', error));
    }

    [auditActionFilter, auditTenantFilter, auditDateFromInput, auditDateToInput].forEach((input) => {
        if (input) {
            input.addEventListener('change', loadAuditLogs);
        }
    });

    const receiptFilterForm = document.getElementById('receipt-filter-form');
    if (receiptFilterForm) {
        const receiptFilterInputs = receiptFilterForm.querySelectorAll('select, input[name="statement_year"]');
        receiptFilterInputs.forEach((input) => {
            input.addEventListener('change', () => {
                receiptFilterForm.requestSubmit();
            });
        });
    }

    function renderAuditLogs(logs) {
        const tbody = document.querySelector('#audit-logs-table tbody');
        if (!tbody) return;

        if (logs.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-muted" style="text-align:center; padding:2rem;">No audit logs match the selected filters.</td></tr>';
            return;
        }

        tbody.innerHTML = logs.map(log => `
            <tr>
                <td><small>${formatDateTime(log.created_at)}</small></td>
                <td><span style="font-family: monospace;">${esc(log.username || '—')}</span></td>
                <td>${esc(log.user_email || 'System')}</td>
                <td>${esc(log.tenant_name || 'Platform')}</td>
                <td><span class="badge badge-blue">${esc(log.action_type)}</span></td>
                <td>${esc(log.entity_type || '—')}</td>
                <td>
                    <button
                        type="button"
                        class="btn btn-outline btn-sm audit-detail-btn"
                        data-created-at="${esc(log.created_at || '')}"
                        data-username="${esc(log.username || '—')}"
                        data-user-email="${esc(log.user_email || 'System')}"
                        data-tenant-name="${esc(log.tenant_name || 'Platform')}"
                        data-action-type="${esc(log.action_type || '—')}"
                        data-entity-type="${esc(log.entity_type || '—')}"
                        data-description="${esc(log.description || '—')}"
                    >
                        <span class="material-symbols-rounded" style="font-size:16px;">visibility</span> View
                    </button>
                </td>
            </tr>
        `).join('');
    }

    // ============================================================
    // SETTINGS: Sub-tab navigation
    // ============================================================
    const settingsTabs = document.querySelectorAll('[data-settings-target]');
    const settingsPanels = document.querySelectorAll('.settings-panel');

    settingsTabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const target = tab.getAttribute('data-settings-target');

            settingsTabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');

            settingsPanels.forEach(p => p.classList.remove('active'));
            const panel = document.getElementById(target);
            if (panel) panel.classList.add('active');
        });
    });

    // ============================================================
    // HELPERS
    // ============================================================

    function bindProvisionButtons() {
        const btns = document.querySelectorAll('.btn-provision-from-demo');
        btns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const tenantName = btn.getAttribute('data-tenant-name');
                const companyEmail = btn.getAttribute('data-company-email');
                let planTier = btn.getAttribute('data-plan-tier');
                const requestType = btn.getAttribute('data-request-type') || 'tenant_application';
                if (!planTier || planTier === '') planTier = 'Starter';
                const firstName = btn.getAttribute('data-first-name') || '';
                const lastName = btn.getAttribute('data-last-name') || '';
                const mi = btn.getAttribute('data-mi') || '';
                const suffix = btn.getAttribute('data-suffix') || '';
                const companyAddress = btn.getAttribute('data-company-address') || '';

                if (modalForm) {
                    // Make fields read-only for demo provision
                    Array.from(modalForm.elements).forEach(el => {
                        if (el.tagName !== 'BUTTON' && el.type !== 'hidden') {
                            el.setAttribute('readonly', 'true');
                            if (el.tagName === 'SELECT' || el.type === 'checkbox') {
                                el.style.pointerEvents = 'none';
                                el.style.opacity = '0.7';
                            }
                            el.style.backgroundColor = 'var(--bg-tertiary)';
                            el.style.cursor = 'default';
                        }
                    });

                    const nameInput = modalForm.querySelector('input[name="tenant_name"]');
                    const emailInput = modalForm.querySelector('input[name="admin_email"]');
                    const slugInput = modalForm.querySelector('input[name="custom_slug"]');
                    const requestTypeInput = modalForm.querySelector('input[name="request_type"]');
                    const planSelect = modalForm.querySelector('select[name="plan_tier"]');
                    const firstNameInput = modalForm.querySelector('input[name="first_name"]');
                    const lastNameInput = modalForm.querySelector('input[name="last_name"]');
                    const miInput = modalForm.querySelector('input[name="mi"]');
                    const suffixInput = modalForm.querySelector('input[name="suffix"]');
                    const companyAddressInput = modalForm.querySelector('input[name="company_address"]');

                    if (nameInput) nameInput.value = tenantName;
                    if (emailInput) emailInput.value = companyEmail;
                    if (requestTypeInput) requestTypeInput.value = requestType;
                    if (slugInput) {
                        slugInput.value = tenantName.toLowerCase().trim().replace(/[^a-z0-9-]+/g, '-').replace(/^-+|-+$/g, '');
                        delete slugInput.dataset.manuallyEdited;
                    }
                    if (firstNameInput) firstNameInput.value = firstName;
                    if (lastNameInput) lastNameInput.value = lastName;
                    if (miInput) miInput.value = mi;
                    if (suffixInput) suffixInput.value = suffix;
                    if (companyAddressInput) companyAddressInput.value = companyAddress;
                    if (planSelect) {
                        for (let i = 0; i < planSelect.options.length; i++) {
                            if (planSelect.options[i].value === planTier) {
                                planSelect.selectedIndex = i;
                                break;
                            }
                        }
                    }
                }

                if (modalBackdrop) modalBackdrop.classList.add('show');
            });
        });
    }



    function esc(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function formatDate(dateStr) {
        if (!dateStr) return '—';
        const d = new Date(dateStr);
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }

    function formatCurrency(amount) {
        const value = Number(amount || 0);
        return '₱' + value.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function formatDateTime(dateStr) {
        if (!dateStr) return '—';
        const d = new Date(dateStr);
        return d.toLocaleString('en-US', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }


    // ============================================================
    // BACKUP: Stats, History, Create
    // ============================================================
    function formatBytes(bytes) {
        if (!bytes || bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    function loadBackupInfo() {
        fetch('api_backup.php?action=info')
            .then(r => r.json())
            .then(data => {
                const el = (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; };
                el('backup-stat-total', data.total_backups || 0);
                el('backup-stat-last', data.last_backup ? formatDateTime(data.last_backup) : 'Never');
                el('backup-stat-dbsize', formatBytes(data.db_size_bytes));
                el('backup-stat-tables', data.table_count || '—');
            })
            .catch(e => console.error('Backup info error:', e));
    }

    function loadBackupHistory() {
        fetch('api_backup.php?action=history')
            .then(r => r.json())
            .then(data => {
                const tbody = document.querySelector('#backup-history-table tbody');
                if (!tbody) return;
                if (!data.logs || data.logs.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-muted" style="text-align:center; padding:2rem;"><span class="material-symbols-rounded" style="font-size:40px; display:block; margin-bottom:.5rem;">cloud_off</span>No backups have been created yet.</td></tr>';
                    return;
                }
                tbody.innerHTML = data.logs.map(log => {
                    const statusBadge = log.status === 'Success'
                        ? '<span class="badge badge-green">Success</span>'
                        : '<span class="badge badge-red" title="' + esc(log.error_message || '') + '">Failed</span>';
                    const typeLabel = log.backup_type === 'tenant'
                        ? '<span class="badge badge-blue">Tenant</span>'
                        : '<span class="badge badge-purple">Full</span>';
                    return `<tr>
                        <td class="text-muted">${formatDateTime(log.created_at)}</td>
                        <td>${typeLabel}</td>
                        <td style="font-family:monospace; font-size:.85rem;">${esc(log.file_name)}</td>
                        <td>${formatBytes(log.file_size_bytes)}</td>
                        <td>${statusBadge}</td>
                        <td>${esc(log.initiated_by_name || 'System')}</td>
                    </tr>`;
                }).join('');
            })
            .catch(e => console.error('Backup history error:', e));
    }

    function triggerBackupDownload(url, progressText) {
        const progress = document.getElementById('backup-progress');
        const pText = document.getElementById('backup-progress-text');
        if (progress) progress.style.display = 'block';
        if (pText) pText.textContent = progressText || 'Generating backup...';

        // Use a hidden iframe to trigger the download without leaving the page
        const iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        iframe.src = url;
        document.body.appendChild(iframe);

        // Poll to check if download started, then clean up
        setTimeout(() => {
            if (progress) progress.style.display = 'none';
            loadBackupInfo();
            loadBackupHistory();
            setTimeout(() => iframe.remove(), 5000);
        }, 3000);
    }

    // Full backup button
    const btnBackupFull = document.getElementById('btn-backup-full');
    if (btnBackupFull) {
        btnBackupFull.addEventListener('click', () => {
            triggerBackupDownload('api_backup.php?action=create', 'Generating full database backup...');
        });
    }

    // Tenant export
    const backupTenantSelect = document.getElementById('backup-tenant-select');
    const btnBackupTenant = document.getElementById('btn-backup-tenant');
    if (backupTenantSelect && btnBackupTenant) {
        backupTenantSelect.addEventListener('change', () => {
            btnBackupTenant.disabled = !backupTenantSelect.value;
        });
        btnBackupTenant.addEventListener('click', () => {
            const tid = backupTenantSelect.value;
            if (!tid) return;
            const tenantName = backupTenantSelect.options[backupTenantSelect.selectedIndex].text;
            triggerBackupDownload(
                'api_backup.php?action=create&tenant_id=' + encodeURIComponent(tid),
                'Exporting data for ' + tenantName + '...'
            );
        });
    }

    // Load backup data on page load
    if (document.getElementById('backup')) {
        loadBackupInfo();
        loadBackupHistory();
    }

});

