<?php
/**
 * view_demo.php
 * Ürünlerin demo (örnek) PDF dosyalarını filigranlı şekilde görüntüler.
 * Yazarlar için kolaylık: Özel demo yoksa ana dosyadan otomatik üretir.
 * Performans: Oluşturulan demoları cache klasöründe saklar.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php'; 
require_once __DIR__ . '/vendor/autoload.php'; 

use Mpdf\Mpdf;

// 1. Parametre Kontrolü
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(400);
    die("Hata: Geçersiz ürün ID.");
}

// 2. Cache Klasörü Hazırlığı
$cache_dir = __DIR__ . '/cache/demos/';
if (!is_dir($cache_dir)) {
    mkdir($cache_dir, 0777, true);
}
$cache_file = $cache_dir . "demo_product_{$id}.pdf";

try {
    // 3. Ürün Verilerini Çek
    $stmt = $pdo->prepare("SELECT id, deneme_adi, demo_dosyasi, soru_kitapcik_dosyasi FROM denemeler WHERE id = ? AND aktif_mi = 1");
    $stmt->execute([$id]);
    $urun = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$urun) {
        die("Hata: Ürün bulunamadı veya aktif değil.");
    }

    // Hedef dosyayı belirle (Önce demo dosyasına bak, yoksa ana soru dosyasına bak)
    $target_file = !empty($urun['demo_dosyasi']) ? $urun['demo_dosyasi'] : $urun['soru_kitapcik_dosyasi'];
    
    // Dosyanın nerede olduğunu kontrol et (products klasörü standarttır)
    $file_path = __DIR__ . "/uploads/products/" . $target_file;

    if (empty($target_file) || !file_exists($file_path)) {
        die("Hata: Ön izleme dökümanı sunucuda bulunamadı.");
    }

    // 4. Cache Kontrolü (Kaynak dosya değişmediyse cache'den oku)
    if (file_exists($cache_file) && filemtime($cache_file) > filemtime($file_path)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="demo_' . $id . '.pdf"');
        readfile($cache_file);
        exit;
    }

    // 5. PDF Oluşturma (Cache yoksa veya eskiyse)
    $mpdf = new Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4', 
        'default_font' => 'dejavusans',
        'tempDir' => __DIR__ . '/tmp'
    ]);

    // Kaynak dosyayı yükle
    $pageCount = $mpdf->setSourceFile($file_path);
    
    // --- GÜVENLİK FİLİGRANI ---
    $watermarkText = "ÖRNEK İNCELEME - " . strtoupper(SITE_NAME);
    $mpdf->SetWatermarkText($watermarkText);
    $mpdf->showWatermarkText = true;
    $mpdf->watermarkTextAlpha = 0.1;
    $mpdf->watermarkAngle = 45;

    // Sadece ilk 5 sayfayı al (Yazara kolaylık: Otomatik kesme)
    $maxPages = min($pageCount, 5);

    for ($i = 1; $i <= $maxPages; $i++) {
        $tplId = $mpdf->importPage($i);
        $size = $mpdf->getTemplateSize($tplId);
        
        $mpdf->AddPage($size['orientation'], '', 0, 0, 0, 0, 0, 0, 0, 0, '', '', '', '', '', $size['width'], $size['height']);
        $mpdf->useTemplate($tplId);
        
        // Alt bilgi notu
        $mpdf->SetFont('dejavusans', 'B', 8);
        $mpdf->SetTextColor(150, 150, 150);
        $mpdf->SetXY(0, $size['height'] - 10);
        $mpdf->Cell(0, 10, "Bu bir tanitim dökümanidir. Tamami icin denemeags.com üzerinden satin aliniz.", 0, 0, 'C');
    }

    // 6. Dosyayı Hem Cache'e Kaydet Hem de Ekrana Bas
    ob_end_clean(); // Çıktı tamponunu temizle
    
    // Önce dosyayı fiziksel olarak cache klasörüne yaz
    $mpdf->Output($cache_file, \Mpdf\Output\Destination::FILE);
    
    // Sonra kullanıcıya gönder
    $mpdf->Output("demo_{$id}.pdf", \Mpdf\Output\Destination::INLINE); 

} catch (\Exception $e) {
    error_log("Demo PDF Üretim Hatası: " . $e->getMessage());
    die("Bir teknik sorun oluştu. Lütfen daha sonra tekrar deneyiniz.");
}