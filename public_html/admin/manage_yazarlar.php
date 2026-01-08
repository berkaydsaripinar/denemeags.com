<?php
// admin/manage_yazarlar.php - Yazar Performans ve Finans Takip Merkezi
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAdminLogin();

$page_title = "Yazar & Hakediş Yönetimi";
$csrf_token = generate_admin_csrf_token();

// --- AKSİYON: MANUEL ÖDEME KAYDI ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manual_payment'])) {
    if (isSuperAdmin() && verify_admin_csrf_token($_POST['csrf_token'])) {
        $yid = (int)$_POST['yazar_id'];
        $tutar = (float)$_POST['tutar'];
        $not = trim($_POST['notlar']);

        if ($tutar > 0) {
            $stmt = $pdo->prepare("INSERT INTO yazar_odemeleri (yazar_id, tutar, notlar) VALUES (?, ?, ?)");
            $stmt->execute([$yid, $tutar, "Manuel Ödeme: $not"]);
            set_admin_flash_message('success', 'Ödeme kaydı başarıyla oluşturuldu.');
        }
    }
    redirect('admin/manage_yazarlar.php');
}

// --- VERİ ÇEKME ---
// Taleplerle senkronize edilmiş yazar listesi
$yazarlar = $pdo->query("
    SELECT y.*, 
    COALESCE((SELECT SUM(yazar_payi) FROM satis_loglari WHERE yazar_id = y.id), 0) as toplam_hakedis,
    COALESCE((SELECT SUM(tutar) FROM yazar_odemeleri WHERE yazar_id = y.id), 0) as toplam_odenmis,
    COALESCE((SELECT SUM(tutar) FROM yazar_odeme_talepleri WHERE yazar_id = y.id AND durum = 'beklemede'), 0) as bekleyen_talep,
    (SELECT COUNT(*) FROM denemeler WHERE yazar_id = y.id) as urun_sayisi
    FROM yazarlar y
    ORDER BY y.id DESC
")->fetchAll();

include_once __DIR__ . '/../templates/admin_header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col">
        <h3 class="fw-bold mb-0">Yazar ve Finans Takibi</h3>
        <p class="text-muted small">Yazarların hakedişlerini, ödemelerini ve bekleyen taleplerini yönetin.</p>
    </div>
    <div class="col-auto">
        <a href="manage_yazar_talepleri.php" class="btn btn-warning shadow-sm position-relative">
            <i class="fas fa-hand-holding-usd me-2"></i> Bekleyen Talepler
            <?php 
            $pending_count = $pdo->query("SELECT COUNT(*) FROM yazar_odeme_talepleri WHERE durum='beklemede'")->fetchColumn();
            if($pending_count > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?php echo $pending_count; ?></span>
            <?php endif; ?>
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 admin-table text-center">
                <thead class="table-light small text-uppercase">
                    <tr>
                        <th class="ps-4 text-start">Yazar</th>
                        <th>Toplam Hakediş</th>
                        <th>Yapılan Ödeme</th>
                        <th class="text-warning">Bekleyen Talep</th>
                        <th class="text-danger">Kalan Bakiye</th>
                        <th class="pe-4 text-end">Aksiyon</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($yazarlar as $y): 
                        $net_kalan = $y['toplam_hakedis'] - $y['toplam_odenmis'];
                    ?>
                    <tr>
                        <td class="ps-4 text-start">
                            <div class="fw-bold text-dark"><?php echo escape_html($y['ad_soyad']); ?></div>
                            <div class="text-muted" style="font-size: 0.65rem;">IBAN: <?php echo $y['iban_bilgisi'] ?: 'Belirtilmedi'; ?></div>
                        </td>
                        <td class="fw-bold"><?php echo number_format($y['toplam_hakedis'], 2); ?> ₺</td>
                        <td class="text-success small"><?php echo number_format($y['toplam_odenmis'], 2); ?> ₺</td>
                        <td class="text-warning fw-bold"><?php echo number_format($y['bekleyen_talep'], 2); ?> ₺</td>
                        <td class="text-danger fw-black"><?php echo number_format($net_kalan, 2); ?> ₺</td>
                        <td class="pe-4 text-end">
                            <button class="btn btn-sm btn-outline-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#manualPayModal<?php echo $y['id']; ?>">
                                Ödeme Kaydı
                            </button>
                            
                            <!-- Manuel Ödeme Modalı -->
                            <div class="modal fade text-start" id="manualPayModal<?php echo $y['id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content border-0 shadow-lg rounded-4">
                                        <div class="modal-body p-4">
                                            <h6 class="fw-bold mb-3"><?php echo $y['ad_soyad']; ?> için Ödeme Girişi</h6>
                                            <form method="POST">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                <input type="hidden" name="yazar_id" value="<?php echo $y['id']; ?>">
                                                <input type="hidden" name="manual_payment" value="1">
                                                
                                                <div class="mb-3">
                                                    <label class="form-label small fw-bold">ÖDENEN TUTAR (₺)</label>
                                                    <input type="number" step="0.01" name="tutar" class="form-control" value="<?php echo $net_kalan; ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label small fw-bold">NOT (DEKONT / AÇIKLAMA)</label>
                                                    <input type="text" name="notlar" class="form-control" placeholder="EFT No / Tarih">
                                                </div>
                                                <button type="submit" class="btn btn-primary w-100">KAYDI İŞLE</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../templates/admin_footer.php'; ?>