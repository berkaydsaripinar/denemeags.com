<?php
// admin/manage_kodlar.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAdminLogin();
$page_title = "Kod Yönetimi";
$csrf_token = generate_admin_csrf_token();
include_once __DIR__ . '/../templates/admin_header.php';

$authorized_ids = getAuthorizedDenemeIds(); 
$active_tab = $_GET['tab'] ?? 'urun'; 

// --- DENEME LİSTESİ ---
$deneme_list_sql = "SELECT id, deneme_adi FROM denemeler WHERE aktif_mi = 1 ORDER BY deneme_adi ASC";
$deneme_list_params = [];
if ($authorized_ids !== null) { 
    if (empty($authorized_ids)) {
        $denemeler_for_form = [];
    } else {
        $in_clause = implode(',', array_fill(0, count($authorized_ids), '?'));
        $deneme_list_sql = "SELECT id, deneme_adi FROM denemeler WHERE aktif_mi = 1 AND id IN ($in_clause) ORDER BY deneme_adi ASC";
        $deneme_list_params = $authorized_ids;
    }
}

try {
    if (!isset($denemeler_for_form)) {
        $stmt_denemeler_list = $pdo->prepare($deneme_list_sql);
        $stmt_denemeler_list->execute($deneme_list_params);
        $denemeler_for_form = $stmt_denemeler_list->fetchAll();
    }
} catch (PDOException $e) {
    $denemeler_for_form = [];
}

// --- LİSTELEME SORGULARI ---
// Ürün Kodları
$limit = 50; 
$page_urun = filter_input(INPUT_GET, 'page_urun', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
$filter_deneme_id = filter_input(INPUT_GET, 'filter_deneme_id', FILTER_VALIDATE_INT);
$offset_urun = ($page_urun - 1) * $limit;

$where_clauses_urun = [];
$params_urun = [];

if ($filter_deneme_id) {
    $where_clauses_urun[] = "ek.urun_id = ?";
    $params_urun[] = $filter_deneme_id;
}
if ($authorized_ids !== null) {
    if (!empty($authorized_ids)) {
        $in_clause = implode(',', array_fill(0, count($authorized_ids), '?'));
        $where_clauses_urun[] = "ek.urun_id IN ($in_clause)";
        $params_urun = array_merge($params_urun, $authorized_ids);
    } else {
        $where_clauses_urun[] = "1=0"; 
    }
}
$where_sql_urun = !empty($where_clauses_urun) ? "WHERE " . implode(" AND ", $where_clauses_urun) : "";

try {
    $count_sql_urun = "SELECT COUNT(*) FROM erisim_kodlari ek " . $where_sql_urun;
    $stmt_total_urun = $pdo->prepare($count_sql_urun);
    $stmt_total_urun->execute($params_urun);
    $total_codes_urun = $stmt_total_urun->fetchColumn();
    $total_pages_urun = ceil($total_codes_urun / $limit);

    $list_sql_urun = "
        SELECT ek.id, ek.kod, ek.kullanici_id, ek.olusturulma_tarihi, ek.cok_kullanimlik,
               k.ad_soyad AS kullanici_adi, d.deneme_adi
        FROM erisim_kodlari ek
        LEFT JOIN kullanicilar k ON ek.kullanici_id = k.id
        LEFT JOIN denemeler d ON ek.urun_id = d.id
        " . $where_sql_urun . "
        ORDER BY ek.id DESC 
        LIMIT ? OFFSET ?
    ";
    $stmt_urun = $pdo->prepare($list_sql_urun);
    $params_urun_limit = array_merge($params_urun, [$limit, $offset_urun]);
    $stmt_urun->execute($params_urun_limit);
    $urun_kodlari = $stmt_urun->fetchAll();
} catch (PDOException $e) {
    $urun_kodlari = [];
}

// Kayıt Kodları
$kayit_kodlari = [];
$total_codes_kayit = 0;
$total_pages_kayit = 1;
$page_kayit = filter_input(INPUT_GET, 'page_kayit', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);

if (isSuperAdmin()) {
    $offset_kayit = ($page_kayit - 1) * $limit;
    try {
        $stmt_total_kayit = $pdo->query("SELECT COUNT(*) FROM kayit_kodlari");
        $total_codes_kayit = $stmt_total_kayit->fetchColumn();
        $total_pages_kayit = ceil($total_codes_kayit / $limit);

        $stmt_kayit = $pdo->prepare("
            SELECT kk.id, kk.kod, kk.kullanici_id, kk.olusturulma_tarihi, kk.cok_kullanimlik, k.ad_soyad AS kullanici_adi
            FROM kayit_kodlari kk
            LEFT JOIN kullanicilar k ON kk.kullanici_id = k.id
            ORDER BY kk.id DESC LIMIT ? OFFSET ?
        ");
        $stmt_kayit->execute([$limit, $offset_kayit]);
        $kayit_kodlari = $stmt_kayit->fetchAll();
    } catch (PDOException $e) {
        $kayit_kodlari = [];
    }
}
?>

<div class="admin-page-title">Kod Yönetimi</div>

<ul class="nav nav-tabs mb-4" id="codeTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link <?php echo $active_tab === 'urun' ? 'active' : ''; ?>" id="urun-tab" data-bs-toggle="tab" data-bs-target="#urun" type="button" role="tab">Ürün Erişim Kodları</button>
  </li>
  <?php if (isSuperAdmin()): ?>
  <li class="nav-item" role="presentation">
    <button class="nav-link <?php echo $active_tab === 'kayit' ? 'active' : ''; ?>" id="kayit-tab" data-bs-toggle="tab" data-bs-target="#kayit" type="button" role="tab">Üyelik Kayıt Kodları</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link <?php echo $active_tab === 'ozel' ? 'active' : ''; ?>" id="ozel-tab" data-bs-toggle="tab" data-bs-target="#ozel" type="button" role="tab">⭐ Özel / Standart Kod Ekle</button>
  </li>
  <?php endif; ?>
</ul>

<div class="tab-content" id="codeTabsContent">
    
    <!-- TAB 1: ÜRÜN KODLARI (Toplu Üretim) -->
    <div class="tab-pane fade <?php echo $active_tab === 'urun' ? 'show active' : ''; ?>" id="urun" role="tabpanel">
        <div class="card card-theme mb-4">
            <div class="card-header card-header-theme-light">Rastgele Ürün Kodu Üret (Toplu)</div>
            <div class="card-body">
                <form action="generate_codes.php" method="POST" class="row g-3 align-items-end">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="kod_turu" value="urun">
                    <input type="hidden" name="islem_tipi" value="toplu">
                    
                    <div class="col-md-4">
                        <label for="deneme_id_select" class="form-label">Deneme/Ürün Seçiniz</label>
                        <select name="deneme_id" id="deneme_id_select" class="form-select input-admin" required>
                            <option value="">-- Seçiniz --</option>
                            <?php foreach ($denemeler_for_form as $deneme_item): ?>
                                <option value="<?php echo $deneme_item['id']; ?>"><?php echo escape_html($deneme_item['deneme_adi']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="adet_urun" class="form-label">Adet</label>
                        <input type="number" id="adet_urun" name="adet" class="form-control input-admin" value="50" min="1" max="5000">
                    </div>
                    <div class="col-md-3">
                        <label for="uzunluk_urun" class="form-label">Kod Uzunluğu</label>
                        <input type="number" id="uzunluk_urun" name="uzunluk" class="form-control input-admin" value="8" min="5" max="15">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-theme-primary w-100">Kodları Üret</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Ürün Kodları Listesi -->
        <h5>Mevcut Ürün Kodları (Toplam: <?php echo $total_codes_urun; ?>)</h5>
        <div class="table-responsive">
            <table class="admin-table table table-striped">
                <thead><tr><th>ID</th><th>Kod</th><th>Tür</th><th>Ürün</th><th>Durum</th><th>Kullanan</th><th>Tarih</th></tr></thead>
                <tbody>
                    <?php foreach ($urun_kodlari as $kod): $kullanildi = !is_null($kod['kullanici_id']); ?>
                    <tr>
                        <td><?php echo $kod['id']; ?></td>
                        <td><code class="text-theme-primary"><?php echo escape_html($kod['kod']); ?></code></td>
                        <td><?php echo $kod['cok_kullanimlik'] ? '<span class="badge bg-info">Standart (Sınırsız)</span>' : '<span class="badge bg-secondary">Tek Seferlik</span>'; ?></td>
                        <td><?php echo escape_html($kod['deneme_adi']); ?></td>
                        <td>
                            <?php if ($kod['cok_kullanimlik']): ?>
                                <span class="badge bg-success">Aktif</span>
                            <?php else: ?>
                                <?php echo $kullanildi ? '<span class="badge bg-danger">Kullanıldı</span>' : '<span class="badge bg-success">Boşta</span>'; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($kod['cok_kullanimlik']): ?>
                                <small class="text-muted">Çoklu</small>
                            <?php else: ?>
                                <?php echo $kullanildi ? escape_html($kod['kullanici_adi']) : '-'; ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo format_tr_datetime($kod['olusturulma_tarihi'], 'd.m.Y H:i'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($total_pages_urun > 1): ?>
            <nav><ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $total_pages_urun; $i++): ?>
                <li class="page-item <?php echo ($i == $page_urun) ? 'active' : ''; ?>">
                    <a class="page-link" href="?tab=urun&page_urun=<?php echo $i; echo $filter_deneme_id ? '&filter_deneme_id='.$filter_deneme_id : ''; ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
            </ul></nav>
        <?php endif; ?>
    </div>

    <!-- TAB 2: KAYIT KODLARI (Sadece Superadmin) -->
    <?php if (isSuperAdmin()): ?>
    <div class="tab-pane fade <?php echo $active_tab === 'kayit' ? 'show active' : ''; ?>" id="kayit" role="tabpanel">
        <div class="card card-theme mb-4 border-danger">
            <div class="card-header bg-danger bg-opacity-10 text-danger"><strong>Rastgele Kayıt Kodu Üret (Toplu)</strong></div>
            <div class="card-body">
                <form action="generate_codes.php" method="POST" class="row g-3 align-items-end">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="kod_turu" value="kayit">
                    <input type="hidden" name="islem_tipi" value="toplu">
                    
                    <div class="col-md-5">
                        <label for="adet_kayit" class="form-label">Adet</label>
                        <input type="number" id="adet_kayit" name="adet" class="form-control input-admin" value="20" min="1" max="1000">
                    </div>
                    <div class="col-md-5">
                        <label for="uzunluk_kayit" class="form-label">Kod Uzunluğu</label>
                        <input type="number" id="uzunluk_kayit" name="uzunluk" class="form-control input-admin" value="10" min="6" max="20">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-danger w-100">Kodları Üret</button>
                    </div>
                </form>
            </div>
        </div>

        <h5>Mevcut Kayıt Kodları (Toplam: <?php echo $total_codes_kayit; ?>)</h5>
        <div class="table-responsive">
            <table class="admin-table table table-striped">
                <thead><tr><th>ID</th><th>Kayıt Kodu</th><th>Tür</th><th>Durum</th><th>Kullanan Üye</th><th>Tarih</th></tr></thead>
                <tbody>
                    <?php foreach ($kayit_kodlari as $kod): $kullanildi = !is_null($kod['kullanici_id']); ?>
                    <tr>
                        <td><?php echo $kod['id']; ?></td>
                        <td><code class="text-danger fw-bold"><?php echo escape_html($kod['kod']); ?></code></td>
                        <td><?php echo $kod['cok_kullanimlik'] ? '<span class="badge bg-info">Standart (Sınırsız)</span>' : '<span class="badge bg-secondary">Tek Seferlik</span>'; ?></td>
                        <td>
                             <?php if ($kod['cok_kullanimlik']): ?>
                                <span class="badge bg-success">Aktif</span>
                            <?php else: ?>
                                <?php echo $kullanildi ? '<span class="badge bg-danger">Kullanıldı</span>' : '<span class="badge bg-success">Boşta</span>'; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                             <?php if ($kod['cok_kullanimlik']): ?>
                                <small class="text-muted">Çoklu</small>
                            <?php else: ?>
                                <?php echo $kullanildi ? escape_html($kod['kullanici_adi']) : '-'; ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo format_tr_datetime($kod['olusturulma_tarihi'], 'd.m.Y H:i'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($total_pages_kayit > 1): ?>
            <nav><ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $total_pages_kayit; $i++): ?>
                <li class="page-item <?php echo ($i == $page_kayit) ? 'active' : ''; ?>">
                    <a class="page-link" href="?tab=kayit&page_kayit=<?php echo $i; ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
            </ul></nav>
        <?php endif; ?>
    </div>

    <!-- TAB 3: ÖZEL / STANDART KOD EKLE (YENİ) -->
    <div class="tab-pane fade <?php echo $active_tab === 'ozel' ? 'show active' : ''; ?>" id="ozel" role="tabpanel">
        <div class="card card-theme mb-4 border-warning">
            <div class="card-header bg-warning bg-opacity-10 text-dark-gold">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-star-fill me-1" viewBox="0 0 16 16"><path d="M3.612 15.443c-.386.198-.824-.149-.746-.592l.83-4.73L.173 6.765c-.329-.314-.158-.888.283-.95l4.898-.696L7.538.792c.197-.39.73-.39.927 0l2.184 4.327 4.898.696c.441.062.612.636.282.95l-3.522 3.356.83 4.73c.078.443-.36.79-.746.592L8 13.187l-4.389 2.256z"/></svg>
                <strong>Özel Manuel Kod Ekle</strong>
            </div>
            <div class="card-body">
                <p>Buradan belirlediğiniz özel bir kelimeyi (örn: <code>DENEME2025</code>) kod olarak ekleyebilirsiniz. Bu kod tek seferlik veya herkesin kullanabileceği standart bir kod olabilir.</p>
                
                <form action="generate_codes.php" method="POST" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="islem_tipi" value="manuel">
                    
                    <div class="col-md-6">
                        <label for="ozel_kod" class="form-label fw-bold">Özel Kodunuz</label>
                        <input type="text" id="ozel_kod" name="ozel_kod" class="form-control input-admin text-uppercase" placeholder="Örn: HOŞGELDİN" required>
                        <div class="form-text">Boşluk içermemeli, Türkçe karakter kullanılabilir. Otomatik büyük harfe çevrilir.</div>
                    </div>

                    <div class="col-md-6">
                        <label for="kod_turu_ozel" class="form-label fw-bold">Kodun Amacı</label>
                        <select name="kod_turu" id="kod_turu_ozel" class="form-select input-admin" required onchange="toggleDenemeSelect()">
                            <option value="urun">Bir Ürüne/Denemeye Erişim İçin</option>
                            <option value="kayit">Siteye Üye Olmak İçin</option>
                        </select>
                    </div>

                    <div class="col-md-12" id="deneme_select_container">
                        <label for="deneme_id_ozel" class="form-label">İlgili Ürün/Deneme</label>
                        <select name="deneme_id" id="deneme_id_ozel" class="form-select input-admin">
                            <option value="">-- Seçiniz --</option>
                            <?php foreach ($denemeler_for_form as $deneme_item): ?>
                                <option value="<?php echo $deneme_item['id']; ?>"><?php echo escape_html($deneme_item['deneme_adi']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="cok_kullanimlik" name="cok_kullanimlik" value="1">
                            <label class="form-check-label fw-bold" for="cok_kullanimlik">Standart (Çok Kullanımlık) Kod Yap</label>
                        </div>
                        <div class="form-text text-warning">
                            <i class="bi bi-exclamation-triangle"></i> İşaretlerseniz, bu kod <strong>sınırsız sayıda kişi</strong> tarafından kullanılabilir (örn: Ücretsiz dağıtım için).<br>
                            İşaretlemezseniz, kod sadece <strong>tek bir kişi</strong> tarafından bir kez kullanılabilir.
                        </div>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-warning w-100 text-dark fw-bold">Özel Kodu Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function toggleDenemeSelect() {
    const tip = document.getElementById('kod_turu_ozel').value;
    const container = document.getElementById('deneme_select_container');
    if (tip === 'kayit') {
        container.style.display = 'none';
    } else {
        container.style.display = 'block';
    }
}
// Sayfa yüklendiğinde çalıştır
toggleDenemeSelect();
</script>

<?php
include_once __DIR__ . '/../templates/admin_footer.php';
?>