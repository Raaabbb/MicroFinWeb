<?php
/**
 * Template 3 — Fundline Modern Enterprise
 * 
 * This template powers both the live editor canvas (contenteditable) AND the public site.
 * When loaded inside the editor (admin_panel/website_editor/index.php), JS enables contenteditable.
 * When loaded via site.php, the contenteditable attributes are stripped by output buffering.
 * 
 * Expected variables (set by calling page):
 *   $primary, $border_color, $border_radius, $shadow,
 *   $text_heading_color, $text_body_color, $btn_bg_color, $btn_text_color,
 *   $logo, $company_name, $display_name, $short_name,
 *   $hero_title, $hero_subtitle, $hero_badge_text, $display_image,
 *   $about_body, $download_description, $footer_desc,
 *   $contact_address, $contact_phone, $contact_email, $contact_hours,
 *   $services (array), $loan_products (array), $sec_styles (array),
 *   $show_services, $show_stats, $show_loan_calc, $show_about, $show_download,
 *   $e (htmlspecialchars helper), getBgStyle() function
 */

$loan_calc_products = is_array($loan_products ?? null) ? array_values($loan_products) : [];
$loan_calc_default = $loan_calc_products[0] ?? [
    'product_name' => 'Demo Personal Loan',
    'product_type' => 'Personal Loan',
    'interest_rate' => 2.5,
    'interest_type' => 'Flat',
    'min_amount' => 1000,
    'max_amount' => 50000,
    'min_term_months' => 1,
    'max_term_months' => 12,
    'processing_fee_percentage' => 5,
    'insurance_fee_percentage' => 0,
    'service_charge' => 0,
    'documentary_stamp' => 0,
];

$loan_calc_step = static function (float $min, float $max): int {
    $range = max(0, $max - $min);
    if ($range <= 20000) return 50;
    if ($range <= 100000) return 100;
    if ($range <= 500000) return 500;
    return 1000;
};

$loan_calc_money = static function (float $amount): string {
    return '&#8369;' . number_format(round($amount), 0);
};

$loan_calc_min_amount = max(0, (float)($loan_calc_default['min_amount'] ?? 1000));
$loan_calc_max_amount = max($loan_calc_min_amount, (float)($loan_calc_default['max_amount'] ?? 50000));
$loan_calc_amount_step = $loan_calc_step($loan_calc_min_amount, $loan_calc_max_amount);
$loan_calc_amount_value = $loan_calc_min_amount;
if ($loan_calc_max_amount > $loan_calc_min_amount) {
    $loan_calc_amount_value = round((($loan_calc_min_amount + $loan_calc_max_amount) / 2) / $loan_calc_amount_step) * $loan_calc_amount_step;
    $loan_calc_amount_value = min($loan_calc_max_amount, max($loan_calc_min_amount, $loan_calc_amount_value));
}

$loan_calc_min_term = max(1, (int)($loan_calc_default['min_term_months'] ?? 1));
$loan_calc_max_term = max($loan_calc_min_term, (int)($loan_calc_default['max_term_months'] ?? 12));
$loan_calc_term_value = max($loan_calc_min_term, min($loan_calc_max_term, (int)round(($loan_calc_min_term + $loan_calc_max_term) / 2)));

$loan_calc_rate = max(0, (float)($loan_calc_default['interest_rate'] ?? 2.5)) / 100;
$loan_calc_type = (string)($loan_calc_default['interest_type'] ?? 'Flat');
$loan_calc_processing = max(0, (float)($loan_calc_default['processing_fee_percentage'] ?? 5));
$loan_calc_insurance = max(0, (float)($loan_calc_default['insurance_fee_percentage'] ?? 0));
$loan_calc_service_charge = max(0, (float)($loan_calc_default['service_charge'] ?? 0));
$loan_calc_doc_stamp = max(0, (float)($loan_calc_default['documentary_stamp'] ?? 0));

$loan_calc_monthly = 0.0;
$loan_calc_interest_total = 0.0;
$loan_calc_total = 0.0;

if ($loan_calc_type === 'Diminishing') {
    $loan_calc_monthly = $loan_calc_rate > 0
        ? $loan_calc_amount_value * ($loan_calc_rate * pow(1 + $loan_calc_rate, $loan_calc_term_value)) / (pow(1 + $loan_calc_rate, $loan_calc_term_value) - 1)
        : ($loan_calc_amount_value / max(1, $loan_calc_term_value));
    $loan_calc_total = $loan_calc_monthly * $loan_calc_term_value;
    $loan_calc_interest_total = $loan_calc_total - $loan_calc_amount_value;
} else {
    $loan_calc_interest_total = $loan_calc_amount_value * $loan_calc_rate * $loan_calc_term_value;
    $loan_calc_total = $loan_calc_amount_value + $loan_calc_interest_total;
    $loan_calc_monthly = $loan_calc_total / max(1, $loan_calc_term_value);
}

$loan_calc_fee_total = ($loan_calc_amount_value * ($loan_calc_processing / 100))
    + ($loan_calc_amount_value * ($loan_calc_insurance / 100))
    + $loan_calc_service_charge
    + $loan_calc_doc_stamp;
?>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&family=Outfit:wght@500;700;800&display=swap');
    @import url('https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap');

    :root {
        --bs-primary: <?php echo $primary ?? '#ec1313'; ?>;
        --bs-primary-rgb: <?php echo isset($primary) ? implode(', ', array_map('hexdec', str_split(ltrim($primary, '#'), 2))) : '236, 19, 19'; ?>;
        --color-primary: <?php echo $primary ?? '#ec1313'; ?>;
        --color-primary-hover: <?php echo $primary ?? '#d11111'; ?>;
        --color-primary-light: rgba(var(--bs-primary-rgb), 0.08);
        --color-primary-dark: <?php echo $primary ?? '#b30f0f'; ?>;

        --bs-card-bg: <?php echo $btn_text_color ?? '#ffffff'; ?>;

        --color-surface-light: #ffffff;
        --color-surface-dark: #1a1a1a;
        --color-surface-light-alt: #f8fafc;
        --color-surface-dark-alt: #242424;

        --color-background-light: #f3f4f6;
        --color-background-dark: #0f0f0f;

        --color-text-main: <?php echo $text_heading_color ?? '#0f172a'; ?>;
        --color-text-muted: <?php echo $text_body_color ?? '#64748b'; ?>;
        --color-text-dark: #ffffff;
        --color-text-light: #ffffff;

        --color-border-subtle: <?php echo $border_color ?? '#e2e8f0'; ?>;
        --color-border-medium: #cbd5e1;
        --color-border-dark: rgba(255, 255, 255, 0.1);

        --color-hover-light: #f1f5f9;
        --color-hover-dark: rgba(255, 255, 255, 0.05);

        --bs-body-font-family: 'Manrope', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        --font-weight-normal: 400;
        --font-weight-medium: 500;
        --font-weight-semibold: 600;
        --font-weight-bold: 700;
        --font-weight-extrabold: 800;

        --radius-sm: 0.375rem;
        --radius-md: 0.5rem;
        --radius-lg: 0.75rem;
        --radius-xl: 1rem;
        --radius-2xl: <?php echo $border_radius ?? '1.25'; ?>rem;
        --radius-full: 9999px;

        --shadow-xs: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.06);
        --shadow-card: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.06);
        --shadow-hover: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.05);
        --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
        --transition-normal: 300ms cubic-bezier(0.4, 0, 0.2, 1);
    }

    body {
        font-family: var(--bs-body-font-family);
        color: var(--color-text-main);
        background-color: var(--color-background-light);
    }
    
    h1, h2, h3, h4, h5, h6, .h1, .h2, .h3, .h4, .h5, .h6 {
        font-weight: var(--font-weight-bold);
        color: var(--color-text-main);
    }
    .text-primary {
        color: var(--color-primary) !important;
    }
    .text-main {
        color: var(--color-text-main) !important;
    }
    .bg-main {
        background-color: var(--color-background-light) !important;
    }
    .bg-primary {
        background-color: var(--color-primary) !important;
    }
    .bg-surface {
        background-color: var(--bs-card-bg) !important;
    }
    .text-muted {
        color: var(--color-text-muted) !important;
    }
    .btn-primary {
        background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-dark) 100%);
        box-shadow: 0 4px 14px rgba(var(--bs-primary-rgb), 0.25);
        color: <?php echo $btn_text_color ?? '#ffffff'; ?>;
        border: none;
        border-radius: var(--radius-full);
        transition: all var(--transition-normal);
        font-weight: var(--font-weight-semibold);
    }
    .btn-primary:hover {
        background: linear-gradient(135deg, var(--color-primary-hover) 0%, var(--color-primary-dark) 100%);
        box-shadow: 0 6px 20px rgba(var(--bs-primary-rgb), 0.35);
        transform: translateY(-1px);
        color: <?php echo $btn_text_color ?? '#ffffff'; ?>;
    }
    .btn-outline-primary {
        border: 2px solid var(--color-primary);
        color: var(--color-primary);
        background: transparent;
        border-radius: var(--radius-full);
        font-weight: var(--font-weight-semibold);
    }
    .btn-outline-primary:hover {
        background: var(--color-primary);
        border-color: var(--color-primary);
        color: white;
        transform: translateY(-1px);
    }
    .hover-lift {
        transition: transform var(--transition-normal), box-shadow var(--transition-normal);
    }
    .hover-lift:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1) !important;
    }
    
    .hero-section {
        position: relative;
        min-height: 100vh;
        display: flex;
        align-items: center;
        padding-top: 80px;
        background: linear-gradient(to right, var(--color-surface-light) 0%, rgba(255,255,255,0.95) 40%, transparent 70%);
        overflow: hidden;
    }
    .hero-bg {
        position: absolute;
        top: 0;
        right: 0;
        width: 50%;
        height: 100%;
        background-size: cover;
        background-position: center;
        mask-image: linear-gradient(to right, transparent, black);
        -webkit-mask-image: linear-gradient(to right, transparent, black);
        opacity: 0.8;
        z-index: 0;
    }
    @media (max-width: 991.98px) {
        .hero-section {
            background: linear-gradient(to bottom, rgba(255,255,255,0.95) 0%, rgba(255,255,255,0.85) 100%);
            padding-bottom: 60px;
        }
        .hero-bg {
            width: 100%;
            height: 100%;
            opacity: 0.15;
            mask-image: none;
            -webkit-mask-image: none;
        }
    }
    
    .feature-icon {
        width: 64px;
        height: 64px;
        border-radius: var(--radius-xl);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 32px;
        margin-bottom: 1.5rem;
        background: rgba(var(--bs-primary-rgb), 0.1);
        color: var(--color-primary);
        transition: all 0.3s ease;
    }
    .card:hover .feature-icon {
        transform: scale(1.1) rotate(5deg);
        background: var(--color-primary);
        color: white;
    }

    .lc-product-btn {
        border-radius: calc(var(--radius-xl) - 2px);
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    }
    .lc-product-btn:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-sm);
    }
    
    .fade-in-up {
        animation: fadeInUp 0.8s ease-out forwards;
        opacity: 0;
        transform: translateY(20px);
    }
    .delay-100 { animation-delay: 0.1s; }
    .delay-200 { animation-delay: 0.2s; }
    .delay-300 { animation-delay: 0.3s; }
    
    @keyframes fadeInUp {
        to { opacity: 1; transform: translateY(0); }
    }

	.shared-app-steps {
        display: grid;
        gap: 12px;
        margin-top: 28px;
    }

    .shared-app-step {
        padding: 16px 18px;
        border-radius: 18px;
        border: 1px solid rgba(var(--bs-primary-rgb), 0.18);
        background: rgba(var(--bs-primary-rgb), 0.05);
        text-align: left;
    }

    .shared-app-step-label {
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        opacity: 0.6;
        color: var(--color-primary);
        margin-bottom: 8px;
    }

    .shared-app-step-title {
        font-size: 1rem;
        font-weight: 700;
        color: var(--color-text-main);
        margin-bottom: 4px;
    }

    .shared-app-step-copy {
        font-size: 0.92rem;
        line-height: 1.6;
        color: var(--color-text-muted);
        margin: 0;
    }

    .shared-app-qr-card {
        margin-top: 28px;
        padding: 18px;
        border-radius: 24px;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(0, 0, 0, 0.16);
        display: inline-flex;
        flex-direction: column;
        align-items: center;
        gap: 14px;
        min-width: 220px;
    }

    .shared-app-qr-card img {
        width: 180px;
        height: 180px;
        border-radius: 18px;
        background: #fff;
        padding: 12px;
        object-fit: contain;
    }

    .shared-app-code {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 12px 16px;
        border-radius: 999px;
        background: rgba(var(--bs-primary-rgb), 0.1);
        border: 1px solid rgba(var(--bs-primary-rgb), 0.2);
        color: var(--color-primary);
        font-weight: 700;
    }

    .shared-app-qr-toggle {
        margin-top: 28px;
    }

    .shared-app-qr-toggle summary {
        list-style: none;
    }

    .shared-app-qr-toggle summary::-webkit-details-marker {
        display: none;
    }

    .shared-app-qr-toggle-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding: 14px 22px;
        border-radius: 999px;
        border: 1px solid rgba(0, 0, 0, 0.2);
        background: rgba(0, 0, 0, 0.05);
        color: var(--color-text-main);
        font-weight: 700;
        cursor: pointer;
        transition: transform 0.2s ease, background 0.2s ease;
    }

    .shared-app-qr-toggle-button:hover {
        background: rgba(0, 0, 0, 0.1);
        transform: translateY(-1px);
    }
</style>

<!-- Navigation -->
<nav class="navbar navbar-expand-lg fixed-top bg-surface shadow-sm py-3 site-nav">
    <div class="container">
        <a class="navbar-brand d-flex flex-column lh-1 text-decoration-none" href="#">
            <img id="preview_logo" src="<?php echo $e($logo ?? ''); ?>"
                style="height:36px; <?php if (!($logo ?? '')) echo 'display:none;'; ?>">
            <span class="d-flex align-items-center display-short-name" style="font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 2rem; letter-spacing: -2px; color: var(--color-primary);" contenteditable="false">
                <?php echo $e($display_name ?? 'fundline'); ?>
            </span>
        </a>
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
        <div class="ms-auto" id="navbarNav">
            <ul class="navbar-nav align-items-center gap-3">
                <li class="nav-item ms-lg-3">
                    <a href="<?php echo $login_href; ?>" class="btn btn-outline-primary px-4 rounded-pill fw-bold" contenteditable="false">Login</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<section id="sec_hero" class="hero-section editable-section" style="<?php echo getBgStyle('sec_hero', $sec_styles, ''); ?>">
    <div class="hero-bg" id="hero_img_container" style="background-image: url('<?php echo $e($display_image ?? 'landing_banner.png'); ?>');"></div>
    <div class="container position-relative z-1" style="pointer-events: none;">
        <div class="row align-items-center" style="pointer-events: auto;">
            <div class="col-lg-6">
                <div class="pe-lg-5">
                    <span class="badge bg-primary bg-opacity-10 text-primary mb-3 px-3 py-2 rounded-pill fw-bold fade-in-up" data-edit="hero_badge_text" contenteditable="true">
                        <?php echo $e($hero_badge_text ?? '🚀 Official Lending Partner'); ?>
                    </span>
                    <h1 class="display-3 fw-bolder mb-4 text-main lh-sm fade-in-up delay-100" data-edit="hero_title" contenteditable="true">
                        <?php echo $e($hero_title ?? 'Business & Personal Loans, Approved in 24 Hours.'); ?>
                    </h1>
                    <p class="lead text-muted mb-5 fade-in-up delay-200" data-edit="hero_subtitle" contenteditable="true">
                        <?php echo $e($hero_subtitle ?? 'Apply for Business, Education, Housing, or Emergency loans completely online. Track your credit limit, manage payments, and get funded without leaving your home.'); ?>
                    </p>
                    <div class="d-flex gap-3 fade-in-up delay-300">
                        <button class="btn btn-primary btn-lg rounded-pill px-5 py-3 fw-bold shadow-lg hover-lift d-flex align-items-center gap-2" contenteditable="false">
                            Apply Now
                            <span class="material-symbols-outlined">arrow_forward</span>
                        </button>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 text-center text-lg-end pt-5 pt-lg-0 fade-in-up delay-300">
                 <button type="button" id="btn_open_hero_picker" class="btn btn-primary shadow-sm" contenteditable="false"><?php echo $e(($display_image ?? '') !== '' ? 'Change Background Cover' : 'Upload BG Cover'); ?></button>
            </div>
        </div>
    </div>
</section>

<!-- Dashboard Preview Section (Stats) -->
<?php
$show_stats_val = $show_stats ?? true;
if (is_string($show_stats_val)) $show_stats_val = filter_var($show_stats_val, FILTER_VALIDATE_BOOLEAN);
?>
<section id="sec_stats" class="py-5 bg-surface overflow-hidden editable-section" style="<?php echo getBgStyle('sec_stats', $sec_styles, '#ffffff'); ?> <?php if (!$show_stats_val) echo 'display:none;'; ?>">
    <div class="container py-5">
        <div class="row align-items-center g-5">
            <div class="col-lg-5 order-lg-2">
                <span class="text-primary fw-bold text-uppercase ls-1">Powerful System</span>
                <h2 class="display-5 fw-bold mb-4 text-main" contenteditable="true">Complete Financial Control Dashboard</h2>
                <div class="text-muted lead mb-4" data-edit="about_body" contenteditable="true"><?php echo $e($about_body ?? 'See exactly what our clients see. A powerful, intuitive dashboard to manage your financial life.'); ?></div>
                <ul class="list-unstyled d-flex flex-column gap-3 mb-4" contenteditable="false">
                    <li class="d-flex align-items-center gap-3">
                        <span class="material-symbols-outlined text-primary fs-4">check_circle</span>
                        <span class="fs-5 text-main">Real-time Credit Limit Monitoring</span>
                    </li>
                    <li class="d-flex align-items-center gap-3">
                        <span class="material-symbols-outlined text-primary fs-4">check_circle</span>
                        <span class="fs-5 text-main">Track Active Loans & Payment History</span>
                    </li>
                    <li class="d-flex align-items-center gap-3">
                        <span class="material-symbols-outlined text-primary fs-4">check_circle</span>
                        <span class="fs-5 text-main">Instant Loan Calculator</span>
                    </li>
                </ul>
            </div>
            <div class="col-lg-7 order-lg-1" contenteditable="false">
                <div class="card border-0 shadow-xl rounded-4 overflow-hidden bg-body-tertiary" style="transform: perspective(1000px) rotateY(10deg) rotateX(2deg); transition: transform 0.5s ease; border: 1px solid var(--color-border-subtle);">
                    <div class="card-header bg-white border-bottom p-3 d-flex align-items-center justify-content-between text-muted">
                        <div class="d-flex align-items-center gap-2">
                            <div class="rounded-circle bg-danger" style="width: 12px; height: 12px;"></div>
                            <div class="rounded-circle bg-warning" style="width: 12px; height: 12px;"></div>
                            <div class="rounded-circle bg-success" style="width: 12px; height: 12px;"></div>
                        </div>
                        <div class="small bg-light px-3 py-1 rounded-pill">dashboard.preview</div>
                    </div>
                    <div class="card-body p-4 bg-light">
                        <div class="card border-0 text-white mb-4 rounded-4" style="background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-dark) 100%);">
                            <div class="card-body p-4 position-relative overflow-hidden">
                                <h4 class="fw-bold mb-1">Welcome back, Maria!</h4>
                                <p class="mb-0 opacity-75">Your remaining credit limit is ₱150,000.00</p>
                                <span class="material-symbols-outlined position-absolute opacity-25" style="font-size: 6rem; right: -20px; bottom: -20px;">account_balance</span>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="card border-0 shadow-sm rounded-4 h-100">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between mb-2">
                                            <div class="bg-success bg-opacity-10 text-success p-2 rounded-3">
                                                <span class="material-symbols-outlined">payments</span>
                                            </div>
                                        </div>
                                        <h3 class="fw-bold mb-0 text-dark">₱45,200</h3>
                                        <small class="text-muted">Total Paid</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="card border-0 shadow-sm rounded-4 h-100">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between mb-2">
                                            <div class="bg-primary bg-opacity-10 text-primary p-2 rounded-3">
                                                <span class="material-symbols-outlined">pending</span>
                                            </div>
                                        </div>
                                        <h3 class="fw-bold mb-0 text-dark">Active</h3>
                                        <small class="text-muted">Loan Status</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Loan Products Section -->
<?php
$show_services_val = $show_services ?? true;
if (is_string($show_services_val)) $show_services_val = filter_var($show_services_val, FILTER_VALIDATE_BOOLEAN);
?>
<section id="sec_services" class="py-5 editable-section" style="<?php echo getBgStyle('sec_services', $sec_styles, '#f8fafc'); ?> <?php if (!$show_services_val) echo 'display:none;'; ?>">
    <div class="container py-5">
        <div class="text-center mb-5 mw-lg mx-auto" style="max-width: 700px;">
            <span class="text-primary fw-bold text-uppercase ls-1">Tailored For You</span>
            <h2 class="display-5 fw-bold mb-3 text-main">Our Loan Products</h2>
            <p class="text-muted lead">Flexible financing solutions designed for every stage of your life and business.</p>
        </div>
        
        <div class="row g-4" id="services_row">
            <?php foreach (($services ?? []) as $index => $svc): ?>
                <div class="col-md-4 service-col">
                    <div class="card h-100 p-4 border-0 shadow-sm hover-lift position-relative">
                        <button class="btn btn-sm btn-danger position-absolute top-0 end-0 m-2 delete-card" contenteditable="false">×</button>
                        <div class="feature-icon editable-icon-wrap" title="Click to select a new icon">
                            <span class="material-symbols-outlined service-icon-text"><?php echo $e($svc['icon'] ?? 'storefront'); ?></span>
                        </div>
                        <h4 class="fw-bold mb-3 text-main service-title" contenteditable="true"><?php echo $e($svc['title'] ?? 'Service Title'); ?></h4>
                        <p class="text-muted mb-0 service-desc" contenteditable="true"><?php echo $e($svc['description'] ?? 'Service description.'); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
            <div class="col-md-4" id="add_service_col" contenteditable="false">
                <div class="card h-100 p-4 border align-items-center justify-content-center hover-lift" style="border: 2px dashed var(--color-border-subtle) !important; background: transparent; cursor: pointer; min-height: 250px;" onclick="addServiceCard()">
                    <div class="text-center text-primary opacity-50">
                        <span class="material-symbols-outlined" style="font-size: 3rem;">add_circle</span>
                        <div class="fw-bold mt-2">Add Service Card</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Loan Calculator Section -->
<?php
$show_calc_val = $show_loan_calc ?? true;
if (is_string($show_calc_val)) $show_calc_val = filter_var($show_calc_val, FILTER_VALIDATE_BOOLEAN);
?>
<section id="sec_calc" class="py-5 bg-surface editable-section" style="<?php echo getBgStyle('sec_calc', $sec_styles, '#ffffff'); ?> <?php if (!$show_calc_val) echo 'display:none;'; ?>">
    <div class="container py-5" contenteditable="false">
        <div class="text-center mb-5 mw-lg mx-auto" style="max-width: 700px;">
            <span class="text-primary fw-bold text-uppercase ls-1">Plan Ahead</span>
            <h2 class="display-5 fw-bold mb-3 text-main">Loan Calculator</h2>
            <p class="text-muted lead">Estimate your monthly payments and see how much you can afford.</p>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card border-0 shadow-lg rounded-4 p-4 p-md-5">
                    <div class="row g-5">
                        <!-- Calculator Inputs -->
                        <div class="col-md-6">
                            <div class="mb-4">
                                <label class="form-label fw-bold text-main mb-3">Select Loan Product</label>
                                <div class="row g-2 lc-product-grid mb-4">
                                    <?php foreach (($loan_products ?? []) as $i => $prod): ?>
                                    <div class="col-6">
                                        <button type="button" class="btn w-100 text-start border p-3 lc-product-btn <?php echo $i === 0 ? 'border-primary bg-primary bg-opacity-10' : 'bg-surface'; ?>">
                                            <div class="fw-bold text-main small"><?php echo $e($prod['product_name'] ?? 'Demo Loan'); ?></div>
                                            <div class="small text-muted mt-1" style="font-size: 0.70rem;"><?php echo $e($prod['interest_rate'] ?? '2.5'); ?>% / mo · <?php echo $e($prod['interest_type'] ?? 'Flat'); ?></div>
                                        </button>
                                    </div>
                                    <?php endforeach; ?>
                                </div>

                                <label class="form-label fw-bold text-main mt-4">Loan Amount</label>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="fs-4 text-primary fw-bolder" id="lc-amount-display"><?php echo $loan_calc_money($loan_calc_amount_value); ?></span>
                                </div>
                                <input type="range" class="form-range mt-2" id="lc-amount-slider" min="<?php echo (int)$loan_calc_min_amount; ?>" max="<?php echo (int)$loan_calc_max_amount; ?>" step="<?php echo (int)$loan_calc_amount_step; ?>" value="<?php echo (int)$loan_calc_amount_value; ?>">
                                <div class="d-flex justify-content-between small text-muted mt-1">
                                    <span id="lc-min-amount"><?php echo $loan_calc_money($loan_calc_min_amount); ?></span>
                                    <span id="lc-max-amount"><?php echo $loan_calc_money($loan_calc_max_amount); ?></span>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold text-main">Loan Term (Months)</label>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="fs-4 text-primary fw-bolder" id="lc-term-display"><?php echo (int)$loan_calc_term_value; ?> mo</span>
                                </div>
                                <input type="range" class="form-range mt-2" id="lc-term-slider" min="<?php echo (int)$loan_calc_min_term; ?>" max="<?php echo (int)$loan_calc_max_term; ?>" step="1" value="<?php echo (int)$loan_calc_term_value; ?>">
                                <div class="d-flex justify-content-between small text-muted mt-1">
                                    <span id="lc-min-term"><?php echo (int)$loan_calc_min_term; ?> mo</span>
                                    <span id="lc-max-term"><?php echo (int)$loan_calc_max_term; ?> mo</span>
                                </div>
                            </div>
                        </div>

                        <!-- Calculator Results -->
                        <div class="col-md-6">
                            <div class="card border-0 bg-primary bg-opacity-10 rounded-4 p-4 h-100">
                                <h5 class="fw-bold text-primary mb-4">Payment Summary</h5>
                                
                                <div class="mb-4">
                                    <p class="text-muted small mb-2">Monthly Payment</p>
                                    <h2 class="display-6 fw-bold text-primary mb-0" id="lc-monthly"><?php echo $loan_calc_money($loan_calc_monthly); ?></h2>
                                </div>

                                <hr class="my-4" style="border-color: rgba(var(--bs-primary-rgb), 0.2);">

                                <div class="d-flex justify-content-between mb-3">
                                    <span class="text-muted">Total Interest:</span>
                                    <span class="fw-bold text-main" id="lc-interest"><?php echo $loan_calc_money($loan_calc_interest_total); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="text-muted">Upfront Fees:</span>
                                    <span class="fw-bold text-main" id="lc-fee"><?php echo $loan_calc_money($loan_calc_fee_total); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-4">
                                    <span class="text-muted">Total Repayment:</span>
                                    <span class="fw-bold text-main fs-5" id="lc-total"><?php echo $loan_calc_money($loan_calc_total); ?></span>
                                </div>

                                <div class="alert alert-info mb-0 d-flex align-items-start gap-2 bg-surface shadow-sm border-0 mt-auto">
                                    <span class="material-symbols-outlined text-primary mt-1">info</span>
                                    <small class="text-muted">This is an estimate. Actual rates may vary based on your credit profile.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Call to Action Section -->
<?php
$show_download_val = $show_download ?? true;
if (is_string($show_download_val)) $show_download_val = filter_var($show_download_val, FILTER_VALIDATE_BOOLEAN);
?>
<section id="sec_download" class="py-5 position-relative overflow-hidden editable-section" style="<?php echo getBgStyle('sec_download', $sec_styles, '#f3f4f6'); ?> <?php if (!$show_download_val) echo 'display:none;'; ?>">
    <div class="position-absolute top-0 start-0 w-100 h-100 bg-primary opacity-10 pointer-events-none"></div>
    <div class="container py-5 position-relative z-1">
        <div class="card border-0 shadow-lg p-5 text-center bg-surface">
            <h2 class="display-6 fw-bold mb-3 text-main">MicroFin Mobile App</h2>
            <p class="lead text-muted mb-4 mx-auto" style="max-width: 600px;" data-edit="download_description" contenteditable="true">
                <?php echo $e($download_description ?? 'Download the MicroFin app, then bind your registration to this institution with the QR code or referral code below.'); ?>
            </p>
            
            <div class="d-flex justify-content-center gap-3 mb-4">
                <a href="<?php echo $e($download_href); ?>" class="btn btn-primary btn-lg rounded-pill px-5 fw-bold" contenteditable="false">
                    Download App
                </a>
            </div>

            <!-- Download Steps Match Template 1 Form -->
            <div class="shared-app-steps mx-auto mt-4" style="max-width:780px;" contenteditable="false">
                <div class="shared-app-step">
                    <div class="shared-app-step-label">Step 1</div>
                    <div class="shared-app-step-title">Install the MicroFin app</div>
                    <p class="shared-app-step-copy">The download button installs the company-branded MicroFin mobile app.</p>
                </div>
                <div class="shared-app-step">
                    <div class="shared-app-step-label">Step 2</div>
                    <div class="shared-app-step-title">Open Create Account and tap the QR button below</div>
                    <p class="shared-app-step-copy">That will reveal this institution's registration QR so the app can unlock the form with the correct <strong>@<?php echo $e($tenant_slug ?? $site_slug ?? 'tenant'); ?></strong> suffix.</p>
                </div>
                <div class="shared-app-step">
                    <div class="shared-app-step-label">Step 3</div>
                    <div class="shared-app-step-title">Use the referral code if scanning is unavailable</div>
                    <p class="shared-app-step-copy">Manual fallback code: <strong><?php echo $e($tenant_referral_code_value !== '' ? $tenant_referral_code_value : ($tenant_slug ?? $site_slug ?? '')); ?></strong></p>
                </div>
            </div>
            
            <details class="shared-app-qr-toggle" contenteditable="false">
                <summary class="shared-app-qr-toggle-button">
                    <span class="material-symbols-outlined" style="font-size:1.1rem;">help</span>
                    Downloaded the app already? Show the registration QR
                </summary>
                <div class="shared-app-qr-panel">
                    <div class="shared-app-qr-card">
                        <?php if ($tenant_qr_url !== ''): ?>
                            <img src="<?php echo $e($tenant_qr_url); ?>" alt="Tenant registration QR code">
                        <?php else: ?>
                            <div class="d-flex align-items-center justify-content-center text-dark text-center" style="width:180px;height:180px;border-radius:18px;background:rgba(0,0,0,0.05);padding:20px;">
                                Publish the site to generate the tenant QR code.
                            </div>
                        <?php endif; ?>
                        <div class="shared-app-code">
                            <span class="material-symbols-outlined" style="font-size:1.1rem;">confirmation_number</span>
                            Referral Code: <?php echo $e($tenant_referral_code_value !== '' ? $tenant_referral_code_value : ($tenant_slug ?? $site_slug ?? '')); ?>
                        </div>
                    </div>
                </div>
            </details>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="py-5" style="background-color: #111827; color: #f9fafb;">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-4">
                <div class="d-flex flex-column lh-1 mb-4">
                    <span class="d-flex align-items-center display-company-name" style="font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 1.75rem; letter-spacing: -2px; color: white;" contenteditable="false">
                        <?php echo $e($company_name ?? 'fundline'); ?>
                    </span>
                </div>
                <p class="text-white-50 lh-lg" style="font-size:0.95rem" data-edit="footer_desc" contenteditable="true">
                    <?php echo $e($footer_desc ?? 'Empowering individuals and small businesses with accessible, fair, and transparent financial solutions.'); ?>
                </p>
            </div>
            
            <div class="col-lg-3 offset-lg-2">
                <h6 class="fw-bold mb-4 text-primary text-uppercase" style="letter-spacing: 0.1em;">Contact</h6>
                <ul class="list-unstyled text-white-50 d-flex flex-column gap-3 small">
                    <li class="d-flex align-items-center gap-3">
                        <span class="material-symbols-outlined fs-5 text-primary">location_on</span> 
                        <span data-edit="contact_address" contenteditable="true"><?php echo $e($contact_address ?? '123 Finance Ave'); ?></span>
                    </li>
                    <li class="d-flex align-items-center gap-3">
                        <span class="material-symbols-outlined fs-5 text-primary">call</span> 
                        <span data-edit="contact_phone" contenteditable="true"><?php echo $e($contact_phone ?? '+63 912 345 6789'); ?></span>
                    </li>
                    <li class="d-flex align-items-center gap-3">
                        <span class="material-symbols-outlined fs-5 text-primary">mail</span> 
                        <span data-edit="contact_email" contenteditable="true"><?php echo $e($contact_email ?? 'hello@fundline.os'); ?></span>
                    </li>
                    <li class="d-flex align-items-center gap-3">
                        <span class="material-symbols-outlined fs-5 text-primary">schedule</span> 
                        <span data-edit="contact_hours" contenteditable="true"><?php echo $e($contact_hours ?? 'Mon-Fri: 8AM - 5PM'); ?></span>
                    </li>
                </ul>
            </div>
        </div>
        <div class="border-top border-secondary mt-5 pt-4 text-center text-white-50 small opacity-75">
            <p class="mb-0">© <?php echo date('Y'); ?> <?php echo $e($company_name ?? 'Fundline Micro Financing Services'); ?>. All rights reserved.</p>
        </div>
    </div>
</footer>
