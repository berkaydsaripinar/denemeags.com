<?php
// config.php
// Veritabanı Bağlantı Bilgileri
define('DB_HOST', 'localhost'); 
define('DB_NAME', 'u120099295_deneme');
define('DB_USER', 'u120099295_deneme');
define('DB_PASS', 'Bds242662!');
define('DB_CHARSET', 'utf8mb4');

// Site Ayarları
define('SITE_NAME', 'DenemeAGS-Hibrit Yayın');
define('BASE_URL', 'https://denemeags.com'); 

// Shopier Ayarları (ENV üzerinden okunur)
define('SHOPIER_API_KEY', getenv('SHOPIER_API_KEY') ?: '');
define('SHOPIER_API_SECRET', getenv('SHOPIER_API_SECRET') ?: '');
define('SHOPIER_WEBSITE_INDEX', getenv('SHOPIER_WEBSITE_INDEX') ?: '1');    
define('VIDEO_STREAM_SECRET', getenv('VIDEO_STREAM_SECRET') ?: 'change-this-secret');
// Saat Dilimi
date_default_timezone_set('Europe/Istanbul');

// Oturum (Session) Ayarları
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Hata Raporlama (Geliştirme aşamasında E_ALL, canlıda 0 veya E_ERROR)
error_reporting(E_ALL);
ini_set('display_errors', 1); 

// --- Sistem Ayarlarını Veritabanından Çekme veya Varsayılanları Kullanma ---
try {
    // Bu aşamada $pdo nesnesi henüz tanımlı olmayabilir, bu yüzden geçici bir bağlantı kuracağız.
    // Eğer db_connect.php bu dosyadan önce include ediliyorsa ve $pdo global ise,
    // bu geçici bağlantıya gerek kalmaz. Ancak config genellikle ilk yüklenenlerden olduğu için
    // burada kendi bağlantısını kurması daha güvenli olabilir.
    // VEYA bu ayar çekme işlemini db_connect.php sonrasına taşıyabiliriz.
    // Şimdilik, ayarları db_connect.php içinde tanımlayacağız.

    // Varsayılan değerler (veritabanından çekilemezse bunlar kullanılacak)
    $default_settings = [
        'NET_KATSAYISI' => 4,
        'PUAN_CARPANI' => 2
    ];

    // db_connect.php içinde $pdo oluşturulduktan sonra bu ayarlar yüklenecek.
    // Bu yüzden sabitleri burada tanımlamıyoruz, db_connect.php'den sonra tanımlanacaklar.
    // Bu dosya sadece varsayılanları tutacak.
    global $site_ayarlari; // Ayarları global bir değişkende tutabiliriz.
    $site_ayarlari = $default_settings;


} catch (PDOException $e) {
    // Veritabanı bağlantı hatası durumunda varsayılanları kullan
    error_log("Config.php - Sistem ayarları çekilirken veritabanı hatası: " . $e->getMessage());
    // Sabitler zaten yukarıda $default_settings ile belirlendi, burada tekrar define etmeye gerek yok
    // eğer db_connect.php'den sonra define edileceklerse.
}

// NET_KATSAYISI ve PUAN_CARPANI sabitleri artık db_connect.php veya 
// ayarları yükleyen bir bootstrap dosyasında tanımlanacak.
// Şimdilik, eski sabit tanımlamalarını yorum satırı yapalım veya kaldıralım.
// define('NET_KATSAYISI', 4); 
// define('PUAN_CARPANI', 2); 

// Fonksiyonlar dosyasını dahil et (eğer burada gerekiyorsa)
// require_once __DIR__ . '/includes/functions.php'; // Genellikle diğer dosyalarda include edilir.

?>
