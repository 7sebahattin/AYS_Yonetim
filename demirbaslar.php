<?php
// ============================================================
//  demirbaslar.php — DEMİRBAŞ VE BAKIM TAKİBİ
//
//  Modülün asıl değeri envanter listesi değil, HATIRLATMADIR:
//  asansör yıllık muayenesi, yangın tüpü dolum kontrolü, jeneratör ve
//  paratoner ölçümü gibi kontroller yasal zorunluluktur ve
//  kaçırılması ciddi sorumluluk doğurur.
//
//  Bu yüzden iki mekanizma var:
//   1. Bir bakım "yapıldı" işaretlenince, periyoda göre bir sonraki
//      planlı kayıt OTOMATİK oluşturulur — zincir kopmasın diye.
//   2. Yaklaşan/geciken bakımlar dashboard'da ve bu sayfanın üstünde
//      gösterilir; araclar/bakim_hatirlatma.php ile e-posta gönderilir.
//
//  Geçmiş tarihli planlı bakımlar listeden DÜŞMEZ: "tarihi geçti,
//  artık gösterme" davranışı modülün amacını boşa çıkarırdı.
// ============================================================
require_once 'config.php';
require_once 'includes/functions.php';
require_once 'includes/operasyon.php';

$sayfa_basligi = 'Demirbaş & Bakım';
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

// ─── FORM İŞLEME ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_kontrol();
    $islem = $_POST['islem'] ?? '';

    // ── Demirbaş ────────────────────────────────────────────
    if ($islem === 'demirbas_ekle' || $islem === 'demirbas_guncelle') {
        $ad          = mb_substr(buyuk($_POST['ad'] ?? ''), 0, 140, 'UTF-8');
        $kategori    = enum_deger($_POST['kategori'] ?? '', DEMIRBAS_KATEGORILERI, 'diger');
        $durum       = enum_deger($_POST['durum'] ?? '', DEMIRBAS_DURUMLARI, 'aktif');
        $marka_model = mb_substr(buyuk($_POST['marka_model'] ?? ''), 0, 140, 'UTF-8') ?: null;
        $seri_no     = mb_substr(buyuk($_POST['seri_no'] ?? ''), 0, 80, 'UTF-8') ?: null;
        $konum       = mb_substr(buyuk($_POST['konum'] ?? ''), 0, 140, 'UTF-8') ?: null;
        $blok_id     = gecerli_blok_id($site_id, $_POST['blok_id'] ?? 0);
        $alim_tarihi = tarih_veya_null($_POST['alim_tarihi'] ?? '');
        $garanti     = tarih_veya_null($_POST['garanti_bitisi'] ?? '');
        $bedel       = $_POST['alim_bedeli'] === '' ? null
                     : max(0, (float)str_replace(',', '.', $_POST['alim_bedeli'] ?? '0'));
        $notlar      = trim($_POST['notlar'] ?? '') ?: null;

        if ($ad === '') {
            flash('Demirbaş adı zorunludur.', 'hata');
        } elseif ($islem === 'demirbas_ekle') {
            $db->prepare("
                INSERT INTO demirbaslar (site_id, blok_id, ad, kategori, marka_model, seri_no,
                                         konum, alim_tarihi, alim_bedeli, garanti_bitisi, durum, notlar)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
            ")->execute([$site_id, $blok_id, $ad, $kategori, $marka_model, $seri_no,
                         $konum, $alim_tarihi, $bedel, $garanti, $durum, $notlar]);
            ekleri_yukle($site_id, 'demirbas', (int)$db->lastInsertId(), (int)$kullanici['id']);
            flash('Demirbaş eklendi.');
        } else {
            $id = (int)($_POST['demirbas_id'] ?? 0);
            $db->prepare("
                UPDATE demirbaslar SET blok_id=?, ad=?, kategori=?, marka_model=?, seri_no=?,
                       konum=?, alim_tarihi=?, alim_bedeli=?, garanti_bitisi=?, durum=?, notlar=?
                WHERE id=? AND site_id=?
            ")->execute([$blok_id, $ad, $kategori, $marka_model, $seri_no, $konum,
                         $alim_tarihi, $bedel, $garanti, $durum, $notlar, $id, $site_id]);
            ekleri_yukle($site_id, 'demirbas', $id, (int)$kullanici['id']);
            flash('Demirbaş güncellendi.');
        }
    }

    if ($islem === 'demirbas_sil') {
        $id = (int)($_POST['demirbas_id'] ?? 0);
        // Demirbaşa bağlı bakımların gider satırları da temizlenir;
        // FK cascade bakımları siler ama giderler'e dokunmaz.
        $st = $db->prepare("SELECT id FROM bakimlar WHERE demirbas_id=? AND site_id=?");
        $st->execute([$id, $site_id]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $bakim_id) {
            bagli_gideri_sil($site_id, 'bakim', (int)$bakim_id);
            hedefin_eklerini_sil($site_id, 'bakim', (int)$bakim_id);
        }
        hedefin_eklerini_sil($site_id, 'demirbas', $id);
        $db->prepare("DELETE FROM demirbaslar WHERE id=? AND site_id=?")->execute([$id, $site_id]);
        flash('Demirbaş ve bakım geçmişi silindi.');
        header('Location: /demirbaslar.php');
        exit;
    }

    // ── Bakım ───────────────────────────────────────────────
    if ($islem === 'bakim_ekle' || $islem === 'bakim_guncelle') {
        $demirbas_id = site_kaydi_gecerli_mi('demirbaslar', $site_id, $_POST['demirbas_id'] ?? 0);
        $tur       = enum_deger($_POST['tur'] ?? '', BAKIM_TURLERI, 'periyodik');
        $durum     = enum_deger($_POST['durum'] ?? '', BAKIM_DURUMLARI, 'planlandi');
        $baslik    = mb_substr(buyuk($_POST['baslik'] ?? ''), 0, 160, 'UTF-8') ?: null;
        $planlanan = tarih_veya_null($_POST['planlanan_tarih'] ?? '');
        $yapilan   = tarih_veya_null($_POST['yapilan_tarih'] ?? '');
        $periyot   = (int)($_POST['periyot_ay'] ?? 0) ?: null;
        $firma     = mb_substr(buyuk($_POST['firma'] ?? ''), 0, 140, 'UTF-8') ?: null;
        $tutar     = $_POST['tutar'] === '' ? null
                   : max(0, (float)str_replace(',', '.', $_POST['tutar'] ?? '0'));
        $sonuc     = trim($_POST['sonuc'] ?? '') ?: null;

        if (!$demirbas_id) {
            flash('Geçerli bir demirbaş seçin.', 'hata');
        } else {
            // "Yapıldı" işaretlendi ama tarih girilmediyse bugün varsayılır;
            // aksi halde sonraki bakım tarihi hesaplanamazdı.
            if ($durum === 'yapildi' && !$yapilan) $yapilan = date('Y-m-d');

            if ($islem === 'bakim_ekle') {
                $db->prepare("
                    INSERT INTO bakimlar (site_id, demirbas_id, tur, baslik, planlanan_tarih,
                                          yapilan_tarih, periyot_ay, firma, tutar, sonuc, durum)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?)
                ")->execute([$site_id, $demirbas_id, $tur, $baslik, $planlanan,
                             $yapilan, $periyot, $firma, $tutar, $sonuc, $durum]);
                $bakim_id = (int)$db->lastInsertId();
                $onceki_durum = null;
            } else {
                $bakim_id = (int)($_POST['bakim_id'] ?? 0);
                $st = $db->prepare("SELECT durum FROM bakimlar WHERE id=? AND site_id=?");
                $st->execute([$bakim_id, $site_id]);
                $onceki_durum = $st->fetchColumn() ?: null;

                $db->prepare("
                    UPDATE bakimlar SET demirbas_id=?, tur=?, baslik=?, planlanan_tarih=?,
                           yapilan_tarih=?, periyot_ay=?, firma=?, tutar=?, sonuc=?, durum=?
                    WHERE id=? AND site_id=?
                ")->execute([$demirbas_id, $tur, $baslik, $planlanan, $yapilan, $periyot,
                             $firma, $tutar, $sonuc, $durum, $bakim_id, $site_id]);
            }

            ekleri_yukle($site_id, 'bakim', $bakim_id, (int)$kullanici['id']);

            // Tutar → gider (yalnızca yapılmış bakımlar için; planlı bir
            // bakımın tahmini bedeli gider defterine girmemeli).
            $st = $db->prepare("SELECT * FROM bakimlar WHERE id=? AND site_id=?");
            $st->execute([$bakim_id, $site_id]);
            $bakim = $st->fetch();

            $demirbas_adi = $db->prepare("SELECT ad FROM demirbaslar WHERE id=?");
            $demirbas_adi->execute([$demirbas_id]);
            $demirbas_adi = (string)$demirbas_adi->fetchColumn();

            $gider_id = gidere_yansit(
                $site_id, 'bakim', $bakim_id, $bakim['gider_id'] ? (int)$bakim['gider_id'] : null,
                $durum === 'yapildi' ? $tutar : null,
                'BAKIM',
                $demirbas_adi . ' — ' . etiket(BAKIM_TURLERI, $tur),
                $yapilan ?: $planlanan
            );
            $db->prepare("UPDATE bakimlar SET gider_id=? WHERE id=?")->execute([$gider_id, $bakim_id]);

            // Zincirin devamı: yeni "yapıldı" olan periyodik bakım için
            // bir sonraki planlı kayıt üretilir. Sadece duruma GEÇİŞTE
            // çalışır; her düzenlemede tekrar üretilmez.
            if ($durum === 'yapildi' && $onceki_durum !== 'yapildi') {
                $bakim['durum'] = 'yapildi';
                if (sonraki_bakimi_planla($bakim)) {
                    flash('Bakım kaydedildi. Periyoda göre bir sonraki bakım planlandı.');
                } else {
                    flash('Bakım kaydedildi.');
                }
            } else {
                flash($islem === 'bakim_ekle' ? 'Bakım kaydı eklendi.' : 'Bakım güncellendi.');
            }
        }
    }

    if ($islem === 'bakim_sil') {
        $bakim_id = (int)($_POST['bakim_id'] ?? 0);
        hedefin_eklerini_sil($site_id, 'bakim', $bakim_id);
        bagli_gideri_sil($site_id, 'bakim', $bakim_id);
        $db->prepare("DELETE FROM bakimlar WHERE id=? AND site_id=?")->execute([$bakim_id, $site_id]);
        flash('Bakım kaydı silindi.');
    }

    if ($islem === 'ek_sil') {
        ek_sil($site_id, (int)($_POST['ek_id'] ?? 0));
        flash('Ek silindi.');
    }

    $geri = !empty($_POST['ac']) ? '/demirbaslar.php?ac=' . (int)$_POST['ac'] : '/demirbaslar.php';
    header('Location: ' . $geri);
    exit;
}

// Boş tarih alanı NULL olmalı; '' değeri DATE sütununa yazılamaz.
function tarih_veya_null(?string $ham): ?string
{
    $ham = trim((string)$ham);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $ham) ? $ham : null;
}

// ─── VERİ ÇEK ────────────────────────────────────────────────
$yaklasan = yaklasan_bakimlar($site_id, 60);

$st = $db->prepare("
    SELECT d.*, b.ad AS blok_adi,
           (SELECT COUNT(*) FROM bakimlar bk WHERE bk.demirbas_id = d.id) AS bakim_sayisi,
           (SELECT MIN(bk.planlanan_tarih) FROM bakimlar bk
             WHERE bk.demirbas_id = d.id AND bk.durum = 'planlandi'
               AND bk.planlanan_tarih IS NOT NULL) AS sonraki_bakim
    FROM demirbaslar d
    LEFT JOIN bloklar b ON b.id = d.blok_id
    WHERE d.site_id = ?
    ORDER BY d.kategori, d.ad
");
$st->execute([$site_id]);
$demirbaslar = $st->fetchAll();

$bloklar = site_bloklari($site_id);

// Açık demirbaş detayı
$ac_id = (int)($_GET['ac'] ?? 0);
$detay = null; $bakimlar = []; $detay_ekler = [];
if ($ac_id) {
    $st = $db->prepare("SELECT d.*, b.ad AS blok_adi FROM demirbaslar d
                        LEFT JOIN bloklar b ON b.id = d.blok_id
                        WHERE d.id=? AND d.site_id=?");
    $st->execute([$ac_id, $site_id]);
    $detay = $st->fetch();

    if ($detay) {
        $st = $db->prepare("
            SELECT b.*, (SELECT COUNT(*) FROM ekler e
                          WHERE e.hedef_tur='bakim' AND e.hedef_id=b.id) AS ek_sayisi
            FROM bakimlar b WHERE b.demirbas_id=? AND b.site_id=?
            ORDER BY COALESCE(b.yapilan_tarih, b.planlanan_tarih) DESC, b.id DESC
        ");
        $st->execute([$ac_id, $site_id]);
        $bakimlar = $st->fetchAll();
        $detay_ekler = ekleri_getir($site_id, 'demirbas', $ac_id);
    }
}

$bakim_duzenle = null;
if (!empty($_GET['bakim'])) {
    $st = $db->prepare("SELECT * FROM bakimlar WHERE id=? AND site_id=?");
    $st->execute([(int)$_GET['bakim'], $site_id]);
    $bakim_duzenle = $st->fetch();
}

include 'includes/header.php';
?>

<?php if ($yaklasan): ?>
<div class="card" style="border-left:4px solid #f5a623">
  <h3 style="margin:0 0 10px;font-size:15px">⏰ Yaklaşan ve geciken bakımlar</h3>
  <div class="table-wrap">
  <table class="table">
    <thead><tr><th>Demirbaş</th><th>Bakım</th><th>Planlanan</th><th>Durum</th></tr></thead>
    <tbody>
    <?php foreach ($yaklasan as $y): ?>
      <?php
      $kalan = (int)$y['kalan_gun'];
      $renk  = $kalan < 0 ? '#e74c3c' : ($kalan <= 7 ? '#f5a623' : '#9494ae');
      ?>
      <tr>
        <td><strong><?= e_buyuk($y['demirbas_adi']) ?></strong></td>
        <td><?= e(etiket(BAKIM_TURLERI, $y['tur'])) ?><?= $y['baslik'] ? ' — ' . e_buyuk($y['baslik']) : '' ?></td>
        <td><?= tarih_format($y['planlanan_tarih']) ?></td>
        <td><span class="badge" style="background:<?= $renk ?>22;color:<?= $renk ?>">
          <?= $kalan < 0 ? abs($kalan) . ' gün GECİKTİ' : ($kalan === 0 ? 'BUGÜN' : $kalan . ' gün kaldı') ?>
        </span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php endif; ?>

<div class="toolbar">
  <div>
    <span class="muted">Kayıtlı demirbaş:</span>
    <strong style="font-size:20px;margin-left:8px"><?= count($demirbaslar) ?></strong>
  </div>
  <button onclick="document.getElementById('modal-demirbas').style.display='flex'" class="btn btn-primary btn-sm">
    + Demirbaş Ekle
  </button>
</div>

<div class="card p0">
  <div class="table-wrap">
  <table class="table">
    <thead>
      <tr><th>Demirbaş</th><th>Kategori</th><th>Konum</th><th>Garanti</th>
          <th>Sonraki bakım</th><th>Durum</th><th></th></tr>
    </thead>
    <tbody>
    <?php if (!$demirbaslar): ?>
      <tr><td colspan="7" class="empty-state">Henüz demirbaş kaydı yok.</td></tr>
    <?php endif; ?>
    <?php foreach ($demirbaslar as $d): ?>
      <?php
      $garanti_bitti = $d['garanti_bitisi'] && $d['garanti_bitisi'] < date('Y-m-d');
      $bakim_gecikti = $d['sonraki_bakim'] && $d['sonraki_bakim'] < date('Y-m-d');
      ?>
      <tr>
        <td>
          <strong><?= e_buyuk($d['ad']) ?></strong>
          <?php if ($d['marka_model']): ?>
            <div class="muted" style="font-size:12px"><?= e_buyuk($d['marka_model']) ?></div>
          <?php endif; ?>
        </td>
        <td><span class="badge badge-cat"><?= e(etiket(DEMIRBAS_KATEGORILERI, $d['kategori'])) ?></span></td>
        <td class="muted">
          <?= $d['blok_adi'] ? e_buyuk($d['blok_adi']) . ' · ' : '' ?><?= e_buyuk($d['konum'] ?? '') ?: '—' ?>
        </td>
        <td class="muted" style="<?= $garanti_bitti ? 'color:#e74c3c' : '' ?>">
          <?= $d['garanti_bitisi'] ? tarih_format($d['garanti_bitisi']) : '—' ?>
        </td>
        <td style="<?= $bakim_gecikti ? 'color:#e74c3c;font-weight:700' : '' ?>">
          <?= $d['sonraki_bakim'] ? tarih_format($d['sonraki_bakim']) : '—' ?>
        </td>
        <td>
          <span class="badge" style="background:<?= $d['durum']==='aktif'?'#2ecc71':'#e74c3c' ?>22;
                                     color:<?= $d['durum']==='aktif'?'#2ecc71':'#e74c3c' ?>">
            <?= e(etiket(DEMIRBAS_DURUMLARI, $d['durum'])) ?></span>
        </td>
        <td><a href="?ac=<?= (int)$d['id'] ?>" class="btn btn-sm btn-ghost">Aç</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<!-- ══ DEMİRBAŞ EKLE ═══════════════════════════════════════ -->
<div class="modal-overlay" id="modal-demirbas" style="display:none">
  <div class="modal">
    <div class="modal-header">
      <h3>+ Demirbaş Ekle</h3>
      <button onclick="document.getElementById('modal-demirbas').style.display='none'" class="modal-close">×</button>
    </div>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="islem" value="demirbas_ekle">
      <?php include 'includes/demirbas_form.php'; ?>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">💾 Kaydet</button>
        <button type="button" onclick="document.getElementById('modal-demirbas').style.display='none'" class="btn btn-ghost">İptal</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ DEMİRBAŞ DETAYI ═════════════════════════════════════ -->
<?php if ($detay): ?>
<div class="modal-overlay" id="modal-detay" style="display:flex">
  <div class="modal modal-lg">
    <div class="modal-header">
      <h3><?= e_buyuk($detay['ad']) ?></h3>
      <a href="/demirbaslar.php" class="modal-close">×</a>
    </div>

    <div class="modal-body">
      <div class="detay-kunye">
        <div><span>Kategori</span><strong><?= e(etiket(DEMIRBAS_KATEGORILERI, $detay['kategori'])) ?></strong></div>
        <div><span>Marka / Model</span><strong><?= e_buyuk($detay['marka_model'] ?? '') ?: '—' ?></strong></div>
        <div><span>Seri No</span><strong><?= e_buyuk($detay['seri_no'] ?? '') ?: '—' ?></strong></div>
        <div><span>Konum</span><strong>
          <?= $detay['blok_adi'] ? e_buyuk($detay['blok_adi']) . ' · ' : '' ?>
          <?= e_buyuk($detay['konum'] ?? '') ?: '—' ?></strong></div>
        <div><span>Alım</span><strong>
          <?= $detay['alim_tarihi'] ? tarih_format($detay['alim_tarihi']) : '—' ?>
          <?= $detay['alim_bedeli'] ? ' · ' . para((float)$detay['alim_bedeli']) : '' ?></strong></div>
        <div><span>Garanti bitişi</span><strong>
          <?= $detay['garanti_bitisi'] ? tarih_format($detay['garanti_bitisi']) : '—' ?></strong></div>
        <div><span>Durum</span><strong><?= e(etiket(DEMIRBAS_DURUMLARI, $detay['durum'])) ?></strong></div>
      </div>

      <?php if ($detay['notlar']): ?>
      <div class="detay-blok"><h4>Notlar</h4><p><?= nl2br(e_buyuk($detay['notlar'])) ?></p></div>
      <?php endif; ?>

      <?php if ($detay_ekler): ?>
      <div class="detay-blok">
        <h4>Belgeler</h4>
        <ul class="ek-listesi">
          <?php foreach ($detay_ekler as $ek): ?>
          <li>
            <a href="/belge_indir.php?id=<?= (int)$ek['id'] ?>">📎 <?= e($ek['orijinal_ad']) ?></a>
            <span class="muted"><?= e(boyut_okunabilir((int)$ek['boyut'])) ?></span>
            <form method="post" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
              <input type="hidden" name="islem" value="ek_sil">
              <input type="hidden" name="ek_id" value="<?= (int)$ek['id'] ?>">
              <input type="hidden" name="ac" value="<?= (int)$detay['id'] ?>">
              <button type="submit" class="btn btn-sm btn-danger" data-confirm="Belge silinsin mi?">✕</button>
            </form>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <div class="detay-blok">
        <h4>Bakım geçmişi
          <button onclick="document.getElementById('modal-bakim').style.display='flex'"
                  class="btn btn-sm btn-primary" style="float:right">+ Bakım Ekle</button>
        </h4>
        <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Tür</th><th>Planlanan</th><th>Yapılan</th><th>Firma</th>
                     <th>Tutar</th><th>Durum</th><th></th></tr></thead>
          <tbody>
          <?php if (!$bakimlar): ?>
            <tr><td colspan="7" class="empty-state">Bakım kaydı yok.</td></tr>
          <?php endif; ?>
          <?php foreach ($bakimlar as $b): ?>
            <tr>
              <td><?= e(etiket(BAKIM_TURLERI, $b['tur'])) ?>
                <?= $b['ek_sayisi'] > 0 ? ' 📎' : '' ?>
                <?php if ($b['periyot_ay']): ?>
                  <div class="muted" style="font-size:11px"><?= (int)$b['periyot_ay'] ?> ayda bir</div>
                <?php endif; ?>
              </td>
              <td class="muted"><?= $b['planlanan_tarih'] ? tarih_format($b['planlanan_tarih']) : '—' ?></td>
              <td><?= $b['yapilan_tarih'] ? tarih_format($b['yapilan_tarih']) : '—' ?></td>
              <td class="muted"><?= e_buyuk($b['firma'] ?? '') ?: '—' ?></td>
              <td><?= $b['tutar'] !== null ? para((float)$b['tutar']) : '—' ?></td>
              <td>
                <span class="badge" style="background:<?= $b['durum']==='yapildi'?'#2ecc71':($b['durum']==='iptal'?'#9494ae':'#f5a623') ?>22;
                                           color:<?= $b['durum']==='yapildi'?'#2ecc71':($b['durum']==='iptal'?'#9494ae':'#f5a623') ?>">
                  <?= e(etiket(BAKIM_DURUMLARI, $b['durum'])) ?></span>
              </td>
              <td style="white-space:nowrap">
                <a href="?ac=<?= (int)$detay['id'] ?>&bakim=<?= (int)$b['id'] ?>" class="btn btn-sm btn-ghost">✎</a>
                <form method="post" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                  <input type="hidden" name="islem" value="bakim_sil">
                  <input type="hidden" name="bakim_id" value="<?= (int)$b['id'] ?>">
                  <input type="hidden" name="ac" value="<?= (int)$detay['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-danger"
                          data-confirm="Bakım kaydı ve bağlı gider silinsin mi?">✕</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        </div>
      </div>
    </div>

    <div class="modal-footer">
      <a href="?ac=<?= (int)$detay['id'] ?>&duzenle=1" class="btn btn-sm btn-ghost">✎ Demirbaşı Düzenle</a>
      <form method="post" style="display:inline">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="islem" value="demirbas_sil">
        <input type="hidden" name="demirbas_id" value="<?= (int)$detay['id'] ?>">
        <button type="submit" class="btn btn-sm btn-danger"
                data-confirm="Demirbaş, tüm bakım geçmişi ve bağlı gider kayıtları silinsin mi?">✕ Sil</button>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ══ DEMİRBAŞ DÜZENLE ════════════════════════════════════ -->
<?php if ($detay && !empty($_GET['duzenle'])): ?>
<div class="modal-overlay" id="modal-demirbas-duzenle" style="display:flex">
  <div class="modal">
    <div class="modal-header">
      <h3>✎ Demirbaş Düzenle</h3>
      <a href="?ac=<?= (int)$detay['id'] ?>" class="modal-close">×</a>
    </div>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="islem" value="demirbas_guncelle">
      <input type="hidden" name="demirbas_id" value="<?= (int)$detay['id'] ?>">
      <input type="hidden" name="ac" value="<?= (int)$detay['id'] ?>">
      <?php $mevcut = $detay; include 'includes/demirbas_form.php'; ?>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">💾 Kaydet</button>
        <a href="?ac=<?= (int)$detay['id'] ?>" class="btn btn-ghost">İptal</a>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- ══ BAKIM EKLE / DÜZENLE ════════════════════════════════ -->
<?php if ($detay): ?>
<div class="modal-overlay" id="modal-bakim" style="display:<?= $bakim_duzenle ? 'flex' : 'none' ?>">
  <div class="modal">
    <div class="modal-header">
      <h3><?= $bakim_duzenle ? '✎ Bakım Düzenle' : '+ Bakım Ekle' ?></h3>
      <a href="?ac=<?= (int)$detay['id'] ?>" class="modal-close">×</a>
    </div>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="islem" value="<?= $bakim_duzenle ? 'bakim_guncelle' : 'bakim_ekle' ?>">
      <input type="hidden" name="demirbas_id" value="<?= (int)$detay['id'] ?>">
      <input type="hidden" name="ac" value="<?= (int)$detay['id'] ?>">
      <?php if ($bakim_duzenle): ?>
        <input type="hidden" name="bakim_id" value="<?= (int)$bakim_duzenle['id'] ?>">
      <?php endif; ?>
      <div class="form-grid">
        <div class="form-group">
          <label>Bakım türü</label>
          <select name="tur" class="input">
            <?php foreach (BAKIM_TURLERI as $k => $ad): ?>
              <option value="<?= e($k) ?>" <?= ($bakim_duzenle['tur'] ?? '')===$k?'selected':'' ?>><?= e($ad) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Durum</label>
          <select name="durum" class="input">
            <?php foreach (BAKIM_DURUMLARI as $k => $ad): ?>
              <option value="<?= e($k) ?>" <?= ($bakim_duzenle['durum'] ?? 'planlandi')===$k?'selected':'' ?>><?= e($ad) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group full-width">
          <label>Başlık / açıklama</label>
          <input type="text" name="baslik" class="input buyuk"
                 value="<?= e($bakim_duzenle['baslik'] ?? '') ?>" placeholder="örn. YILLIK MUAYENE">
        </div>
        <div class="form-group">
          <label>Planlanan tarih</label>
          <input type="date" name="planlanan_tarih" class="input"
                 value="<?= e($bakim_duzenle['planlanan_tarih'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Yapılan tarih</label>
          <input type="date" name="yapilan_tarih" class="input"
                 value="<?= e($bakim_duzenle['yapilan_tarih'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Periyot (ay)</label>
          <input type="number" name="periyot_ay" class="input" min="0" max="120"
                 value="<?= e($bakim_duzenle['periyot_ay'] ?? '') ?>" placeholder="örn. 12">
          <small class="muted">Dolu ise bakım "yapıldı" olduğunda sonraki tarih otomatik planlanır.</small>
        </div>
        <div class="form-group">
          <label>Firma</label>
          <input type="text" name="firma" class="input buyuk" value="<?= e($bakim_duzenle['firma'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Tutar (₺)</label>
          <input type="number" name="tutar" step="0.01" min="0" class="input"
                 value="<?= $bakim_duzenle && $bakim_duzenle['tutar'] !== null ? e($bakim_duzenle['tutar']) : '' ?>">
          <small class="muted">Yapılmış bakımın tutarı gider defterine BAKIM kategorisiyle işlenir.</small>
        </div>
        <div class="form-group full-width">
          <label>Sonuç / rapor notu</label>
          <textarea name="sonuc" class="input" rows="2"><?= e($bakim_duzenle['sonuc'] ?? '') ?></textarea>
        </div>
        <div class="form-group full-width">
          <label>Rapor / fatura</label>
          <input type="file" name="ekler[]" class="input" multiple accept=".jpg,.jpeg,.png,.webp,.pdf">
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
