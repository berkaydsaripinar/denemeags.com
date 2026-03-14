<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_functions.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Mpdf\Mpdf;

requireAdminLogin();
if (!isSuperAdmin()) {
    http_response_code(403);
    exit('Yetkisiz erişim');
}

$yazarId = filter_input(INPUT_GET, 'yazar_id', FILTER_VALIDATE_INT) ?: 0;
$saleIdsRaw = trim((string) ($_GET['sale_ids'] ?? ''));
$saleIds = array_values(array_filter(array_map('intval', explode(',', $saleIdsRaw))));

if ($yazarId <= 0 || empty($saleIds)) {
    http_response_code(400);
    exit('Geçersiz parametre');
}

$placeholders = implode(',', array_fill(0, count($saleIds), '?'));
$params = array_merge([$yazarId], $saleIds);

$stmt = $pdo->prepare("
    SELECT sl.id, sl.siparis_id, sl.tutar_brut, sl.yazar_payi, sl.tarih, sl.kdv_haric_tutar, sl.kdv_tutari, sl.odenen_toplam_tutar,
           y.ad_soyad AS yazar_adi, y.iban_bilgisi, d.deneme_adi
    FROM satis_loglari sl
    JOIN yazarlar y ON y.id = sl.yazar_id
    JOIN denemeler d ON d.id = sl.deneme_id
    WHERE sl.yazar_id = ?
      AND sl.id IN ($placeholders)
      AND (sl.yazar_odeme_durumu IS NULL OR sl.yazar_odeme_durumu = 'beklemede')
    ORDER BY sl.tarih ASC
");
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($rows)) {
    http_response_code(404);
    exit('Kayıt bulunamadı');
}

$yazarAdi = (string) ($rows[0]['yazar_adi'] ?? 'Yazar');
$iban = (string) ($rows[0]['iban_bilgisi'] ?? '');
$totalPayout = 0.0;
$totalGross = 0.0;
$totalVat = 0.0;

$htmlRows = '';
foreach ($rows as $r) {
    $gross = (float) $r['tutar_brut'];
    $vat = (float) ($r['kdv_tutari'] ?? 0);
    $paid = (float) ($r['odenen_toplam_tutar'] ?? ($gross + $vat));
    $share = (float) $r['yazar_payi'];
    $totalPayout += $share;
    $totalGross += $gross;
    $totalVat += $vat;

    $htmlRows .= '<tr>'
        . '<td>' . (int) $r['id'] . '</td>'
        . '<td>' . escape_html((string) $r['siparis_id']) . '</td>'
        . '<td>' . escape_html((string) $r['deneme_adi']) . '</td>'
        . '<td style="text-align:right;">' . number_format($gross, 2, ',', '.') . ' ₺</td>'
        . '<td style="text-align:right;">' . number_format($vat, 2, ',', '.') . ' ₺</td>'
        . '<td style="text-align:right;">' . number_format($paid, 2, ',', '.') . ' ₺</td>'
        . '<td style="text-align:right; color:#0a7a2f; font-weight:bold;">' . number_format($share, 2, ',', '.') . ' ₺</td>'
        . '<td>' . date('d.m.Y H:i', strtotime((string) $r['tarih'])) . '</td>'
        . '</tr>';
}

$mpdf = new Mpdf(['mode' => 'utf-8', 'format' => 'A4-L']);
$html = '
<h2 style="margin-bottom:8px;">Yazar Ödeme Dökümü</h2>
<div style="margin-bottom:12px; font-size:13px;">
<strong>Yazar:</strong> ' . escape_html($yazarAdi) . '<br>
<strong>IBAN:</strong> ' . escape_html($iban ?: 'Belirtilmemiş') . '<br>
<strong>Rapor Tarihi:</strong> ' . date('d.m.Y H:i') . '
</div>
<table width="100%" border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse; font-size:12px;">
    <thead>
        <tr style="background:#f0f3f8;">
            <th>ID</th>
            <th>Sipariş ID</th>
            <th>Ürün</th>
            <th>Brüt</th>
            <th>KDV</th>
            <th>Tahsil</th>
            <th>Yazar Payı</th>
            <th>Tarih</th>
        </tr>
    </thead>
    <tbody>' . $htmlRows . '</tbody>
</table>
<div style="margin-top:16px; font-size:13px;">
    <strong>Toplam Brüt:</strong> ' . number_format($totalGross, 2, ',', '.') . ' ₺<br>
    <strong>Toplam KDV:</strong> ' . number_format($totalVat, 2, ',', '.') . ' ₺<br>
    <strong>Ödenecek Hakediş:</strong> <span style="color:#0a7a2f;">' . number_format($totalPayout, 2, ',', '.') . ' ₺</span>
</div>';

$mpdf->WriteHTML($html);
$fileName = 'yazar_odeme_dokumu_' . $yazarId . '_' . date('Ymd_His') . '.pdf';
$mpdf->Output($fileName, \Mpdf\Output\Destination::INLINE);
exit;
