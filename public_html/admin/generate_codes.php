<?php
// admin/generate_codes.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_functions.php'; 

requireAdminLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: manage_kodlar.php"); exit;
}

if (!verify_admin_csrf_token($_POST['csrf_token'])) {
    set_admin_flash_message('error', 'Geçersiz CSRF token.');
    header("Location: manage_kodlar.php"); exit;
}

$islem_tipi = $_POST['islem_tipi'] ?? 'toplu'; // 'toplu' veya 'manuel'
$kod_turu = $_POST['kod_turu'] ?? 'urun'; // 'urun' veya 'kayit'
$redirect_tab = 'urun';

try {
    $pdo->beginTransaction();

    // --- MANUEL / ÖZEL KOD EKLEME ---
    if ($islem_tipi === 'manuel') {
        
        // Sadece süper admin özel kod ekleyebilir (İsterseniz bunu değiştirebilirsiniz)
        if (!isSuperAdmin()) {
             throw new Exception('Özel kod ekleme yetkiniz yok.');
        }

        $ozel_kod = strtoupper(trim($_POST['ozel_kod'] ?? ''));
        $cok_kullanimlik = isset($_POST['cok_kullanimlik']) ? 1 : 0;
        $redirect_tab = 'ozel';

        if (empty($ozel_kod)) throw new Exception('Kod alanı boş bırakılamaz.');
        if (strlen($ozel_kod) < 3) throw new Exception('Kod en az 3 karakter olmalıdır.');

        if ($kod_turu === 'urun') {
            $deneme_id = filter_input(INPUT_POST, 'deneme_id', FILTER_VALIDATE_INT);
            if (!$deneme_id) throw new Exception('Lütfen bir ürün/deneme seçin.');
            
            // Aynı kod var mı kontrolü (Unique Key zaten var ama temiz hata için)
            $stmt_check = $pdo->prepare("SELECT id FROM erisim_kodlari WHERE kod = ?");
            $stmt_check->execute([$ozel_kod]);
            if ($stmt_check->fetch()) throw new Exception("Bu kod ('$ozel_kod') zaten sistemde mevcut.");

            $stmt = $pdo->prepare("INSERT INTO erisim_kodlari (kod, deneme_id, urun_id, kod_turu, cok_kullanimlik) VALUES (?, ?, ?, 'urun', ?)");
            $stmt->execute([$ozel_kod, $deneme_id, $deneme_id, $cok_kullanimlik]);
            
            set_admin_flash_message('success', "Özel ürün kodu '$ozel_kod' başarıyla eklendi.");

        } elseif ($kod_turu === 'kayit') {
            $stmt_check = $pdo->prepare("SELECT id FROM kayit_kodlari WHERE kod = ?");
            $stmt_check->execute([$ozel_kod]);
            if ($stmt_check->fetch()) throw new Exception("Bu kod ('$ozel_kod') zaten kayıt kodlarında mevcut.");

            $stmt = $pdo->prepare("INSERT INTO kayit_kodlari (kod, cok_kullanimlik) VALUES (?, ?)");
            $stmt->execute([$ozel_kod, $cok_kullanimlik]);

            set_admin_flash_message('success', "Özel kayıt kodu '$ozel_kod' başarıyla eklendi.");
        }

    // --- TOPLU RASTGELE KOD ÜRETİMİ ---
    } else {
        $adet = filter_input(INPUT_POST, 'adet', FILTER_VALIDATE_INT, ['options' => ['default' => 100, 'min_range' => 1, 'max_range' => 5000]]);
        $uzunluk = filter_input(INPUT_POST, 'uzunluk', FILTER_VALIDATE_INT, ['options' => ['default' => 8, 'min_range' => 5, 'max_range' => 20]]);
        $yeni_kodlar = generate_unique_codes($adet, $uzunluk);
        $eklenen_sayisi = 0;

        if ($kod_turu === 'urun') {
            $deneme_id = filter_input(INPUT_POST, 'deneme_id', FILTER_VALIDATE_INT);
            $authorized_ids = getAuthorizedDenemeIds();
            if ($authorized_ids !== null && !in_array($deneme_id, $authorized_ids)) {
                throw new Exception('Bu deneme için kod üretme yetkiniz yok.');
            }
            if (!$deneme_id) throw new Exception('Lütfen bir deneme seçin.');

            $stmt = $pdo->prepare("INSERT IGNORE INTO erisim_kodlari (kod, deneme_id, urun_id, kod_turu, cok_kullanimlik) VALUES (?, ?, ?, 'urun', 0)");
            foreach ($yeni_kodlar as $kod) {
                $stmt->execute([$kod, $deneme_id, $deneme_id]);
                if ($stmt->rowCount() > 0) $eklenen_sayisi++;
            }
            $redirect_tab = 'urun';

        } elseif ($kod_turu === 'kayit') {
            if (!isSuperAdmin()) throw new Exception('Kayıt kodu üretme yetkiniz yok.');

            $stmt = $pdo->prepare("INSERT IGNORE INTO kayit_kodlari (kod, cok_kullanimlik) VALUES (?, 0)");
            foreach ($yeni_kodlar as $kod) {
                $stmt->execute([$kod]);
                if ($stmt->rowCount() > 0) $eklenen_sayisi++;
            }
            $redirect_tab = 'kayit';
        }

        if ($eklenen_sayisi > 0) {
            set_admin_flash_message('success', "$eklenen_sayisi adet kod başarıyla üretildi.");
        } else {
            set_admin_flash_message('warning', "Hiç kod eklenemedi.");
        }
    }

    $pdo->commit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    set_admin_flash_message('error', "Hata: " . $e->getMessage());
}

header("Location: manage_kodlar.php?tab=" . $redirect_tab);
exit;
?>