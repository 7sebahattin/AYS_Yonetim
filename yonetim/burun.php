<?php
// ============================================================
//  yonetim/burun.php — KULLANICI ADINA GÖRÜNTÜLEME
//
//  Sistemdeki en hassas yetki: bir süper admin, herhangi bir
//  yöneticinin ekranını olduğu gibi görebilir. Destek için gerekli
//  (kullanıcı "bende şöyle görünüyor" dediğinde tek güvenilir yol),
//  ama kötüye kullanımı tüm kiracıların verisini açar.
//
//  Bu yüzden dört kural pazarlık dışıdır:
//   1. VARSAYILAN SALT-OKUNUR. Yazma, ayrı bir platform ayarıyla
//      (burunme_yazma_izni) açılır; kontrol giris_kontrol() içindeki
//      tek bir noktadadır, sayfa sayfa hatırlanması gerekmez.
//   2. Başlangıç ve bitiş denetim kaydına yazılır; arada yapılan her
//      işlem de bürünen yöneticinin id'siyle etiketlenir.
//   3. Bir süper admin başka bir platform yetkilisinin adına
//      bürünemez — yetki yükseltme zinciri kapatılır.
//   4. Uygulama arayüzünde kapatılamayan bir uyarı bandı görünür.
//
//  Oturum: $_SESSION['yonetim_id'] bürünme boyunca DEĞİŞMEZ, yalnızca
//  $_SESSION['kullanici_id'] hedefe döner. Böylece bitirme yetkisi
//  hedef kullanıcıya değil, gerçek yöneticide kalır.
// ============================================================

require_once __DIR__ . '/ortak.php';

$islem = $_POST['islem'] ?? $_GET['islem'] ?? '';

// ─── Bitirme ────────────────────────────────────────────────
// GET ile de kabul edilir: yetki DÜŞÜREN bir işlem olduğu için
// CSRF ile zorla tetiklenmesi saldırganın işine yaramaz; buna karşılık
// salt-okunur uyarı ekranından tek tıkla çıkabilmek önemlidir.
if ($islem === 'bitir') {
    if (kimlige_burunuluyor_mu()) {
        $burunme = $_SESSION['kimlik_burunme'];
        $hedef   = (int)($burunme['hedef'] ?? 0);
        $sure    = time() - (int)($burunme['baslangic'] ?? time());
        unset($_SESSION['kimlik_burunme']);

        // Uygulama oturumu, bürünmeden ÖNCEKİ haline döndürülür.
        // Yönetici bürünmeye kendi uygulama oturumundan girmiş
        // olabilir; onu kaybetmemek için anahtarlar saklanmıştı.
        foreach (['kullanici_id','kullanici_adi','apartman_adi','toplam_daire','tema','aktif_site_id'] as $anahtar) {
            if (array_key_exists($anahtar, $burunme['onceki'] ?? [])) {
                $_SESSION[$anahtar] = $burunme['onceki'][$anahtar];
            } else {
                unset($_SESSION[$anahtar]);
            }
        }

        denetim_yaz('yonetim_burunme_bitti', 'kullanici', $hedef,
                    ['saniye' => $sure], (int)($_SESSION['yonetim_id'] ?? 0) ?: null, null);
    }
    header('Location: /yonetim/');
    exit;
}

// ─── Başlatma ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $islem !== 'basla') {
    header('Location: /yonetim/');
    exit;
}

$yonetici = yonetim_yazma_kontrol();   // CSRF + süper admin şartı

$hedef_id = (int)($_POST['kullanici_id'] ?? 0);
$site_id  = (int)($_POST['site_id'] ?? 0);

$st = db()->prepare("SELECT id, kullanici_adi, apartman_adi, toplam_daire, tema, platform_rolu
                     FROM kullanicilar WHERE id = ?");
$st->execute([$hedef_id]);
$hedef = $st->fetch();

if (!$hedef) {
    flash('Kullanıcı bulunamadı.', 'hata');
    header('Location: /yonetim/');
    exit;
}

// Kural 3: platform yetkilisinin adına bürünülemez. Aksi halde bir
// destek hesabı, bir süper adminin adına bürünüp kendi rolünü
// yükseltebilirdi.
if (platform_yetkili_mi($hedef['platform_rolu'])) {
    yonetim_denetim('burunme_reddedildi', 'kullanici', $hedef_id, ['sebep' => 'platform_yetkilisi']);
    flash('Platform yetkisi olan bir hesabın adına görüntüleme yapılamaz.', 'hata');
    header('Location: /yonetim/');
    exit;
}

// Kendi adına bürünmek anlamsız; oturumu bozar.
if ((int)$hedef['id'] === (int)$yonetici['id']) {
    flash('Kendi hesabınıza bürünemezsiniz.', 'hata');
    header('Location: /yonetim/');
    exit;
}

// Yazma izni platform ayarından gelir ve VARSAYILAN KAPALIDIR.
$yazma = platform_ayari('burunme_yazma_izni', '0') === '1';

// Yöneticinin kendi uygulama oturumu (varsa) saklanır; bürünme
// bitince geri yüklenir, aksi halde kendi hesabından da düşerdi.
$onceki = [];
foreach (['kullanici_id','kullanici_adi','apartman_adi','toplam_daire','tema','aktif_site_id'] as $anahtar) {
    if (isset($_SESSION[$anahtar])) $onceki[$anahtar] = $_SESSION[$anahtar];
}

$_SESSION['kimlik_burunme'] = [
    'hedef'      => (int)$hedef['id'],
    'hedef_ad'   => $hedef['kullanici_adi'],
    'yazma'      => $yazma,
    'baslangic'  => time(),
    'onceki'     => $onceki,
];

// Uygulama tarafı artık hedef kullanıcıyı görür.
$_SESSION['kullanici_id']  = (int)$hedef['id'];
$_SESSION['kullanici_adi'] = $hedef['kullanici_adi'];
$_SESSION['apartman_adi']  = $hedef['apartman_adi'];
$_SESSION['toplam_daire']  = (int)$hedef['toplam_daire'];
$_SESSION['tema']          = $hedef['tema'] ?? 'koyu';
$_SESSION['son_islem']     = time();
unset($_SESSION['aktif_site_id']);

// İstenen site, hedefin yetkili olduğu bir siteyse başlangıç olarak
// seçilir; değilse aktif_site_belirle() zaten reddedip varsayılana düşer.
if ($site_id > 0) $_SESSION['aktif_site_id'] = $site_id;

denetim_yaz('yonetim_burunme_basladi', 'kullanici', (int)$hedef['id'],
            ['site_id' => $site_id, 'yazma' => $yazma],
            (int)$yonetici['id'], $site_id ?: null);

header('Location: /dashboard.php');
exit;
