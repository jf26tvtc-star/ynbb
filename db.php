<?php
// الاتصال بقاعدة البيانات المحلية (XAMPP). يمكن تغيير الإعدادات عبر متغيرات البيئة (انظر includes/database.php)
require_once __DIR__ . '/includes/database.php';

try {
    $conn = yanbu_db();
} catch (mysqli_sql_exception $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    yanbu_render_unavailable($e->getMessage());
}

// دالة لتنظيف المدخلات (trim فقط — الحماية من SQL تتم عبر prepared statements،
// والحماية من XSS تتم عند العرض باستخدام htmlspecialchars)
function clean($input) {
    return trim($input ?? '');
}

// دالة للتحقق من صيغة البريد الإلكتروني
function valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// دالة لتصنيف الفئة ضمن الأنواع الأربعة الرئيسية (بحرية / تاريخية / طبيعية / ترفيهية)
// تُستخدم لاختيار الأيقونة/الطابع المناسب لكل فئة دون تعديل بنية قاعدة البيانات
function category_type($categoryName) {
    $name = $categoryName;
    $map = [
        'بحرية' => ['بحر', 'شاطئ', 'شواطئ', 'كورنيش', 'مارينا'],
        'تاريخية' => ['تاريخ', 'معلم', 'معالم', 'أثر', 'قلعة', 'تراث'],
        'طبيعية' => ['طبيع', 'جبل', 'جبال', 'حديقة', 'واحة'],
        'ترفيهية' => ['ترفيه', 'مطعم', 'مطاعم', 'فندق', 'فنادق', 'منتزه'],
    ];
    foreach ($map as $type => $keywords) {
        foreach ($keywords as $kw) {
            if (mb_strpos($name, $kw) !== false) return $type;
        }
    }
    return 'عام';
}

function category_icon($categoryName) {
    $icons = ['بحرية' => '🏖️', 'تاريخية' => '🏛️', 'طبيعية' => '🏔️', 'ترفيهية' => '🎡', 'عام' => '📍'];
    return $icons[category_type($categoryName)] ?? '📍';
}

// صيغة إنجليزية مختصرة (slug) لنوع الفئة، تُستخدم كاسم كلاس CSS لتلوين الشارات والبطاقات
function category_slug($categoryName) {
    $slugs = ['بحرية' => 'sea', 'تاريخية' => 'history', 'طبيعية' => 'nature', 'ترفيهية' => 'fun', 'عام' => 'general'];
    return $slugs[category_type($categoryName)] ?? 'general';
}

// رابط خريطة قوقل المضمّنة (iframe) لمكان معيّن — لا يحتاج مفتاح API
function maps_embed_url($placeName, $location) {
    $q = trim($placeName . ' ' . $location . ' ينبع');
    return 'https://www.google.com/maps?q=' . urlencode($q) . '&output=embed';
}

// رابط خرائط قوقل للفتح في تبويب جديد: يستخدم الرابط المخصص إن وُجد، وإلا يُولَّد من اسم المكان وموقعه
function maps_open_url($googleMapsUrl, $placeName, $location) {
    if (!empty($googleMapsUrl)) return $googleMapsUrl;
    return 'https://www.google.com/maps/search/?api=1&query=' . urlencode(trim($placeName . ' ' . $location . ' ينبع'));
}

// اسم ملف الصورة الفعلي، أو الصورة الافتراضية عند عدم وجود صورة للمكان
// (استدعها مع بادئة المسار المناسبة: "images/" في الصفحات الرئيسية أو "../images/" في لوحة التحكم)
function place_image($path) {
    return $path !== '' ? $path : 'placeholder.svg';
}

// حفظ صورة مرفوعة في مجلد images (يُنشر مع الموقع على GitHub Pages عند الضغط على "نشر الموقع")
function store_place_image($tmpPath, $filename, $mime) {
    return move_uploaded_file($tmpPath, __DIR__ . '/images/' . $filename);
}

// عند تجاوز حجم الطلب الحد المسموح، يفرّغ PHP بيانات النموذج بالكامل؛ نكتشف ذلك لنعرض رسالة واضحة
function upload_too_large() {
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;
}

function delete_place_image_file($filename) {
    $filename = basename($filename);
    $file = __DIR__ . '/images/' . $filename;
    if ($filename !== 'placeholder.svg' && is_file($file)) @unlink($file);
}

// دالة للتحقق من جلسة الأدمن (تستخدم مسارًا نسبيًا ليعمل أيًا كان اسم مجلد المشروع)
function check_admin() {
    if (!isset($_SESSION['admin_id'])) {
        header("Location: login.php");
        exit();
    }
}
?>
