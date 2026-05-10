<?php
/**
 * Template 1 — Classic Corporate
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
    /* ─── TEMPLATE 1 SPECIFIC STYLES ─── */
    :root {
        --brand:
            <?php echo $primary ?? '#2563eb'; ?>
        ;
        --brand-rgb:
            <?php echo isset($primary) ? implode(', ', array_map('hexdec', str_split(ltrim($primary, '#'), 2))) : '37, 99, 235'; ?>
        ;
        --border-clr:
            <?php echo $border_color ?? '#e2e8f0'; ?>
        ;
        --radius:
            <?php echo $border_radius ?? '16'; ?>
            px;
        --shadow: 0 8px 24px rgba(0, 0, 0,
                <?php echo $shadow ?? '0.1'; ?>
            );

        --text-heading:
            <?php echo $text_heading_color ?? '#0f172a'; ?>
        ;
        --text-body:
            <?php echo $text_body_color ?? '#64748b'; ?>
        ;
        --btn-bg:
            <?php echo $btn_bg_color ?? ($primary ?? '#2563eb'); ?>
        ;
        --btn-text:
            <?php echo $btn_text_color ?? '#ffffff'; ?>
        ;
    }

    /* Typography Routing */
    h1:not(.text-white),
    h2:not(.text-white),
    h3:not(.text-white),
    h4:not(.text-white),
    h5:not(.text-white),
    .headline:not(.text-white),
    .text-dark {
        color: var(--text-heading) !important;
        font-family: 'Manrope', sans-serif;
    }

    p:not(.text-white),
    .text-muted:not(.text-white),
    .small:not(.text-white) {
        color: var(--text-body) !important;
    }

    .btn-brand {
        background: var(--btn-bg) !important;
        color: var(--btn-text) !important;
        border: none;
        border-radius: var(--radius);
        padding: 12px 24px;
        font-weight: bold;
    }

    .text-brand {
        color: var(--brand) !important;
    }

    .material-symbols-rounded {
        vertical-align: middle;
        user-select: none;
    }

    .site-nav {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(10px);
        border-bottom: 1px solid var(--border-clr);
    }

    .custom-card {
        background: #fff;
        border: 1px solid var(--border-clr);
        border-radius: var(--radius);
        padding: 32px 24px;
        box-shadow: var(--shadow);
        height: 100%;
        position: relative;
    }

    .service-icon-wrap {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        background: rgba(var(--brand-rgb), .1);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: var(--brand);
    }

    .db-tag {
        font-size: 0.65rem;
        background: #fef08a;
        color: #854d0e;
        padding: 2px 6px;
        border-radius: 4px;
        font-weight: bold;
        text-transform: uppercase;
        vertical-align: super;
        margin-left: 4px;
    }

    .lc-product-grid > [class*="col-"] {
        display: flex;
    }

    .lc-product-btn {
        height: 100%;
        display: flex;
        flex-direction: column;
        gap: 8px;
        justify-content: space-between;
        border-radius: calc(var(--radius) - 2px);
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    }

    .lc-product-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08);
    }

    .lc-product-meta {
        display: grid;
        gap: 2px;
    }

    .shared-app-steps {
        display: grid;
        gap: 12px;
        margin-top: 28px;
    }

    .shared-app-step {
        padding: 16px 18px;
        border-radius: 18px;
        border: 1px solid rgba(255, 255, 255, 0.18);
        background: rgba(255, 255, 255, 0.08);
        text-align: left;
    }

    .shared-app-step-label {
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        opacity: 0.6;
        margin-bottom: 8px;
    }

    .shared-app-step-title {
        font-size: 1rem;
        font-weight: 700;
        color: #fff;
        margin-bottom: 4px;
    }

    .shared-app-step-copy {
        font-size: 0.92rem;
        line-height: 1.6;
        color: rgba(255, 255, 255, 0.78);
        margin: 0;
    }

    .shared-app-qr-card {
        margin-top: 28px;
        padding: 18px;
        border-radius: 24px;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.16);
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
        background: rgba(15, 23, 42, 0.36);
        border: 1px solid rgba(255, 255, 255, 0.16);
        color: #fff;
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
        border: 1px solid rgba(255, 255, 255, 0.2);
        background: rgba(255, 255, 255, 0.08);
        color: #fff;
        font-weight: 700;
        cursor: pointer;
        transition: transform 0.2s ease, background 0.2s ease;
    }

    .shared-app-qr-toggle-button:hover {
        background: rgba(255, 255, 255, 0.14);
        transform: translateY(-1px);
    }

    .shared-app-qr-panel {
        margin-top: 16px;
    }
</style>

<nav class="site-nav sticky-top py-3">
    <div class="container d-flex justify-content-between align-items-center">
        <a class="d-flex align-items-center gap-2 text-decoration-none" href="#">
            <img id="preview_logo" src="<?php echo $e($logo ?? ''); ?>"
                style="height:36px; <?php if (!($logo ?? ''))
                    echo 'display:none;'; ?>">
            <span class="fw-800 headline fs-5 text-brand display-short-name"
                contenteditable="false"><?php echo $e($display_name ?? 'MicroFin'); ?></span>
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
        <a href="<?php echo $login_href; ?>" class="btn btn-brand rounded-pill px-4 shadow-sm" contenteditable="false">Log In</a>
    </div>
</nav>

<section id="sec_hero" class="editable-section"
    style="min-height: 80vh; display: flex; align-items: center; padding: 60px 0; <?php echo getBgStyle('sec_hero', $sec_styles, '#f8fafc'); ?>">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <div class="d-inline-flex align-items-center gap-2 mb-3 px-3 py-1 rounded-pill"
                    style="background: rgba(var(--brand-rgb), 0.1); color: var(--brand); border: 1px solid var(--border-clr);">
                    <span class="material-symbols-rounded" style="font-size: 1rem;">verified</span>
                    <span class="small fw-bold text-uppercase" data-edit="hero_badge_text"
                        contenteditable="true"><?php echo $e($hero_badge_text ?? 'Verified Partner'); ?></span>
                </div>
                <h1 class="headline fw-bolder mb-4" style="font-size: 3.5rem;" data-edit="hero_title"
                    contenteditable="true"><?php echo $e($hero_title ?? 'Empowering Your Financial Future'); ?></h1>
                <p class="fs-5 text-muted mb-5" data-edit="hero_subtitle" contenteditable="true">
                    <?php echo $e($hero_subtitle ?? 'Get flexible loans with fast approval and transparent terms.'); ?>
                </p>
            </div>
            <div class="col-lg-6 text-center">
                <div id="hero_img_container" class="position-relative shadow-sm"
                    style="aspect-ratio: 1/1; min-height: 350px; border-radius: var(--radius); border: 1px solid var(--border-clr); background: #fff; overflow: hidden; display: flex; align-items: center; justify-content: center; flex-direction: column; transition: aspect-ratio 0.3s ease;">
                    <img id="preview_hero" src="<?php echo $e($display_image ?? ''); ?>"
                        style="position: absolute; width: 100%; height: 100%; object-fit: cover; z-index: 1; <?php if (!($display_image ?? '')) echo 'display:none;'; ?>">
                    <div
                        style="z-index: 2; background: rgba(255,255,255,0.9); padding: 15px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                        <button type="button" id="btn_open_hero_picker"
                            class="btn btn-outline-primary btn-sm mb-0 fw-bold shadow-sm"><?php echo $e(($hero_image ?? '') !== '' ? 'Change Hero Image' : 'Upload Hero Image'); ?></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
$show_services_val = $show_services ?? true;
if (is_string($show_services_val))
    $show_services_val = filter_var($show_services_val, FILTER_VALIDATE_BOOLEAN);
?>
<section id="sec_services" class="py-5 editable-section"
    style="<?php echo getBgStyle('sec_services', $sec_styles, '#ffffff'); ?> <?php if (!$show_services_val)
              echo 'display:none;'; ?>">
    <div class="container py-4">
        <div class="text-center mb-5">
            <h2 class="headline fw-800 display-6">Our Services</h2>
        </div>
        <div class="row g-4" id="services_row">
            <?php foreach (($services ?? []) as $index => $svc): ?>
                <div class="col-md-4 service-col">
                    <div class="custom-card text-center">
                        <button class="btn btn-sm btn-danger position-absolute top-0 end-0 m-2 delete-card"
                            contenteditable="false">×</button>
                        <div class="text-center mb-3">
                            <div class="service-icon-wrap mx-auto editable-icon-wrap" title="Click to select a new icon">
                                <span
                                    class="material-symbols-rounded service-icon-text"><?php echo $e($svc['icon'] ?? 'star'); ?></span>
                            </div>
                        </div>
                        <h4 class="headline fw-bold h5 mb-3 service-title" contenteditable="true">
                            <?php echo $e($svc['title'] ?? 'Service Title'); ?></h4>
                        <p class="text-muted mb-0 service-desc" contenteditable="true">
                            <?php echo $e($svc['description'] ?? 'Service description.'); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
            <div class="col-md-4" id="add_service_col" contenteditable="false">
                <div class="custom-card d-flex align-items-center justify-content-center"
                    style="border: 2px dashed var(--border-clr); background: transparent; cursor: pointer; min-height: 250px;"
                    onclick="addServiceCard()">
                    <div class="text-center text-brand opacity-50">
                        <span class="material-symbols-rounded" style="font-size: 3rem;">add_circle</span>
                        <div class="fw-bold mt-2">Add Service Card</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
$show_stats_val = $show_stats ?? true;
if (is_string($show_stats_val))
    $show_stats_val = filter_var($show_stats_val, FILTER_VALIDATE_BOOLEAN);
?>
<section id="sec_stats" class="py-5 editable-section"
    style="<?php echo getBgStyle('sec_stats', $sec_styles, $primary ?? '#2563eb'); ?> <?php if (!$show_stats_val)
              echo 'display:none;'; ?>">
    <div class="container py-5 text-center text-white" contenteditable="false">
        <h2 class="headline fw-800 mb-2 text-white">Our Impact</h2>
        <p class="opacity-75 mb-5 text-white">Numbers that show our dedication.</p>
        <div class="row justify-content-center g-4">
            <div class="col-6 col-md-3">
                <div class="headline fw-bolder text-white" style="font-size: 2.5rem;">1,500+</div>
                <div class="text-uppercase small text-white fw-bold opacity-75">Active Members</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="headline fw-bolder text-white" style="font-size: 2.5rem;">3,200+ </div>
                <div class="text-uppercase small text-white fw-bold opacity-75">Loans Funded</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="headline fw-bolder text-white" style="font-size: 2.5rem;">10+ </div>
                <div class="text-uppercase small text-white fw-bold opacity-75">Cities Served</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="headline fw-bolder text-white" style="font-size: 2.5rem;">5+ </div>
                <div class="text-uppercase small text-white fw-bold opacity-75">Years Experience</div>
            </div>
        </div>
    </div>
</section>

<?php
$show_calc_val = $show_loan_calc ?? true;
if (is_string($show_calc_val))
    $show_calc_val = filter_var($show_calc_val, FILTER_VALIDATE_BOOLEAN);
?>
<section id="sec_calc" class="py-5 editable-section"
    style="border-top: 1px solid var(--border-clr); <?php echo getBgStyle('sec_calc', $sec_styles, '#f8fafc'); ?> <?php if (!$show_calc_val)
              echo 'display:none;'; ?>">
    <div class="container py-4" contenteditable="false">
        <div class="text-center mb-5">
            <h2 class="headline fw-800 display-6">Estimate Your Payment</h2>
        </div>
        <div class="custom-card mx-auto p-5" style="max-width:820px;">
            <div class="mb-4">
                <label class="small fw-700 text-uppercase text-muted mb-3">Select Loan Product</label>
                <div class="row g-3 lc-product-grid">
                    <?php foreach (($loan_products ?? []) as $i => $prod): ?>
                        <div class="col-md-6">
                            <button type="button"
                                class="btn w-100 text-start border p-3 lc-product-btn <?php echo $i === 0 ? 'border-primary bg-light' : 'bg-white'; ?>">
                                <div class="fw-bold text-dark"><?php echo $e($prod['product_name'] ?? 'Demo Loan'); ?></div>
                                <div class="lc-product-meta">
                                    <div class="small text-muted"><?php echo $e($prod['interest_rate'] ?? '2.5'); ?>% / mo · <?php echo $e($prod['interest_type'] ?? 'Flat'); ?></div>
                                    <div class="small text-muted"><?php echo $loan_calc_money((float)($prod['min_amount'] ?? 1000)); ?> to <?php echo $loan_calc_money((float)($prod['max_amount'] ?? 50000)); ?></div>
                                    <div class="small text-muted"><?php echo (int)($prod['min_term_months'] ?? 1); ?>-<?php echo (int)($prod['max_term_months'] ?? 12); ?> months</div>
                                </div>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="mb-4 p-4 rounded bg-light border">
                <div class="d-flex justify-content-between mb-1">
                    <label class="small fw-bold text-muted">Loan Amount</label>
                    <span class="fw-bolder fs-4 text-brand headline" id="lc-amount-display"><?php echo $loan_calc_money($loan_calc_amount_value); ?></span>
                </div>
                <input type="range" class="form-range" id="lc-amount-slider" min="<?php echo (int)$loan_calc_min_amount; ?>" max="<?php echo (int)$loan_calc_max_amount; ?>" step="<?php echo (int)$loan_calc_amount_step; ?>" value="<?php echo (int)$loan_calc_amount_value; ?>">
                <div class="d-flex justify-content-between"><small class="text-muted"
                        id="lc-min-amount"><?php echo $loan_calc_money($loan_calc_min_amount); ?></small><small class="text-muted" id="lc-max-amount"><?php echo $loan_calc_money($loan_calc_max_amount); ?></small>
                </div>
            </div>
            <div class="mb-4 p-4 rounded bg-light border">
                <div class="d-flex justify-content-between mb-1">
                    <label class="small fw-bold text-muted">Loan Term</label>
                    <span class="fw-bolder fs-5 text-brand headline" id="lc-term-display"><?php echo (int)$loan_calc_term_value; ?> mo</span>
                </div>
                <input type="range" class="form-range" id="lc-term-slider" min="<?php echo (int)$loan_calc_min_term; ?>" max="<?php echo (int)$loan_calc_max_term; ?>" value="<?php echo (int)$loan_calc_term_value; ?>">
                <div class="d-flex justify-content-between"><small class="text-muted" id="lc-min-term"><?php echo (int)$loan_calc_min_term; ?>
                        mo</small><small class="text-muted" id="lc-max-term"><?php echo (int)$loan_calc_max_term; ?> mo</small></div>
            </div>
            <div class="row g-3 text-center mt-3">
                <div class="col-6 col-md-3">
                    <div class="small text-muted">Monthly</div>
                    <div class="fw-bold headline fs-5" id="lc-monthly"><?php echo $loan_calc_money($loan_calc_monthly); ?></div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="small text-muted">Interest</div>
                    <div class="fw-bold headline fs-5" id="lc-interest"><?php echo $loan_calc_money($loan_calc_interest_total); ?></div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="small text-muted">Upfront Fees</div>
                    <div class="fw-bold headline fs-5" id="lc-fee"><?php echo $loan_calc_money($loan_calc_fee_total); ?></div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="small text-muted">Total</div>
                    <div class="fw-bold headline fs-5" id="lc-total"><?php echo $loan_calc_money($loan_calc_total); ?></div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
$show_about_val = $show_about ?? true;
if (is_string($show_about_val))
    $show_about_val = filter_var($show_about_val, FILTER_VALIDATE_BOOLEAN);
?>
<section id="sec_about" class="py-5 editable-section"
    style="<?php echo getBgStyle('sec_about', $sec_styles, '#ffffff'); ?> border-top: 1px solid var(--border-clr); <?php if (!$show_about_val)
              echo 'display:none;'; ?>">
    <div class="container py-4 text-center">
        <h2 class="headline fw-800 display-6 mb-4">WHO WE ARE</h2>
        <div class="text-muted lh-lg fs-5 mx-auto" style="max-width:800px;" data-edit="about_body"
            contenteditable="true"><?php echo $e($about_body ?? 'We believe in empowering our community.'); ?></div>
    </div>
</section>

<?php
$show_download_val = $show_download ?? true;
if (is_string($show_download_val))
    $show_download_val = filter_var($show_download_val, FILTER_VALIDATE_BOOLEAN);
?>
<section id="sec_download" class="py-5 text-center text-white editable-section"
    style="<?php echo getBgStyle('sec_download', $sec_styles, '#0f172a'); ?> <?php if (!$show_download_val)
              echo 'display:none;'; ?>">
    <div class="container py-5">
        <div class="small opacity-50 text-uppercase fw-700 mb-3 text-white" style="letter-spacing:.15em;">MicroFin Mobile App
        </div>
        <h2 class="headline fw-800 mb-4 display-5 text-white">Install Once, Register With Your Institution</h2>
        <div class="mb-5 lh-lg opacity-75 mx-auto text-white" style="max-width:600px;" data-edit="download_description"
            contenteditable="true"><?php echo $e($download_description ?? 'Download the MicroFin app, then bind your registration to this institution with the QR code or referral code below.'); ?></div>
        <a href="<?php echo $e($download_href); ?>" 
            class="btn btn-outline-light rounded-pill px-5 py-3 fw-bold text-decoration-none" 
            contenteditable="false">Download App</a>
        <div class="shared-app-steps mx-auto" style="max-width:780px;">
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
                <span class="material-symbols-rounded" style="font-size:1.1rem;">help</span>
                Downloaded the app already? Show the registration QR
            </summary>
            <div class="shared-app-qr-panel">
                <div class="shared-app-qr-card">
                    <?php if ($tenant_qr_url !== ''): ?>
                        <img src="<?php echo $e($tenant_qr_url); ?>" alt="Tenant registration QR code">
                    <?php else: ?>
                        <div class="d-flex align-items-center justify-content-center text-white text-center" style="width:180px;height:180px;border-radius:18px;background:rgba(255,255,255,0.12);padding:20px;">
                            Publish the site to generate the tenant QR code.
                        </div>
                    <?php endif; ?>
                    <div class="shared-app-code">
                        <span class="material-symbols-rounded" style="font-size:1.1rem;">confirmation_number</span>
                        Referral Code: <?php echo $e($tenant_referral_code_value !== '' ? $tenant_referral_code_value : ($tenant_slug ?? $site_slug ?? '')); ?>
                    </div>
                </div>
            </div>
        </details>
      </div>
</section>

<footer class="site-footer py-5" style="background: #0f172a; color: rgba(255,255,255,.6);">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-5">
                <span class="headline fw-800 text-white fs-4 d-block mb-3 display-company-name"
                    contenteditable="false"><?php echo $e($company_name ?? 'MicroFin'); ?></span>
                <div class="lh-lg text-white opacity-75" style="font-size:.9rem;" data-edit="footer_desc"
                    contenteditable="true"><?php echo $e($footer_desc ?? 'Your trusted partner.'); ?></div>
            </div>
            <div class="col-lg-3 offset-lg-1">
                <h6 class="text-white fw-bold text-uppercase mb-4">Contact</h6>
                <ul class="list-unstyled d-flex flex-column gap-3 small text-white opacity-75">
                    <li class="d-flex gap-3"><span class="material-symbols-rounded text-brand"
                            style="font-size:1rem;">location_on</span> <span data-edit="contact_address"
                            contenteditable="true"><?php echo $e($contact_address ?? '123 Finance Ave'); ?></span></li>
                    <li class="d-flex gap-3"><span class="material-symbols-rounded text-brand"
                            style="font-size:1rem;">phone</span> <span data-edit="contact_phone"
                            contenteditable="true"><?php echo $e($contact_phone ?? '0900-123-4567'); ?></span></li>
                    <li class="d-flex gap-3"><span class="material-symbols-rounded text-brand"
                            style="font-size:1rem;">email</span> <span data-edit="contact_email"
                            contenteditable="true"><?php echo $e($contact_email ?? 'hello@microfin.os'); ?></span></li>
                    <li class="d-flex gap-3"><span class="material-symbols-rounded text-brand"
                            style="font-size:1rem;">schedule</span> <span data-edit="contact_hours"
                            contenteditable="true"><?php echo $e($contact_hours ?? 'Mon-Fri: 8AM - 5PM'); ?></span></li>
                </ul>
            </div>
        </div>
    </div>
</footer>
