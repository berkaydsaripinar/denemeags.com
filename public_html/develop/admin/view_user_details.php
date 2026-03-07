<?php
// admin/view_user_details.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAdminLogin();

$user_id_to_view = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
if (!$user_id_to_view) {
    set_admin_flash_message('error', 'Geçersiz kullanıcı ID.');
    header("Location: view_kullanicilar.php");
    exit;
}

try {
    // Kullanıcı Bilgileri
    $stmt_user = $pdo->prepare("SELECT * FROM kullanicilar WHERE id = ?");
    $stmt_user->execute([$user_id_to_view]);
    $user_info = $stmt_user->fetch();

    if (!$user_info) {
        set_admin_flash_message('error', 'Kullanıcı bulunamadı.');
        header("Location: view_kullanicilar.php");
        exit;
    }

    // Katıldığı Sınavlar
    $stmt_participations = $pdo->prepare("
        SELECT 
            kk.*, d.deneme_adi, d.tur, d.soru_sayisi
        FROM kullanici_katilimlari kk
        JOIN denemeler d ON kk.deneme_id = d.id
        WHERE kk.kullanici_id = ? AND kk.sinav_tamamlama_tarihi IS NOT NULL
        ORDER BY kk.sinav_tamamlama_tarihi DESC
    ");
    $stmt_participations->execute([$user_id_to_view]);
    $participations = $stmt_participations->fetchAll();

} catch (PDOException $e) {
    set_admin_flash_message('error', "Veri yüklenirken hata: " . $e->getMessage());
    $participations = [];
}

$page_title = "Öğrenci Analizi: " . $user_info['ad_soyad'];
include_once __DIR__ . '/../templates/admin_header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="view_kullanicilar.php">Öğrenciler</a></li>
                <li class="breadcrumb-item active">Detay</li>
            </ol>
        </nav>
        <h3 class="fw-bold mb-0 text-theme-primary"><?php echo escape_html($user_info['ad_soyad']); ?></h3>
    </div>
    <div class="col-auto">
        <a href="edit_kullanici.php?user_id=<?php echo $user_id_to_view; ?>" class="btn btn-outline-secondary">
            <i class="fas fa-user-edit me-2"></i> Profili Düzenle
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Sol Kolon: Bilgi Kartı -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4 text-center">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user_info['ad_soyad']); ?>&size=128&background=random&color=fff" class="rounded-circle mb-3 shadow-sm" width="100">
                <h5 class="fw-bold mb-1"><?php echo escape_html($user_info['ad_soyad']); ?></h5>
                <p class="text-muted small mb-3"><?php echo escape_html($user_info['email']); ?></p>
                
                <div class="d-flex justify-content-center gap-2 mb-3">
                    <?php if($user_info['aktif_mi']): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3">Aktif Öğrenci</span>
                    <?php else: ?>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3">Pasif</span>
                    <?php endif; ?>
                </div>
                
                <hr class="opacity-10">
                
                <div class="text-start">
                    <div class="small text-muted mb-1">Kayıt Tarihi</div>
                    <div class="fw-bold small mb-3"><?php echo date('d.m.Y H:i', strtotime($user_info['kayit_tarihi'])); ?></div>
                    
                    <div class="small text-muted mb-1">Toplam Sınav Katılımı</div>
                    <div class="fw-bold small"><?php echo count($participations); ?> Deneme</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sağ Kolon: Sınav Geçmişi -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="mb-0 fw-bold text-theme-primary">Sınav Performans Geçmişi</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small text-uppercase">
                            <tr>
                                <th class="ps-4">Deneme Adı</th>
                                <th class="text-center">D / Y / B</th>
                                <th class="text-center">Net</th>
                                <th class="text-center">Puan (Çan)</th>
                                <th class="text-end pe-4">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($participations)): ?>
                                <tr><td colspan="5" class="text-center py-5 text-muted">Öğrenci henüz bir sınavı tamamlamamış.</td></tr>
                            <?php else: ?>
                                <?php foreach($participations as $katilim): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold small"><?php echo escape_html($katilim['deneme_adi']); ?></div>
                                        <div class="text-muted" style="font-size: 0.7rem;"><?php echo date('d.m.Y', strtotime($katilim['sinav_tamamlama_tarihi'])); ?></div>
                                    </td>
                                    <td class="text-center">
                                        <span class="text-success fw-bold"><?php echo $katilim['dogru_sayisi']; ?></span> /
                                        <span class="text-danger fw-bold"><?php echo $katilim['yanlis_sayisi']; ?></span> /
                                        <span class="text-muted"><?php echo $katilim['bos_sayisi']; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-primary rounded-pill px-3"><?php echo number_format($katilim['net_sayisi'], 2); ?></span>
                                    </td>
                                    <td class="text-center">
                                        <div class="fw-bold text-dark"><?php echo number_format($katilim['puan'], 2); ?></div>
                                        <?php if($katilim['puan_can_egrisi']): ?>
                                            <div class="text-theme-primary fw-bold small" style="font-size: 0.7rem;">Çan: <?php echo number_format($katilim['puan_can_egrisi'], 2); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <a href="edit_user_answers.php?katilim_id=<?php echo $katilim['id']; ?>" class="btn btn-sm btn-light border" title="Cevapları Düzenle">
                                            <i class="fas fa-edit text-primary"></i>
                                        </a>
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