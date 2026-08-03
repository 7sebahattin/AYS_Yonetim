<?php
// ============================================================
//  sifre_yenile.php — YENİ ŞİFRE BELİRLEME (jetonlu)
// ============================================================
require_once 'config.php';
require_once 'includes/functions.php';
require_once 'includes/kimlik.php';
oturum_baslat();

$hata      = '';
$basarili  = false;
$ham_jeton = $_GET['jeton'] ?? ($_POST['jeton'] ?? '');
$kayit     = $ham_jeton !== '' ? kimlik_jetonu_dogrula($ham_jeton, 'sifre_sifirlama') : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $kayit) {
    csrf_kontrol();
    $yeni  = $_POST['yeni_sifre'] ?? '';
    $yeni2 = $_POST['yeni_sifre2'] ?? '';

    if (strlen($yeni) < 6) {
        $hata = 'Şifre en az 6 karakter olmalıdır.';
    } elseif ($yeni !== $yeni2) {
        $hata = 'Şifreler eşleşmiyor.';
    } else {
        $db = db();
        $db->beginTransaction();
        try {
            $db->prepare("UPDATE kullanicilar SET sifre_hash = ? WHERE id = ?")
               ->execute([password_hash($yeni, PASSWORD_DEFAULT), (int)$kayit['id']]);

            kimlik_jetonu_tuket((int)$kayit['jeton_id'], (int)$kayit['id'], 'sifre_sifirlama');

            // Şifre değiştiğinde tüm "Beni Hatırla" oturumları düşürülür:
            // şifre ele geçirildiği için sıfırlanıyor olabilir, saldırganın
            // açık kalan kalıcı oturumu da kapanmalı.
            $db->prepare("DELETE FROM hatirlama_jetonlari WHERE kullanici_id = ?")
               ->execute([(int)$kayit['id']]);

            $db->commit();
            denetim_yaz('sifre_sifirlandi', 'kullanici', (int)$kayit['id'], [], (int)$kayit['id']);

            if (!empty($kayit['eposta'])) sifre_degisti_bildir($kayit['eposta']);
            $basarili = true;
        } catch (Throwable $ex) {
            $db->rollBack();
            error_log('Şifre sıfırlama hatası: ' . $ex->getMessage());
            $hata = 'Şifre güncellenirken bir hata oluştu. Lütfen tekrar deneyin.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="theme-color" content="#0d0d1a">
  <title>Yeni Şifre Belirle — AYS</title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="icon" href="/assets/icons/favicon-32.png?v=2" sizes="32x32">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/style.css?v=2">
</head>
<body class="auth-body">

<div class="auth-bg">
  <?php for ($i = 0; $i < 5; $i++): ?><div class="auth-orb auth-orb-<?= $i ?>"></div><?php endfor; ?>
</div>

<div class="auth-card">
  <div class="auth-header">
    <a href="/" aria-label="Ana sayfaya dön">
      <div class="auth-logo">🔑</div>
      <h1 class="auth-title">AYS</h1>
    </a>
    <p class="auth-subtitle">Yeni Şifre Belirle</p>
  </div>

  <?php if ($basarili): ?>
    <div class="alert alert-success">✓ Şifreniz güncellendi. Artık yeni şifrenizle giriş yapabilirsiniz.</div>
    <a href="/login.php" class="btn btn-primary btn-block">Giriş Yap</a>

  <?php elseif (!$kayit): ?>
    <div class="alert alert-error">✕ Bu bağlantı geçersiz veya süresi dolmuş.</div>
    <p style="font-size:13px;color:var(--muted);line-height:1.7;margin-bottom:18px">
      Sıfırlama bağlantıları <?= (int)round(SIFRE_SIFIRLAMA_OMRU / 60) ?> dakika geçerlidir ve
      yalnızca bir kez kullanılabilir. Yeni bir bağlantı talep edebilirsiniz.
    </p>
    <a href="/sifre_unuttum.php" class="btn btn-primary btn-block">Yeni Bağlantı İste</a>

  <?php else: ?>
    <?php if ($hata): ?><div class="alert alert-error">✕ <?= e($hata) ?></div><?php endif; ?>
    <p style="font-size:13px;color:var(--muted);line-height:1.7;margin-bottom:4px">
      <strong style="color:var(--text)"><?= e($kayit['kullanici_adi']) ?></strong> hesabı için yeni şifre belirleyin.
    </p>
    <form method="post" class="auth-form">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="jeton" value="<?= e($ham_jeton) ?>">
      <div class="form-group">
        <label>Yeni Şifre</label>
        <input type="password" name="yeni_sifre" class="input" required minlength="6"
               placeholder="En az 6 karakter" autofocus>
      </div>
      <div class="form-group">
        <label>Yeni Şifre Tekrar</label>
        <input type="password" name="yeni_sifre2" class="input" required placeholder="••••••••">
      </div>
      <button type="submit" class="btn btn-primary btn-block">Şifreyi Güncelle</button>
    </form>
  <?php endif; ?>
</div>

</body>
</html>
