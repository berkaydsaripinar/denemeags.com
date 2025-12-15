<?php
// dashboard.php (Modern Dashboard - Denemelerde Soru Kitapçığı İndirme Özelliği ile)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin(); 

$page_title = "Kütüphanem";
$user_id = $_SESSION['user_id'];
$user_ad_soyad = $_SESSION['user_ad_soyad'];
$csrf_token = generate_csrf_token(); 

include_once __DIR__ . '/templates/header.php'; 

// Kullanıcının sahip olduğu ürünleri çek
$my_products = [];
try {
    $stmt_products = $pdo->prepare("
        SELECT 
            d.id, d.deneme_adi, d.tur, d.kisa_aciklama, d.resim_url,
            d.soru_kitapcik_dosyasi, d.cozum_linki,
            ke.erisim_tarihi
        FROM kullanici_erisimleri ke
        JOIN denemeler d ON ke.deneme_id = d.id
        WHERE ke.kullanici_id = :user_id AND d.aktif_mi = 1
        ORDER BY ke.erisim_tarihi DESC
    ");
    $stmt_products->execute([':user_id' => $user_id]);
    $all_products = $stmt_products->fetchAll(PDO::FETCH_ASSOC);

    // Ürünleri türlerine göre ayır
    $denemeler = [];
    $kaynaklar = [];

    foreach ($all_products as $prod) {
        if ($prod['tur'] === 'deneme') {
            $denemeler[] = $prod;
        } else {
            $kaynaklar[] = $prod; // soru_bankasi veya diger
        }
    }

    // Sınav durumlarını kontrol et
    $exam_statuses = [];
    $stmt_exams = $pdo->prepare("SELECT deneme_id, sinav_tamamlama_tarihi, id as katilim_id FROM kullanici_katilimlari WHERE kullanici_id = :user_id");
    $stmt_exams->execute([':user_id' => $user_id]);
    while ($row = $stmt_exams->fetch(PDO::FETCH_ASSOC)) {
        $exam_statuses[$row['deneme_id']] = $row;
    }

} catch (PDOException $e) {
    error_log("Dashboard ürün listeleme hatası: " . $e->getMessage());
    set_flash_message('error', "Kütüphaneniz yüklenirken bir sorun oluştu.");
}
?>

<div class="mb-5 pt-3 text-center"> 
    <h2 class="display-5 text-theme-primary fw-bold">Kütüphanem</h2>
    <p class="lead text-theme-secondary">Hoş geldiniz, <strong class="text-theme-dark"><?php echo escape_html($user_ad_soyad); ?></strong>!</p>
</div>

<!-- Yeni İçerik Ekleme (Kod Girme) Bölümü -->
<div class="row justify-content-center mb-5">
    <div class="col-md-8 col-lg-6">
        <div class="card shadow-sm card-theme border-theme-primary">
            <div class="card-body p-4">
                <h5 class="card-title text-center text-theme-primary mb-3">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-plus-circle-fill me-2" viewBox="0 0 16 16"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0M8.5 4.5a.5.5 0 0 0-1 0v3h-3a.5.5 0 0 0 0 1h3v3a.5.5 0 0 0 1 0v-3h3a.5.5 0 0 0 0-1h-3z"/></svg>
                    Yeni İçerik Ekle
                </h5>
                <form action="add_product_with_code.php" method="POST" class="d-flex gap-2">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="text" name="urun_kodu" class="form-control input-theme text-uppercase" placeholder="Erişim Kodunu Giriniz" required>
                    <button type="submit" class="btn btn-theme-primary px-4">Ekle</button>
                </form>
                <div class="form-text text-center mt-2 text-muted small">Deneme veya soru bankası kodunuzu girerek kütüphanenizi genişletin.</div>
            </div>
        </div>
    </div>
</div>

<!-- Sekmeler (Tabs) -->
<ul class="nav nav-tabs nav-fill mb-4" id="libraryTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active fw-bold" id="denemeler-tab" data-bs-toggle="tab" data-bs-target="#denemeler" type="button" role="tab" aria-controls="denemeler" aria-selected="true">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-pencil-square me-2" viewBox="0 0 16 16"><path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/><path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5v11z"/></svg>
        Deneme Sınavlarım
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link fw-bold" id="kaynaklar-tab" data-bs-toggle="tab" data-bs-target="#kaynaklar" type="button" role="tab" aria-controls="kaynaklar" aria-selected="false">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-book-half me-2" viewBox="0 0 16 16"><path d="M8.5 2.687c.654-.689 1.782-.886 3.112-.752 1.234.124 2.503.523 3.388.893v9.923c-.918-.35-2.107-.692-3.287-.81-1.094-.111-2.278-.039-3.213.492V2.687zM8 1.783C7.015.936 5.587.81 4.287.94c-1.514.153-3.042.672-3.994 1.105A.5.5 0 0 0 0 2.5v11a.5.5 0 0 0 .707.455c.882-.4 2.303-.881 3.68-1.02 1.409-.142 2.59.087 3.223.877a.5.5 0 0 0 .78 0c.633-.79 1.814-1.019 3.222-.877 1.378.139 2.8.62 3.681 1.02A.5.5 0 0 0 16 13.5v-11a.5.5 0 0 0-.293-.455c-.952-.433-2.48-.952-3.994-1.105C10.413.809 8.985.936 8 1.783z"/></svg>
        Soru Bankası / Kaynaklar
    </button>
  </li>
</ul>

<div class="tab-content" id="libraryTabsContent">
    
    <!-- TAB 1: DENEME SINAVLARI -->
    <div class="tab-pane fade show active" id="denemeler" role="tabpanel" aria-labelledby="denemeler-tab">
        <?php if (empty($denemeler)): ?>
            <div class="alert alert-theme-info text-center py-5">
                <h4>Aktif bir deneme sınavınız bulunmuyor.</h4>
                <p>Erişim kodu girerek veya mağazadan satın alarak deneme ekleyebilirsiniz.</p>
                <a href="index.php" class="btn btn-theme-primary mt-3">Mağazaya Git</a>
            </div>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <?php foreach ($denemeler as $product): ?>
                    <div class="col">
                        <div class="card h-100 shadow-sm card-theme">
                            <?php if (!empty($product['resim_url'])): ?>
                                <img src="<?php echo escape_html($product['resim_url']); ?>" class="card-img-top" alt="<?php echo escape_html($product['deneme_adi']); ?>" style="max-height: 180px; object-fit: cover;">
                            <?php endif; ?>
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="badge bg-primary">Deneme Sınavı</span>
                                    <small class="text-muted" style="font-size: 0.75rem;"><?php echo format_tr_datetime($product['erisim_tarihi'], 'd M Y'); ?></small>
                                </div>
                                <h5 class="card-title text-theme-primary mb-2"><?php echo escape_html($product['deneme_adi']); ?></h5>
                                <?php if (!empty($product['kisa_aciklama'])): ?>
                                    <p class="card-text text-theme-dark small flex-grow-1 mb-3"><?php echo nl2br(escape_html($product['kisa_aciklama'])); ?></p>
                                <?php else: ?>
                                    <p class="card-text text-muted small flex-grow-1 mb-3">Açıklama bulunmuyor.</p>
                                <?php endif; ?>
                                
                                <div class="mt-auto d-grid gap-2">
                                    
                                    <!-- YENİ: Deneme Sınavı İçin Soru Kitapçığı İndirme Butonu -->
                                    <?php if (!empty($product['soru_kitapcik_dosyasi'])): ?>
                                        <a href="download_secure_pdf.php?id=<?php echo $product['id']; ?>&type=question" class="btn btn-outline-primary btn-sm">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-earmark-pdf me-1" viewBox="0 0 16 16"><path d="M14 14V4.5L9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2M9.5 3A1.5 1.5 0 0 0 11 4.5h2V14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h5.5z"/><path d="M4.603 14.087a.81.81 0 0 1-.438-.42c-.195-.388-.13-.771.08-1.177.138-.272.367-.554.65-.755a.5.5 0 0 1 .43.86c-.21.15-.382.318-.482.517-.117.233-.057.514.155.686a.5.5 0 0 1-.395.289"/></svg>
                                            Soru Kitapçığı İndir
                                        </a>
                                    <?php endif; ?>

                                    <?php 
                                        $exam_status = $exam_statuses[$product['id']] ?? null;
                                        if ($exam_status): 
                                            // Sınav bitmişse veya devam ediyorsa
                                            if ($exam_status['sinav_tamamlama_tarihi']): ?>
                                                <a href="results.php?katilim_id=<?php echo $exam_status['katilim_id']; ?>" class="btn btn-success btn-sm">Sonuçları Gör / Çözümler</a>
                                            <?php else: ?>
                                                <a href="exam.php?katilim_id=<?php echo $exam_status['katilim_id']; ?>" class="btn btn-warning btn-sm">Sınava Devam Et</a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <!-- Sınav Başlamamış -->
                                            <form action="start_exam.php" method="POST">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                <input type="hidden" name="deneme_id" value="<?php echo $product['id']; ?>">
                                                <button type="submit" class="btn btn-theme-primary btn-sm w-100">Optik Formu Doldur / Sınava Başla</button>
                                            </form>
                                        <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- TAB 2: SORU BANKALARI / KAYNAKLAR -->
    <div class="tab-pane fade" id="kaynaklar" role="tabpanel" aria-labelledby="kaynaklar-tab">
        <?php if (empty($kaynaklar)): ?>
            <div class="alert alert-theme-info text-center py-5">
                <h4>Kütüphanenizde soru bankası bulunmuyor.</h4>
                <p>Erişim kodu ile kaynak ekleyebilirsiniz.</p>
            </div>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <?php foreach ($kaynaklar as $product): ?>
                    <div class="col">
                        <div class="card h-100 shadow-sm card-theme border-theme-primary">
                            <?php if (!empty($product['resim_url'])): ?>
                                <img src="<?php echo escape_html($product['resim_url']); ?>" class="card-img-top" alt="<?php echo escape_html($product['deneme_adi']); ?>" style="max-height: 250px; object-fit: contain; background-color:#f8f9fa;">
                            <?php endif; ?>
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="badge bg-secondary">Soru Bankası</span>
                                </div>
                                <h5 class="card-title text-theme-primary mb-2"><?php echo escape_html($product['deneme_adi']); ?></h5>
                                <p class="card-text text-theme-dark small flex-grow-1 mb-3">
                                    <?php echo !empty($product['kisa_aciklama']) ? nl2br(escape_html($product['kisa_aciklama'])) : 'Kaynak dosyaları aşağıdadır.'; ?>
                                </p>
                                
                                <div class="mt-auto d-grid gap-2">
                                    <!-- Soru Bankası İçin Doğrudan İndirme Linkleri -->
                                    <?php if (!empty($product['soru_kitapcik_dosyasi'])): ?>
                                        <a href="download_secure_pdf.php?id=<?php echo $product['id']; ?>&type=question" class="btn btn-theme-primary btn-sm">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-download me-2" viewBox="0 0 16 16"><path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/><path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z"/></svg>
                                            Soru Dosyasını İndir
                                        </a>
                                    <?php endif; ?>

                                    <?php if (!empty($product['cozum_linki'])): ?>
                                        <a href="download_secure_pdf.php?id=<?php echo $product['id']; ?>&type=solution" class="btn btn-outline-secondary btn-sm">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-journal-check me-2" viewBox="0 0 16 16"><path d="M10.854 6.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7.5 8.793l2.646-2.647a.5.5 0 0 1 .708 0"/><path d="M3 0h10a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2v-1h1v1a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1v1H3V2a2 2 0 0 1 2-2"/><path d="M1 5v-.5a.5.5 0 0 1 1 0V5h.5a.5.5 0 0 1 0 1h-2a.5.5 0 0 1 0-1zm0 3v-.5a.5.5 0 0 1 1 0V9h.5a.5.5 0 0 1 0 1h-2a.5.5 0 0 1 0-1zm0 3v-.5a.5.5 0 0 1 1 0v.5h.5a.5.5 0 0 1 0 1h-2a.5.5 0 0 1 0-1z"/></svg>
                                            Çözüm Dosyasını İndir
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="text-center mt-5 pb-4">
    <a href="logout.php" class="btn btn-danger">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-box-arrow-right me-2" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M10 12.5a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v2a.5.5 0 0 0 1 0v-2A1.5 1.5 0 0 0 9.5 2h-8A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-2a.5.5 0 0 0-1 0z"/><path fill-rule="evenodd" d="M15.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708.708L14.293 7.5H5.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708z"/></svg>
        Çıkış Yap
    </a>
</div>

<?php
include_once __DIR__ . '/templates/footer.php'; 
?>