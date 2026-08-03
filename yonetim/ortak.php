<?php
// ============================================================
//  yonetim/ortak.php — PANEL ÖNYÜKLEME VE YETKİ BEKÇİSİ
//
//  /yonetim/ altındaki HER sayfa bu dosyayla başlar. Yetki kontrolü
//  tek noktada toplanır: yeni bir panel sayfası eklendiğinde koruma
//  kendiliğinden geçerli olur.
//
//  OTURUM MODELİ — iki ayrı anahtar bilinçli olarak ayrıdır:
//    $_SESSION['yonetim_id']   → paneldeki kimliği doğrulanmış yönetici
//    $_SESSION['kullanici_id'] → uygulamanın gördüğü kullanıcı
//  Kimliğe bürünme sırasında ikincisi hedef kullanıcıya döner, birincisi
//  gerçek yöneticide kalır. Tek anahtar kullanılsaydı bürünme sırasında
//  panel yetkisi de hedef kullanıcıya geçerdi.
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform.php';
require_once __DIR__ . '/../includes/totp.php';
require_once __DIR__ . '/../includes/eposta.php';

oturum_baslat();

// Panel arama sonuçlarında görünmemeli.
header('X-Robots-Tag: noindex, nofollow', true);

// ─── IP kısıtı (isteğe bağlı) ───────────────────────────────
// Boşsa kısıt yok. Dolu ise yalnızca listedeki IP'ler (veya CIDR
// blokları) panele ulaşabilir. Şifre + 2FA'nın üstüne ek bir katman;
// tek başına yeterli SAYILMAZ (X-Forwarded-For uydurulabilir).
function yonetim_ip_izinli_mi(): bool
{
    $liste = trim((string)platform_ayari('yonetim_ip_listesi', ''));
    if ($liste === '') return true;

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    foreach (preg_split('/[\s,;]+/', $liste, -1, PREG_SPLIT_NO_EMPTY) as $kural) {
        if (ip_kurala_uyuyor_mu($ip, $kural)) return true;
    }
    return false;
}

// IPv4/IPv6 tam eşleşme veya IPv4 CIDR (ör. 88.230.10.0/24).
function ip_kurala_uyuyor_mu(string $ip, string $kural): bool
{
    if ($ip === '' || $kural === '') return false;
    if (!str_contains($kural, '/')) return strcasecmp($ip, $kural) === 0;

    [$ag, $bit] = explode('/', $kural, 2);
    $ip_uzun = ip2long($ip);
    $ag_uzun = ip2long($ag);
    $bit     = (int)$bit;
    if ($ip_uzun === false || $ag_uzun === false || $bit < 0 || $bit > 32) return false;

    $maske = $bit === 0 ? 0 : (-1 << (32 - $bit)) & 0xFFFFFFFF;
    return ($ip_uzun & $maske) === ($ag_uzun & $maske);
}

// ─── Bekçi ──────────────────────────────────────────────────
// Panele girişi olmayanı giriş sayfasına atar ve güncel platform
// rolünü VERİTABANINDAN okur — oturumda saklanan bir rol, yetkisi
// alındıktan sonra da geçerli kalırdı.
function yonetim_kontrol(bool $yazma_gerekli = false): array
{
    if (!platform_semasi_hazir_mi()) {
        http_response_code(503);
        die('<div style="font-family:sans-serif;padding:40px;max-width:560px">'
          . '<h2>Platform şeması hazır değil</h2>'
          . '<p>Yönetim paneli için 004 numaralı göç uygulanmalıdır:</p>'
          . '<pre style="background:#f4f4f4;padding:12px;border-radius:6px">php araclar/goc_cli.php uygula</pre>'
          . '</div>');
    }

    if (!yonetim_ip_izinli_mi()) {
        denetim_yaz('yonetim_ip_reddedildi', 'platform', null,
                    ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
        http_response_code(403);
        die('<div style="font-family:sans-serif;padding:40px">'
          . '<h2>Erişim reddedildi</h2><p>Bu IP adresi yönetim paneli için yetkili değil.</p></div>');
    }

    if (empty($_SESSION['yonetim_id'])) {
        header('Location: /yonetim/giris.php');
        exit;
    }

    $st = db()->prepare("SELECT id, kullanici_adi, eposta, platform_rolu, totp_aktif
                         FROM kullanicilar WHERE id = ?");
    $st->execute([(int)$_SESSION['yonetim_id']]);
    $yonetici = $st->fetch();

    // Rol geri alınmışsa oturum anında düşer.
    if (!$yonetici || !platform_yetkili_mi($yonetici['platform_rolu'])) {
        unset($_SESSION['yonetim_id']);
        header('Location: /yonetim/giris.php?mesaj=yetki_yok');
        exit;
    }

    // Yazma işlemleri yalnızca süper admin; 'destek' salt-okunurdur.
    if ($yazma_gerekli && !platform_yazabilir_mi($yonetici['platform_rolu'])) {
        denetim_yaz('yonetim_yazma_reddedildi', 'platform', null,
                    ['yol' => $_SERVER['PHP_SELF'] ?? ''], (int)$yonetici['id']);
        http_response_code(403);
        die('<div style="font-family:sans-serif;padding:40px">'
          . '<h2>Bu işlem için yetkiniz yok</h2>'
          . '<p>Destek rolü salt-okunurdur.</p>'
          . '<p><a href="/yonetim/">Panele dön</a></p></div>');
    }

    return $yonetici;
}

// POST işleyen panel sayfaları için: CSRF + süper admin yazma yetkisi.
function yonetim_yazma_kontrol(): array
{
    csrf_kontrol();
    return yonetim_kontrol(true);
}

// Panel içi denetim kaydı — eylem adı otomatik "yonetim_" ön ekli olur,
// böylece denetim görünümünde platform işlemleri süzülebilir.
function yonetim_denetim(string $eylem, ?string $hedef_tur = null,
                         ?int $hedef_id = null, array $detay = []): void
{
    denetim_yaz('yonetim_' . $eylem, $hedef_tur, $hedef_id, $detay,
                (int)($_SESSION['yonetim_id'] ?? 0) ?: null, null);
}

// ─── Görünüm yardımcıları ───────────────────────────────────
function yonetim_menu(): array
{
    return [
        ['yol' => '/yonetim/',              'ad' => 'Genel Bakış',   'ikon' => '📊'],
        ['yol' => '/yonetim/siteler.php',   'ad' => 'Siteler',       'ikon' => '🏢'],
        ['yol' => '/yonetim/kullanicilar.php', 'ad' => 'Kullanıcılar', 'ikon' => '👤'],
        ['yol' => '/yonetim/duyurular.php', 'ad' => 'Duyurular',     'ikon' => '📣'],
        ['yol' => '/yonetim/icerik.php',    'ad' => 'İçerik',        'ikon' => '📝'],
        ['yol' => '/yonetim/denetim.php',   'ad' => 'Denetim Kaydı', 'ikon' => '🔍'],
        ['yol' => '/yonetim/goc.php',       'ad' => 'Şema Göçü',     'ikon' => '🗄'],
        ['yol' => '/yonetim/ayarlar.php',   'ad' => 'Platform Ayarları', 'ikon' => '⚙️'],
    ];
}

function yonetim_basla(array $yonetici, string $baslik): void
{
    $aktif  = $_SERVER['PHP_SELF'] ?? '';
    $bakim  = bakim_modu_aktif_mi();
    $salt   = !platform_yazabilir_mi($yonetici['platform_rolu']);
    ?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <meta name="theme-color" content="#12121f">
  <title><?= e($baslik) ?> — AYS Yönetim</title>
  <link rel="icon" href="/assets/icons/favicon-32.png?v=2" sizes="32x32">
  <link rel="stylesheet" href="/assets/yonetim.css?v=1">
</head>
<body>
<div class="y-kabuk">
  <aside class="y-yan">
    <a href="/yonetim/" class="y-marka">
      <span class="y-marka-ikon">🛡</span>
      <span>
        <strong>AYS Yönetim</strong>
        <small><?= e($yonetici['kullanici_adi']) ?> · <?= e($yonetici['platform_rolu']) ?></small>
      </span>
    </a>
    <nav class="y-menu">
      <?php foreach (yonetim_menu() as $m): ?>
        <?php
        // '/yonetim/' yalnızca tam eşleşmede aktif; aksi halde tüm
        // alt sayfalarda da "Genel Bakış" seçili görünürdü.
        $secili = $m['yol'] === '/yonetim/'
            ? in_array($aktif, ['/yonetim/', '/yonetim/index.php'], true)
            : str_starts_with($aktif, rtrim($m['yol'], '/'));
        ?>
        <a href="<?= e($m['yol']) ?>" class="y-menu-oge<?= $secili ? ' aktif' : '' ?>">
          <span><?= $m['ikon'] ?></span> <?= e($m['ad']) ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="y-yan-alt">
      <a href="/yonetim/iki_faktor.php" class="y-menu-oge">🔐 İki Faktör</a>
      <a href="/dashboard.php" class="y-menu-oge">↩ Uygulamaya dön</a>
      <a href="/yonetim/cikis.php" class="y-menu-oge y-cikis">🚪 Çıkış</a>
    </div>
  </aside>

  <main class="y-ana">
    <header class="y-ust">
      <h1><?= e($baslik) ?></h1>
      <?php if ($bakim): ?><span class="y-rozet y-rozet-uyari">Bakım modu açık</span><?php endif; ?>
      <?php if ($salt): ?><span class="y-rozet">Salt-okunur (destek)</span><?php endif; ?>
    </header>
    <div class="y-icerik">
      <?= flash_goster() ?>
    <?php
}

function yonetim_bitir(): void
{
    ?>
    </div>
  </main>
</div>
</body>
</html>
    <?php
}

// Sayfalama yardımcısı: (limit, ofset, sayfa) döndürür.
function yonetim_sayfalama(int $sayfa_basi = 25): array
{
    $sayfa  = max(1, (int)($_GET['s'] ?? 1));
    return [$sayfa_basi, ($sayfa - 1) * $sayfa_basi, $sayfa];
}

function yonetim_sayfalama_ciz(int $sayfa, int $toplam, int $sayfa_basi): void
{
    $son = max(1, (int)ceil($toplam / $sayfa_basi));
    if ($son <= 1) return;
    $sorgu = $_GET;
    echo '<div class="y-sayfalama">';
    if ($sayfa > 1) {
        $sorgu['s'] = $sayfa - 1;
        echo '<a href="?' . e(http_build_query($sorgu)) . '">‹ Önceki</a>';
    }
    echo '<span>' . $sayfa . ' / ' . $son . ' &nbsp;·&nbsp; ' . $toplam . ' kayıt</span>';
    if ($sayfa < $son) {
        $sorgu['s'] = $sayfa + 1;
        echo '<a href="?' . e(http_build_query($sorgu)) . '">Sonraki ›</a>';
    }
    echo '</div>';
}
