<?php
// exam.php (Katılım ID ile Sınav Sayfası - Modern Dashboard Teması ile)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$katilim_id = filter_input(INPUT_GET, 'katilim_id', FILTER_VALIDATE_INT);
if (!$katilim_id) {
    set_flash_message('error', 'Geçersiz katılım ID.');
    redirect('dashboard.php');
}

$user_id = $_SESSION['user_id'];

try {
    $stmt_katilim_info = $pdo->prepare("
        SELECT 
            kk.id AS katilim_id, 
            kk.deneme_id,
            kk.sinav_tamamlama_tarihi,
            d.deneme_adi, 
            d.soru_sayisi,
            d.aktif_mi AS deneme_aktif_mi
        FROM kullanici_katilimlari kk
        JOIN denemeler d ON kk.deneme_id = d.id
        WHERE kk.id = :katilim_id AND kk.kullanici_id = :user_id
    ");
    $stmt_katilim_info->execute([':katilim_id' => $katilim_id, ':user_id' => $user_id]);
    $katilim_detaylari = $stmt_katilim_info->fetch();

    if (!$katilim_detaylari) {
        set_flash_message('error', 'Geçersiz katılım ID veya bu katılım size ait değil.');
        redirect('dashboard.php');
    }

    if (!$katilim_detaylari['deneme_aktif_mi']) {
        set_flash_message('error', 'Bu deneme artık aktif değil.');
        redirect('dashboard.php');
    }

    if (!empty($katilim_detaylari['sinav_tamamlama_tarihi'])) {
        set_flash_message('info', 'Bu denemeyi zaten tamamladınız. Sonuçlarınızı panelden görebilirsiniz.');
        redirect('results.php?katilim_id=' . $katilim_id);
    }

    $deneme_id = $katilim_detaylari['deneme_id'];
    $deneme_adi = $katilim_detaylari['deneme_adi'];
    $soru_sayisi = $katilim_detaylari['soru_sayisi'];

    $stmt_saved_answers = $pdo->prepare("SELECT soru_no, verilen_cevap FROM kullanici_cevaplari WHERE katilim_id = ?");
    $stmt_saved_answers->execute([$katilim_id]);
    $saved_answers_raw = $stmt_saved_answers->fetchAll();
    $saved_answers = [];
    foreach($saved_answers_raw as $sa) {
        $saved_answers[$sa['soru_no']] = $sa['verilen_cevap'];
    }

} catch (PDOException $e) {
    error_log("Sınav sayfası hatası (katilim_id: $katilim_id): " . $e->getMessage());
    set_flash_message('error', 'Sınav yüklenirken bir sorun oluştu.');
    redirect('dashboard.php');
}

$page_title = $deneme_adi;
$csrf_token = generate_csrf_token();
$options = ['A', 'B', 'C', 'D', 'E'];
$exam_duration_minutes = $soru_sayisi * 1.5; 

include_once __DIR__ . '/templates/header.php'; // Bootstrap'li header (yeni style.css'i çağıracak)
?>

<div class="text-center mb-4 pt-2"> 
    <h2 class="display-6 text-theme-primary fw-bold"><?php echo escape_html($deneme_adi); ?></h2>
    <p class="lead text-theme-secondary">Lütfen cevaplarınızı aşağıdaki optik forma işaretleyiniz.</p>
    <p class="text-theme-secondary">Toplam Soru: <?php echo $soru_sayisi; ?> | Süre: <?php echo $exam_duration_minutes; ?> Dakika</p>
</div>

<div id="timer" class="timer-bs sticky-top py-2 mb-4 shadow-sm">Süre Başlatılıyor...</div>

<form id="examForm" action="submit_exam.php" method="POST">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="katilim_id" value="<?php echo $katilim_id; ?>">

    <div class="row">
        <?php for ($i = 1; $i <= $soru_sayisi; $i++): ?>
            <div class="col-md-6 mb-4">
                <div class="card h-100 shadow-sm question-item-bs"> 
                    <div class="card-body">
                        <h5 class="card-title">Soru <?php echo $i; ?>:</h5> 
                        <div class="mt-3">
                            <?php foreach ($options as $option_key => $option_val): ?>
                                <div class="form-check form-check-inline mb-2">
                                    <input class="form-check-input form-check-input-theme" type="radio" 
                                           name="cevaplar[<?php echo $i; ?>]" 
                                           id="q<?php echo $i; ?>_opt<?php echo $option_key; ?>" 
                                           value="<?php echo $option_val; ?>"
                                           <?php echo (isset($saved_answers[$i]) && $saved_answers[$i] === $option_val) ? 'checked' : ''; ?>>
                                    <label class="form-check-label text-theme-dark" for="q<?php echo $i; ?>_opt<?php echo $option_key; ?>">
                                        <?php echo $option_val; ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                            <div class="form-check form-check-inline mb-2">
                                <input class="form-check-input form-check-input-theme" type="radio" 
                                       name="cevaplar[<?php echo $i; ?>]" 
                                       id="q<?php echo $i; ?>_opt_bos" 
                                       value="" 
                                       <?php echo (!isset($saved_answers[$i]) || $saved_answers[$i] === null || $saved_answers[$i] === '') ? 'checked' : ''; ?>>
                                <label class="form-check-label text-theme-dark" for="q<?php echo $i; ?>_opt_bos">
                                    Boş
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endfor; ?>
    </div>

    <div class="text-center mt-4 mb-5">
        <button type="submit" id="submitExamBtn" class="btn btn-theme-primary btn-lg px-5">Sınavı Bitir ve Cevapları Gönder</button>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const examDurationMinutes = <?php echo $exam_duration_minutes; ?>;
    let timeLeft = examDurationMinutes * 60;
    const timerDisplay = document.getElementById('timer');
    const examForm = document.getElementById('examForm');
    const submitExamBtn = document.getElementById('submitExamBtn');

    function updateTimerDisplay() {
        const hours = Math.floor(timeLeft / 3600);
        const minutes = Math.floor((timeLeft % 3600) / 60);
        const seconds = timeLeft % 60;
        timerDisplay.textContent = 
            `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    }

    const timerInterval = setInterval(() => {
        timeLeft--;
        updateTimerDisplay();
        if (timeLeft <= 0) {
            clearInterval(timerInterval);
            timerDisplay.textContent = "Süre Doldu!";
            // Süre dolduğunda timer'a Bootstrap danger stili uygula
            timerDisplay.classList.remove('bg-light', 'text-primary'); // Önceki stilleri kaldır (varsa)
            timerDisplay.classList.add('bg-danger-subtle', 'text-danger-emphasis', 'border-danger-subtle');
            alert("Sınav süreniz doldu! Cevaplarınız otomatik olarak gönderiliyor.");
            examForm.submit(); 
        }
    }, 1000);

    updateTimerDisplay(); 

    submitExamBtn.addEventListener('click', function(event) {
        if (!confirm('Sınavı bitirmek ve cevaplarınızı göndermek istediğinizden emin misiniz? Bu işlem geri alınamaz.')) {
            event.preventDefault(); 
        }
    });
});
</script>
<?php
include_once __DIR__ . '/templates/footer.php'; 
?>
