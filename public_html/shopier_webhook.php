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

function parse_merchant_oid(string $merchantOid): array
{
    if (preg_match('/^[A-Za-z0-9_-]+-(\d+)-(\d+)-/', $merchantOid, $matches)) {
        return [
            'deneme_id' => (int) $matches[1],
            'user_id' => (int) $matches[2],
        ];
    }

    return ['deneme_id' => 0, 'user_id' => 0];
}

function send_ok_and_exit()
{
    echo 'OK';
    exit;
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

$expectedHash = base64_encode(hash_hmac(
    'sha256',
    $merchantOid . PAYTR_MERCHANT_SALT . $status . $totalAmountRaw,
    PAYTR_MERCHANT_KEY,
    true
));

if (!hash_equals($expectedHash, $hashFromPaytr)) {
    debug_log('PAYTR notification failed: bad hash | OID: ' . $merchantOid);
    die('PAYTR notification failed: bad hash');
}

$amount = ((float) $totalAmountRaw) / 100;
$parsed = parse_merchant_oid($merchantOid);
$denemeId = $parsed['deneme_id'];
$userIdFromOid = $parsed['user_id'];

debug_log('══════════════════════════════════════════════════');
debug_log('PAYTR WEBHOOK');
debug_log('OID: ' . $merchantOid);
debug_log('Status: ' . $status);
debug_log('Toplam (kurus): ' . $totalAmountRaw);
debug_log('Toplam (TL): ' . number_format($amount, 2, '.', ''));
debug_log('Parsed deneme_id: ' . $denemeId . ', user_id: ' . $userIdFromOid);

try {
    $pdo->beginTransaction();

    $stmtExistingSale = $pdo->prepare('SELECT id FROM satis_loglari WHERE siparis_id = ? LIMIT 1');
    $stmtExistingSale->execute([$merchantOid]);
    $existingSale = $stmtExistingSale->fetch(PDO::FETCH_ASSOC);

    if ($status === 'success') {
        if ($existingSale) {
            debug_log('Idempotent: Sipariş zaten işlenmiş. OID: ' . $merchantOid);
            $pdo->commit();
            send_ok_and_exit();
        }

        if ($denemeId <= 0 || $userIdFromOid <= 0) {
            debug_log('Geçersiz merchant_oid formatı. OID: ' . $merchantOid);
            $pdo->rollBack();
            send_ok_and_exit();
        }

        $stmtUser = $pdo->prepare('SELECT id, ad_soyad, email FROM kullanicilar WHERE id = ? LIMIT 1');
        $stmtUser->execute([$userIdFromOid]);
        $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

        $stmtDeneme = $pdo->prepare('SELECT d.id, d.deneme_adi, d.yazar_id, y.komisyon_orani FROM denemeler d LEFT JOIN yazarlar y ON d.yazar_id = y.id WHERE d.id = ? LIMIT 1');
        $stmtDeneme->execute([$denemeId]);
        $deneme = $stmtDeneme->fetch(PDO::FETCH_ASSOC);

        if (!$user || !$deneme) {
            debug_log('Kullanıcı veya ürün bulunamadı. OID: ' . $merchantOid);
            $pdo->rollBack();
            send_ok_and_exit();
        }

        $stmtAccess = $pdo->prepare('SELECT id FROM kullanici_erisimleri WHERE kullanici_id = ? AND deneme_id = ? LIMIT 1');
        $stmtAccess->execute([(int) $user['id'], (int) $deneme['id']]);
        if (!$stmtAccess->fetch(PDO::FETCH_ASSOC)) {
            $kod = strtoupper(bin2hex(random_bytes(4)));

            $stmtCode = $pdo->prepare('INSERT INTO erisim_kodlari (kod, kod_turu, urun_id, deneme_id, kullanici_id, kullanilma_tarihi, cok_kullanimlik) VALUES (?, "urun", ?, ?, ?, NOW(), 0)');
            $stmtCode->execute([$kod, (int) $deneme['id'], (int) $deneme['id'], (int) $user['id']]);
            $erisimKoduId = (int) $pdo->lastInsertId();

            $stmtGrant = $pdo->prepare('INSERT INTO kullanici_erisimleri (kullanici_id, deneme_id, erisim_kodu_id, erisim_tarihi) VALUES (?, ?, ?, NOW())');
            $stmtGrant->execute([(int) $user['id'], (int) $deneme['id'], $erisimKoduId]);
            debug_log('Erişim tanımlandı. Kod: ' . $kod);
        } else {
            debug_log('Idempotent: Kullanıcı erişimi zaten mevcut.');
        }

        $komisyonOrani = isset($deneme['komisyon_orani']) ? (float) $deneme['komisyon_orani'] : 0.0;
        $yazarPayi = round($amount * ($komisyonOrani / 100), 2);
        $platformPayi = round($amount - $yazarPayi, 2);

        $stmtSale = $pdo->prepare('INSERT INTO satis_loglari (deneme_id, yazar_id, kullanici_id, siparis_id, tutar_brut, komisyon_yazar_orani, yazar_payi, platform_payi, yazar_odeme_durumu) VALUES (?, ?, ?, ?, ?, ?, ?, ?, "beklemede")');
        $stmtSale->execute([
            (int) $deneme['id'],
            (int) ($deneme['yazar_id'] ?? 0),
            (int) $user['id'],
            $merchantOid,
            $amount,
            $komisyonOrani,
            $yazarPayi,
            $platformPayi,
        ]);

        $subject = '✅ Siparişiniz Onaylandı: ' . $deneme['deneme_adi'];
        $message = '<p>Merhaba ' . escape_html($user['ad_soyad']) . ',</p>'
            . '<p>Ödemeniz başarıyla onaylandı. <strong>' . escape_html($deneme['deneme_adi']) . '</strong> ürününüz kütüphanenize eklendi.</p>'
            . '<p><a href="' . BASE_URL . '/dashboard.php">Kütüphaneye Git</a></p>';

        send_smtp_email((string) $user['email'], $subject, $message);
        debug_log('Başarılı sipariş işlendi. OID: ' . $merchantOid);
    } else {
        if ($existingSale) {
            $stmtFailedUpdate = $pdo->prepare('UPDATE satis_loglari SET yazar_odeme_durumu = "basarisiz" WHERE siparis_id = ?');
            $stmtFailedUpdate->execute([$merchantOid]);
        }

        debug_log('Başarısız ödeme. OID: ' . $merchantOid . ' | Kod: ' . $failedReasonCode . ' | Mesaj: ' . $failedReasonMsg);
    }

    $pdo->commit();
    send_ok_and_exit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    debug_log('EXCEPTION: ' . $e->getMessage());
    send_ok_and_exit();
}
