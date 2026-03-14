<?php
// includes/admin_functions.php
require_once __DIR__ . '/../config.php'; // For session_start and other configs

/**
 * Admin kullanıcısının giriş yapıp yapmadığını kontrol eder.
 * @return bool
 */
function isAdminLoggedIn() {
    if (session_status() == PHP_SESSION_NONE) session_start();
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true && isset($_SESSION['admin_id']);
}

/**
 * Giriş yapmış olan adminin Süper Admin olup olmadığını kontrol eder.
 * @return bool
 */
function isSuperAdmin() {
    return isAdminLoggedIn() && isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'superadmin';
}

/**
 * Giriş yapmış olan adminin yetkili olduğu deneme ID'lerinin bir dizisini döndürür.
 * Eğer Süper Admin ise null döndürür (tüm denemelere yetkili olduğu anlamına gelir).
 * Eğer Sub-Admin ise ve yetkisi yoksa boş bir dizi döndürür.
 * @return array|null
 */
function getAuthorizedDenemeIds() {
    if (isSuperAdmin()) {
        return null; // Süper Admin tüm denemelere yetkilidir, filtreleme yapılmaz.
    }
    if (isAdminLoggedIn() && isset($_SESSION['authorized_deneme_ids'])) {
        // Değerlerin integer olduğundan emin olalım
        return array_map('intval', $_SESSION['authorized_deneme_ids']);
    }
    return []; // Yetkili deneme yoksa boş dizi
}

/**
 * Admin giriş yapmamışsa admin login sayfasına yönlendirir.
 */
function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        $admin_base_url = rtrim(BASE_URL, '/') . '/admin';
        header("Location: " . $admin_base_url . "/index.php");
        exit;
    }
}

/**
 * Admin için CSRF token üretir ve session'a kaydeder.
 * @return string
 */
function generate_admin_csrf_token() {
    if (session_status() == PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['admin_csrf_token'])) {
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['admin_csrf_token'];
}

/**
 * Gönderilen admin CSRF token'ını doğrular.
 * @param string $token
 * @return bool
 */
function verify_admin_csrf_token($token) {
    if (session_status() == PHP_SESSION_NONE) session_start();
    return isset($_SESSION['admin_csrf_token']) && hash_equals($_SESSION['admin_csrf_token'], $token);
}

// ... (set_admin_flash_message, get_admin_flash_messages, generate_unique_codes fonksiyonları aynı kalacak) ...
function set_admin_flash_message($type, $message) {
    if (session_status() == PHP_SESSION_NONE) session_start();
    $_SESSION['admin_flash_messages'][] = ['type' => $type, 'message' => $message];
}

function get_admin_flash_messages() {
    if (session_status() == PHP_SESSION_NONE) session_start();
    if (isset($_SESSION['admin_flash_messages'])) {
        $messages = $_SESSION['admin_flash_messages'];
        unset($_SESSION['admin_flash_messages']);
        return $messages;
    }
    return [];
}
function generate_unique_codes($count = 100, $length = 8) {
    $codes = [];
    $characters = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    $char_length = strlen($characters);
    $generated_count = 0;
    while ($generated_count < $count) {
        $random_string = '';
        for ($j = 0; $j < $length; $j++) {
            $random_string .= $characters[random_int(0, $char_length - 1)];
        }
        if (!in_array($random_string, $codes)) {
            $codes[] = strtoupper($random_string);
            $generated_count++;
        }
    }
    return $codes;
}

function create_payout_batch(PDO $pdo, array $batchData, array $items): int
{
    if (empty($items)) {
        throw new InvalidArgumentException('Batch kalemi olmadan batch olusturulamaz.');
    }

    $stmtBatch = $pdo->prepare("
        INSERT INTO odeme_batchleri (batch_tipi, batch_adi, durum, toplam_tutar, planlanan_odeme_tarihi, notlar, created_by_admin_id, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");

    $totalAmount = 0.0;
    foreach ($items as $item) {
        $totalAmount += (float) ($item['tutar'] ?? 0);
    }

    $stmtBatch->execute([
        $batchData['batch_tipi'],
        $batchData['batch_adi'],
        $batchData['durum'] ?? 'draft',
        $totalAmount,
        $batchData['planlanan_odeme_tarihi'] ?? null,
        $batchData['notlar'] ?? null,
        $batchData['created_by_admin_id'] ?? null,
    ]);

    $batchId = (int) $pdo->lastInsertId();

    $stmtItem = $pdo->prepare("
        INSERT INTO odeme_batch_kalemleri (batch_id, referans_tipi, referans_id, yazar_id, influencer_id, tutar, aciklama, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    foreach ($items as $item) {
        $stmtItem->execute([
            $batchId,
            $item['referans_tipi'],
            $item['referans_id'] ?? null,
            $item['yazar_id'] ?? null,
            $item['influencer_id'] ?? null,
            $item['tutar'],
            $item['aciklama'] ?? null,
        ]);
    }

    return $batchId;
}

function mark_payout_batch_paid(PDO $pdo, int $batchId, ?int $adminId = null): void
{
    $stmtBatch = $pdo->prepare('SELECT * FROM odeme_batchleri WHERE id = ? LIMIT 1');
    $stmtBatch->execute([$batchId]);
    $batch = $stmtBatch->fetch(PDO::FETCH_ASSOC);

    if (!$batch) {
        throw new RuntimeException('Batch bulunamadi.');
    }

    if ($batch['durum'] === 'paid') {
        return;
    }

    if ($batch['durum'] === 'cancelled') {
        throw new RuntimeException('Iptal edilen batch odendi yapilamaz.');
    }

    $stmtItems = $pdo->prepare('SELECT * FROM odeme_batch_kalemleri WHERE batch_id = ? ORDER BY id ASC');
    $stmtItems->execute([$batchId]);
    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    if (empty($items)) {
        throw new RuntimeException('Batch kalemi bulunamadi.');
    }

    $payoutDate = date('Y-m-d H:i:s');
    $authorPayoutTotals = [];

    $stmtLedgerCheck = $pdo->prepare('SELECT id FROM muhasebe_hareketleri WHERE hareket_tipi = "payout" AND kaynak_tipi = "payout_batch_item" AND kaynak_id = ? LIMIT 1');
    $stmtLedgerInsert = $pdo->prepare("
        INSERT INTO muhasebe_hareketleri (hareket_tipi, yon, kaynak_tipi, kaynak_id, siparis_id, kullanici_id, yazar_id, influencer_id, tutar, para_birimi, aciklama, metadata_json, hareket_tarihi, created_at)
        VALUES ('payout', 'out', 'payout_batch_item', ?, ?, NULL, ?, ?, ?, 'TRY', ?, ?, ?, NOW())
    ");

    foreach ($items as $item) {
        $itemId = (int) $item['id'];
        $stmtLedgerCheck->execute([$itemId]);
        $ledgerExists = (bool) $stmtLedgerCheck->fetchColumn();

        if ($item['referans_tipi'] === 'sale_log' && !empty($item['referans_id'])) {
            $stmtSale = $pdo->prepare('UPDATE satis_loglari SET yazar_odeme_durumu = "odendi", yazar_odeme_tarihi = ? WHERE id = ?');
            $stmtSale->execute([$payoutDate, (int) $item['referans_id']]);

            if (!empty($item['yazar_id'])) {
                if (!isset($authorPayoutTotals[$item['yazar_id']])) {
                    $authorPayoutTotals[$item['yazar_id']] = [
                        'tutar' => 0.0,
                        'sale_ids' => [],
                    ];
                }
                $authorPayoutTotals[$item['yazar_id']]['tutar'] += (float) $item['tutar'];
                $authorPayoutTotals[$item['yazar_id']]['sale_ids'][] = (int) $item['referans_id'];
            }
        }

        if (!$ledgerExists) {
            $stmtLedgerInsert->execute([
                $itemId,
                'BATCH-' . $batchId,
                $item['yazar_id'] ?: null,
                $item['influencer_id'] ?: null,
                $item['tutar'],
                $item['aciklama'] ?: ('Odeme batchi #' . $batchId),
                json_encode([
                    'batch_id' => $batchId,
                    'batch_type' => $batch['batch_tipi'],
                    'referans_tipi' => $item['referans_tipi'],
                    'referans_id' => $item['referans_id'],
                    'admin_id' => $adminId,
                ], JSON_UNESCAPED_UNICODE),
                $payoutDate,
            ]);
        }
    }

    if ($batch['batch_tipi'] === 'author' && !empty($authorPayoutTotals)) {
        $stmtLegacy = $pdo->prepare('INSERT INTO yazar_odemeleri (yazar_id, tutar, notlar) VALUES (?, ?, ?)');
        foreach ($authorPayoutTotals as $authorId => $authorData) {
            $stmtLegacy->execute([
                $authorId,
                $authorData['tutar'],
                'Batch #' . $batchId . ' | Satış ID: ' . implode(',', $authorData['sale_ids']),
            ]);
        }
    }

    $stmtUpdate = $pdo->prepare('UPDATE odeme_batchleri SET durum = "paid", updated_at = NOW() WHERE id = ?');
    $stmtUpdate->execute([$batchId]);
}
?>
