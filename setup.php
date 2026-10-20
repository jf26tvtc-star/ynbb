<?php
// صفحة تهيئة قاعدة البيانات لأول مرة على الاستضافة (مثل Vercel).
// لا تعمل إلا إذا ضُبط متغير البيئة SETUP_TOKEN (12 حرفًا على الأقل) وأُدخل نفس الرمز في النموذج.
// بعد نجاح التهيئة احذف SETUP_TOKEN من إعدادات الاستضافة لتعطيل هذه الصفحة.

require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/install.php';

header('X-Robots-Tag: noindex');
$setupToken = (string)yanbu_env('SETUP_TOKEN', '');
$enabled = strlen($setupToken) >= 12;
$log = [];
$error = '';

$dbStatus = '';
$dbOk = false;
try {
    yanbu_db();
    $dbOk = true;
    $dbStatus = 'الاتصال بقاعدة البيانات يعمل ✓';
} catch (mysqli_sql_exception $e) {
    error_log('Setup database connection failed: ' . $e->getMessage());
    $dbStatus = 'تعذّر الاتصال بقاعدة البيانات. تأكد من متغيرات DB_HOST و DB_PORT و DB_USER و DB_PASSWORD و DB_NAME ثم أعد النشر.';
}

if ($enabled && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['token'] ?? '');
    $password = (string)($_POST['admin_password'] ?? '');
    if (!hash_equals($setupToken, $token)) {
        $error = 'رمز التهيئة غير صحيح.';
    } elseif (strlen($password) < 8) {
        $error = 'كلمة مرور المدير يجب أن تكون 8 أحرف على الأقل.';
    } elseif (!$dbOk) {
        $error = $dbStatus;
    } else {
        try {
            $log = yanbu_install(yanbu_db(), $password);
        } catch (mysqli_sql_exception $e) {
            error_log('Setup failed: ' . $e->getMessage());
            $error = 'فشلت التهيئة: ' . $e->getMessage();
        }
    }
}

if (!$enabled) {
    http_response_code(404);
}
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>تهيئة الموقع - روح ينبع</title>
    <link rel="stylesheet" href="css/style.css?v=<?= @filemtime(__DIR__ . '/css/style.css') ?: 1 ?>">
</head>
<body>
<section class="container" style="max-width:640px;margin:60px auto;">
    <h2>تهيئة قاعدة البيانات</h2>
    <?php if (!$enabled): ?>
        <div class="alert alert-danger">صفحة التهيئة معطّلة. لتفعيلها أضف متغير البيئة SETUP_TOKEN (12 حرفًا على الأقل) ثم أعد النشر.</div>
    <?php else: ?>
        <div class="alert <?= $dbOk ? 'alert-success' : 'alert-danger' ?>"><?= htmlspecialchars($dbStatus) ?></div>
        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($log): ?>
            <div class="alert alert-success">
                <strong>تمت التهيئة بنجاح:</strong>
                <ul style="margin:8px 20px 0;"><?php foreach ($log as $line): ?><li><?= htmlspecialchars($line) ?></li><?php endforeach; ?></ul>
            </div>
            <p>الخطوة التالية: احذف متغير SETUP_TOKEN من إعدادات Vercel ثم أعد النشر، وبعدها <a href="admin/login.php">سجّل دخولك</a> باسم المستخدم admin.</p>
        <?php else: ?>
            <form method="post" class="contact-form" style="background:#fff;padding:24px;border-radius:14px;">
                <label for="token">رمز التهيئة (نفس قيمة SETUP_TOKEN)</label>
                <input id="token" type="password" name="token" required autocomplete="off">
                <label for="admin_password">كلمة مرور جديدة لحساب المدير admin (8 أحرف على الأقل)</label>
                <input id="admin_password" type="password" name="admin_password" minlength="8" required autocomplete="new-password">
                <p style="color:var(--muted);font-size:13px;margin-bottom:14px;">تُستخدم كلمة المرور فقط إذا لم يكن هناك حساب مدير بعد. التهيئة آمنة ولا تحذف أي بيانات موجودة.</p>
                <button class="btn" type="submit">تهيئة قاعدة البيانات</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</section>
</body>
</html>
