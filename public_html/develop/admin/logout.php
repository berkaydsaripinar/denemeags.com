<?php
// admin/logout.php
require_once __DIR__ . '/../config.php'; // session_start() için
require_once __DIR__ . '/../includes/functions.php'; // set_flash_message
require_once __DIR__ . '/../includes/admin_functions.php'; // Admin fonksiyonları

// Admin oturumunu sonlandır
unset($_SESSION['admin_logged_in']);
unset($_SESSION['admin_username']);
// İsteğe bağlı: Admin'e özel diğer session değişkenlerini de temizle

// Genel session'ı tamamen yok etmek yerine sadece admin ile ilgili kısımları temizlemek daha iyi olabilir
// Eğer admin ve kullanıcı session'ları tamamen ayrıysa session_destroy() kullanılabilir.
// session_destroy(); // Eğer kullanıcı ve admin session'ları karışmıyorsa

set_flash_message('info', 'Admin panelinden başarıyla çıkış yaptınız.'); // Bu mesaj index.php'de gösterilecek
header("Location: index.php");
exit;
?>
