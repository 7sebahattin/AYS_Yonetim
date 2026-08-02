<?php
// ============================================================
//  personel.php — PERSONEL / GÖREVLİ YÖNETİMİ
//
//  KVKK NOTU: Kimlik numarası, SGK sicili gibi hassas alanlar
//  BİLİNÇLİ OLARAK TOPLANMIYOR. Bu veriler özel nitelikli kişisel
//  veri kategorisine girer; gerçekten gerekmedikçe toplanmamalı,
//  toplanacaksa erişim kısıtı ve saklama süresi tanımlanmalıdır.
//  Aidat takibi için ad, görev ve telefon yeterli.
//
//  GİDER ENTEGRASYONU — çift sayım tehlikesi:
//  Personel ödemesi hem burada hem giderler'de görünmeli ama aynı
//  para iki kez sayılmamalı. Ödeme kaydedildiğinde otomatik bir gider
//  satırı üretilir ve iki kayıt karşılıklı bağlanır; gider ekranından
//  bu satırın silinmesi/düzenlenmesi engellenir.
// ============================================================
require_once 'config.php';
require_once 'includes/functions.php';
require_once 'includes/operasyon.php';

$sayfa_basligi = 'Personel';
$kullanici = giris_kontrol();
$db = db();

if (!operasyon_semasi_hazir_mi()) {
    include 'includes/header.php';
    echo '<div class="card"><p class="empty-state">Bu modül için 005 numaralı şema göçü '
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
        $ad_soyad = mb_substr(buyuk($_POST['ad_soyad'] ?? ''), 0, 120, 'UTF-8');
        $gorev    = enum_deger($_POST['gorev'] ?? '', PERSONEL_GOREVLERI, 'kapici');
        $durum    = ($_POST['durum'] ?? '') === 'ayrildi' ? 'ayrildi' : 'aktif';
        $telefon  = mb_substr(trim($_POST['telefon'] ?? ''), 0, 20, 'UTF-8') ?: null;
        $baslama  = personel_tarih($_POST['baslama_tarihi'] ?? '');
        $ayrilma  = personel_tarih($_POST['ayrilma_tarihi'] ?? '');
        $ucret    = max(0, (float)str_replace(',', '.', $_POST['aylik_ucret'] ?? '0'));
        $notlar   = trim($_POST['notlar'] ?? '') ?: null;

        // Ayrılma tarihi girildiyse durum da "ayrıldı" olmalı; ikisi
        // ayrışırsa aktif personel listesi yanlış olur.
        if ($ayrilma && $durum === 'aktif') $durum = 'ayrildi';

        if ($ad_soyad === '') {
            flash('Ad soyad zorunludur.', 'hata');
        } elseif ($islem === 'ekle') {
            $db->prepare("
                INSERT INTO personel (site_id, ad_soyad, gorev, telefon, baslama_tarihi,
                                      ayrilma_tarihi, aylik_ucret, notlar, durum)
                VALUES (?,?,?,?,?,?,?,?,?)
            ")->execute([$site_id, $ad_soyad, $gorev, $telefon, $baslama,
                         $ayrilma, $ucret, $notlar, $durum]);
            flash('Personel eklendi.');
        } else {
            $id = (int)($_POST['personel_id'] ?? 0);
            $db->prepare("
                UPDATE personel SET ad_soyad=?, gorev=?, telefon=?, baslama_tarihi=?,
                       ayrilma_tarihi=?, aylik_ucret=?, notlar=?, durum=?
                WHERE id=? AND site_id=?
            ")->execute([$ad_soyad, $gorev, $telefon, $baslama, $ayrilma,
                         $ucret, $notlar, $durum, $id, $site_id]);
            flash('Personel güncellendi.');
        }
    }

    if ($islem === 'sil') {
        $id = (int)($_POST['personel_id'] ?? 0);
        // Personelin ödemelerine bağlı gider satırları da temizlenir;
        // FK cascade ödemeleri siler ama giderler'e dokunmaz.
        $st = $db->prepare("SELECT id FROM personel_odemeleri WHERE personel_id=? AND site_id=?");
        $st->execute([$id, $site_id]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $odeme_id) {
            bagli_gideri_sil($site_id, 'personel', (int)$odeme_id);
        }
        $db->prepare("DELETE FROM personel WHERE id=? AND site_id=?")->execute([$id, $site_id]);
        flash('Personel ve ödeme geçmişi silindi.');
        header('Location: /personel.php?donem=' . urlencode($donem));
        exit;
    }

    // ── Ödeme ───────────────────────────────────────────────
    if ($islem === 'odeme_ekle' || $islem === 'odeme_guncelle') {
        $personel_id = site_kaydi_gecerli_mi('personel', $site_id, $_POST['personel_id'] ?? 0);
        $tur       = enum_deger($_POST['tur'] ?? '', ODEME_TURLERI, 'maas');
        $tutar     = max(0, (float)str_replace(',', '.', $_POST['tutar'] ?? '0'));
        $tarih     = personel_tarih($_POST['odeme_tarihi'] ?? '') ?: date('Y-m-d');
        $aciklama  = mb_substr(buyuk($_POST['aciklama'] ?? ''), 0, 255, 'UTF-8') ?: null;
        // Dönem, ödeme tarihinden türetilir: gider defteriyle aynı
        // dönemde görünmesi için ikisinin ayrışmaması gerekir.
        $odeme_donem = substr($tarih, 0, 7);

        if (!$personel_id) {
            flash('Geçerli bir personel seçin.', 'hata');
        } elseif ($tutar <= 0) {
            flash('Tutar sıfırdan büyük olmalıdır.', 'hata');
        } else {
            $st = $db->prepare("SELECT ad_soyad FROM personel WHERE id=?");
            $st->execute([$personel_id]);
            $personel_adi = (string)$st->fetchColumn();

            if ($islem === 'odeme_ekle') {
                $db->prepare("
                    INSERT INTO personel_odemeleri (site_id, personel_id, donem, tur, tutar, odeme_tarihi, aciklama)
                    VALUES (?,?,?,?,?,?,?)
                ")->execute([$site_id, $personel_id, $odeme_donem, $tur, $tutar, $tarih, $aciklama]);
                $odeme_id = (int)$db->lastInsertId();
                $mevcut_gider = null;
            } else {
                $odeme_id = (int)($_POST['odeme_id'] ?? 0);
                $st = $db->prepare("SELECT gider_id FROM personel_odemeleri WHERE id=? AND site_id=?");
                $st->execute([$odeme_id, $site_id]);
                $mevcut_gider = $st->fetchColumn() ?: null;

                $db->prepare("
                    UPDATE personel_odemeleri SET personel_id=?, donem=?, tur=?, tutar=?,
                           odeme_tarihi=?, aciklama=? WHERE id=? AND site_id=?
                ")->execute([$personel_id, $odeme_donem, $tur, $tutar, $tarih,
                             $aciklama, $odeme_id, $site_id]);
            }

            $gider_id = gidere_yansit(
                $site_id, 'personel', $odeme_id, $mevcut_gider ? (int)$mevcut_gider : null,
                $tutar, 'PERSONEL',
                $personel_adi . ' — ' . etiket(ODEME_TURLERI, $tur),
                $tarih
            );
            $db->prepare("UPDATE personel_odemeleri SET gider_id=? WHERE id=?")
               ->execute([$gider_id, $odeme_id]);

            flash('Ödeme kaydedildi ve gider defterine işlendi.');
        }
    }

    if ($islem === 'odeme_sil') {
        $odeme_id = (int)($_POST['odeme_id'] ?? 0);
        bagli_gideri_sil($site_id, 'personel', $odeme_id);
        $db->prepare("DELETE FROM personel_odemeleri WHERE id=? AND site_id=?")
           ->execute([$odeme_id, $site_id]);
        flash('Ödeme ve bağlı gider kaydı silindi.');
    }

    $geri = !empty($_POST['ac'])
        ? '/personel.php?ac=' . (int)$_POST['ac'] . '&donem=' . urlencode($donem)
        : '/personel.php?donem=' . urlencode($donem);
    header('Location: ' . $geri);
    exit;
}

function personel_tarih(?string $ham): ?string
{
    $ham = trim((string)$ham);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $ham) ? $ham : null;
}

// ─── VERİ ÇEK ────────────────────────────────────────────────
$st = $db->prepare("
    SELECT p.*,
           (SELECT COALESCE(SUM(o.tutar),0) FROM personel_odemeleri o
             WHERE o.personel_id = p.id AND o.donem = ?) AS donem_odeme,
           (SELECT COUNT(*) FROM talepler t
             WHERE t.atanan_personel_id = p.id
               AND t.durum NOT IN ('cozuldu','kapali','iptal'))        AS acik_talep
    FROM personel p
    WHERE p.site_id = ?
    ORDER BY p.durum, p.ad_soyad
");
$st->execute([$donem, $site_id]);
$personeller = $st->fetchAll();

$aktif_sayisi = count(array_filter($personeller, fn($p) => $p['durum'] === 'aktif'));
$aylik_yuk    = array_sum(array_map(
    fn($p) => $p['durum'] === 'aktif' ? (float)$p['aylik_ucret'] : 0, $personeller));

$st = $db->prepare("SELECT COALESCE(SUM(tutar),0) FROM personel_odemeleri WHERE site_id=? AND donem=?");
$st->execute([$site_id, $donem]);
$donem_toplam = (float)$st->fetchColumn();

// Açık personel detayı
$ac_id = (int)($_GET['ac'] ?? 0);
$detay = null; $odemeler = [];
if ($ac_id) {
    $st = $db->prepare("SELECT * FROM personel WHERE id=? AND site_id=?");
    $st->execute([$ac_id, $site_id]);
    $detay = $st->fetch();

    if ($detay) {
        $st = $db->prepare("SELECT * FROM personel_odemeleri
                            WHERE personel_id=? AND site_id=? ORDER BY odeme_tarihi DESC, id DESC");
        $st->execute([$ac_id, $site_id]);
        $odemeler = $st->fetchAll();
    }
}

$odeme_duzenle = null;
if (!empty($_GET['odeme'])) {
    $st = $db->prepare("SELECT * FROM personel_odemeleri WHERE id=? AND site_id=?");
    $st->execute([(int)$_GET['odeme'], $site_id]);
    $odeme_duzenle = $st->fetch();
}

include 'includes/header.php';
?>

<div class="toolbar">
  <div style="display:flex;gap:22px;flex-wrap:wrap">
    <div><span class="muted">Aktif personel:</span>
      <strong style="font-size:20px;margin-left:6px"><?= $aktif_sayisi ?></strong></div>
    <div><span class="muted">Aylık ücret yükü:</span>
      <strong style="font-size:20px;margin-left:6px;color:#f5a623"><?= para($aylik_yuk) ?></strong></div>
    <div><span class="muted"><?= e(donem_adi($donem)) ?> ödemesi:</span>
      <strong style="font-size:20px;margin-left:6px;color:#e94560"><?= para($donem_toplam) ?></strong></div>
  </div>
  <button onclick="document.getElementById('modal-personel').style.display='flex'" class="btn btn-primary btn-sm">
    + Personel Ekle
  </button>
</div>

<div class="card p0">
  <div class="table-wrap">
  <table class="table">
    <thead>
      <tr><th>Ad Soyad</th><th>Görev</th><th>Telefon</th><th>Başlama</th>
          <th>Aylık ücret</th><th><?= e(donem_adi($donem)) ?></th><th>Açık talep</th><th>Durum</th><th></th></tr>
    </thead>
    <tbody>
    <?php if (!$personeller): ?>
      <tr><td colspan="9" class="empty-state">Henüz personel kaydı yok.</td></tr>
    <?php endif; ?>
    <?php foreach ($personeller as $p): ?>
      <tr style="<?= $p['durum']==='ayrildi' ? 'opacity:.55' : '' ?>">
        <td><strong><?= e_buyuk($p['ad_soyad']) ?></strong></td>
        <td><span class="badge badge-cat"><?= e(etiket(PERSONEL_GOREVLERI, $p['gorev'])) ?></span></td>
        <td class="muted">
          <?php if ($p['telefon']): ?>
            <a href="tel:<?= e(preg_replace('/\s+/', '', $p['telefon'])) ?>"><?= e($p['telefon']) ?></a>
          <?php else: ?>—<?php endif; ?>
        </td>
        <td class="muted"><?= $p['baslama_tarihi'] ? tarih_format($p['baslama_tarihi']) : '—' ?></td>
        <td><?= para((float)$p['aylik_ucret']) ?></td>
        <td><?= (float)$p['donem_odeme'] > 0 ? para((float)$p['donem_odeme']) : '<span class="muted">—</span>' ?></td>
        <td><?= (int)$p['acik_talep'] > 0
              ? '<span class="badge" style="background:#e9456022;color:#e94560">' . (int)$p['acik_talep'] . '</span>'
              : '<span class="muted">—</span>' ?></td>
        <td>
          <span class="badge" style="background:<?= $p['durum']==='aktif'?'#2ecc71':'#9494ae' ?>22;
                                     color:<?= $p['durum']==='aktif'?'#2ecc71':'#9494ae' ?>">
            <?= $p['durum']==='aktif' ? 'Aktif' : 'Ayrıldı' ?></span>
        </td>
        <td><a href="?ac=<?= (int)$p['id'] ?>&donem=<?= e($donem) ?>" class="btn btn-sm btn-ghost">Aç</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<!-- ══ PERSONEL EKLE ═══════════════════════════════════════ -->
<div class="modal-overlay" id="modal-personel" style="display:none">
  <div class="modal">
    <div class="modal-header">
      <h3>+ Personel Ekle</h3>
      <button onclick="document.getElementById('modal-personel').style.display='none'" class="modal-close">×</button>
    </div>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="islem" value="ekle">
      <?php include 'includes/personel_form.php'; ?>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">💾 Kaydet</button>
        <button type="button" onclick="document.getElementById('modal-personel').style.display='none'" class="btn btn-ghost">İptal</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ PERSONEL DETAYI ═════════════════════════════════════ -->
<?php if ($detay): ?>
<div class="modal-overlay" id="modal-detay" style="display:flex">
  <div class="modal modal-lg">
    <div class="modal-header">
      <h3><?= e_buyuk($detay['ad_soyad']) ?></h3>
      <a href="/personel.php?donem=<?= e($donem) ?>" class="modal-close">×</a>
    </div>

    <div class="modal-body">
      <div class="detay-kunye">
        <div><span>Görev</span><strong><?= e(etiket(PERSONEL_GOREVLERI, $detay['gorev'])) ?></strong></div>
        <div><span>Telefon</span><strong><?= e($detay['telefon'] ?? '') ?: '—' ?></strong></div>
        <div><span>Başlama</span><strong><?= $detay['baslama_tarihi'] ? tarih_format($detay['baslama_tarihi']) : '—' ?></strong></div>
        <div><span>Ayrılma</span><strong><?= $detay['ayrilma_tarihi'] ? tarih_format($detay['ayrilma_tarihi']) : '—' ?></strong></div>
        <div><span>Aylık ücret</span><strong><?= para((float)$detay['aylik_ucret']) ?></strong></div>
        <div><span>Durum</span><strong><?= $detay['durum']==='aktif' ? 'Aktif' : 'Ayrıldı' ?></strong></div>
      </div>

      <?php if ($detay['notlar']): ?>
      <div class="detay-blok"><h4>Notlar</h4><p><?= nl2br(e_buyuk($detay['notlar'])) ?></p></div>
      <?php endif; ?>

      <div class="detay-blok">
        <h4>Ödeme geçmişi
          <button onclick="document.getElementById('modal-odeme').style.display='flex'"
                  class="btn btn-sm btn-primary" style="float:right">+ Ödeme Ekle</button>
        </h4>
        <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Tarih</th><th>Dönem</th><th>Tür</th><th>Tutar</th><th>Açıklama</th><th></th></tr></thead>
          <tbody>
          <?php if (!$odemeler): ?>
            <tr><td colspan="6" class="empty-state">Ödeme kaydı yok.</td></tr>
          <?php endif; ?>
          <?php foreach ($odemeler as $o): ?>
            <tr>
              <td><?= tarih_format($o['odeme_tarihi']) ?></td>
              <td class="muted"><?= e(donem_adi($o['donem'])) ?></td>
              <td><span class="badge badge-cat"><?= e(etiket(ODEME_TURLERI, $o['tur'])) ?></span></td>
              <td><strong><?= para((float)$o['tutar']) ?></strong></td>
              <td class="muted"><?= e_buyuk($o['aciklama'] ?? '') ?: '—' ?></td>
              <td style="white-space:nowrap">
                <a href="?ac=<?= (int)$detay['id'] ?>&odeme=<?= (int)$o['id'] ?>&donem=<?= e($donem) ?>"
                   class="btn btn-sm btn-ghost">✎</a>
                <form method="post" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                  <input type="hidden" name="islem" value="odeme_sil">
                  <input type="hidden" name="odeme_id" value="<?= (int)$o['id'] ?>">
                  <input type="hidden" name="ac" value="<?= (int)$detay['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-danger"
                          data-confirm="Ödeme ve bağlı gider kaydı silinsin mi?">✕</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        </div>
        <small class="muted">
          Her ödeme, gider defterine <strong>PERSONEL</strong> kategorisiyle otomatik işlenir.
          Oluşan gider satırı bu ödemeye bağlıdır ve giderler ekranından ayrıca silinemez —
          aynı harcamanın iki kez sayılmasını önler.
        </small>
      </div>
    </div>

    <div class="modal-footer">
      <a href="?ac=<?= (int)$detay['id'] ?>&duzenle=1&donem=<?= e($donem) ?>" class="btn btn-sm btn-ghost">✎ Düzenle</a>
      <form method="post" style="display:inline">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="islem" value="sil">
        <input type="hidden" name="personel_id" value="<?= (int)$detay['id'] ?>">
        <button type="submit" class="btn btn-sm btn-danger"
                data-confirm="Personel, tüm ödeme geçmişi ve bağlı gider kayıtları silinsin mi?">✕ Sil</button>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ══ PERSONEL DÜZENLE ════════════════════════════════════ -->
<?php if ($detay && !empty($_GET['duzenle'])): ?>
<div class="modal-overlay" id="modal-personel-duzenle" style="display:flex">
  <div class="modal">
    <div class="modal-header">
      <h3>✎ Personel Düzenle</h3>
      <a href="?ac=<?= (int)$detay['id'] ?>&donem=<?= e($donem) ?>" class="modal-close">×</a>
    </div>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="islem" value="guncelle">
      <input type="hidden" name="personel_id" value="<?= (int)$detay['id'] ?>">
      <input type="hidden" name="ac" value="<?= (int)$detay['id'] ?>">
      <?php $mevcut = $detay; include 'includes/personel_form.php'; ?>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">💾 Kaydet</button>
        <a href="?ac=<?= (int)$detay['id'] ?>&donem=<?= e($donem) ?>" class="btn btn-ghost">İptal</a>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- ══ ÖDEME EKLE / DÜZENLE ════════════════════════════════ -->
<?php if ($detay): ?>
<div class="modal-overlay" id="modal-odeme" style="display:<?= $odeme_duzenle ? 'flex' : 'none' ?>">
  <div class="modal">
    <div class="modal-header">
      <h3><?= $odeme_duzenle ? '✎ Ödeme Düzenle' : '+ Ödeme Ekle' ?></h3>
      <a href="?ac=<?= (int)$detay['id'] ?>&donem=<?= e($donem) ?>" class="modal-close">×</a>
    </div>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="islem" value="<?= $odeme_duzenle ? 'odeme_guncelle' : 'odeme_ekle' ?>">
      <input type="hidden" name="personel_id" value="<?= (int)$detay['id'] ?>">
      <input type="hidden" name="ac" value="<?= (int)$detay['id'] ?>">
      <?php if ($odeme_duzenle): ?>
        <input type="hidden" name="odeme_id" value="<?= (int)$odeme_duzenle['id'] ?>">
      <?php endif; ?>
      <div class="form-grid">
        <div class="form-group">
          <label>Ödeme türü</label>
          <select name="tur" class="input">
            <?php foreach (ODEME_TURLERI as $k => $ad): ?>
              <option value="<?= e($k) ?>" <?= ($odeme_duzenle['tur'] ?? '')===$k?'selected':'' ?>><?= e($ad) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Tutar (₺) <span class="req">*</span></label>
          <input type="number" name="tutar" step="0.01" min="0.01" class="input" required
                 value="<?= e($odeme_duzenle['tutar'] ?? $detay['aylik_ucret']) ?>">
        </div>
        <div class="form-group">
          <label>Ödeme tarihi</label>
          <input type="date" name="odeme_tarihi" class="input"
                 value="<?= e($odeme_duzenle['odeme_tarihi'] ?? date('Y-m-d')) ?>">
          <small class="muted">Dönem bu tarihten türetilir.</small>
        </div>
        <div class="form-group full-width">
          <label>Açıklama</label>
          <input type="text" name="aciklama" class="input buyuk" value="<?= e($odeme_duzenle['aciklama'] ?? '') ?>">
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">💾 Kaydet</button>
        <a href="?ac=<?= (int)$detay['id'] ?>&donem=<?= e($donem) ?>" class="btn btn-ghost">İptal</a>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
