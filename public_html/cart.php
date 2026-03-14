<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = 'Sepetim';

$cartIds = get_cart_session_items();
$cartBundleIds = get_cart_bundle_session_items();
$urunler = [];
$paketler = [];
$subtotalExVat = 0.0;
$vatRate = get_vat_rate();
$popularSuggestions = [];
$lowSalesSuggestions = [];
$appliedCoupon = get_cart_coupon();
$discountExVat = 0.0;
$discountVat = 0.0;
$discountTotal = 0.0;

if ($appliedCoupon && !empty($appliedCoupon['kod'])) {
    $liveCoupon = find_active_discount_code($pdo, (string) $appliedCoupon['kod']);
    if (!$liveCoupon) {
        clear_cart_coupon();
        $appliedCoupon = null;
    } else {
        $appliedCoupon['id'] = (int) $liveCoupon['id'];
        $appliedCoupon['indirim_tipi'] = (string) $liveCoupon['indirim_tipi'];
        $appliedCoupon['indirim_degeri'] = (float) $liveCoupon['indirim_degeri'];
        $appliedCoupon['influencer_id'] = (int) ($liveCoupon['influencer_id'] ?? 0);
        $appliedCoupon['influencer_komisyon_orani'] = (float) ($liveCoupon['influencer_komisyon_orani'] ?? 0);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token((string) $_POST['csrf_token'])) {
        set_flash_message('error', 'Güvenlik doğrulaması başarısız.');
        redirect('cart.php');
    }

    $couponAction = (string) ($_POST['coupon_action'] ?? '');
    if ($couponAction === 'remove') {
        clear_cart_coupon();
        set_flash_message('info', 'İndirim kodu kaldırıldı.');
        redirect('cart.php');
    }

    if ($couponAction === 'apply') {
        $couponInput = strtoupper(trim((string) ($_POST['coupon_code'] ?? '')));
        if ($couponInput === '') {
            set_flash_message('error', 'Lütfen indirim kodu girin.');
            redirect('cart.php');
        }

        $coupon = find_active_discount_code($pdo, $couponInput);
        if (!$coupon) {
            clear_cart_coupon();
            set_flash_message('error', 'İndirim kodu geçersiz veya süresi dolmuş.');
            redirect('cart.php');
        }

        set_cart_coupon([
            'id' => (int) $coupon['id'],
            'kod' => (string) $coupon['kod'],
            'indirim_tipi' => (string) $coupon['indirim_tipi'],
            'indirim_degeri' => (float) $coupon['indirim_degeri'],
            'influencer_id' => (int) ($coupon['influencer_id'] ?? 0),
            'influencer_komisyon_orani' => (float) ($coupon['influencer_komisyon_orani'] ?? 0),
            'influencer_adi' => (string) ($coupon['influencer_adi'] ?? ''),
        ]);
        set_flash_message('success', 'İndirim kodu uygulandı: ' . $coupon['kod']);
        redirect('cart.php');
    }
}

if (!empty($cartIds)) {
    $placeholders = implode(',', array_fill(0, count($cartIds), '?'));
    $stmt = $pdo->prepare("SELECT d.*, y.ad_soyad as yazar_adi FROM denemeler d LEFT JOIN yazarlar y ON d.yazar_id = y.id WHERE d.aktif_mi = 1 AND d.id IN ($placeholders)");
    $stmt->execute($cartIds);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $indexed = [];
    foreach ($rows as $row) {
        $indexed[(int) $row['id']] = $row;
    }

    foreach ($cartIds as $id) {
        if (isset($indexed[$id])) {
            $urunler[] = $indexed[$id];
            $subtotalExVat += (float) $indexed[$id]['fiyat'];
        }
    }
}

if (!empty($cartBundleIds)) {
    try {
        $placeholders = implode(',', array_fill(0, count($cartBundleIds), '?'));
        $stmt = $pdo->prepare("SELECT p.*, COUNT(po.id) as icerik_adedi FROM urun_paketleri p LEFT JOIN urun_paket_ogeleri po ON po.paket_id = p.id WHERE p.aktif_mi = 1 AND p.id IN ($placeholders) GROUP BY p.id");
        $stmt->execute($cartBundleIds);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(int) $row['id']] = $row;
        }

        foreach ($cartBundleIds as $id) {
            if (isset($indexed[$id])) {
                $paketler[] = $indexed[$id];
                $subtotalExVat += (float) $indexed[$id]['fiyat'];
            }
        }
    } catch (Throwable $e) {
        $paketler = [];
    }
}

try {
    $excludeIds = $cartIds;
    if (!empty($excludeIds)) {
        $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
        $sqlPopular = "
            SELECT d.*, y.ad_soyad as yazar_adi, COUNT(sl.id) as satis_adedi
            FROM denemeler d
            LEFT JOIN yazarlar y ON d.yazar_id = y.id
            LEFT JOIN satis_loglari sl ON sl.deneme_id = d.id
            WHERE d.aktif_mi = 1 AND d.id NOT IN ($placeholders)
            GROUP BY d.id
            ORDER BY satis_adedi DESC, d.id DESC
            LIMIT 2
        ";
        $stmtPopular = $pdo->prepare($sqlPopular);
        $stmtPopular->execute($excludeIds);

        $sqlLow = "
            SELECT d.*, y.ad_soyad as yazar_adi, COUNT(sl.id) as satis_adedi
            FROM denemeler d
            LEFT JOIN yazarlar y ON d.yazar_id = y.id
            LEFT JOIN satis_loglari sl ON sl.deneme_id = d.id
            WHERE d.aktif_mi = 1 AND d.id NOT IN ($placeholders)
            GROUP BY d.id
            ORDER BY satis_adedi ASC, d.id DESC
            LIMIT 2
        ";
        $stmtLow = $pdo->prepare($sqlLow);
        $stmtLow->execute($excludeIds);
    } else {
        $stmtPopular = $pdo->query("
            SELECT d.*, y.ad_soyad as yazar_adi, COUNT(sl.id) as satis_adedi
            FROM denemeler d
            LEFT JOIN yazarlar y ON d.yazar_id = y.id
            LEFT JOIN satis_loglari sl ON sl.deneme_id = d.id
            WHERE d.aktif_mi = 1
            GROUP BY d.id
            ORDER BY satis_adedi DESC, d.id DESC
            LIMIT 2
        ");
        $stmtLow = $pdo->query("
            SELECT d.*, y.ad_soyad as yazar_adi, COUNT(sl.id) as satis_adedi
            FROM denemeler d
            LEFT JOIN yazarlar y ON d.yazar_id = y.id
            LEFT JOIN satis_loglari sl ON sl.deneme_id = d.id
            WHERE d.aktif_mi = 1
            GROUP BY d.id
            ORDER BY satis_adedi ASC, d.id DESC
            LIMIT 2
        ");
    }

    $popularSuggestions = $stmtPopular ? $stmtPopular->fetchAll(PDO::FETCH_ASSOC) : [];
    $lowSalesSuggestions = $stmtLow ? $stmtLow->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable $e) {
    $popularSuggestions = [];
    $lowSalesSuggestions = [];
}

$vatAmount = round($subtotalExVat * $vatRate, 2);
$totalInclVat = round($subtotalExVat + $vatAmount, 2);

if ($appliedCoupon) {
    if (($appliedCoupon['indirim_tipi'] ?? '') === 'percent') {
        $discountExVat = round($subtotalExVat * ((float) $appliedCoupon['indirim_degeri'] / 100), 2);
    } else {
        $discountExVat = min($subtotalExVat, round((float) $appliedCoupon['indirim_degeri'], 2));
    }
    $discountVat = round($discountExVat * $vatRate, 2);
    $discountTotal = round($discountExVat + $discountVat, 2);
    $subtotalAfterDiscount = max(0, round($subtotalExVat - $discountExVat, 2));
    $vatAmount = round($subtotalAfterDiscount * $vatRate, 2);
    $totalInclVat = round($subtotalAfterDiscount + $vatAmount, 2);
}

include_once __DIR__ . '/templates/header.php';
?>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="fw-bold text-primary mb-0">Sepetim</h1>
        <a href="store.php" class="btn btn-outline-secondary rounded-pill px-4">Mağazaya Dön</a>
    </div>

    <?php if (empty($urunler) && empty($paketler)): ?>
        <div class="alert alert-light border rounded-4 py-5 text-center">
            <h5 class="mb-2">Sepetiniz boş</h5>
            <p class="text-muted mb-0">Mağazadan ürün ekleyerek ödeme adımına geçebilirsiniz.</p>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <div class="col-lg-8">
                <?php foreach ($urunler as $u): ?>
                    <div class="card border-0 shadow-sm rounded-4 mb-3">
                        <div class="card-body d-flex justify-content-between align-items-center gap-3">
                            <div>
                                <div class="fw-bold"><?php echo escape_html($u['deneme_adi']); ?></div>
                                <div class="small text-muted">Yazar: <?php echo escape_html($u['yazar_adi'] ?: 'DenemeAGS'); ?></div>
                                <div class="small text-muted">Fiyat: <?php echo number_format((float) $u['fiyat'], 2); ?> TL + KDV</div>
                            </div>
                            <a href="cart_action.php?action=remove&id=<?php echo (int) $u['id']; ?>" class="btn btn-outline-danger btn-sm rounded-pill">Çıkar</a>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php foreach ($paketler as $p): ?>
                    <div class="card border-0 shadow-sm rounded-4 mb-3 border border-warning-subtle">
                        <div class="card-body d-flex justify-content-between align-items-center gap-3">
                            <div>
                                <div class="fw-bold"><?php echo escape_html($p['paket_adi']); ?> <span class="badge bg-warning text-dark">PAKET</span></div>
                                <div class="small text-muted">İçerik: <?php echo (int) $p['icerik_adedi']; ?> eser</div>
                                <div class="small text-muted">Fiyat: <?php echo number_format((float) $p['fiyat'], 2); ?> TL + KDV</div>
                            </div>
                            <a href="cart_action.php?action=remove_bundle&bundle_id=<?php echo (int) $p['id']; ?>" class="btn btn-outline-danger btn-sm rounded-pill">Çıkar</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3">Sipariş Özeti</h5>
                        <form method="post" class="mb-3">
                            <input type="hidden" name="csrf_token" value="<?php echo escape_html(generate_csrf_token()); ?>">
                            <input type="hidden" name="coupon_action" value="<?php echo $appliedCoupon ? 'remove' : 'apply'; ?>">
                            <?php if (!$appliedCoupon): ?>
                                <label class="form-label small fw-bold">İndirim Kodu</label>
                                <div class="input-group">
                                    <input type="text" name="coupon_code" class="form-control" placeholder="Örn: INFLUENCER5">
                                    <button class="btn btn-outline-primary" type="submit">Uygula</button>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-success py-2 mb-0 d-flex justify-content-between align-items-center">
                                    <span><strong><?php echo escape_html($appliedCoupon['kod']); ?></strong> kodu aktif</span>
                                    <button class="btn btn-sm btn-outline-success" type="submit">Kaldır</button>
                                </div>
                            <?php endif; ?>
                        </form>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Ara Toplam (KDV Hariç)</span>
                            <strong><?php echo number_format($subtotalExVat, 2); ?> TL</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">KDV (%20)</span>
                            <strong><?php echo number_format($vatAmount, 2); ?> TL</strong>
                        </div>
                        <?php if ($discountTotal > 0): ?>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-success">İndirim (<?php echo escape_html($appliedCoupon['kod']); ?>)</span>
                                <strong class="text-success">-<?php echo number_format($discountTotal, 2); ?> TL</strong>
                            </div>
                        <?php endif; ?>
                        <hr>
                        <div class="d-flex justify-content-between mb-4">
                            <span class="fw-bold">Ödenecek Toplam</span>
                            <strong class="text-primary fs-5"><?php echo number_format($totalInclVat, 2); ?> TL</strong>
                        </div>

                        <?php if (isLoggedIn()): ?>
                            <a href="checkout.php" class="btn btn-primary w-100 rounded-pill fw-bold">Ödemeye Geç</a>
                        <?php else: ?>
                            <a href="login.php" class="btn btn-primary w-100 rounded-pill fw-bold">Giriş Yapıp Öde</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($popularSuggestions) || !empty($lowSalesSuggestions)): ?>
        <div class="mt-5">
            <h4 class="fw-bold text-primary mb-3">Bunlari da begenebilirsiniz</h4>
            <div class="row g-3">
                <?php foreach (array_merge($popularSuggestions, $lowSalesSuggestions) as $s): ?>
                    <div class="col-md-6 col-lg-3">
                        <div class="card border-0 shadow-sm rounded-4 h-100">
                            <div class="card-body">
                                <div class="small text-muted mb-1"><?php echo ((int) $s['satis_adedi'] > 0) ? 'Populer' : 'Yeni/az satilan'; ?></div>
                                <div class="fw-bold mb-1"><?php echo escape_html($s['deneme_adi']); ?></div>
                                <div class="small text-muted mb-3"><?php echo escape_html($s['yazar_adi'] ?: 'Platform'); ?></div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong class="text-primary"><?php echo number_format((float) $s['fiyat'], 2); ?> TL + KDV</strong>
                                    <a href="cart_action.php?action=add&id=<?php echo (int) $s['id']; ?>" class="btn btn-sm btn-primary rounded-pill">Ekle</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include_once __DIR__ . '/templates/footer.php'; ?>
