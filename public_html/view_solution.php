<?php
// view_solution.php
// PDF dosyalarını (Soru veya Çözüm), kullanıcının erişim kodunu filigran ekleyerek tarayıcıda görüntüler.
// Dinamik filigran kullanır.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php'; 
require_once __DIR__ . '/vendor/autoload.php'; 

use Mpdf\Mpdf;

requireLogin(); 

// Parametreleri güvenli al
$katilim_id = isset($_GET['katilim_id']) && is_numeric($_GET['katilim_id']) ? (int)$_GET['katilim_id'] : 0;
$deneme_id = isset($_GET['deneme_id']) && is_numeric($_GET['deneme_id']) ? (int)$_GET['deneme_id'] : 0;
$type = $_GET['type'] ?? 'solution'; // 'question' veya 'solution'

$user_id = $_SESSION['user_id'];

if (!$katilim_id && !$deneme_id) {
    http_response_code(400);
    die("Hata: Geçersiz parametreler.");
}

try {
    $pdf_data = null;
    $ip_adresi = $_SERVER['REMOTE_ADDR'] ?? 'Bilinmiyor';
    $user_ad_soyad_db = 'Kullanici';

    // Kullanıcı Ad Soyadını veritabanından çek
    $stmt_user_name = $pdo->prepare("SELECT ad_soyad FROM kullanicilar WHERE id = ?");
    $stmt_user_name->execute([$user_id]);
    $user_ad_soyad_db = $stmt_user_name->fetchColumn() ?? 'Kullanici';

    // 1. VERİ ÇEKME
    if ($katilim_id > 0) {
        $stmt = $pdo->prepare("
            SELECT 
                kk.deneme_id, kk.erisim_kodu_id, kk.sinav_tamamlama_tarihi,
                d.deneme_adi, d.tur, d.cozum_linki, d.soru_kitapcik_dosyasi, d.aktif_mi,
                ek.kod AS erisim_kodu
            FROM kullanici_katilimlari kk
            JOIN denemeler d ON kk.deneme_id = d.id
            LEFT JOIN erisim_kodlari ek ON kk.erisim_kodu_id = ek.id 
            WHERE kk.id = :id AND kk.kullanici_id = :uid
        ");
        $stmt->execute([':id' => $katilim_id, ':uid' => $user_id]);
        $pdf_data = $stmt->fetch(PDO::FETCH_ASSOC);

    } elseif ($deneme_id > 0) {
        $stmt = $pdo->prepare("
            SELECT 
                ke.deneme_id, ke.erisim_kodu_id, NULL as sinav_tamamlama_tarihi,
                d.deneme_adi, d.tur, d.cozum_linki, d.soru_kitapcik_dosyasi, d.aktif_mi,
                ek.kod AS erisim_kodu
            FROM kullanici_erisimleri ke
            JOIN denemeler d ON ke.deneme_id = d.id
            LEFT JOIN erisim_kodlari ek ON ke.erisim_kodu_id = ek.id
            WHERE ke.deneme_id = :id AND ke.kullanici_id = :uid
        ");
        $stmt->execute([':id' => $deneme_id, ':uid' => $user_id]);
        $pdf_data = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$pdf_data) { die("Hata: Bu dökümana erişim yetkiniz yok veya kayıt bulunamadı."); }

    // 2. DOSYA ADINI VE İZNİ BELİRLE
    $target_file = ($type === 'question') ? $pdf_data['soru_kitapcik_dosyasi'] : $pdf_data['cozum_linki'];
    // Dosyalar artık uploads/products klasöründen çekiliyor
    $file_path = __DIR__ . "/uploads/products/" . $target_file;
    
    // Çözüm görüntüleme kontrolü (Sadece Deneme türü için sınavı bitirme şartı)
    if ($type !== 'question' && isset($pdf_data['tur']) && $pdf_data['tur'] === 'deneme') {
         if ($katilim_id <= 0 || empty($pdf_data['sinav_tamamlama_tarihi'])) {
             // Eğer katilim_id yoksa veya sınav tamamlanmamışsa
             $stmt_check = $pdo->prepare("SELECT id FROM kullanici_katilimlari WHERE kullanici_id = ? AND deneme_id = ? AND sinav_tamamlama_tarihi IS NOT NULL");
             $stmt_check->execute([$user_id, $pdf_data['deneme_id']]);
             if (!$stmt_check->fetch()) {
                 die("Deneme sınavı çözümlerini görmek için önce sınavı tamamlamalısınız.");
             }
         }
    }

    if (empty($target_file) || !file_exists($file_path)) {
        die("Hata: İstenen dosya bulunamadı veya sisteme yüklenmemiş.");
    }

    // 3. LOGLAMA
    $erisim_kodu = $pdf_data['erisim_kodu'] ?? 'KOD_YOK';
    $deneme_id_log = $pdf_data['deneme_id'];
    
    $stmt_log = $pdo->prepare("INSERT INTO pdf_filigran_loglari (uuid, kullanici_id, deneme_id, katilim_id, ip_adresi) VALUES (:code, :uid, :did, :kid, :ip)");
    $stmt_log->execute([
        ':code' => $erisim_kodu, 
        ':uid' => $user_id, 
        ':did' => $deneme_id_log, 
        ':kid' => $katilim_id ?: null, 
        ':ip' => $ip_adresi
    ]);

    // 4. PDF OLUŞTURMA
    $mpdf = new Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4', 
        'default_font' => 'dejavusans',
        'tempDir' => __DIR__ . '/tmp'
    ]);
    
    $pageCount = $mpdf->setSourceFile($file_path);
    
    // --- GÜVENLİK FİLİGRANI TANIMLAMA ---
    // Filigran Metni: Ad Soyad | Erişim Kodu | IP Adresi
    $watermarkText = escape_html($user_ad_soyad_db) . " | KOD: " . escape_html($erisim_kodu) . " | IP: " . $ip_adresi;

    $mpdf->SetWatermarkText($watermarkText);
    $mpdf->showWatermarkText = true;
    $mpdf->watermark_font = 'dejavusans';
    $mpdf->watermarkTextAlpha = 0.08; // Siliklik
    $mpdf->watermarkAngle = 45; // Çaprazlık açısı

    for ($i = 1; $i <= $pageCount; $i++) {
        $tplId = $mpdf->importPage($i);
        $size = $mpdf->getTemplateSize($tplId);
        $mpdf->AddPage($size['orientation'], '', 0, 0, 0, 0, 0, 0, 0, 0, '', '', '', '', '', $size['width'], $size['height']);
        $mpdf->useTemplate($tplId);
    }

    $output_filename = $type . "_" . $deneme_id_log . ".pdf";
    
    ob_end_clean(); 
    $mpdf->Output($output_filename, \Mpdf\Output\Destination::INLINE); 

} catch (\Exception $e) {
    error_log("PDF Hatası: " . $e->getMessage());
    die("Bir hata oluştu: " . $e->getMessage());
}
?>