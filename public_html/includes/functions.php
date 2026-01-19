<?php
// includes/functions.php
// Site genelinde kullanılacak yardımcı fonksiyonlar.

// 1. Config Dosyası Kontrolü
if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
} else {
    if (!defined('BASE_URL')) {
        // Acil durum BASE_URL tanımı
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
        define('BASE_URL', $protocol . "://" . $_SERVER['HTTP_HOST']);
    }
}

// 2. Session Başlatma (Her fonksiyon içinde tekrar etmemek için)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- GÜVENLİK VE YARDIMCI FONKSİYONU ---

function escape_html($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function redirect($url) {
    $base_url = defined('BASE_URL') ? BASE_URL : '';
    if (empty($base_url)) {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
        $base_url = $protocol . "://" . $_SERVER['HTTP_HOST'];
    }

    // Tam URL oluştur
    // Eğer $url zaten http ile başlıyorsa dokunma, değilse birleştir.
    if (strpos($url, 'http') === 0) {
        $location = $url;
    } else {
        $location = rtrim($base_url, '/') . '/' . ltrim($url, '/');
    }

    if (!headers_sent()) {
        header("Location: " . $location);
        exit;
    } else {
        echo "<script>window.location.href='" . $location . "';</script>";
        echo "<noscript><meta http-equiv=\"refresh\" content=\"0;url=" . $location . "\" /></noscript>";
        exit;
    }
}

function requireLogin() {
    if (!isLoggedIn()) {
        set_flash_message('warning', 'Bu sayfayı görüntülemek için giriş yapmalısınız.');
        redirect('login.php');
    }
}
function send_custom_mail($to, $subject, $content_title, $content_body, $button_text = '', $button_url = '') {
    $site_name = "Deneme AGS";
    $site_url = "https://denemeags.com";
    $logo_url = $site_url . "/assets/img/logo.png"; // Varsa logonuzun tam yolu

    // HTML E-posta Şablonu (Inline CSS kullanılması zorunludur)
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 0; background-color: #f4f7f9; color: #333; }
            .container { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
            .header { background-color: #1e293b; padding: 30px; text-align: center; color: #ffffff; }
            .content { padding: 40px; line-height: 1.6; }
            .footer { background-color: #f8fafc; padding: 20px; text-align: center; font-size: 12px; color: #64748b; border-top: 1px solid #e2e8f0; }
            .btn { display: inline-block; padding: 12px 25px; background-color: #ef4444; color: #ffffff !important; text-decoration: none; border-radius: 6px; font-weight: bold; margin-top: 20px; }
            h1 { margin-top: 0; color: #1e293b; font-size: 22px; }
            p { margin-bottom: 15px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2 style='margin:0;'>$site_name</h2>
            </div>
            <div class='content'>
                <h1>$content_title</h1>
                <div>$content_body</div>
                " . ($button_url ? "<a href='$button_url' class='btn'>$button_text</a>" : "") . "
            </div>
            <div class='footer'>
                &copy; " . date('Y') . " $site_name. Tüm hakları saklıdır.<br>
                Bu e-posta otomatik olarak gönderilmiştir.
            </div>
        </div>
    </body>
    </html>";

    // Mail Başlıkları (HTML olarak gitmesini sağlayan kritik kısım)
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: $site_name <noreply@denemeags.com>" . "\r\n";
    $headers .= "Reply-To: support@denemeags.com" . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    return mail($to, $subject, $message, $headers);
}

// --- FLASH MESAJ SİSTEMİ (DÜZELTİLEN KISIM) ---

function set_flash_message($type, $message) {
    if (session_status() == PHP_SESSION_NONE) session_start();
    $_SESSION['flash_messages'][] = ['type' => $type, 'message' => $message];
}

// BU FONKSİYON EKSİKTİ, O YÜZDEN HATA ALIYORDUNUZ:
function display_flash_message() {
    if (session_status() == PHP_SESSION_NONE) session_start();
    
    if (isset($_SESSION['flash_messages'])) {
        foreach ($_SESSION['flash_messages'] as $msg) {
            // Bootstrap alert class'ları ile uyumlu hale getirme
            $alert_type = ($msg['type'] == 'error') ? 'danger' : $msg['type']; // error -> danger
            
            echo '<div class="alert alert-' . $alert_type . ' alert-dismissible fade show m-3" role="alert">';
            echo $msg['message'];
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>';
            echo '</div>';
        }
        // Mesajları gösterdikten sonra sil
        unset($_SESSION['flash_messages']);
    }
}

function get_flash_messages() {
    if (isset($_SESSION['flash_messages'])) {
        $messages = $_SESSION['flash_messages'];
        unset($_SESSION['flash_messages']);
        return $messages;
    }
    return [];
}

// --- CSRF GÜVENLİK ---

function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        try {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            $_SESSION['csrf_token'] = md5(uniqid(rand(), true));
        }
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function generate_video_stream_token($deneme_id, $katilim_id = null, $ttl_seconds = 300) {
    if (!isset($_SESSION['video_stream_tokens'])) {
        $_SESSION['video_stream_tokens'] = [];
    }

    $now = time();
    foreach ($_SESSION['video_stream_tokens'] as $token => $data) {
        if (!isset($data['expires']) || $data['expires'] < $now) {
            unset($_SESSION['video_stream_tokens'][$token]);
        }
    }

    $token = bin2hex(random_bytes(24));
    $_SESSION['video_stream_tokens'][$token] = [
        'deneme_id' => (int)$deneme_id,
        'katilim_id' => $katilim_id ? (int)$katilim_id : null,
        'expires' => $now + (int)$ttl_seconds
    ];

    return $token;
}

function validate_video_stream_token($token, $deneme_id, $katilim_id = null) {
    if (empty($token) || !isset($_SESSION['video_stream_tokens'][$token])) {
        return false;
    }

    $token_data = $_SESSION['video_stream_tokens'][$token];
    if ($token_data['expires'] < time()) {
        unset($_SESSION['video_stream_tokens'][$token]);
        return false;
    }

    if ((int)$token_data['deneme_id'] !== (int)$deneme_id) {
        return false;
    }

    if ($katilim_id !== null && (int)$token_data['katilim_id'] !== (int)$katilim_id) {
        return false;
    }

    return true;
}

// --- TARİH VE FORMATLAMA ---

function format_tr_datetime($datetime_str, $format = 'd F Y, H:i') {
    if (empty($datetime_str) || $datetime_str === '0000-00-00 00:00:00') return '';
    try {
        $date = new DateTime($datetime_str); 
        $date->setTimezone(new DateTimeZone('Europe/Istanbul'));
        
        $aylar = ['January'=>'Ocak', 'February'=>'Şubat', 'March'=>'Mart', 'April'=>'Nisan', 'May'=>'Mayıs', 'June'=>'Haziran', 'July'=>'Temmuz', 'August'=>'Ağustos', 'September'=>'Eylül', 'October'=>'Ekim', 'November'=>'Kasım', 'December'=>'Aralık'];
        $gunler = ['Monday'=>'Pazartesi', 'Tuesday'=>'Salı', 'Wednesday'=>'Çarşamba', 'Thursday'=>'Perşembe', 'Friday'=>'Cuma', 'Saturday'=>'Cumartesi', 'Sunday'=>'Pazar'];

        $formatted = $date->format($format);
        return str_replace(array_keys($gunler), array_values($gunler), str_replace(array_keys($aylar), array_values($aylar), $formatted));
    } catch (Exception $e) {
        return $datetime_str; 
    }
}

// --- ÖZEL FONKSİYONLAR (Çan Eğrisi vb.) ---

function generateUuidV4() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); 
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); 
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function recalculateAndApplyBellCurve($deneme_id, $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT id, puan FROM kullanici_katilimlari WHERE deneme_id = ? AND sinav_tamamlama_tarihi IS NOT NULL");
        $stmt->execute([$deneme_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) return true;

        $max_raw = 0.0;
        foreach ($rows as $r) {
            if ((float)$r['puan'] > $max_raw) $max_raw = (float)$r['puan'];
        }
        
        if ($max_raw <= 0) {
            // Puanlar 0 ise güncelleme yapma veya ham puanı eşitle
            $pdo->prepare("UPDATE kullanici_katilimlari SET puan_can_egrisi = puan WHERE deneme_id = ?")->execute([$deneme_id]);
            return true;
        }

        $stmt_update = $pdo->prepare("UPDATE kullanici_katilimlari SET puan_can_egrisi = ? WHERE id = ?");
        foreach ($rows as $r) {
            $raw = (float)$r['puan'];
            $bell = ($raw > 0) ? min(round(($raw / $max_raw) * 100, 3), 100) : 0;
            $stmt_update->execute([$bell, $r['id']]);
        }
        return true;
    } catch (Exception $e) {
        error_log("Çan eğrisi hatası: " . $e->getMessage());
        return false;
    }
}

// --- DOĞRULAMA FONKSİYONLARI (Kayıt Ol Sayfası İçin) ---

function tc_kimlik_dogrula($tc) {
    if (strlen($tc) != 11 || !ctype_digit($tc)) return false;
    if ($tc[0] == '0') return false;

    $rakamlar = str_split($tc);
    $tekler = $rakamlar[0] + $rakamlar[2] + $rakamlar[4] + $rakamlar[6] + $rakamlar[8];
    $ciftler = $rakamlar[1] + $rakamlar[3] + $rakamlar[5] + $rakamlar[7];
    
    $h10 = (($tekler * 7) - $ciftler) % 10;
    $ilk10toplam = array_sum(array_slice($rakamlar, 0, 10));
    $h11 = $ilk10toplam % 10;

    return ($rakamlar[9] == $h10 && $rakamlar[10] == $h11);
}

function telefon_formatla($tel) {
    $tel = preg_replace('/[^0-9]/', '', $tel);
    if (substr($tel, 0, 2) == '90') $tel = substr($tel, 2);
    if (substr($tel, 0, 1) != '0') $tel = '0' . $tel;
    return $tel;
}

// --- SMTP MAİL FONKSİYONU (PHPMailer) ---

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Dosyaların varlığını kontrol ederek dahil et
if (file_exists(__DIR__ . '/PHPMailer/PHPMailer.php')) {
    require_once __DIR__ . '/PHPMailer/Exception.php';
    require_once __DIR__ . '/PHPMailer/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/SMTP.php';
}

function send_smtp_email($to_email, $subject, $message_body) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; 
        $mail->SMTPAuth   = true;
        $mail->Username   = 'denemeags@gmail.com'; 
        $mail->Password   = 'jwvc jneb yubv ihzj'; // Senin uygulama şifren
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('denemeags@gmail.com', 'DenemeAGS Bilgilendirme'); // From ismi eklendi
        $mail->addAddress($to_email);

        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body    = nl2br($message_body);
        $mail->AltBody = strip_tags($message_body);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mail Hatası: " . $mail->ErrorInfo);
        return false;
    }
}

// --- URL TABANLI RESİM GETİRİCİ ---
function get_image_url($url) {
    // 1. Veritabanında bilgi yoksa varsayılan resim göster
    if (empty($url)) {
        return 'https://via.placeholder.com/400x300?text=Gorsel+Yok&bg=eee&color=999';
    }

    // 2. Kullanıcı ne girdiyse (https://...) aynen geri döndür.
    // Hiçbir klasör eklemesi yapmıyoruz.
    return escape_html($url);
}

?>