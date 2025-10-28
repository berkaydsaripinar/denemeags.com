<?php
// view_solution.php
// Bu dosya, çözüm PDF'lerini, kullanıcının kullandığı erişim kodunu filigran olarak ekleyerek tarayıcıda görüntüler.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php'; 

// mPDF kütüphanesinin autoload.php dosyasının yolu
require_once __DIR__ . '/vendor/autoload.php'; 

use Mpdf\Mpdf;

requireLogin(); 

$katilim_id = filter_input(INPUT_GET, 'katilim_id', FILTER_VALIDATE_INT);

if (!$katilim_id) {
    http_response_code(400); 
    echo "Hata: Geçersiz katılım ID'si.";
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // 1. Katılım, deneme, kullanıcı ve kullanılan erişim kodu bilgilerini çek
    $stmt_data = $pdo->prepare("
        SELECT 
            kk.deneme_id,
            kk.erisim_kodu_id, 
            kk.sinav_tamamlama_tarihi,
            d.cozum_linki, 
            d.deneme_adi,
            d.aktif_mi AS deneme_aktif_mi,
            dek.kod AS kullanilan_erisim_kodu 
        FROM kullanici_katilimlari kk
        JOIN denemeler d ON kk.deneme_id = d.id
        JOIN deneme_erisim_kodlari dek ON kk.erisim_kodu_id = dek.id
        WHERE kk.id = :katilim_id AND kk.kullanici_id = :user_id
    ");
    $stmt_data->execute([':katilim_id' => $katilim_id, ':user_id' => $user_id]);
    $data = $stmt_data->fetch();

    if (!$data) {
        http_response_code(403);
        echo "Hata: Bu çözümü görüntüleme yetkiniz yok veya katılım bilgileri eksik.";
        exit;
    }
    if (!$data['deneme_aktif_mi']) { 
        http_response_code(403); echo "Hata: Bu deneme artık aktif değil."; exit; 
    }
    if (empty($data['sinav_tamamlama_tarihi'])) { 
        http_response_code(403); echo "Hata: Çözümleri görüntüleyebilmek için öncelikle sınavı tamamlamanız gerekmektedir."; exit; 
    }
    if (empty($data['cozum_linki'])) { 
        http_response_code(404); echo "Hata: Bu deneme için bir çözüm dosyası tanımlanmamış."; exit; 
    }
    if (empty($data['kullanilan_erisim_kodu'])) {
        error_log("Kritik Hata: Katılım ID $katilim_id için kullanılan erişim kodu bulunamadı. Veritabanı sorgusunu kontrol edin.");
        http_response_code(500); echo "Hata: Erişim bilgileri alınırken önemli bir sorun oluştu."; exit;
    }

    $deneme_id_for_log = $data['deneme_id'];
    $solution_pdf_filename = $data['cozum_linki'];
    $original_pdf_path = __DIR__ . '/uploads/solutions/' . $solution_pdf_filename;
    $erisim_kodu_filigran = $data['kullanilan_erisim_kodu']; // Bu değişkenin doğru değeri aldığından emin olalım.

    if (!file_exists($original_pdf_path) || !is_readable($original_pdf_path)) {
        error_log("Çözüm PDF dosyası bulunamadı: " . $original_pdf_path);
        http_response_code(404);
        echo "Hata: Çözüm dosyası sunucuda bulunamadı.";
        exit;
    }

    // Loglama işlemi (her görüntülemede)
    $ip_adresi = $_SERVER['REMOTE_ADDR'] ?? 'Bilinmiyor';
    $stmt_log_filigran = $pdo->prepare("
        INSERT INTO pdf_filigran_loglari (uuid, kullanici_id, deneme_id, katilim_id, ip_adresi)
        VALUES (:erisim_kodu, :kullanici_id, :deneme_id, :katilim_id, :ip_adresi)
    ");
    $stmt_log_filigran->execute([
        ':erisim_kodu' => $erisim_kodu_filigran,
        ':kullanici_id' => $user_id,
        ':deneme_id' => $deneme_id_for_log,
        ':katilim_id' => $katilim_id,
        ':ip_adresi' => $ip_adresi
    ]);

    $mpdf = new Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4', 
        'default_font' => 'dejavusans', // Türkçe karakterler için iyi bir font
        'setAutoTopMargin' => 'stretch', 
        'setAutoBottomMargin' => 'stretch' 
    ]);
    
    $pageCount = $mpdf->setSourceFile($original_pdf_path);

    for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
        $templateId = $mpdf->importPage($pageNo);
        $size = $mpdf->getTemplateSize($templateId); 

        $mpdf->AddPage($size['orientation'], '', 0, 0, 0, 0, 0, 0, 0, 0, '', '', '', '', '', $size['width'], $size['height']);
        $mpdf->useTemplate($templateId);

        // Filigran metnini oluştur
        $footerText = "Erişim Kodu: " . escape_html($erisim_kodu_filigran) . " | Kullanıcı ID: " . $user_id . " | Deneme ID: " . $deneme_id_for_log;
        
        // Font, renk ve konum ayarları
        $mpdf->SetFont('dejavusans', '', 7); // Fontu ve boyutu ayarla
        $mpdf->SetTextColor(120, 120, 120);  // Rengi biraz daha belirgin bir gri yapalım (0-255 arası)
        
        // Metni sayfanın sol alt köşesine yerleştir
        $x_pos = 10; // Sol kenardan boşluk (mm)
        $y_pos = $size['height'] - 7; // Alt kenardan boşluk (mm), değeri artırarak daha yukarı alabilirsiniz
        if ($y_pos < 5) $y_pos = 5; // Sayfanın çok altına gitmesini engelle

        $mpdf->SetXY($x_pos, $y_pos);
        // $mpdf->WriteText(0,0, $footerText); // WriteText yerine Cell kullanalım, bazen daha stabil olabilir.
        $mpdf->Cell(0, 5, $footerText, 0, 0, 'L'); // Sola yaslı, genişlik 0 (otomatik), yükseklik 5mm
    }

    $output_filename = "cozum_" . str_replace(' ', '_', $data['deneme_adi'] ?? 'deneme') . "_" . $user_id . ".pdf";
    
    ob_end_clean(); 
    $mpdf->Output($output_filename, \Mpdf\Output\Destination::INLINE); 
    exit;

} catch (PDOException $e) {
    error_log("Erişim Kodu Filigran PDF (PDO) hatası: " . $e->getMessage() . " | SQL State: " . $e->getCode() . " | Sorgu: " . ($stmt_data ? $stmt_data->queryString : "N/A") . " | Trace: " . $e->getTraceAsString()); 
    http_response_code(500); 
    echo "Hata: Veritabanı sorunu oluştu. Detaylar: <pre>" . escape_html($e->getMessage()) . "</pre>"; 
    echo "SQL State: " . escape_html($e->getCode());
    exit;
} catch (\Mpdf\MpdfException $e) { 
    error_log("Erişim Kodu Filigran PDF (mPDF) hatası: " . $e->getMessage());
    http_response_code(500); echo "Hata: PDF oluşturma sorunu: " . escape_html($e->getMessage()); exit; 
} catch (Exception $e) { 
    error_log("Erişim Kodu Filigran PDF (Genel) hatası: " . $e->getMessage());
    http_response_code(500); echo "Hata: Beklenmedik bir sorun oluştu."; exit;
}
?>
