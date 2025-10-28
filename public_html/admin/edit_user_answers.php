<?php
// admin/edit_user_answers.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAdminLogin();

$katilim_id = filter_input(INPUT_GET, 'katilim_id', FILTER_VALIDATE_INT);
if (!$katilim_id) {
    set_admin_flash_message('error', 'Geçersiz katılım ID.');
    header("Location: view_kullanicilar.php"); // Veya bir önceki sayfaya
    exit;
}

$csrf_token = generate_admin_csrf_token();
$options = ['A', 'B', 'C', 'D', 'E', '']; // Boş seçeneği de ekle

try {
    // Katılım ve deneme bilgilerini çek
    $stmt_info = $pdo->prepare("
        SELECT kk.id AS katilim_id, kk.kullanici_id, kk.deneme_id, 
               u.ad_soyad, d.deneme_adi, d.soru_sayisi
        FROM kullanici_katilimlari kk
        JOIN kullanicilar u ON kk.kullanici_id = u.id
        JOIN denemeler d ON kk.deneme_id = d.id
        WHERE kk.id = ?
    ");
    $stmt_info->execute([$katilim_id]);
    $participation_info = $stmt_info->fetch();

    if (!$participation_info) {
        set_admin_flash_message('error', 'Katılım bulunamadı.');
        header("Location: view_kullanicilar.php");
        exit;
    }
    $deneme_id = $participation_info['deneme_id'];
    $soru_sayisi_deneme = $participation_info['soru_sayisi'];

    // Cevap anahtarını çek
    $stmt_answer_key = $pdo->prepare("SELECT soru_no, dogru_cevap FROM cevap_anahtarlari WHERE deneme_id = ?");
    $stmt_answer_key->execute([$deneme_id]);
    $answer_key_map = $stmt_answer_key->fetchAll(PDO::FETCH_KEY_PAIR); // soru_no => dogru_cevap

    // Kullanıcının mevcut cevaplarını çek
    $stmt_user_answers = $pdo->prepare("SELECT soru_no, verilen_cevap, dogru_mu FROM kullanici_cevaplari WHERE katilim_id = ?");
    $stmt_user_answers->execute([$katilim_id]);
    $user_answers_raw = $stmt_user_answers->fetchAll(PDO::FETCH_ASSOC);
    $user_answers_map = [];
    foreach ($user_answers_raw as $ans) {
        $user_answers_map[$ans['soru_no']] = $ans;
    }

} catch (PDOException $e) {
    set_admin_flash_message('error', "Veri yüklenirken hata: " . $e->getMessage());
    // Hata durumunda bir önceki sayfaya yönlendirme daha iyi olabilir
    $participation_info = null; // Hata durumunda formu gösterme
}


// Form gönderildiğinde
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $participation_info) {
    if (!verify_admin_csrf_token($_POST['csrf_token'])) {
        set_admin_flash_message('error', 'Geçersiz CSRF token.');
    } else {
        $gelen_cevaplar = $_POST['cevaplar'] ?? [];
        $degisiklik_yapildi = false;

        try {
            $pdo->beginTransaction();

            $stmt_update_user_answer = $pdo->prepare(
                "UPDATE kullanici_cevaplari SET verilen_cevap = :verilen_cevap, dogru_mu = :dogru_mu 
                 WHERE katilim_id = :katilim_id AND soru_no = :soru_no"
            );
            $stmt_insert_user_answer = $pdo->prepare( // Eğer cevap daha önce hiç kaydedilmemişse (olmamalı ama önlem)
                "INSERT INTO kullanici_cevaplari (katilim_id, soru_no, verilen_cevap, dogru_mu) 
                 VALUES (:katilim_id, :soru_no, :verilen_cevap, :dogru_mu)"
            );


            for ($i = 1; $i <= $soru_sayisi_deneme; $i++) {
                $yeni_cevap = isset($gelen_cevaplar[$i]) ? trim($gelen_cevaplar[$i]) : null;
                if ($yeni_cevap === '') $yeni_cevap = null; // Boş string yerine NULL kullan

                $mevcut_verilen_cevap = $user_answers_map[$i]['verilen_cevap'] ?? null;

                if ($yeni_cevap !== $mevcut_verilen_cevap) { // Sadece cevap değişmişse işlem yap
                    $degisiklik_yapildi = true;
                    $dogru_cevap_bu_soru = $answer_key_map[$i] ?? null;
                    $yeni_dogru_mu = null;

                    if ($yeni_cevap !== null && $dogru_cevap_bu_soru !== null) {
                        $yeni_dogru_mu = ($yeni_cevap === $dogru_cevap_bu_soru) ? 1 : 0;
                    }

                    // Kayıt var mı kontrol et, yoksa insert, varsa update
                    if(isset($user_answers_map[$i])) {
                        $stmt_update_user_answer->execute([
                            ':verilen_cevap' => $yeni_cevap,
                            ':dogru_mu' => $yeni_dogru_mu,
                            ':katilim_id' => $katilim_id,
                            ':soru_no' => $i
                        ]);
                    } else {
                         $stmt_insert_user_answer->execute([
                            ':katilim_id' => $katilim_id,
                            ':soru_no' => $i,
                            ':verilen_cevap' => $yeni_cevap,
                            ':dogru_mu' => $yeni_dogru_mu
                        ]);
                    }
                }
            }

            if ($degisiklik_yapildi) {
                // D/Y/B, Net, Puan'ı yeniden hesapla
                $stmt_recalculate_check = $pdo->prepare("
                    SELECT soru_no, dogru_mu FROM kullanici_cevaplari WHERE katilim_id = ?
                ");
                $stmt_recalculate_check->execute([$katilim_id]);
                $guncel_cevaplar_list = $stmt_recalculate_check->fetchAll(PDO::FETCH_ASSOC);

                $yeni_d = 0; $yeni_y = 0; $yeni_b = 0;
                $cevaplanan_soru_sayisi = count($guncel_cevaplar_list);

                foreach($guncel_cevaplar_list as $guncel_cvp){
                    if($guncel_cvp['dogru_mu'] === 1) $yeni_d++;
                    elseif($guncel_cvp['dogru_mu'] === 0) $yeni_y++;
                    else $yeni_b++; // dogru_mu NULL ise boş
                }
                // Eğer tüm sorular için kayıt yoksa, kalanları boş say
                if ($cevaplanan_soru_sayisi < $soru_sayisi_deneme) {
                    $yeni_b += ($soru_sayisi_deneme - $cevaplanan_soru_sayisi);
                }


                $yeni_net = $yeni_d - ($yeni_y / NET_KATSAYISI);
                $yeni_puan = $yeni_net * PUAN_CARPANI;

                $stmt_update_summary = $pdo->prepare(
                    "UPDATE kullanici_katilimlari SET 
                     dogru_sayisi = ?, yanlis_sayisi = ?, bos_sayisi = ?, 
                     net_sayisi = ?, puan = ?, puan_can_egrisi = NULL 
                     WHERE id = ?"
                );
                $stmt_update_summary->execute([$yeni_d, $yeni_y, $yeni_b, $yeni_net, $yeni_puan, $katilim_id]);

                // Çan eğrisini yeniden hesapla
                recalculateAndApplyBellCurve($deneme_id, $pdo);
                
                // Loglama
                $admin_user = $_SESSION['admin_username'] ?? 'Bilinmeyen Admin';
                $log_eylem = "Kullanıcı ID $participation_info[kullanici_id] için Katılım ID $katilim_id cevapları düzenlendi.";
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'Bilinmiyor';
                $stmt_log = $pdo->prepare("INSERT INTO admin_loglari (admin_kullanici_adi, eylem, ip_adresi) VALUES (?, ?, ?)");
                $stmt_log->execute([$admin_user, $log_eylem, $ip]);

                set_admin_flash_message('success', 'Kullanıcının cevapları ve puanı başarıyla güncellendi. Çan eğrisi de yeniden hesaplandı.');
            } else {
                set_admin_flash_message('info', 'Herhangi bir değişiklik yapılmadı.');
            }
            $pdo->commit();

        } catch (PDOException $e) {
            $pdo->rollBack();
            set_admin_flash_message('error', 'Güncelleme sırasında veritabanı hatası: ' . $e->getMessage());
        }
        header("Location: edit_user_answers.php?katilim_id=" . $katilim_id);
        exit;
    }
}


$page_title = "Cevap Düzenle: " . escape_html($participation_info['ad_soyad'] ?? '') . " - " . escape_html($participation_info['deneme_adi'] ?? '');
include_once __DIR__ . '/../templates/admin_header.php'; // Üstbilgiyi burada çağırıyoruz, çünkü $page_title yukarıda set ediliyor.
?>

<?php if ($participation_info): ?>
    <div class="admin-page-title"><?php echo $page_title; ?></div>
    <p>
        <a href="view_user_details.php?user_id=<?php echo $participation_info['kullanici_id']; ?>" class="btn-admin yellow btn-sm">&laquo; Kullanıcı Detaylarına Geri Dön</a>
    </p>
    <p>Aşağıdaki tablodan kullanıcının cevaplarını değiştirebilirsiniz. Değişiklik sonrası "Cevapları Güncelle" butonuna basınız. Bu işlem kullanıcının D/Y/B, Net, Puan ve denemenin genel çan eğrisi puanlarını yeniden hesaplayacaktır.</p>

    <form action="edit_user_answers.php?katilim_id=<?php echo $katilim_id; ?>" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Soru No</th>
                        <th>Kullanıcının Cevabı</th>
                        <th>Doğru Cevap</th>
                        <th>Mevcut Durum</th>
                        <th>Yeni Cevap</th>
                    </tr>
                </thead>
                <tbody>
                    <?php for ($i = 1; $i <= $soru_sayisi_deneme; $i++): ?>
                        <?php 
                            $mevcut_cevap_detay = $user_answers_map[$i] ?? ['verilen_cevap' => null, 'dogru_mu' => null];
                            $mevcut_verilen = $mevcut_cevap_detay['verilen_cevap'];
                            $mevcut_dogru_mu = $mevcut_cevap_detay['dogru_mu'];
                            $dogru_secenek = $answer_key_map[$i] ?? 'N/A';
                            $durum_metni = '';
                            $durum_class = '';
                            if ($mevcut_dogru_mu === 1) {
                                $durum_metni = 'Doğru'; $durum_class = 'text-success';
                            } elseif ($mevcut_dogru_mu === 0) {
                                $durum_metni = 'Yanlış'; $durum_class = 'text-danger';
                            } else {
                                $durum_metni = 'Boş'; $durum_class = 'text-muted';
                            }
                        ?>
                        <tr>
                            <td><?php echo $i; ?></td>
                            <td><?php echo escape_html($mevcut_verilen ?: 'Boş'); ?></td>
                            <td><?php echo escape_html($dogru_secenek); ?></td>
                            <td class="<?php echo $durum_class; ?> fw-bold"><?php echo $durum_metni; ?></td>
                            <td>
                                <select name="cevaplar[<?php echo $i; ?>]" class="input-admin form-select form-select-sm" style="min-width: 80px;">
                                    <?php foreach ($options as $opt): ?>
                                        <option value="<?php echo $opt; ?>" <?php echo ($mevcut_verilen === $opt && $opt !== '') ? 'selected' : ''; ?>>
                                            <?php echo $opt ?: 'Boş Bırak'; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
        <button type="submit" class="btn-admin green mt-3">
             <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-check-circle-fill me-1" viewBox="0 0 16 16">
                <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/>
            </svg>
            Cevapları Güncelle ve Puanları Yeniden Hesapla
        </button>
    </form>
<?php else: ?>
    <p class="message-box error">Seçilen katılım bilgileri yüklenemedi.</p>
<?php endif; ?>

<?php
include_once __DIR__ . '/../templates/admin_footer.php';
?>
