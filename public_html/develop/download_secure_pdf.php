<?php
// download_secure_pdf_v2.php
// GELİŞMİŞ GÜVENLİK: Rastgele Dağıtım (Scatter), Gürültü (Noise) ve Yasal Uyarı Sayfası

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/vendor/autoload.php';

use Mpdf\Mpdf;

requireLogin();

$deneme_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$type = $_GET['type'] ?? ''; 

if (!$deneme_id || !in_array($type, ['question', 'solution'])) {
    die('Geçersiz parametreler.');
}

$user_id = $_SESSION['user_id'];
$ip_adresi = $_SERVER['REMOTE_ADDR'];

try {
    // --- (Veritabanı Sorguları ve Dosya Yolu Bulma Kısımları Aynı Kalacak) ---
    $stmt_user_name = $pdo->prepare("SELECT ad_soyad FROM kullanicilar WHERE id = ?");
    $stmt_user_name->execute([$user_id]);
    $user_ad_soyad = $stmt_user_name->fetchColumn() ?? 'Kullanici';

    $stmt_access = $pdo->prepare("
        SELECT ke.id, ek.kod AS erisim_kodu, d.deneme_adi, d.tur, d.soru_kitapcik_dosyasi, d.cozum_linki
        FROM kullanici_erisimleri ke
        JOIN denemeler d ON ke.deneme_id = d.id
        LEFT JOIN erisim_kodlari ek ON ke.erisim_kodu_id = ek.id
        WHERE ke.kullanici_id = :user_id AND ke.deneme_id = :deneme_id
    ");
    $stmt_access->execute([':user_id' => $user_id, ':deneme_id' => $deneme_id]);
    $data = $stmt_access->fetch(PDO::FETCH_ASSOC);

    if (!$data) die('Erişim yetkiniz yok.');

    // Dosya yolu belirleme
    $target_filename = ($type === 'question') ? $data['soru_kitapcik_dosyasi'] : $data['cozum_linki'];
    $specific_folder = ($type === 'question') ? 'questions' : 'solutions';
    
    // Çözüm kontrolü
    if ($type === 'solution' && $data['tur'] === 'deneme') {
         $stmt_check = $pdo->prepare("SELECT id FROM kullanici_katilimlari WHERE kullanici_id=? AND deneme_id=? AND sinav_tamamlama_tarihi IS NOT NULL");
         $stmt_check->execute([$user_id, $deneme_id]);
         if (!$stmt_check->fetch()) die('Önce sınavı tamamlamalısınız.');
    }

    $file_path = resolve_document_file_path($target_filename, $type === 'question' ? 'question' : 'solution');
    if (!$file_path) die('Dosya bulunamadı.');

    // Temp ve ID işlemleri
    $tempDir = TMP_DIR;
    ensure_directory($tempDir);
    
    $erisim_kodu = $data['erisim_kodu'] ?? 'X';
    $document_id = bin2hex(random_bytes(4)); // ID
    
    // --- GÜVENLİK METNİ HAZIRLIĞI ---
    $identity_string = mb_strtoupper(escape_html($user_ad_soyad)) . " - " . $erisim_kodu;
    $unique_id_string = "ID: " . $document_id . " - IP: " . $ip_adresi;

    // mPDF Başlat
    $mpdf = new Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'default_font' => 'dejavusans', 
        'tempDir' => $tempDir
    ]);

    $mpdf->SetTitle('DenemeAGS Lisanslı Belge');
    $mpdf->SetAuthor($user_ad_soyad);

    // PDF Okuma
    $pageCount = $mpdf->setSourceFile($file_path);

    for ($i = 1; $i <= $pageCount; $i++) {
        
        // --- 1. ASIL PDF SAYFALARI (Önce basılır) ---
        $tplId = $mpdf->importPage($i);
        $size = $mpdf->getTemplateSize($tplId);
        
        $mpdf->AddPage($size['orientation'], '', 0, 0, 0, 0, 0, 0, 0, 0, '', '', '', '', '', $size['width'], $size['height']);
        $mpdf->useTemplate($tplId);

        // Güvenlik katmanlarını uygula
        applySecurityLayers($mpdf, $size['width'], $size['height'], $identity_string, $unique_id_string, $document_id);

        // --- 2. YASAL UYARI SAYFASI (1. Sayfadan Sonra Araya Girer) ---
        // Böylece 1. sayfa (Kapak) biter bitmez Yasal Uyarı gelir.
        if ($i == 1) {
            $mpdf->AddPage('P'); // Yeni bir dikey sayfa (Yasal Uyarı İçin)
            
            // Yasal Uyarı HTML Tasarımı
            $guvenlik_html = '
                <div style="border: 5px solid #dc3545; padding: 40px; margin-top: 50px; text-align: center; font-family: dejavusans;">
                    <h1 style="color: #dc3545; font-size: 32pt; margin-bottom: 20px;">⚠️ YASAL UYARI</h1>
                    <p style="font-size: 14pt; margin-bottom: 30px;">Bu dijital belge, aşağıda kimlik bilgileri belirtilen kullanıcıya özel olarak lisanslanmıştır:</p>
                    
                    <div style="background-color: #f8f9fa; padding: 20px; border: 1px solid #ddd; margin: 0 auto; width: 80%;">
                        <p style="font-size: 12pt; margin: 5px 0;"><strong>Adı Soyadı:</strong> ' . escape_html($user_ad_soyad) . '</p>
                        <p style="font-size: 12pt; margin: 5px 0;"><strong>Erişim Kodu:</strong> ' . escape_html($erisim_kodu) . '</p>
                        <p style="font-size: 12pt; margin: 5px 0;"><strong>Belge ID:</strong> ' . $document_id . '</p>
                        <p style="font-size: 12pt; margin: 5px 0;"><strong>İndirme Tarihi:</strong> ' . date('d.m.Y H:i') . '</p>
                        <p style="font-size: 12pt; margin: 5px 0;"><strong>IP Adresi:</strong> ' . $ip_adresi . '</p>
                    </div>

                    <p style="font-size: 12pt; line-height: 1.6; margin-top: 40px; text-align: justify;">
                        5846 sayılı Fikir ve Sanat Eserleri Kanunu uyarınca; bu belgenin izinsiz paylaşımı, internet ortamında yayınlanması veya ticari amaçla kullanımı YASAKTIR. 
                        Tespiti halinde yasal işlem başlatılacaktır.
                    </p>
                    <p style="font-size: 10pt; margin-top: 30px; color: #666;">
                        Dijital İmza ID: ' . substr($document_id, 0, 20) . '...
                    </p>
                </div>
            ';
            $mpdf->WriteHTML($guvenlik_html);

            // Yasal uyarı sayfasına da güvenlik katmanlarını bas (Silinip not kağıdı yapılmasın)
            applySecurityLayers($mpdf, 210, 297, $identity_string, $unique_id_string, $document_id);
        }
    }

    // İZİN AYARLARI
    $mpdf->SetProtection(['print', 'annot-forms'], '', PDF_SIGNATURE_SECRET);

    // Çıktı
    $outputName = 'Guvenli_' . $deneme_id . '.pdf';
    $mpdf->Output($outputName, 'I'); 

} catch (\Exception $e) {
    die('Hata: ' . $e->getMessage());
}

/**
 * Sayfaya Gürültü ve Dağıtılmış Filigran Basan Yardımcı Fonksiyon
 * Kod tekrarını önlemek için buraya alındı.
 */
function applySecurityLayers($mpdf, $width, $height, $identity_string, $unique_id_string, $document_id) {
    
    // --- KATMAN A: GÜRÜLTÜ ÇİZGİLERİ (Noise Lines) ---
    // Otomatik temizleme araçlarını şaşırtmak için
    $mpdf->SetAlpha(0.03); // Çok silik (%3)
    $mpdf->SetDrawColor(0, 0, 0);
    
    for ($line = 0; $line < 15; $line++) {
        $x1 = rand(0, (int)$width);
        $y1 = rand(0, (int)$height);
        $x2 = rand(0, (int)$width);
        $y2 = rand(0, (int)$height);
        $mpdf->Line($x1, $y1, $x2, $y2);
    }

    // --- KATMAN B: SCATTER (SAÇILMA) FİLİGRAN ---
    $watermark_count = 25; // Sayfa başına adet
    
    for ($w = 0; $w < $watermark_count; $w++) {
        $posX = rand(10, (int)$width - 10);
        $posY = rand(10, (int)$height - 10);
        $angle = rand(-45, 45);
        $opacity = rand(4, 9) / 100; // Değişken opaklık
        $fontSize = rand(8, 12);
        
        // Metni değiştir (Ad Soyad veya ID)
        $textToPrint = ($w % 2 == 0) ? $identity_string : $unique_id_string;

        $mpdf->SetAlpha($opacity);
        $mpdf->SetFont('dejavusans', 'B', $fontSize);
        $mpdf->WriteText($posX, $posY, $textToPrint, $angle);
    }

    // --- KATMAN C: GÖRÜNMEZ METİN (Steganography Lite) ---
    $mpdf->SetAlpha(0); // Tamamen görünmez
    $mpdf->SetFont('dejavusans', '', 1);
    $mpdf->SetXY(10, 10);
    $mpdf->WriteCell(10, 10, "Bu belge $identity_string ($document_id) kişisine aittir. İzinsiz çoğaltılamaz.");
    
    $mpdf->SetAlpha(1); // Normale dön
}
?>
