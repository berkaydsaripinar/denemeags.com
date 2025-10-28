<?php
// includes/functions.php
// Bu dosya, site genelinde kullanılacak yardımcı fonksiyonları içerir.

// config.php'yi çağırarak BASE_URL gibi sabitlere erişim sağlarız.
// Bu, redirect gibi fonksiyonların doğru çalışması için önemlidir.
// Eğer config.php zaten bu dosyayı çağıran dosyalarda include ediliyorsa,
// buradaki require_once gereksiz olabilir, ancak genellikle güvenli bir yaklaşımdır.
if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
} else {
    // config.php bulunamazsa, temel bir BASE_URL tanımla veya hata ver.
    // Bu durum genellikle geliştirme ortamında veya yanlış dosya yapısında oluşur.
    if (!defined('BASE_URL')) {
        // Projenizin kök dizinine göre bir varsayım yapın.
        // Örnek: Eğer includes klasörü kök dizinin bir altındaysa:
        // define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST']);
        // Veya daha spesifik bir yol:
        // define('BASE_URL', 'http://localhost/proje_klasor_adi');
        // Bu, redirect fonksiyonunun çalışması için önemlidir.
        // En iyi pratik, config.php'nin her zaman yüklendiğinden emin olmaktır.
        error_log("UYARI: config.php bulunamadı, BASE_URL tanımlanmamış olabilir. Redirect fonksiyonu düzgün çalışmayabilir.");
    }
}


/**
 * Kullanıcının giriş yapıp yapmadığını kontrol eder.
 * Oturum başlatılmamışsa başlatır.
 * @return bool
 */
function isLoggedIn() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Belirtilen sayfaya HTTP yönlendirmesi yapar.
 * BASE_URL tanımlı olmalıdır.
 * @param string $url Yönlendirilecek URL (BASE_URL'den sonraki kısım, örn: 'dashboard.php' veya 'admin/login.php').
 */
function redirect($url) {
    // BASE_URL'in tanımlı olduğundan emin olalım, değilse bir varsayılan atayalım veya hata verelim.
    $base_url_to_use = defined('BASE_URL') ? BASE_URL : '';
    if (empty($base_url_to_use) && isset($_SERVER['HTTP_HOST'])) {
        // Eğer BASE_URL tanımlı değilse ve HTTP_HOST varsa, geçici bir temel URL oluşturmaya çalış.
        // Bu ideal değildir, BASE_URL config.php'de doğru şekilde tanımlanmalıdır.
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
        $base_url_to_use = $protocol . "://" . $_SERVER['HTTP_HOST'];
        // Eğer projeniz bir alt klasördeyse, bu manuel olarak eklenmelidir.
        // Örneğin: $base_url_to_use .= '/proje_klasoru';
        error_log("UYARI: redirect() fonksiyonunda BASE_URL tanımlı değil, geçici URL kullanılıyor: " . $base_url_to_use);
    }


    $location = rtrim($base_url_to_use, '/') . '/' . ltrim($url, '/');
    if (!headers_sent()) {
        header("Location: " . $location);
        exit;
    } else {
        // Başlıklar zaten gönderilmişse JavaScript ile yönlendirme dene
        // DÜZELTİLMİŞ SATIR: İçteki çift tırnaklar escape edildi (\")
        echo "<script type=\"text/javascript\">window.location.href='" . $location . "';</script>";
        echo "<noscript><meta http-equiv=\"refresh\" content=\"0;url=" . $location . "\" /></noscript>";
        exit;
    }
}

/**
 * Kullanıcı giriş yapmamışsa, giriş sayfasına yönlendirir.
 */
function requireLogin() {
    if (!isLoggedIn()) {
        set_flash_message('error', 'Bu sayfayı görüntülemek için giriş yapmalısınız.');
        redirect('index.php'); // Varsayılan giriş sayfası
    }
}

/**
 * Flash mesajları (tek seferlik bilgilendirme mesajları) session'a ayarlar.
 * @param string $type Mesaj tipi ('success', 'error', 'info', 'warning').
 * @param string $message Gösterilecek mesaj.
 */
function set_flash_message($type, $message) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash_messages'][] = ['type' => $type, 'message' => $message];
}

/**
 * Session'daki tüm flash mesajları alır ve session'dan temizler.
 * @return array Alınan flash mesajların dizisi.
 */
function get_flash_messages() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['flash_messages'])) {
        $messages = $_SESSION['flash_messages'];
        unset($_SESSION['flash_messages']);
        return $messages;
    }
    return [];
}

/**
 * Belirli bir tipte flash mesaj olup olmadığını kontrol eder (session'dan silmez).
 * @param string $type Kontrol edilecek mesaj tipi.
 * @return bool Belirtilen tipte mesaj varsa true, yoksa false.
 */
function has_flash_messages_type($type) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['flash_messages'])) {
        foreach ($_SESSION['flash_messages'] as $msg) {
            if ($msg['type'] === $type) {
                return true;
            }
        }
    }
    return false;
}

/**
 * HTML çıktısını güvenli hale getirir (XSS saldırılarını önlemek için).
 * @param string|null $string Güvenli hale getirilecek metin.
 * @return string Güvenli hale getirilmiş metin.
 */
function escape_html($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * CSRF (Cross-Site Request Forgery) token üretir ve session'a kaydeder.
 * @return string Üretilen CSRF token.
 */
function generate_csrf_token() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        try {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            $_SESSION['csrf_token'] = md5(uniqid(rand(), true) . microtime());
            error_log("CSRF token üretimi için random_bytes başarısız, fallback kullanıldı: " . $e->getMessage());
        }
    }
    return $_SESSION['csrf_token'];
}

/**
 * Gönderilen CSRF token'ını session'daki ile doğrular.
 * @param string $token Doğrulanacak token.
 * @return bool Token geçerliyse true, değilse false.
 */
function verify_csrf_token($token) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['csrf_token']) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
        return true;
    }
    return false;
}

/**
 * Tarih ve saati Türkiye'ye özgü formatta ve Türkçe ay/gün isimleriyle gösterir.
 * @param string $datetime_str Veritabanından gelen veya standart bir tarih-saat metni.
 * @param string $format İstenen PHP tarih formatı (örn: 'd F Y, H:i').
 * @return string Formatlanmış tarih-saat metni veya hata durumunda orijinal metin.
 */
function format_tr_datetime($datetime_str, $format = 'd F Y, H:i') {
    if (empty($datetime_str) || $datetime_str === '0000-00-00 00:00:00') {
        return '';
    }
    try {
        $date = new DateTime($datetime_str); 
        $date->setTimezone(new DateTimeZone('Europe/Istanbul'));
        
        $aylar = [
            'January' => 'Ocak', 'February' => 'Şubat', 'March' => 'Mart',
            'April' => 'Nisan', 'May' => 'Mayıs', 'June' => 'Haziran',
            'July' => 'Temmuz', 'August' => 'Ağustos', 'September' => 'Eylül',
            'October' => 'Ekim', 'November' => 'Kasım', 'December' => 'Aralık'
        ];
        $gunler = [
            'Monday' => 'Pazartesi', 'Tuesday' => 'Salı', 'Wednesday' => 'Çarşamba',
            'Thursday' => 'Perşembe', 'Friday' => 'Cuma', 'Saturday' => 'Cumartesi', 'Sunday' => 'Pazar'
        ];

        $formatted_date = $date->format($format);
        $formatted_date = str_replace(array_keys($aylar), array_values($aylar), $formatted_date);
        $formatted_date = str_replace(array_keys($gunler), array_values($gunler), $formatted_date);
        
        return $formatted_date;
    } catch (Exception $e) {
        error_log("Tarih formatlama hatası (format_tr_datetime): " . $e->getMessage() . " | Gelen değer: " . $datetime_str);
        return $datetime_str; 
    }
}

/**
 * RFC 4122 uyumlu bir UUID v4 üretir.
 * @return string 36 karakterlik UUID (örn: "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx").
 * @throws Exception Eğer güvenli rastgele byte üretilemezse.
 */
function generateUuidV4() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // version 4
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // variant RFC 4122
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Belirtilen deneme için çan eğrisi puanlarını yeniden hesaplar ve günceller.
 * En yüksek ham puan 100'e eşitlenir, diğer puanlar orantılı olarak ayarlanır.
 * Ham puanı 0 veya daha düşük olanların çan eğrisi puanı ham puanlarıyla aynı kalır.
 *
 * @param int $deneme_id Çan eğrisi hesaplanacak denemenin ID'si.
 * @param PDO $pdo Veritabanı bağlantı nesnesi.
 * @return bool Başarılı olursa true, aksi halde false.
 */
function recalculateAndApplyBellCurve($deneme_id, $pdo) {
    try {
        $stmt_scores = $pdo->prepare("
            SELECT id, puan 
            FROM kullanici_katilimlari 
            WHERE deneme_id = :deneme_id AND sinav_tamamlama_tarihi IS NOT NULL
        ");
        $stmt_scores->execute([':deneme_id' => $deneme_id]);
        $participations = $stmt_scores->fetchAll(PDO::FETCH_ASSOC);

        if (empty($participations)) {
            error_log("Bell curve for deneme_id $deneme_id: No completed participations found.");
            return true; 
        }

        $max_raw_score = 0.0; 
        foreach ($participations as $p) {
            if ($p['puan'] !== null && (float)$p['puan'] > $max_raw_score) {
                $max_raw_score = (float)$p['puan'];
            }
        }
        
        if ($max_raw_score <= 0) {
            $stmt_update_all_to_raw = $pdo->prepare(
                "UPDATE kullanici_katilimlari SET puan_can_egrisi = puan WHERE deneme_id = :deneme_id_update AND sinav_tamamlama_tarihi IS NOT NULL"
            );
            $stmt_update_all_to_raw->execute([':deneme_id_update' => $deneme_id]);
            error_log("Bell curve for deneme_id $deneme_id: Max raw score is $max_raw_score. All bell scores set to their raw puan.");
            return true;
        }

        $stmt_update_bell = $pdo->prepare(
            "UPDATE kullanici_katilimlari SET puan_can_egrisi = :puan_can WHERE id = :katilim_id"
        );

        foreach ($participations as $p) {
            $current_raw_puan = (float)($p['puan'] ?? 0); 
            $bell_score = $current_raw_puan; 

            if ($current_raw_puan > 0) {
                $calculated_bell_score = round(($current_raw_puan / $max_raw_score) * 100, 3);
                if (abs($current_raw_puan - $max_raw_score) < 0.0001) { 
                    $bell_score = 100.000;
                } else {
                    $bell_score = min($calculated_bell_score, 99.999); 
                }
            }
            
            $stmt_update_bell->execute([
                ':puan_can' => $bell_score,
                ':katilim_id' => $p['id']
            ]);
        }
        error_log("Bell curve successfully recalculated for deneme_id $deneme_id. Max raw score was $max_raw_score.");
        return true;

    } catch (PDOException $e) {
        error_log("PDOException in recalculateAndApplyBellCurve for deneme_id $deneme_id: " . $e->getMessage());
        return false;
    } catch (Exception $e) {
        error_log("Exception in recalculateAndApplyBellCurve for deneme_id $deneme_id: " . $e->getMessage());
        return false;
    }
}
?>
