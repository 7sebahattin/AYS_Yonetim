<?php
// ============================================================
//  print_bilanco.php — RESMİ BİLANÇO / YIL SONU RAPORU (A4 YAZDIRMA)
//
//  ⚠️ Bu rapor mali müşavir onaylı bir belge değildir; KMK m.33/m.39
//  hesap verme yükümlülüğünü hedefler. Resmi kullanım öncesi bir mali
//  müşavire teyit ettirilmesi önerilir — uyarı çıktının kendisinde de
//  görünür (imza alanının hemen üstünde).
//
//  print.php'deki genel yazdırma tiplerinden AYRI bir dosya: bilanço
//  çok bölümlü (özet + gider kırılımı + borç listesi + karşılaştırma)
//  ve tek bir $type switch'ine sığdırmak okunabilirliği bozardı.
// ============================================================
require_once 'config.php';
require_once 'includes/functions.php';
require_once 'includes/print_utils.php';

$kullanici = giris_kontrol();

if (!bilanco_semasi_hazir_mi()) {
    die('Bu rapor için 006 numaralı şema göçü uygulanmalıdır.');
}

$yil = (int)($_GET['yil'] ?? date('Y'));
if ($yil < 2000 || $yil > 2100) $yil = (int)date('Y');

$ozet = yillik_ozet((int)$kullanici['site_id'], $yil);
$sayfa_basligi = $yil . ' Yılı Bilanço / Kapanış Raporu';

echo print_header($sayfa_basligi, $kullanici);
echo print_controls();
?>
<div class="print-container">
  <?= print_page_header($kullanici, $sayfa_basligi) ?>

  <div class="print-section" style="background:#fff8e1;border:1px solid #f0d878;border-radius:4px;padding:8px 10px;margin-bottom:14px">
    <p style="font-size:7.5pt;color:#7a5c00;margin:0">
      ⚖️ <strong>Kapsam uyarısı:</strong> Bu rapor mali müşavir onaylı bir belge değildir;
      Kat Mülkiyeti Kanunu'nun hesap verme yükümlülüğünü (m.33, m.39) karşılamayı
      hedefler. Resmi kullanım öncesi bir mali müşavire teyit ettirilmesi önerilir.
    </p>
  </div>

  <div class="print-section">
    <h2 class="print-section-title" style="font-size:11pt;margin-bottom:10px;padding-bottom:4px">
      💼 <?= $yil ?> Yılı Özet
    </h2>
    <?= print_bilanco_ozet_table($ozet) ?>
  </div>

  <div class="print-section">
    <h2 class="print-section-title" style="font-size:11pt;margin-bottom:10px;padding-bottom:4px">
      📋 Kategori Bazlı Gider Dağılımı
    </h2>
    <?= print_bilanco_gider_ozet($ozet['gider_kirilimi'], $ozet['toplam_gider']) ?>
    <p style="font-size:8pt;color:#666;margin-top:6px">
      Önceki yıl (<?= $ozet['onceki_yil'] ?>) toplam gider: <strong><?= para($ozet['onceki_gider']) ?></strong>
      <?php if ($ozet['onceki_gider'] > 0):
        $fark = round((($ozet['toplam_gider'] - $ozet['onceki_gider']) / $ozet['onceki_gider']) * 100, 1); ?>
        (<?= $fark >= 0 ? '+' : '' ?><?= e(number_format($fark, 1, ',', '.')) ?>%)
      <?php endif; ?>
    </p>
  </div>

  <div class="print-section">
    <h2 class="print-section-title" style="font-size:11pt;margin-bottom:10px;padding-bottom:4px">
      📊 Tahsilat Durumu
    </h2>
    <p style="font-size:9pt">
      Tahsilat oranı: <strong><?= e(number_format($ozet['tahsilat_oran'], 1, ',', '.')) ?>%</strong> ·
      Toplam açık borç: <strong style="color:#e74c3c"><?= para($ozet['toplam_borc']) ?></strong>
    </p>
  </div>

  <div class="print-section">
    <h2 class="print-section-title" style="font-size:11pt;margin-bottom:10px;padding-bottom:4px">
      🏢 Daire Bazlı Borç Listesi
    </h2>
    <?= print_bilanco_borc_table($ozet['borclu_daireler'], $ozet['toplam_borc']) ?>
  </div>

  <?= print_footer(true) ?>
</div>
