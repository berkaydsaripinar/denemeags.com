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

$filters = [
    'yazar_id' => filter_input(INPUT_GET, 'yazar_id', FILTER_VALIDATE_INT) ?: 0,
    'deneme_id' => filter_input(INPUT_GET, 'deneme_id', FILTER_VALIDATE_INT) ?: 0,
    'durum' => $_GET['durum'] ?? 'tum',
    'donem' => $_GET['donem'] ?? 'tum',
];

$valid_statuses = ['tum', 'beklemede', 'odendi'];
if (!in_array($filters['durum'], $valid_statuses, true)) {
    $filters['durum'] = 'tum';
}

$valid_periods = ['tum', 'odenebilir', 'bekleyen'];
if (!in_array($filters['donem'], $valid_periods, true)) {
    $filters['donem'] = 'tum';
}

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
              AND (yazar_odeme_durumu IS NULL OR yazar_odeme_durumu = 'beklemede')
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
    $conditions = [];
    $params = [];

    if ($filters['yazar_id']) {
        $conditions[] = 'sl.yazar_id = ?';
        $params[] = $filters['yazar_id'];
    }

    if ($filters['deneme_id']) {
        $conditions[] = 'sl.deneme_id = ?';
        $params[] = $filters['deneme_id'];
    }

    if ($filters['durum'] !== 'tum') {
        $conditions[] = "COALESCE(sl.yazar_odeme_durumu, 'beklemede') = ?";
        $params[] = $filters['durum'];
    }

    if ($filters['donem'] === 'odenebilir') {
        $conditions[] = "COALESCE(sl.yazar_odeme_durumu, 'beklemede') = 'beklemede'";
        $conditions[] = 'sl.tarih <= DATE_SUB(NOW(), INTERVAL 14 DAY)';
    } elseif ($filters['donem'] === 'bekleyen') {
        $conditions[] = "COALESCE(sl.yazar_odeme_durumu, 'beklemede') = 'beklemede'";
        $conditions[] = 'sl.tarih > DATE_SUB(NOW(), INTERVAL 14 DAY)';
    }

    $where_sql = '';
    if (!empty($conditions)) {
        $where_sql = 'WHERE ' . implode(' AND ', $conditions);
    }

    $stmt = $pdo->prepare("
        SELECT
            sl.id,
            sl.yazar_id,
            sl.deneme_id,
            sl.siparis_id,
            sl.yazar_payi,
            sl.tutar_brut,
            sl.tarih,
            sl.yazar_odeme_durumu,
            y.ad_soyad AS yazar_adi,
            y.iban_bilgisi,
            d.deneme_adi
        FROM satis_loglari sl
        JOIN yazarlar y ON sl.yazar_id = y.id
        JOIN denemeler d ON sl.deneme_id = d.id
        {$where_sql}
        ORDER BY y.ad_soyad ASC, sl.tarih ASC
    ");
    $stmt->execute($params);
    $eligible_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    set_admin_flash_message('error', 'Veriler çekilirken hata oluştu: ' . $e->getMessage());
    $eligible_sales = [];
}

$sales_by_author = [];
$overall_totals = [
    'eligible' => 0,
    'pending' => 0,
    'paid' => 0,
];

foreach ($eligible_sales as $sale) {
    $author_id = $sale['yazar_id'];
    $status = $sale['yazar_odeme_durumu'] ?: 'beklemede';
    $is_eligible = ($status === 'beklemede' && strtotime($sale['tarih']) <= strtotime('-14 days'));
    $is_pending = ($status === 'beklemede' && !$is_eligible);

    if (!isset($sales_by_author[$author_id])) {
        $sales_by_author[$author_id] = [
            'yazar_adi' => $sale['yazar_adi'],
            'iban_bilgisi' => $sale['iban_bilgisi'],
            'sales' => [],
            'total' => 0,
            'eligible_total' => 0,
            'pending_total' => 0,
            'paid_total' => 0,
            'eligible_ids' => [],
        ];
    }

    $sale['odeme_durumu'] = $status;
    $sale['odeme_uygun'] = $is_eligible;

    $sales_by_author[$author_id]['sales'][] = $sale;
    $sales_by_author[$author_id]['total'] += (float) $sale['yazar_payi'];

    if ($status === 'odendi') {
        $sales_by_author[$author_id]['paid_total'] += (float) $sale['yazar_payi'];
        $overall_totals['paid'] += (float) $sale['yazar_payi'];
    } elseif ($is_eligible) {
        $sales_by_author[$author_id]['eligible_total'] += (float) $sale['yazar_payi'];
        $sales_by_author[$author_id]['eligible_ids'][] = $sale['id'];
        $overall_totals['eligible'] += (float) $sale['yazar_payi'];
    } elseif ($is_pending) {
        $sales_by_author[$author_id]['pending_total'] += (float) $sale['yazar_payi'];
        $overall_totals['pending'] += (float) $sale['yazar_payi'];
    }
}

$yazarlar = $pdo->query("SELECT id, ad_soyad FROM yazarlar ORDER BY ad_soyad ASC")->fetchAll(PDO::FETCH_ASSOC);
$denemeler = $pdo->query("SELECT id, deneme_adi FROM denemeler ORDER BY deneme_adi ASC")->fetchAll(PDO::FETCH_ASSOC);

include_once __DIR__ . '/../templates/admin_header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col">
        <h3 class="fw-bold mb-0 text-theme-primary">Yazar Hakedişleri</h3>
        <p class="text-muted small">Tüm yazar hakedişlerini görüntüleyin, filtreleyin ve ödemeleri yönetin.</p>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label small text-muted">Yazar</label>
                <select name="yazar_id" class="form-select">
                    <option value="0">Tüm yazarlar</option>
                    <?php foreach ($yazarlar as $yazar): ?>
                        <option value="<?php echo (int) $yazar['id']; ?>" <?php echo $filters['yazar_id'] == $yazar['id'] ? 'selected' : ''; ?>>
                            <?php echo escape_html($yazar['ad_soyad']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">Yayın</label>
                <select name="deneme_id" class="form-select">
                    <option value="0">Tüm yayınlar</option>
                    <?php foreach ($denemeler as $deneme): ?>
                        <option value="<?php echo (int) $deneme['id']; ?>" <?php echo $filters['deneme_id'] == $deneme['id'] ? 'selected' : ''; ?>>
                            <?php echo escape_html($deneme['deneme_adi']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">Durum</label>
                <select name="durum" class="form-select">
                    <option value="tum" <?php echo $filters['durum'] === 'tum' ? 'selected' : ''; ?>>Tümü</option>
                    <option value="beklemede" <?php echo $filters['durum'] === 'beklemede' ? 'selected' : ''; ?>>Beklemede</option>
                    <option value="odendi" <?php echo $filters['durum'] === 'odendi' ? 'selected' : ''; ?>>Ödendi</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">Hakediş Dönemi</label>
                <select name="donem" class="form-select">
                    <option value="tum" <?php echo $filters['donem'] === 'tum' ? 'selected' : ''; ?>>Tümü</option>
                    <option value="odenebilir" <?php echo $filters['donem'] === 'odenebilir' ? 'selected' : ''; ?>>Ödenebilir (14+ gün)</option>
                    <option value="bekleyen" <?php echo $filters['donem'] === 'bekleyen' ? 'selected' : ''; ?>>Bekleyen (14 gün dolmadı)</option>
                </select>
            </div>
            <div class="col-12 text-end">
                <button type="submit" class="btn btn-primary">Filtrele</button>
                <a href="manage_yazar_odemeleri.php" class="btn btn-outline-secondary">Sıfırla</a>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body">
                <div class="text-muted small">Ödenebilir Hakediş</div>
                <div class="h4 fw-bold text-success mb-0"><?php echo number_format($overall_totals['eligible'], 2, ',', '.'); ?> ₺</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body">
                <div class="text-muted small">Bekleyen Hakediş</div>
                <div class="h4 fw-bold text-warning mb-0"><?php echo number_format($overall_totals['pending'], 2, ',', '.'); ?> ₺</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body">
                <div class="text-muted small">Ödenmiş Hakediş</div>
                <div class="h4 fw-bold text-muted mb-0"><?php echo number_format($overall_totals['paid'], 2, ',', '.'); ?> ₺</div>
            </div>
        </div>
    </div>
</div>

<?php if (empty($sales_by_author)): ?>
    <div class="alert alert-info border-0 shadow-sm rounded-4">
        Seçilen filtrelerde kayıt bulunamadı.
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
                    <div class="text-muted small">Toplam Hakediş</div>
                    <div class="h5 fw-bold text-dark mb-1"><?php echo number_format($data['total'], 2, ',', '.'); ?> ₺</div>
                    <div class="small text-success">Ödenebilir: <?php echo number_format($data['eligible_total'], 2, ',', '.'); ?> ₺</div>
                    <div class="small text-warning">Bekleyen: <?php echo number_format($data['pending_total'], 2, ',', '.'); ?> ₺</div>
                    <div class="small text-muted">Ödenmiş: <?php echo number_format($data['paid_total'], 2, ',', '.'); ?> ₺</div>
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
                                <th class="text-center">Durum</th>
                                <th class="text-end pe-4">Tarih</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data['sales'] as $sale): ?>
                                <tr>
                                    <td class="ps-4"><code><?php echo escape_html($sale['siparis_id']); ?></code></td>
                                    <td><?php echo escape_html($sale['deneme_adi']); ?></td>
                                    <td class="text-center"><?php echo number_format($sale['tutar_brut'], 2, ',', '.'); ?> ₺</td>
                                    <td class="text-center text-success fw-bold"><?php echo number_format($sale['yazar_payi'], 2, ',', '.'); ?> ₺</td>
                                    <td class="text-center">
                                        <?php if ($sale['odeme_durumu'] === 'odendi'): ?>
                                            <span class="badge bg-secondary">Ödendi</span>
                                        <?php elseif ($sale['odeme_uygun']): ?>
                                            <span class="badge bg-success">Ödenebilir</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">Bekliyor</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4 text-muted small"><?php echo date('d.m.Y H:i', strtotime($sale['tarih'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white border-0 text-end">
                <?php if (!empty($data['eligible_ids'])): ?>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="yazar_id" value="<?php echo (int) $author_id; ?>">
                        <input type="hidden" name="sale_ids" value="<?php echo implode(',', $data['eligible_ids']); ?>">
                        <button type="submit" name="mark_paid" class="btn btn-success">
                            Ödenebilir Hakedişleri Ödendi Olarak İşaretle
                        </button>
                    </form>
                <?php else: ?>
                    <span class="text-muted small">Bu yazar için ödenebilir kayıt bulunmuyor.</span>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php include_once __DIR__ . '/../templates/admin_footer.php'; ?>
