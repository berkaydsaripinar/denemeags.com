<?php
// admin/recalculate_scores.php
// Belirli bir denemenin tüm katılımcı sonuçlarını güncel cevap anahtarına göre yeniden hesaplar.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';       // Genel fonksiyonlar
require_once __DIR__ . '/../includes/admin_functions.php'; // Admin fonksiyonları

requireAdminLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_admin_flash_message('error', 'Geçersiz istek türü.');
    header("Location: manage_denemeler.php");
    exit;
}

if (!isset($_POST['csrf_token']) || !verify_admin_csrf_token($_POST['csrf_token'])) {
    set_admin_flash_message('error', 'Geçersiz CSRF token. Lütfen tekrar deneyin.');
    header("Location: manage_denemeler.php");
    exit;
}

$deneme_id_to_recalculate = filter_input(INPUT_POST, 'deneme_id', FILTER_VALIDATE_INT);

if (!$deneme_id_to_recalculate) {
    set_admin_flash_message('error', 'Geçersiz deneme ID\'si.');
    header("Location: manage_denemeler.php");
    exit;
}

// config.php'den NET_KATSAYISI ve PUAN_CARPANI alınır, yoksa varsayılanlar kullanılır.
defined('NET_KATSAYISI') or define('NET_KATSAYISI', 4);
defined('PUAN_CARPANI') or define('PUAN_CARPANI', 2);

try {
    // 1. İlgili denemenin varlığını ve cevap anahtarını kontrol et
    $stmt_deneme_info = $pdo->prepare("SELECT id, deneme_adi, soru_sayisi FROM denemeler WHERE id = ?");
    $stmt_deneme_info->execute([$deneme_id_to_recalculate]);
    $deneme_info = $stmt_deneme_info->fetch();

    if (!$deneme_info) {
        set_admin_flash_message('error', "Deneme ID $deneme_id_to_recalculate bulunamadı.");
        header("Location: manage_denemeler.php");
        exit;
    }
    $soru_sayisi = $deneme_info['soru_sayisi'];

    $stmt_answer_key = $pdo->prepare("SELECT soru_no, dogru_cevap FROM cevap_anahtarlari WHERE deneme_id = :deneme_id");
    $stmt_answer_key->execute([':deneme_id' => $deneme_id_to_recalculate]);
    $answer_key_raw = $stmt_answer_key->fetchAll(PDO::FETCH_KEY_PAIR); // soru_no => dogru_cevap

    if (empty($answer_key_raw) || count($answer_key_raw) < $soru_sayisi) {
        set_admin_flash_message('error', "Deneme ID $deneme_id_to_recalculate için tam cevap anahtarı bulunamadı. Hesaplama yapılamadı.");
        header("Location: manage_denemeler.php");
        exit;
    }
    
    // 2. İlgili denemeyi tamamlamış tüm katılımları çek
    $stmt_participations = $pdo->prepare("
        SELECT id AS katilim_id, kullanici_id 
        FROM kullanici_katilimlari 
        WHERE deneme_id = :deneme_id AND sinav_tamamlama_tarihi IS NOT NULL
    ");
    $stmt_participations->execute([':deneme_id' => $deneme_id_to_recalculate]);
    $participations = $stmt_participations->fetchAll(PDO::FETCH_ASSOC);

    if (empty($participations)) {
        set_admin_flash_message('info', "Deneme ID $deneme_id_to_recalculate için yeniden hesaplanacak tamamlanmış katılım bulunamadı.");
        header("Location: manage_denemeler.php");
        exit;
    }

    $pdo->beginTransaction();
    $updated_participations_count = 0;

    foreach ($participations as $participation) {
        $katilim_id = $participation['katilim_id'];

        // a. Kullanıcının tüm cevaplarını çek
        $stmt_user_answers = $pdo->prepare("
            SELECT soru_no, verilen_cevap 
            FROM kullanici_cevaplari 
            WHERE katilim_id = :katilim_id
        ");
        $stmt_user_answers->execute([':katilim_id' => $katilim_id]);
        $user_answers_list = $stmt_user_answers->fetchAll(PDO::FETCH_ASSOC);
        
        $user_answers_map = []; // soru_no => verilen_cevap
        foreach ($user_answers_list as $ans) {
            $user_answers_map[$ans['soru_no']] = $ans['verilen_cevap'];
        }

        // b. Yeni Doğru, Yanlış, Boş sayılarını hesapla
        $new_dogru = 0;
        $new_yanlis = 0;
        $new_bos = 0;
        
        $stmt_update_user_answer = $pdo->prepare("UPDATE kullanici_cevaplari SET dogru_mu = :dogru_mu WHERE katilim_id = :katilim_id AND soru_no = :soru_no");

        for ($current_soru_no = 1; $current_soru_no <= $soru_sayisi; $current_soru_no++) {
            $verilen_cevap = $user_answers_map[$current_soru_no] ?? null;
            $dogru_cevap_db = $answer_key_raw[$current_soru_no] ?? '---NOKEY---'; // Cevap anahtarında yoksa
            $is_correct_flag = null;

            if ($verilen_cevap === null || $verilen_cevap === '') {
                $new_bos++;
                $is_correct_flag = null;
            } elseif ($verilen_cevap === $dogru_cevap_db) {
                $new_dogru++;
                $is_correct_flag = 1;
            } else {
                $new_yanlis++;
                $is_correct_flag = 0;
            }
            // kullanici_cevaplari.dogru_mu güncelle
            $stmt_update_user_answer->execute([
                ':dogru_mu' => $is_correct_flag,
                ':katilim_id' => $katilim_id,
                ':soru_no' => $current_soru_no
            ]);
        }
        
        $new_net_sayisi = $new_dogru - ($new_yanlis / NET_KATSAYISI);
        $new_puan = $new_net_sayisi * PUAN_CARPANI;

        // c. kullanici_katilimlari tablosunu güncelle
        $stmt_update_katilim = $pdo->prepare("
            UPDATE kullanici_katilimlari 
            SET dogru_sayisi = :dogru, yanlis_sayisi = :yanlis, bos_sayisi = :bos, 
                net_sayisi = :net, puan = :puan, puan_can_egrisi = NULL /* Çan puanını sıfırla, yeniden hesaplanacak */
            WHERE id = :katilim_id
        ");
        $stmt_update_katilim->execute([
            ':dogru' => $new_dogru,
            ':yanlis' => $new_yanlis,
            ':bos' => $new_bos,
            ':net' => $new_net_sayisi,
            ':puan' => $new_puan,
            ':katilim_id' => $katilim_id
        ]);
        $updated_participations_count++;
    }

    // d. Tüm deneme için çan eğrisini yeniden hesapla
    if (recalculateAndApplyBellCurve($deneme_id_to_recalculate, $pdo)) {
        set_admin_flash_message('success', "$updated_participations_count katılımcının sonuçları ve çan eğrisi başarıyla yeniden hesaplandı.");
    } else {
        set_admin_flash_message('warning', "$updated_participations_count katılımcının ham puanları yeniden hesaplandı, ancak çan eğrisi güncellenirken bir sorun oluştu.");
    }

    $pdo->commit();

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_admin_flash_message('error', "Sonuçlar yeniden hesaplanırken veritabanı hatası: " . $e->getMessage());
} catch (Exception $e) {
     if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_admin_flash_message('error', "Sonuçlar yeniden hesaplanırken genel bir hata oluştu: " . $e->getMessage());
}

header("Location: manage_denemeler.php");
exit;
?>
