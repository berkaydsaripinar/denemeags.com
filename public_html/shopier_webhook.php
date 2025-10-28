<?php
// shopier_webhook.php
// Shopier'dan gelen başarılı satış bildirimlerini işler ve kullanıcıya PHPMailer ile e-posta gönderir.

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Hata Yönetimi
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');

// Gerekli Dosyalar
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/vendor/autoload.php'; // PHPMailer için

// === SHOPIER AYARLARINIZI BURAYA GİRİN ===
$shopier_api_key = '37c91eb405dad6a700412d211c1b9f08';
$shopier_api_secret = '52062638586c0b6e99c05aeedefe8923';
// === E-POSTA SMTP AYARLARINIZI BURAYA GİRİN ===
$smtp_host = 'smtp.hostinger.com'; // Hosting sağlayıcınızın SMTP adresi
$smtp_username = 'admin@denemeags.com'; // E-posta adresiniz
$smtp_password = 'Bds242662!'; // E-posta şifreniz
$smtp_port = 587; 
$smtp_secure = PHPMailer::ENCRYPTION_STARTTLS;

// === Güvenlik Kontrolü ve Veri Alma ===
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); exit('Invalid request method.');
}
// Shopier imza doğrulaması burada yapılmalı (canlı sistem için kritik)
$shopier_product_id = $_POST['product_id'] ?? null;
$customer_email = $_POST['customer_email'] ?? null;
$customer_name = $_POST['customer_name'] ?? 'Yeni Kullanıcı';
$payment_status = $_POST['status'] ?? null;
if ($payment_status !== 'success' || empty($shopier_product_id) || empty($customer_email)) {
    http_response_code(400); exit('Eksik veri veya başarısız ödeme.');
}

try {
    // 1. Satın alınan ürüne karşılık gelen denemeyi ve SORU KİTAPÇIĞI dosyasını bul
    $stmt_deneme = $pdo->prepare("SELECT id, deneme_adi, soru_kitapcik_dosyasi FROM denemeler WHERE shopier_product_id = ? AND aktif_mi = 1");
    $stmt_deneme->execute([$shopier_product_id]);
    $deneme = $stmt_deneme->fetch();

    if (!$deneme || empty($deneme['soru_kitapcik_dosyasi'])) {
        throw new Exception("Shopier ürün ID'si '$shopier_product_id' ile eşleşen deneme veya soru kitapçığı bulunamadı.");
    }
    $deneme_id = $deneme['id'];

    // 2. Müşteri e-postası ile kullanıcıyı bul veya oluştur
    $stmt_user = $pdo->prepare("SELECT id FROM kullanicilar WHERE email = ?");
    $stmt_user->execute([$customer_email]);
    $user = $stmt_user->fetch();
    $yeni_kullanici = false; $user_password_plain = '';
    if ($user) { $kullanici_id = $user['id']; } 
    else {
        $yeni_kullanici = true;
        $user_password_plain = substr(str_shuffle("abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789"), 0, 10);
        $user_password_hash = password_hash($user_password_plain, PASSWORD_DEFAULT);
        $stmt_create_user = $pdo->prepare("INSERT INTO kullanicilar (ad_soyad, email, sifre_hash, aktif_mi) VALUES (?, ?, ?, 1)");
        $stmt_create_user->execute([$customer_name, $customer_email, $user_password_hash]);
        $kullanici_id = $pdo->lastInsertId();
    }
    
    // 3. Kullanıcının bu denemeye zaten sahip olup olmadığını kontrol et
    $stmt_check_access = $pdo->prepare("SELECT id FROM kullanici_katilimlari WHERE kullanici_id = ? AND deneme_id = ?");
    $stmt_check_access->execute([$kullanici_id, $deneme_id]);
    if ($stmt_check_access->fetch()) {
        throw new Exception("Kullanıcı ID $kullanici_id zaten Deneme ID $deneme_id için erişime sahip.");
    }

    // 4. Deneme için kullanılmamış bir erişim kodu bul ve kullanıcıya ata
    $pdo->beginTransaction();
    $stmt_find_code = $pdo->prepare("SELECT id, kod FROM deneme_erisim_kodlari WHERE deneme_id = ? AND kullanici_id IS NULL LIMIT 1 FOR UPDATE");
    $stmt_find_code->execute([$deneme_id]);
    $erisim_kodu_data = $stmt_find_code->fetch();
    if (!$erisim_kodu_data) { $pdo->rollBack(); throw new Exception("KRİTİK HATA: Deneme ID $deneme_id için kullanılabilir erişim kodu kalmadı!"); }
    $erisim_kodu_id = $erisim_kodu_data['id']; $erisim_kodu = $erisim_kodu_data['kod'];
    $stmt_assign_code = $pdo->prepare("UPDATE deneme_erisim_kodlari SET kullanici_id = ?, kullanilma_tarihi = NOW() WHERE id = ?");
    $stmt_assign_code->execute([$kullanici_id, $erisim_kodu_id]);
    $stmt_grant_access = $pdo->prepare("INSERT INTO kullanici_katilimlari (kullanici_id, deneme_id, erisim_kodu_id) VALUES (?, ?, ?)");
    $stmt_grant_access->execute([$kullanici_id, $deneme_id, $erisim_kodu_id]);
    $pdo->commit();

    // 5. Müşteriye e-posta gönder
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $smtp_host;
    $mail->SMTPAuth = true;
    $mail->Username = $smtp_username;
    $mail->Password = $smtp_password;
    $mail->SMTPSecure = $smtp_secure;
    $mail->Port = $smtp_port;
    $mail->CharSet = 'UTF-8';
    $mail->setFrom($smtp_username, SITE_NAME . ' Ekibi');
    $mail->addAddress($customer_email, $customer_name);
    $mail->isHTML(true);
    $email_subject = "Deneme Sınavı Erişim Bilgileriniz (" . $deneme['deneme_adi'] . ")";
    $soru_kitapcik_url = rtrim(BASE_URL, '/') . '/uploads/questions/' . $deneme['soru_kitapcik_dosyasi'];
    
    // E-posta için HTML içeriği
    $email_body = "<html><body>";
    $email_body .= "<h2>Merhaba " . htmlspecialchars($customer_name) . ",</h2>";
    $email_body .= "<p>'" . htmlspecialchars($deneme['deneme_adi']) . "' için satın alımınız tamamlanmıştır. Teşekkür ederiz.</p>";
    $email_body .= "<p><b>Soru kitapçığınıza</b> aşağıdaki linkten ulaşabilirsiniz:</p>";
    $email_body .= "<p><a href='" . $soru_kitapcik_url . "' style='padding: 10px 15px; background-color:#28a745; color:white; text-decoration:none; border-radius:5px;'>Soru Kitapçığını İndir</a></p><hr>";
    $email_body .= "<h3>Platform Giriş Bilgileriniz</h3>";
    if ($yeni_kullanici) {
        $email_body .= "<p><b>E-posta Adresiniz:</b> " . $customer_email . "</p>";
        $email_body .= "<p><b>Geçici Şifreniz:</b> " . $user_password_plain . "</p>";
    } else {
        $email_body .= "<p><b>E-posta Adresiniz:</b> " . $customer_email . "</p><p><small>(Mevcut şifrenizle giriş yapabilirsiniz.)</small></p>";
    }
    $email_body .= "<p>Denemeyi online optik forma işlemek için giriş yaptıktan sonra kullanıcı panelinizde ilgili denemenin altına aşağıdaki erişim kodunu girmeniz gerekmektedir:</p>";
    $email_body .= "<p><b>Deneme Erişim Kodunuz:</b> <span style='font-size: 1.2em; font-weight: bold; color: #dc3545;'>" . $erisim_kodu . "</span></p>";
    $email_body .= "<p><a href='" . rtrim(BASE_URL, '/') . "/index.php' style='padding:10px 15px; background-color:#0d6efd; color:white; text-decoration:none; border-radius:5px;'>Platforma Giriş Yapmak İçin Tıklayın</a></p>";
    $email_body .= "<br><p>İyi çalışmalar dileriz,<br>" . SITE_NAME . " Ekibi</p>";
    $email_body .= "</body></html>";
    
    $mail->Subject = $email_subject;
    $mail->Body    = $email_body;
    $mail->AltBody = strip_tags($email_body); 
    $mail->send();
    
    http_response_code(200);
    echo "OK";

} catch (Exception $e) {
    error_log("Shopier Webhook Hatası: " . $e->getMessage() . " | Sipariş ID: " . ($shopier_order_id ?? 'N/A'));
    http_response_code(500); 
    echo "Error processing request.";
}
?>
