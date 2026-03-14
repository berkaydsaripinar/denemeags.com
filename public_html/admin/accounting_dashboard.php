<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAdminLogin();
if (!isSuperAdmin()) {
    set_admin_flash_message('error', 'Bu sayfa için Süper Admin yetkisi gerekir.');
    redirect('admin/dashboard.php');
}

$page_title = 'Muhasebe Dashboard';

$stats = [
    'month_sales' => 0,
    'month_vat' => 0,
    'month_author' => 0,
    'month_influencer' => 0,
    'month_expense' => 0,
    'month_net' => 0,
    'draft_batches' => 0,
    'pending_payouts' => 0,
];
$recentEntries = [];

try {
    $stmt = $pdo->query("
        SELECT
            COALESCE(SUM(CASE WHEN hareket_tipi = 'sale' AND DATE_FORMAT(hareket_tarihi, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m') THEN tutar ELSE 0 END), 0) AS month_sales,
            COALESCE(SUM(CASE WHEN hareket_tipi = 'vat' AND DATE_FORMAT(hareket_tarihi, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m') THEN tutar ELSE 0 END), 0) AS month_vat,
            COALESCE(SUM(CASE WHEN hareket_tipi = 'author_commission' AND DATE_FORMAT(hareket_tarihi, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m') THEN tutar ELSE 0 END), 0) AS month_author,
            COALESCE(SUM(CASE WHEN hareket_tipi = 'influencer_commission' AND DATE_FORMAT(hareket_tarihi, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m') THEN tutar ELSE 0 END), 0) AS month_influencer,
            COALESCE((SELECT SUM(tutar) FROM gider_kayitlari WHERE DATE_FORMAT(gider_tarihi, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')), 0) AS month_expense,
            COALESCE(SUM(CASE WHEN hareket_tipi = 'platform_revenue' AND DATE_FORMAT(hareket_tarihi, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m') THEN CASE WHEN yon = 'in' THEN tutar ELSE -tutar END ELSE 0 END), 0) AS month_net,
            COALESCE((SELECT SUM(toplam_tutar) FROM odeme_batchleri WHERE durum = 'draft'), 0) AS draft_batches,
            COALESCE((
                SELECT SUM(sl.yazar_payi)
                FROM satis_loglari sl
                LEFT JOIN odeme_batch_kalemleri obk ON obk.referans_tipi = 'sale_log' AND obk.referans_id = sl.id
                LEFT JOIN odeme_batchleri ob ON ob.id = obk.batch_id AND ob.durum <> 'cancelled'
                WHERE (sl.yazar_odeme_durumu IS NULL OR sl.yazar_odeme_durumu = 'beklemede')
                  AND ob.id IS NULL
            ), 0) + COALESCE((
                SELECT SUM(u.influencer_komisyon_tutari)
                FROM indirim_kodu_kullanimlari u
                LEFT JOIN odeme_batch_kalemleri obk ON obk.referans_tipi = 'discount_usage' AND obk.referans_id = u.id
                LEFT JOIN odeme_batchleri ob ON ob.id = obk.batch_id AND ob.durum <> 'cancelled'
                WHERE u.influencer_komisyon_tutari > 0
                  AND ob.id IS NULL
            ), 0) AS pending_payouts
        FROM muhasebe_hareketleri
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC) ?: $stats;

    $stmtRecent = $pdo->query("
        SELECT *
        FROM muhasebe_hareketleri
        ORDER BY hareket_tarihi DESC, id DESC
        LIMIT 15
    ");
    $recentEntries = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    set_admin_flash_message('error', 'Muhasebe tabloları hazır değil. Önce accounting_core_migration.sql çalıştırın.');
}

include_once __DIR__ . '/../templates/admin_header.php';
?>

<div class="row g-4 mb-4">
    <div class="col-md-4"><div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body"><div class="text-muted small fw-bold">AYLIK TAHSİLAT</div><div class="h3 fw-bold text-success mb-0"><?php echo number_format((float) $stats['month_sales'], 2); ?> ₺</div></div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body"><div class="text-muted small fw-bold">AYLIK KDV YÜKÜ</div><div class="h3 fw-bold text-danger mb-0"><?php echo number_format((float) $stats['month_vat'], 2); ?> ₺</div></div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body"><div class="text-muted small fw-bold">AYLIK PLATFORM NETİ</div><div class="h3 fw-bold text-primary mb-0"><?php echo number_format((float) $stats['month_net'], 2); ?> ₺</div></div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body"><div class="text-muted small fw-bold">AYLIK YAZAR HAKEDİŞİ</div><div class="h4 fw-bold mb-0"><?php echo number_format((float) $stats['month_author'], 2); ?> ₺</div></div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body"><div class="text-muted small fw-bold">AYLIK INFLUENCER PAYI</div><div class="h4 fw-bold mb-0"><?php echo number_format((float) $stats['month_influencer'], 2); ?> ₺</div></div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body"><div class="text-muted small fw-bold">AYLIK GİDER</div><div class="h4 fw-bold mb-0"><?php echo number_format((float) $stats['month_expense'], 2); ?> ₺</div></div></div></div>
    <div class="col-md-6"><div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body"><div class="text-muted small fw-bold">AÇIK PAYOUT YÜKÜ</div><div class="h4 fw-bold text-warning mb-0"><?php echo number_format((float) $stats['pending_payouts'], 2); ?> ₺</div></div></div></div>
    <div class="col-md-6"><div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body"><div class="text-muted small fw-bold">TASLAK BATCH TOPLAMI</div><div class="h4 fw-bold text-secondary mb-0"><?php echo number_format((float) $stats['draft_batches'], 2); ?> ₺</div></div></div></div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold">Son Finans Hareketleri</h6>
                <a href="accounting_ledger.php" class="btn btn-sm btn-outline-primary">Defteri Aç</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr><th class="ps-4">Tarih</th><th>Tip</th><th>Açıklama</th><th>Sipariş</th><th class="pe-4 text-end">Tutar</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentEntries)): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">Muhasebe hareketi bulunamadı.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentEntries as $entry): ?>
                                    <tr>
                                        <td class="ps-4 small text-muted"><?php echo date('d.m.Y H:i', strtotime((string) $entry['hareket_tarihi'])); ?></td>
                                        <td><span class="badge bg-light text-dark border"><?php echo escape_html($entry['hareket_tipi']); ?></span></td>
                                        <td><?php echo escape_html($entry['aciklama'] ?? '-'); ?></td>
                                        <td><code><?php echo escape_html($entry['siparis_id'] ?? '-'); ?></code></td>
                                        <td class="pe-4 text-end fw-bold <?php echo $entry['yon'] === 'in' ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo $entry['yon'] === 'in' ? '+' : '-'; ?><?php echo number_format((float) $entry['tutar'], 2); ?> ₺
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white py-3 border-0"><h6 class="mb-0 fw-bold">Hızlı Finans İşlemleri</h6></div>
            <div class="card-body d-grid gap-2">
                <a href="accounting_ledger.php" class="btn btn-outline-primary">Finans Defteri</a>
                <a href="profit_loss.php" class="btn btn-outline-secondary">Kar / Zarar</a>
                <a href="cashflow.php" class="btn btn-outline-secondary">Nakit Akışı</a>
                <a href="manage_payout_batches.php" class="btn btn-outline-success">Ödeme Batchleri</a>
                <a href="manage_expenses.php" class="btn btn-outline-dark">Gider Yönetimi</a>
                <a href="manage_yazar_odemeleri.php" class="btn btn-outline-success">Hakediş Ödemeleri</a>
                <a href="manage_indirim_kodlari.php" class="btn btn-outline-warning">İndirim & Influencer</a>
            </div>
        </div>
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body">
                <div class="text-muted small mb-2">Bu ekran muhasebe çekirdeğinin ilk fazıdır.</div>
                <div class="small">Gelir, KDV, hakediş ve giderleri aynı yerden takip etmenizi sağlar. Sonraki adımda batch, iade ve nakit akışı detaylarını bağlayacağız.</div>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../templates/admin_footer.php'; ?>
