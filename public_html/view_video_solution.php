<?php
// view_video_solution.php
// Video çözümlerini kullanıcı yetkisine göre güvenli şekilde izletir ve dinamik filigran ekler.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$katilim_id = isset($_GET['katilim_id']) && is_numeric($_GET['katilim_id']) ? (int)$_GET['katilim_id'] : 0;
$deneme_id = isset($_GET['deneme_id']) && is_numeric($_GET['deneme_id']) ? (int)$_GET['deneme_id'] : 0;
$user_id = $_SESSION['user_id'];

if (!$katilim_id && !$deneme_id) {
    http_response_code(400);
    die('Hata: Geçersiz parametreler.');
}

try {
    $video_data = null;
    $user_ad_soyad_db = 'Kullanici';

    $stmt_user_name = $pdo->prepare("SELECT ad_soyad FROM kullanicilar WHERE id = ?");
    $stmt_user_name->execute([$user_id]);
    $user_ad_soyad_db = $stmt_user_name->fetchColumn() ?? 'Kullanici';

    if ($katilim_id > 0) {
        $stmt = $pdo->prepare("
            SELECT 
                kk.deneme_id, kk.erisim_kodu_id, kk.sinav_tamamlama_tarihi,
                d.deneme_adi, d.tur, d.cozum_video_dosyasi, d.aktif_mi
            FROM kullanici_katilimlari kk
            JOIN denemeler d ON kk.deneme_id = d.id
            WHERE kk.id = :id AND kk.kullanici_id = :uid
        ");
        $stmt->execute([':id' => $katilim_id, ':uid' => $user_id]);
        $video_data = $stmt->fetch(PDO::FETCH_ASSOC);
    } elseif ($deneme_id > 0) {
        $stmt = $pdo->prepare("
            SELECT 
                ke.deneme_id, ke.erisim_kodu_id, NULL as sinav_tamamlama_tarihi,
                d.deneme_adi, d.tur, d.cozum_video_dosyasi, d.aktif_mi
            FROM kullanici_erisimleri ke
            JOIN denemeler d ON ke.deneme_id = d.id
            WHERE ke.deneme_id = :id AND ke.kullanici_id = :uid
        ");
        $stmt->execute([':id' => $deneme_id, ':uid' => $user_id]);
        $video_data = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$video_data) {
        die('Hata: Bu video çözümüne erişim yetkiniz yok veya kayıt bulunamadı.');
    }

    if (empty($video_data['cozum_video_dosyasi'])) {
        die('Hata: Video çözümü henüz yüklenmemiş.');
    }

    if (isset($video_data['tur']) && $video_data['tur'] === 'deneme') {
        if ($katilim_id <= 0 || empty($video_data['sinav_tamamlama_tarihi'])) {
            $stmt_check = $pdo->prepare("SELECT id FROM kullanici_katilimlari WHERE kullanici_id = ? AND deneme_id = ? AND sinav_tamamlama_tarihi IS NOT NULL");
            $stmt_check->execute([$user_id, $video_data['deneme_id']]);
            if (!$stmt_check->fetch()) {
                die('Deneme sınavı video çözümlerini görmek için önce sınavı tamamlamalısınız.');
            }
        }
    }

    $token = generate_video_stream_token($video_data['deneme_id'], $katilim_id ?: null);
    $video_url = BASE_URL . '/video_stream.php?token=' . urlencode($token) . '&deneme_id=' . (int)$video_data['deneme_id'];
    if ($katilim_id) {
        $video_url .= '&katilim_id=' . (int)$katilim_id;
    }

    $video_ext = strtolower(pathinfo($video_data['cozum_video_dosyasi'], PATHINFO_EXTENSION));
    $video_mime = $video_ext === 'webm' ? 'video/webm' : 'video/mp4';
} catch (PDOException $e) {
    error_log("Video çözüm erişim hatası: " . $e->getMessage());
    die('Bir hata oluştu.');
}

$page_title = ($video_data['deneme_adi'] ?? 'Video Çözümü') . ' - Video Çözüm';
include_once __DIR__ . '/templates/header.php';

$watermark_text = $user_ad_soyad_db . ' – Kullanıcı ID: ' . $user_id;
?>

<style>
    .video-solution-wrapper {
        background: #0f172a;
        border-radius: 20px;
        padding: 24px;
        box-shadow: 0 18px 50px rgba(15, 23, 42, 0.3);
        position: relative;
        overflow: hidden;
    }
    .video-shell {
        position: relative;
        border-radius: 16px;
        overflow: hidden;
        background: #000;
    }
    .video-shell video {
        width: 100%;
        height: auto;
        display: block;
        outline: none;
    }
    .video-watermark {
        position: absolute;
        top: 10%;
        left: 10%;
        color: rgba(255, 255, 255, 0.32);
        font-size: 0.9rem;
        font-weight: 700;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        pointer-events: none;
        user-select: none;
        text-shadow: 0 2px 8px rgba(0,0,0,0.5);
        transition: transform 1.5s ease-in-out;
        white-space: nowrap;
    }
    .video-watermark.secondary {
        font-size: 0.75rem;
        opacity: 0.2;
    }
    .video-info {
        color: rgba(148, 163, 184, 0.9);
    }
</style>

<div class="container py-4 py-md-5">
    <div class="mb-4">
        <h2 class="fw-bold text-white mb-1"><?php echo escape_html($video_data['deneme_adi'] ?? 'Video Çözümü'); ?></h2>
        <p class="video-info mb-0">Satın aldığınız materyalin video çözümünü güvenli şekilde izliyorsunuz.</p>
    </div>

    <div class="video-solution-wrapper">
        <div class="video-shell" id="videoShell">
            <video id="solutionVideo" controls controlslist="nodownload noplaybackrate" disablepictureinpicture>
                <source src="<?php echo escape_html($video_url); ?>" type="<?php echo escape_html($video_mime); ?>">
            </video>
            <div class="video-watermark" id="watermarkPrimary"><?php echo escape_html($watermark_text); ?></div>
            <div class="video-watermark secondary" id="watermarkSecondary"><?php echo escape_html($watermark_text); ?></div>
        </div>
        <div class="mt-3 small video-info">
            <i class="fas fa-shield-alt me-2 text-warning"></i>
            Video içerikleri kişiye özel filigranla korunur. Paylaşımlar tespit edildiğinde erişim iptali uygulanır.
        </div>
    </div>
</div>

<script>
const watermarkPrimary = document.getElementById('watermarkPrimary');
const watermarkSecondary = document.getElementById('watermarkSecondary');
const videoShell = document.getElementById('videoShell');
const solutionVideo = document.getElementById('solutionVideo');

function moveWatermark(element, offsetFactor = 0) {
    if (!videoShell || !element) return;
    const padding = 30;
    const maxX = Math.max(videoShell.clientWidth - element.offsetWidth - padding, padding);
    const maxY = Math.max(videoShell.clientHeight - element.offsetHeight - padding, padding);
    const randomX = Math.floor(Math.random() * maxX) + padding;
    const randomY = Math.floor(Math.random() * maxY) + padding;
    element.style.transform = `translate(${randomX}px, ${randomY}px) rotate(${offsetFactor}deg)`;
}

function cycleWatermarks() {
    moveWatermark(watermarkPrimary, 4);
    moveWatermark(watermarkSecondary, -4);
}

solutionVideo?.addEventListener('contextmenu', (event) => {
    event.preventDefault();
});

window.addEventListener('resize', cycleWatermarks);
setInterval(cycleWatermarks, 4500);
cycleWatermarks();
</script>

<?php include_once __DIR__ . '/templates/footer.php'; ?>
