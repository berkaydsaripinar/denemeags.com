<?php
// add_product_with_code.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('dashboard.php');
}

if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
    set_flash_message('error', 'Geçersiz istek.');
    redirect('dashboard.php');
}

$urun_kodu = strtoupper(trim($_POST['urun_kodu'] ?? ''));
$user_id = $_SESSION['user_id'];

if (empty($urun_kodu)) {
    set_flash_message('error', 'Lütfen bir kod giriniz.');
    redirect('dashboard.php');
}

try {
    // 1. Kodu Kontrol Et
    // cok_kullanimlik bilgisini de çek
    $stmt_code = $pdo->prepare("
        SELECT ek.id, ek.deneme_id, ek.kullanici_id, ek.cok_kullanimlik, d.deneme_adi 
        FROM erisim_kodlari ek
        JOIN denemeler d ON ek.deneme_id = d.id
        WHERE ek.kod = ? AND ek.kod_turu = 'urun'
    ");
    $stmt_code->execute([$urun_kodu]);
    $code_data = $stmt_code->fetch(PDO::FETCH_ASSOC);

    if (!$code_data) {
        set_flash_message('error', 'Geçersiz erişim kodu.');
        redirect('dashboard.php');
    }

    // Tek kullanımlıksa ve kullanılmışsa hata ver
    if ($code_data['cok_kullanimlik'] == 0 && $code_data['kullanici_id'] !== null) {
        set_flash_message('error', 'Bu erişim kodu daha önce kullanılmış.');
        redirect('dashboard.php');
    }

    $deneme_id = $code_data['deneme_id'];

    // 2. Kullanıcının bu ürüne zaten sahip olup olmadığını kontrol et
    $stmt_check_access = $pdo->prepare("SELECT id FROM kullanici_erisimleri WHERE kullanici_id = ? AND deneme_id = ?");
    $stmt_check_access->execute([$user_id, $deneme_id]);
    if ($stmt_check_access->fetch()) {
        set_flash_message('info', 'Bu ürüne zaten sahipsiniz.');
        redirect('dashboard.php');
    }

    // 3. Erişimi Tanımla
    $pdo->beginTransaction();

    // Erişimi ekle
    // Eğer kod çok kullanımlıksa erisim_kodu_id'yi kaydetmek mantıklı olabilir (hangi kodla geldiğini bilmek için)
    // veya NULL geçilebilir. Kaydetmek daha iyidir.
    $stmt_add_access = $pdo->prepare("INSERT INTO kullanici_erisimleri (kullanici_id, deneme_id, erisim_kodu_id, erisim_tarihi) VALUES (?, ?, ?, NOW())");
    $stmt_add_access->execute([$user_id, $deneme_id, $code_data['id']]);

    // SADECE TEK KULLANIMLIKSA kodu "kullanıldı" olarak işaretle
    if ($code_data['cok_kullanimlik'] == 0) {
        $stmt_update_code = $pdo->prepare("UPDATE erisim_kodlari SET kullanici_id = ?, kullanilma_tarihi = NOW() WHERE id = ?");
        $stmt_update_code->execute([$user_id, $code_data['id']]);
    }

    $pdo->commit();

    set_flash_message('success', '"' . htmlspecialchars($code_data['deneme_adi']) . '" kütüphanenize başarıyla eklendi!');
    redirect('dashboard.php');

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Ürün ekleme hatası: " . $e->getMessage());
    // Hata mesajını daha detaylı görmek isterseniz (sadece geliştirme aşamasında):
    // set_flash_message('error', 'Hata: ' . $e->getMessage());
    set_flash_message('error', 'İşlem sırasında bir hata oluştu. Lütfen daha sonra tekrar deneyin.');
    redirect('dashboard.php');
}
?>