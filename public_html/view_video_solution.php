<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$deneme_id = filter_input(INPUT_GET, 'deneme_id', FILTER_VALIDATE_INT);
if (!$deneme_id) {
    http_response_code(400);
    die('Geçersiz deneme ID.');
}

$user_id = $_SESSION['user_id'];
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
$session_id = session_id();
$watch_timestamp = time();

// Video izleme logla
try {
    $stmt_log = $pdo->prepare("
        INSERT INTO video_izleme_loglari 
        (kullanici_id, deneme_id, ip_adresi, session_id, izleme_zamani) 
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt_log->execute([$user_id, $deneme_id, $ip_address, $session_id]);
    
    // Şüpheli aktivite kontrolü
    $stmt_check = $pdo->prepare("
        SELECT COUNT(*) as izleme_sayisi 
        FROM video_izleme_loglari 
        WHERE kullanici_id = ? 
        AND deneme_id = ? 
        AND izleme_zamani > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ");
    $stmt_check->execute([$user_id, $deneme_id]);
    $check_result = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    if ($check_result['izleme_sayisi'] > 10) {
        error_log("ŞÜPHELI AKTİVİTE: Kullanıcı $user_id, Video $deneme_id - 5 dakikada {$check_result['izleme_sayisi']} erişim");
    }
} catch (PDOException $e) {
    error_log("Video log hatası: " . $e->getMessage());
}

try {
    $stmt_user = $pdo->prepare("SELECT ad_soyad FROM kullanicilar WHERE id = ?");
    $stmt_user->execute([$user_id]);
    $user_ad_soyad = $stmt_user->fetchColumn() ?: 'Kullanıcı';

    $stmt_access = $pdo->prepare("
        SELECT d.deneme_adi, d.tur, d.cozum_video_dosyasi
        FROM kullanici_erisimleri ke
        JOIN denemeler d ON ke.deneme_id = d.id
        WHERE ke.kullanici_id = :user_id AND ke.deneme_id = :deneme_id
    ");
    $stmt_access->execute([':user_id' => $user_id, ':deneme_id' => $deneme_id]);
    $video_data = $stmt_access->fetch(PDO::FETCH_ASSOC);

    if (!$video_data) {
        die('Bu video içeriğine erişim yetkiniz yok.');
    }

    if (empty($video_data['cozum_video_dosyasi'])) {
        die('Bu yayına ait video çözüm bulunamadı.');
    }

    if ($video_data['tur'] === 'deneme') {
        $stmt_check_exam = $pdo->prepare("
            SELECT id FROM kullanici_katilimlari
            WHERE kullanici_id = ? AND deneme_id = ? AND sinav_tamamlama_tarihi IS NOT NULL
        ");
        $stmt_check_exam->execute([$user_id, $deneme_id]);
        if (!$stmt_check_exam->fetch()) {
            die('Deneme video çözümlerini görmek için önce sınavı tamamlamalısınız.');
        }
    }

    $video_filename = trim($video_data['cozum_video_dosyasi']);

    $resolveVideoPath = function (string $fileName): ?string {
        $fileName = trim($fileName);
        if (empty($fileName)) return null;
        $fileName = basename($fileName);
        if (strpos($fileName, '..') !== false || strpos($fileName, '/') !== false) return null;
        
        $baseDirs = [
            dirname(__DIR__) . '/uploads/videos',
            dirname(__DIR__) . '/uploads/products'
        ];

        foreach ($baseDirs as $baseDir) {
            $fullPath = $baseDir . DIRECTORY_SEPARATOR . $fileName;
            if (is_file($fullPath)) {
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
    if (!$video_path) {
        die('Video dosyası sunucuda bulunamadı.');
    }

    $expires = time() + 300;
    $payload = $user_id . '|' . $deneme_id . '|' . $expires . '|' . $session_id . '|' . $ip_address;
    $token = hash_hmac('sha256', $payload, VIDEO_STREAM_SECRET);
    $stream_url = "video_stream.php?" . http_build_query([
        'deneme_id' => $deneme_id,
        'expires' => $expires,
        'token' => $token
    ]);

} catch (PDOException $e) {
    error_log("Video çözüm erişim hatası: " . $e->getMessage());
    die('Bir hata oluştu. Lütfen tekrar deneyin.');
}

$page_title = $video_data['deneme_adi'] . " - Video Çözüm";
include_once __DIR__ . '/templates/header.php';
?>

<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    :root {
        --primary: #4f46e5;
        --primary-hover: #4338ca;
        --bg-body: #f8fafc;
        --glass-bg: rgba(255, 255, 255, 0.85);
        --text-main: #1e293b;
        --text-muted: #64748b;
    }

    body {
        font-family: 'Inter', sans-serif;
        background: radial-gradient(circle at top right, #eef2ff 0%, #f8fafc 100%);
        color: var(--text-main);
        min-height: 100vh;
        margin: 0;
    }

    .lms-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 2rem 1rem;
    }

    /* Üst Başlık ve Navigasyon */
    .lms-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 2rem;
        animation: fadeIn 0.8s ease-out;
    }

    .back-link {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: var(--text-muted);
        text-decoration: none;
        font-weight: 500;
        transition: color 0.3s;
    }

    .back-link:hover {
        color: var(--primary);
    }

    .course-title-section h1 {
        font-size: 1.75rem;
        font-weight: 800;
        letter-spacing: -0.025em;
        margin: 0;
        color: #0f172a;
    }

    .course-badge {
        background: #e0e7ff;
        color: var(--primary);
        padding: 0.25rem 0.75rem;
        border-radius: 99px;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        display: inline-block;
        margin-top: 0.5rem;
    }

    /* Ana Düzen */
    .video-grid {
        display: grid;
        grid-template-columns: 1fr 350px;
        gap: 2rem;
    }

    @media (max-width: 1100px) {
        .video-grid { grid-template-columns: 1fr; }
    }

    /* Video Alanı */
    .video-main-wrapper {
        background: var(--glass-bg);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.5);
        border-radius: 24px;
        overflow: hidden;
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.05);
    }

    .video-viewport {
        position: relative;
        background: #000;
        aspect-ratio: 16/9;
        box-shadow: inset 0 0 100px rgba(0,0,0,0.5);
    }

    video {
        width: 100%;
        height: 100%;
        display: block;
    }

    /* Watermark */
    .watermark-overlay {
        position: absolute;
        inset: 0;
        pointer-events: none;
        z-index: 10;
        overflow: hidden;
    }

    .watermark-text {
        position: absolute;
        color: rgba(255, 255, 255, 0.15);
        font-size: 12px;
        font-weight: 600;
        white-space: nowrap;
        user-select: none;
        transition: all 4s linear;
    }

    /* Güvenlik Bannerı */
    .security-alert {
        padding: 1rem 1.5rem;
        background: #fffbeb;
        border-bottom: 1px solid #fde68a;
        display: flex;
        align-items: center;
        gap: 1rem;
        font-size: 0.9rem;
        color: #92400e;
    }

    /* Yan Panel / Bilgi Alanı */
    .sidebar-panel {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .info-card {
        background: white;
        border-radius: 20px;
        padding: 1.5rem;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
        border: 1px solid #f1f5f9;
    }

    .info-card h3 {
        font-size: 1rem;
        font-weight: 700;
        margin-top: 0;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .meta-item {
        display: flex;
        justify-content: space-between;
        padding: 0.75rem 0;
        border-bottom: 1px solid #f1f5f9;
        font-size: 0.9rem;
    }

    .meta-item:last-child { border: none; }
    .meta-label { color: var(--text-muted); }
    .meta-value { font-weight: 600; color: var(--text-main); }

    /* Notlar Alanı */
    .notes-area textarea {
        width: 100%;
        min-height: 180px;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1rem;
        font-family: inherit;
        font-size: 0.95rem;
        resize: none;
        transition: all 0.3s;
        background: #f8fafc;
    }

    .notes-area textarea:focus {
        outline: none;
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
    }

    .btn-save-note {
        width: 100%;
        background: var(--primary);
        color: white;
        border: none;
        padding: 0.85rem;
        border-radius: 12px;
        font-weight: 600;
        cursor: pointer;
        margin-top: 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        transition: all 0.3s;
    }

    .btn-save-note:hover {
        background: var(--primary-hover);
        transform: translateY(-1px);
    }

    /* Animasyonlar */
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Toast Mesajı (Custom Alert yerine) */
    #customToast {
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        background: #0f172a;
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        display: none;
        z-index: 9999;
        animation: slideUp 0.3s ease;
    }

    @keyframes slideUp {
        from { transform: translateY(100%); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
</style>

<div class="lms-container">
    <!-- Header -->
    <header class="lms-header">
        <div class="course-title-section">
            <a href="dashboard.php" class="back-link">
                <i class="fa-solid fa-arrow-left"></i> Panelime Dön
            </a>
            <h1><?php echo escape_html($video_data['deneme_adi']); ?></h1>
            <span class="course-badge"><?php echo ($video_data['tur'] == 'deneme') ? 'Sınav Analizi' : 'Video Çözüm'; ?></span>
        </div>
        <div class="user-info d-none d-md-block">
            <div class="d-flex align-items-center gap-3">
                <div class="text-end">
                    <div class="fw-bold"><?php echo escape_html($user_ad_soyad); ?></div>
                    <small class="text-muted">Öğrenci Profili</small>
                </div>
                <div style="width: 45px; height: 45px; background: #ddd; border-radius: 50%; display: flex; align-items:center; justify-content:center;">
                    <i class="fa-solid fa-user text-white"></i>
                </div>
            </div>
        </div>
    </header>

    <div class="video-grid">
        <!-- Sol: Video Oynatıcı -->
        <div class="video-content">
            <div class="video-main-wrapper">
                <div class="security-alert">
                    <i class="fa-solid fa-shield-halved"></i>
                    <span>Bu içerik <strong><?php echo escape_html($user_ad_soyad); ?></strong> adına lisanslanmıştır. Paylaşılması yasal suçtur.</span>
                </div>
                <div class="video-viewport">
                    <video
                        id="player"
                        controls
                        controlslist="nodownload"
                        oncontextmenu="return false;"
                        disablepictureinpicture
                    >
                        <source src="<?php echo escape_html($stream_url); ?>" type="video/mp4">
                    </video>
                    <!-- Watermark Layer -->
                    <div class="watermark-overlay" id="wmContainer"></div>
                </div>
            </div>
            
            <div class="mt-4 p-3 bg-white rounded-4 shadow-sm border border-light">
                <h4 class="h6 fw-bold mb-2">Video Hakkında</h4>
                <p class="text-muted small m-0">
                    Video çözümünü izlerken anlamadığınız kısımları sağ taraftaki not defterine kaydedebilirsiniz. 
                    Notlarınız sadece sizin tarafınızdan görülebilir ve tarayıcınızda saklanır.
                </p>
            </div>
        </div>

        <!-- Sağ: Sidebar Bilgileri -->
        <aside class="sidebar-panel">
            <div class="info-card">
                <h3><i class="fa-solid fa-circle-info text-primary"></i> Oturum Bilgileri</h3>
                <div class="meta-item">
                    <span class="meta-label">ID No:</span>
                    <span class="meta-value">#<?php echo (int)$user_id; ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Tarih:</span>
                    <span class="meta-value"><?php echo date('d.m.Y'); ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Erişim Süresi:</span>
                    <span class="meta-value">5 Dakika (Limitli)</span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">IP Adresi:</span>
                    <span class="meta-value"><?php echo escape_html($ip_address); ?></span>
                </div>
            </div>

            <div class="info-card notes-area">
                <h3><i class="fa-solid fa-pen-fancy text-primary"></i> Ders Notlarım</h3>
                <textarea id="videoNotes" placeholder="Buraya aldığınız notlar otomatik olarak bu video için saklanır..."></textarea>
                <button class="btn-save-note" onclick="saveNotes()">
                    <i class="fa-solid fa-floppy-disk"></i> Notu Kaydet
                </button>
            </div>
        </aside>
    </div>
</div>

<div id="customToast">✅ Notlarınız başarıyla kaydedildi!</div>

<script>
    // Dinamik Su Yolu Sistemi (Watermark)
    const wmContainer = document.getElementById('wmContainer');
    const userTag = "<?php echo addslashes($user_ad_soyad); ?> (ID: <?php echo $user_id; ?>)";
    
    function createWatermark() {
        const wm = document.createElement('div');
        wm.className = 'watermark-text';
        wm.innerText = userTag;
        wmContainer.appendChild(wm);
        
        moveWatermark(wm);
        
        // Her 8 saniyede bir yeni pozisyona taşı
        setInterval(() => moveWatermark(wm), 8000);
    }

    function moveWatermark(el) {
        const maxX = wmContainer.clientWidth - el.clientWidth - 20;
        const maxY = wmContainer.clientHeight - el.clientHeight - 20;
        
        const randomX = Math.max(20, Math.floor(Math.random() * maxX));
        const randomY = Math.max(20, Math.floor(Math.random() * maxY));
        
        el.style.left = randomX + 'px';
        el.style.top = randomY + 'px';
        el.style.opacity = (Math.random() * 0.15 + 0.1).toString();
    }

    // Başlangıçta 3 adet watermark oluştur
    for(let i=0; i<3; i++) {
        setTimeout(createWatermark, i * 2000);
    }

    // DevTools Tespiti
    let warningCount = 0;
    const detectDev = () => {
        const threshold = 160;
        if (window.outerWidth - window.innerWidth > threshold || window.outerHeight - window.innerHeight > threshold) {
            warningCount++;
            if(warningCount === 1) {
                console.warn("Lütfen geliştirici araçlarını kapatın. Güvenlik protokolü gereği bu işlem loglanmaktadır.");
                fetch('log_suspicious_activity.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ deneme_id: <?php echo $deneme_id; ?>, activity: 'devtools_opened' })
                });
            }
        }
    };
    setInterval(detectDev, 2000);

    // Not Kaydetme Fonksiyonu
    function saveNotes() {
        const notes = document.getElementById('videoNotes').value;
        localStorage.setItem('notes_v_<?php echo $deneme_id; ?>_<?php echo $user_id; ?>', notes);
        
        const toast = document.getElementById('customToast');
        toast.style.display = 'block';
        setTimeout(() => { toast.style.display = 'none'; }, 3000);
    }

    // Sayfa Yüklenince Notları Getir
    window.addEventListener('load', () => {
        const saved = localStorage.getItem('notes_v_<?php echo $deneme_id; ?>_<?php echo $user_id; ?>');
        if (saved) document.getElementById('videoNotes').value = saved;
    });

    // Video Güvenlik
    const video = document.getElementById('player');
    video.addEventListener('play', () => {
        // Tam ekran kontrolü veya başka güvenlik adımları buraya eklenebilir
    });
</script>

<?php include_once __DIR__ . '/templates/footer.php'; ?>