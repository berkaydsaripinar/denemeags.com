<?php
// admin/manage_yazar_talepleri.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAdminLogin();
if (!isSuperAdmin()) redirect('admin/dashboard.php');

$page_title = "Ödeme Talepleri";
$csrf_token = generate_admin_csrf_token();

// --- AKSİYONLAR ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_request'])) {
    if (verify_admin_csrf_token($_POST['csrf_token'])) {
        $tid = (int)$_POST['talep_id'];
        $yid = (int)$_POST['yazar_id'];
        $tutar = (float)$_POST['tutar'];
        $durum = $_POST['yeni_durum']; // 'onaylandi' veya 'reddedildi'
        $not = trim($_POST['admin_notu']);

        $pdo->beginTransaction();
        try {
            // Talebi güncelle
            $stmt = $pdo->prepare("UPDATE yazar_odeme_talepleri SET durum = ?, admin_notu = ?, islem_tarihi = NOW() WHERE id = ?");
            $stmt->execute([$durum, $not, $tid]);

            // Eğer ONAYLANDI ise yazar_odemeleri tablosuna asıl kaydı yap
            if ($durum === 'onaylandi') {
                $stmt_pay = $pdo->prepare("INSERT INTO yazar_odemeleri (yazar_id, tutar, notlar) VALUES (?, ?, ?)");
                $stmt_pay->execute([$yid, $tutar, "Talep #$tid Onaylandı: $not"]);
            }

            $pdo->commit();
            set_admin_flash_message('success', 'Talep başarıyla işlendi.');
        } catch (Exception $e) { $pdo->rollBack(); set_admin_flash_message('error', 'Hata!'); }
    }
    redirect('admin/manage_yazar_talepleri.php');
}

// Talepleri Çek
$talepler = $pdo->query("
    SELECT ot.*, y.ad_soyad, y.email, y.iban_bilgisi 
    FROM yazar_odeme_talepleri ot
    JOIN yazarlar y ON ot.yazar_id = y.id
    ORDER BY ot.talep_tarihi DESC
")->fetchAll();

include_once __DIR__ . '/../templates/admin_header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col">
        <h3 class="fw-bold mb-0">Yazar Ödeme Talepleri</h3>
        <p class="text-muted small">Yazarların para çekme taleplerini buradan inceleyip onaylayabilirsiniz.</p>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 admin-table text-center">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4 text-start">Yazar & IBAN</th>
                        <th>Tutar</th>
                        <th>Talep Tarihi</th>
                        <th>Durum</th>
                        <th class="pe-4 text-end">İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($talepler)): ?>
                        <tr><td colspan="5" class="py-5 text-muted">Bekleyen talep bulunmuyor.</td></tr>
                    <?php else: ?>
                        <?php foreach($talepler as $t): ?>
                        <tr>
                            <td class="ps-4 text-start">
                                <div class="fw-bold"><?php echo $t['ad_soyad']; ?></div>
                                <code class="small text-muted"><?php echo $t['iban_adresi'] ?: $t['iban_bilgisi']; ?></code>
                            </td>
                            <td class="fw-bold text-dark"><?php echo number_format($t['tutar'], 2); ?> ₺</td>
                            <td class="small text-muted"><?php echo date('d.m.Y H:i', strtotime($t['talep_tarihi'])); ?></td>
                            <td>
                                <?php if($t['durum'] == 'beklemede'): ?>
                                    <span class="badge bg-warning rounded-pill">Onay Bekliyor</span>
                                <?php elseif($t['durum'] == 'onaylandi'): ?>
                                    <span class="badge bg-success rounded-pill">Ödendi</span>
                                <?php else: ?>
                                    <span class="badge bg-danger rounded-pill">Reddedildi</span>
                                <?php endif; ?>
                            </td>
                            <td class="pe-4 text-end">
                                <?php if($t['durum'] == 'beklemede'): ?>
                                    <button class="btn btn-sm btn-primary px-3" data-bs-toggle="modal" data-bs-target="#processModal<?php echo $t['id']; ?>">İşle</button>
                                    
                                    <!-- Modal -->
                                    <div class="modal fade text-start" id="processModal<?php echo $t['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content border-0 shadow rounded-4">
                                                <div class="modal-body p-4">
                                                    <h6 class="fw-bold mb-3">Talep İşleme (#<?php echo $t['id']; ?>)</h6>
                                                    <form method="POST">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                        <input type="hidden" name="talep_id" value="<?php echo $t['id']; ?>">
                                                        <input type="hidden" name="yazar_id" value="<?php echo $t['yazar_id']; ?>">
                                                        <input type="hidden" name="tutar" value="<?php echo $t['tutar']; ?>">
                                                        <input type="hidden" name="process_request" value="1">
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label small fw-bold">KARAR</label>
                                                            <select name="yeni_durum" class="form-select" required>
                                                                <option value="onaylandi">Onayla ve Ödendi İşaretle</option>
                                                                <option value="reddedildi">Reddet (Bakiyeye Geri Döner)</option>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label small fw-bold">ADMİN NOTU / DEKONT NO</label>
                                                            <input type="text" name="admin_notu" class="form-control" placeholder="Havale yapıldı vb.">
                                                        </div>
                                                        <button type="submit" class="btn btn-success w-100 py-2">KAYDET</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <small class="text-muted"><?php echo $t['admin_notu']; ?></small>
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

<?php include_once __DIR__ . '/../templates/admin_footer.php'; ?>