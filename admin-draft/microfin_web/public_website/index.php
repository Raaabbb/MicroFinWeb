<?php
require_once '../../microfin_backend/config/db_connect.php';
require_once '../../microfin_backend/utils/mobile_app_build.php';
require_once __DIR__ . '/install_attribution.php';

$microfinLogoFile = __DIR__ . '/logo/MicroFin-logo-transparent-temp.png';
$microfinLogoAsset = 'logo/MicroFin-logo-transparent-temp.png?v=' . urlencode((string) @filemtime($microfinLogoFile));
$microfinAnimatedLogoFile = __DIR__ . '/logo/microfin_wide_smooth_flip_transparent.gif';
$microfinAnimatedLogoAsset = 'logo/microfin_wide_smooth_flip_transparent.gif?v=' . urlencode((string) @filemtime($microfinAnimatedLogoFile));
$microfinAnimatedLogoDurationMs = 9230;

$renderUnavailable = static function (string $title, string $message) use ($microfinLogoAsset): void {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    $pageTitle = 'MicroFin | ' . $title;
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>'
        . htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8')
        . '</title><link rel="icon" type="image/png" href="'
        . htmlspecialchars($microfinLogoAsset, ENT_QUOTES, 'UTF-8')
        . '"><link rel="apple-touch-icon" href="'
        . htmlspecialchars($microfinLogoAsset, ENT_QUOTES, 'UTF-8')
        . '"></head><body style="font-family: \'Plus Jakarta Sans\', Arial, sans-serif; background:#f7f3e8; color:#1f2d25; display:flex; min-height:100vh; align-items:center; justify-content:center; margin:0;"><div style="max-width:420px; background:#fffdf7; border:transparent; border-radius:18px; padding:32px; text-align:center; box-shadow:0 18px 40px rgba(54,43,12,0.12);"><h1 style="margin:0 0 12px; font-size:1.5rem;">'
        . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
        . '</h1><p style="margin:0; color:#475569; line-height:1.6;">'
        . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
        . '</p></div></body></html>';
    exit;
};

$requestedRoute = mf_install_requested_route();
if ($requestedRoute === 'get-app') {
    $tenantIdentifier = trim((string)($_GET['bank_id'] ?? $_GET['tenant'] ?? $_GET['tenant_slug'] ?? $_GET['site'] ?? ''));
    $tenant = mf_install_resolve_tenant($pdo, $tenantIdentifier);

    if (!is_array($tenant)) {
        $renderUnavailable(
            'App download unavailable',
            'We could not find the requested tenant download link. Please return to the bank website and try again.'
        );
    }

    $apkAsset = mf_install_resolve_apk_asset($tenant, true);

    $download = mf_install_record_download($pdo, $tenant, $apkAsset);
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    if (!empty($apkAsset['path'])) {
        mf_install_stream_apk((string)$apkAsset['path'], (string)$apkAsset['filename']);
        exit;
    }

    header('Location: ' . (string)($apkAsset['url'] ?? $download['apk_url']), true, 302);
    exit;
}

if ($requestedRoute === 'get-generic-app') {
    $apkAsset = mf_install_resolve_generic_apk_asset();
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    if (!empty($apkAsset['path'])) {
        mf_install_stream_apk((string)$apkAsset['path'], (string)$apkAsset['filename']);
        exit;
    }

    header('Location: ' . (string)($apkAsset['url'] ?? mf_install_generic_apk_url()), true, 302);
    exit;
}

// Fetch active tenants to display in the "Trusted By" section
$stmt = $pdo->query("SELECT tenant_name FROM tenants WHERE status = 'Active' AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 5");
$active_tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt_count = $pdo->query("SELECT COUNT(*) as count FROM tenants WHERE status = 'Active' AND deleted_at IS NULL");
$tenant_count = $stmt_count->fetch(PDO::FETCH_ASSOC)['count'];
$powered_by_count = $tenant_count > 0 ? $tenant_count : "leading";
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MicroFin | The Cloud Banking Platform for Modern MFIs</title>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($microfinLogoAsset, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($microfinLogoAsset, ENT_QUOTES, 'UTF-8'); ?>">
    <script>
        (function () {
            try {
                var themeKeys = ['microfin_ui_theme', 'microfin_public_theme', 'microfin_super_admin_theme'];
                for (var i = 0; i < themeKeys.length; i += 1) {
                    var storedTheme = localStorage.getItem(themeKeys[i]);
                    if (storedTheme === 'light' || storedTheme === 'dark') {
                        document.documentElement.setAttribute('data-theme', storedTheme);
                        break;
                    }
                }
            } catch (error) {}
        }());
    </script>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Material Symbols -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="style.css?v=<?php echo urlencode((string) @filemtime(__DIR__ . '/style.css')); ?>">
    <link rel="stylesheet" href="sarah/sarah-chatbot.css?v=<?php echo urlencode((string) @filemtime(__DIR__ . '/sarah/sarah-chatbot.css')); ?>">
    <style>
        .navbar .logo-text-animated {
            display: inline-grid;
            align-items: center;
            justify-items: end;
        }

        .navbar .logo-text-animated .logo-text-sizer,
        .navbar .logo-text-animated .logo-text-track {
            grid-area: 1 / 1;
            white-space: pre;
        }

        .navbar .logo-text-animated .logo-text-sizer {
            visibility: hidden;
            user-select: none;
            pointer-events: none;
        }

        .navbar .logo-text-animated .logo-text-track {
            display: inline-flex;
            align-items: baseline;
            justify-self: stretch;
            justify-content: flex-end;
            width: 100%;
            pointer-events: none;
        }

        .navbar .logo-text-animated .logo-char {
            display: inline-flex;
            overflow: hidden;
            max-width: var(--char-width, 1.2ch);
            transform: translateX(0);
            transition:
                max-width 290ms cubic-bezier(0.16, 1, 0.3, 1),
                transform 290ms cubic-bezier(0.16, 1, 0.3, 1),
                margin-right 290ms cubic-bezier(0.16, 1, 0.3, 1);
            will-change: max-width, transform, margin-right;
        }

        .navbar .logo-text-animated .logo-char-glyph {
            display: inline-block;
            min-width: max-content;
        }

        .navbar .logo-text-animated .logo-char.is-hidden {
            max-width: 0;
            margin-right: 0 !important;
            transform: translateX(0.12em);
        }

        .navbar .logo-text-animated .logo-char.logo-char-last.is-hidden {
            transform: translateX(0);
        }

        .navbar .logo-mark-stack[data-animated-logo] {
            position: relative;
            display: block;
            width: 68px;
            height: 38px;
            flex-shrink: 0;
        }

        .navbar .logo-mark-stack[data-animated-logo] .logo-mark {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: contain;
            transform-origin: left center;
            pointer-events: none;
            user-select: none;
        }

        .navbar .logo-mark-stack[data-animated-logo] .logo-mark-static {
            opacity: 1;
            transform: scale(1);
            transition:
                opacity 120ms ease,
                transform 220ms cubic-bezier(0.22, 1, 0.36, 1);
            will-change: opacity, transform;
        }

        .navbar .logo-mark-stack[data-animated-logo] .logo-mark-animated {
            opacity: 0;
            transform: scale(1.1);
            transition: opacity 80ms ease;
            will-change: opacity;
        }

        .navbar .logo-mark-stack[data-animated-logo][data-logo-state="animated"] .logo-mark-static {
            opacity: 0;
        }

        .navbar .logo-mark-stack[data-animated-logo][data-logo-transition="handoff"] .logo-mark-animated {
            opacity: 0 !important;
            transition: none;
        }

        .navbar .logo-mark-stack[data-animated-logo][data-logo-state="animated"] .logo-mark-animated {
            opacity: 1;
        }
    </style>
</head>
<body>

    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="container nav-container">
            <div class="logo">
                <span
                    class="logo-mark-stack"
                    data-animated-logo
                    data-logo-static-src="<?php echo htmlspecialchars($microfinLogoAsset, ENT_QUOTES, 'UTF-8'); ?>"
                    data-logo-animated-src="<?php echo htmlspecialchars($microfinAnimatedLogoAsset, ENT_QUOTES, 'UTF-8'); ?>"
                    data-logo-idle-delay="30000"
                    data-logo-play-duration="<?php echo (int) $microfinAnimatedLogoDurationMs; ?>"
                    data-logo-preload-timeout="1250"
                    data-logo-state="static"
                >
                    <img
                        src="<?php echo htmlspecialchars($microfinLogoAsset, ENT_QUOTES, 'UTF-8'); ?>"
                        alt="MicroFin logo"
                        class="logo-mark logo-mark-static"
                        data-logo-layer="static"
                    >
                    <img
                        src="<?php echo htmlspecialchars($microfinAnimatedLogoAsset, ENT_QUOTES, 'UTF-8'); ?>"
                        alt=""
                        class="logo-mark logo-mark-animated"
                        data-logo-layer="animated"
                        aria-hidden="true"
                    >
                </span>
                <span class="logo-text logo-text-animated" data-animated-logo-text data-logo-word="MicroFin">
                    <span class="logo-text-sizer" aria-hidden="true">MicroFin</span>
                    <span class="logo-text-track" aria-hidden="true"></span>
                </span>
            </div>
            
            <div class="nav-links">
                <a href="#features">Features</a>
                <a href="#pricing">Pricing</a>
                <a href="#how-it-works">How it Works</a>
                <a href="#security">Security</a>
            </div>
            
            <div class="nav-cta">
                <button type="button" class="theme-toggle-btn js-public-theme-toggle" aria-label="Switch to dark mode">
                    <span class="material-symbols-rounded theme-toggle-icon">dark_mode</span>
                </button>
                <a href="../super_admin/login.php" class="btn btn-login">Platform Login</a>
                <a href="demo.php" class="btn btn-primary">Apply Now</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <header class="hero">
        <div class="container hero-container">
            <div class="hero-content">
                <div class="badge-pill">SaaS for Microfinance</div>
                <h1>Empower your institution with a true cloud core banking system.</h1>
                <p>MicroFin is a fully isolated, multi-tenant cloud banking platform for Microfinance Institutions, SACCOs, and Cooperatives, with a secure platform shell that stays distinct from each tenant's own brand experience.</p>
                
                <div class="hero-actions">
                    <a href="demo.php" class="btn btn-primary btn-lg">Apply Now</a>
                    <button type="button" class="btn btn-outline btn-lg js-open-sarah-chat">Chat with Sarah</button>
                </div>
                <div class="trust-marks">
                    <span>Trusted by <?php echo $powered_by_count; ?> microfinance institutions <?php if($powered_by_count > 0) echo "including:"; ?></span>
                    <?php if (!empty($active_tenants)): ?>
                    <div class="trusted-tenants-list">
                        <?php foreach($active_tenants as $tenant): ?>
                            <span class="trusted-tenant-badge">
                                <span class="material-symbols-rounded">corporate_fare</span>
                                <?php echo htmlspecialchars($tenant['tenant_name']); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="hero-image">
                <div class="mockup-window">
                    <div class="mockup-header">
                        <div class="dot red"></div><div class="dot yellow"></div><div class="dot green"></div>
                    </div>
                    <img src="https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&q=80&w=800&h=500" alt="Dashboard Preview" class="mockup-img">
                </div>
            </div>
        </div>
    </header>

    <!-- Features Grid -->
    <section id="features" class="section bg-light showcase-features-section">
        <div class="container">
            <div class="section-header text-center">
                <h2>Built for Scale, Designed for Security</h2>
                <p>Everything your cooperative needs to operate digitally, out of the box.</p>
            </div>
            
            <div class="grid-3 showcase-feature-grid">
                <!-- Feature 1 -->
                <div class="feature-card feature-card-cosmos">
                    <div class="feature-icon"><span class="material-symbols-rounded">dns</span></div>
                    <h3>Multi-Tenant Architecture</h3>
                    <p>Your data is perfectly isolated. Experience enterprise-grade security where your institution's records are completely separated from others.</p>
                </div>
                <!-- Feature 2 -->
                <div class="feature-card feature-card-guidance">
                    <div class="feature-icon"><span class="material-symbols-rounded">palette</span></div>
                    <h3>Fully Whitelabeled</h3>
                    <p>It's your brand. Tenant logos, colors, and themes stay inside tenant-owned spaces, while the MicroFin platform shell remains a separate secure parent layer.</p>
                </div>
                <!-- Feature 3 -->
                <div class="feature-card feature-card-vault">
                    <div class="feature-icon"><span class="material-symbols-rounded">account_balance</span></div>
                    <h3>Core Banking Engine</h3>
                    <p>Automated loan origination, savings management, and real-time interest calculation baked directly into the platform core.</p>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Extended Capabilities -->
    <section id="capabilities" class="section bg-white">
        <div class="container">
            <div class="section-header text-center">
                <h2>Beyond Core Banking</h2>
                <p>Advanced tools completely integrated into your ecosystem to drive growth and efficiency.</p>
            </div>
            
            <div class="grid-3">
                <div class="feature-card">
                    <div class="feature-icon"><span class="material-symbols-rounded">monitoring</span></div>
                    <h3>Advanced Analytics</h3>
                    <p>Generate real-time PAR (Portfolio at Risk) reports, balance sheets, and income statements with one click.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><span class="material-symbols-rounded">sms</span></div>
                    <h3>Automated Notifications</h3>
                    <p>Send automated email reminders to borrowers for upcoming dues, reducing default rates automatically.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><span class="material-symbols-rounded">api</span></div>
                    <h3>API-Ready & Integrations</h3>
                    <p>Connect seamlessly with payment gateways, credit bureaus, and external accounting tools via secure APIs.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="section bg-white text-dark">
        <div class="container">
            <div class="section-header text-center">
                <h2>Simple, Transparent Pricing</h2>
                <p>Scale your financial institution with plans designed for growth. No hidden fees.</p>
            </div>
            
            <div class="pricing-grid" style="grid-template-columns: repeat(2, minmax(0, 1fr)); max-width: 900px; margin: 0 auto;">
                <!-- Starter -->
                <div class="pricing-card pricing-card-starter">
                    <div class="pricing-header">
                        <h3>Starter</h3>
                        <div class="price">&#8369;4,999<span>/mo</span></div>
                    </div>
                    <ul class="pricing-features">
                        <li><span class="material-symbols-rounded">check_circle</span> <strong>2,000</strong> Max Clients</li>
                        <li><span class="material-symbols-rounded">check_circle</span> <strong>1,000</strong> Max Staffs</li>
                        <li><span class="material-symbols-rounded">check_circle</span> Enterprise Core Engine</li>
                        <li><span class="material-symbols-rounded">check_circle</span> Branded Mobile APK</li>
                        <li><span class="material-symbols-rounded">check_circle</span> <strong>3</strong> Website Templates</li>
                    </ul>
                    <div style="margin-top: 24px; text-align: center;">
                        <a href="demo.php" class="btn btn-outline" style="width: 100%;">Select Starter</a>
                    </div>
                </div>
                
                <!-- Enterprise -->
                <div class="pricing-card pricing-card-enterprise popular">
                    <div class="popular-badge">Unlimited Power</div>
                    <div class="pricing-header">
                        <h3>Enterprise</h3>
                        <div class="price">&#8369;14,999<span>/mo</span></div>
                    </div>
                    <ul class="pricing-features">
                        <li><span class="material-symbols-rounded">check_circle</span> <strong>Unlimited</strong> Clients</li>
                        <li><span class="material-symbols-rounded">check_circle</span> <strong>Unlimited</strong> Max Staffs</li>
                        <li><span class="material-symbols-rounded">check_circle</span> White-Labeled Mobile App</li>
                        <li><span class="material-symbols-rounded">check_circle</span> <strong>All Premium</strong> Templates</li>
                        <li><span class="material-symbols-rounded">check_circle</span> Isolated Cloud Architecture</li>
                    </ul>
                    <div style="margin-top: 24px; text-align: center;">
                        <a href="demo.php" class="btn btn-primary" style="width: 100%;">Go Enterprise</a>
                    </div>
                </div>
            </div>
            
            
        </div>
    </section>

    <!-- How it Works Flow -->
    <section id="how-it-works" class="section bg-light">
        <div class="container">
            <div class="section-header">
                <h2>Go live in days, not months.</h2>
                <p>Because it's a SaaS platform, we handle the infrastructure. You just run your business.</p>
            </div>
            
            <div class="timeline">
                <div class="timeline-item">
                    <div class="timeline-dot">1</div>
                    <div class="timeline-content">
                        <h3>Book a Discovery Call</h3>
                        <p>We meet to understand your current loan volume, data migration needs, and compliance requirements.</p>
                    </div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-dot">2</div>
                    <div class="timeline-content">
                        <h3>Instant Provisioning</h3>
                        <p>Once approved, our Super Admins spin up your isolated database environment in seconds.</p>
                    </div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-dot">3</div>
                    <div class="timeline-content">
                        <h3>Your Custom Dashboard</h3>
                        <p>You receive an invite to your brand new Admin Panel. Change the colors, add your staff, and start issuing loans immediately.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Security Section -->
    <section id="security" class="section bg-white security-trust-section">
        <div class="container container-flex security-shell">
            <div class="security-content security-panel">
                <span class="badge-pill badge-pill-accent">Bank-Grade Security</span>
                <h2 class="security-title">Your data is encrypted, isolated, and continuously backed up.</h2>
                <ul class="security-list">
                    <li>
                        <span class="material-symbols-rounded">check_circle</span>
                        <div class="security-copy">
                            <strong>Strict Tenant Isolation</strong>
                            <span>Every institution has its own dedicated database schema. Commingling of records is impossible.</span>
                        </div>
                    </li>
                    <li>
                        <span class="material-symbols-rounded">check_circle</span>
                        <div class="security-copy">
                            <strong>End-to-End Encryption</strong>
                            <span>All data in transit and at rest is secured using AES-256 and TLS 1.3 standards.</span>
                        </div>
                    </li>
                    <li>
                        <span class="material-symbols-rounded">check_circle</span>
                        <div class="security-copy">
                            <strong>Automated Backups & Redundancy</strong>
                            <span>Multi-region data replication ensures you never lose a single transaction record, even in hardware failure events.</span>
                        </div>
                    </li>
                </ul>
                <a href="#contact" class="btn btn-outline security-cta">Read our Security Whitepaper</a>
            </div>
            <div class="security-image security-vault-card">
                <span class="security-image-badge">Always-on resilience</span>
                <span class="material-symbols-rounded">gpp_good</span>
                <div>ISO 27001 & PCI-DSS Compliant Infrastructure</div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section id="contact" class="section text-white contact-cta-section">
        <div class="contact-cta-glow"></div>
        <div class="container contact-cta-container">
            <span class="contact-cta-badge">MicroFin Cloud Onboarding</span>
            <h2>Ready to modernize your cooperative?</h2>
            <p class="contact-cta-subtitle">Leave legacy desktop software behind. Let our team migrate your data to the cloud seamlessly.</p>
            <div class="contact-cta-buttons">
                <a href="demo.php" class="btn btn-primary btn-lg">
                    <span class="material-symbols-rounded" style="font-size: 20px; margin-right: 8px; vertical-align: middle;">calendar_month</span>
                    Apply Now
                </a>
                <button type="button" class="btn btn-outline btn-lg js-open-sarah-chat">
                    <span class="material-symbols-rounded" style="font-size: 20px; margin-right: 8px; vertical-align: middle;">smart_toy</span>
                    Chat with Sarah
                </button>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer footer-galaxy">
        <div class="container footer-grid">
            <div class="footer-brand">
                <div class="logo">
                    <img src="<?php echo htmlspecialchars($microfinLogoAsset, ENT_QUOTES, 'UTF-8'); ?>" alt="MicroFin logo" class="logo-mark">
                    <span class="logo-text">MicroFin</span>
                </div>
                <p>Cloud core banking for cooperatives, MFIs, and SACCOs.</p>
            </div>
            <div class="footer-links">
                <h4>Product</h4>
                <a href="#features">Core Banking</a>
                <a href="#security">Security</a>
                <a href="#pricing">Pricing</a>
            </div>
            <div class="footer-links">
                <h4>Company</h4>
                <a href="#how-it-works">How It Works</a>
                <a href="javascript:void(0)" class="js-open-sarah-chat">Chat with Sarah</a>
                <a href="../super_admin/login.php">Platform Login</a>
            </div>
        </div>
        <div class="container footer-bottom">
            <p>&copy; 2026 MicroFin Platform. All rights reserved.</p>
        </div>
    </footer>

    <?php require __DIR__ . '/sarah/widget.php'; ?>

    <script src="script.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var animatedLogo = document.querySelector('.navbar [data-animated-logo]');
            var animatedText = document.querySelector('.navbar [data-animated-logo-text]');

            if (!animatedLogo || !animatedText) {
                return;
            }

            var textTrack = animatedText.querySelector('.logo-text-track');
            var fullWord = animatedText.getAttribute('data-logo-word') || 'MicroFin';
            var activeTimers = [];
            var cycleToken = 0;
            var currentState = animatedLogo.getAttribute('data-logo-state') || 'static';
            var charNodes = [];
            var revealTimerId = 0;
            var revealStarted = false;

            if (!textTrack) {
                return;
            }

            function clearRevealTimer() {
                if (revealTimerId) {
                    window.clearTimeout(revealTimerId);
                    revealTimerId = 0;
                }
            }

            function clearTimers() {
                while (activeTimers.length > 0) {
                    window.clearTimeout(activeTimers.pop());
                }

                clearRevealTimer();
            }

            function queueStep(callback, delay) {
                var timerId = window.setTimeout(callback, delay);
                activeTimers.push(timerId);
            }

            function buildAnimatedLetters() {
                var fragment = document.createDocumentFragment();
                var spacingValue = window.getComputedStyle(animatedText).letterSpacing;
                var letterSpacing = spacingValue === 'normal' ? 0 : parseFloat(spacingValue);

                textTrack.textContent = '';
                charNodes = [];

                fullWord.split('').forEach(function (character, index) {
                    var shell = document.createElement('span');
                    var glyph = document.createElement('span');

                    shell.className = 'logo-char';
                    if (index === fullWord.length - 1) {
                        shell.classList.add('logo-char-last');
                    }
                    glyph.className = 'logo-char-glyph';
                    glyph.textContent = character;
                    shell.appendChild(glyph);

                    if (!Number.isNaN(letterSpacing) && index < fullWord.length - 1) {
                        shell.style.marginRight = letterSpacing + 'px';
                    }

                    fragment.appendChild(shell);
                    charNodes.push(shell);
                });

                textTrack.appendChild(fragment);
                syncCharacterWidths();
            }

            function syncCharacterWidths() {
                charNodes.forEach(function (node) {
                    var wasHidden = node.classList.contains('is-hidden');

                    if (wasHidden) {
                        node.classList.remove('is-hidden');
                    }

                    node.style.setProperty('--char-width', node.scrollWidth + 'px');

                    if (wasHidden) {
                        node.classList.add('is-hidden');
                    }
                });
            }

            function showAllCharactersInstant() {
                clearTimers();
                cycleToken++;
                revealStarted = false;
                charNodes.forEach(function (node) {
                    node.classList.remove('is-hidden');
                });
            }

            function runHideSequence(token) {
                var characterCount = charNodes.length;

                if (!characterCount) {
                    return;
                }

                var hideStepDelay = 108;
                revealStarted = false;

                charNodes.forEach(function (node) {
                    node.classList.remove('is-hidden');
                });

                charNodes.forEach(function (node, index) {
                    (function (charNode, stepIndex) {
                        queueStep(function () {
                            if (token !== cycleToken) {
                                return;
                            }

                            charNode.classList.add('is-hidden');
                        }, stepIndex * hideStepDelay);
                    }(node, index));
                });
            }

            function runRevealSequence(token) {
                clearTimers();
                revealStarted = true;

                var reversedNodes = charNodes.slice().reverse();
                var revealStepDelay = 102;

                reversedNodes.forEach(function (node, index) {
                    (function (charNode, stepIndex) {
                        queueStep(function () {
                            if (token !== cycleToken) {
                                return;
                            }

                            charNode.classList.remove('is-hidden');
                        }, stepIndex * revealStepDelay);
                    }(node, index));
                });
            }

            function startAnimatedTextCycle() {
                clearTimers();
                cycleToken++;

                var token = cycleToken;
                var totalDuration = Number(animatedLogo.getAttribute('data-logo-play-duration') || 9230);
                var revealWindow = ((charNodes.length - 1) * 102) + 290 + 160;
                var revealLeadIn = 220;
                var revealStartDelay = Math.max(0, totalDuration - revealWindow - revealLeadIn);

                runHideSequence(token);

                revealTimerId = window.setTimeout(function () {
                    revealTimerId = 0;
                    if (token !== cycleToken) {
                        return;
                    }

                    runRevealSequence(token);
                }, revealStartDelay);
            }

            buildAnimatedLetters();

            if (document.fonts && document.fonts.ready) {
                document.fonts.ready.then(syncCharacterWidths).catch(function () {});
            }

            var observer = new MutationObserver(function () {
                var nextState = animatedLogo.getAttribute('data-logo-state') || 'static';

                if (nextState === currentState) {
                    return;
                }

                currentState = nextState;

                if (nextState === 'animated') {
                    startAnimatedTextCycle();
                    return;
                }

                if (revealStarted) {
                    return;
                }

                if (revealTimerId) {
                    runRevealSequence(cycleToken);
                    return;
                }

                showAllCharactersInstant();
            });

            observer.observe(animatedLogo, {
                attributes: true,
                attributeFilter: ['data-logo-state']
            });

            if (currentState === 'animated') {
                startAnimatedTextCycle();
            } else {
                showAllCharactersInstant();
            }
        });
    </script>
    <script src="sarah/sarah-chatbot.js?v=<?php echo urlencode((string) @filemtime(__DIR__ . '/sarah/sarah-chatbot.js')); ?>"></script>
</body>
</html>



