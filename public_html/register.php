<?php
// register.php (E-posta ve Şifre ile Kayıt - Modern Dashboard Teması ile)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$page_title = "Yeni Hesap Oluştur";
$csrf_token = generate_csrf_token();

$ad_soyad_val = '';
$email_val = '';
if (isset($_SESSION['form_data_register'])) {
    $form_data = $_SESSION['form_data_register'];
    $ad_soyad_val = $form_data['ad_soyad'] ?? '';
    $email_val = $form_data['email'] ?? '';
    unset($_SESSION['form_data_register']);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        set_flash_message('error', 'Geçersiz istek. Lütfen tekrar deneyin.');
        $_SESSION['form_data_register'] = $_POST;
        redirect('register.php');
    }

    $ad_soyad_val = trim($_POST['ad_soyad'] ?? '');
    $email_val = trim($_POST['email'] ?? '');
    $sifre = $_POST['sifre'] ?? '';
    $sifre_tekrar = $_POST['sifre_tekrar'] ?? '';

    $errors = [];
    if (empty($ad_soyad_val)) {
        $errors[] = "Ad Soyad alanı boş bırakılamaz.";
    }
    if (empty($email_val)) {
        $errors[] = "E-posta alanı boş bırakılamaz.";
    } elseif (!filter_var($email_val, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Geçersiz e-posta formatı.";
    }
    if (empty($sifre)) {
        $errors[] = "Şifre alanı boş bırakılamaz.";
    } elseif (strlen($sifre) < 6) {
        $errors[] = "Şifre en az 6 karakter olmalıdır.";
    }
    if ($sifre !== $sifre_tekrar) {
        $errors[] = "Şifreler eşleşmiyor.";
    }

    if (empty($errors) && !empty($email_val)) {
        try {
            $stmt_check_email = $pdo->prepare("SELECT id FROM kullanicilar WHERE email = ?");
            $stmt_check_email->execute([$email_val]);
            if ($stmt_check_email->fetch()) {
                $errors[] = "Bu e-posta adresi zaten kayıtlı. Lütfen farklı bir e-posta deneyin veya giriş yapın.";
            }
        } catch (PDOException $e) {
            error_log("E-posta kontrol hatası: " . $e->getMessage());
            $errors[] = "Kayıt sırasında bir sorun oluştu. Lütfen tekrar deneyin.";
        }
    }

    if (empty($errors)) {
        try {
            $sifre_hash = password_hash($sifre, PASSWORD_DEFAULT);

            $stmt_user = $pdo->prepare("INSERT INTO kullanicilar (ad_soyad, email, sifre_hash, aktif_mi) VALUES (?, ?, ?, 1)"); // aktif_mi eklendi
            $stmt_user->execute([$ad_soyad_val, $email_val, $sifre_hash]);
            $user_id = $pdo->lastInsertId();
            
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_ad_soyad'] = $ad_soyad_val;
            $_SESSION['user_email'] = $email_val; 
            
            set_flash_message('success', 'Kayıt başarılı! Platforma hoş geldiniz, ' . escape_html($ad_soyad_val) . '.');
            redirect('dashboard.php');

        } catch (PDOException $e) {
            error_log("Kayıt hatası: " . $e->getMessage());
            set_flash_message('error', 'Kayıt sırasında bir veritabanı sorunu oluştu. Lütfen tekrar deneyin.');
            $_SESSION['form_data_register'] = $_POST;
            redirect('register.php');
        }
    } else {
        foreach ($errors as $error) {
            set_flash_message('error', $error);
        }
        $_SESSION['form_data_register'] = $_POST;
        redirect('register.php');
    }
}

include_once __DIR__ . '/templates/header.php'; 
?>

<div class="container py-5"> 
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6 col-xl-5"> 
            <div class="card shadow-lg border-0 card-theme"> 
                <div class="card-body p-4 p-md-5"> 
                    <h2 class="card-title text-center text-theme-primary mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="currentColor" class="bi bi-person-plus-fill me-2" viewBox="0 0 16 16">
                            <path d="M1 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6"/>
                            <path fill-rule="evenodd" d="M13.5 5a.5.5 0 0 1 .5.5V7h1.5a.5.5 0 0 1 0 1H14v1.5a.5.5 0 0 1-1 0V8h-1.5a.5.5 0 0 1 0-1H13V5.5a.5.5 0 0 1 .5-.5"/>
                        </svg>
                        Yeni Hesap Oluştur
                    </h2>
                    <p class="text-center text-theme-secondary mb-4 small">Lütfen bilgilerinizi girerek kaydınızı tamamlayın.</p>

                    <form action="register.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        
                        <div class="mb-3">
                            <label for="ad_soyad" class="form-label text-theme-secondary">Ad Soyad:</label>
                            <input type="text" class="form-control form-control-lg input-theme" id="ad_soyad" name="ad_soyad" value="<?php echo escape_html($ad_soyad_val); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label text-theme-secondary">E-posta Adresiniz:</label>
                            <input type="email" class="form-control form-control-lg input-theme" id="email" name="email" value="<?php echo escape_html($email_val); ?>" placeholder="ornek@eposta.com" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="sifre" class="form-label text-theme-secondary">Şifre (En az 6 karakter):</label>
                            <input type="password" class="form-control form-control-lg input-theme" id="sifre" name="sifre" required>
                        </div>
                        
                        <div class="mb-4"> 
                            <label for="sifre_tekrar" class="form-label text-theme-secondary">Şifre Tekrar:</label>
                            <input type="password" class="form-control form-control-lg input-theme" id="sifre_tekrar" name="sifre_tekrar" required>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-theme-primary btn-lg">Kayıt Ol</button>
                        </div>
                    </form>
                    <hr class="my-4">
                    <p class="text-center text-theme-dark">Zaten bir hesabınız var mı? <a href="index.php" class="fw-bold text-theme-primary">Giriş Yapın</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include_once __DIR__ . '/templates/footer.php'; 
?>
