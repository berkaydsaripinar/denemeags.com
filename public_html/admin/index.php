<?php
// admin/index.php (Veritabanı Tabanlı Admin Girişi)
require_once __DIR__ . '/../config.php'; 
require_once __DIR__ . '/../includes/db_connect.php'; 
require_once __DIR__ . '/../includes/functions.php'; 
require_once __DIR__ . '/../includes/admin_functions.php'; 

if (isAdminLoggedIn()) {
    header("Location: dashboard.php");
    exit;
}

$page_title = "Admin Girişi";
$csrf_token = generate_admin_csrf_token(); 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_admin_csrf_token($_POST['csrf_token'])) {
        set_flash_message('error', 'Geçersiz istek. Lütfen formu tekrar gönderin.');
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        try {
            $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ?");
            $stmt->execute([$username]);
            $admin_user = $stmt->fetch();

            if ($admin_user && $admin_user['is_active'] == 1 && password_verify($password, $admin_user['password_hash'])) {
                // Giriş Başarılı
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $admin_user['id'];
                $_SESSION['admin_username'] = $admin_user['username'];
                $_SESSION['admin_role'] = $admin_user['role'];

                // Eğer subadmin ise, yetkili olduğu deneme ID'lerini de session'a yükle
                if ($admin_user['role'] === 'subadmin') {
                    $stmt_perms = $pdo->prepare("SELECT deneme_id FROM admin_deneme_permissions WHERE admin_id = ?");
                    $stmt_perms->execute([$admin_user['id']]);
                    $_SESSION['authorized_deneme_ids'] = $stmt_perms->fetchAll(PDO::FETCH_COLUMN);
                } else {
                    // superadmin ise yetki listesini boşalt/yok et
                    unset($_SESSION['authorized_deneme_ids']);
                }

                // Girişi logla
                $ip_adresi = $_SERVER['REMOTE_ADDR'] ?? 'Bilinmiyor';
                $stmt_log = $pdo->prepare("INSERT INTO admin_loglari (admin_kullanici_adi, eylem, ip_adresi) VALUES (?, 'Giriş Başarılı', ?)");
                $stmt_log->execute([$username, $ip_adresi]);
                
                set_admin_flash_message('success', 'Admin paneline başarıyla giriş yaptınız.');
                header("Location: dashboard.php");
                exit;

            } else {
                // Başarısız giriş denemesini logla
                $ip_adresi = $_SERVER['REMOTE_ADDR'] ?? 'Bilinmiyor';
                $stmt_log_fail = $pdo->prepare("INSERT INTO admin_loglari (admin_kullanici_adi, eylem, ip_adresi) VALUES (?, 'Giriş Başarısız', ?)");
                $stmt_log_fail->execute([$username, $ip_adresi]);
                set_flash_message('error', 'Geçersiz kullanıcı adı, şifre veya hesap pasif.');
            }
        } catch (PDOException $e) {
            error_log("Admin giriş hatası: " . $e->getMessage());
            set_flash_message('error', 'Giriş sırasında bir veritabanı hatası oluştu.');
        }
    }
    header("Location: index.php");
    exit;
}

include_once __DIR__ . '/../templates/admin_header.php';
?>

<div class="admin-page-title text-center">Yönetici Girişi</div>

<form action="index.php" method="POST" style="max-width: 400px; margin: 20px auto; padding: 20px; background-color: #f9f9f9; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1);">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <div class="form-group">
        <label for="username" class="form-label">Kullanıcı Adı:</label>
        <input type="text" id="username" name="username" class="input-admin form-control" required>
    </div>
    <div class="form-group">
        <label for="password" class="form-label">Şifre:</label>
        <input type="password" id="password" name="password" class="input-admin form-control" required>
    </div>
    <button type="submit" class="btn-admin w-full">Giriş Yap</button>
</form>

<?php
include_once __DIR__ . '/../templates/admin_footer.php';
?>
