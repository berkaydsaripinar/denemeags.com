<?php
// activate_exam.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('dashboard.php');
}

if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
    set_flash_message('error', 'Geçersiz istek. Lütfen formu tekrar gönderin.');
    redirect('dashboard.php');
}

$deneme_id = filter_input(INPUT_POST, 'deneme_id', FILTER_VALIDATE_INT);
$girilen_erisim_kodu = strtoupper(trim($_POST['erisim_kodu'] ?? ''));
$user_id = $_SESSION['user_id'];

if (!$deneme_id || empty($girilen_erisim_kodu)) {
    set_flash_message('error', 'Deneme ID veya erişim kodu eksik.');
    redirect('dashboard.php');
}

try {
    // 1. Denemenin varlığını ve aktifliğini kontrol et
    $stmt_deneme_check = $pdo->prepare("SELECT id FROM denemeler WHERE id = :deneme_id AND aktif_mi = 1");
    $stmt_deneme_check->execute([':deneme_id' => $deneme_id]);
    if (!$stmt_deneme_check->fetch()) {
        set_flash_message('error', 'Belirtilen deneme bulunamadı veya aktif değil.');
        redirect('dashboard.php');
    }

    // 2. Kullanıcının bu denemeye zaten katılıp katılmadığını kontrol et
    $stmt_katilim_check = $pdo->prepare("SELECT id FROM kullanici_katilimlari WHERE kullanici_id = :user_id AND deneme_id = :deneme_id");
    $stmt_katilim_check->execute([':user_id' => $user_id, ':deneme_id' => $deneme_id]);
    $mevcut_katilim = $stmt_katilim_check->fetch();

    if ($mevcut_katilim) {
        set_flash_message('info', 'Bu denemeye zaten katıldınız. Sınavınız hazırlanıyor...');
        // Kullanıcıyı sınava devam etmesi veya sonuçları görmesi için yönlendir
        // Eğer yarım kalmışsa loading screen üzerinden exam.php'ye, tamamlanmışsa results.php'ye gidilebilir.
        // Şimdilik direkt loading screen'e, oradan exam.php'ye gidecek. exam.php zaten tamamlanmışsa results'a atar.
        header("Location: " . BASE_URL . "/loading_screen.php?katilim_id=" . $mevcut_katilim['id']);
        exit;
    }

    // 3. Girilen erişim kodunu `deneme_erisim_kodlari` tablosunda ara
    $stmt_kod_check = $pdo->prepare("SELECT id, kullanici_id FROM deneme_erisim_kodlari WHERE kod = :erisim_kodu AND deneme_id = :deneme_id");
    $stmt_kod_check->execute([':erisim_kodu' => $girilen_erisim_kodu, ':deneme_id' => $deneme_id]);
    $kod_bilgisi = $stmt_kod_check->fetch();

    if (!$kod_bilgisi) {
        set_flash_message('error', 'Geçersiz erişim kodu veya bu denemeye ait değil.');
        redirect('dashboard.php');
    }

    if ($kod_bilgisi['kullanici_id'] !== null) {
        set_flash_message('error', 'Bu erişim kodu daha önce kullanılmış.');
        redirect('dashboard.php');
    }

    // 5. Tüm kontrollerden geçtiyse, katılımı oluştur ve kodu güncelle
    $pdo->beginTransaction();

    $stmt_insert_katilim = $pdo->prepare(
        "INSERT INTO kullanici_katilimlari (kullanici_id, deneme_id, erisim_kodu_id, katilim_baslangic_tarihi) 
         VALUES (:user_id, :deneme_id, :erisim_kodu_id, NOW())"
    );
    $stmt_insert_katilim->execute([
        ':user_id' => $user_id,
        ':deneme_id' => $deneme_id,
        ':erisim_kodu_id' => $kod_bilgisi['id']
    ]);
    $yeni_katilim_id = $pdo->lastInsertId();

    $stmt_update_kod = $pdo->prepare(
        "UPDATE deneme_erisim_kodlari SET kullanici_id = :user_id, kullanilma_tarihi = NOW() WHERE id = :kod_id"
    );
    $stmt_update_kod->execute([':user_id' => $user_id, ':kod_id' => $kod_bilgisi['id']]);

    $pdo->commit();

    // Flash mesajı burada göstermeye gerek yok, loading_screen halledecek.
    // set_flash_message('success', 'Erişim kodu kabul edildi. Sınavınız hazırlanıyor!');
    // Doğrudan loading_screen.php'ye yönlendir
    header("Location: " . BASE_URL . "/loading_screen.php?katilim_id=" . $yeni_katilim_id);
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Deneme aktivasyon hatası (Deneme ID: $deneme_id, Kod: $girilen_erisim_kodu, Kullanıcı: $user_id): " . $e->getMessage());
    set_flash_message('error', 'Deneme aktive edilirken bir veritabanı sorunu oluştu: ' . $e->getMessage());
    redirect('dashboard.php');
}
?>
