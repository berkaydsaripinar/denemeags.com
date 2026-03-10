<?php
// includes/db_connect.php
require_once __DIR__ . '/../config.php'; // Önce config.php'yi çağır (varsayılan ayarlar için)

if (!function_exists('is_truthy_setting')) {
    function is_truthy_setting($value) {
        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('should_bypass_maintenance_mode')) {
    function should_bypass_maintenance_mode() {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $path = '/' . ltrim((string) $path, '/');

        if (
            str_starts_with($path, '/admin') ||
            str_starts_with($path, '/yazar') ||
            $path === '/admin.php' ||
            $path === '/yazar.php'
        ) {
            return true;
        }

        return false;
    }
}

if (!function_exists('should_show_maintenance_page')) {
    function should_show_maintenance_page() {
        if (!defined('APP_ENV') || APP_ENV !== 'production') {
            return false;
        }

        $rawHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $hostWithoutPort = explode(':', $rawHost)[0];
        if (!in_array($hostWithoutPort, ['denemeags.com', 'www.denemeags.com'], true)) {
            return false;
        }

        return !should_bypass_maintenance_mode();
    }
}

if (!function_exists('render_maintenance_page')) {
    function render_maintenance_page() {
        http_response_code(503);
        header('Retry-After: 3600');
        include __DIR__ . '/../templates/maintenance_page.php';
        exit;
    }
}

$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (\PDOException $e) {
    error_log("Veritabanı bağlantı hatası: " . $e->getMessage());
    // Veritabanı bağlantısı olmadan ayarları çekemeyiz, varsayılanlar config.php'de kalmalı.
    // Bu durumda config.php'deki $default_settings kullanılacak.
    // Ancak sabitleri burada tanımlamamız gerekiyor.
    if (!defined('NET_KATSAYISI')) {
        define('NET_KATSAYISI', $GLOBALS['site_ayarlari']['NET_KATSAYISI'] ?? 4);
    }
    if (!defined('PUAN_CARPANI')) {
        define('PUAN_CARPANI', $GLOBALS['site_ayarlari']['PUAN_CARPANI'] ?? 2);
    }
    if (!defined('SITE_MAINTENANCE_MODE')) {
        define('SITE_MAINTENANCE_MODE', 0);
    }
    // Eğer kritik bir hataysa, siteyi durdurabiliriz.
    die("Site şu anda teknik bir sorun yaşamaktadır. Lütfen daha sonra tekrar deneyiniz. (DB Connect Error)");
}

// Veritabanı bağlantısı başarılıysa, sistem ayarlarını çek ve sabit olarak tanımla
try {
    $stmt_settings = $pdo->query("SELECT ayar_adi, ayar_degeri FROM sistem_ayarlari");
    $db_settings = $stmt_settings->fetchAll(PDO::FETCH_KEY_PAIR); // ayar_adi => ayar_degeri

    // NET_KATSAYISI
    if (isset($db_settings['NET_KATSAYISI']) && is_numeric($db_settings['NET_KATSAYISI'])) {
        if (!defined('NET_KATSAYISI')) define('NET_KATSAYISI', (float)$db_settings['NET_KATSAYISI']);
    } elseif (!defined('NET_KATSAYISI')) {
        define('NET_KATSAYISI', $GLOBALS['site_ayarlari']['NET_KATSAYISI'] ?? 4); // config.php'deki varsayılan
    }

    // PUAN_CARPANI
    if (isset($db_settings['PUAN_CARPANI']) && is_numeric($db_settings['PUAN_CARPANI'])) {
        if (!defined('PUAN_CARPANI')) define('PUAN_CARPANI', (float)$db_settings['PUAN_CARPANI']);
    } elseif (!defined('PUAN_CARPANI')) {
        define('PUAN_CARPANI', $GLOBALS['site_ayarlari']['PUAN_CARPANI'] ?? 2); // config.php'deki varsayılan
    }

    if (!defined('SITE_MAINTENANCE_MODE')) {
        define('SITE_MAINTENANCE_MODE', isset($db_settings['SITE_MAINTENANCE_MODE']) && is_truthy_setting($db_settings['SITE_MAINTENANCE_MODE']) ? 1 : 0);
    }

} catch (PDOException $e) {
    error_log("Sistem ayarları veritabanından çekilirken hata: " . $e->getMessage());
    // Hata durumunda varsayılan sabitleri kullan (config.php'den gelenler)
    if (!defined('NET_KATSAYISI')) {
        define('NET_KATSAYISI', $GLOBALS['site_ayarlari']['NET_KATSAYISI'] ?? 4);
    }
    if (!defined('PUAN_CARPANI')) {
        define('PUAN_CARPANI', $GLOBALS['site_ayarlari']['PUAN_CARPANI'] ?? 2);
    }
    if (!defined('SITE_MAINTENANCE_MODE')) {
        define('SITE_MAINTENANCE_MODE', 0);
    }
}

if (SITE_MAINTENANCE_MODE === 1 && should_show_maintenance_page()) {
    render_maintenance_page();
}
?>
