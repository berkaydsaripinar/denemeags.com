<?php
// indir_karne.php
// Bu dosya, mPDF kullanarak kullanıcının deneme karnesini PDF olarak oluşturur.
// Ders bazlı analiz, özel uyarılar (yanlış+boş oranına göre) ve yanlış yapılan soruların detayları eklendi.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php'; 

// mPDF kütüphanesinin autoload.php dosyasının yolu
require_once __DIR__ . '/vendor/autoload.php'; 

requireLogin(); 

$katilim_id = filter_input(INPUT_GET, 'katilim_id', FILTER_VALIDATE_INT);

if (!$katilim_id) {
    http_response_code(400); 
    echo "Hata: Geçersiz katılım ID'si.";
    exit;
}

$user_id = $_SESSION['user_id'];

// Ders tanımlamaları ve soru aralıkları
$dersler_ve_soru_araliklari = [
    "Dört Temel Beceri" => ['baslangic' => 1, 'bitis' => 16],
    "Dil Bilimi" => ['baslangic' => 17, 'bitis' => 21], 
    "Dil Bilgisi" => ['baslangic' => 22, 'bitis' => 28], 
    "Çocuk Edebiyatı" => ['baslangic' => 29, 'bitis' => 31],
    "Türk Halk Edebiyatı" => ['baslangic' => 32, 'bitis' => 36],
    "Eski Türk Edebiyatı" => ['baslangic' => 37, 'bitis' => 41],
    "Yeni Türk Edebiyatı" => ['baslangic' => 42, 'bitis' => 46],
    "Edebiyat Bilgi ve Kuramları" => ['baslangic' => 47, 'bitis' => 50]
];
// ÖNEMLİ NOT: Eğer 21. soru Dil Bilgisi'ne aitse, yukarıdaki aralıkları düzenleyin:
// $dersler_ve_soru_araliklari["Dil Bilimi"] = ['baslangic' => 17, 'bitis' => 20];
// $dersler_ve_soru_araliklari["Dil Bilgisi"] = ['baslangic' => 21, 'bitis' => 28];


try {
    // Katılım, sonuçlar ve deneme bilgilerini çek
    $stmt_karne_data = $pdo->prepare("
        SELECT 
            kk.id AS katilim_id, 
            kk.deneme_id,
            kk.dogru_sayisi, 
            kk.yanlis_sayisi, 
            kk.bos_sayisi, 
            kk.net_sayisi, 
            kk.puan, 
            kk.puan_can_egrisi,
            kk.sinav_tamamlama_tarihi,
            d.deneme_adi, 
            d.soru_sayisi AS deneme_soru_sayisi,
            d.sonuc_aciklama_tarihi AS siralama_aciklama_tarihi,
            u.ad_soyad AS kullanici_adi_db
        FROM kullanici_katilimlari kk
        JOIN denemeler d ON kk.deneme_id = d.id
        JOIN kullanicilar u ON kk.kullanici_id = u.id
        WHERE kk.id = :katilim_id AND kk.kullanici_id = :user_id
    ");
    $stmt_karne_data->execute([':katilim_id' => $katilim_id, ':user_id' => $user_id]);
    $karne_data = $stmt_karne_data->fetch(PDO::FETCH_ASSOC);

    if (!$karne_data) {
        http_response_code(403);
        echo "Hata: Karne bilgileri bulunamadı veya bu karneye erişim yetkiniz yok.";
        exit;
    }

    if (empty($karne_data['sinav_tamamlama_tarihi'])) {
        http_response_code(403);
        echo "Hata: Karnenizi indirebilmek için öncelikle sınavı tamamlamanız gerekmektedir.";
        exit;
    }
    
    $deneme_id = $karne_data['deneme_id'];
    $user_ad_soyad_db = $karne_data['kullanici_adi_db'];

    // Kullanıcının tüm cevaplarını (verilen cevap ve doğruluk durumu) çek
    $stmt_kullanici_cevaplari_detay = $pdo->prepare("
        SELECT soru_no, verilen_cevap, dogru_mu 
        FROM kullanici_cevaplari 
        WHERE katilim_id = :katilim_id
    ");
    $stmt_kullanici_cevaplari_detay->execute([':katilim_id' => $katilim_id]);
    $kullanici_cevaplari_list = $stmt_kullanici_cevaplari_detay->fetchAll(PDO::FETCH_ASSOC);
    $kullanici_cevaplari_map = []; // soru_no => ['verilen_cevap' => X, 'dogru_mu' => Y]
    foreach ($kullanici_cevaplari_list as $cvp) {
        $kullanici_cevaplari_map[$cvp['soru_no']] = ['verilen_cevap' => $cvp['verilen_cevap'], 'dogru_mu' => $cvp['dogru_mu']];
    }

    // Denemenin cevap anahtarını çek
    $stmt_correct_answers = $pdo->prepare("SELECT soru_no, dogru_cevap FROM cevap_anahtarlari WHERE deneme_id = :deneme_id ORDER BY soru_no ASC");
    $stmt_correct_answers->execute([':deneme_id' => $deneme_id]);
    $correct_answers_map = $stmt_correct_answers->fetchAll(PDO::FETCH_KEY_PAIR); // soru_no => dogru_cevap


    // Ders bazlı analiz yap
    $ders_analizi_data = [];
    foreach ($dersler_ve_soru_araliklari as $ders_adi => $aralik) {
        $ders_toplam_soru = $aralik['bitis'] - $aralik['baslangic'] + 1;
        $ders_dogru = 0;
        $ders_yanlis = 0;
        $ders_bos = 0;
        $yanlis_yapilan_sorular_bu_derste = [];

        for ($soru_no = $aralik['baslangic']; $soru_no <= $aralik['bitis']; $soru_no++) {
            if (isset($kullanici_cevaplari_map[$soru_no])) {
                $cevap_detayi = $kullanici_cevaplari_map[$soru_no];
                if ($cevap_detayi['dogru_mu'] === 1) {
                    $ders_dogru++;
                } elseif ($cevap_detayi['dogru_mu'] === 0) {
                    $ders_yanlis++;
                    $yanlis_yapilan_sorular_bu_derste[] = [
                        'soru_no' => $soru_no,
                        'verilen_cevap' => $cevap_detayi['verilen_cevap'],
                        'dogru_cevap' => $correct_answers_map[$soru_no] ?? 'N/A'
                    ];
                } else { // null (veya farklı bir değer) ise boş
                    $ders_bos++;
                     // Boşları da yanlış detayında göstermek için (isteğe bağlı)
                    $yanlis_yapilan_sorular_bu_derste[] = [
                        'soru_no' => $soru_no,
                        'verilen_cevap' => 'Boş',
                        'dogru_cevap' => $correct_answers_map[$soru_no] ?? 'N/A'
                    ];
                }
            } else { // Cevap yoksa boş sayılır
                $ders_bos++;
                 $yanlis_yapilan_sorular_bu_derste[] = [ 
                        'soru_no' => $soru_no,
                        'verilen_cevap' => 'Boş',
                        'dogru_cevap' => $correct_answers_map[$soru_no] ?? 'N/A'
                    ];
            }
        }
        $ders_net = $ders_dogru - ($ders_yanlis / NET_KATSAYISI); 
        $ders_basari_yuzdesi = ($ders_toplam_soru > 0) ? (($ders_dogru / $ders_toplam_soru) * 100) : 0;
        
        // YANLIŞ + BOŞ yüzdesi hesaplaması
        $ders_yanlis_ve_bos_sayisi = $ders_yanlis + $ders_bos;
        $ders_yanlis_ve_bos_yuzdesi = ($ders_toplam_soru > 0) ? (($ders_yanlis_ve_bos_sayisi / $ders_toplam_soru) * 100) : 0;
        
        $uyari_mesaji = "";
        if ($ders_yanlis_ve_bos_yuzdesi > 50) { // Yanlış + Boş oranı %50'den fazlaysa
            $uyari_mesaji = "Bu dersi kesinlikle tekrar etmelisin.";
        } elseif ($ders_yanlis_ve_bos_yuzdesi > 30) { // Yanlış + Boş oranı %30'dan fazlaysa
            $uyari_mesaji = "Tekrar etmen gerekebilir ama korkacak bir şey de yok.";
        }

        $ders_analizi_data[] = [
            'ders_adi' => $ders_adi,
            'toplam_soru' => $ders_toplam_soru,
            'dogru' => $ders_dogru,
            'yanlis' => $ders_yanlis,
            'bos' => $ders_bos,
            'net' => $ders_net,
            'basari_yuzdesi' => $ders_basari_yuzdesi,
            'uyari' => $uyari_mesaji,
            'yanlis_sorular_detay' => $yanlis_yapilan_sorular_bu_derste 
        ];
    }

    // ... (Sıralama bilgisi çekme kodu aynı kalacak) ...
    $siralama_gosterim_metni_karne = null;
    $now = new DateTime('now', new DateTimeZone('Europe/Istanbul'));
    $siralama_aciklama_dt = new DateTime($karne_data['siralama_aciklama_tarihi'], new DateTimeZone('Europe/Istanbul'));
    $siralama_aciklandi_mi_karne = ($now >= $siralama_aciklama_dt);

    if ($siralama_aciklandi_mi_karne) {
        $stmt_siralama_karne = $pdo->prepare("
            SELECT COUNT(*) + 1 AS rank
            FROM kullanici_katilimlari
            WHERE deneme_id = :deneme_id
            AND sinav_tamamlama_tarihi IS NOT NULL
            AND COALESCE(puan_can_egrisi, puan, -9999) > COALESCE(:user_puan_can, :user_puan_raw, -9999)
        ");
        $stmt_siralama_karne->execute([
            ':deneme_id' => $deneme_id,
            ':user_puan_can' => $karne_data['puan_can_egrisi'],
            ':user_puan_raw' => $karne_data['puan']
        ]);
        $siralama_data_karne = $stmt_siralama_karne->fetch();
        $kullanici_siralamasi_karne = $siralama_data_karne['rank'] ?? null;

        $stmt_toplam_katilimci_karne = $pdo->prepare("
            SELECT COUNT(*) 
            FROM kullanici_katilimlari 
            WHERE deneme_id = :deneme_id AND sinav_tamamlama_tarihi IS NOT NULL
        ");
        $stmt_toplam_katilimci_karne->execute([':deneme_id' => $deneme_id]);
        $toplam_katilimci_karne = $stmt_toplam_katilimci_karne->fetchColumn();
        
        if ($kullanici_siralamasi_karne !== null && $toplam_katilimci_karne > 0) {
            $siralama_gosterim_metni_karne = $kullanici_siralamasi_karne . " / " . $toplam_katilimci_karne;
        } elseif ($kullanici_siralamasi_karne !== null) {
            $siralama_gosterim_metni_karne = $kullanici_siralamasi_karne . " (Toplam: " . $toplam_katilimci_karne . ")";
        } else {
            $siralama_gosterim_metni_karne = "Belirlenemedi";
        }
    }


    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8', 'format' => 'A4',
        'margin_left' => 15, 'margin_right' => 15,
        'margin_top' => 20, 'margin_bottom' => 25,
        'margin_header' => 10, 'margin_footer' => 10,
        'default_font' => 'dejavusans'
    ]);

    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
    $html .= '<style>
                body { font-family: "dejavusans", sans-serif; font-size: 10pt; color: #333; }
                .header { text-align: center; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom:10px;}
                .header h1 { color: #B45309; margin:0; font-size: 18pt;}
                .header h2 { color: #D97706; margin:0; font-size: 14pt; font-weight:normal; }
                .user-info { margin-bottom: 15px; padding: 10px; background-color: #FFFBEB; border: 1px solid #FDE68A; border-radius: 5px;}
                .user-info p { margin: 3px 0; }
                .section-title { font-size: 13pt; color: #B45309; margin-top: 15px; margin-bottom: 8px; border-bottom: 1px solid #FCD34D; padding-bottom: 4px;}
                table { width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 9pt;}
                th, td { border: 1px solid #FCD34D; padding: 5px; text-align: left; vertical-align: top; }
                th { background-color: #FEF3C7; font-weight: bold; }
                .text-center { text-align: center; }
                .fw-bold { font-weight: bold; }
                .text-success { color: #198754; }
                .text-danger { color: #dc3545; }
                .text-warning { color: #DAA520; } 
                .uyari-mesaji { font-size: 8.5pt; font-style: italic; color: #D97706; display:block; margin-top:3px;}
                .uyari-mesaji.tehlike { color: #dc3545; font-weight:bold; }
                .yanlis-soru-listesi { list-style-type: none; padding-left: 5px; margin-top: 5px; font-size: 8.5pt; }
                .yanlis-soru-listesi li { margin-bottom: 2px; }
                .yanlis-soru-listesi .user-answer { color: #E63946; } 
                .yanlis-soru-listesi .correct-answer { color: #2A9D8F; } 
                .footer { text-align: center; font-size: 8pt; color: #777; }
              </style>';
    $html .= '</head><body>';

    $html .= '<div class="header">';
    $html .= '<h1>' . escape_html(SITE_NAME) . '</h1>';
    $html .= '<h2>' . escape_html($karne_data['deneme_adi']) . ' Sınav Karnesi</h2>';
    $html .= '</div>';

    $html .= '<div class="user-info">';
    $html .= '<p><strong>Ad Soyad:</strong> ' . escape_html($user_ad_soyad_db) . '</p>';
    $html .= '<p><strong>Sınav Tamamlama Tarihi:</strong> ' . format_tr_datetime($karne_data['sinav_tamamlama_tarihi']) . '</p>';
    $html .= '</div>';

    $html .= '<div class="section-title">Genel Sonuçlarınız</div>';
    $html .= '<table>';
    $html .= '<tr><th>Doğru Sayısı</th><td class="text-center">' . $karne_data['dogru_sayisi'] . '</td></tr>';
    $html .= '<tr><th>Yanlış Sayısı</th><td class="text-center">' . $karne_data['yanlis_sayisi'] . '</td></tr>';
    $html .= '<tr><th>Boş Sayısı</th><td class="text-center">' . $karne_data['bos_sayisi'] . '</td></tr>';
    $html .= '<tr><th class="fw-bold">Net Sayınız</th><td class="text-center fw-bold">' . number_format($karne_data['net_sayisi'], 2) . '</td></tr>';
    $html .= '<tr><th>Ham Puanınız</th><td class="text-center">' . number_format($karne_data['puan'], 3) . '</td></tr>';
    if (isset($karne_data['puan_can_egrisi'])) {
        $html .= '<tr><th class="fw-bold">Çan Eğrisi Puanınız</th><td class="text-center fw-bold">' . number_format($karne_data['puan_can_egrisi'], 3) . '</td></tr>';
    }
    $html .= '</table>';

    if ($siralama_aciklandi_mi_karne && $siralama_gosterim_metni_karne) {
        $html .= '<div class="section-title">Genel Sıralamanız</div>';
        $html .= '<p style="font-size: 11pt; text-align:center;">Bu denemedeki sıralamanız: <strong style="color:#B45309;">' . escape_html($siralama_gosterim_metni_karne) . '</strong></p>';
    } elseif ($siralama_aciklandi_mi_karne) {
        $html .= '<div class="section-title">Genel Sıralamanız</div>';
        $html .= '<p style="font-size: 10pt; text-align:center; color:#777;">Sıralamanız hesaplanamadı veya henüz sıralamaya dahil edilmediniz.</p>';
    }

    if (!empty($ders_analizi_data)) {
        $html .= '<div class="section-title">Ders Bazlı Performans ve Yanlış Analizi</div>';
        $html .= '<table><thead><tr><th>Ders Adı ve Uyarılar / Yanlış Sorular</th><th class="text-center">Soru</th><th class="text-center">D</th><th class="text-center">Y</th><th class="text-center">B</th><th class="text-center">Net</th><th class="text-center">Başarı (%)</th></tr></thead><tbody>';
        foreach ($ders_analizi_data as $ders) {
            $basari_class_ders = ($ders['basari_yuzdesi'] >= 70) ? 'text-success' : (($ders['basari_yuzdesi'] >= 50) ? 'text-warning' : 'text-danger');
            $uyari_class_ders = ($ders['uyari'] === "Bu dersi kesinlikle tekrar etmelisin.") ? 'tehlike' : '';

            $html .= '<tr>';
            $html .= '<td><span class="fw-bold">' . escape_html($ders['ders_adi']) . '</span>';
            if (!empty($ders['uyari'])) {
                $html .= '<span class="uyari-mesaji ' . $uyari_class_ders . '">' . escape_html($ders['uyari']) . '</span>';
            }
            if (!empty($ders['yanlis_sorular_detay'])) {
                $html .= '<ul class="yanlis-soru-listesi">';
                foreach($ders['yanlis_sorular_detay'] as $yanlis_soru_detay) {
                     $html .= '<li>Soru ' . $yanlis_soru_detay['soru_no'] . ': Cevabınız: <span class="user-answer">' . escape_html($yanlis_soru_detay['verilen_cevap'] ?: 'Boş') . '</span>, Doğru: <span class="correct-answer">' . escape_html($yanlis_soru_detay['dogru_cevap']) . '</span></li>';
                }
                $html .= '</ul>';
            } elseif ($ders['yanlis'] > 0 || $ders['bos'] > 0 && empty($ders['yanlis_sorular_detay'])) { // Yanlış veya boş var ama detay yoksa
                 $html .= '<p style="font-size:8pt; color:#777;">Bu derste ' . ($ders['yanlis'] + $ders['bos']) . ' yanlış/boşunuz var (detaylar yüklenemedi).</p>';
            }

            $html .= '</td>';
            $html .= '<td class="text-center">' . $ders['toplam_soru'] . '</td>';
            $html .= '<td class="text-center">' . $ders['dogru'] . '</td>';
            $html .= '<td class="text-center">' . $ders['yanlis'] . '</td>';
            $html .= '<td class="text-center">' . $ders['bos'] . '</td>';
            $html .= '<td class="text-center fw-bold">' . number_format($ders['net'], 2) . '</td>';
            $html .= '<td class="text-center fw-bold ' . $basari_class_ders . '">' . number_format($ders['basari_yuzdesi'], 1) . '%</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
    }
    
    $mpdf->SetHTMLFooter('<div class="footer">Sayfa {PAGENO} / {nbpg} | ' . escape_html(SITE_NAME) . ' | Oluşturulma Tarihi: ' . date('d.m.Y H:i') . '</div>');
    $mpdf->WriteHTML($html);

    $filename = "deneme_karnesi_" . str_replace(' ', '_', $karne_data['deneme_adi']) . "_" . $user_id . ".pdf";
    $mpdf->Output($filename, \Mpdf\Output\Destination::DOWNLOAD); 
    exit;

} catch (PDOException $e) {
    error_log("Karne PDF (PDO) hatası (Katilim ID: $katilim_id): " . $e->getMessage());
    http_response_code(500);
    echo "Hata: Karne oluşturulurken bir veritabanı sorunu oluştu.";
    exit;
} catch (\Mpdf\MpdfException $e) { 
    error_log("Karne PDF (mPDF) hatası (Katilim ID: $katilim_id): " . $e->getMessage());
    http_response_code(500);
    echo "Hata: Karne PDF olarak oluşturulurken bir sorun oluştu.";
    exit;
} catch (Exception $e) { 
    error_log("Karne PDF (Genel) hatası (Katilim ID: $katilim_id): " . $e->getMessage());
    http_response_code(500);
    echo "Hata: Karne oluşturulurken beklenmedik bir hata meydana geldi.";
    exit;
}
?>
