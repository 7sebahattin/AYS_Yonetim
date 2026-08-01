<?php
// ============================================================
//  giderler.php — GİDER YÖNETİMİ
// ============================================================
require_once 'config.php';
require_once 'includes/functions.php';
$sayfa_basligi = 'Giderler';

$kullanici = giris_kontrol();
$db  = db();
$donem = $_GET['donem'] ?? date('Y-m');

$kategoriler = ['Temizlik','Elektrik','Su','Doğalgaz','Asansör',
                'Bahçe','Güvenlik','Tamirat','Yönetim','Sigorta','Diğer'];

// ─── FORM İŞLEME ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_kontrol();
    $islem = $_POST['islem'] ?? '';

    if ($islem === 'ekle') {
        $kategori   = in_array($_POST['kategori'], $kategoriler) ? $_POST['kategori'] : 'Diğer';
        $aciklama   = trim($_POST['aciklama'] ?? '');
        $tutar      = max(0, (float)str_replace(',', '.', $_POST['tutar'] ?? 0));
        $tarih      = $_POST['tarih'] ?: date('Y-m-d');
        $fatura_no  = trim($_POST['fatura_no'] ?? '');
        $kayit_donem = substr($tarih, 0, 7);

        if (!$aciklama || $tutar <= 0) {
            flash('Açıklama ve tutar zorunludur.', 'hata');
        } else {
            $db->prepare("INSERT INTO giderler (kullanici_id, kategori, aciklama, tutar, tarih, donem, fatura_no)
                          VALUES (?,?,?,?,?,?,?)")
               ->execute([$kullanici['id'], $kategori, $aciklama, $tutar, $tarih, $kayit_donem, $fatura_no]);
            flash('Gider kaydedildi.');
        }
    }

    if ($islem === 'sil') {
        $id = (int)$_POST['gider_id'];
        $db->prepare("DELETE FROM giderler WHERE id=? AND kullanici_id=?")->execute([$id, $kullanici['id']]);
        flash('Kayıt silindi.');
    }

    header("Location: /giderler.php?donem=$donem");
    exit;
}

// ─── VERİ ÇEK ────────────────────────────────────────────────
$stmt = $db->prepare("SELECT * FROM giderler WHERE kullanici_id=? AND donem=? ORDER BY tarih DESC");
$stmt->execute([$kullanici['id'], $donem]);
$giderler = $stmt->fetchAll();

$toplam = array_sum(array_column($giderler, 'tutar'));

$kat_ozet = [];
foreach ($giderler as $g) {
    $kat_ozet[$g['kategori']] = ($kat_ozet[$g['kategori']] ?? 0) + $g['tutar'];
}
arsort($kat_ozet);

include 'includes/header.php';
?>

<div class="toolbar">
  <div>
    <span class="muted">Bu dönem toplam gider:</span>
    <strong style="color:#e94560;font-size:20px;margin-left:8px"><?= para($toplam) ?></strong>
  </div>
  <button onclick="document.getElementById('modal-gider').style.display='flex'" class="btn btn-primary btn-sm">
    + Gider Ekle
  </button>
</div>

<?php if (!empty($kat_ozet)): ?>
<div class="kat-ozet-grid">
  <?php foreach ($kat_ozet as $kat => $tutar): ?>
  <div class="kat-card">
    <div class="kat-label"><?= e($kat) ?></div>
    <div class="kat-tutar"><?= para($tutar) ?></div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card p0">
  <div class="table-wrap">
  <table class="table">
    <thead>
      <tr><th>Kategori</th><th>Açıklama</th><th>Tutar</th><th>Tarih</th><th>Fatura No</th><th>İşlem</th></tr>
    </thead>
    <tbody>
    <?php if (empty($giderler)): ?>
    <tr><td colspan="6" class="empty-state">Bu dönem gider kaydı bulunmuyor.</td></tr>
    <?php endif; ?>
    <?php foreach ($giderler as $g): ?>
    <tr>
      <td><span class="badge badge-cat"><?= e($g['kategori']) ?></span></td>
      <td><?= e($g['aciklama']) ?></td>
      <td><strong style="color:#e94560"><?= para((float)$g['tutar']) ?></strong></td>
      <td><?= tarih_format($g['tarih']) ?></td>
      <td><?= e($g['fatura_no'] ?: '—') ?></td>
      <td>
        <form method="post" style="display:inline">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="islem" value="sil">
          <input type="hidden" name="gider_id" value="<?= $g['id'] ?>">
          <button type="submit" class="btn btn-sm btn-danger" data-confirm="Bu gider kaydı silinsin mi?">✕ Sil</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    <?php if (!empty($giderler)): ?>
    <tfoot>
      <tr>
        <td colspan="2"><strong>TOPLAM</strong></td>
        <td><strong style="color:#e94560"><?= para($toplam) ?></strong></td>
        <td colspan="3"></td>
      </tr>
    </tfoot>
    <?php endif; ?>
  </table>
  </div>
</div>

<div class="modal-overlay" id="modal-gider" style="display:none">
  <div class="modal">
    <div class="modal-header">
      <h3>+ Gider Ekle</h3>
      <button onclick="document.getElementById('modal-gider').style.display='none'" class="modal-close">×</button>
    </div>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="islem" value="ekle">
      <div class="form-grid">
        <div class="form-group">
          <label>Kategori</label>
          <select name="kategori" class="input">
            <?php foreach ($kategoriler as $k): ?>
            <option><?= e($k) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Tutar (₺) <span class="req">*</span></label>
          <input type="number" name="tutar" step="0.01" class="input" required placeholder="0.00">
        </div>
        <div class="form-group full-width">
          <label>Açıklama <span class="req">*</span></label>
          <input type="text" name="aciklama" class="input" required placeholder="Gider açıklaması...">
        </div>
        <div class="form-group">
          <label>Tarih</label>
          <input type="date" name="tarih" class="input" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-group">
          <label>Fatura / Makbuz No</label>
          <input type="text" name="fatura_no" class="input" placeholder="Fatura numarası...">
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">💾 Kaydet</button>
        <button type="button" onclick="document.getElementById('modal-gider').style.display='none'" class="btn btn-ghost">İptal</button>
      </div>
    </form>
  </div>
</div>

<?php include 'includes/footer.php'; ?>