<?php
// إنشاء جداول المشروع وبياناته الأساسية بأمان (لا يحذف أي بيانات موجودة، ويمكن تشغيله أكثر من مرة)

function yanbu_install(mysqli $conn, $adminPassword) {
    $log = [];
    $tables = [
        'categories' => "CREATE TABLE IF NOT EXISTS categories (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            description VARCHAR(255) DEFAULT '',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'places' => "CREATE TABLE IF NOT EXISTS places (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            description TEXT NOT NULL,
            category_id INT UNSIGNED NOT NULL,
            location VARCHAR(200) NOT NULL,
            google_maps_url VARCHAR(500) DEFAULT '',
            phone VARCHAR(30) DEFAULT '',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_places_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'images' => "CREATE TABLE IF NOT EXISTS images (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            place_id INT UNSIGNED NOT NULL,
            image_path VARCHAR(255) NOT NULL,
            is_main TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_images_place FOREIGN KEY (place_id) REFERENCES places(id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'users' => "CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(80) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'admin',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'messages' => "CREATE TABLE IF NOT EXISTS messages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(190) NOT NULL,
            phone VARCHAR(30) DEFAULT '',
            subject VARCHAR(190) DEFAULT '',
            message TEXT NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        // الصور المرفوعة من لوحة التحكم على Vercel تُحفظ هنا لأن نظام ملفاته لا يحتفظ بالملفات
        'image_files' => "CREATE TABLE IF NOT EXISTS image_files (
            name VARCHAR(255) NOT NULL PRIMARY KEY,
            mime VARCHAR(50) NOT NULL,
            data LONGBLOB NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'sessions' => "CREATE TABLE IF NOT EXISTS sessions (
            id VARCHAR(128) NOT NULL PRIMARY KEY,
            data MEDIUMBLOB NOT NULL,
            last_activity INT UNSIGNED NOT NULL,
            INDEX idx_sessions_activity (last_activity)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];
    foreach ($tables as $name => $sql) {
        $conn->query($sql);
        $log[] = "جدول $name جاهز";
    }

    $categories = [
        ['شواطئ', 'الشواطئ والواجهات البحرية'],
        ['جبال', 'الجبال والطبيعة'],
        ['معالم', 'المعالم التاريخية والثقافية'],
        ['مطاعم', 'المطاعم والفنادق'],
    ];
    $catCount = (int)$conn->query('SELECT COUNT(*) FROM categories')->fetch_row()[0];
    if ($catCount === 0) {
        $stmt = $conn->prepare('INSERT INTO categories (name, description) VALUES (?, ?)');
        foreach ($categories as [$catName, $catDesc]) {
            $stmt->bind_param('ss', $catName, $catDesc);
            $stmt->execute();
        }
        $log[] = 'أُضيفت التصنيفات الأساسية';
    }

    $adminExists = (bool)$conn->query("SELECT 1 FROM users WHERE role = 'admin' LIMIT 1")->fetch_row();
    if (!$adminExists) {
        $hash = password_hash($adminPassword, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES ('admin', ?, 'admin')");
        $stmt->bind_param('s', $hash);
        $stmt->execute();
        $log[] = 'أُنشئ حساب المدير admin بكلمة المرور التي اخترتها';
    } else {
        $log[] = 'حساب المدير موجود مسبقًا ولم يتم تغييره';
    }

    $placeCount = (int)$conn->query('SELECT COUNT(*) FROM places')->fetch_row()[0];
    $natureId = $conn->query("SELECT id FROM categories WHERE name = 'جبال' LIMIT 1")->fetch_row();
    if ($placeCount === 0 && $natureId) {
        $name = 'مهرجان الزهور والحدائق بينبع';
        $location = 'ينبع الصناعية - حديقة المناسبات';
        $maps = 'https://www.google.com/maps/search/?api=1&query=' . urlencode('Yanbu Flowers and Gardens Festival Park, Yanbu Industrial City');
        $description = "مهرجان سنوي تنظمه الهيئة الملكية بينبع في حديقة المناسبات بمدينة ينبع الصناعية مع بداية فصل الربيع، ويُعد من أشهر فعاليات ينبع وأجملها. تتحول فيه الحديقة إلى لوحات ملونة من ملايين الزهور الموزعة على مساحات واسعة.\n\n"
            . "اشتهر المهرجان بسجادة الزهور التي دخلت موسوعة غينيس كأكبر سجادة زهور في العالم عام 2014، ثم استعادت اللقب عام 2017 بمساحة تتجاوز 16 ألف متر مربع.\n\n"
            . "يضم المهرجان ممرات للمشي ومناطق مخصصة للعائلات والأطفال وفعاليات ترفيهية وثقافية، ويُنصح بزيارته في فترة المساء حين تتزين الحديقة بالإضاءة ويعتدل الجو.\n\n"
            . "مصدر الصورة: Ahmed Abdulbasit عبر ويكيميديا كومنز (رخصة CC BY-SA 4.0).";
        $categoryId = (int)$natureId[0];
        $phone = '';
        $stmt = $conn->prepare('INSERT INTO places (name, description, category_id, location, google_maps_url, phone) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('ssisss', $name, $description, $categoryId, $location, $maps, $phone);
        $stmt->execute();
        $placeId = $stmt->insert_id;
        $image = 'place_6ad7128249b062.66658225.jpg';
        $img = $conn->prepare('INSERT INTO images (place_id, image_path, is_main) VALUES (?, ?, 1)');
        $img->bind_param('is', $placeId, $image);
        $img->execute();
        $log[] = 'أُضيفت وجهة مهرجان الزهور والحدائق بينبع مع صورتها';
    }

    return $log;
}
