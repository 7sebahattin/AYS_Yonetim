<?php
// ============================================================
//  yonetim/index.php — PLATFORM GENEL BAKIŞ
//
//  Tek bakışta: kaç site, kaç daire, kaç kullanıcı, ne kadar para
//  dönüyor, son 12 ayda büyüme nasıl, sistemde neler oluyor.
// ============================================================

require_once __DIR__ . '/ortak.php';
$yonetici = yonetim_kontrol();

$db = db();

// ─── Sayımlar ───────────────────────────────────────────────
$ozet = [
    'site'          => (int)$db->query("SELECT COUNT(*) FROM siteler")->fetchColumn(),
    'site_aktif'    => (int)$db->query("SELECT COUNT(*) FROM siteler WHERE durum='aktif'")->fetchColumn(),
    'daire'         => (int)$db->query("SELECT COUNT(*) FROM daireler")->fetchColumn(),
    'kullanici'     => (int)$db->query("SELECT COUNT(*) FROM kullanicilar")->fetchColumn(),
    'eposta_dogru'  => (int)$db->query("SELECT COUNT(*) FROM kullanicilar WHERE eposta_dogrulandi=1")->fetchColumn(),
];

// Son 30 günde en az bir kez giriş yapmış kullanıcı (aylık aktif).
$ozet['aktif_kullanici'] = (int)$db->query(
    "SELECT COUNT(*) FROM kullanicilar WHERE son_giris >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
)->fetchColumn();

// ─── Bu ayın para hacmi ─────────────────────────────────────
$donem = date('Y-m');
$st = $db->prepare("SELECT COALESCE(SUM(tutar),0) FROM aidatlar WHERE donem=? AND durum='odendi'");
$st->execute([$donem]);
$ozet['tahsilat'] = (float)$st->fetchColumn();

$st = $db->prepare("SELECT COALESCE(SUM(tutar),0) FROM giderler WHERE donem=?");
$st->execute([$donem]);
$ozet['gider'] = (float)$st->fetchColumn();

// ─── Büyüme: son 12 ayda açılan site sayısı ─────────────────
$buyume = $db->query("
    SELECT DATE_FORMAT(olusturma,'%Y-%m') AS ay, COUNT(*) AS adet
    FROM siteler
    WHERE olusturma >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
    GROUP BY ay ORDER BY ay
")->fetchAll();
$buyume_haritasi = array_column($buyume, 'adet', 'ay');

$aylar = [];
for ($i = 11; $i >= 0; $i--) {
    $ay = date('Y-m', strtotime("-$i months"));
    $aylar[$ay] = (int)($buyume_haritasi[$ay] ?? 0);
}
$zirve = max(1, max($aylar));

// ─── En büyük siteler ───────────────────────────────────────
$en_buyuk = $db->query("
    SELECT s.id, s.ad, s.durum, COUNT(d.id) AS daire_sayisi
    FROM siteler s LEFT JOIN daireler d ON d.site_id = s.id
    GROUP BY s.id, s.ad, s.durum
    ORDER BY daire_sayisi DESC, s.ad LIMIT 8
")->fetchAll();

// ─── Son olaylar ────────────────────────────────────────────
$son_olaylar = $db->query("
    SELECT dk.eylem, dk.olusturma, dk.ip_adresi, k.kullanici_adi
    FROM denetim_kaydi dk LEFT JOIN kullanicilar k ON k.id = dk.kullanici_id
    ORDER BY dk.id DESC LIMIT 12
")->fetchAll();

// ─── Dikkat gerektirenler ───────────────────────────────────
$uyarilar = [];
if (bakim_modu_aktif_mi()) {
    $uyarilar[] = ['Bakım modu açık — normal kullanıcılar panele giremiyor.', '/yonetim/ayarlar.php'];
}
if (!eposta_yapilandirildi_mi()) {
    $uyarilar[] = ['SMTP yapılandırılmamış — şifre sıfırlama e-postaları gönderilemiyor.', null];
}
require_once __DIR__ . '/../includes/goc.php';
$bekleyen = bekleyen_gocler();
if ($bekleyen) {
    $uyarilar[] = [count($bekleyen) . ' göç uygulanmayı bekliyor.', '/yonetim/goc.php'];
}
$sa_sayisi = superadmin_sayisi();
if ($sa_sayisi < 2) {
    $uyarilar[] = ['Yalnızca ' . $sa_sayisi . ' süper admin var. Telefonunuzu kaybederseniz '
                 . 'panele girecek ikinci bir hesap kalmaz.', '/yonetim/kullanicilar.php'];
}

yonetim_basla($yonetici, 'Genel Bakış');
?>

<?php if ($uyarilar): ?>
<div class="y-uyari-liste">
  <?php foreach ($uyarilar as [$metin, $yol]): ?>
    <div class="y-uyari y-uyari-uyari">
      <span><?= e($metin) ?></span>
      <?php if ($yol): ?><a href="<?= e($yol) ?>">Git →</a><?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="y-kart-izgara">
  <div class="y-kart">
    <span class="y-kart-etiket">Site / Apartman</span>
    <strong class="y-kart-deger"><?= $ozet['site'] ?></strong>
    <span class="y-kart-alt"><?= $ozet['site_aktif'] ?> aktif ·
      <?= $ozet['site'] - $ozet['site_aktif'] ?> askıda</span>
  </div>
  <div class="y-kart">
    <span class="y-kart-etiket">Toplam Daire</span>
    <strong class="y-kart-deger"><?= number_format($ozet['daire'], 0, ',', '.') ?></strong>
    <span class="y-kart-alt">tüm sitelerde</span>
  </div>
  <div class="y-kart">
    <span class="y-kart-etiket">Kullanıcı</span>
    <strong class="y-kart-deger"><?= $ozet['kullanici'] ?></strong>
    <span class="y-kart-alt"><?= $ozet['aktif_kullanici'] ?> son 30 günde aktif ·
      <?= $ozet['eposta_dogru'] ?> doğrulanmış e-posta</span>
  </div>
  <div class="y-kart">
    <span class="y-kart-etiket">Bu Ay Tahsilat</span>
    <strong class="y-kart-deger"><?= para($ozet['tahsilat']) ?></strong>
    <span class="y-kart-alt">gider: <?= para($ozet['gider']) ?></span>
  </div>
</div>

<div class="y-bolum">
  <h2>Son 12 ayda açılan site</h2>
  <div class="y-grafik">
    <?php foreach ($aylar as $ay => $adet): ?>
      <div class="y-cubuk-sarmal" title="<?= e(donem_adi($ay)) ?>: <?= $adet ?>">
        <span class="y-cubuk-deger"><?= $adet ?: '' ?></span>
        <div class="y-cubuk" style="height:<?= max(3, round($adet / $zirve * 100)) ?>%"></div>
        <span class="y-cubuk-etiket"><?= e(substr($ay, 5, 2) . '.' . substr($ay, 2, 2)) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="y-ikili">
  <div class="y-bolum">
    <h2>En büyük siteler</h2>
    <table class="y-tablo">
      <thead><tr><th>Site</th><th>Daire</th><th>Durum</th></tr></thead>
      <tbody>
      <?php foreach ($en_buyuk as $s): ?>
        <tr>
          <td><a href="/yonetim/site_detay.php?id=<?= (int)$s['id'] ?>"><?= e_buyuk($s['ad']) ?></a></td>
          <td><?= (int)$s['daire_sayisi'] ?></td>
          <td><span class="y-rozet <?= $s['durum'] === 'aktif' ? 'y-rozet-basari' : 'y-rozet-uyari' ?>">
            <?= e($s['durum']) ?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$en_buyuk): ?><tr><td colspan="3" class="y-bos">Kayıt yok</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="y-bolum">
    <h2>Son olaylar</h2>
    <table class="y-tablo">
      <thead><tr><th>Eylem</th><th>Kullanıcı</th><th>Zaman</th></tr></thead>
      <tbody>
      <?php foreach ($son_olaylar as $o): ?>
        <tr>
          <td><code><?= e($o['eylem']) ?></code></td>
          <td><?= e($o['kullanici_adi'] ?? '—') ?></td>
          <td class="y-soluk"><?= e(date('d.m.Y H:i', strtotime($o['olusturma']))) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$son_olaylar): ?><tr><td colspan="3" class="y-bos">Kayıt yok</td></tr><?php endif; ?>
      </tbody>
    </table>
    <p class="y-alt-baglanti"><a href="/yonetim/denetim.php">Tüm denetim kaydı →</a></p>
  </div>
</div>

<?php yonetim_bitir(); ?>
