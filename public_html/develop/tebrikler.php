<?php
// tebrikler.php (Kademeli Sıralama Animasyonu ile - Yeni Sıra ve Kısaltılmış Bekleme)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$deneme_id_animasyon = filter_input(INPUT_GET, 'deneme_id', FILTER_VALIDATE_INT);
if (!$deneme_id_animasyon) {
    redirect('dashboard.php'); 
}

$user_id = $_SESSION['user_id'];
$top_10_kullanicilar_anim = []; 
$deneme_adi_anim = "Belirtilen Deneme";

try {
    $stmt_deneme_info_anim = $pdo->prepare("SELECT deneme_adi, sonuc_aciklama_tarihi FROM denemeler WHERE id = ? AND aktif_mi = 1");
    $stmt_deneme_info_anim->execute([$deneme_id_animasyon]);
    $deneme_bilgisi = $stmt_deneme_info_anim->fetch();

    if (!$deneme_bilgisi) {
        redirect('dashboard.php'); 
    }
    $deneme_adi_anim = $deneme_bilgisi['deneme_adi'];

    $now_anim = new DateTime('now', new DateTimeZone('Europe/Istanbul'));
    $siralama_aciklama_dt_anim = new DateTime($deneme_bilgisi['sonuc_aciklama_tarihi'], new DateTimeZone('Europe/Istanbul'));
    if ($now_anim < $siralama_aciklama_dt_anim) {
        redirect('dashboard.php'); 
    }

    // İlk 10'u çek
    $stmt_top_10_anim = $pdo->prepare("
        SELECT k.ad_soyad, kk.net_sayisi
        FROM kullanici_katilimlari kk
        JOIN kullanicilar k ON kk.kullanici_id = k.id
        WHERE kk.deneme_id = :deneme_id AND kk.sinav_tamamlama_tarihi IS NOT NULL
        ORDER BY COALESCE(kk.puan_can_egrisi, kk.puan) DESC, kk.net_sayisi DESC, kk.dogru_sayisi DESC
        LIMIT 10 
    ");
    $stmt_top_10_anim->execute([':deneme_id' => $deneme_id_animasyon]);
    $top_10_kullanicilar_anim = $stmt_top_10_anim->fetchAll(PDO::FETCH_ASSOC);

    // Animasyonun görüldüğünü veritabanına kaydet
    $stmt_mark_viewed = $pdo->prepare("
        INSERT INTO kullanici_deneme_gorunumleri (kullanici_id, deneme_id, animasyon_goruldu_mu, ilk_goruntuleme_tarihi)
        VALUES (:user_id, :deneme_id, 1, NOW())
        ON DUPLICATE KEY UPDATE animasyon_goruldu_mu = 1, 
                                ilk_goruntuleme_tarihi = IF(animasyon_goruldu_mu = 0, NOW(), ilk_goruntuleme_tarihi)
    ");
    $stmt_mark_viewed->execute([':user_id' => $user_id, ':deneme_id' => $deneme_id_animasyon]);

} catch (PDOException $e) {
    error_log("Tebrikler sayfası veri çekme veya kaydetme hatası (Deneme ID: $deneme_id_animasyon): " . $e->getMessage());
    redirect('dashboard.php'); 
}

$page_title = "Sıralama Sonuçları Açıklandı!";
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escape_html($page_title) . " - " . escape_html(SITE_NAME); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&family=Pacifico&display=swap" rel="stylesheet">
    <style>
        body, html { height: 100%; margin: 0; overflow: hidden; }
        #tebrikSayfasi {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center; 
            width: 100vw;
            height: 100vh;
            background: linear-gradient(135deg, #FFFDE7 0%, #FFF3C4 100%); 
            color: #A16207; 
            font-family: 'Inter', sans-serif;
            text-align: center;
            padding: 20px;
            box-sizing: border-box;
            opacity: 0; 
            transition: opacity 0.5s ease-in-out;
        }
        #tebrikSayfasi.visible { opacity: 1; }

        .anim-item { opacity: 0; transform: translateY(25px); transition: opacity 0.7s ease-out, transform 0.7s ease-out; }
        .anim-item.visible { opacity: 1; transform: translateY(0px); }

        #tebrikBaslik { 
            font-family: 'Pacifico', cursive; 
            font-size: 4.5rem; 
            font-weight: normal; 
            margin-bottom: 5px; 
            color: #D97706; 
            text-shadow: 2px 2px 3px rgba(179, 100, 9, 0.2);
        }
        #tebrikDenemeAdi { 
            font-size: 1.8rem; 
            margin-bottom: 25px; 
            color: #B45309;
        }
        
        .siralama-grup { margin-bottom: 15px; width: 100%; max-width:500px; }
        
        .sira-item { 
            margin-bottom: 10px; 
            background-color: rgba(255, 255, 255, 0.65); 
            padding: 8px 15px; 
            border-radius: 8px; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            display: block; 
        }
        
        .top3-item { font-size: 1.6rem; padding: 12px 20px; } 
        .top3-item .sira-no { font-weight: 700; font-size: 1.9rem; margin-right:12px; }
        .top3-item .sira-no.birinci { color: #D4AF37; } 
        .top3-item .sira-no.ikinci { color: #A0A0A0; text-shadow: 0 0 1px #707070; } 
        .top3-item .sira-no.ucuncu { color: #B08D57; } 
        
        .next-ranks-item { font-size: 1.05rem; padding: 7px 12px;}
        .next-ranks-item .sira-no { font-weight: 500; margin-right:8px; color: #78553A; }

        .sira-item .ad-soyad { font-weight: 600; color: #8C6F4D; }
        .sira-item .net-bilgisi { font-size: 0.9em; color: #A16207; margin-left: 8px; }
        
        #devamButonu { 
            opacity: 0; 
            transition: opacity 0.5s ease-in-out;
            padding: 12px 35px; 
            font-size: 1.2rem;
            background-color: #F59E0B;
            color: #FFFFFF;
            border: none;
            border-radius: 50px; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        #devamButonu.visible { opacity: 1; }
        .emoji { font-size: 1.7rem; vertical-align: middle; margin-right: 5px;}
    </style>
</head>
<body>
    <div id="tebrikSayfasi">
        <div id="tebrikBaslik" class="anim-item">TEBRİKLER!</div>
        <div id="tebrikDenemeAdi" class="anim-item"><?php echo escape_html($deneme_adi_anim); ?> Sonuçları Açıklandı!</div>
        
        <div class="siralama-grup" id="siralamaListesi"> 
            <?php if (empty($top_10_kullanicilar_anim)): ?>
                 <p class="anim-item sira-item" id="sira-item-empty">Bu denemede sıralamaya giren katılımcı bilgisi bulunmamaktadır.</p>
            <?php endif; ?>
        </div>
        
        <button id="devamButonu" class="btn mt-4 anim-item">Panele Devam Et</button>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tebrikSayfasi = document.getElementById('tebrikSayfasi');
            const tebrikBaslik = document.getElementById('tebrikBaslik');
            const tebrikDenemeAdi = document.getElementById('tebrikDenemeAdi');
            const siralamaListesiDiv = document.getElementById('siralamaListesi');
            const devamButonu = document.getElementById('devamButonu');
            
            const top10Data = <?php echo json_encode($top_10_kullanicilar_anim); ?>;
            let currentDelay = 0;

            const animasyonSuresi = 700; 
            const kucukBekleme = 300; 
            const buyukBekleme = 1000; 
            const sonrakiGrupIcinEkBekleme = 2000; // 10 saniyeden 2 saniyeye düşürüldü

            // 0. Sayfayı görünür yap
            setTimeout(() => {
                tebrikSayfasi.classList.add('visible');
            }, 100);
            currentDelay += 100;

            // 1. "TEBRİKLER!"
            setTimeout(() => {
                tebrikBaslik.classList.add('visible');
            }, currentDelay + kucukBekleme);
            currentDelay += kucukBekleme + animasyonSuresi;

            // 2. Deneme Adı
            setTimeout(() => {
                tebrikDenemeAdi.classList.add('visible');
            }, currentDelay + kucukBekleme);
            currentDelay += kucukBekleme + animasyonSuresi;
            
            // 3. 10. kişiden 4. kişiye kadar olanlar (eğer varsa)
            const startIndexNextRanks = Math.min(9, top10Data.length - 1); 
            const endIndexNextRanks = 3; 

            for (let i = startIndexNextRanks; i >= endIndexNextRanks; i--) {
                if (top10Data[i]) {
                    const kisi = top10Data[i];
                    const siraNumarasi = i + 1;
                    const listItem = document.createElement('div');
                    listItem.classList.add('anim-item', 'sira-item', 'next-ranks-item');
                    listItem.innerHTML = `<span class="sira-no">${siraNumarasi}.</span> <span class="ad-soyad">${escapeHTML(kisi.ad_soyad)}</span> <span class="net-bilgisi">- ${parseFloat(kisi.net_sayisi).toFixed(2)} Net</span>`;
                    siralamaListesiDiv.appendChild(listItem);
                    
                    setTimeout(() => {
                        listItem.classList.add('visible');
                    }, currentDelay);
                    currentDelay += buyukBekleme; 
                }
            }
            if (startIndexNextRanks >= endIndexNextRanks) {
                 currentDelay += animasyonSuresi - buyukBekleme; 
            }

            // 4. İlk 3 kişi (eğer varsa) - belirlenen süre sonra
            const top3StartIndex = Math.min(2, top10Data.length - 1); 
            const emojiler = ["<span class='emoji'>🏆</span>", "<span class='emoji'>🥈</span>", "<span class='emoji'>🥉</span>"];
            const renkler_top3 = ["birinci", "ikinci", "ucuncu"];
            
            // Bekleme süresini ekle
            currentDelay += (top10Data.length > 3 ? sonrakiGrupIcinEkBekleme : 0);

            for (let i = top3StartIndex; i >= 0; i--) { // 3., 2., 1.
                if (top10Data[i]) {
                    const kisi = top10Data[i];
                    const siraNumarasiGercek = i + 1; 
                    const emojiIndex = i; 
                    
                    const listItem = document.createElement('div');
                    listItem.classList.add('anim-item', 'sira-item', 'top3-item');
                    listItem.innerHTML = `<span class="sira-no ${renkler_top3[emojiIndex] || ''}">${emojiler[emojiIndex] || (siraNumarasiGercek + '.')}</span> <span class="ad-soyad">${escapeHTML(kisi.ad_soyad)}</span> <span class="net-bilgisi">- ${parseFloat(kisi.net_sayisi).toFixed(2)} Net</span>`;
                    siralamaListesiDiv.appendChild(listItem); 
                    
                    setTimeout(() => {
                        listItem.classList.add('visible');
                    }, currentDelay);
                    currentDelay += buyukBekleme * 1.5; 
                }
            }
            if (top3StartIndex >= 0) {
                 currentDelay += animasyonSuresi - (buyukBekleme * 1.5);
            }

            // 5. Devam Butonu
            setTimeout(() => {
                devamButonu.classList.add('visible');
            }, currentDelay + buyukBekleme);


            devamButonu.addEventListener('click', function() {
                window.location.href = '<?php echo BASE_URL; ?>/dashboard.php';
            });

            function escapeHTML(str) {
                if (str === null || str === undefined) return '';
                return str.toString().replace(/[&<>"']/g, function (match) {
                    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[match];
                });
            }
        });
    </script>
</body>
</html>
