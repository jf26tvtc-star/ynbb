<?php
session_start();
include '../db.php';
check_admin();

$places_count = (int)$conn->query("SELECT COUNT(*) AS count FROM places")->fetch_assoc()['count'];
$messages_count = (int)$conn->query("SELECT COUNT(*) AS count FROM messages WHERE is_read = 0")->fetch_assoc()['count'];
$total_messages = (int)$conn->query("SELECT COUNT(*) AS count FROM messages")->fetch_assoc()['count'];
$categories = $conn->query("SELECT c.id, c.name, COUNT(p.id) AS places_count FROM categories c LEFT JOIN places p ON p.category_id = c.id GROUP BY c.id, c.name ORDER BY places_count DESC, c.id")->fetch_all(MYSQLI_ASSOC);
$categories_count = count($categories);
$places_with_images = (int)$conn->query("SELECT COUNT(*) AS count FROM places p WHERE EXISTS (SELECT 1 FROM images i WHERE i.place_id = p.id AND TRIM(i.image_path) <> '')")->fetch_assoc()['count'];
$image_coverage = $places_count > 0 ? (int)round($places_with_images * 100 / $places_count) : 0;
$recent_messages = $conn->query("SELECT name, subject, is_read, created_at FROM messages ORDER BY created_at DESC, id DESC LIMIT 3")->fetch_all(MYSQLI_ASSOC);

$search_input = $_GET['q'] ?? '';
$category_input = $_GET['category'] ?? '0';
$category_id = is_string($category_input) ? filter_var($category_input, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) : false;
if (!is_string($search_input) || $category_id === false) {
    http_response_code(400);
    exit('خيارات البحث غير صالحة. يرجى العودة إلى لوحة التحكم وإعادة المحاولة.');
}
$search = trim($search_input);
$category_ids = array_map('intval', array_column($categories, 'id'));
if ($category_id !== 0 && !in_array($category_id, $category_ids, true)) {
    http_response_code(400);
    exit('التصنيف المطلوب غير موجود. يرجى العودة إلى لوحة التحكم واختيار تصنيف صالح.');
}
$has_filters = $search !== '' || $category_id > 0;
$search_term = '%' . $search . '%';
$stmt = $conn->prepare("SELECT p.id, p.name, p.location, p.created_at, c.name AS category,
    COALESCE((SELECT image_path FROM images WHERE place_id = p.id AND TRIM(image_path) <> '' ORDER BY is_main DESC, id ASC LIMIT 1), '') AS image
    FROM places p JOIN categories c ON c.id = p.category_id
    WHERE (? = '' OR p.name LIKE ? OR p.location LIKE ?) AND (? = 0 OR p.category_id = ?)
    ORDER BY p.created_at DESC, p.id DESC LIMIT 6");
$stmt->bind_param('sssii', $search, $search_term, $search_term, $category_id, $category_id);
$stmt->execute();
$recent_places = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

function dashboard_icon($name) {
    $paths = [
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>',
        'pin' => '<path d="M20 10c0 6-8 11-8 11S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
        'tag' => '<path d="m21 13-8 8L3 11V3h8Z"/><circle cx="7.5" cy="7.5" r="1"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="3"/><path d="m3 7 9 6 9-6"/>',
        'arrow' => '<path d="M19 12H5m6-6-6 6 6 6"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'globe' => '<circle cx="12" cy="12" r="9"/><ellipse cx="12" cy="12" rx="4" ry="9"/><path d="M3 12h18"/>',
        'logout' => '<path d="M9 4H4v16h5m5-12 4 4-4 4m-5-4h12"/>',
        'search' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M7 3v4m10-4v4M3 11h18"/>',
        'image' => '<rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8" cy="8" r="1.5"/><path d="m3 17 5-5 4 4 4-6 5 7"/>',
        'edit' => '<path d="m16 3 5 5-12 12-6 1 1-6Zm-2 2 5 5"/>',
        'trash' => '<path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7m4-7v7"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>',
    ];
    return '<svg class="dash-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths[$name] . '</svg>';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم - روح ينبع</title>
    <link rel="stylesheet" href="../css/style.css?v=<?= @filemtime(__DIR__ . '/../css/style.css') ?: 1 ?>">
    <link rel="stylesheet" href="../css/admin-dashboard.css?v=<?= @filemtime(__DIR__ . '/../css/admin-dashboard.css') ?: 1 ?>">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body class="dashboard-page">
    <a class="dash-skip" href="#main-content">تجاوز إلى المحتوى</a>
    <aside class="dash-sidebar">
        <a class="dash-brand" href="dashboard.php">روح ينبع<small>لوحة إدارة الوجهات</small></a>
        <nav class="dash-menu" aria-label="القائمة الرئيسية للإدارة">
            <a class="dash-menu-home is-active" href="dashboard.php" aria-current="page">نظرة عامة</a>
            <details class="dash-menu-group" open>
                <summary>المحتوى</summary>
                <div class="dash-menu-links">
                    <a href="places-list.php">إدارة الأماكن <span class="dash-nav-count"><?= $places_count ?></span></a>
                    <a href="add_place.php">إضافة مكان جديد</a>
                    <a href="categories.php">التصنيفات <span class="dash-nav-count"><?= $categories_count ?></span></a>
                </div>
            </details>
            <details class="dash-menu-group" open>
                <summary>التواصل <?php if ($messages_count > 0): ?><span class="dash-nav-count is-new"><?= $messages_count ?> جديدة</span><?php endif; ?></summary>
                <div class="dash-menu-links">
                    <a href="messages.php">رسائل الزوار <span class="dash-nav-count"><?= $total_messages ?></span></a>
                </div>
            </details>
            <details class="dash-menu-group">
                <summary>الحساب</summary>
                <div class="dash-menu-links">
                    <a href="../index.php">زيارة الموقع</a>
                    <a class="dash-menu-logout" href="logout.php">تسجيل الخروج</a>
                </div>
            </details>
        </nav>
        <div class="dash-sidebar-note">
            <strong>وجهات تستحق الاكتشاف</strong>
            <p>أضف مكانًا جديدًا، وساعد زوار ينبع على صنع رحلة أجمل.</p>
            <a href="add_place.php">إضافة وجهة</a>
        </div>
    </aside>

    <div class="dash-workspace">
        <header class="dash-topbar">
            <div class="dash-breadcrumb">الإدارة <span>/</span> <strong>نظرة عامة</strong></div>
            <div class="dash-topbar-actions">
                <a class="dash-notification" href="messages.php" aria-label="رسائل الزوار، <?= $messages_count ?> غير مقروءة">
                    <?= dashboard_icon('mail') ?><?php if ($messages_count > 0): ?><span class="dash-notification-dot"></span><?php endif; ?>
                </a>
                <div class="dash-profile"><span class="dash-avatar">م</span><div><strong><?= htmlspecialchars($_SESSION['admin_username'] ?? 'المسؤول') ?></strong><small>مدير الموقع</small></div></div>
            </div>
        </header>

        <main class="dash-main" id="main-content">
            <div class="dash-page-heading">
                <div><p class="dash-eyebrow">كل التفاصيل، في مكان واحد</p><h1>لوحة التحكم</h1><p>نظرة شاملة على وجهات ينبع وتواصل زوارك.</p></div>
                <span class="dash-date"><?= dashboard_icon('calendar') ?> <time datetime="<?= date('Y-m-d') ?>"><?= date('Y/m/d') ?></time></span>
            </div>

            <section class="dash-welcome" aria-labelledby="welcome-title">
                <div class="dash-welcome-content">
                    <span class="dash-welcome-label"><span></span> مساحة إدارة روح ينبع</span>
                    <h2 id="welcome-title">أهلًا بك، رحلة أجمل تبدأ من هنا</h2>
                    <p>أدر وجهاتك، نظّم محتواك، وابقَ قريبًا من زوارك.<br>كل ما تحتاجه لتُظهر ينبع بأجمل صورة.</p>
                    <div class="dash-welcome-actions">
                        <a href="add_place.php" class="dash-button dash-button-gold"><?= dashboard_icon('plus') ?> إضافة مكان جديد</a>
                        <a href="../index.php" class="dash-hero-link">استعراض الموقع <?= dashboard_icon('arrow') ?></a>
                    </div>
                </div>
                <div class="dash-coast-art" aria-hidden="true"><span class="dash-sun"></span><span class="dash-orbit orbit-one"></span><span class="dash-orbit orbit-two"></span><span class="dash-art-pin"><?= dashboard_icon('pin') ?></span><span class="dash-art-caption">ينبع<span>بحر، تراث، وحياة</span></span></div>
            </section>

            <section class="dash-stats" aria-label="إحصائيات الموقع">
                <a class="dash-stat" href="places-list.php"><span class="dash-stat-icon tone-green"><?= dashboard_icon('pin') ?></span><span class="dash-stat-label">إجمالي الأماكن</span><strong><?= number_format($places_count) ?></strong><span class="dash-stat-foot">وجهات متاحة للاستكشاف <?= dashboard_icon('arrow') ?></span></a>
                <a class="dash-stat" href="categories.php"><span class="dash-stat-icon tone-gold"><?= dashboard_icon('tag') ?></span><span class="dash-stat-label">التصنيفات</span><strong><?= number_format($categories_count) ?></strong><span class="dash-stat-foot">تنوع يُلهم رحلة الزائر <?= dashboard_icon('arrow') ?></span></a>
                <a class="dash-stat" href="messages.php"><span class="dash-stat-icon tone-blue"><?= dashboard_icon('mail') ?></span><span class="dash-stat-label">رسائل غير مقروءة</span><strong><?= number_format($messages_count) ?></strong><span class="dash-stat-foot"><?= $messages_count > 0 ? 'بانتظار اطلاعك وردّك' : 'أنت مطّلع على كل الرسائل' ?> <?= dashboard_icon('arrow') ?></span></a>
                <a class="dash-stat" href="messages.php"><span class="dash-stat-icon tone-purple"><?= dashboard_icon('mail') ?></span><span class="dash-stat-label">إجمالي الرسائل</span><strong><?= number_format($total_messages) ?></strong><span class="dash-stat-foot">كل تواصل يصنع فرقًا <?= dashboard_icon('arrow') ?></span></a>
            </section>

            <section class="dash-quick-actions" aria-label="إجراءات سريعة">
                <strong>وصول سريع</strong>
                <a href="add_place.php"><?= dashboard_icon('plus') ?> إضافة مكان</a>
                <a href="places-list.php"><?= dashboard_icon('pin') ?> إدارة الأماكن</a>
                <a href="categories.php"><?= dashboard_icon('tag') ?> إدارة التصنيفات</a>
                <a href="messages.php"><?= dashboard_icon('mail') ?> متابعة الرسائل</a>
            </section>

            <div class="dash-content-grid">
                <section class="dash-panel dash-places-panel" aria-labelledby="places-title">
                    <div class="dash-panel-heading">
                        <div><h2 id="places-title"><?= $has_filters ? 'نتائج البحث عن الأماكن' : 'آخر الأماكن المضافة' ?></h2><p>عرض أحدث 6 أماكن<?= $has_filters ? ' مطابقة لبحثك' : ' في دليل روح ينبع' ?></p></div>
                        <a class="dash-text-link" href="places-list.php">عرض الكل <?= dashboard_icon('arrow') ?></a>
                    </div>
                    <form class="dash-search" method="get" action="dashboard.php#places-title" role="search" aria-label="البحث في الأماكن">
                        <div class="dash-search-input"><?= dashboard_icon('search') ?><label class="dash-sr-only" for="place-search">اسم المكان أو الموقع</label><input id="place-search" type="search" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="ابحث باسم المكان أو الموقع..."></div>
                        <label class="dash-sr-only" for="category-filter">التصنيف</label>
                        <select id="category-filter" name="category">
                            <option value="0">كل التصنيفات</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int)$category['id'] ?>" <?= $category_id === (int)$category['id'] ? 'selected' : '' ?>><?= htmlspecialchars($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="dash-button dash-button-primary" type="submit">بحث</button>
                        <?php if ($has_filters): ?><a class="dash-reset" href="dashboard.php#places-title">مسح</a><?php endif; ?>
                    </form>
                    <?php if (count($recent_places) > 0): ?>
                        <div class="dash-table-wrap">
                            <table class="dash-table">
                                <caption class="dash-sr-only"><?= $has_filters ? 'الأماكن المطابقة للبحث' : 'أحدث الأماكن المضافة' ?></caption>
                                <thead><tr><th scope="col">المكان</th><th scope="col">التصنيف</th><th scope="col">تاريخ الإضافة</th><th scope="col">الإجراءات</th></tr></thead>
                                <tbody>
                                    <?php foreach ($recent_places as $place): ?>
                                        <tr>
                                            <td><div class="dash-place-cell"><img src="../images/<?= htmlspecialchars(place_image($place['image'])) ?>" alt="" width="46" height="46" loading="lazy"><div><a href="../details.php?id=<?= (int)$place['id'] ?>"><?= htmlspecialchars($place['name']) ?></a><small><?= htmlspecialchars($place['location']) ?></small></div></div></td>
                                            <td><span class="dash-category-badge"><?= htmlspecialchars($place['category']) ?></span></td>
                                            <td><time datetime="<?= date('Y-m-d', strtotime($place['created_at'])) ?>"><?= date('Y/m/d', strtotime($place['created_at'])) ?></time></td>
                                            <td><div class="dash-row-actions"><a href="edit_place.php?id=<?= (int)$place['id'] ?>" aria-label="تعديل <?= htmlspecialchars($place['name']) ?>" title="تعديل"><?= dashboard_icon('edit') ?></a><a class="dash-delete" href="delete_place.php?id=<?= (int)$place['id'] ?>" aria-label="حذف <?= htmlspecialchars($place['name']) ?>" title="حذف" onclick="return confirm('هل أنت متأكد من حذف هذا المكان؟')"><?= dashboard_icon('trash') ?></a></div></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="dash-empty"><span><?= dashboard_icon($has_filters ? 'search' : 'pin') ?></span><h3><?= $has_filters ? 'لا توجد أماكن مطابقة' : 'أول وجهة تبدأ معك' ?></h3><p><?= $has_filters ? 'جرّب اسمًا آخر أو اختر تصنيفًا مختلفًا.' : 'أضف أول مكان سياحي ليبدأ الزوار باكتشاف جمال ينبع.' ?></p><a class="dash-button dash-button-primary" href="<?= $has_filters ? 'dashboard.php#places-title' : 'add_place.php' ?>"><?= $has_filters ? 'عرض أحدث الأماكن' : 'إضافة أول مكان' ?></a></div>
                    <?php endif; ?>
                </section>

                <section class="dash-panel dash-categories-panel" aria-labelledby="categories-title">
                    <div class="dash-panel-heading"><div><h2 id="categories-title">توزيع الوجهات</h2><p>الأماكن حسب التصنيف</p></div><span class="dash-panel-symbol"><?= dashboard_icon('tag') ?></span></div>
                    <div class="dash-category-total"><strong><?= number_format($places_count) ?></strong><span>مكان سياحي في <?= $categories_count ?> تصنيفات</span></div>
                    <div class="dash-category-list">
                        <?php foreach ($categories as $category): $percentage = $places_count > 0 ? (int)round((int)$category['places_count'] * 100 / $places_count) : 0; ?>
                            <a class="dash-category-item" href="dashboard.php?category=<?= (int)$category['id'] ?>#places-title"><span class="dash-category-info"><span><?= htmlspecialchars($category['name']) ?></span><strong><?= (int)$category['places_count'] ?> <small>مكان</small></strong></span><span class="dash-progress" aria-hidden="true"><span style="width: <?= $percentage ?>%"></span></span></a>
                        <?php endforeach; ?>
                        <?php if ($categories_count === 0): ?><p class="dash-muted">لا توجد تصنيفات بعد. أضف تصنيفًا لتبدأ بتنظيم وجهاتك.</p><?php endif; ?>
                    </div>
                    <a class="dash-bottom-link" href="categories.php">إدارة التصنيفات <?= dashboard_icon('arrow') ?></a>
                </section>

                <section class="dash-panel dash-messages-panel" aria-labelledby="messages-title">
                    <div class="dash-panel-heading"><div><h2 id="messages-title">آخر رسائل الزوار</h2><p>ابقَ على تواصل مع جمهورك</p></div><a class="dash-text-link" href="messages.php">كل الرسائل <?= dashboard_icon('arrow') ?></a></div>
                    <div class="dash-message-list">
                        <?php foreach ($recent_messages as $message): ?>
                            <a class="dash-message" href="messages.php"><span class="dash-message-avatar"><?= dashboard_icon('mail') ?></span><span class="dash-message-text"><strong><?= htmlspecialchars($message['name']) ?><?php if (!(int)$message['is_read']): ?><span class="dash-unread">جديدة</span><?php endif; ?></strong><span><?= htmlspecialchars($message['subject'] !== '' ? $message['subject'] : 'رسالة بدون عنوان') ?></span></span><time datetime="<?= date('c', strtotime($message['created_at'])) ?>"><?= date('Y/m/d', strtotime($message['created_at'])) ?></time></a>
                        <?php endforeach; ?>
                        <?php if (count($recent_messages) === 0): ?><div class="dash-small-empty"><?= dashboard_icon('mail') ?><p>لا توجد رسائل بعد. ستظهر رسائل الزوار هنا عند وصولها.</p></div><?php endif; ?>
                    </div>
                </section>

                <section class="dash-panel dash-quality-panel" aria-labelledby="quality-title">
                    <div class="dash-panel-heading"><div><h2 id="quality-title">صورة أجمل لوجهاتك</h2><p>تغطية الأماكن بالصور</p></div><span class="dash-panel-symbol"><?= dashboard_icon('image') ?></span></div>
                    <div class="dash-coverage"><strong><?= $image_coverage ?><small>%</small></strong><span><?= $places_with_images ?> من <?= $places_count ?> أماكن لديها صور</span></div>
                    <progress class="dash-quality-progress" max="100" value="<?= $image_coverage ?>" aria-label="نسبة الأماكن التي تحتوي على صور"><?= $image_coverage ?>%</progress>
                    <p class="dash-quality-hint"><?= dashboard_icon($places_count > 0 && $places_with_images === $places_count ? 'check' : 'image') ?><?= $places_count === 0 ? 'أضف أول وجهة مع صورها لتمنح الزائر تجربة أغنى.' : ($places_with_images === $places_count ? 'رائع! جميع وجهاتك مدعومة بصور.' : 'لديك ' . ($places_count - $places_with_images) . ' أماكن بدون صور. أكملها لتُلهم زوارك.') ?></p>
                    <a class="dash-bottom-link" href="<?= $places_count === 0 ? 'add_place.php' : 'places-list.php' ?>"><?= $places_count === 0 ? 'إضافة وجهة جديدة' : 'إدارة صور الأماكن' ?> <?= dashboard_icon('arrow') ?></a>
                </section>
            </div>
            <footer class="dash-footer"><p>&copy; <?= date('Y') ?> روح ينبع. كل رحلة تبدأ باكتشاف.</p><a href="../index.php">صُنع لإبراز جمال ينبع <?= dashboard_icon('globe') ?></a></footer>
        </main>
    </div>
</body>
</html>
