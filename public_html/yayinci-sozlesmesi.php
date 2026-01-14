<?php
/**
 * yayinci-sozlesmesi.php - Deneme AGS Yayıncı İş Birliği Sözleşmesi
 */
require_once __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yayıncı Sözleşmesi | <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1F3C88;
            --primary-dark: #0f1e45;
            --accent: #F57C00;
        }
        body { font-family: 'Open Sans', sans-serif; background-color: #f8f9fa; color: #333; line-height: 1.8; }
        h1, h2, h3, h4 { font-family: 'Montserrat', sans-serif; color: var(--primary-dark); }
        
        .contract-container { 
            max-width: 900px; 
            margin: 50px auto; 
            background: white; 
            padding: 60px; 
            border-radius: 20px; 
            box-shadow: 0 15px 50px rgba(0,0,0,0.05);
        }
        
        .contract-header { text-align: center; border-bottom: 2px solid #eee; padding-bottom: 30px; margin-bottom: 40px; }
        .contract-header i { color: var(--accent); font-size: 3rem; margin-bottom: 15px; }
        
        .article-title { 
            background: #f0f4f8; 
            padding: 10px 20px; 
            border-left: 5px solid var(--primary); 
            font-weight: 700; 
            margin: 35px 0 20px 0;
            text-transform: uppercase;
            font-size: 1.1rem;
        }
        
        .package-box { 
            border: 1px solid #e2e8f0; 
            border-radius: 12px; 
            padding: 25px; 
            margin-bottom: 20px; 
            background: #fcfcfc;
        }
        .package-box h5 { color: var(--primary); font-weight: 700; }
        
        .back-btn { margin-bottom: 20px; display: inline-block; color: var(--primary); text-decoration: none; font-weight: 600; }
        
        @media (max-width: 768px) {
            .contract-container { padding: 30px 20px; margin: 20px; }
            .contract-header h1 { font-size: 1.5rem; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="contract-container">
        <a href="yazar/register.php" class="back-btn"><i class="fas fa-arrow-left me-2"></i> Kayıt Sayfasına Dön</a>
        
        <div class="contract-header">
            <i class="fas fa-file-signature"></i>
            <h1>DİJİTAL YAYINCI VE İŞ BİRLİĞİ SÖZLEŞMESİ</h1>
            <p class="text-muted">Son Güncelleme: <?php echo date('d.m.Y'); ?></p>
        </div>

        <p>İşbu sözleşme, bir tarafta <strong>Deneme AGS</strong> (Bundan sonra "PLATFORM" olarak anılacaktır) ile diğer tarafta platforma kayıt olan ve eserlerini sisteme yükleyen <strong>Yazar/Yayıncı</strong> (Bundan sonra "YAYINCI" olarak anılacaktır) arasında, dijital içeriklerin korunması, satışı ve dağıtımı hususunda akdedilmiştir.</p>

        <div class="article-title">MADDE 1: SÖZLEŞMENİN KONUSU</div>
        <p>Sözleşmenin konusu, YAYINCI'ya ait deneme sınavı, soru bankası veya her türlü eğitim materyalinin PDF formatında PLATFORM altyapısına yüklenmesi, siber güvenlik teknolojileri ile korunması ve PLATFORM tarafından sunulan modeller çerçevesinde öğrencilere ulaştırılmasıdır.</p>

        <div class="article-title">MADDE 2: ÇALIŞMA MODELLERİ VE MALİ ŞARTLAR</div>
        <p>YAYINCI, kayıt esnasında aşağıdaki iki modelden birini tercih eder. Seçilen model, yüklenen her yeni eser için aksine bir bildirim olmadıkça geçerli kabul edilir.</p>
        
        <div class="package-box">
            <h5>MODEL-1: MANUEL İŞLEYİŞ (Hizmet Bedeli Esası)</h5>
            <ul>
                <li>YAYINCI, satış işlemlerini kendi Shopier veya harici tahsilat kanalları üzerinden gerçekleştirir.</li>
                <li>PLATFORM, sadece siber güvenlik (filigran, dinamik damgalama) ve sınav altyapısını sağlar.</li>
                <li>YAYINCI, sistem üzerinden oluşturduğu ve dağıttığı her bir kod/aktivasyon için brüt satış bedelinin <strong>%15</strong>'ini PLATFORM'a hizmet bedeli olarak öder.</li>
                <li>Mahsuplaşma ve ödeme takibi haftalık periyotlarda gerçekleştirilir.</li>
            </ul>
        </div>

        <div class="package-box">
            <h5>MODEL-2: OTOMATİK İŞLEYİŞ (Tam Servis Esası)</h5>
            <ul>
                <li>Satış, tahsilat, kod üretimi ve otomatik teslimat süreçlerinin tamamı PLATFORM tarafından yönetilir.</li>
                <li>Elde edilen toplam cironun <strong>%70</strong>'i YAYINCI hakedişi olarak ayrılır.</li>
                <li>PLATFORM, %30'luk pay içerisinde vergi, banka komisyonları ve işletme giderlerini karşılar.</li>
                <li>Hakediş ödemeleri, 14 günlük periyotlarla YAYINCI'nın beyan ettiği IBAN hesabına gönderilir.</li>
            </ul>
        </div>

        <div class="article-title">MADDE 3: SİBER GÜVENLİK VE FİKRİ MÜLKİYET</div>
        <p>3.1. PLATFORM, YAYINCI'nın eserlerini korumak amacıyla her sayfa üzerine alıcıya özel T.C. Kimlik No, Ad Soyad ve IP adresi damgalama teknolojisini kullanır.</p>
        <p>3.2. Eserlerin tüm fikri ve sınai mülkiyet hakları YAYINCI'ya aittir. PLATFORM, sadece dijital satış ve sınav uygulama yetkisine sahiptir.</p>
        <p>3.3. Eserlerin içeriğinden kaynaklanabilecek her türlü hukuki sorumluluk (telif hakkı ihlali, yanlış bilgi vb.) tamamen YAYINCI'ya aittir.</p>

        <div class="article-title">MADDE 4: ONAY SÜRECİ</div>
        <p>YAYINCI tarafından sisteme yüklenen içerikler, PLATFORM yönetimi tarafından kontrol edildikten sonra (maksimum 48 saat içinde) onaylanır. Uygun görülmeyen içerikler PLATFORM tarafından gerekçe gösterilmeksizin reddedilebilir.</p>

        <div class="article-title">MADDE 5: SÖZLEŞME FESHİ</div>
        <p>Taraflar, 15 gün önceden yazılı bildirim yapmak kaydıyla sözleşmeyi tek taraflı feshedebilir. Fesih durumunda, o tarihe kadar satılmış olan kodların kullanım hakları öğrencilere karşı korunmaya devam eder.</p>

        <div class="mt-5 p-4 bg-light border-start border-warning border-4">
            <p class="small mb-0"><strong>NOT:</strong> Yazar paneli üzerinden "Kayıt Ol" butonuna basarak işbu sözleşmeyi elektronik ortamda onaylamış sayılırsınız. PLATFORM, sözleşme şartlarında yapılacak güncellemeleri 7 gün önceden YAYINCI'ya bildirmekle yükümlüdür.</p>
        </div>

        <div class="text-center mt-5">
            <button onclick="window.print();" class="btn btn-outline-secondary btn-sm"><i class="fas fa-print me-2"></i> Bu Belgeyi Yazdır</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>