<?php
// index.php (Türkiye'nin Hibrit Deneme Platformu - Modern ve Mobil Uyumlu)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$page_title = "Türkiye'nin Hibrit Yayın Platformu";
$csrf_token = generate_csrf_token();

// Veritabanından SADECE "anasayfada_goster = 1" olan ürünleri çek
try {
    $stmt = $pdo->query("
        SELECT d.*, y.ad_soyad as yazar_adi 
        FROM denemeler d 
        LEFT JOIN yazarlar y ON d.yazar_id = y.id 
        WHERE d.aktif_mi = 1 AND d.anasayfada_goster = 1
        ORDER BY d.id DESC
    ");
    $urunler = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Anasayfa ürün çekme hatası: " . $e->getMessage());
    $urunler = [];
}

$sidebar_defaults = [
    'HOMEPAGE_SIDEBAR_TITLE' => 'Mağaza Kısa Yolu',
    'HOMEPAGE_SIDEBAR_DESC' => 'DenemeAGS mağazasında tüm yayınlar dijital, hızlı ve erişilebilir. Satın alım sonrası anında kütüphanenizde.',
    'HOMEPAGE_SIDEBAR_LIST' => "Anında PDF erişimi\nKargo bekleme yok\nYazara adil pay\nÇevre dostu içerik",
    'HOMEPAGE_SIDEBAR_CTA_LABEL' => 'Mağazaya Git',
    'HOMEPAGE_SIDEBAR_CTA_URL' => 'store.php',
    'HOMEPAGE_SIDEBAR_NOTE' => 'Mobilde bile mağaza bir tık uzaklıkta.'
];

$sidebar_settings = $sidebar_defaults;
try {
    $placeholders = implode(',', array_fill(0, count($sidebar_defaults), '?'));
    $stmt = $pdo->prepare("SELECT ayar_adi, ayar_degeri FROM sistem_ayarlari WHERE ayar_adi IN ($placeholders)");
    $stmt->execute(array_keys($sidebar_defaults));
    $sidebar_rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach ($sidebar_rows as $key => $value) {
        if (isset($sidebar_settings[$key]) && $value !== null && $value !== '') {
            $sidebar_settings[$key] = $value;
        }
    }
} catch (Exception $e) {
    error_log("Anasayfa sidebar ayarları okunamadı: " . $e->getMessage());
}

$sidebar_cta_url = trim($sidebar_settings['HOMEPAGE_SIDEBAR_CTA_URL']);
$sidebar_cta_url = filter_var($sidebar_cta_url, FILTER_SANITIZE_URL);
$sidebar_cta_url = $sidebar_cta_url !== '' ? $sidebar_cta_url : '#urunler';

$sidebar_list_items = array_values(array_filter(array_map('trim', explode("\n", $sidebar_settings['HOMEPAGE_SIDEBAR_LIST']))));

include_once __DIR__ . '/templates/header.php';
?>

<style>
    :root {
        --primary: #1F3C88;
        --accent: #F57C00;
        --dark: #0B162C;
        --light: #f4f7f6;
    }

    /* Navbar Z-index Sabitleme */
    nav.navbar { position: relative; z-index: 9999 !important; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }

    /* YENİ NESİL HERO ALANI */
    .hero-section {
        background: radial-gradient(circle at top right, #2a4b9c 0%, var(--dark) 100%);
        color: white;
        padding: clamp(60px, 10vh, 100px) 0 clamp(80px, 15vh, 140px);
        position: relative;
        overflow: hidden;
    }
    
    .hero-badge {
        display: inline-flex; align-items: center;
        background: rgba(245, 124, 0, 0.15);
        border: 1px solid rgba(245, 124, 0, 0.3);
        color: var(--accent);
        padding: 8px 20px;
        border-radius: 50px;
        font-weight: 700;
        margin-bottom: 25px;
        font-size: 0.85rem;
    }

    .hero-title {
        font-family: 'Montserrat', sans-serif;
        font-weight: 800;
        /* Mobilde küçülen, masaüstünde büyüyen dinamik yazı boyutu */
        font-size: clamp(2rem, 5vw, 3.5rem);
        line-height: 1.1;
        margin-bottom: 20px;
        text-shadow: 0 10px 30px rgba(0,0,0,0.3);
    }
    
    .hero-desc {
        font-size: clamp(1rem, 2vw, 1.2rem);
        opacity: 0.85;
        font-weight: 300;
        line-height: 1.6;
        margin-bottom: 40px;
        max-width: 600px;
    }

    /* Hibrit Görsel Alanı (Responsive Düzenleme) */
    .hybrid-demo {
        position: relative;
        height: 400px;
        perspective: 1000px;
        display: flex;
        justify-content: center;
        align-items: center;
    }
    .demo-tablet {
        width: 260px; height: 360px;
        background: #111; border-radius: 20px;
        border: 8px solid #333;
        position: absolute; right: 10%; top: 0;
        transform: rotateY(-15deg) rotateZ(5deg);
        box-shadow: -20px 20px 50px rgba(0,0,0,0.5);
        z-index: 2;
        overflow: hidden;
    }
    .demo-screen {
        width: 100%; height: 100%;
        background: white;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        color: #333; font-weight: bold;
    }
    .demo-paper {
        width: 260px; height: 360px;
        background: #fff;
        position: absolute; right: 25%; top: 40px;
        transform: rotateY(10deg) rotateZ(-10deg);
        box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        z-index: 1;
        border-radius: 5px;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        color: #333; font-weight: bold;
    }
    .demo-paper::after { content: ''; position: absolute; bottom: 0; right: 0; border-width: 0 0 40px 40px; border-style: solid; border-color: transparent transparent #eee transparent; }

    /* Butonlar */
    .btn-hero {
        padding: 12px 30px; font-weight: 700; border-radius: 8px;
        transition: all 0.3s; text-transform: uppercase; letter-spacing: 0.5px;
        display: inline-block; text-decoration: none; position: relative; z-index: 10;
        font-size: 0.9rem;
    }
    .btn-accent { background: var(--accent); color: white; border: none; box-shadow: 0 10px 25px rgba(245, 124, 0, 0.4); }
    .btn-accent:hover { background: #e65100; transform: translateY(-3px); color: white; }
    
    /* ADIM ADIM İŞLEYİŞ */
    .process-section { padding: 60px 0; background: white; margin-top: -60px; position: relative; z-index: 5; border-radius: 30px 30px 0 0; }
    .process-card {
        text-align: center; padding: 25px 15px;
        transition: 0.3s; height: 100%;
    }
    .process-card:hover { transform: translateY(-10px); }
    .process-icon {
        width: 70px; height: 70px;
        background: rgba(31, 60, 136, 0.08);
        color: var(--primary);
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 28px; margin: 0 auto 15px;
    }
    .process-step { color: var(--accent); font-weight: 800; letter-spacing: 1px; font-size: 0.8rem; text-transform: uppercase; display: block; margin-bottom: 5px; }

    /* HİBRİT ÖZELLİKLER */
    .hybrid-features { background-color: var(--light); padding: 70px 0; }
    .h-feature-box { background: white; padding: 25px; border-radius: 12px; height: 100%; border-left: 5px solid var(--primary); box-shadow: 0 5px 15px rgba(0,0,0,0.03); }
    
    /* ÜRÜN KARTLARI */
    .product-card {
        border: none; border-radius: 15px;
        background: white; overflow: hidden;
        box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        transition: 0.3s; height: 100%;
        display: flex; flex-direction: column;
    }
    .product-card:hover { transform: translateY(-8px); box-shadow: 0 20px 40px rgba(0,0,0,0.08); }
    .product-img-wrapper { height: 220px; overflow: hidden; position: relative; background: #f0f0f0; }
    .product-img-wrapper img { width: 100%; height: 100%; object-fit: cover; }
    
    .badge-hybrid {
        position: absolute; bottom: 10px; left: 10px;
        background: rgba(0,0,0,0.7); color: white;
        padding: 4px 8px; border-radius: 5px; font-size: 0.7rem;
        backdrop-filter: blur(5px);
    }
    .badge-tur {
        position: absolute; top: 12px; right: 12px;
        background: var(--accent); color: white;
        padding: 4px 12px; border-radius: 50px; font-size: 0.7rem; font-weight: 800;
        box-shadow: 0 5px 12px rgba(245, 124, 0, 0.3);
    }

    .btn-buy {
        background: var(--primary); color: white;
        width: 100%; padding: 10px; border-radius: 8px; font-weight: 600;
        text-decoration: none; display: inline-block; text-align: center;
        transition: 0.2s; font-size: 0.95rem;
    }
    .btn-buy:hover { background: var(--dark); color: white; }

    /* ANASAYFA LAYOUT */
    .home-layout { background: var(--light); padding: 60px 0; }
    .home-section {
        background: #fff;
        border-radius: 20px;
        padding: clamp(28px, 4vw, 42px);
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);
        margin-bottom: 24px;
    }
    .home-section h2 { color: var(--primary); }
    .store-access-card {
        background: linear-gradient(135deg, rgba(31, 60, 136, 0.08), rgba(245, 124, 0, 0.08));
        border-radius: 16px;
        padding: 24px;
        display: flex;
        flex-direction: column;
        gap: 16px;
    }
    .store-access-card .tag {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: var(--accent);
    }
    .store-access-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
    }
    .store-sidebar {
        position: sticky;
        top: 110px;
    }
    .sidebar-card {
        background: #0f172a;
        color: #fff;
        border-radius: 20px;
        padding: 28px;
        box-shadow: 0 20px 40px rgba(15, 23, 42, 0.2);
        margin-bottom: 20px;
    }
    .sidebar-card.secondary {
        background: #fff;
        color: #1f2937;
        border: 1px solid rgba(148, 163, 184, 0.2);
        box-shadow: 0 12px 28px rgba(15, 23, 42, 0.08);
    }
    .sidebar-card h3 { font-size: 1.2rem; margin-bottom: 12px; }
    .sidebar-list { list-style: none; padding: 0; margin: 0; }
    .sidebar-list li { display: flex; gap: 10px; margin-bottom: 10px; font-size: 0.9rem; opacity: 0.9; }
    .sidebar-cta {
        background: var(--accent);
        color: #fff;
        border: none;
        padding: 12px 18px;
        border-radius: 10px;
        text-decoration: none;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: 0.2s;
    }
    .sidebar-cta:hover { color: #fff; background: #e65100; transform: translateY(-2px); }
    .impact-grid .impact-card {
        background: #f8fafc;
        border-radius: 16px;
        padding: 22px;
        height: 100%;
        border: 1px solid rgba(148, 163, 184, 0.2);
    }
    .impact-card h5 { font-weight: 700; margin-bottom: 8px; }
    .impact-card i { color: var(--accent); margin-bottom: 12px; }

    /* FAQ (Sık Sorulan Sorular) */
    .faq-section { padding: 70px 0; background: white; }
    .accordion-button:not(.collapsed) { background-color: rgba(31, 60, 136, 0.05); color: var(--primary); font-weight: bold; }
    .accordion-button:focus { box-shadow: none; }

    /* Mobil Düzenlemeler */
    @media (max-width: 768px) {
        .hero-section { text-align: center; padding-bottom: 80px; }
        .hero-desc { margin: 0 auto 30px; }
        .hero-title { margin-bottom: 15px; }
        .product-img-wrapper { height: 180px; }
        .d-flex.gap-3 { justify-content: center; flex-direction: column; align-items: center; }
        .btn-hero { width: 80%; max-width: 300px; }
        body { padding-bottom: 90px; }
        .store-sidebar { position: static; }
    }

    .mobile-store-bar {
        position: fixed;
        left: 0;
        bottom: 0;
        width: 100%;
        background: #0f172a;
        color: #fff;
        z-index: 1050;
        padding: 12px 0;
        box-shadow: 0 -12px 30px rgba(15, 23, 42, 0.25);
    }
    .mobile-store-bar .btn {
        background: var(--accent);
        color: #fff;
        font-weight: 700;
        border-radius: 999px;
        padding: 8px 16px;
        white-space: nowrap;
    }
    .mobile-store-bar small { color: rgba(255,255,255,0.7); }
</style>

<!-- HERO SECTION -->
<header class="hero-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <div class="hero-badge">
                    <i class="fas fa-bolt me-2"></i> YENİ NESİL HİBRİT YAYINLAR
                </div>
                <h1 class="hero-title">İster Çıktı Al Çöz,<br>İster Tabletten Çöz.</h1>
                <p class="hero-desc">
                    Sınav hazırlığında özgürlük dönemi başladı. Satın aldığınız denemeyi 
                    anında PDF olarak indirin. İster yazıcıdan çıktı alıp klasik yöntemle çözün, 
                    ister tabletinize atıp dijital kalemle çözün.
                </p>
                
                <div class="d-flex gap-3 flex-wrap">
                    <a href="store.php" class="btn-hero btn-accent">
                        <i class="fas fa-store me-2"></i> Mağazaya Git
                    </a>
                    <a href="#urunler" class="btn-hero" style="background: rgba(255,255,255,0.12); color: white; backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.2);">
                        <i class="fas fa-book-open me-2"></i> Vitrin Ürünleri
                    </a>
                    <?php if (!isLoggedIn()): ?>
                    <a href="register.php" class="btn-hero" style="background: rgba(255,255,255,0.12); color: white; backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.2);">
                        <i class="fas fa-user-plus me-2"></i> Ücretsiz Kayıt
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="col-lg-6 d-none d-lg-block">
                <div class="hybrid-demo">
                    <div class="demo-tablet">
                        <div class="demo-screen">
                            <i class="fas fa-tablet-alt fa-3x mb-3 text-secondary"></i>
                            <div class="small">DİJİTAL ÇÖZÜM</div>
                            <div class="text-success mt-2"><i class="fas fa-check-circle"></i> Anında Erişim</div>
                        </div>
                    </div>
                    <div class="demo-paper">
                        <i class="fas fa-print fa-3x mb-3 text-secondary"></i>
                        <div class="small">KAĞIT BASKI</div>
                        <div class="text-primary mt-2"><i class="fas fa-pen-fancy"></i> Klasik Deneyim</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<section class="home-layout">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="home-section">
                    <div class="store-access-card">
                        <div class="tag">
                            <i class="fas fa-store"></i> Mağazaya hızlı erişim
                        </div>
                        <div>
                            <h2 class="fw-bold mb-2">Mağaza her zaman görünür, ürünler bir tık uzağınızda.</h2>
                            <p class="text-muted mb-0">Denemeler, soru bankaları ve yeni eklenen materyaller tek ekranda. Telefonla girenler için mağaza bağlantısı sabit ve görünür.</p>
                        </div>
                        <div class="store-access-actions">
                            <a href="store.php" class="btn btn-primary fw-bold">
                                <i class="fas fa-shopping-bag me-2"></i> Tüm ürünlere git
                            </a>
                            <a href="#urunler" class="btn btn-outline-primary fw-bold">
                                <i class="fas fa-star me-2"></i> Vitrin ürünleri
                            </a>
                        </div>
                    </div>
                </div>

                <div class="home-section">
                    <div class="text-center mb-4">
                        <h2 class="fw-bold mb-2">Süreç Nasıl İşler?</h2>
                        <p class="text-muted">Teknoloji ile eğitimi birleştiren en kolay yol.</p>
                    </div>
                    <div class="row g-4">
                        <div class="col-md-4">
                            <div class="process-card">
                                <div class="process-icon"><i class="fas fa-shopping-cart"></i></div>
                                <span class="process-step">1. ADIM</span>
                                <h4 class="fw-bold h5">Güvenle Satın Al</h4>
                                <p class="text-muted small">Beğendiğiniz deneme sınavını veya soru bankasını Shopier altyapısıyla anında satın alın.</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="process-card">
                                <div class="process-icon"><i class="fas fa-file-pdf"></i></div>
                                <span class="process-step">2. ADIM</span>
                                <h4 class="fw-bold h5">PDF Dosyanı İndir</h4>
                                <p class="text-muted small">Size özel olarak isminizle damgalanmış "Kişiye Özel PDF" dosyanıza kütüphanenizden anında erişin.</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="process-card">
                                <div class="process-icon"><i class="fas fa-print"></i></div>
                                <span class="process-step">3. ADIM</span>
                                <h4 class="fw-bold h5">Çıktı Al veya Dijital Çöz</h4>
                                <p class="text-muted small">Dosyanız yazdırmaya uygundur. İster kağıda basın, ister ekrandan çözün. Tercih sizin!</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="home-section">
                    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4">
                        <div>
                            <h2 class="fw-bold mb-2">DenemeAGS Etkisi</h2>
                            <p class="text-muted mb-0">Satın aldığınız her içerik hem çevre hem de üretici için daha sürdürülebilir bir modele katkı sağlar.</p>
                        </div>
                    </div>
                    <div class="row g-3 impact-grid">
                        <div class="col-md-6">
                            <div class="impact-card">
                                <i class="fas fa-leaf fa-lg"></i>
                                <h5>Çevreye Katkı</h5>
                                <p class="small text-muted mb-0">Fiziksel kargo, stok ve gereksiz baskı olmadığı için karbon ayak izi düşer, sadece ihtiyaç kadar çıktı alınır.</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="impact-card">
                                <i class="fas fa-hand-holding-heart fa-lg"></i>
                                <h5>Yazara Adil Pay</h5>
                                <p class="small text-muted mb-0">Matbaa ve dağıtım yükü azalır, içerik üreticisi emeğinin karşılığını daha şeffaf ve adil alır.</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="impact-card">
                                <i class="fas fa-wallet fa-lg"></i>
                                <h5>Daha Ulaşılabilir Fiyatlar</h5>
                                <p class="small text-muted mb-0">Kargo ve baskı maliyetleri düşürüldüğü için öğrencilere daha erişilebilir materyaller sunulur.</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="impact-card">
                                <i class="fas fa-bolt fa-lg"></i>
                                <h5>Anında Erişim</h5>
                                <p class="small text-muted mb-0">Satın aldığınız içerik saniyeler içinde kütüphanenizde hazır olur, bekleme süresi yoktur.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="home-section">
                    <div class="row align-items-center">
                        <div class="col-lg-5 mb-4 mb-lg-0">
                            <h2 class="fw-bold mb-3">Neden Hibrit Yayın?</h2>
                            <p class="text-muted mb-4">Eğitimde tek bir doğru yöntem yoktur. Kimi öğrenci kağıdın kokusunu sever, kimi teknolojinin hızını. Biz size her iki konforu da aynı anda sunuyoruz.</p>
                            <ul class="list-unstyled">
                                <li class="mb-3 d-flex align-items-start"><i class="fas fa-check-circle text-success me-3 mt-1"></i> <div><strong>Kargo Beklemek Yok:</strong> Satın aldığınız an döküman kütüphanenizde.</div></li>
                                <li class="mb-3 d-flex align-items-start"><i class="fas fa-check-circle text-success me-3 mt-1"></i> <div><strong>Ekonomik Seçenek:</strong> Matbaa maliyeti olmadığı için çok daha avantajlı fiyatlar.</div></li>
                                <li class="mb-3 d-flex align-items-start"><i class="fas fa-check-circle text-success me-3 mt-1"></i> <div><strong>Kişiye Özel Koruma:</strong> Emeğin korunması için size özel dijital damgalama.</div></li>
                            </ul>
                        </div>
                        <div class="col-lg-7">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="h-feature-box">
                                        <i class="fas fa-print fa-2x mb-3 text-secondary opacity-50"></i>
                                        <h5 class="h6 fw-bold">Yazıcı Dostu Format</h5>
                                        <p class="small text-muted mb-0">Tüm PDF'lerimiz A4 boyutunda, siyah-beyaz baskıya uygun ve mürekkep tasarruflu olarak optimize edilmiştir.</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="h-feature-box" style="border-left-color: var(--accent);">
                                        <i class="fas fa-tablet-alt fa-2x mb-3 text-secondary opacity-50"></i>
                                        <h5 class="h6 fw-bold">Tablet ve Not Uyumu</h5>
                                        <p class="small text-muted mb-0">iPad veya Android tabletlerdeki not alma uygulamalarıyla (GoodNotes, Notability vb.) %100 uyumludur.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <aside class="store-sidebar">
                    <div class="sidebar-card">
                        <span class="badge bg-warning text-dark mb-3">Mağaza Sidebar</span>
                        <h3><?php echo escape_html($sidebar_settings['HOMEPAGE_SIDEBAR_TITLE']); ?></h3>
                        <p class="small opacity-75"><?php echo escape_html($sidebar_settings['HOMEPAGE_SIDEBAR_DESC']); ?></p>
                        <ul class="sidebar-list">
                            <?php foreach ($sidebar_list_items as $item): ?>
                                <li><i class="fas fa-check-circle text-warning"></i><span><?php echo escape_html($item); ?></span></li>
                            <?php endforeach; ?>
                        </ul>
                        <a href="<?php echo escape_html($sidebar_cta_url); ?>" class="sidebar-cta mt-3">
                            <i class="fas fa-shopping-basket"></i> <?php echo escape_html($sidebar_settings['HOMEPAGE_SIDEBAR_CTA_LABEL']); ?>
                        </a>
                        <div class="mt-3 small opacity-75">
                            <i class="fas fa-mobile-alt me-1"></i> <?php echo escape_html($sidebar_settings['HOMEPAGE_SIDEBAR_NOTE']); ?>
                        </div>
                    </div>
                    <div class="sidebar-card secondary">
                        <h3 class="text-dark">Bugünün Mağaza Hatırlatıcısı</h3>
                        <p class="small text-muted mb-3">Yeni içerikleri kaçırmamak için mağazayı her gün ziyaret edin. Mobilde bile tek dokunuşla erişim.</p>
                        <div class="d-flex flex-column gap-2">
                            <a href="#urunler" class="btn btn-outline-primary btn-sm fw-bold">
                                <i class="fas fa-star me-1"></i> Vitrin ürünleri
                            </a>
                            <a href="store.php" class="btn btn-primary btn-sm fw-bold">
                                <i class="fas fa-store me-1"></i> Tam mağaza
                            </a>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </div>
</section>

<!-- ÜRÜNLER (VİTRİN) -->
<section id="urunler" class="py-5 bg-white">
    <div class="container py-5">
        <div class="text-center text-lg-start mb-5">
            <span class="text-uppercase text-primary fw-bold small ls-1">DİJİTAL KÜTÜPHANE</span>
            <h2 class="fw-bold display-6 mb-0">Popüler Yayınlarımız</h2>
        </div>

        <div class="row g-4 justify-content-center">
            <?php if (empty($urunler)): ?>
                <div class="col-12">
                    <div class="alert alert-light text-center py-5 shadow-sm border-0">
                        <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                        <h4 class="h5">Henüz vitrinde ürün bulunmuyor.</h4>
                        <p class="text-muted small">Yakında yeni denemeler eklenecektir. Lütfen takipte kalın.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($urunler as $urun): ?>
                    <div class="col-sm-10 col-md-6 col-lg-4">
                        <div class="card product-card">
                            <div class="product-img-wrapper">
                                <img src="<?php echo get_image_url($urun['resim_url']); ?>" alt="<?php echo escape_html($urun['deneme_adi']); ?>">                                
                                <span class="badge-tur">
                                    <?php echo ($urun['tur'] == 'deneme') ? 'DENEME' : 'SORU BANKASI'; ?>
                                </span>
                                <div class="badge-hybrid">
                                    <i class="fas fa-print me-1"></i> + <i class="fas fa-tablet-alt me-1"></i> Hibrit
                                </div>
                            </div>

                            <div class="card-body d-flex flex-column p-4">
                                <h5 class="fw-bold text-dark mb-1 h6"><?php echo escape_html($urun['deneme_adi']); ?></h5>
                                <div class="text-muted small mb-3" style="font-size: 0.75rem;">
                                    <i class="fas fa-feather-alt me-1 text-primary"></i> <?php echo escape_html($urun['yazar_adi'] ?? 'Platform Kaynağı'); ?>
                                </div>
                                
                                <p class="text-secondary small flex-grow-1" style="font-size: 0.85rem; line-height: 1.4;">
                                    <?php 
                                        $desc = strip_tags($urun['kisa_aciklama']);
                                        echo (mb_strlen($desc) > 95) ? mb_substr($desc, 0, 92) . '...' : $desc; 
                                    ?>
                                </p>
                                
                                <div class="mt-3 pt-3 border-top">
                                    <a href="urun.php?id=<?php echo $urun['id']; ?>" class="btn btn-buy shadow-sm">
                                        <i class="fas fa-shopping-cart me-2"></i> Detayları Gör
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- FAQ SECTION -->
<section class="faq-section bg-light">
    <div class="container py-5">
        <h2 class="text-center fw-bold mb-5" style="color: var(--primary); font-size: 1.75rem;">Merak Edilenler</h2>
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="accordion" id="faqAccordion">
                    <div class="accordion-item border-0 mb-3 shadow-sm rounded-3 overflow-hidden">
                        <h2 class="accordion-header">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                Satın aldıktan sonra PDF'e nasıl ulaşırım?
                            </button>
                        </h2>
                        <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-muted small">
                                Ödemeniz Shopier üzerinden onaylandığı an sistemimiz size bir erişim kodu üretir ve kütüphanenize tanımlar. Giriş yaptıktan sonra "Kütüphanem" sekmesinden dosyanızı anında indirebilirsiniz.
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item border-0 mb-3 shadow-sm rounded-3 overflow-hidden">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                Dosyayı kaç kez indirme hakkım var?
                            </button>
                        </h2>
                        <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-muted small">
                                Dosyayı kendi cihazlarınıza indirmek için bir sınır yoktur. Ancak her indirme işlemi IP adresiniz ve kullanıcı bilgilerinizle loglanmaktadır. Güvenlik gereği hesabınızı başkalarıyla paylaşmamanız önerilir.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item border-0 mb-3 shadow-sm rounded-3 overflow-hidden">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                Filigran (Watermark) dökümanı görmemi engeller mi?
                            </button>
                        </h2>
                        <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-muted small">
                                Hayır. Filigranlar sayfa kenarlarında veya arka planda çok düşük opaklıkta (%8) yer alır. Okumanızı veya soruları çözmenizi kesinlikle zorlaştırmaz, sadece dökümanın size ait olduğunu kanıtlar.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- AUTHOR CTA -->
<section class="container pb-5 mb-5 mt-5">
    <div class="p-4 p-md-5 text-white rounded-4 shadow-lg position-relative overflow-hidden" style="background: linear-gradient(90deg, #111 0%, #333 100%);">
        <div class="row align-items-center position-relative" style="z-index: 2;">
            <div class="col-lg-8">
                <span class="badge bg-warning text-dark mb-2">YAZAR VE EĞİTMENLER İÇİN</span>
                <h2 class="fw-bold mb-3 h3">Dijital Emeğiniz Koruma Altında</h2>
                <p class="lead mb-4 opacity-75 small">Kendi PDF yayınlarınızı platformumuzda yayınlayın, dinamik filigran sistemiyle korsan paylaşımların önüne geçin.</p>
                <a href="yazarlar.php" class="btn btn-light text-dark fw-bold px-4 py-2 rounded-pill text-decoration-none">
                    Yazar Başvurusu <i class="fas fa-arrow-right ms-2"></i>
                </a>
            </div>
        </div>
        <i class="fas fa-pen-nib position-absolute d-none d-md-block" style="font-size: 12rem; color: rgba(255,255,255,0.05); right: 20px; bottom: -30px; transform: rotate(-15deg);"></i>
    </div>
</section>

<div class="mobile-store-bar d-md-none">
    <div class="container d-flex align-items-center justify-content-between gap-3">
        <div>
            <strong>Mağaza burada</strong>
            <div><small><?php echo escape_html($sidebar_settings['HOMEPAGE_SIDEBAR_NOTE']); ?></small></div>
        </div>
        <a href="<?php echo escape_html($sidebar_cta_url); ?>" class="btn">
            <i class="fas fa-store me-1"></i> <?php echo escape_html($sidebar_settings['HOMEPAGE_SIDEBAR_CTA_LABEL']); ?>
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
