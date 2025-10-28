<?php
// index.php (Anasayfa - Modern Dashboard Teması ile)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$page_title = "ÖABT Online Deneme Sınavları";
$csrf_token = generate_csrf_token();

$anasayfa_denemeleri = [];
try {
    $stmt_anasayfa = $pdo->query("
        SELECT id, deneme_adi, kisa_aciklama, resim_url, shopier_link 
        FROM denemeler 
        WHERE anasayfada_goster = 1 AND aktif_mi = 1
        ORDER BY id DESC
    ");
    $anasayfa_denemeleri = $stmt_anasayfa->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Anasayfa deneme listeleme hatası: " . $e->getMessage());
}

include_once __DIR__ . '/templates/header.php'; // Bootstrap'li header (yeni style.css'i çağıracak)
?>

<div class="container py-4">
    
    <div class="row">
        <div class="col-lg-8 mb-4 mb-lg-0">
            <div class="p-3 p-md-4 rounded shadow-sm bg-main-content"> 
                <h1 class="display-5 text-center text-theme-primary mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="currentColor" class="bi bi-journals me-2" viewBox="0 0 16 16">
                        <path d="M5 0h8a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2 2 2 0 0 1-2 1H3a2 2 0 0 1-2-2h1a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1H3a1 1 0 0 0-1 1H1a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v9a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1H3a2 2 0 0 1-2-2"/>
                        <path d="M1 6v-.5a.5.5 0 0 1 1 0V6h.5a.5.5 0 0 1 0 1h-2a.5.5 0 0 1 0-1zm0 3v-.5a.5.5 0 0 1 1 0V9h.5a.5.5 0 0 1 0 1h-2a.5.5 0 0 1 0-1zm0 3v-.5a.5.5 0 0 1 1 0v.5h.5a.5.5 0 0 1 0 1h-2a.5.5 0 0 1 0-1z"/>
                    </svg>
                    Deneme Sınavlarımız
                </h1>
                <?php if (empty($anasayfa_denemeleri)): ?>
                    <div class="alert alert-theme-info text-center" role="alert"> 
                        Şu anda anasayfada gösterilecek aktif bir deneme sınavı bulunmamaktadır.
                    </div>
                <?php else: ?>
                    <div class="row row-cols-1 row-cols-md-2 g-4">
                        <?php foreach ($anasayfa_denemeleri as $deneme): ?>
                            <div class="col">
                                <div class="card h-100 shadow-sm card-theme"> 
                                    <?php if (!empty($deneme['resim_url'])): ?>
                                        <img src="<?php echo escape_html($deneme['resim_url']); ?>" class="card-img-top" alt="<?php echo escape_html($deneme['deneme_adi']); ?>" style="max-height: 220px; object-fit: cover;">
                                    <?php else: ?>
                                        {/* Yeni temaya uygun placeholder */}
                                        <img src="https://placehold.co/600x220/E0E7FF/4A69FF?text=Deneme+Görseli" class="card-img-top" alt="Deneme Görseli Yok" style="max-height: 220px; object-fit: cover;">
                                    <?php endif; ?>
                                    <div class="card-body d-flex flex-column">
                                        <h5 class="card-title text-theme-primary"><?php echo escape_html($deneme['deneme_adi']); ?></h5>
                                        <?php if (!empty($deneme['kisa_aciklama'])): ?>
                                            <p class="card-text text-theme-dark small flex-grow-1"><?php echo nl2br(escape_html($deneme['kisa_aciklama'])); ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($deneme['shopier_link'])): ?>
                                            <a href="<?php echo escape_html($deneme['shopier_link']); ?>" class="btn btn-theme-primary w-100 mt-auto" target="_blank" rel="noopener noreferrer">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cart-fill me-2" viewBox="0 0 16 16">
                                                  <path d="M0 1.5A.5.5 0 0 1 .5 1H2a.5.5 0 0 1 .485.379L2.89 3H14.5a.5.5 0 0 1 .491.592l-1.5 8A.5.5 0 0 1 13 12H4a.5.5 0 0 1-.491-.408L2.01 3.607 1.61 2H.5a.5.5 0 0 1-.5-.5M5 12a2 2 0 1 0 0 4 2 2 0 0 0 0-4m7 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4m-7 1a1 1 0 1 1 0 2 1 1 0 0 1 0-2m7 0a1 1 0 1 1 0 2 1 1 0 0 1 0-2"/>
                                                </svg>
                                                Satın Al
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm card-theme"> 
                <div class="card-body p-4">
                    <h3 class="card-title text-center text-theme-primary mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-person-circle me-2" viewBox="0 0 16 16">
                            <path d="M11 6a3 3 0 1 1-6 0 3 3 0 0 1 6 0"/>
                            <path fill-rule="evenodd" d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8m8-7a7 7 0 0 0-5.468 11.37C3.242 11.226 4.805 10 8 10s4.757 1.225 5.468 2.37A7 7 0 0 0 8 1"/>
                        </svg>
                        Giriş Yap
                    </h3>
                    <form action="login.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="login">
                        <div class="mb-3">
                            <label for="email_login" class="form-label text-theme-secondary">E-posta:</label>
                            <input type="email" class="form-control input-theme" id="email_login" name="email" placeholder="ornek@eposta.com" required>
                        </div>
                        <div class="mb-3">
                            <label for="password_login" class="form-label text-theme-secondary">Şifre:</label>
                            <input type="password" class="form-control input-theme" id="password_login" name="password" required>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-theme-primary btn-lg">Giriş Yap</button>
                        </div>
                    </form>
                    <hr class="my-4">
                    <p class="text-center text-theme-dark">Hesabınız yok mu? <a href="register.php" class="fw-bold text-theme-primary">Hemen Kayıt Olun</a></p>
                </div>
            </div>
        </div>
    </div>

</div> 
<?php
include_once __DIR__ . '/templates/footer.php'; 
?>
