<?php
// checkout_window.php - PAYTR geçişi sonrası checkout.php'ye yönlendirme
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($id) {
    redirect('checkout.php?id=' . $id);
}

redirect('store.php');
