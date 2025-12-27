<?php
// admin/manage_duyurular.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAdminLogin();
$page_title = "Duyuru Yönetimi";
$csrf_token = generate_admin_csrf_token();

$action = $_GET['action'] ?? 'list';
$duyuru_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// --- İŞLEMLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_admin_csrf_token($_POST['csrf_token'])) {
        set_admin_flash_message('error', 'Güvenlik doğrulaması başarısız.');
    } else {
        $baslik = trim($_POST['baslik'] ?? '');
        $icerik = trim($_POST['icerik'] ?? '');
        $aktif_mi = isset($_POST['aktif_mi']) ? 1 : 0;
        $p_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

        if (empty($baslik) || empty($icerik)) {
            set_admin_flash_message('error', 'Lütfen tüm alanları doldurun.');
        } else {
            try {
                if ($p_id) { // Güncelleme
                    $stmt = $pdo->prepare("UPDATE duyurular SET baslik = ?, icerik = ?, aktif_mi = ? WHERE id = ?");
                    $stmt->execute([$baslik, $icerik, $aktif_mi, $p_id]);
                    set_admin_flash_message('success', 'Duyuru başarıyla güncellendi.');
                } else { // Ekleme
                    $stmt = $pdo->prepare("INSERT INTO duyurular (baslik, icerik, aktif_mi) VALUES (?, ?, ?)");
                    $stmt->execute([$baslik, $icerik, $aktif_mi]);
                    set_admin_flash_message('success', 'Yeni duyuru yayınlandı.');
                }
                redirect('manage_duyurular.php');
            } catch (PDOException $e) {
                set_admin_flash_message('error', 'Hata: ' . $e->getMessage());
            }
        }
    }
}

// Silme İşlemi
if ($action === 'delete' && $duyuru_id) {
    $pdo->prepare("DELETE FROM duyurular WHERE id = ?")->execute([$duyuru_id]);
    set_admin_flash_message('success', 'Duyuru sistemden kaldırıldı.');
    redirect('manage_duyurular.php');
}

// Düzenleme Verisi
$edit_data = ['baslik' => '', 'icerik' => '', 'aktif_mi' => 1, 'id' => null];
if ($action === 'edit' && $duyuru_id) {
    $stmt = $pdo->prepare("SELECT * FROM duyurular WHERE id = ?");
    $stmt->execute([$duyuru_id]);
    $res = $stmt->fetch();
    if ($res) $edit_data = $res;
}

// Listeleme
$duyurular = $pdo->query("SELECT * FROM duyurular ORDER BY id DESC")->fetchAll();

include_once __DIR__ . '/../templates/admin_header.php';
?>

<div class="row g-4">
    <!-- SOL: Form Alanı -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-4 sticky-top" style="top: 100px;">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold text-theme-primary">
                    <i class="fas <?php echo $edit_data['id'] ? 'fa-edit' : 'fa-plus-circle'; ?> me-2"></i>
                    <?php echo $edit_data['id'] ? 'Duyuruyu Düzenle' : 'Yeni Duyuru Oluştur'; ?>
                </h6>
            </div>
            <div class="card-body p-4">
                <form action="manage_duyurular.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <?php if($edit_data['id']): ?>
                        <input type="hidden" name="id" value="<?php echo $edit_data['id']; ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">DUYURU BAŞLIĞI</label>
                        <input type="text" name="baslik" class="form-control input-theme" value="<?php echo escape_html($edit_data['baslik']); ?>" placeholder="Örn: Yeni Sınav Tarihi Hakkında" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">MESAJ İÇERİĞİ</label>
                        <textarea name="icerik" class="form-control input-theme" rows="6" placeholder="Öğrencilere iletmek istediğiniz mesaj..." required><?php echo escape_html($edit_data['icerik']); ?></textarea>
                    </div>

                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" name="aktif_mi" value="1" id="activeCheck" <?php echo $edit_data['aktif_mi'] ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-bold small" for="activeCheck">YAYINDA (ÖĞRENCİLER GÖREBİLİR)</label>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-theme-primary py-2 shadow-sm">
                            <i class="fas fa-save me-2"></i> Duyuruyu Kaydet
                        </button>
                        <?php if($edit_data['id']): ?>
                            <a href="manage_duyurular.php" class="btn btn-light border py-2">Vazgeç</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- SAĞ: Liste Alanı -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold">Mevcut Duyurular</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th class="ps-4">Duyuru</th>
                                <th class="text-center">Durum</th>
                                <th class="text-end pe-4">İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($duyurular)): ?>
                                <tr><td colspan="3" class="text-center py-5 text-muted small">Henüz bir duyuru eklenmemiş.</td></tr>
                            <?php else: ?>
                                <?php foreach($duyurular as $d): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold small mb-1"><?php echo escape_html($d['baslik']); ?></div>
                                        <div class="text-muted" style="font-size: 0.7rem;">
                                            <i class="far fa-calendar-alt me-1"></i> <?php echo date('d.m.Y H:i', strtotime($d['olusturulma_tarihi'])); ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <?php if($d['aktif_mi']): ?>
                                            <span class="badge bg-success-subtle text-success rounded-pill px-3">Yayında</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary rounded-pill px-3">Pasif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="btn-group">
                                            <a href="manage_duyurular.php?action=edit&id=<?php echo $d['id']; ?>" class="btn btn-sm btn-outline-primary" title="Düzenle">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="manage_duyurular.php?action=delete&id=<?php echo $d['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Bu duyuru kalıcı olarak silinecek. Emin misiniz?')" title="Sil">
                                                <i class="fas fa-trash"></i>
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
    </div>
</div>

<?php include_once __DIR__ . '/../templates/admin_footer.php'; ?>