<?php
// ============================================================
//  includes/functions.php — ORTAK FONKSİYONLAR
// ============================================================

require_once __DIR__ . '/../config.php';
// varsayilanlar.php, config.php'de tanımlanmamış ayarlara güvenli
// varsayılan verir. config.php sunucuya deploy EDİLMEDİĞİ için, koda
// eklenen yeni bir ayar sabiti olmadan da sistem çalışmaya devam eder.
require_once __DIR__ . '/varsayilanlar.php';
require_once __DIR__ . '/denetim.php';
require_once __DIR__ . '/hiz_limiti.php';

// ─── Şema hazırlık kontrolü ─────────────────────────────────
// Dosyalar sunucuya kopyalandıktan sonra göç (migration) elle
// çalıştırılır. Bu iki adım arasında e-posta sütunu henüz yokken
// sistemin ölümcül hata vermesini önler: e-posta özellikleri sessizce
// devre dışı kalır, uygulamanın geri kalanı çalışmaya devam eder.
function eposta_semasi_hazir_mi(): bool {
    static $hazir = null;
    if ($hazir !== null) return $hazir;
    try {
        db()->query("SELECT eposta FROM kullanicilar LIMIT 0");
        $hazir = true;
    } catch (Throwable $ex) {
        $hazir = false;
    }
    return $hazir;
}

// ─── Güvenlik Header'ları ───────────────────────────────────
function guvenlik_headerlari(): void {
    if (headers_sent()) return;
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: frame-ancestors 'self'");
}

// ─── Oturum Başlat ──────────────────────────────────────────
function oturum_baslat(): void {
    guvenlik_headerlari();
    if (session_status() === PHP_SESSION_NONE) {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.cookie_secure', $https ? 1 : 0);
        session_start();
    }
}

// ─── "Beni Hatırla" — kalıcı oturum çerezi ──────────────────
// Selector/validator deseni: çerezde açıkça duran "seçici" ile DB'de
// satır bulunur, gizli "doğrulayıcı" ise hiçbir zaman düz metin
// saklanmaz — yalnızca hash'i tutulur ve hash_equals ile karşılaştırılır.
// Her kullanımda jeton rotasyona sokulur (eski satır silinir, yenisi
// yazılır) — çerez çalınsa bile tekrar kullanılabilirlik penceresi
// tek seferle sınırlı kalır.
const HATIRLAMA_COOKIE = 'ays_hatirla';
const HATIRLAMA_GUN    = 30;

function hatirlama_tablosunu_hazirla(): void {
    db()->exec("
        CREATE TABLE IF NOT EXISTS hatirlama_jetonlari (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            kullanici_id INT UNSIGNED NOT NULL,
            secici VARCHAR(24) NOT NULL,
            dogrulayici_hash VARCHAR(64) NOT NULL,
            son_kullanim DATETIME NOT NULL,
            olusturma DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_secici (secici),
            KEY fk_hatirlama_kullanici (kullanici_id),
            CONSTRAINT fk_hatirlama_kullanici FOREIGN KEY (kullanici_id)
                REFERENCES kullanicilar (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci
    ");
}

function hatirlama_cerez_ayarlari(int $gecerlilik_saniye): array {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    return [
        'expires'  => time() + $gecerlilik_saniye,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

// Girişte "Beni Hatırla" işaretliyse çağrılır: yeni jeton üretir, DB'ye
// hash'ini yazar, ham değeri (seçici:doğrulayıcı) çerez olarak ayarlar.
function hatirlama_jetonu_baslat(int $kullanici_id): void {
    hatirlama_tablosunu_hazirla();
    $secici       = bin2hex(random_bytes(9));
    $dogrulayici  = bin2hex(random_bytes(32));
    $son_kullanim = date('Y-m-d H:i:s', time() + HATIRLAMA_GUN * 86400);

    db()->prepare("INSERT INTO hatirlama_jetonlari (kullanici_id, secici, dogrulayici_hash, son_kullanim) VALUES (?,?,?,?)")
        ->execute([$kullanici_id, $secici, hash('sha256', $dogrulayici), $son_kullanim]);

    setcookie(HATIRLAMA_COOKIE, $secici . ':' . $dogrulayici, hatirlama_cerez_ayarlari(HATIRLAMA_GUN * 86400));
}

// Aktif oturum yoksa "Beni Hatırla" çerezinden sessizce oturum kurmayı
// dener. Başarılıysa $_SESSION doldurulur, jeton rotasyona sokulur ve
// true döner; aksi halde (çerez yok/geçersiz/süresi dolmuş) false döner.
function oturumu_hatirlama_ile_dene(): bool {
    if (!empty($_SESSION['kullanici_id'])) return true;
    if (empty($_COOKIE[HATIRLAMA_COOKIE]) || !str_contains($_COOKIE[HATIRLAMA_COOKIE], ':')) return false;

    [$secici, $dogrulayici] = explode(':', $_COOKIE[HATIRLAMA_COOKIE], 2);

    hatirlama_tablosunu_hazirla();
    $stmt = db()->prepare("
        SELECT hj.id, hj.kullanici_id, hj.dogrulayici_hash,
               k.kullanici_adi, k.apartman_adi, k.toplam_daire, k.tema
        FROM hatirlama_jetonlari hj
        JOIN kullanicilar k ON k.id = hj.kullanici_id
        WHERE hj.secici = ? AND hj.son_kullanim > NOW()
    ");
    $stmt->execute([$secici]);
    $jeton = $stmt->fetch();

    if (!$jeton || !hash_equals($jeton['dogrulayici_hash'], hash('sha256', $dogrulayici))) {
        hatirlama_cerezini_temizle(); // gecersiz/calinmis olabilecek cerezi temizle
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['kullanici_id']  = (int)$jeton['kullanici_id'];
    $_SESSION['kullanici_adi'] = $jeton['kullanici_adi'];
    $_SESSION['apartman_adi']  = $jeton['apartman_adi'];
    $_SESSION['toplam_daire']  = (int)$jeton['toplam_daire'];
    $_SESSION['tema']          = $jeton['tema'] ?? 'koyu';
    $_SESSION['son_islem']     = time();

    db()->prepare("DELETE FROM hatirlama_jetonlari WHERE id=?")->execute([$jeton['id']]);
    hatirlama_jetonu_baslat((int)$jeton['kullanici_id']);

    return true;
}

// Çıkışta ya da geçersiz/çalıntı jeton tespit edildiğinde çağrılır.
function hatirlama_cerezini_temizle(): void {
    if (!empty($_COOKIE[HATIRLAMA_COOKIE])) {
        $secici = explode(':', $_COOKIE[HATIRLAMA_COOKIE], 2)[0] ?? '';
        if ($secici !== '') {
            hatirlama_tablosunu_hazirla();
            db()->prepare("DELETE FROM hatirlama_jetonlari WHERE secici=?")->execute([$secici]);
        }
    }
    setcookie(HATIRLAMA_COOKIE, '', hatirlama_cerez_ayarlari(-3600));
    unset($_COOKIE[HATIRLAMA_COOKIE]);
}

// ─── Giriş Kontrolü ─────────────────────────────────────────
function giris_kontrol(): array {
    oturum_baslat();

    $suresi_doldu = !empty($_SESSION['kullanici_id'])
        && !empty($_SESSION['son_islem'])
        && (time() - $_SESSION['son_islem']) > SESSION_SURE;

    if (empty($_SESSION['kullanici_id']) || $suresi_doldu) {
        if ($suresi_doldu) {
            session_unset();
            session_destroy();
            // session_destroy() sonrası session "aktif" sayılmaz; oturumu_hatirlama_ile_dene()
            // içindeki session_regenerate_id() çalışabilsin diye taze bir oturum başlatılır.
            session_start();
        }
        if (!oturumu_hatirlama_ile_dene()) {
            header('Location: /login.php' . ($suresi_doldu ? '?mesaj=suresi_doldu' : ''));
            exit;
        }
    }

    $_SESSION['son_islem'] = time();
    return [
        'id'            => (int)$_SESSION['kullanici_id'],
        'kullanici_adi' => $_SESSION['kullanici_adi'],
        'apartman_adi'  => $_SESSION['apartman_adi'],
        'toplam_daire'  => (int)$_SESSION['toplam_daire'],
        'tema'          => $_SESSION['tema'] ?? 'koyu', // YENİ EKLENEN SATIR
    ];
}

// ─── Para Formatı ────────────────────────────────────────────
function para(float $tutar): string {
    return '₺' . number_format($tutar, 2, ',', '.');
}

// ─── Tarih Formatı ───────────────────────────────────────────
function tarih_format(string $tarih): string {
    return $tarih ? date('d.m.Y', strtotime($tarih)) : '—';
}

// ─── Dönem Adı (2025-01 → Ocak 2025) ────────────────────────
$AYLAR = ['','Ocak','Şubat','Mart','Nisan','Mayıs','Haziran',
           'Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'];

function donem_adi(string $donem): string {
    global $AYLAR;
    [$yil, $ay] = explode('-', $donem);
    return ($AYLAR[(int)$ay] ?? $ay) . ' ' . $yil;
}

// ─── XSS Güvenlik ────────────────────────────────────────────
function e(mixed $v): string {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

// ─── Türkçe Büyük Harf ──────────────────────────────────────
// PHP'nin mb_strtoupper()'ı ve JS'in toUpperCase()'i Türkçe nokta(sız)
// I kuralını (i→İ, ı→I) bilmez; önce bu iki harfi elle işaretleyip
// sonra mb_strtoupper çağırıyoruz.
function turkce_buyuk(string $s): string {
    $s = str_replace(['i', 'ı'], ['İ', 'I'], $s);
    return mb_strtoupper($s, 'UTF-8');
}

// Formdan gelen serbest metin alanlarını kaydetmeden önce normalize eder.
function buyuk(?string $s): string {
    return turkce_buyuk(trim((string)$s));
}

// Görüntülemede kullanılır: htmlspecialchars + Türkçe büyük harf.
// Bu özellikten önce küçük/karışık harfle kaydedilmiş eski verileri de
// büyük gösterir; veritabanındaki değeri fiilen değiştirmez.
function e_buyuk(mixed $v): string {
    return e(turkce_buyuk((string)($v ?? '')));
}

// ─── CSRF Token ──────────────────────────────────────────────
function csrf_token(): string {
    oturum_baslat();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_kontrol(): void {
    $beklenen = $_SESSION['csrf_token'] ?? '';
    if (empty($_POST['csrf_token']) || empty($beklenen) || !hash_equals($beklenen, $_POST['csrf_token'])) {
        http_response_code(403);
        die('Güvenlik hatası. Lütfen sayfayı yenileyin.');
    }
}

// ─── Dönem Listesi (son 12 ay) ───────────────────────────────
function donem_listesi(): array {
    $list = [];
    for ($i = 11; $i >= 0; $i--) {
        $list[] = date('Y-m', strtotime("-$i months"));
    }
    return $list;
}

// ─── Genişletilmiş Dönem Listesi (geçmiş 24 + gelecek 24 ay) ─
function donem_listesi_genisletilmis(): array {
    $list = [];
    for ($i = 23; $i >= -23; $i--) {
        $list[] = date('Y-m', strtotime("-$i months"));
    }
    return $list;
}

// ─── Bildirim Flash Mesajı ────────────────────────────────────
function flash(string $mesaj, string $tip = 'basari'): void {
    oturum_baslat();
    $_SESSION['flash'] = ['mesaj' => $mesaj, 'tip' => $tip];
}

function flash_goster(): string {
    oturum_baslat();
    if (empty($_SESSION['flash'])) return '';
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    $renk = $f['tip'] === 'basari' ? '#2ecc71' : ($f['tip'] === 'hata' ? '#e74c3c' : '#f39c12');
    $ikon = $f['tip'] === 'basari' ? '✓' : ($f['tip'] === 'hata' ? '✕' : '⚠');
    return sprintf(
        '<div class="flash flash-%s" style="background:%s20;border:1px solid %s40;color:%s">%s %s</div>',
        e($f['tip']), $renk, $renk, $renk, $ikon, e($f['mesaj'])
    );
}

// ─── Dashboard İstatistikleri ─────────────────────────────────
function istatistikler(int $kullanici_id, string $donem): array {
    $db = db();

    // Daire sayısı
    $stmt = $db->prepare("SELECT COUNT(*) FROM daireler WHERE kullanici_id = ?");
    $stmt->execute([$kullanici_id]);
    $toplam_daire = (int)$stmt->fetchColumn();

    // Bu dönem aidatlar
    $stmt = $db->prepare("
        SELECT
            SUM(CASE WHEN durum='odendi' THEN tutar ELSE 0 END) AS tahsilat,
            SUM(CASE WHEN durum='bekliyor' OR durum='gecikme' THEN tutar ELSE 0 END) AS bekleyen,
            COUNT(CASE WHEN durum='odendi' THEN 1 END) AS odenen_daire,
            COUNT(CASE WHEN durum<>'odendi' THEN 1 END) AS bekleyen_daire
        FROM aidatlar
        WHERE kullanici_id = ? AND donem = ?
    ");
    $stmt->execute([$kullanici_id, $donem]);
    $aidat = $stmt->fetch();

    // Bu dönem giderler
    $stmt = $db->prepare("SELECT COALESCE(SUM(tutar),0) FROM giderler WHERE kullanici_id = ? AND donem = ?");
    $stmt->execute([$kullanici_id, $donem]);
    $gider = (float)$stmt->fetchColumn();

    $tahsilat = (float)($aidat['tahsilat'] ?? 0);
    return [
        'toplam_daire'   => $toplam_daire,
        'tahsilat'       => $tahsilat,
        'gider'          => $gider,
        'bakiye'         => $tahsilat - $gider,
        'odenen_daire'   => (int)($aidat['odenen_daire'] ?? 0),
        'bekleyen_daire' => (int)($aidat['bekleyen_daire'] ?? 0),
        'tahsilat_oran'  => $toplam_daire > 0
            ? round(((int)($aidat['odenen_daire'] ?? 0) / $toplam_daire) * 100)
            : 0,
    ];
}

// ─── Gider Kategorileri (kullanıcıya özel öneri listesi) ──────
// Tablo yoksa kendiliğinden oluşturur (ayrı bir migration adımı
// gerektirmez). Var olan tabloda tekrar çağrılması ucuzdur.
function gider_kategorileri_tablosunu_hazirla(): void {
    db()->exec("
        CREATE TABLE IF NOT EXISTS gider_kategorileri (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            kullanici_id INT UNSIGNED NOT NULL,
            ad VARCHAR(50) NOT NULL,
            olusturma DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_kullanici_kategori (kullanici_id, ad),
            KEY fk_giderkat_kullanici (kullanici_id),
            CONSTRAINT fk_giderkat_kullanici FOREIGN KEY (kullanici_id)
                REFERENCES kullanicilar (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci
    ");
}

// Bir kullanıcı için gider kategori önerileri: varsayılan liste +
// kullanıcının daha önce eklediği özel kategoriler + geçmişte
// giderler.kategori'de fiilen kullanılmış değerler (tekilleştirilmiş).
function gider_kategori_onerileri(int $kullanici_id): array {
    $varsayilan = ['TEMİZLİK', 'ELEKTRİK', 'SU', 'DOĞALGAZ', 'ASANSÖR',
                   'BAHÇE', 'GÜVENLİK', 'TAMİRAT', 'YÖNETİM', 'SİGORTA', 'DİĞER'];

    $db = db();
    $ozel = $db->prepare("SELECT ad FROM gider_kategorileri WHERE kullanici_id=? ORDER BY ad");
    $ozel->execute([$kullanici_id]);
    $ozel = $ozel->fetchAll(PDO::FETCH_COLUMN);

    $gecmis = $db->prepare("SELECT DISTINCT kategori FROM giderler WHERE kullanici_id=? AND kategori<>''");
    $gecmis->execute([$kullanici_id]);
    $gecmis = $gecmis->fetchAll(PDO::FETCH_COLUMN);

    // Eski (bu özellikten önce) küçük/karışık harfle kaydedilmiş kategoriler
    // de büyük harfe çevrilip aynı listede tekilleştirilir.
    $tumu = [];
    foreach (array_merge($varsayilan, $ozel, $gecmis) as $ad) {
        $buyuk_ad = turkce_buyuk(trim((string)$ad));
        if ($buyuk_ad === '' || isset($tumu[$buyuk_ad])) continue;
        $tumu[$buyuk_ad] = $buyuk_ad;
    }
    $liste = array_values($tumu);
    natcasesort($liste);
    return array_values($liste);
}

// Yeni bir gider eklenirken kullanıcının özel kategori listesine kaydeder
// (varsayılan listedeki isimler için de zararsızdır — yalnızca kişisel
// öneri havuzunu büyütür, tekrar eklemede sessizce yok sayılır).
function gider_kategori_kaydet(int $kullanici_id, string $ad): void {
    $ad = trim($ad);
    if ($ad === '') return;
    db()->prepare("INSERT IGNORE INTO gider_kategorileri (kullanici_id, ad) VALUES (?, ?)")
        ->execute([$kullanici_id, $ad]);
}

// ─── Gelir/Gider Trendi (dönem aralığı, en yeniden en eskiye) ─
function trend_verisi(int $kullanici_id, string $baslangic, string $bitis): array {
    $db = db();

    // Dönem başına tahsilat toplamı — tek GROUP BY sorgusu
    $stmt = $db->prepare("
        SELECT donem, SUM(tutar) AS toplam
        FROM aidatlar
        WHERE kullanici_id = ? AND durum = 'odendi' AND donem BETWEEN ? AND ?
        GROUP BY donem
    ");
    $stmt->execute([$kullanici_id, $baslangic, $bitis]);
    $tahsilatlar = array_column($stmt->fetchAll(), 'toplam', 'donem');

    // Dönem başına gider toplamı — tek GROUP BY sorgusu
    $stmt = $db->prepare("
        SELECT donem, SUM(tutar) AS toplam
        FROM giderler
        WHERE kullanici_id = ? AND donem BETWEEN ? AND ?
        GROUP BY donem
    ");
    $stmt->execute([$kullanici_id, $baslangic, $bitis]);
    $giderler = array_column($stmt->fetchAll(), 'toplam', 'donem');

    $trend = [];
    $current = $bitis;
    while ($current >= $baslangic) {
        $tahsilat = (float)($tahsilatlar[$current] ?? 0);
        $gider    = (float)($giderler[$current] ?? 0);
        $trend[] = [
            'donem'    => $current,
            'ad'       => donem_adi($current),
            'tahsilat' => $tahsilat,
            'gider'    => $gider,
            'bakiye'   => $tahsilat - $gider,
        ];
        $current = date('Y-m', strtotime($current . '-01 -1 month'));
    }
    return $trend;
}
