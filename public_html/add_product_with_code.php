<?php
/**
 * add_product_with_code.php
 * Öğrencinin girdiği erişim kodunu doğrular ve kullanıcının kütüphanesine (kullanici_erisimleri) ekler.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

// 1. Giriş ve Metot Kontrolü
if (!isLoggedIn()) {
    set_flash_message('error', 'Lütfen önce giriş yapınız.');
    redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('dashboard.php');
}

// 2. Güvenlik (CSRF) Kontrolü
if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
    set_flash_message('error', 'Güvenlik doğrulaması başarısız. Lütfen sayfayı yenileyip tekrar deneyin.');
    redirect('dashboard.php');
}

$user_id = $_SESSION['user_id'];
$urun_kodu = strtoupper(trim($_POST['urun_kodu'] ?? ''));

if (empty($urun_kodu)) {
    set_flash_message('error', 'Lütfen geçerli bir kod giriniz.');
    redirect('dashboard.php');
}

try {
    // 3. Kodu Veritabanında Ara (erisim_kodlari tablosu)
    $stmt_code = $pdo->prepare("
        SELECT id, deneme_id, urun_id, kullanici_id, cok_kullanimlik 
        FROM erisim_kodlari 
        WHERE kod = ? AND kod_turu = 'urun'
    ");
    $stmt_code->execute([$urun_kodu]);
    $code_data = $stmt_code->fetch(PDO::FETCH_ASSOC);

    if (!$code_data) {
        set_flash_message('error', 'Girdiğiniz kod sistemde bulunamadı veya hatalı.');
        redirect('dashboard.php');
    }

    // Geriye dönük uyumluluk için urun_id veya deneme_id'yi seç
    $target_id = $code_data['urun_id'] ?: $code_data['deneme_id'];

    if (!$target_id) {
        set_flash_message('error', 'Bu kodla ilişkilendirilmiş bir ürün bulunamadı.');
        redirect('dashboard.php');
    }

    // 4. Tek Kullanımlık Kod ise Daha Önce Kullanılmış mı Kontrol Et
    if ($code_data['cok_kullanimlik'] == 0 && !is_null($code_data['kullanici_id'])) {
        set_flash_message('error', 'Bu kod daha önce başka bir kullanıcı tarafından aktifleştirilmiş.');
        redirect('dashboard.php');
    }

    // 5. Kullanıcının Kütüphanesinde Zaten Var mı?
    $stmt_check = $pdo->prepare("SELECT id FROM kullanici_erisimleri WHERE kullanici_id = ? AND deneme_id = ?");
    $stmt_check->execute([$user_id, $target_id]);
    
    if ($stmt_check->fetch()) {
        set_flash_message('info', 'Bu yayın kütüphanenizde zaten mevcut.');
        redirect('dashboard.php');
    }

    // 6. İşlemi Başlat (Transaction)
    $pdo->beginTransaction();

    // Kütüphaneye (kullanici_erisimleri) ekle
    $stmt_add = $pdo->prepare("INSERT INTO kullanici_erisimleri (kullanici_id, deneme_id, erisim_kodu_id, erisim_tarihi) VALUES (?, ?, ?, NOW())");
    $stmt_add->execute([$user_id, $target_id, $code_data['id']]);

    // Eğer kod TEK KULLANIMLIK ise, kodu bu kullanıcıya kilitle
    if ($code_data['cok_kullanimlik'] == 0) {
        $stmt_update = $pdo->prepare("UPDATE erisim_kodlari SET kullanici_id = ?, kullanilma_tarihi = NOW() WHERE id = ?");
        $stmt_update->execute([$user_id, $code_data['id']]);
    }

    $pdo->commit();
    
    set_flash_message('success', 'Harika! İçerik kütüphanenize başarıyla eklendi.');
    redirect('dashboard.php');

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Kod Aktivasyon Hatası: " . $e->getMessage());
    set_flash_message('error', 'Bir sorun oluştu, lütfen daha sonra tekrar deneyiniz.');
    redirect('dashboard.php');
}