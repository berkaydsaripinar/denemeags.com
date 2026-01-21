<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = 'PDF Doğrulama';
$verification = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Lütfen geçerli bir PDF dosyası yükleyin.';
    } else {
        $tmpPath = $_FILES['pdf_file']['tmp_name'];
        $mimeType = mime_content_type($tmpPath);
        if ($mimeType !== 'application/pdf') {
            $error = 'Yalnızca PDF dosyaları doğrulanabilir.';
        } else {
            $pdfContents = file_get_contents($tmpPath);
            if ($pdfContents === false) {
                $error = 'PDF dosyası okunamadı.';
            } else {
                $pattern = '/DENEMEAGS_SIG:([A-Za-z0-9_-]+)\.([a-f0-9]{64})/';
                if (!preg_match($pattern, $pdfContents, $matches)) {
                    $error = 'Bu PDF içinde doğrulama imzası bulunamadı.';
                } else {
                    $payloadB64 = $matches[1];
                    $signature = $matches[2];
                    $expectedSignature = hash_hmac('sha256', $payloadB64, PDF_SIGNATURE_SECRET);

                    if (!hash_equals($expectedSignature, $signature)) {
                        $error = 'PDF imzası doğrulanamadı. Dosya tahrif edilmiş olabilir.';
                    } else {
                        $payloadB64Padded = strtr($payloadB64, '-_', '+/');
                        $payloadB64Padded .= str_repeat('=', (4 - strlen($payloadB64Padded) % 4) % 4);
                        $payloadJson = base64_decode($payloadB64Padded, true);
                        $payload = $payloadJson ? json_decode($payloadJson, true) : null;

                        if (!is_array($payload) || empty($payload['user_id'])) {
                            $error = 'PDF doğrulama verisi okunamadı.';
                        } else {
                            $stmtUser = $pdo->prepare('SELECT ad_soyad, email FROM kullanicilar WHERE id = ?');
                            $stmtUser->execute([$payload['user_id']]);
                            $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

                            $stmtDeneme = $pdo->prepare('SELECT deneme_adi FROM denemeler WHERE id = ?');
                            $stmtDeneme->execute([$payload['deneme_id'] ?? null]);
                            $deneme = $stmtDeneme->fetch(PDO::FETCH_ASSOC);

                            $verification = [
                                'document_id' => $payload['document_id'] ?? null,
                                'issued_at' => $payload['issued_at'] ?? null,
                                'type' => $payload['type'] ?? null,
                                'user' => $user,
                                'deneme' => $deneme
                            ];
                        }
                    }
                }
            }
        }
    }
}

include_once __DIR__ . '/templates/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <h1 class="h4 fw-bold mb-3">PDF Doğrulama</h1>
                    <p class="text-muted">
                        Bu sayfa, DenemeAGS tarafından oluşturulan PDF'lerin doğrulama imzasını kontrol eder.
                        Filigran silinse bile belge kime ait olduğu burada doğrulanır.
                    </p>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <?php if ($verification): ?>
                        <div class="alert alert-success">
                            PDF imzası doğrulandı. Aşağıda doğrulama bilgileri yer almaktadır.
                        </div>
                        <div class="border rounded p-3 mb-4">
                            <p class="mb-1"><strong>Belge Kodu:</strong> <?php echo htmlspecialchars($verification['document_id'] ?? 'Bilinmiyor'); ?></p>
                            <p class="mb-1"><strong>Belge Türü:</strong> <?php echo htmlspecialchars($verification['type'] ?? 'Bilinmiyor'); ?></p>
                            <p class="mb-1"><strong>İndirme Zamanı:</strong> <?php echo htmlspecialchars($verification['issued_at'] ?? 'Bilinmiyor'); ?></p>
                            <p class="mb-1"><strong>Deneme:</strong> <?php echo htmlspecialchars($verification['deneme']['deneme_adi'] ?? 'Bilinmiyor'); ?></p>
                            <p class="mb-0"><strong>Kullanıcı:</strong>
                                <?php echo htmlspecialchars($verification['user']['ad_soyad'] ?? 'Bilinmiyor'); ?>
                                <?php if (!empty($verification['user']['email'])): ?>
                                    <span class="text-muted">(<?php echo htmlspecialchars($verification['user']['email']); ?>)</span>
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <form method="post" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="pdf_file" class="form-label">PDF Dosyası</label>
                            <input type="file" class="form-control" id="pdf_file" name="pdf_file" accept="application/pdf" required>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-file-signature me-2"></i> PDF Doğrula
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/templates/footer.php'; ?>
