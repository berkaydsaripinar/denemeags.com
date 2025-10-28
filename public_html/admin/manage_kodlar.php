<?php
// admin/manage_kodlar.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAdminLogin();
$page_title = "Giriş Kodlarını Yönet";
$csrf_token = generate_admin_csrf_token();
include_once __DIR__ . '/../templates/admin_header.php';

$authorized_ids = getAuthorizedDenemeIds(); // Giriş yapan adminin yetkili olduğu deneme ID'lerini al

// Düşen menüler için deneme listesini role göre filtrele
$deneme_list_sql = "SELECT id, deneme_adi FROM denemeler WHERE aktif_mi = 1 ORDER BY deneme_adi ASC";
$deneme_list_params = [];
if ($authorized_ids !== null) { // Eğer superadmin değilse
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
    set_admin_flash_message('error', "Denemeler yüklenirken hata: " . $e->getMessage());
    $denemeler_for_form = [];
}

// Kodları listeleme
$limit = 100; 
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
$filter_deneme_id = filter_input(INPUT_GET, 'filter_deneme_id', FILTER_VALIDATE_INT);
$offset = ($page - 1) * $limit;

if ($filter_deneme_id && $authorized_ids !== null && !in_array($filter_deneme_id, $authorized_ids)) {
    set_admin_flash_message('error', 'Bu denemeyi filtreleme yetkiniz yok.');
    $filter_deneme_id = null;
}

$where_clauses = [];
$params = [];

if ($filter_deneme_id) {
    $where_clauses[] = "dek.deneme_id = ?";
    $params[] = $filter_deneme_id;
}

if ($authorized_ids !== null) {
    if (empty($authorized_ids)) {
        $deneme_kodlari = [];
    } else {
        $in_clause = implode(',', array_fill(0, count($authorized_ids), '?'));
        $where_clauses[] = "dek.deneme_id IN ($in_clause)";
        $params = array_merge($params, $authorized_ids);
    }
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

try {
    if (!isset($deneme_kodlari)) {
        $count_sql = "SELECT COUNT(*) FROM deneme_erisim_kodlari dek " . $where_sql;
        $stmt_total = $pdo->prepare($count_sql);
        $stmt_total->execute($params);
        $total_codes = $stmt_total->fetchColumn();
        $total_pages = ceil($total_codes / $limit);

        // DÜZELTME: LIMIT ve OFFSET için de konumsal (?) parametreler kullanıldı.
        $list_sql = "
            SELECT dek.id, dek.kod, dek.kullanici_id, dek.olusturulma_tarihi, 
                   k.ad_soyad AS kullanici_adi, d.deneme_adi
            FROM deneme_erisim_kodlari dek
            LEFT JOIN kullanicilar k ON dek.kullanici_id = k.id
            LEFT JOIN denemeler d ON dek.deneme_id = d.id
            " . $where_sql . "
            ORDER BY dek.id DESC 
            LIMIT ? OFFSET ?
        ";
        
        $stmt = $pdo->prepare($list_sql);
        
        // DÜZELTME: LIMIT ve OFFSET parametreleri ana parametre dizisine eklendi.
        $params_with_limit = $params;
        $params_with_limit[] = $limit;
        $params_with_limit[] = $offset;

        // DÜZELTME: Tüm parametreler execute() ile tek seferde gönderildi.
        $stmt->execute($params_with_limit);
        $deneme_kodlari = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    set_admin_flash_message('error', "Kodlar listelenirken hata oluştu: " . $e->getMessage());
    $deneme_kodlari = [];
    $total_codes = 0;
    $total_pages = 1;
}
?>

<div class="admin-page-title">Giriş Kodu Yönetimi</div>

<form action="generate_codes.php" method="POST" class="form-inline" style="padding:15px; background-color:#f9f9f9; border-radius:5px; margin-bottom:20px;">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <div class="form-group">
        <label for="deneme_id_select">Hangi Deneme İçin Kod Üretilecek?</label>
        <select name="deneme_id" id="deneme_id_select" class="input-admin form-select" required>
            <option value="">-- Deneme Seçiniz --</option>
            <?php foreach ($denemeler_for_form as $deneme_item_form): ?>
                <option value="<?php echo $deneme_item_form['id']; ?>"><?php echo escape_html($deneme_item_form['deneme_adi']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="adet">Yeni Kod Adedi:</label>
        <input type="number" id="adet" name="adet" class="input-admin form-control" value="100" min="1" max="1000" style="width: 100px;">
    </div>
    <div class="form-group">
        <label for="uzunluk">Kod Uzunluğu:</label>
        <input type="number" id="uzunluk" name="uzunluk" class="input-admin form-control" value="8" min="5" max="15" style="width: 80px;">
    </div>
    <button type="submit" class="btn-admin green">Yeni Kodlar Üret ve Ekle</button>
</form>

<form method="GET" action="manage_kodlar.php" class="form-inline">
    <div class="form-group">
        <label for="filter_deneme_id_select">Denemeye Göre Filtrele:</label>
        <select name="filter_deneme_id" id="filter_deneme_id_select" class="input-admin form-select" onchange="this.form.submit()">
            <option value="">-- Tüm Denemeler --</option>
            <?php foreach ($denemeler_for_form as $deneme_item_filter): ?>
                <option value="<?php echo $deneme_item_filter['id']; ?>" <?php echo ($filter_deneme_id == $deneme_item_filter['id']) ? 'selected' : ''; ?>>
                    <?php echo escape_html($deneme_item_filter['deneme_adi']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php if ($filter_deneme_id): ?>
        <a href="manage_kodlar.php" class="btn-admin yellow btn-sm" style="align-self: center;">Filtreyi Temizle</a>
    <?php endif; ?>
</form>

<p class="mb-3">Toplam <?php echo $total_codes; ?> adet kod bulunmaktadır <?php echo $filter_deneme_id ? "(filtrelenmiş)" : ""; ?>.</p>

<?php if (empty($deneme_kodlari)): ?>
    <p class="message-box info">Yönetme yetkiniz olan bir deneme için kod bulunmamaktadır.</p>
<?php else: ?>
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Kod</th>
                <th>Ait Olduğu Deneme</th>
                <th>Kullanıldı Mı?</th>
                <th>Kullanan Kişi</th>
                <th>Oluşturulma Tarihi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($deneme_kodlari as $kod_item): ?>
            <?php $kullanildi = !is_null($kod_item['kullanici_id']); ?>
            <tr>
                <td><?php echo escape_html($kod_item['id']); ?></td>
                <td><strong><?php echo escape_html($kod_item['kod']); ?></strong></td>
                <td><?php echo escape_html($kod_item['deneme_adi']); ?></td>
                <td><?php echo $kullanildi ? '<span class="badge bg-danger">Evet</span>' : '<span class="badge bg-success">Hayır</span>'; ?></td>
                <td><?php echo $kullanildi ? escape_html($kod_item['kullanici_adi']) : '-'; ?></td>
                <td><?php echo format_tr_datetime($kod_item['olusturulma_tarihi'], 'd M Y H:i'); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Sayfalama -->
    <?php if ($total_pages > 1): ?>
    <nav class="mt-3">
        <ul class="pagination">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $i; echo $filter_deneme_id ? '&filter_deneme_id='.$filter_deneme_id : ''; ?>"><?php echo $i; ?></a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>

<?php endif; ?>

<?php
include_once __DIR__ . '/../templates/admin_footer.php';
?>
