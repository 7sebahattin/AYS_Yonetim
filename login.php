<?php
// ============================================================
//  login.php — GİRİŞ / KAYIT SAYFASI
//  (Tanıtım/landing sayfası için index.php'ye bakınız)
// ============================================================
require_once 'config.php';
require_once 'includes/functions.php';
oturum_baslat();

// Zaten giriş yapmışsa dashboard'a yönlendir
if (!empty($_SESSION['kullanici_id'])) {
    header('Location: /dashboard.php');
    exit;
}

$hata   = '';
$basari = '';
$mod    = $_GET['mod'] ?? 'giris'; // 'giris' | 'kayit'

// ─── Dönem süresi doldu mesajı ───────────────────────────────
if (isset($_GET['mesaj']) && $_GET['mesaj'] === 'suresi_doldu') {
    $hata = 'Oturum süreniz doldu. Lütfen tekrar giriş yapın.';
}

// ─── FORM İŞLEME ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_kontrol();
    $islem = $_POST['islem'] ?? '';

    // ── Giriş ────────────────────────────────────────────────
    if ($islem === 'giris') {
        $kullanici_adi = trim($_POST['kullanici_adi'] ?? '');
        $sifre         = $_POST['sifre'] ?? '';

        if (!$kullanici_adi || !$sifre) {
            $hata = 'Kullanıcı adı ve şifre zorunludur.';
        } else {
            $stmt = db()->prepare("SELECT * FROM kullanicilar WHERE kullanici_adi = ?");
            $stmt->execute([$kullanici_adi]);
            $k = $stmt->fetch();

            if ($k && password_verify($sifre, $k['sifre_hash'])) {
                session_regenerate_id(true);
                $_SESSION['kullanici_id']  = $k['id'];
                $_SESSION['kullanici_adi'] = $k['kullanici_adi'];
                $_SESSION['apartman_adi']  = $k['apartman_adi'];
                $_SESSION['toplam_daire']  = $k['toplam_daire'];
                $_SESSION['tema']          = $k['tema'] ?? 'koyu'; 
                $_SESSION['son_islem']     = time();

                // Log kaydet
                db()->prepare("INSERT INTO oturum_loglari (kullanici_id, ip_adresi) VALUES (?, ?)")
                    ->execute([$k['id'], $_SERVER['REMOTE_ADDR'] ?? '']);

                header('Location: /dashboard.php');
                exit;
            } else {
                $hata = 'Kullanıcı adı veya şifre hatalı.';
            }
        }
    }

    // ── Kayıt ────────────────────────────────────────────────
    if ($islem === 'kayit') {
        $kullanici_adi  = trim($_POST['kullanici_adi'] ?? '');
        $sifre          = $_POST['sifre'] ?? '';
        $sifre2         = $_POST['sifre2'] ?? '';
        $apartman_adi   = trim($_POST['apartman_adi'] ?? '');
        $toplam_daire   = max(1, min(200, (int)($_POST['toplam_daire'] ?? 10)));

        if (!$kullanici_adi || !$sifre || !$apartman_adi) {
            $hata = 'Tüm zorunlu alanları doldurun.';
        } elseif (strlen($sifre) < 6) {
            $hata = 'Şifre en az 6 karakter olmalıdır.';
        } elseif ($sifre !== $sifre2) {
            $hata = 'Şifreler eşleşmiyor.';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $kullanici_adi)) {
            $hata = 'Kullanıcı adı sadece harf, rakam ve _ içerebilir.';
        } else {
            // Kullanıcı adı müsait mi?
            $stmt = db()->prepare("SELECT id FROM kullanicilar WHERE kullanici_adi = ?");
            $stmt->execute([$kullanici_adi]);
            if ($stmt->fetch()) {
                $hata = 'Bu kullanıcı adı zaten kullanımda.';
            } else {
                $pdo = db();
                $pdo->beginTransaction();
                try {
                    // Kullanıcı oluştur
                    $pdo->prepare("INSERT INTO kullanicilar (kullanici_adi, sifre_hash, apartman_adi, toplam_daire)
                                   VALUES (?, ?, ?, ?)")
                        ->execute([$kullanici_adi, password_hash($sifre, PASSWORD_DEFAULT),
                                   $apartman_adi, $toplam_daire]);
                    $yeni_id = (int)$pdo->lastInsertId();

                    // Daireları oluştur
                    $ins = $pdo->prepare("INSERT INTO daireler (kullanici_id, daire_no, aylik_aidat) VALUES (?, ?, 500)");
                    for ($i = 1; $i <= $toplam_daire; $i++) {
                        $ins->execute([$yeni_id, $i]);
                    }
                    $pdo->commit();
                    $basari = 'Kaydınız oluşturuldu! Giriş yapabilirsiniz.';
                    $mod = 'giris';
                } catch (Exception $ex) {
                    $pdo->rollBack();
                    $hata = 'Kayıt sırasında hata oluştu. Lütfen tekrar deneyin.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Giriş Yap — AYS Apartman Yönetim Sistemi</title>
  <!-- Giriş ekranı arama sonuçlarında görünmemeli; tanıtım için index.php indekslenir -->
  <meta name="robots" content="noindex, follow">
  <link rel="canonical" href="/login.php">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/style.css">
</head>
<body class="auth-body">

<div class="auth-bg">
  <?php for ($i = 0; $i < 5; $i++): ?>
    <div class="auth-orb auth-orb-<?= $i ?>"></div>
  <?php endfor; ?>
</div>

<div class="auth-card">
  <div class="auth-header">
    <a href="/" aria-label="Ana sayfaya dön">
      <div class="auth-logo">🏢</div>
      <h1 class="auth-title">AYS</h1>
    </a>
    <p class="auth-subtitle">Apartman Yönetim Sistemi</p>
  </div>

  <div class="tab-bar">
    <a href="?mod=giris" class="tab-btn <?= $mod === 'giris' ? 'active' : '' ?>">Giriş Yap</a>
    <a href="?mod=kayit" class="tab-btn <?= $mod === 'kayit' ? 'active' : '' ?>">Yeni Kayıt</a>
  </div>

  <?php if ($hata): ?>
    <div class="alert alert-error">✕ <?= e($hata) ?></div>
  <?php endif; ?>
  <?php if ($basari): ?>
    <div class="alert alert-success">✓ <?= e($basari) ?></div>
  <?php endif; ?>

  <?php if ($mod === 'giris'): ?>
  <!-- GİRİŞ FORMU -->
  <form method="post" class="auth-form">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="islem" value="giris">
    <div class="form-group">
      <label>Kullanıcı Adı</label>
      <input type="text" name="kullanici_adi" class="input" placeholder="kullanici_adi"
             value="<?= e($_POST['kullanici_adi'] ?? '') ?>" required autofocus>
    </div>
    <div class="form-group">
      <label>Şifre</label>
      <input type="password" name="sifre" class="input" placeholder="••••••••" required>
    </div>
    <button type="submit" class="btn btn-primary btn-block">Giriş Yap</button>
  </form>

  <?php else: ?>
  <!-- KAYIT FORMU -->
  <form method="post" class="auth-form">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="islem" value="kayit">
    <div class="form-group">
      <label>Kullanıcı Adı <span class="req">*</span></label>
      <input type="text" name="kullanici_adi" class="input" placeholder="harf_rakam_alt_cizgi"
             value="<?= e($_POST['kullanici_adi'] ?? '') ?>" required pattern="[a-zA-Z0-9_]+">
    </div>
    <div class="form-group">
      <label>Apartman Adı <span class="req">*</span></label>
      <input type="text" name="apartman_adi" class="input" placeholder="örn: Gül Apartmanı"
             value="<?= e($_POST['apartman_adi'] ?? '') ?>" required>
    </div>
    <div class="form-group">
      <label>Toplam Daire Sayısı</label>
      <input type="number" name="toplam_daire" class="input" min="1" max="200"
             value="<?= e($_POST['toplam_daire'] ?? '10') ?>">
    </div>
    <div class="form-group">
      <label>Şifre <span class="req">*</span></label>
      <input type="password" name="sifre" class="input" placeholder="En az 6 karakter" required minlength="6">
    </div>
    <div class="form-group">
      <label>Şifre Tekrar <span class="req">*</span></label>
      <input type="password" name="sifre2" class="input" placeholder="Şifrenizi tekrar girin" required>
    </div>
    <button type="submit" class="btn btn-primary btn-block">Apartmanımı Kur</button>
  </form>
  <?php endif; ?>

  <p style="text-align:center;margin-top:20px;font-size:12.5px;color:var(--muted)">
    <a href="/" style="color:var(--muted)">← Tanıtım sayfasına dön</a>
  </p>
</div>

</body>
</html>
