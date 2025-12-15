<?php
// download_secure_pdf.php
// PDF dosyalarını (Soru veya Çözüm) kullanıcının bilgileriyle damgalayarak indirir.
// Hata düzeltmeleri: Deprecated filter kaldırıldı, veritabanı sütun adı deneme_id yapıldı.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/vendor/autoload.php'; // mPDF için

use Mpdf\Mpdf;

requireLogin();

$deneme_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// Deprecated FILTER_SANITIZE_STRING yerine doğrudan alıyoruz
$type = $_GET['type'] ?? ''; 

if (!$deneme_id || !in_array($type, ['question', 'solution'])) {
    die('Geçersiz parametreler.');
}

$user_id = $_SESSION['user_id'];
$user_ad_soyad = $_SESSION['user_ad_soyad'];
$ip_adresi = $_SERVER['REMOTE_ADDR'];

try {
    // 1. Kullanıcının erişimini ve ürünün türünü (tur) bul
    // DÜZELTME: ke.urun_id yerine ke.deneme_id kullanıldı.
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

    $file_path = '';
    $file_name_prefix = '';

    if ($type === 'question') {
        if (empty($data['soru_kitapcik_dosyasi'])) die('Soru kitapçığı dosyası bulunamadı.');
        $file_path = __DIR__ . '/uploads/questions/' . $data['soru_kitapcik_dosyasi'];
        $file_name_prefix = 'SoruKitapcigi';

    } elseif ($type === 'solution') {
        if (empty($data['cozum_linki'])) die('Çözüm kitapçığı dosyası bulunamadı.');

        // EĞER TÜR 'DENEME' İSE SINAV KONTROLÜ YAP
        if ($data['tur'] === 'deneme') {
            $stmt_check_exam = $pdo->prepare("SELECT id FROM kullanici_katilimlari WHERE kullanici_id = ? AND deneme_id = ? AND sinav_tamamlama_tarihi IS NOT NULL");
            $stmt_check_exam->execute([$user_id, $deneme_id]);
            if (!$stmt_check_exam->fetch()) {
                 die('Deneme sınavı çözüm dökümanını indirmek için önce sınavı tamamlamanız gerekmektedir.');
            }
        }
        // Eğer 'soru_bankasi' veya 'diger' ise kontrolsüz izin ver

        $file_path = __DIR__ . '/uploads/solutions/' . $data['cozum_linki'];
        $file_name_prefix = 'CozumKitapcigi';
    }

    if (!file_exists($file_path)) {
        die('Dosya sunucuda bulunamadı. Lütfen yönetici ile iletişime geçin.');
    }

    // 4. mPDF ile Damgalama İşlemi
    $mpdf = new Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'default_font' => 'dejavusans',
        'tempDir' => __DIR__ . '/tmp' 
    ]);

    $pageCount = $mpdf->setSourceFile($file_path);
    $erisim_kodu = $data['erisim_kodu'] ?? 'KOD_BULUNAMADI';

    for ($i = 1; $i <= $pageCount; $i++) {
        $tplId = $mpdf->importPage($i);
        $size = $mpdf->getTemplateSize($tplId);
        $mpdf->AddPage($size['orientation'], '', 0, 0, 0, 0, 0, 0, 0, 0, '', '', '', '', '', $size['width'], $size['height']);
        $mpdf->useTemplate($tplId);

        $headerText = sprintf("Ad Soyad: %s | IP: %s | Tarih: %s", $user_ad_soyad, $ip_adresi, date('d.m.Y H:i'));
        $mpdf->SetFont('dejavusans', 'B', 8); 
        $mpdf->SetTextColor(255, 0, 0); 
        $mpdf->SetXY(10, 5); 
        $mpdf->Cell(0, 0, $headerText, 0, 0, 'C');

        $footerText = sprintf("Erişim Kodu: %s | Ürün ID: %d | Bu belge kişiye özeldir, paylaşılamaz.", $erisim_kodu, $deneme_id);
        $mpdf->SetFont('dejavusans', 'I', 8); 
        $mpdf->SetTextColor(100, 100, 100); 
        $mpdf->SetXY(10, $size['height'] - 8); 
        $mpdf->Cell(0, 0, $footerText, 0, 0, 'C');
    }

    $outputName = $file_name_prefix . '_' . $deneme_id . '_' . date('Ymd') . '.pdf';
    $mpdf->Output($outputName, \Mpdf\Output\Destination::DOWNLOAD);

} catch (Exception $e) {
    die('Bir hata oluştu: ' . $e->getMessage());
}
?>