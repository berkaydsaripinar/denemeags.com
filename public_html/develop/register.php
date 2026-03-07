<?php
// register.php (Kayıt Kodu Zorunluluğu Kaldırılmış, Ad-Soyad Ayrılmış, Telefon Numarası Eklenmiş Sürüm)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

// Kullanıcı zaten giriş yapmışsa panele yönlendir
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$page_title = "Üye Ol";
$csrf_token = generate_csrf_token();

// Form verilerini geri yüklemek için değişkenler
$ad_val = '';
$soyad_val = '';
$email_val = '';
$telefon_val = '';

if (isset($_SESSION['form_data_register'])) {
    $form_data = $_SESSION['form_data_register'];
    $ad_val = $form_data['ad'] ?? '';
    $soyad_val = $form_data['soyad'] ?? '';
    $email_val = $form_data['email'] ?? '';
    $telefon_val = $form_data['telefon'] ?? '';
    unset($_SESSION['form_data_register']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        set_flash_message('error', 'Geçersiz istek. Lütfen tekrar deneyin.');
        $_SESSION['form_data_register'] = $_POST;
        redirect('register.php');
    }

    $ad_val = trim($_POST['ad'] ?? '');
    $soyad_val = trim($_POST['soyad'] ?? '');
    $email_val = trim($_POST['email'] ?? '');
    $telefon_val = trim($_POST['telefon'] ?? '');
    $sifre = $_POST['sifre'] ?? '';
    $sifre_tekrar = $_POST['sifre_tekrar'] ?? '';
    $sozlesme_onay = isset($_POST['sozlesme_onay']) ? true : false;

    $errors = [];

    // 0. Sözleşme Onayı Kontrolü
    if (!$sozlesme_onay) {
        $errors[] = "Kayıt olabilmek için Kullanıcı Sözleşmesi'ni okuyup kabul etmelisiniz.";
    }

    // 1. Alanların Kontrolü
    if (empty($ad_val)) $errors[] = "Ad alanı boş bırakılamaz.";
    if (empty($soyad_val)) $errors[] = "Soyad alanı boş bırakılamaz.";
    if (empty($email_val)) $errors[] = "E-posta alanı boş bırakılamaz.";
    elseif (!filter_var($email_val, FILTER_VALIDATE_EMAIL)) $errors[] = "Geçersiz e-posta formatı.";
    if (empty($telefon_val)) $errors[] = "Telefon numarası alanı boş bırakılamaz.";
    if (empty($sifre)) $errors[] = "Şifre alanı boş bırakılamaz.";
    elseif (strlen($sifre) < 6) $errors[] = "Şifre en az 6 karakter olmalıdır.";
    if ($sifre !== $sifre_tekrar) $errors[] = "Şifreler eşleşmiyor.";

    // 2. E-posta Benzersizlik Kontrolü
    if (empty($errors) && !empty($email_val)) {
        try {
            $stmt_check_email = $pdo->prepare("SELECT id FROM kullanicilar WHERE email = ?");
            $stmt_check_email->execute([$email_val]);
            if ($stmt_check_email->fetch()) {
                $errors[] = "Bu e-posta adresi zaten kayıtlı. Lütfen farklı bir e-posta deneyin veya giriş yapın.";
            }
        } catch (PDOException $e) {
            error_log("E-posta kontrol hatası: " . $e->getMessage());
            $errors[] = "Kayıt sırasında bir sorun oluştu.";
        }
    }

    // 3. Kayıt İşlemi
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Ad ve Soyadı birleştirerek DB'ye kaydet
            $ad_soyad_full = mb_convert_case($ad_val . ' ' . $soyad_val, MB_CASE_TITLE, "UTF-8");
            $sifre_hash = password_hash($sifre, PASSWORD_DEFAULT);
            
            // Veritabanında 'telefon' sütunu olduğu varsayılmaktadır
            $stmt_user = $pdo->prepare("INSERT INTO kullanicilar (ad_soyad, email, telefon, sifre_hash, aktif_mi, kayit_tarihi) VALUES (?, ?, ?, ?, 1, NOW())");
            $stmt_user->execute([$ad_soyad_full, $email_val, $telefon_val, $sifre_hash]);
            $user_id = $pdo->lastInsertId();

            $pdo->commit();
            
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_ad_soyad'] = $ad_soyad_full;
            $_SESSION['user_email'] = $email_val;
            
            set_flash_message('success', 'Kaydınız başarıyla oluşturuldu! Hoş geldiniz.');
            redirect('dashboard.php');

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
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

                        <!-- Ad ve Soyad Alanları -->
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label for="ad" class="form-label text-theme-secondary small fw-bold">ADINIZ</label>
                                <input type="text" class="form-control input-theme" id="ad" name="ad" value="<?php echo escape_html($ad_val); ?>" placeholder="Örn: Ahmet" required>
                            </div>
                            <div class="col-md-6">
                                <label for="soyad" class="form-label text-theme-secondary small fw-bold">SOYADINIZ</label>
                                <input type="text" class="form-control input-theme" id="soyad" name="soyad" value="<?php echo escape_html($soyad_val); ?>" placeholder="Örn: Yılmaz" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label text-theme-secondary small fw-bold">E-POSTA ADRESİNİZ</label>
                            <input type="email" class="form-control input-theme" id="email" name="email" value="<?php echo escape_html($email_val); ?>" placeholder="ornek@mail.com" required>
                        </div>

                        <div class="mb-3">
                            <label for="telefon" class="form-label text-theme-secondary small fw-bold">TELEFON NUMARANIZ</label>
                            <input type="tel" class="form-control input-theme" id="telefon" name="telefon" value="<?php echo escape_html($telefon_val); ?>" placeholder="05XX XXX XX XX" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="sifre" class="form-label text-theme-secondary small fw-bold">ŞİFRE BELİRLEYİN</label>
                            <input type="password" class="form-control input-theme" id="sifre" name="sifre" placeholder="••••••" required>
                            <div class="form-text text-muted small">En az 6 karakter olmalıdır.</div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="sifre_tekrar" class="form-label text-theme-secondary small fw-bold">ŞİFREYİ TEKRARLAYIN</label>
                            <input type="password" class="form-control input-theme" id="sifre_tekrar" name="sifre_tekrar" placeholder="••••••" required>
                        </div>

                        <!-- Sözleşme Onay Kutusu -->
                        <div class="form-check mb-4">
                            <input class="form-check-input form-check-input-theme" type="checkbox" name="sozlesme_onay" id="sozlesme_onay" value="1" required>
                            <label class="form-check-label small text-theme-secondary" for="sozlesme_onay">
                                <a href="sozlesme.php" target="_blank" class="text-theme-primary text-decoration-underline fw-bold">Kullanıcı Sözleşmesi ve Gizlilik Politikası</a>'nı okudum ve kabul ediyorum.
                            </label>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-theme-primary btn-lg fw-bold">KAYDI TAMAMLA</button>
                        </div>
                    </form>
                    <hr class="my-4">
                    <p class="text-center text-theme-dark">Zaten üye misiniz? <a href="index.php" class="fw-bold text-theme-primary text-decoration-none">Giriş Yapın</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include_once __DIR__ . '/templates/footer.php'; 
?>