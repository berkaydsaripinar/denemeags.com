<?php
// admin/view_kullanicilar.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAdminLogin();
$page_title = "Öğrenci Yönetimi";

// Sayfalama
$limit = 50;
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
$offset = ($page - 1) * $limit;

// Arama
$search = trim($_GET['search'] ?? '');
$where_sql = "";
$params = [];
if (!empty($search)) {
    $where_sql = "WHERE ad_soyad LIKE ? OR email LIKE ?";
    $params = ["%$search%", "%$search%"];
}

try {
    $stmt_total = $pdo->prepare("SELECT COUNT(*) FROM kullanicilar $where_sql");
    $stmt_total->execute($params);
    $total_users = $stmt_total->fetchColumn();
    $total_pages = ceil($total_users / $limit);

    $stmt_k = $pdo->prepare("
        SELECT id, ad_soyad, email, kayit_tarihi, aktif_mi 
        FROM kullanicilar 
        $where_sql
        ORDER BY id DESC 
        LIMIT $limit OFFSET $offset
    ");
    $stmt_k->execute($params);
    $kullanicilar = $stmt_k->fetchAll();

} catch (PDOException $e) {
    set_admin_flash_message('error', "Hata: " . $e->getMessage());
    $kullanicilar = [];
    $total_users = 0;
    $total_pages = 1;
}

include_once __DIR__ . '/../templates/admin_header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col">
        <h3 class="fw-bold mb-0">Öğrenci Listesi</h3>
        <p class="text-muted small">Sisteme kayıtlı toplam <?php echo $total_users; ?> öğrenci bulunuyor.</p>
    </div>
    <div class="col-md-4">
        <form method="GET" class="input-group shadow-sm rounded-3">
            <input type="text" name="search" class="form-control border-0" placeholder="İsim veya e-posta ile ara..." value="<?php echo escape_html($search); ?>">
            <button class="btn btn-white bg-white border-0 text-primary" type="submit"><i class="fas fa-search"></i></button>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 admin-table">
                <thead>
                    <tr>
                        <th class="ps-4">ÖĞRENCİ BİLGİSİ</th>
                        <th>DURUM</th>
                        <th>KAYIT TARİHİ</th>
                        <th class="text-end pe-4">İŞLEMLER</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($kullanicilar)): ?>
                        <tr><td colspan="4" class="text-center py-5 text-muted">Arama kriterlerine uygun öğrenci bulunamadı.</td></tr>
                    <?php else: ?>
                        <?php foreach ($kullanicilar as $user): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center">
                                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['ad_soyad']); ?>&background=random&color=fff" class="rounded-circle me-3" width="40" height="40">
                                    <div>
                                        <div class="fw-bold text-dark"><?php echo escape_html($user['ad_soyad']); ?></div>
                                        <div class="text-muted small"><?php echo escape_html($user['email']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if ($user['aktif_mi']): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3">Pasif</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="small text-secondary"><?php echo date('d.m.Y', strtotime($user['kayit_tarihi'])); ?></div>
                                <div class="text-muted" style="font-size: 0.7rem;"><?php echo date('H:i', strtotime($user['kayit_tarihi'])); ?></div>
                            </td>
                            <td class="text-end pe-4">
                                <div class="btn-group">
                                    <a href="view_user_details.php?user_id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-primary" title="Sonuçlar">
                                        <i class="fas fa-chart-bar me-1"></i> Analiz
                                    </a>
                                    <a href="edit_kullanici.php?user_id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Düzenle">
                                        <i class="fas fa-user-edit"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Sayfalama -->
<?php if ($total_pages > 1): ?>
<nav class="mt-4">
    <ul class="pagination justify-content-center">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                <a class="page-link shadow-sm border-0 mx-1 rounded-3" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<?php include_once __DIR__ . '/../templates/admin_footer.php'; ?>