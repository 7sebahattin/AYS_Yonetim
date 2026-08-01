<?php
// ============================================================
//  eposta_dogrula.php — E-POSTA ADRESİ DOĞRULAMA (jetonlu)
// ============================================================
require_once 'config.php';
require_once 'includes/functions.php';
require_once 'includes/kimlik.php';
oturum_baslat();

$ham_jeton = $_GET['jeton'] ?? '';
$kayit     = $ham_jeton !== '' ? kimlik_jetonu_dogrula($ham_jeton, 'eposta_dogrulama') : null;
$basarili  = false;

if ($kayit) {
    db()->prepare("UPDATE kullanicilar SET eposta_dogrulandi = 1 WHERE id = ?")
       ->execute([(int)$kayit['id']]);
    kimlik_jetonu_tuket((int)$kayit['jeton_id'], (int)$kayit['id'], 'eposta_dogrulama');
    denetim_yaz('eposta_dogrulandi', 'kullanici', (int)$kayit['id'], [], (int)$kayit['id']);
    $basarili = true;
}

$oturum_acik = !empty($_SESSION['kullanici_id']);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="theme-color" content="#0d0d1a">
  <title>E-posta Doğrulama — AYS</title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="icon" href="/assets/icons/favicon-32.png" sizes="32x32">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/style.css">
</head>
<body class="auth-body">

<div class="auth-bg">
  <?php for ($i = 0; $i < 5; $i++): ?><div class="auth-orb auth-orb-<?= $i ?>"></div><?php endfor; ?>
</div>

<div class="auth-card">
  <div class="auth-header">
    <a href="/" aria-label="Ana sayfaya dön">
      <div class="auth-logo"><?= $basarili ? '✅' : '⚠️' ?></div>
      <h1 class="auth-title">AYS</h1>
    </a>
    <p class="auth-subtitle">E-posta Doğrulama</p>
  </div>

  <?php if ($basarili): ?>
    <div class="alert alert-success">✓ E-posta adresiniz doğrulandı.</div>
    <p style="font-size:13px;color:var(--muted);line-height:1.7;margin-bottom:18px">
      Şifrenizi unutursanız artık bu adrese kurtarma bağlantısı gönderebiliriz.
    </p>
  <?php else: ?>
    <div class="alert alert-error">✕ Bu doğrulama bağlantısı geçersiz veya süresi dolmuş.</div>
    <p style="font-size:13px;color:var(--muted);line-height:1.7;margin-bottom:18px">
      Doğrulama bağlantıları <?= (int)round(EPOSTA_DOGRULAMA_OMRU / 3600) ?> saat geçerlidir.
      Ayarlar sayfasından yeni bir doğrulama e-postası gönderebilirsiniz.
    </p>
  <?php endif; ?>

  <a href="<?= $oturum_acik ? '/ayarlar.php' : '/login.php' ?>" class="btn btn-primary btn-block">
    <?= $oturum_acik ? 'Ayarlara Git' : 'Giriş Yap' ?>
  </a>
</div>

</body>
</html>
