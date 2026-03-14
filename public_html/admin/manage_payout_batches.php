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

$page_title = 'Ödeme Batch Yönetimi';
$nextPayoutDate = get_next_biweekly_payout_date();
$cutoffDate = get_next_payout_cutoff_datetime();
$cutoffSql = $cutoffDate->format('Y-m-d H:i:s');
$currentType = $_GET['type'] ?? 'all';
if (!in_array($currentType, ['all', 'author', 'influencer'], true)) {
    $currentType = 'all';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_admin_csrf_token($_POST['csrf_token'] ?? '')) {
        set_admin_flash_message('error', 'CSRF doğrulaması başarısız.');
        redirect('manage_payout_batches.php');
    }

    try {
        if (isset($_POST['create_author_batch'])) {
            $stmt = $pdo->prepare("
                SELECT sl.id, sl.yazar_id, sl.siparis_id, sl.yazar_payi, y.ad_soyad
                FROM satis_loglari sl
                JOIN yazarlar y ON y.id = sl.yazar_id
                LEFT JOIN odeme_batch_kalemleri obk ON obk.referans_tipi = 'sale_log' AND obk.referans_id = sl.id
                LEFT JOIN odeme_batchleri ob ON ob.id = obk.batch_id AND ob.durum <> 'cancelled'
                WHERE (sl.yazar_odeme_durumu IS NULL OR sl.yazar_odeme_durumu = 'beklemede')
                  AND sl.tarih <= ?
                  AND ob.id IS NULL
                ORDER BY y.ad_soyad ASC, sl.tarih ASC
            ");
            $stmt->execute([$cutoffSql]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($rows)) {
                throw new RuntimeException('Batch oluşturulacak yazar hakedişi bulunamadı.');
            }

            $items = [];
            foreach ($rows as $row) {
                $items[] = [
                    'referans_tipi' => 'sale_log',
                    'referans_id' => (int) $row['id'],
                    'yazar_id' => (int) $row['yazar_id'],
                    'tutar' => (float) $row['yazar_payi'],
                    'aciklama' => $row['ad_soyad'] . ' | ' . $row['siparis_id'],
                ];
            }

            $pdo->beginTransaction();
            $batchId = create_payout_batch($pdo, [
                'batch_tipi' => 'author',
                'batch_adi' => 'Yazar Batch ' . $nextPayoutDate->format('d.m.Y'),
                'durum' => 'draft',
                'planlanan_odeme_tarihi' => $nextPayoutDate->format('Y-m-d'),
                'notlar' => 'Kapanış tarihi: ' . $cutoffDate->format('d.m.Y H:i'),
                'created_by_admin_id' => $_SESSION['admin_id'] ?? null,
            ], $items);
            $pdo->commit();

            set_admin_flash_message('success', 'Yazar ödeme batch\'i oluşturuldu. Batch #' . $batchId);
            redirect('manage_payout_batches.php?type=author');
        }

        if (isset($_POST['create_influencer_batch'])) {
            $stmt = $pdo->prepare("
                SELECT u.id, u.influencer_id, u.merchant_oid, u.influencer_komisyon_tutari, i.ad_soyad
                FROM indirim_kodu_kullanimlari u
                JOIN influencers i ON i.id = u.influencer_id
                LEFT JOIN odeme_batch_kalemleri obk ON obk.referans_tipi = 'discount_usage' AND obk.referans_id = u.id
                LEFT JOIN odeme_batchleri ob ON ob.id = obk.batch_id AND ob.durum <> 'cancelled'
                WHERE u.influencer_komisyon_tutari > 0
                  AND u.created_at <= ?
                  AND ob.id IS NULL
                ORDER BY i.ad_soyad ASC, u.created_at ASC
            ");
            $stmt->execute([$cutoffSql]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($rows)) {
                throw new RuntimeException('Batch oluşturulacak influencer hakedişi bulunamadı.');
            }

            $items = [];
            foreach ($rows as $row) {
                $items[] = [
                    'referans_tipi' => 'discount_usage',
                    'referans_id' => (int) $row['id'],
                    'influencer_id' => (int) $row['influencer_id'],
                    'tutar' => (float) $row['influencer_komisyon_tutari'],
                    'aciklama' => $row['ad_soyad'] . ' | ' . $row['merchant_oid'],
                ];
            }

            $pdo->beginTransaction();
            $batchId = create_payout_batch($pdo, [
                'batch_tipi' => 'influencer',
                'batch_adi' => 'Influencer Batch ' . $nextPayoutDate->format('d.m.Y'),
                'durum' => 'draft',
                'planlanan_odeme_tarihi' => $nextPayoutDate->format('Y-m-d'),
                'notlar' => 'Kapanış tarihi: ' . $cutoffDate->format('d.m.Y H:i'),
                'created_by_admin_id' => $_SESSION['admin_id'] ?? null,
            ], $items);
            $pdo->commit();

            set_admin_flash_message('success', 'Influencer ödeme batch\'i oluşturuldu. Batch #' . $batchId);
            redirect('manage_payout_batches.php?type=influencer');
        }

        if (isset($_POST['mark_batch_paid'])) {
            $batchId = filter_input(INPUT_POST, 'batch_id', FILTER_VALIDATE_INT);
            if (!$batchId) {
                throw new RuntimeException('Geçerli batch seçilmedi.');
            }

            $pdo->beginTransaction();
            mark_payout_batch_paid($pdo, $batchId, (int) ($_SESSION['admin_id'] ?? 0));
            $pdo->commit();

            set_admin_flash_message('success', 'Batch ödendi olarak işlendi.');
            redirect('manage_payout_batches.php');
        }

        if (isset($_POST['cancel_batch'])) {
            $batchId = filter_input(INPUT_POST, 'batch_id', FILTER_VALIDATE_INT);
            if (!$batchId) {
                throw new RuntimeException('Geçerli batch seçilmedi.');
            }

            $stmt = $pdo->prepare('UPDATE odeme_batchleri SET durum = "cancelled", updated_at = NOW() WHERE id = ? AND durum <> "paid"');
            $stmt->execute([$batchId]);
            set_admin_flash_message('success', 'Batch iptal edildi.');
            redirect('manage_payout_batches.php');
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        set_admin_flash_message('error', $e->getMessage());
        redirect('manage_payout_batches.php');
    }
}

$stats = [
    'author_pending' => 0.0,
    'influencer_pending' => 0.0,
    'draft_total' => 0.0,
    'paid_total' => 0.0,
];
$batches = [];
$batchItems = [];

try {
    $stmtStats = $pdo->prepare("
        SELECT
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
            ), 0) AS draft_total,
            COALESCE((
                SELECT SUM(toplam_tutar) FROM odeme_batchleri WHERE durum = 'paid'
            ), 0) AS paid_total
    ");
    $stmtStats->execute([$cutoffSql, $cutoffSql]);
    $stats = $stmtStats->fetch(PDO::FETCH_ASSOC) ?: $stats;

    $conditions = [];
    $params = [];
    if ($currentType !== 'all') {
        $conditions[] = 'b.batch_tipi = ?';
        $params[] = $currentType;
    }
    $whereSql = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

    $stmtBatches = $pdo->prepare("
        SELECT b.*, COUNT(k.id) AS kalem_sayisi
        FROM odeme_batchleri b
        LEFT JOIN odeme_batch_kalemleri k ON k.batch_id = b.id
        {$whereSql}
        GROUP BY b.id
        ORDER BY b.created_at DESC
        LIMIT 20
    ");
    $stmtBatches->execute($params);
    $batches = $stmtBatches->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($batches)) {
        $batchIds = array_map('intval', array_column($batches, 'id'));
        $placeholders = implode(',', array_fill(0, count($batchIds), '?'));
        $stmtItems = $pdo->prepare("
            SELECT k.*,
                   y.ad_soyad AS yazar_adi,
                   i.ad_soyad AS influencer_adi,
                   sl.siparis_id,
                   u.merchant_oid
            FROM odeme_batch_kalemleri k
            LEFT JOIN yazarlar y ON y.id = k.yazar_id
            LEFT JOIN influencers i ON i.id = k.influencer_id
            LEFT JOIN satis_loglari sl ON sl.id = k.referans_id AND k.referans_tipi = 'sale_log'
            LEFT JOIN indirim_kodu_kullanimlari u ON u.id = k.referans_id AND k.referans_tipi = 'discount_usage'
            WHERE k.batch_id IN ($placeholders)
            ORDER BY k.id ASC
        ");
        $stmtItems->execute($batchIds);
        foreach ($stmtItems->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $batchItems[(int) $item['batch_id']][] = $item;
        }
    }
} catch (Throwable $e) {
    set_admin_flash_message('error', 'Batch verileri yüklenemedi. Önce accounting_core_migration.sql çalıştırın.');
}

include_once __DIR__ . '/../templates/admin_header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col">
        <h3 class="fw-bold mb-0 text-theme-primary">Ödeme Batch Yönetimi</h3>
        <p class="text-muted small mb-0">
            Kapanış: <?php echo $cutoffDate->format('d.m.Y H:i'); ?> |
            Planlanan ödeme: <?php echo $nextPayoutDate->format('d.m.Y'); ?> (Cumartesi)
        </p>
    </div>
    <div class="col-auto">
        <div class="btn-group">
            <a href="manage_payout_batches.php?type=all" class="btn btn-outline-secondary <?php echo $currentType === 'all' ? 'active' : ''; ?>">Tümü</a>
            <a href="manage_payout_batches.php?type=author" class="btn btn-outline-secondary <?php echo $currentType === 'author' ? 'active' : ''; ?>">Yazar</a>
            <a href="manage_payout_batches.php?type=influencer" class="btn btn-outline-secondary <?php echo $currentType === 'influencer' ? 'active' : ''; ?>">Influencer</a>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body"><div class="text-muted small">Ödenebilir Yazar</div><div class="h4 fw-bold text-success mb-0"><?php echo number_format((float) $stats['author_pending'], 2, ',', '.'); ?> ₺</div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body"><div class="text-muted small">Ödenebilir Influencer</div><div class="h4 fw-bold text-primary mb-0"><?php echo number_format((float) $stats['influencer_pending'], 2, ',', '.'); ?> ₺</div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body"><div class="text-muted small">Taslak Batch Toplamı</div><div class="h4 fw-bold text-warning mb-0"><?php echo number_format((float) $stats['draft_total'], 2, ',', '.'); ?> ₺</div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body"><div class="text-muted small">Ödenmiş Batch Toplamı</div><div class="h4 fw-bold text-dark mb-0"><?php echo number_format((float) $stats['paid_total'], 2, ',', '.'); ?> ₺</div></div></div></div>
</div>

<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header bg-white border-0 py-3">
        <h6 class="mb-0 fw-bold">Yeni Batch Oluştur</h6>
    </div>
    <div class="card-body d-flex flex-wrap gap-2">
        <form method="POST" class="d-inline">
            <input type="hidden" name="csrf_token" value="<?php echo generate_admin_csrf_token(); ?>">
            <button type="submit" name="create_author_batch" class="btn btn-success">Yazar Batch Oluştur</button>
        </form>
        <form method="POST" class="d-inline">
            <input type="hidden" name="csrf_token" value="<?php echo generate_admin_csrf_token(); ?>">
            <button type="submit" name="create_influencer_batch" class="btn btn-primary">Influencer Batch Oluştur</button>
        </form>
    </div>
</div>

<?php if (empty($batches)): ?>
    <div class="alert alert-info border-0 shadow-sm rounded-4">Henüz batch bulunmuyor.</div>
<?php else: ?>
    <?php foreach ($batches as $batch): ?>
        <?php $items = $batchItems[(int) $batch['id']] ?? []; ?>
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white border-0 d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <div class="fw-bold"><?php echo escape_html($batch['batch_adi']); ?></div>
                    <div class="text-muted small">
                        #<?php echo (int) $batch['id']; ?> |
                        <?php echo $batch['batch_tipi'] === 'author' ? 'Yazar' : 'Influencer'; ?> |
                        Planlanan: <?php echo $batch['planlanan_odeme_tarihi'] ? date('d.m.Y', strtotime((string) $batch['planlanan_odeme_tarihi'])) : '-'; ?>
                    </div>
                </div>
                <div class="text-end">
                    <div class="h5 fw-bold mb-1"><?php echo number_format((float) $batch['toplam_tutar'], 2, ',', '.'); ?> ₺</div>
                    <div class="small text-muted"><?php echo (int) $batch['kalem_sayisi']; ?> kalem</div>
                    <div class="small">
                        <?php if ($batch['durum'] === 'paid'): ?>
                            <span class="badge bg-success">Ödendi</span>
                        <?php elseif ($batch['durum'] === 'draft'): ?>
                            <span class="badge bg-warning text-dark">Taslak</span>
                        <?php elseif ($batch['durum'] === 'approved'): ?>
                            <span class="badge bg-info text-dark">Onaylı</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">İptal</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th class="ps-4">Tip</th>
                                <th>Hak Sahibi</th>
                                <th>Referans</th>
                                <th class="text-end pe-4">Tutar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td class="ps-4"><?php echo $item['referans_tipi'] === 'sale_log' ? 'Satış' : 'Kupon'; ?></td>
                                    <td><?php echo escape_html((string) ($item['yazar_adi'] ?: $item['influencer_adi'] ?: '-')); ?></td>
                                    <td><code><?php echo escape_html((string) ($item['siparis_id'] ?: $item['merchant_oid'] ?: '-')); ?></code></td>
                                    <td class="text-end pe-4 fw-bold"><?php echo number_format((float) $item['tutar'], 2, ',', '.'); ?> ₺</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white border-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div class="small text-muted"><?php echo escape_html((string) ($batch['notlar'] ?: 'Ek not yok.')); ?></div>
                <div class="d-flex gap-2">
                    <?php if ($batch['durum'] !== 'paid' && $batch['durum'] !== 'cancelled'): ?>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_admin_csrf_token(); ?>">
                            <input type="hidden" name="batch_id" value="<?php echo (int) $batch['id']; ?>">
                            <button type="submit" name="mark_batch_paid" class="btn btn-success btn-sm">Ödendi Olarak İşle</button>
                        </form>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_admin_csrf_token(); ?>">
                            <input type="hidden" name="batch_id" value="<?php echo (int) $batch['id']; ?>">
                            <button type="submit" name="cancel_batch" class="btn btn-outline-danger btn-sm">İptal Et</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php include_once __DIR__ . '/../templates/admin_footer.php'; ?>
