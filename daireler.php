<?php
// ============================================================
//  daireler.php — DAİRE YÖNETİMİ
// ============================================================
require_once 'config.php';
require_once 'includes/functions.php';
$sayfa_basligi = 'Daireler';

$kullanici = giris_kontrol();
$db = db();

// ─── FORM İŞLEME ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_kontrol();
    $islem = $_POST['islem'] ?? '';

    // ── Daire Güncelle ───────────────────────────────────────
    if ($islem === 'guncelle') {
        $id          = (int)$_POST['id'];
        $sakin_adi   = trim($_POST['sakin_adi'] ?? '');
        $telefon     = trim($_POST['telefon'] ?? '');
        $eposta      = trim($_POST['eposta'] ?? '');
        $aylik_aidat = max(0, (float)str_replace(',', '.', $_POST['aylik_aidat'] ?? 0));
        $kat         = $_POST['kat'] !== '' ? (int)$_POST['kat'] : null;
        $notlar      = trim($_POST['notlar'] ?? '');

        $stmt = $db->prepare("SELECT id FROM daireler WHERE id=? AND kullanici_id=?");
        $stmt->execute([$id, $kullanici['id']]);
        if ($stmt->fetch()) {
            $db->prepare("UPDATE daireler SET sakin_adi=?, telefon=?, eposta=?, aylik_aidat=?, kat=?, notlar=? WHERE id=?")
               ->execute([$sakin_adi, $telefon, $eposta, $aylik_aidat, $kat, $notlar, $id]);
            flash('Daire bilgileri kaydedildi.');
        }
    }

    // ── Yeni Daire Ekle ──────────────────────────────────────
    if ($islem === 'ekle') {
        $daire_no    = (int)$_POST['daire_no'];
        $aylik_aidat = max(0, (float)str_replace(',', '.', $_POST['aylik_aidat'] ?? 500));

        $stmt = $db->prepare("SELECT id FROM daireler WHERE kullanici_id=? AND daire_no=?");
        $stmt->execute([$kullanici['id'], $daire_no]);
        if ($stmt->fetch()) {
            flash("Daire #$daire_no zaten mevcut.", 'hata');
        } else {
            $db->prepare("INSERT INTO daireler (kullanici_id, daire_no, sakin_adi, telefon, eposta, aylik_aidat, notlar)
                          VALUES (?, ?, ?, ?, ?, ?, ?)")
               ->execute([$kullanici['id'], $daire_no, trim($_POST['sakin_adi'] ?? ''), trim($_POST['telefon'] ?? ''), trim($_POST['eposta'] ?? ''), $aylik_aidat, trim($_POST['notlar'] ?? '')]);
            flash("Daire #$daire_no eklendi.");
        }
    }

    // ── Daire Sil ────────────────────────────────────────────
    if ($islem === 'sil') {
        $id = (int)$_POST['id'];
        $stmt = $db->prepare("DELETE FROM daireler WHERE id=? AND kullanici_id=?");
        $stmt->execute([$id, $kullanici['id']]);
        flash('Daire silindi.');
    }

    header('Location: /daireler.php');
    exit;
}

// ─── DAİRE LİSTESİ ───────────────────────────────────────────
$donem = $_GET['donem'] ?? date('Y-m');
$stmt = $db->prepare("
    SELECT d.*,
           COALESCE(a.durum,'bekliyor') AS aidat_durum,
           a.tutar AS odenen_tutar
    FROM daireler d
    LEFT JOIN aidatlar a ON a.daire_id = d.id AND a.donem = ?
    WHERE d.kullanici_id = ?
    ORDER BY d.daire_no
");
$stmt->execute([$donem, $kullanici['id']]);
$daireler = $stmt->fetchAll();

// Düzenleme modu
$duzenle_id = (int)($_GET['duzenle'] ?? 0);
$duzenle = null;
if ($duzenle_id) {
    $stmt = $db->prepare("SELECT * FROM daireler WHERE id=? AND kullanici_id=?");
    $stmt->execute([$duzenle_id, $kullanici['id']]);
    $duzenle = $stmt->fetch();
}

include 'includes/header.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
  <span class="muted"><?= count($daireler) ?> daire kayıtlı</span>
  <button onclick="document.getElementById('modal-ekle').style.display='flex'" class="btn btn-primary">
    + Yeni Daire Ekle
  </button>
</div>

<div class="card p0">
  <div class="table-wrap">
  <table class="table">
    <thead>
      <tr>
        <th>No</th><th>Kat</th><th>Sakin</th><th>Telefon</th>
        <th>E-posta</th><th>Aidat</th><th>Durum</th><th>İşlem</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($daireler as $d): ?>
      <tr style="cursor:pointer" onclick="window.location='/daire_detay.php?id=<?= $d['id'] ?>'">
        <td><strong class="daire-badge">#<?= e($d['daire_no']) ?></strong></td>
        <td><?= $d['kat'] !== null ? e($d['kat'].'. Kat') : '—' ?></td>
        <td><?= $d['sakin_adi'] ? e($d['sakin_adi']) : '<em class="muted">Boş</em>' ?></td>
        <td><?= e($d['telefon'] ?: '—') ?></td>
        <td><?= e($d['eposta'] ?: '—') ?></td>
        <td><strong><?= para((float)$d['aylik_aidat']) ?></strong></td>
        <td>
          <span class="badge badge-<?= $d['aidat_durum'] === 'odendi' ? 'success' : ($d['aidat_durum'] === 'gecikme' ? 'danger' : 'warning') ?>">
            <?= $d['aidat_durum'] === 'odendi' ? 'Ödendi' : ($d['aidat_durum'] === 'gecikme' ? 'Gecikme' : 'Bekliyor') ?>
          </span>
        </td>
        <td onclick="event.stopPropagation()">
          <a href="/daire_detay.php?id=<?= $d['id'] ?>" class="btn btn-sm btn-primary" style="font-size:11px">📋 Detay</a>
          <a href="?duzenle=<?= $d['id'] ?>&donem=<?= e($donem) ?>" class="btn btn-sm btn-ghost">✎ Düzenle</a>
          <form method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="islem" value="sil">
            <input type="hidden" name="id" value="<?= $d['id'] ?>">
            <button type="submit" class="btn btn-sm btn-danger"
                    data-confirm="Daire #<?= $d['daire_no'] ?> silinsin mi? İlgili tüm aidat kayıtları da silinir!">
              ✕ Sil
            </button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<?php if ($duzenle): ?>
<div class="modal-overlay" id="modal-duzenle" style="display:flex">
  <div class="modal">
    <div class="modal-header">
      <h3>Daire #<?= e($duzenle['daire_no']) ?> — Düzenle</h3>
      <a href="/daireler.php?donem=<?= e($donem) ?>" class="modal-close">×</a>
    </div>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="islem" value="guncelle">
      <input type="hidden" name="id" value="<?= $duzenle['id'] ?>">
      <div class="form-grid">
        <div class="form-group">
          <label>Sakin Adı</label>
          <input type="text" name="sakin_adi" class="input" value="<?= e($duzenle['sakin_adi']) ?>" placeholder="Ad Soyad">
        </div>
        <div class="form-group">
          <label>Kat</label>
          <input type="number" name="kat" class="input" value="<?= e($duzenle['kat']) ?>" placeholder="1">
        </div>
        <div class="form-group">
          <label>Telefon</label>
          <input type="text" name="telefon" class="input" value="<?= e($duzenle['telefon']) ?>" placeholder="0555 000 00 00">
        </div>
        <div class="form-group">
          <label>E-posta</label>
          <input type="email" name="eposta" class="input" value="<?= e($duzenle['eposta']) ?>" placeholder="mail@ornek.com">
        </div>
        <div class="form-group">
          <label>Aylık Aidat (₺)</label>
          <input type="number" name="aylik_aidat" class="input" step="0.01" value="<?= e($duzenle['aylik_aidat']) ?>" placeholder="500">
        </div>
        <div class="form-group full-width">
          <label>Notlar</label>
          <textarea name="notlar" class="input" rows="2" placeholder="Ek bilgi..."><?= e($duzenle['notlar']) ?></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">💾 Kaydet</button>
        <a href="/daireler.php?donem=<?= e($donem) ?>" class="btn btn-ghost">İptal</a>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="modal-overlay" id="modal-ekle" style="display:none">
  <div class="modal">
    <div class="modal-header">
      <h3>+ Yeni Daire Ekle</h3>
      <button onclick="document.getElementById('modal-ekle').style.display='none'" class="modal-close">×</button>
    </div>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="islem" value="ekle">
      <div class="form-grid">
        <div class="form-group">
          <label>Daire No <span class="req">*</span></label>
          <input type="number" name="daire_no" class="input" required min="1" placeholder="1">
        </div>
        <div class="form-group">
          <label>Kat</label>
          <input type="number" name="kat" class="input" placeholder="1">
        </div>
        <div class="form-group">
          <label>Sakin Adı</label>
          <input type="text" name="sakin_adi" class="input" placeholder="Ad Soyad">
        </div>
        <div class="form-group">
          <label>Aylık Aidat (₺)</label>
          <input type="number" name="aylik_aidat" class="input" step="0.01" value="500" placeholder="500">
        </div>
        <div class="form-group">
          <label>Telefon</label>
          <input type="text" name="telefon" class="input" placeholder="0555 000 00 00">
        </div>
        <div class="form-group">
          <label>E-posta</label>
          <input type="email" name="eposta" class="input" placeholder="mail@ornek.com">
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">+ Daire Ekle</button>
        <button type="button" onclick="document.getElementById('modal-ekle').style.display='none'" class="btn btn-ghost">İptal</button>
      </div>
    </form>
  </div>
</div>

<?php include 'includes/footer.php'; ?>