<?php
// ============================================================
//  yonetim/siteler.php — TÜM SİTELER
//
//  Arama, durum filtresi, sayfalama. Bir sitenin askıya alınması
//  yöneticilerinin girişini engellemez ama site aktif_site_belirle()
//  tarafından reddedilir; kullanıcı o siteye geçemez.
// ============================================================

require_once __DIR__ . '/ortak.php';
$yonetici = yonetim_kontrol();

// ─── Durum değiştirme ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    yonetim_yazma_kontrol();
    $site_id = (int)($_POST['site_id'] ?? 0);
    $durum   = ($_POST['durum'] ?? '') === 'askida' ? 'askida' : 'aktif';

    $st = db()->prepare("UPDATE siteler SET durum = ? WHERE id = ?");
    $st->execute([$durum, $site_id]);

    yonetim_denetim('site_durumu_degistirildi', 'site', $site_id, ['durum' => $durum]);
    flash('Site durumu güncellendi: ' . $durum);
    header('Location: /yonetim/siteler.php?' . http_build_query(array_diff_key($_GET, ['s' => 1])));
    exit;
}

// ─── Filtreler ──────────────────────────────────────────────
$arama  = trim($_GET['q'] ?? '');
$durum  = in_array($_GET['durum'] ?? '', ['aktif', 'askida'], true) ? $_GET['durum'] : '';

$kosul = [];
$parametre = [];
if ($arama !== '') {
    // Türkçe büyük/küçük harf farkını yutmak için iki yönlü LIKE
    $kosul[] = '(s.ad LIKE ? OR s.adres LIKE ?)';
    $parametre[] = '%' . $arama . '%';
    $parametre[] = '%' . $arama . '%';
}
if ($durum !== '') {
    $kosul[] = 's.durum = ?';
    $parametre[] = $durum;
}
$where = $kosul ? 'WHERE ' . implode(' AND ', $kosul) : '';

[$limit, $ofset, $sayfa] = yonetim_sayfalama(25);

$sayim = db()->prepare("SELECT COUNT(*) FROM siteler s $where");
$sayim->execute($parametre);
$toplam = (int)$sayim->fetchColumn();

// Daire/kullanıcı sayıları alt sorgu ile alınır; JOIN + GROUP BY
// kullanılsaydı LIMIT'ten önce satırlar çoğalır, sayfalama bozulurdu.
$st = db()->prepare("
    SELECT s.id, s.ad, s.tip, s.durum, s.olusturma, s.toplam_daire,
           (SELECT COUNT(*) FROM daireler d WHERE d.site_id = s.id) AS daire_sayisi,
           (SELECT COUNT(*) FROM kullanici_site_yetkileri y
             WHERE y.site_id = s.id AND y.durum='aktif')            AS yonetici_sayisi,
           (SELECT MAX(k.son_giris) FROM kullanici_site_yetkileri y2
              JOIN kullanicilar k ON k.id = y2.kullanici_id
             WHERE y2.site_id = s.id)                               AS son_giris
    FROM siteler s
    $where
    ORDER BY s.olusturma DESC, s.id DESC
    LIMIT $limit OFFSET $ofset
");
$st->execute($parametre);
$siteler = $st->fetchAll();

yonetim_basla($yonetici, 'Siteler');
?>

<form method="get" class="y-filtre">
  <input type="search" name="q" value="<?= e($arama) ?>" placeholder="Site adı veya adres ara…">
  <select name="durum">
    <option value="">Tüm durumlar</option>
    <option value="aktif"  <?= $durum === 'aktif'  ? 'selected' : '' ?>>Aktif</option>
    <option value="askida" <?= $durum === 'askida' ? 'selected' : '' ?>>Askıda</option>
  </select>
  <button type="submit">Filtrele</button>
  <?php if ($arama !== '' || $durum !== ''): ?>
    <a href="/yonetim/siteler.php" class="y-ikincil-baglanti">Temizle</a>
  <?php endif; ?>
</form>

<div class="y-bolum">
  <table class="y-tablo">
    <thead>
      <tr>
        <th>Site</th><th>Tip</th><th>Daire</th><th>Yönetici</th>
        <th>Son giriş</th><th>Kayıt</th><th>Durum</th><th></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($siteler as $s): ?>
      <tr>
        <td><a href="/yonetim/site_detay.php?id=<?= (int)$s['id'] ?>"><?= e_buyuk($s['ad']) ?></a></td>
        <td class="y-soluk"><?= e($s['tip']) ?></td>
        <td><?= (int)$s['daire_sayisi'] ?></td>
        <td><?= (int)$s['yonetici_sayisi'] ?></td>
        <td class="y-soluk"><?= $s['son_giris'] ? e(date('d.m.Y', strtotime($s['son_giris']))) : '—' ?></td>
        <td class="y-soluk"><?= e(date('d.m.Y', strtotime($s['olusturma']))) ?></td>
        <td>
          <span class="y-rozet <?= $s['durum'] === 'aktif' ? 'y-rozet-basari' : 'y-rozet-uyari' ?>">
            <?= e($s['durum']) ?>
          </span>
        </td>
        <td class="y-sag">
          <?php if (platform_yazabilir_mi($yonetici['platform_rolu'])): ?>
          <form method="post" class="y-satir-form"
                onsubmit="return confirm('<?= $s['durum'] === 'aktif' ? 'Site askıya alınsın mı?' : 'Site tekrar aktifleştirilsin mi?' ?>')">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="site_id" value="<?= (int)$s['id'] ?>">
            <input type="hidden" name="durum" value="<?= $s['durum'] === 'aktif' ? 'askida' : 'aktif' ?>">
            <button type="submit" class="y-mini">
              <?= $s['durum'] === 'aktif' ? 'Askıya al' : 'Aktifleştir' ?>
            </button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$siteler): ?>
      <tr><td colspan="8" class="y-bos">Ölçütlere uyan site yok.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
  <?php yonetim_sayfalama_ciz($sayfa, $toplam, $limit); ?>
</div>

<?php yonetim_bitir(); ?>
