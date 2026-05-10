document.addEventListener('DOMContentLoaded', () => {
    const root = document.documentElement;
    const themeButtons = document.querySelectorAll('.js-public-theme-toggle');
    const themeStorageKey = 'microfin_ui_theme';
    const legacyThemeKeys = ['microfin_public_theme', 'microfin_super_admin_theme'];
    const navbar = document.querySelector('.navbar');
    const animatedLogo = document.querySelector('[data-animated-logo]');
    const reducedMotionQuery = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;

    const normalizeTheme = (value) => value === 'dark' ? 'dark' : 'light';

    const getStoredTheme = () => {
        try {
            const themeKeys = [themeStorageKey, ...legacyThemeKeys];
            for (const key of themeKeys) {
                const storedTheme = localStorage.getItem(key);
                if (storedTheme === 'light' || storedTheme === 'dark') {
                    return storedTheme;
                }
            }
        } catch (error) {
            console.warn('Unable to read public theme preference.', error);
        }

        return null;
    };

    const persistTheme = (theme) => {
        try {
            localStorage.setItem(themeStorageKey, theme);
            legacyThemeKeys.forEach((key) => {
                localStorage.setItem(key, theme);
            });
        } catch (error) {
            console.warn('Unable to store public theme preference.', error);
        }
    };

    const updateThemeButtons = (theme) => {
        themeButtons.forEach((button) => {
            const nextTheme = theme === 'dark' ? 'light' : 'dark';
            const icon = button.querySelector('.theme-toggle-icon');
            const label = button.querySelector('.theme-toggle-label');
            button.setAttribute('aria-label', `Switch to ${nextTheme} mode`);
            button.setAttribute('title', `Switch to ${nextTheme} mode`);
            if (icon) {
                icon.textContent = nextTheme === 'dark' ? 'light_mode' : 'dark_mode';
            }
            if (label) {
                label.textContent = nextTheme === 'dark' ? 'Light' : 'Dark';
            }
        });
    };

    const updateNavbarShadow = () => {
        if (!navbar) {
            return;
        }

        if (window.scrollY <= 10) {
            navbar.style.boxShadow = 'none';
            return;
        }

        navbar.style.boxShadow = root.getAttribute('data-theme') === 'dark'
            ? '0 18px 36px -28px rgba(0, 0, 0, 0.65)'
            : '0 10px 22px -18px rgba(15, 23, 42, 0.16)';
    };

    const applyPublicTheme = (theme, persist = true) => {
        const resolvedTheme = normalizeTheme(theme);
        root.setAttribute('data-theme', resolvedTheme);
        updateThemeButtons(resolvedTheme);
        updateNavbarShadow();

        if (persist) {
            persistTheme(resolvedTheme);
        }
    };

    const storedTheme = getStoredTheme();
    applyPublicTheme(storedTheme || root.getAttribute('data-theme') || 'light', Boolean(storedTheme));

    themeButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const currentTheme = normalizeTheme(root.getAttribute('data-theme'));
            applyPublicTheme(currentTheme === 'dark' ? 'light' : 'dark');
        });
    });

    // --- Public Logo Animation ---
    if (animatedLogo) {
        const staticLogoLayer = animatedLogo.querySelector('[data-logo-layer="static"]');
        let animatedLogoLayer = animatedLogo.querySelector('[data-logo-layer="animated"]');
        const usesLayeredLogo = Boolean(staticLogoLayer && animatedLogoLayer);
        const staticSrc = animatedLogo.getAttribute('data-logo-static-src')
            || (usesLayeredLogo ? (staticLogoLayer.getAttribute('src') || '') : (animatedLogo.getAttribute('src') || ''));
        const animatedSrc = animatedLogo.getAttribute('data-logo-animated-src') || '';
        const animatedBlankSrc = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';
        const idleDelayMs = Number(animatedLogo.getAttribute('data-logo-idle-delay') || 30000);
        const playDurationMs = Number(animatedLogo.getAttribute('data-logo-play-duration') || 3600);
        const preloadTimeoutMs = Number(animatedLogo.getAttribute('data-logo-preload-timeout') || 1250);
        const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;

        let idleTimerId = 0;
        let animationTimerId = 0;
        let handoffTimerId = 0;
        let preloadPromise = null;
        let animationEnabled = false;
        let animationPlaying = false;
        let idleAnimationUnlocked = true;
        let lastPointerMoveAt = 0;
        let animationRunId = 0;

        const setLogoState = (state) => {
            if (usesLayeredLogo) {
                if (staticSrc && staticLogoLayer.getAttribute('src') !== staticSrc) {
                    staticLogoLayer.setAttribute('src', staticSrc);
                }

                animatedLogo.setAttribute('data-logo-state', state);
                return;
            }

            const nextSrc = state === 'animated' ? animatedSrc : staticSrc;
            if (!nextSrc) {
                return;
            }

            if (animatedLogo.getAttribute('src') !== nextSrc) {
                animatedLogo.setAttribute('src', nextSrc);
            }

            animatedLogo.setAttribute('data-logo-state', state);
        };

        const clearHandoffTimer = () => {
            if (handoffTimerId) {
                window.clearTimeout(handoffTimerId);
                handoffTimerId = 0;
            }

            if (usesLayeredLogo) {
                animatedLogo.removeAttribute('data-logo-transition');
            }
        };

        const parkAnimatedLayer = () => {
            if (!usesLayeredLogo || !animatedLogoLayer) {
                return;
            }

            if (animatedLogoLayer.getAttribute('src') !== animatedBlankSrc) {
                animatedLogoLayer.setAttribute('src', animatedBlankSrc);
            }
        };

        const clearIdleTimer = () => {
            if (idleTimerId) {
                window.clearTimeout(idleTimerId);
                idleTimerId = 0;
            }
        };

        const stopLogoAnimation = (options = {}) => {
            const immediate = Boolean(options.immediate);

            if (animationTimerId) {
                window.clearTimeout(animationTimerId);
                animationTimerId = 0;
            }

            animationPlaying = false;

            if (usesLayeredLogo && !immediate) {
                clearHandoffTimer();
                animatedLogo.setAttribute('data-logo-transition', 'handoff');
                setLogoState('static');

                handoffTimerId = window.setTimeout(() => {
                    handoffTimerId = 0;
                    animatedLogo.removeAttribute('data-logo-transition');
                    parkAnimatedLayer();
                }, 170);
                return;
            }

            clearHandoffTimer();
            setLogoState('static');
            parkAnimatedLayer();
        };

        const buildAnimatedPlaybackSrc = () => {
            if (!animatedSrc) {
                return '';
            }

            return `${animatedSrc.split('#')[0]}#play=${++animationRunId}`;
        };

        const restartAnimatedLayer = () => {
            if (!usesLayeredLogo || !animatedLogoLayer || !animatedSrc) {
                return;
            }

            const freshLayer = animatedLogoLayer.cloneNode(true);
            freshLayer.setAttribute('src', buildAnimatedPlaybackSrc());
            animatedLogoLayer.replaceWith(freshLayer);
            animatedLogoLayer = freshLayer;
        };

        const pageIsVisible = () => document.visibilityState === 'visible';

        const connectionAllowsAnimation = () => {
            if (!connection) {
                return true;
            }

            if (connection.saveData) {
                return false;
            }

            const effectiveType = String(connection.effectiveType || '').toLowerCase();
            return effectiveType !== 'slow-2g' && effectiveType !== '2g';
        };

        const scheduleIdleAnimation = () => {
            clearIdleTimer();

            if (!animationEnabled || !pageIsVisible()) {
                return;
            }

            idleTimerId = window.setTimeout(() => {
                if (!idleAnimationUnlocked) {
                    return;
                }

                idleAnimationUnlocked = false;
                playLogoAnimation();
            }, idleDelayMs);
        };

        function playLogoAnimation() {
            if (!animationEnabled || animationPlaying || !pageIsVisible()) {
                return false;
            }

            clearHandoffTimer();
            restartAnimatedLayer();
            animationPlaying = true;
            setLogoState('animated');

            animationTimerId = window.setTimeout(() => {
                animationTimerId = 0;
                stopLogoAnimation();
            }, playDurationMs);

            return true;
        }

        const preloadAnimatedLogo = () => {
            if (preloadPromise) {
                return preloadPromise;
            }

            if (!staticSrc || !animatedSrc || (reducedMotionQuery && reducedMotionQuery.matches) || !connectionAllowsAnimation()) {
                preloadPromise = Promise.resolve(false);
                return preloadPromise;
            }

            preloadPromise = new Promise((resolve) => {
                const preloadImage = new Image();
                let settled = false;
                let timeoutId = 0;

                const finish = (result) => {
                    if (settled) {
                        return;
                    }

                    settled = true;
                    window.clearTimeout(timeoutId);
                    preloadImage.onload = null;
                    preloadImage.onerror = null;
                    resolve(result);
                };

                timeoutId = window.setTimeout(() => finish(false), preloadTimeoutMs);

                preloadImage.onload = () => finish(true);
                preloadImage.onerror = () => finish(false);
                preloadImage.decoding = 'async';
                preloadImage.src = animatedSrc;

                if (preloadImage.complete && preloadImage.naturalWidth > 0) {
                    finish(true);
                }
            });

            return preloadPromise;
        };

        const noteActivity = (eventName) => {
            if (eventName === 'pointermove') {
                const now = Date.now();
                if (now - lastPointerMoveAt < 1000) {
                    return;
                }
                lastPointerMoveAt = now;
            }

            if (!pageIsVisible()) {
                return;
            }

            idleAnimationUnlocked = true;
            scheduleIdleAnimation();
        };

        setLogoState('static');
        parkAnimatedLayer();

        preloadAnimatedLogo().then((ready) => {
            animationEnabled = ready;

            if (!animationEnabled) {
                setLogoState('static');
                parkAnimatedLayer();
                return;
            }

            if (pageIsVisible()) {
                playLogoAnimation();
            }

            scheduleIdleAnimation();
        });

        ['pointermove', 'pointerdown', 'keydown', 'scroll', 'touchstart'].forEach((eventName) => {
            window.addEventListener(eventName, () => noteActivity(eventName), { passive: true });
        });

        document.addEventListener('visibilitychange', () => {
            if (!pageIsVisible()) {
                clearIdleTimer();
                stopLogoAnimation({ immediate: true });
                return;
            }

            idleAnimationUnlocked = true;
            scheduleIdleAnimation();
        });
    }

    // --- Smooth Scrolling for Navigation Links ---
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const targetId = this.getAttribute('href');
            if(targetId === '#') return;
            
            e.preventDefault();
            const targetElement = document.querySelector(targetId);
            
            if (targetElement) {
                targetElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // --- Demo Form Date Validation & OTP Logic ---
    const demoForm = document.getElementById('demo-form');
    
    if (demoForm) {
        // OTP Elements
        const btnSendOtp = document.getElementById('btn-send-otp');
        const btnVerifyOtp = document.getElementById('btn-verify-otp');
        const otpGroup = document.getElementById('otp-group');
        const emailInput = document.getElementById('work_email');
        const otpInput = document.getElementById('otp_code');
        const otpMsg = document.getElementById('otp-status-msg');
        const btnFinalSubmit = document.getElementById('btn-final-submit');
        const formBlockNote = document.getElementById('form-block-note');
        const isOtpVerified = document.getElementById('is_otp_verified');

        if (btnSendOtp) {
            btnSendOtp.addEventListener('click', () => {
                const email = emailInput.value.trim();
                if (!email) {
                    alert("Please enter a valid business email first.");
                    return;
                }
                
                // Show loading state on button
                btnSendOtp.disabled = true;
                btnSendOtp.innerHTML = 'Sending...';

                const formData = new FormData();
                formData.append('action', 'send_otp');
                formData.append('email', email);

                fetch('api/api_demo.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        otpGroup.style.display = 'block';
                        btnSendOtp.innerHTML = 'OTP Sent';
                        btnSendOtp.classList.remove('btn-outline');
                        btnSendOtp.style.backgroundColor = '#10b981';
                        btnSendOtp.style.color = '#fff';
                        btnSendOtp.style.borderColor = '#10b981';
                        otpMsg.style.color = '#10b981';
                        
                        let msg = data.message;
                        if(data.dev_otp) {
                            msg += ' (DEV MOCK CODE: ' + data.dev_otp + ')';
                            console.log("MOCK OTP IS:", data.dev_otp);
                        }
                        otpMsg.innerText = msg;
                    } else {
                        btnSendOtp.disabled = false;
                        btnSendOtp.innerHTML = 'Send OTP';
                        alert(data.message);
                    }
                })
                .catch(err => {
                    btnSendOtp.disabled = false;
                    btnSendOtp.innerHTML = 'Send OTP';
                    console.error(err);
                });
            });
        }

        if (btnVerifyOtp) {
            btnVerifyOtp.addEventListener('click', () => {
                const email = emailInput.value.trim();
                const code = otpInput.value.trim();
                
                if (code.length !== 6) {
                    otpMsg.style.color = '#ef4444';
                    otpMsg.innerText = 'Please enter a valid 6-digit OTP.';
                    return;
                }

                btnVerifyOtp.disabled = true;
                btnVerifyOtp.innerHTML = 'Verifying...';

                const formData = new FormData();
                formData.append('action', 'verify_otp');
                formData.append('email', email);
                formData.append('otp_code', code);

                fetch('api/api_demo.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        btnVerifyOtp.innerHTML = 'Verified';
                        btnVerifyOtp.style.backgroundColor = '#10b981';
                        btnVerifyOtp.style.borderColor = '#10b981';
                        otpMsg.style.color = '#10b981';
                        otpMsg.innerText = data.message;
                        
                        emailInput.readOnly = true;
                        otpInput.readOnly = true;
                        isOtpVerified.value = '1';

                        // Final Submission Unlocked
                        btnFinalSubmit.style.opacity = '1';
                        btnFinalSubmit.style.pointerEvents = 'auto';
                        formBlockNote.style.color = '#10b981';
                        formBlockNote.innerText = 'You may now submit your request.';
                    } else {
                        btnVerifyOtp.disabled = false;
                        btnVerifyOtp.innerHTML = 'Verify';
                        otpMsg.style.color = '#ef4444';
                        otpMsg.innerText = data.message;
                    }
                })
                .catch(err => {
                    btnVerifyOtp.disabled = false;
                    btnVerifyOtp.innerHTML = 'Verify';
                    console.error(err);
                });
            });
        }

        demoForm.addEventListener('submit', (e) => {
            const dobInput = demoForm.querySelector('input[name="date_of_birth"]');
            if (dobInput && dobInput.value) {
                const dob = new Date(dobInput.value);
                const today = new Date();
                let age = today.getFullYear() - dob.getFullYear();
                const m = today.getMonth() - dob.getMonth();
                if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) {
                    age--;
                }
                if (age < 18) {
                    e.preventDefault();
                    const dobError = document.getElementById('dob-error');
                    if (dobError) dobError.style.display = 'block';
                    dobInput.focus();
                    return;
                } else {
                    const dobError = document.getElementById('dob-error');
                    if (dobError) dobError.style.display = 'none';
                }
            }

            if (isOtpVerified.value === '0') {
                e.preventDefault();
                alert("Please verify your email with the OTP before submitting.");
                return;
            }

            const submitBtn = demoForm.querySelector('button[type="submit"]');
            
            // Loading state
            submitBtn.innerHTML = '<span class="material-symbols-rounded" style="animation: spin 1s linear infinite; font-size: 18px; margin-right: 8px; vertical-align: middle;">sync</span> Submitting...';
            submitBtn.disabled = true;
            submitBtn.style.opacity = '0.8';
            
            // Add keyframes if not exists
            if (!document.getElementById('spin-keyframes')) {
                const style = document.createElement('style');
                style.id = 'spin-keyframes';
                style.innerHTML = `@keyframes spin { 100% { transform: rotate(360deg); } }`;
                document.head.appendChild(style);
            }
        });
    }

    // --- Navbar Scroll Effect ---
    window.addEventListener('scroll', () => {
        updateNavbarShadow();
    });
    updateNavbarShadow();
});
