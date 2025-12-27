<?php
// author/earnings.php - Gelişmiş Kazanç ve Ödeme Talebi Sayfası
require_once __DIR__ . '/includes/author_header.php'; 

$yazar_id = $_SESSION['author_id'];
$csrf_token = generate_csrf_token();

// --- AKSİYON: ÖDEME TALEBİ OLUŞTUR ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_withdrawal'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        set_author_flash_message('error', 'Geçersiz istek.');
    } else {
        $talep_tutari = (float)$_POST['tutar'];
        
        // Yazarın mevcut bakiyesini hesapla
        $stmt_bal = $pdo->prepare("
            SELECT 
                (SELECT COALESCE(SUM(yazar_payi), 0) FROM satis_loglari WHERE yazar_id = ?) -
                (SELECT COALESCE(SUM(tutar), 0) FROM yazar_odemeleri WHERE yazar_id = ?) -
                (SELECT COALESCE(SUM(tutar), 0) FROM yazar_odeme_talepleri WHERE yazar_id = ? AND durum = 'beklemede') 
            as net_bakiye
        ");
        $stmt_bal->execute([$yazar_id, $yazar_id, $yazar_id]);
        $net_bakiye = $stmt_bal->fetchColumn();

        if ($talep_tutari < 100) {
            set_author_flash_message('error', 'Minimum ödeme talep tutarı 100 ₺\'dir.');
        } elseif ($talep_tutari > $net_bakiye) {
            set_author_flash_message('error', 'Yetersiz bakiye. Maksimum çekilebilir tutar: ' . number_format($net_bakiye, 2) . ' ₺');
        } else {
            // IBAN bilgisini yazardan çek
            $stmt_iban = $pdo->prepare("SELECT iban_bilgisi FROM yazarlar WHERE id = ?");
            $stmt_iban->execute([$yazar_id]);
            $iban = $stmt_iban->fetchColumn();

            if (empty($iban)) {
                set_author_flash_message('error', 'Ödeme alabilmek için profilinizden IBAN bilgisi girmelisiniz.');
            } else {
                $stmt_ins = $pdo->prepare("INSERT INTO yazar_odeme_talepleri (yazar_id, tutar, iban_adresi) VALUES (?, ?, ?)");
                $stmt_ins->execute([$yazar_id, $talep_tutari, $iban]);
                set_author_flash_message('success', 'Ödeme talebiniz başarıyla alındı. Yönetici onayından sonra işleminiz gerçekleşecektir.');
            }
        }
    }
    redirect('author/earnings.php');
}

// --- VERİ ÇEKME ---
try {
    // 1. Genel Finansal Özet
    $stmt_summary = $pdo->prepare("
        SELECT 
            COALESCE(SUM(yazar_payi), 0) as toplam_brut,
            (SELECT COALESCE(SUM(tutar), 0) FROM yazar_odemeleri WHERE yazar_id = ?) as toplam_odenmis,
            (SELECT COALESCE(SUM(tutar), 0) FROM yazar_odeme_talepleri WHERE yazar_id = ? AND durum = 'beklemede') as bekleyen_talep
        FROM satis_loglari WHERE yazar_id = ?
    ");
    $stmt_summary->execute([$yazar_id, $yazar_id, $yazar_id]);
    $fin = $stmt_summary->fetch();

    $bakiye = $fin['toplam_brut'] - $fin['toplam_odenmis'] - $fin['bekleyen_talep'];

    // 2. Son Ödeme Talepleri
    $stmt_reqs = $pdo->prepare("SELECT * FROM yazar_odeme_talepleri WHERE yazar_id = ? ORDER BY talep_tarihi DESC LIMIT 10");
    $stmt_reqs->execute([$yazar_id]);
    $requests = $stmt_reqs->fetchAll();

    // 3. Son Satışlar
    $stmt_sales = $pdo->prepare("
        SELECT sl.*, d.deneme_adi 
        FROM satis_loglari sl 
        JOIN denemeler d ON sl.deneme_id = d.id 
        WHERE sl.yazar_id = ? ORDER BY sl.tarih DESC LIMIT 10
    ");
    $stmt_sales->execute([$yazar_id]);
    $sales = $stmt_sales->fetchAll();

} catch (Exception $e) { $requests = []; $sales = []; $bakiye = 0; }

?>

<div class="row g-4 mb-5">
    <!-- Bakiye Kartı -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 h-100 bg-white overflow-hidden">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between mb-3">
                    <span class="text-muted fw-bold small">ÇEKİLEBİLİR BAKİYE</span>
                    <i class="fas fa-wallet text-success"></i>
                </div>
                <h1 class="fw-black text-dark mb-1"><?php echo number_format($bakiye, 2, ',', '.'); ?> ₺</h1>
                <p class="text-muted small mb-4">Ödenmiş: <?php echo number_format($fin['toplam_odenmis'], 2); ?> ₺</p>
                
                <button class="btn btn-author-primary w-100 py-2 fw-bold" data-bs-toggle="modal" data-bs-target="#withdrawalModal" <?php echo ($bakiye < 100) ? 'disabled' : ''; ?>>
                    <?php echo ($bakiye < 100) ? 'Min. 100 ₺ Gerekli' : 'Ödeme Talep Et'; ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Bekleyen Talep Kartı -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between mb-3">
                    <span class="text-muted fw-bold small">ONAY BEKLEYEN</span>
                    <i class="fas fa-clock text-warning"></i>
                </div>
                <h2 class="fw-bold mb-1"><?php echo number_format($fin['bekleyen_talep'], 2, ',', '.'); ?> ₺</h2>
                <p class="text-muted small">Şu an inceleme aşamasındaki tutar.</p>
            </div>
        </div>
    </div>

    <!-- Toplam Kazanç Kartı -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between mb-3">
                    <span class="text-muted fw-bold small">TOPLAM HASILAT</span>
                    <i class="fas fa-chart-line text-primary"></i>
                </div>
                <h2 class="fw-bold mb-1"><?php echo number_format($fin['toplam_brut'], 2, ',', '.'); ?> ₺</h2>
                <p class="text-muted small">Platformdaki tüm zamanların kazancı.</p>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Ödeme Talepleri Geçmişi -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold">Ödeme Taleplerim</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th class="ps-4">Tarih</th>
                                <th>Tutar</th>
                                <th>Durum</th>
                                <th class="pe-4 text-end">Not</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($requests)): ?>
                                <tr><td colspan="4" class="text-center py-4 text-muted small">Henüz bir talebiniz bulunmuyor.</td></tr>
                            <?php else: ?>
                                <?php foreach($requests as $r): ?>
                                <tr>
                                    <td class="ps-4 small"><?php echo date('d.m.Y', strtotime($r['talep_tarihi'])); ?></td>
                                    <td class="fw-bold"><?php echo number_format($r['tutar'], 2); ?> ₺</td>
                                    <td>
                                        <?php if($r['durum'] == 'beklemede'): ?>
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill">Beklemede</span>
                                        <?php elseif($r['durum'] == 'onaylandi'): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">Ödendi</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill">Reddedildi</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="pe-4 text-end small text-muted"><?php echo escape_html($r['admin_notu'] ?? '-'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Son Satışlar -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold">Son Satışlar</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <tbody>
                            <?php foreach($sales as $s): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold small"><?php echo escape_html($s['deneme_adi']); ?></div>
                                    <div class="text-muted" style="font-size: 0.7rem;"><?php echo date('d.m.Y H:i', strtotime($s['tarih'])); ?></div>
                                </td>
                                <td class="pe-4 text-end">
                                    <div class="fw-bold text-success">+<?php echo number_format($s['yazar_payi'], 2); ?> ₺</div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ÖDEME TALEP MODALI -->
<div class="modal fade" id="withdrawalModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-body p-5">
                <div class="text-center mb-4">
                    <div class="bg-success bg-opacity-10 text-success p-3 rounded-circle d-inline-block mb-3">
                        <i class="fas fa-hand-holding-usd fa-2x"></i>
                    </div>
                    <h5 class="fw-bold">Ödeme Talep Formu</h5>
                    <p class="text-muted small">Bakiyenizi profilinizde kayıtlı olan IBAN adresinize çekebilirsiniz.</p>
                </div>
                
                <form action="earnings.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="request_withdrawal" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold">ÇEKİLECEK TUTAR (₺)</label>
                        <input type="number" name="tutar" class="form-control form-control-lg input-theme text-center fw-black" 
                               value="<?php echo floor($bakiye); ?>" min="100" max="<?php echo floor($bakiye); ?>" step="1" required>
                        <div class="form-text text-center small mt-2">Maksimum: <?php echo number_format($bakiye, 2); ?> ₺</div>
                    </div>

                    <div class="p-3 bg-light rounded-3 mb-4 small">
                        <i class="fas fa-info-circle me-1 text-primary"></i> Ödemeler, talebinizi takip eden ilk Çarşamba günü hesabınıza gönderilir.
                    </div>

                    <button type="submit" class="btn btn-author-primary btn-lg w-100 py-3 shadow">TALEBİ GÖNDER</button>
                    <button type="button" class="btn btn-link w-100 text-muted small mt-2" data-bs-dismiss="modal">İptal Et</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include_once 'includes/author_footer.php'; ?>