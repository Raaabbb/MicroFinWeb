<?php
require_once __DIR__ . '/../bootstrap.php';

libxml_use_internal_errors(true);

$defaultUrl = 'http://localhost/admin-draft-withmobile/admin-draft/microfin_web/admin_panel/admin.php';
$platformRoot = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, mf_platform_root());
$htdocsMarker = DIRECTORY_SEPARATOR . 'htdocs' . DIRECTORY_SEPARATOR;
$markerPos = stripos($platformRoot, $htdocsMarker);

if ($markerPos !== false) {
    $relativePath = substr($platformRoot, $markerPos + strlen($htdocsMarker));
    $defaultUrl = 'http://localhost/' . str_replace(DIRECTORY_SEPARATOR, '/', $relativePath) . '/admin_panel/admin.php';
}

$adminPanelUrl = getenv('MF_ADMIN_PANEL_URL') ?: $defaultUrl;
$dom = new DOMDocument();
$dom->loadHTMLFile($adminPanelUrl);

foreach (libxml_get_errors() as $error) {
    echo $error->message . PHP_EOL;
}
