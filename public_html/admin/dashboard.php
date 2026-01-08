<?php
// admin/dashboard.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php'; 
require_once __DIR__ . '/../includes/admin_functions.php'; 

requireAdminLogin(); 
$page_title = "Dashboard";

// İSTATİSTİK VERİLERİNİ ÇEK
try {
    // 1. Toplam Kayıtlı Kullanıcı
    $total_users = $pdo->query("SELECT COUNT(*) FROM kullanicilar")->fetchColumn();
    
    // 2. Toplam Satış Sayısı (Loglardan)
    $total_sales_count = $pdo->query("SELECT COUNT(*) FROM satis_loglari")->fetchColumn();
    
    // 3. Toplam Ciro (Brüt)
    $total_revenue = $pdo->query("SELECT SUM(tutar_brut) FROM satis_loglari")->fetchColumn() ?: 0;

    // 4. Aktif Yayın Sayısı
    $active_denemeler = $pdo->query("SELECT COUNT(*) FROM denemeler WHERE aktif_mi = 1")->fetchColumn();

    // 5. Son Satışlar (Tablo İçin)
    $stmt_recent = $pdo->query("
        SELECT sl.*, k.ad_soyad, d.deneme_adi 
        FROM satis_loglari sl
        LEFT JOIN kullanicilar k ON sl.kullanici_id = k.id
        LEFT JOIN denemeler d ON sl.deneme_id = d.id
        ORDER BY sl.tarih DESC LIMIT 5
    ");
    $recent_sales = $stmt_recent->fetchAll();

} catch (PDOException $e) {
    error_log("Dashboard Veri Hatası: " . $e->getMessage());
}

include_once __DIR__ . '/../templates/admin_header.php';
?>

<div class="container-fluid px-0">
    
    <!-- İstatistik Kartları -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="fas fa-users"></i></div>
                <div class="text-muted small fw-bold">TOPLAM ÖĞRENCİ</div>
                <div class="h3 fw-bold mb-0"><?php echo number_format($total_users); ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="fas fa-shopping-bag"></i></div>
                <div class="text-muted small fw-bold">TOPLAM SATIŞ</div>
                <div class="h3 fw-bold mb-0"><?php echo number_format($total_sales_count); ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="fas fa-lira-sign"></i></div>
                <div class="text-muted small fw-bold">TOPLAM CİRO</div>
                <div class="h3 fw-bold mb-0"><?php echo number_format($total_revenue, 2); ?> ₺</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="fas fa-book"></i></div>
                <div class="text-muted small fw-bold">AKTİF YAYINLAR</div>
                <div class="h3 fw-bold mb-0"><?php echo $active_denemeler; ?></div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Son Satışlar Tablosu -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-primary">Son Yapılan Satışlar</h6>
                    <a href="#" class="btn btn-sm btn-light">Tümünü Gör</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: 0.9rem;">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Müşteri</th>
                                    <th>Ürün</th>
                                    <th>Tutar</th>
                                    <th>Tarih</th>
                                    <th class="text-center">Durum</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($recent_sales)): ?>
                                    <tr><td colspan="5" class="text-center py-4 text-muted">Henüz satış bulunmuyor.</td></tr>
                                <?php else: ?>
                                    <?php foreach($recent_sales as $sale): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-bold"><?php echo escape_html($sale['ad_soyad']); ?></div>
                                        </td>
                                        <td><?php echo escape_html($sale['deneme_adi']); ?></td>
                                        <td class="fw-bold text-success"><?php echo number_format($sale['tutar_brut'], 2); ?> ₺</td>
                                        <td class="text-muted small"><?php echo date('d.m.Y H:i', strtotime($sale['tarih'])); ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3">Onaylandı</span>
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

        <!-- Hızlı İşlemler -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold text-primary">Hızlı İşlemler</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="edit_deneme.php" class="btn btn-admin-primary py-2"><i class="fas fa-plus me-2"></i> Yeni Yayın Ekle</a>
                        <a href="generate_codes.php" class="btn btn-light py-2 border text-start"><i class="fas fa-key me-2"></i> Toplu Kod Üret</a>
                        <a href="manage_duyurular.php" class="btn btn-light py-2 border text-start"><i class="fas fa-bullhorn me-2"></i> Duyuru Yayınla</a>
                        <a href="webhook_logs.php" class="btn btn-light py-2 border text-start"><i class="fas fa-bullhorn me-2"></i> Shopier Logları</a>
                        <a href="product_stats.php" class="btn btn-light py-2 border text-start"><i class="fas fa-key me-2"></i> Kod İstatistik</a>

                        
                        
                    </div>
                </div>
            </div>

            <!-- Sistem Durumu -->
            <div class="card border-0 shadow-sm rounded-4 bg-primary text-white">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3">Sistem Sağlığı</h6>
                    <div class="d-flex justify-content-between mb-2 small">
                        <span>Veritabanı</span>
                        <span class="text-success"><i class="fas fa-check-circle"></i> Bağlı</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2 small">
                        <span>SMTP (Mail)</span>
                        <span class="text-success"><i class="fas fa-check-circle"></i> Aktif</span>
                    </div>
                    <div class="d-flex justify-content-between small">
                        <span>Shopier Webhook</span>
                        <span class="text-success"><i class="fas fa-check-circle"></i> Hazır</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php include_once __DIR__ . '/../templates/admin_footer.php'; ?>