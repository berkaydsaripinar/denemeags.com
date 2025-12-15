<?php
// register.php (Modern Tema, Çok Kullanımlık Kod Desteği ve Sözleşme Onayı ile)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

// Kullanıcı zaten giriş yapmışsa panele yönlendir
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$page_title = "Kayıt Kodu ile Üye Ol";
$csrf_token = generate_csrf_token();

// Form verilerini geri yüklemek için değişkenler
$kayit_kodu_val = '';
$ad_soyad_val = '';
$email_val = '';

if (isset($_SESSION['form_data_register'])) {
    $form_data = $_SESSION['form_data_register'];
    $kayit_kodu_val = $form_data['kayit_kodu'] ?? '';
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

    $kayit_kodu_val = trim($_POST['kayit_kodu'] ?? '');
    $ad_soyad_val = trim($_POST['ad_soyad'] ?? '');
    $email_val = trim($_POST['email'] ?? '');
    $sifre = $_POST['sifre'] ?? '';
    $sifre_tekrar = $_POST['sifre_tekrar'] ?? '';
    // YENİ: Sözleşme onayı kontrolü
    $sozlesme_onay = isset($_POST['sozlesme_onay']) ? true : false;

    $errors = [];
    $code_data = null;

    // 0. Sözleşme Onayı Kontrolü
    if (!$sozlesme_onay) {
        $errors[] = "Kayıt olabilmek için Kullanıcı Sözleşmesi'ni okuyup kabul etmelisiniz.";
    }

    // 1. Kayıt Kodu Kontrolü
    if (empty($kayit_kodu_val)) {
        $errors[] = "Kayıt kodu boş bırakılamaz.";
    } else {
        $stmt_code = $pdo->prepare("SELECT id, kullanici_id, cok_kullanimlik FROM kayit_kodlari WHERE kod = ?");
        $stmt_code->execute([$kayit_kodu_val]);
        $code_data = $stmt_code->fetch();

        if (!$code_data) {
            $errors[] = "Geçersiz kayıt kodu.";
        } elseif ($code_data['cok_kullanimlik'] == 0 && $code_data['kullanici_id'] !== null) {
            $errors[] = "Bu kayıt kodu daha önce kullanılmış.";
        }
    }

    // 2. Diğer Alanların Kontrolü
    if (empty($ad_soyad_val)) $errors[] = "Ad Soyad boş bırakılamaz.";
    if (empty($email_val)) $errors[] = "E-posta alanı boş bırakılamaz.";
    elseif (!filter_var($email_val, FILTER_VALIDATE_EMAIL)) $errors[] = "Geçersiz e-posta formatı.";
    if (empty($sifre)) $errors[] = "Şifre alanı boş bırakılamaz.";
    elseif (strlen($sifre) < 6) $errors[] = "Şifre en az 6 karakter olmalıdır.";
    if ($sifre !== $sifre_tekrar) $errors[] = "Şifreler eşleşmiyor.";

    // 3. E-posta Benzersizlik Kontrolü
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

    // 4. Kayıt İşlemi
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Kullanıcıyı oluştur
            $sifre_hash = password_hash($sifre, PASSWORD_DEFAULT);
            $stmt_user = $pdo->prepare("INSERT INTO kullanicilar (ad_soyad, email, sifre_hash, aktif_mi, kayit_tarihi) VALUES (?, ?, ?, 1, NOW())");
            $stmt_user->execute([$ad_soyad_val, $email_val, $sifre_hash]);
            $user_id = $pdo->lastInsertId();

            if ($code_data['cok_kullanimlik'] == 0) {
                $stmt_update_code = $pdo->prepare("UPDATE kayit_kodlari SET kullanici_id = ?, kullanilma_tarihi = NOW() WHERE id = ?");
                $stmt_update_code->execute([$user_id, $code_data['id']]);
            }

            $pdo->commit();
            
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_ad_soyad'] = $ad_soyad_val;
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
                        Kayıt Kodu ile Üye Ol
                    </h2>
                    <p class="text-center text-theme-secondary mb-4 small">Size verilen kayıt kodunu kullanarak üyeliğinizi başlatın.</p>

                    <form action="register.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        
                        <div class="mb-3">
                            <label for="kayit_kodu" class="form-label text-theme-primary fw-bold">Kayıt Kodu:</label>
                            <input type="text" class="form-control form-control-lg input-theme text-uppercase" id="kayit_kodu" name="kayit_kodu" value="<?php echo escape_html($kayit_kodu_val); ?>" placeholder="KODU BURAYA GİRİN" required>
                            <div class="form-text text-muted small">Kitabınızda, e-postanızda veya size iletilen belgede yer alan kodu giriniz.</div>
                        </div>

                        <hr class="my-4" style="border-color: #dee2e6;">

                        <div class="mb-3">
                            <label for="ad_soyad" class="form-label text-theme-secondary">Ad Soyad:</label>
                            <input type="text" class="form-control input-theme" id="ad_soyad" name="ad_soyad" value="<?php echo escape_html($ad_soyad_val); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label text-theme-secondary">E-posta:</label>
                            <input type="email" class="form-control input-theme" id="email" name="email" value="<?php echo escape_html($email_val); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="sifre" class="form-label text-theme-secondary">Şifre:</label>
                            <input type="password" class="form-control input-theme" id="sifre" name="sifre" required>
                            <div class="form-text text-muted small">En az 6 karakter.</div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="sifre_tekrar" class="form-label text-theme-secondary">Şifre Tekrar:</label>
                            <input type="password" class="form-control input-theme" id="sifre_tekrar" name="sifre_tekrar" required>
                        </div>

                        <!-- SÖZLEŞME ONAY KUTUSU -->
                        <div class="form-check mb-4">
                            <!-- DÜZELTME: input-theme sınıfı yerine form-check-input kullanıldı -->
                            <input class="form-check-input form-check-input-theme" type="checkbox" name="sozlesme_onay" id="sozlesme_onay" value="1" required>
                            <label class="form-check-label small text-theme-secondary" for="sozlesme_onay">
                                <a href="sozlesme.php" target="_blank" class="text-theme-primary text-decoration-underline fw-bold">Kullanıcı Sözleşmesi ve Gizlilik Politikası</a>'nı okudum ve kabul ediyorum.
                            </label>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-theme-primary btn-lg">Kaydı Tamamla</button>
                        </div>
                    </form>
                    <hr class="my-4">
                    <p class="text-center text-theme-dark">Zaten üye misiniz? <a href="index.php" class="fw-bold text-theme-primary">Giriş Yapın</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include_once __DIR__ . '/templates/footer.php'; 
?>