<?php
// dashboard.php - Öğrenci Kontrol Paneli (Gelişmiş Video Geçiş Animasyonu Entegreli)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

// Giriş kontrolü
if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_ad_soyad = $_SESSION['user_ad_soyad'] ?? ($_SESSION['user_name'] ?? 'Değerli Öğrenci');
$page_title = "Kütüphanem ve Başarılarım";
$csrf_token = generate_csrf_token();

$license_no = "AGS-" . $user_id . "-" . date('Y') . "-" . strtoupper(substr(md5($user_id . SITE_NAME), 0, 4));

$kutuphane = [];
$history = [];
$duyurular = [];
$total_products = 0;
$total_exams_taken = 0;
$avg_net = 0;

try {
    // 1. Kütüphanedeki Ürünleri Çek
    $stmt_erisim = $pdo->prepare("
        SELECT d.*, y.ad_soyad as yazar_adi, ke.erisim_tarihi,
                kk.id as active_katilim_id, kk.sinav_tamamlama_tarihi as katilim_tamamlanma
        FROM kullanici_erisimleri ke
        JOIN denemeler d ON ke.deneme_id = d.id
        LEFT JOIN yazarlar y ON d.yazar_id = y.id
        LEFT JOIN kullanici_katilimlari kk ON d.id = kk.deneme_id AND kk.kullanici_id = ke.kullanici_id
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
        --hologram-gradient: linear-gradient(120deg, #ff00c1, #9600ff, #5b00ff, #00d4ff, #00ff9d, #00ff2a);
    }
    body { background-color: #f4f7fa; }

    /* --- PREMIUM HOLOGRAFIK KART TASARIMI --- */
    .card-perspective { perspective: 2000px; margin: 0 auto; width: 100%; max-width: 420px; }
    .holographic-card {
        width: 100%; height: 260px; 
        background: rgba(15, 23, 42, 0.98);
        backdrop-filter: blur(25px); border-radius: 30px; 
        border: 1px solid rgba(255, 255, 255, 0.15);
        position: relative; overflow: hidden; 
        transition: transform 0.1s ease-out;
        box-shadow: 0 40px 80px rgba(0,0,0,0.4); 
        cursor: pointer; padding: 40px;
    }
    .shimmer {
        position: absolute; top: 0; left: 0; width: 100%; height: 100%;
        background: var(--hologram-gradient); background-size: 400% 400%;
        opacity: 0.15; mix-blend-mode: color-dodge; pointer-events: none;
        animation: moveGradient 8s ease infinite;
    }
    @keyframes moveGradient { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
    .label-premium { font-family: 'Inter', sans-serif; font-size: 0.65rem; letter-spacing: 4px; color: #fff; opacity: 0.5; text-transform: uppercase; font-weight: 700; }
    .cert-user-name { font-size: 1.8rem; font-weight: 800; color: #fff; margin-top: 10px; background: linear-gradient(to bottom, #fff, #aaa); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
    .slogan-tag { color: #ff6f61; font-weight: 700; font-size: 0.75rem; letter-spacing: 1px; padding: 6px 16px; background: rgba(255, 111, 97, 0.1); border: 1px solid rgba(255, 111, 97, 0.2); border-radius: 12px; }
    .license-id-box { position: absolute; top: 35px; right: 35px; font-family: monospace; font-size: 0.65rem; color: #4A69FF; background: rgba(74, 105, 255, 0.1); padding: 3px 10px; border-radius: 6px; }
    .badge-verified { position: absolute; bottom: 35px; right: 35px; width: 70px; height: 70px; background: rgba(255, 255, 255, 0.05); border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 1px solid rgba(255, 255, 255, 0.1); }

    /* --- DASHBOARD UI --- */
    .welcome-card { background: linear-gradient(135deg, var(--dash-primary) 0%, #3a58e0 100%); border-radius: 24px; padding: 35px; color: white; box-shadow: 0 10px 30px rgba(31, 60, 136, 0.2); position: relative; overflow: hidden; }
    .welcome-card::after { content: '\f5da'; font-family: 'Font Awesome 6 Free'; font-weight: 900; position: absolute; right: -20px; bottom: -30px; font-size: 10rem; opacity: 0.1; transform: rotate(-15deg); }
    .stat-pill { background: #fff; border-radius: 20px; padding: 20px; border: 1px solid #eaedf3; transition: 0.3s; height: 100%; }
    .stat-pill:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.05); }
    .stat-icon-box { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-bottom: 12px; }
    .announcement-bar { background: #fff; border-radius: 15px; border-left: 5px solid var(--dash-accent); padding: 15px 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.03); }
    .nav-pills-custom .nav-link { color: var(--dash-primary); font-weight: 700; padding: 10px 25px; border-radius: 50px; border: 1px solid #dee2e6; margin: 0 5px; background: #fff; }
    .nav-pills-custom .nav-link.active { background-color: var(--dash-primary) !important; color: #fff !important; border-color: var(--dash-primary); }

    .badge-premium-btn { background: linear-gradient(90deg, #FF6F61, #FF9A8B); color: white; border: none; padding: 10px 24px; border-radius: 50px; font-weight: 700; transition: 0.3s; }
    .badge-premium-btn:hover { transform: scale(1.05); box-shadow: 0 10px 20px rgba(255, 111, 97, 0.3); }

    /* --- VIDEO GECIS ANIMASYONU (YENİ) --- */
    #videoLoaderOverlay {
        position: fixed; inset: 0; background: rgba(15, 23, 42, 0.95); backdrop-filter: blur(15px);
        z-index: 9999; display: none; align-items: center; justify-content: center; text-align: center; color: white;
    }
    .loader-content { max-width: 400px; width: 90%; }
    .dna-spinner {
        width: 80px; height: 80px; margin: 0 auto 30px; border: 4px solid rgba(255,255,255,0.1);
        border-top-color: var(--dash-accent); border-radius: 50%; animation: spin 1s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    .loader-step { font-size: 1.1rem; font-weight: 600; margin-bottom: 10px; opacity: 0; transform: translateY(10px); transition: all 0.4s ease; }
    .loader-step.active { opacity: 1; transform: translateY(0); }
    .loader-progress-bar { width: 100%; height: 4px; background: rgba(255,255,255,0.1); border-radius: 10px; overflow: hidden; margin-top: 20px; }
    .loader-progress-fill { width: 0%; height: 100%; background: var(--dash-accent); transition: width 0.3s ease; }
    
    .screenshot-mode .modal-header, .screenshot-mode .modal-footer, .screenshot-mode .btn-close { display: none !important; }
    .screenshot-mode .modal-content { background: #05070a !important; padding: 60px !important; border: none !important; }
</style>

<!-- Video Geçiş Overlay -->
<div id="videoLoaderOverlay">
    <div class="loader-content">
        <div class="dna-spinner"></div>
        <div id="loaderSteps">
            <div class="loader-step" id="step1">🔐 Güvenlik anahtarı üretiliyor...</div>
            <div class="loader-step" id="step2">🛡️ Erişim yetkisi doğrulanıyor...</div>
            <div class="loader-step" id="step3">🎬 Video akışı hazırlanıyor...</div>
            <div class="loader-step" id="step4">🚀 Yönlendiriliyorsunuz...</div>
        </div>
        <div class="loader-progress-bar">
            <div class="loader-progress-fill" id="loaderFill"></div>
        </div>
    </div>
</div>

<div class="container py-4 py-md-5">
    <!-- 1. Karşılama ve Duyurular -->
    <div class="row g-4 mb-5">
        <div class="col-lg-8">
            <div class="welcome-card h-100 d-flex flex-column justify-content-center">
                <div class="mb-4">
                    <?php 
                        $first_name = 'Öğrenci';
                        if(!empty($user_ad_soyad)) {
                            $parts = explode(' ', $user_ad_soyad);
                            $first_name = $parts[0] ?: 'Öğrenci';
                        }
                    ?>
                    <h2 class="fw-bold mb-2">Selam, <?php echo escape_html($first_name); ?>! 👋</h2>
                    <p class="opacity-75">Sınav hazırlık yolculuğunda bugün yeni bir başarıya daha imza atmaya ne dersin?</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button class="badge-premium-btn shadow" data-bs-toggle="modal" data-bs-target="#certModal">
                        <i class="fas fa-medal me-2 text-white"></i>ONUR NİŞANIMI GÖR
                    </button>
                    <button class="btn btn-light rounded-pill px-4 fw-bold text-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addCodeModal">
                        <i class="fas fa-key me-2"></i>Yeni Kod Tanımla
                    </button>
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
            <div class="stat-pill">
                <div class="stat-icon-box" style="background: rgba(31, 60, 136, 0.1); color: var(--dash-primary);"><i class="fas fa-book"></i></div>
                <div class="small text-muted fw-bold">Kütüphanem</div>
                <div class="h4 fw-bold mb-0 text-dark"><?php echo (int)$total_products; ?></div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-pill">
                <div class="stat-icon-box" style="background: rgba(25, 135, 84, 0.1); color: #198754;"><i class="fas fa-check-double"></i></div>
                <div class="small text-muted fw-bold">Bitirilen Sınav</div>
                <div class="h4 fw-bold mb-0 text-dark"><?php echo (int)$total_exams_taken; ?></div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-pill">
                <div class="stat-icon-box" style="background: rgba(255, 111, 97, 0.1); color: var(--dash-accent);"><i class="fas fa-chart-line"></i></div>
                <div class="small text-muted fw-bold">Genel Net Ort.</div>
                <div class="h4 fw-bold mb-0 text-dark"><?php echo number_format((float)$avg_net, 2); ?></div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-pill">
                <div class="stat-icon-box" style="background: #f8f9fa; color: #333;"><i class="fas fa-award"></i></div>
                <div class="small text-muted fw-bold">Başarı Puanı</div>
                <div class="h4 fw-bold mb-0 text-dark">Lvl. <?php echo floor($total_exams_taken / 2) + 1; ?></div>
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
                            <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden bg-white">
                                <div class="card-img-container position-relative" style="height: 160px; overflow: hidden; background: #eee;">
                                    <img src="<?php echo !empty($item['resim_url']) ? $item['resim_url'] : 'https://placehold.co/600x400/E0E7FF/4A69FF?text=DenemeAGS'; ?>" class="w-100 h-100 object-fit-cover">
                                    <span class="badge bg-dark bg-opacity-75 position-absolute top-0 start-0 m-3 px-3 py-2 rounded-pill small">
                                        <?php echo ($item['tur'] == 'deneme') ? '🏆 Deneme' : '📚 Soru Bankası'; ?>
                                    </span>
                                </div>
                                <div class="card-body p-4">
                                    <h6 class="fw-bold text-dark mb-1 h6"><?php echo escape_html($item['deneme_adi']); ?></h6>
                                    <p class="text-muted mb-4 small"><i class="fas fa-pen-nib text-primary me-1"></i> <?php echo escape_html($item['yazar_adi'] ?: 'Platform Kaynağı'); ?></p>
                                    
                                    <div class="d-grid gap-2">
                                        <a href="download_secure_pdf.php?id=<?php echo $item['id']; ?>&type=question" class="btn btn-outline-primary btn-sm rounded-pill fw-bold">Soru Kitapçığını İndir</a>
                                        
                                        <?php if($item['tur'] == 'deneme'): ?>
                                            <?php if ($item['active_katilim_id']): ?>
                                                <?php if ($item['katilim_tamamlanma']): ?>
                                                    <a href="results.php?katilim_id=<?php echo $item['active_katilim_id']; ?>" class="btn btn-success btn-sm rounded-pill fw-bold">Sonuçları Gör</a>
                                                <?php else: ?>
                                                    <a href="exam.php?katilim_id=<?php echo $item['active_katilim_id']; ?>" class="btn btn-warning btn-sm rounded-pill fw-bold text-dark">Sınava Devam Et</a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <form action="start_exam.php" method="POST">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <input type="hidden" name="deneme_id" value="<?php echo $item['id']; ?>">
                                                    <button type="submit" class="btn btn-theme-primary btn-sm rounded-pill fw-bold w-100">Sınava Başla / Optik Form</button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <?php if (!empty($item['cozum_linki'])): ?>
                                            <a href="download_secure_pdf.php?id=<?php echo $item['id']; ?>&type=solution" class="btn btn-outline-success btn-sm rounded-pill fw-bold">Çözüm Kitapçığını İndir</a>
                                        <?php endif; ?>

                                        <?php if (!empty($item['cozum_video_dosyasi'])): ?>
                                            <?php if ($item['tur'] == 'deneme' && empty($item['active_katilim_id'])): ?>
                                                <button class="btn btn-outline-warning btn-sm rounded-pill fw-bold" disabled>Video Çözümü İçin Sınava Başlayın</button>
                                            <?php else: ?>
                                                <?php
                                                    $video_url = ($item['tur'] == 'deneme' && $item['active_katilim_id'])
                                                        ? 'view_video_solution.php?katilim_id=' . $item['active_katilim_id']
                                                        : 'view_video_solution.php?deneme_id=' . $item['id'];
                                                ?>
                                                <a href="<?php echo $video_url; ?>" class="btn btn-outline-warning btn-sm rounded-pill fw-bold video-solution-link">Video Çözümü İzle</a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="tab-pane fade" id="history">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 text-center">
                        <thead class="table-light">
                            <tr>
                                <th class="text-start ps-4 small fw-bold">YAYIN BİLGİSİ</th>
                                <th class="small fw-bold">D / Y / B</th>
                                <th class="small fw-bold">NET</th>
                                <th class="small fw-bold">PUAN</th>
                                <th class="small fw-bold">TARİH</th>
                                <th class="text-end pe-4 small fw-bold">İŞLEMLER</th>
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
                                            <span class="text-success fw-bold"><?php echo $res['dogru_sayisi']; ?></span> /
                                            <span class="text-danger fw-bold"><?php echo $res['yanlis_sayisi']; ?></span> /
                                            <span class="text-muted fw-bold"><?php echo $res['bos_sayisi']; ?></span>
                                        </td>
                                        <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3"><?php echo number_format((float)$res['net_sayisi'], 2); ?></span></td>
                                        <td class="fw-bold text-dark"><?php echo number_format((float)$res['puan'], 2); ?></td>
                                        <td class="text-muted small"><?php echo date('d.m.Y', strtotime($res['sinav_tamamlama_tarihi'])); ?></td>
                                        <td class="text-end pe-4">
                                            <div class="btn-group shadow-sm rounded-3">
                                                <?php if($sonuclar_hazir): ?>
                                                    <a href="results.php?katilim_id=<?php echo $res['id']; ?>" class="btn btn-sm btn-white border-end px-3 fw-bold text-primary"><i class="fas fa-chart-pie me-1"></i> Analiz</a>
                                                    <a href="indir_karne.php?katilim_id=<?php echo $res['id']; ?>" class="btn btn-sm btn-white px-2 text-danger"><i class="fas fa-file-pdf"></i></a>
                                                <?php else: ?>
                                                    <button class="btn btn-sm btn-light border px-3 disabled small"><i class="fas fa-clock me-1"></i> Bekleniyor</button>
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
        </div>
    </div>
</div>

<!-- MODAL: DİJİTAL ROZET (STORY HAZIR) -->
<div class="modal fade" id="certModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3 text-center">
                <!-- Story Önizleme -->
                <div class="story-preview-container mb-3">
                    <div class="story-badge" id="storyBadge">
                        <!-- Story Formatı 9:16 -->
                        <div class="story-gradient-bg">
                            <div class="story-content">
                                <!-- Üst Bölüm: Logo & Slogan -->
                                <div class="story-header">
                                    <div class="story-logo">⚡ DenemeAGS</div>
                                    <div class="story-date"><?php echo date('Y'); ?></div>
                                </div>

                                <!-- Orta Bölüm: Ana Kart -->
                                <div class="achievement-card">
                                    <div class="card-glow"></div>
                                    <div class="badge-icon">🏆</div>
                                    <h2 class="user-name-story"><?php echo escape_html($user_ad_soyad); ?></h2>
                                    <div class="license-badge"><?php echo $license_no; ?></div>
                                    
                                    <!-- İstatistikler -->
                                    <div class="stats-grid">
                                        <div class="stat-item">
                                            <div class="stat-value"><?php echo $total_exams_taken; ?></div>
                                            <div class="stat-label">Deneme</div>
                                        </div>
                                        <div class="stat-item">
                                            <div class="stat-value"><?php echo number_format($avg_net, 1); ?></div>
                                            <div class="stat-label">Ort. Net</div>
                                        </div>
                                        <div class="stat-item">
                                            <div class="stat-value">Lvl <?php echo floor($total_exams_taken / 2) + 1; ?></div>
                                            <div class="stat-label">Seviye</div>
                                        </div>
                                    </div>

                                    <div class="achievement-title">
                                        <span class="shine-text">Lisanslı Öğrenci</span>
                                    </div>
                                </div>

                                <!-- Alt Bölüm: CTA -->
                                <div class="story-footer">
                                    <div class="verified-badge">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                                            <path d="M9 12L11 14L15 10" stroke="white" stroke-width="2"/>
                                            <circle cx="12" cy="12" r="10" stroke="white" stroke-width="2"/>
                                        </svg>
                                        <span>Özgür Yayıncılığı Destekliyorum</span>
                                    </div>
                                    <div class="story-cta">Sen de aramıza katıl! 🚀</div>
                                    <div class="story-url">denemeags.com</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Butonlar -->
                <div class="action-buttons">
                    <button class="btn btn-download-story" onclick="downloadStoryImage()">
                        <i class="fas fa-download me-2"></i>Story Olarak İndir
                    </button>
                    <button class="btn btn-copy-caption" onclick="copyCaptionToClipboard()">
                        <i class="fas fa-copy me-2"></i>Açıklamayı Kopyala
                    </button>
                </div>

                <!-- Önerilen Caption -->
                <div class="caption-box mt-3" id="captionText">
                    <small class="text-muted d-block mb-2">📝 Önerilen Story Açıklaması:</small>
                    <div class="caption-content">
2024'te hedeflerimin peşindeyim! 🎯
<?php echo $total_exams_taken; ?> deneme, <?php echo number_format($avg_net, 1); ?> net ortalamayla yoluma devam ediyorum 💪

Özgür yayıncılığı destekliyorum ✨
@denemeags 

#deneme #ayt #tyt #ösym #üniversite #motivasyon #başarı
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Story Önizleme Container */
.story-preview-container {
    background: #f8f9fa;
    border-radius: 20px;
    padding: 20px;
    display: inline-block;
    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
}

.story-badge {
    width: 280px;
    height: 497px; /* 9:16 ratio scaled down */
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    position: relative;
}

/* Gradient Arka Plan */
.story-gradient-bg {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
    position: relative;
    overflow: hidden;
}

.story-gradient-bg::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: 
        radial-gradient(circle at 20% 30%, rgba(255,255,255,0.1) 0%, transparent 50%),
        radial-gradient(circle at 80% 70%, rgba(255,255,255,0.08) 0%, transparent 50%);
    animation: float 15s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translate(0, 0); }
    50% { transform: translate(20px, 20px); }
}

.story-content {
    position: relative;
    z-index: 2;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 25px 20px;
}

/* Üst Header */
.story-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.story-logo {
    font-size: 18px;
    font-weight: 900;
    color: white;
    text-shadow: 0 2px 10px rgba(0,0,0,0.3);
}

.story-date {
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(10px);
    padding: 4px 12px;
    border-radius: 20px;
    color: white;
    font-size: 12px;
    font-weight: 700;
}

/* Ana Achievement Kartı */
.achievement-card {
    background: rgba(255,255,255,0.15);
    backdrop-filter: blur(20px);
    border: 2px solid rgba(255,255,255,0.3);
    border-radius: 25px;
    padding: 30px 20px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 20px 40px rgba(0,0,0,0.2);
}

.card-glow {
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, transparent 70%);
    animation: glow-pulse 3s ease-in-out infinite;
}

@keyframes glow-pulse {
    0%, 100% { opacity: 0.5; transform: scale(1); }
    50% { opacity: 0.8; transform: scale(1.1); }
}

.badge-icon {
    font-size: 60px;
    margin-bottom: 15px;
    filter: drop-shadow(0 5px 15px rgba(0,0,0,0.3));
    animation: bounce 2s ease-in-out infinite;
}

@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

.user-name-story {
    font-size: 22px;
    font-weight: 900;
    color: white;
    margin: 0 0 10px 0;
    text-shadow: 0 3px 10px rgba(0,0,0,0.3);
    line-height: 1.2;
}

.license-badge {
    display: inline-block;
    background: rgba(255,255,255,0.25);
    border: 1px solid rgba(255,255,255,0.4);
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 700;
    color: white;
    letter-spacing: 1px;
    margin-bottom: 20px;
}

/* İstatistik Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin: 20px 0;
}

.stat-item {
    text-align: center;
}

.stat-value {
    font-size: 24px;
    font-weight: 900;
    color: white;
    text-shadow: 0 2px 8px rgba(0,0,0,0.3);
}

.stat-label {
    font-size: 11px;
    color: rgba(255,255,255,0.8);
    font-weight: 600;
    margin-top: 3px;
}

.achievement-title {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid rgba(255,255,255,0.3);
}

.shine-text {
    font-size: 14px;
    font-weight: 800;
    color: white;
    text-transform: uppercase;
    letter-spacing: 2px;
    background: linear-gradient(90deg, #fff 0%, #f0f0f0 50%, #fff 100%);
    background-size: 200% 100%;
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    animation: shine 3s linear infinite;
}

@keyframes shine {
    0% { background-position: -200% 0; }
    100% { background-position: 200% 0; }
}

/* Alt Footer */
.story-footer {
    text-align: center;
}

.verified-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(10px);
    padding: 8px 16px;
    border-radius: 25px;
    color: white;
    font-size: 11px;
    font-weight: 700;
    margin-bottom: 12px;
}

.story-cta {
    font-size: 13px;
    font-weight: 700;
    color: white;
    margin-bottom: 8px;
    text-shadow: 0 2px 8px rgba(0,0,0,0.3);
}

.story-url {
    font-size: 12px;
    color: rgba(255,255,255,0.8);
    font-weight: 600;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    justify-content: center;
}

.btn-download-story,
.btn-copy-caption {
    flex: 1;
    min-width: 200px;
    padding: 12px 20px;
    font-weight: 700;
    border-radius: 15px;
    border: none;
    transition: all 0.3s;
}

.btn-download-story {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.btn-download-story:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
}

.btn-copy-caption {
    background: white;
    color: #667eea;
    border: 2px solid #667eea;
}

.btn-copy-caption:hover {
    background: #667eea;
    color: white;
}

/* Hoca Etiketleme Bölümü */
.teacher-tag-section {
    background: #f8f9fa;
    border-radius: 15px;
    padding: 15px;
}

.teacher-tag {
    background: white;
    border: 2px solid #e9ecef;
    padding: 8px 15px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    color: #495057;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
}

.teacher-tag:hover {
    border-color: #667eea;
    color: #667eea;
    transform: translateY(-2px);
}

.teacher-tag.selected {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-color: #667eea;
    color: white;
}

.teacher-caption-placeholder {
    color: #ff6b6b;
    font-weight: 700;
    font-style: italic;
}

/* Story İçinde Hoca Mention Box */
.teacher-mention-box {
    background: rgba(255,255,255,0.25);
    backdrop-filter: blur(15px);
    border: 2px solid rgba(255,255,255,0.4);
    border-radius: 20px;
    padding: 12px 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
}

.mention-icon {
    font-size: 28px;
    filter: drop-shadow(0 2px 8px rgba(0,0,0,0.2));
}

.mention-text {
    flex: 1;
    text-align: left;
}

.mention-label {
    font-size: 10px;
    color: rgba(255,255,255,0.8);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 2px;
}

.mention-tag {
    font-size: 14px;
    color: white;
    font-weight: 800;
    text-shadow: 0 2px 8px rgba(0,0,0,0.3);
}

/* Caption Box */
.caption-box {
    background: #f8f9fa;
    border-radius: 15px;
    padding: 15px;
    text-align: left;
}

.caption-content {
    background: white;
    padding: 15px;
    border-radius: 10px;
    font-size: 13px;
    line-height: 1.6;
    color: #333;
    border: 1px solid #e9ecef;
    white-space: pre-line;
}

/* Mobil Responsive */
@media (max-width: 576px) {
    .story-badge {
        width: 240px;
        height: 426px;
    }
    
    .user-name-story {
        font-size: 18px;
    }
    
    .badge-icon {
        font-size: 50px;
    }
    
    .stat-value {
        font-size: 20px;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .btn-download-story,
    .btn-copy-caption {
        min-width: 100%;
    }
}
</style>

<script>
let selectedTeacher = '';

// Örnek popüler hocalar (Dinamik olarak backend'den gelebilir)
const popularTeachers = [
    { name: 'Mehmet Hoca', tag: '@mehmethoca' },
    { name: 'Ayşe Öğretmen', tag: '@ayseogretmen' },
    { name: 'Ali Bey', tag: '@alibey' },
];

// Sayfa yüklendiğinde popüler hocaları göster
window.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('teacherTagsContainer');
    container.innerHTML = popularTeachers.map(teacher => 
        `<span class="teacher-tag" onclick="selectTeacher(this)" data-tag="${teacher.tag}">
            <i class="fas fa-user-tie me-1"></i>${teacher.name}
        </span>`
    ).join('');
});

// Hoca seçimi
function selectTeacher(element) {
    // Önceki seçimi kaldır
    document.querySelectorAll('.teacher-tag').forEach(tag => tag.classList.remove('selected'));
    
    // Yeni seçimi ekle
    element.classList.add('selected');
    selectedTeacher = element.getAttribute('data-tag');
    
    // Story'deki mention'ı güncelle
    document.getElementById('storySelectedTeacher').textContent = selectedTeacher;
    
    // Caption'daki placeholder'ı güncelle
    updateCaptionWithTeacher();
}

// Custom hoca ekleme
function addTeacherTag() {
    const input = document.getElementById('customTeacherInput');
    input.classList.remove('d-none');
    input.focus();
    
    input.onkeypress = function(e) {
        if (e.key === 'Enter') {
            const value = input.value.trim();
            if (value && value.startsWith('@')) {
                // Yeni tag oluştur
                const container = document.getElementById('teacherTagsContainer');
                const newTag = document.createElement('span');
                newTag.className = 'teacher-tag selected';
                newTag.setAttribute('data-tag', value);
                newTag.onclick = function() { selectTeacher(this); };
                newTag.innerHTML = `<i class="fas fa-user-tie me-1"></i>${value}`;
                
                // Önceki seçimleri temizle
                document.querySelectorAll('.teacher-tag').forEach(tag => tag.classList.remove('selected'));
                
                container.appendChild(newTag);
                selectedTeacher = value;
                
                // Story'yi güncelle
                document.getElementById('storySelectedTeacher').textContent = value;
                updateCaptionWithTeacher();
                
                input.value = '';
                input.classList.add('d-none');
            } else {
                alert('Lütfen @ ile başlayan bir Instagram kullanıcı adı girin!');
            }
        }
    };
}

function hideCustomInput() {
    setTimeout(() => {
        document.getElementById('customTeacherInput').classList.add('d-none');
    }, 200);
}

// Caption'ı hoca etiketiyle güncelle
function updateCaptionWithTeacher() {
    const placeholder = document.getElementById('teacherTagInCaption');
    if (selectedTeacher) {
        placeholder.innerHTML = `<strong>${selectedTeacher}</strong> ile başarıya giden yolda ilerliyorum! 🙏💜`;
        placeholder.className = '';
    } else {
        placeholder.textContent = 'Sevdiğin hocayı yukarıdan etiketle! 👆';
        placeholder.className = 'teacher-caption-placeholder';
    }
}

// Story İmaj İndirme
function downloadStoryImage() {
    if (!selectedTeacher) {
        const confirmDownload = confirm('⚠️ Hoca etiketi eklemedin!\n\nDevam etmek istiyor musun?\n\n💡 İpucu: Hoca etiketiyle paylaşımın çok daha fazla etkileşim alır!');
        if (!confirmDownload) return;
    }
    
    alert('📸 Story Hazır!\n\n1. Bu pencereyi tam ekran yap\n2. Telefonundan ekran görüntüsü al\n3. Instagram Story\'de paylaş!\n' + 
          (selectedTeacher ? `4. Story\'de ${selectedTeacher} etiketini ekle!\n` : '') +
          '\n💡 Aşağıdaki açıklamayı da kopyalamayı unutma! 💜');
}

// Caption Kopyalama
function copyCaptionToClipboard() {
    if (!selectedTeacher) {
        alert('⚠️ Önce bir hoca seç, sonra açıklamayı kopyala!\n\nHoca etiketiyle paylaşım çok daha etkili olur! 🚀');
        return;
    }
    
    const captionText = document.querySelector('.caption-content').innerText;
    
    if (navigator.clipboard) {
        navigator.clipboard.writeText(captionText).then(() => {
            const btn = event.target.closest('.btn-copy-caption');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check me-2"></i>Kopyalandı!';
            btn.style.background = '#28a745';
            btn.style.color = 'white';
            btn.style.borderColor = '#28a745';
            
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.style.background = '';
                btn.style.color = '';
                btn.style.borderColor = '';
            }, 2000);
        });
    } else {
        const textarea = document.createElement('textarea');
        textarea.value = captionText;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        alert('✅ Açıklama kopyalandı!');
    }
}
</script>

<?php include_once __DIR__ . '/templates/footer.php'; ?>