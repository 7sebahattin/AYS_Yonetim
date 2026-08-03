<?php
// ============================================================
//  includes/arsiv.php — KARAR DEFTERİ / BELGE ARŞİVİ ORTAK KATMANI
//
//  ⚠️ YASAL UYARI (bu dosyanın var olma sebebi): Kat Mülkiyeti
//  Kanunu'na göre karar defteri NOTER TASDİKLİ FİZİKSEL DEFTER olarak
//  tutulur. Buradaki modül yasal aslın YERİNE GEÇMEZ; amacı dijital
//  arşiv ve kolay erişimdir. Bu uyarı kararlar.php ve belgeler.php
//  ekranlarında da gösterilir — kullanıcı yanlış güvene kapılmamalı.
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/varsayilanlar.php';
require_once __DIR__ . '/dosya.php';

// ─── Şema hazır mı? ─────────────────────────────────────────
function arsiv_semasi_hazir_mi(): bool
{
    static $hazir = null;
    if ($hazir !== null) return $hazir;
    try {
        db()->query("SELECT id FROM kararlar LIMIT 0");
        db()->query("SELECT id FROM belgeler LIMIT 0");
        $hazir = true;
    } catch (Throwable $ex) {
        $hazir = false;
    }
    return $hazir;
}

const TOPLANTI_TURLERI = [
    'olagan_genel_kurul'     => 'Olağan Genel Kurul',
    'olaganustu_genel_kurul' => 'Olağanüstü Genel Kurul',
    'yonetim_kurulu'         => 'Yönetim Kurulu',
    'diger'                  => 'Diğer',
];

const BELGE_TURLERI = [
    'karar_defteri'          => 'Karar Defteri Sayfası',
    'yonetim_plani'          => 'Yönetim Planı',
    'genel_kurul_tutanagi'   => 'Genel Kurul Tutanağı',
    'sozlesme'               => 'Sözleşme',
    'sigorta_policesi'       => 'Sigorta Poliçesi',
    'ruhsat'                 => 'Ruhsat',
    'bakim_raporu'           => 'Bakım Raporu',
    'diger'                  => 'Diğer',
];

// Bir sonraki karar numarasını önerir: "<yıl>/<sıra>". Kullanıcı
// isterse elle değiştirebilir (mevcut fiziksel deftere göre farklı
// bir numaralandırma kullanılıyor olabilir); bu yüzden salt öneridir,
// dayatılmaz.
function karar_no_oner(int $site_id, ?int $yil = null): string
{
    $yil = $yil ?? (int)date('Y');
    $st = db()->prepare("
        SELECT karar_no FROM kararlar
        WHERE site_id = ? AND karar_no LIKE ?
        ORDER BY id DESC LIMIT 50
    ");
    $st->execute([$site_id, $yil . '/%']);

    $en_buyuk = 0;
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $mevcut) {
        if (preg_match('#^' . $yil . '/(\d+)$#', $mevcut, $m)) {
            $en_buyuk = max($en_buyuk, (int)$m[1]);
        }
    }
    return $yil . '/' . ($en_buyuk + 1);
}

// Bir kararın gerçekten bu siteye ait olduğunu doğrular. Belge formu
// bunu, girilen karar id'sinin başka bir apartmana ait olmadığını
// kanıtlamak için kullanır.
function karar_gecerli_mi(int $site_id, mixed $karar_id): ?int
{
    $karar_id = (int)$karar_id;
    if ($karar_id <= 0) return null;
    $st = db()->prepare("SELECT id FROM kararlar WHERE id = ? AND site_id = ?");
    $st->execute([$karar_id, $site_id]);
    return $st->fetchColumn() ? $karar_id : null;
}
