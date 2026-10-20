<?php
// إعداد الاتصال بقاعدة البيانات، بقيم افتراضية تناسب XAMPP محليًا (localhost / root / بدون كلمة مرور / yanbu_tourism).
// يمكن تغييرها اختياريًا عبر متغيرات البيئة: DATABASE_URL أو DB_HOST/DB_PORT/DB_USER/DB_PASSWORD/DB_NAME،
// وخيارات TLS لخادم بعيد: DB_SSL (true/false) و DB_SSL_CA (مسار شهادة الجذر).

function yanbu_env($key, $default = null) {
    $value = getenv($key);
    if ($value === false || $value === '') {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;
    }
    return ($value === null || $value === '') ? $default : $value;
}

function yanbu_db_config() {
    $config = [
        'host' => yanbu_env('DB_HOST', 'localhost'),
        'port' => (int)yanbu_env('DB_PORT', 3306),
        'user' => yanbu_env('DB_USER', 'root'),
        'pass' => yanbu_env('DB_PASSWORD', ''),
        'name' => yanbu_env('DB_NAME', 'yanbu_tourism'),
    ];

    $url = yanbu_env('DATABASE_URL');
    if ($url) {
        $parts = parse_url($url);
        if ($parts !== false && isset($parts['host'])) {
            $config['host'] = $parts['host'];
            $config['port'] = (int)($parts['port'] ?? 3306);
            $config['user'] = isset($parts['user']) ? rawurldecode($parts['user']) : $config['user'];
            $config['pass'] = isset($parts['pass']) ? rawurldecode($parts['pass']) : $config['pass'];
            $path = trim($parts['path'] ?? '', '/');
            if ($path !== '') $config['name'] = rawurldecode($path);
        }
    }

    $isLocal = in_array(strtolower($config['host']), ['localhost', '127.0.0.1', '::1'], true);
    $ssl = yanbu_env('DB_SSL');
    $config['ssl'] = $ssl === null ? !$isLocal : filter_var($ssl, FILTER_VALIDATE_BOOLEAN);
    $config['ca'] = $config['ssl'] ? yanbu_ssl_ca() : null;
    return $config;
}

// ملف شهادات الجذر المستخدم للتحقق من شهادة خادم قاعدة البيانات
function yanbu_ssl_ca() {
    $candidates = [
        yanbu_env('DB_SSL_CA'),
        '/etc/pki/tls/certs/ca-bundle.crt',
        '/etc/ssl/certs/ca-certificates.crt',
        '/etc/ssl/cert.pem',
        'C:/xampp/apache/bin/curl-ca-bundle.crt',
        'C:/xampp/php/extras/ssl/cacert.pem',
    ];
    if (function_exists('openssl_get_cert_locations')) {
        $locations = openssl_get_cert_locations();
        $candidates[] = $locations['default_cert_file'] ?? null;
    }
    foreach ($candidates as $file) {
        if ($file && is_file($file)) return $file;
    }
    return null;
}

// اتصال واحد مشترك لكل الطلب (تستخدمه الصفحات ومعالج الجلسات)
function yanbu_db() {
    static $conn = null;
    if ($conn instanceof mysqli) return $conn;

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $config = yanbu_db_config();
    $link = mysqli_init();
    $link->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);
    $flags = 0;
    if ($config['ssl']) {
        $link->ssl_set(null, null, $config['ca'], null, null);
        $flags = MYSQLI_CLIENT_SSL;
    }
    $link->real_connect($config['host'], $config['user'], $config['pass'], $config['name'], $config['port'], null, $flags);
    $link->set_charset('utf8mb4');
    $conn = $link;
    return $conn;
}

function yanbu_render_unavailable($detail = '') {
    if (!headers_sent()) {
        http_response_code(503);
        header('Content-Type: text/html; charset=UTF-8');
        header('Retry-After: 60');
    }
    $extra = $detail !== '' ? '<p style="color:#62738a;font-size:14px;direction:ltr;">' . htmlspecialchars($detail) . '</p>' : '';
    echo '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>الموقع غير متاح مؤقتًا</title></head>'
        . '<body style="font-family:Tahoma,sans-serif;background:#f3f6fa;color:#0f2340;display:grid;place-items:center;min-height:100vh;margin:0;padding:20px;">'
        . '<main style="background:#fff;border:1px solid #e1e8f0;border-radius:14px;padding:32px;max-width:520px;text-align:center;">'
        . '<h1 style="font-size:22px;">تعذّر الاتصال بقاعدة البيانات</h1>'
        . '<p>الموقع غير متاح مؤقتًا. إذا كنت مدير الموقع، تأكد من ضبط متغيرات قاعدة البيانات ثم أعد المحاولة.</p>'
        . $extra . '</main></body></html>';
    exit();
}
