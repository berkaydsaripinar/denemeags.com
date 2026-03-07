<?php
// admin/webhook_logs.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAdminLogin();

// Sadece Süper Admin görebilsin (Güvenlik için)
if (!isSuperAdmin()) {
    set_admin_flash_message('error', 'Bu sayfaya erişim yetkiniz yok.');
    redirect('admin/dashboard.php');
}

$log_file = __DIR__ . '/../webhook_debug.txt';
$page_title = "Shopier Webhook Logları";

// --- AKSİYON: LOGLARI TEMİZLE ---
if (isset($_POST['clear_logs']) && verify_admin_csrf_token($_POST['csrf_token'])) {
    if (file_exists($log_file)) {
        file_put_contents($log_file, ""); // Dosya içeriğini boşalt
        set_admin_flash_message('success', 'Log dosyası başarıyla temizlendi.');
    }
    header("Location: webhook_logs.php");
    exit;
}

include_once __DIR__ . '/../templates/admin_header.php';

// Logları oku ve işle
$logs = [];
if (file_exists($log_file)) {
    $raw_logs = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    // En yeni logları en üstte göstermek için diziyi ters çeviriyoruz
    $logs = array_reverse($raw_logs);
}

// Log içeriğine göre badge/renk belirleme fonksiyonu
function get_log_style($text) {
    if (strpos($text, 'HATA') !== false || strpos($text, 'EXCEPTION') !== false) {
        return ['class' => 'bg-danger-subtle text-danger', 'icon' => 'fa-exclamation-triangle'];
    }
    if (strpos($text, 'BAŞARILI') !== false || strpos($text, 'success') !== false || strpos($text, 'tamamlandı') !== false) {
        return ['class' => 'bg-success-subtle text-success', 'icon' => 'fa-check-circle'];
    }
    if (strpos($text, 'İSTEK GELDİ') !== false) {
        return ['class' => 'bg-primary-subtle text-primary', 'icon' => 'fa-plus-circle'];
    }
    if (strpos($text, 'UYARI') !== false) {
        return ['class' => 'bg-warning-subtle text-warning', 'icon' => 'fa-info-circle'];
    }
    return ['class' => 'bg-light text-dark', 'icon' => 'fa-terminal'];
}
?>

<div class="row mb-4 align-items-center">
    <div class="col">
        <h3 class="fw-bold mb-0">Webhook İşlem Kayıtları</h3>
        <p class="text-muted small">Shopier'den gelen son sinyaller ve işlem detayları.</p>
    </div>
    <div class="col-auto">
        <form method="POST" onsubmit="return confirm('Tüm log kayıtlarını silmek istediğinizden emin misiniz?');">
            <input type="hidden" name="csrf_token" value="<?php echo generate_admin_csrf_token(); ?>">
            <button type="submit" name="clear_logs" class="btn btn-outline-danger btn-sm rounded-pill px-3">
                <i class="fas fa-trash-alt me-2"></i>Logları Temizle
            </button>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive" style="max-height: 700px; overflow-y: auto;">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                <thead class="table-light sticky-top">
                    <tr>
                        <th class="ps-4" style="width: 200px;">Zaman</th>
                        <th style="width: 60px;">Durum</th>
                        <th>İşlem Detayı / Mesaj</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="3" class="text-center py-5 text-muted small">Henüz kaydedilmiş bir log bulunmuyor.</td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $line): 
                            // Format: [2025-12-22 18:34:00] Mesaj...
                            if (preg_match('/^\[(.*?)\] (.*)$/', $line, $matches)):
                                $time = $matches[1];
                                $msg = $matches[2];
                                $style = get_log_style($msg);
                        ?>
                            <tr>
                                <td class="ps-4 text-muted font-monospace small"><?php echo $time; ?></td>
                                <td class="text-center">
                                    <span class="badge <?php echo $style['class']; ?> rounded-circle p-2" title="<?php echo $style['class']; ?>">
                                        <i class="fas <?php echo $style['icon']; ?>"></i>
                                    </span>
                                </td>
                                <td class="py-3">
                                    <?php 
                                        // Bazı özel kelimeleri vurgulayalım
                                        $display_msg = escape_html($msg);
                                        $display_msg = str_replace('İşlem Başlıyor', '<span class="fw-bold">İşlem Başlıyor</span>', $display_msg);
                                        $display_msg = preg_replace('/(Sipariş ID: \d+)/', '<span class="text-primary fw-bold">$1</span>', $display_msg);
                                        $display_msg = preg_replace('/(Email: [^\s]+)/', '<span class="text-dark fw-bold">$1</span>', $display_msg);
                                        echo $display_msg; 
                                    ?>
                                </td>
                            </tr>
                        <?php elseif(strpos($line, '---') !== false): ?>
                            <tr class="table-dark bg-opacity-10">
                                <td colspan="3" class="py-1 text-center small text-muted opacity-50">Yeni Oturum Başlangıcı</td>
                            </tr>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-4 p-4 bg-white rounded-4 shadow-sm border-start border-primary border-4">
    <div class="d-flex align-items-center">
        <i class="fas fa-microchip text-primary fa-2x me-3"></i>
        <div>
            <h6 class="fw-bold mb-1">Log Okuma Notu</h6>
            <p class="text-muted small mb-0">Bu liste doğrudan <code>webhook_debug.txt</code> dosyasından okunmaktadır. Dosya boyutu çok büyürse periyodik olarak temizlemeniz sunucu performansı için önerilir.</p>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../templates/admin_footer.php'; ?>