<?php
// admin/view_admin_logs.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAdminLogin();
$page_title = "Admin Giriş Logları";
include_once __DIR__ . '/../templates/admin_header.php';

// Sayfalama için
$limit = 50;
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
$offset = ($page - 1) * $limit;

try {
    $stmt_total = $pdo->query("SELECT COUNT(*) FROM admin_loglari");
    $total_logs = $stmt_total->fetchColumn();
    $total_pages = ceil($total_logs / $limit);

    $stmt = $pdo->prepare("SELECT * FROM admin_loglari ORDER BY tarih DESC LIMIT :limit OFFSET :offset");
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll();
} catch (PDOException $e) {
    set_admin_flash_message('error', "Loglar listelenirken hata: " . $e->getMessage());
    $logs = [];
    $total_logs = 0;
    $total_pages = 1;
}
?>

<div class="admin-page-title">Admin Giriş Logları</div>
<p>Toplam <?php echo $total_logs; ?> log kaydı bulunmaktadır.</p>

<?php if (empty($logs)): ?>
    <p class="message-box info">Henüz hiç log kaydı bulunmamaktadır.</p>
<?php else: ?>
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Admin Kullanıcı Adı</th>
                <th>Eylem</th>
                <th>IP Adresi</th>
                <th>Tarih</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td><?php echo $log['id']; ?></td>
                <td><?php echo escape_html($log['admin_kullanici_adi']); ?></td>
                <td><?php echo escape_html($log['eylem']); ?></td>
                <td><?php echo escape_html($log['ip_adresi']); ?></td>
                <td><?php echo format_tr_datetime($log['tarih']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($total_pages > 1): ?>
    <nav class="mt-3">
        <ul class="pagination" style="list-style: none; display: flex; gap: 5px; flex-wrap: wrap;">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <li class="<?php echo ($i == $page) ? 'active' : ''; ?>">
                <a href="?page=<?php echo $i; ?>" class="btn-admin <?php echo ($i == $page) ? 'yellow' : ''; ?>" style="padding: 5px 10px;"><?php echo $i; ?></a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
<?php endif; ?>
<p class="mt-4"><a href="dashboard.php" class="btn-admin yellow">&laquo; Admin Ana Sayfasına Geri Dön</a></p>
<?php
include_once __DIR__ . '/../templates/admin_footer.php';
?>
