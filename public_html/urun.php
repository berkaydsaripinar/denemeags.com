<?php
// urun.php - Ürün Detay Sayfası
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) redirect('store.php');

try {
    $stmt = $pdo->prepare("SELECT d.*, y.ad_soyad as yazar_adi, y.biyografi FROM denemeler d LEFT JOIN yazarlar y ON d.yazar_id = y.id WHERE d.id = ?");
    $stmt->execute([$id]);
    $urun = $stmt->fetch();
    if (!$urun) redirect('store.php');

    $has_access = false;
    if (isLoggedIn()) {
        $stmt_acc = $pdo->prepare("SELECT id FROM kullanici_erisimleri WHERE kullanici_id = ? AND deneme_id = ?");
        $stmt_acc->execute([$_SESSION['user_id'], $id]);
        if ($stmt_acc->fetch()) $has_access = true;
    }
} catch (Exception $e) { redirect('store.php'); }

$page_title = $urun['deneme_adi'];
include_once __DIR__ . '/templates/header.php';
?>

<div class="container py-5">
    <div class="row g-5">
        <div class="col-lg-5 text-center">
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden mb-4">
                <img src="<?php echo !empty($urun['resim_url']) ? $urun['resim_url'] : 'https://placehold.co/600x800/E0E7FF/4A69FF?text=Yayın+Kapağı'; ?>" class="img-fluid">
            </div>
            
            <!-- DEMO BUTONU: view_demo.php sayfasına gider -->
            <a href="view_demo.php?id=<?php echo $urun['id']; ?>" target="_blank" class="btn btn-outline-primary btn-lg rounded-pill w-100 fw-bold shadow-sm">
                <i class="fas fa-eye me-2"></i>ÖRNEK SAYFALARI İNCELE
            </a>
        </div>

        <div class="col-lg-7">
            <h1 class="fw-bold mb-3"><?php echo escape_html($urun['deneme_adi']); ?></h1>
            <p class="text-primary fw-bold"><i class="fas fa-user-edit me-1"></i> <?php echo escape_html($urun['yazar_adi'] ?: 'DenemeAGS'); ?></p>
            
            <div class="bg-white p-4 rounded-4 shadow-sm border mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <h2 class="fw-bold text-primary mb-0"><?php echo number_format($urun['fiyat'], 2); ?> ₺</h2>
                    <?php if($has_access): ?>
                        <a href="dashboard.php" class="btn btn-success btn-lg rounded-pill px-5 fw-bold">KÜTÜPHANEMDE VAR</a>
                    <?php else: ?>
                        <a href="checkout.php?id=<?php echo $urun['id']; ?>" target="_blank" rel="noopener" class="btn btn-warning btn-lg rounded-pill px-5 fw-bold text-dark">HEMEN SATIN AL</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mt-4">
                <h6 class="fw-bold text-uppercase border-bottom pb-2">İçerik Hakkında</h6>
                <p class="text-muted mt-3"><?php echo nl2br(escape_html($urun['kisa_aciklama'])); ?></p>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/templates/footer.php'; ?>