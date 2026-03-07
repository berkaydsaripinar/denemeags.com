<?php
// Hata raporlamayı açalım
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Dosya yollarını dahil et
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_functions.php';

/**
 * Bağlantı Değişkeni Kontrolü
 * db_connect.php içinde bağlantı değişkeni $pdo olarak tanımlanmış.
 * Global scope üzerinden erişimi garanti altına alıyoruz.
 */
global $pdo;

if (!isset($pdo)) {
    die("Hata: Veritabanı bağlantısı ($pdo) bulunamadı. Lütfen 'includes/db_connect.php' dosyasını kontrol edin.");
}

// Admin oturum kontrolü
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

$message = "";
$messageType = "";

// Kod Silme İşlemi
if (isset($_GET['delete_id'])) {
    $deleteId = (int)$_GET['delete_id'];
    $stmt = $pdo->prepare("DELETE FROM erisim_kodlari WHERE id = ? AND cok_kullanimlik = 1");
    if ($stmt->execute([$deleteId])) {
        $message = "Standart kod başarıyla silindi.";
        $messageType = "success";
    }
}

// Yeni Standart Kod Üretme İşlemi
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_standard'])) {
    $customCode = trim($_POST['custom_code']);
    $denemeId = (int)$_POST['deneme_id'];

    if (empty($customCode) || $denemeId <= 0) {
        $message = "Lütfen tüm alanları doldurun.";
        $messageType = "danger";
    } else {
        // Kodun daha önce kullanılıp kullanılmadığını kontrol et
        $check = $pdo->prepare("SELECT id FROM erisim_kodlari WHERE kod = ?");
        $check->execute([$customCode]);
        
        if ($check->fetch()) {
            $message = "Bu kod zaten mevcut, lütfen farklı bir kod belirleyin.";
            $messageType = "danger";
        } else {
            // urun_id sütununa denemeId yazıyoruz
            $stmt = $pdo->prepare("INSERT INTO erisim_kodlari (kod, kod_turu, cok_kullanimlik, urun_id, olusturulma_tarihi) VALUES (?, 'urun', 1, ?, NOW())");
            if ($stmt->execute([$customCode, $denemeId])) {
                $message = "Standart kod ('$customCode') başarıyla oluşturuldu.";
                $messageType = "success";
            } else {
                $message = "Kod oluşturulurken bir hata oluştu.";
                $messageType = "danger";
            }
        }
    }
}

// Aktif Denemeleri Çek
try {
    $denemeler = $pdo->query("SELECT id, deneme_adi FROM denemeler WHERE aktif_mi = 1 ORDER BY deneme_adi ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $denemeler = [];
    $message = "Veri çekme hatası: " . $e->getMessage();
    $messageType = "danger";
}

// Mevcut Standart Kodları Çek
try {
    $standardCodes = $pdo->query("
        SELECT ek.*, d.deneme_adi 
        FROM erisim_kodlari ek 
        LEFT JOIN denemeler d ON ek.urun_id = d.id 
        WHERE ek.cok_kullanimlik = 1 
        ORDER BY ek.olusturulma_tarihi DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $standardCodes = [];
}

include '../templates/admin_header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <h2 class="mb-4">Standart (Genel) Erişim Kodları</h2>
            
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                    <?= $message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Kod Üretme Formu -->
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">Yeni Standart Kod Oluştur</h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label">Erişim Kodu (Örn: BEDAVA2024)</label>
                            <input type="text" name="custom_code" class="form-control" placeholder="Büyük harf ve rakam önerilir" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">İlişkili Deneme/Ürün</label>
                            <select name="deneme_id" class="form-select" required>
                                <option value="">Deneme Seçin...</option>
                                <?php foreach ($denemeler as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['deneme_adi']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" name="generate_standard" class="btn btn-success w-100">
                                <i class="fas fa-plus-circle me-2"></i>Kodu Oluştur
                            </button>
                        </div>
                    </form>
                    <div class="mt-2 text-muted small">
                        * Bu kodlar "Çok Kullanımlık" olarak işaretlenir. Herkes bu kodu kullanarak ürünü hesabına ekleyebilir.
                    </div>
                </div>
            </div>

            <!-- Mevcut Kodlar Listesi -->
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white">
                    <h5 class="card-title mb-0">Aktif Standart Kodlar</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Kod</th>
                                    <th>Tanımlı Ürün</th>
                                    <th>Oluşturulma Tarihi</th>
                                    <th class="text-end">İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($standardCodes)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">Henüz standart kod oluşturulmamış.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($standardCodes as $code): ?>
                                        <tr>
                                            <td><strong class="text-primary"><?= htmlspecialchars($code['kod']) ?></strong></td>
                                            <td><?= htmlspecialchars($code['deneme_adi']) ?></td>
                                            <td><?= date('d.m.Y H:i', strtotime($code['olusturulma_tarihi'])) ?></td>
                                            <td class="text-end">
                                                <a href="?delete_id=<?= $code['id'] ?>" 
                                                   class="btn btn-sm btn-outline-danger" 
                                                   onclick="return confirm('Bu kodu silmek istediğinize emin misiniz? Artık kimse bu kodla ürünü aktif edemez.')">
                                                    <i class="fas fa-trash"></i> Sil
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include '../templates/admin_footer.php'; ?>