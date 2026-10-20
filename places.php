<?php
session_start();
include 'db.php';

$category = isset($_GET['category']) ? clean($_GET['category']) : '';
$search = isset($_GET['search']) ? clean($_GET['search']) : '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 9;

// بناء الاستعلام مع ربط جدول التصنيفات والصور
$where = []; $types = ''; $params = [];
if ($category !== '') { $where[] = 'c.name = ?'; $types .= 's'; $params[] = $category; }
if ($search !== '') { $where[] = '(p.name LIKE ? OR p.description LIKE ? OR p.location LIKE ?)'; $types .= 'sss'; $like = "%$search%"; $params[]=$like; $params[]=$like; $params[]=$like; }
$whereSql = $where ? ' WHERE '.implode(' AND ',$where) : '';
$count = $conn->prepare("SELECT COUNT(*) total FROM places p JOIN categories c ON c.id=p.category_id$whereSql");
if ($types) $count->bind_param($types, ...$params);
$count->execute(); $total_rows=(int)$count->get_result()->fetch_assoc()['total']; $total_pages=(int)ceil($total_rows/$limit);
$page = min($page, max(1, $total_pages));
$offset = ($page - 1) * $limit;
$sql="SELECT p.*, c.name AS category, COALESCE((SELECT image_path FROM images WHERE place_id=p.id ORDER BY is_main DESC,id ASC LIMIT 1),'') AS image FROM places p JOIN categories c ON c.id=p.category_id$whereSql ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
$stmt=$conn->prepare($sql); $types2=$types.'ii'; $params2=array_merge($params,[$limit,$offset]); $stmt->bind_param($types2,...$params2); $stmt->execute(); $result=$stmt->get_result();
$categories=$conn->query("SELECT name FROM categories ORDER BY id");
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الأماكن - روح ينبع</title>
    <link rel="stylesheet" href="css/style.css?v=<?= @filemtime(__DIR__ . '/css/style.css') ?: 1 ?>">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&family=Markazi+Text:wght@500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <!-- NAVBAR -->
    <?php require __DIR__ . '/nav.php'; ?>

    <!-- HERO -->
    <section class="hero">
        <div class="container">
            <?php if ($category !== ''): ?>
                <h2><?php echo category_icon($category); ?> وجهات <?php echo htmlspecialchars($category); ?></h2>
                <p>تصفّح كل الأماكن المصنّفة ضمن "<?php echo htmlspecialchars($category); ?>" في ينبع</p>
            <?php else: ?>
                <h2>استكشف الأماكن</h2>
                <p>اكتشف كل الأماكن السياحية الرائعة في ينبع</p>
            <?php endif; ?>
        </div>
    </section>

    <!-- SEARCH & FILTER -->
    <section class="container">
        <div class="search-filter">
            <input type="text" id="search" placeholder="ابحث عن مكان..." value="<?php echo htmlspecialchars($search); ?>">
            <select id="category">
                <option value="">جميع الفئات</option>
                <?php while($cat=$categories->fetch_assoc()): ?>
                <option value="<?=htmlspecialchars($cat['name'])?>" <?=$category===$cat['name']?'selected':''?>><?=htmlspecialchars($cat['name'])?></option>
                <?php endwhile; ?>
            </select>
            <button class="btn" onclick="filterPlaces()">بحث</button>
        </div>
    </section>

    <!-- PLACES GRID -->
    <section class="container">
        <div class="places-grid">
            <?php
            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    ?>
                    <div class="place-card <?php echo category_slug($row['category']); ?>">
                        <div class="img-wrap">
                            <img src="images/<?php echo htmlspecialchars(place_image($row['image'])); ?>" alt="<?php echo htmlspecialchars($row['name']); ?>">
                        </div>
                        <div class="place-card-content">
                            <span class="category <?php echo category_slug($row['category']); ?>"><?php echo category_icon($row['category']); ?> <?php echo htmlspecialchars($row['category']); ?></span>
                            <h3><?php echo htmlspecialchars($row['name']); ?></h3>
                            <p class="location">📍 <?php echo htmlspecialchars($row['location']); ?></p>
                            <p><?php echo substr(htmlspecialchars($row['description']), 0, 100) . '...'; ?></p>
                            <a href="details.php?id=<?php echo $row['id']; ?>" class="btn">عرض التفاصيل</a>
                        </div>
                    </div>
                    <?php
                }
            } else {
                echo "<p style='text-align:center; grid-column: 1/-1; padding: 40px;'>لم يتم العثور على نتائج</p>";
            }
            ?>
        </div>

        <!-- PAGINATION -->
        <?php if($total_pages > 1) { ?>
        <div style="text-align: center; margin-top: 40px;">
            <?php for($i = 1; $i <= $total_pages; $i++) { ?>
                <a href="places.php?page=<?php echo $i; ?>&category=<?php echo htmlspecialchars($category); ?>&search=<?php echo htmlspecialchars($search); ?>" 
                   class="btn <?php if($i == $page) echo 'active'; ?>" 
                   style="margin: 5px;">صفحة <?php echo $i; ?></a>
            <?php } ?>
        </div>
        <?php } ?>
    </section>

    <!-- FOOTER -->
    <footer>
        <div class="container">
            <p>&copy; 2026 روح ينبع | جميع الحقوق محفوظة</p>
            <p>📧 jf26tvtc@gmail.com | 📱 +96656789455</p>
        </div>
    </footer>
    <script src="js/script.js"></script>
</body>
</html>
