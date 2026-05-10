<?php
require_once dirname(__DIR__, 3) . '/microfin_backend/auth/session_auth.php';
mf_start_backend_session();
require_once dirname(__DIR__, 3) . '/microfin_backend/config/db_connect.php';
require_once dirname(__DIR__, 3) . '/microfin_backend/utils/mobile_app_build.php';
mf_require_tenant_session($pdo, [
    'response' => 'die',
    'status' => 403,
    'message' => "<h1>403 Forbidden</h1><p>No valid tenant session.</p>",
]);

$tenant_id   = $_SESSION['tenant_id'];
$tenant_name = $_SESSION['tenant_name'] ?? 'Company Admin';
$tenant_slug = $_SESSION['tenant_slug'] ?? '';

function builder_app_base_path(): string
{
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if (strpos($script, '/admin_panel/website_editor/') !== false) {
        $base = rtrim(str_replace('\\', '/', dirname(dirname(dirname($script)))), '/');
    } else {
        $base = rtrim(str_replace('\\', '/', dirname(dirname($script))), '/');
    }
    return $base === '.' ? '' : $base;
}

function builder_normalize_asset_path(string $path): string
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
    return builder_app_base_path() . '/' . ltrim($path, '/');
}

function builder_store_uploaded_asset(array $file, string $directoryName, string $prefix, string $tenantId, array $allowedExtensions): string
{
    if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return '';
    }

    $extension = strtolower((string)pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    if ($extension === '' || !in_array($extension, $allowedExtensions, true)) {
        return '';
    }

    $baseUploadDir = realpath(dirname(__DIR__, 2) . '/uploads');
    if ($baseUploadDir === false) {
        $baseUploadDir = dirname(__DIR__, 2) . '/uploads';
    }

    $targetDir = rtrim($baseUploadDir, '/\\') . DIRECTORY_SEPARATOR . trim($directoryName, '/\\');
    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        return '';
    }

    $safeTenant = preg_replace('/[^A-Za-z0-9_-]+/', '_', $tenantId);
    
    if ($prefix === 'logo') {
        $filename = $safeTenant . 'logo.' . $extension;
    } elseif ($prefix === 'hero') {
        $filename = $safeTenant . 'hero.' . $extension;
    } else {
        $filename_without_ext = pathinfo((string)($file['name'] ?? ''), PATHINFO_FILENAME);
        $safe_filename = preg_replace('/[^A-Za-z0-9_-]/', '_', $filename_without_ext);
        $filename = $safeTenant . $safe_filename . '.' . $extension;
    }
    
    $destination = $targetDir . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file((string)($file['tmp_name'] ?? ''), $destination)) {
        return '';
    }

    return builder_app_base_path() . '/uploads/' . trim(str_replace('\\', '/', $directoryName), '/') . '/' . $filename;
}

// ==========================================
// AJAX POST Handler — Save Website Content
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    try {
        $jsonRaw = $_POST['json_data'] ?? '{}';
        $payload = json_decode($jsonRaw, true);
        if (!is_array($payload)) $payload = [];

        $existingWebsiteStmt = $pdo->prepare("SELECT layout_template, website_data FROM tenant_website_content WHERE tenant_id = ? LIMIT 1");
        $existingWebsiteStmt->execute([$tenant_id]);
        $existingWebsite = $existingWebsiteStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $existingWebsiteData = [];
        if (!empty($existingWebsite['website_data'])) {
            $decodedWebsiteData = json_decode((string)$existingWebsite['website_data'], true);
            if (is_array($decodedWebsiteData)) {
                $existingWebsiteData = $decodedWebsiteData;
            }
        }

        $existingBrandStmt = $pdo->prepare("SELECT logo_path FROM tenant_branding WHERE tenant_id = ? LIMIT 1");
        $existingBrandStmt->execute([$tenant_id]);
        $existingBrandLogo = trim((string)$existingBrandStmt->fetchColumn());
        $resolvedLogoPath = builder_normalize_asset_path($existingBrandLogo);

        $payload = array_merge($existingWebsiteData, $payload);

        // Extract branding fields (saved to tenant_branding)
        $primary_color = $payload['primary_color'] ?? '#2563eb';
        $border_color  = $payload['border_color'] ?? '#e2e8f0';

        // Update tenant_branding
        $brandUpsert = $pdo->prepare('
            INSERT INTO tenant_branding (tenant_id, theme_primary_color, theme_border_color)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                theme_primary_color = VALUES(theme_primary_color),
                theme_border_color = VALUES(theme_border_color),
                updated_at = CURRENT_TIMESTAMP
        ');
        $brandUpsert->execute([$tenant_id, $primary_color, $border_color]);

        // Handle logo upload
        if (!empty($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
            $logoRelPath = builder_store_uploaded_asset($_FILES['logo_file'], 'tenant_logos', 'logo', (string)$tenant_id, ['png', 'jpg', 'jpeg', 'webp', 'svg']);
            if ($logoRelPath !== '') {
                $pdo->prepare('UPDATE tenant_branding SET logo_path = ? WHERE tenant_id = ?')
                    ->execute([$logoRelPath, $tenant_id]);
                $resolvedLogoPath = $logoRelPath;
            }
        }

        if ($resolvedLogoPath === '' && !empty($payload['logo_url'])) {
            $resolvedLogoPath = builder_normalize_asset_path((string)$payload['logo_url']);
            if ($resolvedLogoPath !== '') {
                $pdo->prepare('INSERT INTO tenant_branding (tenant_id, logo_path) VALUES (?, ?) ON DUPLICATE KEY UPDATE logo_path = VALUES(logo_path), updated_at = CURRENT_TIMESTAMP')
                    ->execute([$tenant_id, $resolvedLogoPath]);
            }
        }

        // Handle hero image upload
        if (!empty($_FILES['hero_image_file']) && $_FILES['hero_image_file']['error'] === UPLOAD_ERR_OK) {
            $storedHeroPath = builder_store_uploaded_asset($_FILES['hero_image_file'], 'hero', 'hero', (string)$tenant_id, ['png', 'jpg', 'jpeg', 'webp']);
            if ($storedHeroPath !== '') {
                $payload['hero_image'] = $storedHeroPath;
            }
        } elseif (!empty($_POST['hero_preset_path'])) {
            $payload['hero_image'] = builder_normalize_asset_path((string)$_POST['hero_preset_path']);
        } elseif (!empty($payload['hero_image'])) {
            $payload['hero_image'] = builder_normalize_asset_path((string)$payload['hero_image']);
        }

        // Extract template selection (saved to layout_template column)
        $selectedTemplate = $payload['selected_template'] ?? ($existingWebsite['layout_template'] ?? 'template1.php');
        // Remove branding/template fields from the JSON payload (they live in their own columns)
        unset($payload['selected_template'], $payload['primary_color'], $payload['border_color'], $payload['logo_url']);

        // Save everything to tenant_website_content
        $websiteUpsert = $pdo->prepare('
            INSERT INTO tenant_website_content (tenant_id, layout_template, website_data)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                layout_template = VALUES(layout_template),
                website_data = VALUES(website_data),
                updated_at = CURRENT_TIMESTAMP
        ');
        $websiteUpsert->execute([
            $tenant_id, 
            $selectedTemplate, 
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        ]);

        // Auto-enable the public website toggle
        $pdo->prepare('INSERT INTO tenant_feature_toggles (tenant_id, toggle_key, is_enabled) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE is_enabled = 1')
            ->execute([$tenant_id, 'public_website_enabled']);

        $buildResult = mf_mobile_app_dispatch_tenant_build(
            $pdo,
            (string) $tenant_id,
            (string) $tenant_slug,
            trim((string) ($payload['company_name'] ?? $tenant_name))
        );

        echo json_encode([
            'status' => 'success',
            'logo_url' => $resolvedLogoPath,
            'hero_image' => (string)($payload['hero_image'] ?? ''),
            'mobile_app_build' => $buildResult,
        ]);
    } catch (Exception $ex) {
        echo json_encode(['status' => 'error', 'message' => $ex->getMessage()]);
    }
    exit;
}

// ==========================================
// Load existing data for the editor
// ==========================================
$pageData = [];
$branding = [];

// Fetch Branding
$stmtBrand = $pdo->prepare("SELECT * FROM tenant_branding WHERE tenant_id = ?");
$stmtBrand->execute([$tenant_id]);
$branding = $stmtBrand->fetch(PDO::FETCH_ASSOC) ?: [];

// Fetch Website Content
$stmtWeb = $pdo->prepare("SELECT layout_template, website_data FROM tenant_website_content WHERE tenant_id = ?");
$stmtWeb->execute([$tenant_id]);
$website = $stmtWeb->fetch(PDO::FETCH_ASSOC);

// Decode the JSON bucket
if ($website && !empty($website['website_data'])) {
    $pageData = json_decode($website['website_data'], true);
    if (!is_array($pageData)) $pageData = [];
}

// ==========================================
// DYNAMIC TEMPLATE SCANNER
// ==========================================
$templates_dir = __DIR__ . '/templates/';
$available_templates = [];
if (is_dir($templates_dir)) {
    foreach (scandir($templates_dir) as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
            $available_templates[] = $file;
        }
    }
}
if (empty($available_templates)) $available_templates[] = 'template1.php';

// Preview template from URL or from DB
$selected_template = $_GET['tpl'] ?? $website['layout_template'] ?? $available_templates[0];
if (!in_array($selected_template, $available_templates)) $selected_template = $available_templates[0];

// ==========================================
// MANIFEST READER (Schema-Driven UI)
// ==========================================
$template_name_only = str_replace('.php', '', $selected_template);
$manifest_path = $templates_dir . $template_name_only . '.json';
$template_rules = [];

if (file_exists($manifest_path)) {
    $template_rules = json_decode(file_get_contents($manifest_path), true);
}

// ==========================================
// PRESET TEMPLATE IMAGES
// ==========================================
$preset_dir_relative = '../../uploads/hero/presets/';
$preset_dir = dirname(__DIR__, 2) . '/uploads/hero/presets/';
$preset_images = [];
if (is_dir($preset_dir)) {
    foreach (scandir($preset_dir) as $file) {
        if (in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'])) {
            $preset_images[] = $preset_dir_relative . $file;
        }
    }
}
if (empty($preset_images)) {
    $preset_images = [
        'https://images.unsplash.com/photo-1556740714-a8395b3bf30f?auto=format&fit=crop&w=600&h=800&q=80',
        'https://images.unsplash.com/photo-1556742044-3c52d6e88c62?auto=format&fit=crop&w=600&h=800&q=80',
        'https://images.unsplash.com/photo-1595841696677-6489ff3f8cd1?auto=format&fit=crop&w=600&h=800&q=80'
    ];
} else {
    $preset_images = array_map(static fn($img) => builder_normalize_asset_path((string)$img), $preset_images);
}

// ==========================================
// GLOBAL TOKENS
// ==========================================
$logo          = builder_normalize_asset_path((string)($branding['logo_path'] ?? ($pageData['logo_url'] ?? '')));
$primary       = $branding['theme_primary_color'] ?? '#2563eb';
$border_color  = $branding['theme_border_color'] ?? '#e2e8f0';
$border_radius = $pageData['border_radius'] ?? '16';
$shadow        = $pageData['shadow_intensity'] ?? '0.1';

// Typography & Buttons
$text_heading_color = $pageData['text_heading_color'] ?? '#0f172a';
$text_body_color    = $pageData['text_body_color'] ?? '#64748b';
$btn_bg_color       = $pageData['btn_bg_color'] ?? $primary;
$btn_text_color     = $pageData['btn_text_color'] ?? '#ffffff';

// Content Strings
$company_name     = $pageData['company_name'] ?? $tenant_name;
$short_name       = $pageData['short_name'] ?? '';
$display_name     = $short_name ? $short_name : $company_name;

$hero_title       = $pageData['hero_title'] ?? 'Empowering Your Financial Future';
$hero_subtitle    = $pageData['hero_subtitle'] ?? 'Get flexible loans with fast approval and transparent terms.';
$hero_badge_text  = $pageData['hero_badge_text'] ?? 'Verified Partner';

$about_body       = $pageData['about_body'] ?? 'We believe in empowering our community through accessible financial tools.';
$download_description = $pageData['download_description'] ?? 'Track your loans, submit applications, receive notifications...';

$contact_address = $pageData['contact_address'] ?? '123 Finance Ave, Business District';
$contact_phone   = $pageData['contact_phone'] ?? '0900-123-4567';
$contact_email   = $pageData['contact_email'] ?? 'hello@microfin.os';
$contact_hours   = $pageData['contact_hours'] ?? 'Mon-Fri: 8AM - 5PM';
$footer_desc     = $pageData['footer_desc'] ?? 'Your trusted microfinance partner.';

// Hero Image
$hero_image       = builder_normalize_asset_path((string)($pageData['hero_image'] ?? ''));
$default_hero_img = $preset_images[0] ?? '';
$display_image    = $hero_image ? $hero_image : $default_hero_img;
$logo_action_label = $logo !== '' ? 'Change Logo' : 'Upload Logo';
$hero_action_label = $hero_image !== '' ? 'Change Hero Image' : 'Upload Hero Image';

// Arrays
$services = $pageData['services'] ?? [['icon' => 'person', 'title' => 'Personal Loan', 'description' => 'Fast approval for your personal needs.']];
if (!is_array($services)) $services = [];

$stats = $pageData['stats'] ?? [['value' => '1.5k+', 'label' => 'Active Members']];
if (!is_array($stats)) $stats = [];

$loan_products = [];
try {
    $loanProductStmt = $pdo->prepare('SELECT product_name, product_type, min_amount, max_amount, interest_rate, interest_type, min_term_months, max_term_months, processing_fee_percentage, insurance_fee_percentage, service_charge, documentary_stamp FROM loan_products WHERE tenant_id = ? AND is_active = 1 ORDER BY product_name');
    $loanProductStmt->execute([$tenant_id]);
    $loan_products = $loanProductStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $ex) {
    $loan_products = [];
}
if (empty($loan_products)) {
    $loan_products = $pageData['loan_products'] ?? [[
        'product_name' => 'Demo Personal Loan',
        'product_type' => 'Personal Loan',
        'min_amount' => 1000,
        'max_amount' => 50000,
        'interest_rate' => 2.5,
        'interest_type' => 'Flat',
        'min_term_months' => 1,
        'max_term_months' => 12,
        'processing_fee_percentage' => 5,
        'insurance_fee_percentage' => 0,
        'service_charge' => 0,
        'documentary_stamp' => 0,
    ]];
}
if (!is_array($loan_products)) $loan_products = [];

// Section toggles
$show_services  = isset($pageData['show_services']) ? filter_var($pageData['show_services'], FILTER_VALIDATE_BOOLEAN) : true;
$show_stats     = isset($pageData['show_stats']) ? filter_var($pageData['show_stats'], FILTER_VALIDATE_BOOLEAN) : true;
$show_loan_calc = isset($pageData['show_calculator']) ? filter_var($pageData['show_calculator'], FILTER_VALIDATE_BOOLEAN) : true;
$show_about     = isset($pageData['show_about']) ? filter_var($pageData['show_about'], FILTER_VALIDATE_BOOLEAN) : true;
$show_download  = isset($pageData['show_download']) ? filter_var($pageData['show_download'], FILTER_VALIDATE_BOOLEAN) : true;

// Helpers
$sec_styles = $pageData['section_styles'] ?? [];
function getBgStyle($secId, $styles, $defaultBg)
{
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
$e = function ($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
};

$admin_url = builder_app_base_path() . '/admin_panel/admin.php';
$site_url = builder_app_base_path() . '/public_website/site.php?site=' . urlencode($tenant_slug);
$save_endpoint = builder_app_base_path() . '/admin_panel/website_editor/index.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Website Editor — <?php echo $e($company_name); ?></title>
    
    <?php if (!empty($logo)): ?>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($logo, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;700;800&family=Public+Sans:wght@300;400;500;600&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,1,0&display=swap" rel="stylesheet" />
    <style>
        body {
            margin: 0;
            padding: 0;
            display: flex;
            background: #f1f5f9;
            font-family: 'Public Sans', sans-serif;
            overflow: hidden;
        }

        .editor-sidebar {
            width: 320px;
            height: 100vh;
            background: #fff;
            border-right: 1px solid #e2e8f0;
            padding: 24px;
            flex-shrink: 0;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 2px 0 15px rgba(0, 0, 0, 0.03);
        }

        .editor-canvas {
            flex-grow: 1;
            height: 100vh;
            overflow-y: auto;
            position: relative;
            background: #fff;
        }

        [contenteditable="true"] {
            outline: none;
            transition: all 0.2s;
            border: 2px dashed transparent;
            border-radius: 4px;
            padding: 2px 4px;
            margin: -2px -4px;
            cursor: text;
        }

        [contenteditable="true"]:hover {
            border-color: #94a3b8;
            background: rgba(255, 255, 255, 0.6);
        }

        [contenteditable="true"]:focus {
            border-style: solid;
            border-color: var(--brand, #2563eb);
            background: #fff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            color: #000 !important;
        }

        .editable-icon-wrap {
            cursor: pointer;
            transition: all 0.2s;
        }

        .editable-icon-wrap:hover {
            outline: 2px dashed var(--brand, #2563eb);
            outline-offset: 4px;
            background: #fff !important;
        }

        .icon-option {
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            color: var(--brand, #2563eb);
        }

        .icon-option:hover {
            background: rgba(37, 99, 235, 0.15);
            transform: scale(1.1);
        }

        .editable-section {
            position: relative;
            transition: outline 0.2s;
        }

        .editable-section:hover {
            outline: 3px solid rgba(37, 99, 235, 0.5);
            outline-offset: -3px;
        }

        #sectionToolbar {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: #1e293b;
            color: white;
            padding: 10px 20px;
            border-radius: 99px;
            z-index: 9999;
            display: none;
            align-items: center;
            gap: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            transition: all 0.3s;
        }

        .toolbar-divider {
            width: 1px;
            height: 24px;
            background: rgba(255, 255, 255, 0.2);
        }

        .back-link {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
            color: #64748b;
            text-decoration: none;
            margin-bottom: 16px;
            padding: 8px 12px;
            border-radius: 8px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }
        .back-link:hover { background: #f1f5f9; color: #0f172a; }
    </style>
</head>

<body>
    <div class="editor-sidebar">
        <a href="<?php echo $e($admin_url); ?>" class="back-link">
            <span class="material-symbols-rounded" style="font-size:18px;">arrow_back</span> Back to Admin
        </a>

        <h5 class="fw-bold mb-4 headline" style="color: #0f172a;">Site Settings</h5>

        <div class="small fw-bold text-muted mb-2">DESIGN TEMPLATE</div>
        <div class="mb-4">
            <select id="inp_template" class="form-select form-select-sm fw-bold border-primary text-primary shadow-sm" style="cursor: pointer;">
                <?php foreach ($available_templates as $tpl): ?>
                    <option value="<?php echo $tpl; ?>" <?php echo $selected_template === $tpl ? 'selected' : ''; ?>>
                        <?php echo ucwords(str_replace(['.php', '-', '_'], ['', ' ', ' '], $tpl)); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="small fw-bold text-muted mb-2">COMPANY & BRANDING</div>
        <div class="mb-4 p-3 rounded" style="background: #f8fafc; border: 1px solid #e2e8f0;">
            <label class="form-label small fw-bold">Company Name</label>
            <input type="text" id="inp_company_name" class="form-control form-control-sm mb-2" value="<?php echo $e($company_name); ?>">

            <label class="form-label small fw-bold mt-1">Brand Acronym <span class="text-muted fw-normal">(Optional)</span></label>
            <input type="text" id="inp_short_name" class="form-control form-control-sm mb-1" placeholder="e.g. BDO" value="<?php echo $e($short_name); ?>">

            <label class="form-label small fw-bold border-top pt-3 w-100"><?php echo $e($logo_action_label); ?></label>
            <input type="file" id="inp_logo" class="d-none" accept="image/png, image/jpeg, image/svg+xml">
            <label for="inp_logo" id="logo_upload_action" class="btn btn-outline-primary w-100 fw-bold mb-2" style="cursor:pointer;"><?php echo $e($logo_action_label); ?></label>
            <?php if ($logo !== ''): ?>
                <div class="small d-flex align-items-center gap-2 rounded border bg-white px-2 py-2 mb-2">
                    <img id="current_logo_thumb" src="<?php echo $e($logo); ?>" alt="Current logo" style="width:44px;height:44px;object-fit:contain;border-radius:10px;border:1px solid #e2e8f0;padding:4px;background:#fff;">
                    <div>
                        <div class="fw-bold text-dark">Current logo loaded</div>
                        <div class="text-muted">This saved logo is already being used by the website.</div>
                    </div>
                </div>
            <?php endif; ?>
            <button id="btn_match_color" class="btn btn-sm btn-outline-primary w-100 fw-bold <?php echo $logo !== '' ? '' : 'd-none'; ?> mb-3">✨ Match Color to Logo</button>

            <label class="form-label small fw-bold mt-2">Primary Color</label>
            <input type="color" id="inp_color" class="form-control form-control-color w-100" value="<?php echo $primary; ?>">
        </div>
        
        <div class="small fw-bold text-muted mb-2 mt-4">TYPOGRAPHY & BUTTONS</div>
        <div class="mb-4 p-3 rounded" style="background: #f8fafc; border: 1px solid #e2e8f0;">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <label class="form-label small fw-bold mb-0">Heading Text</label>
                <input type="color" id="inp_text_heading" class="form-control form-control-color p-0 border-0" style="width:30px;height:30px;" value="<?php echo $text_heading_color; ?>">
            </div>
            <div class="d-flex align-items-center justify-content-between mb-3">
                <label class="form-label small fw-bold mb-0">Body Text</label>
                <input type="color" id="inp_text_body" class="form-control form-control-color p-0 border-0" style="width:30px;height:30px;" value="<?php echo $text_body_color; ?>">
            </div>
            
            <div class="border-top pt-3 mb-2 d-flex align-items-center justify-content-between">
                <label class="form-label small fw-bold mb-0">Button Color</label>
                <input type="color" id="inp_btn_bg" class="form-control form-control-color p-0 border-0" style="width:30px;height:30px;" value="<?php echo $btn_bg_color; ?>">
            </div>
            <div class="d-flex align-items-center justify-content-between">
                <label class="form-label small fw-bold mb-0">Button Text</label>
                <input type="color" id="inp_btn_text" class="form-control form-control-color p-0 border-0" style="width:30px;height:30px;" value="<?php echo $btn_text_color; ?>">
            </div>
        </div>

        <div class="small fw-bold text-muted mb-2">STRUCTURE</div>
        <div class="mb-3">
            <label class="form-label small fw-bold">Border Color</label>
            <input type="color" id="inp_border" class="form-control form-control-color w-100" value="<?php echo $border_color; ?>">
        </div>
        <div class="mb-3">
            <label class="form-label small fw-bold">Rounded Corners</label>
            <input type="range" id="inp_radius" class="form-range" min="0" max="40" value="<?php echo $border_radius; ?>">
        </div>
        <div class="mb-4">
            <label class="form-label small fw-bold">Box Shadow</label>
            <input type="range" id="inp_shadow" class="form-range" min="0" max="0.3" step="0.05" value="<?php echo $shadow; ?>">
        </div>

        <div class="small fw-bold text-muted mb-2">SECTIONS</div>

        <?php
        $sidebar_sections = $template_rules['sections'] ?? [];
        foreach ($sidebar_sections as $sec):
            $toggle_key = 'show_' . $sec['id'];
            $target_id  = $sec['target'];
            $is_checked = isset($pageData[$toggle_key]) ? filter_var($pageData[$toggle_key], FILTER_VALIDATE_BOOLEAN) : $sec['default_state'];
        ?>
            <div class="form-check form-switch mb-2">
                <input class="form-check-input toggle-section dynamic-toggle"
                    type="checkbox"
                    id="toggle_<?php echo $sec['id']; ?>"
                    data-key="<?php echo $toggle_key; ?>"
                    data-target="<?php echo $target_id; ?>"
                    <?php echo $is_checked ? 'checked' : ''; ?>>
                <label class="form-check-label small"><?php echo $sec['label']; ?></label>
            </div>
        <?php endforeach; ?>

        <div class="d-flex gap-2 mt-3">
            <button id="saveBtn" class="btn btn-primary flex-grow-1 fw-bold py-2 shadow">Save & Publish</button>
            <a href="<?php echo $e($site_url); ?>" target="_blank" class="btn btn-outline-secondary py-2" title="Preview Live Site">
                <span class="material-symbols-rounded" style="font-size:18px;">open_in_new</span>
            </a>
        </div>
    </div>

    <div class="editor-canvas" id="canvasArea">
        <img id="hiddenLogo" style="display:none;" <?php if ($logo) echo 'src="' . $e($logo) . '"'; ?>>
        <canvas id="colorCanvas" style="display:none;"></canvas>
        <?php
        $is_editor_context = true;
        $full_template_path = $templates_dir . $selected_template;
        if (file_exists($full_template_path)) {
            include $full_template_path;
        } else {
            echo "<div class='p-5 text-center'><h3>Template Not Found</h3><p>Path: $full_template_path</p></div>";
        }
        ?>
    </div>

    <div id="imagePickerOverlay" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.6); z-index:10050; align-items:center; justify-content:center; backdrop-filter: blur(4px);">
        <div class="bg-white p-4 rounded-4 shadow-lg" style="width: 550px; max-width: 90vw;">
            <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                <h6 class="fw-bold mb-0 text-dark">Choose Hero Image</h6>
                <button type="button" class="btn-close" id="closeImagePicker"></button>
            </div>
            <div class="mb-4">
                <label for="inp_hero_upload" id="hero_upload_action" class="btn btn-primary w-100 fw-bold text-center" style="cursor:pointer; display:block;">
                    <?php echo $e($hero_action_label); ?>
                </label>
                <input type="file" id="inp_hero_upload" class="d-none" accept="image/png, image/jpeg, image/webp">
            </div>
            <div class="d-flex flex-wrap gap-2" style="max-height: 300px; overflow-y:auto;">
                <?php foreach ($preset_images as $img): ?>
                    <div class="preset-img-option" style="width: 110px; height: 110px; cursor: pointer; border-radius: 8px; overflow: hidden; border: 2px solid transparent;">
                        <img src="<?php echo $img; ?>" style="width:100%; height:100%; object-fit:cover;" data-src="<?php echo $img; ?>">
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div id="iconPickerOverlay" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.4); z-index:10050; align-items:center; justify-content:center;">
        <div class="bg-white p-4 rounded-4 shadow-lg" style="width: 420px; max-width: 90vw;">
            <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                <h6 class="fw-bold mb-0 text-dark">Choose an Icon</h6>
                <button type="button" class="btn-close" id="closeIconPicker"></button>
            </div>
            <div class="d-flex flex-wrap gap-2 justify-content-center" id="iconGrid" style="max-height: 350px; overflow-y:auto; padding: 10px;"></div>
        </div>
    </div>

    <div id="sectionToolbar">
        <div class="d-flex align-items-center gap-2">
            <span class="small fw-bold">Color 1:</span>
            <input type="color" id="secBgColor" class="form-control form-control-color p-0" style="width: 30px; height: 30px; border:none; cursor:pointer;">
        </div>
        <div class="form-check form-switch mb-0 ms-2">
            <input class="form-check-input" type="checkbox" id="secGradientToggle">
            <label class="form-check-label small fw-bold">Gradient</label>
        </div>
        <div id="gradControls" class="d-none align-items-center gap-3 ms-2 border-start border-secondary ps-3">
            <div class="d-flex align-items-center gap-2">
                <span class="small fw-bold">Color 2:</span>
                <input type="color" id="secBgColor2" class="form-control form-control-color p-0" style="width: 30px; height: 30px; border:none; cursor:pointer;" value="#e2e8f0">
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="small fw-bold">Direction:</span>
                <select id="secGradDir" class="form-select form-select-sm" style="width: 140px; cursor:pointer;">
                    <option value="135deg">↘ Bottom Right</option>
                    <option value="to right">➡ Right</option>
                    <option value="to bottom">⬇ Bottom</option>
                    <option value="45deg">↗ Top Right</option>
                    <option value="circle">⚪ Radial (Circle)</option>
                </select>
            </div>
        </div>
        <div class="toolbar-divider mx-2"></div>
        <button id="closeSecToolbar" class="btn btn-link text-white p-0 text-decoration-none d-flex align-items-center" title="Close">
            <span class="material-symbols-rounded">close</span>
        </button>
    </div>

    <script>
        const root = document.documentElement;
        let activeSection = null;
        let sectionStyles = <?php echo json_encode($sec_styles); ?>;

        document.getElementById('inp_color').addEventListener('input', (e) => root.style.setProperty('--brand', e.target.value));
        document.getElementById('inp_border').addEventListener('input', (e) => root.style.setProperty('--border-clr', e.target.value));
        document.getElementById('inp_radius').addEventListener('input', (e) => root.style.setProperty('--radius', e.target.value + 'px'));
        document.getElementById('inp_shadow').addEventListener('input', (e) => root.style.setProperty('--shadow', `0 8px 24px rgba(0,0,0,${e.target.value})`));

        // Typography & Button Live Preview
        document.getElementById('inp_text_heading').addEventListener('input', (e) => root.style.setProperty('--text-heading', e.target.value));
        document.getElementById('inp_text_body').addEventListener('input', (e) => root.style.setProperty('--text-body', e.target.value));
        document.getElementById('inp_btn_bg').addEventListener('input', (e) => root.style.setProperty('--btn-bg', e.target.value));
        document.getElementById('inp_btn_text').addEventListener('input', (e) => root.style.setProperty('--btn-text', e.target.value));

        document.getElementById('inp_company_name').addEventListener('input', function() {
            document.querySelectorAll('.display-company-name').forEach(el => el.innerText = this.value);
            if (!document.getElementById('inp_short_name').value.trim()) {
                document.querySelectorAll('.display-short-name').forEach(el => el.innerText = this.value);
            }
        });

        document.getElementById('inp_short_name').addEventListener('input', function() {
            const val = this.value.trim() || document.getElementById('inp_company_name').value;
            document.querySelectorAll('.display-short-name').forEach(el => el.innerText = val);
        });

        const inpLogo = document.getElementById('inp_logo');
        const previewLogo = document.getElementById('preview_logo');
        const hiddenLogo = document.getElementById('hiddenLogo');
        const currentLogoThumb = document.getElementById('current_logo_thumb');
        const logoUploadAction = document.getElementById('logo_upload_action');
        const btnMatch = document.getElementById('btn_match_color');
        let uploadedLogoFile = null;
        let currentLogoPath = <?php echo json_encode($logo, JSON_UNESCAPED_SLASHES); ?>;
        let currentHeroImagePath = <?php echo json_encode($hero_image, JSON_UNESCAPED_SLASHES); ?>;

        inpLogo.addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                uploadedLogoFile = this.files[0];
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (previewLogo) {
                        previewLogo.src = e.target.result;
                        previewLogo.style.display = 'block';
                    }
                    hiddenLogo.src = e.target.result;
                    if (logoUploadAction) {
                        logoUploadAction.textContent = 'Change Logo';
                    }
                    btnMatch.classList.remove('d-none');
                    saveToServer();
                }
                reader.readAsDataURL(uploadedLogoFile);
            }
        });

        // Dominant color extraction
        function getDominantLogoColor() {
            if (!hiddenLogo.src || (hiddenLogo.src.includes(window.location.host) === false && !hiddenLogo.src.startsWith('data:'))) return null;
            try {
                const cvs = document.getElementById('colorCanvas');
                const ctx = cvs.getContext('2d', { willReadFrequently: true });
                cvs.width = hiddenLogo.width || 100;
                cvs.height = hiddenLogo.height || 100;
                ctx.drawImage(hiddenLogo, 0, 0, cvs.width, cvs.height);
                const data = ctx.getImageData(0, 0, cvs.width, cvs.height).data;
                let r = 0, g = 0, b = 0, count = 0;
                for (let i = 0; i < data.length; i += 16) {
                    if (data[i + 3] > 0) { r += data[i]; g += data[i + 1]; b += data[i + 2]; count++; }
                }
                if (count > 0) {
                    return "#" + ((1 << 24) + (Math.floor(r / count) << 16) + (Math.floor(g / count) << 8) + Math.floor(b / count)).toString(16).slice(1);
                }
            } catch (e) { console.warn("Could not extract color.", e); }
            return null;
        }

        btnMatch.addEventListener('click', function() {
            const hex = getDominantLogoColor();
            if (hex) {
                document.getElementById('inp_color').value = hex;
                root.style.setProperty('--brand', hex);
                this.innerText = "Matched! ✅";
                setTimeout(() => this.innerText = "✨ Match Color to Logo", 2000);
                saveToServer();
            }
        });

        // Icon picker
        const iconList = [
            'star', 'home', 'payments', 'account_balance_wallet', 'store', 'rocket', 'verified',
            'shield', 'speed', 'support_agent', 'trending_up', 'group', 'emergency_home',
            'credit_card', 'savings', 'monetization_on', 'real_estate_agent', 'handshake',
            'health_and_safety', 'school', 'agriculture', 'spa', 'bolt', 'lightbulb'
        ];

        const iconGrid = document.getElementById('iconGrid');
        const iconPickerOverlay = document.getElementById('iconPickerOverlay');
        let currentIconTarget = null;

        iconList.forEach(icon => {
            const el = document.createElement('div');
            el.className = 'icon-option';
            el.innerHTML = `<span class="material-symbols-rounded fs-2">${icon}</span>`;
            el.onclick = () => {
                if (currentIconTarget) { currentIconTarget.innerText = icon; saveToServer(); }
                iconPickerOverlay.style.display = 'none';
            };
            iconGrid.appendChild(el);
        });

        document.getElementById('canvasArea').addEventListener('click', function(e) {
            const iconWrap = e.target.closest('.editable-icon-wrap');
            if (iconWrap) {
                currentIconTarget = iconWrap.querySelector('.service-icon-text');
                iconPickerOverlay.style.display = 'flex';
            }
        });
        document.getElementById('closeIconPicker').addEventListener('click', () => { iconPickerOverlay.style.display = 'none'; });

        // Hero Image Picker
        const imagePickerOverlay = document.getElementById('imagePickerOverlay');
        const closeImagePicker = document.getElementById('closeImagePicker');
        const inpHeroUpload = document.getElementById('inp_hero_upload');
        const heroUploadAction = document.getElementById('hero_upload_action');
        let uploadedHeroFile = null;
        let selectedPresetHero = "";

        document.getElementById('canvasArea').addEventListener('click', function(e) {
            if (e.target && e.target.id === 'btn_open_hero_picker') { imagePickerOverlay.style.display = 'flex'; }
        });
        closeImagePicker.addEventListener('click', () => imagePickerOverlay.style.display = 'none');

        window.addEventListener('load', () => {
            const previewHero = document.getElementById('preview_hero');
            const heroContainer = document.getElementById('hero_img_container');
            if (previewHero && previewHero.getAttribute('src') && previewHero.getAttribute('src') !== '') {
                const tempImg = new Image();
                tempImg.onload = function() { heroContainer.style.aspectRatio = this.width + " / " + this.height; };
                tempImg.src = previewHero.getAttribute('src');
            }
        });

        document.querySelectorAll('.preset-img-option img').forEach(img => {
            img.addEventListener('click', function() {
                const src = this.getAttribute('data-src');
                const previewHero = document.getElementById('preview_hero');
                const heroContainer = document.getElementById('hero_img_container');
                if (previewHero) {
                    previewHero.src = src;
                    previewHero.style.display = 'block';
                }
                currentHeroImagePath = src;
                selectedPresetHero = src; uploadedHeroFile = null; inpHeroUpload.value = '';
                if (heroUploadAction) {
                    heroUploadAction.textContent = 'Change Hero Image';
                }
                const tempImg = new Image();
                tempImg.onload = function() { if (heroContainer) heroContainer.style.aspectRatio = this.width + " / " + this.height; };
                tempImg.src = src;
                imagePickerOverlay.style.display = 'none';
                saveToServer();
            });
        });

        inpHeroUpload.addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                uploadedHeroFile = this.files[0]; selectedPresetHero = "";
                const reader = new FileReader();
                reader.onload = function(e) {
                    const previewHero = document.getElementById('preview_hero');
                    const heroContainer = document.getElementById('hero_img_container');
                    if (previewHero) {
                        previewHero.src = e.target.result;
                        previewHero.style.display = 'block';
                    }
                    if (heroUploadAction) {
                        heroUploadAction.textContent = 'Change Hero Image';
                    }
                    const tempImg = new Image();
                    tempImg.onload = function() { if (heroContainer) heroContainer.style.aspectRatio = this.width + " / " + this.height; };
                    tempImg.src = e.target.result;
                }
                reader.readAsDataURL(uploadedHeroFile);
                imagePickerOverlay.style.display = 'none';
                saveToServer();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.target.isContentEditable && e.key === 'Enter') { e.preventDefault(); e.target.blur(); }
        });

        // Service card blueprint
        let serviceCardBlueprint = '';
        window.addEventListener('load', () => {
            const firstCard = document.querySelector('.service-col');
            if (firstCard) {
                const clone = firstCard.cloneNode(true);
                const title = clone.querySelector('.service-title'); if (title) title.innerText = 'New Service';
                const desc = clone.querySelector('.service-desc'); if (desc) desc.innerText = 'Describe your new service here.';
                const icon = clone.querySelector('.service-icon-text'); if (icon) icon.innerText = 'star';
                serviceCardBlueprint = clone.outerHTML;
            }
        });

        window.addServiceCard = function() {
            if (!serviceCardBlueprint) { console.error("No blueprint."); return; }
            const addCol = document.getElementById('add_service_col');
            if (addCol) addCol.insertAdjacentHTML('beforebegin', serviceCardBlueprint);
        };

        document.getElementById('canvasArea').addEventListener('click', function(e) {
            if (e.target.classList.contains('delete-card')) { e.target.closest('.service-col').remove(); saveToServer(); }
        });

        // Section toolbar
        const secToolbar = document.getElementById('sectionToolbar');
        const secBgColor = document.getElementById('secBgColor');
        const secGradToggle = document.getElementById('secGradientToggle');
        const gradControls = document.getElementById('gradControls');
        const secBgColor2 = document.getElementById('secBgColor2');
        const secGradDir = document.getElementById('secGradDir');

        document.getElementById('canvasArea').addEventListener('click', function(e) {
            const sec = e.target.closest('.editable-section');
            if (!sec || e.target.isContentEditable || e.target.closest('button') || e.target.closest('input') || e.target.closest('.editable-icon-wrap')) return;
            activeSection = sec;
            secToolbar.style.display = 'flex';
            const secId = sec.id;
            if (!sectionStyles[secId]) sectionStyles[secId] = { bg: '#ffffff', gradient: false, grad_color2: '#e2e8f0', grad_dir: '135deg' };
            secBgColor.value = sectionStyles[secId].bg || '#ffffff';
            secGradToggle.checked = sectionStyles[secId].gradient || false;
            secBgColor2.value = sectionStyles[secId].grad_color2 || '#e2e8f0';
            secGradDir.value = sectionStyles[secId].grad_dir || '135deg';
            if (secGradToggle.checked) { gradControls.classList.remove('d-none'); gradControls.classList.add('d-flex'); }
            else { gradControls.classList.add('d-none'); gradControls.classList.remove('d-flex'); }
        });

        function updateSectionBackground() {
            if (!activeSection) return;
            const secId = activeSection.id;
            const c1 = secBgColor.value; const isGrad = secGradToggle.checked; const c2 = secBgColor2.value; const dir = secGradDir.value;
            sectionStyles[secId] = { bg: c1, gradient: isGrad, grad_color2: c2, grad_dir: dir };
            if (isGrad) {
                if (dir === 'circle') activeSection.style.background = `radial-gradient(circle, ${c1} 0%, ${c2} 100%)`;
                else activeSection.style.background = `linear-gradient(${dir}, ${c1} 0%, ${c2} 100%)`;
            } else { activeSection.style.background = c1; }
        }

        secBgColor.addEventListener('input', updateSectionBackground);
        secBgColor2.addEventListener('input', updateSectionBackground);
        secGradDir.addEventListener('change', updateSectionBackground);
        secGradToggle.addEventListener('change', (e) => {
            if (e.target.checked) { gradControls.classList.remove('d-none'); gradControls.classList.add('d-flex'); }
            else { gradControls.classList.add('d-none'); gradControls.classList.remove('d-flex'); }
            updateSectionBackground();
        });
        document.getElementById('closeSecToolbar').addEventListener('click', () => { secToolbar.style.display = 'none'; activeSection = null; });

        // Section toggles
        document.querySelector('.editor-sidebar').addEventListener('change', function(e) {
            if (e.target && e.target.classList.contains('toggle-section')) {
                const targetId = e.target.getAttribute('data-target');
                const targetEl = document.getElementById(targetId);
                if (targetEl) targetEl.style.display = e.target.checked ? 'block' : 'none';
                saveToServer();
            }
        });

        // ─── SAVE TO SERVER LOGIC ───
        function saveToServer() {
            const btn = document.getElementById('saveBtn');
            if (btn.disabled) return;
            btn.innerText = 'Publishing...';
            btn.disabled = true;

            const formData = new FormData();
            if (uploadedLogoFile) formData.append('logo_file', uploadedLogoFile);
            if (uploadedHeroFile) formData.append('hero_image_file', uploadedHeroFile);
            if (selectedPresetHero !== "") formData.append('hero_preset_path', selectedPresetHero);

            // Collect services
            const currentServices = [];
            document.querySelectorAll('.service-col').forEach(col => {
                currentServices.push({
                    icon: col.querySelector('.service-icon-text') ? col.querySelector('.service-icon-text').innerText.trim() : 'star',
                    title: col.querySelector('.service-title') ? col.querySelector('.service-title').innerText.trim() : '',
                    description: col.querySelector('.service-desc') ? col.querySelector('.service-desc').innerText.trim() : ''
                });
            });

            // Dynamically catch all toggles
            const dynamicToggles = {};
            document.querySelectorAll('.dynamic-toggle').forEach(toggle => {
                const key = toggle.getAttribute('data-key');
                dynamicToggles[key] = toggle.checked;
            });

            // Build payload
            const jsonPayload = {
                selected_template: document.getElementById('inp_template').value,
                company_name: document.getElementById('inp_company_name').value.trim(),
                short_name: document.getElementById('inp_short_name').value.trim(),
                logo_url: currentLogoPath,
                hero_image: currentHeroImagePath,

                primary_color: document.getElementById('inp_color').value,

                text_heading_color: document.getElementById('inp_text_heading').value,
                text_body_color: document.getElementById('inp_text_body').value,
                btn_bg_color: document.getElementById('inp_btn_bg').value,
                btn_text_color: document.getElementById('inp_btn_text').value,

                border_color: document.getElementById('inp_border').value,
                border_radius: document.getElementById('inp_radius').value,
                shadow_intensity: document.getElementById('inp_shadow').value,

                ...dynamicToggles,
                section_styles: sectionStyles,
                services: currentServices
            };

            // Grab all editable text
            document.querySelectorAll('[data-edit]').forEach(el => {
                jsonPayload[el.getAttribute('data-edit')] = el.innerText.trim();
            });

            formData.append('json_data', JSON.stringify(jsonPayload));

            fetch(<?php echo json_encode($save_endpoint, JSON_UNESCAPED_SLASHES); ?>, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        currentLogoPath = data.logo_url || currentLogoPath;
                        currentHeroImagePath = data.hero_image || currentHeroImagePath;
                        if (currentLogoPath) {
                            if (previewLogo) {
                                previewLogo.src = currentLogoPath;
                                previewLogo.style.display = 'block';
                            }
                            hiddenLogo.src = currentLogoPath;
                            if (logoUploadAction) {
                                logoUploadAction.textContent = 'Change Logo';
                            }
                            if (currentLogoThumb) {
                                currentLogoThumb.src = currentLogoPath;
                            }
                            btnMatch.classList.remove('d-none');
                        }
                        if (currentHeroImagePath) {
                            const previewHero = document.getElementById('preview_hero');
                            if (previewHero) {
                                previewHero.src = currentHeroImagePath;
                                previewHero.style.display = 'block';
                            }
                            if (heroUploadAction) {
                                heroUploadAction.textContent = 'Change Hero Image';
                            }
                        }
                        uploadedLogoFile = null;
                        uploadedHeroFile = null;
                        selectedPresetHero = "";
                        inpLogo.value = '';
                        inpHeroUpload.value = '';
                        btn.innerText = 'Published! ✅';
                        setTimeout(() => { btn.innerText = 'Save & Publish'; btn.disabled = false; }, 2000);
                    } else {
                        console.error("Save Error:", data.message);
                        btn.innerText = 'Error Saving';
                        setTimeout(() => { btn.innerText = 'Save & Publish'; btn.disabled = false; }, 3000);
                    }
                }).catch(err => {
                    console.error(err);
                    btn.innerText = 'Error Saving';
                    btn.disabled = false;
                });
        }

        document.getElementById('saveBtn').addEventListener('click', saveToServer);

        // Template preview
        document.getElementById('inp_template').addEventListener('change', function() {
            const btn = document.getElementById('saveBtn');
            btn.innerText = 'Loading Preview...'; btn.disabled = true;
            const url = new URL(window.location.href);
            url.searchParams.set('tpl', this.value);
            window.location.href = url.toString();
        });

        // Auto-save on contenteditable blur
        document.getElementById('canvasArea').addEventListener('focusout', function(e) {
            if (e.target && e.target.getAttribute('contenteditable') === 'true') {
                saveToServer();
            }
        });
    </script>
</body>

</html>
