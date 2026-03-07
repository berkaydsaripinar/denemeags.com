<?php
// yazar.php - Gelişmiş Yazar Profili
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) redirect('store.php');

try {
    // 1. Yazar Verileri
    $stmt_y = $pdo->prepare("SELECT * FROM yazarlar WHERE id = ? AND aktif_mi = 1");
    $stmt_y->execute([$id]);
    $yazar = $stmt_y->fetch();
    if (!$yazar) redirect('store.php');

    // 2. Yazara Ait Eserler
    $stmt_p = $pdo->prepare("SELECT * FROM denemeler WHERE yazar_id = ? AND aktif_mi = 1 ORDER BY id DESC");
    $stmt_p->execute([$id]);
    $eserler = $stmt_p->fetchAll();

    // 3. İstatistikler (Mock)
    $student_count = ($id * 27) + 450;

} catch (PDOException $e) { redirect('store.php'); }

$page_title = $yazar['ad_soyad'] . " | Yazar Profili";
include_once __DIR__ . '/templates/header.php';
?>

<style>
    .author-hero { background: #1F3C88; color: white; padding: 100px 0 60px; border-radius: 0 0 50px 50px; position: relative; }
    .profile-img-wrap { width: 160px; height: 160px; margin-top: -80px; position: relative; }
    .profile-img { width: 100%; height: 100%; border-radius: 50%; border: 8px solid #fff; box-shadow: 0 10px 30px rgba(0,0,0,0.1); object-fit: cover; background: #fff; }
    .stat-pill { background: #f0f4f8; padding: 15px 30px; border-radius: 20px; display: inline-block; text-align: center; border: 1px solid #e2e8f0; }
    .stat-val { display: block; font-weight: 800; font-size: 1.4rem; color: #1F3C88; }
    .stat-lbl { font-size: 0.75rem; font-weight: 700; color: #666; text-transform: uppercase; }
</style>

<div class="author-hero">
    <div class="container text-center">
        <h1 class="display-6 fw-black"><?php echo escape_html($yazar['ad_soyad']); ?></h1>
        <p class="lead opacity-75"><?php echo escape_html($yazar['uzmanlik_alani'] ?: 'Kıdemli Eğitimci & Yazar'); ?></p>
        
        <?php if(!empty($yazar['instagram_link'])): ?>
            <a href="<?php echo $yazar['instagram_link']; ?>" target="_blank" class="btn btn-outline-light rounded-pill px-4 mt-3">
                <i class="fab fa-instagram me-2"></i>Instagram'da Takip Et
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="container mb-5">
    <div class="row justify-content-center">
        <div class="col-auto text-center">
            <div class="profile-img-wrap mx-auto">
                <img src="<?php echo !empty($yazar['profil_resmi']) ? $yazar['profil_resmi'] : 'https://ui-avatars.com/api/?name='.urlencode($yazar['ad_soyad']).'&size=160&background=FF6F61&color=fff'; ?>" class="profile-img">
            </div>
            
            <div class="mt-4 d-flex gap-3 justify-content-center">
                <div class="stat-pill shadow-sm">
                    <span class="stat-val"><?php echo count($eserler); ?></span>
                    <span class="stat-lbl">Yayın</span>
                </div>
                <div class="stat-pill shadow-sm">
                    <span class="stat-val"><?php echo number_format($student_count); ?>+</span>
                    <span class="stat-lbl">Öğrenci</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row justify-content-center mt-5">
        <div class="col-lg-8 text-center">
            <p class="text-muted fs-5 leading-relaxed">
                <?php echo nl2br(escape_html($yazar['biyografi'] ?: 'DenemeAGS platformunda nitelikli içerikleriyle öğrencilerimize destek vermektedir.')); ?>
            </p>
        </div>
    </div>

    <div class="mt-5 pt-5 border-top">
        <h3 class="fw-bold mb-5 text-center">Yazarın Tüm Yayınları</h3>
        <div class="row g-4">
            <?php foreach($eserler as $u): ?>
                <div class="col-md-4">
                    <div class="card product-card h-100 shadow-sm border-0 rounded-4 overflow-hidden">
                        <div class="card-body p-4 d-flex flex-column">
                            <span class="badge bg-light text-primary mb-3 align-self-start border rounded-pill"><?php echo strtoupper($u['tur']); ?></span>
                            <h5 class="fw-bold text-dark mb-4 h6"><?php echo escape_html($u['deneme_adi']); ?></h5>
                            <div class="mt-auto d-flex justify-content-between align-items-center">
                                <span class="fw-black text-primary fs-5"><?php echo number_format($u['fiyat'], 2); ?> ₺</span>
                                <a href="urun.php?id=<?php echo $u['id']; ?>" class="btn btn-theme-primary btn-sm rounded-pill px-4 fw-bold">İNCELE</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/templates/footer.php'; ?>