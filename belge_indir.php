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
//  İkinci kontrol kritik: dosya id'leri ardışıktır, dolayısıyla id
//  değiştirerek başka bir apartmanın faturasını/belgesini indirme
//  denemesi beklenmelidir. Sorgu site_id ile filtrelendiği için böyle
//  bir istek kayıt bulamaz ve 404 döner.
//
//  Dosya her zaman EK olarak indirilir (Content-Disposition:
//  attachment + nosniff): yüklenmiş bir HTML/SVG'nin tarayıcıda
//  çalıştırılıp oturum çalmasını engeller.
//
//  İKİ KAYNAK TABLOSU: Faz 4'ün 'ekler' tablosu (talep/bakım/demirbaş/
//  personel dosyaları) ve Faz 5'in 'belgeler' tablosu (karar defteri/
//  belge arşivi). `kaynak` parametresi hangisine bakılacağını seçer;
//  varsayılan 'ek' — mevcut bağlantılar (Faz 4) parametre olmadan
//  çalışmaya devam etsin diye.
// ============================================================
require_once 'config.php';
require_once 'includes/functions.php';
require_once 'includes/operasyon.php';

$kullanici = giris_kontrol();
$site_id   = (int)$kullanici['site_id'];
$id        = (int)($_GET['id'] ?? 0);
$kaynak    = ($_GET['kaynak'] ?? 'ek') === 'belge' ? 'belge' : 'ek';

if ($kaynak === 'belge') {
    if (!arsiv_semasi_hazir_mi()) {
        http_response_code(404);
        exit('Dosya bulunamadı.');
    }
    $st = db()->prepare("SELECT yol, orijinal_ad, mime FROM belgeler WHERE id = ? AND site_id = ?");
    $st->execute([$id, $site_id]);
    $tablo_adi = 'belge';
} else {
    if (!operasyon_semasi_hazir_mi()) {
        http_response_code(404);
        exit('Dosya bulunamadı.');
    }
    $st = db()->prepare("SELECT yol, orijinal_ad, mime FROM ekler WHERE id = ? AND site_id = ?");
    $st->execute([$id, $site_id]);
    $tablo_adi = 'ek';
}

$dosya = $st->fetch();

if (!$dosya) {
    // Yetkisiz erişim ile "gerçekten yok" ayrımı yapılmaz: aksi halde
    // hangi id'lerin var olduğu sayılabilirdi.
    denetim_yaz('belge_erisimi_reddedildi', $tablo_adi, $id);
    http_response_code(404);
    exit('Dosya bulunamadı.');
}

denetim_yaz('belge_indirildi', $tablo_adi, $id);

dosya_akit($dosya['yol'], $dosya['orijinal_ad'], $dosya['mime'] ?: 'application/octet-stream');
