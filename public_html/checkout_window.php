<?php
// checkout_window.php - Shopier ödeme ekranı (yalın sekme)
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
    error_log("Checkout (sekme) hata: " . $e->getMessage());
    redirect('store.php');
}

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
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Shopier Ödeme</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f6f7fb; }
        .checkout-shell { max-width: 960px; margin: 24px auto; }
        .checkout-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
        .checkout-top h1 { font-size: 1.5rem; margin: 0; color: #1f3c88; }
        .checkout-card { border-radius: 20px; border: 1px solid rgba(15, 23, 42, 0.08); box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08); background: #fff; }
        .checkout-card iframe { width: 100% !important; min-height: 780px; border: none; border-radius: 20px; }
        @media (max-width: 768px) {
            .checkout-shell { margin: 16px; }
            .checkout-card iframe { min-height: 640px; }
        }
    </style>
</head>
<body>
    <div class="checkout-shell">
        <div class="checkout-top">
            <h1>Shopier Güvenli Ödeme</h1>
            <div class="text-muted small"><i class="fas fa-shield-alt me-1"></i> SSL & Shopier güvencesi</div>
        </div>
        <div class="checkout-card p-0">
            <?php
            try {
                $renderer = new IframeRenderer($shopier);
                $renderer->setWidth('100%');
                $renderer->setHeight(780);
                $shopier->goWith($renderer);
            } catch (Exception $e) {
                echo '<div class="p-4 text-danger">Ödeme başlatılamadı. Lütfen daha sonra tekrar deneyin.</div>';
                error_log("Shopier ödeme hatası (sekme): " . $e->getMessage());
            }
            ?>
        </div>
    </div>
</body>
</html>
