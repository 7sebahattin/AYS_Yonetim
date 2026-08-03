<?php
// ============================================================
//  talepler.php — ARIZA / TALEP BİLDİRİMİ
//
//  1. aşama tasarımı: talebi YÖNETİCİ veya PERSONEL açar. Sakin
//  girişi henüz yok; telefonla/kapıda gelen arıza sisteme buradan
//  girilir. Bu, bugünkü iş akışına uyar ve ayrı bir sakin portalı
//  gerektirmez.
//
//  Durum akışı: Açık → İşlemde → Beklemede → Çözüldü → Kapalı (+İptal)
//  Her durum değişikliği talep_yorumlari'na kaydedilir; "bu talep ne
//  zaman kim tarafından çözüldü?" sorusu böyle cevaplanabilir.
// ============================================================
require_once 'config.php';
require_once 'includes/functions.php';
require_once 'includes/operasyon.php';

$sayfa_basligi = 'Talepler';
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

    if ($islem === 'ekle' || $islem === 'guncelle') {
        $baslik   = mb_substr(buyuk($_POST['baslik'] ?? ''), 0, 160, 'UTF-8');
        $aciklama = trim($_POST['aciklama'] ?? '');
        $kategori = enum_deger($_POST['kategori'] ?? '', TALEP_KATEGORILERI, 'diger');
        $oncelik  = enum_deger($_POST['oncelik'] ?? '', TALEP_ONCELIKLERI, 'normal');
        $bildiren = mb_substr(buyuk($_POST['bildiren'] ?? ''), 0, 120, 'UTF-8');

        // blok/daire/personel formdan geldiği için ait oldukları siteye
        // karşı DOĞRULANIR; aksi halde id değiştirilerek başka bir
        // apartmanın kaydına referans verilebilirdi.
        $blok_id    = gecerli_blok_id($site_id, $_POST['blok_id'] ?? 0);
        $daire_id   = site_kaydi_gecerli_mi('daireler', $site_id, $_POST['daire_id'] ?? 0);
        $personel_id = site_kaydi_gecerli_mi('personel', $site_id, $_POST['atanan_personel_id'] ?? 0);

        if ($baslik === '') {
            flash('Başlık zorunludur.', 'hata');
        } elseif ($islem === 'ekle') {
            $db->prepare("
                INSERT INTO talepler (site_id, blok_id, daire_id, baslik, aciklama, kategori,
                                      oncelik, atanan_personel_id, acan_kullanici_id, bildiren)
                VALUES (?,?,?,?,?,?,?,?,?,?)
            ")->execute([$site_id, $blok_id, $daire_id, $baslik, $aciklama, $kategori,
                         $oncelik, $personel_id, $kullanici['id'], $bildiren ?: null]);

            $yeni_id = (int)$db->lastInsertId();
            talep_gecmisine_yaz($yeni_id, (int)$kullanici['id'], null, null, 'acik');
            ekleri_yukle($site_id, 'talep', $yeni_id, (int)$kullanici['id']);
            flash('Talep açıldı.');
        } else {
            $talep_id = (int)($_POST['talep_id'] ?? 0);
            $db->prepare("
                UPDATE talepler SET blok_id=?, daire_id=?, baslik=?, aciklama=?, kategori=?,
                                    oncelik=?, atanan_personel_id=?, bildiren=?
                WHERE id=? AND site_id=?
            ")->execute([$blok_id, $daire_id, $baslik, $aciklama, $kategori,
                         $oncelik, $personel_id, $bildiren ?: null, $talep_id, $site_id]);
            ekleri_yukle($site_id, 'talep', $talep_id, (int)$kullanici['id']);
            flash('Talep güncellendi.');
        }
    }

    // ── Durum değiştirme + yorum ────────────────────────────
    if ($islem === 'durum') {
        $talep_id = (int)($_POST['talep_id'] ?? 0);
        $yeni     = enum_deger($_POST['durum'] ?? '', TALEP_DURUMLARI, 'acik');
        $yorum    = trim($_POST['yorum'] ?? '');

        $st = $db->prepare("SELECT * FROM talepler WHERE id=? AND site_id=?");
        $st->execute([$talep_id, $site_id]);
        $talep = $st->fetch();

        if (!$talep) {
            flash('Talep bulunamadı.', 'hata');
        } else {
            $eski = $talep['durum'];
            // Kapanış zamanı yalnızca ilk kapanışta damgalanır; talep
            // yeniden açılıp tekrar kapanırsa güncellenir.
            $kapanis = in_array($yeni, TALEP_KAPALI_DURUMLAR, true) ? date('Y-m-d H:i:s') : null;

            $db->prepare("UPDATE talepler SET durum=?, kapanis=? WHERE id=? AND site_id=?")
               ->execute([$yeni, $kapanis, $talep_id, $site_id]);

            talep_gecmisine_yaz($talep_id, (int)$kullanici['id'], $yorum ?: null, $eski, $yeni);
            flash('Talep durumu güncellendi: ' . etiket(TALEP_DURUMLARI, $yeni));
        }
    }

    if ($islem === 'yorum') {
        $talep_id = (int)($_POST['talep_id'] ?? 0);
        $yorum    = trim($_POST['yorum'] ?? '');
        $st = $db->prepare("SELECT id FROM talepler WHERE id=? AND site_id=?");
        $st->execute([$talep_id, $site_id]);
        if ($st->fetchColumn() && $yorum !== '') {
            talep_gecmisine_yaz($talep_id, (int)$kullanici['id'], $yorum, null, null);
            ekleri_yukle($site_id, 'talep', $talep_id, (int)$kullanici['id']);
            flash('Not eklendi.');
        }
    }

    // ── Maliyet → gider ─────────────────────────────────────
    // Çözülen talebin masrafı gider defterine yansıtılır. Oluşan gider
    // satırı kaynak_tur='talep' ile işaretlenir ve gider ekranından
    // silinemez — iki kaydın ayrışmasını önler.
    if ($islem === 'maliyet') {
        $talep_id = (int)($_POST['talep_id'] ?? 0);
        $tutar    = $_POST['maliyet'] === '' ? null
                  : max(0, (float)str_replace(',', '.', $_POST['maliyet'] ?? '0'));

        $st = $db->prepare("SELECT * FROM talepler WHERE id=? AND site_id=?");
        $st->execute([$talep_id, $site_id]);
        $talep = $st->fetch();

        if ($talep) {
            $gider_id = gidere_yansit(
                $site_id, 'talep', $talep_id, $talep['gider_id'] ? (int)$talep['gider_id'] : null,
                $tutar, 'TAMİRAT',
                'TALEP #' . $talep_id . ' — ' . $talep['baslik'],
                date('Y-m-d')
            );
            $db->prepare("UPDATE talepler SET maliyet=?, gider_id=? WHERE id=? AND site_id=?")
               ->execute([$tutar, $gider_id, $talep_id, $site_id]);
            flash($tutar ? 'Maliyet kaydedildi ve gidere işlendi.' : 'Maliyet kaldırıldı.');
        }
    }

    if ($islem === 'ek_sil') {
        ek_sil($site_id, (int)($_POST['ek_id'] ?? 0));
        flash('Ek silindi.');
    }

    if ($islem === 'sil') {
        $talep_id = (int)($_POST['talep_id'] ?? 0);
        hedefin_eklerini_sil($site_id, 'talep', $talep_id);
        bagli_gideri_sil($site_id, 'talep', $talep_id);
        $db->prepare("DELETE FROM talepler WHERE id=? AND site_id=?")->execute([$talep_id, $site_id]);
        flash('Talep silindi.');
        header('Location: /talepler.php');
        exit;
    }

    $geri = isset($_POST['talep_id']) && $islem !== 'sil' && $islem !== 'ekle'
        ? '/talepler.php?ac=' . (int)$_POST['talep_id']
        : '/talepler.php';
    header('Location: ' . $geri);
    exit;
}

function talep_gecmisine_yaz(int $talep_id, int $kullanici_id, ?string $yorum,
                             ?string $eski, ?string $yeni): void
{
    db()->prepare("
        INSERT INTO talep_yorumlari (talep_id, kullanici_id, yorum, durum_eski, durum_yeni)
        VALUES (?,?,?,?,?)
    ")->execute([$talep_id, $kullanici_id, $yorum, $eski, $yeni]);
}

// ─── VERİ ÇEK ────────────────────────────────────────────────
$durum_filtre = $_GET['durum'] ?? 'acik_olanlar';
$kategori_filtre = array_key_exists($_GET['kategori'] ?? '', TALEP_KATEGORILERI) ? $_GET['kategori'] : '';

$kosul = ['t.site_id = ?'];
$parametre = [$site_id];

if ($durum_filtre === 'acik_olanlar') {
    $kosul[] = "t.durum NOT IN ('cozuldu','kapali','iptal')";
} elseif (array_key_exists($durum_filtre, TALEP_DURUMLARI)) {
    $kosul[] = 't.durum = ?';
    $parametre[] = $durum_filtre;
}
if ($kategori_filtre !== '') {
    $kosul[] = 't.kategori = ?';
    $parametre[] = $kategori_filtre;
}

$st = $db->prepare("
    SELECT t.*, d.daire_no, b.ad AS blok_adi, p.ad_soyad AS personel_adi,
           (SELECT COUNT(*) FROM ekler e
             WHERE e.hedef_tur='talep' AND e.hedef_id=t.id) AS ek_sayisi
    FROM talepler t
    LEFT JOIN daireler d  ON d.id = t.daire_id
    LEFT JOIN bloklar  b  ON b.id = t.blok_id
    LEFT JOIN personel p  ON p.id = t.atanan_personel_id
    WHERE " . implode(' AND ', $kosul) . "
    ORDER BY FIELD(t.oncelik,'acil','yuksek','normal','dusuk'), t.acilis DESC
");
$st->execute($parametre);
$talepler = $st->fetchAll();

// Durum sayaçları (filtre çubuğundaki rozetler)
$st = $db->prepare("SELECT durum, COUNT(*) AS adet FROM talepler WHERE site_id=? GROUP BY durum");
$st->execute([$site_id]);
$sayaclar = array_column($st->fetchAll(), 'adet', 'durum');
$acik_toplam = array_sum(array_intersect_key($sayaclar, array_flip(['acik','islemde','beklemede'])));

$bloklar  = site_bloklari($site_id);
$st = $db->prepare("SELECT id, daire_no, sakin_adi FROM daireler WHERE site_id=? ORDER BY CAST(daire_no AS UNSIGNED), daire_no");
$st->execute([$site_id]);
$daireler = $st->fetchAll();
$st = $db->prepare("SELECT id, ad_soyad, gorev FROM personel WHERE site_id=? AND durum='aktif' ORDER BY ad_soyad");
$st->execute([$site_id]);
$personeller = $st->fetchAll();

// Açık talep detayı
$ac_id = (int)($_GET['ac'] ?? 0);
$detay = null; $gecmis = []; $detay_ekler = [];
if ($ac_id) {
    $st = $db->prepare("
        SELECT t.*, d.daire_no, b.ad AS blok_adi, p.ad_soyad AS personel_adi
        FROM talepler t
        LEFT JOIN daireler d ON d.id = t.daire_id
        LEFT JOIN bloklar  b ON b.id = t.blok_id
        LEFT JOIN personel p ON p.id = t.atanan_personel_id
        WHERE t.id=? AND t.site_id=?
    ");
    $st->execute([$ac_id, $site_id]);
    $detay = $st->fetch();

    if ($detay) {
        $st = $db->prepare("
            SELECT y.*, k.kullanici_adi FROM talep_yorumlari y
            LEFT JOIN kullanicilar k ON k.id = y.kullanici_id
            WHERE y.talep_id=? ORDER BY y.id
        ");
        $st->execute([$ac_id]);
        $gecmis = $st->fetchAll();
        $detay_ekler = ekleri_getir($site_id, 'talep', $ac_id);
    }
}

$oncelik_renk = ['acil'=>'#e74c3c','yuksek'=>'#f5a623','normal'=>'#6c8cff','dusuk'=>'#9494ae'];
$durum_renk   = ['acik'=>'#e94560','islemde'=>'#f5a623','beklemede'=>'#9494ae',
                 'cozuldu'=>'#2ecc71','kapali'=>'#0f9b8e','iptal'=>'#9494ae'];

include 'includes/header.php';
?>

<div class="toolbar">
  <div>
    <span class="muted">Açık talep:</span>
    <strong style="color:#e94560;font-size:20px;margin-left:8px"><?= (int)$acik_toplam ?></strong>
  </div>
  <button onclick="document.getElementById('modal-talep').style.display='flex'" class="btn btn-primary btn-sm">
    + Talep Aç
  </button>
</div>

<form method="get" class="filtre-cubugu">
  <select name="durum" class="input input-sm" onchange="this.form.submit()">
    <option value="acik_olanlar" <?= $durum_filtre==='acik_olanlar'?'selected':'' ?>>Açık olanlar (<?= (int)$acik_toplam ?>)</option>
    <option value="tumu" <?= $durum_filtre==='tumu'?'selected':'' ?>>Tümü</option>
    <?php foreach (TALEP_DURUMLARI as $k => $ad): ?>
      <option value="<?= e($k) ?>" <?= $durum_filtre===$k?'selected':'' ?>>
        <?= e($ad) ?> (<?= (int)($sayaclar[$k] ?? 0) ?>)
      </option>
    <?php endforeach; ?>
  </select>
  <select name="kategori" class="input input-sm" onchange="this.form.submit()">
    <option value="">Tüm kategoriler</option>
    <?php foreach (TALEP_KATEGORILERI as $k => $ad): ?>
      <option value="<?= e($k) ?>" <?= $kategori_filtre===$k?'selected':'' ?>><?= e($ad) ?></option>
    <?php endforeach; ?>
  </select>
</form>

<div class="card p0">
  <div class="table-wrap">
  <table class="table">
    <thead>
      <tr><th>#</th><th>Talep</th><th>Konum</th><th>Kategori</th><th>Öncelik</th>
          <th>Atanan</th><th>Açılış</th><th>Durum</th><th></th></tr>
    </thead>
    <tbody>
    <?php if (!$talepler): ?>
      <tr><td colspan="9" class="empty-state">Ölçütlere uyan talep yok.</td></tr>
    <?php endif; ?>
    <?php foreach ($talepler as $t): ?>
    <tr>
      <td class="muted">#<?= (int)$t['id'] ?></td>
      <td>
        <strong><?= e_buyuk($t['baslik']) ?></strong>
        <?php if ($t['ek_sayisi'] > 0): ?>
          <span class="badge" title="<?= (int)$t['ek_sayisi'] ?> ek">📎 <?= (int)$t['ek_sayisi'] ?></span>
        <?php endif; ?>
      </td>
      <td class="muted">
        <?= $t['blok_adi'] ? e_buyuk($t['blok_adi']) : '' ?>
        <?= $t['daire_no'] ? ' · Daire ' . e($t['daire_no']) : '' ?>
        <?= (!$t['blok_adi'] && !$t['daire_no']) ? '—' : '' ?>
      </td>
      <td><span class="badge badge-cat"><?= e(etiket(TALEP_KATEGORILERI, $t['kategori'])) ?></span></td>
      <td><span class="badge" style="background:<?= $oncelik_renk[$t['oncelik']] ?>22;color:<?= $oncelik_renk[$t['oncelik']] ?>">
        <?= e(etiket(TALEP_ONCELIKLERI, $t['oncelik'])) ?></span></td>
      <td class="muted"><?= $t['personel_adi'] ? e_buyuk($t['personel_adi']) : '—' ?></td>
      <td class="muted"><?= tarih_format($t['acilis']) ?></td>
      <td><span class="badge" style="background:<?= $durum_renk[$t['durum']] ?>22;color:<?= $durum_renk[$t['durum']] ?>">
        <?= e(etiket(TALEP_DURUMLARI, $t['durum'])) ?></span></td>
      <td><a href="?ac=<?= (int)$t['id'] ?>" class="btn btn-sm btn-ghost">Aç</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<!-- ══ YENİ TALEP ══════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-talep" style="display:none">
  <div class="modal">
    <div class="modal-header">
      <h3>+ Talep Aç</h3>
      <button onclick="document.getElementById('modal-talep').style.display='none'" class="modal-close">×</button>
    </div>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="islem" value="ekle">
      <div class="form-grid">
        <div class="form-group full-width">
          <label>Başlık <span class="req">*</span></label>
          <input type="text" name="baslik" class="input buyuk" required placeholder="örn. 2. KAT ASANSÖR ARIZASI">
        </div>
        <div class="form-group">
          <label>Kategori</label>
          <select name="kategori" class="input">
            <?php foreach (TALEP_KATEGORILERI as $k => $ad): ?>
              <option value="<?= e($k) ?>"><?= e($ad) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Öncelik</label>
          <select name="oncelik" class="input">
            <?php foreach (TALEP_ONCELIKLERI as $k => $ad): ?>
              <option value="<?= e($k) ?>" <?= $k==='normal'?'selected':'' ?>><?= e($ad) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php if (count($bloklar) > 1): ?>
        <div class="form-group">
          <label>Blok</label>
          <select name="blok_id" class="input">
            <option value="">— Genel —</option>
            <?php foreach ($bloklar as $b): ?>
              <option value="<?= (int)$b['id'] ?>"><?= e_buyuk($b['ad']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <div class="form-group">
          <label>Daire</label>
          <select name="daire_id" class="input">
            <option value="">— Ortak alan —</option>
            <?php foreach ($daireler as $d): ?>
              <option value="<?= (int)$d['id'] ?>">Daire <?= e($d['daire_no']) ?>
                <?= $d['sakin_adi'] ? '— ' . e(turkce_buyuk($d['sakin_adi'])) : '' ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Bildiren</label>
          <input type="text" name="bildiren" class="input buyuk" placeholder="Adı soyadı (isteğe bağlı)">
        </div>
        <div class="form-group">
          <label>Atanan personel</label>
          <select name="atanan_personel_id" class="input">
            <option value="">— Atanmadı —</option>
            <?php foreach ($personeller as $p): ?>
              <option value="<?= (int)$p['id'] ?>"><?= e_buyuk($p['ad_soyad']) ?>
                (<?= e(etiket(PERSONEL_GOREVLERI, $p['gorev'])) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group full-width">
          <label>Açıklama</label>
          <textarea name="aciklama" class="input" rows="3" placeholder="Sorunun ayrıntısı..."></textarea>
        </div>
        <div class="form-group full-width">
          <label>Fotoğraf / belge</label>
          <input type="file" name="ekler[]" class="input" multiple
                 accept=".jpg,.jpeg,.png,.webp,.pdf">
          <small class="muted">En fazla <?= round(DOSYA_MAX_BOYUT / 1048576) ?> MB. Dosyalar
            web erişimine kapalı bir klasörde saklanır.</small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">💾 Kaydet</button>
        <button type="button" onclick="document.getElementById('modal-talep').style.display='none'" class="btn btn-ghost">İptal</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ TALEP DETAYI ════════════════════════════════════════ -->
<?php if ($detay): ?>
<div class="modal-overlay" id="modal-detay" style="display:flex">
  <div class="modal modal-lg">
    <div class="modal-header">
      <h3>#<?= (int)$detay['id'] ?> — <?= e_buyuk($detay['baslik']) ?></h3>
      <a href="/talepler.php" class="modal-close">×</a>
    </div>

    <div class="modal-body">
      <div class="detay-kunye">
        <div><span>Kategori</span><strong><?= e(etiket(TALEP_KATEGORILERI, $detay['kategori'])) ?></strong></div>
        <div><span>Öncelik</span><strong style="color:<?= $oncelik_renk[$detay['oncelik']] ?>">
          <?= e(etiket(TALEP_ONCELIKLERI, $detay['oncelik'])) ?></strong></div>
        <div><span>Durum</span><strong style="color:<?= $durum_renk[$detay['durum']] ?>">
          <?= e(etiket(TALEP_DURUMLARI, $detay['durum'])) ?></strong></div>
        <div><span>Konum</span><strong>
          <?= $detay['blok_adi'] ? e_buyuk($detay['blok_adi']) : '' ?>
          <?= $detay['daire_no'] ? ' Daire ' . e($detay['daire_no']) : '' ?>
          <?= (!$detay['blok_adi'] && !$detay['daire_no']) ? 'Ortak alan' : '' ?></strong></div>
        <div><span>Atanan</span><strong><?= $detay['personel_adi'] ? e_buyuk($detay['personel_adi']) : '—' ?></strong></div>
        <div><span>Bildiren</span><strong><?= $detay['bildiren'] ? e_buyuk($detay['bildiren']) : '—' ?></strong></div>
        <div><span>Açılış</span><strong><?= e(date('d.m.Y H:i', strtotime($detay['acilis']))) ?></strong></div>
        <div><span>Kapanış</span><strong>
          <?= $detay['kapanis'] ? e(date('d.m.Y H:i', strtotime($detay['kapanis']))) : '—' ?></strong></div>
      </div>

      <?php if ($detay['aciklama']): ?>
      <div class="detay-blok">
        <h4>Açıklama</h4>
        <p><?= nl2br(e_buyuk($detay['aciklama'])) ?></p>
      </div>
      <?php endif; ?>

      <?php if ($detay_ekler): ?>
      <div class="detay-blok">
        <h4>Ekler</h4>
        <ul class="ek-listesi">
          <?php foreach ($detay_ekler as $ek): ?>
          <li>
            <a href="/belge_indir.php?id=<?= (int)$ek['id'] ?>">📎 <?= e($ek['orijinal_ad']) ?></a>
            <span class="muted"><?= e(boyut_okunabilir((int)$ek['boyut'])) ?></span>
            <form method="post" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
              <input type="hidden" name="islem" value="ek_sil">
              <input type="hidden" name="ek_id" value="<?= (int)$ek['id'] ?>">
              <input type="hidden" name="talep_id" value="<?= (int)$detay['id'] ?>">
              <button type="submit" class="btn btn-sm btn-danger" data-confirm="Ek silinsin mi?">✕</button>
            </form>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <!-- Maliyet → gider -->
      <div class="detay-blok">
        <h4>Maliyet</h4>
        <form method="post" class="satir-form">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="islem" value="maliyet">
          <input type="hidden" name="talep_id" value="<?= (int)$detay['id'] ?>">
          <input type="number" name="maliyet" step="0.01" min="0" class="input" style="max-width:160px"
                 value="<?= $detay['maliyet'] !== null ? e($detay['maliyet']) : '' ?>" placeholder="0.00">
          <button type="submit" class="btn btn-sm btn-primary">Kaydet</button>
        </form>
        <small class="muted">
          Girilen tutar gider defterine <strong>TAMİRAT</strong> kategorisiyle otomatik işlenir.
          Oluşan gider satırı bu talebe bağlıdır; giderler ekranından ayrıca silinemez.
          <?= $detay['gider_id'] ? ' (Gider #' . (int)$detay['gider_id'] . ')' : '' ?>
        </small>
      </div>

      <!-- İşlem geçmişi -->
      <div class="detay-blok">
        <h4>İşlem geçmişi</h4>
        <ol class="zaman-cizgisi">
          <?php foreach ($gecmis as $g): ?>
          <li>
            <div class="zc-ust">
              <strong><?= e($g['kullanici_adi'] ?? 'Sistem') ?></strong>
              <span class="muted"><?= e(date('d.m.Y H:i', strtotime($g['olusturma']))) ?></span>
            </div>
            <?php if ($g['durum_yeni']): ?>
              <div class="zc-durum">
                <?= $g['durum_eski']
                    ? e(etiket(TALEP_DURUMLARI, $g['durum_eski'])) . ' → '
                    : 'Talep açıldı: ' ?>
                <strong><?= e(etiket(TALEP_DURUMLARI, $g['durum_yeni'])) ?></strong>
              </div>
            <?php endif; ?>
            <?php if ($g['yorum']): ?><p><?= nl2br(e_buyuk($g['yorum'])) ?></p><?php endif; ?>
          </li>
          <?php endforeach; ?>
        </ol>

        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="islem" value="yorum">
          <input type="hidden" name="talep_id" value="<?= (int)$detay['id'] ?>">
          <div class="form-group">
            <label>Not ekle</label>
            <textarea name="yorum" class="input" rows="2" required placeholder="Yapılan işlem, görüşme notu..."></textarea>
          </div>
          <div class="form-group">
            <label>Ek dosya</label>
            <input type="file" name="ekler[]" class="input" multiple accept=".jpg,.jpeg,.png,.webp,.pdf">
          </div>
          <button type="submit" class="btn btn-sm btn-primary">Not Ekle</button>
        </form>
      </div>
    </div>

    <div class="modal-footer" style="flex-wrap:wrap;gap:8px">
      <form method="post" class="satir-form">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="islem" value="durum">
        <input type="hidden" name="talep_id" value="<?= (int)$detay['id'] ?>">
        <select name="durum" class="input input-sm" style="width:auto">
          <?php foreach (TALEP_DURUMLARI as $k => $ad): ?>
            <option value="<?= e($k) ?>" <?= $detay['durum']===$k?'selected':'' ?>><?= e($ad) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="text" name="yorum" class="input input-sm" placeholder="Değişiklik notu (isteğe bağlı)">
        <button type="submit" class="btn btn-sm btn-primary">Durumu Güncelle</button>
      </form>
      <a href="?ac=<?= (int)$detay['id'] ?>&duzenle=1" class="btn btn-sm btn-ghost">✎ Düzenle</a>
      <form method="post" style="display:inline">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="islem" value="sil">
        <input type="hidden" name="talep_id" value="<?= (int)$detay['id'] ?>">
        <button type="submit" class="btn btn-sm btn-danger"
                data-confirm="Talep, tüm notları ve ekleri silinsin mi?">✕ Sil</button>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ══ TALEP DÜZENLE ═══════════════════════════════════════ -->
<?php if ($detay && !empty($_GET['duzenle'])): ?>
<div class="modal-overlay" id="modal-talep-duzenle" style="display:flex">
  <div class="modal">
    <div class="modal-header">
      <h3>✎ Talep Düzenle</h3>
      <a href="?ac=<?= (int)$detay['id'] ?>" class="modal-close">×</a>
    </div>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="islem" value="guncelle">
      <input type="hidden" name="talep_id" value="<?= (int)$detay['id'] ?>">
      <div class="form-grid">
        <div class="form-group full-width">
          <label>Başlık <span class="req">*</span></label>
          <input type="text" name="baslik" class="input buyuk" required value="<?= e($detay['baslik']) ?>">
        </div>
        <div class="form-group">
          <label>Kategori</label>
          <select name="kategori" class="input">
            <?php foreach (TALEP_KATEGORILERI as $k => $ad): ?>
              <option value="<?= e($k) ?>" <?= $detay['kategori']===$k?'selected':'' ?>><?= e($ad) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Öncelik</label>
          <select name="oncelik" class="input">
            <?php foreach (TALEP_ONCELIKLERI as $k => $ad): ?>
              <option value="<?= e($k) ?>" <?= $detay['oncelik']===$k?'selected':'' ?>><?= e($ad) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php if (count($bloklar) > 1): ?>
        <div class="form-group">
          <label>Blok</label>
          <select name="blok_id" class="input">
            <option value="">— Genel —</option>
            <?php foreach ($bloklar as $b): ?>
              <option value="<?= (int)$b['id'] ?>" <?= (int)$detay['blok_id']===(int)$b['id']?'selected':'' ?>>
                <?= e_buyuk($b['ad']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <div class="form-group">
          <label>Daire</label>
          <select name="daire_id" class="input">
            <option value="">— Ortak alan —</option>
            <?php foreach ($daireler as $d): ?>
              <option value="<?= (int)$d['id'] ?>" <?= (int)$detay['daire_id']===(int)$d['id']?'selected':'' ?>>
                Daire <?= e($d['daire_no']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Bildiren</label>
          <input type="text" name="bildiren" class="input buyuk" value="<?= e($detay['bildiren'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Atanan personel</label>
          <select name="atanan_personel_id" class="input">
            <option value="">— Atanmadı —</option>
            <?php foreach ($personeller as $p): ?>
              <option value="<?= (int)$p['id'] ?>" <?= (int)$detay['atanan_personel_id']===(int)$p['id']?'selected':'' ?>>
                <?= e_buyuk($p['ad_soyad']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group full-width">
          <label>Açıklama</label>
          <textarea name="aciklama" class="input" rows="3"><?= e($detay['aciklama'] ?? '') ?></textarea>
        </div>
        <div class="form-group full-width">
          <label>Ek dosya</label>
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
