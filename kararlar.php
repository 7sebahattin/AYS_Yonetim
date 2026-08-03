<?php
// ============================================================
//  kararlar.php — KARAR DEFTERİ (DİJİTAL ARŞİV)
//
//  ⚠️ Kat Mülkiyeti Kanunu'na göre karar defteri NOTER TASDİKLİ
//  FİZİKSEL DEFTER olarak tutulur. Bu sayfa yasal aslın YERİNE
//  GEÇMEZ; amacı geçmiş kararlara hızlı erişim ve dijital yedektir.
//  Uyarı, sayfanın üstünde kalıcı olarak gösterilir.
// ============================================================
require_once 'config.php';
require_once 'includes/functions.php';

$sayfa_basligi = 'Karar Defteri';
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

    if ($islem === 'ekle' || $islem === 'guncelle') {
        $karar_no  = mb_substr(trim($_POST['karar_no'] ?? ''), 0, 30, 'UTF-8');
        $tarih     = karar_tarih($_POST['toplanti_tarihi'] ?? '') ?: date('Y-m-d');
        $tur       = enum_deger($_POST['toplanti_turu'] ?? '', TOPLANTI_TURLERI, 'diger');
        $baslik    = mb_substr(buyuk($_POST['baslik'] ?? ''), 0, 200, 'UTF-8');
        $metin     = trim($_POST['karar_metni'] ?? '');
        $katilim   = ($_POST['katilim_orani'] ?? '') === '' ? null
                   : min(100, max(0, (float)str_replace(',', '.', $_POST['katilim_orani'])));
        $lehte     = ($_POST['lehte']    ?? '') === '' ? null : max(0, (int)$_POST['lehte']);
        $aleyhte   = ($_POST['aleyhte']  ?? '') === '' ? null : max(0, (int)$_POST['aleyhte']);
        $cekimser  = ($_POST['cekimser'] ?? '') === '' ? null : max(0, (int)$_POST['cekimser']);

        if ($karar_no === '' || $baslik === '' || $metin === '') {
            flash('Karar no, başlık ve karar metni zorunludur.', 'hata');
        } elseif ($islem === 'ekle') {
            try {
                $db->prepare("
                    INSERT INTO kararlar (site_id, karar_no, toplanti_tarihi, toplanti_turu, baslik,
                                          karar_metni, katilim_orani, lehte, aleyhte, cekimser, olusturan_id)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?)
                ")->execute([$site_id, $karar_no, $tarih, $tur, $baslik, $metin,
                             $katilim, $lehte, $aleyhte, $cekimser, $kullanici['id']]);
                $yeni_id = (int)$db->lastInsertId();
                karar_belgesi_yukle($site_id, $yeni_id, (int)$kullanici['id']);
                flash('Karar kaydedildi.');
            } catch (PDOException $ex) {
                // uq_site_karar_no ihlali: aynı numara başka bir kararda kullanılmış.
                flash('Bu karar numarası zaten kullanılıyor: ' . e($karar_no), 'hata');
            }
        } else {
            $karar_id = (int)($_POST['karar_id'] ?? 0);
            try {
                $db->prepare("
                    UPDATE kararlar SET karar_no=?, toplanti_tarihi=?, toplanti_turu=?, baslik=?,
                           karar_metni=?, katilim_orani=?, lehte=?, aleyhte=?, cekimser=?
                    WHERE id=? AND site_id=?
                ")->execute([$karar_no, $tarih, $tur, $baslik, $metin,
                             $katilim, $lehte, $aleyhte, $cekimser, $karar_id, $site_id]);
                karar_belgesi_yukle($site_id, $karar_id, (int)$kullanici['id']);
                flash('Karar güncellendi.');
            } catch (PDOException $ex) {
                flash('Bu karar numarası zaten kullanılıyor: ' . e($karar_no), 'hata');
            }
        }
    }

    if ($islem === 'sil') {
        $karar_id = (int)($_POST['karar_id'] ?? 0);
        // Karara bağlı belgeler silinmez, yalnızca bağlantısı kalkar
        // (fk_belge_karar ON DELETE SET NULL): bir toplantı tutanağı
        // taraması, kararın kendisi silinse bile arşivde değerlidir.
        $db->prepare("DELETE FROM kararlar WHERE id=? AND site_id=?")->execute([$karar_id, $site_id]);
        flash('Karar silindi. Bağlı belgeler arşivde kaldı (karar bağlantısı kaldırıldı).');
        header('Location: /kararlar.php');
        exit;
    }

    if ($islem === 'belge_ekle') {
        $karar_id = karar_gecerli_mi($site_id, $_POST['karar_id'] ?? 0);
        if (!$karar_id) {
            flash('Karar bulunamadı.', 'hata');
        } else {
            karar_belgesi_yukle($site_id, $karar_id, (int)$kullanici['id']);
        }
    }

    if ($islem === 'belge_sil') {
        arsiv_belge_sil($site_id, (int)($_POST['belge_id'] ?? 0));
        flash('Belge silindi.');
    }

    $geri = !empty($_POST['ac']) ? '/kararlar.php?ac=' . (int)$_POST['ac'] : '/kararlar.php';
    header('Location: ' . $geri);
    exit;
}

function karar_tarih(?string $ham): ?string
{
    $ham = trim((string)$ham);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $ham) ? $ham : null;
}

// Karara belge eklemek, belgeler.php'deki yükleme mantığından farklı
// değil; tek fark hedef tablonun 'kararlar' değil doğrudan
// belgeler.karar_id olması. Ortak yükleme fonksiyonu belgeler.php'de
// tanımlı; burada tekrar tanımlanmaz — includes/arsiv_yukle.php yok,
// bu yüzden aynı mantık iki dosyada AYRI fonksiyon adlarıyla durur
// (talepler.php / demirbaslar.php Faz 4'te de aynı yaklaşım kullanıldı).
function karar_belgesi_yukle(int $site_id, int $karar_id, int $kullanici_id): void
{
    if (empty($_FILES['belge']['name']) || $_FILES['belge']['error'] === UPLOAD_ERR_NO_FILE) return;

    $sonuc = dosya_yukle($_FILES['belge'], 'belge/' . $site_id);
    if (!$sonuc['basarili']) {
        flash($sonuc['mesaj'], 'uyari');
        return;
    }

    db()->prepare("
        INSERT INTO belgeler (site_id, karar_id, tur, baslik, yol, orijinal_ad, mime, boyut, yukleyen_id)
        VALUES (?,?,?,?,?,?,?,?,?)
    ")->execute([$site_id, $karar_id, 'karar_defteri', $sonuc['orijinal_ad'],
                 $sonuc['yol'], $sonuc['orijinal_ad'], $sonuc['mime'], $sonuc['boyut'], $kullanici_id]);
}

function arsiv_belge_sil(int $site_id, int $belge_id): bool
{
    $st = db()->prepare("SELECT yol FROM belgeler WHERE id=? AND site_id=?");
    $st->execute([$belge_id, $site_id]);
    $yol = $st->fetchColumn();
    if ($yol === false) return false;

    db()->prepare("DELETE FROM belgeler WHERE id=? AND site_id=?")->execute([$belge_id, $site_id]);
    dosya_sil((string)$yol);
    return true;
}

// ─── VERİ ÇEK ────────────────────────────────────────────────
$yil_filtre = (int)($_GET['yil'] ?? 0);
$tur_filtre = array_key_exists($_GET['tur'] ?? '', TOPLANTI_TURLERI) ? $_GET['tur'] : '';
$arama      = trim($_GET['q'] ?? '');

$kosul = ['site_id = ?'];
$parametre = [$site_id];
if ($yil_filtre > 0) {
    $kosul[] = 'YEAR(toplanti_tarihi) = ?';
    $parametre[] = $yil_filtre;
}
if ($tur_filtre !== '') {
    $kosul[] = 'toplanti_turu = ?';
    $parametre[] = $tur_filtre;
}
if ($arama !== '') {
    $kosul[] = '(karar_no LIKE ? OR baslik LIKE ? OR karar_metni LIKE ?)';
    $parametre[] = "%$arama%"; $parametre[] = "%$arama%"; $parametre[] = "%$arama%";
}

$st = $db->prepare("
    SELECT id, karar_no, toplanti_tarihi, toplanti_turu, baslik, katilim_orani
    FROM kararlar WHERE " . implode(' AND ', $kosul) . "
    ORDER BY toplanti_tarihi DESC, id DESC
");
$st->execute($parametre);
$kararlar = $st->fetchAll();

// Filtre için mevcut yıllar
$yillar = $db->prepare("SELECT DISTINCT YEAR(toplanti_tarihi) AS yil FROM kararlar WHERE site_id=? ORDER BY yil DESC");
$yillar->execute([$site_id]);
$yillar = $yillar->fetchAll(PDO::FETCH_COLUMN);

// Açık karar detayı
$ac_id = (int)($_GET['ac'] ?? 0);
$detay = null; $detay_belgeler = [];
if ($ac_id) {
    $st = $db->prepare("SELECT k.*, u.kullanici_adi FROM kararlar k
                        LEFT JOIN kullanicilar u ON u.id = k.olusturan_id
                        WHERE k.id=? AND k.site_id=?");
    $st->execute([$ac_id, $site_id]);
    $detay = $st->fetch();

    if ($detay) {
        $st = $db->prepare("SELECT * FROM belgeler WHERE karar_id=? AND site_id=? ORDER BY id");
        $st->execute([$ac_id, $site_id]);
        $detay_belgeler = $st->fetchAll();
    }
}

$duzenle_no_onerisi = karar_no_oner($site_id);

include 'includes/header.php';
?>

<div class="uyari-yasal">
  ⚖️ <strong>Yasal not:</strong> Karar defteri kanunen noter tasdikli fiziksel defter
  olarak tutulur. Buradaki kayıtlar yasal aslın yerine geçmez; amaç dijital arşiv ve
  hızlı erişimdir.
</div>

<div class="toolbar">
  <div>
    <span class="muted">Kayıtlı karar:</span>
    <strong style="font-size:20px;margin-left:8px"><?= count($kararlar) ?></strong>
  </div>
  <button onclick="document.getElementById('modal-karar').style.display='flex'" class="btn btn-primary btn-sm">
    + Karar Ekle
  </button>
</div>

<form method="get" class="filtre-cubugu">
  <input type="search" name="q" value="<?= e($arama) ?>" placeholder="Karar no, başlık veya metinde ara…">
  <select name="yil" class="input input-sm" onchange="this.form.submit()">
    <option value="">Tüm yıllar</option>
    <?php foreach ($yillar as $y): ?>
      <option value="<?= (int)$y ?>" <?= $yil_filtre===(int)$y?'selected':'' ?>><?= (int)$y ?></option>
    <?php endforeach; ?>
  </select>
  <select name="tur" class="input input-sm" onchange="this.form.submit()">
    <option value="">Tüm toplantı türleri</option>
    <?php foreach (TOPLANTI_TURLERI as $k => $ad): ?>
      <option value="<?= e($k) ?>" <?= $tur_filtre===$k?'selected':'' ?>><?= e($ad) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn btn-sm btn-ghost">Filtrele</button>
</form>

<div class="card p0">
  <div class="table-wrap">
  <table class="table">
    <thead><tr><th>Karar No</th><th>Başlık</th><th>Toplantı</th><th>Tarih</th><th>Katılım</th><th></th></tr></thead>
    <tbody>
    <?php if (!$kararlar): ?>
      <tr><td colspan="6" class="empty-state">Ölçütlere uyan karar yok.</td></tr>
    <?php endif; ?>
    <?php foreach ($kararlar as $k): ?>
      <tr>
        <td><strong class="daire-badge">#<?= e($k['karar_no']) ?></strong></td>
        <td><?= e_buyuk($k['baslik']) ?></td>
        <td><span class="badge badge-cat"><?= e(etiket(TOPLANTI_TURLERI, $k['toplanti_turu'])) ?></span></td>
        <td class="muted"><?= tarih_format($k['toplanti_tarihi']) ?></td>
        <td class="muted"><?= $k['katilim_orani'] !== null ? '%' . e(rtrim(rtrim(number_format((float)$k['katilim_orani'],1,',','.'),'0'),',')) : '—' ?></td>
        <td><a href="?ac=<?= (int)$k['id'] ?>" class="btn btn-sm btn-ghost">Aç</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<!-- ══ YENİ KARAR ═══════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-karar" style="display:none">
  <div class="modal">
    <div class="modal-header">
      <h3>+ Karar Ekle</h3>
      <button onclick="document.getElementById('modal-karar').style.display='none'" class="modal-close">×</button>
    </div>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="islem" value="ekle">
      <div class="form-grid">
        <div class="form-group">
          <label>Karar No <span class="req">*</span></label>
          <input type="text" name="karar_no" class="input" required value="<?= e($duzenle_no_onerisi) ?>">
          <small class="muted">Otomatik önerildi, isterseniz değiştirin.</small>
        </div>
        <div class="form-group">
          <label>Toplantı tarihi</label>
          <input type="date" name="toplanti_tarihi" class="input" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-group">
          <label>Toplantı türü</label>
          <select name="toplanti_turu" class="input">
            <?php foreach (TOPLANTI_TURLERI as $k => $ad): ?>
              <option value="<?= e($k) ?>"><?= e($ad) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group full-width">
          <label>Başlık <span class="req">*</span></label>
          <input type="text" name="baslik" class="input buyuk" required placeholder="örn. ÇATI TADİLATI ONAYI">
        </div>
        <div class="form-group full-width">
          <label>Karar metni <span class="req">*</span></label>
          <textarea name="karar_metni" class="input" rows="4" required placeholder="Kararın tam metni..."></textarea>
        </div>
        <div class="form-group">
          <label>Katılım oranı (%)</label>
          <input type="number" name="katilim_orani" class="input" min="0" max="100" step="0.1">
        </div>
        <div class="form-group">
          <label>Lehte</label>
          <input type="number" name="lehte" class="input" min="0">
        </div>
        <div class="form-group">
          <label>Aleyhte</label>
          <input type="number" name="aleyhte" class="input" min="0">
        </div>
        <div class="form-group">
          <label>Çekimser</label>
          <input type="number" name="cekimser" class="input" min="0">
        </div>
        <div class="form-group full-width">
          <label>Toplantı tutanağı / defter sayfası (isteğe bağlı)</label>
          <input type="file" name="belge" class="input" accept=".jpg,.jpeg,.png,.webp,.pdf">
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">💾 Kaydet</button>
        <button type="button" onclick="document.getElementById('modal-karar').style.display='none'" class="btn btn-ghost">İptal</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ KARAR DETAYI ═════════════════════════════════════════ -->
<?php if ($detay): ?>
<div class="modal-overlay" id="modal-detay" style="display:flex">
  <div class="modal modal-lg">
    <div class="modal-header">
      <h3>#<?= e($detay['karar_no']) ?> — <?= e_buyuk($detay['baslik']) ?></h3>
      <a href="/kararlar.php" class="modal-close">×</a>
    </div>

    <div class="modal-body">
      <div class="detay-kunye">
        <div><span>Toplantı</span><strong><?= e(etiket(TOPLANTI_TURLERI, $detay['toplanti_turu'])) ?></strong></div>
        <div><span>Tarih</span><strong><?= tarih_format($detay['toplanti_tarihi']) ?></strong></div>
        <div><span>Katılım</span><strong><?= $detay['katilim_orani'] !== null ? '%' . e($detay['katilim_orani']) : '—' ?></strong></div>
        <div><span>Lehte / Aleyhte / Çekimser</span><strong>
          <?= $detay['lehte'] ?? '—' ?> / <?= $detay['aleyhte'] ?? '—' ?> / <?= $detay['cekimser'] ?? '—' ?></strong></div>
        <div><span>Kaydeden</span><strong><?= e($detay['kullanici_adi'] ?? '—') ?></strong></div>
        <div><span>Kayıt tarihi</span><strong><?= e(date('d.m.Y H:i', strtotime($detay['olusturma']))) ?></strong></div>
      </div>

      <div class="detay-blok">
        <h4>Karar metni</h4>
        <p><?= nl2br(e($detay['karar_metni'])) ?></p>
      </div>

      <div class="detay-blok">
        <h4>Bağlı belgeler</h4>
        <form method="post" enctype="multipart/form-data" class="satir-form" style="margin-bottom:14px">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="islem" value="belge_ekle">
          <input type="hidden" name="karar_id" value="<?= (int)$detay['id'] ?>">
          <input type="hidden" name="ac" value="<?= (int)$detay['id'] ?>">
          <input type="file" name="belge" class="input" required accept=".jpg,.jpeg,.png,.webp,.pdf" style="max-width:280px">
          <button type="submit" class="btn btn-sm btn-primary">+ Belge Ekle</button>
        </form>
        <?php if ($detay_belgeler): ?>
        <ul class="ek-listesi">
          <?php foreach ($detay_belgeler as $b): ?>
          <li>
            <a href="/belge_indir.php?kaynak=belge&id=<?= (int)$b['id'] ?>">📎 <?= e($b['orijinal_ad']) ?></a>
            <span class="muted"><?= e(boyut_okunabilir((int)$b['boyut'])) ?></span>
            <form method="post" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
              <input type="hidden" name="islem" value="belge_sil">
              <input type="hidden" name="belge_id" value="<?= (int)$b['id'] ?>">
              <input type="hidden" name="ac" value="<?= (int)$detay['id'] ?>">
              <button type="submit" class="btn btn-sm btn-danger" data-confirm="Belge silinsin mi?">✕</button>
            </form>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php else: ?>
          <p class="empty-state">Bağlı belge yok.</p>
        <?php endif; ?>
      </div>
    </div>

    <div class="modal-footer">
      <a href="?ac=<?= (int)$detay['id'] ?>&duzenle=1" class="btn btn-sm btn-ghost">✎ Düzenle</a>
      <form method="post" style="display:inline">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="islem" value="sil">
        <input type="hidden" name="karar_id" value="<?= (int)$detay['id'] ?>">
        <button type="submit" class="btn btn-sm btn-danger"
                data-confirm="Karar silinsin mi? Bağlı belgeler arşivde kalır.">✕ Sil</button>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ══ KARAR DÜZENLE ════════════════════════════════════════ -->
<?php if ($detay && !empty($_GET['duzenle'])): ?>
<div class="modal-overlay" id="modal-karar-duzenle" style="display:flex">
  <div class="modal">
    <div class="modal-header">
      <h3>✎ Karar Düzenle</h3>
      <a href="?ac=<?= (int)$detay['id'] ?>" class="modal-close">×</a>
    </div>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="islem" value="guncelle">
      <input type="hidden" name="karar_id" value="<?= (int)$detay['id'] ?>">
      <div class="form-grid">
        <div class="form-group">
          <label>Karar No <span class="req">*</span></label>
          <input type="text" name="karar_no" class="input" required value="<?= e($detay['karar_no']) ?>">
        </div>
        <div class="form-group">
          <label>Toplantı tarihi</label>
          <input type="date" name="toplanti_tarihi" class="input" value="<?= e($detay['toplanti_tarihi']) ?>">
        </div>
        <div class="form-group">
          <label>Toplantı türü</label>
          <select name="toplanti_turu" class="input">
            <?php foreach (TOPLANTI_TURLERI as $k => $ad): ?>
              <option value="<?= e($k) ?>" <?= $detay['toplanti_turu']===$k?'selected':'' ?>><?= e($ad) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group full-width">
          <label>Başlık <span class="req">*</span></label>
          <input type="text" name="baslik" class="input buyuk" required value="<?= e($detay['baslik']) ?>">
        </div>
        <div class="form-group full-width">
          <label>Karar metni <span class="req">*</span></label>
          <textarea name="karar_metni" class="input" rows="4" required><?= e($detay['karar_metni']) ?></textarea>
        </div>
        <div class="form-group">
          <label>Katılım oranı (%)</label>
          <input type="number" name="katilim_orani" class="input" min="0" max="100" step="0.1"
                 value="<?= e($detay['katilim_orani'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Lehte</label>
          <input type="number" name="lehte" class="input" min="0" value="<?= e($detay['lehte'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Aleyhte</label>
          <input type="number" name="aleyhte" class="input" min="0" value="<?= e($detay['aleyhte'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Çekimser</label>
          <input type="number" name="cekimser" class="input" min="0" value="<?= e($detay['cekimser'] ?? '') ?>">
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">💾 Kaydet</button>
        <a href="?ac=<?= (int)$detay['id'] ?>" class="btn btn-ghost">İptal</a>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
