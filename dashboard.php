<?php
// ============================================================
//  dashboard.php — ANA PANEL (MOBİL OPTİMİZE)
// ============================================================
require_once 'config.php';
require_once 'includes/functions.php';
$sayfa_basligi = 'Anasayfa';

$kullanici = giris_kontrol();
$donem     = $_GET['donem'] ?? date('Y-m');
$stats     = istatistikler($kullanici['site_id'], $donem);
$db        = db();

// Son 6 ay tahsilat trendi
$trend_data = [];
for ($i = 5; $i >= 0; $i--) {
    $d = date('Y-m', strtotime("-$i months"));
    $stmt = $db->prepare("SELECT COALESCE(SUM(tutar),0) FROM aidatlar WHERE site_id=? AND donem=? AND durum='odendi'");
    $stmt->execute([$kullanici['site_id'], $d]);
    $trend_data[] = ['donem' => $d, 'ad' => donem_adi($d), 'tutar' => (float)$stmt->fetchColumn()];
}
$max_trend = max(array_column($trend_data, 'tutar') ?: [1]);

// Ödeme bekleyen daireler
$stmt = $db->prepare("
    SELECT d.daire_no, d.sakin_adi, d.aylik_aidat,
           COALESCE(a.durum,'bekliyor') as durum
    FROM daireler d
    LEFT JOIN aidatlar a ON a.daire_id = d.id AND a.donem = ?
    WHERE d.site_id = ?
      AND (a.durum IS NULL OR a.durum <> 'odendi')
    ORDER BY d.daire_no
    LIMIT 8
");
$stmt->execute([$donem, $kullanici['site_id']]);
$bekleyenler = $stmt->fetchAll();

// Son giderler
$stmt = $db->prepare("SELECT * FROM giderler WHERE site_id=? AND donem=? ORDER BY tarih DESC LIMIT 5");
$stmt->execute([$kullanici['site_id'], $donem]);
$son_giderler = $stmt->fetchAll();

include 'includes/header.php';
?>

<!-- STAT KARTLARI -->
<div class="stats-grid">
  <?php
  $kartlar = [
    ['etiket'=>'Toplam Tahsilat', 'deger'=>para($stats['tahsilat']), 'alt'=>$stats['odenen_daire'].'/'.$stats['toplam_daire'].' daire ödedi', 'renk'=>'#2ecc71', 'ikon'=>'💰'],
    ['etiket'=>'Toplam Gider',    'deger'=>para($stats['gider']),    'alt'=>'Bu dönem harcama', 'renk'=>'#e94560', 'ikon'=>'📋'],
    ['etiket'=>'Net Bakiye',      'deger'=>para($stats['bakiye']),   'alt'=>$stats['bakiye']>=0?'Pozitif bakiye':'Açık bakiye!', 'renk'=>$stats['bakiye']>=0?'#0f9b8e':'#e74c3c', 'ikon'=>'📊'],
    ['etiket'=>'Bekleyen Ödeme',  'deger'=>$stats['bekleyen_daire'].' Daire', 'alt'=>'Aidat ödemesi bekliyor', 'renk'=>'#f5a623', 'ikon'=>'⚠'],
  ];
  foreach ($kartlar as $k): ?>
  <div class="stat-card" style="border-left-color:<?= $k['renk'] ?>">
    <div class="stat-body">
      <div>
        <div class="stat-label"><?= e($k['etiket']) ?></div>
        <div class="stat-value" style="color:<?= $k['renk'] ?>"><?= e($k['deger']) ?></div>
        <div class="stat-alt"><?= e($k['alt']) ?></div>
      </div>
      <div class="stat-icon"><?= $k['ikon'] ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- TAHSİLAT ORANI -->
<div class="card">
  <div class="card-header">
    <span>Aidat Tahsilat Oranı — <?= e(donem_adi($donem)) ?></span>
    <span class="badge badge-<?= $stats['tahsilat_oran'] >= 80 ? 'success' : 'warning' ?>">
      %<?= $stats['tahsilat_oran'] ?>
    </span>
  </div>
  <div class="progress-bar-wrap">
    <div class="progress-bar" style="width:<?= $stats['tahsilat_oran'] ?>%"></div>
  </div>
  <div class="progress-labels">
    <span><?= $stats['odenen_daire'] ?> daire ödedi</span>
    <span><?= $stats['bekleyen_daire'] ?> daire bekliyor</span>
  </div>
</div>

<!-- MOBİL: Tek kolon responsive cards -->
<div class="dashboard-cards">
  <!-- ÖDEME BEKLEYENLEr -->
  <div class="card">
    <div class="card-header">
      <span>⏳ Ödeme Bekleyenler</span>
      <a href="/aidatlar.php?donem=<?= e($donem) ?>" class="btn btn-sm btn-ghost">Tümü</a>
    </div>
    <?php if (empty($bekleyenler)): ?>
      <div class="empty-state">🎉 Bu dönem tüm daireler ödedi!</div>
    <?php else: ?>
      <?php foreach ($bekleyenler as $b): ?>
      <div class="dashboard-list-row">
        <div class="dashboard-list-info">
          <span class="daire-badge">#<?= e($b['daire_no']) ?></span>
          <span class="dashboard-list-name"><?= $b['sakin_adi'] ? e_buyuk($b['sakin_adi']) : 'İSİMSİZ' ?></span>
        </div>
        <div class="dashboard-list-right">
          <span class="dashboard-list-amount"><?= para($b['aylik_aidat']) ?></span>
          <a href="/aidatlar.php?odeme=<?= (int)$b['daire_no'] ?>&donem=<?= e($donem) ?>" 
             class="btn btn-xs btn-success">Ödendi</a>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- SON GİDERLER -->
  <div class="card">
    <div class="card-header">
      <span>📋 Son Giderler</span>
      <a href="/giderler.php?donem=<?= e($donem) ?>" class="btn btn-sm btn-ghost">Tümü</a>
    </div>
    <?php if (empty($son_giderler)): ?>
      <div class="empty-state">Bu dönem gider kaydı yok.</div>
    <?php else: ?>
      <?php foreach ($son_giderler as $g): ?>
      <div class="dashboard-list-row">
        <div class="dashboard-list-info">
          <span class="badge badge-cat"><?= e_buyuk($g['kategori']) ?></span>
          <span class="dashboard-list-name"><?= e(mb_strimwidth(turkce_buyuk($g['aciklama']), 0, 32, '…')) ?></span>
        </div>
        <div class="dashboard-list-right">
          <span class="dashboard-list-amount" style="color:#e94560"><?= para($g['tutar']) ?></span>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- TREND GRAFİĞİ -->
<div class="card">
  <div class="card-header"><span>📈 6 Aylık Tahsilat Trendi</span></div>
  <div class="trend-chart">
    <?php foreach ($trend_data as $t): ?>
    <?php $pct = $max_trend > 0 ? round(($t['tutar'] / $max_trend) * 100) : 0; ?>
    <div class="trend-bar-wrap">
      <div class="trend-amount"><?= para($t['tutar']) ?></div>
      <div class="trend-bar-container">
        <div class="trend-bar <?= $t['donem'] === $donem ? 'trend-bar-active' : '' ?>"
             style="height:<?= max($pct, 4) ?>%"></div>
      </div>
      <div class="trend-label"><?= e(substr(donem_adi($t['donem']), 0, 3)) ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
