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

$page_title = 'Nakit Akışı';
$period = $_GET['period'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
    $period = date('Y-m');
}

$cutoffDate = get_next_payout_cutoff_datetime();
$cutoffSql = $cutoffDate->format('Y-m-d H:i:s');
$summary = [
    'cash_in' => 0.0,
    'author_pending' => 0.0,
    'influencer_pending' => 0.0,
    'draft_batches' => 0.0,
    'expenses_paid' => 0.0,
];
$timeline = [];

try {
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN hareket_tipi = 'sale' THEN tutar ELSE 0 END), 0) AS cash_in,
            COALESCE((
                SELECT SUM(sl.yazar_payi)
                FROM satis_loglari sl
                LEFT JOIN odeme_batch_kalemleri obk ON obk.referans_tipi = 'sale_log' AND obk.referans_id = sl.id
                LEFT JOIN odeme_batchleri ob ON ob.id = obk.batch_id AND ob.durum <> 'cancelled'
                WHERE (sl.yazar_odeme_durumu IS NULL OR sl.yazar_odeme_durumu = 'beklemede')
                  AND sl.tarih <= ?
                  AND ob.id IS NULL
            ), 0) AS author_pending,
            COALESCE((
                SELECT SUM(u.influencer_komisyon_tutari)
                FROM indirim_kodu_kullanimlari u
                LEFT JOIN odeme_batch_kalemleri obk ON obk.referans_tipi = 'discount_usage' AND obk.referans_id = u.id
                LEFT JOIN odeme_batchleri ob ON ob.id = obk.batch_id AND ob.durum <> 'cancelled'
                WHERE u.influencer_komisyon_tutari > 0
                  AND u.created_at <= ?
                  AND ob.id IS NULL
            ), 0) AS influencer_pending,
            COALESCE((
                SELECT SUM(toplam_tutar) FROM odeme_batchleri WHERE durum = 'draft'
            ), 0) AS draft_batches
        FROM muhasebe_hareketleri
        WHERE DATE_FORMAT(hareket_tarihi, '%Y-%m') = ?
    ");
    $stmt->execute([$cutoffSql, $cutoffSql, $period]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: $summary;

    $stmtExpenses = $pdo->prepare("
        SELECT COALESCE(SUM(tutar), 0)
        FROM gider_kayitlari
        WHERE odeme_durumu = 'paid'
          AND DATE_FORMAT(gider_tarihi, '%Y-%m') = ?
    ");
    $stmtExpenses->execute([$period]);
    $summary['expenses_paid'] = (float) $stmtExpenses->fetchColumn();

    $stmtTimeline = $pdo->prepare("
        SELECT hareket_gunu,
               SUM(cash_in) AS cash_in,
               SUM(cash_out) AS cash_out
        FROM (
            SELECT DATE(hareket_tarihi) AS hareket_gunu,
                   SUM(CASE WHEN yon = 'in' THEN tutar ELSE 0 END) AS cash_in,
                   SUM(CASE WHEN yon = 'out' THEN tutar ELSE 0 END) AS cash_out
            FROM muhasebe_hareketleri
            WHERE DATE_FORMAT(hareket_tarihi, '%Y-%m') = ?
            GROUP BY DATE(hareket_tarihi)

            UNION ALL

            SELECT gider_tarihi AS hareket_gunu,
                   0 AS cash_in,
                   SUM(tutar) AS cash_out
            FROM gider_kayitlari
            WHERE odeme_durumu = 'paid'
              AND DATE_FORMAT(gider_tarihi, '%Y-%m') = ?
            GROUP BY gider_tarihi
        ) x
        GROUP BY hareket_gunu
        ORDER BY hareket_gunu DESC
        LIMIT 31
    ");
    $stmtTimeline->execute([$period, $period]);
    $timeline = $stmtTimeline->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    set_admin_flash_message('error', 'Nakit akışı verileri yüklenemedi. Muhasebe migrationlarını kontrol edin.');
}

$projectedBalance = (float) $summary['cash_in'] - (float) $summary['expenses_paid'] - (float) $summary['author_pending'] - (float) $summary['influencer_pending'];

include_once __DIR__ . '/../templates/admin_header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col">
        <h3 class="fw-bold mb-0 text-theme-primary">Nakit Akışı</h3>
        <p class="text-muted small mb-0">Seçilen ay tahsilatlar, giderler ve bekleyen ödeme yükümlülükleri birlikte görünür.</p>
    </div>
    <div class="col-auto">
        <form method="GET" class="d-flex gap-2 align-items-center">
            <input type="month" name="period" class="form-control" value="<?php echo escape_html($period); ?>">
            <button type="submit" class="btn btn-primary">Göster</button>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body"><div class="text-muted small">Dönem Tahsilatı</div><div class="h4 fw-bold text-success mb-0"><?php echo number_format((float) $summary['cash_in'], 2, ',', '.'); ?> ₺</div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body"><div class="text-muted small">Ödenmemiş Yazar</div><div class="h4 fw-bold text-warning mb-0"><?php echo number_format((float) $summary['author_pending'], 2, ',', '.'); ?> ₺</div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body"><div class="text-muted small">Ödenmemiş Influencer</div><div class="h4 fw-bold text-primary mb-0"><?php echo number_format((float) $summary['influencer_pending'], 2, ',', '.'); ?> ₺</div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body"><div class="text-muted small">Projeksiyon Bakiye</div><div class="h4 fw-bold <?php echo $projectedBalance >= 0 ? 'text-success' : 'text-danger'; ?> mb-0"><?php echo number_format($projectedBalance, 2, ',', '.'); ?> ₺</div></div></div></div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white border-0 py-3"><h6 class="mb-0 fw-bold">Özet</h6></div>
            <div class="card-body p-0">
                <table class="table align-middle mb-0">
                    <tbody>
                        <tr><td class="ps-4">Tahsil edilen nakit</td><td class="text-end pe-4"><?php echo number_format((float) $summary['cash_in'], 2, ',', '.'); ?> ₺</td></tr>
                        <tr><td class="ps-4">Ödenmiş giderler</td><td class="text-end pe-4 text-danger">-<?php echo number_format((float) $summary['expenses_paid'], 2, ',', '.'); ?> ₺</td></tr>
                        <tr><td class="ps-4">Bekleyen yazar ödemeleri</td><td class="text-end pe-4 text-danger">-<?php echo number_format((float) $summary['author_pending'], 2, ',', '.'); ?> ₺</td></tr>
                        <tr><td class="ps-4">Bekleyen influencer ödemeleri</td><td class="text-end pe-4 text-danger">-<?php echo number_format((float) $summary['influencer_pending'], 2, ',', '.'); ?> ₺</td></tr>
                        <tr><td class="ps-4">Taslak batch yükü</td><td class="text-end pe-4 text-danger">-<?php echo number_format((float) $summary['draft_batches'], 2, ',', '.'); ?> ₺</td></tr>
                        <tr class="table-light"><td class="ps-4 fw-bold">Projeksiyon bakiye</td><td class="text-end pe-4 fw-bold <?php echo $projectedBalance >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo number_format($projectedBalance, 2, ',', '.'); ?> ₺</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white border-0 py-3"><h6 class="mb-0 fw-bold">Günlük Hareket Özeti</h6></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr><th class="ps-4">Gün</th><th class="text-end">Giren</th><th class="text-end pe-4">Çıkan</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($timeline)): ?>
                                <tr><td colspan="3" class="text-center py-4 text-muted">Bu dönem için hareket bulunmuyor.</td></tr>
                            <?php else: ?>
                                <?php foreach ($timeline as $row): ?>
                                    <tr>
                                        <td class="ps-4"><?php echo date('d.m.Y', strtotime((string) $row['hareket_gunu'])); ?></td>
                                        <td class="text-end text-success"><?php echo number_format((float) $row['cash_in'], 2, ',', '.'); ?> ₺</td>
                                        <td class="text-end pe-4 text-danger"><?php echo number_format((float) $row['cash_out'], 2, ',', '.'); ?> ₺</td>
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
