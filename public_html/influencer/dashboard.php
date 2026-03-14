<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

requireInfluencerLogin();

$page_title = 'Influencer Dashboard';
$inflId = (int) $_SESSION['influencer_id'];

$summary = [
    'kullanim' => 0,
    'indirim_toplam' => 0.0,
    'komisyon_toplam' => 0.0,
    'odenmis_komisyon' => 0.0,
    'bekleyen_komisyon' => 0.0,
];
$codes = [];
$recent = [];
$nextPayoutDate = get_next_biweekly_payout_date();
$cutoffDate = get_next_payout_cutoff_datetime();

try {
    $stmt = $pdo->prepare("
        SELECT COUNT(u.id) AS kullanim,
               COALESCE(SUM(u.indirim_tutari_toplam), 0) AS indirim_toplam,
               COALESCE(SUM(u.influencer_komisyon_tutari), 0) AS komisyon_toplam,
               COALESCE(SUM(CASE WHEN ob.durum = 'paid' THEN u.influencer_komisyon_tutari ELSE 0 END), 0) AS odenmis_komisyon,
               COALESCE(SUM(CASE WHEN ob.id IS NULL OR ob.durum IN ('draft','approved') THEN u.influencer_komisyon_tutari ELSE 0 END), 0) AS bekleyen_komisyon
        FROM indirim_kodu_kullanimlari u
        LEFT JOIN odeme_batch_kalemleri obk ON obk.referans_tipi = 'discount_usage' AND obk.referans_id = u.id
        LEFT JOIN odeme_batchleri ob ON ob.id = obk.batch_id AND ob.durum <> 'cancelled'
        WHERE u.influencer_id = ?
    ");
    $stmt->execute([$inflId]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: $summary;

    $stmtCodes = $pdo->prepare("
        SELECT k.*, COUNT(u.id) AS kullanim, COALESCE(SUM(u.influencer_komisyon_tutari), 0) AS komisyon
        FROM indirim_kodlari k
        LEFT JOIN indirim_kodu_kullanimlari u ON u.kod_id = k.id
        WHERE k.influencer_id = ?
        GROUP BY k.id
        ORDER BY k.created_at DESC
    ");
    $stmtCodes->execute([$inflId]);
    $codes = $stmtCodes->fetchAll(PDO::FETCH_ASSOC);

    $stmtRecent = $pdo->prepare("
        SELECT u.*, k.kod
        FROM indirim_kodu_kullanimlari u
        JOIN indirim_kodlari k ON k.id = u.kod_id
        WHERE u.influencer_id = ?
        ORDER BY u.created_at DESC
        LIMIT 50
    ");
    $stmtRecent->execute([$inflId]);
    $recent = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    set_flash_message('error', 'Panel verileri yüklenemedi: ' . $e->getMessage());
}

include_once __DIR__ . '/../templates/header.php';
?>
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1">Influencer Paneli</h1>
            <div class="text-muted small">Hoş geldin, <?php echo escape_html($_SESSION['influencer_name'] ?? ''); ?></div>
        </div>
        <a href="logout.php" class="btn btn-outline-danger rounded-pill">Çıkış Yap</a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="text-muted small">Toplam Kullanım</div><div class="h4 fw-bold"><?php echo (int) $summary['kullanim']; ?></div></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="text-muted small">Toplam İndirim</div><div class="h4 fw-bold text-primary"><?php echo number_format((float) $summary['indirim_toplam'], 2); ?> ₺</div></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="text-muted small">Toplam Komisyon</div><div class="h4 fw-bold text-success"><?php echo number_format((float) $summary['komisyon_toplam'], 2); ?> ₺</div></div></div></div>
        <div class="col-md-6"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="text-muted small">Ödenmiş Komisyon</div><div class="h4 fw-bold text-dark"><?php echo number_format((float) $summary['odenmis_komisyon'], 2); ?> ₺</div></div></div></div>
        <div class="col-md-6"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="text-muted small">Bekleyen Komisyon</div><div class="h4 fw-bold text-warning"><?php echo number_format((float) $summary['bekleyen_komisyon'], 2); ?> ₺</div><div class="text-muted small mt-2">Sonraki ödeme: <?php echo $nextPayoutDate->format('d.m.Y'); ?> | Kapanış: <?php echo $cutoffDate->format('d.m.Y H:i'); ?></div></div></div></div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white border-0 py-3"><h6 class="mb-0 fw-bold">Kodlarım</h6></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr><th class="ps-3">Kod</th><th>İndirim</th><th>Kullanım</th><th>Komisyon</th><th>Durum</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($codes)): ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">Kod bulunamadı.</td></tr>
                        <?php else: ?>
                            <?php foreach ($codes as $c): ?>
                                <tr>
                                    <td class="ps-3"><code><?php echo escape_html($c['kod']); ?></code></td>
                                    <td><?php echo $c['indirim_tipi'] === 'percent' ? '%' . number_format((float) $c['indirim_degeri'], 2) : number_format((float) $c['indirim_degeri'], 2) . ' TL'; ?></td>
                                    <td><?php echo (int) $c['kullanim']; ?></td>
                                    <td class="text-success fw-bold"><?php echo number_format((float) $c['komisyon'], 2); ?> ₺</td>
                                    <td><?php echo (int) $c['aktif_mi'] === 1 ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-secondary">Pasif</span>'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white border-0 py-3"><h6 class="mb-0 fw-bold">Son Kullanımlar</h6></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr><th class="ps-3">Tarih</th><th>Kod</th><th>Sipariş</th><th>İndirim</th><th>Komisyon</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent)): ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">Kullanım kaydı yok.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recent as $r): ?>
                                <tr>
                                    <td class="ps-3"><?php echo date('d.m.Y H:i', strtotime((string) $r['created_at'])); ?></td>
                                    <td><code><?php echo escape_html($r['kod']); ?></code></td>
                                    <td><code><?php echo escape_html($r['merchant_oid']); ?></code></td>
                                    <td><?php echo number_format((float) $r['indirim_tutari_toplam'], 2); ?> ₺</td>
                                    <td class="text-success fw-bold"><?php echo number_format((float) $r['influencer_komisyon_tutari'], 2); ?> ₺</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../templates/footer.php'; ?>
