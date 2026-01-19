<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$deneme_id = filter_input(INPUT_GET, 'deneme_id', FILTER_VALIDATE_INT);
$expires = filter_input(INPUT_GET, 'expires', FILTER_VALIDATE_INT);
$token = $_GET['token'] ?? '';

if (!$deneme_id || !$expires || empty($token)) {
    http_response_code(400);
    exit('Geçersiz istek.');
}

if ($expires < time()) {
    http_response_code(403);
    exit('Erişim süresi doldu.');
}

$user_id = $_SESSION['user_id'];
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
$payload = $user_id . '|' . $deneme_id . '|' . $expires . '|' . session_id() . '|' . $ip_address;
$expected_token = hash_hmac('sha256', $payload, VIDEO_STREAM_SECRET);

if (!hash_equals($expected_token, $token)) {
    http_response_code(403);
    exit('Geçersiz erişim anahtarı.');
}

try {
    $stmt_access = $pdo->prepare("
        SELECT d.tur, d.cozum_video_dosyasi
        FROM kullanici_erisimleri ke
        JOIN denemeler d ON ke.deneme_id = d.id
        WHERE ke.kullanici_id = :user_id AND ke.deneme_id = :deneme_id
    ");
    $stmt_access->execute([':user_id' => $user_id, ':deneme_id' => $deneme_id]);
    $video_data = $stmt_access->fetch(PDO::FETCH_ASSOC);

    if (!$video_data || empty($video_data['cozum_video_dosyasi'])) {
        http_response_code(403);
        exit('Video erişimi bulunamadı.');
    }

    if ($video_data['tur'] === 'deneme') {
        $stmt_check_exam = $pdo->prepare("
            SELECT id FROM kullanici_katilimlari
            WHERE kullanici_id = ? AND deneme_id = ? AND sinav_tamamlama_tarihi IS NOT NULL
        ");
        $stmt_check_exam->execute([$user_id, $deneme_id]);
        if (!$stmt_check_exam->fetch()) {
            http_response_code(403);
            exit('Sınav tamamlanmadan video çözüm izlenemez.');
        }
    }

    $video_filename = trim($video_data['cozum_video_dosyasi']);

    $resolveVideoPath = function (string $fileName): ?string {
        $fileName = trim($fileName);
        
        // Boş dosya adı kontrolü
        if (empty($fileName)) {
            return null;
        }
        
        // Sadece dosya adını al (eğer path içeriyorsa)
        $fileName = basename($fileName);
        
        // Güvenlik: Dosya adında .. veya / olmamalı
        if (strpos($fileName, '..') !== false || strpos($fileName, '/') !== false) {
            return null;
        }
        
        // Base dizinler - public_html ile aynı seviyede uploads klasörü
        $baseDirs = [
            dirname(__DIR__) . '/uploads/videos',
            dirname(__DIR__) . '/uploads/products'
        ];

        // Her dizinde dosyayı ara
        foreach ($baseDirs as $baseDir) {
            $fullPath = $baseDir . DIRECTORY_SEPARATOR . $fileName;
            
            if (is_file($fullPath)) {
                // Güvenlik kontrolü: Dosya gerçekten base dizin içinde mi?
                $realPath = realpath($fullPath);
                $realBaseDir = realpath($baseDir);
                
                if ($realPath && $realBaseDir && str_starts_with($realPath, $realBaseDir . DIRECTORY_SEPARATOR)) {
                    return $realPath;
                }
            }
        }
        
        return null;
    };

    $video_path = $resolveVideoPath($video_filename);

    if (!$video_path || !is_readable($video_path)) {
        http_response_code(404);
        exit('Video dosyası bulunamadı.');
    }
} catch (PDOException $e) {
    error_log("Video akış hatası: " . $e->getMessage());
    http_response_code(500);
    exit('Sunucu hatası.');
}

$file_size = filesize($video_path);
$mime_type = mime_content_type($video_path) ?: 'video/mp4';

header('Content-Type: ' . $mime_type);
header('Accept-Ranges: bytes');
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

$range = $_SERVER['HTTP_RANGE'] ?? '';
$start = 0;
$end = $file_size - 1;

if ($range && preg_match('/bytes=(\d*)-(\d*)/', $range, $matches)) {
    $range_start = $matches[1];
    $range_end = $matches[2];

    if ($range_start !== '') {
        $start = (int)$range_start;
    }
    if ($range_end !== '') {
        $end = (int)$range_end;
    }

    if ($start > $end || $start >= $file_size) {
        header("Content-Range: bytes */{$file_size}");
        http_response_code(416);
        exit;
    }

    http_response_code(206);
    header("Content-Range: bytes {$start}-{$end}/{$file_size}");
}

$length = $end - $start + 1;
header('Content-Length: ' . $length);

if (ob_get_level()) {
    ob_end_clean();
}

$chunk_size = 8192;
$handle = fopen($video_path, 'rb');
if ($handle === false) {
    http_response_code(500);
    exit('Video akışı başlatılamadı.');
}

try {
    fseek($handle, $start);
    $bytes_left = $length;
    while ($bytes_left > 0 && !feof($handle)) {
        $read_length = ($bytes_left > $chunk_size) ? $chunk_size : $bytes_left;
        $buffer = fread($handle, $read_length);
        if ($buffer === false) {
            break;
        }
        echo $buffer;
        flush();
        $bytes_left -= strlen($buffer);
        if (connection_aborted()) {
            break;
        }
    }
} finally {
    fclose($handle);
}
exit;