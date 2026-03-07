<?php
// includes/admin_functions.php
require_once __DIR__ . '/../config.php'; // For session_start and other configs

/**
 * Admin kullanıcısının giriş yapıp yapmadığını kontrol eder.
 * @return bool
 */
function isAdminLoggedIn() {
    if (session_status() == PHP_SESSION_NONE) session_start();
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true && isset($_SESSION['admin_id']);
}

/**
 * Giriş yapmış olan adminin Süper Admin olup olmadığını kontrol eder.
 * @return bool
 */
function isSuperAdmin() {
    return isAdminLoggedIn() && isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'superadmin';
}

/**
 * Giriş yapmış olan adminin yetkili olduğu deneme ID'lerinin bir dizisini döndürür.
 * Eğer Süper Admin ise null döndürür (tüm denemelere yetkili olduğu anlamına gelir).
 * Eğer Sub-Admin ise ve yetkisi yoksa boş bir dizi döndürür.
 * @return array|null
 */
function getAuthorizedDenemeIds() {
    if (isSuperAdmin()) {
        return null; // Süper Admin tüm denemelere yetkilidir, filtreleme yapılmaz.
    }
    if (isAdminLoggedIn() && isset($_SESSION['authorized_deneme_ids'])) {
        // Değerlerin integer olduğundan emin olalım
        return array_map('intval', $_SESSION['authorized_deneme_ids']);
    }
    return []; // Yetkili deneme yoksa boş dizi
}

/**
 * Admin giriş yapmamışsa admin login sayfasına yönlendirir.
 */
function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        $admin_base_url = rtrim(BASE_URL, '/') . '/admin';
        header("Location: " . $admin_base_url . "/index.php");
        exit;
    }
}

/**
 * Admin için CSRF token üretir ve session'a kaydeder.
 * @return string
 */
function generate_admin_csrf_token() {
    if (session_status() == PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['admin_csrf_token'])) {
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['admin_csrf_token'];
}

/**
 * Gönderilen admin CSRF token'ını doğrular.
 * @param string $token
 * @return bool
 */
function verify_admin_csrf_token($token) {
    if (session_status() == PHP_SESSION_NONE) session_start();
    return isset($_SESSION['admin_csrf_token']) && hash_equals($_SESSION['admin_csrf_token'], $token);
}

// ... (set_admin_flash_message, get_admin_flash_messages, generate_unique_codes fonksiyonları aynı kalacak) ...
function set_admin_flash_message($type, $message) {
    if (session_status() == PHP_SESSION_NONE) session_start();
    $_SESSION['admin_flash_messages'][] = ['type' => $type, 'message' => $message];
}

function get_admin_flash_messages() {
    if (session_status() == PHP_SESSION_NONE) session_start();
    if (isset($_SESSION['admin_flash_messages'])) {
        $messages = $_SESSION['admin_flash_messages'];
        unset($_SESSION['admin_flash_messages']);
        return $messages;
    }
    return [];
}
function generate_unique_codes($count = 100, $length = 8) {
    $codes = [];
    $characters = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    $char_length = strlen($characters);
    $generated_count = 0;
    while ($generated_count < $count) {
        $random_string = '';
        for ($j = 0; $j < $length; $j++) {
            $random_string .= $characters[random_int(0, $char_length - 1)];
        }
        if (!in_array($random_string, $codes)) {
            $codes[] = strtoupper($random_string);
            $generated_count++;
        }
    }
    return $codes;
}
?>
