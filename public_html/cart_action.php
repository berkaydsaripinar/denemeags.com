<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

$action = (string) ($_GET['action'] ?? '');
$productId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$bundleId = filter_input(INPUT_GET, 'bundle_id', FILTER_VALIDATE_INT) ?: 0;
$redirect = (string) ($_SERVER['HTTP_REFERER'] ?? 'store.php');

if ($productId <= 0 && $bundleId <= 0) {
    set_flash_message('error', 'Geçersiz ürün.');
    redirect('store.php');
}

if ($action === 'add') {
    add_to_cart($productId);
    set_flash_message('success', 'Ürün sepete eklendi.');
} elseif ($action === 'remove') {
    remove_from_cart($productId);
    set_flash_message('info', 'Ürün sepetten çıkarıldı.');
} elseif ($action === 'add_bundle') {
    add_bundle_to_cart($bundleId);
    set_flash_message('success', 'Paket sepete eklendi.');
} elseif ($action === 'remove_bundle') {
    remove_bundle_from_cart($bundleId);
    set_flash_message('info', 'Paket sepetten çıkarıldı.');
} else {
    set_flash_message('error', 'Geçersiz işlem.');
}

if (!is_absolute_url($redirect)) {
    redirect($redirect);
}

redirect('store.php');
