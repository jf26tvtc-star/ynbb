<?php
// تصدير محتوى الموقع من قاعدة البيانات إلى data/site.json ليقرأه الموقع الثابت المنشور على GitHub Pages.
// يُستدعى من زر "نشر الموقع" في لوحة التحكم، ومن tools/export.php (سطر الأوامر / publish.bat).

function yanbu_export_site(mysqli $conn, $root) {
    $warnings = [];
    $imagesDir = $root . '/images/';

    $imagesByPlace = [];
    $res = $conn->query('SELECT place_id, image_path FROM images ORDER BY place_id, is_main DESC, id ASC');
    while ($row = $res->fetch_assoc()) {
        $path = basename($row['image_path']);
        if ($path === '') continue;
        if (!is_file($imagesDir . $path)) {
            $warnings[] = 'الصورة غير موجودة في مجلد images: ' . $path;
            continue;
        }
        $imagesByPlace[(int)$row['place_id']][] = $path;
    }

    $categories = [];
    $res = $conn->query("SELECT c.id, c.name, c.description,
        (SELECT COUNT(*) FROM places p WHERE p.category_id = c.id) AS places_count
        FROM categories c ORDER BY c.id");
    while ($c = $res->fetch_assoc()) {
        $categories[(int)$c['id']] = [
            'id' => (int)$c['id'],
            'name' => $c['name'],
            'description' => (string)$c['description'],
            'type' => category_type($c['name']),
            'slug' => category_slug($c['name']),
            'icon' => category_icon($c['name']),
            'places_count' => (int)$c['places_count'],
            'cover' => '',
        ];
    }

    $places = [];
    $res = $conn->query('SELECT p.*, c.name AS category FROM places p JOIN categories c ON c.id = p.category_id ORDER BY p.created_at DESC, p.id DESC');
    while ($p = $res->fetch_assoc()) {
        $id = (int)$p['id'];
        $images = $imagesByPlace[$id] ?? [];
        $categoryId = (int)$p['category_id'];
        if (isset($categories[$categoryId]) && $categories[$categoryId]['cover'] === '' && $images) {
            $categories[$categoryId]['cover'] = $images[0];
        }
        $places[] = [
            'id' => $id,
            'name' => $p['name'],
            'description' => $p['description'],
            'category_id' => $categoryId,
            'category' => $p['category'],
            'type' => category_type($p['category']),
            'slug' => category_slug($p['category']),
            'icon' => category_icon($p['category']),
            'location' => $p['location'],
            'phone' => (string)($p['phone'] ?? ''),
            'maps_embed' => maps_embed_url($p['name'], $p['location']),
            'maps_url' => maps_open_url($p['google_maps_url'] ?? '', $p['name'], $p['location']),
            'created_at' => date('Y-m-d', strtotime($p['created_at'])),
            'images' => $images,
        ];
    }

    // صورة الهيرو في الرئيسية: أحدث صورة مضافة (نفس منطق index.php)
    $hero = '';
    $res = $conn->query('SELECT image_path FROM images ORDER BY id DESC');
    while ($row = $res->fetch_assoc()) {
        if (is_file($imagesDir . basename($row['image_path']))) {
            $hero = basename($row['image_path']);
            break;
        }
    }

    $imageCount = 0;
    foreach ($imagesByPlace as $list) $imageCount += count($list);

    $data = [
        'generated_at' => date('c'),
        'hero_image' => $hero,
        'stats' => ['places' => count($places), 'categories' => count($categories), 'images' => $imageCount],
        'categories' => array_values($categories),
        'places' => $places,
    ];

    $dataDir = $root . '/data';
    $target = $dataDir . '/site.json';
    $result = ['places' => count($places), 'categories' => count($categories), 'images' => $imageCount, 'warnings' => $warnings];

    // لا نعيد كتابة الملف إذا لم يتغير المحتوى، حتى لا يُنشئ كل نشر تحديثًا فارغًا على GitHub
    $previous = is_file($target) ? json_decode((string)file_get_contents($target), true) : null;
    if (is_array($previous)) {
        unset($previous['generated_at']);
        $current = $data;
        unset($current['generated_at']);
        if ($previous == $current) {
            yanbu_stamp_asset_versions($root);
            return $result + ['changed' => false];
        }
    }

    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    if (!is_dir($dataDir) && !mkdir($dataDir, 0775, true)) {
        throw new RuntimeException('تعذّر إنشاء مجلد data');
    }
    $tmp = $target . '.tmp';
    if (file_put_contents($tmp, $json . "\n") === false || !rename($tmp, $target)) {
        @unlink($tmp);
        throw new RuntimeException('تعذّر حفظ الملف data/site.json');
    }

    yanbu_stamp_asset_versions($root);

    return $result + ['changed' => true];
}

// تحديث رقم النسخة (?v=) لملفات CSS وJS داخل صفحات HTML الثابتة، حتى يحمّل المتصفح أحدث نسخة بعد كل نشر
function yanbu_stamp_asset_versions($root) {
    $assets = [];
    foreach (['css/style.css', 'js/site.js'] as $asset) {
        $hash = @md5_file($root . '/' . $asset);
        if ($hash) $assets[$asset] = substr($hash, 0, 8);
    }
    foreach (glob($root . '/*.html') as $page) {
        $html = file_get_contents($page);
        $updated = preg_replace_callback('#(css/style\.css|js/site\.js)\?v=[A-Za-z0-9]*#', function ($m) use ($assets) {
            return isset($assets[$m[1]]) ? $m[1] . '?v=' . $assets[$m[1]] : $m[0];
        }, $html);
        if ($updated !== null && $updated !== $html) {
            file_put_contents($page, $updated);
        }
    }
}
