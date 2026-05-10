<?php
if (!has_permission('VIEW_USERS') && !has_permission('CREATE_USERS')) {
    echo "<div style='padding: 32px;text-align:center;'><h2 style='color:#f87171;'>Access Denied</h2><p>You do not have permission to view the team directory.</p></div>";
    return;
}

require_once __DIR__ . '/../functions/db_team.php';

$teamData = get_tenant_staff($pdo, $_SESSION['tenant_id']);
$all_staff = $teamData['data'];
$available_roles = get_tenant_roles($pdo, $_SESSION['tenant_id']);

// Ensure status badge function exists
if (!function_exists('statusBadgePHP')) {
    function statusBadgePHP($val) {
        $val = trim((string)$val);
        $st = strtolower($val);
        $bg = ''; $fg = '';
        if (in_array($st, ['active', 'verified'])) { $bg = '#dcfce7'; $fg = '#166534'; }
        elseif (in_array($st, ['suspended', 'locked', 'inactive'])) { $bg = '#fee2e2'; $fg = '#991b1b'; }
        else { $bg = '#f1f5f9'; $fg = '#475569'; }
        return "<span style='display:inline-flex;align-items:center;padding:3px 10px;border-radius:99px;font-size:.7rem;font-weight:600;background:{$bg};color:{$fg};'>" 
               . htmlspecialchars($val ?: 'Unknown') . "</span>";
    }
}
?>

<!-- ── TEAM DIRECTORY TAB ── -->
<div class="page-header">
    <div class="page-icon" style="background:rgba(99,102,241,.1);color:#6366f1;">
        <span class="material-symbols-rounded ms" style="font-size:22px;">badge</span>
    </div>
    <div>
        <h1>Team Directory</h1>
        <p>View and manage employee accounts and administrative access.</p>
    </div>
    <div class="page-header-actions">
        <?php if (has_permission('CREATE_USERS')): ?>
            <button class="btn btn-primary" onclick="openModal('inviteStaffModal')">
                <span class="material-symbols-rounded ms">person_add</span> Invite Staff
            </button>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="margin-top: 24px;">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Staff Name</th>
                    <th>Email Address</th>
                    <th>Role</th>
                    <th>Status</th>
                    <?php if (has_permission('CREATE_USERS')): ?>
                        <th style="text-align: center;">Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($all_staff)): ?>
                    <tr class="empty-row"><td colspan="5">No staff members found.</td></tr>
                <?php else: ?>
                    <?php foreach ($all_staff as $staff): ?>
                        <tr>
                            <td class="td-bold">
                                <?php echo htmlspecialchars($staff['full_name'] ?? '—'); ?>
                            </td>
                            <td class="td-muted">
                                <?php echo htmlspecialchars($staff['email'] ?? '—'); ?>
                            </td>
                            <td>
                                <span style="background: rgba(59, 130, 246, 0.1); padding: 4px 10px; border-radius: 6px; color: #3b82f6; font-size: 0.75rem; font-weight: 600;">
                                    <?php echo htmlspecialchars($staff['role_name'] ?? 'No Role Assigned'); ?>
                                </span>
                            </td>
                            <td>
                                <?php echo statusBadgePHP($staff['status']); ?>
                            </td>
                            <?php if (has_permission('CREATE_USERS')): ?>
                                <td style="text-align: center;">
                                    <?php if ((int)$staff['user_id'] === (int)$_SESSION['user_id']): ?>
                                        <span style="font-size: 0.8rem; color: var(--muted); font-weight: 600;">You</span>
                                    <?php elseif ($staff['user_type'] === 'Admin' || $staff['role_name'] === 'Admin'): ?>
                                        <span style="font-size: 0.8rem; color: var(--muted);">Restricted</span>
                                    <?php else: ?>
                                        <?php if (strcasecmp($staff['status'], 'Suspended') === 0 || strcasecmp($staff['status'], 'Locked') === 0 || strcasecmp($staff['status'], 'Inactive') === 0): ?>
                                            <button class="btn btn-sm btn-outline" style="color: #10b981; border-color: #10b981;" onclick="activateStaff(<?php echo (int)$staff['user_id']; ?>, '<?php echo (int)($staff['role_id'] ?? 0); ?>')">Activate</button>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-outline" onclick="openManageStaffModal(<?php echo (int)$staff['user_id']; ?>, '<?php echo htmlspecialchars($staff['full_name'] ?? ''); ?>', '<?php echo (int)($staff['role_id'] ?? 0); ?>', '<?php echo htmlspecialchars($staff['status'] ?? ''); ?>')">Manage</button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── INVITE STAFF MODAL ── -->
<?php if (has_permission('CREATE_USERS')): ?>
<div class="modal-backdrop top" id="inviteStaffModal">
    <div class="modal" style="width: 100%; max-width: 500px; background:var(--bg-card); border-radius:12px; box-shadow:0 10px 25px rgba(0,0,0,0.1);">
        <div class="modal-header" style="display:flex; justify-content:space-between; align-items:center; padding:20px 24px; border-bottom:1px solid var(--border);">
            <h3 style="margin:0; font-size:1.2rem;">Invite New Staff Member</h3>
            <span class="material-symbols-rounded ms" style="cursor:pointer; color:var(--muted);" onclick="closeModal('inviteStaffModal')">close</span>
        </div>
        <div class="modal-body" style="padding: 24px;">
            <p style="font-size: 0.9rem; color: var(--muted); margin-top: 0; margin-bottom: 24px;">
                Enter their details and assign them a role. An automated email will be sent containing their temporary password.
            </p>
            <form id="inviteStaffForm" style="display: flex; flex-direction: column; gap: 16px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div>
                        <label style="display:block; font-size:0.8rem; font-weight:600; color:var(--muted); margin-bottom:6px;">First Name *</label>
                        <input type="text" id="invite_first_name" required style="width:100%; padding:12px; border:1px solid var(--border); border-radius:6px; box-sizing:border-box; outline:none; font-family:inherit;">
                    </div>
                    <div>
                        <label style="display:block; font-size:0.8rem; font-weight:600; color:var(--muted); margin-bottom:6px;">Last Name *</label>
                        <input type="text" id="invite_last_name" required style="width:100%; padding:12px; border:1px solid var(--border); border-radius:6px; box-sizing:border-box; outline:none; font-family:inherit;">
                    </div>
                </div>
                
                <div>
                    <label style="display:block; font-size:0.8rem; font-weight:600; color:var(--muted); margin-bottom:6px;">Email Address *</label>
                    <input type="email" id="invite_email" required placeholder="Will be used for login" style="width:100%; padding:12px; border:1px solid var(--border); border-radius:6px; box-sizing:border-box; outline:none; font-family:inherit;">
                </div>
                
                <div>
                    <label style="display:block; font-size:0.8rem; font-weight:600; color:var(--muted); margin-bottom:6px;">Assign Role *</label>
                    <select id="invite_role_id" required style="width:100%; padding:12px; border:1px solid var(--border); border-radius:6px; box-sizing:border-box; background: var(--bg-body); outline:none; font-family:inherit;">
                        <option value="">Select a role...</option>
                        <?php foreach ($available_roles as $r): ?>
                            <?php if (strcasecmp($r['role_name'], 'Admin') === 0) continue; ?>
                            <option value="<?php echo (int)$r['role_id']; ?>"><?php echo htmlspecialchars($r['role_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 16px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('inviteStaffModal')" style="padding: 10px 20px;">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="btnSubmitInvite" style="padding: 10px 24px;">Send Invitation</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── MANAGE STAFF MODAL ── -->
<?php if (has_permission('CREATE_USERS')): ?>
<div class="modal-backdrop top" id="manageStaffModal">
    <div class="modal" style="width: 100%; max-width: 400px; background:var(--bg-card); border-radius:12px; box-shadow:0 10px 25px rgba(0,0,0,0.1);">
        <div class="modal-header" style="display:flex; justify-content:space-between; align-items:center; padding:20px 24px; border-bottom:1px solid var(--border);">
            <h3 style="margin:0; font-size:1.2rem;">Manage Staff</h3>
            <span class="material-symbols-rounded ms" style="cursor:pointer; color:var(--muted);" onclick="closeModal('manageStaffModal')">close</span>
        </div>
        <div class="modal-body" style="padding: 24px;">
            <p id="manageStaffNameDisplay" style="font-weight: 600; margin-top:0; margin-bottom: 20px; font-size: 1rem; color: var(--text-color);"></p>
            <form id="manageStaffForm" style="display: flex; flex-direction: column; gap: 16px;">
                <input type="hidden" id="manage_user_id">
                
                <div>
                    <label style="display:block; font-size:0.8rem; font-weight:600; color:var(--muted); margin-bottom:6px;">Update Role</label>
                    <select id="manage_role_id" required style="width:100%; padding:12px; border:1px solid var(--border); border-radius:6px; box-sizing:border-box; background: var(--bg-body); outline:none; font-family:inherit;">
                        <option value="">Select a role...</option>
                        <?php foreach ($available_roles as $r): ?>
                            <?php if (strcasecmp($r['role_name'], 'Admin') === 0) continue; ?>
                            <option value="<?php echo (int)$r['role_id']; ?>"><?php echo htmlspecialchars($r['role_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label style="display:block; font-size:0.8rem; font-weight:600; color:var(--muted); margin-bottom:6px;">Update Status</label>
                    <select id="manage_status" required style="width:100%; padding:12px; border:1px solid var(--border); border-radius:6px; box-sizing:border-box; background: var(--bg-body); outline:none; font-family:inherit;">
                        <option value="Active">Active</option>
                        <option value="Suspended">Suspend</option>
                    </select>
                </div>
                
                <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 16px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('manageStaffModal')" style="padding: 10px 20px;">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="btnSubmitManage" style="padding: 10px 24px;">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
    function openManageStaffModal(userId, fullName, roleId, status) {
        document.getElementById('manage_user_id').value = userId;
        document.getElementById('manageStaffNameDisplay').textContent = "Editing: " + fullName;
        document.getElementById('manage_role_id').value = roleId;
        
        let st = status || 'Active';
        if (st === 'Locked' || st === 'Inactive') st = 'Suspended';
        if (!['Active','Suspended'].includes(st)) {
            st = 'Suspended';
        }
        document.getElementById('manage_status').value = st;
        
        openModal('manageStaffModal');
    }

    async function activateStaff(userId, roleId) {
        if (!await showConfirmPopup('Are you sure you want to reactivate this staff member account?', { title: 'Activate Staff' })) {
            return;
        }

        try {
            const res = await fetch('../../../microfin_backend/api/api_team_manage.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    user_id: userId,
                    role_id: roleId,
                    status: 'Active'
                })
            }).then(r => r.json());

            if (res.status === 'success') {
                await showAlertPopup(res.message, { title: 'Success', variant: 'success' });
                window.location.reload();
            } else {
                await showAlertPopup(res.message || 'Failed to activate staff.', { title: 'Error', variant: 'danger' });
            }
        } catch (err) {
            console.error(err);
            await showAlertPopup('A server error occurred. Please try again.', { title: 'Error', variant: 'danger' });
        }
    }

    const inviteForm = document.getElementById('inviteStaffForm');
    if (inviteForm) {
        inviteForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const btn = document.getElementById('btnSubmitInvite');
            const ogText = btn.textContent;
            btn.disabled = true;
            btn.innerHTML = '<span class="material-symbols-rounded ms" style="font-size:16px; animation:spin 1s linear infinite;">sync</span> Sending...';

            try {
                const res = await fetch('../../../microfin_backend/api/api_team_invite.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        first_name: document.getElementById('invite_first_name').value.trim(),
                        last_name: document.getElementById('invite_last_name').value.trim(),
                        email: document.getElementById('invite_email').value.trim(),
                        role_id: document.getElementById('invite_role_id').value
                    })
                }).then(r => r.json());

                if (res.status === 'success') {
                    await showAlertPopup(res.message, { title: 'Success', variant: 'success' });
                    window.location.reload();
                } else {
                    await showAlertPopup(res.message || 'Failed to invite staff.', { title: 'Error', variant: 'danger' });
                }
            } catch (err) {
                console.error(err);
                await showAlertPopup('A server error occurred. Please try again.', { title: 'Error', variant: 'danger' });
            } finally {
                btn.disabled = false;
                btn.textContent = ogText;
            }
        });
    }

    const manageForm = document.getElementById('manageStaffForm');
    if (manageForm) {
        manageForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('btnSubmitManage');
            const ogText = btn.textContent;
            btn.disabled = true;
            btn.innerHTML = '<span class="material-symbols-rounded ms" style="font-size:16px; animation:spin 1s linear infinite;">sync</span> Saving...';

            try {
                const res = await fetch('../../../microfin_backend/api/api_team_manage.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        user_id: document.getElementById('manage_user_id').value,
                        role_id: document.getElementById('manage_role_id').value,
                        status: document.getElementById('manage_status').value
                    })
                }).then(r => r.json());

                if (res.status === 'success') {
                    await showAlertPopup(res.message, { title: 'Success', variant: 'success' });
                    window.location.reload();
                } else {
                    await showAlertPopup(res.message || 'Failed to update staff.', { title: 'Error', variant: 'danger' });
                }
            } catch (err) {
                console.error(err);
                await showAlertPopup('A server error occurred. Please try again.', { title: 'Error', variant: 'danger' });
            } finally {
                btn.disabled = false;
                btn.textContent = ogText;
            }
        });
    }

    if (!document.getElementById('spin-anim')) {
        const style = document.createElement('style');
        style.id = 'spin-anim';
        style.innerHTML = `@keyframes spin { 100% { transform: rotate(360deg); } }`;
        document.head.appendChild(style);
    }
</script>

