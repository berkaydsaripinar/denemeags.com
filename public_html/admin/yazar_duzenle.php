<?php
/**
 * admin/yazar_duzenle.php
 * Yönetim Paneli - Yazar Bilgisi Güncelleme
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

$id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
$status = "";

if (!$id) {
    header('Location: yazarlar.php');
    exit;
}

// Mevcut veriyi çek
$stmt = $pdo->prepare("SELECT * FROM yazarlar WHERE id = ?");
$stmt->execute([$id]);
$yazar = $stmt->fetch();

if (!$yazar) {
    die("Yazar kaydı bulunamadı.");
}

// Güncelleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ad_soyad = trim($_POST['ad_soyad'] ?? '');
    $telefon  = trim($_POST['telefon'] ?? '');

    if (!empty($ad_soyad)) {
        try {
            $sql = "UPDATE yazarlar SET ad_soyad = ?, telefon = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$ad_soyad, $telefon, $id]);
            $status = "success";
            
            // Güncel veriyi tekrar çek
            $yazar['ad_soyad'] = $ad_soyad;
            $yazar['telefon'] = $telefon;
        } catch (PDOException $e) {
            $status = "error";
            $error_msg = $e->getMessage();
        }
    }
}

include_once __DIR__ . '/includes/admin_header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Yazar Düzenle</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="yazarlar.php">Yazarlar</a></li>
        <li class="breadcrumb-item active">Bilgileri Güncelle</li>
    </ol>

    <div class="row">
        <div class="col-xl-8 col-lg-10">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex align-items-center justify-content-between bg-dark text-white">
                    <h6 class="m-0 font-weight-bold"><i class="fas fa-edit me-2"></i>Yazar Düzenle: <?php echo htmlspecialchars($yazar['ad_soyad']); ?></h6>
                    <span class="badge bg-light text-dark">ID: #<?php echo $id; ?></span>
                </div>
                <div class="card-body">
                    
                    <?php if ($status === "success"): ?>
                        <div class="alert alert-info border-left-info shadow-sm">Bilgiler başarıyla güncellendi.</div>
                    <?php elseif ($status === "error"): ?>
                        <div class="alert alert-danger border-left-danger">Güncelleme hatası: <?php echo $error_msg; ?></div>
                    <?php endif; ?>

                    <form action="yazar_duzenle.php?id=<?php echo $id; ?>" method="POST">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Yazar Adı Soyadı</label>
                                <input type="text" name="ad_soyad" class="form-control" 
                                       value="<?php echo htmlspecialchars($yazar['ad_soyad']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Telefon Numarası</label>
                                <input type="text" name="telefon" class="form-control" 
                                       value="<?php echo htmlspecialchars($yazar['telefon'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-top">
                            <div class="d-flex align-items-center justify-content-between">
                                <small class="text-muted italic">Son güncelleme: <?php echo date("d.m.Y H:i"); ?></small>
                                <div>
                                    <a href="yazarlar.php" class="btn btn-outline-secondary me-2">Geri Dön</a>
                                    <button type="submit" class="btn btn-success px-5">
                                        <i class="fas fa-check-circle me-1"></i> Güncelle
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/includes/admin_footer.php'; ?>