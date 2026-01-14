<?php
/**
 * admin/manage_yazarlar.php - Yazar/Yayıncı Yönetimi ve Başvuru Onayı
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/admin_functions.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

$message = "";
$messageType = "";

// --- YAZAR AKTİFLEŞTİRME ---
if (isset($_GET['activate_id'])) {
    $actId = (int)$_GET['activate_id'];
    $stmt = $pdo->prepare("UPDATE yazarlar SET aktif_mi = 1 WHERE id = ?");
    if ($stmt->execute([$actId])) {
        $message = "Yazar hesabı başarıyla aktifleştirildi.";
        $messageType = "success";
    }
}

// --- YAZAR PASİFLEŞTİRME ---
if (isset($_GET['deactivate_id'])) {
    $deactId = (int)$_GET['deactivate_id'];
    $stmt = $pdo->prepare("UPDATE yazarlar SET aktif_mi = 0 WHERE id = ?");
    if ($stmt->execute([$deactId])) {
        $message = "Yazar hesabı donduruldu.";
        $messageType = "warning";
    }
}

// Yazarları getir
$stmt = $pdo->query("SELECT * FROM yazarlar ORDER BY aktif_mi ASC, id DESC");
$yazarlar = $stmt->fetchAll();

include __DIR__ . '/../templates/admin_header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="fas fa-users-cog me-2 text-primary"></i>Yayıncı Başvuruları & Yönetimi</h2>
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
                            <th>Yazar / Kurum</th>
                            <th>İletişim</th>
                            <th>Paket</th>
                            <th>Hakediş (%)</th>
                            <th>Durum</th>
                            <th class="text-end">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($yazarlar as $y): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($y['ad_soyad']) ?></div>
                                    <small class="text-muted">ID: #<?= $y['id'] ?></small>
                                </td>
                                <td>
                                    <small><i class="fas fa-envelope me-1"></i><?= htmlspecialchars($y['email']) ?></small><br>
                                    <small><i class="fas fa-phone me-1"></i><?= htmlspecialchars($y['telefon']) ?></small>
                                </td>
                                <td>
                                    <?php if ($y['paket_turu'] == 'manuel'): ?>
                                        <span class="badge bg-outline-warning text-warning border border-warning">Manuel</span>
                                    <?php else: ?>
                                        <span class="badge bg-outline-primary text-primary border border-primary">Otomatik</span>
                                    <?php endif; ?>
                                </td>
                                <td>%<?= number_format($y['komisyon_orani'], 0) ?></td>
                                <td>
                                    <?php if ($y['aktif_mi'] == 0): ?>
                                        <span class="badge bg-danger">Onay Bekliyor</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Aktif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <?php if ($y['aktif_mi'] == 0): ?>
                                            <a href="?activate_id=<?= $y['id'] ?>" class="btn btn-sm btn-success">
                                                <i class="fas fa-user-check me-1"></i> Onayla
                                            </a>
                                        <?php else: ?>
                                            <a href="?deactivate_id=<?= $y['id'] ?>" class="btn btn-sm btn-warning">
                                                <i class="fas fa-user-slash me-1"></i> Dondur
                                            </a>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                onclick="alert('IBAN: <?= $y['iban_bilgisi'] ?>')">
                                            <i class="fas fa-university"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../templates/admin_footer.php'; ?>