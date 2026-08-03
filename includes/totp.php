<?php
// ============================================================
//  includes/totp.php — İKİ FAKTÖRLÜ DOĞRULAMA (RFC 6238 TOTP)
//
//  Google Authenticator / Authy / Microsoft Authenticator ile
//  uyumlu, bağımlılıksız bir uygulama. Proje Composer kullanmadığı
//  için tek dosyada tutuldu; algoritma küçük ve standart:
//    HMAC-SHA1(gizli, zaman_adimi) → dinamik kesme → 6 hane
//
//  QR KODU NEDEN YOK: QR üretmek ya harici bir servise (secret'ı
//  üçüncü tarafa göndermek demektir — kabul edilemez) ya da projeye
//  bir QR kütüphanesi eklemeye bağlı. Bunun yerine gizli anahtar
//  okunabilir bloklar halinde gösterilir; tüm doğrulayıcı uygulamalar
//  "anahtarı elle gir" seçeneğini destekler.
// ============================================================

const TOTP_ADIM     = 30;  // saniye — RFC 6238 varsayılanı
const TOTP_HANE     = 6;
const TOTP_TOLERANS = 1;   // ±1 adım: telefon saati birkaç saniye kayabilir

// ─── Base32 (RFC 4648) ──────────────────────────────────────
// Doğrulayıcı uygulamalar gizli anahtarı base32 bekler.
const TOTP_ALFABE = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

function totp_gizli_uret(int $bayt = 20): string {
    return totp_base32_kodla(random_bytes($bayt));
}

function totp_base32_kodla(string $ham): string {
    $bit = '';
    for ($i = 0, $n = strlen($ham); $i < $n; $i++) {
        $bit .= str_pad(decbin(ord($ham[$i])), 8, '0', STR_PAD_LEFT);
    }
    $cikti = '';
    foreach (str_split($bit, 5) as $parca) {
        $cikti .= TOTP_ALFABE[bindec(str_pad($parca, 5, '0', STR_PAD_RIGHT))];
    }
    return $cikti; // dolgu ('=') eklenmez; doğrulayıcılar dolgusuz kabul eder
}

function totp_base32_coz(string $gizli): string {
    $gizli = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $gizli));
    if ($gizli === '') return '';

    $bit = '';
    for ($i = 0, $n = strlen($gizli); $i < $n; $i++) {
        $deger = strpos(TOTP_ALFABE, $gizli[$i]);
        if ($deger === false) return '';
        $bit .= str_pad(decbin($deger), 5, '0', STR_PAD_LEFT);
    }

    $ham = '';
    foreach (str_split($bit, 8) as $parca) {
        if (strlen($parca) === 8) $ham .= chr(bindec($parca));
    }
    return $ham;
}

// ─── Kod üretimi ────────────────────────────────────────────
function totp_zaman_adimi(?int $zaman = null): int {
    return (int)floor(($zaman ?? time()) / TOTP_ADIM);
}

function totp_kod(string $gizli, int $adim): string {
    $anahtar = totp_base32_coz($gizli);
    if ($anahtar === '') return '';

    // Adım numarası 8 baytlık big-endian tamsayı olarak paketlenir
    $veri = pack('N*', 0, $adim);
    $hmac = hash_hmac('sha1', $veri, $anahtar, true);

    // Dinamik kesme (RFC 4226 §5.4)
    $ofset = ord($hmac[19]) & 0x0F;
    $sayi  = ((ord($hmac[$ofset])     & 0x7F) << 24)
           | ((ord($hmac[$ofset + 1]) & 0xFF) << 16)
           | ((ord($hmac[$ofset + 2]) & 0xFF) << 8)
           |  (ord($hmac[$ofset + 3]) & 0xFF);

    return str_pad((string)($sayi % (10 ** TOTP_HANE)), TOTP_HANE, '0', STR_PAD_LEFT);
}

// Kodu doğrular. Başarılıysa kullanılan zaman adımını, aksi halde
// null döner.
//
// Dönen adım ÇAĞIRAN TARAFÇA saklanmalıdır (kullanicilar.totp_son_adim):
// aynı kodun ikinci kez kabul edilmemesi buna bağlıdır. Karşılaştırma
// hash_equals ile yapılır — kod kısa olduğu için zamanlama sızıntısı
// pratikte küçük olsa da sabit zamanlı karşılaştırma bedava.
function totp_dogrula(string $gizli, string $kod, ?int $son_adim = null, ?int $zaman = null): ?int
{
    $kod = preg_replace('/\D/', '', $kod);
    if (strlen($kod) !== TOTP_HANE) return null;

    $simdiki = totp_zaman_adimi($zaman);
    for ($fark = -TOTP_TOLERANS; $fark <= TOTP_TOLERANS; $fark++) {
        $adim = $simdiki + $fark;
        if ($son_adim !== null && $adim <= $son_adim) continue; // tekrar kullanım
        $beklenen = totp_kod($gizli, $adim);
        if ($beklenen !== '' && hash_equals($beklenen, $kod)) return $adim;
    }
    return null;
}

// Doğrulayıcı uygulamaya elle/otomatik eklemek için otpauth:// adresi.
function totp_uri(string $gizli, string $hesap, string $yayinci = 'AYS'): string {
    return sprintf(
        'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
        rawurlencode($yayinci), rawurlencode($hesap),
        $gizli, rawurlencode($yayinci), TOTP_HANE, TOTP_ADIM
    );
}

// Gizli anahtarı elle girilebilir bloklara böler (XXXX XXXX …).
function totp_okunabilir(string $gizli): string {
    return trim(chunk_split($gizli, 4, ' '));
}

// ─── Yedek kodlar ───────────────────────────────────────────
// Telefon kaybolduğunda tek kullanımlık kurtarma. Kodun kendisi
// saklanmaz; şifre gibi hash'lenir.
function totp_yedek_kodlari_uret(int $kullanici_id, int $adet = 8): array
{
    $db = db();
    $db->prepare("DELETE FROM totp_yedek_kodlari WHERE kullanici_id = ?")->execute([$kullanici_id]);

    $ins = $db->prepare("INSERT INTO totp_yedek_kodlari (kullanici_id, kod_hash) VALUES (?, ?)");
    $kodlar = [];
    for ($i = 0; $i < $adet; $i++) {
        // 10 haneli, okunması kolay; harf yok → telefonda okurken karışmaz
        $kod = str_pad((string)random_int(0, 99999), 5, '0', STR_PAD_LEFT) . '-'
             . str_pad((string)random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        $ins->execute([$kullanici_id, password_hash($kod, PASSWORD_DEFAULT)]);
        $kodlar[] = $kod;
    }
    return $kodlar;
}

// Yedek kodu tüketmeye çalışır. Doğruysa kodu kullanılmış işaretler ve
// true döner. Kullanılmış kod ikinci kez kabul edilmez.
function totp_yedek_kod_tuket(int $kullanici_id, string $kod): bool
{
    $kod = trim($kod);
    if ($kod === '') return false;

    $st = db()->prepare("SELECT id, kod_hash FROM totp_yedek_kodlari
                         WHERE kullanici_id = ? AND kullanildi = 0");
    $st->execute([$kullanici_id]);

    foreach ($st->fetchAll() as $satir) {
        if (password_verify($kod, $satir['kod_hash'])) {
            db()->prepare("UPDATE totp_yedek_kodlari
                           SET kullanildi = 1, kullanim_zamani = NOW() WHERE id = ?")
                ->execute([$satir['id']]);
            return true;
        }
    }
    return false;
}

function totp_kalan_yedek_kod(int $kullanici_id): int
{
    $st = db()->prepare("SELECT COUNT(*) FROM totp_yedek_kodlari
                         WHERE kullanici_id = ? AND kullanildi = 0");
    $st->execute([$kullanici_id]);
    return (int)$st->fetchColumn();
}
