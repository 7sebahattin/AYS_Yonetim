<?php
// ============================================================
//  yonetim/iki_faktor.php — TOTP KURULUMU
//
//  Bu sayfa bilinçli olarak PANEL OTURUMU İSTEMEZ; normal uygulama
//  oturumu yeterlidir. Sebep: panele girmek için 2FA zorunlu, 2FA'yı
//  kurmak için de panele girmek gerekseydi kilitli bir döngü olurdu.
//
//  Yine de yalnızca platform rolü olan hesaplar erişebilir ve gizli
//  anahtar, kullanıcı geçerli bir kod üretebildiğini KANITLAYANA kadar
//  veritabanına yazılmaz — yanlış kurulmuş bir doğrulayıcı yüzünden
//  hesabın kilitlenmesi böyle önlenir.
//
//  QR kodu yerine anahtarın kendisi gösterilir: QR üretmek için harici
//  bir servise başvurmak, TOTP gizli anahtarını üçüncü tarafa
//  göndermek anlamına gelirdi.
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform.php';
require_once __DIR__ . '/../includes/totp.php';

$kullanici = giris_kontrol();
header('X-Robots-Tag: noindex, nofollow', true);

if (!platform_semasi_hazir_mi()) {
    http_response_code(503);
    die('Platform şeması hazır değil. Önce 004 numaralı göçü uygulayın.');
}

$rol = platform_rolu($kullanici['id']);
if (!platform_yetkili_mi($rol)) {
    http_response_code(403);
    die('<div style="font-family:sans-serif;padding:40px"><h2>Bu sayfaya erişim yetkiniz yok</h2>'
      . '<p><a href="/dashboard.php">Panele dön</a></p></div>');
}

// Kimliğe bürünürken kendi 2FA ayarına dokunulamaz — hedef kullanıcının
// oturumundayken yapılacak bir değişiklik yanlış hesabı etkilerdi.
if (kimlige_burunuluyor_mu()) {
    http_response_code(403);
    die('<div style="font-family:sans-serif;padding:40px"><h2>Kullanıcı adına görüntüleme sürüyor</h2>'
      . '<p><a href="/yonetim/burun.php?islem=bitir">Görüntülemeyi bitir</a></p></div>');
}

$st = db()->prepare("SELECT kullanici_adi, eposta, totp_aktif FROM kullanicilar WHERE id = ?");
$st->execute([$kullanici['id']]);
$hesap = $st->fetch();

$hata        = '';
$yeni_kodlar = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_kontrol();
    $islem = $_POST['islem'] ?? '';

    // ── Kurulumu tamamla ────────────────────────────────────
    if ($islem === 'dogrula') {
        $aday = $_SESSION['totp_aday'] ?? '';
        $kod  = trim($_POST['kod'] ?? '');

        if ($aday === '') {
            $hata = 'Kurulum oturumu düştü. Sayfayı yenileyip tekrar deneyin.';
        } elseif (totp_dogrula($aday, $kod) === null) {
            $hata = 'Kod doğrulanamadı. Telefonunuzun saatinin doğru olduğundan emin olun.';
        } else {
            db()->prepare("UPDATE kullanicilar
                           SET totp_gizli = ?, totp_aktif = 1, totp_son_adim = NULL
                           WHERE id = ?")
                ->execute([$aday, $kullanici['id']]);
            unset($_SESSION['totp_aday']);

            $yeni_kodlar        = totp_yedek_kodlari_uret($kullanici['id']);
            $hesap['totp_aktif'] = 1;
            denetim_yaz('2fa_etkinlestirildi', 'kullanici', $kullanici['id']);
        }
    }

    // ── Yedek kodları yenile ────────────────────────────────
    if ($islem === 'yedek_yenile') {
        $yeni_kodlar = totp_yedek_kodlari_uret($kullanici['id']);
        denetim_yaz('2fa_yedek_kod_yenilendi', 'kullanici', $kullanici['id']);
    }

    // ── Devre dışı bırak ────────────────────────────────────
    // Şifre tekrar sorulur: açık kalmış bir oturumu ele geçiren kişi
    // 2FA'yı tek tıkla kaldıramamalı.
    if ($islem === 'kapat') {
        $ps = db()->prepare("SELECT sifre_hash FROM kullanicilar WHERE id = ?");
        $ps->execute([$kullanici['id']]);
        if (!password_verify($_POST['sifre'] ?? '', (string)$ps->fetchColumn())) {
            $hata = 'Şifre hatalı.';
        } else {
            db()->prepare("UPDATE kullanicilar
                           SET totp_gizli = NULL, totp_aktif = 0, totp_son_adim = NULL
                           WHERE id = ?")->execute([$kullanici['id']]);
            db()->prepare("DELETE FROM totp_yedek_kodlari WHERE kullanici_id = ?")
                ->execute([$kullanici['id']]);
            $hesap['totp_aktif'] = 0;
            unset($_SESSION['yonetim_id']);
            denetim_yaz('2fa_kapatildi', 'kullanici', $kullanici['id']);
            flash('İki faktörlü doğrulama kapatıldı. Yönetim paneline erişiminiz sonlandı.', 'uyari');
        }
    }
}

// Kurulum ekranı için aday anahtar (henüz DB'ye yazılmaz).
$aday_gizli = '';
if ((int)$hesap['totp_aktif'] !== 1) {
    if (empty($_SESSION['totp_aday'])) $_SESSION['totp_aday'] = totp_gizli_uret();
    $aday_gizli = $_SESSION['totp_aday'];
}
$hesap_etiketi = $hesap['eposta'] ?: $hesap['kullanici_adi'];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>İki Faktörlü Doğrulama — AYS Yönetim</title>
  <link rel="icon" href="<?= varlik('/assets/icons/favicon-32.png') ?>" sizes="32x32">
  <link rel="stylesheet" href="<?= varlik('/assets/yonetim.css') ?>">
</head>
<body class="y-giris-govde">
<div class="y-giris-kart y-genis">
  <div class="y-giris-basi">
    <div class="y-giris-ikon">🔐</div>
    <h1>İki Faktörlü Doğrulama</h1>
    <p><?= e($hesap['kullanici_adi']) ?> · platform rolü: <?= e($rol) ?></p>
  </div>

  <?= flash_goster() ?>
  <?php if ($hata): ?><div class="y-uyari y-uyari-hata"><?= e($hata) ?></div><?php endif; ?>

  <?php if ($yeni_kodlar): ?>
    <div class="y-uyari y-uyari-basari">
      <strong>Yedek kodlarınız hazır.</strong> Bu kodlar bir daha gösterilmez —
      şimdi güvenli bir yere kaydedin. Her kod yalnızca bir kez kullanılabilir.
    </div>
    <div class="y-kod-izgara">
      <?php foreach ($yeni_kodlar as $kk): ?><code><?= e($kk) ?></code><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ((int)$hesap['totp_aktif'] === 1): ?>
    <div class="y-uyari y-uyari-basari">
      ✓ İki faktörlü doğrulama etkin. Kalan yedek kod:
      <strong><?= totp_kalan_yedek_kod($kullanici['id']) ?></strong>
    </div>

    <form method="post" class="y-form">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="islem" value="yedek_yenile">
      <button type="submit" class="y-ikincil">Yedek kodları yenile</button>
      <small>Yeni kodlar üretildiğinde eskiler geçersiz olur.</small>
    </form>

    <details class="y-tehlike">
      <summary>İki faktörlü doğrulamayı kapat</summary>
      <p>Kapattığınızda yönetim paneline erişiminiz sona erer; panel 2FA'sız hesap kabul etmez.</p>
      <form method="post" class="y-form">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="islem" value="kapat">
        <label>Şifreniz
          <input type="password" name="sifre" autocomplete="current-password" required>
        </label>
        <button type="submit" class="y-tehlike-btn">Kapat</button>
      </form>
    </details>

  <?php else: ?>
    <ol class="y-adimlar">
      <li>Telefonunuza bir doğrulayıcı uygulama kurun (Google Authenticator, Authy,
          Microsoft Authenticator — hepsi çalışır).</li>
      <li>Uygulamada <strong>"Kurulum anahtarını gir"</strong> / <em>"Enter setup key"</em>
          seçeneğini kullanın ve aşağıdaki bilgileri girin:</li>
    </ol>

    <div class="y-anahtar-kutu">
      <div><span>Hesap</span><code><?= e($hesap_etiketi) ?></code></div>
      <div><span>Anahtar</span><code class="y-anahtar"><?= e(totp_okunabilir($aday_gizli)) ?></code></div>
      <div><span>Tür</span><code>Zaman tabanlı (TOTP) · 6 hane · 30 sn</code></div>
    </div>

    <details class="y-detay">
      <summary>Bağlantı adresi (bazı uygulamalar doğrudan kabul eder)</summary>
      <textarea readonly rows="3" onclick="this.select()"><?= e(totp_uri($aday_gizli, $hesap_etiketi)) ?></textarea>
    </details>

    <form method="post" class="y-form">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="islem" value="dogrula">
      <label>Uygulamadaki 6 haneli kod
        <input type="text" name="kod" inputmode="numeric" autocomplete="one-time-code"
               maxlength="6" required autofocus>
        <small>Kod doğrulanana kadar anahtar kaydedilmez — yanlış kurulumda hesabınız kilitlenmez.</small>
      </label>
      <button type="submit">Doğrula ve Etkinleştir</button>
    </form>
  <?php endif; ?>

  <p class="y-giris-alt">
    <?php if (!empty($_SESSION['yonetim_id'])): ?>
      <a href="/yonetim/">← Yönetim paneli</a> ·
    <?php elseif ((int)$hesap['totp_aktif'] === 1): ?>
      <a href="/yonetim/giris.php">Yönetim girişi</a> ·
    <?php endif; ?>
    <a href="/dashboard.php">Uygulamaya dön</a>
  </p>
</div>
</body>
</html>
