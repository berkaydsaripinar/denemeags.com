<?php
// logout.php
require_once __DIR__ . '/config.php'; // session_start() için
require_once __DIR__ . '/includes/functions.php'; // redirect() ve set_flash_message() için

// Oturumu sonlandır
$_SESSION = array(); // Tüm session değişkenlerini temizle

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

set_flash_message('info', 'Başarıyla çıkış yaptınız.');
redirect('index.php');
?>
