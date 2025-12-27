<?php
// admin/manage_denemeler.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAdminLogin();
$page_title = "Yayınları Yönet";

$authorized_ids = getAuthorizedDenemeIds();

// Denemeleri çek (Yazar bilgisiyle beraber)
$sql = "SELECT d.*, y.ad_soyad as yazar_adi 
        FROM denemeler d 
        LEFT JOIN yazarlar y ON d.yazar_id = y.id";
$params = [];

if ($authorized_ids !== null) {
    if (empty($authorized_ids)) {
        $denemeler = [];
    } else {
        $in_clause = implode(',', array_fill(0, count($authorized_ids), '?'));
        $sql .= " WHERE d.id IN ($in_clause)";
        $params = $authorized_ids;
    }
}
$sql .= " ORDER BY d.id DESC";

try {
    if (!isset($denemeler)) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $denemeler = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    set_admin_flash_message('error', "Hata: " . $e->getMessage());
    $denemeler = [];
}

include_once __DIR__ . '/../templates/admin_header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col">
        <h3 class="fw-bold mb-0">Yayın Kütüphanesi</h3>
        <p class="text-muted small">Tüm deneme sınavları ve soru bankalarının yönetimi.</p>
    </div>
    <div class="col-auto">
        <?php if (isSuperAdmin()): ?>
            <a href="edit_deneme.php" class="btn btn-admin-primary">
                <i class="fas fa-plus me-2"></i> Yeni Yayın Ekle
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 admin-table">
                <thead>
                    <tr>
                        <th class="ps-4">Ürün Bilgisi</th>
                        <th>Yazar / Kaynak</th>
                        <th>Tür</th>
                        <th>Soru</th>
                        <th>Durum</th>
                        <th class="text-end pe-4">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($denemeler)): ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted">Henüz yayınlanmış bir içerik bulunmuyor.</td></tr>
                    <?php else: ?>
                        <?php foreach ($denemeler as $deneme): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center">
                                    <div class="bg-light rounded-3 p-2 me-3 text-primary">
                                        <i class="fas <?php echo $deneme['tur'] == 'deneme' ? 'fa-file-alt' : 'fa-book'; ?> fa-lg"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold"><?php echo escape_html($deneme['deneme_adi']); ?></div>
                                        <div class="text-muted" style="font-size: 0.75rem;">ID: #<?php echo $deneme['id']; ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="small fw-medium text-dark"><?php echo escape_html($deneme['yazar_adi'] ?? 'Platform Kaynağı'); ?></div>
                            </td>
                            <td>
                                <?php if($deneme['tur'] == 'deneme'): ?>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3">Deneme</span>
                                <?php else: ?>
                                    <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-3">Soru Bankası</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="fw-bold"><?php echo $deneme['soru_sayisi']; ?></span></td>
                            <td>
                                <?php if ($deneme['aktif_mi']): ?>
                                    <span class="text-success small fw-bold"><i class="fas fa-check-circle me-1"></i> Yayında</span>
                                <?php else: ?>
                                    <span class="text-danger small fw-bold"><i class="fas fa-times-circle me-1"></i> Pasif</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <div class="btn-group">
                                    <?php if (isSuperAdmin()): ?>
                                        <a href="edit_deneme.php?id=<?php echo $deneme['id']; ?>" class="btn btn-sm btn-outline-primary" title="Düzenle">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="manage_cevaplar.php?deneme_id=<?php echo $deneme['id']; ?>" class="btn btn-sm btn-outline-info" title="Cevap Anahtarı">
                                        <i class="fas fa-key"></i>
                                    </a>
                                    <form action="recalculate_scores.php" method="POST" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_admin_csrf_token(); ?>">
                                        <input type="hidden" name="deneme_id" value="<?php echo $deneme['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-warning" title="Yeniden Hesapla" 
                                                onclick="return confirm('Tüm sonuçlar yeniden hesaplanacak. Emin misiniz?')">
                                            <i class="fas fa-sync"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../templates/admin_footer.php'; ?>