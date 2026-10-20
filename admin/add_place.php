<?php
session_start();
include '../db.php';
check_admin();

$success = '';
$error = '';
$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

if (upload_too_large()) {
    $error = 'حجم الصور المرفوعة أكبر من المسموح في إعدادات الخادم. ارفع صورًا أصغر أو عددًا أقل في المرة الواحدة.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $location = trim($_POST['location'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $google_maps_url = trim($_POST['google_maps_url'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($name === '' || $category_id <= 0 || $location === '' || $description === '') {
        $error = 'يرجى تعبئة جميع الحقول المطلوبة';
    }

    // التحقق من كل الصور المرفوعة قبل حفظ أي شيء في قاعدة البيانات
    $uploadedFiles = [];
    if (!$error && !empty($_FILES['images']['name'][0])) {
        $count = count($_FILES['images']['name']);
        if ($count > 10) {
            $error = 'الحد الأقصى 10 صور لكل مكان';
        } else {
            for ($i = 0; $i < $count; $i++) {
                if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $tmp = $_FILES['images']['tmp_name'][$i];
                $mime = mime_content_type($tmp);
                if (!isset($allowed[$mime]) || $_FILES['images']['size'][$i] > 5 * 1024 * 1024) {
                    $error = 'كل صورة يجب أن تكون JPG أو PNG أو WEBP وأقل من 5MB';
                    break;
                }
                $filename = uniqid('place_', true) . '.' . $allowed[$mime];
                $uploadedFiles[] = ['tmp' => $tmp, 'name' => $filename, 'mime' => $mime];
            }
        }
    }

    if (!$error) {
        $stmt = $conn->prepare('INSERT INTO places (name, description, category_id, location, google_maps_url, phone) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('ssisss', $name, $description, $category_id, $location, $google_maps_url, $phone);
        if ($stmt->execute()) {
            $place_id = $stmt->insert_id;
            $isFirst = true;
            foreach ($uploadedFiles as $f) {
                if (store_place_image($f['tmp'], $f['name'], $f['mime'])) {
                    $im = $conn->prepare('INSERT INTO images (place_id, image_path, is_main) VALUES (?, ?, ?)');
                    $main = $isFirst ? 1 : 0;
                    $im->bind_param('isi', $place_id, $f['name'], $main);
                    $im->execute();
                    $isFirst = false;
                }
            }
            $success = 'تم إضافة المكان بنجاح!';
        } else {
            $error = 'تعذر إضافة المكان';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إضافة مكان - روح ينبع</title>
    <link rel="stylesheet" href="../css/style.css?v=<?= substr((string)@md5_file(__DIR__ . '/../css/style.css'), 0, 8) ?>">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&family=Markazi+Text:wght@500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <h1>🌴 روح ينبع - إضافة مكان جديد</h1>
            <ul>
                <li><a href="dashboard.php">لوحة التحكم</a></li>
                <li><a href="places-list.php">الأماكن</a></li>
                <li><a href="logout.php">تسجيل خروج</a></li>
            </ul>
        </div>
    </nav>

    <section class="container">
        <div style="max-width: 600px; margin: 40px auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.08);">
            <h2 style="color: var(--deep); margin-bottom: 30px;">➕ إضافة مكان جديد</h2>

            <?php if($success) { ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php } ?>
            <?php if($error) { ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php } ?>

            <form method="POST" enctype="multipart/form-data">
                <input type="text" name="name" placeholder="اسم المكان" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required style="width: 100%; padding: 12px; margin-bottom: 15px; border: 2px solid #e0e0e0; border-radius: 6px;">

                <select name="category_id" required style="width: 100%; padding: 12px; margin-bottom: 15px; border: 2px solid #e0e0e0; border-radius: 6px;">
                    <option value="">اختر الفئة</option>
                    <?php $cats=$conn->query("SELECT id,name FROM categories ORDER BY id"); while($cat=$cats->fetch_assoc()): ?>
                    <option value="<?=$cat['id']?>" <?php echo (isset($_POST['category_id']) && (int)$_POST['category_id']===(int)$cat['id']) ? 'selected' : ''; ?>><?=htmlspecialchars($cat['name'])?></option>
                    <?php endwhile; ?>
                </select>

                <input type="text" name="location" placeholder="الموقع" value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>" required style="width: 100%; padding: 12px; margin-bottom: 15px; border: 2px solid #e0e0e0; border-radius: 6px;">

                <input type="url" name="google_maps_url" placeholder="رابط خرائط قوقل (اختياري — سيُولَّد تلقائيًا إن تُرك فارغًا)" value="<?php echo htmlspecialchars($_POST['google_maps_url'] ?? ''); ?>" style="width: 100%; padding: 12px; margin-bottom: 15px; border: 2px solid #e0e0e0; border-radius: 6px;">

                <input type="text" name="phone" placeholder="رقم الهاتف (اختياري — لمطعم أو منشأة لها رقم تواصل)" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" style="width: 100%; padding: 12px; margin-bottom: 15px; border: 2px solid #e0e0e0; border-radius: 6px;">

                <textarea name="description" placeholder="وصف المكان" rows="6" required style="width: 100%; padding: 12px; margin-bottom: 15px; border: 2px solid #e0e0e0; border-radius: 6px; font-family: inherit;"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>

                <label style="display:block;margin-bottom:6px;color:#555;">صور المكان (يمكن اختيار أكثر من صورة — أول صورة تصبح الصورة الرئيسية)</label>
                <input type="file" name="images[]" accept="image/*" multiple style="width: 100%; padding: 12px; margin-bottom: 15px; border: 2px solid #e0e0e0; border-radius: 6px;">

                <button type="submit" class="btn" style="width: 100%; padding: 12px;">إضافة المكان ✓</button>
            </form>
        </div>
    </section>

    <footer>
        <div class="container">
            <p>&copy; 2026 روح ينبع | جميع الحقوق محفوظة</p>
        </div>
    </footer>
</body>
</html>
