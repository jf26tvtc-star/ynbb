<?php
// تحديث رقم النسخة (?v=) لملفات CSS وJS في صفحات HTML، حتى يحمّل المتصفح أحدث نسخة بعد أي تعديل.
// التشغيل: C:\xampp\php\php.exe tools\stamp-assets.php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
$root = dirname(__DIR__);
$assets = [];
foreach (['css/style.css', 'css/admin-dashboard.css', 'js/site.js', 'js/admin.js'] as $asset) {
    $assets[$asset] = substr(md5_file("$root/$asset"), 0, 8);
}
foreach (glob("$root/*.html") as $page) {
    $html = file_get_contents($page);
    $out = preg_replace_callback('#(css/style\.css|css/admin-dashboard\.css|js/site\.js|js/admin\.js)\?v=[A-Za-z0-9]*#', function ($m) use ($assets) {
        return $m[1] . '?v=' . $assets[$m[1]];
    }, $html);
    if ($out !== $html) {
        file_put_contents($page, $out);
        echo 'updated ', basename($page), "\n";
    }
}
