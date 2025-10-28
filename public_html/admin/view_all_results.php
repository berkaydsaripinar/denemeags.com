<?php
// admin/view_all_results.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAdminLogin();
$page_title = "Tüm Sınav Sonuçları";
include_once __DIR__ . '/../templates/admin_header.php';

$authorized_ids = getAuthorizedDenemeIds();

// Deneme listesini role göre filtrele
$deneme_list_sql = "SELECT id, deneme_adi FROM denemeler ORDER BY deneme_adi ASC";
$deneme_list_params = [];
if ($authorized_ids !== null) { // Eğer superadmin değilse
    if (empty($authorized_ids)) {
        $tum_denemeler_form = [];
    } else {
        $in_clause = implode(',', array_fill(0, count($authorized_ids), '?'));
        $deneme_list_sql = "SELECT id, deneme_adi FROM denemeler WHERE id IN ($in_clause) ORDER BY deneme_adi ASC";
        $deneme_list_params = $authorized_ids;
    }
}

try {
    if (!isset($tum_denemeler_form)) {
        $stmt_denemeler_list = $pdo->prepare($deneme_list_sql);
        $stmt_denemeler_list->execute($deneme_list_params);
        $tum_denemeler_form = $stmt_denemeler_list->fetchAll();
    }
} catch (PDOException $e) {
    set_admin_flash_message('error', "Denemeler yüklenirken hata: " . $e->getMessage());
    $tum_denemeler_form = [];
}

$filter_deneme_id = filter_input(INPUT_GET, 'deneme_id', FILTER_VALIDATE_INT);
$results = [];
$deneme_info_for_title = null;

// Eğer subadmin ise, seçilen denemenin yetki dahilinde olduğundan emin ol
if ($filter_deneme_id && $authorized_ids !== null && !in_array($filter_deneme_id, $authorized_ids)) {
    set_admin_flash_message('error', 'Bu denemenin sonuçlarını görüntüleme yetkiniz yok.');
    $filter_deneme_id = null; // Filtreyi sıfırla, sonuçları gösterme
}

if ($filter_deneme_id) {
    try {
        $stmt_deneme_info_admin = $pdo->prepare("SELECT deneme_adi, sonuc_aciklama_tarihi FROM denemeler WHERE id = ?");
        $stmt_deneme_info_admin->execute([$filter_deneme_id]);
        $deneme_info_for_title = $stmt_deneme_info_admin->fetch();

        $stmt_results = $pdo->prepare("
            SELECT 
                k.ad_soyad, k.email, kk.dogru_sayisi, kk.yanlis_sayisi, kk.bos_sayisi, 
                kk.net_sayisi, kk.puan, kk.puan_can_egrisi, kk.sinav_tamamlama_tarihi
            FROM kullanici_katilimlari kk
            JOIN kullanicilar k ON kk.kullanici_id = k.id
            WHERE kk.deneme_id = :deneme_id AND kk.sinav_tamamlama_tarihi IS NOT NULL
            ORDER BY COALESCE(kk.puan_can_egrisi, kk.puan) DESC, kk.net_sayisi DESC
        ");
        $stmt_results->execute([':deneme_id' => $filter_deneme_id]);
        $results = $stmt_results->fetchAll();

    } catch (PDOException $e) {
        set_admin_flash_message('error', "Sonuçlar listelenirken hata oluştu: " . $e->getMessage());
        $results = [];
    }
}
?>

<div class="admin-page-title">Tüm Sınav Sonuçları <?php echo $deneme_info_for_title ? '- ' . escape_html($deneme_info_for_title['deneme_adi']) : ''; ?></div>

<form method="GET" action="view_all_results.php" class="form-inline" style="margin-bottom: 20px;">
    <div class="form-group">
        <label for="deneme_id_select_filter">Deneme Seçiniz:</label>
        <select name="deneme_id" id="deneme_id_select_filter" class="input-admin form-select" onchange="this.form.submit()" required>
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
    <?php if (empty($results)): ?>
        <p class="message-box info">Seçilen deneme için henüz tamamlanmış bir sınav sonucu bulunmamaktadır.</p>
    <?php else: ?>
        <p>Toplam <?php echo count($results); ?> katılımcı bu denemeyi tamamladı.</p>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Sıra</th>
                    <th>Ad Soyad</th>
                    <th>E-posta</th>
                    <th>Net</th>
                    <th>Çan Puanı</th>
                    <th>Ham Puan</th>
                </tr>
            </thead>
            <tbody>
                <?php $sira_no = 1; ?>
                <?php foreach ($results as $result): ?>
                <tr>
                    <td><?php echo $sira_no++; ?></td>
                    <td><?php echo escape_html($result['ad_soyad']); ?></td>
                    <td><?php echo escape_html($result['email']); ?></td>
                    <td><strong><?php echo number_format($result['net_sayisi'], 2); ?></strong></td>
                    <td><strong><?php echo isset($result['puan_can_egrisi']) ? number_format($result['puan_can_egrisi'], 3) : 'N/A'; ?></strong></td>
                    <td><?php echo number_format($result['puan'], 3); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
<?php elseif ($filter_deneme_id && !$deneme_info_for_title): ?>
     <p class="message-box error">Seçilen deneme bulunamadı veya bu denemeyi görüntüleme yetkiniz yok.</p>
<?php else: ?>
    <p class="message-box info">Sonuçları görmek için lütfen yukarıdan bir deneme seçiniz.</p>
<?php endif; ?>
<p class="mt-4"><a href="dashboard.php" class="btn-admin yellow">&laquo; Admin Ana Sayfasına Geri Dön</a></p>

<?php
include_once __DIR__ . '/../templates/admin_footer.php';
?>
