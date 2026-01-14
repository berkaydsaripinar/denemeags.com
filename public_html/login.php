<?php
$page_title = "Giriş Yap";
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

// Zaten giriş yapmışsa yönlendir
if (isset($_SESSION['user_id'])) {
    redirect('index.php');
}

$email = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['sifre'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Lütfen e-posta ve şifrenizi giriniz.";
    } else {
        // Kullanıcıyı veritabanında ara
        $stmt = $pdo->prepare("SELECT * FROM kullanicilar WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['sifre_hash'])) {
            // Giriş Başarılı
            if ($user['aktif_mi'] == 0) {
                $error = "Hesabınız pasif durumdadır. Lütfen yönetici ile iletişime geçin.";
            } else {
                // Session (Oturum) Başlat
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['ad_soyad'];
                
                // Eğer admin ise rol ata (Basit mantık: ID=1 veya özel bir sütun)
                // Şimdilik herkesi standart kullanıcı varsayıyoruz, ileride rol eklenebilir.
                $_SESSION['user_role'] = 'student'; 

                set_flash_message('success', 'Tekrar hoş geldiniz, ' . $user['ad_soyad']);
                
                // Yönlendirme: Varsa Dashboard'a, yoksa Ana Sayfaya
                redirect('index.php'); 
            }
        } else {
            // Güvenlik gereği "Şifre yanlış" veya "Email yok" demek yerine genel hata veriyoruz
            $error = "E-posta adresi veya şifre hatalı.";
        }
    }
}

require_once __DIR__ . '/templates/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5 col-lg-4">
            
            <div class="card shadow-lg border-0 rounded-4">
                <div class="card-body p-5">
                    
                    <div class="text-center mb-4">
                        <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                            <i class="fas fa-sign-in-alt fa-2x" style="color: #1F3C88;"></i>
                        </div>
                        <h2 class="fw-bold text-dark">Giriş Yap</h2>
                        <p class="text-muted small">Eğitim materyallerinize erişin.</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger text-center small py-2">
                            <i class="fas fa-exclamation-circle me-1"></i> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <form action="login.php" method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">E-POSTA</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fas fa-envelope text-muted"></i></span>
                                <input type="email" name="email" class="form-control bg-light border-start-0" placeholder="ornek@email.com" value="<?php echo escape_html($email); ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <label class="form-label fw-bold small text-muted">ŞİFRE</label>
                                <a href="forgot_password.php" class="small text-decoration-none" style="color: #F57C00;">Şifremi Unuttum?</a>
                            </div>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fas fa-lock text-muted"></i></span>
                                <input type="password" name="sifre" class="form-control bg-light border-start-0" placeholder="******" required>
                            </div>
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="rememberMe">
                            <label class="form-check-label small text-muted" for="rememberMe">Beni Hatırla</label>
                        </div>

                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary py-2 fw-bold shadow-sm" style="background-color: #1F3C88; border: none;">
                                Giriş Yap <i class="fas fa-arrow-right ms-2"></i>
                            </button>
                        </div>
                    </form>

                    <hr class="my-4 text-muted opacity-25">

                    <div class="text-center small">
                        Hesabınız yok mu? 
                        <a href="register.php" class="text-decoration-none fw-bold" style="color: #F57C00;">
                            Hemen Kayıt Ol
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>