<?php
// admin/manage_denemeler.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAdminLogin();
$page_title = "Deneme Sınavlarını Yönet";
include_once __DIR__ . '/../templates/admin_header.php';

$authorized_ids = getAuthorizedDenemeIds();
$sql = "SELECT id, deneme_adi, soru_sayisi, sonuc_aciklama_tarihi, aktif_mi FROM denemeler";
$params = [];

// Eğer subadmin ise, sadece yetkili olduğu denemeleri göster
if ($authorized_ids !== null) { // null değilse (yani superadmin değilse)
    if (empty($authorized_ids)) {
        $denemeler = []; // Eğer yetkili olduğu deneme yoksa boş dizi ata
    } else {
        // Güvenli sorgu için IN clause oluşturma
        $in_clause = implode(',', array_fill(0, count($authorized_ids), '?'));
        $sql .= " WHERE id IN ($in_clause)";
        $params = $authorized_ids;
    }
}
$sql .= " ORDER BY id DESC";

try {
    if (!isset($denemeler)) { // Eğer yukarıda yetkisizlikten boş dizi atanmadıysa sorguyu çalıştır
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $denemeler = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    set_admin_flash_message('error', "Denemeler listelenirken hata oluştu: " . $e->getMessage());
    $denemeler = [];
}
?>

<div class="admin-page-title">Deneme Sınavı Yönetimi</div>
<?php if (isSuperAdmin()): // Sadece Süper Admin yeni deneme ekleyebilir ?>
<p><a href="edit_deneme.php" class="btn-admin green">Yeni Deneme Sınavı Ekle</a></p>
<?php endif; ?>

<?php if (empty($denemeler)): ?>
    <p class="message-box info">Yönetme yetkiniz olan bir deneme sınavı bulunmamaktadır.</p>
<?php else: ?>
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Deneme Adı</th>
                <th>Soru Sayısı</th>
                <th>Sıralama Açıklanma Tarihi</th>
                <th>Aktif Mi?</th>
                <th style="width: 320px;">İşlemler</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($denemeler as $deneme): ?>
            <tr>
                <td><?php echo escape_html($deneme['id']); ?></td>
                <td><?php echo escape_html($deneme['deneme_adi']); ?></td>
                <td><?php echo escape_html($deneme['soru_sayisi']); ?></td>
                <td><?php echo format_tr_datetime($deneme['sonuc_aciklama_tarihi']); ?></td>
                <td><?php echo $deneme['aktif_mi'] ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-danger">Pasif</span>'; ?></td>
                <td class="actions">
                    <?php if (isSuperAdmin()): // Düzenleme sadece superadmin için ?>
                        <a href="edit_deneme.php?id=<?php echo $deneme['id']; ?>" class="btn-admin yellow btn-sm">Düzenle</a>
                    <?php endif; ?>
                    <a href="manage_cevaplar.php?deneme_id=<?php echo $deneme['id']; ?>" class="btn-admin btn-sm">Cevap Anahtarı</a>
                    <form action="recalculate_scores.php" method="POST" style="display: inline-block; margin-left: 5px;">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_admin_csrf_token(); ?>">
                        <input type="hidden" name="deneme_id" value="<?php echo $deneme['id']; ?>">
                        <button type="submit" class="btn-admin blue btn-sm" 
                                onclick="return confirm('<?php echo escape_html($deneme['deneme_adi']); ?> için tüm sonuçları güncel cevap anahtarına göre yeniden hesaplamak istediğinizden emin misiniz? Bu işlem biraz zaman alabilir ve geri alınamaz.');">
                            Yeniden Hesapla
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
<style> .btn-admin.blue { background-color: #3B82F6; } .btn-admin.blue:hover { background-color: #2563EB; } </style>
<?php
include_once __DIR__ . '/../templates/admin_footer.php';
?>
