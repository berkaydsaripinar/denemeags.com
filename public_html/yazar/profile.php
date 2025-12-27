<?php
// yazar/profile.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['yazar_id'])) {
    header("Location: login.php");
    exit;
}

$yazar_id = $_SESSION['yazar_id'];
$message = '';

// Bilgi Çekme
$stmt = $pdo->prepare("SELECT * FROM yazarlar WHERE id = ?");
$stmt->execute([$yazar_id]);
$yazar = $stmt->fetch();

// Güncelleme İşlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ad_soyad = trim($_POST['ad_soyad']);
    $iban = trim($_POST['iban_bilgisi']);
    $bio = trim($_POST['biyografi']);
    $sifre = $_POST['sifre'];

    try {
        $pdo->beginTransaction();
        
        $stmt_upd = $pdo->prepare("UPDATE yazarlar SET ad_soyad = ?, iban_bilgisi = ?, biyografi = ? WHERE id = ?");
        $stmt_upd->execute([$ad_soyad, $iban, $bio, $yazar_id]);

        if (!empty($sifre)) {
            $hash = password_hash($sifre, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE yazarlar SET sifre_hash = ? WHERE id = ?")->execute([$hash, $yazar_id]);
        }

        $pdo->commit();
        $_SESSION['yazar_name'] = $ad_soyad;
        $message = '<div class="alert alert-success">Bilgileriniz başarıyla güncellendi.</div>';
        
        // Veriyi tazele
        $stmt->execute([$yazar_id]);
        $yazar = $stmt->fetch();
        
    } catch (Exception $e) { $pdo->rollBack(); $message = '<div class="alert alert-danger">Bir hata oluştu.</div>'; }
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Profilim | DenemeAGS Yazar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #1F3C88; --accent: #F57C00; }
        body { background: #f8f9fa; font-family: 'Open Sans', sans-serif; }
        .sidebar { background: var(--primary); height: 100vh; color: white; position: fixed; width: 240px; }
        .content { margin-left: 240px; padding: 40px; }
        .nav-link { color: rgba(255,255,255,0.7); padding: 12px 25px; transition: 0.3s; }
        .nav-link:hover, .nav-link.active { color: white; background: rgba(255,255,255,0.1); border-left: 4px solid var(--accent); }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="p-4 text-center border-bottom border-white border-opacity-10 mb-3">
            <h4 class="fw-bold mb-0">DENEME<span class="text-warning">AGS</span></h4>
            <small class="opacity-50 text-uppercase">Yazar Paneli</small>
        </div>
        <nav class="nav flex-column">
            <a class="nav-link" href="dashboard.php"><i class="fas fa-chart-line me-2"></i> Genel Bakış</a>
            <a class="nav-link" href="manage_products.php"><i class="fas fa-book me-2"></i> Yayınlarım</a>
            <a class="nav-link" href="earnings.php"><i class="fas fa-wallet me-2"></i> Ödemeler</a>
            <a class="nav-link active" href="profile.php"><i class="fas fa-user-circle me-2"></i> Profilim</a>
            <a class="nav-link text-danger mt-5" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Çıkış Yap</a>
        </nav>
    </div>

    <div class="content">
        <h3 class="fw-bold mb-5">Profil Ayarları</h3>
        <?php echo $message; ?>

        <div class="row">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label small fw-bold">AD SOYAD</label>
                                <input type="text" name="ad_soyad" class="form-control" value="<?php echo escape_html($yazar['ad_soyad']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">E-POSTA (Değiştirilemez)</label>
                                <input type="email" class="form-control bg-light" value="<?php echo escape_html($yazar['email']); ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">IBAN BİLGİSİ (Ödemeler İçin)</label>
                                <input type="text" name="iban_bilgisi" class="form-control" value="<?php echo escape_html($yazar['iban_bilgisi']); ?>" placeholder="TR00...">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">KISA BİYOGRAFİ</label>
                                <textarea name="biyografi" class="form-control" rows="4"><?php echo escape_html($yazar['biyografi']); ?></textarea>
                            </div>
                            <hr class="my-4">
                            <div class="mb-4">
                                <label class="form-label small fw-bold text-danger">ŞİFREYİ DEĞİŞTİR (Opsiyonel)</label>
                                <input type="password" name="sifre" class="form-control" placeholder="Değiştirmek istemiyorsanız boş bırakın">
                            </div>
                            <button type="submit" class="btn btn-primary px-5 py-2 fw-bold" style="background: var(--primary);">DEĞİŞİKLİKLERİ KAYDET</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 mt-4 mt-lg-0">
                <div class="bg-white p-4 rounded-4 shadow-sm">
                    <h5 class="fw-bold mb-3">Hesap Özeti</h5>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small">Komisyon Oranı:</span>
                        <span class="fw-bold text-success">%<?php echo $yazar['komisyon_orani']; ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small">Kayıt Tarihi:</span>
                        <span class="fw-bold"><?php echo date('d.m.Y', strtotime($yazar['kayit_tarihi'])); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>