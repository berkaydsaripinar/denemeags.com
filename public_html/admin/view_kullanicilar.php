<?php
// admin/view_kullanicilar.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAdminLogin();
$page_title = "Kayıtlı Kullanıcıları Görüntüle";
include_once __DIR__ . '/../templates/admin_header.php';

// Kullanıcıları listele (sayfalama eklenebilir)
$limit_users = 50;
$page_users = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
$offset_users = ($page_users - 1) * $limit_users;

try {
    $stmt_total_k = $pdo->query("SELECT COUNT(*) FROM kullanicilar");
    $total_kullanicilar = $stmt_total_k->fetchColumn();
    $total_pages_k = ceil($total_kullanicilar / $limit_users);

    // 'aktif_mi' sütununu da seç
    $stmt_k = $pdo->prepare("
        SELECT id, ad_soyad, email, kayit_tarihi, aktif_mi 
        FROM kullanicilar 
        ORDER BY id DESC 
        LIMIT :limit OFFSET :offset
    ");
    $stmt_k->bindParam(':limit', $limit_users, PDO::PARAM_INT);
    $stmt_k->bindParam(':offset', $offset_users, PDO::PARAM_INT);
    $stmt_k->execute();
    $kullanicilar = $stmt_k->fetchAll();

} catch (PDOException $e) {
    set_admin_flash_message('error', "Kullanıcılar listelenirken hata oluştu: " . $e->getMessage());
    $kullanicilar = [];
    $total_kullanicilar = 0;
    $total_pages_k = 1;
}
?>

<div class="admin-page-title">Kayıtlı Kullanıcılar</div>
<p class="mb-3">Toplam <?php echo $total_kullanicilar; ?> adet kayıtlı kullanıcı bulunmaktadır.</p>
<p><a href="dashboard.php" class="btn-admin yellow">&laquo; Admin Ana Sayfasına Geri Dön</a></p>


<?php if (empty($kullanicilar)): ?>
    <p class="message-box info">Henüz hiç kayıtlı kullanıcı bulunmamaktadır.</p>
<?php else: ?>
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Ad Soyad</th>
                <th>E-posta Adresi</th>
                <th>Durum</th>
                <th>Kayıt Tarihi</th>
                <th>İşlemler</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($kullanicilar as $kullanici): ?>
            <tr>
                <td><?php echo escape_html($kullanici['id']); ?></td>
                <td><?php echo escape_html($kullanici['ad_soyad']); ?></td>
                <td><strong><?php echo escape_html($kullanici['email']); ?></strong></td>
                <td>
                    <?php if ($kullanici['aktif_mi'] == 1): ?>
                        <span style="color:green;">Aktif</span>
                    <?php else: ?>
                        <span style="color:red;">Pasif</span>
                    <?php endif; ?>
                </td>
                <td><?php echo format_tr_datetime($kullanici['kayit_tarihi'], 'd M Y H:i'); ?></td>
                <td class="actions">
                     <a href="edit_kullanici.php?user_id=<?php echo $kullanici['id']; ?>" class="btn-admin yellow btn-sm">Düzenle</a>
                     <a href="view_user_details.php?user_id=<?php echo $kullanici['id']; ?>" class="btn-admin blue btn-sm">Detaylar/Sonuçlar</a> 
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($total_pages_k > 1): ?>
    <nav class="mt-3">
        <ul class="pagination" style="list-style: none; display: flex; gap: 5px;">
            <?php for ($i = 1; $i <= $total_pages_k; $i++): ?>
            <li class="<?php echo ($i == $page_users) ? 'active' : ''; ?>">
                <a href="?page=<?php echo $i; ?>" class="btn-admin <?php echo ($i == $page_users) ? 'yellow' : ''; ?>" style="padding: 5px 10px;"><?php echo $i; ?></a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>

<?php endif; ?>
<style> .btn-admin.blue { background-color: #0D6EFD; } .btn-admin.blue:hover { background-color: #0B5ED7; } </style>
<?php
include_once __DIR__ . '/../templates/admin_footer.php';
?>
