<?php
// templates/admin_header.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/admin_functions.php'; // get_admin_flash_messages için
$admin_base_url = rtrim(BASE_URL, '/') . '/admin'; // Admin klasörünün URL'si
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? escape_html($page_title) . ' - ' : ''; ?>Admin Paneli - <?php echo escape_html(SITE_NAME); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo rtrim(BASE_URL, '/'); ?>/assets/admin_style.css?v=<?php echo time(); // Cache busting ?>">
</head>
<body>
    <div class="admin-container">
        <header class="admin-header-main">
            <h1><a href="<?php echo $admin_base_url; ?>/dashboard.php">Admin Paneli</a></h1>
            <?php if (isAdminLoggedIn()): ?>
            <nav>
                <span>Hoş geldiniz, Admin!</span>
                <a href="<?php echo $admin_base_url; ?>/dashboard.php">Ana Sayfa</a>
                <a href="<?php echo $admin_base_url; ?>/logout.php">Çıkış Yap</a>
            </nav>
            <?php endif; ?>
        </header>
        
        <?php
        // Admin flash mesajlarını göster
        $flash_messages = get_admin_flash_messages(); // veya genel get_flash_messages() kullanılabilir
        if (!empty($flash_messages)) {
            echo '<div id="flashMessageContainerAdmin" class="mt-4">';
            foreach ($flash_messages as $msg) {
                echo '<div class="message-box ' . escape_html($msg['type']) . '">' . escape_html($msg['message']) . '</div>';
            }
            echo '</div>';
        }
        ?>
        <main class="admin-content-main mt-4">
