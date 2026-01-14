<?php
// checkout.php - Shopier ödeme sayfası (yeni sekmede açılır)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/vendor/autoload.php';

use Shopier\Enums\ProductType;
use Shopier\Enums\WebsiteIndex;
use Shopier\Models\Address;
use Shopier\Models\Buyer;
use Shopier\Renderers\AutoSubmitFormRenderer;
use Shopier\Shopier;

requireLogin();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    redirect('store.php');
}

try {
    $stmt = $pdo->prepare("SELECT d.*, y.ad_soyad as yazar_adi FROM denemeler d LEFT JOIN yazarlar y ON d.yazar_id = y.id WHERE d.id = ? AND d.aktif_mi = 1");
    $stmt->execute([$id]);
    $urun = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$urun) {
        redirect('store.php');
    }

    $stmt_acc = $pdo->prepare("SELECT id FROM kullanici_erisimleri WHERE kullanici_id = ? AND deneme_id = ?");
    $stmt_acc->execute([$_SESSION['user_id'], $id]);
    if ($stmt_acc->fetch()) {
        redirect('dashboard.php');
    }

    $stmt_user = $pdo->prepare("SELECT ad_soyad, email, telefon FROM kullanicilar WHERE id = ?");
    $stmt_user->execute([$_SESSION['user_id']]);
    $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        set_flash_message('error', 'Kullanıcı bilgileri bulunamadı.');
        redirect('login.php');
    }
} catch (Exception $e) {
    error_log("Checkout hata: " . $e->getMessage());
    redirect('store.php');
}

$page_title = "Güvenli Ödeme";

if (!class_exists(Shopier::class)) {
    set_flash_message('error', 'Ödeme altyapısı şu anda kullanılamıyor. Lütfen daha sonra tekrar deneyin.');
    redirect('urun.php?id=' . $id);
}

// ÖNEMLİ: Sipariş ID'sine deneme ID'sini gömüyoruz
$order_id = sprintf(
    'AGS-%d-%s-%s',
    (int) $urun['id'],
    date('YmdHis'),
    strtoupper(bin2hex(random_bytes(3)))
);
$price = number_format((float) $urun['fiyat'], 2, '.', '');

$shopier = new Shopier(SHOPIER_API_KEY, SHOPIER_API_SECRET);
$params = $shopier->getParams();
$website_index_value = (string) SHOPIER_WEBSITE_INDEX;
switch ($website_index_value) {
    case '2':
        $params->setWebsiteIndex(WebsiteIndex::SITE_2);
        break;
    case '3':
        $params->setWebsiteIndex(WebsiteIndex::SITE_3);
        break;
    case '4':
        $params->setWebsiteIndex(WebsiteIndex::SITE_4);
        break;
    case '5':
        $params->setWebsiteIndex(WebsiteIndex::SITE_5);
        break;
    default:
        $params->setWebsiteIndex(WebsiteIndex::SITE_1);
        break;
}

$name_parts = explode(' ', trim($user['ad_soyad']));
$buyer_name = $name_parts[0] ?? 'Müşteri';
$buyer_surname = $name_parts[1] ?? 'DenemeAGS';

// Email'e deneme ID ekleyerek gönder (webhook'ta parse edilecek)
// Format: email+DENEME123@domain.com
$email_parts = explode('@', $user['email']);
$custom_email = $email_parts[0] . '+DENEME' . $urun['id'] . '@' . $email_parts[1];

$buyer = new Buyer([
    'id' => $_SESSION['user_id'],
    'name' => $buyer_name,
    'surname' => $buyer_surname,
    'email' => $custom_email,  // berkay+DENEME123@gmail.com
    'phone' => telefon_formatla($user['telefon'] ?? '')
]);

$address = new Address([
    'address' => 'Adres bilgisi kayıtlı değil.',
    'city' => 'Istanbul',
    'country' => 'Turkey',
    'postcode' => '00000',
]);

$params->setBuyer($buyer);
$params->setAddress($address);
$params->setOrderData($order_id, $price);
$params->setProductData($urun['deneme_adi'], ProductType::DOWNLOADABLE_VIRTUAL);

include_once __DIR__ . '/templates/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="mb-4">
                <h1 class="fw-bold text-primary">Güvenli Ödeme</h1>
                <p class="text-muted mb-0">
                    <?php echo escape_html($urun['deneme_adi']); ?> için Shopier ödeme sayfası yeni sekmede açılacak.
                    Ödeme tamamlandığında ürün otomatik olarak kütüphanenize tanımlanacaktır.
                </p>
            </div>

            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-bold"><?php echo escape_html($urun['deneme_adi']); ?></div>
                            <div class="text-muted small">Yazar: <?php echo escape_html($urun['yazar_adi'] ?: 'DenemeAGS'); ?></div>
                        </div>
                        <div class="h4 fw-bold text-primary mb-0"><?php echo number_format((float) $urun['fiyat'], 2); ?> ₺</div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4 text-center">
                    <div class="mb-3">
                        <i class="bi bi-lock-fill text-success" style="font-size: 3rem;"></i>
                        <h5 class="mt-3">Ödeme sayfası açılıyor...</h5>
                        <p class="text-muted small">
                            Eğer sayfa otomatik açılmazsa, aşağıdaki butona tıklayın.
                        </p>
                    </div>

                    <?php
                    try {
                        // AutoSubmitFormRenderer otomatik olarak yeni sekmede açılır
                        $renderer = new AutoSubmitFormRenderer($shopier);
                        
                        // Form'u render et (otomatik submit olacak ve yeni sekmede açılacak)
                        $shopier->goWith($renderer);
                        
                    } catch (Exception $e) {
                        echo '<div class="alert alert-danger">Ödeme başlatılamadı. Lütfen daha sonra tekrar deneyin.</div>';
                        error_log("Shopier ödeme hatası: " . $e->getMessage());
                    }
                    ?>

                    <div class="mt-4">
                        <a href="store.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Mağazaya Dön
                        </a>
                    </div>
                </div>
            </div>

            <div class="alert alert-info mt-4" role="alert">
                <i class="bi bi-info-circle-fill me-2"></i>
                <strong>Önemli:</strong> Ödeme işlemini tamamladıktan sonra bu sayfaya geri dönebilir veya dashboard sayfanıza gidebilirsiniz.
            </div>
        </div>
    </div>
</div>

<script>
// Ödeme penceresi açıldıktan sonra kullanıcıyı bilgilendir
setTimeout(function() {
    // Eğer popup blocker tarafından engellenirse kullanıcıya bilgi ver
    if (document.querySelector('form[target="_blank"]')) {
        console.log('Ödeme formu yeni sekmede açılıyor...');
    }
}, 1000);
</script>

<?php include_once __DIR__ . '/templates/footer.php'; ?>
