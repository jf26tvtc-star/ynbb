<?php
// نقطة الدخول الوحيدة على Vercel (vercel-php): توجّه كل طلب إلى صفحة PHP المطلوبة بأمان،
// وتخدم الصور المرفوعة المحفوظة في قاعدة البيانات، وتمنع الوصول لأي ملف آخر.

define('YANBU_SERVERLESS', true);
define('YANBU_ROOT', dirname(__DIR__));

require_once YANBU_ROOT . '/includes/database.php';
require_once YANBU_ROOT . '/includes/session.php';

function yanbu_not_found() {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>الصفحة غير موجودة</title><link rel="stylesheet" href="/css/style.css"></head>'
        . '<body><section class="container" style="text-align:center;margin:90px auto;"><h2>الصفحة غير موجودة</h2><p style="margin:12px 0 24px;color:var(--muted);">ربما تم نقل الصفحة أو أن الرابط غير صحيح.</p><a class="btn" href="/index.php">العودة للرئيسية</a></section></body></html>';
    exit();
}

function yanbu_request_path() {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $path = is_string($path) ? rawurldecode($path) : '/';
    // إذا مرّر Vercel المسار الداخلي للدالة بدل المسار الأصلي، نستخدم المسار المرسل من vercel.json
    if ($path === '/api/index.php' || $path === '/api' || $path === '/api/') {
        $path = '/' . ltrim((string)($_GET['__path'] ?? ''), '/');
    }
    unset($_GET['__path'], $_REQUEST['__path']);
    return $path;
}

function yanbu_serve_db_image($name) {
    try {
        $stmt = yanbu_db()->prepare('SELECT mime, data FROM image_files WHERE name = ?');
        $stmt->bind_param('s', $name);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
    } catch (mysqli_sql_exception $e) {
        error_log('Image lookup failed: ' . $e->getMessage());
        $row = null;
    }
    if (!$row) {
        http_response_code(404);
        exit();
    }
    header('Content-Type: ' . $row['mime']);
    header('Content-Length: ' . strlen($row['data']));
    header('Cache-Control: public, max-age=31536000, immutable');
    header('X-Content-Type-Options: nosniff');
    echo $row['data'];
    exit();
}

function yanbu_handle_exception($e) {
    error_log('Unhandled exception: ' . $e);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }
    // 1146 = الجدول غير موجود: قاعدة البيانات متصلة لكن لم تُهيّأ بعد
    $needsSetup = $e instanceof mysqli_sql_exception && (int)$e->getCode() === 1146;
    $message = $needsSetup
        ? 'قاعدة البيانات متصلة لكنها لم تُهيّأ بعد. إذا كنت مدير الموقع افتح صفحة <a href="/setup.php">التهيئة</a>.'
        : 'حدث خطأ غير متوقع. حاول مرة أخرى بعد قليل.';
    echo '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>خطأ - روح ينبع</title><link rel="stylesheet" href="/css/style.css"></head>'
        . '<body><section class="container" style="text-align:center;margin:90px auto;"><h2>' . ($needsSetup ? 'الموقع يحتاج تهيئة' : 'عذرًا') . '</h2><p style="margin-top:12px;color:var(--muted);">' . $message . '</p></section></body></html>';
}
set_exception_handler('yanbu_handle_exception');

$yanbuPath = yanbu_request_path();

// عند التشغيل المحلي عبر "php -S" نسمح بخدمة الملفات الثابتة الآمنة فقط
if (PHP_SAPI === 'cli-server' && preg_match('#^/(?:css|js|images)/[A-Za-z0-9._-]+\.(?:css|js|svg|jpe?g|png|webp|ico)$#', $yanbuPath) && is_file(YANBU_ROOT . $yanbuPath)) {
    return false;
}

if ($yanbuPath === '/admin' || $yanbuPath === '/admin/') {
    header('Location: /admin/dashboard.php', true, 302);
    exit();
}
if ($yanbuPath === '/') {
    $yanbuPath = '/index.php';
}

if (preg_match('#^/images/([A-Za-z0-9._-]+)$#', $yanbuPath, $yanbuMatch)) {
    yanbu_serve_db_image($yanbuMatch[1]);
}

// الصفحات المسموح تشغيلها فقط: ملفات PHP في الجذر أو مجلد admin (عدا أدوات التشخيص والملفات المساعدة)
$yanbuBlocked = ['/db.php', '/nav.php', '/admin/diagnose.php'];
if (!preg_match('#^/(?:admin/)?[A-Za-z0-9_-]+\.php$#', $yanbuPath) || in_array($yanbuPath, $yanbuBlocked, true)) {
    yanbu_not_found();
}
$yanbuFile = YANBU_ROOT . $yanbuPath;
if (!is_file($yanbuFile)) {
    yanbu_not_found();
}

try {
    yanbu_db();
} catch (mysqli_sql_exception $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    if ($yanbuPath !== '/setup.php') {
        yanbu_render_unavailable();
    }
}
yanbu_use_db_sessions();

$_SERVER['SCRIPT_NAME'] = $yanbuPath;
$_SERVER['PHP_SELF'] = $yanbuPath;
$_SERVER['SCRIPT_FILENAME'] = $yanbuFile;
chdir(dirname($yanbuFile));
unset($yanbuMatch, $yanbuBlocked, $yanbuPath, $e);

require $yanbuFile;
