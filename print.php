<?php
// ============================================================
//  print.php — PROFESYONEL YAZDIRMA SAYFASI
//  Güvenli · Esnek · A4 Optimized
// ============================================================
require_once 'config.php';
require_once 'includes/functions.php';
require_once 'includes/print_utils.php';

$kullanici = giris_kontrol();
$db = db();

// ═══ PARAMETRELERİ AL & DOĞRULA ════════════════════════════
$type  = $_GET['type'] ?? '';      // aidat | gider | trend | full
$donem = $_GET['donem'] ?? date('Y-m');

// Güvenlik: Sadece izin verilen tipler
$allowed_types = ['aidat', 'gider', 'trend', 'full'];
if (!in_array($type, $allowed_types)) {
    die('Geçersiz yazdırma tipi');
}

// Güvenlik: Dönem formatı kontrolü
if (!preg_match('/^\d{4}-\d{2}$/', $donem)) {
    die('Geçersiz dönem formatı');
}

// ═══ VERİYİ ÇEK ═════════════════════════════════════════════
$data = [];
$stats = istatistikler($kullanici['site_id'], $donem);

// Tip'e göre veri çek
switch ($type) {
    case 'aidat':
    case 'full':
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
        $data['aidatlar'] = $stmt->fetchAll();
        
        if ($type === 'aidat') break;
        // full için devam et
        
    case 'gider':
        $stmt = $db->prepare("SELECT * FROM giderler WHERE site_id=? AND donem=? ORDER BY tarih");
        $stmt->execute([$kullanici['site_id'], $donem]);
        $data['giderler'] = $stmt->fetchAll();
        
        if ($type === 'gider') break;
        // full için devam et
        
    case 'trend':
        $trend_bitis = $_GET['trend_bitis'] ?? $donem;
        $trend_baslangic = $_GET['trend_baslangic'] ?? date('Y-m', strtotime($trend_bitis . '-01 -11 months'));

        $data['trend'] = trend_verisi($kullanici['site_id'], $trend_baslangic, $trend_bitis);
        break;
}

// ═══ BAŞLIK BELİRLE ════════════════════════════════════════
$baslik_map = [
    'aidat' => 'Aidat Detayı',
    'gider' => 'Gider Detayı',
    'trend' => '12 Aylık Gelir/Gider Trendi',
    'full'  => 'Detaylı Finansal Rapor'
];
$sayfa_basligi = $baslik_map[$type];

// ═══ HTML ÇIKTI ════════════════════════════════════════════
echo print_header($sayfa_basligi, $kullanici);
echo print_controls();
?>

<div class="print-container">
    <?= print_page_header($kullanici, $sayfa_basligi, $donem, $type === 'full' ? $stats : null) ?>
    
    <?php if ($type === 'aidat' || $type === 'full'): ?>
        <div class="print-section">
            <h2 class="print-section-title" style="font-size:11pt; margin-bottom:10px; padding-bottom:4px;">💰 Aidat Detayı — <?= e(donem_adi($donem)) ?></h2>
            <?= print_aidat_table($data['aidatlar']) ?>
        </div>
    <?php endif; ?>
    
    <?php if ($type === 'gider' || $type === 'full'): ?>
        <div class="print-section">
            <h2 class="print-section-title" style="font-size:11pt; margin-bottom:10px; padding-bottom:4px;">📋 Gider Detayı — <?= e(donem_adi($donem)) ?></h2>
            <?= print_gider_table($data['giderler'] ?? [], $stats['gider']) ?>
        </div>
    <?php endif; ?>
    
    <?php if ($type === 'trend' || $type === 'full'): ?>
        <div class="print-section">
            <h2 class="print-section-title" style="font-size:11pt; margin-bottom:10px; padding-bottom:4px;">📈 Gelir/Gider Trendi</h2>
            <?= print_trend_table($data['trend']) ?>
        </div>
    <?php endif; ?>
    
    <?= print_footer($type === 'full') ?>
</div>