<?php
// admin/edit_deneme.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAdminLogin();

// YETKİ KONTROLÜ: Sadece Süper Admin bu sayfayı kullanabilir.
if (!isSuperAdmin()) {
    set_admin_flash_message('error', 'Bu sayfaya erişim yetkiniz yok.');
    header("Location: dashboard.php");
    exit;
}

$deneme_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$is_editing = ($deneme_id !== null && $deneme_id > 0);
$page_title = $is_editing ? "Ürün/Deneme Düzenle" : "Yeni Ürün/Deneme Ekle";

// Form için varsayılan değerler
$deneme_data = [
    'deneme_adi' => '',
    'tur' => 'deneme', // YENİ: Ürün Türü Varsayılanı
    'kisa_aciklama' => '',
    'soru_sayisi' => 50,
    'sonuc_aciklama_tarihi' => '',
    'cozum_linki' => '',
    'soru_kitapcik_dosyasi' => '',
    'resim_url' => '',
    'shopier_link' => '',
    'shopier_product_id' => null,
    'aktif_mi' => 1,
    'anasayfada_goster' => 0
];
$deneme_data['sonuc_aciklama_tarihi_formatted'] = '';

// Düzenleme moduysa verileri veritabanından çek
if ($is_editing) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM denemeler WHERE id = ?");
        $stmt->execute([$deneme_id]);
        $deneme_data_db = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($deneme_data_db) {
            $deneme_data = array_merge($deneme_data, $deneme_data_db);
            if (!empty($deneme_data['sonuc_aciklama_tarihi'])) {
                 $dt = new DateTime($deneme_data['sonuc_aciklama_tarihi']);
                 $deneme_data['sonuc_aciklama_tarihi_formatted'] = $dt->format('Y-m-d\TH:i');
            }
        } else {
            set_admin_flash_message('error', 'Düzenlenecek ürün bulunamadı.');
            header("Location: manage_denemeler.php"); exit;
        }
    } catch (PDOException $e) { 
        set_admin_flash_message('error', "Ürün bilgileri yüklenirken hata: " . $e->getMessage());
        header("Location: manage_denemeler.php"); exit;
    }
}

$csrf_token = generate_admin_csrf_token();

// Form gönderildiğinde veriyi işle
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_admin_csrf_token($_POST['csrf_token'])) {
        set_admin_flash_message('error', 'Geçersiz güvenlik token\'ı. Lütfen tekrar deneyin.');
    } else {
        // Gelen verileri al ve temizle
        $deneme_data_post = [
            'deneme_adi' => trim($_POST['deneme_adi'] ?? ''),
            'tur' => $_POST['tur'] ?? 'deneme', // YENİ
            'kisa_aciklama' => trim($_POST['kisa_aciklama'] ?? ''),
            'soru_sayisi' => filter_input(INPUT_POST, 'soru_sayisi', FILTER_VALIDATE_INT, ['options' => ['default' => 50, 'min_range' => 1]]),
            'cozum_linki' => trim($_POST['cozum_linki'] ?? ''),
            'soru_kitapcik_dosyasi' => trim($_POST['soru_kitapcik_dosyasi'] ?? ''),
            'resim_url' => trim($_POST['resim_url'] ?? ''),
            'shopier_link' => trim($_POST['shopier_link'] ?? ''),
            'shopier_product_id' => !empty($_POST['shopier_product_id']) ? filter_input(INPUT_POST, 'shopier_product_id', FILTER_VALIDATE_INT) : null,
            'aktif_mi' => isset($_POST['aktif_mi']) ? 1 : 0,
            'anasayfada_goster' => isset($_POST['anasayfada_goster']) ? 1 : 0
        ];
        
        $sonuc_aciklama_tarihi_input = $_POST['sonuc_aciklama_tarihi'] ?? '';
        $deneme_data_post['sonuc_aciklama_tarihi'] = null;
        
        // Doğrulama (Validation)
        $errors = [];
        if (empty($deneme_data_post['deneme_adi'])) { $errors[] = "Ürün/Deneme adı boş bırakılamaz."; }
        if (!in_array($deneme_data_post['tur'], ['deneme', 'soru_bankasi', 'diger'])) { $errors[] = "Geçersiz ürün türü."; }
        
        // Sıralama tarihi SADECE 'deneme' türü için zorunlu olsun
        if ($deneme_data_post['tur'] === 'deneme') {
            if (empty($sonuc_aciklama_tarihi_input)) {
                $errors[] = "Deneme sınavları için sıralama açıklanma tarihi boş bırakılamaz.";
            } else {
                try {
                    $dt = new DateTime($sonuc_aciklama_tarihi_input);
                    $deneme_data_post['sonuc_aciklama_tarihi'] = $dt->format('Y-m-d H:i:s');
                } catch (Exception $e) {
                    $errors[] = 'Geçersiz sıralama açıklanma tarihi formatı.';
                }
            }
        } else {
             // Soru bankası ise tarih null olabilir veya varsa kaydedilir
             if (!empty($sonuc_aciklama_tarihi_input)) {
                try {
                    $dt = new DateTime($sonuc_aciklama_tarihi_input);
                    $deneme_data_post['sonuc_aciklama_tarihi'] = $dt->format('Y-m-d H:i:s');
                } catch (Exception $e) { /* Hata yok sayılabilir veya düzeltilebilir */ }
             }
        }

        if (!empty($deneme_data_post['resim_url']) && !filter_var($deneme_data_post['resim_url'], FILTER_VALIDATE_URL)) { $errors[] = "Resim URL'si geçerli bir URL formatında değil."; }
        if (!empty($deneme_data_post['shopier_link']) && !filter_var($deneme_data_post['shopier_link'], FILTER_VALIDATE_URL)) { $errors[] = "Shopier linki geçerli bir URL formatında değil."; }
        if (isset($_POST['shopier_product_id']) && $_POST['shopier_product_id'] !== '' && filter_input(INPUT_POST, 'shopier_product_id', FILTER_VALIDATE_INT) === false) { $errors[] = "Shopier Ürün ID'si geçerli bir sayı olmalıdır."; }

        // Doğrulama başarılıysa veritabanı işlemini yap
        if (empty($errors)) {
            try {
                if ($is_editing) {
                    $sql = "UPDATE denemeler SET deneme_adi = :deneme_adi, tur = :tur, kisa_aciklama = :kisa_aciklama, soru_sayisi = :soru_sayisi, 
                            sonuc_aciklama_tarihi = :sonuc_aciklama_tarihi, cozum_linki = :cozum_linki, soru_kitapcik_dosyasi = :soru_kitapcik_dosyasi, 
                            resim_url = :resim_url, shopier_link = :shopier_link, shopier_product_id = :shopier_product_id, 
                            aktif_mi = :aktif_mi, anasayfada_goster = :anasayfada_goster
                            WHERE id = :id";
                    $params = array_merge($deneme_data_post, [':id' => $deneme_id]);
                } else {
                    $sql = "INSERT INTO denemeler (deneme_adi, tur, kisa_aciklama, soru_sayisi, sonuc_aciklama_tarihi, cozum_linki, soru_kitapcik_dosyasi, resim_url, shopier_link, shopier_product_id, aktif_mi, anasayfada_goster) 
                            VALUES (:deneme_adi, :tur, :kisa_aciklama, :soru_sayisi, :sonuc_aciklama_tarihi, :cozum_linki, :soru_kitapcik_dosyasi, :resim_url, :shopier_link, :shopier_product_id, :aktif_mi, :anasayfada_goster)";
                     $params = $deneme_data_post;
                }
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                set_admin_flash_message('success', $is_editing ? 'Ürün başarıyla güncellendi.' : 'Yeni ürün başarıyla eklendi.');
                header("Location: manage_denemeler.php"); exit;
            } catch (PDOException $e) {
                set_admin_flash_message('error', "Veritabanı hatası: " . $e->getMessage());
            }
        } else {
            foreach ($errors as $error) {
                set_admin_flash_message('error', $error);
            }
        }
        
        $deneme_data = array_merge($deneme_data, $deneme_data_post);
        $deneme_data['sonuc_aciklama_tarihi_formatted'] = $sonuc_aciklama_tarihi_input; 
    }
}

include_once __DIR__ . '/../templates/admin_header.php';
?>

<div class="admin-page-title"><?php echo $page_title; ?></div>

<form action="edit_deneme.php<?php echo $is_editing ? '?id='.$deneme_id : ''; ?>" method="POST" style="max-width: 800px;">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    
    <div class="card card-theme mb-3">
        <div class="card-header card-header-theme-light">Temel Bilgiler</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-8 mb-3">
                    <label for="deneme_adi" class="form-label fw-bold">Ürün / Deneme Adı:</label>
                    <input type="text" id="deneme_adi" name="deneme_adi" class="input-admin form-control" 
                           value="<?php echo escape_html($deneme_data['deneme_adi']); ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="tur" class="form-label fw-bold">Ürün Türü:</label>
                    <select name="tur" id="tur" class="form-select input-admin" onchange="toggleDenemeFields()">
                        <option value="deneme" <?php echo ($deneme_data['tur'] === 'deneme') ? 'selected' : ''; ?>>Deneme Sınavı</option>
                        <option value="soru_bankasi" <?php echo ($deneme_data['tur'] === 'soru_bankasi') ? 'selected' : ''; ?>>Soru Bankası</option>
                        <option value="diger" <?php echo ($deneme_data['tur'] === 'diger') ? 'selected' : ''; ?>>Diğer (PDF Kaynak vb.)</option>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label for="kisa_aciklama" class="form-label">Kısa Açıklama (Anasayfada görünecek):</label>
                <textarea id="kisa_aciklama" name="kisa_aciklama" class="input-admin form-control" rows="3"><?php echo escape_html($deneme_data['kisa_aciklama']); ?></textarea>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="soru_sayisi" class="form-label">Soru Sayısı (varsa):</label>
                    <input type="number" id="soru_sayisi" name="soru_sayisi" class="input-admin form-control" 
                           value="<?php echo escape_html($deneme_data['soru_sayisi']); ?>" min="1">
                </div>
                <div class="col-md-6 mb-3" id="div_sonuc_aciklama_tarihi">
                    <label for="sonuc_aciklama_tarihi" class="form-label">Sıralama Açıklanma Tarihi:</label>
                    <input type="datetime-local" id="sonuc_aciklama_tarihi" name="sonuc_aciklama_tarihi" class="input-admin form-control" 
                           value="<?php echo escape_html($deneme_data['sonuc_aciklama_tarihi_formatted']); ?>">
                    <small class="form-text text-muted">Sadece deneme sınavları için gereklidir.</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Soru ve Çözüm Dosyaları -->
    <div class="card card-theme mb-3">
        <div class="card-header card-header-theme-light">Soru ve Çözüm Dosyaları</div>
        <div class="card-body">
            <div class="form-group mb-3">
                <label for="soru_kitapcik_dosyasi" class="form-label fw-bold">Soru Kitapçığı PDF Dosya Adı:</label>
                <input type="text" id="soru_kitapcik_dosyasi" name="soru_kitapcik_dosyasi" class="input-admin form-control" 
                       placeholder="deneme_soru_kitapcigi.pdf" value="<?php echo escape_html($deneme_data['soru_kitapcik_dosyasi']); ?>">
                <small class="form-text text-muted">Otomatik gönderilecek veya indirilecek soru dosyası. (<code>uploads/questions/</code> klasöründe)</small>
            </div>
             <div class="form-group mb-0">
                <label for="cozum_linki" class="form-label">Çözüm PDF Dosya Adı:</label>
                <input type="text" id="cozum_linki" name="cozum_linki" class="input-admin form-control" 
                       placeholder="deneme_cozum.pdf" value="<?php echo escape_html($deneme_data['cozum_linki']); ?>">
                <small class="form-text text-muted">Sonuçlar açıklandığında veya soru bankası için indirilecek çözüm dosyası. (<code>uploads/solutions/</code> klasöründe)</small>
            </div>
        </div>
    </div>
    
    <!-- Anasayfa ve Satış Ayarları -->
    <div class="card card-theme mb-3">
        <div class="card-header card-header-theme-light">Anasayfa ve Satış Ayarları</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="resim_url" class="form-label">Ürün Resmi URL'si:</label>
                    <input type="url" id="resim_url" name="resim_url" class="input-admin form-control" 
                           placeholder="https://example.com/resim.jpg" value="<?php echo escape_html($deneme_data['resim_url']); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="shopier_link" class="form-label">Shopier Satış Linki:</label>
                    <input type="url" id="shopier_link" name="shopier_link" class="input-admin form-control" 
                           placeholder="https://www.shopier.com/..." value="<?php echo escape_html($deneme_data['shopier_link']); ?>">
                </div>
            </div>
            
            <div class="form-group mb-3">
                <label for="shopier_product_id" class="form-label fw-bold">Shopier Ürün ID (OTOMASYON İÇİN):</label>
                <input type="number" id="shopier_product_id" name="shopier_product_id" class="input-admin form-control" value="<?php echo escape_html($deneme_data['shopier_product_id']); ?>">
                <small class="form-text text-muted">Shopier'daki benzersiz ürün ID'si. Otomatik teslimat için zorunludur.</small>
            </div>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="anasayfada_goster_checkbox" name="anasayfada_goster" value="1" <?php echo ($deneme_data['anasayfada_goster'] == 1) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="anasayfada_goster_checkbox">Bu ürünü anasayfada (vitrinde) göster</label>
            </div>
        </div>
    </div>
    
    <div class="form-check form-switch mt-4 mb-3">
        <input class="form-check-input" type="checkbox" id="aktif_mi_checkbox" name="aktif_mi" value="1" <?php echo ($deneme_data['aktif_mi'] == 1) ? 'checked' : ''; ?>>
        <label class="form-check-label" for="aktif_mi_checkbox">Ürün/Deneme Aktif Mi? (Kullanıcılar erişebilsin mi?)</label>
    </div>

    <button type="submit" class="btn-admin green mt-3"><?php echo $is_editing ? 'Güncelle' : 'Ekle'; ?></button>
    <a href="manage_denemeler.php" class="btn-admin yellow mt-3 ms-2">İptal</a>
</form>

<script>
    function toggleDenemeFields() {
        const tur = document.getElementById('tur').value;
        const tarihDiv = document.getElementById('div_sonuc_aciklama_tarihi');
        const tarihInput = document.getElementById('sonuc_aciklama_tarihi');
        
        if (tur === 'deneme') {
            tarihDiv.style.display = 'block';
            tarihInput.required = true;
        } else {
            // Soru bankası veya diğer ise tarihi gizle ve zorunluluğu kaldır (veya isteğe bağlı bırak)
            // İsterseniz gizleyebilirsiniz, ben şimdilik sadece zorunluluğu kaldırıyorum.
            // tarihDiv.style.display = 'none'; 
            tarihInput.required = false;
        }
    }
    // Sayfa yüklendiğinde çalıştır
    toggleDenemeFields();
</script>

<?php
include_once __DIR__ . '/../templates/admin_footer.php';
?>