<?php
// admin/dashboard.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php'; 
require_once __DIR__ . '/../includes/admin_functions.php'; 

requireAdminLogin(); 

$page_title = "Admin Paneli";
include_once __DIR__ . '/../templates/admin_header.php';

// İstatistikler
$total_users = $total_aktif_denemeler = $total_codes = $used_codes = "N/A";
try {
    $stmt_total_users = $pdo->query("SELECT COUNT(*) FROM kullanicilar");
    $total_users = $stmt_total_users->fetchColumn();
    $stmt_total_denemeler = $pdo->query("SELECT COUNT(*) FROM denemeler WHERE aktif_mi = 1");
    $total_aktif_denemeler = $stmt_total_denemeler->fetchColumn();
    $stmt_total_codes = $pdo->query("SELECT COUNT(*) FROM deneme_erisim_kodlari");
    $total_codes = $stmt_total_codes->fetchColumn();
    $stmt_used_codes = $pdo->query("SELECT COUNT(*) FROM deneme_erisim_kodlari WHERE kullanici_id IS NOT NULL");
    $used_codes = $stmt_used_codes->fetchColumn();
} catch (PDOException $e) {
    error_log("Admin Dashboard İstatistik Hatası: " . $e->getMessage());
    set_admin_flash_message('error', 'İstatistikler yüklenirken bir veritabanı sorunu oluştu.');
}
?>
<div class="admin-page-title">Yönetim Paneli Ana Sayfası</div>
<p class="text-theme-secondary">Hoş geldiniz, <strong class="text-theme-primary"><?php echo escape_html($_SESSION['admin_username']); ?></strong>! (Rol: <?php echo $_SESSION['admin_role'] === 'superadmin' ? 'Süper Admin' : 'Deneme Yöneticisi'; ?>)</p>

<!-- İstatistik Kartları -->
<div class="row mb-4">
    <div class="col-md-6 col-lg-3 mb-3">
        <div class="card text-center p-3 shadow-sm h-100">
            <h5 class="card-title text-muted">Toplam Kullanıcı</h5>
            <p class="display-5 fw-bold text-primary"><?php echo $total_users; ?></p>
        </div>
    </div>
    <div class="col-md-6 col-lg-3 mb-3">
         <div class="card text-center p-3 shadow-sm h-100">
            <h5 class="card-title text-muted">Aktif Denemeler</h5>
            <p class="display-5 fw-bold text-success"><?php echo $total_aktif_denemeler; ?></p>
        </div>
    </div>
    <div class="col-md-6 col-lg-3 mb-3">
         <div class="card text-center p-3 shadow-sm h-100">
            <h5 class="card-title text-muted">Toplam Kodlar</h5>
            <p class="display-5 fw-bold text-info"><?php echo $total_codes; ?></p>
        </div>
    </div>
    <div class="col-md-6 col-lg-3 mb-3">
         <div class="card text-center p-3 shadow-sm h-100">
            <h5 class="card-title text-muted">Kullanılan Kodlar</h5>
            <p class="display-5 fw-bold text-warning"><?php echo $used_codes; ?></p>
        </div>
    </div>
</div>

<hr class="my-4">

<!-- Yönetim Menüsü Kartları -->
<h3 class="admin-page-title mt-4" style="border:none; font-size: 1.75rem;">Yönetim Menüsü</h3>
<div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4">
    
    <!-- Deneme ve Kod Yönetimi -->
    <div class="col">
        <div class="card card-theme shadow-sm h-100">
            <div class="card-body d-flex flex-column text-center">
                <div class="mb-3"><svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="currentColor" class="bi bi-journals text-theme-primary" viewBox="0 0 16 16"><path d="M5 0h8a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2 2 2 0 0 1-2 1H3a2 2 0 0 1-2-2h1a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1H3a1 1 0 0 0-1 1H1a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v9a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1H3a2 2 0 0 1-2-2z"/><path d="M1 6v-.5a.5.5 0 0 1 1 0V6h.5a.5.5 0 0 1 0 1h-2a.5.5 0 0 1 0-1h.5zm0 3v-.5a.5.5 0 0 1 1 0V9h.5a.5.5 0 0 1 0 1h-2a.5.5 0 0 1 0-1h.5zm0 3v-.5a.5.5 0 0 1 1 0v.5h.5a.5.5 0 0 1 0 1h-2a.5.5 0 0 1 0-1h.5z"/></svg></div>
                <h5 class="card-title">Denemeler ve Kodlar</h5>
                <p class="card-text text-theme-secondary small flex-grow-1">Denemeleri, cevap anahtarlarını ve deneme erişim kodlarını yönetin.</p>
                <div class="mt-auto">
                    <a href="manage_denemeler.php" class="btn btn-theme-primary w-100">Denemeleri Yönet</a>
                    <a href="manage_kodlar.php" class="btn btn-outline-secondary btn-sm mt-2 w-100">Erişim Kodlarını Yönet</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Kullanıcı Yönetimi -->
    <div class="col">
        <div class="card card-theme shadow-sm h-100">
            <div class="card-body d-flex flex-column text-center">
                <div class="mb-3"><svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="currentColor" class="bi bi-people-fill text-theme-primary" viewBox="0 0 16 16"><path d="M7 14s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1H7zm4-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/><path fill-rule="evenodd" d="M5.216 14A2.238 2.238 0 0 1 5 13c0-1.355.68-2.75 1.936-3.72A6.325 6.325 0 0 0 5 9c-4 0-5 3-5 4s1 1 1 1h4.216zM4.5 8a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z"/></svg></div>
                <h5 class="card-title">Kullanıcılar</h5>
                <p class="card-text text-theme-secondary small flex-grow-1">Kayıtlı kullanıcıları listeleyin ve sonuç detaylarını inceleyin.</p>
                <a href="view_kullanicilar.php" class="btn btn-theme-primary mt-auto">Kullanıcıları Yönet</a>
            </div>
        </div>
    </div>

    <!-- Raporlama ve İstatistikler -->
    <div class="col">
        <div class="card card-theme shadow-sm h-100">
            <div class="card-body d-flex flex-column text-center">
                <div class="mb-3"><svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="currentColor" class="bi bi-bar-chart-line-fill text-theme-primary" viewBox="0 0 16 16"><path d="M11 2a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v12h.5a.5.5 0 0 1 0 1H.5a.5.5 0 0 1 0-1H1v-3a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v3h1V7a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v7h1z"/></svg></div>
                <h5 class="card-title">Raporlar</h5>
                <p class="card-text text-theme-secondary small flex-grow-1">Deneme bazlı ve konu bazlı genel istatistikleri ve sonuçları görüntüleyin.</p>
                 <div class="mt-auto">
                    <a href="view_all_results.php" class="btn btn-theme-primary w-100">Tüm Sonuçlar</a>
                    <a href="deneme_stats.php" class="btn btn-outline-secondary btn-sm mt-2 w-100">Deneme İstatistikleri</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Duyuru Yönetimi -->
    <div class="col">
        <div class="card card-theme shadow-sm h-100">
            <div class="card-body d-flex flex-column text-center">
                 <div class="mb-3"><svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="currentColor" class="bi bi-megaphone-fill text-theme-primary" viewBox="0 0 16 16"><path d="M13 2.5a1.5 1.5 0 0 1 3 0v11a1.5 1.5 0 0 1-3 0zm-1 .724c-2.067.95-4.539 1.481-7 1.656v6.237a25 25 0 0 1 1.088.085c2.053.204 4.038.668 5.912 1.56V3.224z"/><path d="M7.646 1.115A.5.5 0 0 1 8 1h0a.5.5 0 0 1 .354.115l1.73 1.73A.5.5 0 0 1 10 3H6a.5.5 0 0 1-.354-.854zM5 2.5a0.5 0.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm0 3a0.5 0.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm0 3a0.5 0.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5z"/></svg></div>
                <h5 class="card-title">Duyurular</h5>
                <p class="card-text text-theme-secondary small flex-grow-1">Kullanıcı panosunda görünecek duyuruları ekleyin veya düzenleyin.</p>
                <a href="manage_duyurular.php" class="btn btn-theme-primary mt-auto">Duyuruları Yönet</a>
            </div>
        </div>
    </div>

    <!-- Sadece Süper Admin'e özel kartlar -->
    <?php if (isSuperAdmin()): ?>
    <div class="col">
        <div class="card card-theme shadow-sm h-100 border-danger">
            <div class="card-body d-flex flex-column text-center">
                <div class="mb-3"><svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="currentColor" class="bi bi-person-badge-fill text-danger" viewBox="0 0 16 16"><path d="M2 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2zm4.5 0a.5.5 0 0 0 0 1h3a.5.5 0 0 0 0-1h-3zM8 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm5 2.755C12.146 12.825 10.623 12 8 12s-4.146.826-5 1.755V14a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1v-.245z"/></svg></div>
                <h5 class="card-title text-danger">Admin Yönetimi</h5>
                <p class="card-text text-muted small flex-grow-1">Yeni admin kullanıcıları ekleyin, rollerini ve yetkilerini düzenleyin.</p>
                <a href="manage_admins.php" class="btn btn-danger mt-auto">Adminleri Yönet</a>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card card-theme shadow-sm h-100 border-danger">
            <div class="card-body d-flex flex-column text-center">
                <div class="mb-3"><svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="currentColor" class="bi bi-gear-fill text-danger" viewBox="0 0 16 16"><path d="M9.405 1.05c-.413-1.4-2.397-1.4-2.81 0l-.1.34a1.464 1.464 0 0 1-2.105.872l-.31-.17c-1.283-.698-2.686.705-1.987 1.987l.169.311a1.464 1.464 0 0 1-.872 2.105l-.34.1c-1.4.413-1.4 2.397 0 2.81l.34.1a1.464 1.464 0 0 1 .872 2.105l-.17.31c-.698 1.283.705 2.686 1.987 1.987l.311-.169a1.464 1.464 0 0 1 2.105.872l.1.34c.413 1.4 2.397 1.4 2.81 0l.1-.34a1.464 1.464 0 0 1 2.105-.872l.31.17c1.283.698 2.686-.705 1.987-1.987l-.169-.311a1.464 1.464 0 0 1 .872-2.105l.34-.1c1.4-.413-1.4-2.397 0-2.81l.34-.1a1.464 1.464 0 0 1 .872-2.105l.17-.31c.698-1.283-.705-2.686-1.987-1.987l-.311.169a1.464 1.464 0 0 1-2.105-.872l-.1-.34zM8 10.93a2.929 2.929 0 1 1 0-5.86 2.929 2.929 0 0 1 0 5.858z"/></svg></div>
                <h5 class="card-title text-danger">Sistem Ayarları</h5>
                <p class="card-text text-muted small flex-grow-1">Net ve puan hesaplama katsayıları gibi genel sistem ayarlarını yönetin.</p>
                <a href="system_settings.php" class="btn btn-danger mt-auto">Ayarlara Git</a>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>
<!-- Detaylı Navigasyon Menüsü Kaldırıldı -->

<?php
include_once __DIR__ . '/../templates/admin_footer.php';
?>
