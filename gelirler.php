<?php
// ============================================================
//  gelirler.php — AİDAT DIŞI GELİRLER
//
//  Sistemde bugüne kadar tek gelir kaynağı aidattı. Kira (çatı/
//  cephe/dükkân), gecikme cezası, bağış ve demirbaş satışı gibi
//  gelirler kayda giremiyordu — bu, resmi bilanço raporunun (madde 7)
//  önkoşuluydu. giderler.php'nin gelir yönündeki karşılığıdır; aynı
//  desende (dönem, tarih, dekont no) tutulur.
// ============================================================
require_once 'config.php';
require_once 'includes/functions.php';

$sayfa_basligi = 'Gelirler';
$kullanici = giris_kontrol();
$db = db();

if (!bilanco_semasi_hazir_mi()) {
    include 'includes/header.php';
    echo '<div class="card"><p class="empty-state">Bu modül için 006 numaralı şema göçü '
       . 'uygulanmalıdır.</p></div>';
    include 'includes/footer.php';
    exit;
}

$site_id = (int)$kullanici['site_id'];
$donem   = $_GET['donem'] ?? date('Y-m');

// ─── FORM İŞLEME ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_kontrol();
    $islem = $_POST['islem'] ?? '';

    if ($islem === 'ekle' || $islem === 'guncelle') {
        $tur       = enum_deger($_POST['tur'] ?? '', GELIR_TURLERI, 'diger');
        $aciklama  = mb_substr(buyuk($_POST['aciklama'] ?? ''), 0, 255, 'UTF-8');
        $tutar     = max(0, (float)str_replace(',', '.', $_POST['tutar'] ?? 0));
        $tarih     = $_POST['tarih'] ?: date('Y-m-d');
        $dekont_no = mb_substr(buyuk($_POST['dekont_no'] ?? ''), 0, 50, 'UTF-8') ?: null;
        $kayit_donem = substr($tarih, 0, 7);

        if ($aciklama === '' || $tutar <= 0) {
            flash('Açıklama ve tutar zorunludur.', 'hata');
        } elseif ($islem === 'ekle') {
            $db->prepare("
                INSERT INTO gelirler (site_id, tur, aciklama, tutar, tarih, donem, dekont_no)
                VALUES (?,?,?,?,?,?,?)
            ")->execute([$site_id, $tur, $aciklama, $tutar, $tarih, $kayit_donem, $dekont_no]);
            flash('Gelir kaydedildi.');
        } else {
            $id = (int)($_POST['gelir_id'] ?? 0);
            $db->prepare("
                UPDATE gelirler SET tur=?, aciklama=?, tutar=?, tarih=?, donem=?, dekont_no=?
                WHERE id=? AND site_id=?
            ")->execute([$tur, $aciklama, $tutar, $tarih, $kayit_donem, $dekont_no, $id, $site_id]);
            flash('Gelir güncellendi.');
        }
    }

    if ($islem === 'sil') {
        $db->prepare("DELETE FROM gelirler WHERE id=? AND site_id=?")
           ->execute([(int)($_POST['gelir_id'] ?? 0), $site_id]);
        flash('Kayıt silindi.');
    }

    header("Location: /gelirler.php?donem=$donem");
    exit;
}

// ─── VERİ ÇEK ────────────────────────────────────────────────
$st = $db->prepare("SELECT * FROM gelirler WHERE site_id=? AND donem=? ORDER BY tarih DESC");
$st->execute([$site_id, $donem]);
$gelirler = $st->fetchAll();

$toplam = array_sum(array_column($gelirler, 'tutar'));

$tur_ozet = [];
foreach ($gelirler as $g) {
    $tur_ozet[$g['tur']] = ($tur_ozet[$g['tur']] ?? 0) + $g['tutar'];
}
arsort($tur_ozet);

$duzenle_id = (int)($_GET['duzenle'] ?? 0);
$duzenle = null;
if ($duzenle_id) {
    $st = $db->prepare("SELECT * FROM gelirler WHERE id=? AND site_id=?");
    $st->execute([$duzenle_id, $site_id]);
    $duzenle = $st->fetch();
}

include 'includes/header.php';
?>

<div class="toolbar">
  <div>
    <span class="muted">Bu dönem aidat dışı gelir:</span>
    <strong style="color:#2ecc71;font-size:20px;margin-left:8px"><?= para($toplam) ?></strong>
  </div>
  <button onclick="document.getElementById('modal-gelir').style.display='flex'" class="btn btn-primary btn-sm">
    + Gelir Ekle
  </button>
</div>

<?php if ($tur_ozet): ?>
<div class="kat-ozet-grid">
  <?php foreach ($tur_ozet as $tur => $tutar): ?>
  <div class="kat-card">
    <div class="kat-label"><?= e(etiket(GELIR_TURLERI, $tur)) ?></div>
    <div class="kat-tutar" style="color:#2ecc71"><?= para($tutar) ?></div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card p0">
  <div class="table-wrap">
  <table class="table">
    <thead><tr><th>Tür</th><th>Açıklama</th><th>Tutar</th><th>Tarih</th><th>Dekont No</th><th>İşlem</th></tr></thead>
    <tbody>
    <?php if (!$gelirler): ?>
      <tr><td colspan="6" class="empty-state">Bu dönem gelir kaydı bulunmuyor.</td></tr>
    <?php endif; ?>
    <?php foreach ($gelirler as $g): ?>
    <tr>
      <td><span class="badge badge-cat"><?= e(etiket(GELIR_TURLERI, $g['tur'])) ?></span></td>
      <td><?= e_buyuk($g['aciklama']) ?></td>
      <td><strong style="color:#2ecc71"><?= para((float)$g['tutar']) ?></strong></td>
      <td><?= tarih_format($g['tarih']) ?></td>
      <td><?= $g['dekont_no'] ? e_buyuk($g['dekont_no']) : '—' ?></td>
      <td>
        <a href="?duzenle=<?= $g['id'] ?>&donem=<?= e($donem) ?>" class="btn btn-sm btn-ghost">✎ Düzenle</a>
        <form method="post" style="display:inline">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="islem" value="sil">
          <input type="hidden" name="gelir_id" value="<?= $g['id'] ?>">
          <button type="submit" class="btn btn-sm btn-danger" data-confirm="Bu gelir kaydı silinsin mi?">✕ Sil</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    <?php if ($gelirler): ?>
    <tfoot>
      <tr>
        <td colspan="2"><strong>TOPLAM</strong></td>
        <td><strong style="color:#2ecc71"><?= para($toplam) ?></strong></td>
        <td colspan="3"></td>
      </tr>
    </tfoot>
    <?php endif; ?>
  </table>
  </div>
</div>

<div class="modal-overlay" id="modal-gelir" style="display:none">
  <div class="modal">
    <div class="modal-header">
      <h3>+ Gelir Ekle</h3>
      <button onclick="document.getElementById('modal-gelir').style.display='none'" class="modal-close">×</button>
    </div>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="islem" value="ekle">
      <div class="form-grid">
        <div class="form-group">
          <label>Tür</label>
          <select name="tur" class="input">
            <?php foreach (GELIR_TURLERI as $k => $ad): ?>
              <option value="<?= e($k) ?>"><?= e($ad) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Tutar (₺) <span class="req">*</span></label>
          <input type="number" name="tutar" step="0.01" min="0.01" class="input" required placeholder="0.00">
        </div>
        <div class="form-group full-width">
          <label>Açıklama <span class="req">*</span></label>
          <input type="text" name="aciklama" class="input buyuk" required placeholder="örn. ÇATI REKLAM KİRASI — EYLÜL">
        </div>
        <div class="form-group">
          <label>Tarih</label>
          <input type="date" name="tarih" class="input" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-group">
          <label>Dekont / Makbuz No</label>
          <input type="text" name="dekont_no" class="input buyuk">
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">💾 Kaydet</button>
        <button type="button" onclick="document.getElementById('modal-gelir').style.display='none'" class="btn btn-ghost">İptal</button>
      </div>
    </form>
  </div>
</div>

<?php if ($duzenle): ?>
<div class="modal-overlay" id="modal-duzenle" style="display:flex">
  <div class="modal">
    <div class="modal-header">
      <h3>✎ Gelir Düzenle</h3>
      <a href="/gelirler.php?donem=<?= e($donem) ?>" class="modal-close">×</a>
    </div>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="islem" value="guncelle">
      <input type="hidden" name="gelir_id" value="<?= $duzenle['id'] ?>">
      <div class="form-grid">
        <div class="form-group">
          <label>Tür</label>
          <select name="tur" class="input">
            <?php foreach (GELIR_TURLERI as $k => $ad): ?>
              <option value="<?= e($k) ?>" <?= $duzenle['tur']===$k?'selected':'' ?>><?= e($ad) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Tutar (₺) <span class="req">*</span></label>
          <input type="number" name="tutar" step="0.01" min="0.01" class="input" required value="<?= e($duzenle['tutar']) ?>">
        </div>
        <div class="form-group full-width">
          <label>Açıklama <span class="req">*</span></label>
          <input type="text" name="aciklama" class="input buyuk" required value="<?= e($duzenle['aciklama']) ?>">
        </div>
        <div class="form-group">
          <label>Tarih</label>
          <input type="date" name="tarih" class="input" value="<?= e($duzenle['tarih']) ?>">
        </div>
        <div class="form-group">
          <label>Dekont / Makbuz No</label>
          <input type="text" name="dekont_no" class="input buyuk" value="<?= e($duzenle['dekont_no'] ?? '') ?>">
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">💾 Kaydet</button>
        <a href="/gelirler.php?donem=<?= e($donem) ?>" class="btn btn-ghost">İptal</a>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
