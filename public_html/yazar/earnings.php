<?php
// yazar/earnings.php - Bütünleşik ve Profesyonel Finans Yönetimi
$page_title = "Kazançlar ve Ödemeler";
require_once __DIR__ . '/includes/author_header.php';

// --- DEĞİŞKENLERİ VARSAYILANLARLA BAŞLAT ---
// Hataları önlemek için değişkenleri en başta tanımlıyoruz.
$fin = [
    'toplam_brut' => 0,
    'toplam_odenmis' => 0,
    'odenebilir' => 0,
    'bekleyen_hakedis' => 0
];
$odenebilir = 0;
$sales = [];

// --- VERİ ÇEKME ---
try {
    // 1. Genel Finansal Özet (Toplam, Ödenen, Ödenebilir, Bekleyen)
    $stmt_summary = $pdo->prepare("
        SELECT 
            COALESCE(SUM(yazar_payi), 0) as toplam_brut,
            (SELECT COALESCE(SUM(tutar), 0) FROM yazar_odemeleri WHERE yazar_id = ?) as toplam_odenmis,
            (SELECT COALESCE(SUM(yazar_payi), 0) FROM satis_loglari WHERE yazar_id = ? AND (yazar_odeme_durumu IS NULL OR yazar_odeme_durumu = 'beklemede') AND tarih <= DATE_SUB(NOW(), INTERVAL 14 DAY)) as odenebilir,
            (SELECT COALESCE(SUM(yazar_payi), 0) FROM satis_loglari WHERE yazar_id = ? AND (yazar_odeme_durumu IS NULL OR yazar_odeme_durumu = 'beklemede') AND tarih > DATE_SUB(NOW(), INTERVAL 14 DAY)) as bekleyen_hakedis
        FROM satis_loglari WHERE yazar_id = ?
    ");
    $stmt_summary->execute([$yid, $yid, $yid, $yid, $yid]);
    $db_fin = $stmt_summary->fetch();
    
    if ($db_fin) {
        $fin = $db_fin;
    }

    // Değerlerin null olmadığından emin olalım (number_format hata vermemesi için)
    $fin['toplam_brut'] = (float)($fin['toplam_brut'] ?? 0);
    $fin['toplam_odenmis'] = (float)($fin['toplam_odenmis'] ?? 0);
    $fin['odenebilir'] = (float)($fin['odenebilir'] ?? 0);
    $fin['bekleyen_hakedis'] = (float)($fin['bekleyen_hakedis'] ?? 0);

    $odenebilir = $fin['odenebilir'];

    // 2. Son Satış Hareketleri
    $stmt_sales = $pdo->prepare("
        SELECT sl.*, d.deneme_adi 
        FROM satis_loglari sl 
        JOIN denemeler d ON sl.deneme_id = d.id 
        WHERE sl.yazar_id = ? ORDER BY sl.tarih DESC LIMIT 8
    ");
    $stmt_sales->execute([$yid]);
    $sales = $stmt_sales->fetchAll();

} catch (Exception $e) { 
    error_log("Hakediş sayfası hatası: " . $e->getMessage());
}
?>

<div class="d-flex justify-content-between align-items-center mb-5 fade-in">
    <div>
        <h2 class="fw-bold text-dark mb-1">Finansal Durum</h2>
        <p class="text-muted mb-0">Hakedişleriniz satış tarihinden 14 gün sonra otomatik ödeme listesine alınır.</p>
    </div>
    <div class="text-end">
        <span class="badge bg-success-subtle text-success px-3 py-2">Otomatik Ödeme Aktif</span>
    </div>
</div>

<!-- Özet Kartları -->
<div class="row g-4 mb-5">
    <div class="col-md-4">
        <div class="card card-custom border-bottom border-success border-4 p-4 shadow-sm h-100">
            <div class="d-flex justify-content-between mb-3">
                <span class="text-muted small fw-bold">ÖDENEBİLİR BAKİYE</span>
                <div class="bg-success bg-opacity-10 text-success p-2 rounded-3"><i class="fas fa-wallet"></i></div>
            </div>
            <h2 class="fw-black text-dark mb-1"><?php echo number_format($odenebilir, 2, ',', '.'); ?> ₺</h2>
            <p class="text-muted small mb-0">14 gününü dolduran hakedişleriniz.</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card card-custom border-bottom border-primary border-4 p-4 shadow-sm h-100">
            <div class="d-flex justify-content-between mb-3">
                <span class="text-muted small fw-bold">TOPLAM ÖDENEN</span>
                <div class="bg-primary bg-opacity-10 text-primary p-2 rounded-3"><i class="fas fa-check-circle"></i></div>
            </div>
            <h2 class="fw-bold text-dark mb-1"><?php echo number_format((float)$fin['toplam_odenmis'], 2, ',', '.'); ?> ₺</h2>
            <p class="text-muted small mb-0">Bugüne kadar hesabınıza yatan toplam tutar.</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card card-custom border-bottom border-warning border-4 p-4 shadow-sm h-100">
            <div class="d-flex justify-content-between mb-3">
                <span class="text-muted small fw-bold">BEKLEYEN HAKEDİŞ</span>
                <div class="bg-warning bg-opacity-10 text-warning p-2 rounded-3"><i class="fas fa-clock"></i></div>
            </div>
            <h2 class="fw-bold text-dark mb-1"><?php echo number_format((float)$fin['bekleyen_hakedis'], 2, ',', '.'); ?> ₺</h2>
            <p class="text-muted small mb-0">14 gününü doldurması beklenen tutar.</p>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Otomatik Ödeme Bilgisi -->
    <div class="col-lg-7">
        <div class="card card-custom overflow-hidden border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="mb-0 fw-bold"><i class="fas fa-history me-2 text-primary"></i>Ödeme Takvimi</h6>
            </div>
            <div class="card-body p-4">
                <div class="alert alert-info mb-0">
                    Hakedişleriniz satış tarihinden 14 gün sonra ödeme listesine alınır. Ödemeler, belirlenen periyotlarda otomatik olarak işlenir ve IBAN hesabınıza aktarılır.
                </div>
            </div>
        </div>
    </div>

    <!-- Son Satışlar -->
    <div class="col-lg-5">
        <div class="card card-custom border-0 shadow-sm overflow-hidden">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="mb-0 fw-bold text-success"><i class="fas fa-shopping-cart me-2"></i>Son Satış Hakedişleri</h6>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php if(empty($sales)): ?>
                        <li class="list-group-item text-center py-5 text-muted small">Henüz satış gerçekleşmedi.</li>
                    <?php else: ?>
                        <?php foreach($sales as $s): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3 px-4">
                                <div>
                                    <div class="fw-bold text-dark small mb-1"><?php echo escape_html($s['deneme_adi']); ?></div>
                                    <div class="text-muted" style="font-size: 0.65rem;">
                                        <i class="far fa-clock me-1"></i><?php echo date('d.m.Y H:i', strtotime($s['tarih'])); ?>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <span class="fw-black text-success">+<?php echo number_format((float)$s['yazar_payi'], 2, ',', '.'); ?> ₺</span>
                                    <div class="text-muted" style="font-size: 0.6rem;">Brüt: <?php echo number_format((float)$s['tutar_brut'], 2, ',', '.'); ?> ₺</div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="card-footer bg-light border-0 py-3 text-center">
                <a href="analytics.php" class="text-decoration-none small fw-bold">Tüm Satış Analizlerini Gör <i class="fas fa-chevron-right ms-1"></i></a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/author_footer.php'; ?>