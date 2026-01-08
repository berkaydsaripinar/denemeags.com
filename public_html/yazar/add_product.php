<?php
// yazar/add_product.php - Yeni Yayın Yükleme Merkezi
$page_title = "Yeni Yayın Ekle";
require_once __DIR__ . '/includes/author_header.php';

$csrf_token = generate_csrf_token();
$errors = [];

// Dosya Yükleme Ayarları
$product_upload_dir = __DIR__ . '/../../uploads/products/';

// Klasör kontrolü
if (!is_dir($product_upload_dir)) {
    mkdir($product_upload_dir, 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        set_author_flash_message('error', 'Güvenlik doğrulaması başarısız.');
    } else {
        // Form Verileri
        $deneme_adi = trim($_POST['deneme_adi'] ?? '');
        $tur = $_POST['tur'] ?? 'deneme';
        $fiyat = str_replace(',', '.', $_POST['fiyat'] ?? '0');
        $soru_sayisi = (int)($_POST['soru_sayisi'] ?? 50);
        $kisa_aciklama = trim($_POST['kisa_aciklama'] ?? '');
        $shopier_link = trim($_POST['shopier_link'] ?? '');
        $sonuc_tarihi = !empty($_POST['sonuc_aciklama_tarihi']) ? $_POST['sonuc_aciklama_tarihi'] : null;

        // Dosya İsimleri (Başlangıçta boş)
        $resim_url = null;
        $soru_pdf = null;
        $cozum_pdf = null;

        // --- DOSYA YÜKLEME ---
        
        // 1. Kapak Görseli
        if (isset($_FILES['resim_file']) && $_FILES['resim_file']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['resim_file']['name'], PATHINFO_EXTENSION);
            $resim_url = 'img_' . $yid . '_' . uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['resim_file']['tmp_name'], $product_upload_dir . $resim_url);
        }

        // 2. Soru Kitapçığı
        if (isset($_FILES['soru_pdf_file']) && $_FILES['soru_pdf_file']['error'] === UPLOAD_ERR_OK) {
            $soru_pdf = 'soru_' . $yid . '_' . uniqid() . '.pdf';
            move_uploaded_file($_FILES['soru_pdf_file']['tmp_name'], $product_upload_dir . $soru_pdf);
        }

        // 3. Çözüm Kitapçığı
        if (isset($_FILES['cozum_pdf_file']) && $_FILES['cozum_pdf_file']['error'] === UPLOAD_ERR_OK) {
            $cozum_pdf = 'cozum_' . $yid . '_' . uniqid() . '.pdf';
            move_uploaded_file($_FILES['cozum_pdf_file']['tmp_name'], $product_upload_dir . $cozum_pdf);
        }

        // --- VALIDASYON ---
        if (empty($deneme_adi)) $errors[] = "Yayın adı boş bırakılamaz.";
        if (!$soru_pdf) $errors[] = "Soru kitapçığı (PDF) yüklemek zorunludur.";

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO denemeler (
                        yazar_id, deneme_adi, tur, kisa_aciklama, fiyat, soru_sayisi,
                        resim_url, soru_kitapcik_dosyasi, cozum_linki, shopier_link, 
                        sonuc_aciklama_tarihi, aktif_mi
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
                ");
                
                $stmt->execute([
                    $yid, $deneme_adi, $tur, $kisa_aciklama, $fiyat, $soru_sayisi,
                    $resim_url, $soru_pdf, $cozum_pdf, $shopier_link, $sonuc_tarihi
                ]);

                set_author_flash_message('success', 'Yayın başarıyla eklendi. Yönetici onayından sonra mağazada görünecektir.');
                redirect('yazar/manage_products.php');
            } catch (PDOException $e) {
                $errors[] = "Veritabanı hatası: " . $e->getMessage();
            }
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-5 fade-in">
    <div>
        <h2 class="fw-bold text-dark mb-1">Yeni Yayın Yükle</h2>
        <p class="text-muted mb-0">Dijital içeriklerinizi sisteme kaydederek satışa hazırlayın.</p>
    </div>
    <a href="manage_products.php" class="btn btn-light border rounded-pill px-4 shadow-sm">
        <i class="fas fa-arrow-left me-2"></i>Listeye Dön
    </a>
</div>

<?php if(!empty($errors)): ?>
    <div class="alert alert-danger rounded-4 border-0 shadow-sm mb-4">
        <ul class="mb-0 small fw-bold"><?php foreach($errors as $e) echo "<li>$e</li>"; ?></ul>
    </div>
<?php endif; ?>

<form action="add_product.php" method="POST" enctype="multipart/form-data" class="fade-in">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

    <div class="row g-4">
        <!-- SOL KOLON: İçerik Bilgileri -->
        <div class="col-lg-8">
            <div class="card card-custom p-4 p-md-5 mb-4">
                <h5 class="fw-bold text-primary mb-4"><i class="fas fa-info-circle me-2"></i>Genel Bilgiler</h5>
                
                <div class="mb-4">
                    <label class="form-label small fw-bold text-muted text-uppercase">Yayın Adı</label>
                    <input type="text" name="deneme_adi" class="form-control form-control-lg input-theme" placeholder="Örn: ÖABT Türkçe 5'li Deneme Seti" required>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label class="form-label small fw-bold text-muted text-uppercase">Ürün Türü</label>
                        <select name="tur" class="form-select form-select-lg input-theme">
                            <option value="deneme">Deneme Sınavı (Online Optik Formlu)</option>
                            <option value="soru_bankasi">Soru Bankası / PDF Not</option>
                            <option value="diger">Diğer Dijital İçerik</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-4">
                        <label class="form-label small fw-bold text-muted text-uppercase">Soru Sayısı</label>
                        <input type="number" name="soru_sayisi" class="form-control form-control-lg input-theme" value="50" required>
                    </div>
                </div>

                <div class="mb-0">
                    <label class="form-label small fw-bold text-muted text-uppercase">Kısa Açıklama</label>
                    <textarea name="kisa_aciklama" class="form-control input-theme" rows="4" placeholder="Ürününüz hakkında öğrencileri bilgilendirin..."></textarea>
                </div>
            </div>

            <div class="card card-custom p-4 p-md-5">
                <h5 class="fw-bold text-primary mb-4"><i class="fas fa-cloud-upload-alt me-2"></i>Dosya Yükleme</h5>
                
                <div class="row">
                    <div class="col-md-12 mb-4">
                        <label class="form-label small fw-bold text-muted text-uppercase">Kapak Görseli (JPG/PNG)</label>
                        <input type="file" name="resim_file" class="form-control input-theme" accept="image/*">
                        <div class="form-text small">Mağaza vitrininde görünecek profesyonel kapak görseli.</div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <label class="form-label small fw-bold text-muted text-uppercase">Soru Kitapçığı (PDF)</label>
                        <input type="file" name="soru_pdf_file" class="form-control input-theme" accept=".pdf" required>
                        <div class="form-text small">Öğrencinin indireceği ana döküman.</div>
                    </div>
                    <div class="col-md-6 mb-0">
                        <label class="form-label small fw-bold text-muted text-uppercase">Çözüm Kitapçığı (PDF - Opsiyonel)</label>
                        <input type="file" name="cozum_pdf_file" class="form-control input-theme" accept=".pdf">
                        <div class="form-text small">Sınav bitiminde erişilebilecek çözüm dosyası.</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SAĞ KOLON: Fiyat ve Satış -->
        <div class="col-lg-4">
            <div class="card card-custom p-4 mb-4">
                <h5 class="fw-bold text-primary mb-4"><i class="fas fa-tag me-2"></i>Satış Ayarları</h5>
                
                <div class="mb-4">
                    <label class="form-label small fw-bold text-muted text-uppercase">Satış Fiyatı (₺)</label>
                    <div class="input-group">
                        <input type="number" step="0.01" name="fiyat" class="form-control form-control-lg input-theme" placeholder="0.00" required>
                        <span class="input-group-text bg-white border-start-0 text-muted fw-bold">₺</span>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-bold text-muted text-uppercase">Shopier Linki</label>
                    <input type="url" name="shopier_link" class="form-control input-theme" placeholder="https://shopier.com/...">
                    <div class="form-text small">Mağaza yönlendirmesi için gereklidir.</div>
                </div>

                <div class="mb-0">
                    <label class="form-label small fw-bold text-muted text-uppercase">Sonuç Açıklama Tarihi</label>
                    <input type="datetime-local" name="sonuc_aciklama_tarihi" class="form-control input-theme">
                    <div class="form-text small">Sadece süreli denemeler için doldurulur.</div>
                </div>
            </div>

            <div class="alert bg-warning bg-opacity-10 text-dark rounded-4 border-0 p-4 mb-4 small">
                <h6 class="fw-bold mb-2"><i class="fas fa-shield-alt me-2 text-warning"></i>Güvenlik Notu</h6>
                Yüklediğiniz tüm PDF dosyaları, indirme esnasında öğrenci bilgileriyle otomatik olarak <strong>filigranlanacaktır</strong>. Korsan paylaşımlara karşı eseriniz güvendedir.
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-coral btn-lg py-3 shadow-sm fw-bold">
                    <i class="fas fa-check-circle me-2"></i>YAYINI KAYDET
                </button>
            </div>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/includes/author_footer.php'; ?>