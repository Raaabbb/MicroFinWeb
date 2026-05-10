<?php
require_once __DIR__ . '/../functions/db_profile.php';

$user_profile = get_user_profile($pdo, $_SESSION['user_id'], $_SESSION['tenant_id']);

if (!$user_profile) {
    echo "<div style='padding: 32px;text-align:center;'><h2 style='color:#f87171;'>Error Loading Profile</h2><p>Could not fetch your profile data.</p></div>";
    return;
}
?>

<!-- ── PROFILE TAB ── -->
<div class="page-header">
    <div class="page-icon" style="background:rgba(59,130,246,.1);color:#3b82f6;">
        <span class="material-symbols-rounded ms" style="font-size:22px;">manage_accounts</span>
    </div>
    <div>
        <h1>My Profile</h1>
        <p>Review your personal account information and settings.</p>
    </div>
</div>

<div class="card" style="max-width: 600px; margin-top: 24px;">
    <div style="padding: 28px;">
        
        <div style="display:flex; align-items:center; gap: 20px; margin-bottom: 32px; border-bottom: 1px solid var(--border); padding-bottom: 24px;">
            <div class="avatar" style="width: 72px; height: 72px; font-size: 28px; flex-shrink: 0; box-shadow: 0 4px 12px rgba(0,0,0,0.08);">
                <?php echo $initials; // Still inherited gracefully from dashboard.php ?>
            </div>
            <div style="overflow: hidden;">
                <h2 style="font-size: 1.4rem; font-weight: 600; margin-bottom: 6px; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%;">
                    <?php echo htmlspecialchars(trim($user_profile['first_name'] . ' ' . $user_profile['last_name'])) ?: htmlspecialchars($user_profile['username']); ?>
                </h2>
                <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px;">
                    <span style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.2); padding: 4px 12px; border-radius: 20px; color: #3b82f6; font-weight: 600; font-size: 0.75rem; letter-spacing: 0.02em; display: inline-flex; align-items: center; gap: 4px;">
                        <span class="material-symbols-rounded ms" style="font-size: 14px;">verified_user</span>
                        <?php echo htmlspecialchars($user_profile['role_name'] ?? $user_profile['user_type']); ?>
                    </span>
                    <?php if (!empty($user_profile['department'])): ?>
                        <span style="background: var(--bg-body); border: 1px solid var(--border); padding: 4px 12px; border-radius: 20px; color: var(--muted); font-weight: 500; font-size: 0.75rem; letter-spacing: 0.02em; display: inline-flex; align-items: center; gap: 4px;">
                            <span class="material-symbols-rounded ms" style="font-size: 14px;">domain</span>
                            <?php echo htmlspecialchars($user_profile['department']); ?>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($user_profile['position'])): ?>
                        <span style="background: var(--bg-body); border: 1px solid var(--border); padding: 4px 12px; border-radius: 20px; color: var(--muted); font-weight: 500; font-size: 0.75rem; letter-spacing: 0.02em; display: inline-flex; align-items: center; gap: 4px;">
                            <span class="material-symbols-rounded ms" style="font-size: 14px;">work</span>
                            <?php echo htmlspecialchars($user_profile['position']); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <form id="profileForm" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
                <label style="display:block; font-size:0.8rem; font-weight: 600; color:var(--muted); margin-bottom:6px; letter-spacing: 0.05em; text-transform: uppercase;">First Name</label>
                <input type="text" id="prof_first_name" required style="width:100%; padding:12px 14px; border:1px solid var(--border); border-radius:var(--radius-sm); background:var(--bg-body); color:var(--text); font-weight: 500; outline:none; font-family: inherit; font-size: 0.95rem; box-sizing: border-box; transition: 0.2s border;" value="<?php echo htmlspecialchars($user_profile['first_name'] ?? ''); ?>" onfocus="this.style.borderColor='var(--brand)';" onblur="this.style.borderColor='var(--border)';">
            </div>
            <div>
                <label style="display:block; font-size:0.8rem; font-weight: 600; color:var(--muted); margin-bottom:6px; letter-spacing: 0.05em; text-transform: uppercase;">Last Name</label>
                <input type="text" id="prof_last_name" required style="width:100%; padding:12px 14px; border:1px solid var(--border); border-radius:var(--radius-sm); background:var(--bg-body); color:var(--text); font-weight: 500; outline:none; font-family: inherit; font-size: 0.95rem; box-sizing: border-box; transition: 0.2s border;" value="<?php echo htmlspecialchars($user_profile['last_name'] ?? ''); ?>" onfocus="this.style.borderColor='var(--brand)';" onblur="this.style.borderColor='var(--border)';">
            </div>
            
            <div style="grid-column: span 2;">
                <label style="display:block; font-size:0.8rem; font-weight: 600; color:var(--muted); margin-bottom:6px; letter-spacing: 0.05em; text-transform: uppercase;">Username</label>
                <input type="text" id="prof_username" required placeholder="Choose a unique username" style="width:100%; padding:12px 14px; border:1px solid var(--border); border-radius:var(--radius-sm); background:var(--bg-body); color:var(--text); font-weight: 500; outline:none; font-family: inherit; font-size: 0.95rem; box-sizing: border-box; transition: 0.2s border;" value="<?php echo htmlspecialchars($user_profile['username'] ?? ''); ?>" onfocus="this.style.borderColor='var(--brand)';" onblur="this.style.borderColor='var(--border)';">
            </div>
            
            <div style="grid-column: span 2;">
                <label style="display:block; font-size:0.8rem; font-weight: 600; color:var(--muted); margin-bottom:6px; letter-spacing: 0.05em; text-transform: uppercase;">Email Address</label>
                <div style="display: flex; gap: 12px; align-items: center;">
                    <input type="email" id="prof_email" readonly data-current="<?php echo htmlspecialchars($user_profile['email'] ?? ''); ?>" style="flex:1; padding:12px 14px; border:1px solid var(--border); border-radius:var(--radius-sm); background:rgba(0,0,0,0.02); color:var(--muted); font-weight: 500; outline:none; font-family: inherit; font-size: 0.95rem; box-sizing: border-box; cursor: not-allowed;" value="<?php echo htmlspecialchars($user_profile['email'] ?? ''); ?>">
                    <button type="button" class="btn btn-secondary" onclick="openEmailChangeModal()" style="padding: 10px 20px; white-space: nowrap;">Change Email</button>
                </div>
            </div>
            
            <div style="grid-column: span 2;">
                <label style="display:block; font-size:0.8rem; font-weight: 600; color:var(--muted); margin-bottom:6px; letter-spacing: 0.05em; text-transform: uppercase;">Contact Number</label>
                <input type="text" id="prof_contact_number" placeholder="e.g. +63 912 345 6789" style="width:100%; padding:12px 14px; border:1px solid var(--border); border-radius:var(--radius-sm); background:var(--bg-body); color:var(--text); font-weight: 500; outline:none; font-family: inherit; font-size: 0.95rem; box-sizing: border-box; transition: 0.2s border;" value="<?php echo htmlspecialchars($user_profile['phone_number'] ?? ''); ?>" onfocus="this.style.borderColor='var(--brand)';" onblur="this.style.borderColor='var(--border)';">
            </div>
            
            <div style="grid-column: span 2; display: flex; justify-content: space-between; align-items: center; margin-top: 4px;">
                <div>
                    <label style="display:block; font-size:0.8rem; font-weight: 600; color:var(--muted); margin-bottom:2px; letter-spacing: 0.05em; text-transform: uppercase;">Member Since</label>
                    <div style="color:var(--text); font-weight: 500; font-size: 0.9rem;">
                        <?php 
                            $member_date = !empty($user_profile['hire_date']) ? $user_profile['hire_date'] : $user_profile['created_at'];
                            echo date('F j, Y', strtotime($member_date)); 
                        ?>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary" id="btnSaveProfile" style="padding: 10px 24px; min-width: 140px;">
                    Save Details
                </button>
            </div>
        </form>

    </div>
</div>

<!-- EMAIL CHANGE MODAL -->
<div id="emailChangeModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div class="card" style="width: 100%; max-width: 400px; padding: 28px;">
        <h3 style="margin-top:0; font-size:1.3rem; margin-bottom: 24px; color: var(--text);">Change Email Address</h3>
        
        <!-- Step 1: Request Email -->
        <div id="emailStep1">
            <label style="display:block; font-size:0.85rem; font-weight:600; color:var(--muted); margin-bottom:8px;">New Email Address</label>
            <input type="email" id="new_email_input" placeholder="e.g. john@example.com" style="width:100%; padding:12px; border:2px solid var(--border); border-radius:6px; outline:none; transition: border-color 0.2s; font-family: inherit; font-size:0.95rem; box-sizing: border-box;">
            <p id="email_msg" style="font-size:0.8rem; margin-top:8px; min-height: 18px; font-weight:500;"></p>
            
            <div style="display:flex; justify-content:flex-end; gap:12px; margin-top: 24px;">
                <button type="button" class="btn btn-secondary" onclick="closeEmailModal()" style="padding: 10px 20px;">Cancel</button>
                <button type="button" id="btnSendOtp" class="btn btn-primary" style="padding: 10px 20px;" disabled>Send OTP</button>
            </div>
        </div>

        <!-- Step 2: Verify OTP -->
        <div id="emailStep2" style="display:none;">
            <p style="font-size:0.9rem; color:var(--muted); margin-top:0; margin-bottom: 24px;">
                We've sent a 6-digit verification code to <strong id="sentToEmail" style="color:var(--text);"></strong>.
            </p>
            <label style="display:block; font-size:0.85rem; font-weight:600; color:var(--muted); margin-bottom:8px;">Enter Verification Code</label>
            <input type="text" id="otp_input" maxlength="6" style="width:100%; padding:12px; border:1px solid var(--border); border-radius:6px; outline:none; font-family: inherit; font-size:1.2rem; letter-spacing:4px; text-align:center; box-sizing: border-box;">
            
            <div style="display:flex; justify-content:flex-end; gap:12px; margin-top: 24px;">
                <button type="button" class="btn btn-secondary" onclick="closeEmailModal()" style="padding: 10px 20px;">Cancel</button>
                <button type="button" id="btnVerifyOtp" class="btn btn-primary" style="padding: 10px 20px;">Verify & Update</button>
            </div>
        </div>
        
    </div>
</div>

<!-- PROFILE SCRIPT -->
<script>
    const profileForm = document.getElementById('profileForm');
    const btnSaveProfile = document.getElementById('btnSaveProfile');
    const profEmail = document.getElementById('prof_email');
    
    // Modal Elements
    const emailModal = document.getElementById('emailChangeModal');
    const newEmailInput = document.getElementById('new_email_input');
    const emailMsg = document.getElementById('email_msg');
    const btnSendOtp = document.getElementById('btnSendOtp');
    const emailStep1 = document.getElementById('emailStep1');
    const emailStep2 = document.getElementById('emailStep2');
    const sentToEmail = document.getElementById('sentToEmail');
    const otpInput = document.getElementById('otp_input');
    const btnVerifyOtp = document.getElementById('btnVerifyOtp');

    let debounceTimer;

    function openEmailChangeModal() {
        emailModal.style.display = 'flex';
        emailStep1.style.display = 'block';
        emailStep2.style.display = 'none';
        newEmailInput.value = '';
        newEmailInput.style.borderColor = 'var(--border)';
        emailMsg.textContent = '';
        btnSendOtp.disabled = true;
        otpInput.value = '';
    }

    function closeEmailModal() {
        emailModal.style.display = 'none';
    }

    // Debounced real-time email check
    newEmailInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        const emailValue = newEmailInput.value.trim();
        const currentEmail = profEmail.getAttribute('data-current');

        if (!emailValue || !emailValue.includes('@')) {
            newEmailInput.style.borderColor = 'var(--border)';
            emailMsg.textContent = '';
            btnSendOtp.disabled = true;
            return;
        }

        if (emailValue.toLowerCase() === currentEmail.toLowerCase()) {
            newEmailInput.style.borderColor = '#ef4444'; // Red
            emailMsg.style.color = '#ef4444';
            emailMsg.textContent = 'This is already your current email.';
            btnSendOtp.disabled = true;
            return;
        }

        emailMsg.style.color = 'var(--muted)';
        emailMsg.textContent = 'Checking availability...';
        btnSendOtp.disabled = true;

        debounceTimer = setTimeout(async () => {
            try {
                const res = await fetch('../../../microfin_backend/api/api_profile_email_change.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'check_email', email: emailValue })
                }).then(r => r.json());
                
                if (res.status === 'success') {
                    newEmailInput.style.borderColor = '#22c55e'; // Green
                    emailMsg.style.color = '#22c55e';
                    emailMsg.textContent = 'Email is available!';
                    btnSendOtp.disabled = false;
                } else {
                    newEmailInput.style.borderColor = '#ef4444'; // Red
                    emailMsg.style.color = '#ef4444';
                    emailMsg.textContent = res.message || 'Email is already taken.';
                    btnSendOtp.disabled = true;
                }
            } catch (err) {
                console.error(err);
                emailMsg.textContent = 'Error checking email.';
            }
        }, 500); // 500ms debounce
    });

    btnSendOtp.addEventListener('click', async () => {
        const emailValue = newEmailInput.value.trim();
        btnSendOtp.disabled = true;
        let originalText = btnSendOtp.textContent;
        btnSendOtp.innerHTML = '<span class="material-symbols-rounded ms" style="font-size:16px; animation:spin 1s linear infinite;">sync</span> Sending...';

        try {
            const otpRes = await fetch('../../../microfin_backend/api/api_profile_email_change.php', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'send_otp', email: emailValue })
            }).then(r => r.json());
            
            if (otpRes.status !== 'success') {
                alert(otpRes.message || 'Unable to send OTP to that email.');
                btnSendOtp.disabled = false;
            } else {
                sentToEmail.textContent = emailValue;
                emailStep1.style.display = 'none';
                emailStep2.style.display = 'block';
                otpInput.focus();
            }
        } catch (err) {
            console.error(err);
            alert('Failed to interact with server.');
            btnSendOtp.disabled = false;
        } finally {
            btnSendOtp.innerHTML = originalText;
        }
    });

    btnVerifyOtp.addEventListener('click', async () => {
        const emailValue = newEmailInput.value.trim();
        const code = otpInput.value.trim();
        if (code.length !== 6) {
            alert('Please enter the 6-digit code.');
            return;
        }

        btnVerifyOtp.disabled = true;
        let originalText = btnVerifyOtp.textContent;
        btnVerifyOtp.innerHTML = '<span class="material-symbols-rounded ms" style="font-size:16px; animation:spin 1s linear infinite;">sync</span> Verifying...';

        try {
            const verifyRes = await fetch('../../../microfin_backend/api/api_profile_email_change.php', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'verify_otp', email: emailValue, otp_code: code })
            }).then(r => r.json());
            
            if (verifyRes.status !== 'success') {
                alert(verifyRes.message || 'Invalid or expired OTP.');
                btnVerifyOtp.disabled = false;
            } else {
                // If OTP is verified, actually COMMIT the profile update.
                const firstName = document.getElementById('prof_first_name').value.trim();
                const lastName = document.getElementById('prof_last_name').value.trim();
                const username = document.getElementById('prof_username').value.trim();
                const contactNumber = document.getElementById('prof_contact_number').value.trim();

                const updateRes = await fetch('../../../microfin_backend/api/api_profile_update.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        first_name: firstName, 
                        last_name: lastName, 
                        username: username,
                        phone_number: contactNumber,
                        email: emailValue 
                    })
                }).then(r => r.json());

                if (updateRes.status === 'success') {
                    alert('Email successfully changed and verified!');
                    window.location.reload();
                } else {
                    alert(updateRes.message || 'Verification succeeded, but failed to commit profile.');
                }
            }
        } catch (err) {
            console.error(err);
            alert('Failed to interact with server.');
            btnVerifyOtp.disabled = false;
        } finally {
            btnVerifyOtp.innerHTML = originalText;
        }
    });

    // Submitting main form just saves the First / Last Name now
    profileForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const firstName = document.getElementById('prof_first_name').value.trim();
        const lastName = document.getElementById('prof_last_name').value.trim();
        const username = document.getElementById('prof_username').value.trim();
        const contactNumber = document.getElementById('prof_contact_number').value.trim();
        const currentEmailVal = profEmail.getAttribute('data-current');
        
        if (!firstName || !lastName || !username) {
            alert("First Name, Last Name, and Username are required.");
            return;
        }

        btnSaveProfile.disabled = true;
        let oldText = btnSaveProfile.innerHTML;
        btnSaveProfile.innerHTML = '<span class="material-symbols-rounded ms" style="font-size:16px; animation:spin 1s linear infinite;">sync</span> Saving...';

        try {
            const updateRes = await fetch('../../../microfin_backend/api/api_profile_update.php', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    first_name: firstName, 
                    last_name: lastName, 
                    username: username,
                    phone_number: contactNumber,
                    email: currentEmailVal 
                })
            }).then(r => r.json());

            if (updateRes.status === 'success') {
                alert('Profile details updated successfully!');
                window.location.reload();
            } else {
                alert(updateRes.message || 'Failed to update name.');
            }
        } catch (err) {
            console.error(err);
        } finally {
            btnSaveProfile.disabled = false;
            btnSaveProfile.innerHTML = 'Save Details';
        }
    });

    if (!document.getElementById('spin-anim')) {
        const style = document.createElement('style');
        style.id = 'spin-anim';
        style.innerHTML = `@keyframes spin { 100% { transform: rotate(360deg); } }`;
        document.head.appendChild(style);
    }
</script>
