<?php
// admin/manage_cevaplar.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAdminLogin();

$deneme_id = filter_input(INPUT_GET, 'deneme_id', FILTER_VALIDATE_INT);
if (!$deneme_id) {
    set_admin_flash_message('error', 'Geçersiz deneme ID.');
    header("Location: manage_denemeler.php");
    exit;
}

// Deneme bilgilerini çek
try {
    $stmt_deneme = $pdo->prepare("SELECT id, deneme_adi, soru_sayisi FROM denemeler WHERE id = ?");
    $stmt_deneme->execute([$deneme_id]);
    $deneme = $stmt_deneme->fetch();

    if (!$deneme) {
        set_admin_flash_message('error', 'Deneme bulunamadı.');
        header("Location: manage_denemeler.php");
        exit;
    }
} catch (PDOException $e) {
    set_admin_flash_message('error', "Deneme bilgileri yüklenirken hata: " . $e->getMessage());
    header("Location: manage_denemeler.php");
    exit;
}

$page_title = "Cevap Anahtarı Yönetimi: " . escape_html($deneme['deneme_adi']);
$csrf_token = generate_admin_csrf_token();
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
    set_admin_flash_message('error', "Mevcut cevaplar yüklenirken hata: " . $e->getMessage());
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_answers'])) { // Formu ayırt etmek için
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
                } elseif (!empty($dogru_cevap)) {
                    set_admin_flash_message('warning', "Soru $i için geçersiz cevap seçeneği ('$dogru_cevap') atlandı.");
                }
            }
            $pdo->commit();
            set_admin_flash_message('success', "$kaydedilen_sayisi adet cevap anahtarı başarıyla kaydedildi/güncellendi.");
            header("Location: manage_cevaplar.php?deneme_id=" . $deneme_id);
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            set_admin_flash_message('error', "Cevap anahtarı kaydedilirken hata: " . $e->getMessage());
        }
    }
    header("Location: manage_cevaplar.php?deneme_id=" . $deneme_id);
    exit;
}


include_once __DIR__ . '/../templates/admin_header.php';
?>

<div class="admin-page-title"><?php echo $page_title; ?></div>
<p>Toplam Soru Sayısı: <?php echo $soru_sayisi; ?></p>
<div class="mb-3">
    <a href="manage_denemeler.php" class="btn-admin yellow btn-sm">&laquo; Deneme Listesine Geri Dön</a>
    <a href="export_answer_key_csv.php?deneme_id=<?php echo $deneme_id; ?>" class="btn-admin blue btn-sm" style="margin-left: 10px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-download me-1" viewBox="0 0 16 16">
            <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>
            <path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z"/>
        </svg>
        CSV Olarak Dışa Aktar
    </a>
   
</div>


<form action="manage_cevaplar.php?deneme_id=<?php echo $deneme_id; ?>" method="POST">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="save_answers" value="1"> {/* Formu ayırt etmek için */}
    
    <table class="admin-table">
        <thead>
            <tr>
                <th>Soru No</th>
                <th>Doğru Cevap</th>
                <th>Konu Adı (Konu Karnesi İçin - İsteğe Bağlı)</th>
            </tr>
        </thead>
        <tbody>
            <?php for ($i = 1; $i <= $soru_sayisi; $i++): ?>
            <tr>
                <td><?php echo $i; ?></td>
                <td>
                    <select name="cevaplar[<?php echo $i; ?>]" class="input-admin" style="padding: 5px; width: auto;">
                        <option value="">Seçiniz</option>
                        <?php foreach ($options as $opt): ?>
                        <option value="<?php echo $opt; ?>" 
                                <?php echo (isset($mevcut_cevaplar[$i]['dogru_cevap']) && $mevcut_cevaplar[$i]['dogru_cevap'] === $opt) ? 'selected' : ''; ?>>
                            <?php echo $opt; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <input type="text" name="konular[<?php echo $i; ?>]" class="input-admin" 
                           value="<?php echo escape_html($mevcut_cevaplar[$i]['konu_adi'] ?? ''); ?>"
                           placeholder="Örn: Dil Bilgisi - Ses Olayları">
                </td>
            </tr>
            <?php endfor; ?>
        </tbody>
    </table>
    <button type="submit" class="btn-admin green mt-3">Cevap Anahtarını Kaydet</button>
</form>
<style> .btn-admin.blue { background-color: #3B82F6; } .btn-admin.blue:hover { background-color: #2563EB; } </style>
<?php
include_once __DIR__ . '/../templates/admin_footer.php';
?>
