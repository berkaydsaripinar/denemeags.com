<?php
// admin/system_settings.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAdminLogin();

if (!isSuperAdmin()) {
    set_admin_flash_message('error', 'Bu sayfaya erişim yetkiniz yok.');
    redirect('dashboard.php');
}

$page_title = "Sistem Ayarları";
$csrf_token = generate_admin_csrf_token();

// Ayarları Çek
$settings = [];
$res = $pdo->query("SELECT * FROM sistem_ayarlari")->fetchAll();
foreach($res as $row) {
    $settings[$row['ayar_adi']] = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_admin_csrf_token($_POST['csrf_token'])) {
        set_admin_flash_message('error', 'Güvenlik hatası.');
    } else {
        try {
            $pdo->beginTransaction();
            
            $updates = [
                'NET_KATSAYISI' => $_POST['net_katsayisi'],
                'PUAN_CARPANI' => $_POST['puan_carpani']
            ];

            foreach($updates as $key => $val) {
                $stmt = $pdo->prepare("UPDATE sistem_ayarlari SET ayar_degeri = ? WHERE ayar_adi = ?");
                $stmt->execute([$val, $key]);
            }

            $pdo->commit();
            set_admin_flash_message('success', 'Sistem parametreleri başarıyla güncellendi.');
            redirect('system_settings.php');
        } catch (Exception $e) {
            $pdo->rollBack();
            set_admin_flash_message('error', 'Hata: ' . $e->getMessage());
        }
    }
}

include_once __DIR__ . '/../templates/admin_header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden fade-in">
            <div class="card-header bg-primary text-white py-4 px-4 border-0">
                <div class="d-flex align-items-center">
                    <div class="bg-white bg-opacity-20 p-3 rounded-3 me-3">
                        <i class="fas fa-cogs fa-2x"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0">Genel Kontrol Merkezi</h5>
                        <p class="mb-0 small opacity-75">Sınav puanlama ve hesaplama motoru ayarları</p>
                    </div>
                </div>
            </div>
            
            <div class="card-body p-5">
                <form action="system_settings.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <div class="mb-4">
                        <label class="form-label fw-bold text-dark mb-1">
                            NET HESAPLAMA KATSAYISI 
                            <i class="fas fa-info-circle ms-1 text-muted" title="Örn: 4 girerseniz 4 yanlış 1 doğruyu siler."></i>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-divide"></i></span>
                            <input type="number" step="0.1" name="net_katsayisi" class="form-control input-theme border-start-0 ps-0" 
                                   value="<?php echo $settings['NET_KATSAYISI']['ayar_degeri'] ?? 4; ?>" required>
                        </div>
                        <div class="form-text small"><?php echo $settings['NET_KATSAYISI']['aciklama'] ?? ''; ?></div>
                    </div>

                    <div class="mb-5">
                        <label class="form-label fw-bold text-dark mb-1">
                            PUAN ÇARPANI
                            <i class="fas fa-info-circle ms-1 text-muted" title="Her bir netin toplam puana etkisi."></i>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-times"></i></span>
                            <input type="number" step="0.01" name="puan_carpani" class="form-control input-theme border-start-0 ps-0" 
                                   value="<?php echo $settings['PUAN_CARPANI']['ayar_degeri'] ?? 2; ?>" required>
                        </div>
                        <div class="form-text small"><?php echo $settings['PUAN_CARPANI']['aciklama'] ?? ''; ?></div>
                    </div>

                    <div class="alert alert-warning border-0 rounded-4 small mb-4 py-3">
                        <i class="fas fa-exclamation-triangle me-2 text-warning"></i>
                        <strong>Dikkat:</strong> Bu ayarların değiştirilmesi, daha sonra yapılacak olan tüm "Yeniden Hesapla" işlemlerini etkiler.
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-theme-primary btn-lg shadow py-3">
                            <i class="fas fa-check-circle me-2"></i> Değişiklikleri Onayla ve Uygula
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../templates/admin_footer.php'; ?>