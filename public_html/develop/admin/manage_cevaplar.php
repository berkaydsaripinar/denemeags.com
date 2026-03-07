<?php
// admin/manage_cevaplar.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAdminLogin();

$deneme_id = filter_input(INPUT_GET, 'deneme_id', FILTER_VALIDATE_INT);
if (!$deneme_id) {
    set_admin_flash_message('error', 'Geçersiz yayın ID.');
    header("Location: manage_denemeler.php");
    exit;
}

// Yayın bilgilerini çek
try {
    $stmt_deneme = $pdo->prepare("SELECT id, deneme_adi, soru_sayisi, tur FROM denemeler WHERE id = ?");
    $stmt_deneme->execute([$deneme_id]);
    $deneme = $stmt_deneme->fetch();

    if (!$deneme) {
        set_admin_flash_message('error', 'Yayın bulunamadı.');
        header("Location: manage_denemeler.php");
        exit;
    }
} catch (PDOException $e) {
    set_admin_flash_message('error', "Veri hatası: " . $e->getMessage());
    header("Location: manage_denemeler.php");
    exit;
}

$page_title = "Cevap Anahtarı: " . $deneme['deneme_adi'];
$soru_sayisi = $deneme['soru_sayisi'];
$options = ['A', 'B', 'C', 'D', 'E'];

// Mevcut cevapları çek
$mevcut_cevaplar = [];
try {
    $stmt_mevcut = $pdo->prepare("SELECT soru_no, dogru_cevap, konu_adi FROM cevap_anahtarlari WHERE deneme_id = ? ORDER BY soru_no ASC");
    $stmt_mevcut->execute([$deneme_id]);
    while ($row = $stmt_mevcut->fetch()) {
        $mevcut_cevaplar[$row['soru_no']] = ['dogru_cevap' => $row['dogru_cevap'], 'konu_adi' => $row['konu_adi']];
    }
} catch (PDOException $e) {
    error_log("Cevap anahtarı çekme hatası: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_answers'])) {
    if (!verify_admin_csrf_token($_POST['csrf_token'])) {
        set_admin_flash_message('error', 'Geçersiz CSRF token.');
    } else {
        $gelen_cevaplar = $_POST['cevaplar'] ?? [];
        $gelen_konular = $_POST['konular'] ?? [];

        try {
            $pdo->beginTransaction();

            $stmt_delete = $pdo->prepare("DELETE FROM cevap_anahtarlari WHERE deneme_id = ?");
            $stmt_delete->execute([$deneme_id]);

            $stmt_insert = $pdo->prepare("INSERT INTO cevap_anahtarlari (deneme_id, soru_no, dogru_cevap, konu_adi) VALUES (?, ?, ?, ?)");

            $kaydedilen_sayisi = 0;
            for ($i = 1; $i <= $soru_sayisi; $i++) {
                $dogru_cevap = isset($gelen_cevaplar[$i]) ? strtoupper(trim($gelen_cevaplar[$i])) : null;
                $konu_adi = isset($gelen_konular[$i]) ? trim($gelen_konular[$i]) : null;

                if (!empty($dogru_cevap) && in_array($dogru_cevap, $options)) {
                    $stmt_insert->execute([$deneme_id, $i, $dogru_cevap, $konu_adi]);
                    $kaydedilen_sayisi++;
                }
            }
            $pdo->commit();
            set_admin_flash_message('success', "$kaydedilen_sayisi soru için cevap anahtarı güncellendi.");
            header("Location: manage_cevaplar.php?deneme_id=" . $deneme_id);
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            set_admin_flash_message('error', "Kaydedilemedi: " . $e->getMessage());
        }
    }
}

include_once __DIR__ . '/../templates/admin_header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col">
        <h3 class="fw-bold mb-0">Cevap Anahtarı Editörü</h3>
        <p class="text-muted small"><?php echo escape_html($deneme['deneme_adi']); ?> (<?php echo $soru_sayisi; ?> Soru)</p>
    </div>
    <div class="col-auto">
        <a href="manage_denemeler.php" class="btn btn-light border py-2 px-3">
            <i class="fas fa-arrow-left me-2"></i> Yayınlara Dön
        </a>
        <a href="export_answer_key_csv.php?deneme_id=<?php echo $deneme_id; ?>" class="btn btn-outline-primary py-2 px-3 ms-2">
            <i class="fas fa-download me-2"></i> CSV Dışa Aktar
        </a>
    </div>
</div>

<form action="manage_cevaplar.php?deneme_id=<?php echo $deneme_id; ?>" method="POST" class="fade-in">
    <input type="hidden" name="csrf_token" value="<?php echo generate_admin_csrf_token(); ?>">
    <input type="hidden" name="save_answers" value="1">

    <div class="card border-0 shadow-sm rounded-4 mb-5">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 admin-table">
                    <thead class="table-light text-theme-primary">
                        <tr>
                            <th class="ps-4" style="width: 100px;">SORU NO</th>
                            <th style="width: 200px;">DOĞRU CEVAP</th>
                            <th>KONU ADI / ANALİZ ETİKETİ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($i = 1; $i <= $soru_sayisi; $i++): ?>
                        <tr>
                            <td class="ps-4">
                                <span class="badge bg-secondary rounded-pill px-3 py-2">Soru <?php echo $i; ?></span>
                            </td>
                            <td>
                                <select name="cevaplar[<?php echo $i; ?>]" class="form-select input-theme fw-bold text-center border-2" style="width: 120px;">
                                    <option value="">Seç...</option>
                                    <?php foreach ($options as $opt): ?>
                                    <option value="<?php echo $opt; ?>" 
                                            <?php echo (isset($mevcut_cevaplar[$i]['dogru_cevap']) && $mevcut_cevaplar[$i]['dogru_cevap'] === $opt) ? 'selected' : ''; ?>>
                                        <?php echo $opt; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <input type="text" name="konular[<?php echo $i; ?>]" class="form-control input-theme" 
                                       value="<?php echo escape_html($mevcut_cevaplar[$i]['konu_adi'] ?? ''); ?>"
                                       placeholder="Örn: Dil Bilgisi - Ses Olayları">
                            </td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white p-4 text-center border-0 rounded-bottom-4">
            <button type="submit" class="btn btn-admin-primary btn-lg px-5 shadow">
                <i class="fas fa-save me-2"></i> Cevap Anahtarını Kaydet
            </button>
        </div>
    </div>
</form>

<?php include_once __DIR__ . '/../templates/admin_footer.php'; ?>