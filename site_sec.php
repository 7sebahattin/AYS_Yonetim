<?php
// ============================================================
//  site_sec.php — AKTİF SİTE DEĞİŞTİRME
//
//  Birden fazla apartman/site yöneten kullanıcılar için. Seçim
//  oturumda saklanır; asıl yetki doğrulaması giris_kontrol() →
//  aktif_site_belirle() içinde yapılır, dolayısıyla burada geçersiz
//  bir id gönderilse bile karşı tarafta reddedilir.
// ============================================================
require_once 'config.php';
require_once 'includes/functions.php';

$kullanici = giris_kontrol();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dashboard.php');
    exit;
}
csrf_kontrol();

$istenen = (int)($_POST['site_id'] ?? 0);

// Yetki doğrulaması burada da yapılır (savunma katmanı): kullanıcı
// yalnızca kendi yetkili olduğu bir siteye geçebilir.
$st = db()->prepare("
    SELECT s.id FROM kullanici_site_yetkileri y
    JOIN siteler s ON s.id = y.site_id
    WHERE y.kullanici_id = ? AND y.site_id = ? AND y.durum = 'aktif' AND s.durum = 'aktif'
");
$st->execute([$kullanici['id'], $istenen]);

if ($st->fetch()) {
    $_SESSION['aktif_site_id'] = $istenen;
    denetim_yaz('site_degistirildi', 'site', $istenen);
} else {
    denetim_yaz('site_degistirme_reddedildi', 'site', $istenen);
    flash('Bu siteye erişim yetkiniz yok.', 'hata');
}

// Geldiği sayfaya dön (açık yönlendirme olmaması için yalnızca
// site içi göreli yollar kabul edilir).
$geri = $_POST['geri'] ?? '/dashboard.php';
if (!preg_match('#^/[a-z0-9_]+\.php#i', $geri)) $geri = '/dashboard.php';
header('Location: ' . $geri);
exit;
