<?php
// ============================================================
//  includes/header.php — FINAL FIX
// ============================================================
$kullanici    = giris_kontrol();
$aktif_donem  = $_GET['donem'] ?? date('Y-m');
$aktif_sayfa  = basename($_SERVER['PHP_SELF']);

// Alt nav: SIRA BU ŞEKİLDE SABİT
$menu = [
  ['dosya'=>'dashboard.php', 'etiket'=>'Ana Sayfa', 'ikon'=>'🏠'],
  ['dosya'=>'daireler.php',  'etiket'=>'Daireler',  'ikon'=>'🏢'],
  ['dosya'=>'aidatlar.php',  'etiket'=>'Aidatlar',  'ikon'=>'💰'],
  ['dosya'=>'giderler.php',  'etiket'=>'Giderler',  'ikon'=>'📋'],
];

// Daha paneli — göç uygulanmamış modüller gösterilmez; aksi halde
// tıklandığında boş bir uyarı sayfası çıkardı.
$more_pages = [['dosya'=>'raporlar.php', 'etiket'=>'Raporlar', 'ikon'=>'📊']];
if (operasyon_semasi_hazir_mi()) {
  $more_pages[] = ['dosya'=>'talepler.php',   'etiket'=>'Talepler',  'ikon'=>'🛠'];
  $more_pages[] = ['dosya'=>'demirbaslar.php','etiket'=>'Demirbaş',  'ikon'=>'🏗'];
  $more_pages[] = ['dosya'=>'personel.php',   'etiket'=>'Personel',  'ikon'=>'👷'];
}
if (arsiv_semasi_hazir_mi()) {
  $more_pages[] = ['dosya'=>'kararlar.php', 'etiket'=>'Kararlar', 'ikon'=>'📜'];
  $more_pages[] = ['dosya'=>'belgeler.php', 'etiket'=>'Belgeler', 'ikon'=>'📁'];
}
if (bilanco_semasi_hazir_mi()) {
  $more_pages[] = ['dosya'=>'gelirler.php', 'etiket'=>'Gelirler', 'ikon'=>'💵'];
  $more_pages[] = ['dosya'=>'bilanco.php',  'etiket'=>'Bilanço',  'ikon'=>'📈'];
}
$more_pages[] = ['dosya'=>'ayarlar.php', 'etiket'=>'Ayarlar', 'ikon'=>'⚙️'];
$more_pages[] = ['dosya'=>'cikis.php',   'etiket'=>'Çıkış',   'ikon'=>'🚪'];
$more_active = in_array($aktif_sayfa, array_column($more_pages, 'dosya'));
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="theme-color" content="#0d0d1a">
  <meta name="apple-mobile-web-app-title" content="AYS">
  <title><?= e($sayfa_basligi ?? 'Panel') ?> — <?= e_buyuk($kullanici['apartman_adi']) ?></title>
  <link rel="manifest" href="/manifest.json">
  <link rel="icon" href="/assets/icons/favicon-32.png" sizes="32x32">
  <link rel="apple-touch-icon" href="/assets/icons/apple-touch-icon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/style.css?v=final">
  <?php if (kimlige_burunuluyor_mu()): ?>
  <link rel="stylesheet" href="/assets/yonetim.css?v=1">
  <?php endif; ?>
  <script src="/assets/pwa-install.js" defer></script>
  <script src="/assets/kirpici.js" defer></script>
</head>
<body data-theme="<?= e($kullanici['tema'] ?? 'koyu') ?>">

<?php if (kimlige_burunuluyor_mu()): $bur = burunme_bilgisi(); ?>
<!-- Kullanıcı adına görüntüleme bandı. Kapatılamaz: bu ekranın
     başkasının verisi olduğunu unutmak, yanlış apartmanda işlem
     yapmakla sonuçlanır. -->
<div class="ays-burunme-bandi">
  <span>
    👁 <strong><?= e($bur['hedef_ad'] ?? '') ?></strong> adına görüntülüyorsunuz —
    <?= burunme_yazabilir_mi() ? 'YAZMA AÇIK' : 'salt-okunur' ?>
  </span>
  <a href="/yonetim/burun.php?islem=bitir">Görüntülemeyi bitir</a>
</div>
<?php endif; ?>

<!-- ══ DESKTOP SIDEBAR ══════════════════════════════════════ -->
<aside class="sidebar">
  <a href="/dashboard.php" class="sidebar-brand" aria-label="Panele git">
    <div class="brand-icon"><img src="/assets/icons/icon-192.png" alt="AYS logosu"></div>
    <div>
      <div class="brand-name"><?= e_buyuk($kullanici['apartman_adi']) ?></div>
      <div class="brand-user"><?= e($kullanici['kullanici_adi']) ?></div>
    </div>
  </a>
  <nav class="sidebar-nav">
    <?php
    // Kenar çubuğunda ana menü + raporlar + (varsa) göç uygulanmış modüller
    $yan_menu = array_merge($menu, [['dosya'=>'raporlar.php','etiket'=>'Raporlar','ikon'=>'📊']]);
    if (operasyon_semasi_hazir_mi()) {
      $yan_menu[] = ['dosya'=>'talepler.php',    'etiket'=>'Talepler', 'ikon'=>'🛠'];
      $yan_menu[] = ['dosya'=>'demirbaslar.php', 'etiket'=>'Demirbaş', 'ikon'=>'🏗'];
      $yan_menu[] = ['dosya'=>'personel.php',    'etiket'=>'Personel', 'ikon'=>'👷'];
    }
    if (arsiv_semasi_hazir_mi()) {
      $yan_menu[] = ['dosya'=>'kararlar.php', 'etiket'=>'Kararlar', 'ikon'=>'📜'];
      $yan_menu[] = ['dosya'=>'belgeler.php', 'etiket'=>'Belgeler', 'ikon'=>'📁'];
    }
    if (bilanco_semasi_hazir_mi()) {
      $yan_menu[] = ['dosya'=>'gelirler.php', 'etiket'=>'Gelirler', 'ikon'=>'💵'];
      $yan_menu[] = ['dosya'=>'bilanco.php',  'etiket'=>'Bilanço',  'ikon'=>'📈'];
    }
    foreach ($yan_menu as $m): ?>
    <a href="/<?= $m['dosya'] ?>" class="nav-item <?= $aktif_sayfa === $m['dosya'] ? 'active' : '' ?>">
      <span class="nav-icon"><?= $m['ikon'] ?></span> <?= e($m['etiket']) ?>
    </a>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-footer">
    <a href="/ayarlar.php" class="nav-item <?= $aktif_sayfa === 'ayarlar.php' ? 'active' : '' ?>">
      <span class="nav-icon">⚙️</span> Ayarlar
    </a>
    <a href="/cikis.php" class="nav-item nav-logout">
      <span class="nav-icon">🚪</span> Çıkış
    </a>
  </div>
</aside>

<!-- ══ MOBİL TOPBAR ════════════════════════════════════════ -->
<header class="mobile-topbar">
  <a href="/dashboard.php" class="mobile-topbar-brand" aria-label="Panele git">
    <div class="mobile-brand-icon"><img src="/assets/icons/icon-192.png" alt="AYS logosu"></div>
    <div>
      <div class="mobile-brand-text"><?= e_buyuk($kullanici['apartman_adi']) ?></div>
      <div class="mobile-brand-sub"><?= e($kullanici['kullanici_adi']) ?></div>
    </div>
  </a>
  <span class="mobile-page-title"><?= e($sayfa_basligi ?? '') ?></span>
  <form method="get" style="display:flex; align-items:center; gap:6px;">
    <label style="font-size:11px; font-weight:700; color:var(--accent);">Dönem Seç:</label>
    <select name="donem" class="mobile-donem-select donem-highlight" onchange="this.form.submit()">
      <?php foreach (donem_listesi_genisletilmis() as $d): ?>
      <option value="<?= e($d) ?>" <?= $d === $aktif_donem ? 'selected' : '' ?>><?= e(donem_adi($d)) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
</header>

<!-- ══ MOBİL BOTTOM NAV — SABİT SIRALAMA ══════════════════ -->
<nav class="bottom-nav">
  <div class="bottom-nav-inner">
    <?php foreach ($menu as $m): ?>
    <?php $is_active = $aktif_sayfa === $m['dosya']; ?>
    <a href="/<?= $m['dosya'] ?>" class="bnav-item <?= $is_active ? 'active' : '' ?>">
      <span class="bnav-icon-wrap <?= $is_active ? 'active' : '' ?>">
        <span class="bnav-icon"><?= $m['ikon'] ?></span>
      </span>
      <span class="bnav-label"><?= e($m['etiket']) ?></span>
    </a>
    <?php endforeach; ?>
    
    <!-- Daha butonu -->
    <button type="button" class="bnav-item <?= $more_active ? 'active' : '' ?>" onclick="toggleMorePanel()">
      <span class="bnav-icon-wrap <?= $more_active ? 'active' : '' ?>">
        <span class="bnav-icon">⋯</span>
      </span>
      <span class="bnav-label">Daha</span>
    </button>
  </div>
</nav>

<!-- More Panel -->
<div class="bnav-overlay" id="bnav-overlay" onclick="closeMorePanel()"></div>
<div class="bnav-more-panel" id="bnav-more-panel">
  <div class="bnav-more-grid">
    <?php foreach ($more_pages as $m): ?>
    <a href="/<?= $m['dosya'] ?>" class="bnav-more-item <?= $aktif_sayfa === $m['dosya'] ? 'active-more' : '' ?>">
      <span class="bmi"><?= $m['ikon'] ?></span>
      <?= e($m['etiket']) ?>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- ══ DESKTOP TOPBAR ═══════════════════════════════════════ -->
<div class="main-wrap">
  <header class="top-bar">
    <h1 class="page-title"><?= e($sayfa_basligi ?? '') ?></h1>
    <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
      <?php
      // Site seçici yalnızca birden fazla siteye yetkili kullanıcıya
      // gösterilir — tek siteli kullanıcı için arayüz hiç değişmez.
      $kullanici_site_listesi = ($kullanici['site_sayisi'] ?? 1) > 1
          ? kullanici_siteleri($kullanici['id']) : [];
      if ($kullanici_site_listesi):
      ?>
      <form method="post" action="/site_sec.php" style="display:flex;align-items:center;gap:8px">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="geri" value="/<?= e($aktif_sayfa) ?>">
        <label style="font-size:13px;font-weight:700;color:var(--mint)">Site:</label>
        <select name="site_id" class="input input-sm" style="width:auto;max-width:190px" onchange="this.form.submit()">
          <?php foreach ($kullanici_site_listesi as $s): ?>
          <option value="<?= (int)$s['id'] ?>" <?= (int)$s['id'] === (int)$kullanici['site_id'] ? 'selected' : '' ?>>
            <?= e_buyuk($s['ad']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </form>
      <?php endif; ?>
      <form method="get" style="display:flex;align-items:center;gap:8px">
        <label style="font-size:13px;font-weight:700;color:var(--accent);">Dönem Seç:</label>
        <select name="donem" class="input input-sm donem-highlight" style="width:auto" onchange="this.form.submit()">
          <?php foreach (donem_listesi_genisletilmis() as $d): ?>
          <option value="<?= e($d) ?>" <?= $d === $aktif_donem ? 'selected' : '' ?>><?= e(donem_adi($d)) ?></option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>
  </header>

  <div class="page-content">
    <?= flash_goster() ?>
<?php
// ── Sistem duyuruları ──────────────────────────────────────
// Platform yönetiminden yayınlanır; hedefi tüm siteler ya da tek
// bir site olabilir. Kapatma yok — süresi/bitiş tarihi duyuruyu
// yayınlayan tarafından belirlenir.
foreach (aktif_duyurular((int)($kullanici['site_id'] ?? 0)) as $duyuru):
    $renk = $duyuru['tip'] === 'bakim' ? '#e74c3c'
          : ($duyuru['tip'] === 'uyari' ? '#f5a623' : '#6c8cff');
?>
    <div class="flash" style="background:<?= $renk ?>1a;border:1px solid <?= $renk ?>55;color:<?= $renk ?>">
      <strong><?= e($duyuru['baslik']) ?></strong>
      <div style="color:var(--text);opacity:.85;margin-top:3px;font-weight:400">
        <?= nl2br(e($duyuru['mesaj'])) ?>
      </div>
    </div>
<?php endforeach; ?>
<?php
// ── Platform yönetimi kısayolu ─────────────────────────────
// Yalnızca platform rolü olan hesaplara görünür.
if (!kimlige_burunuluyor_mu() && platform_yetkili_mi(platform_rolu((int)$kullanici['id']))): ?>
    <div class="flash" style="background:rgba(160,108,255,.1);border:1px solid rgba(160,108,255,.32);
                              color:#a06cff;display:flex;align-items:center;gap:10px;
                              flex-wrap:wrap;justify-content:space-between">
      <span>🛡 Bu hesabın platform yönetim yetkisi var.</span>
      <a href="/yonetim/giris.php" class="btn btn-sm btn-primary">Yönetim Paneli</a>
    </div>
<?php endif; ?>
<?php
// ── E-posta eksik/doğrulanmamış uyarısı ────────────────────
// Mevcut kullanıcıların hiçbirinde e-posta adresi yok (bu alan sisteme
// sonradan eklendi). Hesap kurtarma yolu olmadan kalmamaları için
// panelde kalıcı ama kapatılabilir bir hatırlatma gösterilir.
//
// "Sonra" tercihi banner çizilmeden ÖNCE işlenir; aksi halde tıklanan
// istekte uyarı bir kez daha görünürdü.
if (isset($_GET['eposta_uyarisi']) && $_GET['eposta_uyarisi'] === 'gizle') {
    $_SESSION['eposta_uyarisi_gizle'] = 1;
}

if (eposta_semasi_hazir_mi() && $aktif_sayfa !== 'ayarlar.php' && empty($_SESSION['eposta_uyarisi_gizle'])) {
    $eu = db()->prepare("SELECT eposta, eposta_dogrulandi FROM kullanicilar WHERE id = ?");
    $eu->execute([$kullanici['id']]);
    $eu_bilgi = $eu->fetch();
    $eu_yok     = empty($eu_bilgi['eposta']);
    $eu_onaysiz = !$eu_yok && (int)$eu_bilgi['eposta_dogrulandi'] === 0;

    if ($eu_yok || $eu_onaysiz):
?>
    <div class="flash" style="background:rgba(245,166,35,.12);border:1px solid rgba(245,166,35,.35);color:#f5a623;
                              display:flex;align-items:center;gap:10px;flex-wrap:wrap;justify-content:space-between">
      <span style="flex:1;min-width:220px">
        <?php if ($eu_yok): ?>
          ⚠ Hesabınızda kayıtlı e-posta yok. Şifrenizi unutursanız kurtarma yolu olmaz.
        <?php else: ?>
          ⚠ E-posta adresiniz doğrulanmadı. Şifre sıfırlama yalnızca doğrulanmış adrese gönderilir.
        <?php endif; ?>
      </span>
      <span style="display:flex;gap:8px;flex-shrink:0">
        <a href="/ayarlar.php" class="btn btn-sm btn-primary">
          <?= $eu_yok ? 'E-posta Ekle' : 'Doğrula' ?>
        </a>
        <a href="?<?= e(http_build_query(array_merge($_GET, ['eposta_uyarisi' => 'gizle']))) ?>"
           class="btn btn-sm btn-ghost">Sonra</a>
      </span>
    </div>
<?php
    endif;
}
?>
