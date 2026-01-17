<?php
// admin/homepage_settings.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAdminLogin();

if (!isSuperAdmin()) {
    set_admin_flash_message('error', 'Bu sayfaya erişim yetkiniz yok.');
    redirect('dashboard.php');
}

$page_title = 'Anasayfa Sidebar Ayarları';
$csrf_token = generate_admin_csrf_token();

$settings_meta = [
    'HOMEPAGE_SIDEBAR_TITLE' => [
        'label' => 'Sidebar Başlığı',
        'default' => 'Mağaza Kısa Yolu',
        'description' => 'Sidebar üzerinde görünen ana başlık.'
    ],
    'HOMEPAGE_SIDEBAR_DESC' => [
        'label' => 'Sidebar Açıklaması',
        'default' => 'DenemeAGS mağazasında tüm yayınlar dijital, hızlı ve erişilebilir. Satın alım sonrası anında kütüphanenizde.',
        'description' => 'Kısa açıklama metni (1-2 cümle önerilir).'
    ],
    'HOMEPAGE_SIDEBAR_LIST' => [
        'label' => 'Sidebar Maddeleri',
        'default' => "Anında PDF erişimi\nKargo bekleme yok\nYazara adil pay\nÇevre dostu içerik",
        'description' => 'Her satıra bir madde yazın.'
    ],
    'HOMEPAGE_SIDEBAR_CTA_LABEL' => [
        'label' => 'CTA Buton Yazısı',
        'default' => 'Mağazaya Git',
        'description' => 'Buton üzerinde görünen metin.'
    ],
    'HOMEPAGE_SIDEBAR_CTA_URL' => [
        'label' => 'CTA Buton Linki',
        'default' => 'store.php',
        'description' => 'Mağaza bağlantısı veya yönlendirmek istediğiniz sayfa.'
    ],
    'HOMEPAGE_SIDEBAR_NOTE' => [
        'label' => 'Mobil Notu',
        'default' => 'Mobilde bile mağaza bir tık uzaklıkta.',
        'description' => 'Mobil mağaza hatırlatıcı metni.'
    ]
];

$settings = [];
$keys = array_keys($settings_meta);
$placeholders = implode(',', array_fill(0, count($keys), '?'));
$stmt = $pdo->prepare("SELECT ayar_adi, ayar_degeri FROM sistem_ayarlari WHERE ayar_adi IN ($placeholders)");
$stmt->execute($keys);
$stored_settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

foreach ($settings_meta as $key => $meta) {
    $settings[$key] = $stored_settings[$key] ?? $meta['default'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_admin_csrf_token($_POST['csrf_token'] ?? '')) {
        set_admin_flash_message('error', 'Güvenlik hatası.');
    } else {
        $payload = [
            'HOMEPAGE_SIDEBAR_TITLE' => trim($_POST['sidebar_title'] ?? ''),
            'HOMEPAGE_SIDEBAR_DESC' => trim($_POST['sidebar_desc'] ?? ''),
            'HOMEPAGE_SIDEBAR_LIST' => trim($_POST['sidebar_list'] ?? ''),
            'HOMEPAGE_SIDEBAR_CTA_LABEL' => trim($_POST['sidebar_cta_label'] ?? ''),
            'HOMEPAGE_SIDEBAR_CTA_URL' => trim($_POST['sidebar_cta_url'] ?? ''),
            'HOMEPAGE_SIDEBAR_NOTE' => trim($_POST['sidebar_note'] ?? '')
        ];

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                "INSERT INTO sistem_ayarlari (ayar_adi, ayar_degeri, aciklama)
                 VALUES (:ayar_adi, :ayar_degeri, :aciklama)
                 ON DUPLICATE KEY UPDATE ayar_degeri = VALUES(ayar_degeri), aciklama = VALUES(aciklama)"
            );

            foreach ($payload as $key => $value) {
                $meta = $settings_meta[$key];
                $stmt->execute([
                    'ayar_adi' => $key,
                    'ayar_degeri' => $value !== '' ? $value : $meta['default'],
                    'aciklama' => $meta['description']
                ]);
            }

            $pdo->commit();
            set_admin_flash_message('success', 'Anasayfa sidebar ayarları güncellendi.');
            redirect('homepage_settings.php');
        } catch (Exception $e) {
            $pdo->rollBack();
            set_admin_flash_message('error', 'Hata: ' . $e->getMessage());
        }
    }
}

include_once __DIR__ . '/../templates/admin_header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden fade-in">
            <div class="card-header bg-primary text-white py-4 px-4 border-0">
                <div class="d-flex align-items-center">
                    <div class="bg-white bg-opacity-20 p-3 rounded-3 me-3">
                        <i class="fas fa-columns fa-2x"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0">Anasayfa Sidebar Yönetimi</h5>
                        <p class="mb-0 small opacity-75">Mağaza hatırlatıcı metinleri ve CTA yönetimi</p>
                    </div>
                </div>
            </div>

            <div class="card-body p-5">
                <form action="homepage_settings.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <div class="mb-4">
                        <label class="form-label fw-bold text-dark mb-1"><?php echo $settings_meta['HOMEPAGE_SIDEBAR_TITLE']['label']; ?></label>
                        <input type="text" name="sidebar_title" class="form-control" value="<?php echo escape_html($settings['HOMEPAGE_SIDEBAR_TITLE']); ?>" required>
                        <div class="form-text small"><?php echo $settings_meta['HOMEPAGE_SIDEBAR_TITLE']['description']; ?></div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-dark mb-1"><?php echo $settings_meta['HOMEPAGE_SIDEBAR_DESC']['label']; ?></label>
                        <textarea name="sidebar_desc" class="form-control" rows="3" required><?php echo escape_html($settings['HOMEPAGE_SIDEBAR_DESC']); ?></textarea>
                        <div class="form-text small"><?php echo $settings_meta['HOMEPAGE_SIDEBAR_DESC']['description']; ?></div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-dark mb-1"><?php echo $settings_meta['HOMEPAGE_SIDEBAR_LIST']['label']; ?></label>
                        <textarea name="sidebar_list" class="form-control" rows="4" required><?php echo escape_html($settings['HOMEPAGE_SIDEBAR_LIST']); ?></textarea>
                        <div class="form-text small"><?php echo $settings_meta['HOMEPAGE_SIDEBAR_LIST']['description']; ?></div>
                    </div>

                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark mb-1"><?php echo $settings_meta['HOMEPAGE_SIDEBAR_CTA_LABEL']['label']; ?></label>
                            <input type="text" name="sidebar_cta_label" class="form-control" value="<?php echo escape_html($settings['HOMEPAGE_SIDEBAR_CTA_LABEL']); ?>" required>
                            <div class="form-text small"><?php echo $settings_meta['HOMEPAGE_SIDEBAR_CTA_LABEL']['description']; ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark mb-1"><?php echo $settings_meta['HOMEPAGE_SIDEBAR_CTA_URL']['label']; ?></label>
                            <input type="text" name="sidebar_cta_url" class="form-control" value="<?php echo escape_html($settings['HOMEPAGE_SIDEBAR_CTA_URL']); ?>" required>
                            <div class="form-text small"><?php echo $settings_meta['HOMEPAGE_SIDEBAR_CTA_URL']['description']; ?></div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="form-label fw-bold text-dark mb-1"><?php echo $settings_meta['HOMEPAGE_SIDEBAR_NOTE']['label']; ?></label>
                        <input type="text" name="sidebar_note" class="form-control" value="<?php echo escape_html($settings['HOMEPAGE_SIDEBAR_NOTE']); ?>" required>
                        <div class="form-text small"><?php echo $settings_meta['HOMEPAGE_SIDEBAR_NOTE']['description']; ?></div>
                    </div>

                    <div class="alert alert-info border-0 rounded-4 small mt-4">
                        <i class="fas fa-info-circle me-2"></i>
                        Sidebar içerikleri anında anasayfada güncellenir.
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-theme-primary btn-lg shadow py-3">
                            <i class="fas fa-save me-2"></i> Değişiklikleri Kaydet
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../templates/admin_footer.php'; ?>
