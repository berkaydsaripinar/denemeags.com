<?php
// includes/db_connect.php
require_once __DIR__ . '/../config.php'; // Önce config.php'yi çağır (varsayılan ayarlar için)

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
    
    // Diğer ayarlar da buraya eklenebilir.

} catch (PDOException $e) {
    error_log("Sistem ayarları veritabanından çekilirken hata: " . $e->getMessage());
    // Hata durumunda varsayılan sabitleri kullan (config.php'den gelenler)
    if (!defined('NET_KATSAYISI')) {
        define('NET_KATSAYISI', $GLOBALS['site_ayarlari']['NET_KATSAYISI'] ?? 4);
    }
    if (!defined('PUAN_CARPANI')) {
        define('PUAN_CARPANI', $GLOBALS['site_ayarlari']['PUAN_CARPANI'] ?? 2);
    }
}
?>
