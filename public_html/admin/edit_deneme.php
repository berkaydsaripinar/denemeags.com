<?php
// admin/edit_deneme.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAdminLogin();

if (!isSuperAdmin()) {
    set_admin_flash_message('error', 'Bu işlem için Süper Admin yetkisi gereklidir.');
    redirect('admin/dashboard.php');
}

$deneme_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$is_editing = ($deneme_id !== null && $deneme_id > 0);
$page_title = $is_editing ? "Yayını Güncelle" : "Yeni Yayın Ekle";

// Form Verileri Varsayılanları
$deneme_data = [
    'deneme_adi' => '', 'tur' => 'deneme', 'yazar_id' => null, 'kisa_aciklama' => '',
    'soru_sayisi' => 50, 'sonuc_aciklama_tarihi' => '', 'cozum_linki' => '',
    'soru_kitapcik_dosyasi' => '', 'resim_url' => '', 'shopier_link' => '',
    'shopier_product_id' => '', 'aktif_mi' => 1, 'anasayfada_goster' => 0
];

// Yazarları Çek (Aktif yazarlar listesi için)
try {
    $yazarlar = $pdo->query("SELECT id, ad_soyad FROM yazarlar WHERE aktif_mi = 1 ORDER BY ad_soyad ASC")->fetchAll();
} catch (PDOException $e) { $yazarlar = []; }

// Düzenleme modunda veriyi veritabanından çek
if ($is_editing) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM denemeler WHERE id = ?");
        $stmt->execute([$deneme_id]);
        $db_data = $stmt->fetch();
        if ($db_data) {
            $deneme_data = array_merge($deneme_data, $db_data);
        }
    } catch (PDOException $e) { error_log($e->getMessage()); }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_admin_csrf_token($_POST['csrf_token'])) {
        $post_data = [
            'deneme_adi' => trim($_POST['deneme_adi'] ?? ''),
            'tur' => $_POST['tur'] ?? 'deneme',
            'yazar_id' => (!empty($_POST['yazar_id'])) ? (int)$_POST['yazar_id'] : null,
            'kisa_aciklama' => trim($_POST['kisa_aciklama'] ?? ''),
            'soru_sayisi' => (int)($_POST['soru_sayisi'] ?? 50),
            'cozum_linki' => trim($_POST['cozum_linki'] ?? ''),
            'soru_kitapcik_dosyasi' => trim($_POST['soru_kitapcik_dosyasi'] ?? ''),
            'resim_url' => trim($_POST['resim_url'] ?? ''),
            'shopier_link' => trim($_POST['shopier_link'] ?? ''),
            'shopier_product_id' => trim($_POST['shopier_product_id'] ?? ''),
            'aktif_mi' => isset($_POST['aktif_mi']) ? 1 : 0,
            'anasayfada_goster' => isset($_POST['anasayfada_goster']) ? 1 : 0,
            'sonuc_aciklama_tarihi' => (!empty($_POST['sonuc_aciklama_tarihi'])) ? $_POST['sonuc_aciklama_tarihi'] : null
        ];

        try {
            if ($is_editing) {
                $sql = "UPDATE denemeler SET deneme_adi=:deneme_adi, tur=:tur, yazar_id=:yazar_id, kisa_aciklama=:kisa_aciklama, 
                        soru_sayisi=:soru_sayisi, cozum_linki=:cozum_linki, soru_kitapcik_dosyasi=:soru_kitapcik_dosyasi, 
                        resim_url=:resim_url, shopier_link=:shopier_link, shopier_product_id=:shopier_product_id, 
                        aktif_mi=:aktif_mi, anasayfada_goster=:anasayfada_goster, sonuc_aciklama_tarihi=:sonuc_aciklama_tarihi WHERE id=:id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array_merge($post_data, ['id' => $deneme_id]));
                set_admin_flash_message('success', 'Yayın başarıyla güncellendi.');
            } else {
                $sql = "INSERT INTO denemeler (deneme_adi, tur, yazar_id, kisa_aciklama, soru_sayisi, cozum_linki, soru_kitapcik_dosyasi, resim_url, shopier_link, shopier_product_id, aktif_mi, anasayfada_goster, sonuc_aciklama_tarihi) 
                        VALUES (:deneme_adi, :tur, :yazar_id, :kisa_aciklama, :soru_sayisi, :cozum_linki, :soru_kitapcik_dosyasi, :resim_url, :shopier_link, :shopier_product_id, :aktif_mi, :anasayfada_goster, :sonuc_aciklama_tarihi)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($post_data);
                set_admin_flash_message('success', 'Yeni yayın başarıyla eklendi.');
            }
            // DÜZELTME: Yönlendirme yolu 'admin/' ile başlamalı
            redirect('admin/manage_denemeler.php');
        } catch (PDOException $e) {
            set_admin_flash_message('error', "Kayıt Hatası: " . $e->getMessage());
        }
    }
}

include_once __DIR__ . '/../templates/admin_header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col">
        <h3 class="fw-bold mb-0"><?php echo $page_title; ?></h3>
        <p class="text-muted small">Hibrit yayın ayarları ve Shopier entegrasyonu.</p>
    </div>
</div>

<form action="edit_deneme.php<?php echo $is_editing ? '?id='.$deneme_id : ''; ?>" method="POST">
    <input type="hidden" name="csrf_token" value="<?php echo generate_admin_csrf_token(); ?>">

    <div class="row g-4">
        <!-- SOL: İçerik -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">YAYIN ADI</label>
                        <input type="text" name="deneme_adi" class="form-control input-theme" value="<?php echo escape_html($deneme_data['deneme_adi']); ?>" placeholder="Örn: ÖABT Türkçe TG-1" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold small">YAZAR / KAYNAK</label>
                            <select name="yazar_id" class="form-select input-theme">
                                <option value="">-- Platform Kaynağı --</option>
                                <?php foreach($yazarlar as $y): ?>
                                    <option value="<?php echo $y['id']; ?>" <?php echo $deneme_data['yazar_id'] == $y['id'] ? 'selected' : ''; ?>><?php echo escape_html($y['ad_soyad']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold small">ÜRÜN TÜRÜ</label>
                            <select name="tur" class="form-select input-theme">
                                <option value="deneme" <?php echo $deneme_data['tur'] == 'deneme' ? 'selected' : ''; ?>>Deneme Sınavı</option>
                                <option value="soru_bankasi" <?php echo $deneme_data['tur'] == 'soru_bankasi' ? 'selected' : ''; ?>>Soru Bankası</option>
                                <option value="diger" <?php echo $deneme_data['tur'] == 'diger' ? 'selected' : ''; ?>>Diğer Kaynak</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label fw-bold small">KISA AÇIKLAMA</label>
                        <textarea name="kisa_aciklama" class="form-control input-theme" rows="3"><?php echo escape_html($deneme_data['kisa_aciklama']); ?></textarea>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="mb-0 fw-bold text-primary">Dosya ve Görsel Bilgileri</h6>
                </div>
                <div class="card-body p-4">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold small">SORU PDF DOSYA ADI</label>
                            <input type="text" name="soru_kitapcik_dosyasi" class="form-control input-theme" value="<?php echo escape_html($deneme_data['soru_kitapcik_dosyasi']); ?>" placeholder="deneme1.pdf">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold small">ÇÖZÜM PDF DOSYA ADI</label>
                            <input type="text" name="cozum_linki" class="form-control input-theme" value="<?php echo escape_html($deneme_data['cozum_linki']); ?>" placeholder="cozum1.pdf">
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-bold small">KAPAK RESMİ URL</label>
                        <input type="text" name="resim_url" class="form-control input-theme" value="<?php echo escape_html($deneme_data['resim_url']); ?>" placeholder="https://...">
                    </div>
                </div>
            </div>
        </div>

        <!-- SAĞ: Ayarlar -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">SHOPIER ÜRÜN ID</label>
                        <input type="text" name="shopier_product_id" class="form-control input-theme" value="<?php echo escape_html($deneme_data['shopier_product_id']); ?>" placeholder="Örn: 12345678">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">SATIŞ LİNKİ (URL)</label>
                        <input type="text" name="shopier_link" class="form-control input-theme" value="<?php echo escape_html($deneme_data['shopier_link']); ?>" placeholder="https://shopier.com/...">
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-bold small">SORU SAYISI</label>
                        <input type="number" name="soru_sayisi" class="form-control input-theme" value="<?php echo $deneme_data['soru_sayisi']; ?>">
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <div class="mb-4">
                        <label class="form-label fw-bold small">SONUÇ AÇIKLAMA TARİHİ</label>
                        <?php 
                            $dt_val = "";
                            if($deneme_data['sonuc_aciklama_tarihi']) {
                                $dt_val = date('Y-m-d\TH:i', strtotime($deneme_data['sonuc_aciklama_tarihi']));
                            }
                        ?>
                        <input type="datetime-local" name="sonuc_aciklama_tarihi" class="form-control input-theme" value="<?php echo $dt_val; ?>">
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="aktif_mi" id="aktifSwitch" value="1" <?php echo $deneme_data['aktif_mi'] ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-bold small" for="aktifSwitch">SİSTEMDE AKTİF</label>
                    </div>

                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" name="anasayfada_goster" id="vitrinSwitch" value="1" <?php echo $deneme_data['anasayfada_goster'] ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-bold small" for="vitrinSwitch">ANASAYFADA GÖSTER</label>
                    </div>
                </div>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-admin-primary btn-lg py-3 shadow">
                    <i class="fas fa-save me-2"></i> Yayını Kaydet
                </button>
                <a href="manage_denemeler.php" class="btn btn-light py-2">İptal Et</a>
            </div>
        </div>
    </div>
</form>

<?php include_once __DIR__ . '/../templates/admin_footer.php'; ?>