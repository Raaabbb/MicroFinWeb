<?php
// Set up fallback data for the new Template 2 features if they aren't in the DB yet
$testi_1_text = $pageData['testi_1_text'] ?? '"MicroFin OS changed the way we handle our daily operations. Approvals are incredibly fast."';
$testi_1_name = $pageData['testi_1_name'] ?? '- Maria Santos, Small Business Owner';

$testi_2_text = $pageData['testi_2_text'] ?? '"Transparent fees and a beautiful app. I recommend them to everyone in my cooperative."';
$testi_2_name = $pageData['testi_2_name'] ?? '- Juan Dela Cruz, Freelancer';

$app_promo_title = $pageData['app_promo_title'] ?? 'Manage Your Loans on the Go';
$app_promo_desc  = $pageData['app_promo_desc'] ?? 'Download our secure mobile app to track payments, apply for new loans, and chat with our support team instantly.';

// Ensure toggles exist for the new sections
$show_testimonials = filter_var($pageData['show_testimonials'] ?? true, FILTER_VALIDATE_BOOLEAN);
$show_app_promo    = filter_var($pageData['show_app_promo'] ?? true, FILTER_VALIDATE_BOOLEAN);
?>

<style>
    :root {
        /* We derive a softer background from your primary brand color using opacity */
        --brand-light: color-mix(in srgb, var(--brand) 10%, white);
        
        /* Mapped to your new Builder Variables */
        --text-dark: var(--text-heading, #0f172a);
        --text-muted: var(--text-body, #64748b);
        --btn-bg: var(--btn-bg, var(--brand));
        --btn-text: var(--btn-text, #ffffff);
    }

    /* Neo-minimalist resets */
    .tpl2-wrapper {
        font-family: 'Public Sans', sans-serif;
        background-color: #ffffff;
        color: var(--text-dark);
    }

    .tpl2-nav {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.5rem 5%;
        border-bottom: 1px solid var(--border-clr);
    }

    .tpl2-nav-logo {
        height: 45px;
        object-fit: contain;
    }

    .tpl2-btn {
        background: var(--btn-bg);
        color: var(--btn-text);
        padding: 12px 28px;
        border-radius: var(--radius);
        text-decoration: none;
        font-weight: 700;
        transition: transform 0.2s, box-shadow 0.2s;
        border: none;
        display: inline-block;
    }

    .tpl2-btn:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow);
        color: var(--btn-text);
    }

    /* Split Hero */
    .tpl2-hero {
        display: flex;
        align-items: center;
        min-height: 80vh;
        padding: 4rem 5%;
        gap: 4rem;
        <?php echo getBgStyle('sec_hero', $sec_styles, '#ffffff'); ?>
    }

    .tpl2-hero-content {
        flex: 1;
    }

    .tpl2-hero-title {
        font-size: 3.5rem;
        font-weight: 800;
        line-height: 1.1;
        margin-bottom: 1.5rem;
        letter-spacing: -1px;
        color: var(--text-dark); /* Enforces heading color */
    }

    .tpl2-hero-title span {
        color: var(--brand);
    }

    .tpl2-hero-image-wrap {
        flex: 1;
        position: relative;
    }

    .tpl2-hero-image {
        width: 100%;
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        object-fit: cover;
        aspect-ratio: 4/3;
    }

    /* Minimalist Services */
    .tpl2-services {
        padding: 6rem 5%;
        background-color: #f8fafc;
        <?php echo getBgStyle('sec_services', $sec_styles, '#f8fafc'); ?>
    }

    .tpl2-services-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 2rem;
        margin-top: 3rem;
    }

    .tpl2-service-card {
        background: white;
        padding: 2.5rem;
        border-radius: var(--radius);
        border: 1px solid var(--border-clr);
        transition: all 0.3s ease;
        position: relative;
    }

    .tpl2-service-card:hover {
        border-color: var(--brand);
        box-shadow: var(--shadow);
    }

    .tpl2-service-icon {
        background: var(--brand-light);
        color: var(--brand);
        width: 60px;
        height: 60px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        margin-bottom: 1.5rem;
    }

    /* Testimonials (NEW) */
    .tpl2-testimonials {
        padding: 6rem 5%;
        <?php echo getBgStyle('sec_testimonials', $sec_styles, '#ffffff'); ?>
    }

    .tpl2-testi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 3rem;
    }

    .tpl2-testi-card {
        padding: 2rem;
        border-left: 4px solid var(--brand);
        background: var(--brand-light);
        border-radius: 0 var(--radius) var(--radius) 0;
    }

    /* App Promo (NEW) */
    .tpl2-app-promo {
        margin: 4rem 5%;
        padding: 4rem;
        background: var(--text-dark); /* Re-uses heading color for app bg */
        color: white; /* Forces text inside to be white to contrast */
        border-radius: var(--radius);
        text-align: center;
        <?php echo getBgStyle('sec_app', $sec_styles, 'var(--text-dark)'); ?>
    }

    /* Footer */
    .tpl2-footer {
        padding: 4rem 5%;
        background: #020617;
        color: #94a3b8;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .tpl2-onboarding {
        margin-top: 2rem;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1rem;
        text-align: left;
    }

    .tpl2-onboarding-card {
        padding: 1.2rem;
        border-radius: var(--radius);
        border: 1px solid rgba(255, 255, 255, 0.14);
        background: rgba(255, 255, 255, 0.08);
    }

    .tpl2-onboarding-card small {
        display: block;
        font-weight: 800;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        opacity: 0.65;
        margin-bottom: 0.6rem;
    }

    .tpl2-onboarding-card strong {
        display: block;
        font-size: 1rem;
        margin-bottom: 0.4rem;
    }

    .tpl2-onboarding-card p {
        margin: 0;
        color: rgba(255, 255, 255, 0.78);
        line-height: 1.6;
    }

    .tpl2-qr-stack {
        margin-top: 1.5rem;
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        align-items: center;
        justify-content: center;
    }

    .tpl2-qr-box {
        width: 180px;
        height: 180px;
        border-radius: 24px;
        background: #fff;
        padding: 12px;
        object-fit: contain;
    }

    .tpl2-referral-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.6rem;
        padding: 0.95rem 1.15rem;
        border-radius: 999px;
        border: 1px solid rgba(255, 255, 255, 0.14);
        background: rgba(255, 255, 255, 0.08);
        color: #fff;
        font-weight: 700;
    }

    .tpl2-qr-toggle {
        margin-top: 1.5rem;
    }

    .tpl2-qr-toggle summary {
        list-style: none;
    }

    .tpl2-qr-toggle summary::-webkit-details-marker {
        display: none;
    }

    .tpl2-qr-toggle-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.7rem;
        padding: 1rem 1.4rem;
        border-radius: 999px;
        border: 1px solid rgba(255, 255, 255, 0.16);
        background: rgba(255, 255, 255, 0.08);
        color: #fff;
        font-weight: 700;
        cursor: pointer;
        transition: transform 0.2s ease, background 0.2s ease;
    }

    .tpl2-qr-toggle-btn:hover {
        background: rgba(255, 255, 255, 0.14);
        transform: translateY(-1px);
    }

    .tpl2-qr-panel {
        margin-top: 1rem;
    }
</style>

<div class="tpl2-wrapper">
    
    <nav class="tpl2-nav">
        <div>
            <?php if ($logo): ?>
                <img src="<?php echo $e($logo); ?>" alt="Logo" class="tpl2-nav-logo" id="preview_logo">
            <?php else: ?>
                <h2 class="fw-bold m-0" style="color: var(--text-dark);" class="display-company-name"><?php echo $e($display_name); ?></h2>
            <?php endif; ?>
        </div>
        <div class="d-none d-md-flex gap-4 fw-bold" style="color: var(--text-muted);">
            <a href="#" class="text-decoration-none text-reset">Products</a>
            <a href="#" class="text-decoration-none text-reset">Reviews</a>
            <a href="#" class="text-decoration-none text-reset">Contact</a>
        </div>
        <?php 
        $tid_val = $tenant_id ?? ($data['tenant_id'] ?? '');
        $is_editor = isset($is_editor_context)
            ? (bool)$is_editor_context
            : (strpos($_SERVER['PHP_SELF'], 'editor') !== false || strpos($_SERVER['PHP_SELF'], 'setup') !== false);
        $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        if (strpos($script, '/admin_panel/website_editor/') !== false) {
            $platform_base = dirname(dirname(dirname($script)));
        } elseif (strpos($script, '/public_website/') !== false) {
            $platform_base = dirname(dirname($script));
        } else {
            $platform_base = dirname($script);
        }
        $platform_base = rtrim(str_replace('\\', '/', (string)$platform_base), '/');
        $login_query = 'tenant=' . urlencode($tid_val);
        if (!$is_editor) {
            $login_query .= '&from_site=1';
        }
        $login_href = $platform_base . '/tenant_login/login.php?' . $login_query;
        $download_identifier = (string)($site_slug ?? $tenant_slug ?? $tid_val);
        $download_href = $download_href ?? ($platform_base . '/public_website/index.php?route=get-app&bank_id=' . urlencode($download_identifier));
        $tenant_referral_code_value = trim((string)($tenant_referral_code ?? $tenant_slug ?? $site_slug ?? ''));
        $tenant_qr_url = trim((string)($tenant_reference_qr_url ?? ''));
        ?>
        <a href="<?php echo $login_href; ?>" class="tpl2-btn" contenteditable="false">Staff Portal</a>
    </nav>

    <section id="sec_hero" class="tpl2-hero editable-section">
        <div class="tpl2-hero-content">
            <div class="badge bg-light text-primary border border-primary mb-3 px-3 py-2 rounded-pill fw-bold shadow-sm" contenteditable="true" data-edit="hero_badge_text">
                <?php echo $e($hero_badge_text); ?>
            </div>
            
            <h1 class="tpl2-hero-title" contenteditable="true" data-edit="hero_title">
                <?php echo $e($hero_title); ?>
            </h1>
            
            <p class="fs-5 mb-4 pe-md-5" style="color: var(--text-muted);" contenteditable="true" data-edit="hero_subtitle">
                <?php echo $e($hero_subtitle); ?>
            </p>
            
        </div>
        
        <div class="tpl2-hero-image-wrap" id="hero_img_container">
            <button id="btn_open_hero_picker" class="btn btn-dark btn-sm position-absolute top-50 start-50 translate-middle z-3 shadow" style="display:none;">Change Image</button>
            
            <img src="<?php echo $e($display_image); ?>" alt="Hero" class="tpl2-hero-image" id="preview_hero" onmouseover="document.getElementById('btn_open_hero_picker').style.display='block'" onmouseout="setTimeout(()=>document.getElementById('btn_open_hero_picker').style.display='none', 2000)">
        </div>
    </section>

    <section id="sec_services" class="tpl2-services editable-section" style="display: <?php echo $show_services ? 'block' : 'none'; ?>">
        <div class="text-center max-w-2xl mx-auto mb-5">
            <h2 class="fw-bold h1 mb-3" style="color: var(--text-dark);">Financial tools designed for you.</h2>
            <p class="fs-5" style="color: var(--text-muted);">Everything you need to grow your business or manage personal expenses.</p>
        </div>

        <div class="tpl2-services-grid" id="services_container">
            <?php foreach ($services as $index => $srv): ?>
                <div class="tpl2-service-card service-col">
                    <button class="btn btn-sm btn-outline-danger position-absolute top-0 end-0 m-3 delete-card" contenteditable="false" style="opacity: 0.5;">×</button>
                    
                    <div class="tpl2-service-icon editable-icon-wrap" title="Click to change icon">
                        <span class="material-symbols-rounded fs-1 service-icon-text"><?php echo $e($srv['icon']); ?></span>
                    </div>
                    
                    <h3 class="fw-bold h4 mb-2 service-title" style="color: var(--text-dark);" contenteditable="true"><?php echo $e($srv['title']); ?></h3>
                    <p class="mb-0 service-desc" style="color: var(--text-muted);" contenteditable="true"><?php echo $e($srv['description']); ?></p>
                </div>
            <?php endforeach; ?>
            
            <div class="tpl2-service-card d-flex align-items-center justify-content-center cursor-pointer border-dashed" id="add_service_col" onclick="addServiceCard()" style="border-style: dashed; background: transparent;">
                <div class="text-center" style="color: var(--text-muted);">
                    <span class="material-symbols-rounded fs-1 mb-2">add_circle</span>
                    <p class="fw-bold mb-0">Add Service</p>
                </div>
            </div>
        </div>
    </section>

    <section id="sec_testimonials" class="tpl2-testimonials editable-section" style="display: <?php echo $show_testimonials ? 'block' : 'none'; ?>">
        <h2 class="fw-bold h1 mb-5 text-center" style="color: var(--text-dark);">Trusted by the Community</h2>
        
        <div class="tpl2-testi-grid">
            <div class="tpl2-testi-card">
                <p class="fs-5 fst-italic mb-3" style="color: var(--text-dark);" contenteditable="true" data-edit="testi_1_text"><?php echo $e($testi_1_text); ?></p>
                <p class="fw-bold mb-0" style="color: var(--text-muted);" contenteditable="true" data-edit="testi_1_name"><?php echo $e($testi_1_name); ?></p>
            </div>
            
            <div class="tpl2-testi-card">
                <p class="fs-5 fst-italic mb-3" style="color: var(--text-dark);" contenteditable="true" data-edit="testi_2_text"><?php echo $e($testi_2_text); ?></p>
                <p class="fw-bold mb-0" style="color: var(--text-muted);" contenteditable="true" data-edit="testi_2_name"><?php echo $e($testi_2_name); ?></p>
            </div>
        </div>
    </section>

    <section id="sec_app" class="editable-section" style="display: <?php echo $show_app_promo ? 'block' : 'none'; ?>">
        <div class="tpl2-app-promo">
            <span class="material-symbols-rounded mb-3" style="font-size: 3rem; color: var(--brand);">smartphone</span>
            <h2 class="fw-bold display-5 mb-3 text-white" contenteditable="true" data-edit="app_promo_title"><?php echo $e($app_promo_title ?: 'Join through the MicroFin app'); ?></h2>
            <p class="fs-5 mb-4 opacity-75 mx-auto text-white" style="max-width: 600px;" contenteditable="true" data-edit="app_promo_desc"><?php echo $e($app_promo_desc ?: 'Install the MicroFin app, open Create Account, then use this tenant QR or referral code so your registration is linked to the correct institution.'); ?></p>
            <div class="d-flex gap-3 justify-content-center">
                <a href="<?php echo $e($download_href); ?>" 
                   class="btn btn-light fw-bold px-4 py-3 d-flex align-items-center gap-2 text-decoration-none" 
                    style="border-radius: var(--radius); color: #000;" contenteditable="false">
                    <span class="material-symbols-rounded">download</span> Download App
                </a>
            </div>
            <div class="tpl2-onboarding">
                <div class="tpl2-onboarding-card">
                    <small>Step 1</small>
                    <strong>Install the MicroFin app</strong>
                    <p>The download button installs the company-branded MicroFin mobile app.</p>
                </div>
                <div class="tpl2-onboarding-card">
                    <small>Step 2</small>
                    <strong>Open Create Account and use the QR button below</strong>
                    <p>That button reveals this institution QR so the app can unlock the Create Account form with the correct <strong>@<?php echo $e($tenant_slug ?? $site_slug ?? 'tenant'); ?></strong> suffix.</p>
                </div>
                <div class="tpl2-onboarding-card">
                    <small>Step 3</small>
                    <strong>Referral code fallback</strong>
                    <p>If scanning is unavailable, enter <strong><?php echo $e($tenant_referral_code_value !== '' ? $tenant_referral_code_value : ($tenant_slug ?? $site_slug ?? '')); ?></strong> manually inside the app.</p>
                </div>
            </div>
            <details class="tpl2-qr-toggle" contenteditable="false">
                <summary class="tpl2-qr-toggle-btn">
                    <span class="material-symbols-rounded">help</span>
                    Downloaded the app already? Show the registration QR
                </summary>
                <div class="tpl2-qr-panel">
                    <div class="tpl2-qr-stack">
                        <?php if ($tenant_qr_url !== ''): ?>
                            <img src="<?php echo $e($tenant_qr_url); ?>" alt="Tenant registration QR code" class="tpl2-qr-box">
                        <?php else: ?>
                            <div class="tpl2-qr-box d-flex align-items-center justify-content-center text-center" style="background: rgba(255,255,255,0.12); color: #fff;">
                                Publish the site to generate the tenant QR code.
                            </div>
                        <?php endif; ?>
                        <div class="tpl2-referral-pill">
                            <span class="material-symbols-rounded">confirmation_number</span>
                            Referral Code: <?php echo $e($tenant_referral_code_value !== '' ? $tenant_referral_code_value : ($tenant_slug ?? $site_slug ?? '')); ?>
                        </div>
                    </div>
                </div>
            </details>
        </div>
    </section>

    <footer class="tpl2-footer">
        <div>
            <h4 class="fw-bold text-white mb-2 display-company-name"><?php echo $e($display_name); ?></h4>
            <p class="mb-0 text-white opacity-75" style="max-width: 300px;" contenteditable="true" data-edit="footer_desc"><?php echo $e($footer_desc); ?></p>
        </div>
        <div class="text-end text-white opacity-75">
            <p class="mb-1" contenteditable="true" data-edit="contact_email"><?php echo $e($contact_email); ?></p>
            <p class="mb-0" contenteditable="true" data-edit="contact_phone"><?php echo $e($contact_phone); ?></p>
        </div>
    </footer>

</div>
