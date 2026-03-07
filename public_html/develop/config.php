<?php
// config.php

$appRoot = __DIR__;
$appParent = dirname($appRoot);
$projectRoot = (basename($appRoot) === 'develop' && basename($appParent) === 'public_html')
    ? dirname($appParent)
    : $appParent;

$runtimeHost = strtolower($_SERVER['HTTP_HOST'] ?? '');
$forcedAppEnv = strtolower((string) getenv('APP_ENV'));

if ($forcedAppEnv !== '') {
    $appEnv = $forcedAppEnv;
} elseif ($runtimeHost === 'develop.denemeags.com' || str_starts_with($runtimeHost, 'develop.')) {
    $appEnv = 'develop';
} else {
    $appEnv = 'production';
}

$environmentDefaults = [
    'production' => [
        'site_name' => 'DenemeAGS-Hibrit Yayın',
        'base_url' => 'https://denemeags.com',
        'db_host' => 'localhost',
        'db_name' => 'u120099295_deneme',
        'db_user' => 'u120099295_deneme',
        'db_pass' => 'Bds242662!',
        'private_uploads_root' => $projectRoot . '/uploads',
    ],
    'develop' => [
        'site_name' => 'DenemeAGS-Hibrit Yayın [Develop]',
        'base_url' => 'https://develop.denemeags.com',
        'db_host' => 'localhost',
        'db_name' => 'u120099295_deneme_develop',
        'db_user' => 'u120099295_deneme_develop',
        'db_pass' => '',
        'private_uploads_root' => $projectRoot . '/develop_uploads',
    ],
];

$selectedEnvironment = $environmentDefaults[$appEnv] ?? $environmentDefaults['production'];

$siteName = getenv('SITE_NAME') ?: $selectedEnvironment['site_name'];
$baseUrl = rtrim((string) (getenv('APP_BASE_URL') ?: $selectedEnvironment['base_url']), '/');
$dbHost = getenv('DB_HOST') ?: $selectedEnvironment['db_host'];
$dbName = getenv('DB_NAME') ?: $selectedEnvironment['db_name'];
$dbUser = getenv('DB_USER') ?: $selectedEnvironment['db_user'];
$dbPass = getenv('DB_PASS');

if ($dbPass === false) {
    $dbPass = $selectedEnvironment['db_pass'];
}

$privateUploadsRoot = getenv('PRIVATE_UPLOADS_ROOT') ?: $selectedEnvironment['private_uploads_root'];
$mailFromAddress = getenv('MAIL_FROM_ADDRESS') ?: 'noreply@denemeags.com';
$mailReplyTo = getenv('MAIL_REPLY_TO') ?: 'support@denemeags.com';
$appDebug = getenv('APP_DEBUG');

if ($appDebug === false || $appDebug === '') {
    $appDebug = '1';
}

define('APP_ENV', $appEnv);
define('APP_ROOT', $appRoot);
define('PROJECT_ROOT', $projectRoot);

// Veritabanı Bağlantı Bilgileri
define('DB_HOST', $dbHost);
define('DB_NAME', $dbName);
define('DB_USER', $dbUser);
define('DB_PASS', $dbPass);
define('DB_CHARSET', 'utf8mb4');

// Site Ayarları
define('SITE_NAME', $siteName);
define('BASE_URL', $baseUrl);
define('SITE_URL', BASE_URL);
define('MAIL_FROM_ADDRESS', $mailFromAddress);
define('MAIL_REPLY_TO', $mailReplyTo);

// Dosya Sistemi Ayarları
define('PRIVATE_UPLOADS_ROOT', rtrim($privateUploadsRoot, '/'));
define('UPLOADS_ROOT', PRIVATE_UPLOADS_ROOT);
define('PRODUCT_UPLOADS_DIR', PRIVATE_UPLOADS_ROOT . '/products');
define('VIDEO_UPLOADS_DIR', PRIVATE_UPLOADS_ROOT . '/videos');
define('PUBLIC_UPLOADS_DIR', APP_ROOT . '/uploads');
define('PUBLIC_PRODUCT_UPLOADS_DIR', PUBLIC_UPLOADS_DIR . '/products');
define('QUESTIONS_UPLOADS_DIR', PUBLIC_UPLOADS_DIR . '/questions');
define('SOLUTIONS_UPLOADS_DIR', PUBLIC_UPLOADS_DIR . '/solutions');
define('TMP_DIR', APP_ROOT . '/tmp');
define('CACHE_DIR', APP_ROOT . '/cache');
define('WEBHOOK_LOG_FILE', APP_ROOT . '/webhook_debug.txt');

// Shopier Ayarları (ENV üzerinden okunur)
define('SHOPIER_API_KEY', getenv('SHOPIER_API_KEY') ?: '');
define('SHOPIER_API_SECRET', getenv('SHOPIER_API_SECRET') ?: '');
define('SHOPIER_WEBSITE_INDEX', getenv('SHOPIER_WEBSITE_INDEX') ?: '1');
define('VIDEO_STREAM_SECRET', getenv('VIDEO_STREAM_SECRET') ?: 'change-this-secret');
define('PDF_SIGNATURE_SECRET', getenv('PDF_SIGNATURE_SECRET') ?: VIDEO_STREAM_SECRET);

// Saat Dilimi
date_default_timezone_set('Europe/Istanbul');

// Oturum (Session) Ayarları
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Hata Raporlama
$isDebugEnabled = in_array(strtolower((string) $appDebug), ['1', 'true', 'yes', 'on'], true);
error_reporting(E_ALL);
ini_set('display_errors', $isDebugEnabled ? '1' : '0');

// Varsayılan sistem ayarları
$default_settings = [
    'NET_KATSAYISI' => 4,
    'PUAN_CARPANI' => 2,
];

global $site_ayarlari;
$site_ayarlari = $default_settings;

?>
