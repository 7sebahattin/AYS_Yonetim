<?php
// ============================================================
//  includes/mali_yil.php — RESMİ BİLANÇO / YIL SONU KAPANIŞ
//
//  ⚠️ KAPSAM UYARISI: Bu modülü tasarlarken mali müşavir görüşü
//  alınmadı. Aşağıdaki hesaplamalar Kat Mülkiyeti Kanunu'nun
//  yöneticiye yüklediği hesap verme yükümlülüğünü (m.33, m.39)
//  karşılamayı HEDEFLER; kesin format için bir mali müşavire teyit
//  ettirilmesi önerilir. Bu uyarı bilanco.php ekranında da gösterilir.
//
//  ÖN KOŞUL: Sistemde bugüne kadar yalnızca AİDAT geliri vardı. Bir
//  bilanço "devreden bakiye + gelirler − giderler = dönem sonu bakiye"
//  formülüne dayanır; açılış bakiyesi ve aidat dışı gelirler
//  (donem_acilis_bakiye, gelirler tabloları) olmadan bu formül eksik
//  kalırdı. Bu dosya, göç 006 ile gelen o iki tabloyu kullanır.
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/varsayilanlar.php';

function bilanco_semasi_hazir_mi(): bool
{
    static $hazir = null;
    if ($hazir !== null) return $hazir;
    try {
        db()->query("SELECT id FROM gelirler LIMIT 0");
        db()->query("SELECT site_id FROM donem_acilis_bakiye LIMIT 0");
        $hazir = true;
    } catch (Throwable $ex) {
        $hazir = false;
    }
    return $hazir;
}

const GELIR_TURLERI = [
    'kira'             => 'Kira Geliri',
    'gecikme_cezasi'   => 'Gecikme Cezası',
    'bagis'            => 'Bağış',
    'demirbas_satisi'  => 'Demirbaş Satışı',
    'diger'            => 'Diğer',
];

function acilis_bakiyesi(int $site_id, int $yil): float
{
    $st = db()->prepare("SELECT tutar FROM donem_acilis_bakiye WHERE site_id=? AND yil=?");
    $st->execute([$site_id, $yil]);
    $deger = $st->fetchColumn();
    return $deger !== false ? (float)$deger : 0.0;
}

function acilis_bakiyesi_yaz(int $site_id, int $yil, float $tutar, ?string $aciklama, ?int $kullanici_id): void
{
    db()->prepare("
        INSERT INTO donem_acilis_bakiye (site_id, yil, tutar, aciklama, olusturan_id)
        VALUES (?,?,?,?,?)
        ON DUPLICATE KEY UPDATE tutar=VALUES(tutar), aciklama=VALUES(aciklama), olusturan_id=VALUES(olusturan_id)
    ")->execute([$site_id, $yil, $tutar, $aciklama, $kullanici_id]);
}

// Bir takvim yılı için tam mali özet: açılış, gelirler (aidat + diğer),
// giderler (kategori kırılımlı), dönem sonu bakiye, daire bazlı borç
// listesi, tahsilat oranı, önceki yılla karşılaştırma.
//
// NOT — "alacak" (fazla ödeme) hesaplanmaz: aidatlar tablosu dönem
// başına tek bir tutar/durum tutar, kısmi/fazla ödeme kavramı sistemde
// yok. Borç listesi yalnızca ödenmemiş dönemlerin toplamıdır.
function yillik_ozet(int $site_id, int $yil): array
{
    $db = db();
    $bas_donem = sprintf('%04d-01', $yil);
    $bit_donem = sprintf('%04d-12', $yil);

    $acilis = acilis_bakiyesi($site_id, $yil);

    // Aidat geliri (yalnızca tahsil edilmiş)
    $st = $db->prepare("
        SELECT COALESCE(SUM(tutar),0) FROM aidatlar
        WHERE site_id=? AND durum='odendi' AND donem BETWEEN ? AND ?
    ");
    $st->execute([$site_id, $bas_donem, $bit_donem]);
    $aidat_geliri = (float)$st->fetchColumn();

    // Aidat dışı gelirler, tür kırılımlı
    $st = $db->prepare("
        SELECT tur, COALESCE(SUM(tutar),0) AS toplam FROM gelirler
        WHERE site_id=? AND donem BETWEEN ? AND ? GROUP BY tur
    ");
    $st->execute([$site_id, $bas_donem, $bit_donem]);
    $diger_gelir_kirilimi = array_column($st->fetchAll(), 'toplam', 'tur');
    $diger_gelir_toplam = array_sum($diger_gelir_kirilimi);

    $toplam_gelir = $aidat_geliri + $diger_gelir_toplam;

    // Giderler, kategori kırılımlı
    $st = $db->prepare("
        SELECT kategori, COALESCE(SUM(tutar),0) AS toplam FROM giderler
        WHERE site_id=? AND donem BETWEEN ? AND ? GROUP BY kategori ORDER BY toplam DESC
    ");
    $st->execute([$site_id, $bas_donem, $bit_donem]);
    $gider_kirilimi = $st->fetchAll();
    $toplam_gider = array_sum(array_column($gider_kirilimi, 'toplam'));

    $donem_sonu_bakiye = $acilis + $toplam_gelir - $toplam_gider;

    // Daire bazlı borç: ödenmemiş dönemlerin toplamı
    $st = $db->prepare("
        SELECT d.id, d.daire_no, d.sakin_adi,
               COALESCE(SUM(CASE WHEN a.durum <> 'odendi' THEN a.tutar ELSE 0 END),0) AS borc,
               COUNT(CASE WHEN a.durum <> 'odendi' THEN 1 END) AS borclu_donem_sayisi
        FROM daireler d
        LEFT JOIN aidatlar a ON a.daire_id = d.id AND a.donem BETWEEN ? AND ?
        WHERE d.site_id = ?
        GROUP BY d.id, d.daire_no, d.sakin_adi
        HAVING borc > 0
        ORDER BY borc DESC
    ");
    $st->execute([$bas_donem, $bit_donem, $site_id]);
    $borclu_daireler = $st->fetchAll();
    $toplam_borc = array_sum(array_column($borclu_daireler, 'borc'));

    // Tahsilat oranı: dönem × daire matrisinde kaç hücre "ödendi"
    $st = $db->prepare("
        SELECT COUNT(*) AS toplam,
               COUNT(CASE WHEN durum='odendi' THEN 1 END) AS odenen
        FROM aidatlar WHERE site_id=? AND donem BETWEEN ? AND ?
    ");
    $st->execute([$site_id, $bas_donem, $bit_donem]);
    $tahsilat = $st->fetch();
    $tahsilat_oran = (int)$tahsilat['toplam'] > 0
        ? round((int)$tahsilat['odenen'] / (int)$tahsilat['toplam'] * 100, 1)
        : 0.0;

    // Önceki yılla karşılaştırma (yalnızca toplamlar; tam özeti tekrar
    // hesaplamak yerine tek sorguyla alınır — sayfa başına iki tam
    // yillik_ozet() çağrısı gereksiz sorgu yükü olurdu).
    $onceki_yil = $yil - 1;
    $onceki_bas = sprintf('%04d-01', $onceki_yil);
    $onceki_bit = sprintf('%04d-12', $onceki_yil);
    $st = $db->prepare("
        SELECT
            (SELECT COALESCE(SUM(tutar),0) FROM aidatlar
              WHERE site_id=? AND durum='odendi' AND donem BETWEEN ? AND ?) +
            (SELECT COALESCE(SUM(tutar),0) FROM gelirler
              WHERE site_id=? AND donem BETWEEN ? AND ?) AS gelir,
            (SELECT COALESCE(SUM(tutar),0) FROM giderler
              WHERE site_id=? AND donem BETWEEN ? AND ?) AS gider
    ");
    $st->execute([$site_id, $onceki_bas, $onceki_bit, $site_id, $onceki_bas, $onceki_bit,
                   $site_id, $onceki_bas, $onceki_bit]);
    $onceki = $st->fetch();

    return [
        'yil'                  => $yil,
        'acilis_bakiye'        => $acilis,
        'aidat_geliri'         => $aidat_geliri,
        'diger_gelir_kirilimi' => $diger_gelir_kirilimi,
        'diger_gelir_toplam'   => $diger_gelir_toplam,
        'toplam_gelir'         => $toplam_gelir,
        'gider_kirilimi'       => $gider_kirilimi,
        'toplam_gider'         => $toplam_gider,
        'donem_sonu_bakiye'    => $donem_sonu_bakiye,
        'borclu_daireler'      => $borclu_daireler,
        'toplam_borc'          => $toplam_borc,
        'tahsilat_oran'        => $tahsilat_oran,
        'onceki_yil'           => $onceki_yil,
        'onceki_gelir'         => (float)($onceki['gelir'] ?? 0),
        'onceki_gider'         => (float)($onceki['gider'] ?? 0),
    ];
}

// Yılı kapatır: bu yılın dönem sonu bakiyesini, bir SONRAKİ yılın
// açılış bakiyesi olarak yazar. Devir bakiyesini elle taşımak hata
// yapmaya açıktır (aynı rakamın iki yerde farklı girilmesi gibi); bu
// fonksiyon zinciri otomatik kurar.
//
// BİLİNÇLİ TASARIM: sonraki yılın açılış bakiyesi zaten elle
// girilmişse (kullanıcı daha önce girdiyse) ÜZERİNE YAZILMAZ — kapanış
// işlemi geri alınabilir olmalı ve elle yapılmış bir düzeltmeyi
// sessizce ezmemeli. Üzerine yazmak isteniyorsa acilis_bakiyesi_yaz()
// doğrudan çağrılabilir.
function yili_kapat(int $site_id, int $yil, float $donem_sonu_bakiye, int $kullanici_id): bool
{
    $sonraki_yil = $yil + 1;
    $st = db()->prepare("SELECT 1 FROM donem_acilis_bakiye WHERE site_id=? AND yil=?");
    $st->execute([$site_id, $sonraki_yil]);
    if ($st->fetchColumn()) return false;

    acilis_bakiyesi_yaz(
        $site_id, $sonraki_yil, $donem_sonu_bakiye,
        $yil . ' yılı kapanışından devreden bakiye',
        $kullanici_id
    );
    return true;
}
