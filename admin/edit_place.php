<?php
session_start();
include '../db.php';
check_admin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: places-list.php'); exit(); }

$stmt = $conn->prepare("SELECT * FROM places WHERE id=?");
$stmt->bind_param('i', $id);
$stmt->execute();
$place = $stmt->get_result()->fetch_assoc();
if (!$place) { header('Location: places-list.php'); exit(); }

$success = '';
$error = '';
$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

if (upload_too_large()) {
    $error = 'حجم الصور المرفوعة أكبر من المسموح. ارفع صورًا أصغر (المجموع أقل من 4MB في المرة الواحدة).';
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

    $uploadedFiles = [];
    if (!$error && !empty($_FILES['images']['name'][0])) {
        $count = count($_FILES['images']['name']);
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

    if (!$error) {
        $u = $conn->prepare('UPDATE places SET name=?, description=?, category_id=?, location=?, google_maps_url=?, phone=? WHERE id=?');
        $u->bind_param('ssisssi', $name, $description, $category_id, $location, $google_maps_url, $phone, $id);
        if ($u->execute()) {
            if (!empty($uploadedFiles)) {
                $hasMain = (bool)$conn->query('SELECT 1 FROM images WHERE place_id=' . (int)$id . ' AND is_main=1')->fetch_row();
                foreach ($uploadedFiles as $f) {
                    if (store_place_image($f['tmp'], $f['name'], $f['mime'])) {
                        $main = $hasMain ? 0 : 1;
                        $im = $conn->prepare('INSERT INTO images (place_id, image_path, is_main) VALUES (?, ?, ?)');
                        $im->bind_param('isi', $id, $f['name'], $main);
                        $im->execute();
                        $hasMain = true;
                    }
                }
            }
            $success = 'تم تعديل المكان بنجاح';
            $place = array_merge($place, compact('name', 'category_id', 'location', 'description', 'google_maps_url', 'phone'));
        } else {
            $error = 'تعذر حفظ التعديلات';
        }
    }
}

$imgRes = $conn->prepare('SELECT id, image_path, is_main FROM images WHERE place_id=? ORDER BY is_main DESC, id ASC');
$imgRes->bind_param('i', $id);
$imgRes->execute();
$images = $imgRes->get_result();
?>
<!doctype html><html lang="ar" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>تعديل مكان - روح ينبع</title><link rel="stylesheet" href="../css/style.css?v=<?= substr((string)@md5_file(__DIR__ . '/../css/style.css'), 0, 8) ?>"><link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&family=Markazi+Text:wght@500;600;700&display=swap" rel="stylesheet">
<style>
.img-manage{display:flex;gap:12px;flex-wrap:wrap;margin:15px 0;}
.img-manage .thumb{position:relative;width:120px;}
.img-manage img{width:120px;height:90px;object-fit:cover;border-radius:6px;border:2px solid #e0e0e0;}
.img-manage .thumb.main img{border-color:var(--primary);}
.img-manage .thumb-actions{display:flex;gap:4px;margin-top:4px;font-size:12px;}
.img-manage .thumb-actions a{flex:1;text-align:center;padding:4px;border-radius:4px;text-decoration:none;}
</style>
</head><body>
<nav class="navbar"><div class="container"><h1>🌴 تعديل مكان</h1><ul><li><a href="dashboard.php">لوحة التحكم</a></li><li><a href="places-list.php">الأماكن</a></li><li><a href="logout.php">تسجيل خروج</a></li></ul></div></nav>
<section class="container"><div class="admin-container" style="max-width:700px;margin:40px auto"><h2>✏️ تعديل المكان</h2>
<?php if($success): ?><div class="alert alert-success"><?=htmlspecialchars($success)?></div><?php endif; ?><?php if($error): ?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif; ?>

<h3 style="margin:15px 0 5px;">صور المكان الحالية</h3>
<div class="img-manage">
<?php if ($images->num_rows === 0): ?>
    <p style="color:#777;">لا توجد صور بعد — أضف صورًا من الأسفل.</p>
<?php else: while ($img = $images->fetch_assoc()): ?>
    <div class="thumb <?php echo $img['is_main'] ? 'main' : ''; ?>">
        <img src="../images/<?php echo htmlspecialchars($img['image_path']); ?>" alt="">
        <div class="thumb-actions">
            <?php if (!$img['is_main']): ?>
                <a href="delete_image.php?id=<?php echo $img['id']; ?>&place_id=<?php echo $id; ?>&make_main=1" class="edit-btn">رئيسية</a>
            <?php else: ?>
                <span style="flex:1;text-align:center;color:var(--primary);">رئيسية ✓</span>
            <?php endif; ?>
            <a href="delete_image.php?id=<?php echo $img['id']; ?>&place_id=<?php echo $id; ?>" class="delete-btn" onclick="return confirm('حذف هذه الصورة؟');">حذف</a>
        </div>
    </div>
<?php endwhile; endif; ?>
</div>

<form method="post" enctype="multipart/form-data" class="contact-form">
<input name="name" value="<?=htmlspecialchars($place['name'])?>" required>
<select name="category_id" required><?php $cats=$conn->query('SELECT id,name FROM categories ORDER BY id'); while($c=$cats->fetch_assoc()): ?><option value="<?=$c['id']?>" <?=$place['category_id']==$c['id']?'selected':''?>><?=htmlspecialchars($c['name'])?></option><?php endwhile; ?></select>
<input name="location" value="<?=htmlspecialchars($place['location'])?>" required>
<input type="url" name="google_maps_url" placeholder="رابط خرائط قوقل (اختياري)" value="<?=htmlspecialchars($place['google_maps_url'] ?? '')?>">
<input type="text" name="phone" placeholder="رقم الهاتف (اختياري)" value="<?=htmlspecialchars($place['phone'] ?? '')?>">
<textarea name="description" rows="7" required><?=htmlspecialchars($place['description'])?></textarea>
<label style="display:block;margin:10px 0 6px;color:#555;">إضافة صور جديدة (اختياري، يمكن اختيار أكثر من صورة، ويُفضّل ألا يتجاوز مجموعها 4MB في المرة الواحدة)</label>
<input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple>
<button class="btn">حفظ التعديلات</button>
</form>
</div></section></body></html>
