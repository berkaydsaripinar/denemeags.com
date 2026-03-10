<?php
// checkout.php - PAYTR iFrame ödeme sayfası
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

function get_client_ip_address(): string
{
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return (string) $_SERVER['HTTP_CLIENT_IP'];
    }

    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $forwarded = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($forwarded[0]);
        if ($ip !== '') {
            return $ip;
        }
    }

    return (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
}

function create_paytr_token(array $postVals): array
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://www.paytr.com/odeme/api/get-token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postVals);
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    $result = curl_exec($ch);
    if ($result === false) {
        $error = curl_error($ch);
        curl_close($ch);
        return ['status' => 'error', 'message' => 'PAYTR bağlantı hatası: ' . $error];
    }

    curl_close($ch);
    $decoded = json_decode((string) $result, true);
    if (!is_array($decoded) || !isset($decoded['status'])) {
        return ['status' => 'error', 'message' => 'PAYTR yanıtı çözümlenemedi.'];
    }

    if (($decoded['status'] ?? '') !== 'success') {
        return ['status' => 'error', 'message' => 'PAYTR token hatası: ' . ($decoded['reason'] ?? 'bilinmiyor')];
    }

    return ['status' => 'success', 'token' => (string) $decoded['token']];
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    redirect('store.php');
}

try {
    $stmt = $pdo->prepare('SELECT d.*, y.ad_soyad as yazar_adi FROM denemeler d LEFT JOIN yazarlar y ON d.yazar_id = y.id WHERE d.id = ? AND d.aktif_mi = 1');
    $stmt->execute([$id]);
    $urun = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$urun) {
        redirect('store.php');
    }

    $stmtAcc = $pdo->prepare('SELECT id FROM kullanici_erisimleri WHERE kullanici_id = ? AND deneme_id = ?');
    $stmtAcc->execute([$_SESSION['user_id'], $id]);
    if ($stmtAcc->fetch()) {
        redirect('dashboard.php');
    }

    $stmtUser = $pdo->prepare('SELECT ad_soyad, email, telefon FROM kullanicilar WHERE id = ?');
    $stmtUser->execute([$_SESSION['user_id']]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        set_flash_message('error', 'Kullanıcı bilgileri bulunamadı.');
        redirect('login.php');
    }
} catch (Exception $e) {
    error_log('Checkout hata: ' . $e->getMessage());
    redirect('store.php');
}

$missingConfig = (PAYTR_MERCHANT_ID === '' || PAYTR_MERCHANT_KEY === '' || PAYTR_MERCHANT_SALT === '');
if ($missingConfig) {
    set_flash_message('error', 'Ödeme altyapısı şu anda kullanılamıyor. Lütfen daha sonra tekrar deneyin.');
    redirect('urun.php?id=' . $id);
}

$oidPrefix = trim((string) ($urun['paytr_merchant_oid_prefix'] ?? ''));
if ($oidPrefix === '') {
    $oidPrefix = 'AGS';
}

$safeOidPrefix = preg_replace('/[^A-Za-z0-9_-]/', '', $oidPrefix);
if ($safeOidPrefix === '') {
    $safeOidPrefix = 'AGS';
}

$merchantOid = sprintf(
    '%s-%d-%d-%s-%s',
    $safeOidPrefix,
    (int) $urun['id'],
    (int) $_SESSION['user_id'],
    date('YmdHis'),
    strtoupper(bin2hex(random_bytes(3)))
);

$unitPrice = number_format((float) $urun['fiyat'], 2, '.', '');
$paymentAmount = (string) (int) round(((float) $urun['fiyat']) * 100);
$userBasket = base64_encode(json_encode([
    [(string) $urun['deneme_adi'], $unitPrice, 1],
], JSON_UNESCAPED_UNICODE));

$okUrl = BASE_URL . '/odeme_tamamlandi.php?deneme_id=' . (int) $urun['id'];
$failUrl = BASE_URL . '/odeme_tamamlandi.php?hata=1&deneme_id=' . (int) $urun['id'];
$userIp = get_client_ip_address();

$hashStr = PAYTR_MERCHANT_ID
    . $userIp
    . $merchantOid
    . (string) $user['email']
    . $paymentAmount
    . $userBasket
    . PAYTR_NO_INSTALLMENT
    . PAYTR_MAX_INSTALLMENT
    . PAYTR_CURRENCY
    . PAYTR_TEST_MODE;
$paytrToken = base64_encode(hash_hmac('sha256', $hashStr . PAYTR_MERCHANT_SALT, PAYTR_MERCHANT_KEY, true));

$postVals = [
    'merchant_id' => PAYTR_MERCHANT_ID,
    'user_ip' => $userIp,
    'merchant_oid' => $merchantOid,
    'email' => (string) $user['email'],
    'payment_amount' => $paymentAmount,
    'paytr_token' => $paytrToken,
    'user_basket' => $userBasket,
    'debug_on' => PAYTR_DEBUG_ON,
    'no_installment' => PAYTR_NO_INSTALLMENT,
    'max_installment' => PAYTR_MAX_INSTALLMENT,
    'user_name' => (string) $user['ad_soyad'],
    'user_address' => 'Adres bilgisi kayıtlı değil.',
    'user_phone' => telefon_formatla($user['telefon'] ?? ''),
    'merchant_ok_url' => $okUrl,
    'merchant_fail_url' => $failUrl,
    'timeout_limit' => PAYTR_TIMEOUT_LIMIT,
    'currency' => PAYTR_CURRENCY,
    'test_mode' => PAYTR_TEST_MODE,
];

$tokenResult = create_paytr_token($postVals);
$iframeToken = $tokenResult['status'] === 'success' ? $tokenResult['token'] : null;
if (!$iframeToken) {
    error_log('PAYTR token üretimi başarısız: ' . $tokenResult['message']);
}

$page_title = 'Güvenli Ödeme';
include_once __DIR__ . '/templates/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="mb-4">
                <h1 class="fw-bold text-primary">Güvenli Ödeme</h1>
                <p class="text-muted mb-0">
                    <?php echo escape_html($urun['deneme_adi']); ?> için PAYTR güvenli ödeme sayfasındasınız.
                    Ödeme onaylandığında ürün otomatik olarak kütüphanenize tanımlanır.
                </p>
            </div>

            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4 d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-bold"><?php echo escape_html($urun['deneme_adi']); ?></div>
                        <div class="text-muted small">Yazar: <?php echo escape_html($urun['yazar_adi'] ?: 'DenemeAGS'); ?></div>
                        <div class="text-muted small">Sipariş No: <?php echo escape_html($merchantOid); ?></div>
                    </div>
                    <div class="h4 fw-bold text-primary mb-0"><?php echo number_format((float) $urun['fiyat'], 2); ?> ₺</div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-0">
                    <?php if ($iframeToken): ?>
                        <script src="https://www.paytr.com/js/iframeResizer.min.js"></script>
                        <iframe
                            src="https://www.paytr.com/odeme/guvenli/<?php echo escape_html($iframeToken); ?>"
                            id="paytriframe"
                            frameborder="0"
                            scrolling="no"
                            style="width: 100%; min-height: 760px;"
                        ></iframe>
                        <script>iFrameResize({}, '#paytriframe');</script>
                    <?php else: ?>
                        <div class="p-4">
                            <div class="alert alert-danger mb-3" role="alert">
                                Ödeme oturumu başlatılamadı. Lütfen birkaç dakika sonra tekrar deneyin.
                            </div>
                            <p class="text-muted small mb-0">Hata detayı log dosyasına kaydedildi.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <a href="store.php" class="btn btn-outline-secondary rounded-pill px-4">Mağazaya Dön</a>
                <a href="urun.php?id=<?php echo (int) $urun['id']; ?>" class="btn btn-light border rounded-pill px-4">Ürün Sayfası</a>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/templates/footer.php'; ?>
