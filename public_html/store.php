<?php
// store.php - Mağaza / Keşfet Sayfası
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = "Eğitim Mağazası";

$kat = $_GET['kategori'] ?? '';
$arama = trim($_GET['s'] ?? '');

try {
    $where = ["d.aktif_mi = 1"];
    $params = [];

    if ($kat) { $where[] = "d.kategori = ?"; $params[] = $kat; }
    if ($arama) { $where[] = "(d.deneme_adi LIKE ? OR d.kisa_aciklama LIKE ?)"; $params[] = "%$arama%"; $params[] = "%$arama%"; }

    $sql = "SELECT d.*, y.ad_soyad as yazar_adi FROM denemeler d LEFT JOIN yazarlar y ON d.yazar_id = y.id WHERE " . implode(" AND ", $where) . " ORDER BY d.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $urunler = $stmt->fetchAll();

    $kategoriler = $pdo->query("SELECT DISTINCT kategori FROM denemeler WHERE aktif_mi = 1 AND kategori IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) { $urunler = []; }

include_once __DIR__ . '/templates/header.php';
?>

<div class="container py-5">
    <div class="text-center mb-5">
        <h1 class="fw-bold text-primary">Tüm Yayınlar</h1>
        <p class="text-muted">İstediğin dökümanı seç, hemen çalışmaya başla.</p>
    </div>

    <!-- Filtreler -->
    <div class="d-flex flex-wrap justify-content-center gap-2 mb-5">
        <a href="store.php" class="btn <?php echo !$kat ? 'btn-primary' : 'btn-outline-primary'; ?> rounded-pill px-4">Tümü</a>
        <?php foreach($kategoriler as $k): ?>
            <a href="store.php?kategori=<?php echo urlencode($k); ?>" class="btn <?php echo $kat == $k ? 'btn-primary' : 'btn-outline-primary'; ?> rounded-pill px-4"><?php echo escape_html($k); ?></a>
        <?php endforeach; ?>
    </div>

    <div class="row g-4">
        <?php foreach($urunler as $u): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden">
                <img src="<?php echo !empty($u['resim_url']) ? $u['resim_url'] : 'https://placehold.co/600x400/E0E7FF/4A69FF?text=Yayın+Kapağı'; ?>" class="card-img-top" style="height: 200px; object-fit: cover;">
                <div class="card-body p-4 d-flex flex-column">
                    <h5 class="fw-bold"><?php echo escape_html($u['deneme_adi']); ?></h5>
                    <p class="small text-muted mb-4">
                        <i class="fas fa-pen-nib me-1"></i> 
                        <a href="yazar.php?id=<?php echo $u['yazar_id']; ?>" class="text-decoration-none text-muted fw-bold">
                            <?php echo escape_html($u['yazar_adi'] ?: 'Platform'); ?>
                        </a>
                    </p>
                    <div class="mt-auto d-flex justify-content-between align-items-center">
                        <span class="h5 mb-0 fw-bold text-primary"><?php echo number_format($u['fiyat'], 2); ?> ₺</span>
                        <!-- İncele butonu urun.php sayfasına yönlendirir -->
                        <a href="urun.php?id=<?php echo $u['id']; ?>" class="btn btn-primary rounded-pill px-4 fw-bold">İNCELE</a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include_once __DIR__ . '/templates/footer.php'; ?>