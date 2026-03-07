<?php
// yazar/manage_products.php - Temiz Liste Görünümü
$page_title = "Yayınlarım";
require_once __DIR__ . '/includes/author_header.php';

try {
    $stmt = $pdo->prepare("
        SELECT * FROM denemeler 
        WHERE yazar_id = ? 
        ORDER BY id DESC
    ");
    $stmt->execute([$yid]);
    $products = $stmt->fetchAll();
} catch (PDOException $e) { $products = []; }
?>

<div class="d-flex justify-content-between align-items-center mb-5">
    <div>
        <h2 class="fw-bold text-dark mb-1">Yayın Kütüphaneniz</h2>
        <p class="text-muted mb-0">Yüklediğiniz tüm dijital içeriklerin durumunu buradan izleyin.</p>
    </div>
    <a href="add_product.php" class="btn-coral text-decoration-none shadow">
        <i class="fas fa-plus me-2"></i>Yeni Yayın Yükle
    </a>
</div>

<div class="card card-custom overflow-hidden">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4 py-3">Ürün Bilgisi</th>
                        <th>Tür</th>
                        <th>Fiyat</th>
                        <th>Durum</th>
                        <th class="pe-4 text-end">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($products)): ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">Henüz yüklenmiş bir yayınınız bulunmuyor.</td></tr>
                    <?php else: ?>
                        <?php foreach($products as $p): ?>
                        <tr>
                            <td class="ps-4 py-3">
                                <div class="d-flex align-items-center">
                                    <div class="bg-light rounded-3 p-2 me-3">
                                        <i class="fas <?php echo ($p['tur'] == 'deneme') ? 'fa-file-alt' : 'fa-book'; ?> text-primary fa-lg"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark small"><?php echo escape_html($p['deneme_adi']); ?></div>
                                        <div class="text-muted" style="font-size: 0.65rem;">Eklenme: <?php echo date('d.m.Y', strtotime($p['olusturulma_tarihi'])); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="small text-muted text-uppercase fw-bold"><?php echo $p['tur']; ?></span>
                            </td>
                            <td class="fw-bold text-primary"><?php echo number_format($p['fiyat'], 2); ?> ₺</td>
                            <td>
                                <?php if($p['aktif_mi']): ?>
                                    <span class="badge bg-success-subtle text-success rounded-pill px-3">Yayında</span>
                                <?php else: ?>
                                    <span class="badge bg-warning-subtle text-warning rounded-pill px-3">Onay Bekliyor</span>
                                <?php endif; ?>
                            </td>
                            <td class="pe-4 text-end">
                                <div class="btn-group shadow-sm rounded-3">
                                    <a href="edit_product.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-white border" title="Düzenle">
                                        <i class="fas fa-edit text-primary"></i>
                                    </a>
                                    <a href="analytics.php?pid=<?php echo $p['id']; ?>" class="btn btn-sm btn-white border" title="Analiz">
                                        <i class="fas fa-chart-bar text-info"></i>
                                    </a>
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

<?php require_once __DIR__ . '/includes/author_footer.php'; ?>