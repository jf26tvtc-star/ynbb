<?php
session_start();
include '../db.php';
check_admin();

$success = $_SESSION['flash'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash'], $_SESSION['flash_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    if ($name === '') {
        $error = 'يرجى إدخال اسم الفئة';
    } else {
        try {
            $stmt = $conn->prepare('INSERT INTO categories (name, description) VALUES (?, ?)');
            $stmt->bind_param('ss', $name, $description);
            $stmt->execute();
            $success = 'تمت إضافة الفئة بنجاح';
        } catch (mysqli_sql_exception $e) {
            $error = 'هذه الفئة موجودة مسبقًا';
        }
    }
}

$cats = $conn->query("SELECT c.*, (SELECT COUNT(*) FROM places p WHERE p.category_id=c.id) AS places_count FROM categories c ORDER BY c.id");
?>
<!doctype html><html lang="ar" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>إدارة التصنيفات - روح ينبع</title><link rel="stylesheet" href="../css/style.css"><link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&family=Markazi+Text:wght@500;600;700&display=swap" rel="stylesheet"></head><body>
<nav class="navbar"><div class="container"><h1>🏷️ إدارة التصنيفات</h1><ul><li><a href="dashboard.php">لوحة التحكم</a></li><li><a href="places-list.php">الأماكن</a></li><li><a href="logout.php">تسجيل خروج</a></li></ul></div></nav>

<section class="container">
    <?php if($success): ?><div class="alert alert-success"><?=htmlspecialchars($success)?></div><?php endif; ?>
    <?php if($error): ?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif; ?>

    <div style="background:white;padding:25px;border-radius:10px;margin:20px 0;box-shadow:0 4px 15px rgba(0,0,0,0.08);max-width:600px;">
        <h3 style="color:var(--deep);margin-bottom:15px;">➕ إضافة فئة جديدة</h3>
        <form method="post" class="contact-form">
            <input type="hidden" name="action" value="add">
            <input type="text" name="name" placeholder="اسم الفئة" required>
            <input type="text" name="description" placeholder="وصف مختصر (اختياري)">
            <button class="btn">إضافة</button>
        </form>
    </div>

    <div class="table-responsive" style="background: white; padding: 20px; border-radius: 10px; margin: 20px 0; box-shadow: 0 4px 15px rgba(0,0,0,0.08);">
        <table>
            <thead><tr><th>الاسم</th><th>الوصف</th><th>عدد الأماكن</th><th>إجراءات</th></tr></thead>
            <tbody>
            <?php if ($cats->num_rows === 0): ?>
                <tr><td colspan="4" style="text-align:center;padding:30px;">لا توجد فئات بعد</td></tr>
            <?php else: while($c = $cats->fetch_assoc()): ?>
                <tr>
                    <td><?php echo category_icon($c['name']); ?> <?=htmlspecialchars($c['name'])?></td>
                    <td><?=htmlspecialchars($c['description'])?></td>
                    <td><?=(int)$c['places_count']?></td>
                    <td class="action-btns">
                        <a href="edit_category.php?id=<?=$c['id']?>" class="edit-btn">تعديل</a>
                        <a href="delete_category.php?id=<?=$c['id']?>" class="delete-btn" onclick="return confirm('حذف هذه الفئة؟');">حذف</a>
                    </td>
                </tr>
            <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</section>

<footer><div class="container"><p>&copy; 2026 روح ينبع | جميع الحقوق محفوظة</p></div></footer>
</body></html>
