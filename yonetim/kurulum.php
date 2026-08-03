<?php
// ============================================================
//  yonetim/kurulum.php — İLK SÜPER ADMİN ATAMA (TEK SEFERLİK)
//
//  Yumurta-tavuk sorunu: panele girmek için süper admin olmak
//  gerekir, süper admin atamak için de panele girmek. Bu sayfa o
//  düğümü tek seferlik açar.
//
//  İKİ KOŞUL BİRLİKTE aranır:
//    1. Sistemde HİÇ süper admin olmamalı — bir tane atandığı anda
//       bu sayfa kendini kapatır ve bir daha çalışmaz.
//    2. config.php'deki GOC_ANAHTARI bilinmeli — yani sunucu
//       dosyalarına erişimi olan kişi olmalısınız.
//
//  Böylece açıkta duran bir yetki yükseltme uç noktası bırakılmaz.
//  SSH erişiminiz varsa bu sayfa yerine şunu kullanın:
//    php araclar/superadmin_ata.php <kullanici_adi>
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform.php';

oturum_baslat();
header('X-Robots-Tag: noindex, nofollow', true);

$hata   = '';
$basari = '';

$sema_hazir = platform_semasi_hazir_mi();
$mevcut     = $sema_hazir ? superadmin_sayisi() : 0;
$anahtar_var = GOC_ANAHTARI !== '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $sema_hazir && $mevcut === 0 && $anahtar_var) {
    csrf_kontrol();

    // Kurulum uç noktası da hız sınırına tabidir: anahtar tahmin
    // edilmeye çalışılabilir.
    if (!hiz_limiti_gec('yonetim_kurulum:ip:' . istemci_ip(), 5, 900)) {
        $hata = 'Çok fazla deneme. 15 dakika sonra tekrar deneyin.';
    } elseif (!hash_equals(GOC_ANAHTARI, (string)($_POST['anahtar'] ?? ''))) {
        denetim_yaz('yonetim_kurulum_anahtar_hatali', 'platform');
        $hata = 'Anahtar hatalı.';
    } else {
        $kullanici_adi = trim($_POST['kullanici_adi'] ?? '');
        $st = db()->prepare("SELECT id, kullanici_adi FROM kullanicilar WHERE kullanici_adi = ?");
        $st->execute([$kullanici_adi]);
        $k = $st->fetch();

        if (!$k) {
            $hata = 'Böyle bir kullanıcı yok.';
        } else {
            // Yarış koşulu koruması: iki istek aynı anda gelirse
            // ikincisi de "hiç süper admin yok" görmüş olabilir.
            // Koşullu UPDATE, atamayı yalnızca gerçekten kimse yokken
            // yapar.
            $st = db()->prepare("
                UPDATE kullanicilar SET platform_rolu = 'superadmin'
                WHERE id = ?
                  AND (SELECT sayi FROM (SELECT COUNT(*) AS sayi FROM kullanicilar
                        WHERE platform_rolu = 'superadmin') AS t) = 0
            ");
            $st->execute([$k['id']]);

            if ($st->rowCount() === 0) {
                $hata = 'Atama yapılamadı — sistemde zaten bir süper admin var.';
            } else {
                denetim_yaz('yonetim_ilk_superadmin_atandi', 'kullanici', (int)$k['id'],
                            ['kullanici_adi' => $k['kullanici_adi']], (int)$k['id']);
                $basari = $k['kullanici_adi'] . ' artık süper admin. Sıradaki adım: '
                        . 'bu hesapla uygulamaya girip iki faktörlü doğrulamayı kurun.';
                $mevcut = 1;
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
  <title>Yönetim Kurulumu — AYS</title>
  <link rel="stylesheet" href="/assets/yonetim.css?v=1">
</head>
<body class="y-giris-govde">
<div class="y-giris-kart y-genis">
  <div class="y-giris-basi">
    <div class="y-giris-ikon">🔑</div>
    <h1>İlk Süper Admin</h1>
    <p>Tek seferlik kurulum</p>
  </div>

  <?php if (!$sema_hazir): ?>
    <div class="y-uyari y-uyari-hata">
      Platform şeması hazır değil. Önce göçleri uygulayın:
      <code>php araclar/goc_cli.php uygula</code>
    </div>

  <?php elseif ($basari): ?>
    <div class="y-uyari y-uyari-basari"><?= e($basari) ?></div>
    <ol class="y-adimlar">
      <li>Uygulamaya bu hesapla girin.</li>
      <li><a href="/yonetim/iki_faktor.php">Yönetim → İki Faktör</a> adımını tamamlayın
          ve yedek kodlarınızı saklayın.</li>
      <li><a href="/yonetim/giris.php">Yönetim girişi</a>nden panele geçin.</li>
      <li>Bu sayfa artık işlem kabul etmiyor; istersen sunucudan silebilirsin.</li>
    </ol>

  <?php elseif ($mevcut > 0): ?>
    <div class="y-uyari">
      Sistemde zaten <?= (int)$mevcut ?> süper admin var; bu kurulum sayfası kapalı.
      Yeni yetki atamaları <a href="/yonetim/kullanicilar.php">panelden</a> yapılır.
    </div>

  <?php elseif (!$anahtar_var): ?>
    <div class="y-uyari y-uyari-hata">
      <code>config.php</code> içindeki <code>GOC_ANAHTARI</code> tanımsız. Bu sayfanın
      çalışabilmesi için sunucudaki config dosyasına uzun ve rastgele bir anahtar yazın:
      <code>define('GOC_ANAHTARI', '…');</code>
    </div>

  <?php else: ?>
    <?php if ($hata): ?><div class="y-uyari y-uyari-hata"><?= e($hata) ?></div><?php endif; ?>
    <p class="y-soluk">
      Sistemde henüz süper admin yok. Mevcut bir kullanıcıya platform yetkisi vermek için
      <code>config.php</code>'deki göç anahtarını girin.
    </p>
    <form method="post" class="y-form">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <label>Kullanıcı adı
        <input type="text" name="kullanici_adi" required autofocus
               value="<?= e($_POST['kullanici_adi'] ?? '') ?>">
      </label>
      <label>GOC_ANAHTARI
        <input type="password" name="anahtar" required autocomplete="off">
      </label>
      <button type="submit">Süper Admin Yap</button>
    </form>
  <?php endif; ?>

  <p class="y-giris-alt"><a href="/dashboard.php">← Uygulamaya dön</a></p>
</div>
</body>
</html>
