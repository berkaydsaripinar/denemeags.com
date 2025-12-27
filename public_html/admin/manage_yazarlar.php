<?php
// admin/manage_yazarlar.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAdminLogin();

$page_title = "Yazar & Hakediş Yönetimi";
$csrf_token = generate_admin_csrf_token();
$is_super = isSuperAdmin();

// --- YAZAR EKLEME İŞLEMİ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_yazar'])) {
    if (verify_admin_csrf_token($_POST['csrf_token'])) {
        $ad = trim($_POST['ad_soyad']);
        $email = trim($_POST['email']);
        $sifre = $_POST['password'];
        $komisyon = (float)$_POST['komisyon_orani'];

        if ($ad && $email && $sifre) {
            try {
                $hash = password_hash($sifre, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO yazarlar (ad_soyad, email, sifre_hash, komisyon_orani, aktif_mi) VALUES (?, ?, ?, ?, 1)");
                $stmt->execute([$ad, $email, $hash, $komisyon]);
                set_admin_flash_message('success', 'Yazar başarıyla eklendi. Panel bilgilerini iletebilirsiniz.');
            } catch (PDOException $e) {
                set_admin_flash_message('error', 'Bu e-posta adresi zaten kullanımda.');
            }
        }
    }
    // DÜZELTME: Yönlendirme yolu admin/ ile başlamalı
    redirect('admin/manage_yazarlar.php');
}

// --- KOMİSYON GÜNCELLEME (SADECE SUPERADMIN) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_commission'])) {
    if ($is_super && verify_admin_csrf_token($_POST['csrf_token'])) {
        $yid = (int)$_POST['yazar_id'];
        $oran = (float)$_POST['komisyon_orani'];
        $stmt = $pdo->prepare("UPDATE yazarlar SET komisyon_orani = ? WHERE id = ?");
        $stmt->execute([$oran, $yid]);
        set_admin_flash_message('success', 'Komisyon oranı güncellendi.');
    } else {
        set_admin_flash_message('error', 'Yetkisiz işlem.');
    }
    // DÜZELTME: Yönlendirme yolu admin/ ile başlamalı
    redirect('admin/manage_yazarlar.php');
}

// Durum Değiştirme
if (isset($_GET['action']) && $_GET['action'] === 'toggle' && isset($_GET['id'])) {
    $pdo->prepare("UPDATE yazarlar SET aktif_mi = 1 - aktif_mi WHERE id = ?")->execute([(int)$_GET['id']]);
    set_admin_flash_message('success', 'Yazar durumu güncellendi.');
    // DÜZELTME: Yönlendirme yolu admin/ ile başlamalı
    redirect('admin/manage_yazarlar.php');
}

// Veri Çekme
$yazarlar = $pdo->query("
    SELECT y.*, 
    COALESCE(SUM(sl.yazar_payi), 0) as toplam_hakedis,
    (SELECT COUNT(*) FROM denemeler WHERE yazar_id = y.id) as urun_sayisi
    FROM yazarlar y
    LEFT JOIN satis_loglari sl ON y.id = sl.yazar_id
    GROUP BY y.id ORDER BY y.id DESC
")->fetchAll();

include_once __DIR__ . '/../templates/admin_header.php';
?>

<div class="row mb-4">
    <div class="col">
        <h3 class="fw-bold">Yazar & Finans Kontrolü</h3>
        <p class="text-muted small">Platform yazarlarını ve hakedişlerini buradan yönetin.</p>
    </div>
    <div class="col-auto">
        <button class="btn btn-admin-primary px-4" data-bs-toggle="modal" data-bs-target="#addYazarModal">
            <i class="fas fa-user-plus me-2"></i> Yeni Yazar Tanımla
        </button>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 admin-table text-center">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4 text-start">Yazar Bilgisi</th>
                        <th>Yayın</th>
                        <th>Komisyon Oranı</th>
                        <th>Toplam Hakediş</th>
                        <th>Durum</th>
                        <th class="pe-4 text-end">Aksiyon</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($yazarlar)): ?>
                        <tr><td colspan="6" class="py-4 text-muted">Kayıtlı yazar bulunamadı.</td></tr>
                    <?php else: ?>
                        <?php foreach($yazarlar as $y): ?>
                        <tr>
                            <td class="ps-4 text-start">
                                <div class="fw-bold"><?php echo escape_html($y['ad_soyad']); ?></div>
                                <div class="text-muted small"><?php echo $y['email']; ?></div>
                            </td>
                            <td><span class="badge bg-light text-dark px-3"><?php echo $y['urun_sayisi']; ?> Eser</span></td>
                            <td>
                                <form action="manage_yazarlar.php" method="POST" class="d-flex align-items-center justify-content-center">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="yazar_id" value="<?php echo $y['id']; ?>">
                                    <div class="input-group input-group-sm" style="width: 90px;">
                                        <input type="number" name="komisyon_orani" class="form-control text-center fw-bold" value="<?php echo $y['komisyon_orani']; ?>" <?php echo !$is_super ? 'disabled' : ''; ?>>
                                        <span class="input-group-text">%</span>
                                    </div>
                                    <?php if($is_super): ?>
                                        <button type="submit" name="update_commission" class="btn btn-link text-success p-0 ms-2"><i class="fas fa-check-circle fa-lg"></i></button>
                                    <?php endif; ?>
                                </form>
                            </td>
                            <td class="fw-bold text-success"><?php echo number_format($y['toplam_hakedis'], 2); ?> ₺</td>
                            <td>
                                <?php echo $y['aktif_mi'] ? '<span class="badge bg-success rounded-pill">Aktif</span>' : '<span class="badge bg-danger rounded-pill">Pasif</span>'; ?>
                            </td>
                            <td class="pe-4 text-end">
                                <a href="manage_yazarlar.php?action=toggle&id=<?php echo $y['id']; ?>" class="btn btn-sm <?php echo $y['aktif_mi'] ? 'btn-outline-danger' : 'btn-outline-success'; ?> px-3">
                                    <?php echo $y['aktif_mi'] ? 'Durdur' : 'Aktif Et'; ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Yeni Yazar Modal -->
<div class="modal fade" id="addYazarModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-0 pb-0"><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-4">
                <h4 class="fw-bold text-center mb-4">Yeni Yazar Kaydı</h4>
                <form method="POST" action="manage_yazarlar.php">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="add_yazar" value="1">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">AD SOYAD</label>
                        <input type="text" name="ad_soyad" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">E-POSTA ADRESİ</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">PANEL ŞİFRESİ</label>
                        <input type="password" name="password" class="form-control" placeholder="En az 6 karakter" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-bold">VARSAYILAN KOMİSYON (%)</label>
                        <input type="number" name="komisyon_orani" class="form-control" value="70">
                    </div>
                    <button type="submit" class="btn btn-admin-primary w-100 py-2 fw-bold">KAYDET VE OLUŞTUR</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../templates/admin_footer.php'; ?>