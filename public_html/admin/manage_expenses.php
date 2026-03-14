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

$page_title = 'Gider Yönetimi';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_admin_csrf_token($_POST['csrf_token'] ?? '')) {
        set_admin_flash_message('error', 'CSRF doğrulaması başarısız.');
        redirect('admin/manage_expenses.php');
    }

    try {
        $giderTipi = (string) ($_POST['gider_tipi'] ?? 'other');
        $baslik = trim((string) ($_POST['baslik'] ?? ''));
        $aciklama = trim((string) ($_POST['aciklama'] ?? ''));
        $tutar = (float) ($_POST['tutar'] ?? 0);
        $giderTarihi = (string) ($_POST['gider_tarihi'] ?? date('Y-m-d'));
        $kdvDahilMi = isset($_POST['kdv_dahil_mi']) ? 1 : 0;
        $odemeDurumu = (string) ($_POST['odeme_durumu'] ?? 'paid');

        if ($baslik === '' || $tutar <= 0) {
            throw new RuntimeException('Başlık ve tutar zorunludur.');
        }

        $stmt = $pdo->prepare('INSERT INTO gider_kayitlari (gider_tipi, baslik, aciklama, tutar, kdv_dahil_mi, gider_tarihi, odeme_durumu, created_by_admin_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
        $stmt->execute([$giderTipi, $baslik, $aciklama, $tutar, $kdvDahilMi, $giderTarihi, $odemeDurumu, $_SESSION['admin_id'] ?? null]);

        try {
            $stmtLedger = $pdo->prepare('INSERT INTO muhasebe_hareketleri (hareket_tipi, yon, kaynak_tipi, kaynak_id, tutar, para_birimi, aciklama, metadata_json, hareket_tarihi, created_at) VALUES ("expense", "out", "expense_record", ?, ?, "TRY", ?, ?, ?, NOW())');
            $stmtLedger->execute([
                (int) $pdo->lastInsertId(),
                $tutar,
                $baslik,
                json_encode(['gider_tipi' => $giderTipi, 'odeme_durumu' => $odemeDurumu], JSON_UNESCAPED_UNICODE),
                $giderTarihi . ' 00:00:00',
            ]);
        } catch (Throwable $e) {
        }

        set_admin_flash_message('success', 'Gider kaydı eklendi.');
    } catch (Throwable $e) {
        set_admin_flash_message('error', 'Gider kaydı eklenemedi: ' . $e->getMessage());
    }

    redirect('admin/manage_expenses.php');
}

$expenses = [];
try {
    $expenses = $pdo->query('SELECT * FROM gider_kayitlari ORDER BY gider_tarihi DESC, id DESC LIMIT 300')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    set_admin_flash_message('error', 'Gider tabloları hazır değil. Önce accounting_core_migration.sql çalıştırın.');
}

include_once __DIR__ . '/../templates/admin_header.php';
?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white py-3 border-0"><h6 class="mb-0 fw-bold">Yeni Gider</h6></div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_admin_csrf_token(); ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Gider Tipi</label>
                            <select name="gider_tipi" class="form-select">
                                <option value="fixed">Sabit</option>
                                <option value="variable">Değişken</option>
                                <option value="ads">Reklam</option>
                                <option value="hosting">Hosting</option>
                                <option value="service">Hizmet</option>
                                <option value="tax">Vergi</option>
                                <option value="other">Diğer</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Tarih</label>
                            <input type="date" name="gider_tarihi" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Başlık</label>
                            <input type="text" name="baslik" class="form-control" placeholder="Meta reklam gideri" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Açıklama</label>
                            <textarea name="aciklama" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Tutar</label>
                            <input type="number" step="0.01" min="0" name="tutar" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Ödeme Durumu</label>
                            <select name="odeme_durumu" class="form-select">
                                <option value="paid">Ödendi</option>
                                <option value="pending">Bekliyor</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="kdv_dahil_mi" id="kdvSwitch" checked>
                                <label class="form-check-label" for="kdvSwitch">Tutar KDV dahil</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary w-100">Gideri Kaydet</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white py-3 border-0"><h6 class="mb-0 fw-bold">Son Giderler</h6></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr><th class="ps-3">Tarih</th><th>Başlık</th><th>Tip</th><th>Durum</th><th class="pe-3 text-end">Tutar</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($expenses)): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">Gider kaydı bulunamadı.</td></tr>
                            <?php else: ?>
                                <?php foreach ($expenses as $expense): ?>
                                    <tr>
                                        <td class="ps-3"><?php echo date('d.m.Y', strtotime((string) $expense['gider_tarihi'])); ?></td>
                                        <td><div class="fw-bold small"><?php echo escape_html($expense['baslik']); ?></div><div class="text-muted" style="font-size:0.72rem;"><?php echo escape_html($expense['aciklama'] ?? ''); ?></div></td>
                                        <td><span class="badge bg-light text-dark border"><?php echo escape_html($expense['gider_tipi']); ?></span></td>
                                        <td><?php echo $expense['odeme_durumu'] === 'paid' ? '<span class="badge bg-success">Ödendi</span>' : '<span class="badge bg-warning text-dark">Bekliyor</span>'; ?></td>
                                        <td class="pe-3 text-end fw-bold text-danger"><?php echo number_format((float) $expense['tutar'], 2); ?> ₺</td>
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
