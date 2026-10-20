<?php
session_start();
include '../db.php';
require_once '../includes/publish.php';
check_admin();

$root = dirname(__DIR__);
if (empty($_SESSION['publish_token'])) {
    $_SESSION['publish_token'] = bin2hex(random_bytes(32));
}

// تشغيل أوامر git داخل مجلد المشروع وإرجاع (رمز الخروج، المخرجات)
function publish_git(array $args, $root) {
    $git = is_file('C:/Program Files/Git/cmd/git.exe') ? 'C:/Program Files/Git/cmd/git.exe' : 'git';
    $env = getenv();
    $env['GIT_TERMINAL_PROMPT'] = '0';
    $env['GCM_INTERACTIVE'] = 'never';
    if (empty($env['HOME']) && !empty($env['USERPROFILE'])) {
        $env['HOME'] = $env['USERPROFILE'];
    }
    $proc = proc_open(array_merge([$git], $args), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, $env);
    if (!is_resource($proc)) {
        return [1, 'تعذّر تشغيل git. تأكد أنه مثبت على جهازك.'];
    }
    $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($proc), trim($out)];
}

$result = null;
$error = '';
$log = [];
$uploaded = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!hash_equals($_SESSION['publish_token'], (string)($_POST['token'] ?? ''))) {
        $error = 'انتهت صلاحية الصفحة. حدّث الصفحة وحاول مرة أخرى.';
    } elseif (!in_array($action, ['export', 'upload'], true)) {
        $error = 'إجراء غير معروف.';
    } else {
        try {
            $result = yanbu_export_site($conn, $root);
        } catch (Throwable $e) {
            $error = 'فشل تصدير البيانات: ' . $e->getMessage();
        }

        if (!$error && $action === 'upload') {
            set_time_limit(180);
            [$code, $out] = publish_git(['add', '-A'], $root);
            if ($code !== 0) {
                $error = 'فشل تجهيز الملفات للرفع.';
                $log[] = $out;
            } else {
                [$changed] = publish_git(['diff', '--cached', '--quiet'], $root);
                if ($changed === 0) {
                    $log[] = 'لا توجد تغييرات جديدة — الموقع المنشور محدّث بالفعل.';
                    $uploaded = true;
                } else {
                    [$code, $out] = publish_git(['commit', '-m', 'Update site content'], $root);
                    $log[] = $out;
                    if ($code !== 0) {
                        $error = 'فشل حفظ التغييرات في git.';
                    } else {
                        [$code, $out] = publish_git(['push'], $root);
                        $log[] = $out;
                        if ($code !== 0) {
                            $error = 'تم تجهيز التحديث لكن فشل رفعه إلى GitHub. تأكد من الإنترنت وتسجيل دخولك في GitHub، أو شغّل الملف publish.bat من مجلد المشروع.';
                        } else {
                            $uploaded = true;
                        }
                    }
                }
            }
        }
    }
}

$lastExport = is_file($root . '/data/site.json') ? date('Y/m/d H:i', filemtime($root . '/data/site.json')) : null;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نشر الموقع - روح ينبع</title>
    <link rel="stylesheet" href="../css/style.css?v=<?= substr((string)@md5_file(__DIR__ . '/../css/style.css'), 0, 8) ?>">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <h1>🌐 نشر الموقع على GitHub Pages</h1>
            <ul>
                <li><a href="dashboard.php">لوحة التحكم</a></li>
                <li><a href="places-list.php">الأماكن</a></li>
                <li><a href="logout.php">تسجيل خروج</a></li>
            </ul>
        </div>
    </nav>

    <section class="container" style="max-width:820px;">
        <h2>نشر التحديثات</h2>
        <p style="color:var(--muted);margin-bottom:24px;">
            الموقع المنشور على GitHub Pages لا يتصل بقاعدة البيانات مباشرة، بل يقرأ نسخة من المحتوى.
            بعد إضافة أو تعديل الأماكن والتصنيفات هنا، اضغط "نشر" لتصل التحديثات للزوار.
        </p>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($result && !$error): ?>
            <div class="alert alert-success">
                <?= $uploaded ? 'تم النشر بنجاح ✓ سيظهر التحديث على الموقع خلال دقيقة إلى دقيقتين.' : 'تم تحديث بيانات الموقع محليًا ✓ (لم تُرفع بعد).' ?>
                <br>تم تصدير <?= (int)$result['places'] ?> مكان، و<?= (int)$result['categories'] ?> تصنيف، و<?= (int)$result['images'] ?> صورة.
            </div>
        <?php endif; ?>
        <?php if ($result && $result['warnings']): ?>
            <div class="alert alert-danger">
                <strong>تنبيه:</strong>
                <ul style="margin:6px 20px 0;"><?php foreach ($result['warnings'] as $w): ?><li><?= htmlspecialchars($w) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>
        <?php if ($log): ?>
            <details style="margin-bottom:20px;">
                <summary style="cursor:pointer;color:var(--muted);">تفاصيل العملية</summary>
                <pre dir="ltr" style="white-space:pre-wrap;background:#fff;border:1px solid #e1e8f0;border-radius:10px;padding:14px;font-size:12px;margin-top:8px;"><?= htmlspecialchars(implode("\n\n", array_filter($log))) ?></pre>
            </details>
        <?php endif; ?>

        <div class="place-details">
            <h3>🚀 نشر على الموقع</h3>
            <p style="margin-bottom:16px;">يصدّر المحتوى الحالي ويرفعه إلى GitHub مباشرة.</p>
            <form method="post" style="display:inline-block;">
                <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['publish_token']) ?>">
                <button class="btn" type="submit" name="action" value="upload">نشر الآن</button>
            </form>
            <form method="post" style="display:inline-block;margin-right:10px;">
                <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['publish_token']) ?>">
                <button class="btn btn-secondary" type="submit" name="action" value="export">تحديث المعاينة المحلية فقط</button>
            </form>
            <p style="margin-top:16px;color:var(--muted);font-size:14px;">
                آخر تصدير: <?= $lastExport ? htmlspecialchars($lastExport) : 'لم يتم بعد' ?> —
                <a href="../index.html" target="_blank" rel="noopener" style="color:var(--primary);">معاينة النسخة الثابتة محليًا</a> |
                <a href="https://jf26tvtc-star.github.io/ynbb/" target="_blank" rel="noopener" style="color:var(--primary);">فتح الموقع المنشور</a>
            </p>
        </div>

        <div class="place-details">
            <h3>💡 بديل</h3>
            <p>يمكنك أيضًا النشر بالنقر المزدوج على الملف <code>publish.bat</code> داخل مجلد المشروع.</p>
        </div>
    </section>

    <footer>
        <div class="container">
            <p>&copy; 2026 روح ينبع | جميع الحقوق محفوظة</p>
        </div>
    </footer>
</body>
</html>
