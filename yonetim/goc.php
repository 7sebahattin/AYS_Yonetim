<?php
// ============================================================
//  yonetim/goc.php — ŞEMA GÖÇÜ YÖNETİMİ
//
//  Kök dizindeki /goc.php ile aynı işi yapar ama farklı bir kapıdan:
//  orada GOC_ANAHTARI gerekir (henüz süper admin yokken kullanılır),
//  burada kimliği doğrulanmış bir süper admin oturumu yeterlidir.
//
//  DDL geri alınamaz: MySQL/MariaDB'de ALTER/CREATE transaction'a
//  girmez. Bu yüzden çalıştırmadan önce yedek uyarısı gösterilir ve
//  onay kutusu işaretlenmeden düğme çalışmaz.
// ============================================================

require_once __DIR__ . '/ortak.php';
require_once __DIR__ . '/../includes/goc.php';

$yonetici = yonetim_kontrol();
$sonuclar = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    yonetim_yazma_kontrol();

    if (empty($_POST['yedek_aldim'])) {
        flash('Yedek onayı işaretlenmeden göç çalıştırılamaz.', 'hata');
    } else {
        $bekleyen_once = bekleyen_gocler();
        $sonuclar      = tum_gocleri_uygula();

        $basarili = count(array_filter($sonuclar, fn($s) => $s['basarili']));
        yonetim_denetim('goc_calistirildi', 'platform', null, [
            'bekleyen'  => $bekleyen_once,
            'basarili'  => $basarili,
            'toplam'    => count($sonuclar),
        ]);

        if (!$sonuclar) {
            flash('Bekleyen göç yok.');
        } elseif ($basarili === count($sonuclar)) {
            flash($basarili . ' göç uygulandı.');
        } else {
            flash('Göç sırasında hata oluştu — aşağıdaki çıktıya bakın.', 'hata');
        }
    }
}

$uygulanmis = uygulanmis_gocler();
$bekleyen   = bekleyen_gocler();

// Uygulanma zamanları
$zamanlar = [];
try {
    goc_tablosunu_hazirla();
    foreach (db()->query("SELECT surum, uygulanma, sure_ms FROM sema_surumu") as $s) {
        $zamanlar[$s['surum']] = $s;
    }
} catch (Throwable $ex) {
    // sema_surumu okunamazsa liste zamansız gösterilir
}

$yazabilir = platform_yazabilir_mi($yonetici['platform_rolu']);
yonetim_basla($yonetici, 'Şema Göçü');
?>

<?php if ($sonuclar): ?>
<div class="y-bolum">
  <h2>Çalıştırma çıktısı</h2>
  <pre class="y-cikti"><?php foreach ($sonuclar as $s): ?>
<?= $s['basarili'] ? '✓' : '✕' ?> <?= e($s['mesaj']) ?>
<?php endforeach; ?></pre>
</div>
<?php endif; ?>

<div class="y-bolum">
  <h2>Durum</h2>
  <table class="y-tablo">
    <thead><tr><th>Göç</th><th>Durum</th><th>Uygulanma</th><th>Süre</th></tr></thead>
    <tbody>
    <?php foreach (tum_gocler() as $dosya): ?>
      <?php $bitti = in_array($dosya, $uygulanmis, true); ?>
      <tr>
        <td><code><?= e($dosya) ?></code></td>
        <td><span class="y-rozet <?= $bitti ? 'y-rozet-basari' : 'y-rozet-uyari' ?>">
          <?= $bitti ? 'uygulandı' : 'bekliyor' ?></span></td>
        <td class="y-soluk">
          <?= isset($zamanlar[$dosya]) ? e(date('d.m.Y H:i', strtotime($zamanlar[$dosya]['uygulanma']))) : '—' ?>
        </td>
        <td class="y-soluk">
          <?= isset($zamanlar[$dosya]['sure_ms']) ? (int)$zamanlar[$dosya]['sure_ms'] . ' ms' : '—' ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php if ($bekleyen && $yazabilir): ?>
<div class="y-bolum">
  <h2><?= count($bekleyen) ?> göç bekliyor</h2>
  <div class="y-uyari y-uyari-uyari">
    <strong>Önce veritabanı yedeği alın.</strong> MySQL/MariaDB'de DDL işlemleri
    transaction'a girmez; yarıda kalan bir göç geri alınamaz ve elle temizlenmesi gerekir.
  </div>
  <form method="post" class="y-form"
        onsubmit="return confirm('Bekleyen göçler uygulanacak. Yedeğiniz hazır mı?')">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <label class="y-onay">
      <input type="checkbox" name="yedek_aldim" value="1" required>
      Veritabanı yedeğini aldım
    </label>
    <button type="submit" class="y-tehlike-btn">Bekleyen göçleri uygula</button>
  </form>
</div>
<?php elseif (!$bekleyen): ?>
<div class="y-bolum"><p class="y-bos">Tüm göçler uygulanmış.</p></div>
<?php endif; ?>

<?php yonetim_bitir(); ?>
