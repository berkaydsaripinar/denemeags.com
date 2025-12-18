<?php
// shopier_webhook.php - HATA AYIKLAMA (DEBUG) MODU
// Sorunu bulmak için her adımı 'webhook_debug.txt' dosyasına yazar.

error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

// --- LOG FONKSİYONU ---
function debug_log($text) {
    $logfile = __DIR__ . '/webhook_debug.txt';
    $time = date("Y-m-d H:i:s");
    file_put_contents($logfile, "[$time] $text" . PHP_EOL, FILE_APPEND);
}

debug_log("--------------------------------------------------");
debug_log("YENİ İSTEK GELDİ. İşlem Başlıyor...");

// --- AYARLAR ---
$username = '3579768d3cd1d74b30b7582b55ddb90c'; // OSB Kullanıcı Adı
$key      = '0d50fb442141bb411b45e5912a823348'; // OSB Şifresi

// 1. Veri Kontrolü
if (!isset($_POST['res'])) {
    debug_log("HATA: POST verisi (res) gelmedi.");
    die();
}

$json_result = base64_decode($_POST['res']);
$data = json_decode($json_result, true);

if (!$data) {
    debug_log("HATA: JSON verisi çözülemedi.");
    die();
}

$siparis_id = $data['orderid'];
$email      = $data['email'];
$price      = $data['price'];
$is_test    = $data['istest'] ?? 0;

debug_log("Sipariş ID: $siparis_id | Email: $email | Tutar: $price | Test Modu: $is_test");

// 2. Test Modu Kontrolü
if ($is_test == 1) {
    debug_log("UYARI: Bu bir TEST işlemidir (Shopier Panelinden). Mail atılmadan success dönülüyor.");
    echo "success";
    exit;
}

try {
    $pdo->beginTransaction();

    // 3. Kullanıcı İşlemleri
    $stmt_user = $pdo->prepare("SELECT id FROM kullanicilar WHERE email = ?");
    $stmt_user->execute([$email]);
    $user_row = $stmt_user->fetch();
    
    $user_id = 0;
    $yeni_sifre = null; // Değişkeni başta tanımladık

    if ($user_row) {
        $user_id = $user_row['id'];
        debug_log("Mevcut kullanıcı bulundu. ID: $user_id");
    } else {
        $sifre_ham = bin2hex(random_bytes(4));
        $yeni_sifre = $sifre_ham; // Mailde kullanmak için sakla
        $stmt_new = $pdo->prepare("INSERT INTO kullanicilar (ad_soyad, email, sifre_hash, aktif_mi) VALUES (?, ?, ?, 1)");
        $stmt_new->execute(['Musteri', $email, password_hash($sifre_ham, PASSWORD_DEFAULT)]);
        $user_id = $pdo->lastInsertId();
        debug_log("Yeni kullanıcı oluşturuldu. ID: $user_id");
    }

    // 4. Ürün Eşleştirme
    $gelen_shopier_id = $data['productid']; 
    debug_log("Shopier'den Gelen Ürün ID: " . $gelen_shopier_id);

    $stmt_deneme = $pdo->prepare("SELECT id, deneme_adi, yazar_id FROM denemeler WHERE shopier_product_id = ?");
    $stmt_deneme->execute([$gelen_shopier_id]);
    $deneme = $stmt_deneme->fetch();

    if ($deneme) {
        debug_log("EŞLEŞME BAŞARILI! Veritabanındaki Ürün: " . $deneme['deneme_adi'] . " (ID: " . $deneme['id'] . ")");
        
        // Kod üretme ve kaydetme
        $kod = strtoupper(bin2hex(random_bytes(4)));
        $stmt_code = $pdo->prepare("INSERT INTO erisim_kodlari (kod, kod_turu, urun_id, deneme_id, kullanici_id, kullanilma_tarihi, cok_kullanimlik) VALUES (?, 'urun', ?, ?, ?, NOW(), 0)");
        $stmt_code->execute([$kod, $deneme['id'], $deneme['id'], $user_id]);
        
        $erisim_kodu_id = $pdo->lastInsertId();

        $stmt_acc = $pdo->prepare("INSERT IGNORE INTO kullanici_erisimleri (kullanici_id, deneme_id, erisim_kodu_id, erisim_tarihi) VALUES (?, ?, ?, NOW())");
        $stmt_acc->execute([$user_id, $deneme['id'], $erisim_kodu_id]);
        
        // Finansal Log
        // Yazar payını hesaplamak isterseniz burayı geliştirebilirsiniz, şimdilik 0 geçiyoruz.
        $stmt_log = $pdo->prepare("INSERT INTO satis_loglari (deneme_id, yazar_id, kullanici_id, siparis_id, tutar_brut, komisyon_yazar_orani, yazar_payi, platform_payi) VALUES (?, ?, ?, ?, ?, 0, 0, ?)");
        $stmt_log->execute([$deneme['id'], $deneme['yazar_id'], $user_id, $siparis_id, $price, $price]);

        debug_log("Veritabanı kayıtları tamamlandı. Kod: $kod");

        // --- 5. PROFESYONEL HTML E-POSTA TASARIMI ---
        
        $subject = "✅ Siparişiniz Onaylandı: " . $deneme['deneme_adi'];

        $logo_src = BASE_URL . '/assets/images/logo.png'; 
        $site_url = BASE_URL;
        $login_url = BASE_URL . '/index.php'; // Giriş sayfası ana sayfa ise

        // Müşteri Adı
        $ad_soyad_mail = ($data['buyername'] ?? 'Değerli') . ' ' . ($data['buyersurname'] ?? 'Müşterimiz');

        $message = '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f6f8; margin: 0; padding: 0; }
                .email-container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.05); font-size: 16px; color: #333333; }
                .header { background-color: #1F3C88; padding: 25px; text-align: center; }
                .header h1 { color: #ffffff; margin: 0; font-size: 24px; letter-spacing: 1px; }
                .content { padding: 30px; }
                .product-box { background-color: #eef2f6; border-left: 5px solid #F57C00; padding: 15px; margin: 20px 0; border-radius: 4px; }
                .code-box { text-align: center; margin: 30px 0; padding: 20px; background-color: #fff8e1; border: 1px dashed #F57C00; border-radius: 8px; }
                .access-code { font-size: 32px; font-weight: bold; color: #F57C00; letter-spacing: 2px; display: block; margin-top: 5px; }
                .credentials { background-color: #f8f9fa; padding: 15px; border-radius: 6px; margin-top: 20px; font-size: 14px; }
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
                    <p>Merhaba <strong>' . $ad_soyad_mail . '</strong>,</p>
                    <p>Siparişiniz başarıyla onaylandı ve eğitim materyaliniz hesabınıza tanımlandı. Aramıza hoş geldiniz! 🚀</p>

                    <div class="product-box">
                        <strong>Satın Alınan Ürün:</strong><br>
                        ' . $deneme['deneme_adi'] . '
                    </div>

                    <div class="code-box">
                        <span style="font-size:14px; color:#666;">KİŞİYE ÖZEL ERİŞİM KODUNUZ</span>
                        <span class="access-code">' . $kod . '</span>
                    </div>
        ';

        if ($yeni_sifre) {
            $message .= '
                    <div class="credentials">
                        <h3 style="margin-top:0; color:#1F3C88;">👤 Yeni Üyelik Bilgileriniz</h3>
                        <p style="margin:5px 0;">Sizin için otomatik bir hesap oluşturduk. Aşağıdaki bilgilerle giriş yapabilirsiniz:</p>
                        <table style="width:100%; margin-top:10px;">
                            <tr>
                                <td style="width:100px; color:#666;"><strong>E-posta:</strong></td>
                                <td>' . $email . '</td>
                            </tr>
                            <tr>
                                <td style="color:#666;"><strong>Geçici Şifre:</strong></td>
                                <td>' . $yeni_sifre . '</td>
                            </tr>
                        </table>
                        <p style="font-size:12px; color:#d9534f; margin-top:10px;">* Güvenliğiniz için giriş yaptıktan sonra şifrenizi değiştirmenizi öneririz.</p>
                    </div>
            ';
        } else {
            $message .= '
                    <p>Ürününüz mevcut hesabınızdaki <strong>"Kütüphanem"</strong> bölümüne otomatik olarak eklenmiştir.</p>
            ';
        }

        $message .= '
                    <div style="text-align: center;">
                        <a href="' . $login_url . '" class="btn">Giriş Yap ve İncele</a>
                    </div>
                </div>

                <div class="footer">
                    <p>Bu e-posta otomatik olarak gönderilmiştir.</p>
                    <p>&copy; ' . date("Y") . ' ' . SITE_NAME . '. Tüm hakları saklıdır.</p>
                    <p><a href="' . $site_url . '">Web Sitemizi Ziyaret Edin</a></p>
                </div>
            </div>
        </body>
        </html>
        ';

        debug_log("Mail gönderimi başlatılıyor... Alıcı: $email");
        
        $mail_sonuc = send_smtp_email($email, $subject, $message);
        
        if ($mail_sonuc) {
            debug_log("SONUÇ: HTML Mail başarıyla gönderildi.");
        } else {
            debug_log("HATA: Mail gönderilemedi.");
        }

    } else {
        debug_log("KRİTİK HATA: Shopier'den gelen '$gelen_shopier_id' numaralı ürün veritabanında bulunamadı! Admin panelinden kontrol edin.");
    }

    $pdo->commit();
    echo "success";
    debug_log("İşlem başarıyla tamamlandı.");

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    debug_log("EXCEPTION HATASI: " . $e->getMessage());
    echo "success";
}
?>