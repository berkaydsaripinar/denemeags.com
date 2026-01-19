<?php
// odeme_tamamlandi.php - Ödeme tamamlandı ekranı
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

$deneme_id = filter_input(INPUT_GET, 'deneme_id', FILTER_VALIDATE_INT);
$deneme = null;

if ($deneme_id) {
    try {
        $stmt = $pdo->prepare("SELECT d.id, d.deneme_adi, y.ad_soyad as yazar_adi FROM denemeler d LEFT JOIN yazarlar y ON d.yazar_id = y.id WHERE d.id = ?");
        $stmt->execute([$deneme_id]);
        $deneme = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Ödeme tamamlandı sayfası ürün bilgisi hatası: " . $e->getMessage());
    }
}

$page_title = "Ödeme Tamamlandı";
include_once __DIR__ . '/templates/header.php';
?>

<section class="py-5 bg-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success-subtle text-success" style="width: 86px; height: 86px;">
                                <i class="bi bi-check-lg" style="font-size: 2.5rem;"></i>
                            </div>
                            <h1 class="mt-4 fw-bold text-success">Ödeme Tamamlandı</h1>
                            <p class="text-muted mb-0">
                                Ödemeniz başarıyla alındı. Ürününüz birkaç saniye içinde kütüphanenize tanımlanacaktır.
                            </p>
                        </div>

                        <?php if ($deneme): ?>
                            <div class="border rounded-4 p-4 bg-white mb-4">
                                <div class="d-flex flex-column flex-md-row justify-content-between gap-3">
                                    <div>
                                        <h5 class="fw-bold mb-1"><?php echo escape_html($deneme['deneme_adi']); ?></h5>
                                        <div class="text-muted small">Yazar: <?php echo escape_html($deneme['yazar_adi'] ?: 'DenemeAGS'); ?></div>
                                    </div>
                                    <span class="badge rounded-pill bg-primary-subtle text-primary align-self-start">Kütüphaneye ekleniyor</span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <div class="border rounded-4 p-3 h-100">
                                    <div class="fw-semibold mb-2">1. Onay</div>
                                    <div class="text-muted small">Shopier ödemenizi onayladı.</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded-4 p-3 h-100">
                                    <div class="fw-semibold mb-2">2. Tanımlama</div>
                                    <div class="text-muted small">Ürün kütüphanenize aktarılıyor.</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded-4 p-3 h-100">
                                    <div class="fw-semibold mb-2">3. Bildirim</div>
                                    <div class="text-muted small">E-posta ile bilgilendirileceksiniz.</div>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info border-0 rounded-4" role="alert">
                            <i class="bi bi-info-circle-fill me-2"></i>
                            Eğer ürününüz 1-2 dakika içinde görünmezse sayfayı yenileyebilir veya destek ile iletişime geçebilirsiniz.
                        </div>

                        <div class="d-flex flex-column flex-sm-row gap-3">
                            <?php if (isLoggedIn()): ?>
                                <a href="dashboard.php" class="btn btn-success btn-lg rounded-pill px-4">Kütüphaneme Git</a>
                            <?php else: ?>
                                <a href="login.php" class="btn btn-success btn-lg rounded-pill px-4">Giriş Yap</a>
                            <?php endif; ?>
                            <a href="store.php" class="btn btn-outline-secondary btn-lg rounded-pill px-4">Mağazaya Dön</a>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-4 text-muted small">
                    Siparişiniz için teşekkürler. Güvenli alışveriş deneyiminiz için buradayız.
                </div>
            </div>
        </div>
    </div>
</section>

<?php include_once __DIR__ . '/templates/footer.php'; ?>