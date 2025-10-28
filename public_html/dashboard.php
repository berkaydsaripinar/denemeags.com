<?php
// dashboard.php (Modern Dashboard Teması ile)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin(); 

$user_id = $_SESSION['user_id']; 

try {
    $stmt_check_unseen_animation = $pdo->prepare("
        SELECT d.id AS deneme_id_to_show
        FROM denemeler d
        LEFT JOIN kullanici_deneme_gorunumleri kdg ON d.id = kdg.deneme_id AND kdg.kullanici_id = :user_id
        WHERE d.aktif_mi = 1 AND d.sonuc_aciklama_tarihi <= NOW()
        AND (kdg.animasyon_goruldu_mu IS NULL OR kdg.animasyon_goruldu_mu = 0)
        ORDER BY d.sonuc_aciklama_tarihi DESC, d.id DESC 
        LIMIT 1
    ");
    $stmt_check_unseen_animation->execute([':user_id' => $user_id]);
    $deneme_for_animation = $stmt_check_unseen_animation->fetch();

    if ($deneme_for_animation) {
        if (!isset($_SESSION['redirected_to_tebrik_for_deneme_' . $deneme_for_animation['deneme_id_to_show']])) {
             $_SESSION['redirected_to_tebrik_for_deneme_' . $deneme_for_animation['deneme_id_to_show']] = true; 
             redirect('tebrikler.php?deneme_id=' . $deneme_for_animation['deneme_id_to_show']);
        }
    } else {
        foreach ($_SESSION as $key => $value) {
            if (strpos($key, 'redirected_to_tebrik_for_deneme_') === 0) {
                unset($_SESSION[$key]);
            }
        }
    }
} catch (PDOException $e) { /* Hata loglama */ }


$page_title = "Kullanıcı Paneli";
$user_ad_soyad = $_SESSION['user_ad_soyad']; 
$csrf_token = generate_csrf_token(); 

include_once __DIR__ . '/templates/header.php'; 

$now = new DateTime('now', new DateTimeZone('Europe/Istanbul'));

$featured_deneme_info = null;
$top_3_kullanicilar_featured = [];
$featured_deneme_siralama_aciklandi_mi = false;
try {
    $stmt_featured_deneme = $pdo->query("
        SELECT id, deneme_adi, sonuc_aciklama_tarihi AS siralama_aciklama_tarihi
        FROM denemeler WHERE aktif_mi = 1 ORDER BY id DESC LIMIT 1
    ");
    $featured_deneme_info = $stmt_featured_deneme->fetch();
    if ($featured_deneme_info) {
        $siralama_aciklama_dt_featured = new DateTime($featured_deneme_info['siralama_aciklama_tarihi'], new DateTimeZone('Europe/Istanbul'));
        $featured_deneme_siralama_aciklandi_mi = ($now >= $siralama_aciklama_dt_featured);
        if ($featured_deneme_siralama_aciklandi_mi) {
            $stmt_top_3 = $pdo->prepare("
                SELECT k.ad_soyad, kk.net_sayisi FROM kullanici_katilimlari kk
                JOIN kullanicilar k ON kk.kullanici_id = k.id
                WHERE kk.deneme_id = :deneme_id AND kk.sinav_tamamlama_tarihi IS NOT NULL
                ORDER BY COALESCE(kk.puan_can_egrisi, kk.puan) DESC, kk.net_sayisi DESC, kk.dogru_sayisi DESC LIMIT 3
            ");
            $stmt_top_3->execute([':deneme_id' => $featured_deneme_info['id']]);
            $top_3_kullanicilar_featured = $stmt_top_3->fetchAll();
        }
    }
} catch (PDOException $e) { /* Hata loglama */ }

$aktif_duyurular = [];
try {
    $stmt_duyurular = $pdo->query("SELECT baslik, icerik, olusturulma_tarihi FROM duyurular WHERE aktif_mi = 1 ORDER BY olusturulma_tarihi DESC LIMIT 3");
    $aktif_duyurular = $stmt_duyurular->fetchAll();
} catch (PDOException $e) { /* Hata loglama */ }

$aktif_denemeler = [];
$kullanici_katilimlari = [];
try {
    $stmt_denemeler = $pdo->prepare("
        SELECT d.id AS deneme_id, d.deneme_adi, d.soru_sayisi, d.sonuc_aciklama_tarihi AS siralama_aciklama_tarihi 
        FROM denemeler d WHERE d.aktif_mi = 1 ORDER BY d.id ASC
    ");
    $stmt_denemeler->execute();
    $aktif_denemeler = $stmt_denemeler->fetchAll();

    $stmt_katilimlar = $pdo->prepare("
        SELECT kk.deneme_id, kk.id AS katilim_id, kk.sinav_tamamlama_tarihi
        FROM kullanici_katilimlari kk WHERE kk.kullanici_id = :user_id
    ");
    $stmt_katilimlar->execute([':user_id' => $user_id]);
    $kullanici_katilimlari_raw = $stmt_katilimlar->fetchAll();
    foreach ($kullanici_katilimlari_raw as $katilim) {
        $kullanici_katilimlari[$katilim['deneme_id']] = [
            'katilim_id' => $katilim['katilim_id'],
            'tamamlandi_mi' => !empty($katilim['sinav_tamamlama_tarihi'])
        ];
    }
} catch (PDOException $e) { set_flash_message('error', "Denemeler yüklenirken bir sorun oluştu."); }
?>

<div class="mb-4 pt-3"> 
    <h2 class="display-5 text-center text-theme-primary fw-bold"><?php echo escape_html($page_title); ?></h2>
    <p class="lead text-center text-theme-secondary">Hoş geldiniz, <strong class="text-theme-dark"><?php echo escape_html($user_ad_soyad); ?></strong>!</p>
</div>

<?php if ($featured_deneme_info): ?>
<div class="row justify-content-center mb-4">
    <div class="col-lg-10 col-xl-8">
        <div class="card shadow-sm card-theme border-theme-primary">
            <div class="card-header"> 
                <h5 class="mb-0">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-trophy-fill me-2" viewBox="0 0 16 16">
                        <path d="M2.5.5A.5.5 0 0 1 3 .5V1h10V.5a.5.5 0 0 1 .5-.5h0a.5.5 0 0 1 .5.5V1H15a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-.5.5h-.5v2.5a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 1 7.5V5H.5A.5.5 0 0 1 0 4.5v-2A.5.5 0 0 1 .5 2H2V.5a.5.5 0 0 1 .5-.5zM3 13.5A1.5 1.5 0 0 0 4.5 15h7a1.5 1.5 0 0 0 1.5-1.5V8H3z"/>
                    </svg>
                    Öne Çıkan Deneme: <?php echo escape_html($featured_deneme_info['deneme_adi']); ?>
                </h5>
            </div>
            <div class="card-body"> 
                <p class="card-text text-theme-dark"> 
                    <strong>Genel Sıralama Açıklanma Tarihi:</strong> 
                    <span class="text-theme-primary"><?php echo format_tr_datetime($featured_deneme_info['siralama_aciklama_tarihi']); ?></span>
                </p>
                <?php if ($featured_deneme_siralama_aciklandi_mi): ?>
                    <?php if (!empty($top_3_kullanicilar_featured)): ?>
                        <h6 class="text-theme-primary mt-3">İlk 3 Sıralaması:</h6>
                        <ol class="list-group list-group-numbered">
                            <?php foreach ($top_3_kullanicilar_featured as $sira => $kisi): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-start bg-light border-light"> 
                                    <div class="ms-2 me-auto">
                                        <div class="fw-bold text-theme-dark"><?php echo escape_html($kisi['ad_soyad']); ?></div>
                                    </div>
                                    <span class="badge bg-info text-dark rounded-pill fs-6"> 
                                        <?php echo number_format($kisi['net_sayisi'], 2); ?> Net
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php else: ?>
                        <p class="text-theme-secondary mt-3">Bu denemeye henüz yeterli katılım olmamış veya ilk 3 sıralaması hesaplanamamıştır.</p>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-theme-info mt-3 small py-2 px-3">İlk 3'e girenler yukarıda belirtilen tarihte açıklanacaktır.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($aktif_duyurular)): ?>
<div class="row justify-content-center mb-4">
    <div class="col-lg-10 col-xl-8">
        <div class="card shadow-sm announcements-section-theme">
            <div class="card-header">
                <h5 class="mb-0"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-megaphone-fill me-2" viewBox="0 0 16 16">
                  
                    </svg>Güncel Duyurular
                </h5>
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($aktif_duyurular as $duyuru): ?>
                    <div class="list-group-item">
                        <div class="d-flex w-100 justify-content-between align-items-center">
                            <h6 class="mb-1 text-theme-primary"><?php echo escape_html($duyuru['baslik']); ?></h6>
                            <small class="text-theme-secondary"><?php echo format_tr_datetime($duyuru['olusturulma_tarihi'], 'd F Y'); ?></small>
                        </div>
                        <p class="mb-1 text-theme-dark" style="font-size: 0.9rem;"><?php echo nl2br(escape_html($duyuru['icerik'])); ?></p>
                    </div>
                <?php endforeach; ?>
                 <?php if(count($aktif_duyurular) == 0): ?> 
                    <div class="list-group-item text-theme-secondary">Şu anda aktif bir duyuru bulunmamaktadır.</div> 
                 <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>


<div class="row">
    <?php if (empty($aktif_denemeler)): ?>
        <div class="col-12">
            <div class="alert alert-theme-info text-center" role="alert">
                Şu anda aktif bir deneme sınavı bulunmamaktadır.
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($aktif_denemeler as $deneme): ?>
            <?php 
            $deneme_id_current = $deneme['deneme_id'];
            $siralama_aciklama_dt_bireysel = new DateTime($deneme['siralama_aciklama_tarihi'], new DateTimeZone('Europe/Istanbul'));
            $siralama_aciklandi_mi_bireysel = $now >= $siralama_aciklama_dt_bireysel; 
            $kullanici_bu_denemeye_katildi_mi = isset($kullanici_katilimlari[$deneme_id_current]);
            $katilim_bilgisi = $kullanici_bu_denemeye_katildi_mi ? $kullanici_katilimlari[$deneme_id_current] : null;
            ?>
            <div class="col-lg-6 mb-4">
                <div class="card h-100 shadow-sm exam-info-bs"> 
                    <div class="card-body d-flex flex-column">
                        <h4 class="card-title text-theme-primary"><?php echo escape_html($deneme['deneme_adi']); ?></h4>
                        <p class="card-text text-theme-secondary small mb-3"> 
                            Soru Sayısı: <?php echo escape_html($deneme['soru_sayisi']); ?><br>
                            Sıralama Açıklanma Tarihi: <?php echo format_tr_datetime($deneme['siralama_aciklama_tarihi']); ?>
                        </p>
                        
                        <div class="mt-auto"> 
                            <?php if ($kullanici_bu_denemeye_katildi_mi): ?>
                                <?php if ($katilim_bilgisi['tamamlandi_mi']): ?>
                                    <div class="alert alert-theme-success small py-2 px-3 mb-2" role="alert">Bu denemeyi tamamladınız.</div>
                                    <a href="results.php?katilim_id=<?php echo $katilim_bilgisi['katilim_id']; ?>" class="btn btn-theme-primary w-100">Puanınızı ve Çözümleri Gör</a>
                                    <?php if (!$siralama_aciklandi_mi_bireysel): ?> 
                                        <p class="text-theme-secondary small mt-2 mb-0">Bireysel sıralamanız, belirtilen sıralama açıklanma tarihinde gösterilecektir.</p> 
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="alert alert-theme-warning small py-2 px-3 mb-2" role="alert">Bu denemeye başladınız, henüz tamamlamadınız.</div>
                                    <a href="exam.php?katilim_id=<?php echo $katilim_bilgisi['katilim_id']; ?>" class="btn btn-theme-primary w-100">Sınava Devam Et</a>
                                <?php endif; ?>
                            <?php else: ?>
                                <form action="activate_exam.php" method="POST" class="mt-2">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="deneme_id" value="<?php echo $deneme_id_current; ?>">
                                    <div class="mb-2">
                                        <label for="erisim_kodu_<?php echo $deneme_id_current; ?>" class="form-label small text-theme-secondary">Erişim Kodu:</label>
                                        <input type="text" name="erisim_kodu" id="erisim_kodu_<?php echo $deneme_id_current; ?>" class="form-control form-control-sm input-theme text-uppercase" placeholder="DENEME KODU" required maxlength="20">
                                    </div>
                                    <button type="submit" class="btn btn-theme-primary w-100">Kodu Gönder ve Sınava Başla</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="text-center mt-4 py-3"> 
    <a href="logout.php" class="btn btn-danger btn-lg"> 
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-box-arrow-right me-2" viewBox="0 0 16 16">
            
        </svg>
        Çıkış Yap 
    </a>
</div>

<?php
include_once __DIR__ . '/templates/footer.php'; 
?>
