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

$page_title = 'Finans Defteri';

$typeFilter = trim((string) ($_GET['type'] ?? ''));
$directionFilter = trim((string) ($_GET['direction'] ?? ''));
$where = [];
$params = [];

if ($typeFilter !== '') {
    $where[] = 'hareket_tipi = ?';
    $params[] = $typeFilter;
}
if ($directionFilter !== '') {
    $where[] = 'yon = ?';
    $params[] = $directionFilter;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
$entries = [];

try {
    $stmt = $pdo->prepare("
        SELECT *
        FROM muhasebe_hareketleri
        $whereSql
        ORDER BY hareket_tarihi DESC, id DESC
        LIMIT 500
    ");
    $stmt->execute($params);
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    set_admin_flash_message('error', 'Muhasebe hareketleri okunamadı: ' . $e->getMessage());
}

include_once __DIR__ . '/../templates/admin_header.php';
?>

<div class="card border-0 shadow-sm rounded-4 mb-4 bg-light">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-bold">Hareket Tipi</label>
                <select name="type" class="form-select">
                    <option value="">Tümü</option>
                    <?php foreach (['sale','discount','vat','author_commission','influencer_commission','platform_revenue','gateway_fee','refund','expense','payout','adjustment'] as $type): ?>
                        <option value="<?php echo $type; ?>" <?php echo $typeFilter === $type ? 'selected' : ''; ?>><?php echo $type; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-bold">Yön</label>
                <select name="direction" class="form-select">
                    <option value="">Tümü</option>
                    <option value="in" <?php echo $directionFilter === 'in' ? 'selected' : ''; ?>>Giriş</option>
                    <option value="out" <?php echo $directionFilter === 'out' ? 'selected' : ''; ?>>Çıkış</option>
                </select>
            </div>
            <div class="col-md-4 text-md-end">
                <button type="submit" class="btn btn-primary">Filtrele</button>
                <a href="accounting_ledger.php" class="btn btn-outline-secondary">Sıfırla</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr><th class="ps-4">Tarih</th><th>Tip</th><th>Kaynak</th><th>Sipariş</th><th>Açıklama</th><th class="text-center">Yön</th><th class="pe-4 text-end">Tutar</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($entries)): ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted">Kayıt bulunamadı.</td></tr>
                    <?php else: ?>
                        <?php foreach ($entries as $entry): ?>
                            <tr>
                                <td class="ps-4 small text-muted"><?php echo date('d.m.Y H:i', strtotime((string) $entry['hareket_tarihi'])); ?></td>
                                <td><code><?php echo escape_html($entry['hareket_tipi']); ?></code></td>
                                <td><?php echo escape_html(($entry['kaynak_tipi'] ?? '-') . (($entry['kaynak_id'] ?? null) ? (' #' . $entry['kaynak_id']) : '')); ?></td>
                                <td><code><?php echo escape_html($entry['siparis_id'] ?? '-'); ?></code></td>
                                <td><?php echo escape_html($entry['aciklama'] ?? '-'); ?></td>
                                <td class="text-center"><?php echo $entry['yon'] === 'in' ? '<span class="badge bg-success">Giriş</span>' : '<span class="badge bg-danger">Çıkış</span>'; ?></td>
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

<?php include_once __DIR__ . '/../templates/admin_footer.php'; ?>
