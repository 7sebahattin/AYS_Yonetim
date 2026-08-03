<?php
// ============================================================
//  yonetim/giris.php — PANEL GİRİŞİ (ŞİFRE + İSTEĞE BAĞLI 2FA)
//
//  Uygulama girişinden AYRI bir yoldur. Buradan geçen kişi tüm
//  kiracıların mali ve kişisel verisine erişir; bu yüzden:
//   · agresif hız sınırlama (IP ve kullanıcı adı bazlı)
//   · başarısız/başarılı her deneme denetim kaydına
//   · hata mesajları hangi adımın yanlış olduğunu SIZDIRMAZ
//     (kullanıcı adı sayımına ve rol keşfine kapalı)
//
//  2FA HESAP BAZINDA: Bir hesapta TOTP kuruluysa (totp_aktif=1) kod
//  zorunlu ve doğrulanır — kurulu hesaplar için koruma AYNI kalır.
//  Kurulu değilse şifre tek başına yeterlidir; bu durum
//  'yonetim_giris_2fa_atlandi' olarak ayrıca denetime yazılır ki
//  hangi oturumların 2FA'sız açıldığı /yonetim/denetim.php'den
//  görülebilsin. 2FA kurmak isteyen hesap /yonetim/iki_faktor.php'yi
//  kullanmaya devam edebilir.
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

// Panel oturumunu başlatır ve /yonetim/'e yönlendirir. Hem 2FA'lı hem
// 2FA'sız girişten çağrıldığı için tek yerde tutulur — iki ayrı kopya
// oturum kurulumunda (session_regenerate_id, son_islem damgası vb.)
// er ya da geç birbirinden sapardı.
function panele_oturum_ac(int $kullanici_id, bool $yedek_kod_kullanildi): never
{
    session_regenerate_id(true);
    $_SESSION['yonetim_id']       = $kullanici_id;
    $_SESSION['yonetim_baslangic'] = time();
    unset($_SESSION['kimlik_burunme']);

    db()->prepare("UPDATE kullanicilar SET son_giris = NOW() WHERE id = ?")
        ->execute([$kullanici_id]);

    denetim_yaz('yonetim_giris_basarili', 'platform', $kullanici_id,
                ['yedek_kod' => $yedek_kod_kullanildi], $kullanici_id);

    header('Location: /yonetim/');
    exit;
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

        $twofa_kurulu = (int)($k['totp_aktif'] ?? 0) === 1 && !empty($k['totp_gizli']);

        if (!$sifre_dogru || !$yetkili) {
            // Tek ve aynı mesaj: "böyle bir kullanıcı yok", "şifre yanlış"
            // ve "bu hesabın panel yetkisi yok" ayrımı yapılmaz.
            denetim_yaz('yonetim_giris_basarisiz', 'platform', $k['id'] ?? null,
                        ['ad' => $kullanici_adi, 'sebep' => $sifre_dogru ? 'yetkisiz' : 'kimlik']);
            $hata = 'Giriş bilgileri hatalı.';
        } elseif (!$twofa_kurulu) {
            // 2FA bu hesapta kurulu değil: şifre tek başına yeterli.
            // Bu, denetime AYRICA yazılır ki hangi oturumların 2FA'sız
            // açıldığı /yonetim/denetim.php'den izlenebilsin.
            denetim_yaz('yonetim_giris_2fa_atlandi', 'platform', (int)$k['id']);
            panele_oturum_ac((int)$k['id'], false);
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
                panele_oturum_ac((int)$k['id'], $yedek_kullanildi);
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
        Doğrulama Kodu <span style="opacity:.6;font-weight:400">(2FA kuruluysa)</span>
        <input type="text" name="kod" inputmode="numeric" autocomplete="one-time-code"
               placeholder="6 haneli kod veya yedek kod">
        <small>Hesabınızda iki faktörlü doğrulama kuruluysa buraya kodu girin.
               Kurulu değilse boş bırakabilirsiniz.</small>
      </label>
      <button type="submit">Panele Gir</button>
    </form>
  <?php endif; ?>

  <p class="y-giris-alt"><a href="/dashboard.php">← Uygulamaya dön</a></p>
</div>
</body>
</html>
