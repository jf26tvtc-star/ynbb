<?php
session_start();
include 'db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id == 0) {
    header("Location: places.php");
    exit();
}

$stmt = $conn->prepare("SELECT p.*, c.name AS category FROM places p JOIN categories c ON c.id=p.category_id WHERE p.id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: places.php");
    exit();
}

$place = $result->fetch_assoc();

// كل صور المكان (الرئيسية أولًا) لعرضها في معرض الصور التفاعلي
$imgStmt = $conn->prepare("SELECT image_path, is_main FROM images WHERE place_id=? ORDER BY is_main DESC, id ASC");
$imgStmt->bind_param('i', $id);
$imgStmt->execute();
$imagesResult = $imgStmt->get_result();
$images = [];
while ($row = $imagesResult->fetch_assoc()) { $images[] = $row['image_path']; }
if (empty($images)) { $images[] = ''; } // سيُستبدل تلقائيًا بالصورة الافتراضية

// روابط خرائط قوقل: خريطة مضمّنة (iframe) + زر فتح في تبويب جديد
$mapsEmbed = maps_embed_url($place['name'], $place['location']);
$mapsUrl = maps_open_url($place['google_maps_url'] ?? '', $place['name'], $place['location']);
$catSlug = category_slug($place['category']);

// أماكن مشابهة من نفس الفئة
$similarStmt = $conn->prepare("SELECT p.*, c.name AS category, COALESCE((SELECT image_path FROM images WHERE place_id=p.id ORDER BY is_main DESC,id ASC LIMIT 1),'') AS image FROM places p JOIN categories c ON c.id=p.category_id WHERE p.category_id=? AND p.id<>? ORDER BY p.created_at DESC LIMIT 4");
$similarStmt->bind_param('ii', $place['category_id'], $id);
$similarStmt->execute();
$similar = $similarStmt->get_result();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($place['name']); ?> - روح ينبع</title>
    <link rel="stylesheet" href="css/style.css?v=<?= substr((string)@md5_file(__DIR__ . '/css/style.css'), 0, 8) ?>">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&family=Markazi+Text:wght@500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <!-- NAVBAR -->
    <?php require __DIR__ . '/nav.php'; ?>

    <!-- BREADCRUMB -->
    <div style="background: #e9f4fd; padding: 13px 0;">
        <div class="container" style="font-size:14px;">
            <a href="index.php" style="color: var(--primary); text-decoration: none;">الرئيسية</a> /
            <a href="places.php" style="color: var(--primary); text-decoration: none;">الأماكن</a> /
            <span style="color:var(--muted);"><?php echo htmlspecialchars($place['name']); ?></span>
        </div>
    </div>

    <!-- DETAILS -->
    <section class="container details">

        <!-- HERO IMAGE WITH OVERLAY TITLE -->
        <div class="details-hero">
            <img id="mainImage" src="images/<?php echo htmlspecialchars(place_image($images[0])); ?>" alt="<?php echo htmlspecialchars($place['name']); ?>">
            <div class="overlay">
                <div>
                    <span class="category <?php echo $catSlug; ?>"><?php echo category_icon($place['category']); ?> <?php echo htmlspecialchars($place['category']); ?></span>
                    <h2><?php echo htmlspecialchars($place['name']); ?></h2>
                </div>
            </div>
        </div>

        <?php if (count($images) > 1): ?>
        <div class="gallery-thumbs">
            <?php foreach ($images as $i => $img): ?>
                <img src="images/<?php echo htmlspecialchars(place_image($img)); ?>"
                     class="<?php echo $i === 0 ? 'active' : ''; ?>"
                     onclick="document.getElementById('mainImage').src=this.src; document.querySelectorAll('.gallery-thumbs img').forEach(t=>t.classList.remove('active')); this.classList.add('active');"
                     alt="صورة <?php echo $i+1; ?>">
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- INFO CARDS -->
        <div class="info-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 30px 0;">
            <div class="place-details">
                <h3>📍 الموقع</h3>
                <p><?php echo htmlspecialchars($place['location']); ?></p>
            </div>
            <div class="place-details">
                <h3>⭐ التقييم</h3>
                <p class="rating">★★★★★ (4.5/5)</p>
                <?php if (!empty($place['phone'])): ?>
                    <p style="margin-top:10px;">📞 <a href="tel:<?php echo htmlspecialchars($place['phone']); ?>" style="color:var(--primary);font-weight:700;text-decoration:none;"><?php echo htmlspecialchars($place['phone']); ?></a></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- EMBEDDED GOOGLE MAP -->
        <div class="place-details">
            <h3>🗺️ الموقع على الخريطة</h3>
            <div class="map-embed">
                <iframe src="<?php echo htmlspecialchars($mapsEmbed); ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
            </div>
            <a class="btn" style="margin-top:12px;" href="<?php echo htmlspecialchars($mapsUrl); ?>" target="_blank" rel="noopener">🗺️ فتح في خرائط قوقل</a>
        </div>

        <!-- DESCRIPTION -->
        <div class="place-details">
            <h3>📝 الوصف</h3>
            <p><?php echo nl2br(htmlspecialchars($place['description'])); ?></p>
        </div>

        <!-- FEATURES -->
        <div class="place-details">
            <h3>✨ المميزات</h3>
            <ul style="list-style: none; padding: 0;">
                <li style="padding: 8px 0;">✓ مكان آمن وموثوق</li>
                <li style="padding: 8px 0;">✓ خدمات سياحية متميزة</li>
                <li style="padding: 8px 0;">✓ إطلالات طبيعية خلابة</li>
                <li style="padding: 8px 0;">✓ مرافق حديثة</li>
            </ul>
        </div>

        <!-- BUTTONS -->
        <div style="text-align: center; margin: 40px 0;">
            <a href="contact.php" class="btn">تواصل معنا 📧</a>
            <a href="places.php" class="btn btn-secondary" style="margin-right: 10px;">العودة للأماكن ←</a>
        </div>

        <!-- SIMILAR PLACES -->
        <?php if ($similar->num_rows > 0): ?>
        <div class="section-head">
            <div><h2>أماكن مشابهة</h2></div>
        </div>
        <div class="scroll-row">
            <?php while ($row = $similar->fetch_assoc()): ?>
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
            <?php endwhile; ?>
        </div>
        <?php endif; ?>
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
