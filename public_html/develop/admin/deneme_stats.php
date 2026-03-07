<?php
// admin/deneme_stats.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAdminLogin();
$page_title = "Deneme Bazlı İstatistikler";
include_once __DIR__ . '/../templates/admin_header.php';

$deneme_istatistikleri = [];
try {
    $stmt = $pdo->query("
        SELECT
            d.id AS deneme_id,
            d.deneme_adi,
            COUNT(kk.id) AS toplam_tamamlayan_katilimci,
            AVG(kk.dogru_sayisi) AS ort_dogru,
            AVG(kk.yanlis_sayisi) AS ort_yanlis,
            AVG(kk.bos_sayisi) AS ort_bos,
            AVG(kk.net_sayisi) AS ort_net,
            AVG(kk.puan) AS ort_puan,
            AVG(kk.puan_can_egrisi) AS ort_puan_can
        FROM denemeler d
        LEFT JOIN kullanici_katilimlari kk ON d.id = kk.deneme_id AND kk.sinav_tamamlama_tarihi IS NOT NULL
        WHERE d.aktif_mi = 1 -- Sadece aktif denemeler için istatistik (isteğe bağlı)
        GROUP BY d.id, d.deneme_adi
        ORDER BY d.id DESC
    ");
    $deneme_istatistikleri = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    set_admin_flash_message('error', "Deneme istatistikleri yüklenirken hata oluştu: " . $e->getMessage());
    $deneme_istatistikleri = [];
}
?>

<div class="admin-page-title"><?php echo $page_title; ?></div>
<p>Bu sayfada, her bir deneme sınavı için katılımcıların ortalama performanslarını görebilirsiniz. İstatistikler, sınavı tamamlamış katılımcılar üzerinden hesaplanmaktadır.</p>
<p><a href="dashboard.php" class="btn-admin yellow btn-sm">&laquo; Admin Ana Sayfasına Geri Dön</a></p>


<?php if (empty($deneme_istatistikleri)): ?>
    <p class="message-box info">Henüz istatistik üretilebilecek tamamlanmış bir deneme veya katılım bulunmamaktadır.</p>
<?php else: ?>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Deneme Adı</th>
                    <th class="text-center">Tamamlayan Katılımcı Sayısı</th>
                    <th class="text-center">Ort. Doğru</th>
                    <th class="text-center">Ort. Yanlış</th>
                    <th class="text-center">Ort. Boş</th>
                    <th class="text-center">Ort. Net</th>
                    <th class="text-center">Ort. Ham Puan</th>
                    <th class="text-center">Ort. Çan Puanı</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($deneme_istatistikleri as $istatistik): ?>
                <tr>
                    <td>
                        <a href="view_all_results.php?deneme_id=<?php echo $istatistik['deneme_id']; ?>">
                            <?php echo escape_html($istatistik['deneme_adi']); ?>
                        </a>
                    </td>
                    <td class="text-center"><?php echo (int)$istatistik['toplam_tamamlayan_katilimci']; ?></td>
                    <td class="text-center"><?php echo number_format($istatistik['ort_dogru'], 2); ?></td>
                    <td class="text-center"><?php echo number_format($istatistik['ort_yanlis'], 2); ?></td>
                    <td class="text-center"><?php echo number_format($istatistik['ort_bos'], 2); ?></td>
                    <td class="text-center fw-bold"><?php echo number_format($istatistik['ort_net'], 2); ?></td>
                    <td class="text-center"><?php echo number_format($istatistik['ort_puan'], 3); ?></td>
                    <td class="text-center fw-bold"><?php echo $istatistik['toplam_tamamlayan_katilimci'] > 0 && isset($istatistik['ort_puan_can']) ? number_format($istatistik['ort_puan_can'], 3) : 'N/A'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<style>
    .text-center { text-align: center; }
    .fw-bold { font-weight: bold; }
</style>
<?php
include_once __DIR__ . '/../templates/admin_footer.php';
?>
