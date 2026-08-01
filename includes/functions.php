<?php
// ============================================================
//  includes/functions.php — ORTAK FONKSİYONLAR
// ============================================================

require_once __DIR__ . '/../config.php';

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

// ─── Giriş Kontrolü ─────────────────────────────────────────
function giris_kontrol(): array {
    oturum_baslat();
    if (empty($_SESSION['kullanici_id'])) {
        header('Location: /index.php');
        exit;
    }
    // Oturum süresi kontrolü
    if (!empty($_SESSION['son_islem']) && (time() - $_SESSION['son_islem']) > SESSION_SURE) {
        session_destroy();
        header('Location: /index.php?mesaj=suresi_doldu');
        exit;
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
