<?php
// results.php (Modern Dashboard Teması ile - Çözüm ve Karne Butonları)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$katilim_id = filter_input(INPUT_GET, 'katilim_id', FILTER_VALIDATE_INT);
if (!$katilim_id) {
    set_flash_message('error', 'Geçersiz katılım ID.');
    redirect('dashboard.php');
}

$user_id = $_SESSION['user_id'];
$siralama_gosterim_metni = null; 

try {
    $stmt_katilim_sonuc = $pdo->prepare("
        SELECT 
            kk.id AS katilim_id, kk.deneme_id, kk.dogru_sayisi, kk.yanlis_sayisi, kk.bos_sayisi, 
            kk.net_sayisi, kk.puan, kk.puan_can_egrisi, kk.sinav_tamamlama_tarihi,
            d.deneme_adi, d.sonuc_aciklama_tarihi AS siralama_aciklama_tarihi, d.cozum_linki, d.cozum_video_dosyasi
        FROM kullanici_katilimlari kk
        JOIN denemeler d ON kk.deneme_id = d.id
        WHERE kk.id = :katilim_id AND kk.kullanici_id = :user_id
    ");
    $stmt_katilim_sonuc->execute([':katilim_id' => $katilim_id, ':user_id' => $user_id]);
    $sonuc_detaylari = $stmt_katilim_sonuc->fetch();

    if (!$sonuc_detaylari) { 
        set_flash_message('error', 'Sonuç detayları bulunamadı.');
        redirect('dashboard.php'); 
    }
    if (empty($sonuc_detaylari['sinav_tamamlama_tarihi'])) { 
        set_flash_message('info', 'Bu denemeyi henüz tamamlamadınız.');
        redirect('exam.php?katilim_id=' . $katilim_id); 
    }
    
    $deneme_id = $sonuc_detaylari['deneme_id']; 
    $now = new DateTime('now', new DateTimeZone('Europe/Istanbul'));
    $siralama_aciklama_dt = new DateTime($sonuc_detaylari['siralama_aciklama_tarihi'], new DateTimeZone('Europe/Istanbul'));
    $siralama_aciklandi_mi = $now >= $siralama_aciklama_dt;

    if ($siralama_aciklandi_mi) {
        $stmt_siralama_new = $pdo->prepare("
            SELECT COUNT(*) + 1 AS rank FROM kullanici_katilimlari
            WHERE deneme_id = :deneme_id AND sinav_tamamlama_tarihi IS NOT NULL
            AND COALESCE(puan_can_egrisi, puan, -9999) > COALESCE(:user_puan_can, :user_puan_raw, -9999)
        ");
        $stmt_siralama_new->execute([
            ':deneme_id' => $deneme_id,
            ':user_puan_can' => $sonuc_detaylari['puan_can_egrisi'],
            ':user_puan_raw' => $sonuc_detaylari['puan']
        ]);
        $siralama_data = $stmt_siralama_new->fetch();
        $kullanici_siralamasi = $siralama_data['rank'] ?? null;

        $stmt_toplam_katilimci = $pdo->prepare("
            SELECT COUNT(*) FROM kullanici_katilimlari 
            WHERE deneme_id = :deneme_id AND sinav_tamamlama_tarihi IS NOT NULL
        ");
        $stmt_toplam_katilimci->execute([':deneme_id' => $deneme_id]);
        $toplam_katilimci = $stmt_toplam_katilimci->fetchColumn();
        if ($kullanici_siralamasi !== null && $toplam_katilimci > 0) {
            $siralama_gosterim_metni = $kullanici_siralamasi . " / " . $toplam_katilimci;
        } else { $siralama_gosterim_metni = "Belirlenemedi"; }
    }

    $stmt_konu_analizi = $pdo->prepare("
        SELECT ca.konu_adi, COUNT(kc.id) as toplam_soru,
               SUM(CASE WHEN kc.dogru_mu = 1 THEN 1 ELSE 0 END) as dogru,
               SUM(CASE WHEN kc.dogru_mu = 0 THEN 1 ELSE 0 END) as yanlis,
               SUM(CASE WHEN (kc.verilen_cevap IS NULL OR kc.verilen_cevap = '') THEN 1 ELSE 0 END) as bos
        FROM kullanici_cevaplari kc
        JOIN cevap_anahtarlari ca ON kc.soru_no = ca.soru_no AND ca.deneme_id = :deneme_id_for_konu 
        WHERE kc.katilim_id = :katilim_id AND ca.konu_adi IS NOT NULL AND ca.konu_adi != ''
        GROUP BY ca.konu_adi ORDER BY ca.konu_adi
    ");
    $stmt_konu_analizi->execute([':katilim_id' => $katilim_id, ':deneme_id_for_konu' => $deneme_id]);
    $konu_analizi = $stmt_konu_analizi->fetchAll();
} catch (PDOException $e) { 
    error_log("Sonuç sayfası PDO hatası: " . $e->getMessage());
    set_flash_message('error', 'Sonuçlar yüklenirken bir veritabanı sorunu oluştu.');
    redirect('dashboard.php');
}

$page_title = ($sonuc_detaylari['deneme_adi'] ?? 'Deneme') . " Sonuçları";
include_once __DIR__ . '/templates/header.php'; 

$deneme_adi_js_cozum = htmlspecialchars(addslashes($sonuc_detaylari['deneme_adi'] ?? ''), ENT_QUOTES, 'UTF-8');
$konu_labels_js = []; $konu_dogru_js = []; $konu_yanlis_js = []; $konu_bos_js = [];
if (!empty($konu_analizi)) {
    foreach ($konu_analizi as $konu) {
        $konu_labels_js[] = $konu['konu_adi'];
        $konu_dogru_js[] = (int)$konu['dogru'];
        $konu_yanlis_js[] = (int)$konu['yanlis'];
        $konu_bos_js[] = (int)$konu['bos'];
    }
}
?>

<div class="text-center mb-4 pt-2">
    <h2 class="display-6 text-theme-primary fw-bold"><?php echo escape_html($sonuc_detaylari['deneme_adi']); ?> Sınav Sonucunuz</h2>
    <p class="text-theme-secondary small">Sınav Tamamlama Tarihi: <?php echo format_tr_datetime($sonuc_detaylari['sinav_tamamlama_tarihi']); ?></p>
</div>

<div class="row justify-content-center">
    <div class="col-lg-6 mb-4">
        <div class="card h-100 shadow-sm card-theme">
            <div class="card-header card-header-theme-light"> 
                <h5 class="mb-0 text-theme-primary">Bireysel Sonuçlarınız</h5>
            </div>
            <div class="card-body">
                <table class="table table-sm table-striped results-table-bs">
                    <tbody>
                        <tr><th scope="row">Doğru Sayısı</th><td><?php echo $sonuc_detaylari['dogru_sayisi']; ?></td></tr>
                        <tr><th scope="row">Yanlış Sayısı</th><td><?php echo $sonuc_detaylari['yanlis_sayisi']; ?></td></tr>
                        <tr><th scope="row">Boş Sayısı</th><td><?php echo $sonuc_detaylari['bos_sayisi']; ?></td></tr>
                        <tr><th scope="row">Net Sayınız <small>(<?php echo NET_KATSAYISI; ?>Y/1D)</small></th><td><strong class="text-theme-primary"><?php echo number_format($sonuc_detaylari['net_sayisi'], 2); ?></strong></td></tr>
                        <tr><th scope="row">Ham Puanınız</th><td><?php echo number_format($sonuc_detaylari['puan'], 3); ?></td></tr>
                        <?php if (isset($sonuc_detaylari['puan_can_egrisi'])): ?>
                        <tr><th scope="row">Çan Eğrisi Puanı <small>(En Yüksek 100)</small></th><td><strong class="text-theme-primary"><?php echo number_format($sonuc_detaylari['puan_can_egrisi'], 3); ?></strong></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <div class="mt-3 d-grid gap-2">
                    <?php 
                    // Çözüm Görüntüleme Butonu
                    if (!empty($sonuc_detaylari['cozum_linki'])): 
                        $view_solution_url = BASE_URL . '/view_solution.php?katilim_id=' . $katilim_id;
                    ?>
                        <button onclick="confirmAndViewSolution('<?php echo $view_solution_url; ?>', '<?php echo $deneme_adi_js_cozum; ?>')" class="btn btn-theme-primary">
                            Sınav Çözümlerini Görüntüle
                        </button>
                    <?php else: ?>
                        <div class="alert alert-theme-info small py-2 px-3 mb-0 text-center">Çözüm dosyası henüz yüklenmemiş.</div>
                    <?php endif; ?>

                    <?php if (!empty($sonuc_detaylari['cozum_video_dosyasi'])): ?>
                        <?php $view_video_url = BASE_URL . '/view_video_solution.php?katilim_id=' . $katilim_id; ?>
                        <button onclick="confirmAndViewVideo('<?php echo $view_video_url; ?>', '<?php echo $deneme_adi_js_cozum; ?>')" class="btn btn-outline-warning">
                            Video Çözümünü İzle
                        </button>
                    <?php endif; ?>
                    
                    <?php
                    // Karne İndirme Butonu
                    $karne_indir_url = BASE_URL . '/indir_karne.php?katilim_id=' . $katilim_id;
                    ?>
                    <a href="<?php echo $karne_indir_url; ?>" class="btn btn-success" target="_blank"> 
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-earmark-arrow-down-fill me-2" viewBox="0 0 16 16">
                            <path d="M9.293 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4.707A1 1 0 0 0 13.707 4L10 .293A1 1 0 0 0 9.293 0M9.5 3.5v-2l3 3h-2a1 1 0 0 1-1-1m-1 4v3.793l1.146-1.147a.5.5 0 0 1 .708.708l-2 2a.5.5 0 0 1-.708 0l-2-2a.5.5 0 0 1 .708-.708L7.5 11.293V7.5a.5.5 0 0 1 1 0"/>
                        </svg>
                        Deneme Karnesini PDF İndir
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6 mb-4">
        <div class="card h-100 shadow-sm card-theme">
            <div class="card-header card-header-theme-light"> 
                 <h5 class="mb-0 text-theme-primary">Genel Sıralama</h5>
            </div>
            <div class="card-body d-flex flex-column justify-content-center align-items-center">
                <?php if ($siralama_aciklandi_mi && $siralama_gosterim_metni): ?>
                    <p class="card-text fs-4">Sıralamanız: <strong class="text-theme-primary"><?php echo escape_html($siralama_gosterim_metni); ?></strong></p>
                    <p class="card-text small text-theme-secondary">(Sıralama, çan eğrisi puanlarına (varsa) veya ham puanlara göre hesaplanmıştır.)</p>
                <?php else: ?>
                    <div class="alert alert-theme-info small">Sıralama, <strong><?php echo format_tr_datetime($sonuc_detaylari['siralama_aciklama_tarihi']); ?></strong> tarihinde açıklanacaktır.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($konu_analizi)): ?>
<div class="row justify-content-center mt-4">
    <div class="col-lg-10 col-xl-8">
        <div class="card shadow-sm card-theme">
            <div class="card-header card-header-theme-light">
                <h5 class="mb-0 text-theme-primary">Konu Analizi (Konu Karnesi)</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-bordered table-hover konu-karnesi-tablosu-bs small">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Konu Adı</th>
                                <th scope="col" class="text-center">Soru</th> <th scope="col" class="text-center">D</th>
                                <th scope="col" class="text-center">Y</th> <th scope="col" class="text-center">B</th>
                                <th scope="col" class="text-center">Net</th> <th scope="col" class="text-center">Başarı (%)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($konu_analizi as $konu): 
                                $konu_net = $konu['dogru'] - ($konu['yanlis'] / NET_KATSAYISI);
                                $konu_basari = ($konu['toplam_soru'] > 0) ? (($konu['dogru'] / $konu['toplam_soru']) * 100) : 0;
                            ?>
                                <tr>
                                    <td><?php echo escape_html($konu['konu_adi']); ?></td>
                                    <td class="text-center"><?php echo $konu['toplam_soru']; ?></td>
                                    <td class="text-center table-success bg-opacity-75"><?php echo $konu['dogru']; ?></td>
                                    <td class="text-center table-danger bg-opacity-75"><?php echo $konu['yanlis']; ?></td>
                                    <td class="text-center table-secondary bg-opacity-75"><?php echo $konu['bos']; ?></td>
                                    <td class="text-center fw-bold"><?php echo number_format($konu_net, 2); ?></td>
                                    <td class="text-center fw-bold <?php echo ($konu_basari >= 70) ? 'text-success' : (($konu_basari >= 50) ? 'text-warning' : 'text-danger'); ?>">
                                        <?php echo number_format($konu_basari, 1); ?>%
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="text-theme-secondary small text-center">Not: Başarı yüzdesi doğru cevap sayısına göre hesaplanmıştır.</p>
                <?php if (!empty($konu_labels_js)): ?>
                <div class="mt-4 p-3 bg-light rounded shadow-sm" style="max-height: 400px;">
                    <canvas id="konuAnaliziChart"></canvas>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="text-center mt-5">
    <a href="dashboard.php" class="btn btn-theme-primary btn-lg">
         <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-left-circle-fill me-2" viewBox="0 0 16 16">
            <path d="M8 0a8 8 0 1 0 0 16A8 8 0 0 0 8 0m3.5 7.5a.5.5 0 0 1 0 1H5.707l2.147 2.146a.5.5 0 0 1-.708.708l-3-3a.5.5 0 0 1 0-.708l3-3a.5.5 0 1 1 .708.708L5.707 7.5z"/>
        </svg>
        Panele Geri Dön
    </a>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function confirmAndViewSolution(viewUrl, denemeAdi) {
    const uyariMesaji = "Sınav ve çözüm dökümanlarını (\"" + denemeAdi + "\") başkalarıyla paylaşırsam yasal bir maddi tazminat sorumluluğu üstleneceğimi anlıyorum.";
    if (confirm(uyariMesaji)) {
        window.open(viewUrl, '_blank');
    }
}

function confirmAndViewVideo(viewUrl, denemeAdi) {
    const uyariMesaji = "Video çözümlerini (\"" + denemeAdi + "\") başkalarıyla paylaşırsam erişimimin iptal edileceğini ve yasal sorumluluk taşıdığımı anlıyorum.";
    if (confirm(uyariMesaji)) {
        window.open(viewUrl, '_blank');
    }
}

<?php if (!empty($konu_labels_js)): ?>
document.addEventListener('DOMContentLoaded', function () {
    const konuLabels = <?php echo json_encode($konu_labels_js); ?>;
    const dogruSayilari = <?php echo json_encode($konu_dogru_js); ?>;
    const yanlisSayilari = <?php echo json_encode($konu_yanlis_js); ?>;
    const bosSayilari = <?php echo json_encode($konu_bos_js); ?>;
    if (konuLabels.length > 0) { 
        const ctxKonuAnalizi = document.getElementById('konuAnaliziChart').getContext('2d');
        new Chart(ctxKonuAnalizi, {
            type: 'bar',
            data: {
                labels: konuLabels,
                datasets: [
                    { label: 'Doğru', data: dogruSayilari, backgroundColor: 'rgba(25, 135, 84, 0.7)', borderColor: 'rgba(25, 135, 84, 1)', borderWidth: 1 },
                    { label: 'Yanlış', data: yanlisSayilari, backgroundColor: 'rgba(220, 53, 69, 0.7)', borderColor: 'rgba(220, 53, 69, 1)', borderWidth: 1 },
                    { label: 'Boş', data: bosSayilari, backgroundColor: 'rgba(108, 117, 125, 0.7)', borderColor: 'rgba(108, 117, 125, 1)', borderWidth: 1 }
                ]
            },
            options: { /* ... (grafik seçenekleri aynı kalabilir) ... */ }
        });
    }
});
<?php endif; ?>
</script>

<?php
include_once __DIR__ . '/templates/footer.php'; 
?>