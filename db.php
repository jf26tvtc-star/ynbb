<?php
// إعدادات الاتصال
$servername = "localhost";
$username = "root";
$password = "";
$database = "yanbu_tourism";

// الاتصال
$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("خطأ في الاتصال: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

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

// دالة للتحقق من جلسة الأدمن (تستخدم مسارًا نسبيًا ليعمل أيًا كان اسم مجلد المشروع)
function check_admin() {
    if (!isset($_SESSION['admin_id'])) {
        header("Location: login.php");
        exit();
    }
}
?>
