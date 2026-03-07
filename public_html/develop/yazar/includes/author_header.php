<?php
// yazar/includes/author_header.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isset($_SESSION['yazar_id'])) { redirect('yazar/login.php'); }

$yid = $_SESSION['yazar_id'];
$yazar_adi = $_SESSION['yazar_name'] ?? 'Yazar';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Yazar Paneli'; ?> | DenemeAGS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #1F3C88; --coral: #FF6F61; --bg: #f4f7fa; --sidebar-w: 260px; }
        body { background: var(--bg); font-family: 'Inter', sans-serif; color: #333; }
        
        /* Sidebar */
        .sidebar { background: var(--primary); height: 100vh; position: fixed; width: var(--sidebar-w); padding: 30px 0; color: #fff; z-index: 1000; transition: 0.3s; }
        .main-panel { margin-left: var(--sidebar-w); padding: 40px; transition: 0.3s; }
        
        .nav-link { color: rgba(255,255,255,0.65); padding: 12px 25px; font-weight: 500; transition: 0.2s; display: flex; align-items: center; text-decoration: none; border-left: 4px solid transparent; margin-bottom: 4px; }
        .nav-link i { width: 25px; font-size: 1.1rem; margin-right: 10px; }
        .nav-link:hover { color: #fff; background: rgba(255,255,255,0.05); }
        .nav-link.active { color: #fff; background: rgba(255,255,255,0.1); border-left-color: var(--coral); font-weight: 700; }
        
        .sidebar-heading { padding: 0 25px 10px; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px; color: rgba(255,255,255,0.4); font-weight: 800; }
        .stat-card { border: none; border-radius: 20px; background: #fff; box-shadow: 0 10px 30px rgba(0,0,0,0.03); padding: 25px; height: 100%; border: 1px solid #eee; }
        
        @media (max-width: 992px) {
            .sidebar { margin-left: calc(-1 * var(--sidebar-w)); }
            .sidebar.active { margin-left: 0; }
            .main-panel { margin-left: 0; padding: 20px; }
        }
    </style>
</head>
<body>

<div class="sidebar shadow-lg" id="sidebar">
    <div class="px-4 mb-5 text-center">
        <h4 class="fw-black mb-0 text-white">DENEME<span class="text-warning">AGS</span></h4>
        <div class="badge bg-warning text-dark rounded-pill px-3 mt-1 small fw-bold" style="font-size: 0.65rem;">YAZAR MERKEZİ</div>
    </div>
    
    <div class="sidebar-heading">Yönetim</div>
    <nav class="nav flex-column">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php"><i class="fas fa-th-large"></i> Genel Bakış</a>
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_products.php' ? 'active' : ''; ?>" href="manage_products.php"><i class="fas fa-book"></i> Yayınlarım</a>
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'analytics.php' ? 'active' : ''; ?>" href="analytics.php"><i class="fas fa-chart-pie"></i> Satış Analizi</a>
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_codes.php' ? 'active' : ''; ?>" href="manage_codes.php">
    <i class="fas fa-key"></i> Kod Yönetimi
</a>
    </nav>

    <div class="sidebar-heading mt-4">Finans & Profil</div>
    <nav class="nav flex-column">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'earnings.php' ? 'active' : ''; ?>" href="earnings.php"><i class="fas fa-wallet"></i> Ödemeler</a>
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>" href="profile.php"><i class="fas fa-user-cog"></i> Profil Ayarları</a>
        <a class="nav-link" href="../yazar.php?id=<?php echo $yid; ?>" target="_blank"><i class="fas fa-store"></i> Mağazamı Gör</a>
    </nav>

    <nav class="nav flex-column mt-5">
        <a class="nav-link text-danger" href="logout.php"><i class="fas fa-sign-out-alt"></i> Güvenli Çıkış</a>
    </nav>
</div>

<div class="main-panel">
    <!-- Mobil Header -->
    <div class="d-lg-none d-flex justify-content-between align-items-center mb-4 bg-white p-3 rounded-4 shadow-sm">
        <h5 class="mb-0 fw-bold">DenemeAGS</h5>
        <button class="btn btn-primary" onclick="document.getElementById('sidebar').classList.toggle('active')">
            <i class="fas fa-bars"></i>
        </button>
    </div>