<?php
// yazar/edit_product.php - Yayın Düzenleme ve Dosya Güncelleme Merkezi
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['yazar_id'])) { redirect('yazar/login.php'); }

$yid = $_SESSION['yazar_id'];
$pid = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$pid) { redirect('yazar/manage_products.php'); }

// --- VERİ ÇEKME VE YETKİ KONTROLÜ ---
try {
    $stmt = $pdo->prepare("SELECT * FROM denemeler WHERE id = ? AND yazar_id = ?");
    $stmt->execute([$pid, $yid]);
    $product = $stmt->fetch();

    if (!$product) {
        // Ürün yoksa veya yazarın değilse erişimi engelle
        redirect('yazar/manage_products.php');
    }
} catch (PDOException $e) { redirect('yazar/manage_products.php'); }

$page_title = "Yayını Düzenle: " . $product['deneme_adi'];
$csrf_token = generate_csrf_token();

// --- GÜNCELLEME İŞLEMİ ---
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $errors[] = "Güvenlik doğrulaması başarısız.";
    } else {
        $adi = trim($_POST['deneme_adi']);
        $fiyat = (float)$_POST['fiyat'];
        $soru = (int)$_POST['soru_sayisi'];
        $desc = trim($_POST['kisa_aciklama']);
        $shopier = trim($_POST['shopier_link']);
        $aciklama_tarihi = !empty($_POST['sonuc_aciklama_tarihi']) ? $_POST['sonuc_aciklama_tarihi'] : null;

        if (empty($adi)) $errors[] = "Yayın adı boş bırakılamaz.";

        if (empty($errors)) {
            try {
                $stmt_upd = $pdo->prepare("
                    UPDATE denemeler SET 
                        deneme_adi = ?, fiyat = ?, soru_sayisi = ?, 
                        kisa_aciklama = ?, shopier_link = ?, sonuc_aciklama_tarihi = ? 
                    WHERE id = ? AND yazar_id = ?
                ");
                $stmt_upd->execute([$adi, $fiyat, $soru, $desc, $shopier, $aciklama_tarihi, $pid, $yid]);
                
                // Veriyi tazele
                $product['deneme_adi'] = $adi;
                $product['fiyat'] = $fiyat;
                $product['soru_sayisi'] = $soru;
                $product['kisa_aciklama'] = $desc;
                $product['shopier_link'] = $shopier;
                $product['sonuc_aciklama_tarihi'] = $aciklama_tarihi;
                
                $success = "Yayın bilgileri başarıyla güncellendi.";
            } catch (PDOException $e) { $errors[] = "Veritabanı hatası: " . $e->getMessage(); }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> | Yazar Paneli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #1F3C88; --coral: #FF6F61; --bg: #f8faff; }
        body { background: var(--bg); font-family: 'Inter', sans-serif; }
        .sidebar { background: var(--primary); height: 100vh; position: fixed; width: 250px; padding: 30px 0; color: #fff; z-index: 1000; }
        .main-panel { margin-left: 250px; padding: 40px; }
        .nav-link { color: rgba(255,255,255,0.7); padding: 15px 30px; font-weight: 600; transition: 0.3s; display: flex; align-items: center; text-decoration: none; }
        .nav-link:hover, .nav-link.active { color: #fff; background: rgba(255,255,255,0.1); border-left: 5px solid var(--coral); }
        .form-card { border: none; border-radius: 20px; background: #fff; box-shadow: 0 10px 30px rgba(0,0,0,0.03); padding: 30px; }
        .input-theme { border-radius: 12px; padding: 12px 15px; border: 1px solid #eee; background: #fafafa; }
        .input-theme:focus { border-color: var(--coral); box-shadow: 0 0 0 0.25rem rgba(255, 111, 97, 0.1); background: #fff; }
        @media (max-width: 992px) { .sidebar { display: none; } .main-panel { margin-left: 0; padding: 20px; } }
    </style>
</head>
<body>

<div class="sidebar shadow-lg">
    <div class="px-4 mb-5 text-center">
        <h4 class="fw-black mb-0 text-white">DENEME<span class="text-warning">AGS</span></h4>
        <div class="badge bg-warning text-dark rounded-pill px-3 mt-1 small fw-bold">Yazar Merkezi</div>
    </div>
    <nav class="nav flex-column">
        <a class="nav-link" href="dashboard.php"><i class="fas fa-th-large me-2"></i> Genel Bakış</a>
        <a class="nav-link active" href="manage_products.php"><i class="fas fa-book me-2"></i> Yayınlarım</a>
        <a class="nav-link" href="analytics.php"><i class="fas fa-chart-pie me-2"></i> Satış Analizi</a>
        <a class="nav-link" href="earnings.php"><i class="fas fa-wallet me-2"></i> Ödemeler</a>
        <a class="nav-link text-danger mt-5" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Çıkış Yap</a>
    </nav>
</div>

<div class="main-panel">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h2 class="fw-bold text-dark mb-1">Yayın Düzenle</h2>
            <p class="text-muted mb-0">"<?php echo escape_html($product['deneme_adi']); ?>" bilgilerini güncelleyin.</p>
        </div>
        <a href="manage_products.php" class="btn btn-light border rounded-pill px-4"><i class="fas fa-arrow-left me-2"></i>Geri Dön</a>
    </div>

    <?php if(!empty($errors)): ?>
        <div class="alert alert-danger rounded-4 border-0 shadow-sm mb-4">
            <ul class="mb-0 small fw-bold"><?php foreach($errors as $e) echo "<li>$e</li>"; ?></ul>
        </div>
    <?php endif; ?>

    <?php if($success): ?>
        <div class="alert alert-success rounded-4 border-0 shadow-sm mb-4 fw-bold small">
            <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="form-card">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted">YAYIN ADI</label>
                        <input type="text" name="deneme_adi" class="form-control input-theme" value="<?php echo escape_html($product['deneme_adi']); ?>" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="form-label small fw-bold text-muted">SATIŞ FİYATI (₺)</label>
                            <input type="number" step="0.01" name="fiyat" class="form-control input-theme" value="<?php echo $product['fiyat']; ?>" required>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label small fw-bold text-muted">SORU SAYISI</label>
                            <input type="number" name="soru_sayisi" class="form-control input-theme" value="<?php echo $product['soru_sayisi']; ?>" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted">KISA AÇIKLAMA</label>
                        <textarea name="kisa_aciklama" class="form-control input-theme" rows="4"><?php echo escape_html($product['kisa_aciklama']); ?></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted">SHOPIER ÖDEME LİNKİ</label>
                        <input type="url" name="shopier_link" class="form-control input-theme" value="<?php echo escape_html($product['shopier_link']); ?>" placeholder="https://www.shopier.com/...">
                    </div>

                    <div class="mb-5">
                        <label class="form-label small fw-bold text-muted">SONUÇ AÇIKLAMA TARİHİ (SADECE DENEME İSE)</label>
                        <?php $dt = $product['sonuc_aciklama_tarihi'] ? date('Y-m-d\TH:i', strtotime($product['sonuc_aciklama_tarihi'])) : ''; ?>
                        <input type="datetime-local" name="sonuc_aciklama_tarihi" class="form-control input-theme" value="<?php echo $dt; ?>">
                        <div class="form-text small">Soru bankaları için boş bırakabilirsiniz.</div>
                    </div>

                    <button type="submit" class="btn btn-theme-primary w-100 py-3 rounded-pill fw-bold shadow">
                        <i class="fas fa-save me-2"></i>DEĞİŞİKLİKLERİ KAYDET
                    </button>
                </form>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="form-card mb-4 bg-light border">
                <h6 class="fw-bold mb-3"><i class="fas fa-info-circle me-2 text-primary"></i>Önemli Bilgi</h6>
                <p class="text-muted small mb-0">Dosya güncellemeleri (Soru Kitapçığı veya Çözüm PDF) güvenlik politikaları gereği şimdilik sadece sistem yöneticisi üzerinden yapılabilmektedir. Yeni bir dosya yüklemek istiyorsanız lütfen admin ile iletişime geçin.</p>
            </div>

            <div class="form-card text-center">
                <div class="mb-3 small fw-bold text-muted">YAYIN DURUMU</div>
                <?php if($product['aktif_mi']): ?>
                    <div class="p-3 bg-success bg-opacity-10 text-success rounded-4 fw-bold">
                        <i class="fas fa-check-circle me-2"></i>YAYINDA
                    </div>
                <?php else: ?>
                    <div class="p-3 bg-warning bg-opacity-10 text-warning rounded-4 fw-bold">
                        <i class="fas fa-clock me-2"></i>ONAY BEKLİYOR
                    </div>
                <?php endif; ?>
                
                <div class="mt-4 pt-4 border-top">
                    <div class="small text-muted mb-2">Katalog Görünümü</div>
                    <a href="../urun.php?id=<?php echo $pid; ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                        <i class="fas fa-external-link-alt me-1"></i>Mağazada Gör
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>