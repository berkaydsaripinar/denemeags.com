<?php
// yazar/profile.php - Bütünleşik Profil Ayarları
$page_title = "Profil Ayarları";
require_once __DIR__ . '/includes/author_header.php';

$message = '';
$csrf_token = generate_csrf_token();

// Yazarın güncel verilerini çek
try {
    $stmt = $pdo->prepare("SELECT * FROM yazarlar WHERE id = ?");
    $stmt->execute([$yid]);
    $yazar = $stmt->fetch();
} catch (Exception $e) { redirect('dashboard.php'); }

// Güncelleme İşlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        set_author_flash_message('error', 'Güvenlik doğrulaması başarısız.');
    } else {
        $ad_soyad = trim($_POST['ad_soyad']);
        $iban = trim($_POST['iban_bilgisi']);
        $bio = trim($_POST['biyografi']);
        $sifre = $_POST['sifre'];

        try {
            $pdo->beginTransaction();
            
            $stmt_upd = $pdo->prepare("UPDATE yazarlar SET ad_soyad = ?, iban_bilgisi = ?, biyografi = ? WHERE id = ?");
            $stmt_upd->execute([$ad_soyad, $iban, $bio, $yid]);

            if (!empty($sifre)) {
                $hash = password_hash($sifre, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE yazarlar SET sifre_hash = ? WHERE id = ?")->execute([$hash, $yid]);
            }

            $pdo->commit();
            $_SESSION['yazar_name'] = $ad_soyad;
            set_author_flash_message('success', 'Profil bilgileriniz başarıyla güncellendi.');
            redirect('profile.php');
            
        } catch (Exception $e) { 
            if ($pdo->inTransaction()) $pdo->rollBack();
            set_author_flash_message('error', 'Güncelleme sırasında bir hata oluştu.'); 
        }
    }
}
?>

<div class="mb-5">
    <h2 class="fw-bold text-dark mb-1">Hesap Ayarları</h2>
    <p class="text-muted mb-0">Kişisel bilgilerinizi ve ödeme detaylarınızı buradan yönetebilirsiniz.</p>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card card-custom p-4 p-md-5">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label class="form-label small fw-bold text-muted">AD SOYAD</label>
                        <input type="text" name="ad_soyad" class="form-control form-control-lg border-0 bg-light rounded-3" value="<?php echo escape_html($yazar['ad_soyad']); ?>" required>
                    </div>
                    <div class="col-md-6 mb-4">
                        <label class="form-label small fw-bold text-muted">E-POSTA ADRESİ</label>
                        <input type="email" class="form-control form-control-lg border-0 bg-light rounded-3 opacity-50" value="<?php echo escape_html($yazar['email']); ?>" readonly>
                        <div class="form-text small">E-posta adresi değiştirilemez.</div>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-bold text-muted">IBAN BİLGİSİ (Ödemeler İçin)</label>
                    <input type="text" name="iban_bilgisi" class="form-control form-control-lg border-0 bg-light rounded-3" value="<?php echo escape_html($yazar['iban_bilgisi']); ?>" placeholder="TR00 0000...">
                    <div class="form-text small text-primary"><i class="fas fa-info-circle me-1"></i> Kazançlarınızın yatırılacağı geçerli bir TR IBAN'ı giriniz.</div>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-bold text-muted">KISA BİYOGRAFİ</label>
                    <textarea name="biyografi" class="form-control border-0 bg-light rounded-3" rows="4" placeholder="Kendinizden kısaca bahsedin..."><?php echo escape_html($yazar['biyografi']); ?></textarea>
                </div>

                <div class="p-4 bg-light rounded-4 mb-4 border border-dashed">
                    <h6 class="fw-bold mb-3 text-dark"><i class="fas fa-lock me-2"></i>Güvenlik</h6>
                    <div class="mb-0">
                        <label class="form-label small fw-bold text-muted">ŞİFREYİ DEĞİŞTİR (Opsiyonel)</label>
                        <input type="password" name="sifre" class="form-control border-0 bg-white" placeholder="Değiştirmek istemiyorsanız boş bırakın">
                    </div>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-coral px-5 py-3 shadow">DEĞİŞİKLİKLERİ KAYDET</button>
                </div>
            </form>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card card-custom border-top border-primary border-4 mb-4">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3">Hesap Özeti</h6>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted small">Komisyon Oranı:</span>
                    <span class="fw-bold text-success">%<?php echo $yazar['komisyon_orani']; ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted small">Kayıt Tarihi:</span>
                    <span class="fw-bold"><?php echo date('d.m.Y', strtotime($yazar['kayit_tarihi'])); ?></span>
                </div>
                <hr>
                <a href="../yazar.php?id=<?php echo $yid; ?>" target="_blank" class="btn btn-outline-primary btn-sm w-100 rounded-pill fw-bold">
                    <i class="fas fa-eye me-2"></i>Mağaza Profilimi Gör
                </a>
            </div>
        </div>

        <div class="alert bg-primary bg-opacity-10 text-primary rounded-4 border-0 p-4 small">
            <h6 class="fw-bold"><i class="fas fa-shield-alt me-2"></i>Veri Güvenliği</h6>
            Biyografiniz ve adınız mağaza sayfanızda öğrenciler tarafından görüntülenebilir. IBAN ve e-posta adresiniz tamamen gizli tutulmaktadır.
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/author_footer.php'; ?>