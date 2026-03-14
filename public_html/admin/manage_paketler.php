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

$page_title = 'Paket Yönetimi';

function update_package_items(PDO $pdo, int $paketId, array $denemeIds): void
{
    $pdo->prepare('DELETE FROM urun_paket_ogeleri WHERE paket_id = ?')->execute([$paketId]);
    if (empty($denemeIds)) {
        return;
    }
    $stmt = $pdo->prepare('INSERT INTO urun_paket_ogeleri (paket_id, deneme_id) VALUES (?, ?)');
    foreach ($denemeIds as $id) {
        $stmt->execute([$paketId, (int) $id]);
    }
}

function refresh_auto_bundle(PDO $pdo): void
{
    $top = $pdo->query("
        SELECT d.id, d.fiyat
        FROM denemeler d
        LEFT JOIN satis_loglari sl ON sl.deneme_id = d.id
        WHERE d.aktif_mi = 1
        GROUP BY d.id
        ORDER BY COUNT(sl.id) DESC, d.id DESC
        LIMIT 3
    ")->fetchAll(PDO::FETCH_ASSOC);
    if (count($top) < 2) {
        throw new RuntimeException('Otomatik paket için en az 2 aktif eser gerekiyor.');
    }

    $sum = 0.0;
    foreach ($top as $t) {
        $sum += (float) $t['fiyat'];
    }
    $bundlePrice = round($sum * 0.85, 2);

    $bundle = $pdo->query('SELECT id FROM urun_paketleri WHERE auto_generated_mi = 1 LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    if ($bundle) {
        $bundleId = (int) $bundle['id'];
        $stmt = $pdo->prepare('UPDATE urun_paketleri SET paket_adi = ?, kisa_aciklama = ?, fiyat = ?, aktif_mi = 1, updated_at = NOW() WHERE id = ?');
        $stmt->execute(['Cok Satanlar Paketi', 'Magazadaki en cok satan 3 eser bir arada.', $bundlePrice, $bundleId]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO urun_paketleri (paket_adi, kisa_aciklama, fiyat, aktif_mi, auto_generated_mi, created_at, updated_at) VALUES (?, ?, ?, 1, 1, NOW(), NOW())');
        $stmt->execute(['Cok Satanlar Paketi', 'Magazadaki en cok satan 3 eser bir arada.', $bundlePrice]);
        $bundleId = (int) $pdo->lastInsertId();
    }

    update_package_items($pdo, $bundleId, array_map(static function ($row) {
        return (int) $row['id'];
    }, $top));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_admin_csrf_token($_POST['csrf_token'] ?? '')) {
        set_admin_flash_message('error', 'CSRF doğrulaması başarısız.');
        redirect('admin/manage_paketler.php');
    }

    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'save') {
            $paketId = filter_input(INPUT_POST, 'paket_id', FILTER_VALIDATE_INT) ?: 0;
            $paketAdi = trim((string) ($_POST['paket_adi'] ?? ''));
            $aciklama = trim((string) ($_POST['kisa_aciklama'] ?? ''));
            $fiyat = (float) ($_POST['fiyat'] ?? 0);
            $aktifMi = isset($_POST['aktif_mi']) ? 1 : 0;
            $denemeIds = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['deneme_ids'] ?? [])))));

            if ($paketAdi === '' || $fiyat <= 0 || empty($denemeIds)) {
                throw new RuntimeException('Paket adı, fiyat ve en az bir eser zorunludur.');
            }

            $pdo->beginTransaction();
            if ($paketId > 0) {
                $stmt = $pdo->prepare('UPDATE urun_paketleri SET paket_adi = ?, kisa_aciklama = ?, fiyat = ?, aktif_mi = ?, auto_generated_mi = 0, updated_at = NOW() WHERE id = ?');
                $stmt->execute([$paketAdi, $aciklama, $fiyat, $aktifMi, $paketId]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO urun_paketleri (paket_adi, kisa_aciklama, fiyat, aktif_mi, auto_generated_mi, created_at, updated_at) VALUES (?, ?, ?, ?, 0, NOW(), NOW())');
                $stmt->execute([$paketAdi, $aciklama, $fiyat, $aktifMi]);
                $paketId = (int) $pdo->lastInsertId();
            }
            update_package_items($pdo, $paketId, $denemeIds);
            $pdo->commit();
            set_admin_flash_message('success', 'Paket kaydedildi.');
        } elseif ($action === 'delete') {
            $paketId = filter_input(INPUT_POST, 'paket_id', FILTER_VALIDATE_INT) ?: 0;
            if ($paketId <= 0) {
                throw new RuntimeException('Geçersiz paket.');
            }
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM urun_paket_ogeleri WHERE paket_id = ?')->execute([$paketId]);
            $pdo->prepare('DELETE FROM urun_paketleri WHERE id = ?')->execute([$paketId]);
            $pdo->commit();
            set_admin_flash_message('success', 'Paket silindi.');
        } elseif ($action === 'refresh_auto') {
            refresh_auto_bundle($pdo);
            set_admin_flash_message('success', 'Çok satanlar paketi güncellendi.');
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        set_admin_flash_message('error', 'İşlem hatası: ' . $e->getMessage());
    }

    redirect('admin/manage_paketler.php');
}

$denemeler = $pdo->query("SELECT id, deneme_adi, fiyat FROM denemeler WHERE aktif_mi = 1 ORDER BY deneme_adi ASC")->fetchAll(PDO::FETCH_ASSOC);
$paketler = [];
try {
    $paketler = $pdo->query("
        SELECT p.*, GROUP_CONCAT(d.deneme_adi ORDER BY d.deneme_adi SEPARATOR ', ') AS icerik_adlari, COUNT(po.id) AS icerik_adedi
        FROM urun_paketleri p
        LEFT JOIN urun_paket_ogeleri po ON po.paket_id = p.id
        LEFT JOIN denemeler d ON d.id = po.deneme_id
        GROUP BY p.id
        ORDER BY p.auto_generated_mi DESC, p.updated_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    set_admin_flash_message('error', 'Paket tabloları bulunamadı. Önce migration çalıştırın: deployment/bundle_and_payout_migration.sql');
}

include_once __DIR__ . '/../templates/admin_header.php';
?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white border-0 py-3">
                <h6 class="mb-0 fw-bold">Yeni Paket</h6>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_admin_csrf_token(); ?>">
                    <input type="hidden" name="action" value="save">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Paket Adı</label>
                        <input type="text" name="paket_adi" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Kısa Açıklama</label>
                        <textarea name="kisa_aciklama" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Fiyat (KDV Hariç)</label>
                        <input type="number" step="0.01" min="0" name="fiyat" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">İçerik Eserleri</label>
                        <select name="deneme_ids[]" class="form-select" size="8" multiple required>
                            <?php foreach ($denemeler as $d): ?>
                                <option value="<?php echo (int) $d['id']; ?>"><?php echo escape_html($d['deneme_adi']); ?> (<?php echo number_format((float) $d['fiyat'], 2); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Ctrl/Cmd ile çoklu seçim yapabilirsiniz.</div>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="aktifSwitch" name="aktif_mi" checked>
                        <label class="form-check-label" for="aktifSwitch">Aktif</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Paketi Kaydet</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-4 mb-3">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="fw-bold mb-1">Otomatik Çok Satan Paket</h6>
                    <div class="text-muted small">En çok satılan 3 eserden %15 indirimli paket üretir/günceller.</div>
                </div>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_admin_csrf_token(); ?>">
                    <input type="hidden" name="action" value="refresh_auto">
                    <button type="submit" class="btn btn-warning fw-bold">Otomatik Güncelle</button>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white border-0 py-3">
                <h6 class="mb-0 fw-bold">Mevcut Paketler</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th class="ps-3">Paket</th>
                                <th>İçerik</th>
                                <th class="text-center">Fiyat</th>
                                <th class="text-center">Durum</th>
                                <th class="text-end pe-3">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($paketler)): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">Paket bulunamadı.</td></tr>
                            <?php else: ?>
                                <?php foreach ($paketler as $p): ?>
                                    <tr>
                                        <td class="ps-3">
                                            <div class="fw-bold"><?php echo escape_html($p['paket_adi']); ?></div>
                                            <?php if ((int) $p['auto_generated_mi'] === 1): ?>
                                                <span class="badge bg-warning text-dark">Otomatik</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><small class="text-muted"><?php echo escape_html((string) ($p['icerik_adlari'] ?? '')); ?></small></td>
                                        <td class="text-center fw-bold"><?php echo number_format((float) $p['fiyat'], 2); ?> ₺</td>
                                        <td class="text-center"><?php echo (int) $p['aktif_mi'] === 1 ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-secondary">Pasif</span>'; ?></td>
                                        <td class="text-end pe-3">
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo generate_admin_csrf_token(); ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="paket_id" value="<?php echo (int) $p['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Sil</button>
                                            </form>
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
</div>

<?php include_once __DIR__ . '/../templates/admin_footer.php'; ?>
