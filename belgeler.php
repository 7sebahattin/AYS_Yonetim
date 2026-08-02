<?php
// ============================================================
//  belgeler.php — BELGE ARŞİVİ
//
//  Karara bağlı olmayan (yönetim planı, sigorta poliçesi, ruhsat,
//  sözleşme gibi) belgeler burada yönetilir. Bir kararla ilişkili
//  belgeler kararlar.php üzerinden eklenir/görüntülenir; bu sayfa
//  ikisini birden LİSTELER (arama ve tür filtresi hepsini kapsasın
//  diye) ama yalnızca buradan YENİ EKLENEN belgeler karar_id'siz olur.
// ============================================================
require_once 'config.php';
require_once 'includes/functions.php';

$sayfa_basligi = 'Belge Arşivi';
$kullanici = giris_kontrol();
$db = db();

if (!arsiv_semasi_hazir_mi()) {
    include 'includes/header.php';
    echo '<div class="card"><p class="empty-state">Bu modül için 006 numaralı şema göçü '
       . 'uygulanmalıdır.</p></div>';
    include 'includes/footer.php';
    exit;
}

$site_id = (int)$kullanici['site_id'];

// ─── FORM İŞLEME ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_kontrol();
    $islem = $_POST['islem'] ?? '';

    if ($islem === 'ekle') {
        $tur      = enum_deger($_POST['tur'] ?? '', BELGE_TURLERI, 'diger');
        $baslik   = mb_substr(buyuk($_POST['baslik'] ?? ''), 0, 200, 'UTF-8');
        $aciklama = trim($_POST['aciklama'] ?? '') ?: null;
        $karar_id = karar_gecerli_mi($site_id, $_POST['karar_id'] ?? 0);

        if ($baslik === '') {
            flash('Başlık zorunludur.', 'hata');
        } elseif (empty($_FILES['dosya']['name'])) {
            flash('Dosya seçmelisiniz.', 'hata');
        } else {
            $sonuc = dosya_yukle($_FILES['dosya'], 'belge/' . $site_id);
            if (!$sonuc['basarili']) {
                flash($sonuc['mesaj'], 'hata');
            } else {
                $db->prepare("
                    INSERT INTO belgeler (site_id, karar_id, tur, baslik, aciklama,
                                          yol, orijinal_ad, mime, boyut, yukleyen_id)
                    VALUES (?,?,?,?,?,?,?,?,?,?)
                ")->execute([$site_id, $karar_id, $tur, $baslik, $aciklama,
                             $sonuc['yol'], $sonuc['orijinal_ad'], $sonuc['mime'],
                             $sonuc['boyut'], $kullanici['id']]);
                flash('Belge eklendi.');
            }
        }
    }

    if ($islem === 'guncelle') {
        $belge_id = (int)($_POST['belge_id'] ?? 0);
        $tur      = enum_deger($_POST['tur'] ?? '', BELGE_TURLERI, 'diger');
        $baslik   = mb_substr(buyuk($_POST['baslik'] ?? ''), 0, 200, 'UTF-8');
        $aciklama = trim($_POST['aciklama'] ?? '') ?: null;

        if ($baslik === '') {
            flash('Başlık zorunludur.', 'hata');
        } else {
            $db->prepare("UPDATE belgeler SET tur=?, baslik=?, aciklama=? WHERE id=? AND site_id=?")
               ->execute([$tur, $baslik, $aciklama, $belge_id, $site_id]);
            flash('Belge güncellendi.');
        }
    }

    if ($islem === 'sil') {
        $st = $db->prepare("SELECT yol FROM belgeler WHERE id=? AND site_id=?");
        $st->execute([(int)($_POST['belge_id'] ?? 0), $site_id]);
        $yol = $st->fetchColumn();
        if ($yol !== false) {
            $db->prepare("DELETE FROM belgeler WHERE id=? AND site_id=?")
               ->execute([(int)$_POST['belge_id'], $site_id]);
            dosya_sil((string)$yol);
            flash('Belge silindi.');
        }
    }

    header('Location: /belgeler.php?' . http_build_query(array_intersect_key($_GET, ['q'=>1,'tur'=>1])));
    exit;
}

// ─── VERİ ÇEK ────────────────────────────────────────────────
$tur_filtre = array_key_exists($_GET['tur'] ?? '', BELGE_TURLERI) ? $_GET['tur'] : '';
$arama      = trim($_GET['q'] ?? '');

$kosul = ['b.site_id = ?'];
$parametre = [$site_id];
if ($tur_filtre !== '') {
    $kosul[] = 'b.tur = ?';
    $parametre[] = $tur_filtre;
}
if ($arama !== '') {
    $kosul[] = '(b.baslik LIKE ? OR b.aciklama LIKE ?)';
    $parametre[] = "%$arama%"; $parametre[] = "%$arama%";
}

$st = $db->prepare("
    SELECT b.*, k.karar_no, u.kullanici_adi
    FROM belgeler b
    LEFT JOIN kararlar k     ON k.id = b.karar_id
    LEFT JOIN kullanicilar u ON u.id = b.yukleyen_id
    WHERE " . implode(' AND ', $kosul) . "
    ORDER BY b.olusturma DESC
");
$st->execute($parametre);
$belgeler = $st->fetchAll();

$kararlar_secim = $db->prepare("SELECT id, karar_no, baslik FROM kararlar WHERE site_id=? ORDER BY toplanti_tarihi DESC");
$kararlar_secim->execute([$site_id]);
$kararlar_secim = $kararlar_secim->fetchAll();

$duzenle_id = (int)($_GET['duzenle'] ?? 0);
$duzenle = null;
if ($duzenle_id) {
    $st = $db->prepare("SELECT * FROM belgeler WHERE id=? AND site_id=?");
    $st->execute([$duzenle_id, $site_id]);
    $duzenle = $st->fetch();
}

$tur_ikonlari = [
    'karar_defteri' => '📜', 'yonetim_plani' => '📘', 'genel_kurul_tutanagi' => '📋',
    'sozlesme' => '📝', 'sigorta_policesi' => '🛡', 'ruhsat' => '📄',
    'bakim_raporu' => '🔧', 'diger' => '📎',
];

include 'includes/header.php';
?>

<div class="uyari-yasal">
  ⚖️ <strong>Yasal not:</strong> Bu arşiv, sözleşme/poliçe/ruhsat gibi belgeler için
  pratik bir dijital yedektir; yasal olarak saklanması zorunlu asıl belgelerin yerine
  geçmez.
</div>

<div class="toolbar">
  <div>
    <span class="muted">Kayıtlı belge:</span>
    <strong style="font-size:20px;margin-left:8px"><?= count($belgeler) ?></strong>
  </div>
  <button onclick="document.getElementById('modal-belge').style.display='flex'" class="btn btn-primary btn-sm">
    + Belge Ekle
  </button>
</div>

<form method="get" class="filtre-cubugu">
  <input type="search" name="q" value="<?= e($arama) ?>" placeholder="Başlık veya açıklamada ara…">
  <select name="tur" class="input input-sm" onchange="this.form.submit()">
    <option value="">Tüm türler</option>
    <?php foreach (BELGE_TURLERI as $k => $ad): ?>
      <option value="<?= e($k) ?>" <?= $tur_filtre===$k?'selected':'' ?>><?= e($ad) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn btn-sm btn-ghost">Filtrele</button>
</form>

<div class="belge-izgara">
  <?php if (!$belgeler): ?>
    <p class="empty-state">Ölçütlere uyan belge yok.</p>
  <?php endif; ?>
  <?php foreach ($belgeler as $b): ?>
    <div class="belge-kart">
      <div class="belge-kart-ust">
        <span class="belge-ikon"><?= $tur_ikonlari[$b['tur']] ?? '📎' ?></span>
        <span class="badge badge-cat"><?= e(etiket(BELGE_TURLERI, $b['tur'])) ?></span>
      </div>
      <strong class="belge-baslik"><?= e_buyuk($b['baslik']) ?></strong>
      <?php if ($b['aciklama']): ?>
        <p class="belge-aciklama"><?= e_buyuk(mb_strimwidth($b['aciklama'], 0, 110, '…', 'UTF-8')) ?></p>
      <?php endif; ?>
      <?php if ($b['karar_no']): ?>
        <a href="/kararlar.php?ac=<?= (int)$b['karar_id'] ?>" class="belge-karar-link">
          🔗 Karar #<?= e($b['karar_no']) ?></a>
      <?php endif; ?>
      <div class="belge-alt">
        <span class="muted"><?= e(boyut_okunabilir((int)$b['boyut'])) ?> ·
          <?= e(date('d.m.Y', strtotime($b['olusturma']))) ?></span>
      </div>
      <div class="belge-eylem">
        <a href="/belge_indir.php?kaynak=belge&id=<?= (int)$b['id'] ?>" class="btn btn-sm btn-ghost">⬇ İndir</a>
        <a href="?duzenle=<?= (int)$b['id'] ?>" class="btn btn-sm btn-ghost">✎</a>
        <form method="post" style="display:inline">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="islem" value="sil">
          <input type="hidden" name="belge_id" value="<?= (int)$b['id'] ?>">
          <button type="submit" class="btn btn-sm btn-danger" data-confirm="Belge silinsin mi?">✕</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- ══ BELGE EKLE ═══════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-belge" style="display:none">
  <div class="modal">
    <div class="modal-header">
      <h3>+ Belge Ekle</h3>
      <button onclick="document.getElementById('modal-belge').style.display='none'" class="modal-close">×</button>
    </div>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="islem" value="ekle">
      <div class="form-grid">
        <div class="form-group full-width">
          <label>Başlık <span class="req">*</span></label>
          <input type="text" name="baslik" class="input buyuk" required placeholder="örn. YANGIN SİGORTASI POLİÇESİ">
        </div>
        <div class="form-group">
          <label>Tür</label>
          <select name="tur" class="input">
            <?php foreach (BELGE_TURLERI as $k => $ad): ?>
              <option value="<?= e($k) ?>"><?= e($ad) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Dosya <span class="req">*</span></label>
          <input type="file" name="dosya" class="input" required accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx">
        </div>
        <div class="form-group full-width">
          <label>İlgili karar (isteğe bağlı)</label>
          <select name="karar_id" class="input">
            <option value="">— Bağımsız belge —</option>
            <?php foreach ($kararlar_secim as $k): ?>
              <option value="<?= (int)$k['id'] ?>">#<?= e($k['karar_no']) ?> — <?= e_buyuk($k['baslik']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group full-width">
          <label>Açıklama</label>
          <textarea name="aciklama" class="input" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">💾 Kaydet</button>
        <button type="button" onclick="document.getElementById('modal-belge').style.display='none'" class="btn btn-ghost">İptal</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ BELGE DÜZENLE ════════════════════════════════════════ -->
<?php if ($duzenle): ?>
<div class="modal-overlay" id="modal-duzenle" style="display:flex">
  <div class="modal">
    <div class="modal-header">
      <h3>✎ Belge Düzenle</h3>
      <a href="/belgeler.php" class="modal-close">×</a>
    </div>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="islem" value="guncelle">
      <input type="hidden" name="belge_id" value="<?= (int)$duzenle['id'] ?>">
      <div class="form-grid">
        <div class="form-group full-width">
          <label>Başlık <span class="req">*</span></label>
          <input type="text" name="baslik" class="input buyuk" required value="<?= e($duzenle['baslik']) ?>">
        </div>
        <div class="form-group">
          <label>Tür</label>
          <select name="tur" class="input">
            <?php foreach (BELGE_TURLERI as $k => $ad): ?>
              <option value="<?= e($k) ?>" <?= $duzenle['tur']===$k?'selected':'' ?>><?= e($ad) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group full-width">
          <label>Açıklama</label>
          <textarea name="aciklama" class="input" rows="2"><?= e($duzenle['aciklama'] ?? '') ?></textarea>
        </div>
      </div>
      <small class="muted" style="display:block;margin:-6px 0 14px">
        Dosyanın kendisi değiştirilemez; yerine yenisini yüklemek için önce bu kaydı
        silip yeniden ekleyin.
      </small>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">💾 Kaydet</button>
        <a href="/belgeler.php" class="btn btn-ghost">İptal</a>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
