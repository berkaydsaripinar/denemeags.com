<?php
// yazar/manage_codes.php - Yazarlar İçin Gelişmiş Kod Yönetimi
$page_title = "Erişim Kodları Yönetimi";
require_once __DIR__ . '/includes/author_header.php';

$csrf_token = generate_csrf_token();

// 1. Yazara ait aktif ürünleri çek (Dropdown ve Filtre için)
try {
    $stmt_prods = $pdo->prepare("SELECT id, deneme_adi FROM denemeler WHERE yazar_id = ? AND aktif_mi = 1 ORDER BY deneme_adi ASC");
    $stmt_prods->execute([$yid]);
    $my_products = $stmt_prods->fetchAll();
} catch (PDOException $e) { $my_products = []; }

// 2. Filtreleme ve Sayfalama
$filter_id = filter_input(INPUT_GET, 'filter_id', FILTER_VALIDATE_INT);
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
$limit = 50;
$offset = ($page - 1) * $limit;

try {
    // Yazarın sadece kendi ürünlerine ait kodları görmesini sağlayan sorgu
    $where = ["d.yazar_id = ?"];
    $params = [$yid];

    if ($filter_id) {
        $where[] = "ek.urun_id = ?";
        $params[] = $filter_id;
    }

    $where_sql = implode(" AND ", $where);

    // Toplam Sayı
    $count_sql = "SELECT COUNT(*) FROM erisim_kodlari ek JOIN denemeler d ON ek.urun_id = d.id WHERE $where_sql";
    $stmt_count = $pdo->prepare($count_sql);
    $stmt_count->execute($params);
    $total_codes = $stmt_count->fetchColumn();
    $total_pages = ceil($total_codes / $limit);

    // Liste
    $list_sql = "
        SELECT ek.*, d.deneme_adi, k.ad_soyad as kullanan_ogrenci
        FROM erisim_kodlari ek
        JOIN denemeler d ON ek.urun_id = d.id
        LEFT JOIN kullanicilar k ON ek.kullanici_id = k.id
        WHERE $where_sql
        ORDER BY ek.id DESC 
        LIMIT $limit OFFSET $offset
    ";
    $stmt_list = $pdo->prepare($list_sql);
    $stmt_list->execute($params);
    $codes = $stmt_list->fetchAll();

} catch (PDOException $e) { $codes = []; $total_codes = 0; }

?>

<div class="d-flex justify-content-between align-items-center mb-5 fade-in">
    <div>
        <h2 class="fw-bold text-dark mb-1">Kod Üretimi ve Yönetimi</h2>
        <p class="text-muted mb-0">Yayınlarınız için erişim kodlarını buradan oluşturabilir ve takip edebilirsiniz.</p>
    </div>
</div>

<div class="row g-4">
    <!-- SOL: Kod Üretme Formu -->
    <div class="col-lg-4">
        <div class="card card-custom border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="mb-0 fw-bold text-primary"><i class="fas fa-plus-circle me-2"></i>Yeni Kodlar Üret</h6>
            </div>
            <div class="card-body p-4">
                <form action="generate_codes.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">HEDEF YAYIN</label>
                        <select name="urun_id" class="form-select input-theme" required>
                            <option value="">-- Yayın Seçiniz --</option>
                            <?php foreach($my_products as $p): ?>
                                <option value="<?php echo $p['id']; ?>"><?php echo escape_html($p['deneme_adi']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label small fw-bold text-muted">ADET</label>
                            <input type="number" name="adet" class="form-control input-theme" value="10" min="1" max="500">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label small fw-bold text-muted">UZUNLUK</label>
                            <input type="number" name="uzunluk" class="form-control input-theme" value="8" min="5" max="15">
                        </div>
                    </div>

                    <div class="alert alert-info border-0 rounded-4 small mb-4">
                        <i class="fas fa-info-circle me-2"></i> Üretilen kodlar tek kullanımlıktır ve anında listeye eklenir.
                    </div>

                    <button type="submit" class="btn btn-coral w-100 py-2 fw-bold shadow-sm">KODLARI OLUŞTUR</button>
                </form>
            </div>
        </div>

        <div class="card card-custom bg-dark text-white border-0 shadow-sm">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3 small"><i class="fas fa-lightbulb text-warning me-2"></i>Yazar İpucu</h6>
                <p class="small opacity-75 mb-0">Kodları buradan üretip, toplu liste olarak dışa aktarabilir veya tek tek kopyalayıp WhatsApp/Instagram üzerinden öğrencilerinize iletebilirsiniz.</p>
            </div>
        </div>
    </div>

    <!-- SAĞ: Liste ve Filtre -->
    <div class="col-lg-8">
        <div class="card card-custom border-0 shadow-sm overflow-hidden">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-0">
                <h6 class="mb-0 fw-bold">Mevcut Kodlarım</h6>
                <form method="GET" class="d-flex gap-2">
                    <select name="filter_id" class="form-select form-select-sm rounded-pill border-secondary-subtle" onchange="this.form.submit()">
                        <option value="">Tüm Yayınlar</option>
                        <?php foreach($my_products as $p): ?>
                            <option value="<?php echo $p['id']; ?>" <?php echo $filter_id == $p['id'] ? 'selected' : ''; ?>><?php echo $p['deneme_adi']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th class="ps-4">ERİŞİM KODU</th>
                                <th>YAYIN ADI</th>
                                <th>DURUM</th>
                                <th class="pe-4 text-end">KULLANAN</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($codes)): ?>
                                <tr><td colspan="4" class="text-center py-5 text-muted small">Henüz bir kod bulunmuyor.</td></tr>
                            <?php else: ?>
                                <?php foreach($codes as $c): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center gap-2">
                                            <code class="text-primary fw-bold bg-light px-2 py-1 rounded border"><?php echo $c['kod']; ?></code>
                                            <button class="btn btn-sm btn-link text-muted p-0" onclick="copyToClipboard('<?php echo $c['kod']; ?>', this)" title="Kopyala">
                                                <i class="far fa-copy"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <td><div class="small fw-medium text-dark"><?php echo escape_html($c['deneme_adi']); ?></div></td>
                                    <td>
                                        <?php if($c['kullanici_id']): ?>
                                            <span class="badge bg-danger-subtle text-danger rounded-pill px-3">Kullanıldı</span>
                                        <?php else: ?>
                                            <span class="badge bg-success-subtle text-success rounded-pill px-3">Müsait</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="pe-4 text-end">
                                        <?php if($c['kullanici_id']): ?>
                                            <div class="fw-bold small text-dark"><?php echo escape_html($c['kullanan_ogrenci']); ?></div>
                                            <div class="text-muted" style="font-size: 0.65rem;"><?php echo date('d.m.Y H:i', strtotime($c['kullanilma_tarihi'])); ?></div>
                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- Sayfalama -->
            <?php if($total_pages > 1): ?>
            <div class="card-footer bg-white border-0 py-3">
                <nav>
                    <ul class="pagination pagination-sm justify-content-center mb-0">
                        <?php for($i=1; $i<=$total_pages; $i++): ?>
                            <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                <a class="page-link shadow-none" href="?page=<?php echo $i; ?>&filter_id=<?php echo $filter_id; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function copyToClipboard(text, btn) {
    const el = document.createElement('textarea');
    el.value = text;
    document.body.appendChild(el);
    el.select();
    document.execCommand('copy');
    document.body.removeChild(el);
    
    const icon = btn.querySelector('i');
    icon.className = 'fas fa-check text-success';
    setTimeout(() => { icon.className = 'far fa-copy text-muted'; }, 2000);
}
</script>

<?php require_once __DIR__ . '/includes/author_footer.php'; ?>