<?php
/**
 * admin/yazar_ekle.php
 * Yönetim Paneli - Yeni Yazar Kaydı
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

// Oturum kontrolü (Admin yetkisi sorgusu buraya eklenebilir)
// if (!is_admin()) { header('Location: login.php'); exit; }

$page_title = "Admin | Yeni Yazar Ekle";
$status = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ad_soyad = trim($_POST['ad_soyad'] ?? '');
    $telefon  = trim($_POST['telefon'] ?? '');

    if (!empty($ad_soyad)) {
        try {
            $sql = "INSERT INTO yazarlar (ad_soyad, telefon) VALUES (?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$ad_soyad, $telefon]);
            $status = "success";
        } catch (PDOException $e) {
            $status = "error";
            $error_msg = $e->getMessage();
        }
    } else {
        $status = "warning";
    }
}

// Admin Header Include (Menü ve CSS dosyaları)
include_once __DIR__ . '/includes/admin_header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Yazar Yönetimi</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="yazarlar.php">Yazarlar</a></li>
        <li class="breadcrumb-item active">Yeni Ekle</li>
    </ol>

    <div class="row">
        <div class="col-xl-8 col-lg-10">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex align-items-center justify-content-between bg-primary text-white">
                    <h6 class="m-0 font-weight-bold"><i class="fas fa-user-plus me-2"></i>Yeni Yazar Tanımla</h6>
                </div>
                <div class="card-body">
                    
                    <?php if ($status === "success"): ?>
                        <div class="alert alert-success border-left-success">Yazar başarıyla sisteme eklendi. <a href="yazarlar.php" class="alert-link">Listeye dön.</a></div>
                    <?php elseif ($status === "error"): ?>
                        <div class="alert alert-danger border-left-danger">Hata oluştu: <?php echo $error_msg; ?></div>
                    <?php elseif ($status === "warning"): ?>
                        <div class="alert alert-warning border-left-warning">Lütfen ad ve soyad alanını doldurun.</div>
                    <?php endif; ?>

                    <form action="yazar_ekle.php" method="POST">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label small font-weight-bold">YAZAR ADI SOYADI</label>
                                <input type="text" name="ad_soyad" class="form-control" placeholder="Ad Soyad giriniz..." required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small font-weight-bold">İLETİŞİM TELEFONU</label>
                                <input type="text" name="telefon" class="form-control" placeholder="05xx xxx xx xx">
                            </div>
                        </div>

                        <hr>
                        
                        <div class="d-flex justify-content-end mt-4">
                            <a href="yazarlar.php" class="btn btn-secondary me-2">
                                <i class="fas fa-chevron-left me-1"></i> Vazgeç
                            </a>
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="fas fa-save me-1"></i> Yazarı Kaydet
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/includes/admin_footer.php'; ?>