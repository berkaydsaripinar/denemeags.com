<?php
// templates/header.php (Bootstrap ile Güncellenmiş)
require_once __DIR__ . '/../config.php'; // BASE_URL ve SITE_NAME için
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? escape_html($page_title) . ' - ' : ''; echo escape_html(SITE_NAME); ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/style.css?v=<?php echo time(); // Cache busting ?>">
</head>
<body class="bg-light-gold">
    <div class="container py-4">
        <header class="header-main-bs text-center text-dark-gold mb-4 p-3 rounded shadow-sm">
            <h1><a href="<?php echo BASE_URL; ?>/dashboard.php" class="text-decoration-none text-dark-gold"><?php echo escape_html(SITE_NAME); ?></a></h1>
        </header>
        
        <main class="content-main bg-very-light-gold p-3 p-md-4 rounded shadow-sm">
            <?php include_once __DIR__ . '/message_area.php'; // Flash mesajları göstermek için ?>
