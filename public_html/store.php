<?php
// store.php - Mağaza / Keşfet Sayfası
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = "Eğitim Mağazası";

$kat = $_GET['kategori'] ?? '';
$arama = trim($_GET['s'] ?? '');
$paketler = [];

function ensure_auto_bestseller_bundle(PDO $pdo): void
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
        return;
    }

    $sum = 0.0;
    foreach ($top as $row) {
        $sum += (float) $row['fiyat'];
    }
    $bundlePrice = round($sum * 0.85, 2); // %15 paket indirimi

    $bundle = $pdo->query('SELECT id FROM urun_paketleri WHERE auto_generated_mi = 1 LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    if ($bundle) {
        $bundleId = (int) $bundle['id'];
        $stmtUp = $pdo->prepare('UPDATE urun_paketleri SET paket_adi = ?, kisa_aciklama = ?, fiyat = ?, aktif_mi = 1, updated_at = NOW() WHERE id = ?');
        $stmtUp->execute([
            'Cok Satanlar Paketi',
            'Magazadaki en cok satan 3 eser bir arada.',
            $bundlePrice,
            $bundleId,
        ]);
    } else {
        $stmtIns = $pdo->prepare('INSERT INTO urun_paketleri (paket_adi, kisa_aciklama, fiyat, aktif_mi, auto_generated_mi, created_at, updated_at) VALUES (?, ?, ?, 1, 1, NOW(), NOW())');
        $stmtIns->execute([
            'Cok Satanlar Paketi',
            'Magazadaki en cok satan 3 eser bir arada.',
            $bundlePrice,
        ]);
        $bundleId = (int) $pdo->lastInsertId();
    }

    $pdo->prepare('DELETE FROM urun_paket_ogeleri WHERE paket_id = ?')->execute([$bundleId]);
    $stmtItem = $pdo->prepare('INSERT INTO urun_paket_ogeleri (paket_id, deneme_id) VALUES (?, ?)');
    foreach ($top as $row) {
        $stmtItem->execute([$bundleId, (int) $row['id']]);
    }
}

try {
    try {
        ensure_auto_bestseller_bundle($pdo);
    } catch (Throwable $e) {
        // Paket tabloları henüz migrate edilmemiş olabilir.
    }

    $where = ["d.aktif_mi = 1"];
    $params = [];

    if ($kat) { $where[] = "d.kategori = ?"; $params[] = $kat; }
    if ($arama) { $where[] = "(d.deneme_adi LIKE ? OR d.kisa_aciklama LIKE ?)"; $params[] = "%$arama%"; $params[] = "%$arama%"; }

    $sql = "SELECT d.*, y.ad_soyad as yazar_adi FROM denemeler d LEFT JOIN yazarlar y ON d.yazar_id = y.id WHERE " . implode(" AND ", $where) . " ORDER BY d.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $urunler = $stmt->fetchAll();

    $kategoriler = $pdo->query("SELECT DISTINCT kategori FROM denemeler WHERE aktif_mi = 1 AND kategori IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);

    try {
        $paketler = $pdo->query("
            SELECT p.*,
                   COUNT(po.id) AS icerik_adedi
            FROM urun_paketleri p
            LEFT JOIN urun_paket_ogeleri po ON po.paket_id = p.id
            WHERE p.aktif_mi = 1
            GROUP BY p.id
            ORDER BY p.auto_generated_mi DESC, p.updated_at DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $paketler = [];
    }
} catch (Exception $e) { $urunler = []; }

include_once __DIR__ . '/templates/header.php';
?>

<style>
    .store-hero {
        background: radial-gradient(circle at top, rgba(31, 60, 136, 0.12), rgba(255, 255, 255, 0.95));
        border-radius: 24px;
        padding: 48px 40px;
        box-shadow: 0 20px 40px rgba(31, 60, 136, 0.08);
    }
    .store-search {
        background: #fff;
        border-radius: 999px;
        padding: 8px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.06);
    }
    .store-search input {
        border: none;
        outline: none;
        box-shadow: none;
    }
    .filter-pill {
        border-radius: 999px;
        padding: 8px 20px;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    .product-card-modern {
        border: none;
        border-radius: 22px;
        overflow: hidden;
        box-shadow: 0 10px 25px rgba(0,0,0,0.05);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        background: #fff;
        display: flex;
        flex-direction: column;
        height: 100%;
        min-height: 480px; /* Kartın çok küçülüp butonları yutmasını engelliyoruz */
    }
    .product-card-modern:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 40px rgba(0,0,0,0.12);
    }
    .product-cover {
        position: relative;
        height: 200px;
        min-height: 200px;
        overflow: hidden;
    }
    .product-cover img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .product-badge {
        position: absolute;
        top: 12px;
        left: 12px;
        background: rgba(31, 60, 136, 0.9);
        color: #fff;
        padding: 4px 12px;
        border-radius: 999px;
        font-size: 0.7rem;
        font-weight: 700;
        z-index: 2;
    }
    .product-content {
        padding: 24px;
        display: flex;
        flex-direction: column;
        flex-grow: 1;
    }
    .product-price {
        font-size: 1.25rem;
        font-weight: 800;
        color: #1F3C88;
    }
    .product-meta {
        font-size: 0.85rem;
        color: #6c757d;
    }
    .product-actions {
        margin-top: auto; /* Butonları en alta sabitler */
        padding-top: 20px;
        border-top: 1px solid rgba(0,0,0,0.05);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
    }
    .product-cta {
        border-radius: 999px;
        padding: 8px 16px;
        font-weight: 700;
        font-size: 0.9rem;
        white-space: nowrap;
        transition: all 0.2s;
    }
    .btn-outline-primary.product-cta:hover {
        background-color: #1F3C88;
        color: #fff;
    }
</style>

<div class="container py-5">
    <div class="d-flex justify-content-end mb-3">
        <a href="cart.php" class="btn btn-outline-primary rounded-pill px-4 fw-bold">
            Sepetim (<?php echo get_cart_count(); ?>)
        </a>
    </div>
    <div class="store-hero mb-5">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <span class="text-uppercase text-primary fw-bold small">DenemeAGS Mağaza</span>
                <h1 class="fw-bold display-6 mt-2">Eğitim materyallerini keşfet</h1>
                <p class="text-muted mb-4">Butonlar dahil her şey yerli yerinde! Aramaya hemen başla.</p>
                <form class="store-search d-flex align-items-center gap-2" method="get" action="store.php">
                    <?php if ($kat): ?>
                        <input type="hidden" name="kategori" value="<?php echo escape_html($kat); ?>">
                    <?php endif; ?>
                    <i class="fas fa-search text-muted ms-3"></i>
                    <input type="text" name="s" class="form-control border-0" placeholder="Deneme, yazar veya içerik ara..." value="<?php echo escape_html($arama); ?>">
                    <button class="btn btn-primary px-4 rounded-pill" type="submit">Ara</button>
                </form>
            </div>
            <div class="col-lg-5 text-lg-end">
                <div class="d-inline-flex flex-column gap-2 align-items-lg-end">
                    <div class="fw-semibold text-primary">Kategoriler</div>
                    <div class="d-flex flex-wrap gap-2 justify-content-lg-end">
                        <a href="store.php<?php echo $arama ? '?s=' . urlencode($arama) : ''; ?>" class="btn <?php echo !$kat ? 'btn-primary' : 'btn-outline-primary'; ?> filter-pill text-sm">Tümü</a>
                        <?php foreach($kategoriler as $k): ?>
                            <a href="store.php?kategori=<?php echo urlencode($k); ?><?php echo $arama ? '&s=' . urlencode($arama) : ''; ?>" class="btn <?php echo $kat == $k ? 'btn-primary' : 'btn-outline-primary'; ?> filter-pill text-sm"><?php echo escape_html($k); ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($paketler)): ?>
        <div class="mb-4 d-flex justify-content-between align-items-center">
            <h2 class="h4 fw-bold mb-0">Hazır Paketler</h2>
            <span class="text-muted small">Cok satan urunler otomatik paketlenir.</span>
        </div>
        <div class="row g-4 mb-5">
            <?php foreach ($paketler as $p): ?>
                <div class="col-md-6 col-xl-4">
                    <div class="product-card-modern border border-warning-subtle">
                        <div class="product-cover d-flex align-items-center justify-content-center bg-warning-subtle">
                            <span class="product-badge bg-warning text-dark">PAKET</span>
                            <i class="fas fa-box-open fa-3x text-warning-emphasis"></i>
                        </div>
                        <div class="product-content">
                            <h5 class="fw-bold mb-2 text-dark"><?php echo escape_html($p['paket_adi']); ?></h5>
                            <div class="product-meta mb-3">
                                <i class="fas fa-layer-group me-1 text-primary"></i>
                                <?php echo (int) $p['icerik_adedi']; ?> eser icerir
                            </div>
                            <p class="text-muted small mb-4">
                                <?php echo escape_html($p['kisa_aciklama'] ?: 'Hazir paket urunu.'); ?>
                            </p>
                            <div class="product-actions">
                                <div class="product-price"><?php echo number_format((float) $p['fiyat'], 2); ?> TL + KDV</div>
                                <a href="cart_action.php?action=add_bundle&bundle_id=<?php echo (int) $p['id']; ?>" class="btn btn-warning text-dark product-cta">Sepete Ekle</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($urunler)): ?>
        <div class="alert alert-light text-center py-5 shadow-sm border-0 rounded-4">
            <i class="fas fa-search fa-3x text-muted mb-3"></i>
            <h4 class="h5">Aradığınız şeyi bulamadık (ama en azından arama butonu çalışıyor).</h4>
            <p class="text-muted small mb-0">Başka anahtar kelimelerle şansınızı deneyin.</p>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach($urunler as $u): ?>
            <div class="col-md-6 col-xl-4">
                <div class="product-card-modern">
                    <div class="product-cover">
                        <img src="<?php echo get_image_url($u['resim_url'] ?? ''); ?>" alt="<?php echo escape_html($u['deneme_adi']); ?>">
                        <span class="product-badge"><?php echo strtoupper(escape_html($u['tur'] ?? 'DENEME')); ?></span>
                    </div>
                    <div class="product-content">
                        <h5 class="fw-bold mb-2 text-dark"><?php echo escape_html($u['deneme_adi']); ?></h5>
                        
                        <div class="product-meta mb-3">
                            <i class="fas fa-user-edit me-1 text-primary"></i>
                            <a href="yazar.php?id=<?php echo $u['yazar_id']; ?>" class="text-decoration-none text-muted fw-semibold">
                                <?php echo escape_html($u['yazar_adi'] ?: 'Platform'); ?>
                            </a>
                        </div>

                        <p class="text-muted small mb-4">
                            <?php
                                $desc = strip_tags($u['kisa_aciklama']);
                                echo (mb_strlen($desc) > 100) ? mb_substr($desc, 0, 97) . '...' : $desc;
                            ?>
                        </p>
                        
                        <!-- Butonların ve Fiyatın Merkezi -->
                        <div class="product-actions">
                            <div class="product-price"><?php echo number_format((float) $u['fiyat'], 2); ?> TL + KDV</div>
                            <div class="d-flex gap-2">
                                <a href="urun.php?id=<?php echo $u['id']; ?>" class="btn btn-outline-primary product-cta">İncele</a>
                                <a href="cart_action.php?action=add&id=<?php echo $u['id']; ?>" class="btn btn-primary product-cta">Sepete Ekle</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include_once __DIR__ . '/templates/footer.php'; ?>
