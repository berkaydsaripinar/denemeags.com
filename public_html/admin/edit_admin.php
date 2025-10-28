<?php
// admin/edit_admin.php
// Bu dosya, Süper Admin'in diğer adminleri eklemesini, düzenlemesini ve yetkilendirmesini sağlar.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAdminLogin();

// YETKİ KONTROLÜ: Sadece Süper Admin bu sayfayı kullanabilir.
if (!isSuperAdmin()) {
    set_admin_flash_message('error', 'Bu sayfaya erişim yetkiniz yok.');
    header("Location: dashboard.php");
    exit;
}

$admin_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$is_editing = ($admin_id !== null && $admin_id > 0);
$page_title = $is_editing ? "Admin Düzenle ve Yetkilendir" : "Yeni Admin Ekle";

// Form için varsayılan değerler
$admin_data = ['username' => '', 'role' => 'subadmin', 'is_active' => 1];
$assigned_denemeler = []; // Seçili adminin yetkili olduğu denemelerin ID'leri

// Yetkilendirme için tüm denemeleri çek
try {
    $stmt_denemeler = $pdo->query("SELECT id, deneme_adi FROM denemeler ORDER BY deneme_adi ASC");
    $all_denemeler = $stmt_denemeler->fetchAll();
} catch (PDOException $e) { 
    set_admin_flash_message('error', 'Yetkilendirilecek denemeler listelenirken bir hata oluştu.');
    $all_denemeler = []; 
}

// Eğer düzenleme modundaysak, mevcut adminin bilgilerini ve yetkilerini çek
if ($is_editing) {
    try {
        $stmt_admin = $pdo->prepare("SELECT * FROM admin_users WHERE id = ?");
        $stmt_admin->execute([$admin_id]);
        $admin_data_db = $stmt_admin->fetch(PDO::FETCH_ASSOC);

        if ($admin_data_db) {
            $admin_data = $admin_data_db;
            // Adminin yetkili olduğu denemeleri çek
            $stmt_permissions = $pdo->prepare("SELECT deneme_id FROM admin_deneme_permissions WHERE admin_id = ?");
            $stmt_permissions->execute([$admin_id]);
            $assigned_denemeler = $stmt_permissions->fetchAll(PDO::FETCH_COLUMN);
        } else {
            set_admin_flash_message('error', 'Düzenlenecek admin kullanıcısı bulunamadı.');
            header("Location: manage_admins.php"); exit;
        }

    } catch (PDOException $e) { 
        set_admin_flash_message('error', 'Admin bilgileri yüklenirken bir hata oluştu: ' . $e->getMessage());
        header("Location: manage_admins.php"); exit;
    }
}

$csrf_token = generate_admin_csrf_token();

// Form gönderildiğinde veriyi işle
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_admin_csrf_token($_POST['csrf_token'])) {
        $username = trim($_POST['username'] ?? '');
        $role = $_POST['role'] ?? 'subadmin';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $password = $_POST['password'] ?? '';
        $permissions = $_POST['permissions'] ?? [];

        $errors = [];
        // Kullanıcı adı kontrolü
        if(empty($username)) {
            $errors[] = "Kullanıcı adı boş bırakılamaz.";
        }
        
        // Ekleme veya şifre girilmişse şifre kontrolü
        if (!$is_editing || !empty($password)) {
            if (empty($password) || strlen($password) < 8) {
                $errors[] = 'Şifre en az 8 karakter olmalıdır.';
            }
        }

        if(empty($errors)) {
            try {
                $pdo->beginTransaction();
                if ($is_editing) {
                    // Mevcut admini güncelle
                    $sql = "UPDATE admin_users SET username = ?, role = ?, is_active = ? ";
                    $params = [$username, $role, $is_active];
                    if (!empty($password)) {
                        $sql .= ", password_hash = ? ";
                        $params[] = password_hash($password, PASSWORD_DEFAULT);
                    }
                    $sql .= "WHERE id = ?";
                    $params[] = $admin_id;
                    $stmt_update = $pdo->prepare($sql);
                    $stmt_update->execute($params);
                } else {
                    // Yeni admin ekle
                    $sql = "INSERT INTO admin_users (username, password_hash, role, is_active) VALUES (?, ?, ?, ?)";
                    $params = [$username, password_hash($password, PASSWORD_DEFAULT), $role, $is_active];
                    $stmt_insert = $pdo->prepare($sql);
                    $stmt_insert->execute($params);
                    $admin_id = $pdo->lastInsertId(); // Yeni admin ID'sini al
                }

                // Yetkileri güncelle (sadece subadmin için)
                // Önce bu adminin eski yetkilerini sil
                $stmt_delete_perms = $pdo->prepare("DELETE FROM admin_deneme_permissions WHERE admin_id = ?");
                $stmt_delete_perms->execute([$admin_id]);

                // Eğer rol subadmin ise ve yetkiler seçilmişse yeni yetkileri ekle
                if ($role === 'subadmin' && !empty($permissions)) {
                    $stmt_add_perm = $pdo->prepare("INSERT INTO admin_deneme_permissions (admin_id, deneme_id) VALUES (?, ?)");
                    foreach ($permissions as $deneme_id_perm) {
                        if (filter_var($deneme_id_perm, FILTER_VALIDATE_INT)) {
                             $stmt_add_perm->execute([$admin_id, $deneme_id_perm]);
                        }
                    }
                }

                $pdo->commit();
                set_admin_flash_message('success', 'Admin kullanıcısı başarıyla kaydedildi.');
                header("Location: manage_admins.php"); exit;

            } catch (PDOException $e) {
                $pdo->rollBack();
                if ($e->getCode() == 23000) { // Unique constraint hatası
                    set_admin_flash_message('error', 'Bu kullanıcı adı zaten kullanılıyor.');
                } else {
                    set_admin_flash_message('error', 'Veritabanı hatası: ' . $e->getMessage());
                }
            }
        } else {
            foreach($errors as $error) {
                set_admin_flash_message('error', $error);
            }
        }
    } else {
        set_admin_flash_message('error', 'Geçersiz güvenlik token\'ı. Lütfen tekrar deneyin.');
    }
    header("Location: edit_admin.php" . ($is_editing ? "?id=$admin_id" : "")); exit;
}


include_once __DIR__ . '/../templates/admin_header.php';
?>

<div class="admin-page-title"><?php echo $page_title; ?></div>
<form action="edit_admin.php<?php echo $is_editing ? '?id='.$admin_id : ''; ?>" method="POST" style="max-width: 700px;">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <div class="card card-theme mb-4">
        <div class="card-header">Admin Bilgileri</div>
        <div class="card-body">
            <div class="form-group mb-3">
                <label for="username" class="form-label">Kullanıcı Adı:</label>
                <input type="text" id="username" name="username" class="input-admin form-control" value="<?php echo escape_html($admin_data['username']); ?>" required>
            </div>
            <div class="form-group mb-3">
                <label for="password" class="form-label">Şifre:</label>
                <input type="password" id="password" name="password" class="input-admin form-control" <?php echo !$is_editing ? 'required' : ''; ?>>
                <small class="form-text text-muted"><?php echo $is_editing ? 'Şifreyi değiştirmek istemiyorsanız boş bırakın.' : 'En az 8 karakter olmalıdır.'; ?></small>
            </div>
            <div class="form-group mb-3">
                <label for="role" class="form-label">Rol:</label>
                <select name="role" id="role" class="form-select input-admin">
                    <option value="superadmin" <?php echo ($admin_data['role'] === 'superadmin') ? 'selected' : ''; ?>>Süper Admin (Tüm Yetkiler)</option>
                    <option value="subadmin" <?php echo ($admin_data['role'] === 'subadmin') ? 'selected' : ''; ?>>Deneme Yöneticisi (Sınırlı Yetki)</option>
                </select>
            </div>
             <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?php echo ($admin_data['is_active'] == 1) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="is_active">Admin Aktif Mi?</label>
            </div>
        </div>
    </div>
    
    <div class="card card-theme mt-4" id="permissionsCard" style="<?php echo ($admin_data['role'] === 'superadmin') ? 'display:none;' : ''; ?>">
        <div class="card-header">Deneme Yetkileri (Sadece Deneme Yöneticisi için)</div>
        <div class="card-body">
            <p>Bu yöneticiye, yönetmesi için denemeler atayın:</p>
            <?php if (empty($all_denemeler)): ?>
                 <p class="text-muted">Yetkilendirilecek deneme bulunamadı.</p>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($all_denemeler as $deneme): ?>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="permissions[]" value="<?php echo $deneme['id']; ?>" id="deneme_<?php echo $deneme['id']; ?>"
                                       <?php echo in_array($deneme['id'], $assigned_denemeler) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="deneme_<?php echo $deneme['id']; ?>">
                                    <?php echo escape_html($deneme['deneme_adi']); ?>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <button type="submit" class="btn-admin green mt-3">Kaydet</button>
    <a href="manage_admins.php" class="btn-admin yellow mt-3 ms-2">İptal</a>
</form>

<script>
    // Rol değiştiğinde Yetki kartını göster/gizle
    document.getElementById('role').addEventListener('change', function() {
        document.getElementById('permissionsCard').style.display = this.value === 'subadmin' ? 'block' : 'none';
    });
</script>

<?php
include_once __DIR__ . '/../templates/admin_footer.php';
?>
