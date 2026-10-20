<?php
session_start();
include '../db.php';
check_admin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: categories.php'); exit(); }

$stmt = $conn->prepare('SELECT * FROM categories WHERE id=?');
$stmt->bind_param('i', $id);
$stmt->execute();
$cat = $stmt->get_result()->fetch_assoc();
if (!$cat) { header('Location: categories.php'); exit(); }

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    if ($name === '') {
        $error = 'يرجى إدخال اسم الفئة';
    } else {
        try {
            $u = $conn->prepare('UPDATE categories SET name=?, description=? WHERE id=?');
            $u->bind_param('ssi', $name, $description, $id);
            $u->execute();
            $success = 'تم تعديل الفئة بنجاح';
            $cat = array_merge($cat, compact('name', 'description'));
        } catch (mysqli_sql_exception $e) {
            $error = 'هذا الاسم مستخدم في فئة أخرى';
        }
    }
}
?>
<!doctype html><html lang="ar" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>تعديل فئة - روح ينبع</title><link rel="stylesheet" href="../css/style.css?v=<?= substr((string)@md5_file(__DIR__ . '/../css/style.css'), 0, 8) ?>"><link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&family=Markazi+Text:wght@500;600;700&display=swap" rel="stylesheet"></head><body>
<nav class="navbar"><div class="container"><h1>✏️ تعديل فئة</h1><ul><li><a href="dashboard.php">لوحة التحكم</a></li><li><a href="categories.php">التصنيفات</a></li><li><a href="logout.php">تسجيل خروج</a></li></ul></div></nav>
<section class="container"><div style="max-width:600px;margin:40px auto;background:white;padding:30px;border-radius:10px;box-shadow:0 4px 15px rgba(0,0,0,0.08);">
<h2>تعديل الفئة</h2>
<?php if($success): ?><div class="alert alert-success"><?=htmlspecialchars($success)?></div><?php endif; ?>
<?php if($error): ?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif; ?>
<form method="post" class="contact-form">
<input type="text" name="name" value="<?=htmlspecialchars($cat['name'])?>" required>
<input type="text" name="description" value="<?=htmlspecialchars($cat['description'])?>" placeholder="وصف مختصر">
<button class="btn">حفظ التعديلات</button>
</form>
</div></section></body></html>
