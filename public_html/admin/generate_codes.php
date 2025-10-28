<?php
// admin/generate_codes.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_functions.php'; // generate_unique_codes için

requireAdminLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: manage_kodlar.php");
    exit;
}

if (!verify_admin_csrf_token($_POST['csrf_token'])) {
    set_admin_flash_message('error', 'Geçersiz CSRF token.');
    header("Location: manage_kodlar.php");
    exit;
}

$deneme_id_for_codes = filter_input(INPUT_POST, 'deneme_id', FILTER_VALIDATE_INT);
$adet = filter_input(INPUT_POST, 'adet', FILTER_VALIDATE_INT, ['options' => ['default' => 100, 'min_range' => 1, 'max_range' => 5000]]);
$uzunluk = filter_input(INPUT_POST, 'uzunluk', FILTER_VALIDATE_INT, ['options' => ['default' => 8, 'min_range' => 5, 'max_range' => 15]]);

if (!$deneme_id_for_codes) {
    set_admin_flash_message('error', 'Lütfen kodların üretileceği denemeyi seçin.');
    header("Location: manage_kodlar.php");
    exit;
}

if ($adet <= 0 || $uzunluk <= 0) {
    set_admin_flash_message('error', 'Geçersiz adet veya uzunluk değeri.');
    header("Location: manage_kodlar.php");
    exit;
}

// Seçilen denemenin varlığını kontrol et (isteğe bağlı ama iyi bir pratik)
try {
    $stmt_check_deneme = $pdo->prepare("SELECT id FROM denemeler WHERE id = ?");
    $stmt_check_deneme->execute([$deneme_id_for_codes]);
    if (!$stmt_check_deneme->fetch()) {
        set_admin_flash_message('error', 'Seçilen deneme bulunamadı.');
        header("Location: manage_kodlar.php");
        exit;
    }
} catch (PDOException $e) {
    set_admin_flash_message('error', 'Deneme kontrolü sırasında hata: ' . $e->getMessage());
    header("Location: manage_kodlar.php");
    exit;
}


$yeni_kodlar = generate_unique_codes($adet, $uzunluk); // Bu fonksiyon admin_functions.php içinde
$eklenen_sayisi = 0;
$zaten_var_sayisi = 0;

if (empty($yeni_kodlar)) {
    set_admin_flash_message('warning', 'Hiç yeni kod üretilemedi (muhtemelen çakışma oldu, tekrar deneyin).');
    header("Location: manage_kodlar.php");
    exit;
}

try {
    $pdo->beginTransaction();
    // INSERT IGNORE: Eğer kod zaten varsa (UNIQUE kısıtlaması nedeniyle) hata vermez, eklemez.
    $stmt = $pdo->prepare("INSERT IGNORE INTO deneme_erisim_kodlari (kod, deneme_id) VALUES (?, ?)");
    
    foreach ($yeni_kodlar as $kod) {
        $stmt->execute([$kod, $deneme_id_for_codes]);
        if ($stmt->rowCount() > 0) { // rowCount() > 0 ise yeni kayıt eklendi
            $eklenen_sayisi++;
        } else { // rowCount() == 0 ise kod zaten vardı (başka bir denemede veya aynı denemede), IGNORE edildi
            $zaten_var_sayisi++;
        }
    }
    $pdo->commit();

    if ($eklenen_sayisi > 0) {
        set_admin_flash_message('success', "$eklenen_sayisi adet yeni benzersiz kod başarıyla seçilen deneme için veritabanına eklendi.");
    } else {
        set_admin_flash_message('info', "Yeni kod eklenemedi. Üretilen kodlar muhtemelen veritabanında zaten mevcuttu veya bir sorun oluştu.");
    }
    if ($zaten_var_sayisi > 0 && $eklenen_sayisi == 0) { // Sadece zaten var olanlar üretildiyse
         set_admin_flash_message('warning', "$zaten_var_sayisi adet üretilen kod veritabanında zaten mevcut olduğu için eklenmedi.");
    } elseif ($zaten_var_sayisi > 0) {
         set_admin_flash_message('info', "Ek olarak, $zaten_var_sayisi adet üretilen kod veritabanında zaten mevcut olduğu için atlandı.");
    }


} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_admin_flash_message('error', "Kodlar veritabanına eklenirken hata: " . $e->getMessage());
}

header("Location: manage_kodlar.php" . ($deneme_id_for_codes ? "?filter_deneme_id=".$deneme_id_for_codes : ""));
exit;
?>
