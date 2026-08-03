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

gider_kategorileri_tablosunu_hazirla();

// ─── FORM İŞLEME ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_kontrol();
    $islem = $_POST['islem'] ?? '';

    if ($islem === 'ekle' || $islem === 'guncelle') {
        $kategori    = mb_substr(buyuk($_POST['kategori'] ?? ''), 0, 50, 'UTF-8');
        $aciklama    = buyuk($_POST['aciklama'] ?? '');
        $tutar       = max(0, (float)str_replace(',', '.', $_POST['tutar'] ?? 0));
        $tarih       = $_POST['tarih'] ?: date('Y-m-d');
        $fatura_no   = buyuk($_POST['fatura_no'] ?? '');
        $kayit_donem = substr($tarih, 0, 7);

        if ($kategori === '' || !$aciklama || $tutar <= 0) {
            flash('Kategori, açıklama ve tutar zorunludur.', 'hata');
        } else {
            $db->beginTransaction();
            try {
                if ($islem === 'ekle') {
                    $db->prepare("INSERT INTO giderler (site_id, kategori, aciklama, tutar, tarih, donem, fatura_no)
                                  VALUES (?,?,?,?,?,?,?)")
                       ->execute([$kullanici['site_id'], $kategori, $aciklama, $tutar, $tarih, $kayit_donem, $fatura_no]);
                    $gider_id = (int)$db->lastInsertId();
                    $mesaj = 'Gider kaydedildi.';
                } else {
                    $gider_id = (int)($_POST['gider_id'] ?? 0);
                    // kaynak_tur IS NULL koşulu: otomatik üretilmiş satır
                    // burada değiştirilirse kaynak kayıtla ayrışır.
                    $kosul = operasyon_semasi_hazir_mi() ? ' AND kaynak_tur IS NULL' : '';
                    $db->prepare("UPDATE giderler SET kategori=?, aciklama=?, tutar=?, tarih=?, donem=?, fatura_no=?
                                  WHERE id=? AND site_id=?" . $kosul)
                       ->execute([$kategori, $aciklama, $tutar, $tarih, $kayit_donem, $fatura_no, $gider_id, $kullanici['site_id']]);
                    $mesaj = 'Gider güncellendi.';
                }
                // Yeni/kullanılan kategoriyi bu kullanıcının öneri listesine kaydet
                gider_kategori_kaydet($kullanici['site_id'], $kategori);
                $db->commit();
                // Fiş/fatura fotoğrafı: göç 007 uygulanmadıysa alan zaten
                // arayüzde gösterilmez, burada da sessizce atlanır.
                if ($gider_id && gider_fisi_semasi_hazir_mi()) {
                    ekleri_yukle((int)$kullanici['site_id'], 'gider', $gider_id, (int)$kullanici['id']);
                }
                flash($mesaj);
            } catch (Exception $ex) {
                $db->rollBack();
                flash('Kaydedilirken hata oluştu.', 'hata');
            }
        }
    }

    if ($islem === 'sil') {
        $id = (int)$_POST['gider_id'];
        // Bakım/personel/talep kaydından otomatik üretilmiş gider satırı
        // buradan SİLİNEMEZ: silinseydi kaynak kayıt hâlâ "gidere
        // işlendi" derken defterde karşılığı olmazdı. Silme, kaynak
        // modülünden yapılır ve iki kayıt birlikte kalkar.
        if (operasyon_semasi_hazir_mi() && bagli_gider_mi($id, (int)$kullanici['site_id'])) {
            flash('Bu gider bir bakım/personel/talep kaydından geliyor. '
                . 'Silmek için ilgili modüldeki kaydı silin.', 'hata');
        } else {
            $kosul = operasyon_semasi_hazir_mi() ? ' AND kaynak_tur IS NULL' : '';
            $db->prepare("DELETE FROM giderler WHERE id=? AND site_id=?" . $kosul)
               ->execute([$id, $kullanici['site_id']]);
            if (gider_fisi_semasi_hazir_mi()) {
                hedefin_eklerini_sil((int)$kullanici['site_id'], 'gider', $id);
            }
            flash('Kayıt silindi.');
        }
    }

    if ($islem === 'ek_sil') {
        // Fiş listesinden tek bir eki kaldırır; gider kaydının kendisine dokunmaz.
        ek_sil((int)$kullanici['site_id'], (int)($_POST['ek_id'] ?? 0));
        header("Location: /giderler.php?duzenle=" . (int)($_POST['gider_id'] ?? 0) . "&donem=$donem");
        exit;
    }

    header("Location: /giderler.php?donem=$donem");
    exit;
}

// ─── VERİ ÇEK ────────────────────────────────────────────────
$stmt = $db->prepare("SELECT * FROM giderler WHERE site_id=? AND donem=? ORDER BY tarih DESC");
$stmt->execute([$kullanici['site_id'], $donem]);
$giderler = $stmt->fetchAll();

$toplam = array_sum(array_column($giderler, 'tutar'));

$kat_ozet = [];
foreach ($giderler as $g) {
    $kat_ozet[$g['kategori']] = ($kat_ozet[$g['kategori']] ?? 0) + $g['tutar'];
}
arsort($kat_ozet);

$kategori_onerileri = gider_kategori_onerileri($kullanici['site_id']);

// Fiş/fatura sayıları: liste tablosunda rozet göstermek için tek
// sorguda toplu çekilir — satır başına ayrı sorgu atmaktan kaçınılır.
$fis_sayilari = [];
if (gider_fisi_semasi_hazir_mi() && $giderler) {
    $ids = array_column($giderler, 'id');
    $yer_tutucular = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("SELECT hedef_id, COUNT(*) AS say FROM ekler
                          WHERE site_id=? AND hedef_tur='gider' AND hedef_id IN ($yer_tutucular)
                          GROUP BY hedef_id");
    $stmt->execute([$kullanici['site_id'], ...$ids]);
    foreach ($stmt->fetchAll() as $r) $fis_sayilari[(int)$r['hedef_id']] = (int)$r['say'];
}

// Düzenleme modu
$duzenle_id = (int)($_GET['duzenle'] ?? 0);
$duzenle = null;
$duzenle_ekler = [];
if ($duzenle_id) {
    // kaynak_tur IS NULL: otomatik üretilmiş satır düzenleme modalini
    // hiç açmaz — adres çubuğundan ?duzenle= denense bile.
    $kosul = operasyon_semasi_hazir_mi() ? ' AND kaynak_tur IS NULL' : '';
    $stmt = $db->prepare("SELECT * FROM giderler WHERE id=? AND site_id=?" . $kosul);
    $stmt->execute([$duzenle_id, $kullanici['site_id']]);
    $duzenle = $stmt->fetch();
    if ($duzenle && gider_fisi_semasi_hazir_mi()) {
        $duzenle_ekler = ekleri_getir((int)$kullanici['site_id'], 'gider', $duzenle_id);
    }
}

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
    <div class="kat-label"><?= e_buyuk($kat) ?></div>
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
      <td><span class="badge badge-cat"><?= e_buyuk($g['kategori']) ?></span></td>
      <td><?= e_buyuk($g['aciklama']) ?></td>
      <td><strong style="color:#e94560"><?= para((float)$g['tutar']) ?></strong></td>
      <td><?= tarih_format($g['tarih']) ?></td>
      <td>
        <?= $g['fatura_no'] ? e_buyuk($g['fatura_no']) : '—' ?>
        <?php if (!empty($fis_sayilari[$g['id']])): ?>
          <a href="?duzenle=<?= $g['id'] ?>&donem=<?= e($donem) ?>" class="fis-rozeti" title="Fiş/fatura eklendi">
            📎 <?= (int)$fis_sayilari[$g['id']] ?>
          </a>
        <?php endif; ?>
      </td>
      <td>
        <?php if (!empty($g['kaynak_tur'] ?? null)): ?>
          <?php
          // Otomatik üretilmiş satır. Kaynak modülüne yönlendirilir;
          // burada düzenlenmesi/silinmesi iki kaydı ayrıştırırdı.
          $kaynak_yol = match ($g['kaynak_tur']) {
              'bakim'    => '/demirbaslar.php',
              'personel' => '/personel.php',
              'talep'    => '/talepler.php?ac=' . (int)$g['kaynak_id'],
              default    => null,
          };
          $kaynak_ad = match ($g['kaynak_tur']) {
              'bakim'    => 'Bakım kaydı',
              'personel' => 'Personel ödemesi',
              'talep'    => 'Talep #' . (int)$g['kaynak_id'],
              default    => 'Bağlı kayıt',
          };
          ?>
          <span class="badge" style="background:#6c8cff22;color:#6c8cff" title="Bu satır otomatik oluşturuldu">
            🔗 <?= e($kaynak_ad) ?>
          </span>
          <?php if ($kaynak_yol): ?>
            <a href="<?= e($kaynak_yol) ?>" class="btn btn-sm btn-ghost">Kayda git</a>
          <?php endif; ?>
        <?php else: ?>
          <a href="?duzenle=<?= $g['id'] ?>&donem=<?= e($donem) ?>" class="btn btn-sm btn-ghost">✎ Düzenle</a>
          <form method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="islem" value="sil">
            <input type="hidden" name="gider_id" value="<?= $g['id'] ?>">
            <button type="submit" class="btn btn-sm btn-danger" data-confirm="Bu gider kaydı silinsin mi?">✕ Sil</button>
          </form>
        <?php endif; ?>
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
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="islem" value="ekle">
      <div class="form-grid">
        <div class="form-group">
          <label>Kategori <span class="req">*</span></label>
          <div class="combo" id="kategori-combo-ekle">
            <input type="text" id="kategori-input-ekle" class="input buyuk" autocomplete="off"
                   placeholder="Yazmaya başlayın..." required>
            <input type="hidden" name="kategori" id="kategori-hidden-ekle" value="">
            <div class="combo-dropdown" id="kategori-dropdown-ekle" hidden></div>
          </div>
        </div>
        <div class="form-group">
          <label>Tutar (₺) <span class="req">*</span></label>
          <input type="number" name="tutar" step="0.01" class="input" required placeholder="0.00">
        </div>
        <div class="form-group full-width">
          <label>Açıklama <span class="req">*</span></label>
          <input type="text" name="aciklama" class="input buyuk" required placeholder="Gider açıklaması...">
        </div>
        <div class="form-group">
          <label>Tarih</label>
          <input type="date" name="tarih" class="input" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-group">
          <label>Fatura / Makbuz No</label>
          <input type="text" name="fatura_no" class="input buyuk" placeholder="Fatura numarası...">
        </div>
        <?php if (gider_fisi_semasi_hazir_mi()): ?>
        <div class="form-group full-width">
          <label>Fiş / Fatura Fotoğrafı</label>
          <input type="file" name="ekler[]" class="input" multiple data-kirpici
                 accept=".jpg,.jpeg,.png,.webp,.pdf">
          <small class="muted">Fotoğraf seçildiğinde kırpma penceresi açılır — kenarları
            kırpıp temiz bir görsel kaydedebilirsiniz. En fazla <?= round(DOSYA_MAX_BOYUT / 1048576) ?> MB.</small>
        </div>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">💾 Kaydet</button>
        <button type="button" onclick="document.getElementById('modal-gider').style.display='none'" class="btn btn-ghost">İptal</button>
      </div>
    </form>
  </div>
</div>

<?php if ($duzenle): ?>
<div class="modal-overlay" id="modal-duzenle" style="display:flex">
  <div class="modal">
    <div class="modal-header">
      <h3>✎ Gider Düzenle</h3>
      <a href="/giderler.php?donem=<?= e($donem) ?>" class="modal-close">×</a>
    </div>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="islem" value="guncelle">
      <input type="hidden" name="gider_id" value="<?= $duzenle['id'] ?>">
      <div class="form-grid">
        <div class="form-group">
          <label>Kategori <span class="req">*</span></label>
          <div class="combo" id="kategori-combo-duzenle">
            <input type="text" id="kategori-input-duzenle" class="input buyuk" autocomplete="off"
                   value="<?= e($duzenle['kategori']) ?>" placeholder="Yazmaya başlayın..." required>
            <input type="hidden" name="kategori" id="kategori-hidden-duzenle" value="<?= e($duzenle['kategori']) ?>">
            <div class="combo-dropdown" id="kategori-dropdown-duzenle" hidden></div>
          </div>
        </div>
        <div class="form-group">
          <label>Tutar (₺) <span class="req">*</span></label>
          <input type="number" name="tutar" step="0.01" class="input" required value="<?= e($duzenle['tutar']) ?>" placeholder="0.00">
        </div>
        <div class="form-group full-width">
          <label>Açıklama <span class="req">*</span></label>
          <input type="text" name="aciklama" class="input buyuk" required value="<?= e($duzenle['aciklama']) ?>" placeholder="Gider açıklaması...">
        </div>
        <div class="form-group">
          <label>Tarih</label>
          <input type="date" name="tarih" class="input" value="<?= e($duzenle['tarih']) ?>">
        </div>
        <div class="form-group">
          <label>Fatura / Makbuz No</label>
          <input type="text" name="fatura_no" class="input buyuk" value="<?= e($duzenle['fatura_no']) ?>" placeholder="Fatura numarası...">
        </div>
        <?php if (gider_fisi_semasi_hazir_mi()): ?>
        <div class="form-group full-width">
          <label>Fiş / Fatura Fotoğrafı Ekle</label>
          <input type="file" name="ekler[]" class="input" multiple data-kirpici
                 accept=".jpg,.jpeg,.png,.webp,.pdf">
          <small class="muted">Fotoğraf seçildiğinde kırpma penceresi açılır. En fazla
            <?= round(DOSYA_MAX_BOYUT / 1048576) ?> MB.</small>
        </div>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">💾 Kaydet</button>
        <a href="/giderler.php?donem=<?= e($donem) ?>" class="btn btn-ghost">İptal</a>
      </div>
    </form>
    <?php if ($duzenle_ekler): ?>
    <div class="form-grid" style="padding:0 20px 20px">
      <div class="form-group full-width">
        <label>Kayıtlı Fişler</label>
        <ul class="ek-listesi">
          <?php foreach ($duzenle_ekler as $ek): ?>
          <li>
            <?php if (ek_onizlenebilir_mi($ek['mime'])): ?>
            <a href="/belge_indir.php?id=<?= (int)$ek['id'] ?>" class="ek-onizleme-baglanti"
               data-lightbox data-baslik="<?= e($ek['orijinal_ad']) ?>">
              <img src="/belge_indir.php?id=<?= (int)$ek['id'] ?>" alt="<?= e($ek['orijinal_ad']) ?>"
                   class="ek-onizleme" loading="lazy">
            </a>
            <?php endif; ?>
            <a href="/belge_indir.php?id=<?= (int)$ek['id'] ?>" target="_blank" rel="noopener">📎 <?= e($ek['orijinal_ad']) ?></a>
            <span class="muted"><?= e(boyut_okunabilir((int)$ek['boyut'])) ?></span>
            <form method="post" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
              <input type="hidden" name="islem" value="ek_sil">
              <input type="hidden" name="ek_id" value="<?= (int)$ek['id'] ?>">
              <input type="hidden" name="gider_id" value="<?= (int)$duzenle['id'] ?>">
              <button type="submit" class="btn btn-sm btn-danger" data-confirm="Bu fiş silinsin mi?">✕</button>
            </form>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<script>
(function () {
  var kategoriler = <?= json_encode($kategori_onerileri, JSON_UNESCAPED_UNICODE) ?>;

  // Aynı öneri kombobox mantığı hem "Ekle" hem "Düzenle" modalinde kullanılır.
  function kurulumKategoriCombo(ekIsim) {
    var input    = document.getElementById('kategori-input-' + ekIsim);
    var hidden   = document.getElementById('kategori-hidden-' + ekIsim);
    var dropdown = document.getElementById('kategori-dropdown-' + ekIsim);
    if (!input) return;

    var vurgu = -1; // klavyeyle seçili satır

    function kapat() {
      dropdown.hidden = true;
      dropdown.innerHTML = '';
      vurgu = -1;
    }

    function sec(deger) {
      input.value  = deger;
      hidden.value = deger;
      kapat();
    }

    function satirlariGetir() {
      return dropdown.querySelectorAll('.combo-item');
    }

    function vurguGuncelle(satirlar) {
      satirlar.forEach(function (el, i) {
        el.classList.toggle('combo-active', i === vurgu);
      });
      if (satirlar[vurgu]) satirlar[vurgu].scrollIntoView({ block: 'nearest' });
    }

    function listeyiCiz() {
      var q = input.value.trim();
      var qKucuk = q.toLocaleLowerCase('tr-TR');
      var eslesenler = q === ''
        ? kategoriler
        : kategoriler.filter(function (k) { return k.toLocaleLowerCase('tr-TR').indexOf(qKucuk) !== -1; });
      var tamEslesme = kategoriler.some(function (k) { return k.toLocaleLowerCase('tr-TR') === qKucuk; });

      dropdown.innerHTML = '';
      vurgu = -1;

      eslesenler.slice(0, 30).forEach(function (k) {
        var satir = document.createElement('div');
        satir.className = 'combo-item';
        satir.textContent = k;
        satir.addEventListener('mousedown', function (e) { e.preventDefault(); sec(k); });
        dropdown.appendChild(satir);
      });

      if (q !== '' && !tamEslesme) {
        var ekleSatiri = document.createElement('div');
        ekleSatiri.className = 'combo-item combo-item-add';
        ekleSatiri.textContent = '+ “' + q + '” kategorisini ekle';
        ekleSatiri.addEventListener('mousedown', function (e) { e.preventDefault(); sec(q); });
        dropdown.appendChild(ekleSatiri);
      }

      dropdown.hidden = dropdown.children.length === 0;
    }

    input.addEventListener('focus', listeyiCiz);
    input.addEventListener('input', function () {
      hidden.value = input.value.trim();
      listeyiCiz();
    });
    input.addEventListener('blur', function () { setTimeout(kapat, 120); });

    input.addEventListener('keydown', function (e) {
      var satirlar = satirlariGetir();
      if (e.key === 'Escape') { kapat(); return; }
      if (!satirlar.length) return;
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        vurgu = Math.min(vurgu + 1, satirlar.length - 1);
        vurguGuncelle(satirlar);
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        vurgu = Math.max(vurgu - 1, 0);
        vurguGuncelle(satirlar);
      } else if (e.key === 'Enter' && vurgu >= 0) {
        e.preventDefault();
        satirlar[vurgu].dispatchEvent(new MouseEvent('mousedown'));
      }
    });

    // Kullanıcı hiç öğe seçmeden doğrudan Kaydet'e basarsa, yazdığı metin
    // aynen kategori olarak gönderilsin.
    input.closest('form').addEventListener('submit', function () {
      if (!hidden.value.trim()) hidden.value = input.value.trim();
    });
  }

  kurulumKategoriCombo('ekle');
  kurulumKategoriCombo('duzenle');
})();
</script>

<?php include 'includes/footer.php'; ?>
