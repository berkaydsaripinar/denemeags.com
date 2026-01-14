<?php
$page_title = "Eser Sahipleri İçin - Maksimum Güvenlik";
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> | <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;900&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #1F3C88;      /* Lacivert */
            --primary-dark: #0f1e45; /* Koyu Lacivert */
            --accent: #F57C00;       /* Turuncu */
            --danger: #dc3545;       /* Kırmızı (Güvenlik/Uyarı) */
            --light: #f8f9fa;
        }
        
        body { font-family: 'Open Sans', sans-serif; color: #333; overflow-x: hidden; }
        h1, h2, h3, h4, h5 { font-family: 'Montserrat', sans-serif; font-weight: 700; }
        
        .navbar-landing { position: absolute; top: 0; left: 0; width: 100%; z-index: 100; padding: 25px 0; }

        /* HERO SECTION */
        .hero {
            background: radial-gradient(circle at center, #1a2c5a 0%, #000000 100%);
            color: white;
            padding: 180px 0 120px;
            position: relative;
            overflow: hidden;
        }
        .hero::before {
            content: "";
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background-image: linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
            background-size: 30px 30px;
            pointer-events: none;
        }

        .hero-title { font-size: 3.5rem; line-height: 1.1; font-weight: 900; margin-bottom: 25px; text-shadow: 0 0 20px rgba(31, 60, 136, 0.8); }
        .hero-subtitle { font-size: 1.25rem; opacity: 0.9; margin-bottom: 40px; font-weight: 400; max-width: 650px; }
        
        .btn-cta {
            background-color: var(--accent);
            color: white;
            padding: 18px 40px;
            font-size: 1.1rem;
            font-weight: 700;
            border-radius: 4px;
            text-decoration: none;
            transition: all 0.3s;
            box-shadow: 0 0 30px rgba(245, 124, 0, 0.3);
            display: inline-block;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .btn-cta:hover { background-color: #ff9100; transform: translateY(-2px); color: white; box-shadow: 0 0 40px rgba(245, 124, 0, 0.6); }

        .btn-login {
            background-color: transparent;
            color: white;
            padding: 18px 40px;
            font-size: 1.1rem;
            font-weight: 700;
            border-radius: 4px;
            text-decoration: none;
            border: 2px solid rgba(255,255,255,0.3);
            transition: all 0.3s;
            display: inline-block;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .btn-login:hover { background-color: white; color: var(--primary-dark); border-color: white; }

        /* SECURITY DEMO */
        .security-demo-wrapper {
            position: relative;
            padding: 20px;
            background: rgba(255,255,255,0.05);
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
        }
        .css-doc {
            background: white;
            width: 100%;
            max-width: 350px;
            height: 480px;
            margin: 0 auto;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
            position: relative;
            overflow: hidden;
            padding: 40px;
            border-radius: 2px;
        }
        .doc-watermark {
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 26px;
            font-weight: 900;
            color: rgba(220, 53, 69, 0.15); 
            white-space: nowrap;
            text-align: center;
            border: 2px dashed rgba(220, 53, 69, 0.2);
            padding: 10px 40px;
            z-index: 10;
            user-select: none;
        }
        .doc-line { height: 10px; background: #e9ecef; margin-bottom: 15px; border-radius: 4px; }
        .doc-header-line { height: 20px; background: #343a40; width: 70%; margin-bottom: 40px; }
        
        /* SECURITY FEATURES */
        .sec-card {
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            height: 100%;
            border-bottom: 4px solid var(--primary);
            transition: transform 0.3s;
        }
        .sec-card:hover { transform: translateY(-5px); }
        .sec-icon { font-size: 2.5rem; color: var(--primary); margin-bottom: 20px; }
        .feature-card {
            background: #ffffff;
            padding: 28px;
            border-radius: 16px;
            border: 1px solid rgba(31, 60, 136, 0.1);
            box-shadow: 0 12px 30px rgba(0,0,0,0.06);
            height: 100%;
        }
        .feature-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(31, 60, 136, 0.08);
            color: var(--primary);
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 0.85rem;
        }
        .feature-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--primary);
            color: #fff;
            font-size: 1.2rem;
            margin-bottom: 16px;
        }

        /* COMPARISON TABLE */
        .comparison-section { background-color: #f4f6f8; padding: 100px 0; }
        .comp-table-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .comp-header {
            background: var(--primary-dark);
            color: white;
            padding: 20px;
            text-align: center;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .comp-row {
            display: flex;
            border-bottom: 1px solid #eee;
        }
        .comp-row:last-child { border-bottom: none; }
        .comp-col-feature { flex: 2; padding: 20px; font-weight: 600; display: flex; align-items: center; background: #fcfcfc; }
        .comp-col-old { flex: 1.5; padding: 20px; text-align: center; color: #6c757d; border-right: 1px solid #eee; border-left: 1px solid #eee; }
        .comp-col-new { flex: 1.5; padding: 20px; text-align: center; color: var(--primary); font-weight: 700; background: rgba(31, 60, 136, 0.03); }
        
        @media (max-width: 768px) {
            .comp-row { flex-direction: column; text-align: center; }
            .comp-col-feature, .comp-col-old, .comp-col-new { border: none; padding: 10px; }
            .comp-col-feature { background: var(--accent); color: white; justify-content: center; }
            .hero-title { font-size: 2.5rem; }
        }

        /* DASHBOARD BLUR */
        .blur-wrapper { position: relative; border-radius: 15px; overflow: hidden; border: 1px solid #eee; }
        .blur-img { filter: blur(6px); width: 100%; opacity: 0.7; }
        .coming-soon-badge {
            position: absolute; top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            background: var(--primary); color: white;
            padding: 15px 30px; border-radius: 50px;
            font-weight: bold; box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            white-space: nowrap;
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-landing">
        <div class="container">
            <a class="navbar-brand text-white fw-bold fs-3" href="index.php">
                <i class="fas fa-shield-alt me-2 text-warning"></i><?php echo SITE_NAME; ?>
            </a>
            <div class="ms-auto text-white d-none d-md-block">
                <a href="yazar/login.php" class="btn btn-sm btn-outline-light px-3 py-2 fw-bold">YAZAR GİRİŞİ</a>
            </div>
        </div>
    </nav>

    <header class="hero">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="d-inline-block border border-danger text-danger px-3 py-1 rounded-pill fw-bold mb-3 small" style="background: rgba(220, 53, 69, 0.1);">
                        <i class="fas fa-lock me-1"></i> %100 KORSAN KORUMASI
                    </div>
                    <h1 class="hero-title">Emeğinizin<br>Dijital Kalesi.</h1>
                    <p class="hero-subtitle">PDF’leriniz Telegram gruplarında ücretsiz dağıtılmasın. <strong>DenemeAGS Dijital İmza Teknolojisi</strong> ile eserlerinizi güvenceye alıyor, sadece hak ettiğiniz kazancı sunuyoruz.</p>
                    
                    <div class="d-flex flex-column flex-sm-row gap-3">
                        <a href="yazar/register.php" class="btn-cta text-center">
                            <i class="fas fa-file-contract me-2"></i> Yayıncı Olun
                        </a>
                        <a href="yazar/login.php" class="btn-login text-center">
                            <i class="fas fa-sign-in-alt me-2"></i> Giriş Yapın
                        </a>
                    </div>
                    <p class="mt-4 text-white-50 small"><i class="fas fa-check text-success me-1"></i> Şeffaf Raporlama &nbsp;&nbsp; <i class="fas fa-check text-success me-1"></i> Yasal Güvence</p>
                </div>
                
                <div class="col-lg-6 mt-5 mt-lg-0">
                    <div class="security-demo-wrapper">
                        <div class="css-doc">
                            <div class="doc-header-line"></div>
                            <div class="doc-line w-100"></div>
                            <div class="doc-line w-100"></div>
                            <div class="doc-line w-75"></div>
                            <div class="doc-line w-90"></div>
                            <br>
                            <div class="doc-line w-100" style="height: 120px; background: #f8f9fa; border: 1px dashed #ced4da;"></div>
                            <br>
                            <div class="doc-line w-100"></div>
                            <div class="doc-line w-80"></div>
                            
                            <div class="doc-watermark">
                                <div>TC: 123*****890</div>
                                <div>IP: 192.168.1.1</div>
                                <div>AHMET YILMAZ</div>
                            </div>
                        </div>
                        <div class="text-center mt-3 text-white-50 small">
                            <i class="fas fa-eye me-1"></i> <strong>Canlı Önizleme:</strong> Her sayfa alıcının kimliğiyle damgalanır.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <section class="py-5 bg-white">
        <div class="container py-5">
            <div class="text-center mb-5">
                <h2 class="fw-bold text-primary">Çalışma Modellerimiz</h2>
                <p class="text-muted">Size en uygun yayıncılık modelini seçerek hemen kazanmaya başlayın.</p>
            </div>
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="sec-card shadow-sm">
                        <div class="sec-icon"><i class="fas fa-user-cog"></i></div>
                        <h4>Paket-1: Manuel İşleyiş</h4>
                        <p class="text-muted small">Kendi Shopier hesabınız üzerinden satış yaparsınız. Güvenlik altyapımızı kullanırken operasyonu siz yönetirsiniz.</p>
                        <ul class="list-unstyled small mt-3">
                            <li><i class="fas fa-check text-success me-2"></i> Kendi Shopier hesabınızdan tahsilat</li>
                            <li><i class="fas fa-check text-success me-2"></i> Panelden kendi kodlarınızı üretme</li>
                            <li><i class="fas fa-check text-success me-2"></i> <strong>Sadece %15</strong> hizmet bedeli</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="sec-card shadow-sm border-primary">
                        <div class="sec-icon"><i class="fas fa-robot"></i></div>
                        <h4>Paket-2: Otomatik (Full Servis)</h4>
                        <p class="text-muted small">Siz sadece PDF'i gönderin, geri kalan tüm satış, kod dağıtımı ve destek sürecini biz yönetelim.</p>
                        <ul class="list-unstyled small mt-3">
                            <li><i class="fas fa-check text-success me-2"></i> Tam otomatik satış ve teslimat</li>
                            <li><i class="fas fa-check text-success me-2"></i> <strong>Cironun %70'i</strong> yazar hakedişi</li>
                            <li><i class="fas fa-check text-success me-2"></i> 15 günlük periyotlarla düzenli ödeme</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5" style="background: #f4f6fb;">
        <div class="container py-5">
            <div class="text-center mb-5">
                <span class="feature-badge"><i class="fas fa-bolt"></i> YENİ</span>
                <h2 class="fw-bold mt-3">Yeni Nesil Ödeme ve Teslimat Deneyimi</h2>
                <p class="text-muted">Shopier entegrasyonumuz yenilendi. Ödeme sonrası ürün otomatik kütüphaneye tanımlanır ve satış raporları panelinize anında düşer.</p>
            </div>
            <div class="row g-4">
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="fas fa-credit-card"></i></div>
                        <h5 class="fw-bold">Yeni Sekmede Ödeme</h5>
                        <p class="text-muted small">Kullanıcılar Shopier sayfasını ayrı sekmede açar, ödeme akışı bölünmeden tamamlanır.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="fas fa-user-check"></i></div>
                        <h5 class="fw-bold">Otomatik Kütüphane Tanımlama</h5>
                        <p class="text-muted small">Ödeme onayı gelir gelmez ürün, kullanıcının kütüphanesine otomatik atanır.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="fas fa-chart-line"></i></div>
                        <h5 class="fw-bold">Anlık Satış Raporlama</h5>
                        <p class="text-muted small">Yazar panelinde satışlar, komisyonlar ve kazançlar gerçek zamanlı güncellenir.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="fas fa-envelope-open-text"></i></div>
                        <h5 class="fw-bold">Otomatik Bilgilendirme</h5>
                        <p class="text-muted small">Satın alan kullanıcıya erişim kodu ve giriş bilgileri otomatik e-posta ile iletilir.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="fas fa-shield-check"></i></div>
                        <h5 class="fw-bold">Güçlü Eşleştirme</h5>
                        <p class="text-muted small">Yeni eşleştirme yöntemi ile her ödeme doğru ürünle eşleştirilir, kayıp satış olmaz.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="fas fa-gift"></i></div>
                        <h5 class="fw-bold">Ödeme Tamamlandı Ekranı</h5>
                        <p class="text-muted small">Kullanıcılar ödeme sonrası net bir durum ekranı görür ve kütüphaneye yönlendirilir.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="comparison-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="text-center mb-5">
                        <h2 class="fw-bold">Dijitalin Gücü, Matbaanın Ötesinde</h2>
                        <p class="text-muted">Emeğinizin karşılığını tam olarak almanız için sistemi optimize ettik.</p>
                    </div>

                    <div class="comp-table-container">
                        <div class="d-none d-md-flex">
                            <div class="comp-col-feature bg-light">ÖZELLİK</div>
                            <div class="comp-col-old bg-light text-dark fw-bold">BASILI YAYIN</div>
                            <div class="comp-col-new" style="background: var(--primary); color: white;">DENEMEAGS DİJİTAL</div>
                        </div>

                        <div class="comp-row">
                            <div class="comp-col-feature"><i class="fas fa-percent me-2 text-muted"></i> Yazar Kazancı</div>
                            <div class="comp-col-old text-danger">%10 - %15</div>
                            <div class="comp-col-new text-success">%70 - %85</div>
                        </div>

                        <div class="comp-row">
                            <div class="comp-col-feature"><i class="fas fa-shield-alt me-2 text-muted"></i> Korsan Koruması</div>
                            <div class="comp-col-old">Yok (Fotokopi/Paylaşım)</div>
                            <div class="comp-col-new text-success"><i class="fas fa-check-circle me-1"></i> %100 Dijital İmza</div>
                        </div>

                        <div class="comp-row">
                            <div class="comp-col-feature"><i class="fas fa-bolt me-2 text-muted"></i> Teslimat Hızı</div>
                            <div class="comp-col-old">Günler süren kargo</div>
                            <div class="comp-col-new text-success">Anında (1 Dakika)</div>
                        </div>

                        <div class="comp-row">
                            <div class="comp-col-feature"><i class="fas fa-wallet me-2 text-muted"></i> Ödeme Süreci</div>
                            <div class="comp-col-old">6 aylık uzun vadeler</div>
                            <div class="comp-col-new text-success">Şeffaf ve Düzenli</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5 bg-white">
        <div class="container py-5 text-center">
            <h2 class="fw-bold mb-4">Geleceğin Yayıncılık Standartlarına Katılın</h2>
            <p class="lead text-muted mb-5">Hemen başvurunuzu yapın, eserlerinizi saniyeler içinde binlerce öğrenciye ulaştırın.</p>
            <div class="d-flex justify-content-center gap-3">
                <a href="yazar/register.php" class="btn btn-primary btn-lg px-5 shadow-sm">HEMEN KAYIT OLUN</a>
                <a href="yazar/login.php" class="btn btn-outline-primary btn-lg px-5">GİRİŞ YAPIN</a>
            </div>
        </div>
    </section>

    <section class="py-5 text-center bg-dark text-white">
        <div class="container">
            <p class="mb-3 opacity-75">Sorularınız için bizimle iletişime geçin:</p>
            <a href="mailto:denemeags@gmail.com" class="text-white fw-bold text-decoration-none">
                <i class="fas fa-envelope me-2"></i> denemeags@gmail.com
            </a>
            <div class="mt-5 pt-4 border-top border-secondary">
                <small>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. Tüm hakları saklıdır.</small>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
