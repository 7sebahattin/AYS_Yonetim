<?php
// ============================================================
//  belge_indir.php — DOSYA İNDİRME BEKÇİSİ
//
//  Yüklenen dosyalar web kökünün DIŞINDA saklanır (includes/dosya.php).
//  Bu betik onlara ulaşmanın TEK yoludur ve her istekte iki şeyi
//  doğrular:
//    1. Oturum açık mı?
//    2. Dosya, kullanıcının AKTİF SİTESİNE mi ait?
//
//  İkinci kontrol kritik: ek id'leri ardışıktır, dolayısıyla id
//  değiştirerek başka bir apartmanın faturasını indirme denemesi
//  beklenmelidir. Sorgu site_id ile filtrelendiği için böyle bir
//  istek kayıt bulamaz ve 404 döner.
//
//  Dosya her zaman EK olarak indirilir (Content-Disposition:
//  attachment + nosniff): yüklenmiş bir HTML/SVG'nin tarayıcıda
//  çalıştırılıp oturum çalmasını engeller.
// ============================================================
require_once 'config.php';
require_once 'includes/functions.php';
require_once 'includes/operasyon.php';

$kullanici = giris_kontrol();

if (!operasyon_semasi_hazir_mi()) {
    http_response_code(404);
    exit('Dosya bulunamadı.');
}

$ek_id = (int)($_GET['id'] ?? 0);

$st = db()->prepare("SELECT yol, orijinal_ad, mime FROM ekler WHERE id = ? AND site_id = ?");
$st->execute([$ek_id, (int)$kullanici['site_id']]);
$ek = $st->fetch();

if (!$ek) {
    // Yetkisiz erişim ile "gerçekten yok" ayrımı yapılmaz: aksi halde
    // hangi id'lerin var olduğu sayılabilirdi.
    denetim_yaz('belge_erisimi_reddedildi', 'ek', $ek_id);
    http_response_code(404);
    exit('Dosya bulunamadı.');
}

denetim_yaz('belge_indirildi', 'ek', $ek_id);

dosya_akit($ek['yol'], $ek['orijinal_ad'], $ek['mime'] ?: 'application/octet-stream');
