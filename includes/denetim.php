<?php
// ============================================================
//  includes/denetim.php — DENETİM KAYDI (AUDIT LOG)
//
//  Kim, ne zaman, neyi yaptı. Faz 3'teki süper admin panelinin
//  denetlenebilir olması için zorunlu altyapı; şimdilik kimlik
//  olaylarını (giriş, çıkış, şifre sıfırlama) kaydeder.
//
//  Tasarım kuralı: denetim yazımı ASLA ana işlemi bozmamalı. Tablo
//  henüz oluşmamışsa veya yazma başarısız olursa sessizce yutulur —
//  aksi halde göç uygulanmamış bir sunucuda giriş yapılamaz hale
//  gelirdi.
// ============================================================

require_once __DIR__ . '/../config.php';

// denetim_kaydi.site_id göç 004 ile geldi. Sütun yokken de kayıt
// yazılabilmesi için varlığı bir kez yoklanır (göç uygulanmamış
// sunucuda denetim yazımı sessizce eski şemaya düşer).
function denetim_site_sutunu_var_mi(): bool
{
    static $var = null;
    if ($var !== null) return $var;
    try {
        db()->query("SELECT site_id FROM denetim_kaydi LIMIT 0");
        $var = true;
    } catch (Throwable $ex) {
        $var = false;
    }
    return $var;
}

function denetim_yaz(
    string $eylem,
    ?string $hedef_tur = null,
    ?int $hedef_id = null,
    array $detay = [],
    ?int $kullanici_id = null,
    ?int $site_id = null
): void {
    try {
        if ($kullanici_id === null && !empty($_SESSION['kullanici_id'])) {
            $kullanici_id = (int)$_SESSION['kullanici_id'];
        }
        if ($site_id === null && !empty($_SESSION['aktif_site_id'])) {
            $site_id = (int)$_SESSION['aktif_site_id'];
        }

        // Kimliğe bürünme sırasında yapılan her işlem, bürünen süper
        // admin ile birlikte kaydedilir. Aksi halde denetim izi eylemi
        // masum kullanıcının üstüne yazardı.
        if (!empty($_SESSION['kimlik_burunme']['hedef']) && !empty($_SESSION['yonetim_id'])) {
            $detay['burunen_yonetici_id'] = (int)$_SESSION['yonetim_id'];
        }
        $detay_json = $detay ? json_encode($detay, JSON_UNESCAPED_UNICODE) : null;

        $eylem     = mb_substr($eylem, 0, 60, 'UTF-8');
        $hedef_tur = $hedef_tur !== null ? mb_substr($hedef_tur, 0, 40, 'UTF-8') : null;

        if (denetim_site_sutunu_var_mi()) {
            db()->prepare("
                INSERT INTO denetim_kaydi (kullanici_id, site_id, eylem, hedef_tur, hedef_id, detay, ip_adresi)
                VALUES (?,?,?,?,?,?,?)
            ")->execute([$kullanici_id, $site_id, $eylem, $hedef_tur, $hedef_id, $detay_json, istemci_ip()]);
        } else {
            db()->prepare("
                INSERT INTO denetim_kaydi (kullanici_id, eylem, hedef_tur, hedef_id, detay, ip_adresi)
                VALUES (?,?,?,?,?,?)
            ")->execute([$kullanici_id, $eylem, $hedef_tur, $hedef_id, $detay_json, istemci_ip()]);
        }
    } catch (Throwable $ex) {
        // Denetim kaydı yazılamazsa ana akış devam etmeli.
        error_log('Denetim kaydı yazılamadı: ' . $ex->getMessage());
    }
}

// İstemci IP'si. Ters vekil (proxy/CDN) arkasındaysa X-Forwarded-For'un
// İLK değeri alınır; ancak bu başlık istemci tarafından uydurulabildiği
// için yalnızca teşhis/denetim amaçlı kullanılır — yetkilendirme veya
// güvenlik kararı bu değere DAYANDIRILMAMALIDIR.
function istemci_ip(): string {
    $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($xff !== '') {
        $ilk = trim(explode(',', $xff)[0]);
        if (filter_var($ilk, FILTER_VALIDATE_IP)) return $ilk;
    }
    return $_SERVER['REMOTE_ADDR'] ?? '';
}
