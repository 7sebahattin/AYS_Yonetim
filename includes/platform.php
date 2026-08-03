<?php
// ============================================================
//  includes/platform.php — PLATFORM YÖNETİM KATMANI
//
//  Kiracı (site) düzeyinin ÜSTÜNDEKİ katman: platform rolleri,
//  bakım modu, sistem duyuruları, düzenlenebilir tanıtım içeriği
//  ve kimliğe bürünme (impersonation).
//
//  Site içi roller (yonetici/muhasebe/denetci) buradan bağımsızdır:
//  bir kullanıcı sitesinde yönetici olabilir ama platform rolü
//  'kullanici' kalır. İkisi karıştırılırsa her yönetici tüm
//  apartmanların verisine erişir hale gelirdi.
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/varsayilanlar.php';
require_once __DIR__ . '/denetim.php';

const PLATFORM_ROLLERI = ['kullanici', 'destek', 'superadmin'];

// ─── Şema hazır mı? ─────────────────────────────────────────
// Göç 004 uygulanmadan da sistem çalışmalı: panel erişilemez olur,
// uygulamanın geri kalanı normal çalışır.
function platform_semasi_hazir_mi(): bool
{
    static $hazir = null;
    if ($hazir !== null) return $hazir;
    try {
        db()->query("SELECT anahtar FROM platform_ayarlari LIMIT 0");
        db()->query("SELECT platform_rolu FROM kullanicilar LIMIT 0");
        $hazir = true;
    } catch (Throwable $ex) {
        $hazir = false;
    }
    return $hazir;
}

// ─── Platform rolü ──────────────────────────────────────────
function platform_rolu(int $kullanici_id): string
{
    if (!platform_semasi_hazir_mi()) return 'kullanici';
    $st = db()->prepare("SELECT platform_rolu FROM kullanicilar WHERE id = ?");
    $st->execute([$kullanici_id]);
    $rol = $st->fetchColumn();
    return in_array($rol, PLATFORM_ROLLERI, true) ? $rol : 'kullanici';
}

function platform_yetkili_mi(string $rol): bool
{
    return $rol === 'superadmin' || $rol === 'destek';
}

// 'destek' rolü salt-okunur; yazma işlemleri yalnızca 'superadmin'.
function platform_yazabilir_mi(string $rol): bool
{
    return $rol === 'superadmin';
}

function superadmin_sayisi(): int
{
    if (!platform_semasi_hazir_mi()) return 0;
    return (int)db()->query("SELECT COUNT(*) FROM kullanicilar WHERE platform_rolu = 'superadmin'")
                    ->fetchColumn();
}

// ─── Platform ayarları ──────────────────────────────────────
// Ayarlar config.php'de tutulamaz; config.php deploy edilmediği için
// panelden değiştirilebilir bir ayarın veritabanında olması gerekir.
function platform_ayarlari(bool $tazele = false): array
{
    static $onbellek = null;
    if ($tazele) $onbellek = null;
    if ($onbellek !== null) return $onbellek;
    if (!platform_semasi_hazir_mi()) return $onbellek = [];
    try {
        $onbellek = db()->query("SELECT anahtar, deger FROM platform_ayarlari")
                        ->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Throwable $ex) {
        $onbellek = [];
    }
    return $onbellek;
}

function platform_ayari(string $anahtar, ?string $varsayilan = null): ?string
{
    $t = platform_ayarlari();
    return array_key_exists($anahtar, $t) && $t[$anahtar] !== null ? $t[$anahtar] : $varsayilan;
}

function platform_ayari_yaz(string $anahtar, string $deger, ?int $guncelleyen = null): void
{
    if (!platform_semasi_hazir_mi()) return;
    db()->prepare("
        INSERT INTO platform_ayarlari (anahtar, deger, guncelleyen) VALUES (?,?,?)
        ON DUPLICATE KEY UPDATE deger = VALUES(deger), guncelleyen = VALUES(guncelleyen)
    ")->execute([$anahtar, $deger, $guncelleyen]);

    // Statik önbelleği tazele — aynı istekte okunursa eski değer dönmesin.
    platform_ayarlari(true);
}

// ─── Bakım modu ─────────────────────────────────────────────
// Açıkken normal kullanıcı panele giremez; süper admin ve destek
// erişmeye devam eder (aksi halde bakım modunu kapatacak kişi de
// dışarıda kalırdı).
function bakim_modu_aktif_mi(): bool
{
    return platform_ayari('bakim_modu', '0') === '1';
}

function bakim_mesaji(): string
{
    return (string)platform_ayari(
        'bakim_mesaji',
        'Sistem kısa süreli bakımda. Kısa süre içinde tekrar hizmetinizdeyiz.'
    );
}

function bakim_sayfasi_goster(): void
{
    http_response_code(503);
    header('Retry-After: 1800');
    $mesaj = e(bakim_mesaji());
    echo <<<HTML
<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Bakım Çalışması — AYS</title>
<style>
 body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;
      background:#0d0d1a;color:#e8e8f0;font-family:system-ui,-apple-system,sans-serif;padding:24px}
 .kutu{max-width:460px;text-align:center}
 .ikon{font-size:56px;margin-bottom:18px}
 h1{font-size:22px;margin:0 0 12px}
 p{color:#9a9ab0;line-height:1.6;margin:0 0 22px}
 a{color:#e94560;text-decoration:none;font-size:13px}
</style></head><body>
<div class="kutu">
  <div class="ikon">🔧</div>
  <h1>Bakım çalışması sürüyor</h1>
  <p>{$mesaj}</p>
  <a href="/yonetim/giris.php">Yönetim girişi</a>
</div></body></html>
HTML;
    exit;
}

// ─── Sistem duyuruları ──────────────────────────────────────
// Panelde bant olarak gösterilir. site_id NULL → tüm siteler.
function aktif_duyurular(?int $site_id = null): array
{
    if (!platform_semasi_hazir_mi()) return [];
    try {
        $st = db()->prepare("
            SELECT id, baslik, mesaj, tip FROM duyurular
            WHERE durum = 'aktif'
              AND (baslangic IS NULL OR baslangic <= NOW())
              AND (bitis     IS NULL OR bitis     >= NOW())
              AND (site_id   IS NULL OR site_id   = ?)
            ORDER BY FIELD(tip,'bakim','uyari','bilgi'), id DESC
        ");
        $st->execute([$site_id]);
        return $st->fetchAll();
    } catch (Throwable $ex) {
        return [];
    }
}

// ─── Düzenlenebilir tanıtım içeriği ─────────────────────────
// Kayıt yoksa koddaki varsayılana düşülür: tablo boş olsa da tanıtım
// sayfası eksiksiz görünür, göç uygulanmadan da çalışır.
function icerik_blogu(string $anahtar, array $varsayilan = []): array
{
    static $tumu = null;
    if ($tumu === null) {
        $tumu = [];
        if (platform_semasi_hazir_mi()) {
            try {
                foreach (db()->query("SELECT anahtar, baslik, govde FROM icerik_bloklari") as $s) {
                    $tumu[$s['anahtar']] = $s;
                }
            } catch (Throwable $ex) {
                $tumu = [];
            }
        }
    }
    $kayit = $tumu[$anahtar] ?? [];
    return [
        'baslik' => ($kayit['baslik'] ?? '') !== '' ? $kayit['baslik'] : ($varsayilan['baslik'] ?? ''),
        'govde'  => ($kayit['govde']  ?? '') !== '' ? $kayit['govde']  : ($varsayilan['govde']  ?? ''),
    ];
}

// SSS kayıtları; tablo boş/eksikse verilen varsayılan liste kullanılır.
function sss_listesi(array $varsayilan = []): array
{
    if (!platform_semasi_hazir_mi()) return $varsayilan;
    try {
        $satirlar = db()->query("SELECT soru, cevap FROM sss_kayitlari
                                 WHERE durum = 'aktif' ORDER BY sira, id")->fetchAll();
    } catch (Throwable $ex) {
        return $varsayilan;
    }
    if (!$satirlar) return $varsayilan;
    return array_map(fn($r) => ['s' => $r['soru'], 'c' => $r['cevap']], $satirlar);
}

// ─── Kimliğe bürünme (impersonation) ────────────────────────
//
//  Destek için gerekli ama sistemin en hassas yetkisi: bir süper admin
//  herhangi bir kullanıcının ekranını görebilir. Bu yüzden:
//   1. VARSAYILAN SALT-OKUNUR — yazma ayrı bir platform ayarıyla açılır
//   2. Başlangıç ve bitiş denetim kaydına yazılır
//   3. Panelde ve kullanıcı arayüzünde kalıcı bir uyarı bandı görünür
//   4. Süper admin oturumu ($_SESSION['yonetim_id']) korunur; bürünme
//      bitince aynı oturumdan panele dönülür
//
//  $_SESSION['yonetim_id'] ile $_SESSION['kullanici_id'] bilinçli olarak
//  AYRI anahtarlardır: bürünme sırasında uygulama tarafı hedef kullanıcıyı
//  görür, panel tarafı gerçek yöneticiyi.
function kimlige_burunuluyor_mu(): bool
{
    return !empty($_SESSION['kimlik_burunme']['hedef']);
}

function burunme_yazabilir_mi(): bool
{
    return kimlige_burunuluyor_mu() && !empty($_SESSION['kimlik_burunme']['yazma']);
}

function burunme_bilgisi(): array
{
    return $_SESSION['kimlik_burunme'] ?? [];
}
