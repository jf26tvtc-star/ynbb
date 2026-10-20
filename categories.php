<?php
session_start();
include 'db.php';

$cats = $conn->query("SELECT c.*, (SELECT COUNT(*) FROM places p WHERE p.category_id=c.id) AS places_count,
    COALESCE((SELECT i.image_path FROM places p2 JOIN images i ON i.place_id=p2.id WHERE p2.category_id=c.id ORDER BY i.is_main DESC, i.id ASC LIMIT 1), '') AS cover
    FROM categories c ORDER BY c.id");
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>التصنيفات السياحية - روح ينبع</title>
    <link rel="stylesheet" href="css/style.css?v=<?= substr((string)@md5_file(__DIR__ . '/css/style.css'), 0, 8) ?>">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&family=Markazi+Text:wght@500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <!-- NAVBAR -->
    <?php require __DIR__ . '/nav.php'; ?>

    <!-- HERO -->
    <section class="hero">
        <div class="container">
            <h2>التصنيفات السياحية</h2>
            <p>تصفّح وجهات ينبع حسب نوعها: بحرية، تاريخية، طبيعية، وترفيهية</p>
        </div>
    </section>

    <!-- CATEGORIES -->
    <section class="container">
        <div class="category-grid">
            <?php while ($c = $cats->fetch_assoc()): ?>
                <a class="category-card <?php echo category_slug($c['name']); ?>" href="places.php?category=<?php echo urlencode($c['name']); ?>" style="display:block;text-decoration:none;color:inherit;">
                    <img src="images/<?php echo htmlspecialchars(place_image($c['cover'])); ?>" alt="<?php echo htmlspecialchars($c['name']); ?>" style="width:100%;height:160px;object-fit:cover;display:block;">
                    <div class="cc-body">
                        <h3><?php echo category_icon($c['name']); ?> <?php echo htmlspecialchars($c['name']); ?></h3>
                        <p><?php echo htmlspecialchars($c['description']); ?></p>
                        <p style="margin-top:8px;color:var(--primary);font-weight:600;"><?php echo (int)$c['places_count']; ?> مكان مسجّل</p>
                    </div>
                </a>
            <?php endwhile; ?>
        </div>
    </section>

    <!-- FOOTER -->
    <footer>
        <div class="container">
            <p>&copy; 2026 روح ينبع | جميع الحقوق محفوظة</p>
            <p>📧 jf26tvtc@gmail.com | 📱 +96656789455</p>
            <p class="footer-admin"><a href="admin/dashboard.php">لوحة التحكم</a></p>
        </div>
    </footer>
</body>
</html>
