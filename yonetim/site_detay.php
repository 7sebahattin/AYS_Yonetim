<?php
// ============================================================
//  yonetim/site_detay.php — SİTE DETAYI
//
//  Bir apartmanın künyesi, yöneticileri, mali özeti ve destek
//  işlemleri (askıya alma, yöneticiye şifre sıfırlama bağlantısı
//  gönderme, kullanıcı adına görüntüleme).
// ============================================================

require_once __DIR__ . '/ortak.php';
require_once __DIR__ . '/../includes/kimlik.php';

$yonetici = yonetim_kontrol();
$site_id  = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    yonetim_yazma_kontrol();
    $site_id = (int)($_POST['site_id'] ?? 0);
    $islem   = $_POST['islem'] ?? '';

    if ($islem === 'durum') {
        $durum = ($_POST['durum'] ?? '') === 'askida' ? 'askida' : 'aktif';
        db()->prepare("UPDATE siteler SET durum = ? WHERE id = ?")->execute([$durum, $site_id]);
        yonetim_denetim('site_durumu_degistirildi', 'site', $site_id, ['durum' => $durum]);
        flash('Site durumu güncellendi: ' . $durum);
    }

    if ($islem === 'sifirlama_gonder') {
        $hedef = (int)($_POST['kullanici_id'] ?? 0);

        // Hedefin gerçekten BU sitenin yöneticisi olduğu doğrulanır:
        // aksi halde form değiştirilerek herhangi bir hesaba sıfırlama
        // bağlantısı tetiklenebilirdi.
        $st = db()->prepare("
            SELECT k.id, k.eposta, k.eposta_dogrulandi
            FROM kullanici_site_yetkileri y JOIN kullanicilar k ON k.id = y.kullanici_id
            WHERE y.site_id = ? AND y.kullanici_id = ?
        ");
        $st->execute([$site_id, $hedef]);
        $kisi = $st->fetch();

        if (!$kisi) {
            flash('Bu kullanıcı bu sitenin yöneticisi değil.', 'hata');
        } elseif (empty($kisi['eposta'])) {
            flash('Kullanıcının kayıtlı e-posta adresi yok.', 'hata');
        } elseif (!eposta_yapilandirildi_mi()) {
            flash('SMTP yapılandırılmamış; e-posta gönderilemiyor.', 'hata');
        } else {
            $ok = sifre_sifirlama_gonder((int)$kisi['id'], $kisi['eposta']);
            yonetim_denetim('sifirlama_baglantisi_gonderildi', 'kullanici', (int)$kisi['id'],
                            ['site_id' => $site_id, 'sonuc' => $ok ? 'gonderildi' : 'hata']);
            flash($ok ? 'Sıfırlama bağlantısı gönderildi.' : 'E-posta gönderilemedi (kayda bakın).',
                  $ok ? 'basari' : 'hata');
        }
    }

    header('Location: /yonetim/site_detay.php?id=' . $site_id);
    exit;
}

$st = db()->prepare("SELECT * FROM siteler WHERE id = ?");
$st->execute([$site_id]);
$site = $st->fetch();

if (!$site) {
    yonetim_basla($yonetici, 'Site bulunamadı');
    echo '<div class="y-bolum"><p class="y-bos">Bu site kaydı yok.</p>'
       . '<p><a href="/yonetim/siteler.php">← Site listesi</a></p></div>';
    yonetim_bitir();
    exit;
}

// ─── Yöneticiler ────────────────────────────────────────────
$st = db()->prepare("
    SELECT k.id, k.kullanici_adi, k.eposta, k.eposta_dogrulandi, k.son_giris,
           k.platform_rolu, y.rol, y.durum
    FROM kullanici_site_yetkileri y JOIN kullanicilar k ON k.id = y.kullanici_id
    WHERE y.site_id = ? ORDER BY k.kullanici_adi
");
$st->execute([$site_id]);
$yoneticiler = $st->fetchAll();

// ─── Bloklar ────────────────────────────────────────────────
$bloklar = site_bloklari($site_id);

// ─── Mali özet (son 6 dönem) ────────────────────────────────
$st = db()->prepare("
    SELECT donem,
           SUM(CASE WHEN durum='odendi' THEN tutar ELSE 0 END) AS tahsilat,
           SUM(CASE WHEN durum<>'odendi' THEN tutar ELSE 0 END) AS bekleyen
    FROM aidatlar WHERE site_id = ?
    GROUP BY donem ORDER BY donem DESC LIMIT 6
");
$st->execute([$site_id]);
$aidat_ozet = $st->fetchAll();

$st = db()->prepare("SELECT donem, SUM(tutar) AS toplam FROM giderler
                     WHERE site_id = ? GROUP BY donem ORDER BY donem DESC LIMIT 6");
$st->execute([$site_id]);
$gider_ozet = array_column($st->fetchAll(), 'toplam', 'donem');

// ─── Veri hacmi ─────────────────────────────────────────────
$sayimlar = [];
foreach (['daireler' => 'Daire', 'aidatlar' => 'Aidat kaydı', 'giderler' => 'Gider kaydı'] as $tablo => $ad) {
    $st = db()->prepare("SELECT COUNT(*) FROM `$tablo` WHERE site_id = ?");
    $st->execute([$site_id]);
    $sayimlar[$ad] = (int)$st->fetchColumn();
}

// ─── Bu siteye ait son denetim kayıtları ────────────────────
$st = db()->prepare("
    SELECT dk.eylem, dk.olusturma, k.kullanici_adi
    FROM denetim_kaydi dk LEFT JOIN kullanicilar k ON k.id = dk.kullanici_id
    WHERE dk.site_id = ? ORDER BY dk.id DESC LIMIT 10
");
$st->execute([$site_id]);
$olaylar = $st->fetchAll();

$yazabilir = platform_yazabilir_mi($yonetici['platform_rolu']);
yonetim_basla($yonetici, e_buyuk($site['ad']));
?>

<p class="y-geri"><a href="/yonetim/siteler.php">← Site listesi</a></p>

<div class="y-kart-izgara">
  <div class="y-kart"><span class="y-kart-etiket">Durum</span>
    <strong class="y-kart-deger y-kucuk"><?= e($site['durum']) ?></strong>
    <span class="y-kart-alt"><?= e($site['tip']) ?></span></div>
  <?php foreach ($sayimlar as $ad => $adet): ?>
  <div class="y-kart"><span class="y-kart-etiket"><?= e($ad) ?></span>
    <strong class="y-kart-deger"><?= $adet ?></strong>
    <span class="y-kart-alt">&nbsp;</span></div>
  <?php endforeach; ?>
</div>

<div class="y-ikili">
  <div class="y-bolum">
    <h2>Künye</h2>
    <table class="y-tablo y-tablo-kunye">
      <tr><th>Ad</th><td><?= e_buyuk($site['ad']) ?></td></tr>
      <tr><th>Adres</th><td><?= e_buyuk($site['adres'] ?? '') ?: '—' ?></td></tr>
      <tr><th>Telefon</th><td><?= e($site['telefon'] ?? '') ?: '—' ?></td></tr>
      <tr><th>Beyan edilen daire</th><td><?= (int)$site['toplam_daire'] ?></td></tr>
      <tr><th>Bloklar</th><td>
        <?= $bloklar ? e(implode(', ', array_map(fn($b) => turkce_buyuk($b['ad']), $bloklar))) : '—' ?>
      </td></tr>
      <tr><th>Kayıt</th><td><?= e(date('d.m.Y H:i', strtotime($site['olusturma']))) ?></td></tr>
    </table>

    <?php if ($yazabilir): ?>
    <form method="post" class="y-form y-satir-form"
          onsubmit="return confirm('<?= $site['durum'] === 'aktif' ? 'Site askıya alınsın mı? Yöneticileri bu siteye erişemez.' : 'Site aktifleştirilsin mi?' ?>')">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="islem" value="durum">
      <input type="hidden" name="site_id" value="<?= (int)$site['id'] ?>">
      <input type="hidden" name="durum" value="<?= $site['durum'] === 'aktif' ? 'askida' : 'aktif' ?>">
      <button type="submit" class="<?= $site['durum'] === 'aktif' ? 'y-tehlike-btn' : '' ?>">
        <?= $site['durum'] === 'aktif' ? 'Siteyi askıya al' : 'Siteyi aktifleştir' ?>
      </button>
    </form>
    <?php endif; ?>
  </div>

  <div class="y-bolum">
    <h2>Son 6 dönem</h2>
    <table class="y-tablo">
      <thead><tr><th>Dönem</th><th>Tahsilat</th><th>Bekleyen</th><th>Gider</th></tr></thead>
      <tbody>
      <?php foreach ($aidat_ozet as $a): ?>
        <tr>
          <td><?= e(donem_adi($a['donem'])) ?></td>
          <td><?= para((float)$a['tahsilat']) ?></td>
          <td class="y-soluk"><?= para((float)$a['bekleyen']) ?></td>
          <td><?= para((float)($gider_ozet[$a['donem']] ?? 0)) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$aidat_ozet): ?><tr><td colspan="4" class="y-bos">Aidat kaydı yok</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="y-bolum">
  <h2>Yöneticiler</h2>
  <table class="y-tablo">
    <thead><tr><th>Kullanıcı</th><th>E-posta</th><th>Site rolü</th><th>Son giriş</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($yoneticiler as $k): ?>
      <tr>
        <td>
          <a href="/yonetim/kullanicilar.php?q=<?= e($k['kullanici_adi']) ?>"><?= e($k['kullanici_adi']) ?></a>
          <?php if ($k['platform_rolu'] !== 'kullanici'): ?>
            <span class="y-rozet y-rozet-mor"><?= e($k['platform_rolu']) ?></span>
          <?php endif; ?>
        </td>
        <td class="y-soluk">
          <?= e($k['eposta'] ?? '') ?: '—' ?>
          <?php if ($k['eposta'] && !$k['eposta_dogrulandi']): ?>
            <span class="y-rozet y-rozet-uyari">doğrulanmadı</span>
          <?php endif; ?>
        </td>
        <td><?= e($k['rol']) ?><?= $k['durum'] === 'pasif' ? ' (pasif)' : '' ?></td>
        <td class="y-soluk"><?= $k['son_giris'] ? e(date('d.m.Y H:i', strtotime($k['son_giris']))) : '—' ?></td>
        <td class="y-sag">
          <?php if ($yazabilir && !empty($k['eposta'])): ?>
          <form method="post" class="y-satir-form"
                onsubmit="return confirm('Bu kullanıcıya şifre sıfırlama bağlantısı gönderilsin mi?')">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="islem" value="sifirlama_gonder">
            <input type="hidden" name="site_id" value="<?= (int)$site['id'] ?>">
            <input type="hidden" name="kullanici_id" value="<?= (int)$k['id'] ?>">
            <button type="submit" class="y-mini">Sıfırlama gönder</button>
          </form>
          <?php endif; ?>
          <?php if ($yazabilir): ?>
          <form method="post" action="/yonetim/burun.php" class="y-satir-form"
                onsubmit="return confirm('Bu kullanıcının ekranını salt-okunur olarak görüntülemek üzeresiniz. İşlem denetim kaydına yazılacak.')">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="islem" value="basla">
            <input type="hidden" name="kullanici_id" value="<?= (int)$k['id'] ?>">
            <input type="hidden" name="site_id" value="<?= (int)$site['id'] ?>">
            <button type="submit" class="y-mini">Adına görüntüle</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$yoneticiler): ?>
      <tr><td colspan="5" class="y-bos">Bu siteye tanımlı kullanıcı yok — veri erişilemez durumda.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="y-bolum">
  <h2>Bu sitedeki son olaylar</h2>
  <table class="y-tablo">
    <thead><tr><th>Eylem</th><th>Kullanıcı</th><th>Zaman</th></tr></thead>
    <tbody>
    <?php foreach ($olaylar as $o): ?>
      <tr><td><code><?= e($o['eylem']) ?></code></td>
          <td><?= e($o['kullanici_adi'] ?? '—') ?></td>
          <td class="y-soluk"><?= e(date('d.m.Y H:i', strtotime($o['olusturma']))) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$olaylar): ?><tr><td colspan="3" class="y-bos">Kayıt yok</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php yonetim_bitir(); ?>
