<?php
// admin/view_all_results.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAdminLogin();
$page_title = "Sınav Sonuç Raporları";

$authorized_ids = getAuthorizedDenemeIds();

// 1. Yetki Dahilindeki Deneme Listesini Çek
$deneme_list_sql = "SELECT id, deneme_adi, tur FROM denemeler";
$deneme_list_params = [];
if ($authorized_ids !== null) {
    if (empty($authorized_ids)) {
        $available_denemeler = [];
    } else {
        $in_clause = implode(',', array_fill(0, count($authorized_ids), '?'));
        $deneme_list_sql .= " WHERE id IN ($in_clause)";
        $deneme_list_params = $authorized_ids;
    }
}
$deneme_list_sql .= " ORDER BY id DESC";

try {
    if (!isset($available_denemeler)) {
        $stmt_list = $pdo->prepare($deneme_list_sql);
        $stmt_list->execute($deneme_list_params);
        $available_denemeler = $stmt_list->fetchAll();
    }
} catch (PDOException $e) {
    $available_denemeler = [];
}

// 2. Filtrelenen Deneme Verilerini Çek
$filter_deneme_id = filter_input(INPUT_GET, 'deneme_id', FILTER_VALIDATE_INT);
$results = [];
$selected_deneme_info = null;

if ($filter_deneme_id) {
    // Subadmin yetki kontrolü
    if ($authorized_ids !== null && !in_array($filter_deneme_id, $authorized_ids)) {
        set_admin_flash_message('error', 'Bu denemenin sonuçlarını görme yetkiniz yok.');
        redirect('admin/view_all_results.php');
    }

    try {
        $stmt_info = $pdo->prepare("SELECT deneme_adi, soru_sayisi FROM denemeler WHERE id = ?");
        $stmt_info->execute([$filter_deneme_id]);
        $selected_deneme_info = $stmt_info->fetch();

        if ($selected_deneme_info) {
            $stmt_results = $pdo->prepare("
                SELECT 
                    k.ad_soyad, k.email, 
                    kk.id as katilim_id, kk.dogru_sayisi, kk.yanlis_sayisi, kk.bos_sayisi, 
                    kk.net_sayisi, kk.puan, kk.puan_can_egrisi, kk.sinav_tamamlama_tarihi
                FROM kullanici_katilimlari kk
                JOIN kullanicilar k ON kk.kullanici_id = k.id
                WHERE kk.deneme_id = :deneme_id AND kk.sinav_tamamlama_tarihi IS NOT NULL
                ORDER BY COALESCE(kk.puan_can_egrisi, kk.puan) DESC, kk.net_sayisi DESC
            ");
            $stmt_results->execute([':deneme_id' => $filter_deneme_id]);
            $results = $stmt_results->fetchAll();
        }
    } catch (PDOException $e) {
        error_log($e->getMessage());
    }
}

include_once __DIR__ . '/../templates/admin_header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col">
        <h3 class="fw-bold mb-0">Sınav Sonuçları ve Sıralama</h3>
        <p class="text-muted small">Deneme bazlı başarı listeleri ve detaylı öğrenci analizleri.</p>
    </div>
    <div class="col-auto">
        <a href="dashboard.php" class="btn btn-light border py-2"><i class="fas fa-arrow-left me-2"></i> Panele Dön</a>
    </div>
</div>

<!-- FİLTRELEME KARTI -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-4">
        <form method="GET" action="view_all_results.php" class="row g-3 align-items-end">
            <div class="col-md-8">
                <label class="form-label small fw-bold">SONUÇLARINI GÖRMEK İSTEDİĞİNİZ YAYINI SEÇİN</label>
                <select name="deneme_id" class="form-select input-theme py-2" onchange="this.form.submit()">
                    <option value="">-- Yayın Seçiniz --</option>
                    <?php foreach ($available_denemeler as $d): ?>
                        <option value="<?php echo $d['id']; ?>" <?php echo $filter_deneme_id == $d['id'] ? 'selected' : ''; ?>>
                            <?php echo ($d['tur'] == 'deneme' ? '🏆 ' : '📚 ') . escape_html($d['deneme_adi']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 text-md-end">
                <?php if($filter_deneme_id && !empty($results)): ?>
                    <button type="button" onclick="window.print()" class="btn btn-outline-secondary py-2 px-4">
                        <i class="fas fa-print me-2"></i> Listeyi Yazdır
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php if ($filter_deneme_id): ?>
    <?php if (!$selected_deneme_info): ?>
        <div class="alert alert-danger rounded-4 border-0 shadow-sm">Seçilen yayın bulunamadı.</div>
    <?php elseif (empty($results)): ?>
        <div class="card border-0 shadow-sm rounded-4 py-5 text-center">
            <div class="p-4 bg-light d-inline-block rounded-circle mx-auto mb-3">
                <i class="fas fa-user-clock fa-3x text-muted opacity-50"></i>
            </div>
            <h5 class="fw-bold">Henüz Katılım Yok</h5>
            <p class="text-muted small">Bu denemeyi henüz hiçbir öğrenci tamamlamadı.</p>
        </div>
    <?php else: ?>
        <!-- SONUÇ TABLOSU -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden fade-in">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-primary"><?php echo escape_html($selected_deneme_info['deneme_adi']); ?> - Başarı Sıralaması</h6>
                <span class="badge bg-primary-subtle text-primary rounded-pill px-3"><?php echo count($results); ?> Katılımcı</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 admin-table text-center">
                        <thead class="table-light small text-uppercase">
                            <tr>
                                <th class="ps-4 text-start" style="width: 80px;">Sıra</th>
                                <th class="text-start">Öğrenci Bilgisi</th>
                                <th>D / Y / B</th>
                                <th>Net</th>
                                <th>Ham Puan</th>
                                <th class="text-primary">Çan Puanı</th>
                                <th class="pe-4 text-end">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $rank = 1;
                            foreach ($results as $res): 
                                $is_top3 = ($rank <= 3);
                            ?>
                            <tr class="<?php echo $is_top3 ? 'table-warning bg-opacity-10' : ''; ?>">
                                <td class="ps-4 text-start">
                                    <?php if($rank == 1): ?>
                                        <span class="fs-4">🥇</span>
                                    <?php elseif($rank == 2): ?>
                                        <span class="fs-4">🥈</span>
                                    <?php elseif($rank == 3): ?>
                                        <span class="fs-4">🥉</span>
                                    <?php else: ?>
                                        <span class="fw-bold text-muted">#<?php echo $rank; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-start">
                                    <div class="fw-bold text-dark"><?php echo escape_html($res['ad_soyad']); ?></div>
                                    <div class="text-muted" style="font-size: 0.7rem;"><?php echo escape_html($res['email']); ?></div>
                                </td>
                                <td>
                                    <span class="text-success fw-bold"><?php echo $res['dogru_sayisi']; ?></span> /
                                    <span class="text-danger fw-bold"><?php echo $res['yanlis_sayisi']; ?></span> /
                                    <span class="text-muted small"><?php echo $res['bos_sayisi']; ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-dark rounded-pill px-3"><?php echo number_format($res['net_sayisi'], 2); ?></span>
                                </td>
                                <td class="small fw-medium"><?php echo number_format($res['puan'], 2); ?></td>
                                <td class="fw-bold text-primary fs-6">
                                    <?php echo $res['puan_can_egrisi'] ? number_format($res['puan_can_egrisi'], 2) : '---'; ?>
                                </td>
                                <td class="pe-4 text-end">
                                    <a href="view_user_details.php?user_id=<?php // Bu alan için user_id gerekli, katilim_id üzerinden de gidilebilir.
                                        $stmt_uid = $pdo->prepare("SELECT kullanici_id FROM kullanici_katilimlari WHERE id = ?");
                                        $stmt_uid->execute([$res['katilim_id']]);
                                        echo $stmt_uid->fetchColumn();
                                    ?>" class="btn btn-sm btn-light border px-3 rounded-pill" title="Analiz">
                                        <i class="fas fa-search me-1"></i> Detay
                                    </a>
                                </td>
                            </tr>
                            <?php $rank++; endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="mt-4 p-4 bg-white rounded-4 shadow-sm border-start border-primary border-4 d-flex align-items-center">
            <i class="fas fa-info-circle text-primary fa-2x me-3"></i>
            <p class="mb-0 small text-muted">
                <strong>Sıralama Mantığı:</strong> Liste öncelikle <strong>Çan Puanı</strong> (eğer hesaplanmışsa), ardından <strong>Net Sayısı</strong> ve son olarak <strong>Doğru Sayısına</strong> göre büyükten küçüğe sıralanmaktadır. 
                Puanlar güncel değilse Yayın Yönetimi ekranından "Yeniden Hesapla" butonunu kullanabilirsiniz.
            </p>
        </div>
    <?php endif; ?>
<?php else: ?>
    <!-- HİÇ SEÇİM YAPILMAMIŞSA -->
    <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white">
        <div class="mb-4 text-primary opacity-25">
            <i class="fas fa-chart-bar fa-5x"></i>
        </div>
        <h4 class="fw-bold">Raporlama Hazır</h4>
        <p class="text-muted mx-auto" style="max-width: 450px;">
            Öğrenci başarı sıralamalarını, genel net ortalamalarını ve çan eğrisi sonuçlarını görmek için yukarıdaki listeden bir yayın seçin.
        </p>
    </div>
<?php endif; ?>

<?php include_once __DIR__ . '/../templates/admin_footer.php'; ?>