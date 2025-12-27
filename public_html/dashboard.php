<?php
// dashboard.php - Öğrenci Kontrol Paneli (Gelişmiş UI ve Yeni Özellikler)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

// Giriş kontrolü
if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$page_title = "Öğrenci Paneli";
$csrf_token = generate_csrf_token(); // CSRF Güvenliği için

// Değişkenleri varsayılanlarla başlat
$kutuphane = [];
$history = [];
$duyurular = [];
$total_products = 0;
$total_exams_taken = 0;
$avg_net = 0;

try {
    // 1. Kütüphanedeki Ürünleri Çek
    $stmt_erisim = $pdo->prepare("
        SELECT d.*, y.ad_soyad as yazar_adi, ke.erisim_tarihi 
        FROM kullanici_erisimleri ke
        JOIN denemeler d ON ke.deneme_id = d.id
        LEFT JOIN yazarlar y ON d.yazar_id = y.id
        WHERE ke.kullanici_id = ? AND d.aktif_mi = 1
        ORDER BY ke.erisim_tarihi DESC
    ");
    $stmt_erisim->execute([$user_id]);
    $kutuphane = $stmt_erisim->fetchAll();

    // 2. Sınav Geçmişini Çek
    $stmt_history = $pdo->prepare("
        SELECT kk.*, d.deneme_adi, d.soru_sayisi, d.sonuc_aciklama_tarihi
        FROM kullanici_katilimlari kk
        JOIN denemeler d ON kk.deneme_id = d.id
        WHERE kk.kullanici_id = ? AND kk.sinav_tamamlama_tarihi IS NOT NULL
        ORDER BY kk.sinav_tamamlama_tarihi DESC
    ");
    $stmt_history->execute([$user_id]);
    $history = $stmt_history->fetchAll();

    // 3. Aktif Duyuruları Çek
    $stmt_duyuru = $pdo->query("SELECT * FROM duyurular WHERE aktif_mi = 1 ORDER BY olusturulma_tarihi DESC LIMIT 3");
    $duyurular = $stmt_duyuru->fetchAll();

    // 4. İstatistikleri Hesapla
    $total_products = count($kutuphane);
    $total_exams_taken = count($history);
    
    if ($total_exams_taken > 0) {
        $sum_net = array_sum(array_column($history, 'net_sayisi'));
        $avg_net = $sum_net / $total_exams_taken;
    }

} catch (PDOException $e) {
    error_log("Dashboard veri hatası: " . $e->getMessage());
}

include_once __DIR__ . '/templates/header.php';
?>

<style>
    :root {
        --dash-primary: #1F3C88;
        --dash-accent: #FF6F61;
        --dash-bg: #f4f7fa;
        --card-border: #eaedf3;
    }
    body { background-color: var(--dash-bg); }

    /* Karşılama Kartı */
    .welcome-card {
        background: linear-gradient(135deg, var(--dash-primary) 0%, #3a58e0 100%);
        border-radius: 20px;
        padding: 35px;
        color: white;
        position: relative;
        overflow: hidden;
        box-shadow: 0 10px 25px rgba(31, 60, 136, 0.2);
    }
    .welcome-card::after {
        content: '\f5da';
        font-family: 'Font Awesome 6 Free';
        font-weight: 900;
        position: absolute;
        right: -20px;
        bottom: -30px;
        font-size: 10rem;
        opacity: 0.1;
        transform: rotate(-15deg);
    }

    /* İstatistik Hapları */
    .stat-card {
        background: #fff;
        border-radius: 18px;
        padding: 20px;
        border: 1px solid var(--card-border);
        transition: 0.3s;
    }
    .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.05); }
    .stat-icon-box {
        width: 40px; height: 40px;
        border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        margin-bottom: 12px;
    }

    /* Duyuru Alanı */
    .announcement-bar {
        background: #fff;
        border-radius: 15px;
        border-left: 5px solid var(--dash-accent);
        padding: 15px 20px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.03);
    }

    /* Yayın Kartları */
    .dash-product-card {
        border: 1px solid var(--card-border);
        border-radius: 18px;
        background: #fff;
        transition: 0.3s;
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    .dash-product-card:hover { border-color: var(--dash-primary); box-shadow: 0 12px 30px rgba(31, 60, 136, 0.08); }
    .card-img-container { height: 160px; overflow: hidden; border-radius: 17px 17px 0 0; }
    .card-img-container img { width: 100%; height: 100%; object-fit: cover; }

    /* Tab Stilize */
    .nav-pills-custom .nav-link {
        color: var(--dash-primary);
        font-weight: 700;
        padding: 10px 25px;
        border-radius: 50px;
        border: 1px solid #dee2e6;
        margin: 0 5px;
        background: #fff;
    }
    .nav-pills-custom .nav-link.active {
        background-color: var(--dash-primary) !important;
        color: #fff !important;
        border-color: var(--dash-primary);
    }

    /* Badge Renkleri */
    .bg-soft-primary { background: rgba(31, 60, 136, 0.1); color: var(--dash-primary); }
    .bg-soft-success { background: rgba(25, 135, 84, 0.1); color: #198754; }
    .bg-soft-warning { background: rgba(255, 111, 97, 0.1); color: var(--dash-accent); }

    @media (max-width: 768px) {
        .welcome-card { padding: 25px; text-align: center; }
        .nav-pills-custom { display: flex; width: 100%; overflow-x: auto; padding-bottom: 10px; }
        .nav-pills-custom .nav-item { flex: 1; min-width: 140px; }
    }
</style>

<div class="container py-4 py-md-5">

    <!-- 1. Karşılama ve Duyurular -->
    <div class="row g-4 mb-5">
        <div class="col-lg-8">
            <div class="welcome-card h-100">
                <h2 class="fw-bold mb-2">Selam, <?php echo escape_html(explode(' ', $_SESSION['user_ad_soyad'] ?? 'Öğrenci')[0]); ?>! 👋</h2>
                <p class="opacity-75 mb-4">Sınav hazırlık yolculuğunda bugün yeni bir başarıya daha imza atmaya ne dersin?</p>
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-warning rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addCodeModal">
                        <i class="fas fa-key me-2"></i>Yeni Kod Tanımla
                    </button>
                    <a href="index.php" class="btn btn-light rounded-pill px-4 fw-bold text-primary">
                        <i class="fas fa-shopping-cart me-2"></i>Mağaza
                    </a>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="d-flex flex-column gap-3 h-100 justify-content-center">
                <h6 class="fw-bold text-muted text-uppercase mb-0 small ls-1"><i class="fas fa-bullhorn me-2"></i>Son Duyurular</h6>
                <?php if(empty($duyurular)): ?>
                    <div class="announcement-bar py-4 text-center">
                        <small class="text-muted">Şu an aktif bir duyuru yok.</small>
                    </div>
                <?php else: ?>
                    <?php foreach($duyurular as $d): ?>
                        <div class="announcement-bar">
                            <h6 class="mb-1 fw-bold small text-dark"><?php echo escape_html($d['baslik']); ?></h6>
                            <p class="mb-0 text-muted" style="font-size: 0.75rem;"><?php echo mb_substr(strip_tags($d['icerik']), 0, 80); ?>...</p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 2. İstatistik Özetleri -->
    <div class="row g-3 g-md-4 mb-5">
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon-box bg-soft-primary"><i class="fas fa-book"></i></div>
                <div class="small text-muted fw-bold">Kütüphanem</div>
                <div class="h4 fw-bold mb-0"><?php echo (int)$total_products; ?></div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon-box bg-soft-success"><i class="fas fa-check-double"></i></div>
                <div class="small text-muted fw-bold">Bitirilen Sınav</div>
                <div class="h4 fw-bold mb-0"><?php echo (int)$total_exams_taken; ?></div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon-box bg-soft-warning"><i class="fas fa-chart-line"></i></div>
                <div class="small text-muted fw-bold">Genel Net Ort.</div>
                <div class="h4 fw-bold mb-0"><?php echo number_format((float)$avg_net, 2); ?></div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon-box bg-light text-dark"><i class="fas fa-award"></i></div>
                <div class="small text-muted fw-bold">Sıralama</div>
                <div class="h4 fw-bold mb-0">-</div>
            </div>
        </div>
    </div>

    <!-- 3. İçerik Sekmeleri -->
    <div class="text-center mb-4">
        <ul class="nav nav-pills nav-pills-custom d-inline-flex shadow-sm p-1 bg-white rounded-pill" id="dashTab" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#library"><i class="fas fa-th-large me-2"></i>Kütüphanem</button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="pill" data-bs-target="#history"><i class="fas fa-poll me-2"></i>Sınav Geçmişim</button>
            </li>
        </ul>
    </div>

    <div class="tab-content" id="dashTabContent">
        <!-- SEKME: KÜTÜPHANEM -->
        <div class="tab-pane fade show active" id="library">
            <?php if (empty($kutuphane)): ?>
                <div class="text-center py-5 bg-white rounded-4 shadow-sm border border-dashed">
                    <i class="fas fa-box-open fa-3x text-muted mb-3 opacity-50"></i>
                    <h5 class="fw-bold text-muted">Kütüphanen henüz boş.</h5>
                    <p class="text-muted small">Erişim kodunu girerek dökümanlarını hemen ekleyebilirsin.</p>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($kutuphane as $item): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="dash-product-card">
                                <div class="card-img-container position-relative">
                                    <img src="<?php echo !empty($item['resim_url']) ? $item['resim_url'] : 'https://placehold.co/600x400/E0E7FF/4A69FF?text=Yayın+Görseli'; ?>" alt="Kapak">
                                    <span class="badge bg-dark bg-opacity-75 position-absolute top-0 start-0 m-3 px-3 py-2 rounded-pill small">
                                        <?php echo ($item['tur'] == 'deneme') ? 'Deneme' : 'Soru Bankası'; ?>
                                    </span>
                                </div>
                                <div class="card-body p-4">
                                    <h6 class="fw-bold text-dark mb-1"><?php echo escape_html($item['deneme_adi']); ?></h6>
                                    <p class="text-muted mb-4" style="font-size: 0.8rem;"><i class="fas fa-pen-nib text-primary me-1"></i> <?php echo escape_html($item['yazar_adi'] ?: SITE_NAME); ?></p>
                                    
                                    <div class="row g-2">
                                        <?php if (!empty($item['soru_kitapcik_dosyasi'])): ?>
                                            <div class="col-6">
                                                <a href="download_secure_pdf.php?id=<?php echo $item['id']; ?>&type=question" class="btn btn-outline-primary btn-sm w-100 rounded-pill fw-bold">
                                                    Soru Kitapçığı
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($item['cozum_linki'])): ?>
                                            <div class="col-6">
                                                <a href="download_secure_pdf.php?id=<?php echo $item['id']; ?>&type=solution" class="btn btn-outline-success btn-sm w-100 rounded-pill fw-bold">
                                                    Çözüm İndir
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($item['tur'] === 'deneme'): ?>
                                            <div class="col-12 mt-2">
                                                <form action="start_exam.php" method="POST">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <input type="hidden" name="deneme_id" value="<?php echo $item['id']; ?>">
                                                    <button type="submit" class="btn btn-theme-primary btn-sm w-100 rounded-pill fw-bold">
                                                        <i class="fas fa-edit me-2"></i>Optik Form ve Sonuçlar
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- SEKME: SINAV GEÇMİŞİM -->
        <div class="tab-pane fade" id="history">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 text-center">
                        <thead class="table-light">
                            <tr>
                                <th class="text-start ps-4 small fw-bold">DENEME DETAYI</th>
                                <th class="small fw-bold">D / Y / B</th>
                                <th class="small fw-bold">NET</th>
                                <th class="small fw-bold">PUAN</th>
                                <th class="small fw-bold">TARİH</th>
                                <th class="text-end pe-4 small fw-bold">AKSİYON</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($history)): ?>
                                <tr><td colspan="6" class="py-5 text-muted small italic">Henüz tamamlanmış bir sınavın yok. Başarılar dileriz!</td></tr>
                            <?php else: ?>
                                <?php foreach ($history as $res): ?>
                                    <?php 
                                        $sonuc_aciklama_dt = new DateTime($res['sonuc_aciklama_tarihi'], new DateTimeZone('Europe/Istanbul'));
                                        $now = new DateTime('now', new DateTimeZone('Europe/Istanbul'));
                                        $sonuclar_hazir = ($now >= $sonuc_aciklama_dt);
                                    ?>
                                    <tr>
                                        <td class="text-start ps-4">
                                            <div class="fw-bold text-dark small"><?php echo escape_html($res['deneme_adi']); ?></div>
                                            <div class="text-muted" style="font-size: 0.7rem;"><?php echo $res['soru_sayisi']; ?> Soru</div>
                                        </td>
                                        <td>
                                            <span class="text-success fw-bold"><?php echo $res['dogru_sayisi']; ?></span> <span class="text-muted mx-1">/</span>
                                            <span class="text-danger fw-bold"><?php echo $res['yanlis_sayisi']; ?></span> <span class="text-muted mx-1">/</span>
                                            <span class="text-muted fw-bold"><?php echo $res['bos_sayisi']; ?></span>
                                        </td>
                                        <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3"><?php echo number_format((float)$res['net_sayisi'], 2); ?></span></td>
                                        <td class="fw-bold text-dark"><?php echo number_format((float)$res['puan'], 2); ?></td>
                                        <td class="text-muted small"><?php echo date('d.m.Y', strtotime($res['sinav_tamamlama_tarihi'])); ?></td>
                                        <td class="text-end pe-4">
                                            <div class="btn-group shadow-sm rounded-3">
                                                <?php if($sonuclar_hazir): ?>
                                                    <a href="results.php?katilim_id=<?php echo $res['id']; ?>" class="btn btn-sm btn-white border-end px-3 fw-bold text-primary" title="Detaylı Analiz"><i class="fas fa-chart-pie me-1"></i> Analiz</a>
                                                    <a href="indir_karne.php?katilim_id=<?php echo $res['id']; ?>" class="btn btn-sm btn-white px-2 text-danger" title="Karne İndir"><i class="fas fa-file-pdf"></i></a>
                                                <?php else: ?>
                                                    <button class="btn btn-sm btn-light border px-3 disabled small" title="Sonuçlar Hazırlanıyor..."><i class="fas fa-clock me-1"></i> Bekleniyor</button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if(!empty($history)): ?>
                <p class="mt-3 text-center text-muted small"><i class="fas fa-info-circle me-1"></i> Sıralama sonuçları deneme açıklanma tarihinde listene eklenir.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal: Yeni Kod Tanımlama -->
<div class="modal fade" id="addCodeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0 pe-4 pt-4">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 p-md-5 pt-2">
                <div class="text-center mb-4">
                    <div class="bg-primary bg-opacity-10 text-primary p-4 rounded-circle d-inline-block mb-3" style="width:80px; height:80px;">
                        <i class="fas fa-plus fa-2x"></i>
                    </div>
                    <h4 class="fw-bold">İçerik Kodunu Tanımla</h4>
                    <p class="text-muted small">Aldığınız aktivasyon kodunu buraya girerek yayını kütüphanenize anında ekleyebilirsiniz.</p>
                </div>
                <form action="add_product_with_code.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <div class="mb-4">
                        <input type="text" name="urun_kodu" class="form-control form-control-lg input-theme text-center fw-bold text-uppercase" placeholder="KODU BURAYA YAZIN" required autofocus>
                    </div>
                    <button type="submit" class="btn btn-theme-primary btn-lg w-100 shadow rounded-pill py-3">AKTİF ET</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/templates/footer.php'; ?>