<?php
/**
 * yazar/register.php - Yayıncı Başvuru ve Kayıt Formu
 */

require_once '../config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

$message = "";
$messageType = "";
$selectedPaket = $_GET['paket'] ?? 'otomatik';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
    $ad_soyad = trim($_POST['ad_soyad']);
    $email = trim($_POST['email']);
    $telefon = telefon_formatla($_POST['telefon']);
    $sifre = $_POST['sifre'];
    $sifre_tekrar = $_POST['sifre_tekrar'];
    $paket_turu = $_POST['paket_turu'];
    $iban = trim($_POST['iban_bilgisi']);

    global $pdo;

    // Temel Kontroller
    $stmt = $pdo->prepare("SELECT id FROM yazarlar WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->fetch()) {
        $message = "Bu e-posta adresi zaten bir yazar kaydına sahip.";
        $messageType = "danger";
    } elseif ($sifre !== $sifre_tekrar) {
        $message = "Girdiğiniz şifreler uyuşmuyor.";
        $messageType = "warning";
    } elseif (strlen($sifre) < 6) {
        $message = "Şifreniz en az 6 karakterden oluşmalıdır.";
        $messageType = "warning";
    } else {
        $sifre_hash = password_hash($sifre, PASSWORD_BCRYPT);
        // Paket bazlı varsayılan komisyon oranları (Yazara kalan net oran)
        $komisyon = ($paket_turu == 'otomatik') ? 70.00 : 85.00;

        $insert = $pdo->prepare("INSERT INTO yazarlar (ad_soyad, email, sifre_hash, telefon, iban_bilgisi, paket_turu, komisyon_orani, aktif_mi) VALUES (?, ?, ?, ?, ?, ?, ?, 0)");
        
        if ($insert->execute([$ad_soyad, $email, $sifre_hash, $telefon, $iban, $paket_turu, $komisyon])) {
            $message = "Başvurunuz başarıyla alındı! Temsilcimiz bilgilerinizi inceledikten sonra sizinle iletişime geçecektir.";
            $messageType = "success";
        } else {
            $message = "Kayıt sırasında teknik bir hata oluştu. Lütfen daha sonra deneyin.";
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
    <title>Yayıncı Başvurusu | Deneme AGS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { background-color: #f0f2f5; font-family: 'Open Sans', sans-serif; }
        .register-wrapper { max-width: 900px; margin: 40px auto; }
        .card-main { border: none; border-radius: 20px; overflow: hidden; }
        .header-bg { background: linear-gradient(135deg, #1F3C88 0%, #0f1e45 100%); color: white; padding: 40px; text-align: center; }
        
        .paket-option {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            height: 100%;
            position: relative;
        }
        .paket-option:hover { border-color: #1F3C88; transform: translateY(-3px); background-color: #fff; }
        .paket-option.active { border-color: #1F3C88; background-color: #f8faff; box-shadow: 0 5px 15px rgba(31, 60, 136, 0.1); }
        .paket-radio { position: absolute; opacity: 0; }
        
        .feature-list { font-size: 0.85rem; padding-left: 0; list-style: none; margin-top: 15px; }
        .feature-list li { margin-bottom: 8px; color: #555; }
        .feature-list i { color: #28a745; margin-right: 8px; }
        
        .income-example { background: #fff; border-radius: 8px; padding: 10px; border-left: 4px solid #F57C00; margin-top: 15px; font-size: 0.8rem; }
        .form-control { border-radius: 8px; padding: 12px; }
        .btn-submit { background-color: #F57C00; border: none; padding: 15px; font-weight: 700; border-radius: 8px; text-transform: uppercase; letter-spacing: 1px; }
        .btn-submit:hover { background-color: #e67600; }
    </style>
</head>
<body>

<div class="container">
    <div class="register-wrapper shadow-lg">
        <div class="card card-main">
            <div class="header-bg">
                <h2 class="fw-bold mb-2">DenemeAGS Yayıncı Ailesine Katılın</h2>
                <p class="opacity-75 mb-0">Eserlerinizi siber güvenlik altyapımızla koruyun ve kazancınızı artırın.</p>
            </div>
            
            <div class="card-body p-4 p-md-5 bg-white">
                <?php if($message): ?>
                    <div class="alert alert-<?=$messageType?> alert-dismissible fade show mb-4" role="alert">
                        <?=$message?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" id="registerForm">
                    <div class="row g-4 mb-5">
                        <div class="col-12"><h5 class="fw-bold border-bottom pb-2 mb-3">1. Çalışma Modelinizi Seçin</h5></div>
                        
                        <!-- PAKET 1: MANUEL -->
                        <div class="col-md-6">
                            <label class="paket-option <?= $selectedPaket == 'manuel' ? 'active' : '' ?>" id="label_manuel">
                                <input type="radio" name="paket_turu" value="manuel" class="paket-radio" <?= $selectedPaket == 'manuel' ? 'checked' : '' ?> onchange="updateSelection('manuel')">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="fw-bold mb-0">Paket-1: Manuel</h5>
                                    <span class="badge bg-warning text-dark">%15 Hizmet Bedeli</span>
                                </div>
                                <p class="small text-muted mb-0">Operasyonu ve tahsilatı kendi Shopier hesabınızdan yönetmek isteyen yazarlar için.</p>
                                <ul class="feature-list">
                                    <li><i class="fas fa-check"></i> Kendi Shopier hesabınızdan satış</li>
                                    <li><i class="fas fa-check"></i> Kodları panelden siz üretirsiniz</li>
                                    <li><i class="fas fa-check"></i> Güvenlik altyapımız aktiftir</li>
                                    <li><i class="fas fa-check"></i> Haftalık mahsuplaşma ve ödeme</li>
                                </ul>
                                <div class="income-example">
                                    <strong>Örnek:</strong> 10.000₺ haftalık gelirin 1.500₺'si hizmet bedeli olarak ödenir, <b>8.500₺</b> net hakediş kalır.
                                </div>
                            </label>
                        </div>

                        <!-- PAKET 2: OTOMATİK -->
                        <div class="col-md-6">
                            <label class="paket-option <?= $selectedPaket == 'otomatik' ? 'active' : '' ?>" id="label_otomatik">
                                <input type="radio" name="paket_turu" value="otomatik" class="paket-radio" <?= $selectedPaket == 'otomatik' ? 'checked' : '' ?> onchange="updateSelection('otomatik')">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="fw-bold mb-0">Paket-2: Otomatik</h5>
                                    <span class="badge bg-primary">En Popüler</span>
                                </div>
                                <p class="small text-muted mb-0">Sadece PDF gönderin; satış, dağıtım ve desteği tamamen biz yönetelim.</p>
                                <ul class="feature-list">
                                    <li><i class="fas fa-bolt text-primary"></i> Tam otomatik satış & kod teslimi</li>
                                    <li><i class="fas fa-check"></i> 1 dakikada otomatik üyelik</li>
                                    <li><i class="fas fa-check"></i> 2 haftalık periyotlarla hakediş</li>
                                    <li><i class="fas fa-check"></i> <strong>%70 net yazar payı</strong></li>
                                </ul>
                                <div class="income-example" style="border-left-color: #1F3C88;">
                                    <strong>Örnek:</strong> 10.000₺ haftalık satış cirosunun <b>7.000₺</b>'si doğrudan yazar hesabına ödenir.
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12"><h5 class="fw-bold border-bottom pb-2 mb-3">2. İletişim ve Hesap Bilgileri</h5></div>
                        
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Ad Soyad / Kurum Adı</label>
                            <input type="text" name="ad_soyad" class="form-control" placeholder="Örn: Ahmet Yılmaz veya ABC Yayınları" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">E-Posta Adresi</label>
                            <input type="email" name="email" class="form-control" placeholder="iletisim@yayinadi.com" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Telefon Numarası</label>
                            <input type="tel" name="telefon" class="form-control" placeholder="05XX XXX XX XX" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Ödeme Alınacak IBAN (Adınıza Kayıtlı)</label>
                            <input type="text" name="iban_bilgisi" class="form-control" placeholder="TR00 0000 0000..." required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Panel Giriş Şifresi</label>
                            <input type="password" name="sifre" class="form-control" required minlength="6">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Şifre Tekrar</label>
                            <input type="password" name="sifre_tekrar" class="form-control" required minlength="6">
                        </div>

                        <div class="col-12 mt-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="terms" required>
                                <label class="form-check-label small text-muted" for="terms">
                                    <a href="../yayinci-sozlesmesi.php" target="_blank" class="text-primary text-decoration-none">Yayıncı Sözleşmesini</a> okudum ve kabul ediyorum.
                                </label>
                            </div>
                        </div>

                        <div class="col-12 mt-4 text-center">
                            <button type="submit" name="register" class="btn btn-submit text-white w-100 shadow">
                                BAŞVURUYU TAMAMLA VE KAYDET
                            </button>
                            <p class="mt-3 small text-muted">
                                Zaten bir yazar hesabınız var mı? <a href="login.php" class="fw-bold text-primary text-decoration-none">Giriş Yapın</a>
                            </p>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function updateSelection(type) {
        document.querySelectorAll('.paket-option').forEach(el => el.classList.remove('active'));
        document.getElementById('label_' + type).classList.add('active');
    }
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>