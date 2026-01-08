<?php
/**
 * Ürün Bazlı Kod Kullanım İstatistikleri Raporu
 * Bu dosya, ürünler (denemeler) ve bu ürünlere ait erişim kodlarının (erisim_kodlari) 
 * kullanım durumlarını 'kullanilma_tarihi' kolonunun NULL durumuna göre raporlar.
 */

// 1. HATA RAPORLAMA VE GÜVENLİK
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- VERİTABANI VE TABLO YAPILANDIRMASI ---
$TABLE_PRODUCTS     = 'denemeler';          // Ürün tablosu adı
$TABLE_CODES        = 'erisim_kodlari';     // Erişim kodları tablosu adı
$COL_PROD_ID        = 'id';                 // Ürün tablosu birincil anahtarı
$COL_PROD_NAME      = 'deneme_adi';         // Ürün adı kolonu
$COL_CODE_PROD_ID   = 'deneme_id';          // Kod tablosundaki ürün ilişkisi (Foreign Key)
$COL_USED_DATE      = 'kullanilma_tarihi';  // Kullanım tarihi (NULL = Kullanılmamış)
// -------------------------------------------------------------

// 2. YAPILANDIRMA DOSYASININ KONTROLÜ
$config_path = __DIR__ . '/../config.php';
if (!file_exists($config_path)) {
    die("Sistem Hatası: Yapılandırma dosyası (config.php) bulunamadı.");
}
require_once $config_path;

// 3. VERİTABANI BAĞLANTISI (PDO)
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $db = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die("Bağlantı Hatası: " . $e->getMessage());
}

// 4. İSTATİSTİKSEL VERİLERİN ÇEKİLMESİ
$stats = [];
try {
    // kullanilma_tarihi IS NULL olanlar stokta, IS NOT NULL olanlar kullanılmış sayılır
    $query = "SELECT 
                p.$COL_PROD_ID as id, 
                p.$COL_PROD_NAME as product_name, 
                COUNT(c.id) as total_count,
                SUM(CASE WHEN c.$COL_USED_DATE IS NOT NULL THEN 1 ELSE 0 END) as used_count,
                SUM(CASE WHEN c.$COL_USED_DATE IS NULL THEN 1 ELSE 0 END) as available_count
              FROM $TABLE_PRODUCTS p
              LEFT JOIN $TABLE_CODES c ON p.$COL_PROD_ID = c.$COL_CODE_PROD_ID
              GROUP BY p.$COL_PROD_ID, p.$COL_PROD_NAME
              ORDER BY total_count DESC";
              
    $stmt = $db->query($query);
    $stats = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Sorgu Hatası: " . $e->getMessage());
}

// Genel Toplamlar
$total_all = array_sum(array_column($stats, 'total_count'));
$used_all  = array_sum(array_column($stats, 'used_count'));
$rem_all   = array_sum(array_column($stats, 'available_count'));
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kod Stok Raporu | Yönetim Paneli</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', system-ui, -apple-system, sans-serif; background-color: #f1f5f9; }
        .table-container { background: #ffffff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    </style>
</head>
<body class="text-gray-800 antialiased">

    <div class="flex min-h-screen">
        <!-- Sidebar / Navigation -->
        <aside class="w-64 bg-gray-900 text-white hidden md:flex flex-col shrink-0">
            <div class="p-6 border-b border-gray-800">
                <div class="flex items-center gap-3">
                    <i class="fas fa-shield-alt text-blue-500 text-xl"></i>
                    <h1 class="font-bold text-lg tracking-wide uppercase">Yönetim Paneli</h1>
                </div>
            </div>
            <nav class="flex-1 p-4 space-y-1">
                <a href="../admin" class="flex items-center gap-3 p-3 text-gray-400 hover:text-white hover:bg-gray-800 rounded-lg transition-all">
                    <i class="fas fa-tachometer-alt w-5"></i>
                    <span class="text-sm font-medium">Dashboard</span>
                </a>
                <a href="product_stats.php" class="flex items-center gap-3 p-3 bg-blue-600 text-white rounded-lg shadow-sm">
                    <i class="fas fa-barcode w-5"></i>
                    <span class="text-sm font-medium">Kod İstatistikleri</span>
                </a>
            </nav>
            <div class="p-6 text-xs text-gray-500 border-t border-gray-800 text-center">
                Sistem Sürümü v2.1.0
            </div>
        </aside>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col min-w-0">
            <header class="h-16 px-8 bg-white border-b border-gray-200 flex justify-between items-center sticky top-0 z-10">
                <h2 class="text-lg font-semibold text-gray-700">Erişim Kodları Dağılım Raporu</h2>
                <div class="text-xs font-medium text-gray-400">
                    Son Güncelleme: <?php echo date('d.m.Y H:i'); ?>
                </div>
            </header>

            <main class="p-8 max-w-7xl w-full mx-auto">
                
                <!-- Özet Kartları -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
                        <p class="text-sm font-medium text-gray-500 mb-1">Toplam Oluşturulan Kod</p>
                        <div class="text-3xl font-bold text-gray-900"><?php echo number_format($total_all); ?></div>
                    </div>

                    <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm border-l-4 border-l-orange-500">
                        <p class="text-sm font-medium text-gray-500 mb-1">Toplam Kullanılan Kod</p>
                        <div class="text-3xl font-bold text-orange-600"><?php echo number_format($used_all); ?></div>
                    </div>

                    <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm border-l-4 border-l-green-500">
                        <p class="text-sm font-medium text-gray-500 mb-1">Mevcut Stok (Kullanılabilir)</p>
                        <div class="text-3xl font-bold text-green-600"><?php echo number_format($rem_all); ?></div>
                    </div>
                </div>

                <!-- Veri Tablosu -->
                <div class="table-container overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                        <h3 class="font-semibold text-gray-700">Ürün Bazlı Detaylar</h3>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="text-xs font-semibold text-gray-400 uppercase tracking-wider bg-gray-50">
                                    <th class="px-6 py-4">Ürün Bilgisi</th>
                                    <th class="px-6 py-4 text-center">Toplam Havuz</th>
                                    <th class="px-6 py-4 text-center">Kullanılan</th>
                                    <th class="px-6 py-4 text-center">Kalan Stok</th>
                                    <th class="px-6 py-4 text-right">Doluluk Oranı</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if(empty($stats)): ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-10 text-center text-gray-400 italic">Kayıtlı veri bulunamadı.</td>
                                </tr>
                                <?php endif; ?>

                                <?php foreach($stats as $row): 
                                    $pt = (int)$row['total_count'];
                                    $pu = (int)$row['used_count'];
                                    $pa = (int)$row['available_count'];
                                    $perc = ($pt > 0) ? round(($pu / $pt) * 100) : 0;
                                ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($row['product_name']); ?></div>
                                        <div class="text-xs text-gray-400 italic">ID: #<?php echo $row['id']; ?></div>
                                    </td>
                                    <td class="px-6 py-4 text-center font-medium text-gray-600"><?php echo number_format($pt); ?></td>
                                    <td class="px-6 py-4 text-center text-orange-600 font-semibold"><?php echo number_format($pu); ?></td>
                                    <td class="px-6 py-4 text-center text-green-600 font-semibold"><?php echo number_format($pa); ?></td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center justify-end gap-3">
                                            <div class="w-24 bg-gray-200 rounded-full h-1.5 overflow-hidden">
                                                <div class="bg-blue-600 h-1.5" style="width: <?php echo $perc; ?>%"></div>
                                            </div>
                                            <span class="text-xs font-bold text-gray-500 w-8">%<?php echo $perc; ?></span>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-8 text-center text-gray-400 text-xs">
                    &copy; <?php echo date('Y'); ?> Yönetim Bilgi Sistemi. Tüm hakları saklıdır.
                </div>
            </main>
        </div>
    </div>

</body>
</html>