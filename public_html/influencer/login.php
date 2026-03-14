<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

if (isInfluencerLoggedIn()) {
    redirect('influencer/dashboard.php');
}

$page_title = 'Influencer Girişi';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Güvenlik doğrulaması başarısız.';
    } else {
        $username = strtolower(trim((string) ($_POST['kullanici_adi'] ?? '')));
        $password = (string) ($_POST['sifre'] ?? '');

        if ($username === '' || $password === '') {
            $error = 'Kullanıcı adı ve şifre zorunludur.';
        } else {
            $stmt = $pdo->prepare('SELECT * FROM influencers WHERE kullanici_adi = ? LIMIT 1');
            $stmt->execute([$username]);
            $infl = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$infl || (int) $infl['aktif_mi'] !== 1 || !password_verify($password, (string) $infl['sifre_hash'])) {
                $error = 'Giriş bilgileri hatalı.';
            } else {
                $_SESSION['influencer_logged_in'] = true;
                $_SESSION['influencer_id'] = (int) $infl['id'];
                $_SESSION['influencer_name'] = (string) $infl['ad_soyad'];
                $_SESSION['influencer_username'] = (string) $infl['kullanici_adi'];
                redirect('influencer/dashboard.php');
            }
        }
    }
}

include_once __DIR__ . '/../templates/header.php';
?>
<div class="container py-5" style="max-width:560px;">
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4 p-md-5">
            <h1 class="h4 fw-bold mb-3">Influencer Panel Girişi</h1>
            <?php if ($error): ?><div class="alert alert-danger"><?php echo escape_html($error); ?></div><?php endif; ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo escape_html(generate_csrf_token()); ?>">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Kullanıcı Adı</label>
                    <input type="text" name="kullanici_adi" class="form-control" required>
                </div>
                <div class="mb-4">
                    <label class="form-label small fw-bold">Şifre</label>
                    <input type="password" name="sifre" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Giriş Yap</button>
            </form>
        </div>
    </div>
</div>
<?php include_once __DIR__ . '/../templates/footer.php'; ?>
