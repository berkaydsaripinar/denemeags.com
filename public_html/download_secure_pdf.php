<?php
// download_secure_pdf.php
// PDF dosyalarını (Soru veya Çözüm) kullanıcının bilgileriyle GÖRÜNÜR (Üst/Alt) ve GÖRÜNMEZ (Filigran) olarak damgalayarak indirir.
// GÜNCELLEME: 2. Sayfa olarak kişiye özel Yasal Uyarı sayfası ekler.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/vendor/autoload.php'; // mPDF için

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
    // Kullanıcı Ad Soyadını veritabanından çek (daha güvenilir)
    $stmt_user_name = $pdo->prepare("SELECT ad_soyad FROM kullanicilar WHERE id = ?");
    $stmt_user_name->execute([$user_id]);
    $user_ad_soyad = $stmt_user_name->fetchColumn() ?? 'Kullanici';

    // 1. Kullanıcının erişimini ve ürünün türünü (tur) bul
    $stmt_access = $pdo->prepare("
        SELECT 
            ke.id, 
            ek.kod AS erisim_kodu, 
            d.deneme_adi, 
            d.tur, 
            d.soru_kitapcik_dosyasi, 
            d.cozum_linki
        FROM kullanici_erisimleri ke
        JOIN denemeler d ON ke.deneme_id = d.id
        LEFT JOIN erisim_kodlari ek ON ke.erisim_kodu_id = ek.id
        WHERE ke.kullanici_id = :user_id AND ke.deneme_id = :deneme_id
    ");
    $stmt_access->execute([':user_id' => $user_id, ':deneme_id' => $deneme_id]);
    $data = $stmt_access->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        die('Bu dökümana erişim yetkiniz yok.');
    }

    $target_filename = '';
    $file_name_prefix = '';
    $specific_folder = '';

    if ($type === 'question') {
        if (empty($data['soru_kitapcik_dosyasi'])) die('Soru kitapçığı dosyası tanımlanmamış.');
        $target_filename = $data['soru_kitapcik_dosyasi'];
        $file_name_prefix = 'SoruKitapcigi';
        $specific_folder = 'questions';

    } elseif ($type === 'solution') {
        if (empty($data['cozum_linki'])) die('Çözüm kitapçığı dosyası tanımlanmamış.');

        // Deneme türü ise sınav kontrolü
        if ($data['tur'] === 'deneme') {
            $stmt_check_exam = $pdo->prepare("SELECT id FROM kullanici_katilimlari WHERE kullanici_id = ? AND deneme_id = ? AND sinav_tamamlama_tarihi IS NOT NULL");
            $stmt_check_exam->execute([$user_id, $deneme_id]);
            if (!$stmt_check_exam->fetch()) {
                 die('Deneme sınavı çözüm dökümanını indirmek için önce sınavı tamamlamanız gerekmektedir.');
            }
        }
        $target_filename = $data['cozum_linki'];
        $file_name_prefix = 'CozumKitapcigi';
        $specific_folder = 'solutions';
    }

    // --- DOSYA YOLU KONTROLÜ ---
    $possible_paths = [
        __DIR__ . "/uploads/$specific_folder/" . $target_filename, 
        __DIR__ . "/uploads/products/" . $target_filename          
    ];

    $file_path = null;
    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            $file_path = $path;
            break;
        }
    }

    if (!$file_path) {
        die('Dosya sunucuda bulunamadı. Lütfen yönetici ile iletişime geçin. (Aranan dosya: ' . htmlspecialchars($target_filename) . ')');
    }

    // 4. mPDF ile Filigranlama ve Metin Ekleme İşlemi
    $mpdf = new Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'default_font' => 'dejavusans',
        'tempDir' => __DIR__ . '/tmp' 
    ]);

    $pageCount = $mpdf->setSourceFile($file_path);
    $erisim_kodu = $data['erisim_kodu'] ?? 'KOD_BULUNAMADI';
    $document_id = bin2hex(random_bytes(8));
    
    // --- GÜVENLİK FİLİGRANI (Çapraz, Silik) ---
    $watermarkText = escape_html($user_ad_soyad) . " | KOD: " . escape_html($erisim_kodu) . " | IP: " . $ip_adresi . " | BELGE: " . $document_id;
    
    $mpdf->SetWatermarkText($watermarkText);
    $mpdf->showWatermarkText = true;
    $mpdf->watermark_font = 'dejavusans'; 
    $mpdf->watermarkTextAlpha = 0.08; 
    $mpdf->watermarkAngle = 45; 

    // --- ÜST VE ALT BİLGİ METİNLERİ ---
    $headerFooterText = sprintf(
        "KİŞİYE ÖZEL KOPYA: %s | ERIŞİM KODU: %s | BELGE KODU: %s | %s - BU BELGE PAYLAŞILAMAZ!", 
        escape_html($user_ad_soyad), 
        escape_html($erisim_kodu), 
        $document_id,
        date('d.m.Y H:i')
    );

    for ($i = 1; $i <= $pageCount; $i++) {
        // 1. Orijinal Sayfayı İçe Aktar ve Ekle
        $tplId = $mpdf->importPage($i);
        $size = $mpdf->getTemplateSize($tplId);
        
        $mpdf->AddPage($size['orientation'], '', 0, 0, 0, 0, 0, 0, 0, 0, '', '', '', '', '', $size['width'], $size['height']);
        $mpdf->useTemplate($tplId);
        
        // Sayfa Üzerine Bilgileri Yaz
        $mpdf->SetFont('dejavusans', 'B', 7); 
        $mpdf->SetTextColor(0, 0, 0); 
        $mpdf->SetXY(5, 5); 
        $mpdf->Cell(0, 5, $headerFooterText, 0, 0, 'C'); 

        $mpdf->SetFont('dejavusans', 'B', 7); 
        $mpdf->SetTextColor(0, 0, 0); 
        $mpdf->SetXY(5, $size['height'] - 7); 
        $mpdf->Cell(0, 5, $headerFooterText, 0, 0, 'C'); 

        // 2. Eğer bu 1. Sayfa (Kapak) ise, hemen arkasına Güvenlik Uyarısı Sayfası Ekle
        if ($i == 1) {
            $mpdf->AddPage('P'); // Dikey standart sayfa
            
            $guvenlik_html = '
                <div style="border: 5px solid #dc3545; padding: 40px; margin-top: 50px; text-align: center; font-family: dejavusans;">
                    <h1 style="color: #dc3545; font-size: 32pt; margin-bottom: 20px;">⚠️ YASAL UYARI</h1>
                    <p style="font-size: 14pt; margin-bottom: 30px;">Bu dijital belge, aşağıda kimlik bilgileri belirtilen kullanıcıya özel olarak lisanslanmıştır:</p>
                    
                    <div style="background-color: #f8f9fa; padding: 20px; border: 1px solid #ddd; margin: 0 auto; width: 80%;">
                        <p style="font-size: 12pt; margin: 5px 0;"><strong>Adı Soyadı:</strong> ' . escape_html($user_ad_soyad) . '</p>
                        <p style="font-size: 12pt; margin: 5px 0;"><strong>Erişim Kodu:</strong> ' . escape_html($erisim_kodu) . '</p>
                        <p style="font-size: 12pt; margin: 5px 0;"><strong>Belge Kodu:</strong> ' . $document_id . '</p>
                        <p style="font-size: 12pt; margin: 5px 0;"><strong>İndirme Tarihi:</strong> ' . date('d.m.Y H:i') . '</p>
                        <p style="font-size: 12pt; margin: 5px 0;"><strong>IP Adresi:</strong> ' . $ip_adresi . '</p>
                    </div>

                    <p style="font-size: 12pt; line-height: 1.6; margin-top: 40px; text-align: justify;">
                        5846 sayılı Fikir ve Sanat Eserleri Kanunu uyarınca; bu belgenin tamamının veya bir kısmının, hak sahibinin izni olmaksızın 
                        kopyalanması, çoğaltılması, dijital platformlarda (WhatsApp, Telegram, Sosyal Medya vb.) paylaşılması veya ticari amaçla kullanılması 
                        kesinlikle <strong>YASAKTIR ve SUÇTUR</strong>.
                    </p>
                    
                    <p style="font-size: 12pt; color: #dc3545; font-weight: bold; margin-top: 20px;">
                        Belge üzerinde, izinsiz paylaşım yapan kişiyi tespit etmeye yarayan görünür ve gizli dijital takip kodları bulunmaktadır. 
                        İhlal tespiti durumunda yasal işlem başlatılacak ve maddi/manevi tazminat talep edilecektir.
                    </p>

                    <p style="font-size: 11pt; margin-top: 25px;">
                        Bu belgenin bütünlüğü sunucuda kriptografik olarak doğrulanabilir. Belge kodu ile doğrulama talep edebilirsiniz.
                    </p>
                </div>
            ';
            
            $mpdf->WriteHTML($guvenlik_html);
            
            // Güvenlik sayfasına da alt bilgi ekleyelim (bütünlük için)
            $mpdf->SetFont('dejavusans', 'B', 7); 
            $mpdf->SetTextColor(100, 100, 100); 
            $mpdf->SetXY(5, 290); // Sayfa altı
            $mpdf->Cell(0, 5, $headerFooterText, 0, 0, 'C'); 
        }
    }

    $tempDir = __DIR__ . '/tmp';
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }

    $tempPdfPath = tempnam($tempDir, 'stamped_') . '.pdf';
    $mpdf->Output($tempPdfPath, \Mpdf\Output\Destination::FILE);

    if (!class_exists('Imagick')) {
        throw new RuntimeException('PDF güvenliği için Imagick PHP eklentisi gereklidir.');
    }

    $rasterDpi = 200;
    $imagick = new Imagick();
    $imagick->setResolution($rasterDpi, $rasterDpi);
    $imagick->readImage($tempPdfPath);

    $pageImages = [];
    foreach ($imagick as $index => $page) {
        $page->setImageBackgroundColor('white');
        $page->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
        $page->setImageFormat('png');
        $page->setImageCompressionQuality(90);

        $imagePath = $tempDir . '/page_' . $index . '_' . $document_id . '.png';
        $page->writeImage($imagePath);

        $pageImages[] = [
            'path' => $imagePath,
            'width_px' => $page->getImageWidth(),
            'height_px' => $page->getImageHeight()
        ];
        $page->clear();
    }
    $imagick->clear();
    $imagick->destroy();

    $signaturePayload = [
        'document_id' => $document_id,
        'user_id' => $user_id,
        'deneme_id' => $deneme_id,
        'type' => $type,
        'issued_at' => date('c')
    ];
    $signaturePayloadB64 = rtrim(strtr(base64_encode(json_encode($signaturePayload, JSON_UNESCAPED_UNICODE)), '+/', '-_'), '=');
    $signature = hash_hmac('sha256', $signaturePayloadB64, PDF_SIGNATURE_SECRET);
    $signatureToken = 'DENEMEAGS_SIG:' . $signaturePayloadB64 . '.' . $signature;

    $finalPdf = new Mpdf([
        'mode' => 'utf-8',
        'default_font' => 'dejavusans',
        'tempDir' => $tempDir
    ]);
    $finalPdf->SetTitle('DenemeAGS Güvenli PDF');
    $finalPdf->SetAuthor(SITE_NAME);
    $finalPdf->SetSubject('Güvenli indirme doğrulama bilgisi içerir.');
    $finalPdf->SetKeywords($signatureToken);

    foreach ($pageImages as $pageImage) {
        $widthMm = ($pageImage['width_px'] / $rasterDpi) * 25.4;
        $heightMm = ($pageImage['height_px'] / $rasterDpi) * 25.4;

        $finalPdf->AddPage('', '', 0, 0, 0, 0, 0, 0, 0, 0, '', '', '', '', '', $widthMm, $heightMm);
        $finalPdf->Image($pageImage['path'], 0, 0, $widthMm, $heightMm, 'PNG');
    }

    $pdfBinary = $finalPdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
    $pdfHash = hash('sha256', $pdfBinary);
    $pdfHmac = hash_hmac('sha256', $pdfHash, PDF_SIGNATURE_SECRET);

    $signatureLogPath = __DIR__ . '/uploads/pdf_signature_log.jsonl';
    $logEntry = [
        'document_id' => $document_id,
        'user_id' => $user_id,
        'deneme_id' => $deneme_id,
        'type' => $type,
        'file' => $target_filename,
        'signature_token' => $signatureToken,
        'hash' => $pdfHash,
        'hmac' => $pdfHmac,
        'created_at' => date('c'),
        'ip' => $ip_adresi
    ];
    file_put_contents($signatureLogPath, json_encode($logEntry, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);

    $outputName = $file_name_prefix . '_' . $deneme_id . '_' . date('Ymd') . '.pdf';
    if (ob_get_length()) {
        ob_end_clean();
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $outputName . '"');
    header('Content-Length: ' . strlen($pdfBinary));
    echo $pdfBinary;

    if (file_exists($tempPdfPath)) {
        unlink($tempPdfPath);
    }
    foreach ($pageImages as $pageImage) {
        if (file_exists($pageImage['path'])) {
            unlink($pageImage['path']);
        }
    }

} catch (\Exception $e) {
    die('Bir hata oluştu: ' . $e->getMessage());
}
?>
