<?php
// templates/message_area.php (Bootstrap Alerts ile)
require_once __DIR__ . '/../includes/functions.php'; // get_flash_messages için

$flash_messages = get_flash_messages();
if (!empty($flash_messages)) {
    echo '<div id="flashMessageContainer" class="my-3">'; // Bootstrap margin class'ı
    foreach ($flash_messages as $msg) {
        $alert_type_bs = 'info'; // Varsayılan
        switch ($msg['type']) {
            case 'success':
                $alert_type_bs = 'success-gold'; // Özel sınıf veya Bootstrap 'success'
                break;
            case 'error':
                $alert_type_bs = 'danger-gold'; // Özel sınıf veya Bootstrap 'danger'
                break;
            case 'info':
            case 'warning': // warning için de info stili kullanılabilir veya ayrı bir stil eklenebilir
                $alert_type_bs = 'info-gold'; // Özel sınıf veya Bootstrap 'info' / 'warning'
                break;
        }
        // Bootstrap alert sınıflarını kullan
        // echo '<div class="alert alert-' . $alert_type_bs . ' alert-dismissible fade show" role="alert">';
        // Özel stil sınıflarımızı kullanalım:
        echo '<div class="alert alert-'. ($msg['type'] === 'error' ? 'danger' : $msg['type']) .'-gold alert-dismissible fade show" role="alert">';
        echo escape_html($msg['message']);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
    }
    echo '</div>';
}

// Admin flash mesajları için (eğer admin paneli de güncelleniyorsa)
if (function_exists('get_admin_flash_messages')) { // Sadece fonksiyon varsa çalışsın
    $admin_flash_messages = get_admin_flash_messages();
    if (!empty($admin_flash_messages)) {
        echo '<div id="flashMessageContainerAdmin" class="my-3">';
        foreach ($admin_flash_messages as $msg) {
            $admin_alert_type_bs = 'info';
             switch ($msg['type']) {
                case 'success':
                    $admin_alert_type_bs = 'success'; // Bootstrap 'success'
                    break;
                case 'error':
                    $admin_alert_type_bs = 'danger'; // Bootstrap 'danger'
                    break;
            }
            echo '<div class="alert alert-' . $admin_alert_type_bs . ' alert-dismissible fade show" role="alert">';
            echo escape_html($msg['message']);
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
            echo '</div>';
        }
        echo '</div>';
    }
}
?>
