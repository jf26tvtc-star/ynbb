<?php
session_start();
include 'db.php';

// صورة الهيرو: نستخدم صورة أحدث مكان مُضاف إن وجدت، وإلا خلفية متدرّجة افتراضية
$heroRow = $conn->query("SELECT i.image_path FROM images i ORDER BY i.id DESC LIMIT 1")->fetch_assoc();
$heroStyle = $heroRow ? "--hero-img:url('images/" . htmlspecialchars($heroRow['image_path']) . "');" : '';

$places_total = $conn->query("SELECT COUNT(*) c FROM places")->fetch_assoc()['c'];
$cats_total = $conn->query("SELECT COUNT(*) c FROM categories")->fetch_assoc()['c'];
$images_total = $conn->query("SELECT COUNT(*) c FROM images")->fetch_assoc()['c'];

$cats_home = $conn->query("SELECT c.*, (SELECT COUNT(*) FROM places p WHERE p.category_id=c.id) AS places_count FROM categories c ORDER BY c.id");
$categories_list = [];
while ($row = $cats_home->fetch_assoc()) { $categories_list[] = $row; }

// كل الأماكن مجمّعة حسب النوع الأساسي (بحرية / تاريخية / طبيعية / ترفيهية) لعرضها في أقسام مواضيعية
$allPlaces = $conn->query("SELECT p.*, c.name AS category, COALESCE((SELECT image_path FROM images WHERE place_id=p.id ORDER BY is_main DESC,id ASC LIMIT 1), '') AS image FROM places p JOIN categories c ON c.id=p.category_id ORDER BY p.created_at DESC");
$byType = ['بحرية' => [], 'تاريخية' => [], 'طبيعية' => [], 'ترفيهية' => []];
while ($row = $allPlaces->fetch_assoc()) {
    $t = category_type($row['category']);
    if (isset($byType[$t])) $byType[$t][] = $row;
}
$typeLabels = [
    'بحرية'   => ['title' => '🏖️ وجهات بحرية',   'sub' => 'شواطئ وكورنيش ينبع الساحرة'],
    'تاريخية' => ['title' => '🏛️ معالم تاريخية', 'sub' => 'آثار وتراث يحكي قصة ينبع'],
    'طبيعية' => ['title' => '🏔️ وجهات طبيعية', 'sub' => 'جبال وطبيعة خلّابة'],
    'ترفيهية' => ['title' => '🎡 مطاعم وترفيه', 'sub' => 'أفضل المطاعم والفنادق وأماكن الترفيه'],
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>روح ينبع - اكتشف جمال ينبع</title>
    <link rel="stylesheet" href="css/style.css?v=<?= substr((string)@md5_file(__DIR__ . '/css/style.css'), 0, 8) ?>">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&family=Markazi+Text:wght@500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <!-- NAVBAR -->
    <?php require __DIR__ . '/nav.php'; ?>

    <!-- HERO -->
    <section class="hero" style="<?php echo $heroStyle; ?>">
        <div class="container">
            <span class="eyebrow">المملكة العربية السعودية</span>
            <h2>اكتشف جمال ينبع</h2>
            <p>شواطئ ساحرة، تاريخ عريق، طبيعة خلّابة، ومطاعم تستحق الزيارة — كل وجهات ينبع في مكان واحد</p>
        </div>
    </section>

    <!-- SEARCH BAR -->
    <div class="container">
        <form class="hero-search" action="places.php" method="get">
            <input type="text" name="search" placeholder="🔍 ابحث عن مكان، شاطئ، مطعم...">
            <select name="category">
                <option value="">كل التصنيفات</option>
                <?php foreach ($categories_list as $c): ?>
                    <option value="<?php echo htmlspecialchars($c['name']); ?>"><?php echo category_icon($c['name']); ?> <?php echo htmlspecialchars($c['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn">ابحث</button>
        </form>

        <!-- CATEGORY PILLS -->
        <div class="cat-pills">
            <?php foreach ($categories_list as $c): ?>
                <a class="cat-pill" href="places.php?category=<?php echo urlencode($c['name']); ?>">
                    <span class="ico"><?php echo category_icon($c['name']); ?></span>
                    <?php echo htmlspecialchars($c['name']); ?>
                    <span style="color:var(--muted);font-weight:600;">(<?php echo (int)$c['places_count']; ?>)</span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- THEMATIC SECTIONS PER CATEGORY TYPE -->
    <?php foreach ($byType as $type => $places): if (empty($places)) continue; ?>
    <section class="container">
        <div class="section-head">
            <div>
                <h2><?php echo $typeLabels[$type]['title']; ?></h2>
                <p class="section-sub"><?php echo $typeLabels[$type]['sub']; ?></p>
            </div>
            <a class="see-all" href="places.php?category=<?php echo urlencode($places[0]['category']); ?>">عرض الكل ←</a>
        </div>
        <div class="scroll-row">
            <?php foreach (array_slice($places, 0, 8) as $row): ?>
                <div class="place-card <?php echo category_slug($row['category']); ?>">
                    <div class="img-wrap">
                        <img src="images/<?php echo htmlspecialchars(place_image($row['image'])); ?>" alt="<?php echo htmlspecialchars($row['name']); ?>">
                    </div>
                    <div class="place-card-content">
                        <span class="category <?php echo category_slug($row['category']); ?>"><?php echo category_icon($row['category']); ?> <?php echo htmlspecialchars($row['category']); ?></span>
                        <h3><?php echo htmlspecialchars($row['name']); ?></h3>
                        <p class="location">📍 <?php echo htmlspecialchars($row['location']); ?></p>
                        <a href="details.php?id=<?php echo $row['id']; ?>" class="btn" style="padding:9px 20px;font-size:14px;">عرض التفاصيل</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endforeach; ?>

    <?php if ($places_total == 0): ?>
    <section class="container">
        <p style="text-align:center;color:var(--muted);padding:40px;">لا توجد أماكن مسجلة حالياً — ابدأ بإضافتها من لوحة التحكم.</p>
    </section>
    <?php endif; ?>

    <!-- STATS STRIP -->
    <section class="container">
        <div class="dashboard-grid">
            <div class="stat-card"><h3>وجهات سياحية</h3><div class="number"><?php echo (int)$places_total; ?></div></div>
            <div class="stat-card"><h3>تصنيفات</h3><div class="number"><?php echo (int)$cats_total; ?></div></div>
            <div class="stat-card"><h3>صور</h3><div class="number"><?php echo (int)$images_total; ?></div></div>
            <div class="stat-card"><h3>تقييم الزوار</h3><div class="number">4.5 ⭐</div></div>
        </div>
    </section>

    <!-- CTA -->
    <section class="container" style="text-align:center;background:var(--card);padding:50px 30px;border-radius:var(--radius-lg);box-shadow:var(--shadow-sm);">
        <h2 style="margin-bottom:10px;">خطّط لرحلتك القادمة إلى ينبع</h2>
        <p class="section-sub" style="margin-bottom:22px;">تصفّح دليل الوجهات الكامل أو تواصل معنا لأي استفسار</p>
        <a href="places.php" class="btn">تصفّح كل الأماكن</a>
        <a href="contact.php" class="btn btn-secondary" style="margin-right:10px;">تواصل معنا</a>
    </section>

    <!-- FOOTER -->
    <footer>
        <div class="container">
            <p>&copy; 2026 روح ينبع | جميع الحقوق محفوظة</p>
            <p>📧 jf26tvtc@gmail.com | 📱 +96656789455</p>
        </div>
    </footer>
</body>
</html>
