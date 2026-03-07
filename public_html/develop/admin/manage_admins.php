<?php
// admin/manage_admins.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAdminLogin();

if (!isSuperAdmin()) {
    set_admin_flash_message('error', 'Bu sayfaya erişim yetkiniz yok.');
    redirect('dashboard.php');
}

$page_title = "Yönetici Ayarları";

// Adminleri Çek
try {
    $admins = $pdo->query("SELECT * FROM admin_users ORDER BY username ASC")->fetchAll();
} catch (PDOException $e) {
    set_admin_flash_message('error', 'Veri hatası.');
    $admins = [];
}

include_once __DIR__ . '/../templates/admin_header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col">
        <h3 class="fw-bold mb-0">Yönetici Kadrosu</h3>
        <p class="text-muted small">Panel erişimi olan tüm yöneticiler ve yetki seviyeleri.</p>
    </div>
    <div class="col-auto">
        <a href="edit_admin.php" class="btn btn-admin-primary px-4 py-2">
            <i class="fas fa-user-plus me-2"></i> Yeni Yönetici Ekle
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th class="ps-4">Kullanıcı Bilgisi</th>
                        <th>Rol / Yetki Seviyesi</th>
                        <th>Durum</th>
                        <th>Son Giriş</th>
                        <th class="text-end pe-4">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($admins as $admin): ?>
                    <tr>
                        <td class="ps-4">
                            <div class="d-flex align-items-center">
                                <div class="bg-primary bg-opacity-10 text-primary p-2 rounded-circle me-3">
                                    <i class="fas fa-user-shield"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark"><?php echo escape_html($admin['username']); ?></div>
                                    <div class="text-muted" style="font-size: 0.7rem;">Eklenme: <?php echo date('d.m.Y', strtotime($admin['created_at'])); ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if($admin['role'] === 'superadmin'): ?>
                                <span class="badge bg-dark rounded-pill px-3"><i class="fas fa-crown text-warning me-1"></i> Süper Admin</span>
                            <?php else: ?>
                                <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-3">Yayın Editörü</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($admin['is_active']): ?>
                                <span class="text-success small fw-bold"><i class="fas fa-check-circle me-1"></i> Aktif</span>
                            <?php else: ?>
                                <span class="text-danger small fw-bold"><i class="fas fa-ban me-1"></i> Pasif</span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted">
                            <?php // Bu alan veritabanına son_giris sütunu eklenirse aktif edilebilir. ?>
                            <i class="fas fa-history me-1"></i> Bilgi yok
                        </td>
                        <td class="text-end pe-4">
                            <a href="edit_admin.php?id=<?php echo $admin['id']; ?>" class="btn btn-sm btn-outline-primary shadow-sm px-3">
                                <i class="fas fa-key me-1"></i> Yetkilendir
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-4 p-4 bg-white rounded-4 border-start border-warning border-4 shadow-sm">
    <div class="d-flex">
        <i class="fas fa-lightbulb text-warning fa-2x me-3"></i>
        <div>
            <h6 class="fw-bold">Yönetici Rolleri Hakkında Bilgi</h6>
            <p class="text-muted small mb-0">
                <strong>Süper Admin:</strong> Tüm sistem ayarlarını ve yöneticileri yönetebilir.<br>
                <strong>Yayın Editörü:</strong> Sadece kendisinden sorumlu olduğu denemelerin cevaplarını ve sonuçlarını yönetebilir.
            </p>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../templates/admin_footer.php'; ?>