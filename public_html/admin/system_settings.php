<?php
// admin/system_settings.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php'; // $pdo ve ayar sabitleri burada tanımlanır
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAdminLogin();
$page_title = "Sistem Ayarları";
$csrf_token = generate_admin_csrf_token();
include_once __DIR__ . '/../templates/admin_header.php';

// Mevcut ayarları veritabanından çek
$current_settings = [];
try {
    $stmt_get_settings = $pdo->query("SELECT ayar_adi, ayar_degeri, aciklama FROM sistem_ayarlari");
    while($row = $stmt_get_settings->fetch(PDO::FETCH_ASSOC)) {
        $current_settings[$row['ayar_adi']] = $row;
    }
} catch (PDOException $e) {
    set_admin_flash_message('error', "Ayarlar yüklenirken veritabanı hatası: " . $e->getMessage());
}

// Form gönderildiğinde ayarları güncelle
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_admin_csrf_token($_POST['csrf_token'])) {
        set_admin_flash_message('error', 'Geçersiz CSRF token.');
    } else {
        $new_net_katsayisi = filter_input(INPUT_POST, 'net_katsayisi', FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 1]]);
        $new_puan_carpani = filter_input(INPUT_POST, 'puan_carpani', FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0.1]]);

        $errors_update = [];
        if ($new_net_katsayisi === false || $new_net_katsayisi === null) {
            $errors_update[] = "Net Katsayısı geçerli bir sayı olmalıdır (Örn: 4).";
        }
        if ($new_puan_carpani === false || $new_puan_carpani === null) {
            $errors_update[] = "Puan Çarpanı geçerli bir sayı olmalıdır (Örn: 2).";
        }

        if (empty($errors_update)) {
            try {
                $pdo->beginTransaction();
                
                $stmt_update_net = $pdo->prepare("UPDATE sistem_ayarlari SET ayar_degeri = ? WHERE ayar_adi = 'NET_KATSAYISI'");
                $stmt_update_net->execute([$new_net_katsayisi]);

                $stmt_update_puan = $pdo->prepare("UPDATE sistem_ayarlari SET ayar_degeri = ? WHERE ayar_adi = 'PUAN_CARPANI'");
                $stmt_update_puan->execute([$new_puan_carpani]);
                
                $pdo->commit();
                set_admin_flash_message('success', 'Sistem ayarları başarıyla güncellendi. Değişikliklerin tam olarak yansıması için bazen sayfanın yeniden yüklenmesi veya cache temizliği gerekebilir.');
                // Ayarları yeniden yükle
                header("Location: system_settings.php"); // Sayfayı yeniden yönlendirerek güncel değerleri göster
                exit;

            } catch (PDOException $e) {
                $pdo->rollBack();
                set_admin_flash_message('error', "Ayarlar güncellenirken veritabanı hatası: " . $e->getMessage());
            }
        } else {
            foreach ($errors_update as $err) {
                set_admin_flash_message('error', $err);
            }
        }
        // Hata durumunda da sayfayı yeniden yönlendirerek flash mesajları göster
        header("Location: system_settings.php");
        exit;
    }
}

// Form için mevcut değerleri al (veritabanından veya varsayılanlardan)
$val_net_katsayisi = $current_settings['NET_KATSAYISI']['ayar_degeri'] ?? (defined('NET_KATSAYISI') ? NET_KATSAYISI : 4);
$val_puan_carpani = $current_settings['PUAN_CARPANI']['ayar_degeri'] ?? (defined('PUAN_CARPANI') ? PUAN_CARPANI : 2);

?>

<div class="admin-page-title"><?php echo $page_title; ?></div>
<p>Bu sayfadan sistemin temel çalışma parametrelerini (net hesaplama katsayısı, puanlama çarpanı vb.) düzenleyebilirsiniz.</p>

<form action="system_settings.php" method="POST" style="max-width: 600px;">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    
    <div class="form-group mb-3">
        <label for="net_katsayisi" class="form-label">Net Katsayısı:</label>
        <input type="number" step="0.1" id="net_katsayisi" name="net_katsayisi" class="input-admin form-control" 
               value="<?php echo escape_html($val_net_katsayisi); ?>" required>
        <small class="form-text text-muted">
            <?php echo escape_html($current_settings['NET_KATSAYISI']['aciklama'] ?? 'Kaç yanlışın bir doğruyu götüreceği. Örneğin, 4 girerseniz 4 yanlış 1 doğruyu götürür.'); ?>
        </small>
    </div>

    <div class="form-group mb-3">
        <label for="puan_carpani" class="form-label">Puan Çarpanı:</label>
        <input type="number" step="0.01" id="puan_carpani" name="puan_carpani" class="input-admin form-control" 
               value="<?php echo escape_html($val_puan_carpani); ?>" required>
        <small class="form-text text-muted">
            <?php echo escape_html($current_settings['PUAN_CARPANI']['aciklama'] ?? 'Her bir netin kaç puanla çarpılacağı. Örneğin, 2 girerseniz her net 2 puan değerinde olur.'); ?>
        </small>
    </div>

    <button type="submit" class="btn-admin green">Ayarları Kaydet</button>
    <a href="dashboard.php" class="btn-admin yellow ms-2">İptal</a>
</form>

<?php
include_once __DIR__ . '/../templates/admin_footer.php';
?>
