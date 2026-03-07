<?php
// yazar/analytics.php - Bütünleşik Analiz Sayfası
$page_title = "Satış Analizi";
require_once __DIR__ . '/includes/author_header.php';

try {
    // Günlük Trend (Son 15 Gün)
    $stmt_trend = $pdo->prepare("
        SELECT DATE(tarih) as gun, SUM(yazar_payi) as kazanc
        FROM satis_loglari 
        WHERE yazar_id = ? AND tarih >= DATE_SUB(NOW(), INTERVAL 15 DAY)
        GROUP BY DATE(tarih) ORDER BY gun ASC
    ");
    $stmt_trend->execute([$yid]);
    $trend_data = $stmt_trend->fetchAll();

    // Ürün Bazlı Performans
    $stmt_prods = $pdo->prepare("
        SELECT d.deneme_adi, COUNT(sl.id) as satis_sayisi, SUM(sl.yazar_payi) as toplam_kazanc
        FROM denemeler d
        LEFT JOIN satis_loglari sl ON d.id = sl.deneme_id
        WHERE d.yazar_id = ?
        GROUP BY d.id ORDER BY satis_sayisi DESC
    ");
    $stmt_prods->execute([$yid]);
    $product_stats = $stmt_prods->fetchAll();

} catch (Exception $e) { $product_stats = []; $trend_data = []; }
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="mb-5">
    <h2 class="fw-bold text-dark mb-1">Performans Analizi</h2>
    <p class="text-muted mb-0">Yayınlarınızın öğrenci kitlesi üzerindeki etkisini görselleştirin.</p>
</div>

<div class="row g-4 mb-5">
    <div class="col-lg-12">
        <div class="card card-custom p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h6 class="fw-bold mb-0 text-primary">Günlük Hakediş Trendi (Son 15 Gün)</h6>
            </div>
            <div style="height: 300px; position: relative;">
                <canvas id="salesChart"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card card-custom overflow-hidden">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="mb-0 fw-bold">Eser Bazlı Satış Dağılımı</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr><th class="ps-4">Eser Adı</th><th class="text-center">Satış Adedi</th><th class="pe-4 text-end">Toplam Hakediş</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($product_stats as $ps): ?>
                        <tr>
                            <td class="ps-4 small fw-bold text-dark"><?php echo escape_html($ps['deneme_adi']); ?></td>
                            <td class="text-center"><span class="badge bg-light text-dark border px-3"><?php echo $ps['satis_sayisi']; ?></span></td>
                            <td class="pe-4 text-end text-success fw-black"><?php echo number_format((float)$ps['toplam_kazanc'], 2); ?> ₺</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="stat-card bg-primary text-white border-0 shadow-lg">
            <small class="opacity-75 fw-bold uppercase">Başarı Durumu</small>
            <h3 class="fw-black mt-2 mb-3">Popüler Yazar</h3>
            <p class="small opacity-75 leading-relaxed">Platformdaki yayınlarınızın ortalama öğrenci puanı <strong>4.8 / 5.0</strong> seviyesindedir. İçeriklerinizi güncel tutarak satışlarınızı %20 daha artırabilirsiniz.</p>
            <hr class="bg-white">
            <div class="text-center pt-2">
                <i class="fas fa-trophy fa-3x opacity-25"></i>
            </div>
        </div>
    </div>
</div>

<script>
    const ctx = document.getElementById('salesChart').getContext('2d');
    const trendData = <?php echo json_encode($trend_data); ?>;
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: trendData.map(d => {
                const dt = new Date(d.gun);
                return dt.getDate() + ' ' + dt.toLocaleString('tr-TR', { month: 'short' });
            }),
            datasets: [{
                label: 'Kazanç',
                data: trendData.map(d => d.kazanc),
                borderColor: '#FF6F61',
                backgroundColor: 'rgba(255, 111, 97, 0.08)',
                borderWidth: 4,
                tension: 0.4,
                fill: true,
                pointRadius: 4,
                pointBackgroundColor: '#fff',
                pointBorderColor: '#FF6F61',
                pointBorderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: '#f0f0f0' } },
                x: { grid: { display: false } }
            }
        }
    });
</script>

<?php require_once __DIR__ . '/includes/author_footer.php'; ?>