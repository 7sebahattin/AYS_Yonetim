<?php
// ============================================================
//  yonetim/denetim.php — DENETİM KAYDI GÖRÜNÜMÜ
//
//  Faz 0-D'de yazılmaya başlanan kayıtların filtrelenebilir hali.
//  Denetim kaydı yalnızca OKUNUR: panelden silme veya düzenleme yolu
//  bilinçli olarak yoktur — silinebilen bir denetim izi denetim izi
//  değildir.
// ============================================================

require_once __DIR__ . '/ortak.php';
$yonetici = yonetim_kontrol();

// ─── Filtreler ──────────────────────────────────────────────
$eylem    = trim($_GET['eylem'] ?? '');
$kullanici = trim($_GET['kullanici'] ?? '');
$baslangic = trim($_GET['baslangic'] ?? '');
$bitis     = trim($_GET['bitis'] ?? '');
$sadece_yonetim = !empty($_GET['yonetim']);

$kosul = [];
$parametre = [];

if ($eylem !== '') {
    $kosul[] = 'dk.eylem LIKE ?';
    $parametre[] = '%' . $eylem . '%';
}
if ($kullanici !== '') {
    $kosul[] = 'k.kullanici_adi LIKE ?';
    $parametre[] = '%' . $kullanici . '%';
}
// Tarih girdileri yalnızca YYYY-MM-DD biçiminde kabul edilir; biçimi
// tutmayan değer sorguya hiç girmez.
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $baslangic)) {
    $kosul[] = 'dk.olusturma >= ?';
    $parametre[] = $baslangic . ' 00:00:00';
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $bitis)) {
    $kosul[] = 'dk.olusturma <= ?';
    $parametre[] = $bitis . ' 23:59:59';
}
if ($sadece_yonetim) {
    $kosul[] = "dk.eylem LIKE 'yonetim_%'";
}
$where = $kosul ? 'WHERE ' . implode(' AND ', $kosul) : '';

[$limit, $ofset, $sayfa] = yonetim_sayfalama(50);

$sayim = db()->prepare("SELECT COUNT(*) FROM denetim_kaydi dk
                        LEFT JOIN kullanicilar k ON k.id = dk.kullanici_id $where");
$sayim->execute($parametre);
$toplam = (int)$sayim->fetchColumn();

$st = db()->prepare("
    SELECT dk.id, dk.eylem, dk.hedef_tur, dk.hedef_id, dk.detay, dk.ip_adresi,
           dk.olusturma, dk.site_id, k.kullanici_adi, s.ad AS site_adi
    FROM denetim_kaydi dk
    LEFT JOIN kullanicilar k ON k.id = dk.kullanici_id
    LEFT JOIN siteler s      ON s.id = dk.site_id
    $where
    ORDER BY dk.id DESC
    LIMIT $limit OFFSET $ofset
");
$st->execute($parametre);
$kayitlar = $st->fetchAll();

// Filtre kutusu için sistemde fiilen geçen eylem adları
$eylemler = db()->query("SELECT DISTINCT eylem FROM denetim_kaydi ORDER BY eylem")
                ->fetchAll(PDO::FETCH_COLUMN);

yonetim_basla($yonetici, 'Denetim Kaydı');
?>

<form method="get" class="y-filtre">
  <input list="eylem-listesi" name="eylem" value="<?= e($eylem) ?>" placeholder="Eylem…">
  <datalist id="eylem-listesi">
    <?php foreach ($eylemler as $ey): ?><option value="<?= e($ey) ?>"><?php endforeach; ?>
  </datalist>
  <input type="search" name="kullanici" value="<?= e($kullanici) ?>" placeholder="Kullanıcı…">
  <input type="date" name="baslangic" value="<?= e($baslangic) ?>">
  <input type="date" name="bitis" value="<?= e($bitis) ?>">
  <label class="y-onay">
    <input type="checkbox" name="yonetim" value="1" <?= $sadece_yonetim ? 'checked' : '' ?>>
    Yalnızca panel işlemleri
  </label>
  <button type="submit">Filtrele</button>
  <a href="/yonetim/denetim.php" class="y-ikincil-baglanti">Temizle</a>
</form>

<div class="y-bolum">
  <table class="y-tablo y-tablo-dar">
    <thead>
      <tr><th>Zaman</th><th>Eylem</th><th>Kullanıcı</th><th>Site</th>
          <th>Hedef</th><th>IP</th><th>Detay</th></tr>
    </thead>
    <tbody>
    <?php foreach ($kayitlar as $k): ?>
      <?php $panel = str_starts_with($k['eylem'], 'yonetim_'); ?>
      <tr class="<?= $panel ? 'y-satir-vurgu' : '' ?>">
        <td class="y-soluk"><?= e(date('d.m.Y H:i:s', strtotime($k['olusturma']))) ?></td>
        <td><code><?= e($k['eylem']) ?></code></td>
        <td><?= e($k['kullanici_adi'] ?? '—') ?></td>
        <td class="y-soluk"><?= $k['site_adi'] ? e_buyuk($k['site_adi']) : '—' ?></td>
        <td class="y-soluk">
          <?= $k['hedef_tur'] ? e($k['hedef_tur']) . ' #' . (int)$k['hedef_id'] : '—' ?>
        </td>
        <td class="y-soluk"><?= e($k['ip_adresi'] ?? '') ?: '—' ?></td>
        <td class="y-detay-hucre"><?php
          // Detay JSON olarak saklanır; okunabilir olması için
          // anahtar=değer listesine çevrilir. Bozuk JSON ham gösterilir.
          $d = $k['detay'] ? json_decode($k['detay'], true) : null;
          if (is_array($d)) {
              $parcalar = [];
              foreach ($d as $anahtar => $deger) {
                  if (is_bool($deger)) $deger = $deger ? 'evet' : 'hayır';
                  if (is_array($deger)) $deger = json_encode($deger, JSON_UNESCAPED_UNICODE);
                  $parcalar[] = e($anahtar) . '=' . e((string)$deger);
              }
              echo implode(' · ', $parcalar);
          } else {
              echo e($k['detay'] ?? '') ?: '—';
          }
        ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$kayitlar): ?>
      <tr><td colspan="7" class="y-bos">Ölçütlere uyan kayıt yok.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
  <?php yonetim_sayfalama_ciz($sayfa, $toplam, $limit); ?>
</div>

<?php yonetim_bitir(); ?>
