<?php
session_start();
include '../db.php';
check_admin();

$sql = "SELECT p.*, c.name AS category, COALESCE((SELECT image_path FROM images WHERE place_id=p.id ORDER BY is_main DESC,id ASC LIMIT 1), '') AS image FROM places p JOIN categories c ON c.id=p.category_id ORDER BY p.created_at DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الأماكن - روح ينبع</title>
    <link rel="stylesheet" href="../css/style.css?v=<?= substr((string)@md5_file(__DIR__ . '/../css/style.css'), 0, 8) ?>">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&family=Markazi+Text:wght@500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <h1>🌴 روح ينبع - إدارة الأماكن</h1>
            <ul>
                <li><a href="dashboard.php">لوحة التحكم</a></li>
                <li><a href="categories.php">التصنيفات</a></li>
                <li><a href="messages.php">الرسائل</a></li>
                <li><a href="logout.php">تسجيل خروج</a></li>
            </ul>
        </div>
    </nav>

    <section class="container">
        <h2>📋 إدارة الأماكن</h2>
        <a href="add_place.php" class="btn" style="margin: 20px 0; display: inline-block;">➕ إضافة مكان جديد</a>

        <div class="table-responsive" style="background: white; padding: 20px; border-radius: 10px; margin: 20px 0; box-shadow: 0 4px 15px rgba(0,0,0,0.08);">
            <table>
                <thead>
                    <tr>
                        <th>الصورة</th>
                        <th>الاسم</th>
                        <th>الفئة</th>
                        <th>الموقع</th>
                        <th>التاريخ</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result->num_rows > 0) {
                        while($row = $result->fetch_assoc()) {
                            ?>
                            <tr>
                                <td><img src="../images/<?php echo htmlspecialchars(place_image($row['image'])); ?>" style="width: 50px; height: 50px; border-radius: 5px; object-fit: cover;"></td>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['category']); ?></td>
                                <td><?php echo htmlspecialchars($row['location']); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($row['created_at'])); ?></td>
                                <td class="action-btns">
                                    <a href="edit_place.php?id=<?php echo $row['id']; ?>" class="edit-btn">تعديل</a>
                                    <a href="delete_place.php?id=<?php echo $row['id']; ?>" class="delete-btn" onclick="return confirm('هل أنت متأكد؟')">حذف</a>
                                </td>
                            </tr>
                            <?php
                        }
                    } else {
                        echo "<tr><td colspan='6' style='text-align: center; padding: 40px;'>لا توجد أماكن مسجلة</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </section>

    <footer>
        <div class="container">
            <p>&copy; 2024 روح ينبع | جميع الحقوق محفوظة</p>
        </div>
    </footer>
</body>
</html>