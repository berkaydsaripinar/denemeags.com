<?php
// checkout.php - PAYTR iFrame ödeme sayfası (sepet + KDV)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

function checkout_log(string $text): void
{
    $time = date('Y-m-d H:i:s');
    file_put_contents(WEBHOOK_LOG_FILE, '[' . $time . '] [CHECKOUT] ' . $text . PHP_EOL, FILE_APPEND);
}

function get_client_ip_address(): string
{
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return (string) $_SERVER['HTTP_CF_CONNECTING_IP'];
    }

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

function normalize_paytr_phone($phone): string
{
    $digits = preg_replace('/[^0-9]/', '', (string) $phone);

    if (strpos($digits, '00') === 0) {
        $digits = substr($digits, 2);
    }

    if (strpos($digits, '90') === 0 && strlen($digits) === 12) {
        $digits = substr($digits, 2);
    }

    if (strpos($digits, '0') === 0 && strlen($digits) === 11) {
        $digits = substr($digits, 1);
    }

    if (strlen($digits) < 10) {
        return '';
    }

    return $digits;
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
        return ['status' => 'error', 'message' => 'PAYTR bağlantı hatası: ' . $error, 'raw' => ''];
    }

    curl_close($ch);
    $decoded = json_decode((string) $result, true);
    if (!is_array($decoded) || !isset($decoded['status'])) {
        return ['status' => 'error', 'message' => 'PAYTR yanıtı çözümlenemedi.', 'raw' => (string) $result];
    }

    if (($decoded['status'] ?? '') !== 'success') {
        return ['status' => 'error', 'message' => 'PAYTR token hatası: ' . ($decoded['reason'] ?? 'bilinmiyor'), 'raw' => (string) $result];
    }

    return ['status' => 'success', 'token' => (string) $decoded['token'], 'raw' => (string) $result];
}

function fetch_checkout_products(PDO $pdo, array $productIds, int $userId): array
{
    if (empty($productIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $stmt = $pdo->prepare("SELECT d.*, y.ad_soyad as yazar_adi FROM denemeler d LEFT JOIN yazarlar y ON d.yazar_id = y.id WHERE d.aktif_mi = 1 AND d.id IN ($placeholders)");
    $stmt->execute($productIds);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $indexed = [];
    foreach ($rows as $row) {
        $indexed[(int) $row['id']] = $row;
    }

    $eligible = [];
    foreach ($productIds as $pid) {
        if (!isset($indexed[$pid])) {
            continue;
        }

        $stmtAcc = $pdo->prepare('SELECT id FROM kullanici_erisimleri WHERE kullanici_id = ? AND deneme_id = ? LIMIT 1');
        $stmtAcc->execute([$userId, $pid]);
        if ($stmtAcc->fetch(PDO::FETCH_ASSOC)) {
            continue;
        }

        $eligible[] = $indexed[$pid];
    }

    return $eligible;
}

function fetch_checkout_bundles(PDO $pdo, array $bundleIds): array
{
    if (empty($bundleIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($bundleIds), '?'));
    $stmt = $pdo->prepare("SELECT p.* FROM urun_paketleri p WHERE p.aktif_mi = 1 AND p.id IN ($placeholders)");
    $stmt->execute($bundleIds);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $indexed = [];
    foreach ($rows as $row) {
        $indexed[(int) $row['id']] = $row;
    }

    $eligible = [];
    foreach ($bundleIds as $bid) {
        if (isset($indexed[$bid])) {
            $eligible[] = $indexed[$bid];
        }
    }

    return $eligible;
}

function allocate_bundle_lines(PDO $pdo, int $bundleId, float $bundleSubtotalExVat): array
{
    $stmt = $pdo->prepare("
        SELECT d.id, d.yazar_id, d.deneme_adi, d.fiyat
        FROM urun_paket_ogeleri po
        JOIN denemeler d ON d.id = po.deneme_id
        WHERE po.paket_id = ? AND d.aktif_mi = 1
        ORDER BY po.id ASC
    ");
    $stmt->execute([$bundleId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($items)) {
        return [];
    }

    $listTotal = 0.0;
    foreach ($items as $it) {
        $listTotal += max(0.01, (float) $it['fiyat']);
    }

    if ($listTotal <= 0.0) {
        return [];
    }

    $allocated = [];
    $running = 0.0;
    $lastIdx = count($items) - 1;
    foreach ($items as $idx => $it) {
        if ($idx === $lastIdx) {
            $lineExVat = round($bundleSubtotalExVat - $running, 2);
        } else {
            $weight = max(0.01, (float) $it['fiyat']) / $listTotal;
            $lineExVat = round($bundleSubtotalExVat * $weight, 2);
            $running += $lineExVat;
        }

        $allocated[] = [
            'deneme_id' => (int) $it['id'],
            'yazar_id' => (int) ($it['yazar_id'] ?? 0),
            'deneme_adi' => (string) $it['deneme_adi'],
            'line_ex_vat' => max(0.01, $lineExVat),
        ];
    }

    return $allocated;
}

function apply_proportional_discount(array $linesByDeneme, float $discountExVat): array
{
    if ($discountExVat <= 0 || empty($linesByDeneme)) {
        return $linesByDeneme;
    }

    $total = 0.0;
    foreach ($linesByDeneme as $line) {
        $total += (float) $line['line_ex_vat'];
    }
    if ($total <= 0) {
        return $linesByDeneme;
    }

    $newTotal = max(0, round($total - $discountExVat, 2));
    $ratio = $newTotal / $total;

    $keys = array_keys($linesByDeneme);
    $lastKey = end($keys);
    $running = 0.0;
    foreach ($linesByDeneme as $key => $line) {
        if ($key === $lastKey) {
            $lineAmount = round($newTotal - $running, 2);
        } else {
            $lineAmount = round((float) $line['line_ex_vat'] * $ratio, 2);
            $running += $lineAmount;
        }
        $linesByDeneme[$key]['line_ex_vat'] = max(0, $lineAmount);
    }

    return $linesByDeneme;
}

$singleId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$cartMode = $singleId <= 0;

try {
    $stmtUser = $pdo->prepare('SELECT ad_soyad, email, telefon FROM kullanicilar WHERE id = ?');
    $stmtUser->execute([$_SESSION['user_id']]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        set_flash_message('error', 'Kullanıcı bilgileri bulunamadı.');
        redirect('login.php');
    }

    if ($cartMode) {
        $productIds = get_cart_session_items();
        $bundleIds = get_cart_bundle_session_items();
    } else {
        $productIds = [$singleId];
        $bundleIds = [];
    }

    $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds), function ($v) {
        return $v > 0;
    })));
    $bundleIds = array_values(array_unique(array_filter(array_map('intval', $bundleIds), function ($v) {
        return $v > 0;
    })));

    $urunler = fetch_checkout_products($pdo, $productIds, (int) $_SESSION['user_id']);
    $paketler = fetch_checkout_bundles($pdo, $bundleIds);
    if (empty($urunler) && empty($paketler)) {
        set_flash_message('warning', 'Ödeme için uygun ürün bulunamadı.');
        redirect($cartMode ? 'cart.php' : 'store.php');
    }
} catch (Throwable $e) {
    error_log('Checkout hazırlık hatası: ' . $e->getMessage());
    set_flash_message('error', 'Checkout hazırlık hatası: ' . $e->getMessage());
    redirect($cartMode ? 'cart.php' : 'store.php');
}

$missingConfig = (PAYTR_MERCHANT_ID === '' || PAYTR_MERCHANT_KEY === '' || PAYTR_MERCHANT_SALT === '');
if ($missingConfig) {
    set_flash_message('error', 'Ödeme altyapısı şu anda kullanılamıyor. Lütfen daha sonra tekrar deneyin.');
    redirect($cartMode ? 'cart.php' : ('urun.php?id=' . (int) $singleId));
}

$vatRate = get_vat_rate();
$subtotalExVat = 0.0;
$userBasketArr = [];
$displayLines = [];
$orderLinesByDeneme = [];
$selectedPrefix = 'AGS';
$appliedCoupon = null;
$discountExVat = 0.0;
$discountVat = 0.0;
$discountTotal = 0.0;
$influencerId = 0;
$influencerCommissionRate = 0.0;
$influencerCommissionTotal = 0.0;

foreach ($urunler as $u) {
    $priceExVat = round((float) $u['fiyat'], 2);
    $lineVat = round($priceExVat * $vatRate, 2);
    $lineTotal = round($priceExVat + $lineVat, 2);

    $subtotalExVat += $priceExVat;
    $userBasketArr[] = [
        (string) $u['deneme_adi'],
        number_format($lineTotal, 2, '.', ''),
        1,
    ];
    $displayLines[] = [
        'title' => (string) $u['deneme_adi'],
        'author' => (string) ($u['yazar_adi'] ?: 'DenemeAGS'),
        'price' => $priceExVat,
        'is_bundle' => false,
    ];

    $denemeId = (int) $u['id'];
    if (!isset($orderLinesByDeneme[$denemeId])) {
        $orderLinesByDeneme[$denemeId] = [
            'deneme_id' => $denemeId,
            'yazar_id' => (int) ($u['yazar_id'] ?? 0),
            'line_ex_vat' => 0.0,
        ];
    }
    $orderLinesByDeneme[$denemeId]['line_ex_vat'] += $priceExVat;

    // Sepet modunda ürün bazlı prefix kullanmayalım; OID tek ve nötr kalsın.
    if (!$cartMode) {
        $prefix = trim((string) ($u['paytr_merchant_oid_prefix'] ?? ''));
        if ($prefix !== '') {
            $selectedPrefix = $prefix;
        }
    }
}

foreach ($paketler as $p) {
    $bundleExVat = round((float) $p['fiyat'], 2);
    $bundleVat = round($bundleExVat * $vatRate, 2);
    $bundleTotal = round($bundleExVat + $bundleVat, 2);

    $subtotalExVat += $bundleExVat;
    $userBasketArr[] = [
        (string) $p['paket_adi'],
        number_format($bundleTotal, 2, '.', ''),
        1,
    ];
    $displayLines[] = [
        'title' => (string) $p['paket_adi'] . ' (Paket)',
        'author' => 'Coklu yazar',
        'price' => $bundleExVat,
        'is_bundle' => true,
    ];

    $allocatedLines = allocate_bundle_lines($pdo, (int) $p['id'], $bundleExVat);
    foreach ($allocatedLines as $al) {
        $denemeId = (int) $al['deneme_id'];
        if (!isset($orderLinesByDeneme[$denemeId])) {
            $orderLinesByDeneme[$denemeId] = [
                'deneme_id' => $denemeId,
                'yazar_id' => (int) $al['yazar_id'],
                'line_ex_vat' => 0.0,
            ];
        }
        $orderLinesByDeneme[$denemeId]['line_ex_vat'] += (float) $al['line_ex_vat'];
    }
}

if ($cartMode) {
    $couponSession = get_cart_coupon();
    if ($couponSession && !empty($couponSession['kod'])) {
        $liveCoupon = find_active_discount_code($pdo, (string) $couponSession['kod']);
        if (!$liveCoupon) {
            clear_cart_coupon();
        } else {
            $appliedCoupon = $couponSession;
            $appliedCoupon['id'] = (int) $liveCoupon['id'];
            $appliedCoupon['kod'] = (string) $liveCoupon['kod'];
            $appliedCoupon['indirim_tipi'] = (string) $liveCoupon['indirim_tipi'];
            $appliedCoupon['indirim_degeri'] = (float) $liveCoupon['indirim_degeri'];
            $appliedCoupon['influencer_id'] = (int) ($liveCoupon['influencer_id'] ?? 0);
            $appliedCoupon['influencer_komisyon_orani'] = (float) ($liveCoupon['influencer_komisyon_orani'] ?? 0);

            if ($subtotalExVat > 0) {
                if ($appliedCoupon['indirim_tipi'] === 'percent') {
                    $discountExVat = round($subtotalExVat * ($appliedCoupon['indirim_degeri'] / 100), 2);
                } else {
                    $discountExVat = min($subtotalExVat, round((float) $appliedCoupon['indirim_degeri'], 2));
                }
                $discountVat = round($discountExVat * $vatRate, 2);
                $discountTotal = round($discountExVat + $discountVat, 2);
            }

            $influencerId = (int) ($appliedCoupon['influencer_id'] ?? 0);
            $influencerCommissionRate = (float) ($appliedCoupon['influencer_komisyon_orani'] ?? 0);
        }
    }
}

if ($discountExVat > 0) {
    $orderLinesByDeneme = apply_proportional_discount($orderLinesByDeneme, $discountExVat);
}

$subtotalExVatAfterDiscount = max(0, round($subtotalExVat - $discountExVat, 2));
$vatAmount = round($subtotalExVatAfterDiscount * $vatRate, 2);
$totalInclVat = round($subtotalExVatAfterDiscount + $vatAmount, 2);
$influencerCommissionTotal = $influencerId > 0 ? round($subtotalExVatAfterDiscount * ($influencerCommissionRate / 100), 2) : 0.0;

if ($discountTotal > 0 && !empty($userBasketArr)) {
    $basketTotal = 0.0;
    foreach ($userBasketArr as $row) {
        $basketTotal += (float) $row[1];
    }
    $targetBasketTotal = max(0, round($basketTotal - $discountTotal, 2));
    if ($basketTotal > 0) {
        $ratio = $targetBasketTotal / $basketTotal;
        $running = 0.0;
        $lastIndex = count($userBasketArr) - 1;
        foreach ($userBasketArr as $idx => $row) {
            if ($idx === $lastIndex) {
                $newVal = round($targetBasketTotal - $running, 2);
            } else {
                $newVal = round(((float) $row[1]) * $ratio, 2);
                $running += $newVal;
            }
            $userBasketArr[$idx][1] = number_format(max(0, $newVal), 2, '.', '');
        }
    }
}
$paymentAmount = (string) (int) round($totalInclVat * 100);
$userBasket = base64_encode(json_encode($userBasketArr, JSON_UNESCAPED_UNICODE));

$safePrefix = preg_replace('/[^A-Za-z0-9]/', '', $selectedPrefix);
if ($safePrefix === '') {
    $safePrefix = 'AGS';
}

$merchantOid = sprintf(
    '%sC%dU%dT%sR%s',
    $safePrefix,
    count($displayLines),
    (int) $_SESSION['user_id'],
    date('YmdHis'),
    strtoupper(bin2hex(random_bytes(4)))
);

try {
    $pdo->beginTransaction();

    try {
        $stmtOrder = $pdo->prepare('INSERT INTO paytr_orders (user_id, merchant_oid, status, subtotal_ex_vat, vat_amount, total_amount, basket_json, discount_code_id, discount_code, discount_amount_ex_vat, discount_vat_amount, discount_total, influencer_id, influencer_commission_total, created_at, updated_at) VALUES (?, ?, "pending", ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
        $stmtOrder->execute([
            (int) $_SESSION['user_id'],
            $merchantOid,
            $subtotalExVatAfterDiscount,
            $vatAmount,
            $totalInclVat,
            json_encode($userBasketArr, JSON_UNESCAPED_UNICODE),
            $appliedCoupon['id'] ?? null,
            $appliedCoupon['kod'] ?? null,
            $discountExVat,
            $discountVat,
            $discountTotal,
            $influencerId > 0 ? $influencerId : null,
            $influencerCommissionTotal,
        ]);
    } catch (Throwable $e) {
        $stmtOrder = $pdo->prepare('INSERT INTO paytr_orders (user_id, merchant_oid, status, subtotal_ex_vat, vat_amount, total_amount, basket_json, created_at, updated_at) VALUES (?, ?, "pending", ?, ?, ?, ?, NOW(), NOW())');
        $stmtOrder->execute([
            (int) $_SESSION['user_id'],
            $merchantOid,
            $subtotalExVatAfterDiscount,
            $vatAmount,
            $totalInclVat,
            json_encode($userBasketArr, JSON_UNESCAPED_UNICODE),
        ]);
    }
    $orderId = (int) $pdo->lastInsertId();

    $stmtItem = $pdo->prepare('INSERT INTO paytr_order_items (order_id, deneme_id, yazar_id, unit_price_ex_vat, vat_rate, line_subtotal_ex_vat, line_vat_amount, line_total) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    foreach ($orderLinesByDeneme as $line) {
        $priceExVat = round((float) $line['line_ex_vat'], 2);
        $lineVat = round($priceExVat * $vatRate, 2);
        $lineTotal = round($priceExVat + $lineVat, 2);

        $stmtItem->execute([
            $orderId,
            (int) $line['deneme_id'],
            (int) ($line['yazar_id'] ?? 0),
            $priceExVat,
            $vatRate,
            $priceExVat,
            $lineVat,
            $lineTotal,
        ]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('paytr_orders kayıt hatası: ' . $e->getMessage());
    set_flash_message('error', 'Sipariş hazırlığı başarısız oldu. Lütfen tekrar deneyin.');
    redirect($cartMode ? 'cart.php' : ('urun.php?id=' . (int) $singleId));
}

$okUrl = BASE_URL . '/odeme_tamamlandi.php?oid=' . urlencode($merchantOid);
$failUrl = BASE_URL . '/odeme_tamamlandi.php?hata=1&oid=' . urlencode($merchantOid);
$userIp = get_client_ip_address();
$paytrPhone = normalize_paytr_phone($user['telefon'] ?? '');
if ($paytrPhone === '') {
    $paytrPhone = '5555555555';
    checkout_log('Geçersiz user_phone, fallback kullanıldı. user_id=' . (int) $_SESSION['user_id']);
}

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
    'user_phone' => $paytrPhone,
    'merchant_ok_url' => $okUrl,
    'merchant_fail_url' => $failUrl,
    'timeout_limit' => PAYTR_TIMEOUT_LIMIT,
    'currency' => PAYTR_CURRENCY,
    'test_mode' => PAYTR_TEST_MODE,
];

$tokenResult = create_paytr_token($postVals);
$iframeToken = $tokenResult['status'] === 'success' ? $tokenResult['token'] : null;
$paymentErrorMessage = '';
if (!$iframeToken) {
    $paymentErrorMessage = (string) ($tokenResult['message'] ?? 'Bilinmeyen hata');
    $rawResult = (string) ($tokenResult['raw'] ?? '');
    error_log('PAYTR token üretimi başarısız: ' . $paymentErrorMessage . ' | raw: ' . $rawResult);
    checkout_log('PAYTR token üretimi başarısız | message: ' . $paymentErrorMessage . ' | raw: ' . $rawResult . ' | merchant_oid: ' . $merchantOid . ' | user_ip: ' . $userIp);
}

$page_title = 'Güvenli Ödeme';
include_once __DIR__ . '/templates/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="mb-4 d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="fw-bold text-primary mb-1">Güvenli Ödeme</h1>
                    <p class="text-muted mb-0"><?php echo count($displayLines); ?> kalem için PAYTR ödeme ekranındasınız.</p>
                </div>
                <a href="cart.php" class="btn btn-outline-secondary rounded-pill px-4">Sepete Dön</a>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-3">Ürünler</h5>
                            <?php foreach ($displayLines as $line): ?>
                                <div class="d-flex justify-content-between border-bottom py-2">
                                    <div>
                                        <div class="fw-semibold"><?php echo escape_html($line['title']); ?></div>
                                        <div class="small text-muted">Yazar: <?php echo escape_html($line['author']); ?></div>
                                    </div>
                                    <div class="small text-end text-muted"><?php echo number_format((float) $line['price'], 2); ?> TL + KDV</div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-3">Ödeme Özeti</h5>
                            <div class="d-flex justify-content-between mb-2"><span class="text-muted">Ara Toplam (KDV Hariç)</span><strong><?php echo number_format($subtotalExVat, 2); ?> TL</strong></div>
                            <?php if ($discountTotal > 0): ?>
                                <div class="d-flex justify-content-between mb-2"><span class="text-success">İndirim (<?php echo escape_html((string) ($appliedCoupon['kod'] ?? '')); ?>)</span><strong class="text-success">-<?php echo number_format($discountTotal, 2); ?> TL</strong></div>
                            <?php endif; ?>
                            <div class="d-flex justify-content-between mb-2"><span class="text-muted">KDV (%20)</span><strong><?php echo number_format($vatAmount, 2); ?> TL</strong></div>
                            <hr>
                            <div class="d-flex justify-content-between"><span class="fw-bold">Ödenecek Toplam</span><strong class="text-primary fs-5"><?php echo number_format($totalInclVat, 2); ?> TL</strong></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-0">
                    <?php if ($iframeToken): ?>
                        <script src="https://www.paytr.com/js/iframeResizer.min.js"></script>
                        <iframe src="https://www.paytr.com/odeme/guvenli/<?php echo escape_html($iframeToken); ?>" id="paytriframe" frameborder="0" scrolling="no" style="width: 100%; min-height: 760px;"></iframe>
                        <script>iFrameResize({}, '#paytriframe');</script>
                    <?php else: ?>
                        <div class="p-4">
                            <div class="alert alert-danger mb-3" role="alert">Ödeme oturumu başlatılamadı. Lütfen birkaç dakika sonra tekrar deneyin.</div>
                            <?php if ($paymentErrorMessage !== ''): ?>
                                <p class="text-muted small mb-1"><strong>PAYTR:</strong> <?php echo escape_html($paymentErrorMessage); ?></p>
                            <?php endif; ?>
                            <p class="text-muted small mb-0">Hata detayı log dosyasına kaydedildi: <code>webhook_debug.txt</code></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/templates/footer.php'; ?>
