<?php session_start(); ?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>خطط رحلتك - روح ينبع</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&family=Markazi+Text:wght@500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php require __DIR__ . '/nav.php'; ?>
    <main>
        <section class="hero">
            <div class="container">
                <h2>خطط رحلتك</h2>
                <p>اختر الوجهة التي تناسبك، ثم رتّب زيارتك حسب وقتك واهتماماتك.</p>
            </div>
        </section>
        <section class="container">
            <h2>مسارات مقترحة</h2>
            <p>هذه أفكار مرنة للزيارة وليست حجوزات أو برامج سياحية مؤكدة.</p>
            <div class="category-grid">
                <article class="category-card sea">
                    <div class="cc-body">
                        <h3>يوم في ينبع البحر</h3>
                        <p>ابدأ بجولة تراثية، ثم استراحة لتناول الطعام، واختم اليوم بالمشي قرب البحر وقت الغروب.</p>
                        <a class="btn" href="destination.php?area=sea">استكشف ينبع البحر</a>
                    </div>
                </article>
                <article class="category-card nature">
                    <div class="cc-body">
                        <h3>جولة في ينبع النخل</h3>
                        <p>انطلق مبكراً لجولة في الطبيعة والقرى، وخذ استراحة خلال النهار، ورتّب العودة وفق حالة الطريق والطقس.</p>
                        <a class="btn" href="destination.php?area=nakhl">استكشف ينبع النخل</a>
                    </div>
                </article>
                <article class="category-card fun">
                    <div class="cc-body">
                        <h3>وقت عائلي في ينبع الصناعية</h3>
                        <p>اجمع بين نزهة في المساحات الخضراء وجولة على الواجهة البحرية، ثم اختر مطعماً يناسب العائلة.</p>
                        <a class="btn" href="destination.php?area=industrial">استكشف ينبع الصناعية</a>
                    </div>
                </article>
            </div>
        </section>
        <section class="container">
            <h2>قبل الانطلاق</h2>
            <p>راجع الطقس ومواعيد العمل، وحدد ميزانيتك ووسيلة التنقل، وتأكد من موقع كل وجهة وإمكانية الوصول إليها. تحقق من أي حجز أو رسوم مباشرة مع مقدم الخدمة.</p>
            <a class="btn" href="places.php">اكتشف الأماكن</a>
            <a class="btn" href="contact.php">تواصل معنا للاستفسار</a>
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
