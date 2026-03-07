<?php
// admin/manage_kodlar.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAdminLogin();
$page_title = "Erişim Kodlarını Yönet";

// Aktif denemeleri çek (Form için)
$denemeler = $pdo->query("SELECT id, deneme_adi FROM denemeler WHERE aktif_mi = 1 ORDER BY id DESC")->fetchAll();

// Filtreleme
$filter_id = filter_input(INPUT_GET, 'deneme_id', FILTER_VALIDATE_INT);
$where = $filter_id ? "WHERE urun_id = ?" : "";
$params = $filter_id ? [$filter_id] : [];

// Kodları Listele
$stmt_list = $pdo->prepare("
    SELECT ek.*, d.deneme_adi, k.ad_soyad as kullanan 
    FROM erisim_kodlari ek
    JOIN denemeler d ON ek.urun_id = d.id
    LEFT JOIN kullanicilar k ON ek.kullanici_id = k.id
    $where
    ORDER BY ek.id DESC LIMIT 100
");
$stmt_list->execute($params);
$codes = $stmt_list->fetchAll();

include_once __DIR__ . '/../templates/admin_header.php';
?>

<div class="row g-4">
    <!-- Sol Kolon: Kod Üretme -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold text-theme-primary">Toplu Kod Üret</h6>
            </div>
            <div class="card-body p-4">
                <form action="generate_codes.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_admin_csrf_token(); ?>">
                    <input type="hidden" name="islem_tipi" value="toplu">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold">HEDEF YAYIN</label>
                        <select name="deneme_id" class="form-select input-theme" required>
                            <option value="">-- Seçiniz --</option>
                            <?php foreach($denemeler as $d): ?>
                                <option value="<?php echo $d['id']; ?>"><?php echo escape_html($d['deneme_adi']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label small fw-bold">ADET</label>
                            <input type="number" name="adet" class="form-control input-theme" value="50" min="1" max="1000">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label small fw-bold">UZUNLUK</label>
                            <input type="number" name="uzunluk" class="form-control input-theme" value="8" min="4" max="12">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-theme-primary w-100 py-2">
                        <i class="fas fa-magic me-2"></i> Kodları Oluştur
                    </button>
                </form>
            </div>
        </div>
        
        <div class="card border-0 shadow-sm rounded-4 bg-dark text-white p-4">
            <h6 class="fw-bold mb-3 text-warning">İpucu</h6>
            <p class="small opacity-75 mb-0">Ürettiğiniz kodları Excel formatında dışa aktarmak için filtrelemeyi kullandıktan sonra "Dışa Aktar" butonuna basın.</p>
        </div>
    </div>

    <!-- Sağ Kolon: Liste -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-theme-primary">Mevcut Kodlar (Son 100)</h6>
                <form class="d-flex gap-2">
                    <select name="deneme_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">Tüm Yayınlar</option>
                        <?php foreach($denemeler as $d): ?>
                            <option value="<?php echo $d['id']; ?>" <?php echo $filter_id == $d['id'] ? 'selected' : ''; ?>><?php echo $d['deneme_adi']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th class="ps-4">Kod</th>
                                <th>Yayın</th>
                                <th class="text-center">Durum</th>
                                <th class="text-end pe-4">Kullanan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($codes)): ?>
                                <tr><td colspan="4" class="text-center py-5">Seçili yayına ait kod bulunamadı.</td></tr>
                            <?php else: ?>
                                <?php foreach($codes as $code): ?>
                                <tr>
                                    <td class="ps-4"><code class="text-theme-primary fw-bold fs-6"><?php echo $code['kod']; ?></code></td>
                                    <td><div class="small fw-medium"><?php echo escape_html($code['deneme_adi']); ?></div></td>
                                    <td class="text-center">
                                        <?php if($code['kullanici_id']): ?>
                                            <span class="badge bg-danger rounded-pill px-3">Kullanıldı</span>
                                        <?php else: ?>
                                            <span class="badge bg-success rounded-pill px-3">Müsait</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4 small"><?php echo $code['kullanan'] ? escape_html($code['kullanan']) : '-'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../templates/admin_footer.php'; ?>