<?php
// admin/manage_admins.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAdminLogin();
// Sadece Süper Admin bu sayfayı görebilir
if ($_SESSION['admin_role'] !== 'superadmin') {
    set_admin_flash_message('error', 'Bu sayfaya erişim yetkiniz yok.');
    redirect('dashboard.php');
}

$page_title = "Admin Kullanıcılarını Yönet";
include_once __DIR__ . '/../templates/admin_header.php';

try {
    $stmt = $pdo->query("SELECT id, username, role, is_active, created_at FROM admin_users ORDER BY username ASC");
    $admins = $stmt->fetchAll();
} catch (PDOException $e) {
    set_admin_flash_message('error', "Adminler listelenirken hata: " . $e->getMessage());
    $admins = [];
}
?>

<div class="admin-page-title">Admin Kullanıcı Yönetimi</div>
<p><a href="edit_admin.php" class="btn-admin green">Yeni Admin Ekle</a></p>

<table class="admin-table">
    <thead>
        <tr>
            <th>Kullanıcı Adı</th>
            <th>Rol</th>
            <th>Durum</th>
            <th>Oluşturulma Tarihi</th>
            <th>İşlemler</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($admins as $admin): ?>
        <tr>
            <td><?php echo escape_html($admin['username']); ?></td>
            <td><?php echo $admin['role'] === 'superadmin' ? 'Süper Admin' : 'Deneme Yöneticisi'; ?></td>
            <td><?php echo $admin['is_active'] ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-danger">Pasif</span>'; ?></td>
            <td><?php echo format_tr_datetime($admin['created_at']); ?></td>
            <td class="actions">
                <a href="edit_admin.php?id=<?php echo $admin['id']; ?>" class="btn-admin yellow btn-sm">Düzenle / Yetki Ver</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php
include_once __DIR__ . '/../templates/admin_footer.php';
?>
