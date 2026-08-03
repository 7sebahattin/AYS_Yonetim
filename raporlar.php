<?php
// ============================================================
//  raporlar.php — RAPORLAR & YAZDIR
// ============================================================
require_once 'config.php';
require_once 'includes/functions.php';
$sayfa_basligi = 'Raporlar';

$kullanici = giris_kontrol();
$db  = db();
$donem = $_GET['donem'] ?? date('Y-m');

// ─── FİLTRE: 12 AYLIK TREND ──────────────────────────────────
$trend_bitis = $_GET['trend_bitis'] ?? $donem; 
$trend_baslangic = $_GET['trend_baslangic'] ?? date('Y-m', strtotime($trend_bitis . '-01 -11 months'));

// ─── VERİ ÇEK ────────────────────────────────────────────────
// Daire + aidat detayları
$stmt = $db->prepare("
    SELECT d.daire_no, d.sakin_adi, d.telefon, d.eposta, d.aylik_aidat,
           a.id AS aidat_id, a.tutar AS odenen_tutar, a.durum,
           a.odeme_tarihi, a.dekont_no, a.notlar
    FROM daireler d
    LEFT JOIN aidatlar a ON a.daire_id = d.id AND a.donem = ?
    WHERE d.site_id = ?
    ORDER BY d.daire_no
");
$stmt->execute([$donem, $kullanici['site_id']]);
$daire_listesi = $stmt->fetchAll();

// Giderler
$stmt = $db->prepare("SELECT * FROM giderler WHERE site_id=? AND donem=? ORDER BY tarih");
$stmt->execute([$kullanici['site_id'], $donem]);
$gider_listesi = $stmt->fetchAll();

// Özet
$stats = istatistikler($kullanici['site_id'], $donem);

// Filtrelenmiş Trend
$trend = trend_verisi($kullanici['site_id'], $trend_baslangic, $trend_bitis);

include 'includes/header.php';
?>

<div class="toolbar" style="margin-bottom:24px">
  <div></div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a href="/print.php?type=full&donem=<?= e($donem) ?>&trend_baslangic=<?= e($trend_baslangic) ?>&trend_bitis=<?= e($trend_bitis) ?>" target="_blank" class="btn btn-primary">
      🖨 Tam Rapor Yazdır
    </a>
  </div>
</div>

<div class="stats-grid print-section">
  <?php foreach ([
    ['Tahsilat', para($stats['tahsilat']), $stats['odenen_daire'].'/'.$stats['toplam_daire'].' daire', '#2ecc71'],
    ['Gider', para($stats['gider']), 'Bu dönem harcama', '#e94560'],
    ['Net Bakiye', para($stats['bakiye']), $stats['bakiye']>=0?'Pozitif':'Açık bakiye', $stats['bakiye']>=0?'#0f9b8e':'#e74c3c'],
    ['Tahsilat Oranı', '%'.$stats['tahsilat_oran'], $stats['bekleyen_daire'].' daire bekliyor', '#f5a623'],
  ] as [$lbl,$val,$alt,$renk]): ?>
  <div class="stat-card" style="border-left-color:<?= $renk ?>">
    <div class="stat-body">
      <div>
        <div class="stat-label"><?= $lbl ?></div>
        <div class="stat-value" style="color:<?= $renk ?>"><?= $val ?></div>
        <div class="stat-alt"><?= $alt ?></div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="two-col print-section">
  <div class="card p0">
    <div class="card-header">
      <span>💰 Aidat Detayı — <?= e(donem_adi($donem)) ?></span>
      <a href="/print.php?type=aidat&donem=<?= e($donem) ?>" target="_blank" class="btn btn-sm btn-ghost no-print">
        🖨 Yazdır
      </a>
    </div>
    <div class="table-wrap">
    <table class="table">
      <thead><tr><th>No</th><th>Sakin</th><th>Aidat</th><th>Ödeme Tarihi</th><th>Dekont</th><th>Durum</th></tr></thead>
      <tbody>
      <?php foreach ($daire_listesi as $r): ?>
      <tr>
        <td><strong class="daire-badge">#<?= e($r['daire_no']) ?></strong></td>
        <td><?= $r['sakin_adi'] ? e_buyuk($r['sakin_adi']) : '—' ?></td>
        <td><?= para((float)($r['odenen_tutar'] ?? $r['aylik_aidat'])) ?></td>
        <td><?= tarih_format($r['odeme_tarihi'] ?? '') ?></td>
        <td><?= $r['dekont_no'] ? e_buyuk($r['dekont_no']) : '—' ?></td>
        <td><span class="badge badge-<?= ($r['durum']??'bekliyor')==='odendi'?'success':'warning' ?>">
          <?= ($r['durum'] ?? 'bekliyor') === 'odendi' ? '✓ Ödendi' : '⏳ Bekliyor' ?>
        </span></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr><td colspan="2"><strong>Toplam Tahsilat</strong></td>
            <td colspan="4"><strong style="color:#2ecc71"><?= para($stats['tahsilat']) ?></strong></td></tr>
      </tfoot>
    </table>
    </div>
  </div>

  <div class="card p0">
    <div class="card-header">
      <span>📋 Gider Detayı — <?= e(donem_adi($donem)) ?></span>
      <a href="/print.php?type=gider&donem=<?= e($donem) ?>" target="_blank" class="btn btn-sm btn-ghost no-print">
        🖨 Yazdır
      </a>
    </div>
    <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Kategori</th><th>Açıklama</th><th>Tutar</th><th>Tarih</th></tr></thead>
      <tbody>
      <?php if (empty($gider_listesi)): ?>
        <tr><td colspan="4" class="empty-state">Gider kaydı yok.</td></tr>
      <?php endif; ?>
      <?php foreach ($gider_listesi as $g): ?>
      <tr>
        <td><span class="badge badge-cat"><?= e_buyuk($g['kategori']) ?></span></td>
        <td><?= e_buyuk($g['aciklama']) ?></td>
        <td style="color:#e94560"><strong><?= para((float)$g['tutar']) ?></strong></td>
        <td><?= tarih_format($g['tarih']) ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr><td colspan="2"><strong>Toplam Gider</strong></td>
            <td colspan="2"><strong style="color:#e94560"><?= para($stats['gider']) ?></strong></td></tr>
        <tr><td colspan="2"><strong>Net Bakiye</strong></td>
            <td colspan="2" style="color:<?= $stats['bakiye']>=0?'#0f9b8e':'#e74c3c' ?>">
              <strong><?= para($stats['bakiye']) ?></strong></td></tr>
      </tfoot>
    </table>
    </div>
  </div>
</div>

<div class="card p0 print-section" style="margin-top:24px;">
  <div class="card-header" style="flex-wrap:wrap; gap:10px;">
    <span>📈 Gelir/Gider Trendi</span>
    <form method="get" style="display:flex; gap:8px; align-items:center;" class="no-print">
      <input type="hidden" name="donem" value="<?= e($donem) ?>">
      <input type="month" name="trend_baslangic" class="input input-sm" style="width:auto" value="<?= e($trend_baslangic) ?>">
      <span style="color:var(--muted)">-</span>
      <input type="month" name="trend_bitis" class="input input-sm" style="width:auto" value="<?= e($trend_bitis) ?>">
      <button type="submit" class="btn btn-sm btn-primary">Filtrele</button>
    </form>
    <a href="/print.php?type=trend&trend_baslangic=<?= e($trend_baslangic) ?>&trend_bitis=<?= e($trend_bitis) ?>" target="_blank" class="btn btn-sm btn-ghost no-print">
      🖨 Yazdır
    </a>
  </div>
  <div class="table-wrap">
  <table class="table">
    <thead><tr><th>Dönem</th><th>Tahsilat</th><th>Gider</th><th>Net Bakiye</th><th>Durum</th></tr></thead>
    <tbody>
    <?php foreach ($trend as $t): ?>
    <tr <?= $t['donem']===$donem?'style="background:rgba(233,69,96,0.06)"':'' ?>>
      <td><strong><?= e($t['ad']) ?><?= $t['donem']===$donem?' ◀':'' ?></strong></td>
      <td style="color:#2ecc71"><?= para($t['tahsilat']) ?></td>
      <td style="color:#e94560"><?= para($t['gider']) ?></td>
      <td style="color:<?= $t['bakiye']>=0?'#0f9b8e':'#e74c3c' ?>"><?= para($t['bakiye']) ?></td>
      <td>
        <div style="width:100px;height:8px;background:rgba(255,255,255,0.08);border-radius:4px;overflow:hidden">
          <?php $maxT = max(array_column($trend,'tahsilat')+[1]); ?>
          <div style="width:<?= $maxT>0?round($t['tahsilat']/$maxT*100):0 ?>%;height:100%;background:#2ecc71;border-radius:4px"></div>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<?php include 'includes/footer.php'; ?>