<?php
// ============================================================
//  aidatlar.php — AİDAT TAKİBİ
// ============================================================
require_once 'config.php';
require_once 'includes/functions.php';
$sayfa_basligi = 'Aidatlar';

$kullanici = giris_kontrol();
$db  = db();
$donem = $_GET['donem'] ?? date('Y-m');

// ─── FORM İŞLEME ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_kontrol();
    $islem = $_POST['islem'] ?? '';

    if ($islem === 'donemi_olustur') {
        $baslangic = $_POST['donem_baslangic'] ?? $donem;
        $bitis     = $_POST['donem_bitis']     ?? $donem;
        if ($baslangic > $bitis) [$baslangic, $bitis] = [$bitis, $baslangic];

        $donemler = [];
        $current  = $baslangic;
        while ($current <= $bitis) {
            $donemler[] = $current;
            $current = date('Y-m', strtotime($current . '-01 +1 month'));
        }

        $stmt = $db->prepare("SELECT id, aylik_aidat FROM daireler WHERE kullanici_id=?");
        $stmt->execute([$kullanici['id']]);
        $tum_daireler_list = $stmt->fetchAll();

        $ins = $db->prepare("INSERT IGNORE INTO aidatlar (kullanici_id, daire_id, donem, tutar, durum) VALUES (?,?,?,?,'bekliyor')");
        $say = 0;
        foreach ($tum_daireler_list as $d) {
            foreach ($donemler as $dnem) {
                $ins->execute([$kullanici['id'], $d['id'], $dnem, $d['aylik_aidat']]);
                $say += $ins->rowCount();
            }
        }
        $ay_say = count($donemler);
        flash("$say kayıt oluşturuldu ($ay_say dönem × " . count($tum_daireler_list) . " daire).");
    }

    if ($islem === 'odeme_kaydet') {
        $daire_id     = (int)$_POST['daire_id'];
        $tutar        = max(0, (float)str_replace(',', '.', $_POST['tutar'] ?? 0));
        $odeme_tarihi = $_POST['odeme_tarihi'] ?: date('Y-m-d');
        $dekont_no    = trim($_POST['dekont_no'] ?? '');
        $notlar       = trim($_POST['notlar'] ?? '');

        $stmt = $db->prepare("SELECT id FROM daireler WHERE id=? AND kullanici_id=?");
        $stmt->execute([$daire_id, $kullanici['id']]);
        if (!$stmt->fetch()) { flash('Yetkisiz işlem.', 'hata'); header("Location: /aidatlar.php?donem=$donem"); exit; }

        $stmt = $db->prepare("SELECT id FROM aidatlar WHERE daire_id=? AND donem=?");
        $stmt->execute([$daire_id, $donem]);
        $mevcut = $stmt->fetch();

        if ($mevcut) {
            $db->prepare("UPDATE aidatlar SET durum='odendi', tutar=?, odeme_tarihi=?, dekont_no=?, notlar=? WHERE id=?")
               ->execute([$tutar, $odeme_tarihi, $dekont_no, $notlar, $mevcut['id']]);
        } else {
            $db->prepare("INSERT INTO aidatlar (kullanici_id, daire_id, donem, tutar, durum, odeme_tarihi, dekont_no, notlar)
                          VALUES (?,?,?,?,'odendi',?,?,?)")
               ->execute([$kullanici['id'], $daire_id, $donem, $tutar, $odeme_tarihi, $dekont_no, $notlar]);
        }
        flash("Ödeme kaydedildi.");
    }

    if ($islem === 'sil') {
        $id = (int)$_POST['aidat_id'];
        $db->prepare("DELETE FROM aidatlar WHERE id=? AND kullanici_id=?")->execute([$id, $kullanici['id']]);
        flash('Kayıt silindi.');
    }

    header("Location: /aidatlar.php?donem=$donem");
    exit;
}

// ─── VERİ ÇEK ────────────────────────────────────────────────
$filtre = $_GET['filtre'] ?? 'tumu';
$hizli_daire_no = (int)($_GET['odeme'] ?? 0);

$stmt = $db->prepare("
    SELECT d.id AS daire_id, d.daire_no, d.sakin_adi, d.aylik_aidat,
           a.id AS aidat_id, a.tutar, a.durum, a.odeme_tarihi, a.dekont_no, a.notlar
    FROM daireler d
    LEFT JOIN aidatlar a ON a.daire_id = d.id AND a.donem = ?
    WHERE d.kullanici_id = ?
    ORDER BY d.daire_no
");
$stmt->execute([$donem, $kullanici['id']]);
$tum_daireler = $stmt->fetchAll();

$liste = array_filter($tum_daireler, function($r) use ($filtre) {
    $durum = $r['durum'] ?? 'bekliyor';
    if ($filtre === 'odendi')   return $durum === 'odendi';
    if ($filtre === 'bekliyor') return $durum !== 'odendi';
    return true;
});

$toplam_tahsilat = array_sum(array_column(array_filter($tum_daireler, fn($r) => ($r['durum'] ?? '') === 'odendi'), 'tutar'));
$odenen_say = count(array_filter($tum_daireler, fn($r) => ($r['durum'] ?? '') === 'odendi'));

$duzenle_daire = null;
if ($hizli_daire_no) {
    foreach ($tum_daireler as $r) {
        if ($r['daire_no'] == $hizli_daire_no) { $duzenle_daire = $r; break; }
    }
}

include 'includes/header.php';
?>

<div class="toolbar">
  <div class="toolbar-left">
    <div class="filter-tabs">
      <?php foreach (['tumu'=>'Tümü','odendi'=>'Ödenenler','bekliyor'=>'Bekleyenler'] as $k=>$v): ?>
      <a href="?donem=<?= e($donem) ?>&filtre=<?= $k ?>"
         class="filter-tab <?= $filtre === $k ? 'active' : '' ?>"><?= $v ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="toolbar-right">
    <button onclick="document.getElementById('modal-donem-olustur').style.display='flex'" class="btn btn-ghost btn-sm">
      ⊕ Dönem(ler) Oluştur
    </button>
    <button onclick="document.getElementById('modal-odeme').style.display='flex'" class="btn btn-primary btn-sm">
      + Ödeme Ekle
    </button>
  </div>
</div>

<div class="modal-overlay" id="modal-donem-olustur" style="display:none">
  <div class="modal" style="max-width:480px">
    <div class="modal-header">
      <h3>⊕ Dönem Oluştur — Tüm Daireler</h3>
      <button onclick="document.getElementById('modal-donem-olustur').style.display='none'" class="modal-close">×</button>
    </div>
    <form method="post" style="padding:24px">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="islem" value="donemi_olustur">
      <div style="background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:10px;padding:14px;margin-bottom:16px;font-size:13px;color:var(--muted)">
        💡 Seçilen dönem aralığındaki <strong style="color:var(--text)">tüm daireler</strong> için
        "Bekliyor" durumunda kayıt oluşturulur. Zaten mevcut kayıtlar değişmez.
      </div>
      <div class="form-grid">
        <div class="form-group">
          <label>Başlangıç Dönemi</label>
          <select name="donem_baslangic" class="input">
            <?php foreach (donem_listesi_genisletilmis() as $d): ?>
            <option value="<?= e($d) ?>" <?= $d === $donem ? 'selected' : '' ?>><?= e(donem_adi($d)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Bitiş Dönemi</label>
          <select name="donem_bitis" class="input">
            <?php foreach (donem_listesi_genisletilmis() as $d): ?>
            <option value="<?= e($d) ?>" <?= $d === $donem ? 'selected' : '' ?>><?= e(donem_adi($d)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer" style="margin-top:20px">
        <button type="submit" class="btn btn-primary">⊕ Oluştur</button>
        <button type="button" onclick="document.getElementById('modal-donem-olustur').style.display='none'" class="btn btn-ghost">İptal</button>
      </div>
    </form>
  </div>
</div>

<div class="aidat-ozet">
  <span>Tahsilat: <strong style="color:#2ecc71"><?= para($toplam_tahsilat) ?></strong></span>
  <span>Ödenen: <strong><?= $odenen_say ?></strong> / <?= count($tum_daireler) ?> daire</span>
  <span>Dönem: <strong><?= e(donem_adi($donem)) ?></strong></span>
</div>

<div class="card p0">
  <div class="table-wrap">
  <table class="table">
    <thead>
      <tr>
        <th>Daire</th><th>Sakin</th><th>Aidat</th>
        <th>Ödeme Tarihi</th><th>Dekont</th><th>Durum</th><th>İşlem</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($liste as $r): ?>
    <?php $durum = $r['durum'] ?? 'bekliyor'; ?>
    <tr>
      <td><strong class="daire-badge">#<?= e($r['daire_no']) ?></strong></td>
      <td><?= e($r['sakin_adi'] ?: '—') ?></td>
      <td><?= para((float)($r['tutar'] ?? $r['aylik_aidat'])) ?></td>
      <td><?= tarih_format($r['odeme_tarihi'] ?? '') ?></td>
      <td><?= e($r['dekont_no'] ?: '—') ?></td>
      <td>
        <span class="badge badge-<?= $durum === 'odendi' ? 'success' : ($durum === 'gecikme' ? 'danger' : 'warning') ?>">
          <?= $durum === 'odendi' ? '✓ Ödendi' : ($durum === 'gecikme' ? '! Gecikme' : '⏳ Bekliyor') ?>
        </span>
      </td>
      <td>
        <a href="?donem=<?= e($donem) ?>&odeme=<?= e($r['daire_no']) ?>"
           class="btn btn-sm <?= $durum === 'odendi' ? 'btn-ghost' : 'btn-success' ?>">
          <?= $durum === 'odendi' ? '✎ Düzenle' : '✓ Ödendi' ?>
        </a>
        <?php if ($r['aidat_id']): ?>
        <form method="post" style="display:inline">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="islem" value="sil">
          <input type="hidden" name="aidat_id" value="<?= $r['aidat_id'] ?>">
          <button type="submit" class="btn btn-sm btn-danger"
                  data-confirm="Bu aidat kaydı silinsin mi?">✕</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<?php
$modal_daire = $duzenle_daire;
$modal_display = $modal_daire ? 'flex' : 'none';
?>
<div class="modal-overlay" id="modal-odeme" style="display:<?= $modal_display ?>">
  <div class="modal">
    <div class="modal-header">
      <h3>💰 Ödeme Kaydet — <?= e(donem_adi($donem)) ?></h3>
      <a href="/aidatlar.php?donem=<?= e($donem) ?>" class="modal-close">×</a>
    </div>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="islem" value="odeme_kaydet">
      <div class="form-grid">
        <div class="form-group full-width">
          <label>Daire <span class="req">*</span></label>
          <select name="daire_id" class="input" required>
            <option value="">Daire seçin...</option>
            <?php foreach ($tum_daireler as $d): ?>
            <option value="<?= $d['daire_id'] ?>"
              <?= ($modal_daire && $modal_daire['daire_id'] == $d['daire_id']) ? 'selected' : '' ?>>
              Daire #<?= e($d['daire_no']) ?><?= $d['sakin_adi'] ? ' — '.e($d['sakin_adi']) : '' ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Tutar (₺) <span class="req">*</span></label>
          <input type="number" name="tutar" step="0.01" class="input" required
                 value="<?= e($modal_daire['tutar'] ?? $modal_daire['aylik_aidat'] ?? '') ?>"
                 placeholder="500">
        </div>
        <div class="form-group">
          <label>Ödeme Tarihi</label>
          <input type="date" name="odeme_tarihi" class="input"
                 value="<?= e($modal_daire['odeme_tarihi'] ?? date('Y-m-d')) ?>">
        </div>
        <div class="form-group">
          <label>Dekont / Makbuz No</label>
          <input type="text" name="dekont_no" class="input"
                 value="<?= e($modal_daire['dekont_no'] ?? '') ?>"
                 placeholder="Dekont numarası veya açıklama">
        </div>
        <div class="form-group">
          <label>Not</label>
          <input type="text" name="notlar" class="input"
                 value="<?= e($modal_daire['notlar'] ?? '') ?>"
                 placeholder="Ek not...">
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">💾 Kaydet</button>
        <a href="/aidatlar.php?donem=<?= e($donem) ?>" class="btn btn-ghost">İptal</a>
      </div>
    </form>
  </div>
</div>

<?php include 'includes/footer.php'; ?>