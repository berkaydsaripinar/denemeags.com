<?php
// templates/admin_header.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_functions.php';

$is_logged_in = isAdminLoggedIn();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Yönetim Paneli'; ?> | <?php echo SITE_NAME; ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --admin-primary: #1F3C88;
            --admin-accent: #F57C00;
            --admin-bg: #f4f7fa;
            --sidebar-width: 260px;
        }

        body { font-family: 'Inter', sans-serif; background-color: var(--admin-bg); overflow-x: hidden; margin: 0; }

        /* --- Sidebar & Wrapper Mimari --- */
        #wrapper {
            display: flex;
            width: 100%;
            align-items: stretch;
        }

        #sidebar {
            min-width: var(--sidebar-width);
            max-width: var(--sidebar-width);
            background: var(--admin-primary);
            color: #fff;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            min-height: 100vh;
            position: fixed;
            z-index: 1050;
        }

        #sidebar.collapsed {
            margin-left: calc(-1 * var(--sidebar-width));
        }

        .sidebar-header { 
            padding: 20px; 
            background: rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .sidebar-menu { list-style: none; padding: 15px 0; margin: 0; }
        .sidebar-menu li a {
            padding: 12px 25px;
            display: flex;
            align-items: center;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            transition: 0.2s;
            font-size: 0.95rem;
            font-weight: 500;
        }
        .sidebar-menu li a i { width: 25px; font-size: 1.1rem; margin-right: 10px; }
        .sidebar-menu li a:hover, .sidebar-menu li.active a {
            color: #fff;
            background: rgba(255,255,255,0.1);
            border-left: 4px solid var(--admin-accent);
        }
        .menu-label { padding: 15px 25px 5px; font-size: 0.7rem; text-transform: uppercase; color: rgba(255,255,255,0.4); letter-spacing: 1px; font-weight: 700; }

        /* --- Content Area --- */
        #main-content {
            width: 100%;
            padding: 20px;
            min-height: 100vh;
            transition: all 0.3s;
            margin-left: <?php echo $is_logged_in ? 'var(--sidebar-width)' : '0'; ?>;
        }

        #main-content.full-width {
            margin-left: 0 !important;
        }

        /* --- Topbar --- */
        .admin-topbar {
            background: #fff;
            padding: 12px 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* --- Mobile Overlay --- */
        #sidebarOverlay {
            display: none;
            position: fixed;
            width: 100vw;
            height: 100vh;
            background: rgba(0,0,0,0.5);
            z-index: 1040;
            top: 0;
            left: 0;
            backdrop-filter: blur(2px);
        }
        #sidebarOverlay.active { display: block; }

        /* --- Buttons --- */
        .btn-toggle-sidebar {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            color: var(--admin-primary);
            border-radius: 8px;
            padding: 5px 12px;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-toggle-sidebar:hover { background: var(--admin-primary); color: #fff; }

        .close-sidebar-btn {
            background: none; border: none; color: #fff; font-size: 1.5rem; cursor: pointer; padding: 0; line-height: 1; display: none;
        }

        /* --- Responsive --- */
        @media (max-width: 992px) {
            #sidebar { margin-left: calc(-1 * var(--sidebar-width)); }
            #sidebar.active { margin-left: 0; }
            #main-content { margin-left: 0 !important; }
            .close-sidebar-btn { display: block; }
        }
    </style>
</head>
<body>

<?php if ($is_logged_in): ?>
<div id="sidebarOverlay" onclick="toggleSidebar()"></div>

<div id="wrapper">
    <!-- Sidebar -->
    <nav id="sidebar">
        <div class="sidebar-header">
            <h4 class="fw-bold mb-0 text-white"><i class="fas fa-shield-alt text-warning me-2"></i>Deneme<span class="text-warning">AGS</span></h4>
            <button class="close-sidebar-btn d-lg-none" onclick="toggleSidebar()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <ul class="sidebar-menu">
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                <a href="dashboard.php"><i class="fas fa-th-large"></i> Genel Bakış</a>
            </li>
            
            <div class="menu-label">İÇERİK YÖNETİMİ</div>
            <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'manage_denemeler.php' || basename($_SERVER['PHP_SELF']) == 'edit_deneme.php' || basename($_SERVER['PHP_SELF']) == 'manage_cevaplar.php') ? 'active' : ''; ?>">
                <a href="manage_denemeler.php"><i class="fas fa-book"></i> Yayınlar / Ürünler</a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage_kodlar.php' ? 'active' : ''; ?>">
                <a href="manage_kodlar.php"><i class="fas fa-key"></i> Erişim Kodları</a>
            </li>
            <!-- YENİ: Sınav Sonuçları Menüsü -->
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'view_all_results.php' ? 'active' : ''; ?>">
                <a href="view_all_results.php"><i class="fas fa-poll"></i> Sınav Sonuçları</a>
            </li>

            <div class="menu-label">SATIŞ & YAZAR</div>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage_satislar.php' ? 'active' : ''; ?>">
                <a href="manage_satislar.php"><i class="fas fa-shopping-cart"></i> Siparişler / Loglar</a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage_yazar_odemeleri.php' ? 'active' : ''; ?>">
                <a href="manage_yazar_odemeleri.php"><i class="fas fa-hand-holding-usd"></i> Hakediş Ödemeleri</a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage_yazarlar.php' ? 'active' : ''; ?>">
                <a href="manage_yazarlar.php"><i class="fas fa-user-edit"></i> Yazarlar & Hakediş</a>
            </li>

            <div class="menu-label">KULLANICI</div>
            <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'view_kullanicilar.php' || basename($_SERVER['PHP_SELF']) == 'view_user_details.php') ? 'active' : ''; ?>">
                <a href="view_kullanicilar.php"><i class="fas fa-users"></i> Kayıtlı Öğrenciler</a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage_duyurular.php' ? 'active' : ''; ?>">
                <a href="manage_duyurular.php"><i class="fas fa-bullhorn"></i> Duyuru Panosu</a>
            </li>

            <?php if (isSuperAdmin()): ?>
            <div class="menu-label">SİSTEM</div>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage_admins.php' ? 'active' : ''; ?>">
                <a href="manage_admins.php"><i class="fas fa-user-shield"></i> Yönetici Ayarları</a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'homepage_settings.php' ? 'active' : ''; ?>">
                <a href="homepage_settings.php"><i class="fas fa-columns"></i> Anasayfa Sidebar</a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'system_settings.php' ? 'active' : ''; ?>">
                <a href="system_settings.php"><i class="fas fa-cog"></i> Genel Ayarlar</a>
            </li>
            <!-- YENİ: Webhook Logları Menüsü -->
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'webhook_logs.php' ? 'active' : ''; ?>">
                <a href="webhook_logs.php"><i class="fas fa-terminal"></i> Webhook Logları</a>
            </li>
            <?php endif; ?>

            <li class="mt-4"><a href="logout.php" class="text-danger"><i class="fas fa-sign-out-alt"></i> Güvenli Çıkış</a></li>
        </ul>
    </nav>
<?php endif; ?>

    <!-- Main Content Area -->
    <div id="main-content">
        
        <!-- Topbar -->
        <div class="admin-topbar shadow-sm">
            <div class="d-flex align-items-center">
                <?php if ($is_logged_in): ?>
                    <button class="btn-toggle-sidebar me-3" onclick="toggleSidebar()">
                        <i class="fas fa-bars"></i>
                    </button>
                <?php endif; ?>
                <h5 class="mb-0 fw-bold text-secondary"><?php echo $page_title ?? 'Yönetim Paneli'; ?></h5>
            </div>
            
            <div class="d-flex align-items-center">
                <a href="../index.php" target="_blank" class="btn btn-sm btn-outline-secondary me-3 d-none d-md-inline-block"><i class="fas fa-external-link-alt me-1"></i> Siteyi Gör</a>
                
                <?php if ($is_logged_in): ?>
                <div class="dropdown">
                    <div class="d-flex align-items-center" style="cursor: pointer;" data-bs-toggle="dropdown">
                        <div class="text-end me-2 d-none d-sm-block">
                            <div class="fw-bold small"><?php echo escape_html($_SESSION['admin_username'] ?? 'Admin'); ?></div>
                            <div class="text-muted" style="font-size: 0.7rem;"><?php echo isSuperAdmin() ? 'Süper Admin' : 'Editör'; ?></div>
                        </div>
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['admin_username'] ?? 'A'); ?>&background=1F3C88&color=fff" class="rounded-circle shadow-sm" width="35" height="35">
                    </div>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                        <li><a class="dropdown-item small py-2" href="logout.php"><i class="fas fa-sign-out-alt me-2 text-danger"></i> Çıkış Yap</a></li>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Bildirimler -->
        <div class="container-fluid px-0">
            <?php 
            if (function_exists('get_admin_flash_messages')) {
                $msgs = get_admin_flash_messages();
                foreach ($msgs as $msg) {
                    $cls = ($msg['type'] === 'error') ? 'alert-danger' : 'alert-success';
                    echo '<div class="alert ' . $cls . ' alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">';
                    echo escape_html($msg['message']);
                    echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
                    echo '</div>';
                }
            }
            ?>
        </div>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const content = document.getElementById('main-content');
    const overlay = document.getElementById('sidebarOverlay');
    if (!sidebar) return;

    if (window.innerWidth > 992) {
        sidebar.classList.toggle('collapsed');
        content.classList.toggle('full-width');
    } else {
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
        document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : 'auto';
    }
}
</script>