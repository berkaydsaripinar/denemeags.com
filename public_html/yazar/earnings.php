<?php
// yazar/earnings.php - Bütünleşik ve Profesyonel Finans Yönetimi
$page_title = "Kazançlar ve Ödemeler";
require_once __DIR__ . '/includes/author_header.php';

$csrf_token = generate_csrf_token();

// --- DEĞİŞKENLERİ VARSAYILANLARLA BAŞLAT ---
// Hataları önlemek için değişkenleri en başta tanımlıyoruz.
$fin = [
    'toplam_brut' => 0,
    'toplam_odenmis' => 0,
    'bekleyen_talep' => 0
];
$bakiye = 0;
$requests = [];
$sales = [];

// --- AKSİYON: ÖDEME TALEBİ OLUŞTUR ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_withdrawal'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        set_author_flash_message('error', 'Güvenlik doğrulaması başarısız.');
    } else {
        $talep_tutari = (float)$_POST['tutar'];
        
        // Yazarın gerçek çekilebilir bakiyesini hesapla
        $stmt_bal = $pdo->prepare("
            SELECT 
                (SELECT COALESCE(SUM(yazar_payi), 0) FROM satis_loglari WHERE yazar_id = ?) -
                (SELECT COALESCE(SUM(tutar), 0) FROM yazar_odemeleri WHERE yazar_id = ?) -
                (SELECT COALESCE(SUM(tutar), 0) FROM yazar_odeme_talepleri WHERE yazar_id = ? AND durum = 'beklemede') 
            as net_bakiye
        ");
        $stmt_bal->execute([$yid, $yid, $yid]);
        $net_bakiye = (float)$stmt_bal->fetchColumn();

        if ($talep_tutari < 100) {
            set_author_flash_message('error', 'Minimum ödeme talep tutarı 100 ₺\'dir.');
        } elseif ($talep_tutari > $net_bakiye) {
            set_author_flash_message('error', 'Yetersiz bakiye. Maksimum çekilebilir tutar: ' . number_format($net_bakiye, 2) . ' ₺');
        } else {
            // IBAN Kontrolü
            $stmt_iban = $pdo->prepare("SELECT iban_bilgisi FROM yazarlar WHERE id = ?");
            $stmt_iban->execute([$yid]);
            $iban = $stmt_iban->fetchColumn();

            if (empty($iban)) {
                set_author_flash_message('error', 'Lütfen önce Profil Ayarlarından geçerli bir IBAN giriniz.');
            } else {
                $stmt_ins = $pdo->prepare("INSERT INTO yazar_odeme_talepleri (yazar_id, tutar, iban_adresi) VALUES (?, ?, ?)");
                $stmt_ins->execute([$yid, $talep_tutari, $iban]);
                set_author_flash_message('success', 'Ödeme talebiniz başarıyla oluşturuldu. İlk ödeme gününde hesabınıza yansıtılacaktır.');
            }
        }
    }
    redirect('yazar/earnings.php');
}

// --- VERİ ÇEKME ---
try {
    // 1. Genel Finansal Özet (Toplam, Ödenen, Bekleyen)
    $stmt_summary = $pdo->prepare("
        SELECT 
            COALESCE(SUM(yazar_payi), 0) as toplam_brut,
            (SELECT COALESCE(SUM(tutar), 0) FROM yazar_odemeleri WHERE yazar_id = ?) as toplam_odenmis,
            (SELECT COALESCE(SUM(tutar), 0) FROM yazar_odeme_talepleri WHERE yazar_id = ? AND durum = 'beklemede') as bekleyen_talep
        FROM satis_loglari WHERE yazar_id = ?
    ");
    $stmt_summary->execute([$yid, $yid, $yid]);
    $db_fin = $stmt_summary->fetch();
    
    if ($db_fin) {
        $fin = $db_fin;
    }

    // Değerlerin null olmadığından emin olalım (number_format hata vermemesi için)
    $fin['toplam_brut'] = (float)($fin['toplam_brut'] ?? 0);
    $fin['toplam_odenmis'] = (float)($fin['toplam_odenmis'] ?? 0);
    $fin['bekleyen_talep'] = (float)($fin['bekleyen_talep'] ?? 0);

    $bakiye = $fin['toplam_brut'] - $fin['toplam_odenmis'] - $fin['bekleyen_talep'];

    // 2. Son Ödeme Talepleri
    $stmt_reqs = $pdo->prepare("SELECT * FROM yazar_odeme_talepleri WHERE yazar_id = ? ORDER BY id DESC LIMIT 10");
    $stmt_reqs->execute([$yid]);
    $requests = $stmt_reqs->fetchAll();

    // 3. Son Satış Hareketleri
    $stmt_sales = $pdo->prepare("
        SELECT sl.*, d.deneme_adi 
        FROM satis_loglari sl 
        JOIN denemeler d ON sl.deneme_id = d.id 
        WHERE sl.yazar_id = ? ORDER BY sl.tarih DESC LIMIT 8
    ");
    $stmt_sales->execute([$yid]);
    $sales = $stmt_sales->fetchAll();

} catch (Exception $e) { 
    error_log("Hakediş sayfası hatası: " . $e->getMessage());
}
?>

<div class="d-flex justify-content-between align-items-center mb-5 fade-in">
    <div>
        <h2 class="fw-bold text-dark mb-1">Finansal Durum</h2>
        <p class="text-muted mb-0">Kazançlarınızı izleyin ve hakedişlerinizi kolayca çekin.</p>
    </div>
    <div class="text-end">
        <button class="btn btn-coral shadow-sm px-4 py-2" data-bs-toggle="modal" data-bs-target="#withdrawalModal" <?php echo ($bakiye < 100) ? 'disabled' : ''; ?>>
            <i class="fas fa-hand-holding-usd me-2"></i>Ödeme Talep Et
        </button>
    </div>
</div>

<!-- Özet Kartları -->
<div class="row g-4 mb-5">
    <div class="col-md-4">
        <div class="card card-custom border-bottom border-success border-4 p-4 shadow-sm h-100">
            <div class="d-flex justify-content-between mb-3">
                <span class="text-muted small fw-bold">ÇEKİLEBİLİR BAKİYE</span>
                <div class="bg-success bg-opacity-10 text-success p-2 rounded-3"><i class="fas fa-wallet"></i></div>
            </div>
            <h2 class="fw-black text-dark mb-1"><?php echo number_format($bakiye, 2, ',', '.'); ?> ₺</h2>
            <p class="text-muted small mb-0">Kesintiler ve bekleyen talepler düşülmüştür.</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card card-custom border-bottom border-primary border-4 p-4 shadow-sm h-100">
            <div class="d-flex justify-content-between mb-3">
                <span class="text-muted small fw-bold">TOPLAM ÖDENEN</span>
                <div class="bg-primary bg-opacity-10 text-primary p-2 rounded-3"><i class="fas fa-check-circle"></i></div>
            </div>
            <h2 class="fw-bold text-dark mb-1"><?php echo number_format((float)$fin['toplam_odenmis'], 2, ',', '.'); ?> ₺</h2>
            <p class="text-muted small mb-0">Bugüne kadar hesabınıza yatan toplam tutar.</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card card-custom border-bottom border-warning border-4 p-4 shadow-sm h-100">
            <div class="d-flex justify-content-between mb-3">
                <span class="text-muted small fw-bold">ONAY BEKLEYEN</span>
                <div class="bg-warning bg-opacity-10 text-warning p-2 rounded-3"><i class="fas fa-clock"></i></div>
            </div>
            <h2 class="fw-bold text-dark mb-1"><?php echo number_format((float)$fin['bekleyen_talep'], 2, ',', '.'); ?> ₺</h2>
            <p class="text-muted small mb-0">İşlem sırasındaki aktif talepleriniz.</p>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Ödeme Talepleri -->
    <div class="col-lg-7">
        <div class="card card-custom overflow-hidden border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="mb-0 fw-bold"><i class="fas fa-history me-2 text-primary"></i>Ödeme Taleplerim</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small text-uppercase">
                            <tr>
                                <th class="ps-4">Tarih</th>
                                <th>Tutar</th>
                                <th>Durum</th>
                                <th class="pe-4 text-end">Admin Notu</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($requests)): ?>
                                <tr><td colspan="4" class="text-center py-5 text-muted small">Henüz bir talebiniz bulunmuyor.</td></tr>
                            <?php else: ?>
                                <?php foreach($requests as $r): ?>
                                <tr>
                                    <td class="ps-4 small text-muted"><?php echo date('d.m.Y', strtotime($r['talep_tarihi'])); ?></td>
                                    <td class="fw-bold text-dark"><?php echo number_format((float)$r['tutar'], 2, ',', '.'); ?> ₺</td>
                                    <td>
                                        <?php if($r['durum'] == 'beklemede'): ?>
                                            <span class="badge bg-warning-subtle text-warning rounded-pill px-3">İnceleniyor</span>
                                        <?php elseif($r['durum'] == 'onaylandi'): ?>
                                            <span class="badge bg-success-subtle text-success rounded-pill px-3">Ödendi</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger rounded-pill px-3">Reddedildi</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="pe-4 text-end small text-muted"><?php echo escape_html($r['admin_notu'] ?: '-'); ?></td>
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
        <div class="card card-custom border-0 shadow-sm overflow-hidden">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="mb-0 fw-bold text-success"><i class="fas fa-shopping-cart me-2"></i>Son Satış Hakedişleri</h6>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php if(empty($sales)): ?>
                        <li class="list-group-item text-center py-5 text-muted small">Henüz satış gerçekleşmedi.</li>
                    <?php else: ?>
                        <?php foreach($sales as $s): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3 px-4">
                                <div>
                                    <div class="fw-bold text-dark small mb-1"><?php echo escape_html($s['deneme_adi']); ?></div>
                                    <div class="text-muted" style="font-size: 0.65rem;">
                                        <i class="far fa-clock me-1"></i><?php echo date('d.m.Y H:i', strtotime($s['tarih'])); ?>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <span class="fw-black text-success">+<?php echo number_format((float)$s['yazar_payi'], 2, ',', '.'); ?> ₺</span>
                                    <div class="text-muted" style="font-size: 0.6rem;">Brüt: <?php echo number_format((float)$s['tutar_brut'], 2, ',', '.'); ?> ₺</div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="card-footer bg-light border-0 py-3 text-center">
                <a href="analytics.php" class="text-decoration-none small fw-bold">Tüm Satış Analizlerini Gör <i class="fas fa-chevron-right ms-1"></i></a>
            </div>
        </div>
    </div>
</div>

<!-- ÖDEME TALEP MODAL -->
<div class="modal fade" id="withdrawalModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-primary text-white border-0 py-4">
                <h5 class="modal-title fw-bold mx-auto"><i class="fas fa-hand-holding-usd me-2"></i>Ödeme Talep Formu</h5>
            </div>
            <div class="modal-body p-5">
                <form action="earnings.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="request_withdrawal" value="1">
                    
                    <div class="mb-4 text-center">
                        <label class="form-label small fw-bold text-muted mb-3 text-uppercase">ÇEKİLECEK TUTAR</label>
                        <div class="input-group input-group-lg shadow-sm rounded-3 overflow-hidden">
                            <span class="input-group-text bg-light border-0 fw-bold text-primary">₺</span>
                            <input type="number" name="tutar" class="form-control border-0 bg-light text-center fw-black" 
                                   value="<?php echo floor($bakiye); ?>" min="100" max="<?php echo floor($bakiye); ?>" required>
                        </div>
                        <div class="form-text mt-3 small">
                            Mevcut çekilebilir limitiniz: <strong><?php echo number_format($bakiye, 2, ',', '.'); ?> ₺</strong>
                        </div>
                    </div>

                    <div class="alert bg-primary bg-opacity-10 text-primary border-0 rounded-4 small mb-4">
                        <i class="fas fa-info-circle me-2"></i> Ödemeler, profilinizdeki <strong>IBAN</strong> adresine, talebinizi takip eden ilk Çarşamba günü gönderilir.
                    </div>

                    <button type="submit" class="btn btn-coral btn-lg w-100 py-3 shadow fw-bold">TALEBİ SİSTEME GÖNDER</button>
                    <button type="button" class="btn btn-link w-100 text-muted small mt-2 text-decoration-none" data-bs-dismiss="modal">İşlemi İptal Et</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/author_footer.php'; ?>