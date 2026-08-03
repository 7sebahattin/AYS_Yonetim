<?php
// ============================================================
//  araclar/superadmin_ata.php — PLATFORM ROLÜ ATAMA (CLI)
//
//  Kullanım:
//    php araclar/superadmin_ata.php liste
//    php araclar/superadmin_ata.php <kullanici_adi> [rol]
//
//  rol: superadmin (varsayılan) | destek | kullanici
//
//  Sunucuya SSH erişimi olan kişi zaten config.php'yi ve veritabanını
//  okuyabilir; bu betik ona yeni bir yetki vermez, yalnızca elle SQL
//  yazmayı gereksiz kılar. Web üzerinden aynı iş için
//  /yonetim/kurulum.php vardır (yalnızca hiç süper admin yokken).
// ============================================================

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("Bu betik yalnızca komut satırından çalıştırılır.\n");
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform.php';

if (!platform_semasi_hazir_mi()) {
    fwrite(STDERR, "Platform şeması hazır değil. Önce göçleri uygulayın:\n"
                 . "  php araclar/goc_cli.php uygula\n");
    exit(1);
}

$komut = $argv[1] ?? '';

// ─── Listeleme ──────────────────────────────────────────────
if ($komut === '' || $komut === 'liste') {
    $satirlar = db()->query("
        SELECT id, kullanici_adi, eposta, platform_rolu, totp_aktif, son_giris
        FROM kullanicilar WHERE platform_rolu <> 'kullanici' ORDER BY id
    ")->fetchAll();

    if (!$satirlar) {
        echo "Platform yetkisi olan hesap yok.\n\n";
        echo "Atamak için:  php araclar/superadmin_ata.php <kullanici_adi>\n";
        exit(0);
    }

    printf("%-5s %-20s %-14s %-6s %s\n", 'ID', 'KULLANICI', 'ROL', '2FA', 'SON GİRİŞ');
    foreach ($satirlar as $s) {
        printf("%-5d %-20s %-14s %-6s %s\n",
            $s['id'], $s['kullanici_adi'], $s['platform_rolu'],
            $s['totp_aktif'] ? 'var' : 'YOK',
            $s['son_giris'] ?? '—');
    }
    echo "\n2FA'sı 'YOK' olan hesaplar panele giremez; /yonetim/iki_faktor.php ile kurulur.\n";
    exit(0);
}

// ─── Atama ──────────────────────────────────────────────────
$kullanici_adi = $komut;
$rol           = $argv[2] ?? 'superadmin';

if (!in_array($rol, PLATFORM_ROLLERI, true)) {
    fwrite(STDERR, "Geçersiz rol: $rol\nGeçerli roller: " . implode(', ', PLATFORM_ROLLERI) . "\n");
    exit(1);
}

$st = db()->prepare("SELECT id, kullanici_adi, platform_rolu FROM kullanicilar WHERE kullanici_adi = ?");
$st->execute([$kullanici_adi]);
$k = $st->fetch();

if (!$k) {
    fwrite(STDERR, "Böyle bir kullanıcı yok: $kullanici_adi\n");
    exit(1);
}

// Son süper adminin yetkisi alınırsa panele girecek kimse kalmaz ve
// rol atamak için de panel gerekir — sistem kendini kilitler.
if ($k['platform_rolu'] === 'superadmin' && $rol !== 'superadmin' && superadmin_sayisi() <= 1) {
    fwrite(STDERR, "Son süper adminin yetkisi kaldırılamaz. Önce başka bir süper admin atayın.\n");
    exit(1);
}

db()->prepare("UPDATE kullanicilar SET platform_rolu = ? WHERE id = ?")->execute([$rol, $k['id']]);
denetim_yaz('yonetim_platform_rolu_cli', 'kullanici', (int)$k['id'],
            ['eski' => $k['platform_rolu'], 'yeni' => $rol], (int)$k['id'], null);

echo "{$k['kullanici_adi']}: {$k['platform_rolu']} → {$rol}\n";

if ($rol !== 'kullanici') {
    $st = db()->prepare("SELECT totp_aktif FROM kullanicilar WHERE id = ?");
    $st->execute([$k['id']]);
    if (!$st->fetchColumn()) {
        echo "\nSıradaki adım: bu hesapla uygulamaya girip /yonetim/iki_faktor.php\n"
           . "adresinden iki faktörlü doğrulamayı kurun. 2FA olmadan panele giriş kabul edilmez.\n";
    }
}
