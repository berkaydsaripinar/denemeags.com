<?php
/**
 * reset_password.php - Yeni Şifre Belirleme Sayfası
 */

// 1. Hata Raporlama
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. Dosyaları Dahil Et
$baseDir = __DIR__;
require_once $baseDir . '/config.php';
require_once $baseDir . '/includes/db_connect.php';
require_once $baseDir . '/includes/functions.php';

$message = "";
$messageType = "";
$token = $_GET['token'] ?? '';
$isValid = false;

global $pdo; // db_connect.php'den gelen bağlantı nesnesi

// 3. Token Geçerliliğini Kontrol Et
if ($token) {
    $stmt = $pdo->prepare("SELECT id, ad_soyad FROM kullanicilar WHERE reset_token = ? AND reset_expiry > NOW() AND aktif_mi = 1 LIMIT 1");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if ($user) {
        $isValid = true;
    } else {
        $message = "Geçersiz veya süresi dolmuş sıfırlama bağlantısı. Lütfen tekrar talep oluşturun.";
        $messageType = "danger";
    }
} else {
    // Token yoksa giriş sayfasına yönlendir
    header("Location: login.php");
    exit;
}

// 4. Yeni Şifre Kaydetme İşlemi
if ($isValid && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['password'])) {
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];

    if (strlen($password) < 6) {
        $message = "Şifre güvenlik için en az 6 karakter olmalıdır.";
        $messageType = "warning";
    } elseif ($password !== $password_confirm) {
        $message = "Girdiğiniz şifreler birbiriyle eşleşmiyor.";
        $messageType = "warning";
    } else {
        // Yeni şifreyi hashle
        $newHash = password_hash($password, PASSWORD_BCRYPT);
        
        // Şifreyi güncelle ve token bilgilerini temizle
        $update = $pdo->prepare("UPDATE kullanicilar SET sifre_hash = ?, reset_token = NULL, reset_expiry = NULL WHERE id = ?");
        
        if ($update->execute([$newHash, $user['id']])) {
            $message = "Şifreniz başarıyla güncellendi! Artık yeni şifrenizle giriş yapabilirsiniz.";
            $messageType = "success";
            $isValid = false; // Formu gizlemek için
        } else {
            $message = "Sistem hatası: Şifre güncellenirken bir sorun oluştu.";
            $messageType = "danger";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yeni Şifre Belirle - Deneme AGS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f7f6; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .auth-card { max-width: 400px; margin: 80px auto; border: none; border-radius: 15px; }
        .btn-success { border-radius: 8px; padding: 10px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card shadow auth-card">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <h4 class="fw-bold text-success">Yeni Şifre Belirle</h4>
                    <?php if($isValid): ?>
                        <p class="text-muted small">Sayın <b><?= htmlspecialchars($user['ad_soyad']) ?></b>, lütfen yeni şifrenizi giriniz.</p>
                    <?php endif; ?>
                </div>

                <?php if($message): ?>
                    <div class="alert alert-<?=$messageType?> alert-dismissible fade show" role="alert">
                        <?=$message?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if($isValid): ?>
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Yeni Şifre</label>
                            <input type="password" name="password" class="form-control" placeholder="******" required minlength="6">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Yeni Şifre (Tekrar)</label>
                            <input type="password" name="password_confirm" class="form-control" placeholder="******" required minlength="6">
                        </div>
                        <button type="submit" class="btn btn-success w-100">Şifreyi Güncelle</button>
                    </form>
                <?php else: ?>
                    <div class="mt-4 text-center">
                        <a href="login.php" class="btn btn-primary w-100 fw-bold">Giriş Sayfasına Git</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>