<?php
// verify_pdf.php
// DenemeAGS - Gelişmiş PDF Doğrulama ve Adli Analiz Modülü
// UYUMLULUK: download_secure_pdf_v2.php ile oluşturulan imzaları doğrular.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = 'PDF Doğrulama ve Analiz';
$verification = null;
$error = null;
$warning = null;
$info_messages = [];
$csrf_token = generate_csrf_token();

// Yükleme limiti ayarları (Gerekirse)
ini_set('upload_max_filesize', '20M');
ini_set('post_max_size', '20M');

function verify_get_client_ip(): string
{
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return (string) $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim((string) ($parts[0] ?? ''));
    }
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function verify_rate_limit_exceeded(string $ip, int $maxAttempts = 15, int $windowSeconds = 900): bool
{
    $file = TMP_DIR . '/verify_pdf_rate_limit.json';
    if (!is_dir(TMP_DIR)) {
        @mkdir(TMP_DIR, 0775, true);
    }

    $fp = @fopen($file, 'c+');
    if (!$fp) {
        return false;
    }

    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        return false;
    }

    $now = time();
    $raw = stream_get_contents($fp);
    $data = json_decode($raw ?: '{}', true);
    if (!is_array($data)) {
        $data = [];
    }

    foreach ($data as $storedIp => $timestamps) {
        if (!is_array($timestamps)) {
            unset($data[$storedIp]);
            continue;
        }
        $data[$storedIp] = array_values(array_filter($timestamps, function ($t) use ($now, $windowSeconds) {
            return is_int($t) && ($now - $t) <= $windowSeconds;
        }));
        if (empty($data[$storedIp])) {
            unset($data[$storedIp]);
        }
    }

    $attempts = $data[$ip] ?? [];
    if (count($attempts) >= $maxAttempts) {
        flock($fp, LOCK_UN);
        fclose($fp);
        return true;
    }

    $attempts[] = $now;
    $data[$ip] = $attempts;

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    return false;
}

function decode_urlsafe_b64(string $value): ?string
{
    $normalized = strtr($value, '-_', '+/');
    $mod = strlen($normalized) % 4;
    if ($mod > 0) {
        $normalized .= str_repeat('=', 4 - $mod);
    }

    $decoded = base64_decode($normalized, true);
    return $decoded === false ? null : $decoded;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token((string) $_POST['csrf_token'])) {
        $error = 'Güvenlik doğrulaması başarısız (CSRF). Lütfen tekrar deneyin.';
    } else {
        $clientIp = verify_get_client_ip();
        if (verify_rate_limit_exceeded($clientIp)) {
            $error = 'Çok fazla doğrulama denemesi yapıldı. Lütfen 15 dakika sonra tekrar deneyin.';
        }
    }

    if ($error === null && (!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK)) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'Dosya boyutu sunucu limitini aşıyor.',
            UPLOAD_ERR_FORM_SIZE => 'Dosya boyutu form limitini aşıyor.',
            UPLOAD_ERR_PARTIAL => 'Dosya tam yüklenemedi.',
            UPLOAD_ERR_NO_FILE => 'Dosya seçilmedi.',
        ];
        $error = $uploadErrors[$_FILES['pdf_file']['error']] ?? 'Bilinmeyen bir yükleme hatası oluştu.';
    } elseif ($error === null) {
        $tmpPath = $_FILES['pdf_file']['tmp_name'];
        $fileSize = (int) ($_FILES['pdf_file']['size'] ?? 0);
        $maxSize = 20 * 1024 * 1024;
        if ($fileSize <= 0 || $fileSize > $maxSize) {
            $error = 'PDF boyutu geçersiz. Maksimum 20MB olmalıdır.';
        }
        
        // Mime Type Kontrolü
        if ($error === null) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $tmpPath);
            finfo_close($finfo);

            if ($mimeType !== 'application/pdf' && $mimeType !== 'application/octet-stream') {
                $error = 'Yüklenen dosya geçerli bir PDF değil. (Algılanan tür: ' . htmlspecialchars((string) $mimeType) . ')';
            }
        }

        if ($error === null) {
            $fh = fopen($tmpPath, 'rb');
            $header = $fh ? fread($fh, 5) : '';
            if ($fh) {
                fclose($fh);
            }
            if ($header !== '%PDF-') {
                $error = 'Dosya PDF imzası taşımıyor (%PDF-).';
            }
        }

        if ($error === null) {
            // Dosyayı belleğe oku
            $pdfContents = file_get_contents($tmpPath);
            
            if ($pdfContents === false) {
                $error = 'PDF dosya içeriği okunamadı.';
            } else {
                // --- STRATEJİ 1: DERİN İMZA ARAMA ---
                // download_secure_pdf_v2.php imzayı şu formatta atar: DENEMEAGS_SIG:{Base64}.{Hash}
                // Regex, dosyanın herhangi bir yerinde (Metadata, EOF, HTML comment) bu deseni arar.
                
                $pattern = '/DENEMEAGS_SIG:([A-Za-z0-9\-\_]+)\.([a-f0-9]{64})/';
                
                if (!preg_match($pattern, $pdfContents, $matches)) {
                    $error = 'Bu PDF dosyası içinde geçerli bir DenemeAGS dijital imzası bulunamadı. Dosya sistem dışı oluşturulmuş veya imzalar tamamen temizlenmiş.';
                } else {
                    $payloadB64 = $matches[1]; // Base64 JSON verisi
                    $signature = $matches[2];  // HMAC SHA256 İmzası
                    
                    // --- STRATEJİ 2: KRİPTOGRAFİK DOĞRULAMA ---
                    // İmza gerçekten bizim "PDF_SIGNATURE_SECRET" anahtarımızla mı oluşturulmuş?
                    
                    $expectedSignature = hash_hmac('sha256', $payloadB64, PDF_SIGNATURE_SECRET);
                    
                    if (!hash_equals($expectedSignature, $signature)) {
                        $error = 'KRİTİK GÜVENLİK UYARISI: Dosya içinde bir imza bulundu ancak imza GEÇERSİZ! Biri sahte imza üretmeye çalışmış olabilir.';
                    } else {
                        // İmza geçerli, içeriği çöz
                        $jsonStr = decode_urlsafe_b64($payloadB64);
                        $payload = json_decode($jsonStr, true);

                        if (!is_array($payload) || empty($payload['document_id']) || empty($payload['user_id']) || empty($payload['deneme_id'])) {
                            $error = 'İmza verisi bozuk veya okunamıyor.';
                        } else {
                            // --- STRATEJİ 3: VERİTABANI ÇAPRAZ KONTROLÜ ---
                            
                            // 1. Belge Sahibini Bul
                            $stmtUser = $pdo->prepare('SELECT ad_soyad, email, telefon FROM kullanicilar WHERE id = ?');
                            $stmtUser->execute([$payload['user_id']]);
                            $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

                            // 2. Deneme Bilgisini Bul
                            $stmtDeneme = $pdo->prepare('SELECT deneme_adi FROM denemeler WHERE id = ?');
                            $stmtDeneme->execute([$payload['deneme_id'] ?? 0]);
                            $deneme = $stmtDeneme->fetch(PDO::FETCH_ASSOC);

                            // 3. Log Kaydını Bul (Orijinal Hash burada saklı)
                            $stmtLog = $pdo->prepare("SELECT * FROM pdf_logs WHERE document_id = ? LIMIT 1");
                            $stmtLog->execute([$payload['document_id']]);
                            $logData = $stmtLog->fetch(PDO::FETCH_ASSOC);
                            
                            // --- STRATEJİ 4: BÜTÜNLÜK (HASH) KONTROLÜ ---
                            // Yüklenen dosyanın şimdiki hash'i
                            $currentPdfHash = hash('sha256', $pdfContents);
                            $isOriginal = false;
                            $logMatchesPayload = false;
                            $payloadHashMatch = true;

                            if (isset($payload['file_hash']) && is_string($payload['file_hash']) && preg_match('/^[a-f0-9]{64}$/', $payload['file_hash'])) {
                                $payloadHashMatch = hash_equals($payload['file_hash'], $currentPdfHash);
                            }

                            // Veritabanındaki orijinal hash ile karşılaştır
                            if ($logData && hash_equals($logData['file_hash'], $currentPdfHash)) {
                                $isOriginal = true;
                            }

                            if ($logData) {
                                $logUserOk = !isset($logData['user_id']) || (int) $logData['user_id'] === (int) $payload['user_id'];
                                $logDenemeOk = !isset($logData['deneme_id']) || (int) $logData['deneme_id'] === (int) $payload['deneme_id'];
                                $logMatchesPayload = $logUserOk && $logDenemeOk;
                            }

                            // Doğrulama Sonuçlarını Diziye At
                            $verification = [
                                'user' => $user,
                                'deneme' => $deneme,
                                'log_data' => $logData,
                                'is_original' => $isOriginal,
                                'payload_hash_match' => $payloadHashMatch,
                                'log_matches_payload' => $logMatchesPayload,
                                'document_id' => $payload['document_id'],
                                'issued_at' => $payload['issued_at'] ?? null, // Oluşturulma tarihi
                                'type' => $payload['type'] ?? 'Bilinmiyor'
                            ];

                            // --- SONUÇ MESAJLARINI OLUŞTUR ---
                            if (!$payloadHashMatch) {
                                $error = 'KRİTİK UYARI: İmza içindeki file_hash ile dosya hash değeri uyuşmuyor.';
                            } elseif ($isOriginal && $logMatchesPayload) {
                                $info_messages[] = ['type' => 'success', 'msg' => '<strong>MÜKEMMEL:</strong> Dosya %100 orijinal. Byte düzeyinde hiçbir değişiklik yapılmamış.'];
                            } else {
                                $warning = 'DİKKAT: Dosya Bütünlüğü Bozulmuş!';
                                $info_messages[] = ['type' => 'danger', 'msg' => 'Dosyanın HASH değeri orijinal kayıtla uyuşmuyor.'];
                                $info_messages[] = ['type' => 'warning', 'msg' => 'Bu dosya indirilip üzerinde oynanmış (filigran silinmiş, not alınmış veya "Save As" yapılmış).'];
                                
                                if ($user) {
                                    $info_messages[] = ['type' => 'info', 'msg' => 'ANCAK: <strong>Metadata veya Gizli İmzalar</strong> sayesinde dosya sahibinin kimliği tespit edildi.'];
                                }
                                if (!$logMatchesPayload) {
                                    $info_messages[] = ['type' => 'danger', 'msg' => 'Log kaydı ile imza payload bilgileri tam eşleşmiyor. Sahte ilişkilendirme denemesi olabilir.'];
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

include_once __DIR__ . '/templates/header.php';
?>

<style>
    .upload-area {
        transition: all 0.3s ease;
        border: 2px dashed #0d6efd;
        background-color: #f8f9fa;
        position: relative;
        overflow: hidden;
    }
    .upload-area:hover {
        background-color: #e9ecef;
        border-color: #0a58ca;
    }
    .upload-area.dragging {
        background-color: #e7f1ff;
        border-color: #0d6efd;
        transform: scale(1.01);
        box-shadow: 0 0 15px rgba(13, 110, 253, 0.2);
    }
    .animate-pulse {
        animation: pulse 1.5s infinite;
    }
    @keyframes pulse {
        0% { opacity: 0.6; }
        50% { opacity: 1; }
        100% { opacity: 0.6; }
    }
    .status-icon {
        font-size: 4rem;
        margin-bottom: 1rem;
    }
</style>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-lg border-0 rounded-3">
                <div class="card-header bg-primary text-white p-4 text-center rounded-top-3">
                    <h2 class="h4 fw-bold mb-0"><i class="fas fa-search-location me-2"></i> Belge Doğrulama ve Adli Analiz</h2>
                </div>
                <div class="card-body p-5">
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger shadow-sm border-start border-5 border-danger">
                            <h5 class="alert-heading"><i class="fas fa-exclamation-circle me-2"></i>Hata Oluştu</h5>
                            <p class="mb-0"><?php echo htmlspecialchars($error); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($verification): ?>
                        <!-- SONUÇ PANELİ -->
                        <div class="text-center mb-5">
                            <?php if ($verification['is_original']): ?>
                                <div class="text-success status-icon"><i class="fas fa-check-circle"></i></div>
                                <h3 class="text-success fw-bold">Doğrulama Başarılı</h3>
                                <p class="text-muted">Bu belge sistemimizden indirildiği haliyle korunmaktadır.</p>
                            <?php else: ?>
                                <div class="text-warning status-icon"><i class="fas fa-user-secret"></i></div>
                                <h3 class="text-warning fw-bold">Sahibi Tespit Edildi (Dosya Değiştirilmiş)</h3>
                                <p class="text-muted">Dosya bütünlüğü bozulmuş olsa da, dijital parmak izleri sayesinde kaynak bulundu.</p>
                            <?php endif; ?>
                        </div>

                        <!-- KULLANICI KARTI -->
                        <div class="row g-4 mb-4">
                            <div class="col-md-6">
                                <div class="card h-100 bg-light border-0 shadow-sm">
                                    <div class="card-body text-center">
                                        <div class="mb-3 text-primary">
                                            <i class="fas fa-user-circle fa-3x"></i>
                                        </div>
                                        <h5 class="card-title fw-bold">Belge Sahibi</h5>
                                        <p class="card-text fs-5 mb-1"><?php echo htmlspecialchars($verification['user']['ad_soyad'] ?? 'Bilinmiyor'); ?></p>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($verification['user']['email'] ?? '-'); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card h-100 bg-light border-0 shadow-sm">
                                    <div class="card-body text-center">
                                        <div class="mb-3 text-info">
                                            <i class="fas fa-file-pdf fa-3x"></i>
                                        </div>
                                        <h5 class="card-title fw-bold">İçerik Bilgisi</h5>
                                        <p class="card-text fs-5 mb-1"><?php echo htmlspecialchars($verification['deneme']['deneme_adi'] ?? 'Bilinmiyor'); ?></p>
                                        <small class="text-muted d-block">Tür: <?php echo ($verification['type'] == 'question') ? 'Soru Kitapçığı' : 'Çözüm Kitapçığı'; ?></small>
                                        <small class="text-muted">ID: <?php echo htmlspecialchars($verification['document_id']); ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TEKNİK DETAYLAR -->
                        <div class="card border-0 bg-white shadow-sm">
                            <div class="card-header bg-dark text-white fw-bold">
                                <i class="fas fa-microchip me-2"></i> Teknik Analiz Raporu
                            </div>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($info_messages as $msg): ?>
                                    <li class="list-group-item d-flex align-items-start list-group-item-<?php echo $msg['type']; ?>">
                                        <i class="fas fa-info-circle me-3 mt-1"></i>
                                        <div><?php echo $msg['msg']; ?></div>
                                    </li>
                                <?php endforeach; ?>
                                
                                <li class="list-group-item d-flex justify-content-between">
                                    <span><strong>Oluşturulma Tarihi:</strong></span>
                                    <span>
                                        <?php
                                            $issuedAt = $verification['issued_at'] ?? null;
                                            echo $issuedAt ? date('d.m.Y H:i', strtotime((string) $issuedAt)) : 'Kayıt Yok';
                                        ?>
                                    </span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span><strong>İndiren IP Adresi:</strong></span>
                                    <span><?php echo $verification['log_data']['ip_address'] ?? 'Kayıt Yok'; ?></span>
                                </li>
                            </ul>
                        </div>
                        
                        <div class="text-center mt-4">
                            <a href="verify_pdf.php" class="btn btn-outline-primary"><i class="fas fa-redo me-2"></i>Yeni Sorgulama Yap</a>
                        </div>

                    <?php else: ?>
                        <!-- UPLOAD FORMU (Sadece sonuç yoksa göster) -->
                        <div class="alert alert-info border-info shadow-sm mb-4">
                            <i class="fas fa-info-circle me-2"></i>
                            Bu araç, DenemeAGS üzerinden indirilen "Soru" veya "Çözüm" PDF'lerinin kaynağını tespit etmek için kullanılır.
                            Dosya adı değiştirilmiş veya filigranı silinmiş olsa bile analiz edebilir.
                        </div>

                        <form method="post" enctype="multipart/form-data" id="uploadForm">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <input type="file" class="d-none" id="pdf_file" name="pdf_file" accept="application/pdf">
                            
                            <label class="upload-area p-5 rounded-3 text-center w-100 cursor-pointer" id="uploadLabel" for="pdf_file">
                                <div class="mb-3">
                                    <i class="fas fa-cloud-upload-alt fa-4x text-primary opacity-50"></i>
                                </div>
                                <h4 class="fw-bold text-dark">PDF Dosyasını Buraya Bırakın</h4>
                                <p class="text-muted mb-0">veya dosya seçmek için tıklayın</p>
                            </label>

                            <div id="loadingState" class="text-center p-5 d-none">
                                <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status">
                                    <span class="visually-hidden">Yükleniyor...</span>
                                </div>
                                <h5 class="text-primary fw-bold animate-pulse">Analiz Ediliyor...</h5>
                                <p class="text-muted">Kriptografik imzalar çözümleniyor.</p>
                            </div>
                        </form>
                    <?php endif; ?>

                </div>
                <div class="card-footer bg-light text-center py-3 text-muted small">
                    <i class="fas fa-shield-alt me-1"></i> DenemeAGS Güvenlik Protokolü v2.0
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const fileInput = document.getElementById('pdf_file');
        const uploadForm = document.getElementById('uploadForm');
        const uploadLabel = document.getElementById('uploadLabel');
        const loadingState = document.getElementById('loadingState');

        if(fileInput) {
            // Dosya Seçimi
            fileInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    processUpload();
                }
            });

            // Drag & Drop
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                uploadLabel.addEventListener(eventName, preventDefaults, false);
            });

            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }

            ['dragenter', 'dragover'].forEach(eventName => {
                uploadLabel.addEventListener(eventName, () => uploadLabel.classList.add('dragging'), false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                uploadLabel.addEventListener(eventName, () => uploadLabel.classList.remove('dragging'), false);
            });

            uploadLabel.addEventListener('drop', function(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                if (files && files.length > 0) {
                    fileInput.files = files;
                    processUpload();
                }
            }, false);

            function processUpload() {
                uploadLabel.style.display = 'none';
                loadingState.classList.remove('d-none');
                uploadForm.submit();
            }
        }
    });
</script>

<?php include_once __DIR__ . '/templates/footer.php'; ?>
