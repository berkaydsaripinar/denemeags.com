<?php
// admin/manage_duyurular.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAdminLogin();
$page_title = "Duyuruları Yönet";
$csrf_token = generate_admin_csrf_token();
include_once __DIR__ . '/../templates/admin_header.php';

// İşlem (Ekleme/Güncelleme/Silme)
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// duyuru_id'yi GET veya POST'tan alıp doğrulama
$duyuru_id_raw = $_POST['duyuru_id'] ?? $_GET['duyuru_id'] ?? null;
$duyuru_id = null;
if ($duyuru_id_raw !== null) {
    $duyuru_id_validated = filter_var($duyuru_id_raw, FILTER_VALIDATE_INT);
    if ($duyuru_id_validated !== false) { // 0 da geçerli bir ID olabilir (genelde olmaz ama filter_var öyle döner)
        $duyuru_id = $duyuru_id_validated;
    }
}


// Silme İşlemi
if ($action === 'delete' && $duyuru_id !== null) { // $duyuru_id'nin null olmadığını kontrol et
    if (isset($_POST['confirm_delete']) && verify_admin_csrf_token($_POST['csrf_token'])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM duyurular WHERE id = ?");
            $stmt->execute([$duyuru_id]);
            set_admin_flash_message('success', 'Duyuru başarıyla silindi.');
        } catch (PDOException $e) {
            set_admin_flash_message('error', 'Duyuru silinirken hata: ' . $e->getMessage());
        }
        header("Location: manage_duyurular.php");
        exit;
    }
}


// Ekleme/Güncelleme İşlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'add' || $action === 'edit')) {
    if (verify_admin_csrf_token($_POST['csrf_token'])) {
        $baslik = trim($_POST['baslik'] ?? '');
        $icerik = trim($_POST['icerik'] ?? '');
        $aktif_mi = isset($_POST['aktif_mi']) ? 1 : 0;
        // Düzenleme için duyuru_id'yi POST'tan al (formdan gönderildiği için)
        $posted_duyuru_id_for_edit = null;
        if ($action === 'edit') {
            $posted_duyuru_id_raw = $_POST['duyuru_id'] ?? null;
             if ($posted_duyuru_id_raw !== null) {
                $posted_duyuru_id_validated = filter_var($posted_duyuru_id_raw, FILTER_VALIDATE_INT);
                if ($posted_duyuru_id_validated !== false) {
                    $posted_duyuru_id_for_edit = $posted_duyuru_id_validated;
                }
            }
        }


        if (empty($baslik) || empty($icerik)) {
            set_admin_flash_message('error', 'Başlık ve içerik alanları boş bırakılamaz.');
        } elseif ($action === 'edit' && $posted_duyuru_id_for_edit === null) {
            set_admin_flash_message('error', 'Düzenlenecek duyuru ID\'si bulunamadı.');
        } else {
            try {
                if ($action === 'add') {
                    $stmt = $pdo->prepare("INSERT INTO duyurular (baslik, icerik, aktif_mi) VALUES (?, ?, ?)");
                    $stmt->execute([$baslik, $icerik, $aktif_mi]);
                    set_admin_flash_message('success', 'Duyuru başarıyla eklendi.');
                } elseif ($action === 'edit' && $posted_duyuru_id_for_edit !== null) {
                    $stmt = $pdo->prepare("UPDATE duyurular SET baslik = ?, icerik = ?, aktif_mi = ? WHERE id = ?");
                    $stmt->execute([$baslik, $icerik, $aktif_mi, $posted_duyuru_id_for_edit]);
                    set_admin_flash_message('success', 'Duyuru başarıyla güncellendi.');
                }
                header("Location: manage_duyurular.php");
                exit;
            } catch (PDOException $e) {
                set_admin_flash_message('error', 'İşlem sırasında veritabanı hatası: ' . $e->getMessage());
            }
        }
    } else {
        set_admin_flash_message('error', 'Geçersiz CSRF token.');
    }
    // Hata durumunda formu tekrar doldurmak için verileri sakla
    $_SESSION['form_data'] = $_POST;
    // Eğer düzenleme ise ve ID varsa, URL'ye ekle
    $redirect_url = "manage_duyurular.php";
    if ($action === 'edit' && isset($_POST['duyuru_id'])) {
        $redirect_url .= "?action=edit_form&duyuru_id=" . (int)$_POST['duyuru_id'];
    } else {
        $redirect_url .= "?action=add_form";
    }
    header("Location: " . $redirect_url);
    exit;
}

// Düzenleme veya silme formu için duyuru bilgilerini çek
$edit_duyuru = null;
if (($action === 'edit_form' || $action === 'delete_form') && $duyuru_id !== null) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM duyurular WHERE id = ?");
        $stmt->execute([$duyuru_id]);
        $edit_duyuru = $stmt->fetch();
        if (!$edit_duyuru && $action === 'edit_form') {
             set_admin_flash_message('error', 'Düzenlenecek duyuru bulunamadı.');
             header("Location: manage_duyurular.php");
             exit;
        }
    } catch (PDOException $e) {
        set_admin_flash_message('error', 'Duyuru bilgileri alınırken hata: ' . $e->getMessage());
    }
}
// Hatalı form gönderiminden sonra verileri geri yükle
if (isset($_SESSION['form_data'])) {
    $form_data = $_SESSION['form_data'];
    unset($_SESSION['form_data']);
    
    $form_action = $form_data['action'] ?? ''; // Formdan gelen action
    $form_duyuru_id = isset($form_data['duyuru_id']) ? filter_var($form_data['duyuru_id'], FILTER_VALIDATE_INT) : null;

    // Eğer mevcut action edit_form ise ve form_data'dan gelen ID ile URL'deki ID eşleşiyorsa
    if ($action === 'edit_form' && $duyuru_id !== null && $form_action === 'edit' && $form_duyuru_id === $duyuru_id) {
        $edit_duyuru['baslik'] = $form_data['baslik'] ?? ($edit_duyuru['baslik'] ?? '');
        $edit_duyuru['icerik'] = $form_data['icerik'] ?? ($edit_duyuru['icerik'] ?? '');
        $edit_duyuru['aktif_mi'] = isset($form_data['aktif_mi']) ? 1 : 0;
    } elseif ($action === 'add_form' && $form_action === 'add') { // Eğer mevcut action add_form ise
         $edit_duyuru = ['baslik' => $form_data['baslik'] ?? '', 'icerik' => $form_data['icerik'] ?? '', 'aktif_mi' => isset($form_data['aktif_mi']) ? 1 : 0];
    }
}


// Duyuruları Listele
try {
    $stmt_list = $pdo->query("SELECT * FROM duyurular ORDER BY olusturulma_tarihi DESC");
    $duyurular = $stmt_list->fetchAll();
} catch (PDOException $e) {
    set_admin_flash_message('error', "Duyurular listelenirken hata: " . $e->getMessage());
    $duyurular = [];
}

?>

<div class="admin-page-title">Duyuru Yönetimi</div>

<?php if ($action === 'add_form' || ($action === 'edit_form' && $edit_duyuru)): ?>
    <h3><?php echo $action === 'add_form' ? 'Yeni Duyuru Ekle' : 'Duyuruyu Düzenle'; ?></h3>
    <form action="manage_duyurular.php" method="POST" style="padding:15px; background-color:#f9f9f9; border-radius:5px; margin-bottom:20px;">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <input type="hidden" name="action" value="<?php echo $action === 'add_form' ? 'add' : 'edit'; ?>">
        <?php if ($action === 'edit_form' && $edit_duyuru && isset($edit_duyuru['id'])): ?>
            <input type="hidden" name="duyuru_id" value="<?php echo $edit_duyuru['id']; ?>">
        <?php endif; ?>
        
        <div class="form-group">
            <label for="baslik">Başlık:</label>
            <input type="text" id="baslik" name="baslik" class="input-admin" value="<?php echo escape_html($edit_duyuru['baslik'] ?? ''); ?>" required>
        </div>
        <div class="form-group">
            <label for="icerik">İçerik (HTML kullanılabilir):</label>
            <textarea id="icerik" name="icerik" class="input-admin" rows="5" required><?php echo escape_html($edit_duyuru['icerik'] ?? ''); ?></textarea>
        </div>
        <div class="form-group">
            <label for="aktif_mi">
                <input type="checkbox" id="aktif_mi" name="aktif_mi" value="1" <?php echo (isset($edit_duyuru['aktif_mi']) && $edit_duyuru['aktif_mi'] == 1) ? 'checked' : ($action === 'add_form' && (!isset($edit_duyuru) || $edit_duyuru['aktif_mi'] !== 0) ? 'checked' : ''); ?>>
                Aktif (Kullanıcı panosunda görünsün mü?)
            </label>
        </div>
        <button type="submit" class="btn-admin green"><?php echo $action === 'add_form' ? 'Ekle' : 'Güncelle'; ?></button>
        <a href="manage_duyurular.php" class="btn-admin yellow" style="margin-left: 10px;">İptal</a>
    </form>
<?php elseif ($action === 'delete_form' && $edit_duyuru): ?>
    <h3>Duyuruyu Sil Onayı</h3>
    <p>Aşağıdaki duyuruyu silmek istediğinizden emin misiniz?</p>
    <p><strong>Başlık:</strong> <?php echo escape_html($edit_duyuru['baslik']); ?></p>
    <form action="manage_duyurular.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="duyuru_id" value="<?php echo $edit_duyuru['id']; ?>">
        <input type="hidden" name="confirm_delete" value="1">
        <button type="submit" class="btn-admin red">Evet, Sil</button>
        <a href="manage_duyurular.php" class="btn-admin yellow" style="margin-left: 10px;">Hayır, İptal Et</a>
    </form>
<?php else: ?>
    <p><a href="manage_duyurular.php?action=add_form" class="btn-admin green">Yeni Duyuru Ekle</a></p>
<?php endif; ?>


<h3 class="mt-4">Mevcut Duyurular</h3>
<?php if (empty($duyurular)): ?>
    <p class="message-box info">Henüz hiç duyuru eklenmemiş.</p>
<?php else: ?>
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Başlık</th>
                <th>Aktif Mi?</th>
                <th>Oluşturulma Tarihi</th>
                <th>İşlemler</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($duyurular as $duyuru): ?>
            <tr>
                <td><?php echo $duyuru['id']; ?></td>
                <td><?php echo escape_html($duyuru['baslik']); ?></td>
                <td><?php echo $duyuru['aktif_mi'] ? '<span style="color:green;">Evet</span>' : '<span style="color:red;">Hayır</span>'; ?></td>
                <td><?php echo format_tr_datetime($duyuru['olusturulma_tarihi']); ?></td>
                <td class="actions">
                    <a href="manage_duyurular.php?action=edit_form&duyuru_id=<?php echo $duyuru['id']; ?>" class="btn-admin yellow">Düzenle</a>
                    <a href="manage_duyurular.php?action=delete_form&duyuru_id=<?php echo $duyuru['id']; ?>" class="btn-admin red">Sil</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php
include_once __DIR__ . '/../templates/admin_footer.php';
?>
