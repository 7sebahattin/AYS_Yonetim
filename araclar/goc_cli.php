<?php
// ============================================================
//  araclar/goc_cli.php — ŞEMA GÖÇÜ (KOMUT SATIRI)
//
//  Kullanım:
//      php araclar/goc_cli.php durum     → bekleyen göçleri listeler
//      php araclar/goc_cli.php uygula    → bekleyen göçleri uygular
//
//  ⚠ UYGULAMADAN ÖNCE VERİTABANI YEDEĞİ ALIN.
//     MySQL/MariaDB'de DDL işlemleri geri alınamaz; yarıda kalan bir
//     göç elle temizlenmek zorunda kalınabilir.
// ============================================================

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Bu betik yalnızca komut satırından çalıştırılabilir.\n");
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/goc.php';

$komut = $argv[1] ?? 'durum';

if ($komut === 'durum') {
    $bekleyen  = bekleyen_gocler();
    $uygulanan = uygulanmis_gocler();

    echo "Uygulanmış göçler (" . count($uygulanan) . "):\n";
    foreach ($uygulanan as $g) echo "  ✓ $g\n";
    if (!$uygulanan) echo "  (yok)\n";

    echo "\nBekleyen göçler (" . count($bekleyen) . "):\n";
    foreach ($bekleyen as $g) echo "  • $g\n";
    if (!$bekleyen) echo "  (yok — şema güncel)\n";
    exit(0);
}

if ($komut === 'uygula') {
    $bekleyen = bekleyen_gocler();
    if (!$bekleyen) {
        echo "Bekleyen göç yok — şema zaten güncel.\n";
        exit(0);
    }

    echo "Uygulanacak göçler:\n";
    foreach ($bekleyen as $g) echo "  • $g\n";
    echo "\nYedek aldığınızdan emin olun. Devam edilsin mi? [e/H]: ";
    $yanit = trim((string)fgets(STDIN));
    if (mb_strtolower($yanit, 'UTF-8') !== 'e') {
        echo "İptal edildi.\n";
        exit(1);
    }

    $hatali = false;
    foreach (tum_gocleri_uygula() as $sonuc) {
        echo ($sonuc['basarili'] ? '  ✓ ' : '  ✗ ') . $sonuc['mesaj'] . "\n";
        if (!$sonuc['basarili']) $hatali = true;
    }
    echo $hatali ? "\nGöç YARIDA KALDI — lütfen hatayı inceleyin.\n" : "\nTüm göçler uygulandı.\n";
    exit($hatali ? 1 : 0);
}

echo "Bilinmeyen komut: $komut\nKullanım: php araclar/goc_cli.php [durum|uygula]\n";
exit(1);
