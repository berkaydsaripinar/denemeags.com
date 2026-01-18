<?php
// video_stream.php
// Erişim kontrolü yaparak video dosyalarını güvenli şekilde stream eder.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$token = $_GET['token'] ?? '';
$katilim_id = isset($_GET['katilim_id']) && is_numeric($_GET['katilim_id']) ? (int)$_GET['katilim_id'] : null;
$deneme_id = isset($_GET['deneme_id']) && is_numeric($_GET['deneme_id']) ? (int)$_GET['deneme_id'] : 0;
$user_id = $_SESSION['user_id'];

if (!$deneme_id || !validate_video_stream_token($token, $deneme_id, $katilim_id)) {
    http_response_code(403);
    exit('Yetkisiz erişim.');
}

try {
    if ($katilim_id) {
        $stmt = $pdo->prepare("
            SELECT kk.deneme_id, kk.sinav_tamamlama_tarihi, d.tur, d.cozum_video_dosyasi
            FROM kullanici_katilimlari kk
            JOIN denemeler d ON kk.deneme_id = d.id
            WHERE kk.id = :id AND kk.kullanici_id = :uid
        ");
        $stmt->execute([':id' => $katilim_id, ':uid' => $user_id]);
        $video_data = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare("
            SELECT ke.deneme_id, NULL as sinav_tamamlama_tarihi, d.tur, d.cozum_video_dosyasi
            FROM kullanici_erisimleri ke
            JOIN denemeler d ON ke.deneme_id = d.id
            WHERE ke.deneme_id = :id AND ke.kullanici_id = :uid
        ");
        $stmt->execute([':id' => $deneme_id, ':uid' => $user_id]);
        $video_data = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$video_data || empty($video_data['cozum_video_dosyasi'])) {
        http_response_code(404);
        exit('Video bulunamadı.');
    }

    if (isset($video_data['tur']) && $video_data['tur'] === 'deneme' && empty($video_data['sinav_tamamlama_tarihi'])) {
        $stmt_check = $pdo->prepare("SELECT id FROM kullanici_katilimlari WHERE kullanici_id = ? AND deneme_id = ? AND sinav_tamamlama_tarihi IS NOT NULL");
        $stmt_check->execute([$user_id, $video_data['deneme_id']]);
        if (!$stmt_check->fetch()) {
            http_response_code(403);
            exit('Sınav tamamlanmadan video çözümlere erişemezsiniz.');
        }
    }

    $video_filename = basename($video_data['cozum_video_dosyasi']);
    $file_path = __DIR__ . '/uploads/videos/' . $video_filename;

    if (!file_exists($file_path)) {
        http_response_code(404);
        exit('Video dosyası bulunamadı.');
    }

    $file_size = filesize($file_path);
    $start = 0;
    $end = $file_size - 1;

    $is_range_request = isset($_SERVER['HTTP_RANGE']);
    if ($is_range_request) {
        $range = str_replace('bytes=', '', $_SERVER['HTTP_RANGE']);
        $range = explode('-', $range);
        $start = (int)$range[0];
        if (isset($range[1]) && $range[1] !== '') {
            $end = (int)$range[1];
        }
        $end = min($end, $file_size - 1);
        if ($start > $end) {
            http_response_code(416);
            exit;
        }
        header('HTTP/1.1 206 Partial Content');
    }

    $length = $end - $start + 1;
    $mime_type = mime_content_type($file_path) ?: 'video/mp4';

    header('Content-Type: ' . $mime_type);
    header('Content-Length: ' . $length);
    header('Accept-Ranges: bytes');
    if ($is_range_request) {
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $file_size);
    }

    session_write_close();
    $chunk_size = 1024 * 1024;
    $handle = fopen($file_path, 'rb');
    fseek($handle, $start);

    while (!feof($handle) && (ftell($handle) <= $end)) {
        $bytes_to_read = min($chunk_size, $end - ftell($handle) + 1);
        echo fread($handle, $bytes_to_read);
        flush();
    }

    fclose($handle);
    exit;
} catch (PDOException $e) {
    error_log("Video stream hatası: " . $e->getMessage());
    http_response_code(500);
    exit('Bir hata oluştu.');
}
