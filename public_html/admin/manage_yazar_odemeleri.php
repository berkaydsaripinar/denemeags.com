<?php
// admin/manage_yazar_odemeleri.php - 14 gününü dolduran hakedişler
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAdminLogin();

if (!isSuperAdmin()) {
    set_admin_flash_message('error', 'Bu sayfaya erişim yetkiniz yok.');
    redirect('dashboard.php');
}

$page_title = "Yazar Hakediş Ödemeleri";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_paid'])) {
    $yazar_id = filter_input(INPUT_POST, 'yazar_id', FILTER_VALIDATE_INT);
    $sale_ids_raw = trim($_POST['sale_ids'] ?? '');
    $sale_ids = array_filter(array_map('intval', explode(',', $sale_ids_raw)));

    if (!$yazar_id || empty($sale_ids)) {
        set_admin_flash_message('error', 'Ödeme için geçerli kayıt bulunamadı.');
        redirect('manage_yazar_odemeleri.php');
    }

    try {
        $pdo->beginTransaction();

        $placeholders = implode(',', array_fill(0, count($sale_ids), '?'));
        $params = array_merge([$yazar_id], $sale_ids);

        $stmt_sales = $pdo->prepare("
            SELECT id, yazar_payi
            FROM satis_loglari
            WHERE yazar_id = ?
              AND id IN ($placeholders)
              AND yazar_odeme_durumu = 'beklemede'
              AND tarih <= DATE_SUB(NOW(), INTERVAL 14 DAY)
        ");
        $stmt_sales->execute($params);
        $sales = $stmt_sales->fetchAll(PDO::FETCH_ASSOC);

        if (empty($sales)) {
            $pdo->rollBack();
            set_admin_flash_message('warning', 'Seçilen satışlar ödeme için uygun değil.');
            redirect('manage_yazar_odemeleri.php');
        }

        $total_payout = array_sum(array_column($sales, 'yazar_payi'));
        $sale_ids_confirmed = array_column($sales, 'id');

        $stmt_pay = $pdo->prepare("INSERT INTO yazar_odemeleri (yazar_id, tutar, notlar) VALUES (?, ?, ?)");
        $stmt_pay->execute([$yazar_id, $total_payout, 'Satış ID: ' . implode(',', $sale_ids_confirmed)]);

        $placeholders = implode(',', array_fill(0, count($sale_ids_confirmed), '?'));
        $stmt_update = $pdo->prepare("
            UPDATE satis_loglari
            SET yazar_odeme_durumu = 'odendi', yazar_odeme_tarihi = NOW()
            WHERE id IN ($placeholders)
        ");
        $stmt_update->execute($sale_ids_confirmed);

        $pdo->commit();
        set_admin_flash_message('success', 'Ödeme kaydı oluşturuldu ve satışlar güncellendi.');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        set_admin_flash_message('error', 'Ödeme kaydı oluşturulurken hata oluştu: ' . $e->getMessage());
    }

    redirect('manage_yazar_odemeleri.php');
}

try {
    $stmt = $pdo->query("
        SELECT
            sl.id,
            sl.yazar_id,
            sl.deneme_id,
            sl.siparis_id,
            sl.yazar_payi,
            sl.tutar_brut,
            sl.tarih,
            y.ad_soyad AS yazar_adi,
            y.iban_bilgisi,
            d.deneme_adi
        FROM satis_loglari sl
        JOIN yazarlar y ON sl.yazar_id = y.id
        JOIN denemeler d ON sl.deneme_id = d.id
        WHERE sl.yazar_odeme_durumu = 'beklemede'
          AND sl.tarih <= DATE_SUB(NOW(), INTERVAL 14 DAY)
        ORDER BY y.ad_soyad ASC, sl.tarih ASC
    ");
    $eligible_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    set_admin_flash_message('error', 'Veriler çekilirken hata oluştu: ' . $e->getMessage());
    $eligible_sales = [];
}

$sales_by_author = [];
foreach ($eligible_sales as $sale) {
    $author_id = $sale['yazar_id'];
    if (!isset($sales_by_author[$author_id])) {
        $sales_by_author[$author_id] = [
            'yazar_adi' => $sale['yazar_adi'],
            'iban_bilgisi' => $sale['iban_bilgisi'],
            'sales' => [],
            'total' => 0,
        ];
    }
    $sales_by_author[$author_id]['sales'][] = $sale;
    $sales_by_author[$author_id]['total'] += (float) $sale['yazar_payi'];
}

include_once __DIR__ . '/../templates/admin_header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col">
        <h3 class="fw-bold mb-0 text-theme-primary">Ödemeye Hazır Hakedişler</h3>
        <p class="text-muted small">14 gününü dolduran satışlar burada listelenir ve ödeme kayıtları oluşturulur.</p>
    </div>
</div>

<?php if (empty($sales_by_author)): ?>
    <div class="alert alert-info border-0 shadow-sm rounded-4">
        Ödeme yapılacak hakediş bulunmuyor.
    </div>
<?php else: ?>
    <?php foreach ($sales_by_author as $author_id => $data): ?>
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white border-0 d-flex flex-wrap align-items-center justify-content-between">
                <div>
                    <div class="fw-bold"><?php echo escape_html($data['yazar_adi']); ?></div>
                    <div class="text-muted small">IBAN: <?php echo escape_html($data['iban_bilgisi'] ?: 'IBAN bilgisi yok'); ?></div>
                </div>
                <div class="text-end">
                    <div class="text-muted small">Ödenebilir Toplam</div>
                    <div class="h5 fw-bold text-success mb-0"><?php echo number_format($data['total'], 2); ?> ₺</div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th class="ps-4">Sipariş ID</th>
                                <th>Ürün</th>
                                <th class="text-center">Brüt Tutar</th>
                                <th class="text-center">Yazar Payı</th>
                                <th class="text-end pe-4">Tarih</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data['sales'] as $sale): ?>
                                <tr>
                                    <td class="ps-4"><code><?php echo escape_html($sale['siparis_id']); ?></code></td>
                                    <td><?php echo escape_html($sale['deneme_adi']); ?></td>
                                    <td class="text-center"><?php echo number_format($sale['tutar_brut'], 2); ?> ₺</td>
                                    <td class="text-center text-success fw-bold"><?php echo number_format($sale['yazar_payi'], 2); ?> ₺</td>
                                    <td class="text-end pe-4 text-muted small"><?php echo date('d.m.Y H:i', strtotime($sale['tarih'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white border-0 text-end">
                <form method="POST" class="d-inline">
                    <input type="hidden" name="yazar_id" value="<?php echo (int) $author_id; ?>">
                    <input type="hidden" name="sale_ids" value="<?php echo implode(',', array_column($data['sales'], 'id')); ?>">
                    <button type="submit" name="mark_paid" class="btn btn-success">
                        Ödeme Yapıldı Olarak İşaretle
                    </button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php include_once __DIR__ . '/../templates/admin_footer.php'; ?>