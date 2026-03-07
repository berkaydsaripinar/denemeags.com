<?php
/**
 * forgot_password.php - Şifre Sıfırlama Talebi Sayfası
 */

// 1. Hata Raporlama
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. Dosyaları Dahil Et
$baseDir = __DIR__;
require_once $baseDir . '/config.php';
require_once $baseDir . '/includes/db_connect.php';
require_once $baseDir . '/includes/functions.php'; // send_smtp_email fonksiyonu buradan geliyor

$message = "";
$messageType = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email']);
    
    global $pdo; // db_connect.php'den gelen bağlantı nesnesi
    
    // Kullanıcıyı veritabanında ara
    $stmt = $pdo->prepare("SELECT id, ad_soyad FROM kullanicilar WHERE email = ? AND aktif_mi = 1 LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // Güvenli token ve son kullanma tarihi (1 saat) oluştur
        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Token'ı kullanıcı tablosuna kaydet
        $update = $pdo->prepare("UPDATE kullanicilar SET reset_token = ?, reset_expiry = ? WHERE id = ?");
        $update->execute([$token, $expiry, $user['id']]);

        /**
         * Linki Oluştur
         * config.php içinde BASE_URL tanımlı olduğu için onu kullanıyoruz.
         */
        $baseUrl = defined('BASE_URL') ? BASE_URL : (defined('SITE_URL') ? SITE_URL : '');
        $cleanBaseUrl = rtrim($baseUrl, '/') . '/';
        $resetLink = $cleanBaseUrl . "reset_password.php?token=" . $token;

        // E-posta İçeriği (HTML formatında gönderilecek)
        $subject = 'Şifre Sıfırlama Talebi - Deneme AGS';
        $mailBody = "
            <h3>Şifre Sıfırlama Talebi</h3>
            Merhaba <b>" . htmlspecialchars($user['ad_soyad']) . "</b>,<br><br>
            Hesabınız için şifre sıfırlama talebinde bulundunuz. Yeni şifrenizi belirlemek için aşağıdaki bağlantıya tıklayınız:<br><br>
            <a href='{$resetLink}' style='background: #ef4444; color: #ffffff; padding: 12px 25px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block;'>Şifremi Yenile</a><br><br>
            Eğer buton çalışmıyorsa bu linki tarayıcınıza yapıştırın:<br>{$resetLink}<br><br>
            Bu link 1 saat süreyle geçerlidir. Eğer bu talebi siz yapmadıysanız bu e-postayı dikkate almayınız.";

        // includes/functions.php içindeki hazır SMTP fonksiyonunu çağırıyoruz
        // Bu fonksiyonun içinde zaten gmail uygulama şifreniz tanımlı.
        if (send_smtp_email($email, $subject, $mailBody)) {
            $message = "Şifre sıfırlama linki e-posta adresinize gönderildi. Lütfen gelen kutunuzu kontrol edin.";
            $messageType = "success";
        } else {
            $message = "E-posta gönderilirken teknik bir hata oluştu. Lütfen functions.php içindeki SMTP ayarlarını kontrol edin.";
            $messageType = "danger";
        }
    } else {
        $message = "Bu e-posta adresi ile kayıtlı aktif bir hesap bulunamadı.";
        $messageType = "danger";
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Şifremi Unuttum - Deneme AGS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f7f6; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .auth-card { max-width: 400px; margin: 80px auto; border: none; border-radius: 15px; }
        .btn-primary { border-radius: 8px; padding: 10px; background-color: #1e293b; border: none; }
        .btn-primary:hover { background-color: #334155; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card shadow auth-card">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <h4 class="fw-bold text-primary">Şifremi Unuttum</h4>
                    <p class="text-muted small">E-posta adresinizi girerek şifrenizi sıfırlayabilirsiniz.</p>
                </div>

                <?php if($message): ?>
                    <div class="alert alert-<?=$messageType?> alert-dismissible fade show" role="alert">
                        <?=$message?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">E-Posta Adresiniz</label>
                        <input type="email" name="email" class="form-control" placeholder="mail@example.com" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 fw-bold text-white">Sıfırlama Bağlantısı Gönder</button>
                </form>

                <div class="mt-4 text-center">
                    <a href="login.php" class="text-decoration-none small text-muted">← Giriş Sayfasına Dön</a>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>