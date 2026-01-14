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
    }
    .product-card-modern {
        border: none;
        border-radius: 22px;
        overflow: hidden;
        box-shadow: 0 18px 30px rgba(0,0,0,0.08);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        background: #fff;
        height: 100%;
    }
    .product-card-modern:hover {
        transform: translateY(-6px);
        box-shadow: 0 24px 40px rgba(0,0,0,0.12);
    }
    .product-cover {
        position: relative;
        height: 220px;
        overflow: hidden;
    }
    .product-cover img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .product-badge {
        position: absolute;
        top: 16px;
        left: 16px;
        background: rgba(31, 60, 136, 0.92);
        color: #fff;
        padding: 6px 14px;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.5px;
    }
    .product-price {
        font-size: 1.1rem;
        font-weight: 700;
        color: #1F3C88;
    }
    .product-meta {
        font-size: 0.85rem;
        color: #6c757d;
    }
    .product-cta {
        border-radius: 999px;
        padding: 10px 18px;
        font-weight: 700;
    }
</style>

<div class="container py-5">
    <div class="store-hero mb-5">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <span class="text-uppercase text-primary fw-bold small">DenemeAGS Mağaza</span>
                <h1 class="fw-bold display-6 mt-2">Deneme ve soru bankalarını keşfedin</h1>
                <p class="text-muted mb-4">Yeni tasarım mağazamızda en güncel içerikleri, filtreler ve hızlı arama ile kolayca bulun.</p>
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
                    <div class="fw-semibold text-primary">Hızlı Filtreler</div>
                    <div class="d-flex flex-wrap gap-2 justify-content-lg-end">
                        <a href="store.php<?php echo $arama ? '?s=' . urlencode($arama) : ''; ?>" class="btn <?php echo !$kat ? 'btn-primary' : 'btn-outline-primary'; ?> filter-pill">Tümü</a>
                        <?php foreach($kategoriler as $k): ?>
                            <a href="store.php?kategori=<?php echo urlencode($k); ?><?php echo $arama ? '&s=' . urlencode($arama) : ''; ?>" class="btn <?php echo $kat == $k ? 'btn-primary' : 'btn-outline-primary'; ?> filter-pill"><?php echo escape_html($k); ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($urunler)): ?>
        <div class="alert alert-light text-center py-5 shadow-sm border-0">
            <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
            <h4 class="h5">Şu an için eşleşen ürün yok.</h4>
            <p class="text-muted small mb-0">Filtreleri temizleyip tekrar deneyebilir veya ana sayfaya dönebilirsiniz.</p>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach($urunler as $u): ?>
            <div class="col-md-6 col-xl-4">
                <div class="product-card-modern">
                    <div class="product-cover">
                        <img src="<?php echo !empty($u['resim_url']) ? $u['resim_url'] : 'https://placehold.co/600x400/E0E7FF/4A69FF?text=Yayın+Kapağı'; ?>" alt="<?php echo escape_html($u['deneme_adi']); ?>">
                        <span class="product-badge"><?php echo escape_html($u['tur'] ?? 'DENEME'); ?></span>
                    </div>
                    <div class="p-4 d-flex flex-column h-100">
                        <h5 class="fw-bold mb-2"><?php echo escape_html($u['deneme_adi']); ?></h5>
                        <div class="product-meta mb-3">
                            <i class="fas fa-pen-nib me-1"></i>
                            <a href="yazar.php?id=<?php echo $u['yazar_id']; ?>" class="text-decoration-none text-muted fw-semibold">
                                <?php echo escape_html($u['yazar_adi'] ?: 'Platform'); ?>
                            </a>
                        </div>
                        <p class="text-muted small flex-grow-1 mb-4">
                            <?php
                                $desc = strip_tags($u['kisa_aciklama']);
                                echo (mb_strlen($desc) > 120) ? mb_substr($desc, 0, 117) . '...' : $desc;
                            ?>
                        </p>
                        <div class="d-flex justify-content-between align-items-center mt-auto">
                            <span class="product-price"><?php echo number_format($u['fiyat'], 2); ?> ₺</span>
                            <a href="urun.php?id=<?php echo $u['id']; ?>" class="btn btn-primary product-cta">Detay</a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include_once __DIR__ . '/templates/footer.php'; ?>
