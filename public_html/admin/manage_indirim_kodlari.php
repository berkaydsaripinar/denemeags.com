<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAdminLogin();
if (!isSuperAdmin()) {
    set_admin_flash_message('error', 'Bu sayfa için Süper Admin yetkisi gerekir.');
    redirect('admin/dashboard.php');
}

$page_title = 'İndirim Kodları & Influencer';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_admin_csrf_token($_POST['csrf_token'] ?? '')) {
        set_admin_flash_message('error', 'CSRF doğrulaması başarısız.');
        redirect('admin/manage_indirim_kodlari.php');
    }

    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'create_code') {
            $kod = strtoupper(trim((string) ($_POST['kod'] ?? '')));
            $aciklama = trim((string) ($_POST['aciklama'] ?? ''));
            $tip = (string) ($_POST['indirim_tipi'] ?? 'percent');
            $deger = (float) ($_POST['indirim_degeri'] ?? 0);
            $influencerId = filter_input(INPUT_POST, 'influencer_id', FILTER_VALIDATE_INT) ?: null;
            $komisyon = (float) ($_POST['influencer_komisyon_orani'] ?? 0);
            $baslangic = trim((string) ($_POST['baslangic_tarihi'] ?? '')) ?: null;
            $bitis = trim((string) ($_POST['bitis_tarihi'] ?? '')) ?: null;
            if ($baslangic !== null) { $baslangic = str_replace('T', ' ', $baslangic) . ':00'; }
            if ($bitis !== null) { $bitis = str_replace('T', ' ', $bitis) . ':00'; }
            $maxKullanim = filter_input(INPUT_POST, 'max_kullanim', FILTER_VALIDATE_INT);
            $maxKullanim = $maxKullanim && $maxKullanim > 0 ? $maxKullanim : null;
            $aktifMi = isset($_POST['aktif_mi']) ? 1 : 0;

            if ($kod === '' || !in_array($tip, ['percent', 'fixed'], true) || $deger <= 0) {
                throw new RuntimeException('Kod, tip ve indirim değeri zorunludur.');
            }
            if ($tip === 'percent' && $deger > 100) {
                throw new RuntimeException('Yüzde indirim 100\'ü aşamaz.');
            }

            $stmt = $pdo->prepare('INSERT INTO indirim_kodlari (kod, aciklama, indirim_tipi, indirim_degeri, influencer_id, influencer_komisyon_orani, baslangic_tarihi, bitis_tarihi, max_kullanim, aktif_mi, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
            $stmt->execute([$kod, $aciklama, $tip, $deger, $influencerId, $komisyon, $baslangic, $bitis, $maxKullanim, $aktifMi]);
            set_admin_flash_message('success', 'İndirim kodu oluşturuldu.');
        } elseif ($action === 'toggle_code') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
            if ($id <= 0) {
                throw new RuntimeException('Geçersiz kod ID.');
            }
            $pdo->prepare('UPDATE indirim_kodlari SET aktif_mi = IF(aktif_mi = 1, 0, 1), updated_at = NOW() WHERE id = ?')->execute([$id]);
            set_admin_flash_message('success', 'Kod durumu güncellendi.');
        } elseif ($action === 'create_influencer') {
            $adSoyad = trim((string) ($_POST['ad_soyad'] ?? ''));
            $kullaniciAdi = strtolower(trim((string) ($_POST['kullanici_adi'] ?? '')));
            $email = trim((string) ($_POST['email'] ?? ''));
            $telefon = trim((string) ($_POST['telefon'] ?? ''));
            $sifre = (string) ($_POST['sifre'] ?? '');
            $aktifMi = isset($_POST['infl_aktif_mi']) ? 1 : 0;

            if ($adSoyad === '' || $kullaniciAdi === '' || $email === '' || $sifre === '') {
                throw new RuntimeException('İnfluencer için ad, kullanıcı adı, email ve şifre zorunludur.');
            }

            $hash = password_hash($sifre, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO influencers (ad_soyad, kullanici_adi, email, telefon, sifre_hash, aktif_mi, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())');
            $stmt->execute([$adSoyad, $kullaniciAdi, $email, $telefon, $hash, $aktifMi]);
            set_admin_flash_message('success', 'Influencer hesabı oluşturuldu.');
        }
    } catch (Throwable $e) {
        set_admin_flash_message('error', 'İşlem hatası: ' . $e->getMessage());
    }

    redirect('admin/manage_indirim_kodlari.php');
}

$influencers = [];
$codes = [];
$usage = [];

try {
    $influencers = $pdo->query('SELECT * FROM influencers ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    set_admin_flash_message('error', 'Influencer tablosu bulunamadı. Migration çalıştırın.');
}

try {
    $codes = $pdo->query("
        SELECT k.*, i.ad_soyad AS influencer_adi
        FROM indirim_kodlari k
        LEFT JOIN influencers i ON i.id = k.influencer_id
        ORDER BY k.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
}

try {
    $usage = $pdo->query("
        SELECT k.kod, COUNT(u.id) AS kullanim, COALESCE(SUM(u.influencer_komisyon_tutari), 0) AS influencer_toplam
        FROM indirim_kodlari k
        LEFT JOIN indirim_kodu_kullanimlari u ON u.kod_id = k.id
        GROUP BY k.id
        ORDER BY kullanim DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
}

include_once __DIR__ . '/../templates/admin_header.php';
?>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white border-0 py-3"><h6 class="mb-0 fw-bold">Yeni İndirim Kodu</h6></div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_admin_csrf_token(); ?>">
                    <input type="hidden" name="action" value="create_code">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Kod</label>
                            <input type="text" name="kod" class="form-control" placeholder="INFLUENCER5" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">İndirim Tipi</label>
                            <select name="indirim_tipi" class="form-select">
                                <option value="percent">Yüzde (%)</option>
                                <option value="fixed">Sabit Tutar (TL)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">İndirim Değeri</label>
                            <input type="number" step="0.01" name="indirim_degeri" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Influencer</label>
                            <select name="influencer_id" class="form-select">
                                <option value="">Yok</option>
                                <?php foreach ($influencers as $i): ?>
                                    <option value="<?php echo (int) $i['id']; ?>"><?php echo escape_html($i['ad_soyad']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Influencer Payı (%)</label>
                            <input type="number" step="0.01" name="influencer_komisyon_orani" class="form-control" value="5">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Max Kullanım</label>
                            <input type="number" name="max_kullanim" class="form-control" placeholder="Boş = sınırsız">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Başlangıç</label>
                            <input type="datetime-local" name="baslangic_tarihi" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Bitiş</label>
                            <input type="datetime-local" name="bitis_tarihi" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Açıklama</label>
                            <input type="text" name="aciklama" class="form-control">
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input type="checkbox" class="form-check-input" name="aktif_mi" checked>
                                <label class="form-check-label">Aktif</label>
                            </div>
                        </div>
                        <div class="col-12"><button type="submit" class="btn btn-primary w-100">Kodu Oluştur</button></div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white border-0 py-3"><h6 class="mb-0 fw-bold">Yeni Influencer Hesabı</h6></div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_admin_csrf_token(); ?>">
                    <input type="hidden" name="action" value="create_influencer">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label small fw-bold">Ad Soyad</label><input type="text" name="ad_soyad" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label small fw-bold">Kullanıcı Adı</label><input type="text" name="kullanici_adi" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label small fw-bold">E-posta</label><input type="email" name="email" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label small fw-bold">Telefon</label><input type="text" name="telefon" class="form-control"></div>
                        <div class="col-12"><label class="form-label small fw-bold">Şifre</label><input type="text" name="sifre" class="form-control" required></div>
                        <div class="col-12">
                            <div class="form-check form-switch"><input type="checkbox" class="form-check-input" name="infl_aktif_mi" checked><label class="form-check-label">Aktif</label></div>
                        </div>
                        <div class="col-12"><button type="submit" class="btn btn-success w-100">Influencer Oluştur</button></div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 mt-4">
    <div class="card-header bg-white border-0 py-3"><h6 class="mb-0 fw-bold">Kodlar</h6></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr><th class="ps-3">Kod</th><th>İndirim</th><th>Influencer</th><th>Kullanım</th><th>Durum</th><th class="pe-3 text-end">İşlem</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($codes)): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">Kod bulunamadı.</td></tr>
                    <?php else: ?>
                        <?php
                            $usageMap = [];
                            foreach ($usage as $u) { $usageMap[$u['kod']] = $u; }
                        ?>
                        <?php foreach ($codes as $c): ?>
                            <?php $u = $usageMap[$c['kod']] ?? ['kullanim' => 0, 'influencer_toplam' => 0]; ?>
                            <tr>
                                <td class="ps-3"><code><?php echo escape_html($c['kod']); ?></code></td>
                                <td><?php echo $c['indirim_tipi'] === 'percent' ? '%' . number_format((float) $c['indirim_degeri'], 2) : number_format((float) $c['indirim_degeri'], 2) . ' TL'; ?></td>
                                <td><?php echo escape_html($c['influencer_adi'] ?? '-'); ?><div class="small text-muted">Pay: %<?php echo number_format((float) $c['influencer_komisyon_orani'], 2); ?></div></td>
                                <td><?php echo (int) $u['kullanim']; ?><div class="small text-success">Komisyon: <?php echo number_format((float) $u['influencer_toplam'], 2); ?> ₺</div></td>
                                <td><?php echo (int) $c['aktif_mi'] === 1 ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-secondary">Pasif</span>'; ?></td>
                                <td class="pe-3 text-end">
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_admin_csrf_token(); ?>">
                                        <input type="hidden" name="action" value="toggle_code">
                                        <input type="hidden" name="id" value="<?php echo (int) $c['id']; ?>">
                                        <button class="btn btn-sm btn-outline-primary" type="submit">Aktif/Pasif</button>
                                    </form>
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
