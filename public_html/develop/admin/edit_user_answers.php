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
    header("Location: view_kullanicilar.php");
    exit;
}

try {
    // Katılım ve Öğrenci Bilgisi
    $stmt_info = $pdo->prepare("
        SELECT kk.*, u.ad_soyad, u.email, d.deneme_adi, d.soru_sayisi
        FROM kullanici_katilimlari kk
        JOIN kullanicilar u ON kk.kullanici_id = u.id
        JOIN denemeler d ON kk.deneme_id = d.id
        WHERE kk.id = ?
    ");
    $stmt_info->execute([$katilim_id]);
    $info = $stmt_info->fetch();

    if (!$info) {
        set_admin_flash_message('error', 'Kayıt bulunamadı.');
        header("Location: view_kullanicilar.php");
        exit;
    }

    // Cevap Anahtarı Map
    $stmt_key = $pdo->prepare("SELECT soru_no, dogru_cevap FROM cevap_anahtarlari WHERE deneme_id = ?");
    $stmt_key->execute([$info['deneme_id']]);
    $answer_key = $stmt_key->fetchAll(PDO::FETCH_KEY_PAIR);

    // Öğrenci Cevapları Map
    $stmt_user_ans = $pdo->prepare("SELECT soru_no, verilen_cevap, dogru_mu FROM kullanici_cevaplari WHERE katilim_id = ?");
    $stmt_user_ans->execute([$katilim_id]);
    $user_answers = $stmt_user_ans->fetchAll(PDO::FETCH_UNIQUE|PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Hata: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_admin_csrf_token($_POST['csrf_token'])) {
        $posted_answers = $_POST['answers'] ?? [];
        $pdo->beginTransaction();
        try {
            $dogru = 0; $yanlis = 0; $bos = 0;
            
            // Cevapları Güncelle
            $stmt_upd = $pdo->prepare("INSERT INTO kullanici_cevaplari (katilim_id, soru_no, verilen_cevap, dogru_mu) 
                                       VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE verilen_cevap = VALUES(verilen_cevap), dogru_mu = VALUES(dogru_mu)");

            for($i=1; $i<=$info['soru_sayisi']; $i++) {
                $ans = !empty($posted_answers[$i]) ? $posted_answers[$i] : null;
                $is_correct = null;
                
                if($ans === null) {
                    $bos++;
                } elseif($ans === ($answer_key[$i] ?? '')) {
                    $dogru++;
                    $is_correct = 1;
                } else {
                    $yanlis++;
                    $is_correct = 0;
                }
                $stmt_upd->execute([$katilim_id, $i, $ans, $is_correct]);
            }

            // Özeti Güncelle
            $net = $dogru - ($yanlis / NET_KATSAYISI);
            $puan = $net * PUAN_CARPANI;

            $stmt_sum = $pdo->prepare("UPDATE kullanici_katilimlari SET dogru_sayisi=?, yanlis_sayisi=?, bos_sayisi=?, net_sayisi=?, puan=? WHERE id=?");
            $stmt_sum->execute([$dogru, $yanlis, $bos, $net, $puan, $katilim_id]);

            $pdo->commit();
            set_admin_flash_message('success', 'Cevaplar başarıyla güncellendi ve puanlar yeniden hesaplandı.');
            redirect("view_user_details.php?user_id=" . $info['kullanici_id']);
        } catch (Exception $e) {
            $pdo->rollBack();
            set_admin_flash_message('error', 'Hata: ' . $e->getMessage());
        }
    }
}

$page_title = "Cevap Düzenle: " . $info['ad_soyad'];
include_once __DIR__ . '/../templates/admin_header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col">
        <h3 class="fw-bold mb-0 text-theme-primary">Optik Form Müdahale</h3>
        <p class="text-muted small"><?php echo escape_html($info['deneme_adi']); ?> - #<?php echo $info['id']; ?></p>
    </div>
    <div class="col-auto">
        <a href="view_user_details.php?user_id=<?php echo $info['kullanici_id']; ?>" class="btn btn-light border shadow-sm">
            <i class="fas fa-times me-2"></i> Vazgeç
        </a>
    </div>
</div>

<div class="alert alert-warning border-0 shadow-sm rounded-3 py-2 small mb-4">
    <i class="fas fa-exclamation-triangle me-2"></i> <strong>Dikkat:</strong> Buradan yapılan değişiklikler öğrencinin sonucunu anlık olarak etkiler.
</div>

<form action="edit_user_answers.php?katilim_id=<?php echo $katilim_id; ?>" method="POST" class="fade-in">
    <input type="hidden" name="csrf_token" value="<?php echo generate_admin_csrf_token(); ?>">

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark text-uppercase" style="font-size: 0.75rem; letter-spacing: 1px;">
                        <tr>
                            <th class="ps-4 py-3">Soru</th>
                            <th class="text-center py-3">Doğru Cevap</th>
                            <th class="text-center py-3">Öğrenci İşareti</th>
                            <th class="text-center py-3">Durum</th>
                            <th class="pe-4 py-3" style="width: 200px;">Yeni İşaret</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for($i=1; $i<=$info['soru_sayisi']; $i++): 
                            $curr_ans = $user_answers[$i]['verilen_cevap'] ?? '';
                            $correct = $answer_key[$i] ?? '';
                            $status_class = ($curr_ans === $correct) ? 'text-success' : (empty($curr_ans) ? 'text-muted' : 'text-danger');
                        ?>
                        <tr>
                            <td class="ps-4 fw-bold text-secondary">Soru <?php echo $i; ?></td>
                            <td class="text-center"><span class="badge bg-success-subtle text-success fs-6 px-3"><?php echo $correct; ?></span></td>
                            <td class="text-center fw-bold <?php echo $status_class; ?>"><?php echo $curr_ans ?: 'BOŞ'; ?></td>
                            <td class="text-center">
                                <?php if($curr_ans === $correct): ?>
                                    <i class="fas fa-check-circle text-success"></i>
                                <?php elseif(empty($curr_ans)): ?>
                                    <i class="fas fa-minus-circle text-muted"></i>
                                <?php else: ?>
                                    <i class="fas fa-times-circle text-danger"></i>
                                <?php endif; ?>
                            </td>
                            <td class="pe-4 text-end">
                                <select name="answers[<?php echo $i; ?>]" class="form-select form-select-sm input-theme fw-bold">
                                    <option value="">BOŞ</option>
                                    <?php foreach(['A','B','C','D','E'] as $opt): ?>
                                        <option value="<?php echo $opt; ?>" <?php echo ($curr_ans === $opt) ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white p-4 border-0 text-center">
            <button type="submit" class="btn btn-theme-primary btn-lg px-5 shadow">
                <i class="fas fa-save me-2"></i> Değişiklikleri Uygula
            </button>
        </div>
    </div>
</form>

<?php include_once __DIR__ . '/../templates/admin_footer.php'; ?>