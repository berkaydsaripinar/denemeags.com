<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAdminLogin();
if (!isSuperAdmin()) {
    set_admin_flash_message('error', 'Bu sayfa için Süper Admin yetkisi gerekir.');
    redirect('dashboard.php');
}

$page_title = 'Kar / Zarar Raporu';
$period = $_GET['period'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
    $period = date('Y-m');
}

$summary = [
    'sales' => 0.0,
    'discount' => 0.0,
    'vat' => 0.0,
    'author' => 0.0,
    'influencer' => 0.0,
    'gateway' => 0.0,
    'expense' => 0.0,
];
$topProducts = [];

try {
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN hareket_tipi = 'sale' THEN tutar ELSE 0 END), 0) AS sales,
            COALESCE(SUM(CASE WHEN hareket_tipi = 'discount' THEN tutar ELSE 0 END), 0) AS discount,
            COALESCE(SUM(CASE WHEN hareket_tipi = 'vat' THEN tutar ELSE 0 END), 0) AS vat,
            COALESCE(SUM(CASE WHEN hareket_tipi = 'author_commission' THEN tutar ELSE 0 END), 0) AS author,
            COALESCE(SUM(CASE WHEN hareket_tipi = 'influencer_commission' THEN tutar ELSE 0 END), 0) AS influencer,
            COALESCE(SUM(CASE WHEN hareket_tipi = 'gateway_fee' THEN tutar ELSE 0 END), 0) AS gateway
        FROM muhasebe_hareketleri
        WHERE DATE_FORMAT(hareket_tarihi, '%Y-%m') = ?
    ");
    $stmt->execute([$period]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: $summary;

    $stmtExpense = $pdo->prepare("SELECT COALESCE(SUM(tutar), 0) FROM gider_kayitlari WHERE DATE_FORMAT(gider_tarihi, '%Y-%m') = ?");
    $stmtExpense->execute([$period]);
    $summary['expense'] = (float) $stmtExpense->fetchColumn();

    $stmtProducts = $pdo->prepare("
        SELECT d.deneme_adi,
               COUNT(sl.id) AS sale_count,
               COALESCE(SUM(sl.odenen_toplam_tutar), 0) AS gross_collected,
               COALESCE(SUM(sl.platform_payi), 0) AS platform_profit
        FROM satis_loglari sl
        JOIN denemeler d ON d.id = sl.deneme_id
        WHERE DATE_FORMAT(sl.tarih, '%Y-%m') = ?
        GROUP BY sl.deneme_id, d.deneme_adi
        ORDER BY platform_profit DESC, gross_collected DESC
        LIMIT 8
    ");
    $stmtProducts->execute([$period]);
    $topProducts = $stmtProducts->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    set_admin_flash_message('error', 'Kar/zarar verileri yüklenemedi. Muhasebe migrationlarını kontrol edin.');
}

$netRevenue = (float) $summary['sales'] - (float) $summary['discount'];
$grossProfit = $netRevenue - (float) $summary['vat'] - (float) $summary['author'] - (float) $summary['influencer'] - (float) $summary['gateway'];
$netProfit = $grossProfit - (float) $summary['expense'];

include_once __DIR__ . '/../templates/admin_header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col">
        <h3 class="fw-bold mb-0 text-theme-primary">Kar / Zarar</h3>
        <p class="text-muted small mb-0">Seçilen dönem için gelir, indirim, komisyon ve gider etkisi tek tabloda.</p>
    </div>
    <div class="col-auto">
        <form method="GET" class="d-flex gap-2 align-items-center">
            <input type="month" name="period" class="form-control" value="<?php echo escape_html($period); ?>">
            <button type="submit" class="btn btn-primary">Göster</button>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body"><div class="text-muted small">Net Satış Geliri</div><div class="h4 fw-bold text-success mb-0"><?php echo number_format($netRevenue, 2, ',', '.'); ?> ₺</div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body"><div class="text-muted small">Brüt Kar</div><div class="h4 fw-bold text-primary mb-0"><?php echo number_format($grossProfit, 2, ',', '.'); ?> ₺</div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body"><div class="text-muted small">Faaliyet Gideri</div><div class="h4 fw-bold text-danger mb-0"><?php echo number_format((float) $summary['expense'], 2, ',', '.'); ?> ₺</div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body"><div class="text-muted small">Dönem Neti</div><div class="h4 fw-bold <?php echo $netProfit >= 0 ? 'text-success' : 'text-danger'; ?> mb-0"><?php echo number_format($netProfit, 2, ',', '.'); ?> ₺</div></div></div></div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white border-0 py-3"><h6 class="mb-0 fw-bold">Gelir Tablosu</h6></div>
            <div class="card-body p-0">
                <table class="table align-middle mb-0">
                    <tbody>
                        <tr><td class="ps-4">Satış Geliri</td><td class="text-end pe-4 fw-bold text-success"><?php echo number_format((float) $summary['sales'], 2, ',', '.'); ?> ₺</td></tr>
                        <tr><td class="ps-4">İndirimler</td><td class="text-end pe-4 fw-bold text-danger">-<?php echo number_format((float) $summary['discount'], 2, ',', '.'); ?> ₺</td></tr>
                        <tr class="table-light"><td class="ps-4 fw-bold">Net Gelir</td><td class="text-end pe-4 fw-bold"><?php echo number_format($netRevenue, 2, ',', '.'); ?> ₺</td></tr>
                        <tr><td class="ps-4">KDV</td><td class="text-end pe-4 text-danger">-<?php echo number_format((float) $summary['vat'], 2, ',', '.'); ?> ₺</td></tr>
                        <tr><td class="ps-4">Yazar Hakedişleri</td><td class="text-end pe-4 text-danger">-<?php echo number_format((float) $summary['author'], 2, ',', '.'); ?> ₺</td></tr>
                        <tr><td class="ps-4">Influencer Komisyonları</td><td class="text-end pe-4 text-danger">-<?php echo number_format((float) $summary['influencer'], 2, ',', '.'); ?> ₺</td></tr>
                        <tr><td class="ps-4">Ödeme Kuruluşu Kesintisi</td><td class="text-end pe-4 text-danger">-<?php echo number_format((float) $summary['gateway'], 2, ',', '.'); ?> ₺</td></tr>
                        <tr class="table-light"><td class="ps-4 fw-bold">Brüt Kar</td><td class="text-end pe-4 fw-bold"><?php echo number_format($grossProfit, 2, ',', '.'); ?> ₺</td></tr>
                        <tr><td class="ps-4">Faaliyet Giderleri</td><td class="text-end pe-4 text-danger">-<?php echo number_format((float) $summary['expense'], 2, ',', '.'); ?> ₺</td></tr>
                        <tr class="table-light"><td class="ps-4 fw-bold">Dönem Net Kar / Zarar</td><td class="text-end pe-4 fw-bold <?php echo $netProfit >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo number_format($netProfit, 2, ',', '.'); ?> ₺</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white border-0 py-3"><h6 class="mb-0 fw-bold">En Karlı Ürünler</h6></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr><th class="ps-4">Ürün</th><th>Satış</th><th class="text-end pe-4">Platform Neti</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($topProducts)): ?>
                                <tr><td colspan="3" class="text-center py-4 text-muted">Bu dönemde veri yok.</td></tr>
                            <?php else: ?>
                                <?php foreach ($topProducts as $product): ?>
                                    <tr>
                                        <td class="ps-4"><?php echo escape_html($product['deneme_adi']); ?></td>
                                        <td><?php echo (int) $product['sale_count']; ?></td>
                                        <td class="text-end pe-4 fw-bold"><?php echo number_format((float) $product['platform_profit'], 2, ',', '.'); ?> ₺</td>
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
