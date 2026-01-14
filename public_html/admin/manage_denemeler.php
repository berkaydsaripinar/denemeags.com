<?php
/**
 * admin/manage_denemeler.php - Deneme Sınavları Yönetimi ve Onay Sistemi
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/admin_functions.php';

// Oturum kontrolü
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

$message = "";
$messageType = "";

// --- ONAYLAMA İŞLEMİ ---
if (isset($_GET['approve_id'])) {
    $approveId = (int)$_GET['approve_id'];
    $stmt = $pdo->prepare("UPDATE denemeler SET admin_onayi = 1, aktif_mi = 1 WHERE id = ?");
    if ($stmt->execute([$approveId])) {
        $message = "Deneme başarıyla onaylandı ve yayına alındı.";
        $messageType = "success";
    }
}

// --- SİLME İŞLEMİ ---
if (isset($_GET['delete_id'])) {
    $deleteId = (int)$_GET['delete_id'];
    $stmt = $pdo->prepare("DELETE FROM denemeler WHERE id = ?");
    if ($stmt->execute([$deleteId])) {
        $message = "Deneme sistemden silindi.";
        $messageType = "info";
    }
}

// Denemeleri getir (Yazar bilgisiyle birlikte)
$stmt = $pdo->query("SELECT d.*, y.ad_soyad as yazar_adi FROM denemeler d LEFT JOIN yazarlar y ON d.yazar_id = y.id ORDER BY d.admin_onayi ASC, d.id DESC");
$denemeler = $stmt->fetchAll();

include __DIR__ . '/../templates/admin_header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="fas fa-file-pdf me-2 text-primary"></i>Deneme Onay & Yönetim</h2>
        <a href="edit_deneme.php" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Yeni Deneme Ekle</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Deneme Adı / Yazar</th>
                            <th>Tür / Kategori</th>
                            <th>Fiyat</th>
                            <th>Durum</th>
                            <th class="text-end">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($denemeler as $d): ?>
                            <tr>
                                <td>#<?= $d['id'] ?></td>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($d['deneme_adi']) ?></div>
                                    <small class="text-muted"><i class="fas fa-user me-1"></i><?= htmlspecialchars($d['yazar_adi'] ?? 'Yönetici') ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-info text-dark"><?= htmlspecialchars($d['tur']) ?></span><br>
                                    <small class="text-muted"><?= htmlspecialchars($d['kategori']) ?></small>
                                </td>
                                <td><?= number_format($d['fiyat'], 2) ?> ₺</td>
                                <td>
                                    <?php if ($d['admin_onayi'] == 0): ?>
                                        <span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i>Onay Bekliyor</span>
                                    <?php else: ?>
                                        <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Onaylı / Aktif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <?php if ($d['admin_onayi'] == 0): ?>
                                            <a href="?approve_id=<?= $d['id'] ?>" class="btn btn-sm btn-success" title="Onayla">
                                                <i class="fas fa-check"></i> Onayla
                                            </a>
                                        <?php endif; ?>
                                        <a href="edit_deneme.php?id=<?= $d['id'] ?>" class="btn btn-sm btn-outline-primary" title="Düzenle">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="<?= BASE_URL . $d['soru_kitapcik_dosyasi'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="PDF Görüntüle">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="?delete_id=<?= $d['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Bu denemeyi silmek istediğinize emin misiniz?')" title="Sil">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($denemeler)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">Henüz kayıtlı veya onay bekleyen deneme bulunmuyor.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../templates/admin_footer.php'; ?>