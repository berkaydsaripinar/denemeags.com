<?php
// start_exam.php
// Kullanıcının zaten sahip olduğu bir ürün (deneme) için sınav katılımını başlatır.

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

$deneme_id = filter_input(INPUT_POST, 'deneme_id', FILTER_VALIDATE_INT);
$user_id = $_SESSION['user_id'];

if (!$deneme_id) {
    set_flash_message('error', 'Deneme ID eksik.');
    redirect('dashboard.php');
}

try {
    // 1. Kullanıcının bu ürüne erişimi var mı kontrol et
    // DÜZELTME: 'urun_id' yerine 'deneme_id' kullanıldı.
    $stmt_access = $pdo->prepare("SELECT id FROM kullanici_erisimleri WHERE kullanici_id = ? AND deneme_id = ?");
    $stmt_access->execute([$user_id, $deneme_id]);
    
    if (!$stmt_access->fetch()) {
        set_flash_message('error', 'Bu denemeye erişim yetkiniz yok. Lütfen önce kütüphanenize ekleyin.');
        redirect('dashboard.php');
    }

    // 2. Zaten katılım var mı?
    $stmt_katilim_check = $pdo->prepare("SELECT id FROM kullanici_katilimlari WHERE kullanici_id = ? AND deneme_id = ?");
    $stmt_katilim_check->execute([$user_id, $deneme_id]);
    $mevcut_katilim = $stmt_katilim_check->fetch();

    if ($mevcut_katilim) {
        // Zaten varsa, yükleme ekranına yönlendir
        header("Location: " . BASE_URL . "/loading_screen.php?katilim_id=" . $mevcut_katilim['id']);
        exit;
    }

    // 3. Yeni Katılım Oluştur
    // Erişim kodunu bul (kullanici_erisimleri'nden)
    // DÜZELTME: 'urun_id' yerine 'deneme_id' kullanıldı.
    $stmt_get_code_id = $pdo->prepare("SELECT erisim_kodu_id FROM kullanici_erisimleri WHERE kullanici_id = ? AND deneme_id = ?");
    $stmt_get_code_id->execute([$user_id, $deneme_id]);
    $erisim_kodu_id = $stmt_get_code_id->fetchColumn();
    
    // fetchColumn() false dönerse NULL olarak ayarla
    if ($erisim_kodu_id === false) {
        $erisim_kodu_id = null;
    }

    $stmt_insert_katilim = $pdo->prepare(
        "INSERT INTO kullanici_katilimlari (kullanici_id, deneme_id, erisim_kodu_id, katilim_baslangic_tarihi) 
         VALUES (:user_id, :deneme_id, :erisim_kodu_id, NOW())"
    );
    $stmt_insert_katilim->execute([
        ':user_id' => $user_id,
        ':deneme_id' => $deneme_id,
        ':erisim_kodu_id' => $erisim_kodu_id
    ]);
    $yeni_katilim_id = $pdo->lastInsertId();

    // Yükleme ekranına yönlendir
    header("Location: " . BASE_URL . "/loading_screen.php?katilim_id=" . $yeni_katilim_id);
    exit;

} catch (Exception $e) {
    error_log("Sınav başlatma hatası: " . $e->getMessage());
    
    // Hata ayıklama için hatayı geçici olarak gösterelim (Test bittikten sonra kaldırabilirsiniz)
     set_flash_message('error', 'Hata Detayı: ' . $e->getMessage());
    
    set_flash_message('error', 'Sınav başlatılırken bir sorun oluştu.');
    redirect('dashboard.php');
}
?>