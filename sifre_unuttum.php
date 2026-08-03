<?php
// ============================================================
//  sifre_unuttum.php — ŞİFRE SIFIRLAMA TALEBİ
// ============================================================
require_once 'config.php';
require_once 'includes/functions.php';
require_once 'includes/kimlik.php';
oturum_baslat();

if (!empty($_SESSION['kullanici_id'])) { header('Location: /dashboard.php'); exit; }

$hata = '';
$gonderildi = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_kontrol();
    $eposta = trim($_POST['eposta'] ?? '');

    if (!filter_var($eposta, FILTER_VALIDATE_EMAIL)) {
        $hata = 'Geçerli bir e-posta adresi girin.';
    } elseif (!eposta_yapilandirildi_mi()) {
        $hata = 'E-posta gönderimi henüz yapılandırılmamış. Lütfen yönetici ile iletişime geçin.';
    } elseif (!hiz_limiti_gec(hiz_limiti_anahtari('sifre_sifirlama_eposta', $eposta), 3, 900)
           || !hiz_limiti_gec('sifre_sifirlama_ip:' . istemci_ip(), 10, 900)) {
        // Hem adres hem IP başına sınır: aksi halde bu form, başkasının
        // gelen kutusuna spam göndermek için kullanılabilirdi.
        $hata = 'Çok fazla deneme yaptınız. Lütfen 15 dakika sonra tekrar deneyin.';
    } else {
        $stmt = db()->prepare("SELECT id, eposta, eposta_dogrulandi FROM kullanicilar WHERE eposta = ?");
        $stmt->execute([$eposta]);
        $k = $stmt->fetch();

        // Yalnızca DOĞRULANMIŞ adrese gönderilir: doğrulanmamış bir adres
        // yazım hatası olabilir ve sıfırlama bağlantısı yabancı birine gider.
        if ($k && (int)$k['eposta_dogrulandi'] === 1) {
            sifre_sifirlama_gonder((int)$k['id'], $k['eposta']);
            denetim_yaz('sifre_sifirlama_talebi', 'kullanici', (int)$k['id'], [], (int)$k['id']);
        } else {
            denetim_yaz('sifre_sifirlama_talebi_eslesmedi', null, null, ['eposta_hash' => hash('sha256', $eposta)]);
        }

        // Kullanıcı bulunsa da bulunmasa da AYNI mesaj gösterilir —
        // aksi halde bu form, hangi e-postaların sistemde kayıtlı
        // olduğunu keşfetmek için kullanılabilirdi.
        $gonderildi = true;
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="theme-color" content="#0d0d1a">
  <title>Şifremi Unuttum — AYS</title>
  <meta name="robots" content="noindex, follow">
  <link rel="manifest" href="/manifest.json">
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
    <p class="auth-subtitle">Şifre Sıfırlama</p>
  </div>

  <?php if ($gonderildi): ?>
    <div class="alert alert-success">✓ Bu adres sistemde kayıtlı ve doğrulanmışsa, şifre sıfırlama bağlantısı gönderildi.</div>
    <p style="font-size:13px;color:var(--muted);line-height:1.7;margin-bottom:18px">
      E-posta birkaç dakika içinde gelmezse <strong>spam/gereksiz</strong> klasörünü kontrol edin.
      Bağlantı <?= (int)round(SIFRE_SIFIRLAMA_OMRU / 60) ?> dakika geçerlidir.
    </p>
    <a href="/login.php" class="btn btn-primary btn-block">Giriş Sayfasına Dön</a>
  <?php else: ?>
    <?php if ($hata): ?><div class="alert alert-error">✕ <?= e($hata) ?></div><?php endif; ?>

    <p style="font-size:13px;color:var(--muted);line-height:1.7;margin-bottom:18px">
      Hesabınıza kayıtlı e-posta adresini girin; şifrenizi yenilemeniz için bir bağlantı gönderelim.
    </p>

    <form method="post" class="auth-form">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <div class="form-group">
        <label>E-posta Adresi</label>
        <input type="email" name="eposta" class="input" placeholder="ornek@mail.com"
               value="<?= e($_POST['eposta'] ?? '') ?>" required autofocus>
      </div>
      <button type="submit" class="btn btn-primary btn-block">Sıfırlama Bağlantısı Gönder</button>
    </form>
  <?php endif; ?>

  <p style="text-align:center;margin-top:20px;font-size:12.5px;color:var(--muted)">
    <a href="/login.php" style="color:var(--muted)">← Giriş sayfasına dön</a>
  </p>
</div>

</body>
</html>
