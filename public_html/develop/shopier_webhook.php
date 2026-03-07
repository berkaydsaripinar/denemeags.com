<?php
// shopier_webhook.php - Geliştirilmiş 3 Yöntemli Ürün Eşleştirme
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

// --- LOG FONKSİYONU ---
function debug_log($text) {
    $logfile = WEBHOOK_LOG_FILE;
    $time = date("Y-m-d H:i:s");
    file_put_contents($logfile, "[$time] $text" . PHP_EOL, FILE_APPEND);
}

debug_log("══════════════════════════════════════════════════");
debug_log("YENİ WEBHOOK İSTEĞİ");
debug_log("══════════════════════════════════════════════════");

// 1. Veri Kontrolü
if (!isset($_POST['res'])) {
    debug_log("❌ HATA: POST verisi (res) gelmedi.");
    die();
}

$json_result = base64_decode($_POST['res']);
$data = json_decode($json_result, true);

if (!$data) {
    debug_log("❌ HATA: JSON verisi çözülemedi.");
    die();
}

$siparis_id = $data['orderid'];
$email      = $data['email'];
$price_raw  = $data['price'];
$price      = (float) str_replace(',', '.', $price_raw);
$is_test    = $data['istest'] ?? 0;
$product_name = $data['productname'] ?? '';

debug_log("Shopier Sipariş ID: $siparis_id");
debug_log("Email: $email");
debug_log("Tutar: $price TL");
debug_log("Test Modu: " . ($is_test ? 'EVET' : 'HAYIR'));
debug_log("Ürün Adı: $product_name");
debug_log("GET Parametreleri: " . json_encode($_GET));
debug_log("─── TÜM SHOPIER DATA BAŞLANGIÇ ───");
foreach ($data as $key => $value) {
    debug_log("  $key => " . (is_array($value) ? json_encode($value) : $value));
}
debug_log("─── TÜM SHOPIER DATA BİTİŞ ───");
debug_log("Buyer ID (Raw): " . ($data['buyerid'] ?? 'YOK'));
debug_log("Product Name (Raw): " . ($data['productname'] ?? 'YOK'));

// 2. Test Modu Kontrolü
if ($is_test == 1) {
    debug_log("⚠️ TEST MOD - İşlem atlandı.");
    echo "success";
    exit;
}

// Email'den deneme ID'sini çıkar ve gerçek email'i bul
$gercek_email = $email;
$deneme_id_from_email = null;

if (preg_match('/\+DENEME(\d+)@/i', $email, $matches)) {
    $deneme_id_from_email = (int) $matches[1];
    $gercek_email = preg_replace('/\+DENEME\d+@/i', '@', $email);
    debug_log("Email'den deneme ID çıkarıldı: $deneme_id_from_email");
    debug_log("Gerçek email: $gercek_email");
}

try {
    $pdo->beginTransaction();

    // 3. Kullanıcı İşlemleri (gerçek email ile)
    $stmt_user = $pdo->prepare("SELECT id FROM kullanicilar WHERE email = ?");
    $stmt_user->execute([$gercek_email]);
    $user_row = $stmt_user->fetch();
    
    $user_id = 0;
    $yeni_sifre = null;

    if ($user_row) {
        $user_id = $user_row['id'];
        debug_log("✓ Mevcut kullanıcı bulundu (ID: $user_id)");
    } else {
        $buyer_full_name = trim(($data['buyername'] ?? 'Değerli') . ' ' . ($data['buyersurname'] ?? 'Müşterimiz'));
        
        $sifre_ham = bin2hex(random_bytes(4));
        $yeni_sifre = $sifre_ham;
        $stmt_new = $pdo->prepare("INSERT INTO kullanicilar (ad_soyad, email, sifre_hash, aktif_mi) VALUES (?, ?, ?, 1)");
        $stmt_new->execute([$buyer_full_name, $gercek_email, password_hash($sifre_ham, PASSWORD_DEFAULT)]);
        $user_id = $pdo->lastInsertId();
        debug_log("✓ Yeni kullanıcı oluşturuldu: $buyer_full_name (ID: $user_id)");
    }

    // 4. ÜRÜN EŞLEŞTIRME
    debug_log("──────────────────────────────────────────────────");
    debug_log("ÜRÜN EŞLEŞTIRME BAŞLIYOR...");
    debug_log("──────────────────────────────────────────────────");
    
    $deneme = null;
    $eslestirme_yontemi = '';

    // Email'den deneme ID varsa kullan
    if ($deneme_id_from_email) {
        debug_log("Email'den alınan deneme ID: $deneme_id_from_email");
        
        $stmt = $pdo->prepare("
            SELECT d.id, d.deneme_adi, d.yazar_id, y.komisyon_orani
            FROM denemeler d
            LEFT JOIN yazarlar y ON d.yazar_id = y.id
            WHERE d.id = ? AND d.aktif_mi = 1
        ");
        $stmt->execute([$deneme_id_from_email]);
        $deneme = $stmt->fetch();
        
        if ($deneme) {
            $eslestirme_yontemi = 'Email (+DENEME-XXX)';
            debug_log("✓✓ EŞLEŞME BAŞARILI! Ürün: " . $deneme['deneme_adi']);
        }
    } else {
        debug_log("❌ Email'de deneme ID bulunamadı!");
    }

    // EĞER HİÇBİR YÖNTEM ÇALIŞMAZSA
    if (!$deneme) {
        debug_log("❌❌ KRİTİK HATA: Ürün eşleştirilemedi!");
        debug_log("   Sipariş ID: $siparis_id");
        debug_log("   Ürün Adı: $product_name");
        debug_log("   Callback GET: " . ($_GET['deneme_id'] ?? 'YOK'));
        
        $pdo->commit();
        echo "success";
        exit;
    }

    // 5. EŞLEŞME BAŞARILI - DEVAM ET
    debug_log("══════════════════════════════════════════════════");
    debug_log("✓✓✓ ÜRÜN BAŞARIYLA EŞLEŞTİ");
    debug_log("Yöntem: $eslestirme_yontemi");
    debug_log("Ürün ID: " . $deneme['id']);
    debug_log("Ürün Adı: " . $deneme['deneme_adi']);
    debug_log("══════════════════════════════════════════════════");

    // Erişim Kontrolü - Tekrar tanımlama önleme
    $stmt_check = $pdo->prepare("SELECT id FROM kullanici_erisimleri WHERE kullanici_id = ? AND deneme_id = ?");
    $stmt_check->execute([$user_id, $deneme['id']]);
    
    if ($stmt_check->fetch()) {
        debug_log("⚠️ Kullanıcı zaten bu ürüne sahip. İşlem atlandı.");
        $pdo->commit();
        echo "success";
        exit;
    }

    // Erişim Kodu Üret
    $kod = strtoupper(bin2hex(random_bytes(4)));
    $stmt_code = $pdo->prepare("
        INSERT INTO erisim_kodlari 
        (kod, kod_turu, urun_id, deneme_id, kullanici_id, kullanilma_tarihi, cok_kullanimlik) 
        VALUES (?, 'urun', ?, ?, ?, NOW(), 0)
    ");
    $stmt_code->execute([$kod, $deneme['id'], $deneme['id'], $user_id]);
    $erisim_kodu_id = $pdo->lastInsertId();

    // Kullanıcıya Erişim Tanımla
    $stmt_acc = $pdo->prepare("
        INSERT INTO kullanici_erisimleri 
        (kullanici_id, deneme_id, erisim_kodu_id, erisim_tarihi) 
        VALUES (?, ?, ?, NOW())
    ");
    $stmt_acc->execute([$user_id, $deneme['id'], $erisim_kodu_id]);
    
    debug_log("✓ Erişim kodu: $kod");

    // Finansal Log
    $komisyon_orani = isset($deneme['komisyon_orani']) ? (float) $deneme['komisyon_orani'] : 0.0;
    $yazar_payi = round($price * ($komisyon_orani / 100), 2);
    $platform_payi = round($price - $yazar_payi, 2);

    $stmt_log = $pdo->prepare("
        INSERT INTO satis_loglari (
            deneme_id, yazar_id, kullanici_id, siparis_id,
            tutar_brut, komisyon_yazar_orani, yazar_payi, platform_payi,
            yazar_odeme_durumu
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'beklemede')
    ");
    $stmt_log->execute([
        $deneme['id'],
        $deneme['yazar_id'],
        $user_id,
        $siparis_id,
        $price,
        $komisyon_orani,
        $yazar_payi,
        $platform_payi
    ]);

    debug_log("✓ Finansal log kaydedildi (Yazar payı: {$yazar_payi} TL)");

    // 6. E-POSTA GÖNDER
    $subject = "✅ Siparişiniz Onaylandı: " . $deneme['deneme_adi'];
    $site_url = BASE_URL;
    $login_url = BASE_URL . '/index.php';
    $ad_soyad_mail = ($data['buyername'] ?? 'Değerli') . ' ' . ($data['buyersurname'] ?? 'Müşterimiz');

    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f6f8; margin: 0; padding: 0; }
            .email-container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
            .header { background-color: #1F3C88; padding: 25px; text-align: center; }
            .header h1 { color: #ffffff; margin: 0; font-size: 24px; }
            .content { padding: 30px; color: #333; }
            .product-box { background-color: #eef2f6; border-left: 5px solid #F57C00; padding: 15px; margin: 20px 0; border-radius: 4px; }
            .code-box { text-align: center; margin: 30px 0; padding: 20px; background-color: #fff8e1; border: 1px dashed #F57C00; border-radius: 8px; }
            .access-code { font-size: 32px; font-weight: bold; color: #F57C00; letter-spacing: 2px; }
            .credentials { background-color: #f8f9fa; padding: 15px; border-radius: 6px; margin-top: 20px; }
            .btn { display: inline-block; background-color: #1F3C88; color: #ffffff !important; text-decoration: none; padding: 12px 30px; border-radius: 5px; font-weight: bold; margin-top: 20px; }
            .footer { background-color: #1F3C88; color: #aab7d1; text-align: center; padding: 20px; font-size: 12px; }
            .footer a { color: #ffffff; text-decoration: none; }
        </style>
    </head>
    <body>
        <div class="email-container">
            <div class="header">
                <h1>' . SITE_NAME . '</h1>
            </div>
            <div class="content">
                <p>Merhaba <strong>' . htmlspecialchars($ad_soyad_mail) . '</strong>,</p>
                <p>Siparişiniz başarıyla onaylandı ve ürününüz hesabınıza tanımlandı! 🚀</p>
                <div class="product-box">
                    <strong>Satın Alınan Ürün:</strong><br>
                    ' . htmlspecialchars($deneme['deneme_adi']) . '
                </div>
                <div class="code-box">
                    <div style="font-size:14px; color:#666;">ERİŞİM KODUNUZ</div>
                    <div class="access-code">' . $kod . '</div>
                </div>
    ';

    if ($yeni_sifre) {
        $message .= '
                <div class="credentials">
                    <h3 style="margin-top:0; color:#1F3C88;">👤 Giriş Bilgileriniz</h3>
                    <p>Sizin için otomatik hesap oluşturduk:</p>
                    <table style="width:100%; margin-top:10px;">
                        <tr>
                            <td style="width:100px;"><strong>E-posta:</strong></td>
                            <td>' . htmlspecialchars($gercek_email) . '</td>
                        </tr>
                        <tr>
                            <td><strong>Şifre:</strong></td>
                            <td>' . htmlspecialchars($yeni_sifre) . '</td>
                        </tr>
                    </table>
                    <p style="font-size:12px; color:#d9534f; margin-top:10px;">* Güvenliğiniz için giriş yaptıktan sonra şifrenizi değiştirin.</p>
                </div>
        ';
    } else {
        $message .= '<p>Ürününüz <strong>"Kütüphanem"</strong> bölümüne eklendi.</p>';
    }

    $message .= '
                <div style="text-align: center;">
                    <a href="' . $login_url . '" class="btn">Giriş Yap</a>
                </div>
            </div>
            <div class="footer">
                <p>&copy; ' . date("Y") . ' ' . SITE_NAME . '</p>
                <p><a href="' . $site_url . '">Web Sitemizi Ziyaret Edin</a></p>
            </div>
        </div>
    </body>
    </html>
    ';

    debug_log("Mail gönderiliyor → $gercek_email");
    
    $mail_sonuc = send_smtp_email($gercek_email, $subject, $message);
    
    if ($mail_sonuc) {
        debug_log("✓ Mail başarıyla gönderildi.");
    } else {
        debug_log("⚠️ Mail gönderilemedi.");
    }

    $pdo->commit();
    echo "success";
    debug_log("══════════════════════════════════════════════════");
    debug_log("✓✓✓ İŞLEM TAMAMLANDI");
    debug_log("══════════════════════════════════════════════════");

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    debug_log("❌ EXCEPTION: " . $e->getMessage());
    debug_log("Stack: " . $e->getTraceAsString());
    echo "success";
}
?>
