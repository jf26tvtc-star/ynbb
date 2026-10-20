<?php
session_start();
include '../db.php';
check_admin();

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    try {
        $d = $conn->prepare('DELETE FROM categories WHERE id=?');
        $d->bind_param('i', $id);
        $d->execute();
        $_SESSION['flash'] = 'تم حذف الفئة بنجاح';
    } catch (mysqli_sql_exception $e) {
        // قيد المفتاح الأجنبي يمنع حذف فئة مرتبطة بأماكن (FOREIGN KEY RESTRICT)
        $_SESSION['flash_error'] = 'لا يمكن حذف هذه الفئة لأنها مستخدمة في أماكن موجودة. عدّل تلك الأماكن إلى فئة أخرى أولًا.';
    }
}

header('Location: categories.php');
exit();
?>
