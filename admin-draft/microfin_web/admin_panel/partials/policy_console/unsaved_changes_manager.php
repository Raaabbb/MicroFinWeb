<!-- Unsaved Changes Prompt Modal -->
<div id="unsaved-changes-backdrop" class="unsaved-changes-backdrop"></div>
<div id="unsaved-changes-modal" class="unsaved-changes-modal">
    <div class="unsaved-changes-content">
        <h3 style="margin-top: 0; margin-bottom: 8px; color: var(--text-main); font-size: 18px; display: flex; align-items: center; gap: 10px;">
            <svg style="width: 22px; height: 22px; fill: #f59e0b;" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
            Unsaved Changes
        </h3>
        <p style="color: var(--text-muted); font-size: 14px; line-height: 1.5; margin-bottom: 24px; padding-left: 32px;">
            You have unsaved edits. If you leave now, they will be lost.
        </p>
        <div class="unsaved-changes-actions">
            <button type="button" id="unsaved-cancel-btn" class="btn btn-secondary" style="border-radius: 20px; font-size: 13px; font-weight: 500;">Cancel</button>
            <button type="button" id="unsaved-discard-btn" class="btn btn-danger" style="background-color: var(--danger-color, #dc2626); color: white; border: none; border-radius: 20px; font-size: 13px; font-weight: 600;">Discard Changes</button>
            <button type="button" id="unsaved-save-btn" class="btn btn-primary" style="background-color: var(--primary-color, #3b82f6); color: white; border: none; border-radius: 20px; font-size: 13px; font-weight: 600; padding: 10px 20px; box-shadow: 0 4px 10px rgba(59, 130, 246, 0.3);">Save</button>
        </div>
    </div>
</div>

<style>
.unsaved-changes-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(0, 0, 0, 0.6); /* Slightly darker */
    backdrop-filter: grayscale(100%) blur(4px); /* Stronger blur and grayscale */
    -webkit-backdrop-filter: grayscale(100%) blur(4px);
    z-index: 2147483646; /* Maximum z-index possible to cover EVERYTHING including sidebar and navbar */
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s ease, visibility 0.3s ease;
}
.unsaved-changes-backdrop.is-active {
    opacity: 1;
    visibility: visible;
}
.unsaved-changes-modal {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -45%) scale(0.95);
    background: var(--bg-card, #ffffff);
    padding: 24px;
    border-radius: 16px;
    box-shadow: 0 20px 50px rgba(0,0,0,0.3), 0 0 0 1px rgba(0,0,0,0.05);
    z-index: 2147483647; /* Modal on top of everything */
    width: 90%;
    max-width: 480px;
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transition: opacity 0.2s ease, transform 0.2s cubic-bezier(0.4, 0, 0.2, 1), visibility 0.2s;
}
.unsaved-changes-modal.is-active {
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
    transform: translate(-50%, -50%) scale(1);
}
.unsaved-changes-actions {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 12px;
    margin-top: 10px;
}
.btn-secondary {
    background: transparent;
    border: 1px solid var(--border-color);
    color: var(--text-main);
    padding: 10px 16px;
    cursor: pointer;
}
.btn-secondary:hover {
    background: var(--bg-body);
}
</style>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const forms = Array.from(document.querySelectorAll(".credit-policy-tab-panel form"));
    const storedForms = new Map();
    let pendingNavTargetUrl = null;
    let initialStateCaptured = false;

    if (typeof window._isPolicyFormDirty === "undefined") {
        window._isPolicyFormDirty = false;
    }
    if (typeof window._policyConsoleSubmitting === "undefined") {
        window._policyConsoleSubmitting = false;
    }
    if (typeof window._policyConsoleBypassBeforeUnload === "undefined") {
        window._policyConsoleBypassBeforeUnload = false;
    }

    function getFormKey(formElement, index = 0) {
        if (!formElement.dataset.policyDirtyKey) {
            formElement.dataset.policyDirtyKey = formElement.id || `policy-console-form-${index}`;
        }
        return formElement.dataset.policyDirtyKey;
    }

    function serializeForm(formElement) {
        const formData = new FormData(formElement);
        const dataObj = {};

        for (const [key, value] of formData.entries()) {
            if (!dataObj[key]) {
                dataObj[key] = [];
            }
            dataObj[key].push(typeof value === "string" ? value.trim() : value);
        }

        const ordered = {};
        Object.keys(dataObj).sort().forEach((key) => {
            ordered[key] = dataObj[key].slice().sort();
        });

        return JSON.stringify(ordered);
    }

    function updateSaveButtonVisuals(isDirty) {
        const saveBtn = document.getElementById("global-save-policy-btn");
        if (!saveBtn) {
            return;
        }

        if (isDirty) {
            saveBtn.style.transform = "scale(1.05)";
            saveBtn.style.boxShadow = "0 6px 16px rgba(59, 130, 246, 0.5)";
            setTimeout(() => {
                saveBtn.style.transform = "";
            }, 300);
            return;
        }

        saveBtn.style.boxShadow = "";
    }

    function captureFormState(formElement) {
        storedForms.set(getFormKey(formElement), serializeForm(formElement));
    }

    function captureAllFormStates() {
        forms.forEach((form, index) => {
            getFormKey(form, index);
            captureFormState(form);
        });
        initialStateCaptured = true;
        recomputeDirtyState();
    }

    function recomputeDirtyState() {
        if (!initialStateCaptured) {
            return window._isPolicyFormDirty;
        }

        const isDirty = forms.some((form, index) => {
            const formKey = getFormKey(form, index);
            const originalState = storedForms.get(formKey);
            if (typeof originalState === "undefined") {
                return false;
            }
            return originalState !== serializeForm(form);
        });

        window._isPolicyFormDirty = isDirty;
        updateSaveButtonVisuals(isDirty);
        return isDirty;
    }

    function restoreAllForms() {
        forms.forEach((form) => {
            if (typeof form._policyConsoleRestoreOriginal === "function") {
                form._policyConsoleRestoreOriginal();
            } else {
                form.reset();
            }

            if (typeof form._policyConsoleRefreshUi === "function") {
                form._policyConsoleRefreshUi();
            }
        });

        window._policyConsoleSubmitting = false;
        recomputeDirtyState();
    }

    window.policyConsoleUnsavedManager = {
        captureAllStates: captureAllFormStates,
        captureFormState,
        clearDirty() {
            window._isPolicyFormDirty = false;
            updateSaveButtonVisuals(false);
            return false;
        },
        isDirty() {
            return Boolean(recomputeDirtyState());
        },
        markSubmitting() {
            window._policyConsoleSubmitting = true;
            window._policyConsoleBypassBeforeUnload = false;
            window._isPolicyFormDirty = false;
            updateSaveButtonVisuals(false);
        },
        allowConfirmedNavigation() {
            window._policyConsoleSubmitting = false;
            window._policyConsoleBypassBeforeUnload = true;
            window._isPolicyFormDirty = false;
            updateSaveButtonVisuals(false);
        },
        recompute: recomputeDirtyState,
        resetSubmitting() {
            window._policyConsoleSubmitting = false;
            window._policyConsoleBypassBeforeUnload = false;
        },
        restoreAllForms,
    };

    forms.forEach((form, index) => {
        getFormKey(form, index);

        const onFormMutation = () => {
            window._policyConsoleSubmitting = false;
            window._policyConsoleBypassBeforeUnload = false;
            recomputeDirtyState();
        };

        form.addEventListener("input", onFormMutation);
        form.addEventListener("change", onFormMutation);
    });

    const modal = document.getElementById("unsaved-changes-modal");
    const backdrop = document.getElementById("unsaved-changes-backdrop");

    if (backdrop && modal) {
        document.body.appendChild(backdrop);
        document.body.appendChild(modal);
    }

    function hideModal() {
        if (modal) {
            modal.classList.remove("is-active");
        }
        if (backdrop) {
            backdrop.classList.remove("is-active");
        }
        pendingNavTargetUrl = null;
    }

    const cancelBtn = document.getElementById("unsaved-cancel-btn");
    if (cancelBtn) {
        cancelBtn.addEventListener("click", hideModal);
    }

    const discardBtn = document.getElementById("unsaved-discard-btn");
    if (discardBtn) {
        discardBtn.addEventListener("click", () => {
            window.policyConsoleUnsavedManager.restoreAllForms();
            hideModal();

            if (pendingNavTargetUrl) {
                window.policyConsoleUnsavedManager.allowConfirmedNavigation();
                window.location.href = pendingNavTargetUrl;
                return;
            }

            window.policyConsoleUnsavedManager.allowConfirmedNavigation();
            window.location.reload();
        });
    }

    const saveBtn = document.getElementById("unsaved-save-btn");
    if (saveBtn) {
        saveBtn.addEventListener("click", () => {
            hideModal();

            const activeForm = document.querySelector(".credit-policy-tab-panel:not([hidden]) form");
            if (!activeForm) {
                return;
            }

            window.policyConsoleUnsavedManager.markSubmitting();

            const globalSaveBtn = document.getElementById("global-save-policy-btn");
            if (globalSaveBtn) {
                globalSaveBtn.innerHTML = "<i class=\"fas fa-spinner fa-spin\" style=\"margin-right: 6px;\"></i><span>Saving...</span>";
                globalSaveBtn.style.pointerEvents = "none";
            }

            activeForm.submit();
        });
    }

    setTimeout(captureAllFormStates, 0);

    window.addEventListener("beforeunload", (e) => {
        if (window._policyConsoleSubmitting || window._policyConsoleBypassBeforeUnload) {
            return;
        }

        if (recomputeDirtyState() && !pendingNavTargetUrl) {
            e.preventDefault();
            e.returnValue = "";
            return "";
        }
    });
});
</script>
