<?php session_start(); ?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>من نحن - روح ينبع</title>
    <link rel="stylesheet" href="css/style.css?v=<?= substr((string)@md5_file(__DIR__ . '/css/style.css'), 0, 8) ?>">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&family=Markazi+Text:wght@500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php require __DIR__ . '/nav.php'; ?>
    <main>
        <section class="hero">
            <div class="container">
                <h2>من نحن</h2>
                <p>روح ينبع — دليل يساعدك على اكتشاف ينبع والتخطيط لزيارتك.</p>
            </div>
        </section>
        <section class="container">
            <h2>عن روح ينبع</h2>
            <p>نجمع الوجهات والأماكن المسجلة في دليل واحد، ونعرّفك على ينبع البحر وينبع النخل وينبع الصناعية لتختار ما يناسب اهتماماتك.</p>
            <div class="category-grid">
                <article class="category-card">
                    <div class="cc-body">
                        <h3>اكتشف</h3>
                        <p>تصفح الأماكن حسب تصنيفها، واطلع على صورها وتفاصيلها وموقعها عند توفرها.</p>
                        <a class="btn" href="places.php">اكتشف الأماكن</a>
                    </div>
                </article>
                <article class="category-card">
                    <div class="cc-body">
                        <h3>خطط</h3>
                        <p>استلهم أفكاراً لزيارتك ورتب وقتك بين البحر والطبيعة والتراث.</p>
                        <a class="btn" href="trip.php">خطط رحلتك</a>
                    </div>
                </article>
                <article class="category-card">
                    <div class="cc-body">
                        <h3>تواصل</h3>
                        <p>شاركنا استفساراتك وملاحظاتك عبر صفحة التواصل.</p>
                        <a class="btn" href="contact.php">تواصل معنا</a>
                    </div>
                </article>
            </div>
        </section>
    </main>
    <footer>
        <div class="container">
            <p>&copy; 2026 روح ينبع | جميع الحقوق محفوظة</p>
            <a href="categories.php">التصنيفات</a> |
            <a href="contact.php">تواصل معنا</a> |
            <?php if (isset($_SESSION['admin_id'])): ?>
                <a href="admin/dashboard.php">لوحة التحكم</a> |
                <a href="admin/logout.php">تسجيل خروج</a>
            <?php else: ?>
                <a href="admin/login.php">دخول الأدمن</a>
            <?php endif; ?>
        </div>
    </footer>
</body>
</html>
