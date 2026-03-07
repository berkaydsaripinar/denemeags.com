<?php
// admin/edit_kullanici.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAdminLogin();
$page_title = "Kullanıcıyı Düzenle";
$csrf_token = generate_admin_csrf_token();

$user_id_to_edit = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
if (!$user_id_to_edit) {
    set_admin_flash_message('error', 'Geçersiz kullanıcı ID.');
    header("Location: view_kullanicilar.php");
    exit;
}

// Kullanıcı bilgilerini çek
try {
    $stmt_user = $pdo->prepare("SELECT id, ad_soyad, email, aktif_mi FROM kullanicilar WHERE id = ?");
    $stmt_user->execute([$user_id_to_edit]);
    $user_data = $stmt_user->fetch();

    if (!$user_data) {
        set_admin_flash_message('error', 'Düzenlenecek kullanıcı bulunamadı.');
        header("Location: view_kullanicilar.php");
        exit;
    }
} catch (PDOException $e) {
    set_admin_flash_message('error', 'Kullanıcı bilgileri alınırken hata: ' . $e->getMessage());
    header("Location: view_kullanicilar.php");
    exit;
}


// Form Gönderimi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_admin_csrf_token($_POST['csrf_token'])) {
        set_admin_flash_message('error', 'Geçersiz CSRF token.');
    } else {
        $action = $_POST['form_action'] ?? '';
        $errors = []; // Yerel hata dizisi (Hata kontrolü için daha güvenli)

        if ($action === 'update_details') {
            $new_ad_soyad = trim($_POST['ad_soyad'] ?? '');
            $new_email = trim($_POST['email'] ?? '');
            $new_aktif_mi = isset($_POST['aktif_mi']) ? 1 : 0;

            if (empty($new_ad_soyad)) {
                $errors[] = 'Ad Soyad alanı boş bırakılamaz.';
            }

            if (empty($new_email)) {
                $errors[] = 'E-posta alanı boş bırakılamaz.';
            } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Geçersiz e-posta formatı.';
            } else {
                // E-posta benzersizlik kontrolü
                $stmt_check_email = $pdo->prepare("SELECT id FROM kullanicilar WHERE email = ? AND id != ?");
                $stmt_check_email->execute([$new_email, $user_id_to_edit]);
                if ($stmt_check_email->fetch()) {
                    $errors[] = 'Bu e-posta adresi başka bir kullanıcı tarafından kullanılıyor.';
                }
            }

            // Eğer hata yoksa güncelle
            if (empty($errors)) {
                try {
                    $stmt_update = $pdo->prepare("UPDATE kullanicilar SET ad_soyad = ?, email = ?, aktif_mi = ? WHERE id = ?");
                    $stmt_update->execute([$new_ad_soyad, $new_email, $new_aktif_mi, $user_id_to_edit]);
                    set_admin_flash_message('success', 'Kullanıcı bilgileri başarıyla güncellendi.');
                    header("Location: edit_kullanici.php?user_id=" . $user_id_to_edit);
                    exit;
                } catch (PDOException $e) {
                    $errors[] = 'Kullanıcı güncellenirken veritabanı hatası oluştu.';
                }
            }
        } elseif ($action === 'reset_password') {
            $new_sifre = $_POST['yeni_sifre'] ?? '';
            $new_sifre_tekrar = $_POST['yeni_sifre_tekrar'] ?? '';

            if (empty($new_sifre) || strlen($new_sifre) < 6) {
                $errors[] = 'Yeni şifre en az 6 karakter olmalıdır.';
            } elseif ($new_sifre !== $new_sifre_tekrar) {
                $errors[] = 'Girdiğiniz şifreler birbiriyle eşleşmiyor.';
            }

            if (empty($errors)) {
                try {
                    $yeni_sifre_hash = password_hash($new_sifre, PASSWORD_DEFAULT);
                    $stmt_reset_pass = $pdo->prepare("UPDATE kullanicilar SET sifre_hash = ? WHERE id = ?");
                    $stmt_reset_pass->execute([$yeni_sifre_hash, $user_id_to_edit]);
                    set_admin_flash_message('success', 'Kullanıcının şifresi başarıyla yenilendi.');
                    header("Location: edit_kullanici.php?user_id=" . $user_id_to_edit);
                    exit;
                } catch (PDOException $e) {
                    $errors[] = 'Şifre sıfırlanırken veritabanı hatası oluştu.';
                }
            }
        }

        // Hataları flash mesaj olarak aktar
        foreach ($errors as $error) {
            set_admin_flash_message('error', $error);
        }
    }
}

include_once __DIR__ . '/../templates/admin_header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col">
        <h3 class="fw-bold mb-0 text-theme-primary">Kullanıcı Düzenleme</h3>
        <p class="text-muted small">Öğrenci bilgilerini güncelleyin veya şifresini sıfırlayın.</p>
    </div>
    <div class="col-auto">
        <a href="view_kullanicilar.php" class="btn btn-light border shadow-sm">
            <i class="fas fa-arrow-left me-2"></i> Listeye Dön
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- SOL: Temel Bilgiler -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="mb-0 fw-bold"><i class="fas fa-user-edit me-2 text-primary"></i>Profil Detayları (ID: <?php echo $user_data['id']; ?>)</h6>
            </div>
            <div class="card-body p-4">
                <form action="edit_kullanici.php?user_id=<?php echo $user_data['id']; ?>" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="form_action" value="update_details">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold">AD SOYAD</label>
                        <input type="text" name="ad_soyad" class="form-control input-theme" value="<?php echo escape_html($user_data['ad_soyad']); ?>" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold">E-POSTA ADRESİ</label>
                        <input type="email" name="email" class="form-control input-theme" value="<?php echo escape_html($user_data['email']); ?>" required>
                    </div>

                    <div class="form-check form-switch p-3 bg-light rounded-3 border mb-4">
                        <input class="form-check-input ms-0 me-3" type="checkbox" name="aktif_mi" value="1" id="aktifSwitch" <?php echo ($user_data['aktif_mi'] == 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-bold" for="aktifSwitch">Kullanıcı Hesabı Aktif</label>
                        <div class="form-text mt-1 small text-muted">İşaretlenmezse kullanıcı sisteme giriş yapamaz.</div>
                    </div>

                    <button type="submit" class="btn btn-theme-primary w-100 py-2 shadow-sm fw-bold">
                        <i class="fas fa-save me-2"></i> BİLGİLERİ GÜNCELLE
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- SAĞ: Şifre ve Detay -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="mb-0 fw-bold text-danger"><i class="fas fa-lock me-2"></i>Şifreyi Değiştir</h6>
            </div>
            <div class="card-body p-4">
                <form action="edit_kullanici.php?user_id=<?php echo $user_data['id']; ?>" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="form_action" value="reset_password">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold">YENİ ŞİFRE</label>
                        <input type="password" name="yeni_sifre" class="form-control input-theme" placeholder="Min. 6 Karakter" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold">ŞİFREYİ TEKRARLAYIN</label>
                        <input type="password" name="yeni_sifre_tekrar" class="form-control input-theme" placeholder="Aynısını tekrar yazın" required>
                    </div>

                    <button type="submit" class="btn btn-danger w-100 py-2 shadow-sm fw-bold">
                        <i class="fas fa-key me-2"></i> ŞİFREYİ SIFIRLA
                    </button>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 bg-primary text-white">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3"><i class="fas fa-info-circle me-2"></i>Admin Bilgi Notu</h6>
                <p class="small opacity-75 mb-0">Öğrenci şifresini unuttuğunda buradan manuel tanımlama yapabilirsiniz. Güvenlik gereği yeni şifre öğrenciye otomatik bildirilmez, sizin iletmeniz gerekir.</p>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../templates/admin_footer.php'; ?>