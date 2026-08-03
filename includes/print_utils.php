<?php
// ============================================================
//  includes/print_utils.php — YAZDIRMA YARDIMCI FONKSİYONLAR
// ============================================================

/**
 * Yazdırma sayfası HTML header'ı (standart yapı)
 */
function print_header(string $baslik, array $kullanici): string {
    return <<<HTML
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$baslik} - Yazdır</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/print.css">
</head>
<body class="print-page">
HTML;
}

/**
 * Yazdırma sayfası için header bilgileri (Kompakt ve İstatistikli)
 */
function print_page_header(array $kullanici, string $baslik, string $donem = null, array $stats = null): string {
    $tarih_str = $donem ? donem_adi($donem) : tarih_format(date('Y-m-d'));
    $yazdirma_tarihi = date('d.m.Y H:i');
    $apartman_adi_html = e_buyuk($kullanici['apartman_adi']);
    
    // Eğer istatistik (stats) gönderildiyse, başlığın sağına ufak kartlar halinde ekle
    $stats_html = '';
    if ($stats) {
        $stats_html = '
        <div style="display:flex; gap:8px;">
            <div style="border:1px solid #e0e0e0; border-radius:4px; padding:4px 8px; text-align:center; background:#f8f9fa;">
                <div style="font-size:6.5pt; color:#666; font-weight:bold;">TAHSİLAT</div>
                <div style="font-size:10pt; font-weight:bold; color:#27ae60;">'.para($stats['tahsilat']).'</div>
            </div>
            <div style="border:1px solid #e0e0e0; border-radius:4px; padding:4px 8px; text-align:center; background:#f8f9fa;">
                <div style="font-size:6.5pt; color:#666; font-weight:bold;">GİDER</div>
                <div style="font-size:10pt; font-weight:bold; color:#e74c3c;">'.para($stats['gider']).'</div>
            </div>
            <div style="border:1px solid #e0e0e0; border-radius:4px; padding:4px 8px; text-align:center; background:#f8f9fa;">
                <div style="font-size:6.5pt; color:#666; font-weight:bold;">BAKİYE</div>
                <div style="font-size:10pt; font-weight:bold; color:'.($stats['bakiye']>=0?'#27ae60':'#e74c3c').';">'.para($stats['bakiye']).'</div>
            </div>
        </div>';
    }

    return <<<HTML
<div style="display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid #e94560; padding-bottom:8px; margin-bottom:12px;">
    <div style="display:flex; align-items:center; gap:10px;">
        <img src="/assets/icons/icon-192.png" alt="AYS logosu" style="width:36px; height:36px; border-radius:8px; display:block;">
        <div>
            <div style="font-size:14pt; font-weight:700; color:#1a1a2e; line-height:1.1; margin-bottom:3px;">{$apartman_adi_html}</div>
            <div style="font-size:8pt; color:#666;">Dönem: {$tarih_str} &nbsp;|&nbsp; Yazdırma: {$yazdirma_tarihi} &nbsp;|&nbsp; {$baslik}</div>
        </div>
    </div>
    {$stats_html}
</div>
HTML;
}

/**
 * Yazdırma kontrol butonları (ekranda görünür, yazdırmada gizlenir)
 */
function print_controls(): string {
    return <<<HTML
<div class="print-controls no-print">
    <button onclick="window.print()" class="btn-print-action btn-primary">
        🖨️ Yazdır
    </button>
    <button onclick="window.close()" class="btn-print-action btn-secondary">
        ✕ Kapat
    </button>
</div>
HTML;
}

/**
 * Yazdırma footer (sayfa numarası, imza alanı)
 */
function print_footer(bool $show_signature = true): string {
    $signature_html = '';
    if ($show_signature) {
        $signature_html = <<<HTML
<div class="print-signatures">
    <div class="signature-box">
        <div class="signature-line"></div>
        <div class="signature-label">Yönetici</div>
    </div>
    <div class="signature-box">
        <div class="signature-line"></div>
        <div class="signature-label">Teslim Alan</div>
    </div>
</div>
HTML;
    }
    
    return <<<HTML
<div class="print-footer">
    {$signature_html}
    <div class="print-page-number">Sayfa <span class="page-num"></span></div>
</div>

<script>
// Otomatik yazdırma (sayfa yüklenince)
window.addEventListener('load', function() {
    setTimeout(function() {
        // window.print(); // Otomatik yazdırma istemezseniz kaldırın
    }, 500);
});

// Sayfa numarası hesapla
document.querySelectorAll('.page-num').forEach(function(el) {
    el.textContent = '1';
});
</script>
</body>
</html>
HTML;
}

/**
 * Aidat tablosu yazdırma HTML'i (Sıralı, Kompakt ve Daraltılmış Alt Toplamlar)
 */
function print_aidat_table(array $daireler): string {
    $rows = '';
    $toplam_odenen = 0;
    $toplam_bekleyen = 0;

    foreach ($daireler as $d) {
        $tutar = (float)($d['odenen_tutar'] ?? $d['aylik_aidat']);
        $durum = $d['durum'] ?? 'bekliyor';

        if ($durum === 'odendi') {
            $toplam_odenen += $tutar;
        } else {
            $toplam_bekleyen += $tutar;
        }

        $durum_class = $durum === 'odendi' ? 'status-paid' : 'status-pending';
        $durum_text = $durum === 'odendi' ? '✓ Ödendi' : '⏳ Bekliyor';
        
        $rows .= sprintf(
            '<tr>
                <td class="text-center"><strong>#%s</strong></td>
                <td>%s</td>
                <td class="text-center">%s</td>
                <td class="text-center">%s</td>
                <td class="text-center"><span class="%s" style="padding:2px 5px; font-size:7.5pt;">%s</span></td>
                <td class="text-right"><strong>%s</strong></td>
            </tr>',
            e($d['daire_no']),
            $d['sakin_adi'] ? e_buyuk($d['sakin_adi']) : '—',
            $d['dekont_no'] ? e_buyuk($d['dekont_no']) : '—',
            tarih_format($d['odeme_tarihi'] ?? ''),
            $durum_class,
            $durum_text,
            para($tutar)
        );
    }

    $genel_toplam = $toplam_odenen + $toplam_bekleyen;
    
    $bekleyen_str = para($toplam_bekleyen);
    $odenen_str = para($toplam_odenen);
    $genel_str = para($genel_toplam);

    return <<<HTML
<table class="print-table" style="font-size:9pt; margin-bottom:15px;">
    <thead>
        <tr>
            <th class="text-center" style="padding:6px 4px;">Daire No</th>
            <th style="padding:6px 4px;">Sakin Adı</th>
            <th class="text-center" style="padding:6px 4px;">Dekont No</th>
            <th class="text-center" style="padding:6px 4px;">Ödeme Tarihi</th>
            <th class="text-center" style="padding:6px 4px;">Durum</th>
            <th class="text-right" style="padding:6px 4px;">Tutar</th>
        </tr>
    </thead>
    <tbody>
        {$rows}
    </tbody>
    <tfoot>
        <tr class="total-row" style="background:#fff3cd;">
            <td colspan="5" class="text-right" style="padding:3px 8px; font-size:8pt; border:none;"><strong>BEKLEYEN TOPLAM:</strong></td>
            <td class="text-right" style="padding:3px 8px; font-size:8.5pt; border:none;"><strong class="text-warning">{$bekleyen_str}</strong></td>
        </tr>
        <tr class="total-row" style="background:#d4edda;">
            <td colspan="5" class="text-right" style="padding:3px 8px; font-size:8pt; border:none;"><strong>ÖDENEN TOPLAM:</strong></td>
            <td class="text-right" style="padding:3px 8px; font-size:8.5pt; border:none;"><strong class="text-success">{$odenen_str}</strong></td>
        </tr>
        <tr class="total-row" style="background:#e8e8e8; border-top:1px solid #bbb;">
            <td colspan="5" class="text-right" style="padding:4px 8px; font-size:8.5pt;"><strong>GENEL TOPLAM AİDAT:</strong></td>
            <td class="text-right" style="padding:4px 8px; font-size:9.5pt;"><strong style="color:#2c3e50;">{$genel_str}</strong></td>
        </tr>
    </tfoot>
</table>
HTML;
}

/**
 * Gider tablosu yazdırma HTML'i
 */
function print_gider_table(array $giderler, float $toplam): string {
    if (empty($giderler)) {
        return '<p class="empty-message">Bu dönem gider kaydı bulunmamaktadır.</p>';
    }
    
    $rows = '';
    foreach ($giderler as $g) {
        $rows .= sprintf(
            '<tr>
                <td><span class="category-badge">%s</span></td>
                <td>%s</td>
                <td class="text-right text-danger"><strong>%s</strong></td>
                <td class="text-center">%s</td>
                <td class="text-center">%s</td>
            </tr>',
            e_buyuk($g['kategori']),
            e_buyuk($g['aciklama']),
            para((float)$g['tutar']),
            tarih_format($g['tarih']),
            $g['fatura_no'] ? e_buyuk($g['fatura_no']) : '—'
        );
    }
    
    $toplam_str = para($toplam);

    return <<<HTML
<table class="print-table">
    <thead>
        <tr>
            <th>Kategori</th>
            <th>Açıklama</th>
            <th class="text-right">Tutar</th>
            <th class="text-center">Tarih</th>
            <th class="text-center">Fatura No</th>
        </tr>
    </thead>
    <tbody>
        {$rows}
    </tbody>
    <tfoot>
        <tr class="total-row">
            <td colspan="2"><strong>TOPLAM GİDER</strong></td>
            <td class="text-right" colspan="3"><strong class="text-danger">{$toplam_str}</strong></td>
        </tr>
    </tfoot>
</table>
HTML;
}

/**
 * Bilanço özet tablosu: açılış + gelirler + giderler = dönem sonu bakiye.
 * Kalem sırası KMK m.33/m.39'un beklediği "hesap verme" mantığına
 * uyar: önce ne vardı, ne girdi, ne çıktı, sonunda ne kaldı.
 */
function print_bilanco_ozet_table(array $ozet): string {
    $satirlar = [
        ['Açılış (Devir) Bakiyesi', $ozet['acilis_bakiye'], null],
        ['Aidat Geliri', $ozet['aidat_geliri'], '+'],
    ];
    foreach ($ozet['diger_gelir_kirilimi'] as $tur => $tutar) {
        if ((float)$tutar <= 0) continue;
        $satirlar[] = [etiket(GELIR_TURLERI, $tur), (float)$tutar, '+'];
    }
    $satirlar[] = ['Toplam Gider', -$ozet['toplam_gider'], '-'];

    $rows = '';
    foreach ($satirlar as [$etiket, $tutar, $isaret]) {
        $renk = $isaret === '+' ? 'text-success' : ($isaret === '-' ? 'text-danger' : '');
        $rows .= sprintf(
            '<tr><td>%s</td><td class="text-right %s"><strong>%s</strong></td></tr>',
            e($etiket), $renk, para($tutar)
        );
    }

    $bakiye_class = $ozet['donem_sonu_bakiye'] >= 0 ? 'text-success' : 'text-danger';
    $bakiye_str   = para($ozet['donem_sonu_bakiye']);

    return <<<HTML
<table class="print-table" style="margin-bottom:15px">
    <tbody>{$rows}</tbody>
    <tfoot>
        <tr class="total-row" style="background:#e8e8e8;border-top:2px solid #999">
            <td><strong>DÖNEM SONU BAKİYE</strong></td>
            <td class="text-right {$bakiye_class}" style="font-size:11pt"><strong>{$bakiye_str}</strong></td>
        </tr>
    </tfoot>
</table>
HTML;
}

/**
 * Kategori kırılımlı gider tablosu (bilanço eki).
 */
function print_bilanco_gider_ozet(array $gider_kirilimi, float $toplam): string {
    if (empty($gider_kirilimi)) {
        return '<p class="empty-message">Bu yıl gider kaydı bulunmamaktadır.</p>';
    }
    $rows = '';
    foreach ($gider_kirilimi as $gk) {
        $pay = $toplam > 0 ? round($gk['toplam'] / $toplam * 100, 1) : 0;
        $rows .= sprintf(
            '<tr><td><span class="category-badge">%s</span></td>'
          . '<td class="text-right text-danger"><strong>%s</strong></td>'
          . '<td class="text-center">%%%s</td></tr>',
            e_buyuk($gk['kategori']), para((float)$gk['toplam']), e(number_format($pay, 1, ',', '.'))
        );
    }
    $toplam_str = para($toplam);
    return <<<HTML
<table class="print-table">
    <thead><tr><th>Kategori</th><th class="text-right">Tutar</th><th class="text-center">Pay</th></tr></thead>
    <tbody>{$rows}</tbody>
    <tfoot><tr class="total-row"><td><strong>TOPLAM GİDER</strong></td>
        <td class="text-right text-danger" colspan="2"><strong>{$toplam_str}</strong></td></tr></tfoot>
</table>
HTML;
}

/**
 * Daire bazlı borç listesi (bilanço eki). "Alacak" (fazla ödeme)
 * bu sistemde hesaplanmaz — aidatlar dönem başına tek tutar/durum
 * tutar, kısmi/fazla ödeme kavramı yok.
 */
function print_bilanco_borc_table(array $borclu_daireler, float $toplam_borc): string {
    if (empty($borclu_daireler)) {
        return '<p class="empty-message">Borcu olan daire bulunmamaktadır.</p>';
    }
    $rows = '';
    foreach ($borclu_daireler as $b) {
        $rows .= sprintf(
            '<tr><td class="text-center"><strong>#%s</strong></td><td>%s</td>'
          . '<td class="text-center">%s ay</td>'
          . '<td class="text-right text-danger"><strong>%s</strong></td></tr>',
            e($b['daire_no']), $b['sakin_adi'] ? e_buyuk($b['sakin_adi']) : '—',
            (int)$b['borclu_donem_sayisi'], para((float)$b['borc'])
        );
    }
    $toplam_str = para($toplam_borc);
    return <<<HTML
<table class="print-table">
    <thead><tr><th class="text-center">Daire</th><th>Sakin</th>
        <th class="text-center">Borçlu Dönem</th><th class="text-right">Borç</th></tr></thead>
    <tbody>{$rows}</tbody>
    <tfoot><tr class="total-row"><td colspan="3"><strong>TOPLAM BORÇ</strong></td>
        <td class="text-right text-danger"><strong>{$toplam_str}</strong></td></tr></tfoot>
</table>
HTML;
}

/**
 * Trend tablosu yazdırma HTML'i
 */
function print_trend_table(array $trend): string {
    $rows = '';
    foreach ($trend as $t) {
        $bakiye_class = $t['bakiye'] >= 0 ? 'text-success' : 'text-danger';
        
        $rows .= sprintf(
            '<tr>
                <td><strong>%s</strong></td>
                <td class="text-right text-success">%s</td>
                <td class="text-right text-danger">%s</td>
                <td class="text-right %s"><strong>%s</strong></td>
            </tr>',
            e($t['ad']),
            para($t['tahsilat']),
            para($t['gider']),
            $bakiye_class,
            para($t['bakiye'])
        );
    }
    
    return <<<HTML
<table class="print-table">
    <thead>
        <tr>
            <th>Dönem</th>
            <th class="text-right">Tahsilat</th>
            <th class="text-right">Gider</th>
            <th class="text-right">Net Bakiye</th>
        </tr>
    </thead>
    <tbody>
        {$rows}
    </tbody>
</table>
HTML;
}