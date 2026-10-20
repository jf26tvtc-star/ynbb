<?php
session_start();

$destinations = [
    'sea' => [
        'name' => 'ينبع البحر',
        'intro' => 'اكتشف الوجه الساحلي لينبع، بين البحر الأحمر وأجواء المدينة القديمة.',
        'activities' => [
            ['title' => 'البحر والكورنيش', 'text' => 'خصص وقتاً للمشي على الواجهة البحرية والاستمتاع بمنظر البحر، خاصة وقت الغروب.'],
            ['title' => 'التراث والأسواق', 'text' => 'تعرّف على أجواء ينبع التاريخية وتجول في أحيائها وأسواقها القديمة.'],
            ['title' => 'جلسة على الساحل', 'text' => 'اختم جولتك بوجبة أو جلسة قريبة من البحر، وتحقق من مواعيد المكان قبل الزيارة.'],
        ],
    ],
    'nakhl' => [
        'name' => 'ينبع النخل',
        'intro' => 'وجهة لمحبي الطبيعة والهدوء، تجمع أجواء القرى والنخيل والتراث.',
        'activities' => [
            ['title' => 'الطبيعة والنخيل', 'text' => 'استمتع بمشاهدة الطبيعة واختر وقتاً مناسباً للطقس لجولتك.'],
            ['title' => 'القرى والتراث', 'text' => 'اكتشف ملامح الحياة الريفية والتراث المحلي مع احترام خصوصية السكان والمزارع.'],
            ['title' => 'رحلة هادئة', 'text' => 'جهّز الماء واحتياجات الطريق مسبقاً، وتأكد من مسارك وإمكانية الوصول قبل الانطلاق.'],
        ],
    ],
    'industrial' => [
        'name' => 'ينبع الصناعية',
        'intro' => 'اكتشف الواجهات البحرية والمساحات الخضراء والأجواء العائلية في ينبع الصناعية.',
        'activities' => [
            ['title' => 'الواجهة البحرية', 'text' => 'ابدأ جولتك بالمشي والاستمتاع بإطلالة البحر في الأوقات المعتدلة.'],
            ['title' => 'الحدائق والمساحات الخضراء', 'text' => 'خصص وقتاً للاسترخاء أو نزهة عائلية، وتحقق من تعليمات ومواعيد الموقع الذي تختاره.'],
            ['title' => 'مطاعم وترفيه', 'text' => 'اختر من الأماكن المسجلة في الدليل ما يناسب اهتماماتك ووقت رحلتك.'],
        ],
    ],
];
$area = $_GET['area'] ?? '';
$destination = is_string($area) ? ($destinations[$area] ?? null) : null;
if ($destination === null) {
    http_response_code(404);
}
$title = $destination !== null ? $destination['name'] : 'الوجهة غير موجودة';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?> - روح ينبع</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&family=Markazi+Text:wght@500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php require __DIR__ . '/nav.php'; ?>
    <main>
        <section class="hero">
            <div class="container">
                <h2><?php echo htmlspecialchars($title); ?></h2>
                <p><?php echo $destination !== null ? htmlspecialchars($destination['intro']) : 'الرابط المطلوب غير صحيح. اختر إحدى مناطق ينبع من القائمة.'; ?></p>
            </div>
        </section>
        <section class="container">
            <?php if ($destination !== null): ?>
                <h2>أفكار لزيارتك</h2>
                <div class="category-grid">
                    <?php foreach ($destination['activities'] as $activity): ?>
                        <article class="category-card">
                            <div class="cc-body">
                                <h3><?php echo htmlspecialchars($activity['title']); ?></h3>
                                <p><?php echo htmlspecialchars($activity['text']); ?></p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
                <p>تظهر الأماكن المسجلة التي يرد اسم المنطقة في اسمها أو وصفها أو موقعها.</p>
                <a class="btn" href="places.php?search=<?php echo urlencode($destination['name']); ?>">اكتشف أماكن <?php echo htmlspecialchars($destination['name']); ?></a>
                <a class="btn" href="trip.php">خطط رحلتك</a>
            <?php else: ?>
                <a class="btn" href="index.php">العودة للرئيسية</a>
            <?php endif; ?>
        </section>
    </main>
    <footer>
        <div class="container">
            <p>&copy; 2026 روح ينبع | جميع الحقوق محفوظة</p>
            <a href="contact.php">تواصل معنا</a>
        </div>
    </footer>
</body>
</html>
