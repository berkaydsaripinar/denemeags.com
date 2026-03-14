<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

unset($_SESSION['influencer_logged_in'], $_SESSION['influencer_id'], $_SESSION['influencer_name'], $_SESSION['influencer_username']);
set_flash_message('success', 'Influencer panelinden çıkış yapıldı.');
redirect('influencer/login.php');
