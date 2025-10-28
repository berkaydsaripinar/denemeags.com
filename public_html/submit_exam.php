<?php
// submit_exam.php (Katılım ID ile Cevapları İşleme ve Çan Eğrisi Hesaplama)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php'; // recalculateAndApplyBellCurve fonksiyonu burada olmalı

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('dashboard.php');
}

if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
    set_flash_message('error', 'Geçersiz istek. Lütfen formu tekrar gönderin.');
    redirect('dashboard.php');
}

$katilim_id = filter_input(INPUT_POST, 'katilim_id', FILTER_VALIDATE_INT);
$user_id = $_SESSION['user_id'];
$gelen_cevaplar = $_POST['cevaplar'] ?? [];

if (!$katilim_id) {
    set_flash_message('error', 'Geçersiz katılım ID.');
    redirect('dashboard.php');
}

$deneme_id_for_bell_curve = null; // Çan eğrisi için deneme ID'sini sakla

try {
    // Katılım bilgilerini ve ilişkili deneme bilgilerini çek
    $stmt_katilim_info = $pdo->prepare("
        SELECT 
            kk.id AS katilim_id, 
            kk.deneme_id,
            kk.sinav_tamamlama_tarihi,
            d.soru_sayisi,
            d.aktif_mi AS deneme_aktif_mi
        FROM kullanici_katilimlari kk
        JOIN denemeler d ON kk.deneme_id = d.id
        WHERE kk.id = :katilim_id AND kk.kullanici_id = :user_id
    ");
    $stmt_katilim_info->execute([':katilim_id' => $katilim_id, ':user_id' => $user_id]);
    $katilim_detaylari = $stmt_katilim_info->fetch();

    if (!$katilim_detaylari) {
        set_flash_message('error', 'Geçersiz katılım ID veya bu katılım size ait değil.');
        redirect('dashboard.php');
    }

    if (!$katilim_detaylari['deneme_aktif_mi']) {
        set_flash_message('error', 'Bu deneme artık aktif değil, cevaplarınız kaydedilemedi.');
        redirect('dashboard.php');
    }
    
    if (!empty($katilim_detaylari['sinav_tamamlama_tarihi'])) {
        set_flash_message('info', 'Bu denemeyi zaten daha önce gönderdiniz.');
        redirect('results.php?katilim_id=' . $katilim_id);
    }

    $deneme_id_for_bell_curve = $katilim_detaylari['deneme_id']; // Deneme ID'sini al
    $soru_sayisi = $katilim_detaylari['soru_sayisi'];

    // Cevap anahtarını çek
    $stmt_cevap_anahtari = $pdo->prepare("SELECT soru_no, dogru_cevap, konu_adi FROM cevap_anahtarlari WHERE deneme_id = ?");
    $stmt_cevap_anahtari->execute([$deneme_id_for_bell_curve]);
    $cevap_anahtari_raw = $stmt_cevap_anahtari->fetchAll();
    $cevap_anahtari = [];
    foreach ($cevap_anahtari_raw as $ca) {
        $cevap_anahtari[$ca['soru_no']] = ['dogru_cevap' => $ca['dogru_cevap'], 'konu_adi' => $ca['konu_adi']];
    }

    if (count($cevap_anahtari) < $soru_sayisi) { 
         set_flash_message('error', 'Bu denemenin cevap anahtarı eksik. Lütfen yönetici ile iletişime geçin. Cevaplarınız kaydedilemedi.');
         redirect('exam.php?katilim_id=' . $katilim_id); 
    }

    $dogru_sayisi = 0;
    $yanlis_sayisi = 0;
    $bos_sayisi = 0;

    $pdo->beginTransaction();

    $stmt_delete_old_answers = $pdo->prepare("DELETE FROM kullanici_cevaplari WHERE katilim_id = ?");
    $stmt_delete_old_answers->execute([$katilim_id]);

    $stmt_insert_cevap = $pdo->prepare(
        "INSERT INTO kullanici_cevaplari (katilim_id, soru_no, verilen_cevap, dogru_mu) 
         VALUES (:katilim_id, :soru_no, :verilen_cevap, :dogru_mu)"
    );

    for ($i = 1; $i <= $soru_sayisi; $i++) {
        $verilen_cevap = isset($gelen_cevaplar[$i]) ? trim($gelen_cevaplar[$i]) : null;
        $dogru_mu = null;

        if (empty($verilen_cevap)) {
            $bos_sayisi++;
            $verilen_cevap_db = null; 
        } else {
            $verilen_cevap_db = $verilen_cevap; 
            if (isset($cevap_anahtari[$i])) {
                if ($verilen_cevap === $cevap_anahtari[$i]['dogru_cevap']) {
                    $dogru_sayisi++;
                    $dogru_mu = 1;
                } else {
                    $yanlis_sayisi++;
                    $dogru_mu = 0;
                }
            } else { 
                $bos_sayisi++; 
            }
        }
        
        $stmt_insert_cevap->execute([
            ':katilim_id' => $katilim_id,
            ':soru_no' => $i,
            ':verilen_cevap' => $verilen_cevap_db,
            ':dogru_mu' => $dogru_mu
        ]);
    }

    $net_sayisi = $dogru_sayisi - ($yanlis_sayisi / NET_KATSAYISI);
    $puan = $net_sayisi * PUAN_CARPANI; 

    $stmt_update_katilim_sonuc = $pdo->prepare(
        "UPDATE kullanici_katilimlari SET 
            dogru_sayisi = :dogru_sayisi, 
            yanlis_sayisi = :yanlis_sayisi, 
            bos_sayisi = :bos_sayisi, 
            net_sayisi = :net_sayisi, 
            puan = :puan, 
            sinav_tamamlama_tarihi = NOW()
         WHERE id = :katilim_id AND kullanici_id = :user_id"
    );
    $stmt_update_katilim_sonuc->execute([
        ':dogru_sayisi' => $dogru_sayisi,
        ':yanlis_sayisi' => $yanlis_sayisi,
        ':bos_sayisi' => $bos_sayisi,
        ':net_sayisi' => $net_sayisi,
        ':puan' => $puan,
        ':katilim_id' => $katilim_id,
        ':user_id' => $user_id 
    ]);

    $pdo->commit(); // Ham puanlar ve cevaplar kaydedildi.

    // Şimdi tüm deneme için çan eğrisi puanlarını yeniden hesapla
    if ($deneme_id_for_bell_curve !== null) {
        if (recalculateAndApplyBellCurve($deneme_id_for_bell_curve, $pdo)) {
            // Başarılı, bir şey yapmaya gerek yok, loglandı.
        } else {
            // Başarısız, loglandı. Kullanıcı yine de sonuçlarını görebilmeli.
            set_flash_message('warning', 'Ham puanınız kaydedildi ancak genel puanlama güncellenirken bir sorun oluştu. Lütfen daha sonra tekrar kontrol edin.');
        }
    }

    set_flash_message('success', 'Sınavınız başarıyla gönderildi! Sonuçlarınız hesaplandı.');
    redirect('results.php?katilim_id=' . $katilim_id);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Sınav gönderme hatası (katilim_id: $katilim_id, user_id: $user_id): " . $e->getMessage());
    set_flash_message('error', 'Sınav gönderilirken bir veritabanı sorunu oluştu: ' . $e->getMessage());
    redirect('exam.php?katilim_id=' . $katilim_id); 
}
?>
