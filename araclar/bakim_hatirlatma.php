<?php
// ============================================================
//  araclar/bakim_hatirlatma.php — YAKLAŞAN BAKIM E-POSTASI
//
//  Kullanım (cPanel cron, günde bir kez):
//    php /home/<kullanici>/public_html/araclar/bakim_hatirlatma.php
//
//  Seçenekler:
//    --gun=14     kaç gün önceden uyarılsın (varsayılan 14)
//    --deneme     e-posta göndermeden ne olacağını yazdırır
//
//  NEDEN GEREKLİ: Asansör yıllık muayenesi, yangın tüpü dolum
//  kontrolü, jeneratör ve paratoner ölçümü yasal zorunluluktur ve
//  kaçırılması ciddi sorumluluk doğurur. Dashboard kartı yalnızca
//  panele giren yöneticiye görünür; e-posta, panele bakmayan
//  yöneticiye de ulaşır.
//
//  Aynı bakım için ikinci kez e-posta gönderilmez
//  (bakimlar.hatirlatma_gonderildi). Tarih değişirse bayrak
//  uygulama tarafından sıfırlanmaz — bilinçli: bir bakım hakkında
//  spam yapmaktansa bir kez net uyarmak yeterli.
// ============================================================

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("Bu betik yalnızca komut satırından çalıştırılır.\n");
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/operasyon.php';
require_once __DIR__ . '/../includes/eposta.php';

if (!operasyon_semasi_hazir_mi()) {
    fwrite(STDERR, "Operasyon şeması hazır değil. Önce göçleri uygulayın:\n"
                 . "  php araclar/goc_cli.php uygula\n");
    exit(1);
}

// ─── Seçenekler ─────────────────────────────────────────────
$gun    = 14;
$deneme = false;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--gun=')) $gun = max(1, (int)substr($arg, 6));
    if ($arg === '--deneme') $deneme = true;
}

if (!$deneme && !eposta_yapilandirildi_mi()) {
    fwrite(STDERR, "SMTP yapılandırılmamış (config.php). E-posta gönderilemez.\n"
                 . "Ne gönderileceğini görmek için: --deneme\n");
    exit(1);
}

// ─── Yaklaşan ve geciken bakımlar ───────────────────────────
// Geçmiş tarihliler de dahil: "tarihi geçti, artık uyarma" davranışı
// bu betiğin amacını boşa çıkarırdı.
$st = db()->prepare("
    SELECT b.id, b.baslik, b.tur, b.planlanan_tarih, b.site_id,
           d.ad AS demirbas_adi, s.ad AS site_adi,
           DATEDIFF(b.planlanan_tarih, CURDATE()) AS kalan_gun
    FROM bakimlar b
    JOIN demirbaslar d ON d.id = b.demirbas_id
    JOIN siteler s     ON s.id = b.site_id
    WHERE b.durum = 'planlandi'
      AND b.hatirlatma_gonderildi = 0
      AND b.planlanan_tarih IS NOT NULL
      AND b.planlanan_tarih <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
      AND s.durum = 'aktif'
    ORDER BY b.site_id, b.planlanan_tarih
");
$st->execute([$gun]);
$satirlar = $st->fetchAll();

if (!$satirlar) {
    echo "Hatırlatılacak bakım yok (önümüzdeki {$gun} gün).\n";
    exit(0);
}

// Site başına grupla: bir yöneticiye tek e-posta gitsin, her bakım
// için ayrı ayrı değil.
$siteye_gore = [];
foreach ($satirlar as $r) {
    $siteye_gore[(int)$r['site_id']][] = $r;
}

$gonderilen = 0;
$atlanan    = 0;

foreach ($siteye_gore as $site_id => $bakimlar) {
    // Sitenin doğrulanmış e-postası olan yöneticileri
    $st = db()->prepare("
        SELECT DISTINCT k.eposta
        FROM kullanici_site_yetkileri y
        JOIN kullanicilar k ON k.id = y.kullanici_id
        WHERE y.site_id = ? AND y.durum = 'aktif'
          AND k.eposta IS NOT NULL AND k.eposta <> '' AND k.eposta_dogrulandi = 1
    ");
    $st->execute([$site_id]);
    $alicilar = $st->fetchAll(PDO::FETCH_COLUMN);

    $site_adi = $bakimlar[0]['site_adi'];

    if (!$alicilar) {
        echo "· {$site_adi}: doğrulanmış e-postası olan yönetici yok, atlandı ("
           . count($bakimlar) . " bakım)\n";
        $atlanan += count($bakimlar);
        continue;
    }

    // ── İçerik ──────────────────────────────────────────────
    $satir_html = '';
    $gecikmis   = 0;
    foreach ($bakimlar as $b) {
        $kalan = (int)$b['kalan_gun'];
        if ($kalan < 0) $gecikmis++;
        $durum = $kalan < 0 ? abs($kalan) . ' gün GECİKTİ'
               : ($kalan === 0 ? 'BUGÜN' : $kalan . ' gün kaldı');
        $renk  = $kalan < 0 ? '#e74c3c' : ($kalan <= 7 ? '#f5a623' : '#666');

        $satir_html .= '<tr>'
            . '<td style="padding:8px;border-bottom:1px solid #eee">'
            . '<strong>' . e(turkce_buyuk($b['demirbas_adi'])) . '</strong><br>'
            . '<span style="color:#666;font-size:13px">' . e(etiket(BAKIM_TURLERI, $b['tur']))
            . ($b['baslik'] ? ' — ' . e(turkce_buyuk($b['baslik'])) : '') . '</span></td>'
            . '<td style="padding:8px;border-bottom:1px solid #eee;white-space:nowrap">'
            . e(date('d.m.Y', strtotime($b['planlanan_tarih']))) . '</td>'
            . '<td style="padding:8px;border-bottom:1px solid #eee;color:' . $renk . ';'
            . 'font-weight:700;white-space:nowrap">' . e($durum) . '</td>'
            . '</tr>';
    }

    $govde = '<p>Merhaba,</p>'
        . '<p><strong>' . e(turkce_buyuk($site_adi)) . '</strong> için '
        . count($bakimlar) . ' bakım kaydı yaklaşıyor'
        . ($gecikmis > 0 ? ' (<strong style="color:#e74c3c">' . $gecikmis
                         . ' tanesinin tarihi geçmiş</strong>)' : '') . ':</p>'
        . '<table style="width:100%;border-collapse:collapse;font-size:14px">'
        . '<tr><th align="left" style="padding:8px;border-bottom:2px solid #ddd">Demirbaş</th>'
        . '<th align="left" style="padding:8px;border-bottom:2px solid #ddd">Planlanan</th>'
        . '<th align="left" style="padding:8px;border-bottom:2px solid #ddd">Durum</th></tr>'
        . $satir_html . '</table>'
        . '<p style="margin-top:18px"><strong>Neden önemli?</strong> Asansör muayenesi, '
        . 'yangın güvenliği ekipmanı kontrolü ve paratoner ölçümü gibi periyodik kontroller '
        . 'yasal zorunluluktur; kaçırılması yönetim için sorumluluk doğurabilir.</p>';

    $html = eposta_sablonu(
        'Yaklaşan bakımlar',
        $govde,
        'Bakım Listesini Aç',
        rtrim(SITE_ADRESI, '/') . '/demirbaslar.php',
        'Bu ileti, yönetimini üstlendiğiniz ' . e(turkce_buyuk($site_adi))
            . ' için otomatik gönderilmiştir.'
    );

    foreach ($alicilar as $alici) {
        if ($deneme) {
            echo "· [DENEME] {$site_adi} → {$alici} (" . count($bakimlar) . " bakım)\n";
            continue;
        }
        if (eposta_gonder($alici, 'AYS — Yaklaşan bakımlar: ' . turkce_buyuk($site_adi),
                          $html, '', 'bakim_hatirlatma')) {
            echo "✓ {$site_adi} → {$alici}\n";
        } else {
            echo "✕ {$site_adi} → {$alici} (gönderilemedi)\n";
        }
    }

    // Bayrak yalnızca gerçekten gönderim yapıldığında set edilir;
    // deneme çalıştırması kayıtları tüketmemeli.
    if (!$deneme) {
        $idler = array_column($bakimlar, 'id');
        $yer   = implode(',', array_fill(0, count($idler), '?'));
        db()->prepare("UPDATE bakimlar SET hatirlatma_gonderildi = 1 WHERE id IN ($yer)")
            ->execute($idler);
        $gonderilen += count($bakimlar);
    }
}

echo "\n" . ($deneme ? 'Deneme tamamlandı.' : "İşaretlenen bakım: {$gonderilen}")
   . ($atlanan ? " · Alıcısı olmadığı için atlanan: {$atlanan}" : '') . "\n";
