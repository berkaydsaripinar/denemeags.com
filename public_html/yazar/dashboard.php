<?php
// yazar/dashboard.php - Profesyonel Yazar Yönetim Merkezi
$page_title = "Genel Bakış";
require_once __DIR__ . '/includes/author_header.php';

try {
    // 1. Finansal Özet (Toplam Brüt, Net ve Satış Adedi)
    $stmt_fin = $pdo->prepare("
        SELECT 
            SUM(tutar_brut) as ciro,
            SUM(yazar_payi) as net_hakedis,
            COUNT(id) as satis_sayisi
        FROM satis_loglari WHERE yazar_id = ?
    ");
    $stmt_fin->execute([$yid]);
    $fin = $stmt_fin->fetch();

    // 2. Yayınlanan Ürün Sayısı
    $total_prods = $pdo->prepare("SELECT COUNT(*) FROM denemeler WHERE yazar_id = ?");
    $total_prods->execute([$yid]);
    $prod_count = $total_prods->fetchColumn();

    // 3. Son Satış Hareketleri
    $stmt_recent = $pdo->prepare("
        SELECT sl.*, d.deneme_adi 
        FROM satis_loglari sl
        JOIN denemeler d ON sl.deneme_id = d.id
        WHERE sl.yazar_id = ? 
        ORDER BY sl.tarih DESC LIMIT 5
    ");
    $stmt_recent->execute([$yid]);
    $recent_sales = $stmt_recent->fetchAll();

} catch (Exception $e) { 
    error_log("Dashboard hatası: " . $e->getMessage());
    $recent_sales = []; 
}
?>

<div class="d-flex justify-content-between align-items-center mb-5 fade-in">
    <div>
        <h2 class="fw-bold text-dark mb-1">Hoş Geldiniz, <?php echo escape_html($yazar_adi); ?></h2>
        <p class="text-muted mb-0">Platformdaki içeriklerinizin güncel performans özeti.</p>
    </div>
    <div class="text-end d-none d-md-block">
        <a href="manage_products.php" class="btn btn-coral shadow-sm">
            <i class="fas fa-plus me-2"></i>Yeni Yayın Ekle
        </a>
    </div>
</div>

<!-- İstatistik Kartları -->
<div class="row g-4 mb-5">
    <div class="col-md-3 col-6">
        <div class="stat-card border-0 shadow-sm p-4 text-center">
            <div class="stat-icon bg-primary bg-opacity-10 text-primary mx-auto mb-3"><i class="fas fa-shopping-bag"></i></div>
            <div class="text-muted small fw-bold mb-1">TOPLAM SATIŞ</div>
            <div class="h3 fw-black mb-0"><?php echo (int)($fin['satis_sayisi'] ?? 0); ?></div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card border-0 shadow-sm p-4 text-center">
            <div class="stat-icon bg-success bg-opacity-10 text-success mx-auto mb-3"><i class="fas fa-lira-sign"></i></div>
            <div class="text-muted small fw-bold mb-1">BRÜT CIRO</div>
            <div class="h3 fw-black mb-0"><?php echo number_format((float)($fin['ciro'] ?? 0), 2, ',', '.'); ?> ₺</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card border-bottom border-coral border-4 shadow-sm p-4 text-center">
            <div class="stat-icon bg-danger bg-opacity-10 text-danger mx-auto mb-3"><i class="fas fa-wallet"></i></div>
            <div class="text-muted small fw-bold mb-1">TOPLAM HAKEDİŞ</div>
            <div class="h3 fw-black text-success mb-0"><?php echo number_format((float)($fin['net_hakedis'] ?? 0), 2, ',', '.'); ?> ₺</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card border-0 shadow-sm p-4 text-center">
            <div class="stat-icon bg-info bg-opacity-10 text-info mx-auto mb-3"><i class="fas fa-book-open"></i></div>
            <div class="text-muted small fw-bold mb-1">AKTİF ESER</div>
            <div class="h3 fw-black mb-0"><?php echo (int)$prod_count; ?></div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Son Satışlar Tablosu -->
    <div class="col-lg-8">
        <div class="card card-custom border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="fas fa-history me-2 text-primary"></i>Son Satış Hareketleri</h6>
                <a href="analytics.php" class="btn btn-sm btn-light rounded-pill px-3">Tümünü Gör</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small text-uppercase">
                            <tr>
                                <th class="ps-4">Yayın Adı</th>
                                <th class="text-center">Brüt</th>
                                <th class="text-center">Payınız</th>
                                <th class="pe-4 text-end">Tarih</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($recent_sales)): ?>
                                <tr><td colspan="4" class="text-center py-5 text-muted small italic">Henüz bir satış kaydı gerçekleşmedi.</td></tr>
                            <?php else: ?>
                                <?php foreach($recent_sales as $s): ?>
                                <tr>
                                    <td class="ps-4 fw-bold small text-dark"><?php echo escape_html($s['deneme_adi']); ?></td>
                                    <td class="text-center small"><?php echo number_format($s['tutar_brut'], 2); ?> ₺</td>
                                    <td class="text-center fw-bold text-success small">+<?php echo number_format($s['yazar_payi'], 2); ?> ₺</td>
                                    <td class="pe-4 text-end small text-muted"><?php echo date('d.m.Y H:i', strtotime($s['tarih'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Bilgi Panosu / Hızlı Duyuru -->
    <div class="col-lg-4">
        <div class="card card-custom border-0 shadow-sm p-4 bg-white mb-4">
            <h6 class="fw-bold mb-3">Finansal Notlar</h6>
            <div class="alert alert-info border-0 rounded-4 small mb-4">
                <i class="fas fa-info-circle me-2"></i>Kazançlarınız, bakiyeniz 100 ₺ barajını aştığında talep edilebilir hale gelir.
            </div>
            <div class="d-grid">
                <a href="earnings.php" class="btn btn-outline-primary py-2 rounded-pill fw-bold small">
                    <i class="fas fa-hand-holding-usd me-2"></i>Ödeme Taleplerine Git
                </a>
            </div>
        </div>

        <div class="card bg-dark text-white border-0 shadow-sm p-4 rounded-4">
            <h6 class="fw-bold mb-2">Platform Desteği</h6>
            <p class="small opacity-75 mb-0">Ürün eklerken veya teknik bir konuda sorun yaşarsanız bize her zaman ulaşabilirsiniz.</p>
            <div class="mt-3">
                <code class="text-warning">destek@denemeags.com</code>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/author_footer.php'; ?>