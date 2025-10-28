<?php
// login.php - Bu dosya index.php'den gelen giriş form isteklerini işler
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php'); // Sadece POST isteklerini kabul et
}

if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
    set_flash_message('error', 'Geçersiz istek. Lütfen formu tekrar gönderin.');
    redirect('index.php');
}

$action = $_POST['action'] ?? '';

if ($action === 'login') {
    // Kullanıcı e-posta ve şifre ile giriş yapmaya çalışıyor
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        set_flash_message('error', 'E-posta ve şifre boş bırakılamaz.');
        if (!empty($email)) $_SESSION['login_attempt_email'] = $email; // E-postayı formda tut
        redirect('index.php');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        set_flash_message('error', 'Geçersiz e-posta formatı.');
        $_SESSION['login_attempt_email'] = $email; // E-postayı formda tut
        redirect('index.php');
    }
    
    try {
        $stmt = $pdo->prepare("SELECT id, ad_soyad, email, sifre_hash FROM kullanicilar WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['sifre_hash'])) {
            // Giriş başarılı
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_ad_soyad'] = $user['ad_soyad'];
            $_SESSION['user_email'] = $user['email']; // E-postayı session'a ekle
            
            // Eski deneme kodu session değişkenini temizle (artık kullanılmıyor)
            if(isset($_SESSION['user_deneme_kodu'])) unset($_SESSION['user_deneme_kodu']);
            if(isset($_SESSION['login_attempt_email'])) unset($_SESSION['login_attempt_email']);


            set_flash_message('success', 'Giriş başarılı! Hoş geldiniz, ' . escape_html($user['ad_soyad']) . '.');
            redirect('dashboard.php');
        } else {
            set_flash_message('error', 'Geçersiz e-posta veya şifre.');
            $_SESSION['login_attempt_email'] = $email; // E-postayı formda tut
            redirect('index.php');
        }

    } catch (PDOException $e) {
        error_log("Giriş hatası: " . $e->getMessage());
        set_flash_message('error', 'Giriş sırasında bir veritabanı hatası oluştu.');
        $_SESSION['login_attempt_email'] = $email; // E-postayı formda tut
        redirect('index.php');
    }

} else {
    // Eski 'check_code' action'ı artık burada olmayacak.
    // Eğer başka action'lar eklenirse burası genişletilebilir.
    set_flash_message('error', 'Geçersiz işlem türü.');
    redirect('index.php');
}
?>
