<?php
// admin/manage_yazar_talepleri.php - Yazarların Kestiği Talepleri Onaylama Ekranı
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAdminLogin();
if (!isSuperAdmin()) redirect('admin/dashboard.php');

$page_title = "Bekleyen Ödeme Talepleri";
$csrf_token = generate_admin_csrf_token();

// --- TALEBİ İŞLE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_withdrawal'])) {
    if (verify_admin_csrf_token($_POST['csrf_token'])) {
        $tid = (int)$_POST['talep_id'];
        $yid = (int)$_POST['yazar_id'];
        $tutar = (float)$_POST['tutar'];
        $durum = $_POST['karar']; // 'onaylandi' veya 'reddedildi'
        $admin_notu = trim($_POST['admin_notu'] ?? '');

        try {
            $pdo->beginTransaction();

            // 1. Talebi güncelle
            $upd = $pdo->prepare("UPDATE yazar_odeme_talepleri SET durum = ?, admin_notu = ?, islem_tarihi = NOW() WHERE id = ?");
            $upd->execute([$durum, $admin_notu, $tid]);

            // 2. Eğer onaylandıysa asıl ödeme tablosuna (yazar_odemeleri) ekle
            if ($durum === 'onaylandi') {
                $pay = $pdo->prepare("INSERT INTO yazar_odemeleri (yazar_id, tutar, notlar) VALUES (?, ?, ?)");
                $pay->execute([$yid, $tutar, "Onaylanan Talep #$tid | " . $admin_notu]);
            }

            $pdo->commit();
            set_admin_flash_message('success', 'Ödeme talebi başarıyla işlendi.');
        } catch (Exception $e) {
            $pdo->rollBack();
            set_admin_flash_message('error', 'İşlem sırasında hata: ' . $e->getMessage());
        }
    }
    redirect('admin/manage_yazar_talepleri.php');
}

// Bekleyen ve Son Talepleri Çek
$requests = $pdo->query("
    SELECT ot.*, y.ad_soyad, y.email, y.iban_bilgisi as yazar_iban 
    FROM yazar_odeme_talepleri ot
    JOIN yazarlar y ON ot.yazar_id = y.id
    ORDER BY CASE WHEN ot.durum = 'beklemede' THEN 1 ELSE 2 END, ot.talep_tarihi DESC
")->fetchAll();

include_once __DIR__ . '/../templates/admin_header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col">
        <h3 class="fw-bold mb-0">Para Çekme Talepleri</h3>
        <p class="text-muted small">Yazarların gönderdiği ödeme isteklerini buradan yönetin.</p>
    </div>
    <div class="col-auto">
        <a href="manage_yazarlar.php" class="btn btn-light border"><i class="fas fa-arrow-left me-2"></i> Yazarlara Dön</a>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 admin-table text-center">
                <thead class="table-light small">
                    <tr>
                        <th class="ps-4 text-start">Yazar & IBAN</th>
                        <th>Talep Tutarı</th>
                        <th>Tarih</th>
                        <th>Durum</th>
                        <th class="pe-4 text-end">Aksiyon</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($requests)): ?>
                        <tr><td colspan="5" class="py-5 text-muted">Hiç ödeme talebi bulunmuyor.</td></tr>
                    <?php else: ?>
                        <?php foreach($requests as $r): ?>
                        <tr>
                            <td class="ps-4 text-start">
                                <div class="fw-bold text-dark"><?php echo escape_html($r['ad_soyad']); ?></div>
                                <code class="small text-muted"><?php echo $r['iban_adresi'] ?: $r['yazar_iban']; ?></code>
                            </td>
                            <td class="fw-black text-primary fs-5"><?php echo number_format($r['tutar'], 2); ?> ₺</td>
                            <td class="small text-muted"><?php echo date('d.m.Y H:i', strtotime($r['talep_tarihi'])); ?></td>
                            <td>
                                <?php if($r['durum'] == 'beklemede'): ?>
                                    <span class="badge bg-warning rounded-pill px-3">Onay Bekliyor</span>
                                <?php elseif($r['durum'] == 'onaylandi'): ?>
                                    <span class="badge bg-success rounded-pill px-3">Ödendi</span>
                                <?php else: ?>
                                    <span class="badge bg-danger rounded-pill px-3">Reddedildi</span>
                                <?php endif; ?>
                            </td>
                            <td class="pe-4 text-end">
                                <?php if($r['durum'] == 'beklemede'): ?>
                                    <button class="btn btn-sm btn-primary rounded-pill px-4 fw-bold" data-bs-toggle="modal" data-bs-target="#processModal<?php echo $r['id']; ?>">İşle</button>
                                    
                                    <!-- Talep İşleme Modalı -->
                                    <div class="modal fade text-start" id="processModal<?php echo $r['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content border-0 shadow-lg rounded-4">
                                                <div class="modal-body p-4">
                                                    <h6 class="fw-bold mb-4">Talep Yönetimi (#<?php echo $r['id']; ?>)</h6>
                                                    <form method="POST">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                        <input type="hidden" name="talep_id" value="<?php echo $r['id']; ?>">
                                                        <input type="hidden" name="yazar_id" value="<?php echo $r['yazar_id']; ?>">
                                                        <input type="hidden" name="tutar" value="<?php echo $r['tutar']; ?>">
                                                        <input type="hidden" name="process_withdrawal" value="1">
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label small fw-bold">KARARINIZ</label>
                                                            <select name="karar" class="form-select input-theme" required>
                                                                <option value="onaylandi">Ödeme Yapıldı (Onayla)</option>
                                                                <option value="reddedildi">Talebi Reddet</option>
                                                            </select>
                                                        </div>
                                                        <div class="mb-4">
                                                            <label class="form-label small fw-bold">ADMİN NOTU</label>
                                                            <input type="text" name="admin_notu" class="form-control input-theme" placeholder="Havale tamamlandı / IBAN hatalı vb.">
                                                        </div>
                                                        <button type="submit" class="btn btn-success w-100 fw-bold py-2">KAYDET VE BİTİR</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <small class="text-muted italic"><?php echo escape_html($r['admin_notu'] ?: 'İşlem tamamlandı.'); ?></small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../templates/admin_header.php'; ?>