<?php
// yazar/manage_products.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['yazar_id'])) {
    header("Location: login.php");
    exit;
}

$yazar_id = $_SESSION['yazar_id'];

// Yayınları Listele
try {
    $stmt = $pdo->prepare("SELECT * FROM denemeler WHERE yazar_id = ? ORDER BY id DESC");
    $stmt->execute([$yazar_id]);
    $products = $stmt->fetchAll();
} catch (PDOException $e) { $products = []; }

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Yayınlarım | DenemeAGS Yazar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #1F3C88; --accent: #F57C00; }
        body { background: #f8f9fa; font-family: 'Open Sans', sans-serif; }
        .sidebar { background: var(--primary); height: 100vh; color: white; position: fixed; width: 240px; }
        .content { margin-left: 240px; padding: 40px; }
        .nav-link { color: rgba(255,255,255,0.7); padding: 12px 25px; transition: 0.3s; }
        .nav-link:hover, .nav-link.active { color: white; background: rgba(255,255,255,0.1); border-left: 4px solid var(--accent); }
        .product-card { border: none; border-radius: 12px; transition: 0.3s; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .product-card:hover { transform: translateY(-5px); }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="p-4 text-center border-bottom border-white border-opacity-10 mb-3">
            <h4 class="fw-bold mb-0">DENEME<span class="text-warning">AGS</span></h4>
            <small class="opacity-50 text-uppercase">Yazar Paneli</small>
        </div>
        <nav class="nav flex-column">
            <a class="nav-link" href="dashboard.php"><i class="fas fa-chart-line me-2"></i> Genel Bakış</a>
            <a class="nav-link active" href="manage_products.php"><i class="fas fa-book me-2"></i> Yayınlarım</a>
            <a class="nav-link" href="earnings.php"><i class="fas fa-wallet me-2"></i> Ödemeler</a>
            <a class="nav-link" href="profile.php"><i class="fas fa-user-circle me-2"></i> Profilim</a>
            <a class="nav-link text-danger mt-5" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Çıkış Yap</a>
        </nav>
    </div>

    <div class="content">
        <div class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <h3 class="fw-bold mb-0">Yayınlarım</h3>
                <p class="text-muted">Yüklediğiniz tüm deneme ve kaynakları buradan yönetin.</p>
            </div>
            <a href="add_product.php" class="btn btn-warning fw-bold"><i class="fas fa-plus me-2"></i> Yeni Yayın Ekle</a>
        </div>

        <div class="row g-4">
            <?php if(empty($products)): ?>
                <div class="col-12 text-center py-5">
                    <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                    <p class="text-muted">Henüz bir yayın yüklemediniz.</p>
                </div>
            <?php else: ?>
                <?php foreach($products as $p): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card product-card h-100">
                        <div class="card-body p-4 d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <span class="badge <?php echo $p['aktif_mi'] ? 'bg-success' : 'bg-warning text-dark'; ?> rounded-pill px-3">
                                    <?php echo $p['aktif_mi'] ? 'Yayında' : 'Onay Bekliyor'; ?>
                                </span>
                                <span class="text-muted small">ID: #<?php echo $p['id']; ?></span>
                            </div>
                            <h5 class="fw-bold mb-2"><?php echo escape_html($p['deneme_adi']); ?></h5>
                            <p class="text-muted small flex-grow-1"><?php echo substr(escape_html($p['kisa_aciklama']), 0, 100); ?>...</p>
                            <hr>
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="small fw-bold text-primary"><?php echo number_format($p['fiyat'], 2); ?> ₺</div>
                                <div class="btn-group">
                                    <a href="edit_product.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-edit"></i></a>
                                    <a href="#" class="btn btn-sm btn-outline-info" title="İstatistikler"><i class="fas fa-chart-bar"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>