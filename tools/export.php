<?php
// تصدير بيانات الموقع من سطر الأوامر: C:\xampp\php\php.exe tools\export.php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
$root = dirname(__DIR__);
require $root . '/db.php';
require_once $root . '/includes/publish.php';

$result = yanbu_export_site($conn, $root);
echo "Exported data/site.json: {$result['places']} places, {$result['categories']} categories, {$result['images']} images\n";
foreach ($result['warnings'] as $warning) {
    echo "WARNING: $warning\n";
}
