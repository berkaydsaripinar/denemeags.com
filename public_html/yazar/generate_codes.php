<?php
// yazar/generate_codes.php - Yazar Tarafından Kod Üretme Mantığı
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

// Oturum kontrolü (config.php zaten session_start() içerir)
if (!isset($_SESSION['yazar_id'])) { 
    redirect('yazar/login.php'); 
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
    redirect('yazar/manage_codes.php'); 
}

// Güvenlik doğrulaması
if (!verify_csrf_token($_POST['csrf_token'])) {
    set_flash_message('error', 'Güvenlik doğrulaması başarısız.');
    redirect('yazar/manage_codes.php');
}

$yid = $_SESSION['yazar_id'];
$urun_id = filter_input(INPUT_POST, 'urun_id', FILTER_VALIDATE_INT);
$adet = filter_input(INPUT_POST, 'adet', FILTER_VALIDATE_INT, ['options' => ['default' => 10, 'min_range' => 1, 'max_range' => 500]]);
$uzunluk = filter_input(INPUT_POST, 'uzunluk', FILTER_VALIDATE_INT, ['options' => ['default' => 8, 'min_range' => 5, 'max_range' => 15]]);

try {
    // 1. Güvenlik: Seçilen ürün yazara mı ait? 
    // NOT: 'aktif_mi = 1' şartını kaldırdım, yazar onay bekleyen ürünlerine de kod üretebilmeli.
    $stmt_check = $pdo->prepare("SELECT id FROM denemeler WHERE id = ? AND yazar_id = ?");
    $stmt_check->execute([$urun_id, $yid]);
    if (!$stmt_check->fetch()) {
        set_flash_message('error', 'Hatalı ürün seçimi veya bu işleme yetkiniz yok.');
        redirect('yazar/manage_codes.php');
    }

    // 2. Kod Üretme Fonksiyonu
    function generate_author_code($length) {
        $chars = "23456789ABCDEFGHJKLMNPQRSTUVWXYZ"; // Karışıklığı önlemek için 0, O, 1, I çıkarıldı.
        $code = "";
        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        return $code;
    }

    $pdo->beginTransaction();
    $inserted = 0;
    
    // 3. Veritabanına Ekleme
    // INSERT IGNORE kullanarak çakışmaları (duplicate) atlıyoruz.
    // urun_id, deneme_id ve olusturan_yazar_id alanlarını tam dolduruyoruz.
    $stmt_ins = $pdo->prepare("
        INSERT IGNORE INTO erisim_kodlari 
        (kod, kod_turu, urun_id, deneme_id, olusturan_yazar_id, cok_kullanimlik) 
        VALUES (?, 'urun', ?, ?, ?, 0)
    ");

    for ($i = 0; $i < $adet; $i++) {
        $new_code = generate_author_code($uzunluk);
        // Parametreleri sırasıyla: kod, urun_id, deneme_id (geriye dönük), yazar_id
        $stmt_ins->execute([$new_code, $urun_id, $urun_id, $yid]);
        if ($stmt_ins->rowCount() > 0) {
            $inserted++;
        }
    }

    if ($inserted > 0) {
        $pdo->commit();
        set_flash_message('success', "Harika! $inserted adet yeni erişim kodu başarıyla üretildi.");
    } else {
        $pdo->rollBack();
        set_flash_message('error', 'Kodlar üretilemedi. Lütfen adet ve uzunluk değerlerini kontrol edin.');
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Yazar kod üretim hatası: " . $e->getMessage());
    set_flash_message('error', 'İşlem sırasında sistemsel bir hata oluştu: ' . $e->getMessage());
}

// İşlem bittikten sonra listeye geri dön
redirect('yazar/manage_codes.php');