<?php
// loading_screen.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php'; // isLoggedIn, redirect, escape_html

// Bu sayfaya doğrudan erişimi veya katilim_id olmadan erişimi engelle
// Genellikle activate_exam.php'den yönlendirme ile gelinir.
// requireLogin(); // Kullanıcının hala giriş yapmış olduğundan emin olalım.

$katilim_id = filter_input(INPUT_GET, 'katilim_id', FILTER_VALIDATE_INT);

if (!$katilim_id) {
    // Eğer katilim_id yoksa, bir hata mesajı gösterip dashboard'a yönlendirebiliriz
    // veya basitçe dashboard'a yönlendirebiliriz.
    // set_flash_message('error', 'Yükleme ekranına geçersiz erişim.');
    redirect('dashboard.php');
}

// Kullanıcının bu katilima gerçekten sahip olup olmadığını kontrol etmek
// güvenlik açısından iyi bir adımdır, ancak activate_exam.php bunu zaten
// yapmış olmalı. Şimdilik basit tutuyoruz.

$page_title = "Sınav Hazırlanıyor";
// Bootstrap'li header'ı kullanalım
include_once __DIR__ . '/templates/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-sm text-center">
                <div class="card-header card-header-theme-warning">
                    <h4 class="mb-0 text-dark-gold">Sınavınız Hazırlanıyor...</h4>
                </div>
                <div class="card-body p-4">
                    <div id="loadingMessages">
                        <p class="loading-message fs-5 mb-3" style="display: none;">IP adresiniz kaydediliyor...</p>
                        <p class="loading-message fs-5 mb-3" style="display: none;">Kodunuz onaylanıyor...</p>
                        <p class="loading-message fs-5 mb-3" style="display: none;">Kodunuz onaylandı.</p>
                        <p class="loading-message fs-5 mb-3" style="display: none;">Size özel çözüm dökümanı üretiliyor...</p>
                        <p class="loading-message fs-5 mb-3" id="transparentCodesMessage" style="display: none;">Çözüm dökümanına şeffaf kodlar yerleştiriliyor...</p>
                        <p class="loading-message fs-5 mb-3" style="display: none;">Her şey tamam!</p>
                    </div>
                    <div class="progress mt-3" role="progressbar" aria-label="Yükleme İlerlemesi" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="height: 25px; display:none;" id="progressBarContainer">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-warning text-dark" id="progressBar" style="width: 0%; font-weight: bold;">0%</div>
                    </div>
                    <p id="redirectMessage" class="mt-4 text-muted" style="display: none;">Sınava yönlendiriliyorsunuz...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const messages = document.querySelectorAll('#loadingMessages .loading-message');
    const progressBarContainer = document.getElementById('progressBarContainer');
    const progressBar = document.getElementById('progressBar');
    const redirectMessage = document.getElementById('redirectMessage');
    const katilimId = <?php echo json_encode($katilim_id); ?>;
    let currentMessageIndex = 0;
    let progressValue = 0;
    const totalSteps = messages.length + 1; // Mesajlar + son yönlendirme adımı

    function showNextMessage() {
        if (currentMessageIndex < messages.length) {
            messages[currentMessageIndex].style.display = 'block';
            
            // İlerleme çubuğunu güncelle
            progressValue = Math.round(((currentMessageIndex + 1) / totalSteps) * 100);
            progressBar.style.width = progressValue + '%';
            progressBar.textContent = progressValue + '%';
            progressBarContainer.style.display = 'flex';


            let delay = 1500; // Varsayılan gecikme (1.5 saniye)
            if (messages[currentMessageIndex].id === 'transparentCodesMessage') {
                delay = 10000; // "Şeffaf kodlar" için 10 saniye bekle
            }

            currentMessageIndex++;
            setTimeout(showNextMessage, delay);
        } else {
            // Tüm mesajlar gösterildi, yönlendirme yap
            progressBar.style.width = '100%';
            progressBar.textContent = '100%';
            progressBar.classList.remove('progress-bar-animated');
            progressBar.classList.add('bg-success'); // Tamamlandığında yeşil yap
            
            redirectMessage.style.display = 'block';
            setTimeout(function() {
                window.location.href = '<?php echo BASE_URL; ?>/exam.php?katilim_id=' + katilimId;
            }, 1500); // Yönlendirme öncesi kısa bir bekleme
        }
    }

    // İlk mesajı göstermeye başla
    setTimeout(showNextMessage, 500); // Sayfa yüklendikten sonra yarım saniye bekle
});
</script>

<?php
// Bootstrap'li footer'ı kullanalım
include_once __DIR__ . '/templates/footer.php';
?>
