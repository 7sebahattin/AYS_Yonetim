<?php
// ============================================================
//  daire_print.php — DAİRE DETAY YAZDIRMA
// ============================================================
require_once 'config.php';
require_once 'includes/functions.php';
require_once 'includes/print_utils.php';

$kullanici = giris_kontrol();
$db = db();

// ═══ PARAMETRELER ═══════════════════════════════════════════
$daire_id = (int)($_GET['id'] ?? 0);

if (!$daire_id) {
    die('Daire ID belirtilmedi');
}

// Güvenlik: Bu daire bu kullanıcıya ait mi?
$stmt = $db->prepare("SELECT * FROM daireler WHERE id=? AND kullanici_id=?");
$stmt->execute([$daire_id, $kullanici['id']]);
$daire = $stmt->fetch();

if (!$daire) {
    die('Yetkisiz erişim veya daire bulunamadı');
}

// ═══ VERİ ÇEK ═══════════════════════════════════════════════
// Tüm dönem geçmişi
$stmt = $db->prepare("
    SELECT * FROM aidatlar
    WHERE daire_id = ? AND kullanici_id = ?
    ORDER BY donem DESC
");
$stmt->execute([$daire_id, $kullanici['id']]);
$tum_aidatlar = $stmt->fetchAll();

// İstatistik
$toplam_odenen   = array_sum(array_column(array_filter($tum_aidatlar, fn($r) => $r['durum'] === 'odendi'), 'tutar'));
$odenen_ay_say   = count(array_filter($tum_aidatlar, fn($r) => $r['durum'] === 'odendi'));
$bekleyen_ay_say = count(array_filter($tum_aidatlar, fn($r) => $r['durum'] !== 'odendi'));

$sayfa_basligi = "Daire #" . $daire['daire_no'] . " Detay Raporu";

// ═══ HTML ÇIKTI ════════════════════════════════════════════
echo print_header($sayfa_basligi, $kullanici);
echo print_controls();
?>

<div class="print-container">
    <?= print_page_header($kullanici, $sayfa_basligi) ?>
    
    <!-- DAİRE BİLGİLERİ -->
    <div class="print-section">
        <h2 class="print-section-title">🏢 Daire Bilgileri</h2>
        <table class="print-table" style="margin-bottom:20px">
            <tr>
                <td style="background:#f8f9fa;font-weight:600;width:30%">Daire No:</td>
                <td><strong style="color:#e94560;font-size:14pt">#<?= e($daire['daire_no']) ?></strong></td>
            </tr>
            <tr>
                <td style="background:#f8f9fa;font-weight:600">Kat:</td>
                <td><?= $daire['kat'] !== null ? e($daire['kat'].'. Kat') : '—' ?></td>
            </tr>
            <tr>
                <td style="background:#f8f9fa;font-weight:600">Sakin Adı:</td>
                <td><?= e($daire['sakin_adi'] ?: '—') ?></td>
            </tr>
            <tr>
                <td style="background:#f8f9fa;font-weight:600">Telefon:</td>
                <td><?= e($daire['telefon'] ?: '—') ?></td>
            </tr>
            <tr>
                <td style="background:#f8f9fa;font-weight:600">E-posta:</td>
                <td><?= e($daire['eposta'] ?: '—') ?></td>
            </tr>
            <tr>
                <td style="background:#f8f9fa;font-weight:600">Aylık Aidat:</td>
                <td><strong><?= para((float)$daire['aylik_aidat']) ?></strong></td>
            </tr>
        </table>
    </div>
    
    <!-- ÖZET İSTATİSTİKLER -->
    <div class="print-summary-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:25px">
        <div class="print-summary-card">
            <div class="summary-label">Toplam Ödenen</div>
            <div class="summary-value text-success"><?= para($toplam_odenen) ?></div>
            <div class="summary-subtitle"><?= $odenen_ay_say ?> ay ödendi</div>
        </div>
        <div class="print-summary-card">
            <div class="summary-label">Bekleyen Ay</div>
            <div class="summary-value text-warning"><?= $bekleyen_ay_say ?></div>
            <div class="summary-subtitle">Ödenmemiş dönem</div>
        </div>
        <div class="print-summary-card">
            <div class="summary-label">Son Ödeme</div>
            <div class="summary-value text-success">
                <?php
                $son_odeme = array_filter($tum_aidatlar, fn($r) => $r['durum'] === 'odendi');
                if (!empty($son_odeme)) {
                    $son_tarih = max(array_column($son_odeme, 'odeme_tarihi'));
                    echo tarih_format($son_tarih);
                } else {
                    echo '—';
                }
                ?>
            </div>
            <div class="summary-subtitle">Son ödeme tarihi</div>
        </div>
    </div>
    
    <!-- TÜM DÖNEM GEÇMİŞİ -->
    <div class="print-section">
        <h2 class="print-section-title">📋 Tüm Dönem Geçmişi (<?= count($tum_aidatlar) ?> kayıt)</h2>
        
        <?php if (empty($tum_aidatlar)): ?>
            <p class="empty-message">Henüz hiç aidat kaydı bulunmamaktadır.</p>
        <?php else: ?>
            <table class="print-table">
                <thead>
                    <tr>
                        <th>Dönem</th>
                        <th class="text-right">Tutar</th>
                        <th class="text-center">Durum</th>
                        <th class="text-center">Ödeme Tarihi</th>
                        <th>Dekont No</th>
                        <th>Not</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tum_aidatlar as $a): ?>
                    <tr>
                        <td><strong><?= e(donem_adi($a['donem'])) ?></strong></td>
                        <td class="text-right"><?= para((float)$a['tutar']) ?></td>
                        <td class="text-center">
                            <span class="<?= $a['durum'] === 'odendi' ? 'status-paid' : 'status-pending' ?>">
                                <?= $a['durum'] === 'odendi' ? '✓ Ödendi' : '⏳ Bekliyor' ?>
                            </span>
                        </td>
                        <td class="text-center"><?= tarih_format($a['odeme_tarihi'] ?? '') ?></td>
                        <td><?= e($a['dekont_no'] ?: '—') ?></td>
                        <td style="font-size:9pt;color:#666"><?= e($a['notlar'] ?: '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td><strong>TOPLAM ÖDENDİ</strong></td>
                        <td class="text-right" colspan="5">
                            <strong style="color:#27ae60;font-size:12pt"><?= para($toplam_odenen) ?></strong>
                        </td>
                    </tr>
                </tfoot>
            </table>
        <?php endif; ?>
    </div>
    
    <?= print_footer(true) ?>
</div>
