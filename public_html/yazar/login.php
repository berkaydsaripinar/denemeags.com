<?php
// yazar/login.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

if (isset($_SESSION['yazar_id'])) {
    header("Location: dashboard.php");
    exit;
}

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
            
            $pdo->prepare("UPDATE yazarlar SET son_giris = NOW() WHERE id = ?")->execute([$yazar['id']]);
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "E-posta veya şifre hatalı, ya da hesabınız henüz onaylanmadı.";
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
        body { background: #f4f7fa; font-family: 'Inter', sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; }
        .login-card { width: 100%; max-width: 400px; border: none; border-radius: 20px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); }
        .btn-primary { background: #1F3C88; border: none; padding: 12px; font-weight: 600; }
    </style>
</head>
<body>
    <div class="card login-card p-4">
        <div class="text-center mb-4">
            <h3 class="fw-bold text-primary"><i class="fas fa-feather-alt me-2"></i>Yazar Paneli</h3>
            <p class="text-muted small">Lütfen yazar bilgilerinizle giriş yapın.</p>
        </div>
        <?php if($error): ?>
            <div class="alert alert-danger py-2 small"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label small fw-bold">E-POSTA</label>
                <input type="email" name="email" class="form-control" placeholder="ornek@yazar.com" required>
            </div>
            <div class="mb-4">
                <label class="form-label small fw-bold">ŞİFRE</label>
                <input type="password" name="password" class="form-control" placeholder="******" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 rounded-3">GİRİŞ YAP</button>
        </form>
        <div class="text-center mt-4">
            <a href="../index.php" class="text-decoration-none small text-muted"><i class="fas fa-arrow-left me-1"></i> Siteye Dön</a>
        </div>
    </div>
</body>
</html>