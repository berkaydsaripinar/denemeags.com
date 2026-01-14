<?php
// checkout.php - Shopier ödeme sayfası (Site içi iframe)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/vendor/autoload.php';

use Shopier\Enums\ProductType;
use Shopier\Enums\WebsiteIndex;
use Shopier\Models\Address;
use Shopier\Models\Buyer;
use Shopier\Renderers\IframeRenderer;
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

$order_id = sprintf('AGS-%d-%d-%s', $urun['id'], (int) $_SESSION['user_id'], date('YmdHis'));
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

$buyer = new Buyer([
    'id' => $_SESSION['user_id'],
    'name' => $buyer_name,
    'surname' => $buyer_surname,
    'email' => $user['email'],
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

<style>
    .checkout-hero {
        background: linear-gradient(135deg, rgba(31, 60, 136, 0.08), rgba(245, 124, 0, 0.06));
        border-radius: 24px;
        padding: 32px;
        margin-bottom: 24px;
        border: 1px solid rgba(31, 60, 136, 0.08);
    }
    .checkout-hero h1 {
        font-size: clamp(1.8rem, 3vw, 2.4rem);
    }
    .checkout-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 16px;
    }
    .checkout-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 14px;
        border-radius: 999px;
        background: #fff;
        box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
        font-size: 0.85rem;
        color: #1f3c88;
        font-weight: 600;
    }
    .checkout-card {
        border-radius: 20px;
        border: 1px solid rgba(15, 23, 42, 0.08);
        box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
    }
    .checkout-summary {
        background: #ffffff;
        border-radius: 18px;
        padding: 20px 22px;
        border: 1px solid rgba(31, 60, 136, 0.08);
        box-shadow: 0 12px 24px rgba(15, 23, 42, 0.06);
    }
    .checkout-summary .price {
        font-size: 1.6rem;
        font-weight: 800;
        color: #1f3c88;
    }
    .checkout-frame {
        min-height: 720px;
    }
    .checkout-frame iframe {
        width: 100% !important;
        min-height: 720px;
        border: none;
        border-radius: 18px;
    }
    @media (max-width: 768px) {
        .checkout-hero {
            padding: 22px;
        }
        .checkout-frame {
            min-height: 640px;
        }
        .checkout-frame iframe {
            min-height: 640px;
        }
    }
</style>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="checkout-hero">
                <h1 class="fw-bold text-primary mb-2">Güvenli Ödeme</h1>
                <p class="text-muted mb-3">
                    <?php echo escape_html($urun['deneme_adi']); ?> için ödeme adımındasınız. Ödeme tamamlandığında ürün otomatik olarak kütüphanenize tanımlanacaktır.
                </p>
                <div class="checkout-badges">
                    <span class="checkout-badge"><i class="fas fa-shield-alt"></i> Shopier Güvencesi</span>
                    <span class="checkout-badge"><i class="fas fa-lock"></i> SSL Güvenli Ödeme</span>
                    <span class="checkout-badge"><i class="fas fa-bolt"></i> Anında Teslim</span>
                </div>
            </div>

            <div class="checkout-summary mb-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <div class="fw-bold"><?php echo escape_html($urun['deneme_adi']); ?></div>
                        <div class="text-muted small">Yazar: <?php echo escape_html($urun['yazar_adi'] ?: 'DenemeAGS'); ?></div>
                    </div>
                    <div class="price"><?php echo number_format((float) $urun['fiyat'], 2); ?> ₺</div>
                </div>
                <div class="mt-3 d-flex flex-wrap gap-2">
                    <a class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener" href="checkout_window.php?id=<?php echo $urun['id']; ?>">
                        <i class="fas fa-external-link-alt me-1"></i> Shopier ödeme ekranını yeni sekmede aç
                    </a>
                    <span class="text-muted small align-self-center">Sorun yaşarsanız yeni sekmede ödeme ekranını kullanabilirsiniz.</span>
                </div>
            </div>

            <div class="card checkout-card">
                <div class="card-body p-0 checkout-frame">
                    <?php
                    try {
                        $renderer = new IframeRenderer($shopier);
                        $renderer->setWidth('100%');
                        $renderer->setHeight(720);
                        $shopier->goWith($renderer);
                    } catch (Exception $e) {
                        echo '<div class="p-4 text-danger">Ödeme başlatılamadı. Lütfen daha sonra tekrar deneyin.</div>';
                        error_log("Shopier ödeme hatası: " . $e->getMessage());
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/templates/footer.php'; ?>
