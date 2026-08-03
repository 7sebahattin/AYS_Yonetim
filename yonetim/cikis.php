<?php
// ============================================================
//  yonetim/cikis.php — YÖNETİM OTURUMUNU KAPAT
//
//  Yalnızca panel oturumunu sonlandırır; kullanıcının normal
//  uygulama oturumu (varsa) korunur. Bürünme sürüyorsa önce o
//  bitirilir, aksi halde panel yetkisi düştükten sonra hedef
//  kullanıcının oturumunda kilitli kalınırdı.
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform.php';

oturum_baslat();

if (kimlige_burunuluyor_mu()) {
    $burunme = $_SESSION['kimlik_burunme'];
    unset($_SESSION['kimlik_burunme']);

    foreach (['kullanici_id','kullanici_adi','apartman_adi','toplam_daire','tema','aktif_site_id'] as $anahtar) {
        if (array_key_exists($anahtar, $burunme['onceki'] ?? [])) {
            $_SESSION[$anahtar] = $burunme['onceki'][$anahtar];
        } else {
            unset($_SESSION[$anahtar]);
        }
    }

    denetim_yaz('yonetim_burunme_bitti', 'kullanici', (int)($burunme['hedef'] ?? 0),
                ['sebep' => 'yonetim_cikisi'],
                (int)($_SESSION['yonetim_id'] ?? 0) ?: null, null);
}

if (!empty($_SESSION['yonetim_id'])) {
    denetim_yaz('yonetim_cikis', 'platform', null, [], (int)$_SESSION['yonetim_id'], null);
    unset($_SESSION['yonetim_id'], $_SESSION['yonetim_baslangic']);
}

// Oturum kimliği yenilenir: panel yetkisi düştükten sonra aynı
// oturum kimliğinin dolaşımda kalmaması için.
session_regenerate_id(true);

header('Location: /yonetim/giris.php?mesaj=cikis');
exit;
