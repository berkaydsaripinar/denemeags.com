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
        
        $new_ad_soyad = trim($_POST['ad_soyad'] ?? '');
        $new_email = trim($_POST['email'] ?? '');
        $new_aktif_mi = isset($_POST['aktif_mi']) ? 1 : 0;
        $new_sifre = $_POST['yeni_sifre'] ?? '';

        $update_fields = [];
        $params = [];

        if ($action === 'update_details') {
            if (empty($new_ad_soyad)) {
                set_admin_flash_message('error', 'Ad Soyad boş bırakılamaz.');
            } else {
                $update_fields[] = "ad_soyad = :ad_soyad";
                $params[':ad_soyad'] = $new_ad_soyad;
            }

            if (empty($new_email)) {
                set_admin_flash_message('error', 'E-posta boş bırakılamaz.');
            } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                set_admin_flash_message('error', 'Geçersiz e-posta formatı.');
            } else {
                // E-postanın başka bir kullanıcı tarafından kullanılıp kullanılmadığını kontrol et
                $stmt_check_email = $pdo->prepare("SELECT id FROM kullanicilar WHERE email = ? AND id != ?");
                $stmt_check_email->execute([$new_email, $user_id_to_edit]);
                if ($stmt_check_email->fetch()) {
                    set_admin_flash_message('error', 'Bu e-posta adresi başka bir kullanıcı tarafından kullanılıyor.');
                } else {
                    $update_fields[] = "email = :email";
                    $params[':email'] = $new_email;
                }
            }
            
            $update_fields[] = "aktif_mi = :aktif_mi";
            $params[':aktif_mi'] = $new_aktif_mi;

            if (!empty($update_fields) && !has_flash_messages_type('error')) { // Sadece hata yoksa güncelle
                try {
                    $sql = "UPDATE kullanicilar SET " . implode(", ", $update_fields) . " WHERE id = :user_id";
                    $params[':user_id'] = $user_id_to_edit;
                    
                    $stmt_update = $pdo->prepare($sql);
                    $stmt_update->execute($params);
                    set_admin_flash_message('success', 'Kullanıcı bilgileri başarıyla güncellendi.');
                    // Sayfayı yeniden yükleyerek güncel verileri göster
                    header("Location: edit_kullanici.php?user_id=" . $user_id_to_edit);
                    exit;
                } catch (PDOException $e) {
                    set_admin_flash_message('error', 'Kullanıcı güncellenirken veritabanı hatası: ' . $e->getMessage());
                }
            }
        } elseif ($action === 'reset_password') {
            if (empty($new_sifre)) {
                set_admin_flash_message('error', 'Yeni şifre boş bırakılamaz.');
            } elseif (strlen($new_sifre) < 6) {
                set_admin_flash_message('error', 'Yeni şifre en az 6 karakter olmalıdır.');
            } else {
                try {
                    $yeni_sifre_hash = password_hash($new_sifre, PASSWORD_DEFAULT);
                    $stmt_reset_pass = $pdo->prepare("UPDATE kullanicilar SET sifre_hash = ? WHERE id = ?");
                    $stmt_reset_pass->execute([$yeni_sifre_hash, $user_id_to_edit]);
                    set_admin_flash_message('success', 'Kullanıcının şifresi başarıyla sıfırlandı/güncellendi.');
                     // Şifre alanını temizle ve sayfayı yeniden yükle
                    header("Location: edit_kullanici.php?user_id=" . $user_id_to_edit);
                    exit;
                } catch (PDOException $e) {
                    set_admin_flash_message('error', 'Şifre sıfırlanırken veritabanı hatası: ' . $e->getMessage());
                }
            }
        }
    }
    // Hata durumunda veya işlem sonrası formu tekrar doldurmak için verileri al
    // (Flash mesajları zaten ayarlandı, sayfa yeniden yüklendiğinde gösterilecek)
    // Eğer bir hata oluştuysa, $user_data'yı POST'tan gelenlerle güncellemek isteyebiliriz ki kullanıcı
    // girdiği değerleri kaybetmesin, ama bu flash mesajlarla zaten yönlendirme yapıldığı için
    // sayfa yeniden yüklendiğinde veritabanından en güncel hali çekilecek.
    // Eğer yönlendirme yapmazsak, $user_data'yı POST ile güncellemek gerekirdi.
    if (has_flash_messages_type('error')) {
        // Sayfayı yeniden yüklemeden önce, formdaki değerleri POST'tan gelenlerle doldurabiliriz
        // Ancak mevcut yapıda header ile yönlendirme yapıyoruz.
        // Bu yüzden bu kısım genellikle çalışmaz, ama bir önlem olarak kalabilir.
        $user_data['ad_soyad'] = $new_ad_soyad ?? $user_data['ad_soyad'];
        $user_data['email'] = $new_email ?? $user_data['email'];
        $user_data['aktif_mi'] = $new_aktif_mi ?? $user_data['aktif_mi'];
    } else {
        // Başarılı işlem sonrası, güncel verileri çekmek için sayfayı yeniden yönlendirmek daha iyi.
        // Zaten yukarıda header("Location:...") ile yapılıyor.
    }
}


include_once __DIR__ . '/../templates/admin_header.php';
?>

<div class="admin-page-title"><?php echo $page_title; ?> (ID: <?php echo $user_data['id']; ?>)</div>
<p><a href="view_kullanicilar.php" class="btn-admin yellow">&laquo; Kullanıcı Listesine Geri Dön</a></p>

<div style="display: flex; gap: 20px; flex-wrap: wrap;">
    <div style="flex: 1; min-width: 300px; padding:15px; background-color:#f9f9f9; border-radius:5px;">
        <h3>Kullanıcı Bilgileri</h3>
        <form action="edit_kullanici.php?user_id=<?php echo $user_data['id']; ?>" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="form_action" value="update_details">
            
            <div class="form-group">
                <label for="ad_soyad">Ad Soyad:</label>
                <input type="text" id="ad_soyad" name="ad_soyad" class="input-admin" value="<?php echo escape_html($user_data['ad_soyad']); ?>" required>
            </div>
            <div class="form-group">
                <label for="email">E-posta Adresi:</label>
                <input type="email" id="email" name="email" class="input-admin" value="<?php echo escape_html($user_data['email']); ?>" required>
            </div>
            <div class="form-group">
                <label for="aktif_mi_checkbox">
                    <input type="checkbox" id="aktif_mi_checkbox" name="aktif_mi" value="1" <?php echo ($user_data['aktif_mi'] == 1) ? 'checked' : ''; ?>>
                    Kullanıcı Aktif Mi? (Giriş yapabilsin mi?)
                </label>
            </div>
            <button type="submit" class="btn-admin green">Bilgileri Güncelle</button>
        </form>
    </div>

    <div style="flex: 1; min-width: 300px; padding:15px; background-color:#f9f9f9; border-radius:5px;">
        <h3>Şifre Sıfırla / Değiştir</h3>
        <form action="edit_kullanici.php?user_id=<?php echo $user_data['id']; ?>" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="form_action" value="reset_password">
            <div class="form-group">
                <label for="yeni_sifre">Yeni Şifre (En az 6 karakter):</label>
                <input type="password" id="yeni_sifre" name="yeni_sifre" class="input-admin" required>
            </div>
             <div class="form-group">
                <label for="yeni_sifre_tekrar">Yeni Şifre Tekrar:</label>
                <input type="password" id="yeni_sifre_tekrar" name="yeni_sifre_tekrar" class="input-admin" required>
                <small>Not: Şifre tekrarı sadece görsel bir kontroldür, sunucuda tekrar kontrol edilmez. Admin dikkatli olmalıdır.</small>
            </div>
            <button type="submit" class="btn-admin orange" onclick="return document.getElementById('yeni_sifre').value === document.getElementById('yeni_sifre_tekrar').value || alert('Yeni şifreler eşleşmiyor!');">Şifreyi Değiştir</button>
        </form>
    </div>
</div>


<?php
include_once __DIR__ . '/../templates/admin_footer.php';
?>
<style> .btn-admin.orange { background-color: #ED8936; } .btn-admin.orange:hover { background-color: #DD6B20; } </style>
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
        
        $new_ad_soyad = trim($_POST['ad_soyad'] ?? '');
        $new_email = trim($_POST['email'] ?? '');
        $new_aktif_mi = isset($_POST['aktif_mi']) ? 1 : 0;
        $new_sifre = $_POST['yeni_sifre'] ?? '';

        $update_fields = [];
        $params = [];

        if ($action === 'update_details') {
            if (empty($new_ad_soyad)) {
                set_admin_flash_message('error', 'Ad Soyad boş bırakılamaz.');
            } else {
                $update_fields[] = "ad_soyad = :ad_soyad";
                $params[':ad_soyad'] = $new_ad_soyad;
            }

            if (empty($new_email)) {
                set_admin_flash_message('error', 'E-posta boş bırakılamaz.');
            } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                set_admin_flash_message('error', 'Geçersiz e-posta formatı.');
            } else {
                // E-postanın başka bir kullanıcı tarafından kullanılıp kullanılmadığını kontrol et
                $stmt_check_email = $pdo->prepare("SELECT id FROM kullanicilar WHERE email = ? AND id != ?");
                $stmt_check_email->execute([$new_email, $user_id_to_edit]);
                if ($stmt_check_email->fetch()) {
                    set_admin_flash_message('error', 'Bu e-posta adresi başka bir kullanıcı tarafından kullanılıyor.');
                } else {
                    $update_fields[] = "email = :email";
                    $params[':email'] = $new_email;
                }
            }
            
            $update_fields[] = "aktif_mi = :aktif_mi";
            $params[':aktif_mi'] = $new_aktif_mi;

            if (!empty($update_fields) && !has_flash_messages_type('error')) { // Sadece hata yoksa güncelle
                try {
                    $sql = "UPDATE kullanicilar SET " . implode(", ", $update_fields) . " WHERE id = :user_id";
                    $params[':user_id'] = $user_id_to_edit;
                    
                    $stmt_update = $pdo->prepare($sql);
                    $stmt_update->execute($params);
                    set_admin_flash_message('success', 'Kullanıcı bilgileri başarıyla güncellendi.');
                    // Sayfayı yeniden yükleyerek güncel verileri göster
                    header("Location: edit_kullanici.php?user_id=" . $user_id_to_edit);
                    exit;
                } catch (PDOException $e) {
                    set_admin_flash_message('error', 'Kullanıcı güncellenirken veritabanı hatası: ' . $e->getMessage());
                }
            }
        } elseif ($action === 'reset_password') {
            if (empty($new_sifre)) {
                set_admin_flash_message('error', 'Yeni şifre boş bırakılamaz.');
            } elseif (strlen($new_sifre) < 6) {
                set_admin_flash_message('error', 'Yeni şifre en az 6 karakter olmalıdır.');
            } else {
                try {
                    $yeni_sifre_hash = password_hash($new_sifre, PASSWORD_DEFAULT);
                    $stmt_reset_pass = $pdo->prepare("UPDATE kullanicilar SET sifre_hash = ? WHERE id = ?");
                    $stmt_reset_pass->execute([$yeni_sifre_hash, $user_id_to_edit]);
                    set_admin_flash_message('success', 'Kullanıcının şifresi başarıyla sıfırlandı/güncellendi.');
                     // Şifre alanını temizle ve sayfayı yeniden yükle
                    header("Location: edit_kullanici.php?user_id=" . $user_id_to_edit);
                    exit;
                } catch (PDOException $e) {
                    set_admin_flash_message('error', 'Şifre sıfırlanırken veritabanı hatası: ' . $e->getMessage());
                }
            }
        }
    }
    // Hata durumunda veya işlem sonrası formu tekrar doldurmak için verileri al
    // (Flash mesajları zaten ayarlandı, sayfa yeniden yüklendiğinde gösterilecek)
    // Eğer bir hata oluştuysa, $user_data'yı POST'tan gelenlerle güncellemek isteyebiliriz ki kullanıcı
    // girdiği değerleri kaybetmesin, ama bu flash mesajlarla zaten yönlendirme yapıldığı için
    // sayfa yeniden yüklendiğinde veritabanından en güncel hali çekilecek.
    // Eğer yönlendirme yapmazsak, $user_data'yı POST ile güncellemek gerekirdi.
    if (has_flash_messages_type('error')) {
        // Sayfayı yeniden yüklemeden önce, formdaki değerleri POST'tan gelenlerle doldurabiliriz
        // Ancak mevcut yapıda header ile yönlendirme yapıyoruz.
        // Bu yüzden bu kısım genellikle çalışmaz, ama bir önlem olarak kalabilir.
        $user_data['ad_soyad'] = $new_ad_soyad ?? $user_data['ad_soyad'];
        $user_data['email'] = $new_email ?? $user_data['email'];
        $user_data['aktif_mi'] = $new_aktif_mi ?? $user_data['aktif_mi'];
    } else {
        // Başarılı işlem sonrası, güncel verileri çekmek için sayfayı yeniden yönlendirmek daha iyi.
        // Zaten yukarıda header("Location:...") ile yapılıyor.
    }
}


include_once __DIR__ . '/../templates/admin_header.php';
?>

<div class="admin-page-title"><?php echo $page_title; ?> (ID: <?php echo $user_data['id']; ?>)</div>
<p><a href="view_kullanicilar.php" class="btn-admin yellow">&laquo; Kullanıcı Listesine Geri Dön</a></p>

<div style="display: flex; gap: 20px; flex-wrap: wrap;">
    <div style="flex: 1; min-width: 300px; padding:15px; background-color:#f9f9f9; border-radius:5px;">
        <h3>Kullanıcı Bilgileri</h3>
        <form action="edit_kullanici.php?user_id=<?php echo $user_data['id']; ?>" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="form_action" value="update_details">
            
            <div class="form-group">
                <label for="ad_soyad">Ad Soyad:</label>
                <input type="text" id="ad_soyad" name="ad_soyad" class="input-admin" value="<?php echo escape_html($user_data['ad_soyad']); ?>" required>
            </div>
            <div class="form-group">
                <label for="email">E-posta Adresi:</label>
                <input type="email" id="email" name="email" class="input-admin" value="<?php echo escape_html($user_data['email']); ?>" required>
            </div>
            <div class="form-group">
                <label for="aktif_mi_checkbox">
                    <input type="checkbox" id="aktif_mi_checkbox" name="aktif_mi" value="1" <?php echo ($user_data['aktif_mi'] == 1) ? 'checked' : ''; ?>>
                    Kullanıcı Aktif Mi? (Giriş yapabilsin mi?)
                </label>
            </div>
            <button type="submit" class="btn-admin green">Bilgileri Güncelle</button>
        </form>
    </div>

    <div style="flex: 1; min-width: 300px; padding:15px; background-color:#f9f9f9; border-radius:5px;">
        <h3>Şifre Sıfırla / Değiştir</h3>
        <form action="edit_kullanici.php?user_id=<?php echo $user_data['id']; ?>" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="form_action" value="reset_password">
            <div class="form-group">
                <label for="yeni_sifre">Yeni Şifre (En az 6 karakter):</label>
                <input type="password" id="yeni_sifre" name="yeni_sifre" class="input-admin" required>
            </div>
             <div class="form-group">
                <label for="yeni_sifre_tekrar">Yeni Şifre Tekrar:</label>
                <input type="password" id="yeni_sifre_tekrar" name="yeni_sifre_tekrar" class="input-admin" required>
                <small>Not: Şifre tekrarı sadece görsel bir kontroldür, sunucuda tekrar kontrol edilmez. Admin dikkatli olmalıdır.</small>
            </div>
            <button type="submit" class="btn-admin orange" onclick="return document.getElementById('yeni_sifre').value === document.getElementById('yeni_sifre_tekrar').value || alert('Yeni şifreler eşleşmiyor!');">Şifreyi Değiştir</button>
        </form>
    </div>
</div>


<?php
include_once __DIR__ . '/../templates/admin_footer.php';
?>
<style> .btn-admin.orange { background-color: #ED8936; } .btn-admin.orange:hover { background-color: #DD6B20; } </style>
