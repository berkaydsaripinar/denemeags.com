<?php
/**
 * templates/header.php
 * Modern, Responsive (Mobil Uyumlu) ve Temiz Header Yapısı.
 * Bu dosyanın başında hiçbir boşluk olmamasına dikkat edin.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo isset($page_title) ? $page_title . ' | ' . SITE_NAME : SITE_NAME; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts (Montserrat & Open Sans) -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700;800&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">

    <style>
        :root { 
            --primary: #1F3C88; 
            --accent: #F57C00; 
            --dark: #0B162C;
            --light: #f8f9fa;
        }
        
        body { 
            background-color: #f4f7f6; 
            font-family: 'Open Sans', sans-serif; 
            display: flex; 
            flex-direction: column; 
            min-height: 100vh;
            padding-top: 70px; /* Navbar sabit olduğu için içerik altında kalmasın */
        }
        
        h1, h2, h3, h4, h5, .navbar-brand { font-family: 'Montserrat', sans-serif; }

        /* --- Navbar Tasarımı --- */
        .navbar-custom { 
            background-color: var(--primary); 
            padding: 10px 0;
            position: fixed; /* Yukarıya sabitle */
            top: 0;
            width: 100%;
            z-index: 9999 !important;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            transition: all 0.3s ease;
        }

        .navbar-brand { 
            font-weight: 800; 
            letter-spacing: -0.5px; 
            font-size: 1.35rem; 
            color: #fff !important; 
            display: flex;
            align-items: center;
        }

        /* Mobilde Menü Butonları ve Yazılar */
        .nav-link { 
            color: rgba(255,255,255,0.9) !important; 
            font-weight: 600; 
            font-size: 0.95rem;
            padding: 10px 15px !important;
            transition: 0.2s; 
        }
        
        .nav-link:hover { color: var(--accent) !important; }

        /* Menü Butonları Özelleştirme */
        .btn-nav-login {
            color: white; 
            border: 1px solid rgba(255,255,255,0.3);
            padding: 8px 20px; 
            border-radius: 50px; 
            text-decoration: none;
            font-weight: 600; 
            display: inline-block;
            transition: 0.3s;
        }
        .btn-nav-login:hover { background-color: white; color: var(--primary); border-color: white; }

        .btn-nav-register {
            background-color: var(--accent); 
            color: white;
            padding: 8px 22px; 
            border-radius: 50px; 
            text-decoration: none;
            font-weight: 700; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            transition: 0.3s; 
            border: none;
            display: inline-block;
        }
        .btn-nav-register:hover { background-color: #e65100; transform: translateY(-2px); color: white; }

        /* Mobilde Menü Açıldığında Görünüm */
        @media (max-width: 991.98px) {
            .navbar-collapse {
                background: var(--dark);
                margin: 10px -15px -10px -15px;
                padding: 20px;
                border-radius: 0 0 20px 20px;
                box-shadow: 0 10px 20px rgba(0,0,0,0.2);
            }
            .nav-item {
                width: 100%;
                text-align: center;
                margin-bottom: 10px;
            }
            .btn-nav-login, .btn-nav-register {
                width: 100%;
                margin: 5px 0;
            }
            .navbar-brand { font-size: 1.15rem; }
        }

        /* --- Alert Mesajları --- */
        .alert-container { 
            margin-top: 15px; 
            padding: 0 15px;
        }
        .alert {
            border-radius: 12px;
            border: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            font-size: 0.9rem;
            font-weight: 600;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
    <div class="container">
        <!-- Logo -->
        <a class="navbar-brand" href="<?php echo BASE_URL; ?>/index.php">
            <i class="fas fa-bolt me-2 text-warning"></i>DENEME<span class="text-warning">AGS</span>
        </a>
        
        <!-- Hamburger Menü Butonu (Mobil) -->
        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Menüyü Aç">
            <i class="fas fa-bars text-white"></i>
        </button>
        
        <!-- Menü Linkleri -->
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_URL; ?>/index.php">Ana Sayfa</a>
                </li>
                <!-- MAĞAZA LİNKİ -->
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_URL; ?>/store.php"><i class="fas fa-shopping-bag me-1"></i> Mağaza</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_URL; ?>/yazarlar.php">Yazarlar İçin</a>
                </li>
                
                <?php if (isLoggedIn()): ?>
                    <!-- Giriş Yapmış Kullanıcı -->
                    <li class="nav-item ms-lg-3">
                        <a class="btn btn-warning fw-bold text-dark px-4 rounded-pill shadow-sm w-100" href="<?php echo BASE_URL; ?>/dashboard.php">
                            <i class="fas fa-book-reader me-2"></i>Kütüphanem
                        </a>
                    </li>
                    <li class="nav-item ms-lg-2">
                        <a class="nav-link text-white-50" href="<?php echo BASE_URL; ?>/logout.php" title="Çıkış Yap">
                            <i class="fas fa-sign-out-alt fa-lg"></i> <span class="d-lg-none ms-2">Çıkış Yap</span>
                        </a>
                    </li>
                <?php else: ?>
                    <!-- Ziyaretçi -->
                    <li class="nav-item ms-lg-3">
                        <a class="btn-nav-login" href="<?php echo BASE_URL; ?>/login.php">Giriş Yap</a>
                    </li>
                    <li class="nav-item ms-lg-2">
                        <a class="btn-nav-register" href="<?php echo BASE_URL; ?>/register.php">Kayıt Ol</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Bildirim Alanı (Flash Messages) -->
<div class="container alert-container">
    <?php 
    if (function_exists('display_flash_message')) {
        display_flash_message();
    }
    ?>
</div>