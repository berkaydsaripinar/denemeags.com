<?php
// yazar/dashboard.php - Profesyonel Yazar Yönetim Merkezi
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['yazar_id'])) { redirect('login.php'); }

$yid = $_SESSION['yazar_id'];
$page_title = "Panelim";

try {
    // 1. Finansal Özet
    $stmt_fin = $pdo->prepare("
        SELECT 
            SUM(tutar_brut) as ciro,
            SUM(yazar_payi) as net_hakedis,
            COUNT(id) as satis_sayisi
        FROM satis_loglari WHERE yazar_id = ?
    ");
    $stmt_fin->execute([$yid]);
    $fin = $stmt_fin->fetch();

    // 2. Ürün Durumu
    $total_prods = $pdo->prepare("SELECT COUNT(*) FROM denemeler WHERE yazar_id = ?");
    $total_prods->execute([$yid]);
    $prod_count = $total_prods->fetchColumn();

    // 3. Son İşlemler (SATIŞLAR)
    $stmt_recent = $pdo->prepare("
        SELECT sl.*, d.deneme_adi 
        FROM satis_loglari sl
        JOIN denemeler d ON sl.deneme_id = d.id
        WHERE sl.yazar_id = ? 
        ORDER BY sl.tarih DESC LIMIT 5
    ");
    $stmt_recent->execute([$yid]);
    $recent_sales = $stmt_recent->fetchAll();

} catch (Exception $e) { $recent_sales = []; }

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yazar Paneli | <?php echo escape_html(SITE_NAME); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #1F3C88; --coral: #FF6F61; --bg: #f8faff; }
        body { background: var(--bg); font-family: 'Inter', sans-serif; }
        
        .sidebar { background: var(--primary); height: 100vh; position: fixed; width: 250px; padding: 30px 0; color: #fff; z-index: 1000; }
        .main-panel { margin-left: 250px; padding: 40px; }
        
        .nav-link { color: rgba(255,255,255,0.7); padding: 15px 30px; font-weight: 600; transition: 0.3s; display: flex; align-items: center; }
        .nav-link i { width: 25px; font-size: 1.1rem; }
        .nav-link:hover, .nav-link.active { color: #fff; background: rgba(255,255,255,0.1); border-left: 5px solid var(--coral); }
        
        .stat-card { border: none; border-radius: 20px; padding: 25px; background: #fff; box-shadow: 0 10px 25px rgba(0,0,0,0.03); }
        .stat-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 15px; }
        
        @media (max-width: 992px) { .sidebar { display: none; } .main-panel { margin-left: 0; padding: 20px; } }
    </style>
</head>
<body>

<div class="sidebar shadow-lg">
    <div class="px-4 mb-5 text-center">
        <h4 class="fw-black mb-0">DENEME<span class="text-warning">AGS</span></h4>
        <div class="badge bg-warning text-dark rounded-pill px-3 mt-1 small fw-bold">Yazar Merkezi</div>
    </div>
    <nav class="nav flex-column">
        <a class="nav-link active" href="dashboard.php"><i class="fas fa-th-large"></i> Genel Bakış</a>
        <a class="nav-link" href="manage_products.php"><i class="fas fa-book"></i> Yayınlarım</a>
        <a class="nav-link" href="earnings.php"><i class="fas fa-wallet"></i> Ödemeler</a>
        <a class="nav-link" href="profile.php"><i class="fas fa-user-circle"></i> Profilim</a>
        <a class="nav-link text-danger mt-5" href="logout.php"><i class="fas fa-sign-out-alt"></i> Güvenli Çıkış</a>
    </nav>
</div>

<div class="main-panel">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h2 class="fw-bold text-dark mb-1">Hoş Geldiniz, <?php echo $_SESSION['yazar_name']; ?></h2>
            <p class="text-muted mb-0">Yayınlarınızın son performans durumu aşağıdadır.</p>
        </div>
        <div class="text-end">
            <a href="add_product.php" class="btn btn-theme-primary px-4 py-2 rounded-pill fw-bold shadow">
                <i class="fas fa-plus me-2"></i>Yeni Yayın Yükle
            </a>
        </div>
    </div>

    <!-- İstatistikler -->
    <div class="row g-4 mb-5 text-center">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary mx-auto"><i class="fas fa-shopping-bag"></i></div>
                <div class="text-muted small fw-bold mb-1">TOPLAM SATIŞ</div>
                <div class="h3 fw-black mb-0"><?php echo (int)$fin['satis_sayisi']; ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-success bg-opacity-10 text-success mx-auto"><i class="fas fa-lira-sign"></i></div>
                <div class="text-muted small fw-bold mb-1">BRÜT HASILAT</div>
                <div class="h3 fw-black mb-0"><?php echo number_format((float)$fin['ciro'], 2); ?> ₺</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-info bg-opacity-10 text-info mx-auto"><i class="fas fa-wallet"></i></div>
                <div class="text-muted small fw-bold mb-1">NET HAKEDİŞ</div>
                <div class="h3 fw-black text-success mb-0"><?php echo number_format((float)$fin['net_hakedis'], 2); ?> ₺</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card border-bottom border-coral border-4">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning mx-auto"><i class="fas fa-book-open"></i></div>
                <div class="text-muted small fw-bold mb-1">YAYINLANAN ESER</div>
                <div class="h3 fw-black mb-0"><?php echo (int)$prod_count; ?></div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Son Satışlar -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-history me-2 text-primary"></i>Son Satış Hareketleri</h6>
                    <a href="earnings.php" class="btn btn-sm btn-light rounded-pill px-3">Tümünü Gör</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small">
                                <tr>
                                    <th class="ps-4">Yayın Adı</th>
                                    <th>Brüt Tutar</th>
                                    <th>Hakedişiniz</th>
                                    <th class="pe-4 text-end">İşlem Tarihi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($recent_sales)): ?>
                                    <tr><td colspan="4" class="text-center py-5 text-muted small">Henüz bir satış gerçekleşmedi.</td></tr>
                                <?php else: ?>
                                    <?php foreach($recent_sales as $s): ?>
                                    <tr>
                                        <td class="ps-4 fw-bold small text-dark"><?php echo escape_html($s['deneme_adi']); ?></td>
                                        <td><?php echo number_format($s['tutar_brut'], 2); ?> ₺</td>
                                        <td class="text-success fw-bold"><?php echo number_format($s['yazar_payi'], 2); ?> ₺</td>
                                        <td class="pe-4 text-end small text-muted"><?php echo date('d.m.Y H:i', strtotime($s['tarih'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hızlı Durum -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-4 h-100">
                <h6 class="fw-bold mb-4">Ödeme Bilgilendirme</h6>
                <div class="alert alert-info border-0 rounded-4 small">
                    <i class="fas fa-info-circle me-2"></i>Hakedişleriniz, bakiyeniz 100 ₺ barajını aştığında talep edilebilir hale gelir.
                </div>
                <div class="d-grid mt-4">
                    <a href="earnings.php" class="btn btn-outline-primary py-2 rounded-pill fw-bold">Para Çekme İşlemleri</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>