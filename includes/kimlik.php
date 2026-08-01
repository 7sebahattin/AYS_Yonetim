<?php
// ============================================================
//  includes/kimlik.php — ŞİFRE SIFIRLAMA & E-POSTA DOĞRULAMA
//
//  Jeton deseni, mevcut hatirlama_jetonlari ile AYNI (selector/validator):
//  bağlantıda açıkça duran "seçici" ile satır bulunur; gizli
//  "doğrulayıcı" veritabanına asla düz metin yazılmaz, yalnızca
//  SHA-256 hash'i saklanır ve hash_equals ile karşılaştırılır.
//
//  Bu sayede veritabanı sızsa bile jetonlar kullanılamaz, ve seçici
//  üzerinden indeksli arama yapıldığı için doğrulama zamanlama
//  saldırısına kapalı kalır.
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/varsayilanlar.php';
require_once __DIR__ . '/eposta.php';
require_once __DIR__ . '/denetim.php';

// Yeni jeton üretir, DB'ye yazar ve bağlantıya konacak ham değeri döndürür.
function kimlik_jetonu_uret(int $kullanici_id, string $tur, int $omur_saniye): string {
    $secici      = bin2hex(random_bytes(9));
    $dogrulayici = bin2hex(random_bytes(32));
    $son         = date('Y-m-d H:i:s', time() + $omur_saniye);

    db()->prepare("
        INSERT INTO kimlik_jetonlari (kullanici_id, tur, secici, dogrulayici_hash, son_kullanim, ip_adresi)
        VALUES (?,?,?,?,?,?)
    ")->execute([$kullanici_id, $tur, $secici, hash('sha256', $dogrulayici), $son, istemci_ip()]);

    return $secici . ':' . $dogrulayici;
}

// Ham jetonu doğrular. Geçerliyse kullanıcı satırını döndürür, aksi halde null.
// Jetonu TÜKETMEZ — tüketim için kimlik_jetonu_tuket() çağrılmalı.
function kimlik_jetonu_dogrula(string $ham_jeton, string $tur): ?array {
    if (!str_contains($ham_jeton, ':')) return null;
    [$secici, $dogrulayici] = explode(':', $ham_jeton, 2);
    if ($secici === '' || $dogrulayici === '') return null;

    $stmt = db()->prepare("
        SELECT j.id AS jeton_id, j.dogrulayici_hash, k.*
        FROM kimlik_jetonlari j
        JOIN kullanicilar k ON k.id = j.kullanici_id
        WHERE j.secici = ? AND j.tur = ? AND j.kullanildi = 0 AND j.son_kullanim > NOW()
    ");
    $stmt->execute([$secici, $tur]);
    $kayit = $stmt->fetch();
    if (!$kayit) return null;

    if (!hash_equals($kayit['dogrulayici_hash'], hash('sha256', $dogrulayici))) return null;
    return $kayit;
}

// Jetonu tek kullanımlık hale getirir ve aynı kullanıcının aynı türdeki
// diğer bekleyen jetonlarını da iptal eder.
function kimlik_jetonu_tuket(int $jeton_id, int $kullanici_id, string $tur): void {
    $db = db();
    $db->prepare("UPDATE kimlik_jetonlari SET kullanildi = 1 WHERE id = ?")->execute([$jeton_id]);
    $db->prepare("UPDATE kimlik_jetonlari SET kullanildi = 1
                  WHERE kullanici_id = ? AND tur = ? AND kullanildi = 0")
       ->execute([$kullanici_id, $tur]);
}

// ─── E-posta doğrulama ──────────────────────────────────────
function eposta_dogrulama_gonder(int $kullanici_id, string $eposta): bool {
    $ham  = kimlik_jetonu_uret($kullanici_id, 'eposta_dogrulama', EPOSTA_DOGRULAMA_OMRU);
    $link = rtrim(SITE_ADRESI, '/') . '/eposta_dogrula.php?jeton=' . urlencode($ham);
    $saat = (int)round(EPOSTA_DOGRULAMA_OMRU / 3600);

    $html = eposta_sablonu(
        'E-posta adresinizi doğrulayın',
        '<p>Merhaba,</p><p>AYS hesabınıza bu e-posta adresini eklediniz. Adresin size ait '
        . 'olduğunu doğrulamak için aşağıdaki düğmeye tıklayın.</p>'
        . '<p><strong>Bu doğrulama neden gerekli?</strong> Şifrenizi unuttuğunuzda hesabınızı '
        . 'yalnızca doğrulanmış bir adrese kurtarma bağlantısı göndeririz.</p>',
        'E-postamı Doğrula',
        $link,
        "Bağlantı {$saat} saat geçerlidir. Bu isteği siz yapmadıysanız bu iletiyi yok sayabilirsiniz."
    );
    return eposta_gonder($eposta, 'AYS — E-posta adresinizi doğrulayın', $html, '', 'eposta_dogrulama');
}

// ─── Şifre sıfırlama ────────────────────────────────────────
function sifre_sifirlama_gonder(int $kullanici_id, string $eposta): bool {
    $ham  = kimlik_jetonu_uret($kullanici_id, 'sifre_sifirlama', SIFRE_SIFIRLAMA_OMRU);
    $link = rtrim(SITE_ADRESI, '/') . '/sifre_yenile.php?jeton=' . urlencode($ham);
    $dk   = (int)round(SIFRE_SIFIRLAMA_OMRU / 60);

    $html = eposta_sablonu(
        'Şifrenizi sıfırlayın',
        '<p>Merhaba,</p><p>AYS hesabınız için şifre sıfırlama talebinde bulunuldu. '
        . 'Yeni şifrenizi belirlemek için aşağıdaki düğmeye tıklayın.</p>',
        'Şifremi Sıfırla',
        $link,
        "Bağlantı {$dk} dakika geçerlidir ve yalnızca bir kez kullanılabilir.<br>"
        . "<strong>Bu talebi siz yapmadıysanız</strong> hiçbir şey yapmanıza gerek yok; "
        . "şifreniz değişmeden kalır."
    );
    return eposta_gonder($eposta, 'AYS — Şifre sıfırlama', $html, '', 'sifre_sifirlama');
}

// Şifre değiştikten sonra kullanıcıyı bilgilendirir (hesap ele geçirme
// erken uyarısı — kullanıcı bu değişikliği yapmadıysa haberi olur).
function sifre_degisti_bildir(string $eposta): void {
    $html = eposta_sablonu(
        'Şifreniz değiştirildi',
        '<p>AYS hesabınızın şifresi az önce değiştirildi.</p>'
        . '<p>Güvenlik gereği, "Beni Hatırla" ile açık kalan tüm oturumlar da sonlandırıldı.</p>',
        '',
        '',
        '<strong>Bu değişikliği siz yapmadıysanız</strong> derhal yönetici ile iletişime geçin.'
    );
    eposta_gonder($eposta, 'AYS — Şifreniz değiştirildi', $html, '', 'sifre_degisti');
}
