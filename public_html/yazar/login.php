<?php
// yazar/login.php - Geliştirilmiş UI
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

if (isset($_SESSION['yazar_id'])) { redirect('yazar/dashboard.php'); }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Lütfen tüm alanları doldurun.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM yazarlar WHERE email = ? AND aktif_mi = 1");
        $stmt->execute([$email]);
        $yazar = $stmt->fetch();

        if ($yazar && password_verify($password, $yazar['sifre_hash'])) {
            $_SESSION['yazar_id'] = $yazar['id'];
            $_SESSION['yazar_name'] = $yazar['ad_soyad'];
            redirect('dashboard.php');
        } else {
            $error = "E-posta/Şifre hatalı veya hesabınız henüz onaylanmadı.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Yazar Girişi | DenemeAGS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #1F3C88; font-family: 'Inter', sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .login-card { width: 100%; max-width: 400px; background: #fff; border-radius: 30px; padding: 40px; box-shadow: 0 25px 50px rgba(0,0,0,0.2); }
        .btn-primary { background: #FF6F61; border: none; padding: 12px; font-weight: 700; border-radius: 12px; }
        .btn-primary:hover { background: #e65a50; }
        .input-theme { border-radius: 12px; padding: 12px; border: 1px solid #ddd; background: #f9f9f9; }
    </style>
</head>
<body>
    <div class="login-card fade-in">
        <div class="text-center mb-5">
            <h2 class="fw-black text-dark mb-1">YAZAR<span style="color:#FF6F61">PANELİ</span></h2>
            <p class="text-muted small">Eğitim içeriklerini yönetmeye başla.</p>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-danger py-2 small border-0 rounded-3 text-center mb-4"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label small fw-bold text-muted">E-POSTA ADRESİ</label>
                <input type="email" name="email" class="form-control input-theme" placeholder="yazar@denemeags.com" required>
            </div>
            <div class="mb-4">
                <label class="form-label small fw-bold text-muted">ŞİFRE</label>
                <input type="password" name="password" class="form-control input-theme" placeholder="******" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 shadow-lg">GİRİŞ YAP</button>
        </form>
        
        <div class="text-center mt-5">
            <a href="../index.php" class="text-decoration-none small text-muted"><i class="fas fa-arrow-left me-1"></i> Sitedeki Mağazaya Dön</a>
        </div>
    </div>
</body>
</html>