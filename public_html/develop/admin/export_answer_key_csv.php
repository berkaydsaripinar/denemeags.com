<?php
// admin/export_answer_key_csv.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAdminLogin();

$deneme_id = filter_input(INPUT_GET, 'deneme_id', FILTER_VALIDATE_INT);

if (!$deneme_id) {
    set_admin_flash_message('error', 'Geçersiz deneme ID\'si.');
    header("Location: manage_denemeler.php"); // Veya manage_cevaplar.php'ye geri
    exit;
}

try {
    // Deneme adını çek (dosya adı için)
    $stmt_deneme_name = $pdo->prepare("SELECT deneme_adi FROM denemeler WHERE id = ?");
    $stmt_deneme_name->execute([$deneme_id]);
    $deneme_adi_row = $stmt_deneme_name->fetch();
    $deneme_adi = $deneme_adi_row ? str_replace(' ', '_', preg_replace('/[^A-Za-z0-9_ -]/', '', $deneme_adi_row['deneme_adi'])) : 'deneme_'.$deneme_id;


    // Cevap anahtarını çek
    $stmt = $pdo->prepare("SELECT soru_no, dogru_cevap, konu_adi FROM cevap_anahtarlari WHERE deneme_id = ? ORDER BY soru_no ASC");
    $stmt->execute([$deneme_id]);
    $answer_key_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($answer_key_data)) {
        set_admin_flash_message('info', "Deneme ID $deneme_id için dışa aktarılacak cevap anahtarı bulunamadı.");
        header("Location: manage_cevaplar.php?deneme_id=" . $deneme_id);
        exit;
    }

    // CSV Dosyası Oluşturma
    $filename = "cevap_anahtari_" . $deneme_adi . "_" . date('YmdHis') . ".csv";
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w'); // Tarayıcıya çıktı akışı

    // UTF-8 BOM (Excel'in Türkçe karakterleri doğru görmesi için)
    fwrite($output, "\xEF\xBB\xBF"); 

    // Başlık Satırı
    fputcsv($output, ['Soru No', 'Dogru Cevap', 'Konu Adi']);

    // Veri Satırları
    foreach ($answer_key_data as $row) {
        fputcsv($output, [$row['soru_no'], $row['dogru_cevap'], $row['konu_adi']]);
    }

    fclose($output);
    exit;

} catch (PDOException $e) {
    set_admin_flash_message('error', "CSV dışa aktarılırken veritabanı hatası: " . $e->getMessage());
    header("Location: manage_cevaplar.php?deneme_id=" . $deneme_id);
    exit;
}
?>
