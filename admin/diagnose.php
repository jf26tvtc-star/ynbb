<?php
/**
 * أداة تشخيص وإصلاح تسجيل دخول الأدمن.
 * افتحها في المتصفح: http://localhost/yanbu-tourism/admin/diagnose.php
 * ستوضّح لك سبب فشل تسجيل الدخول بالضبط، وتعطيك زر لإصلاحه تلقائيًا.
 *
 * ⚠️ هام: احذف هذا الملف بعد التأكد أن الدخول يعمل (أداة تشخيص مؤقتة فقط).
 */
session_start();
include '../db.php';

$fixed = false;
$fixError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'fix') {
    $newHash = password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 12]);
    $exists = $conn->query("SELECT id FROM users WHERE username='admin'")->fetch_assoc();
    if ($exists) {
        $u = $conn->prepare("UPDATE users SET password=?, role='admin' WHERE username='admin'");
        $u->bind_param('s', $newHash);
        $fixed = $u->execute();
    } else {
        $i = $conn->prepare("INSERT INTO users (username, password, role) VALUES ('admin', ?, 'admin')");
        $i->bind_param('s', $newHash);
        $fixed = $i->execute();
    }
    if (!$fixed) $fixError = $conn->error;
}

// فحوصات التشخيص
$checks = [];

// 1) هل جدول users موجود وفيه الأعمدة الصحيحة؟
$tableOk = false; $cols = [];
$tblRes = $conn->query("SHOW TABLES LIKE 'users'");
if ($tblRes && $tblRes->num_rows > 0) {
    $tableOk = true;
    $colRes = $conn->query("SHOW COLUMNS FROM users");
    while ($c = $colRes->fetch_assoc()) { $cols[] = $c['Field']; }
}
$checks[] = ['ok' => $tableOk, 'label' => 'جدول users موجود في قاعدة البيانات', 'detail' => $tableOk ? ('الأعمدة: ' . implode(', ', $cols)) : 'الجدول غير موجود — تأكد أنك استوردت database.sql في قاعدة yanbu_tourism الصحيحة'];

// 2) هل يوجد مستخدم admin؟
$adminRow = null;
if ($tableOk) {
    $adminRow = $conn->query("SELECT * FROM users WHERE username='admin'")->fetch_assoc();
}
$checks[] = ['ok' => (bool)$adminRow, 'label' => "يوجد مستخدم باسم 'admin'", 'detail' => $adminRow ? ('role = ' . $adminRow['role']) : 'لا يوجد أي صف بهذا الاسم'];

// 3) هل role = admin؟
if ($adminRow) {
    $checks[] = ['ok' => $adminRow['role'] === 'admin', 'label' => "عمود role يساوي 'admin'", 'detail' => 'القيمة الحالية: ' . $adminRow['role']];
}

// 4) هل كلمة المرور المخزّنة تطابق admin123؟
if ($adminRow) {
    $passOk = password_verify('admin123', $adminRow['password']);
    $checks[] = ['ok' => $passOk, 'label' => "كلمة المرور المخزّنة تطابق admin123", 'detail' => 'القيمة المخزّنة تبدأ بـ: ' . substr($adminRow['password'], 0, 10) . '… (طولها ' . strlen($adminRow['password']) . ' حرف، bcrypt الصحيح طوله 60 حرفًا)'];
}

$allOk = true;
foreach ($checks as $c) { if (!$c['ok']) { $allOk = false; break; } }
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>تشخيص تسجيل دخول الأدمن</title>
<link rel="stylesheet" href="../css/style.css?v=<?= @filemtime(__DIR__ . '/../css/style.css') ?: 1 ?>">
</head>
<body>
<nav class="navbar"><div class="container"><h1>🛠️ تشخيص تسجيل الدخول</h1></div></nav>
<section class="container" style="max-width:680px;margin:40px auto;">

    <?php if ($fixed): ?>
        <div class="alert alert-success">
            تم الإصلاح بنجاح ✓ — الآن تقدر تسجّل الدخول بـ: <b>admin</b> / <b>admin123</b> من
            <a href="login.php" style="color:inherit;text-decoration:underline;">صفحة الدخول</a>.
        </div>
    <?php elseif ($fixError): ?>
        <div class="alert alert-danger">فشل الإصلاح: <?php echo htmlspecialchars($fixError); ?></div>
    <?php endif; ?>

    <div class="place-details">
        <h3>نتيجة الفحص</h3>
        <ul style="list-style:none;padding:0;">
            <?php foreach ($checks as $c): ?>
                <li style="padding:10px 0;border-bottom:1px solid #eef2f7;">
                    <?php echo $c['ok'] ? '✅' : '❌'; ?>
                    <strong><?php echo htmlspecialchars($c['label']); ?></strong>
                    <div style="color:var(--muted);font-size:13px;margin-top:4px;"><?php echo htmlspecialchars($c['detail']); ?></div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <?php if (!$allOk && !$fixed): ?>
        <div class="place-details" style="text-align:center;">
            <h3>الإصلاح التلقائي</h3>
            <p style="margin-bottom:15px;color:var(--muted);">
                هذا الزر سينشئ (أو يصلح) مستخدم admin بكلمة مرور admin123 مباشرة من خلال PHP نفسه —
                بدون الحاجة لنسخ/لصق أي نص في phpMyAdmin، فلا يحدث أي تلاعب برموز الكلمة.
            </p>
            <form method="post">
                <input type="hidden" name="action" value="fix">
                <button class="btn" type="submit">🔧 أصلح الآن (admin / admin123)</button>
            </form>
        </div>
    <?php elseif ($allOk): ?>
        <div class="alert alert-success">كل الفحوصات ناجحة ✓ — بيانات الدخول <b>admin / admin123</b> يجب أن تعمل الآن.</div>
        <p style="color:var(--muted);font-size:14px;">
            إذا بقيت تواجه خطأ، تأكد أنك تكتب الاسم وكلمة المرور بدون مسافات زائدة، وأن متصفحك ليس فيه كلمة مرور محفوظة قديمة يتم تعبئتها تلقائيًا.
        </p>
    <?php endif; ?>

    <p style="margin-top:30px;text-align:center;color:var(--danger);font-weight:700;">
        ⚠️ لا تنسَ حذف هذا الملف (admin/diagnose.php) بعد الانتهاء — فهو أداة تشخيص مؤقتة ولا يجب أن يبقى على موقع منشور للعامة.
    </p>
</section>
</body>
</html>
