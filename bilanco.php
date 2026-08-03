<?php
// ============================================================
//  bilanco.php — RESMİ BİLANÇO / YIL SONU KAPANIŞ RAPORU
//
//  ⚠️ KAPSAM UYARISI: Bu rapor mali müşavir görüşü alınmadan
//  tasarlandı. Kat Mülkiyeti Kanunu'nun yöneticiye yüklediği hesap
//  verme yükümlülüğünü (m.33, m.39) karşılamayı HEDEFLER; kesin
//  format için bir mali müşavire teyit ettirilmesi önerilir. Bu
//  uyarı sayfada da kalıcı olarak gösterilir.
// ============================================================
require_once 'config.php';
require_once 'includes/functions.php';

$sayfa_basligi = 'Bilanço';
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
// Formlar action olmadan gönderildiği için tarayıcı, mevcut sayfanın
// sorgu dizesini (?yil=...) POST isteğine de taşır — bu standart
// davranıştır ama test/otomasyon araçları (ör. doğrudan curl) bunu
// yapmayabilir. Belirsizliği ortadan kaldırmak için yıl her iki formda
// da gizli alan olarak AYRICA gönderilir ve POST'ta önce o okunur.
$yil = (int)($_POST['yil'] ?? $_GET['yil'] ?? date('Y'));
if ($yil < 2000 || $yil > 2100) $yil = (int)date('Y');

// ─── FORM İŞLEME ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_kontrol();
    $islem = $_POST['islem'] ?? '';

    if ($islem === 'acilis_kaydet') {
        $tutar    = (float)str_replace(',', '.', $_POST['acilis_tutar'] ?? '0');
        $aciklama = trim($_POST['acilis_aciklama'] ?? '') ?: null;
        acilis_bakiyesi_yaz($site_id, $yil, $tutar, $aciklama, (int)$kullanici['id']);
        denetim_yaz('acilis_bakiyesi_guncellendi', 'donem_acilis_bakiye', null, ['yil' => $yil, 'tutar' => $tutar]);
        flash($yil . ' yılı açılış bakiyesi kaydedildi.');
    }

    if ($islem === 'yili_kapat') {
        $ozet = yillik_ozet($site_id, $yil);
        if (yili_kapat($site_id, $yil, $ozet['donem_sonu_bakiye'], (int)$kullanici['id'])) {
            denetim_yaz('yil_kapatildi', 'donem_acilis_bakiye', null,
                        ['yil' => $yil, 'devir' => $ozet['donem_sonu_bakiye']]);
            flash($yil . ' yılı kapatıldı. ' . para($ozet['donem_sonu_bakiye'])
                . ' tutarı ' . ($yil + 1) . ' yılına devir bakiyesi olarak yazıldı.');
        } else {
            flash(($yil + 1) . ' yılı için zaten bir açılış bakiyesi girilmiş; üzerine '
                . 'yazılmadı. Değiştirmek isterseniz o yılın açılış bakiyesini elle güncelleyin.', 'uyari');
        }
    }

    header("Location: /bilanco.php?yil=$yil");
    exit;
}

$ozet = yillik_ozet($site_id, $yil);

$acilis_kaydi = $db->prepare("SELECT tutar, aciklama, guncelleme FROM donem_acilis_bakiye WHERE site_id=? AND yil=?");
$acilis_kaydi->execute([$site_id, $yil]);
$acilis_kaydi = $acilis_kaydi->fetch();

// Bir sonraki yılın açılışı zaten girilmiş mi? (kapatma düğmesinin
// durumunu belirlemek için — girilmişse üzerine yazılmayacağı arayüzde
// önceden belirtilir.)
$sonraki_kayitli = $db->prepare("SELECT 1 FROM donem_acilis_bakiye WHERE site_id=? AND yil=?");
$sonraki_kayitli->execute([$site_id, $yil + 1]);
$sonraki_kayitli = (bool)$sonraki_kayitli->fetchColumn();

// Bu site için karar verilebilecek yıl aralığı (kayıt olan en eski
// dönemden bugüne + bir sonraki yıl, açılış girmek isteyenler için).
$ilk_yil = (int)$db->query("SELECT MIN(YEAR(STR_TO_DATE(CONCAT(donem,'-01'),'%Y-%m-%d')))
                            FROM (SELECT donem FROM aidatlar WHERE site_id=" . (int)$site_id . "
                                  UNION SELECT donem FROM giderler WHERE site_id=" . (int)$site_id . ") x")
                    ->fetchColumn() ?: date('Y');
$yil_listesi = range(max($ilk_yil, (int)date('Y') - 6), (int)date('Y') + 1);
rsort($yil_listesi);

include 'includes/header.php';
?>

<div class="uyari-yasal">
  ⚖️ <strong>Kapsam uyarısı:</strong> Bu rapor mali müşavir onaylı bir belge değildir;
  Kat Mülkiyeti Kanunu'nun hesap verme yükümlülüğünü (m.33, m.39) karşılamayı hedefler.
  Resmi kullanım öncesi bir mali müşavire teyit ettirmeniz önerilir.
</div>

<div class="toolbar">
  <form method="get" style="display:flex;align-items:center;gap:10px">
    <label style="font-weight:700;color:var(--accent)">Yıl:</label>
    <select name="yil" class="input input-sm" style="width:auto" onchange="this.form.submit()">
      <?php foreach ($yil_listesi as $y): ?>
        <option value="<?= $y ?>" <?= $y===$yil?'selected':'' ?>><?= $y ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <a href="/print_bilanco.php?yil=<?= $yil ?>" target="_blank" class="btn btn-primary btn-sm">
    🖨 Bilançoyu Yazdır
  </a>
</div>

<div class="stats-grid">
  <div class="stat-card" style="border-left-color:#6c8cff">
    <div class="stat-body"><div>
      <div class="stat-label">Açılış Bakiyesi</div>
      <div class="stat-value" style="color:#6c8cff"><?= para($ozet['acilis_bakiye']) ?></div>
      <div class="stat-alt"><?= $yil ?> yılı başı</div>
    </div></div>
  </div>
  <div class="stat-card" style="border-left-color:#2ecc71">
    <div class="stat-body"><div>
      <div class="stat-label">Toplam Gelir</div>
      <div class="stat-value" style="color:#2ecc71"><?= para($ozet['toplam_gelir']) ?></div>
      <div class="stat-alt">Aidat <?= para($ozet['aidat_geliri']) ?> + Diğer <?= para($ozet['diger_gelir_toplam']) ?></div>
    </div></div>
  </div>
  <div class="stat-card" style="border-left-color:#e94560">
    <div class="stat-body"><div>
      <div class="stat-label">Toplam Gider</div>
      <div class="stat-value" style="color:#e94560"><?= para($ozet['toplam_gider']) ?></div>
      <div class="stat-alt"><?= count($ozet['gider_kirilimi']) ?> kategori</div>
    </div></div>
  </div>
  <div class="stat-card" style="border-left-color:<?= $ozet['donem_sonu_bakiye']>=0?'#0f9b8e':'#e74c3c' ?>">
    <div class="stat-body"><div>
      <div class="stat-label">Dönem Sonu Bakiye</div>
      <div class="stat-value" style="color:<?= $ozet['donem_sonu_bakiye']>=0?'#0f9b8e':'#e74c3c' ?>">
        <?= para($ozet['donem_sonu_bakiye']) ?></div>
      <div class="stat-alt"><?= $ozet['donem_sonu_bakiye']>=0?'Pozitif':'Açık bakiye!' ?></div>
    </div></div>
  </div>
</div>

<div class="two-col">
  <!-- Açılış bakiyesi -->
  <div class="card">
    <div class="card-header"><span>💼 Açılış Bakiyesi</span></div>
    <form method="post" class="satir-form" style="margin-top:12px">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="islem" value="acilis_kaydet">
      <input type="hidden" name="yil" value="<?= $yil ?>">
      <div class="form-group">
        <label>Tutar (₺)</label>
        <input type="number" name="acilis_tutar" step="0.01" class="input"
               value="<?= e($acilis_kaydi['tutar'] ?? '0') ?>">
      </div>
      <div class="form-group full-width">
        <label>Açıklama</label>
        <input type="text" name="acilis_aciklama" class="input buyuk"
               value="<?= e($acilis_kaydi['aciklama'] ?? '') ?>"
               placeholder="örn. Banka hesabı devir bakiyesi">
      </div>
      <button type="submit" class="btn btn-sm btn-primary">Kaydet</button>
    </form>
    <?php if ($acilis_kaydi): ?>
      <p class="muted" style="font-size:12px;margin-top:8px">
        Son güncelleme: <?= e(date('d.m.Y H:i', strtotime($acilis_kaydi['guncelleme']))) ?>
      </p>
    <?php else: ?>
      <p class="muted" style="font-size:12px;margin-top:8px">
        Bu yıl için henüz açılış bakiyesi girilmedi (0 kabul ediliyor).
      </p>
    <?php endif; ?>
  </div>

  <!-- Yılı kapat -->
  <div class="card">
    <div class="card-header"><span>🔒 Yıl Sonu Kapanışı</span></div>
    <p class="muted" style="font-size:13px;margin:10px 0">
      Dönem sonu bakiyeyi (<strong><?= para($ozet['donem_sonu_bakiye']) ?></strong>)
      <?= $yil + 1 ?> yılının açılış bakiyesi olarak yazar. Devir bakiyesini elle
      taşımak hata riski taşır; bu işlem zinciri otomatik kurar.
    </p>
    <?php if ($sonraki_kayitli): ?>
      <div class="uyari-yasal" style="margin:0 0 12px">
        ⚠️ <?= $yil + 1 ?> yılı için zaten bir açılış bakiyesi girilmiş. Kapatma işlemi
        onu <strong>ezmeyecek</strong> — üzerine yazmak isterseniz <?= $yil + 1 ?> yılını
        seçip açılış bakiyesini elle güncelleyin.
      </div>
    <?php endif; ?>
    <form method="post" onsubmit="return confirm('<?= $yil ?> yılı kapatılsın mı? Dönem sonu bakiye <?= $yil + 1 ?> yılına devredilecek.')">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="islem" value="yili_kapat">
      <input type="hidden" name="yil" value="<?= $yil ?>">
      <button type="submit" class="btn btn-sm btn-primary" <?= $sonraki_kayitli ? 'disabled' : '' ?>>
        <?= $yil ?> Yılını Kapat →  <?= $yil + 1 ?> Yılına Devret
      </button>
    </form>
  </div>
</div>

<div class="two-col">
  <div class="card p0">
    <div class="card-header"><span>📋 Gider Dağılımı — <?= $yil ?></span></div>
    <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Kategori</th><th>Tutar</th><th>Pay</th></tr></thead>
      <tbody>
      <?php if (!$ozet['gider_kirilimi']): ?>
        <tr><td colspan="3" class="empty-state">Gider kaydı yok.</td></tr>
      <?php endif; ?>
      <?php foreach ($ozet['gider_kirilimi'] as $gk): ?>
        <?php $pay = $ozet['toplam_gider'] > 0 ? round($gk['toplam'] / $ozet['toplam_gider'] * 100, 1) : 0; ?>
        <tr>
          <td><span class="badge badge-cat"><?= e_buyuk($gk['kategori']) ?></span></td>
          <td style="color:#e94560"><strong><?= para((float)$gk['toplam']) ?></strong></td>
          <td class="muted">%<?= e(rtrim(rtrim(number_format($pay,1,',','.'),'0'),',')) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <div style="padding:12px 16px;border-top:1px solid var(--border);font-size:13px" class="muted">
      Önceki yıl (<?= $ozet['onceki_yil'] ?>) toplam gider: <strong><?= para($ozet['onceki_gider']) ?></strong>
      <?php if ($ozet['onceki_gider'] > 0):
        $fark = round((($ozet['toplam_gider'] - $ozet['onceki_gider']) / $ozet['onceki_gider']) * 100, 1); ?>
        <span style="color:<?= $fark>=0?'#e94560':'#2ecc71' ?>">(<?= $fark>=0?'+':'' ?><?= e($fark) ?>%)</span>
      <?php endif; ?>
    </div>
  </div>

  <div class="card p0">
    <div class="card-header">
      <span>📊 Tahsilat Durumu</span>
    </div>
    <div style="padding:16px">
      <div style="display:flex;justify-content:space-between;margin-bottom:8px">
        <span class="muted">Tahsilat oranı</span>
        <strong style="color:<?= $ozet['tahsilat_oran']>=80?'#2ecc71':'#f5a623' ?>">%<?= $ozet['tahsilat_oran'] ?></strong>
      </div>
      <div style="width:100%;height:10px;background:rgba(255,255,255,.08);border-radius:5px;overflow:hidden;margin-bottom:16px">
        <div style="width:<?= $ozet['tahsilat_oran'] ?>%;height:100%;background:#2ecc71;border-radius:5px"></div>
      </div>
      <div style="display:flex;justify-content:space-between">
        <span class="muted">Toplam borç (<?= $yil ?>)</span>
        <strong style="color:#e74c3c"><?= para($ozet['toplam_borc']) ?></strong>
      </div>
    </div>
  </div>
</div>

<div class="card p0">
  <div class="card-header"><span>🏢 Daire Bazlı Borç Listesi — <?= $yil ?></span></div>
  <div class="table-wrap">
  <table class="table">
    <thead><tr><th>Daire</th><th>Sakin</th><th>Borçlu Dönem</th><th>Borç</th></tr></thead>
    <tbody>
    <?php if (!$ozet['borclu_daireler']): ?>
      <tr><td colspan="4" class="empty-state">Borcu olan daire yok. 🎉</td></tr>
    <?php endif; ?>
    <?php foreach ($ozet['borclu_daireler'] as $b): ?>
      <tr>
        <td><strong class="daire-badge">#<?= e($b['daire_no']) ?></strong></td>
        <td><?= $b['sakin_adi'] ? e_buyuk($b['sakin_adi']) : '—' ?></td>
        <td class="muted"><?= (int)$b['borclu_donem_sayisi'] ?> ay</td>
        <td><strong style="color:#e74c3c"><?= para((float)$b['borc']) ?></strong></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <?php if ($ozet['borclu_daireler']): ?>
    <tfoot>
      <tr><td colspan="3"><strong>TOPLAM BORÇ</strong></td>
          <td><strong style="color:#e74c3c"><?= para($ozet['toplam_borc']) ?></strong></td></tr>
    </tfoot>
    <?php endif; ?>
  </table>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
