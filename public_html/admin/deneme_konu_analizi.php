<?php
// admin/deneme_konu_analizi.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAdminLogin();
$page_title = "Deneme Konu Analizi";
include_once __DIR__ . '/../templates/admin_header.php';

// Tüm denemeleri çek (seçim için)
try {
    $stmt_denemeler_list = $pdo->query("SELECT id, deneme_adi FROM denemeler ORDER BY deneme_adi ASC");
    $tum_denemeler_form = $stmt_denemeler_list->fetchAll();
} catch (PDOException $e) {
    set_admin_flash_message('error', "Denemeler yüklenirken hata: " . $e->getMessage());
    $tum_denemeler_form = [];
}

$filter_deneme_id = filter_input(INPUT_GET, 'deneme_id', FILTER_VALIDATE_INT);
$konu_istatistikleri = [];
$deneme_info_for_title = null;
$toplam_sinavi_tamamlayanlar = 0;

if ($filter_deneme_id) {
    try {
        // Seçilen deneme bilgisini al
        $stmt_deneme_info_admin = $pdo->prepare("SELECT id, deneme_adi FROM denemeler WHERE id = ?");
        $stmt_deneme_info_admin->execute([$filter_deneme_id]);
        $deneme_info_for_title = $stmt_deneme_info_admin->fetch();

        if ($deneme_info_for_title) {
            // Bu denemeyi tamamlayan toplam katılımcı sayısı
            $stmt_toplam_katilimci = $pdo->prepare("
                SELECT COUNT(id) AS toplam_katilimci
                FROM kullanici_katilimlari
                WHERE deneme_id = :deneme_id AND sinav_tamamlama_tarihi IS NOT NULL
            ");
            $stmt_toplam_katilimci->execute([':deneme_id' => $filter_deneme_id]);
            $toplam_sinavi_tamamlayanlar = (int)$stmt_toplam_katilimci->fetchColumn();

            // Her konudaki toplam soru sayısını al
            $stmt_konu_soru_sayilari = $pdo->prepare("
                SELECT konu_adi, COUNT(id) AS konudaki_toplam_soru
                FROM cevap_anahtarlari
                WHERE deneme_id = :deneme_id AND konu_adi IS NOT NULL AND konu_adi != ''
                GROUP BY konu_adi
            ");
            $stmt_konu_soru_sayilari->execute([':deneme_id' => $filter_deneme_id]);
            $konu_soru_sayilari_raw = $stmt_konu_soru_sayilari->fetchAll(PDO::FETCH_KEY_PAIR); // konu_adi => konudaki_toplam_soru

            // Konu bazlı Doğru/Yanlış/Boş istatistiklerini çek
            $stmt_stats = $pdo->prepare("
                SELECT
                    ca.konu_adi,
                    SUM(CASE WHEN kc.dogru_mu = 1 THEN 1 ELSE 0 END) AS genel_toplam_dogru,
                    SUM(CASE WHEN kc.dogru_mu = 0 THEN 1 ELSE 0 END) AS genel_toplam_yanlis,
                    SUM(CASE WHEN (kc.verilen_cevap IS NULL OR kc.verilen_cevap = '') THEN 1 ELSE 0 END) AS genel_toplam_bos
                FROM cevap_anahtarlari ca
                LEFT JOIN kullanici_cevaplari kc ON ca.soru_no = kc.soru_no
                LEFT JOIN kullanici_katilimlari kk ON kc.katilim_id = kk.id AND ca.deneme_id = kk.deneme_id
                WHERE ca.deneme_id = :deneme_id 
                  AND kk.sinav_tamamlama_tarihi IS NOT NULL 
                  AND ca.konu_adi IS NOT NULL AND ca.konu_adi != ''
                GROUP BY ca.konu_adi
                ORDER BY genel_toplam_yanlis DESC, ca.konu_adi ASC
            ");
            $stmt_stats->execute([':deneme_id' => $filter_deneme_id]);
            $raw_stats = $stmt_stats->fetchAll(PDO::FETCH_ASSOC);

            // Verileri birleştir
            foreach ($konu_soru_sayilari_raw as $konu_adi => $toplam_soru) {
                $konu_istatistikleri[$konu_adi] = [
                    'konu_adi' => $konu_adi,
                    'konudaki_toplam_soru' => (int)$toplam_soru,
                    'genel_toplam_dogru' => 0,
                    'genel_toplam_yanlis' => 0,
                    'genel_toplam_bos' => 0
                ];
            }

            foreach ($raw_stats as $stat) {
                if (isset($konu_istatistikleri[$stat['konu_adi']])) {
                    $konu_istatistikleri[$stat['konu_adi']]['genel_toplam_dogru'] = (int)$stat['genel_toplam_dogru'];
                    $konu_istatistikleri[$stat['konu_adi']]['genel_toplam_yanlis'] = (int)$stat['genel_toplam_yanlis'];
                    $konu_istatistikleri[$stat['konu_adi']]['genel_toplam_bos'] = (int)$stat['genel_toplam_bos'];
                }
            }
            // İstatistik olmayan konuları da (eğer varsa) listeye dahil etmek için $konu_soru_sayilari_raw baz alınarak döngü kuruldu.
            // Eğer bir konudan hiç cevap gelmemişse (tüm katılımcılar o konudaki tüm soruları boş bırakmışsa veya hiç katılmamışsa)
            // $raw_stats'ta o konu olmayabilir. Bu birleştirme bunu da hesaba katar.

        } else {
            set_admin_flash_message('error', "Seçilen deneme bulunamadı.");
        }
    } catch (PDOException $e) {
        set_admin_flash_message('error', "Konu analizi yüklenirken hata oluştu: " . $e->getMessage());
        $konu_istatistikleri = [];
    }
}
?>

<div class="admin-page-title">Deneme Konu Analizi <?php echo $deneme_info_for_title ? ': ' . escape_html($deneme_info_for_title['deneme_adi']) : ''; ?></div>

<form method="GET" action="deneme_konu_analizi.php" class="form-inline" style="margin-bottom: 20px;">
    <div class="form-group">
        <label for="deneme_id_select_filter">Analiz Edilecek Denemeyi Seçiniz:</label>
        <select name="deneme_id" id="deneme_id_select_filter" class="input-admin" onchange="this.form.submit()" required>
            <option value="">-- Bir Deneme Seçin --</option>
            <?php foreach ($tum_denemeler_form as $deneme_item_filter): ?>
                <option value="<?php echo $deneme_item_filter['id']; ?>" <?php echo ($filter_deneme_id == $deneme_item_filter['id']) ? 'selected' : ''; ?>>
                    <?php echo escape_html($deneme_item_filter['deneme_adi']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<?php if ($filter_deneme_id && $deneme_info_for_title): ?>
    <?php if ($toplam_sinavi_tamamlayanlar > 0): ?>
        <p>Bu denemeyi tamamlayan toplam katılımcı sayısı: <strong><?php echo $toplam_sinavi_tamamlayanlar; ?></strong></p>
        
        <?php if (empty($konu_istatistikleri)): ?>
            <p class="message-box info">Bu deneme için konu bazlı cevap istatistiği bulunmamaktadır (Cevap anahtarında konular tanımlanmamış veya henüz hiç katılım olmamış olabilir).</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Konu Adı</th>
                        <th class="text-center">Konudaki Soru Sayısı</th>
                        <th class="text-center">Toplam Doğru Cevap</th>
                        <th class="text-center">Toplam Yanlış Cevap</th>
                        <th class="text-center">Toplam Boş Bırakılan</th>
                        <th class="text-center">Ort. Başarı (%)</th>
                        <th class="text-center">Ort. Yanlış Oranı (%)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($konu_istatistikleri as $istatistik): ?>
                        <?php
                        $konudaki_max_cevap_sayisi = $istatistik['konudaki_toplam_soru'] * $toplam_sinavi_tamamlayanlar;
                        $ortalama_basari = ($konudaki_max_cevap_sayisi > 0) ? ($istatistik['genel_toplam_dogru'] / $konudaki_max_cevap_sayisi) * 100 : 0;
                        $ortalama_yanlis = ($konudaki_max_cevap_sayisi > 0) ? ($istatistik['genel_toplam_yanlis'] / $konudaki_max_cevap_sayisi) * 100 : 0;
                        ?>
                        <tr>
                            <td><?php echo escape_html($istatistik['konu_adi']); ?></td>
                            <td class="text-center"><?php echo $istatistik['konudaki_toplam_soru']; ?></td>
                            <td class="text-center" style="background-color: rgba(25, 135, 84, 0.1);"><?php echo $istatistik['genel_toplam_dogru']; ?></td>
                            <td class="text-center" style="background-color: rgba(220, 53, 69, 0.1);"><?php echo $istatistik['genel_toplam_yanlis']; ?></td>
                            <td class="text-center" style="background-color: rgba(108, 117, 125, 0.1);"><?php echo $istatistik['genel_toplam_bos']; ?></td>
                            <td class="text-center fw-bold <?php echo ($ortalama_basari >= 70) ? 'text-success' : (($ortalama_basari >= 50) ? 'text-warning' : 'text-danger'); ?>">
                                <?php echo number_format($ortalama_basari, 1); ?>%
                            </td>
                            <td class="text-center <?php echo ($ortalama_yanlis >= 50) ? 'text-danger' : (($ortalama_yanlis >= 30) ? 'text-warning' : ''); ?>">
                                <?php echo number_format($ortalama_yanlis, 1); ?>%
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php else: // $toplam_sinavi_tamamlayanlar == 0 ?>
        <p class="message-box info">Bu denemeyi henüz tamamlayan bir katılımcı bulunmamaktadır.</p>
    <?php endif; ?>
<?php elseif ($filter_deneme_id && !$deneme_info_for_title): ?>
     <p class="message-box error">Seçilen deneme bulunamadı.</p>
<?php else: ?>
    <p class="message-box info">Analiz sonuçlarını görmek için lütfen yukarıdan bir deneme seçiniz.</p>
<?php endif; ?>
<p class="mt-4"><a href="dashboard.php" class="btn-admin yellow">&laquo; Admin Ana Sayfasına Geri Dön</a></p>

<?php
include_once __DIR__ . '/../templates/admin_footer.php';
?>
<style>
    .text-center { text-align: center; }
    .fw-bold { font-weight: bold; }
    .text-success { color: #198754 !important; }
    .text-warning { color: #ffc107 !important; }
    .text-danger { color: #dc3545 !important; }
</style>
