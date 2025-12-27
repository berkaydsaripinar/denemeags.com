<?php
// admin/manage_satislar.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAdminLogin();

if (!isSuperAdmin()) {
    set_admin_flash_message('error', 'Bu sayfaya erişim yetkiniz yok.');
    redirect('dashboard.php');
}

$page_title = "Siparişler / Satış Logları";

// --- FİLTRELEME ---
$author_filter = filter_input(INPUT_GET, 'author_id', FILTER_VALIDATE_INT);
$product_filter = filter_input(INPUT_GET, 'product_id', FILTER_VALIDATE_INT);

$where = [];
$params = [];

if ($author_filter) { $where[] = "sl.yazar_id = ?"; $params[] = $author_filter; }
if ($product_filter) { $where[] = "sl.deneme_id = ?"; $params[] = $product_filter; }

$where_sql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// --- VERİ ÇEKME ---
try {
    // Filtreler için listeler
    $authors_list = $pdo->query("SELECT id, ad_soyad FROM yazarlar ORDER BY ad_soyad ASC")->fetchAll();
    $products_list = $pdo->query("SELECT id, deneme_adi FROM denemeler ORDER BY deneme_adi ASC")->fetchAll();

    // Satışları çek
    $sql = "
        SELECT 
            sl.*, 
            y.ad_soyad as yazar_adi, 
            d.deneme_adi, 
            k.ad_soyad as alici_adi,
            k.email as alici_email
        FROM satis_loglari sl
        LEFT JOIN yazarlar y ON sl.yazar_id = y.id
        LEFT JOIN denemeler d ON sl.deneme_id = d.id
        LEFT JOIN kullanicilar k ON sl.kullanici_id = k.id
        $where_sql
        ORDER BY sl.tarih DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $satislar = $stmt->fetchAll();

} catch (PDOException $e) {
    set_admin_flash_message('error', 'Hata: ' . $e->getMessage());
    $satislar = [];
}

include_once __DIR__ . '/../templates/admin_header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col">
        <h3 class="fw-bold mb-0 text-theme-primary">Satış & Sipariş Raporları</h3>
        <p class="text-muted small">Platform üzerindeki tüm finansal hareketlerin detaylı dökümü.</p>
    </div>
</div>

<!-- Filtreler -->
<div class="card border-0 shadow-sm rounded-4 mb-4 bg-light">
    <div class="card-body px-4 py-3">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-bold">YAZAR FİLTRESİ</label>
                <select name="author_id" class="form-select input-theme" onchange="this.form.submit()">
                    <option value="">Tüm Yazarlar</option>
                    <?php foreach($authors_list as $a): ?>
                        <option value="<?php echo $a['id']; ?>" <?php echo $author_filter == $a['id'] ? 'selected' : ''; ?>><?php echo $a['ad_soyad']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-bold">ÜRÜN FİLTRESİ</label>
                <select name="product_id" class="form-select input-theme" onchange="this.form.submit()">
                    <option value="">Tüm Ürünler</option>
                    <?php foreach($products_list as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo $product_filter == $p['id'] ? 'selected' : ''; ?>><?php echo $p['deneme_adi']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="manage_satislar.php" class="btn btn-outline-secondary btn-sm mb-1">Filtreleri Temizle</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th class="ps-4">Sipariş ID</th>
                        <th>Ürün / Yazar</th>
                        <th>Alıcı</th>
                        <th class="text-center">Brüt Tutar</th>
                        <th class="text-center">Yazar Payı</th>
                        <th class="text-center">Platform Payı</th>
                        <th class="pe-4 text-end">Tarih</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($satislar)): ?>
                        <tr><td colspan="7" class="py-5 text-center text-muted">Satış kaydı bulunamadı.</td></tr>
                    <?php else: ?>
                        <?php foreach($satislar as $s): ?>
                        <tr>
                            <td class="ps-4">
                                <code class="fw-bold text-theme-primary"><?php echo $s['siparis_id']; ?></code>
                            </td>
                            <td>
                                <div class="fw-bold small"><?php echo escape_html($s['deneme_adi']); ?></div>
                                <div class="text-muted" style="font-size: 0.7rem;"><i class="fas fa-pen-nib me-1"></i> <?php echo escape_html($s['yazar_adi'] ?? 'Platform'); ?></div>
                            </td>
                            <td>
                                <div class="small fw-bold"><?php echo escape_html($s['alici_adi'] ?? 'Bilinmeyen'); ?></div>
                                <div class="text-muted" style="font-size: 0.7rem;"><?php echo escape_html($s['alici_email']); ?></div>
                            </td>
                            <td class="text-center fw-bold text-dark"><?php echo number_format($s['tutar_brut'], 2); ?> ₺</td>
                            <td class="text-center fw-bold text-success">
                                <?php echo number_format($s['yazar_payi'], 2); ?> ₺
                                <div class="text-muted" style="font-size: 0.6rem;">(%<?php echo $s['komisyon_yazar_orani']; ?>)</div>
                            </td>
                            <td class="text-center fw-bold text-primary"><?php echo number_format($s['platform_payi'], 2); ?> ₺</td>
                            <td class="pe-4 text-end small text-muted">
                                <?php echo date('d.m.Y', strtotime($s['tarih'])); ?><br>
                                <?php echo date('H:i', strtotime($s['tarih'])); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="alert alert-info mt-4 border-0 shadow-sm rounded-4 small">
    <i class="fas fa-info-circle me-2"></i> <strong>Önemli:</strong> Buradaki rakamlar veritabanına kaydedilen brüt tutarlardır. Aracı ödeme kuruluşlarının (Shopier/Iyzico) kendi işlem ücretleri platform payından düşülmelidir.
</div>

<?php include_once __DIR__ . '/../templates/admin_footer.php'; ?>