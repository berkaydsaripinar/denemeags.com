<?php
// admin/view_user_details.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAdminLogin();

$user_id_to_view = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
if (!$user_id_to_view) {
    set_admin_flash_message('error', 'Geçersiz kullanıcı ID.');
    header("Location: view_kullanicilar.php");
    exit;
}

$page_title = "Kullanıcı Detayları ve Sınav Analizi";
include_once __DIR__ . '/../templates/admin_header.php';

try {
    $stmt_user_info = $pdo->prepare("SELECT id, ad_soyad, email, aktif_mi, kayit_tarihi FROM kullanicilar WHERE id = ?");
    $stmt_user_info->execute([$user_id_to_view]);
    $user_info = $stmt_user_info->fetch();

    if (!$user_info) {
        set_admin_flash_message('error', 'Kullanıcı bulunamadı.');
        header("Location: view_kullanicilar.php");
        exit;
    }

    $stmt_user_participations = $pdo->prepare("
        SELECT 
            kk.id AS katilim_id,
            kk.deneme_id,
            d.deneme_adi,
            kk.dogru_sayisi,
            kk.yanlis_sayisi,
            kk.bos_sayisi,
            kk.net_sayisi,
            kk.puan,
            kk.puan_can_egrisi,
            kk.sinav_tamamlama_tarihi
        FROM kullanici_katilimlari kk
        JOIN denemeler d ON kk.deneme_id = d.id
        WHERE kk.kullanici_id = ? AND kk.sinav_tamamlama_tarihi IS NOT NULL
        ORDER BY kk.sinav_tamamlama_tarihi DESC
    ");
    $stmt_user_participations->execute([$user_id_to_view]);
    $participations = $stmt_user_participations->fetchAll();

} catch (PDOException $e) {
    set_admin_flash_message('error', "Kullanıcı detayları yüklenirken hata: " . $e->getMessage());
    $user_info = null;
    $participations = [];
}

?>

<div class="admin-page-title">Kullanıcı Detayları: <?php echo escape_html($user_info['ad_soyad'] ?? 'N/A'); ?></div>
<p><a href="view_kullanicilar.php" class="btn-admin yellow">&laquo; Kullanıcı Listesine Geri Dön</a></p>

<?php if ($user_info): ?>
    <div class="card mb-4">
        <div class="card-header">Kullanıcı Bilgileri</div>
        <div class="card-body">
            <p><strong>ID:</strong> <?php echo $user_info['id']; ?></p>
            <p><strong>Ad Soyad:</strong> <?php echo escape_html($user_info['ad_soyad']); ?></p>
            <p><strong>E-posta:</strong> <?php echo escape_html($user_info['email']); ?></p>
            <p><strong>Durum:</strong> <?php echo $user_info['aktif_mi'] ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-danger">Pasif</span>'; ?></p>
            <p><strong>Kayıt Tarihi:</strong> <?php echo format_tr_datetime($user_info['kayit_tarihi']); ?></p>
            <a href="edit_kullanici.php?user_id=<?php echo $user_info['id']; ?>" class="btn-admin yellow btn-sm">Bu Kullanıcıyı Düzenle</a>
        </div>
    </div>

    <h3 class="mt-4 mb-3">Katıldığı Denemeler ve Sonuçları</h3>
    <?php if (empty($participations)): ?>
        <p class="message-box info">Bu kullanıcı henüz hiçbir denemeyi tamamlamamış.</p>
    <?php else: ?>
        <?php foreach ($participations as $katilim): ?>
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>
                        <strong><?php echo escape_html($katilim['deneme_adi']); ?></strong>
                        <small class="text-muted ms-2">Tamamlama: <?php echo format_tr_datetime($katilim['sinav_tamamlama_tarihi'], 'd M Y H:i'); ?></small>
                    </span>
                    <a href="edit_user_answers.php?katilim_id=<?php echo $katilim['katilim_id']; ?>" class="btn-admin blue btn-sm"> 
                        Cevapları Düzenle/İncele
                    </a>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <h5>Genel Sonuçlar</h5>
                            <ul class="list-unstyled">
                                <li>Doğru: <?php echo $katilim['dogru_sayisi']; ?></li>
                                <li>Yanlış: <?php echo $katilim['yanlis_sayisi']; ?></li>
                                <li>Boş: <?php echo $katilim['bos_sayisi']; ?></li>
                                <li><strong>Net: <?php echo number_format($katilim['net_sayisi'], 2); ?></strong></li>
                                <li>Ham Puan: <?php echo number_format($katilim['puan'], 3); ?></li>
                                <?php if (isset($katilim['puan_can_egrisi'])): ?>
                                <li><strong>Çan Puanı: <?php echo number_format($katilim['puan_can_egrisi'], 3); ?></strong></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <div class="col-md-8">
                            <h5>Konu Bazlı Yanlışlar ve Detaylar</h5>
                            <?php
                            $konu_detaylari = [];
                            try {
                                $stmt_konu_analiz_user = $pdo->prepare("
                                    SELECT 
                                        ca.konu_adi, 
                                        ca.soru_no, 
                                        kc.verilen_cevap, 
                                        ca.dogru_cevap
                                    FROM kullanici_cevaplari kc
                                    JOIN cevap_anahtarlari ca ON kc.soru_no = ca.soru_no AND ca.deneme_id = :deneme_id
                                    WHERE kc.katilim_id = :katilim_id AND kc.dogru_mu = 0 AND ca.konu_adi IS NOT NULL AND ca.konu_adi != ''
                                    ORDER BY ca.konu_adi, ca.soru_no
                                ");
                                $stmt_konu_analiz_user->execute([':katilim_id' => $katilim['katilim_id'], ':deneme_id' => $katilim['deneme_id']]);
                                $yanlis_yapilan_sorular = $stmt_konu_analiz_user->fetchAll();

                                if (empty($yanlis_yapilan_sorular)) {
                                    echo '<p class="text-success">Bu denemede konu bazında hiç yanlışınız bulunmuyor veya konular tanımlanmamış. Tebrikler!</p>';
                                } else {
                                    foreach ($yanlis_yapilan_sorular as $yanlis_soru) {
                                        $konu_detaylari[$yanlis_soru['konu_adi']][] = $yanlis_soru;
                                    }
                                    echo '<ul class="list-group list-group-flush">';
                                    foreach ($konu_detaylari as $konu_adi => $sorular) {
                                        echo '<li class="list-group-item">';
                                        echo '<strong>Konu: ' . escape_html($konu_adi) . ' (Toplam ' . count($sorular) . ' yanlış)</strong>';
                                        echo '<ul>';
                                        foreach ($sorular as $s) {
                                            echo '<li>Soru ' . $s['soru_no'] . ': Verdiğiniz Cevap: ' . escape_html($s['verilen_cevap'] ?: 'Boş') . ', Doğru Cevap: ' . escape_html($s['dogru_cevap']) . '</li>';
                                        }
                                        echo '</ul>';
                                        echo '</li>';
                                    }
                                    echo '</ul>';
                                }
                            } catch (PDOException $e) {
                                echo '<p class="text-danger">Konu analizi yüklenirken hata oluştu.</p>';
                                error_log("Kullanıcı detay konu analizi hatası: " . $e->getMessage());
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
<?php else: ?>
    <p class="message-box error">Kullanıcı bilgileri yüklenemedi.</p>
<?php endif; ?>
<style> .btn-admin.blue { background-color: #3B82F6; } .btn-admin.blue:hover { background-color: #2563EB; } </style>
<?php
include_once __DIR__ . '/../templates/admin_footer.php';
?>
