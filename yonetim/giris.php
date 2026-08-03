<?php
// ============================================================
//  yonetim/giris.php — PANEL GİRİŞİ (ŞİFRE + ZORUNLU 2FA)
//
//  Uygulama girişinden AYRI bir yoldur. Buradan geçen kişi tüm
//  kiracıların mali ve kişisel verisine erişir; bu yüzden:
//   · şifre doğrulaması + TOTP kodu (veya yedek kod) zorunlu
//   · agresif hız sınırlama (IP ve kullanıcı adı bazlı)
//   · başarısız/başarılı her deneme denetim kaydına
//   · hata mesajları hangi adımın yanlış olduğunu SIZDIRMAZ
//     (kullanıcı adı sayımına ve rol keşfine kapalı)
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform.php';
require_once __DIR__ . '/../includes/totp.php';

oturum_baslat();
header('X-Robots-Tag: noindex, nofollow', true);

if (!empty($_SESSION['yonetim_id'])) {
    header('Location: /yonetim/');
    exit;
}

$hata  = '';
$bilgi = '';

if (($_GET['mesaj'] ?? '') === 'yetki_yok') {
    $bilgi = 'Panel yetkiniz bulunmuyor ya da geri alınmış.';
}
if (($_GET['mesaj'] ?? '') === 'cikis') {
    $bilgi = 'Yönetim oturumu kapatıldı.';
}

$sema_hazir = platform_semasi_hazir_mi();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $sema_hazir) {
    csrf_kontrol();

    $kullanici_adi = trim($_POST['kullanici_adi'] ?? '');
    $sifre         = $_POST['sifre'] ?? '';
    $kod           = trim($_POST['kod'] ?? '');
    $ip            = istemci_ip();

    // Hız sınırı iki eksende: IP başına (dağıtık deneme) ve kullanıcı
    // adı başına (tek hesaba yoğunlaşma). İkisinden biri dolarsa dur.
    $ip_ok  = hiz_limiti_gec('yonetim_giris:ip:' . $ip, 10, 900);
    $ad_ok  = $kullanici_adi !== ''
        ? hiz_limiti_gec(hiz_limiti_anahtari('yonetim_giris:ad', $kullanici_adi), 5, 900)
        : true;

    if (!$ip_ok || !$ad_ok) {
        denetim_yaz('yonetim_giris_limit_asildi', 'platform', null, ['ad' => $kullanici_adi]);
        $hata = 'Çok fazla deneme yapıldı. 15 dakika sonra tekrar deneyin.';
    } elseif ($kullanici_adi === '' || $sifre === '') {
        $hata = 'Kullanıcı adı ve şifre zorunludur.';
    } else {
        $st = db()->prepare("SELECT id, kullanici_adi, sifre_hash, platform_rolu,
                                    totp_gizli, totp_aktif, totp_son_adim
                             FROM kullanicilar WHERE kullanici_adi = ?");
        $st->execute([$kullanici_adi]);
        $k = $st->fetch();

        $sifre_dogru = $k && password_verify($sifre, $k['sifre_hash']);
        $yetkili     = $k && platform_yetkili_mi($k['platform_rolu']);

        if (!$sifre_dogru || !$yetkili) {
            // Tek ve aynı mesaj: "böyle bir kullanıcı yok", "şifre yanlış"
            // ve "bu hesabın panel yetkisi yok" ayrımı yapılmaz.
            denetim_yaz('yonetim_giris_basarisiz', 'platform', $k['id'] ?? null,
                        ['ad' => $kullanici_adi, 'sebep' => $sifre_dogru ? 'yetkisiz' : 'kimlik']);
            $hata = 'Giriş bilgileri hatalı.';
        } elseif ((int)$k['totp_aktif'] !== 1 || empty($k['totp_gizli'])) {
            // 2FA kurulmamış bir süper admin hesabı panele alınmaz.
            // Kurulum, uygulama içindeki /yonetim/iki_faktor.php ile
            // yapılır ve oraya girmek için de normal oturum gerekir —
            // yani hesabın sahibi olduğunu ayrıca kanıtlamış olur.
            denetim_yaz('yonetim_giris_2fa_yok', 'platform', (int)$k['id']);
            $hata = 'Bu hesapta iki faktörlü doğrulama kurulu değil. '
                  . 'Önce uygulamaya girip Yönetim → İki Faktör adımını tamamlayın.';
        } elseif ($kod === '') {
            $hata = 'Doğrulama kodu zorunludur.';
        } else {
            $adim = totp_dogrula($k['totp_gizli'], $kod,
                                 $k['totp_son_adim'] !== null ? (int)$k['totp_son_adim'] : null);

            $yedek_kullanildi = false;
            if ($adim === null) {
                // TOTP tutmadıysa yedek kod olarak dene (telefon kayıp senaryosu).
                $yedek_kullanildi = totp_yedek_kod_tuket((int)$k['id'], $kod);
            }

            if ($adim === null && !$yedek_kullanildi) {
                denetim_yaz('yonetim_giris_2fa_hatali', 'platform', (int)$k['id']);
                $hata = 'Giriş bilgileri hatalı.';
            } else {
                if ($adim !== null) {
                    // Kullanılan zaman adımı saklanır: aynı kod ikinci kez geçmez.
                    db()->prepare("UPDATE kullanicilar SET totp_son_adim = ? WHERE id = ?")
                        ->execute([$adim, $k['id']]);
                }

                session_regenerate_id(true);
                $_SESSION['yonetim_id']       = (int)$k['id'];
                $_SESSION['yonetim_baslangic'] = time();
                unset($_SESSION['kimlik_burunme']);

                db()->prepare("UPDATE kullanicilar SET son_giris = NOW() WHERE id = ?")
                    ->execute([$k['id']]);

                denetim_yaz('yonetim_giris_basarili', 'platform', (int)$k['id'],
                            ['yedek_kod' => $yedek_kullanildi], (int)$k['id']);

                header('Location: /yonetim/');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <meta name="theme-color" content="#12121f">
  <title>Yönetim Girişi — AYS</title>
  <link rel="icon" href="/assets/icons/favicon-32.png" sizes="32x32">
  <link rel="stylesheet" href="/assets/yonetim.css?v=1">
</head>
<body class="y-giris-govde">
<div class="y-giris-kart">
  <div class="y-giris-basi">
    <div class="y-giris-ikon">🛡</div>
    <h1>Yönetim Paneli</h1>
    <p>Platform yöneticileri için ayrı giriş</p>
  </div>

  <?php if (!$sema_hazir): ?>
    <div class="y-uyari y-uyari-hata">
      Platform şeması hazır değil. Önce 004 numaralı göçü uygulayın:
      <code>php araclar/goc_cli.php uygula</code>
    </div>
  <?php else: ?>
    <?php if ($hata): ?><div class="y-uyari y-uyari-hata"><?= e($hata) ?></div><?php endif; ?>
    <?php if ($bilgi): ?><div class="y-uyari"><?= e($bilgi) ?></div><?php endif; ?>

    <form method="post" class="y-form">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <label>
        Kullanıcı Adı
        <input type="text" name="kullanici_adi" autocomplete="username" required autofocus
               value="<?= e($_POST['kullanici_adi'] ?? '') ?>">
      </label>
      <label>
        Şifre
        <input type="password" name="sifre" autocomplete="current-password" required>
      </label>
      <label>
        Doğrulama Kodu
        <input type="text" name="kod" inputmode="numeric" autocomplete="one-time-code"
               placeholder="6 haneli kod veya yedek kod" required>
        <small>Doğrulayıcı uygulamanızdaki 6 haneli kod. Telefonunuza erişemiyorsanız
               yedek kodlarınızdan birini girin.</small>
      </label>
      <button type="submit">Panele Gir</button>
    </form>
  <?php endif; ?>

  <p class="y-giris-alt"><a href="/dashboard.php">← Uygulamaya dön</a></p>
</div>
</body>
</html>
