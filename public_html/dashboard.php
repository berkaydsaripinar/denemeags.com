<?php
// dashboard.php - Öğrenci Kontrol Paneli (Holografik Sertifika & Gelişmiş İstatistik Entegrasyonu)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

// Giriş kontrolü
if (!isLoggedIn()) {
    redirect('login.php');
}

// Hata Giderme: Session anahtarlarını kontrol et ve varsayılan değer ata
// Bazı sistemlerde 'user_ad_soyad', bazılarında 'user_name' kullanıldığı için ikisini de kontrol ediyoruz.
$user_id = $_SESSION['user_id'] ?? 0;
$user_ad_soyad = $_SESSION['user_ad_soyad'] ?? ($_SESSION['user_name'] ?? 'Değerli Öğrenci');
$page_title = "Kütüphanem ve Başarılarım";
$csrf_token = generate_csrf_token();

// Benzersiz Lisans No Oluştur (Örn: AGS-102-2025-X4)
$license_no = "AGS-" . $user_id . "-" . date('Y') . "-" . strtoupper(substr(md5($user_id . SITE_NAME), 0, 4));

// Değişkenleri varsayılanlarla başlat
$kutuphane = [];
$history = [];
$duyurular = [];
$total_products = 0;
$total_exams_taken = 0;
$avg_net = 0;

try {
    // 1. Kütüphanedeki Ürünleri Çek (Ve bu ürünlere ait sınav katılım ID'lerini de al)
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

    // 2. Sınav Geçmişini Çek (Tablo görünümü için)
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
        width: 100%; 
        height: 260px; 
        background: rgba(15, 23, 42, 0.98);
        backdrop-filter: blur(25px); 
        border-radius: 30px; 
        border: 1px solid rgba(255, 255, 255, 0.15);
        position: relative; 
        overflow: hidden; 
        transition: transform 0.1s ease-out;
        box-shadow: 0 40px 80px rgba(0,0,0,0.4); 
        cursor: pointer;
        padding: 40px;
    }

    /* Holografik Parlama Efekti */
    .shimmer {
        position: absolute; top: 0; left: 0; width: 100%; height: 100%;
        background: var(--hologram-gradient); 
        background-size: 400% 400%;
        opacity: 0.15; 
        mix-blend-mode: color-dodge; 
        pointer-events: none;
        animation: moveGradient 8s ease infinite;
    }

    @keyframes moveGradient { 
        0% { background-position: 0% 50%; } 
        50% { background-position: 100% 50%; } 
        100% { background-position: 0% 50%; } 
    }
    
    .label-premium { 
        font-family: 'Inter', sans-serif; 
        font-size: 0.65rem; 
        letter-spacing: 4px; 
        color: #fff; 
        opacity: 0.5; 
        text-transform: uppercase; 
        font-weight: 700;
    }

    .cert-user-name { 
        font-size: 1.8rem; 
        font-weight: 800; 
        color: #fff; 
        margin-top: 10px; 
        background: linear-gradient(to bottom, #fff, #aaa);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .slogan-tag { 
        color: #ff6f61; 
        font-weight: 700; 
        font-size: 0.75rem; 
        letter-spacing: 1px; 
        padding: 6px 16px; 
        background: rgba(255, 111, 97, 0.1); 
        border: 1px solid rgba(255, 111, 97, 0.2); 
        border-radius: 12px; 
    }

    .license-id-box { 
        position: absolute; 
        top: 35px; 
        right: 35px; 
        font-family: monospace; 
        font-size: 0.65rem; 
        color: #4A69FF; 
        background: rgba(74, 105, 255, 0.1); 
        padding: 3px 10px; 
        border-radius: 6px; 
    }
    
    .badge-verified {
        position: absolute; 
        bottom: 35px; 
        right: 35px; 
        width: 70px; 
        height: 70px;
        background: rgba(255, 255, 255, 0.05); 
        border-radius: 50%; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    /* --- DASHBOARD UI --- */
    .welcome-card { background: linear-gradient(135deg, var(--dash-primary) 0%, #3a58e0 100%); border-radius: 24px; padding: 35px; color: white; box-shadow: 0 10px 30px rgba(31, 60, 136, 0.2); position: relative; overflow: hidden; }
    .welcome-card::after { content: '\f5da'; font-family: 'Font Awesome 6 Free'; font-weight: 900; position: absolute; right: -20px; bottom: -30px; font-size: 10rem; opacity: 0.1; transform: rotate(-15deg); }
    
    .stat-pill { background: #fff; border-radius: 20px; padding: 20px; border: 1px solid #eaedf3; transition: 0.3s; height: 100%; }
    .stat-pill:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.05); }
    .stat-icon-box { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-bottom: 12px; }
    
    .announcement-bar { background: #fff; border-radius: 15px; border-left: 5px solid var(--dash-accent); padding: 15px 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.03); }
    
    .nav-pills-custom .nav-link { color: var(--dash-primary); font-weight: 700; padding: 10px 25px; border-radius: 50px; border: 1px solid #dee2e6; margin: 0 5px; background: #fff; }
    .nav-pills-custom .nav-link.active { background-color: var(--dash-primary) !important; color: #fff !important; border-color: var(--dash-primary); }

    .badge-premium-btn {
        background: linear-gradient(90deg, #FF6F61, #FF9A8B); color: white; border: none;
        padding: 10px 24px; border-radius: 50px; font-weight: 700; transition: 0.3s;
    }
    .badge-premium-btn:hover { transform: scale(1.05); box-shadow: 0 10px 20px rgba(255, 111, 97, 0.3); }

    .screenshot-mode .modal-header, .screenshot-mode .modal-footer, .screenshot-mode .btn-close { display: none !important; }
    .screenshot-mode .modal-content { background: #05070a !important; padding: 60px !important; border: none !important; }
</style>

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
                                                <!-- Sınav hiç başlatılmamışsa start_exam.php üzerinden başlatılır -->
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
                                                    $video_query = ($item['tur'] == 'deneme' && $item['active_katilim_id'])
                                                        ? 'katilim_id=' . $item['active_katilim_id']
                                                        : 'deneme_id=' . $item['id'];
                                                ?>
                                                <a href="view_video_solution.php?<?php echo $video_query; ?>" class="btn btn-outline-warning btn-sm rounded-pill fw-bold">Video Çözümü İzle</a>
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

<!-- MODAL: SERTİFİKA (ONUR NİŞANI) -->
<div class="modal fade" id="certModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-5 overflow-hidden" id="captureArea">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 p-md-5 text-center">
                <h4 class="fw-black mb-5 title-to-hide text-dark">DİJİTAL ONUR NİŞANI</h4>
                
                <div class="card-perspective">
                    <div class="holographic-card text-start" id="hologram">
                        <div class="shimmer"></div>
                        <div class="license-id-box"><?php echo $license_no; ?></div>
                        
                        <div class="d-flex flex-column h-100">
                            <div class="mb-auto">
                                <div class="label-premium">Authorized Member</div>
                                <div class="cert-user-name"><?php echo escape_html($user_ad_soyad); ?></div>
                            </div>

                            <div class="mt-4">
                                <span class="slogan-tag">ÖZGÜR YAYINCILIĞI DESTEKLİYORUM</span>
                                <div class="text-white opacity-25 mt-3 small tracking-widest font-light" style="font-size: 9px; letter-spacing: 2px;">DENEMEAGS DIGITAL LICENSE v5.0</div>
                            </div>
                        </div>

                        <div class="badge-verified">
                            <svg width="35" height="35" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <defs>
                                    <linearGradient id="grad1" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" style="stop-color:#4A69FF;stop-opacity:1" />
                                        <stop offset="100%" style="stop-color:#00ff9d;stop-opacity:1" />
                                    </linearGradient>
                                </defs>
                                <path d="M12 1L3 5V11C3 16.55 6.84 21.74 12 23C17.16 21.74 21 16.55 21 11V5L12 1Z" fill="url(#grad1)" fill-opacity="0.2" stroke="url(#grad1)" stroke-width="2"/>
                                <path d="M9 12L11 14L15 10" stroke="#00ff9d" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="mt-5 footer-to-hide">
                    <p class="text-muted small mb-4 px-md-5">Bu nişan, eser sahiplerinin emeğine saygı duyduğunuzu ve lisanslı içerik kullandığınızı temsil eder. Instagram hikayende paylaşarak topluluğa destek olabilirsin!</p>
                    <button class="btn btn-dark rounded-pill px-5 fw-bold shadow-lg" onclick="prepareScreenshot()">
                        <i class="fas fa-camera me-2"></i> PAYLAŞIM MODUNU AÇ (SS AL)
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: YENİ KOD TANIMLAMA -->
<div class="modal fade" id="addCodeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0 pe-4 pt-4">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 p-md-5 pt-2 text-center">
                <div class="bg-primary bg-opacity-10 text-primary p-4 rounded-circle d-inline-block mb-3" style="width:80px; height:80px;">
                    <i class="fas fa-key fa-2x"></i>
                </div>
                <h4 class="fw-bold">İçerik Kodunu Tanımla</h4>
                <p class="text-muted small mb-4">Aldığınız aktivasyon kodunu buraya girerek yayını kütüphanenize anında ekleyebilirsiniz.</p>
                
                <form action="add_product_with_code.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <div class="mb-4">
                        <input type="text" name="urun_kodu" class="form-control form-control-lg text-center fw-bold text-uppercase shadow-sm" placeholder="KODU BURAYA YAZIN" required>
                    </div>
                    <button type="submit" class="btn btn-theme-primary btn-lg w-100 shadow rounded-pill py-3">AKTİF ET</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // --- Tilt Effect (Mouse hareketiyle eğilme) ---
    const card = document.getElementById('hologram');
    const perspective = document.querySelector('.card-perspective');

    if(perspective && card) {
        perspective.addEventListener('mousemove', (e) => {
            const rect = perspective.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            const centerX = rect.width / 2;
            const centerY = rect.height / 2;
            
            const rotateX = (y - centerY) / 12;
            const rotateY = (centerX - x) / 12;
            card.style.transform = `rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale(1.02)`;
        });

        perspective.addEventListener('mouseleave', () => {
            card.style.transform = `rotateX(0deg) rotateY(0deg) scale(1)`;
        });
    }

    // --- Screenshot Hazırlığı ---
    function prepareScreenshot() {
        const modal = document.getElementById('certModal');
        modal.classList.add('screenshot-mode');
        
        // Modal dışındaki elemanları gizle
        document.querySelectorAll('.title-to-hide, .footer-to-hide').forEach(el => el.style.opacity = '0');
        
        const exitMode = () => {
            modal.classList.remove('screenshot-mode');
            document.querySelectorAll('.title-to-hide, .footer-to-hide').forEach(el => el.style.opacity = '1');
            window.removeEventListener('click', exitMode);
        };
        
        // Kullanıcıya bilgi ver
        setTimeout(() => {
            alert("Paylaşım modu aktif! Şimdi temiz bir ekran görüntüsü (SS) alabilirsin. Normal görünüme dönmek için herhangi bir yere dokun.");
            window.addEventListener('click', exitMode);
        }, 300);
    }
</script>

<?php include_once __DIR__ . '/templates/footer.php'; ?>
