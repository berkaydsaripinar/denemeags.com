<?php
// PAYTR Bildirim İşleyicisi (mevcut endpoint yolu korunur)
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

function debug_log($text)
{
    $time = date('Y-m-d H:i:s');
    file_put_contents(WEBHOOK_LOG_FILE, '[' . $time . '] ' . $text . PHP_EOL, FILE_APPEND);
}

function send_ok_and_exit()
{
    echo 'OK';
    exit;
}

function build_sale_order_id(string $merchantOid, int $orderItemId, int $denemeId): string
{
    $base = preg_replace('/[^A-Za-z0-9]/', '', $merchantOid);
    $candidate = $base . 'I' . $orderItemId . 'D' . $denemeId;

    // siparis_id kolonu eski şemalarda kısa olabilir; güvenli limitte tutalım.
    if (strlen($candidate) > 64) {
        $candidate = 'OID' . substr(hash('sha256', $candidate), 0, 48);
    }

    return $candidate;
}

function ensure_access(PDO $pdo, int $userId, int $denemeId): void
{
    $stmtAccess = $pdo->prepare('SELECT id FROM kullanici_erisimleri WHERE kullanici_id = ? AND deneme_id = ? LIMIT 1');
    $stmtAccess->execute([$userId, $denemeId]);
    if ($stmtAccess->fetch(PDO::FETCH_ASSOC)) {
        return;
    }

    $kod = strtoupper(bin2hex(random_bytes(4)));
    $stmtCode = $pdo->prepare('INSERT INTO erisim_kodlari (kod, kod_turu, urun_id, deneme_id, kullanici_id, kullanilma_tarihi, cok_kullanimlik) VALUES (?, "urun", ?, ?, ?, NOW(), 0)');
    $stmtCode->execute([$kod, $denemeId, $denemeId, $userId]);
    $erisimKoduId = (int) $pdo->lastInsertId();

    $stmtGrant = $pdo->prepare('INSERT INTO kullanici_erisimleri (kullanici_id, deneme_id, erisim_kodu_id, erisim_tarihi) VALUES (?, ?, ?, NOW())');
    $stmtGrant->execute([$userId, $denemeId, $erisimKoduId]);
}

function insert_sale_log(PDO $pdo, array $sale): void
{
    try {
        $stmt = $pdo->prepare('INSERT INTO satis_loglari (deneme_id, yazar_id, kullanici_id, siparis_id, tutar_brut, komisyon_yazar_orani, yazar_payi, platform_payi, kdv_haric_tutar, kdv_tutari, odenen_toplam_tutar, yazar_odeme_durumu) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "beklemede")');
        $stmt->execute([
            $sale['deneme_id'],
            $sale['yazar_id'],
            $sale['kullanici_id'],
            $sale['siparis_id'],
            $sale['odenen_toplam_tutar'],
            $sale['komisyon_yazar_orani'],
            $sale['yazar_payi'],
            $sale['platform_payi'],
            $sale['kdv_haric_tutar'],
            $sale['kdv_tutari'],
            $sale['odenen_toplam_tutar'],
        ]);
    } catch (Throwable $e) {
        // Eski şemada yeni KDV kolonları olmayabilir.
        $stmt = $pdo->prepare('INSERT INTO satis_loglari (deneme_id, yazar_id, kullanici_id, siparis_id, tutar_brut, komisyon_yazar_orani, yazar_payi, platform_payi, yazar_odeme_durumu) VALUES (?, ?, ?, ?, ?, ?, ?, ?, "beklemede")');
        $stmt->execute([
            $sale['deneme_id'],
            $sale['yazar_id'],
            $sale['kullanici_id'],
            $sale['siparis_id'],
            $sale['odenen_toplam_tutar'],
            $sale['komisyon_yazar_orani'],
            $sale['yazar_payi'],
            $sale['platform_payi'],
        ]);
    }
}

function insert_discount_usage(PDO $pdo, array $order, int $userId): void
{
    $codeId = (int) ($order['discount_code_id'] ?? 0);
    $discountTotal = (float) ($order['discount_total'] ?? 0);
    if ($codeId <= 0 || $discountTotal <= 0) {
        return;
    }

    $stmt = $pdo->prepare('INSERT INTO indirim_kodu_kullanimlari (kod_id, influencer_id, user_id, order_id, merchant_oid, indirim_tutari_ex_vat, indirim_tutari_kdv, indirim_tutari_toplam, influencer_komisyon_tutari, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
    $stmt->execute([
        $codeId,
        !empty($order['influencer_id']) ? (int) $order['influencer_id'] : null,
        $userId,
        (int) ($order['id'] ?? 0),
        (string) ($order['merchant_oid'] ?? ''),
        (float) ($order['discount_amount_ex_vat'] ?? 0),
        (float) ($order['discount_vat_amount'] ?? 0),
        $discountTotal,
        (float) ($order['influencer_commission_total'] ?? 0),
    ]);
}

function insert_accounting_entry(PDO $pdo, array $entry): void
{
    $stmt = $pdo->prepare('INSERT INTO muhasebe_hareketleri (hareket_tipi, yon, kaynak_tipi, kaynak_id, siparis_id, order_id, kullanici_id, yazar_id, influencer_id, tutar, para_birimi, aciklama, metadata_json, hareket_tarihi, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
    $stmt->execute([
        $entry['hareket_tipi'],
        $entry['yon'],
        $entry['kaynak_tipi'] ?? null,
        $entry['kaynak_id'] ?? null,
        $entry['siparis_id'] ?? null,
        $entry['order_id'] ?? null,
        $entry['kullanici_id'] ?? null,
        $entry['yazar_id'] ?? null,
        $entry['influencer_id'] ?? null,
        $entry['tutar'],
        $entry['para_birimi'] ?? 'TRY',
        $entry['aciklama'] ?? null,
        isset($entry['metadata_json']) ? json_encode($entry['metadata_json'], JSON_UNESCAPED_UNICODE) : null,
        $entry['hareket_tarihi'] ?? date('Y-m-d H:i:s'),
    ]);
}

function insert_order_accounting_entries(PDO $pdo, array $order, array $items): void
{
    $orderId = (int) ($order['id'] ?? 0);
    $merchantOid = (string) ($order['merchant_oid'] ?? '');
    $userId = (int) ($order['user_id'] ?? 0);
    $discountTotal = round((float) ($order['discount_total'] ?? 0), 2);
    $vatAmount = round((float) ($order['vat_amount'] ?? 0), 2);
    $subtotalExVat = round((float) ($order['subtotal_ex_vat'] ?? 0), 2);
    $influencerCommission = round((float) ($order['influencer_commission_total'] ?? 0), 2);

    $stmtCheck = $pdo->prepare('SELECT id FROM muhasebe_hareketleri WHERE order_id = ? LIMIT 1');
    $stmtCheck->execute([$orderId]);
    if ($stmtCheck->fetch(PDO::FETCH_ASSOC)) {
        return;
    }

    insert_accounting_entry($pdo, [
        'hareket_tipi' => 'sale',
        'yon' => 'in',
        'kaynak_tipi' => 'paytr_order',
        'kaynak_id' => $orderId,
        'siparis_id' => $merchantOid,
        'order_id' => $orderId,
        'kullanici_id' => $userId,
        'tutar' => round((float) ($order['total_amount'] ?? 0), 2),
        'aciklama' => 'Siparis tahsilati',
        'metadata_json' => ['status' => $order['status'] ?? 'paid'],
    ]);

    if ($discountTotal > 0) {
        insert_accounting_entry($pdo, [
            'hareket_tipi' => 'discount',
            'yon' => 'out',
            'kaynak_tipi' => 'discount_code',
            'kaynak_id' => $order['discount_code_id'] ?? null,
            'siparis_id' => $merchantOid,
            'order_id' => $orderId,
            'kullanici_id' => $userId,
            'influencer_id' => !empty($order['influencer_id']) ? (int) $order['influencer_id'] : null,
            'tutar' => $discountTotal,
            'aciklama' => 'Siparis indirimi',
        ]);
    }

    if ($vatAmount > 0) {
        insert_accounting_entry($pdo, [
            'hareket_tipi' => 'vat',
            'yon' => 'out',
            'kaynak_tipi' => 'paytr_order',
            'kaynak_id' => $orderId,
            'siparis_id' => $merchantOid,
            'order_id' => $orderId,
            'kullanici_id' => $userId,
            'tutar' => $vatAmount,
            'aciklama' => 'Tahsil edilen KDV',
        ]);
    }

    $authorTotal = 0.0;
    foreach ($items as $item) {
        $komisyonOrani = isset($item['komisyon_orani']) ? (float) $item['komisyon_orani'] : 0.0;
        $lineExVat = round((float) $item['line_subtotal_ex_vat'], 2);
        $authorShare = round($lineExVat * ($komisyonOrani / 100), 2);
        $authorTotal += $authorShare;

        if ($authorShare > 0) {
            insert_accounting_entry($pdo, [
                'hareket_tipi' => 'author_commission',
                'yon' => 'out',
                'kaynak_tipi' => 'sale_item',
                'kaynak_id' => $item['id'] ?? null,
                'siparis_id' => $merchantOid,
                'order_id' => $orderId,
                'kullanici_id' => $userId,
                'yazar_id' => !empty($item['yazar_id']) ? (int) $item['yazar_id'] : null,
                'tutar' => $authorShare,
                'aciklama' => 'Yazar hak edisi',
            ]);
        }
    }

    if ($influencerCommission > 0) {
        insert_accounting_entry($pdo, [
            'hareket_tipi' => 'influencer_commission',
            'yon' => 'out',
            'kaynak_tipi' => 'discount_usage',
            'kaynak_id' => $order['discount_code_id'] ?? null,
            'siparis_id' => $merchantOid,
            'order_id' => $orderId,
            'kullanici_id' => $userId,
            'influencer_id' => !empty($order['influencer_id']) ? (int) $order['influencer_id'] : null,
            'tutar' => $influencerCommission,
            'aciklama' => 'Influencer komisyonu',
        ]);
    }

    $platformRevenue = round($subtotalExVat - $authorTotal - $influencerCommission, 2);
    if ($platformRevenue != 0.0) {
        insert_accounting_entry($pdo, [
            'hareket_tipi' => 'platform_revenue',
            'yon' => $platformRevenue >= 0 ? 'in' : 'out',
            'kaynak_tipi' => 'paytr_order',
            'kaynak_id' => $orderId,
            'siparis_id' => $merchantOid,
            'order_id' => $orderId,
            'kullanici_id' => $userId,
            'tutar' => abs($platformRevenue),
            'aciklama' => 'Platform net gelir',
        ]);
    }
}

$post = $_POST;
if (empty($post)) {
    debug_log('PAYTR webhook boş POST ile geldi.');
    http_response_code(400);
    exit;
}

$requiredKeys = ['merchant_oid', 'status', 'total_amount', 'hash'];
foreach ($requiredKeys as $key) {
    if (!isset($post[$key])) {
        debug_log('PAYTR webhook eksik alan: ' . $key);
        http_response_code(400);
        exit;
    }
}

$merchantOid = (string) $post['merchant_oid'];
$status = (string) $post['status'];
$totalAmountRaw = (string) $post['total_amount'];
$hashFromPaytr = (string) $post['hash'];
$failedReasonCode = (string) ($post['failed_reason_code'] ?? '');
$failedReasonMsg = (string) ($post['failed_reason_msg'] ?? '');

$expectedHash = base64_encode(hash_hmac('sha256', $merchantOid . PAYTR_MERCHANT_SALT . $status . $totalAmountRaw, PAYTR_MERCHANT_KEY, true));
if (!hash_equals($expectedHash, $hashFromPaytr)) {
    debug_log('PAYTR notification failed: bad hash | OID: ' . $merchantOid);
    die('PAYTR notification failed: bad hash');
}

debug_log('══════════════════════════════════════════════════');
debug_log('PAYTR WEBHOOK | OID: ' . $merchantOid . ' | status=' . $status . ' | total_amount=' . $totalAmountRaw);

try {
    $pdo->beginTransaction();

    $stmtOrder = $pdo->prepare('SELECT * FROM paytr_orders WHERE merchant_oid = ? LIMIT 1');
    $stmtOrder->execute([$merchantOid]);
    $order = $stmtOrder->fetch(PDO::FETCH_ASSOC);

    if ($status !== 'success') {
        if ($order) {
            $stmtFail = $pdo->prepare('UPDATE paytr_orders SET status = "failed", updated_at = NOW() WHERE id = ?');
            $stmtFail->execute([(int) $order['id']]);
        }

        debug_log('Başarısız ödeme. OID=' . $merchantOid . ' | Kod=' . $failedReasonCode . ' | Mesaj=' . $failedReasonMsg);
        $pdo->commit();
        send_ok_and_exit();
    }

    if (!$order) {
        debug_log('Sipariş kaydı bulunamadı (paytr_orders). OID=' . $merchantOid);
        $pdo->commit();
        send_ok_and_exit();
    }

    if (($order['status'] ?? '') === 'paid') {
        debug_log('Idempotent: Sipariş zaten paid. OID=' . $merchantOid);
        $pdo->commit();
        send_ok_and_exit();
    }

    $userId = (int) $order['user_id'];
    $orderId = (int) $order['id'];

    $stmtItems = $pdo->prepare('SELECT oi.*, d.deneme_adi, d.yazar_id, y.komisyon_orani FROM paytr_order_items oi JOIN denemeler d ON d.id = oi.deneme_id LEFT JOIN yazarlar y ON y.id = d.yazar_id WHERE oi.order_id = ?');
    $stmtItems->execute([$orderId]);
    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    if (empty($items)) {
        debug_log('Sipariş item bulunamadı. order_id=' . $orderId);
        $pdo->commit();
        send_ok_and_exit();
    }

    foreach ($items as $item) {
        $denemeId = (int) $item['deneme_id'];
        $yazarId = (int) ($item['yazar_id'] ?? 0);
        $orderItemId = (int) ($item['id'] ?? 0);
        $saleOrderId = build_sale_order_id($merchantOid, $orderItemId, $denemeId);

        $stmtExistingSale = $pdo->prepare('SELECT id FROM satis_loglari WHERE siparis_id = ? LIMIT 1');
        $stmtExistingSale->execute([$saleOrderId]);
        if ($stmtExistingSale->fetch(PDO::FETCH_ASSOC)) {
            debug_log('Idempotent item skip: siparis_id mevcut | ' . $saleOrderId);
            continue;
        }

        ensure_access($pdo, $userId, $denemeId);

        $kdvHaricTutar = round((float) $item['line_subtotal_ex_vat'], 2);
        $kdvTutari = round((float) $item['line_vat_amount'], 2);
        $odenenToplam = round((float) $item['line_total'], 2);

        $komisyonOrani = isset($item['komisyon_orani']) ? (float) $item['komisyon_orani'] : 0.0;
        $yazarPayi = round($kdvHaricTutar * ($komisyonOrani / 100), 2);
        $platformPayi = round($kdvHaricTutar - $yazarPayi, 2);

        insert_sale_log($pdo, [
            'deneme_id' => $denemeId,
            'yazar_id' => $yazarId,
            'kullanici_id' => $userId,
            'siparis_id' => $saleOrderId,
            'komisyon_yazar_orani' => $komisyonOrani,
            'yazar_payi' => $yazarPayi,
            'platform_payi' => $platformPayi,
            'kdv_haric_tutar' => $kdvHaricTutar,
            'kdv_tutari' => $kdvTutari,
            'odenen_toplam_tutar' => $odenenToplam,
        ]);
    }

    try {
        insert_discount_usage($pdo, $order, $userId);
    } catch (Throwable $e) {
        debug_log('indirim_kodu_kullanimlari insert atlandı: ' . $e->getMessage());
    }

    try {
        insert_order_accounting_entries($pdo, $order, $items);
    } catch (Throwable $e) {
        debug_log('muhasebe_hareketleri insert atlandı: ' . $e->getMessage());
    }

    $stmtPaid = $pdo->prepare('UPDATE paytr_orders SET status = "paid", updated_at = NOW() WHERE id = ?');
    $stmtPaid->execute([$orderId]);

    debug_log('Sipariş işlendi. OID=' . $merchantOid . ' | item=' . count($items));

    $pdo->commit();
    send_ok_and_exit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    debug_log('EXCEPTION: ' . $e->getMessage());
    send_ok_and_exit();
}
