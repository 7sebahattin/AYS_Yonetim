<?php
// ============================================================
//  index.php — TANITIM (LANDING) SAYFASI
//  Herkese açık; giriş/kayıt için login.php'ye yönlendirir.
// ============================================================
require_once 'includes/functions.php';
require_once 'includes/tanitim_icerik.php';
oturum_baslat();
oturumu_hatirlama_ile_dene();

// Oturumu açık (ya da "Beni Hatırla" ile sessizce açılan) kullanıcıyı doğrudan panele al
if (!empty($_SESSION['kullanici_id'])) {
    header('Location: /dashboard.php');
    exit;
}

// Canonical/Open Graph için mutlak adres. Alan adı değişirse burayı güncelleyin;
// HTTP_HOST kullanılmıyor çünkü host header'ı istemci tarafından değiştirilebilir.
$site_url    = 'https://ays.derspros.com.tr';
$site_adi    = 'AYS — Apartman Yönetim Sistemi';

// SEO künyesi ve hero metni artık yönetim panelinden düzenlenebiliyor.
// icerik_blogu() kayıt bulamazsa (ya da göç 004 uygulanmadıysa)
// includes/tanitim_icerik.php'deki varsayılana düşer — sayfa her
// koşulda eksiksiz çizilir.
$seo         = icerik_blogu('seo', varsayilan_seo());
$sayfa_basi  = $seo['baslik'];
$aciklama    = $seo['govde'];

$hero        = icerik_blogu('hero', varsayilan_hero());
$neden       = icerik_blogu('neden_ays', varsayilan_neden_ays());

// SSS — hem sayfada hem de FAQPage yapısal verisinde kullanılır (tek kaynak)
$sss = sss_listesi(varsayilan_sss());

// ── Yapısal veri (JSON-LD): yazılım künyesi + SSS ────────────
$jsonld = [
    '@context' => 'https://schema.org',
    '@graph'   => [
        [
            '@type'               => 'SoftwareApplication',
            'name'                => 'AYS — Apartman Yönetim Sistemi',
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem'     => 'Web',
            'url'                 => $site_url . '/',
            'description'         => $aciklama,
            'inLanguage'          => 'tr-TR',
            'offers'              => [
                '@type'         => 'Offer',
                'price'         => '0',
                'priceCurrency' => 'TRY',
            ],
        ],
        [
            '@type'      => 'FAQPage',
            'mainEntity' => array_map(fn($x) => [
                '@type'          => 'Question',
                'name'           => $x['s'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $x['c']],
            ], $sss),
        ],
    ],
];

// Özellik kartları
$ozellikler = [
    ['💰', 'Otomatik Aidat Takibi',   'Dönem aralığı seçin, tüm daireler için aidat kayıtları saniyeler içinde oluşsun. Ödeme geldikçe tek tıkla işaretleyin.'],
    ['💬', 'WhatsApp Bildirimleri',   'Borçlu daire sakinine ödenmemiş dönemleri içeren hazır mesajı WhatsApp üzerinden tek tıkla iletin.'],
    ['📊', 'Gelir & Gider Raporları', 'Kategori bazlı gider takibi, tahsilat oranı ve aylık gelir-gider trendiyle kasanın durumunu anlık görün.'],
    ['🖨️', 'Tek Tıkla A4 Rapor',      'Aidat, gider, trend ve daire geçmişi raporlarını imza alanlı, A4’e optimize düzende yazdırın.'],
    ['🏢', 'Kolay Daire Yönetimi',    'Daire, kat, sakin, telefon ve aidat tutarını tek ekrandan yönetin; her daire için tüm dönem geçmişini görün.'],
    ['📱', 'Mobil Uyumlu Panel',      'Telefon, tablet ve masaüstünde aynı deneyim. Uygulama kurmadan tarayıcıdan yönetin.'],
];

// "Neden AYS?" maddeleri
$avantajlar = [
    ['⏱️', 'Zaman tasarrufu',   'Excel tablolarıyla uğraşmayı bırakın. Toplu dönem oluşturma ve toplu ödeme girişiyle aylık rutin dakikalar sürer.'],
    ['🔍', 'Şeffaf yönetim',    'Hangi daire ne ödedi, para nereye gitti? Dekont numarasına kadar kayıt altında, denetime hazır.'],
    ['📱', 'Her yerden erişim', 'Kapıcıyla konuşurken telefondan, toplantıda tabletten. Verileriniz her cihazda güncel.'],
    ['🔒', 'Güvenli altyapı',   'Şifreli parola saklama, CSRF korumalı formlar ve apartmanlar arası tam veri izolasyonu.'],
];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="theme-color" content="#0d0d1a">

  <title><?= e($sayfa_basi) ?></title>
  <meta name="description" content="<?= e($aciklama) ?>">
  <meta name="keywords" content="apartman yönetim yazılımı, site aidat takip sistemi, aidat takip programı, apartman aidat takibi, site yönetim programı, apartman yönetim sistemi, aidat tahsilat takibi, apartman gider takibi">
  <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large">
  <meta name="author" content="AYS">
  <link rel="canonical" href="<?= e($site_url) ?>/">
  <link rel="manifest" href="/manifest.json">
  <link rel="icon" href="<?= varlik('/assets/icons/favicon-32.png') ?>" sizes="32x32">
  <link rel="apple-touch-icon" href="<?= varlik('/assets/icons/apple-touch-icon.png') ?>">

  <!-- Open Graph -->
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="<?= e($site_adi) ?>">
  <meta property="og:locale" content="tr_TR">
  <meta property="og:title" content="<?= e($sayfa_basi) ?>">
  <meta property="og:description" content="<?= e($aciklama) ?>">
  <meta property="og:url" content="<?= e($site_url) ?>/">

  <!-- Twitter Card -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= e($sayfa_basi) ?>">
  <meta name="twitter:description" content="<?= e($aciklama) ?>">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= varlik('/assets/style.css') ?>">
  <link rel="stylesheet" href="<?= varlik('/assets/landing.css') ?>">
  <script src="<?= varlik('/assets/pwa-install.js') ?>" defer></script>

  <script type="application/ld+json">
  <?= json_encode($jsonld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>
  </script>
</head>
<body class="lp-body">

<a href="#lp-main" class="lp-skip">İçeriğe geç</a>

<!-- ══ ÜST BAR ═══════════════════════════════════════════════ -->
<header class="lp-nav">
  <div class="lp-container lp-nav-inner">
    <a href="/" class="lp-brand" aria-label="AYS ana sayfa">
      <span class="lp-brand-icon"><img src="<?= varlik('/assets/icons/icon-192.png') ?>" alt="AYS logosu"></span>
      <span class="lp-brand-text">AYS</span>
    </a>
    <nav class="lp-nav-links" aria-label="Bölümler">
      <a href="#ozellikler">Özellikler</a>
      <a href="#neden">Neden AYS?</a>
      <a href="#sss">SSS</a>
    </nav>
    <div class="lp-nav-cta">
      <a href="/login.php" class="lp-btn lp-btn-ghost">Giriş Yap</a>
      <a href="/login.php?mod=kayit" class="lp-btn lp-btn-primary">Hemen Başla</a>
    </div>
  </div>
</header>

<main id="lp-main">

  <!-- ══ HERO ════════════════════════════════════════════════ -->
  <section class="lp-hero">
    <div class="lp-hero-bg" aria-hidden="true">
      <span class="lp-orb lp-orb-1"></span>
      <span class="lp-orb lp-orb-2"></span>
      <span class="lp-orb lp-orb-3"></span>
    </div>
    <div class="lp-container lp-hero-inner">
      <p class="lp-badge">Apartman ve site yönetimleri için</p>
      <h1 class="lp-hero-title">
        <?php
        // Başlıkta '|' varsa ilk bölüm düz, ikinci bölüm degrade
        // vurgu ile çizilir. Ayraç yoksa başlık tek parça gösterilir —
        // panelden girilen her metin geçerli kalır.
        [$hero_ust, $hero_vurgu] = array_pad(explode('|', $hero['baslik'], 2), 2, null);
        echo e(trim($hero_ust));
        if ($hero_vurgu !== null && trim($hero_vurgu) !== '') {
            echo '<br><span class="lp-grad">' . e(trim($hero_vurgu)) . '</span>';
        }
        ?>
      </h1>
      <p class="lp-hero-sub"><?= e($hero['govde']) ?></p>
      <div class="lp-hero-cta">
        <a href="/login.php?mod=kayit" class="lp-btn lp-btn-primary lp-btn-lg">Hemen Başla — Ücretsiz</a>
        <a href="/login.php" class="lp-btn lp-btn-outline lp-btn-lg">Giriş Yap</a>
      </div>
      <ul class="lp-hero-points">
        <li>✓ Kredi kartı gerekmez</li>
        <li>✓ Kurulum gerektirmez</li>
        <li>✓ Sınırsız dönem kaydı</li>
      </ul>
    </div>
  </section>

  <!-- ══ ÖZELLİKLER ══════════════════════════════════════════ -->
  <section id="ozellikler" class="lp-section">
    <div class="lp-container">
      <header class="lp-section-head">
        <p class="lp-eyebrow">Özellikler</p>
        <h2 class="lp-section-title">Bir yönetimin ihtiyacı olan her şey</h2>
        <p class="lp-section-sub">
          Aidat toplamaktan rapor sunmaya kadar tüm süreç tek sistemde.
        </p>
      </header>
      <div class="lp-grid lp-grid-3">
        <?php foreach ($ozellikler as [$ikon, $baslik, $metin]): ?>
        <article class="lp-card">
          <span class="lp-card-icon" aria-hidden="true"><?= $ikon ?></span>
          <h3 class="lp-card-title"><?= e($baslik) ?></h3>
          <p class="lp-card-text"><?= e($metin) ?></p>
        </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ══ NEDEN AYS ═══════════════════════════════════════════ -->
  <section id="neden" class="lp-section lp-section-alt">
    <div class="lp-container">
      <header class="lp-section-head">
        <p class="lp-eyebrow">Avantajlar</p>
        <h2 class="lp-section-title"><?= e($neden['baslik']) ?></h2>
        <p class="lp-section-sub"><?= e($neden['govde']) ?></p>
      </header>
      <div class="lp-grid lp-grid-2">
        <?php foreach ($avantajlar as [$ikon, $baslik, $metin]): ?>
        <div class="lp-benefit">
          <span class="lp-benefit-icon" aria-hidden="true"><?= $ikon ?></span>
          <div>
            <h3 class="lp-benefit-title"><?= e($baslik) ?></h3>
            <p class="lp-card-text"><?= e($metin) ?></p>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="lp-cta-band">
        <div>
          <h2 class="lp-cta-title">Apartmanınızı bugün kurun</h2>
          <p class="lp-cta-text">Kayıt olun, daire sayınızı girin — sistem sizin için hazırlansın.</p>
        </div>
        <a href="/login.php?mod=kayit" class="lp-btn lp-btn-primary lp-btn-lg">Ücretsiz Hesap Oluştur</a>
      </div>
    </div>
  </section>

  <!-- ══ SSS ═════════════════════════════════════════════════ -->
  <section id="sss" class="lp-section">
    <div class="lp-container lp-narrow">
      <header class="lp-section-head">
        <p class="lp-eyebrow">SSS</p>
        <h2 class="lp-section-title">Sıkça Sorulan Sorular</h2>
      </header>
      <div class="lp-faq">
        <?php foreach ($sss as $i => $x): ?>
        <details class="lp-faq-item"<?= $i === 0 ? ' open' : '' ?>>
          <summary class="lp-faq-q"><?= e($x['s']) ?><span class="lp-faq-mark" aria-hidden="true"></span></summary>
          <div class="lp-faq-a"><p><?= e($x['c']) ?></p></div>
        </details>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

</main>

<!-- ══ FOOTER ════════════════════════════════════════════════ -->
<footer class="lp-footer">
  <div class="lp-container lp-footer-inner">
    <div class="lp-footer-brand">
      <a href="/" class="lp-brand">
        <span class="lp-brand-icon"><img src="<?= varlik('/assets/icons/icon-192.png') ?>" alt="AYS logosu"></span>
        <span class="lp-brand-text">AYS</span>
      </a>
      <p class="lp-footer-desc">
        Apartman ve site yönetimleri için aidat, gider ve raporlama yazılımı.
      </p>
    </div>

    <nav class="lp-footer-col" aria-label="Hızlı bağlantılar">
      <h3>Hızlı Bağlantılar</h3>
      <a href="#ozellikler">Özellikler</a>
      <a href="#neden">Neden AYS?</a>
      <a href="#sss">Sıkça Sorulan Sorular</a>
    </nav>

    <nav class="lp-footer-col" aria-label="Hesap">
      <h3>Hesap</h3>
      <a href="/login.php">Giriş Yap</a>
      <a href="/login.php?mod=kayit">Yeni Kayıt</a>
    </nav>

    <div class="lp-footer-col">
      <h3>Destek</h3>
      <p class="lp-footer-desc">
        Soru ve önerileriniz için yönetim panelindeki iletişim bilgilerinden
        bize ulaşabilirsiniz.
      </p>
    </div>
  </div>
  <div class="lp-container lp-footer-bottom">
    <p>&copy; <?= date('Y') ?> AYS — Apartman Yönetim Sistemi. Tüm hakları saklıdır.</p>
  </div>
</footer>

</body>
</html>
