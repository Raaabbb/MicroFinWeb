 <?php
// site.php — Tenant Public Website
// URL: site.php?site=tenant-slug
// No authentication required.

require_once dirname(__DIR__, 2) . '/microfin_backend/config/db_connect.php';
require_once __DIR__ . '/install_attribution.php';

function site_normalize_asset_path(string $path): string
{
    $path = trim($path);
    if ($path === '') {
        return '';
    }
    if (preg_match('~^(?:https?:)?//|^data:~i', $path)) {
        return $path;
    }
    if ($path[0] === '/') {
        return $path;
    }
    $path = preg_replace('~^(?:\./)+~', '', $path);
    $path = preg_replace('~^(?:\.\./)+~', '', $path);
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if (strpos($script, '/admin_panel/website_editor/') !== false) {
        $base = rtrim(str_replace('\\', '/', dirname(dirname(dirname($script)))), '/');
    } elseif (strpos($script, '/public_website/') !== false) {
        $base = rtrim(str_replace('\\', '/', dirname($script)), '/');
    } else {
        $base = rtrim(str_replace('\\', '/', dirname($script)), '/');
    }
    return ($base === '' ? '' : $base) . '/' . ltrim($path, '/');
}

$slug = trim($_GET['site'] ?? '');
$error_page = false;
$error_msg = '';
$data = null;

if ($slug === '') {
    $error_page = true;
    $error_msg = 'No website specified.';
} else {
    try {
        $stmt = $pdo->prepare("
            SELECT
                t.tenant_id, t.tenant_name, t.tenant_slug, t.status,
                b.logo_path, b.font_family, b.theme_primary_color, b.theme_secondary_color,
                b.theme_text_main, b.theme_text_muted, b.theme_bg_body, b.theme_bg_card,
                b.theme_border_color,
                w.layout_template, w.website_data
            FROM tenants t
            LEFT JOIN tenant_branding b ON t.tenant_id = b.tenant_id
            LEFT JOIN tenant_website_content w ON t.tenant_id = w.tenant_id
            WHERE t.tenant_slug = ?
        ");
        $stmt->execute([$slug]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Failsafe: If columns are missing or query fails, try a minimal query
        $stmt = $pdo->prepare("SELECT tenant_id, tenant_name, tenant_slug, status FROM tenants WHERE tenant_slug = ?");
        $stmt->execute([$slug]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Inject default branding since the branding table or columns are inaccessible
        if ($data) {
            $data = array_merge($data, [
                'logo_path' => null,
                'font_family' => 'Inter',
                'theme_primary_color' => '#dc2626',
                'theme_secondary_color' => '#991b1b',
                'theme_text_main' => '#0f172a',
                'theme_text_muted' => '#64748b',
                'theme_bg_body' => '#f1f5f9',
                'theme_bg_card' => '#ffffff',
                'theme_border_color' => '#e2e8f0',
                'layout_template' => null,
                'website_data' => null
            ]);
        }
    }

    if (!$data) {
        $error_page = true;
        $error_msg = 'This website does not exist.';
    } elseif ($data['status'] !== 'Active') {
        $error_page = true;
        $error_msg = 'This organization is currently inactive.';
    } else {
        // Check public_website_enabled toggle
        try {
            $toggle_stmt = $pdo->prepare('SELECT is_enabled FROM tenant_feature_toggles WHERE tenant_id = ? AND toggle_key = ?');
            $toggle_stmt->execute([$data['tenant_id'], 'public_website_enabled']);
            $toggle = $toggle_stmt->fetch();

            if (!$toggle || !(int)$toggle['is_enabled']) {
                $error_page = true;
                $error_msg = 'This organization has not enabled their public website.';
            } elseif (!$data['layout_template']) {
                $error_page = true;
                $error_msg = 'This organization has not set up their website yet.';
            }
        } catch (PDOException $e) {
            // If toggle table fails, assume disabled to be safe
            $error_page = true;
            $error_msg = 'This website is temporarily unavailable due to a configuration error.';
        }
    }
}


// --- Error Page ---
if ($error_page) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Website Not Available</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; display: flex; align-items: center; justify-content: center; min-height: 100vh; color: #334155; }
        .error-card { background: #fff; border-radius: 16px; padding: 48px; text-align: center; max-width: 440px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .error-card .icon { font-size: 56px; color: #94a3b8; margin-bottom: 16px; }
        .error-card h1 { font-size: 1.25rem; font-weight: 600; margin-bottom: 8px; }
        .error-card p { color: #64748b; font-size: 0.9rem; line-height: 1.6; }
        .error-card a { display: inline-block; margin-top: 20px; color: #4f46e5; text-decoration: none; font-weight: 500; font-size: 0.9rem; }
        .error-card a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="error-card">
        <span class="material-symbols-rounded icon">language</span>
        <h1>Website Not Available</h1>
        <p><?php echo htmlspecialchars($error_msg); ?></p>
        <a href="javascript:history.back()">Go Back</a>
    </div>
</body>
</html>
<?php
    exit;
}

// ==========================================
// DECODE WEBSITE DATA
// ==========================================
$pageData = [];
if (!empty($data['website_data'])) {
    $pageData = json_decode($data['website_data'], true);
    if (!is_array($pageData)) $pageData = [];
}

$e = function($val) { return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8'); };

// Template selection
$selected_template = $data['layout_template'] ?? 'template1.php';
// Normalize old-style names (template1 → template1.php)
if (strpos($selected_template, '.php') === false) $selected_template .= '.php';
$templates_dir = dirname(__DIR__) . '/admin_panel/website_editor/templates/';
$template_path = $templates_dir . basename($selected_template);
if (!file_exists($template_path)) $template_path = $templates_dir . 'template1.php';

// ==========================================
// GLOBAL TOKENS (Prioritize Local Files, then DB, then Default)
// ==========================================
$base_upload_dir = dirname(__DIR__) . '/uploads';

// Check local logo based on tenant_id
$found_logo = false;
$local_logo_path = '';
$tenant_id_clean = preg_replace('/[^A-Za-z0-9_-]+/', '_', $data['tenant_id'] ?? '');

if ($tenant_id_clean !== '') {
    $possible_logo_exts = ['png', 'jpg', 'jpeg', 'webp', 'svg'];
    foreach ($possible_logo_exts as $ext) {
        if (file_exists($base_upload_dir . '/tenant_logos/' . $tenant_id_clean . 'logo.' . $ext)) {
            $local_logo_path = '../uploads/tenant_logos/' . $tenant_id_clean . 'logo.' . $ext;
            $found_logo = true;
            break;
        }
    }
}
$logo = $found_logo ? site_normalize_asset_path($local_logo_path) : site_normalize_asset_path((string)($data['logo_path'] ?? ''));

$primary       = $data['theme_primary_color'] ?: '#2563eb';
$border_color  = $data['theme_border_color'] ?? '#e2e8f0';
$border_radius = $pageData['border_radius'] ?? '16';
$shadow        = $pageData['shadow_intensity'] ?? '0.1';

// Typography & Buttons
$text_heading_color = $pageData['text_heading_color'] ?? '#0f172a';
$text_body_color    = $pageData['text_body_color'] ?? '#64748b';
$btn_bg_color       = $pageData['btn_bg_color'] ?? $primary;
$btn_text_color     = $pageData['btn_text_color'] ?? '#ffffff';

// Content Strings
$tenant_name      = $data['tenant_name'] ?? 'MicroFin OS';
$tenant_id        = $data['tenant_id'] ?? '';
$tenant_slug      = $data['tenant_slug'] ?? $slug;
$company_name     = $pageData['company_name'] ?? $tenant_name;
$short_name       = $pageData['short_name'] ?? '';
$display_name     = $short_name ? $short_name : $company_name;
$site_slug        = $slug;

$hero_title       = $pageData['hero_title'] ?? 'Empowering Your Financial Future';
$hero_subtitle    = $pageData['hero_subtitle'] ?? 'Get flexible loans with fast approval and transparent terms.';
$hero_badge_text  = $pageData['hero_badge_text'] ?? 'Verified Partner';

// Check local hero based on tenant_id
$found_hero = false;
$local_hero_path = '';
if ($tenant_id_clean !== '') {
    $possible_hero_exts = ['png', 'jpg', 'jpeg', 'webp'];
    foreach ($possible_hero_exts as $ext) {
        if (file_exists($base_upload_dir . '/hero/' . $tenant_id_clean . 'hero.' . $ext)) {
            $local_hero_path = '../uploads/hero/' . $tenant_id_clean . 'hero.' . $ext;
            $found_hero = true;
            break;
        }
    }
}
$hero_image       = $found_hero ? site_normalize_asset_path($local_hero_path) : site_normalize_asset_path((string)($pageData['hero_image'] ?? ''));
$display_image    = $hero_image ?: '';

$about_body       = $pageData['about_body'] ?? 'We believe in empowering our community through accessible financial tools.';
$download_description = $pageData['download_description'] ?? 'Track your loans, submit applications, receive notifications — all from your phone.';
$footer_desc      = $pageData['footer_desc'] ?? 'Your trusted microfinance partner.';

$contact_address = $pageData['contact_address'] ?? '';
$contact_phone   = $pageData['contact_phone'] ?? '';
$contact_email   = $pageData['contact_email'] ?? '';
$contact_hours   = $pageData['contact_hours'] ?? '';

// Arrays
$services = $pageData['services'] ?? [
    ['icon' => 'person', 'title' => 'Personal Loan', 'description' => 'Fast approval for your personal needs.'],
    ['icon' => 'store', 'title' => 'Business Capital', 'description' => 'Grow your business with flexible terms.']
];
if (!is_array($services)) $services = [];

$stats = $pageData['stats'] ?? [
    ['value' => '1.5k+', 'label' => 'Active Members'],
    ['value' => '3.2k+', 'label' => 'Loans Funded'],
    ['value' => '98%', 'label' => 'Satisfaction'],
    ['value' => '24h', 'label' => 'Fast Release']
];
if (!is_array($stats)) $stats = [];

// Section Toggles
$show_services  = isset($pageData['show_services']) ? filter_var($pageData['show_services'], FILTER_VALIDATE_BOOLEAN) : true;
$show_stats     = isset($pageData['show_stats']) ? filter_var($pageData['show_stats'], FILTER_VALIDATE_BOOLEAN) : true;
$show_loan_calc = isset($pageData['show_calculator']) ? filter_var($pageData['show_calculator'], FILTER_VALIDATE_BOOLEAN) : true;
$show_about     = isset($pageData['show_about']) ? filter_var($pageData['show_about'], FILTER_VALIDATE_BOOLEAN) : true;
$show_download  = isset($pageData['show_download']) ? filter_var($pageData['show_download'], FILTER_VALIDATE_BOOLEAN) : true;

// Background Style Helper
$sec_styles = $pageData['section_styles'] ?? [];
if (!function_exists('getBgStyle')) {
    function getBgStyle($secId, $styles, $defaultBg) {
        $style = $styles[$secId] ?? null;
        if (!$style) return "background: $defaultBg;";
        $isGrad = filter_var($style['gradient'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $c1 = $style['bg'] ?? $defaultBg;
        if ($isGrad) {
            $c2 = $style['grad_color2'] ?? '#e2e8f0';
            $dir = $style['grad_dir'] ?? '135deg';
            if ($dir === 'circle') return "background: radial-gradient(circle, $c1 0%, $c2 100%);";
            return "background: linear-gradient($dir, $c1 0%, $c2 100%);";
        }
        return "background: $c1;";
    }
}

// Fetch active loan products from DB for calculator
$loan_products = [];
try {
    $lp_stmt = $pdo->prepare('SELECT product_name, product_type, min_amount, max_amount, interest_rate, interest_type, min_term_months, max_term_months, processing_fee_percentage, insurance_fee_percentage, service_charge, documentary_stamp FROM loan_products WHERE tenant_id = ? AND is_active = 1 ORDER BY product_name');
    $lp_stmt->execute([$data['tenant_id']]);
    $loan_products = $lp_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $ex) {
    $loan_products = [];
}
if (empty($loan_products)) {
    $loan_products = [[
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
    ]];
}

// Misc variables expected by templates
$meta_desc = '';
$custom_css = '';
$headline_font = 'Manrope';
$body_font = 'Public Sans';
$onPrimary = '#ffffff';
$secondary = $data['theme_secondary_color'] ?: '#10b981';

// Normalize interest type: map legacy values to new standardized values
$normalize_interest_type = function(?string $type): string {
    $type = trim($type ?? 'Declining Balance');
    if ($type === 'Fixed') return 'Flat';
    if ($type === 'Diminishing') return 'Declining Balance';
    return $type;
};

$tenantReferenceTenant = [
    'tenant_id' => (string) $tenant_id,
    'tenant_name' => (string) $tenant_name,
    'tenant_slug' => (string) $tenant_slug,
];
$tenant_referral_code = function_exists('mf_install_referral_code')
    ? mf_install_referral_code($tenantReferenceTenant)
    : (string) $tenant_slug;
$tenant_reference_payload = function_exists('mf_install_tenant_reference_payload')
    ? mf_install_tenant_reference_payload($tenantReferenceTenant)
    : '';
$tenant_reference_qr_url = function_exists('mf_install_tenant_reference_qr_url')
    ? mf_install_tenant_reference_qr_url($tenantReferenceTenant)
    : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title><?php echo $e($company_name); ?> — Official Website</title>
<?php if (!empty($logo)): ?>
<link rel="icon" type="image/png" href="<?php echo htmlspecialchars($logo, ENT_QUOTES, 'UTF-8'); ?>">
<?php endif; ?>
<?php if ($meta_desc): ?><meta name="description" content="<?php echo $e($meta_desc); ?>"><?php endif; ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=<?php echo urlencode($headline_font); ?>:wght@400;600;700;800&family=<?php echo urlencode($body_font); ?>:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,1,0&display=swap" rel="stylesheet"/>

<style>
/* ─── LIVE SITE OVERRIDES ─── */
body { font-family: '<?php echo $e($body_font); ?>', sans-serif; background: #f8fafc; color: #1e293b; }

/* 1. Animations */
.fade-up { opacity: 0; transform: translateY(30px); transition: opacity .6s ease, transform .6s ease; }
.fade-up.visible { opacity: 1; transform: translateY(0); }

/* 2. Hide Builder Tools from Public Visitors! */
.delete-card, 
#add_service_col, 
#btn_open_hero_picker, 
.editable-section:hover::after {
    display: none !important;
}
.editable-icon-wrap { cursor: default !important; pointer-events: none; }
.editable-section:hover { outline: none !important; }
.custom-card { transition: transform .3s; }
.custom-card:hover { transform: translateY(-4px); border-color: var(--brand); }
<?php if ($custom_css) echo "\n" . $custom_css . "\n"; ?>
</style>

</head>
<body>

<?php
// Load the template, strip contenteditable attributes for public view
if (file_exists($template_path)) {
    $is_editor_context = false;
    ob_start();
    include $template_path;
    $raw_html = ob_get_clean();
    $clean_html = preg_replace('/contenteditable="(true|false)"/i', '', $raw_html);
    echo $clean_html;
} else {
    echo "<div class='container py-5 text-center mt-5'><h1>Template Missing</h1><p>Please log into the Website Editor and publish a design.</p></div>";
}
?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Navbar shadow on scroll
window.addEventListener('scroll', function() {
  var nav = document.querySelector('.site-nav');
  if (nav) nav.classList.toggle('scrolled', window.scrollY > 20);
});

// Scroll fade-in
document.querySelectorAll('section > .container').forEach(el => el.classList.add('fade-up'));
var obs = new IntersectionObserver(function(entries) {
  entries.forEach(function(e) { if (e.isIntersecting) { e.target.classList.add('visible'); obs.unobserve(e.target); }});
}, { threshold: 0.12 });
document.querySelectorAll('.fade-up').forEach(function(el) { obs.observe(el); });

<?php if ($show_loan_calc && !empty($loan_products)): ?>
// Calculator Logic
(function() {
  var products = <?php echo json_encode(array_map(function($p) use ($normalize_interest_type) {
      return [
          'name'       => $p['product_name'] ?? 'Loan',
          'min'        => (float)($p['min_amount'] ?? 1000),
          'max'        => (float)($p['max_amount'] ?? 50000),
          'rate'       => (float)($p['interest_rate'] ?? 2.5),
          'type'       => $normalize_interest_type($p['interest_type'] ?? 'Declining Balance'),
          'minTerm'    => (int)($p['min_term_months'] ?? 1),
          'maxTerm'    => (int)($p['max_term_months'] ?? 12),
          'processing' => (float)($p['processing_fee_percentage'] ?? 5),
          'insurance'  => (float)($p['insurance_fee_percentage'] ?? 0),
          'service'    => (float)($p['service_charge'] ?? 0),
          'docStamp'   => (float)($p['documentary_stamp'] ?? 0),
      ];
  }, $loan_products), JSON_UNESCAPED_UNICODE); ?>;

  var amtSlider = document.getElementById('lc-amount-slider');
  var trmSlider = document.getElementById('lc-term-slider');
  var btns      = document.querySelectorAll('.lc-product-btn');
  var selected  = 0;
  
  if(!amtSlider || !trmSlider) return;

  var php       = new Intl.NumberFormat('en-PH', {minimumFractionDigits:0, maximumFractionDigits:0});
  var fmt       = function(n){ return '₱' + php.format(Math.round(n)); };

  function step(min, max){
      var r = max - min;
      return r <= 20000 ? 50 : r <= 100000 ? 100 : r <= 500000 ? 500 : 1000;
  }

  function selectProduct(idx) {
      selected = idx;
      var p = products[idx];
      btns.forEach(function(b, i){ 
          if(i === idx) { b.classList.add('border-primary', 'bg-light'); b.classList.remove('bg-white'); }
          else { b.classList.remove('border-primary', 'bg-light'); b.classList.add('bg-white'); }
      });
      amtSlider.min  = p.min; amtSlider.max  = p.max; amtSlider.step = step(p.min, p.max);
      amtSlider.value = Math.round(((p.min + p.max) / 2) / step(p.min, p.max)) * step(p.min, p.max);
      trmSlider.min  = p.minTerm; trmSlider.max = p.maxTerm;
      trmSlider.value = Math.round((p.minTerm + p.maxTerm) / 2);
      
      var elMinAmt = document.getElementById('lc-min-amount');
      var elMaxAmt = document.getElementById('lc-max-amount');
      var elMinTrm = document.getElementById('lc-min-term');
      var elMaxTrm = document.getElementById('lc-max-term');
      
      if(elMinAmt) elMinAmt.textContent = fmt(p.min);
      if(elMaxAmt) elMaxAmt.textContent = fmt(p.max);
      if(elMinTrm) elMinTrm.textContent = p.minTerm + ' mo';
      if(elMaxTrm) elMaxTrm.textContent = p.maxTerm + ' mo';
      calc();
  }

  function calc() {
      var p = products[selected];
      var amt  = parseFloat(amtSlider.value);
      var term = parseInt(trmSlider.value, 10);
      var rate = p.rate / 100;
      
      var amtDisp = document.getElementById('lc-amount-display');
      var trmDisp = document.getElementById('lc-term-display');
      if(amtDisp) amtDisp.textContent = fmt(amt);
      if(trmDisp) trmDisp.textContent = term + ' mo';
      
      var monthly = 0, interest = 0, total = 0;
      if (p.type === 'Flat' || p.type === 'Fixed') {
          interest = amt * rate * term; total = amt + interest; monthly = total / term;
      } else if (p.type === 'Declining Balance' || p.type === 'Diminishing') {
          monthly  = rate > 0 ? amt * (rate * Math.pow(1+rate,term)) / (Math.pow(1+rate,term)-1) : amt/term;
          total    = monthly * term; interest = total - amt;
      }
      var fee = (amt * (p.processing / 100)) + (amt * (p.insurance / 100)) + p.service + p.docStamp;
      
      var elMo = document.getElementById('lc-monthly');
      var elInt = document.getElementById('lc-interest');
      var elFee = document.getElementById('lc-fee');
      var elTot = document.getElementById('lc-total');
      
      if(elMo) elMo.textContent  = fmt(monthly);
      if(elInt) elInt.textContent = fmt(interest);
      if(elFee) elFee.textContent      = fmt(fee);
      if(elTot) elTot.textContent    = fmt(total);
  }

  btns.forEach(function(b, i){ b.addEventListener('click', function(){ selectProduct(i); }); });
  amtSlider.addEventListener('input', calc);
  trmSlider.addEventListener('input', calc);
  if (products.length > 0) selectProduct(0);
})();
<?php endif; ?>
</script>
</body>
</html>
