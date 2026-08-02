<?php
// ============================================================
//  includes/operasyon.php — OPERASYONEL MODÜLLER ORTAK KATMANI
//
//  Talep (arıza/istek), demirbaş/bakım ve personel modüllerinin
//  paylaştığı etiketler, gider bağlama mantığı ve ek (dosya)
//  yönetimi.
//
//  GİDER BAĞLAMA — bu dosyadaki en önemli kural:
//  Bakım tutarı ve personel ödemesi hem kendi modülünde hem
//  giderler'de görünmeli ama ÇİFT SAYILMAMALI. Çözüm: kayıt
//  oluşturulduğunda otomatik bir gider satırı üretilir ve iki kayıt
//  gider_id / kaynak_tur+kaynak_id ile karşılıklı bağlanır. Gider
//  ekranından bu satırın silinmesi ve düzenlenmesi engellenir —
//  aksi halde iki kayıt ayrışır ve aynı harcama ya iki kez sayılır
//  ya da hiç görünmez.
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/varsayilanlar.php';
require_once __DIR__ . '/dosya.php';

// ─── Şema hazır mı? ─────────────────────────────────────────
// Göç 005 uygulanmadan da uygulama çalışmalı: modüller menüde
// görünmez, geri kalan her şey normal çalışır.
function operasyon_semasi_hazir_mi(): bool
{
    static $hazir = null;
    if ($hazir !== null) return $hazir;
    try {
        db()->query("SELECT id FROM talepler LIMIT 0");
        db()->query("SELECT id FROM demirbaslar LIMIT 0");
        db()->query("SELECT id FROM personel LIMIT 0");
        $hazir = true;
    } catch (Throwable $ex) {
        $hazir = false;
    }
    return $hazir;
}

// ─── Etiketler ──────────────────────────────────────────────
// Veritabanında İngilizce/ASCII anahtar tutulup arayüzde Türkçe
// gösterilmesi bilinçli: ENUM değerlerini değiştirmek göç gerektirir,
// etiket değiştirmek gerektirmez.
const TALEP_KATEGORILERI = [
    'asansor'    => 'Asansör',
    'su_tesisat' => 'Su / Tesisat',
    'elektrik'   => 'Elektrik',
    'isitma'     => 'Isıtma',
    'temizlik'   => 'Temizlik',
    'guvenlik'   => 'Güvenlik',
    'ortak_alan' => 'Ortak Alan',
    'diger'      => 'Diğer',
];

const TALEP_ONCELIKLERI = [
    'dusuk'  => 'Düşük',
    'normal' => 'Normal',
    'yuksek' => 'Yüksek',
    'acil'   => 'Acil',
];

// Durum akışı: Açık → İşlemde → Beklemede → Çözüldü → Kapalı (+ İptal)
const TALEP_DURUMLARI = [
    'acik'      => 'Açık',
    'islemde'   => 'İşlemde',
    'beklemede' => 'Beklemede',
    'cozuldu'   => 'Çözüldü',
    'kapali'    => 'Kapalı',
    'iptal'     => 'İptal',
];

// Talebin "işi bitmiş" sayıldığı durumlar. Açık talep sayacı ve
// varsayılan liste filtresi bunun dışındakileri gösterir.
const TALEP_KAPALI_DURUMLAR = ['cozuldu', 'kapali', 'iptal'];

const DEMIRBAS_KATEGORILERI = [
    'asansor'    => 'Asansör',
    'yangin'     => 'Yangın Güvenliği',
    'jenerator'  => 'Jeneratör',
    'hidrofor'   => 'Hidrofor',
    'kazan'      => 'Kazan / Isıtma',
    'paratoner'  => 'Paratoner',
    'kamera'     => 'Kamera / Güvenlik',
    'otopark'    => 'Otopark',
    'peyzaj'     => 'Peyzaj',
    'diger'      => 'Diğer',
];

const DEMIRBAS_DURUMLARI = ['aktif' => 'Aktif', 'arizali' => 'Arızalı', 'hurda' => 'Hurda'];

const BAKIM_TURLERI = [
    'periyodik'     => 'Periyodik Bakım',
    'yasal_muayene' => 'Yasal Muayene',
    'ariza'         => 'Arıza Onarımı',
    'montaj'        => 'Montaj / Kurulum',
    'diger'         => 'Diğer',
];

const BAKIM_DURUMLARI = ['planlandi' => 'Planlandı', 'yapildi' => 'Yapıldı', 'iptal' => 'İptal'];

const PERSONEL_GOREVLERI = [
    'kapici'     => 'Kapıcı',
    'guvenlik'   => 'Güvenlik',
    'temizlik'   => 'Temizlik',
    'teknisyen'  => 'Teknisyen',
    'bahcivan'   => 'Bahçıvan',
    'diger'      => 'Diğer',
];

const ODEME_TURLERI = [
    'maas'     => 'Maaş',
    'avans'    => 'Avans',
    'ikramiye' => 'İkramiye',
    'sgk'      => 'SGK Primi',
    'diger'    => 'Diğer',
];

// Bir ENUM değerini doğrular; geçersizse varsayılana düşer.
// Formdan gelen değerin doğrudan sorguya girmesini engeller.
function enum_deger(mixed $deger, array $secenekler, string $varsayilan): string
{
    $deger = (string)$deger;
    return array_key_exists($deger, $secenekler) ? $deger : $varsayilan;
}

function etiket(array $secenekler, ?string $anahtar): string
{
    return $secenekler[(string)$anahtar] ?? (string)$anahtar;
}

// Formdan gelen bir yabancı anahtarın GERÇEKTEN bu siteye ait olduğunu
// doğrular. Bu kontrol olmadan kullanıcı, form alanını değiştirerek
// talebini başka bir apartmanın dairesine ya da personeline
// bağlayabilirdi. Geçersizse null döner (bağlantı kurulmaz, hata verilmez).
//
// Tablo adı SABİT bir listeden gelir; kullanıcı girdisi tablo adına
// dönüşemez.
function site_kaydi_gecerli_mi(string $tablo, int $site_id, mixed $id): ?int
{
    $izinli = ['daireler', 'personel', 'demirbaslar', 'bloklar'];
    if (!in_array($tablo, $izinli, true)) return null;

    $id = (int)$id;
    if ($id <= 0) return null;

    $st = db()->prepare("SELECT id FROM `$tablo` WHERE id = ? AND site_id = ?");
    $st->execute([$id, $site_id]);
    return $st->fetchColumn() ? $id : null;
}

// ─── Gider bağlama ──────────────────────────────────────────
// Bir bakım/ödeme kaydı için gider satırı oluşturur veya günceller.
// Var olan bağlı gideri günceller, yoksa yenisini yaratır; tutar
// null/0 ise bağlı gider varsa siler (bakım "tutarsız" hale gelmiş
// demektir).
//
// Döner: gider_id ya da null.
function gidere_yansit(
    int $site_id,
    string $kaynak_tur,
    int $kaynak_id,
    ?int $mevcut_gider_id,
    ?float $tutar,
    string $kategori,
    string $aciklama,
    ?string $tarih
): ?int {
    $db = db();

    if ($tutar === null || $tutar <= 0) {
        if ($mevcut_gider_id) {
            $db->prepare("DELETE FROM giderler WHERE id = ? AND site_id = ?
                          AND kaynak_tur = ? AND kaynak_id = ?")
               ->execute([$mevcut_gider_id, $site_id, $kaynak_tur, $kaynak_id]);
        }
        return null;
    }

    $tarih    = $tarih ?: date('Y-m-d');
    $donem    = substr($tarih, 0, 7);
    $kategori = mb_substr(turkce_buyuk($kategori), 0, 50, 'UTF-8');
    $aciklama = mb_substr(turkce_buyuk($aciklama), 0, 255, 'UTF-8');

    if ($mevcut_gider_id) {
        $st = $db->prepare("
            UPDATE giderler SET kategori=?, aciklama=?, tutar=?, tarih=?, donem=?
            WHERE id=? AND site_id=? AND kaynak_tur=? AND kaynak_id=?
        ");
        $st->execute([$kategori, $aciklama, $tutar, $tarih, $donem,
                      $mevcut_gider_id, $site_id, $kaynak_tur, $kaynak_id]);
        // Satır bulunamadıysa (elle silinmiş olabilir) yenisi oluşturulur.
        if ($st->rowCount() > 0 || gider_var_mi($mevcut_gider_id, $site_id)) {
            return $mevcut_gider_id;
        }
    }

    $db->prepare("
        INSERT INTO giderler (site_id, kategori, aciklama, tutar, tarih, donem, kaynak_tur, kaynak_id)
        VALUES (?,?,?,?,?,?,?,?)
    ")->execute([$site_id, $kategori, $aciklama, $tutar, $tarih, $donem, $kaynak_tur, $kaynak_id]);

    return (int)$db->lastInsertId();
}

// Bir gider satırının otomatik üretilip üretilmediğini söyler.
// Giderler ekranı bunu kullanarak düzenle/sil düğmelerini kilitler.
// Göç 005 uygulanmamışsa sütun yoktur → her gider "elle girilmiş"
// sayılır ve eski davranış korunur.
function bagli_gider_mi(int $gider_id, int $site_id): bool
{
    try {
        $st = db()->prepare("SELECT kaynak_tur FROM giderler WHERE id=? AND site_id=?");
        $st->execute([$gider_id, $site_id]);
        return !empty($st->fetchColumn());
    } catch (Throwable $ex) {
        return false;
    }
}

function gider_var_mi(int $gider_id, int $site_id): bool
{
    $st = db()->prepare("SELECT 1 FROM giderler WHERE id = ? AND site_id = ?");
    $st->execute([$gider_id, $site_id]);
    return (bool)$st->fetchColumn();
}

// Kaynak kayıt silinirken bağlı gider satırını da temizler.
function bagli_gideri_sil(int $site_id, string $kaynak_tur, int $kaynak_id): void
{
    db()->prepare("DELETE FROM giderler WHERE site_id=? AND kaynak_tur=? AND kaynak_id=?")
        ->execute([$site_id, $kaynak_tur, $kaynak_id]);
}

// ─── Ekler (dosya) ──────────────────────────────────────────
// Yüklenen dosyalar web kökünün dışında saklanır; burada yalnızca
// künye tutulur ve indirme belge_indir.php üzerinden yapılır.
function ek_kaydet(int $site_id, string $hedef_tur, int $hedef_id,
                   array $dosya, ?int $yukleyen_id): array
{
    $sonuc = dosya_yukle($dosya, $hedef_tur . '/' . $site_id);
    if (!$sonuc['basarili']) return $sonuc;

    db()->prepare("
        INSERT INTO ekler (site_id, hedef_tur, hedef_id, yol, orijinal_ad, mime, boyut, yukleyen_id)
        VALUES (?,?,?,?,?,?,?,?)
    ")->execute([$site_id, $hedef_tur, $hedef_id, $sonuc['yol'],
                 $sonuc['orijinal_ad'], $sonuc['mime'], $sonuc['boyut'], $yukleyen_id]);

    return $sonuc;
}

// Çoklu dosya alanını ($_FILES['ekler'][]) tek tek işler. Bir dosya
// reddedilirse diğerleri yüklenmeye devam eder; kullanıcıya yalnızca
// reddedilenler bildirilir — beş fotoğraftan biri bozuk diye hepsinin
// kaybolması kötü bir takas olurdu.
function ekleri_yukle(int $site_id, string $hedef_tur, int $hedef_id,
                      ?int $kullanici_id, string $alan = 'ekler'): void
{
    if (empty($_FILES[$alan]['name'][0])) return;

    $hatalar = [];
    foreach ($_FILES[$alan]['name'] as $i => $ad) {
        if ($ad === '') continue;
        $tek = [
            'name'     => $_FILES[$alan]['name'][$i],
            'type'     => $_FILES[$alan]['type'][$i],
            'tmp_name' => $_FILES[$alan]['tmp_name'][$i],
            'error'    => $_FILES[$alan]['error'][$i],
            'size'     => $_FILES[$alan]['size'][$i],
        ];
        $sonuc = ek_kaydet($site_id, $hedef_tur, $hedef_id, $tek, $kullanici_id);
        if (!$sonuc['basarili']) $hatalar[] = $ad . ': ' . $sonuc['mesaj'];
    }
    if ($hatalar) flash(implode(' · ', $hatalar), 'uyari');
}

function ekleri_getir(int $site_id, string $hedef_tur, int $hedef_id): array
{
    if (!operasyon_semasi_hazir_mi()) return [];
    $st = db()->prepare("
        SELECT id, orijinal_ad, mime, boyut, olusturma
        FROM ekler WHERE site_id=? AND hedef_tur=? AND hedef_id=? ORDER BY id
    ");
    $st->execute([$site_id, $hedef_tur, $hedef_id]);
    return $st->fetchAll();
}

// Ek kaydını ve diskteki dosyayı birlikte siler. site_id koşulu
// zorunlu: başka bir apartmanın eki id tahminiyle silinemesin.
function ek_sil(int $site_id, int $ek_id): bool
{
    $st = db()->prepare("SELECT yol FROM ekler WHERE id=? AND site_id=?");
    $st->execute([$ek_id, $site_id]);
    $yol = $st->fetchColumn();
    if ($yol === false) return false;

    db()->prepare("DELETE FROM ekler WHERE id=? AND site_id=?")->execute([$ek_id, $site_id]);
    dosya_sil((string)$yol);
    return true;
}

// Bir kayıt silinmeden önce ona bağlı tüm ekleri temizler.
// FK cascade dosyaları diskten silmez; bu yüzden elle yapılır.
function hedefin_eklerini_sil(int $site_id, string $hedef_tur, int $hedef_id): void
{
    $st = db()->prepare("SELECT id, yol FROM ekler WHERE site_id=? AND hedef_tur=? AND hedef_id=?");
    $st->execute([$site_id, $hedef_tur, $hedef_id]);
    foreach ($st->fetchAll() as $ek) {
        dosya_sil((string)$ek['yol']);
    }
    db()->prepare("DELETE FROM ekler WHERE site_id=? AND hedef_tur=? AND hedef_id=?")
        ->execute([$site_id, $hedef_tur, $hedef_id]);
}

function boyut_okunabilir(?int $bayt): string
{
    $bayt = (int)$bayt;
    if ($bayt >= 1048576) return round($bayt / 1048576, 1) . ' MB';
    if ($bayt >= 1024)    return round($bayt / 1024) . ' KB';
    return $bayt . ' B';
}

// ─── Dashboard göstergeleri ─────────────────────────────────
function acik_talep_sayisi(int $site_id): int
{
    if (!operasyon_semasi_hazir_mi()) return 0;
    $st = db()->prepare("SELECT COUNT(*) FROM talepler WHERE site_id=? AND durum IN ('acik','islemde','beklemede')");
    $st->execute([$site_id]);
    return (int)$st->fetchColumn();
}

// Yaklaşan ve geciken planlı bakımlar. Yasal muayeneler (asansör,
// yangın, paratoner) kaçırıldığında ciddi sorumluluk doğduğu için
// geçmiş tarihliler de listeye dahil edilir — "geçti, artık gösterme"
// davranışı bu modülün amacını boşa çıkarırdı.
function yaklasan_bakimlar(int $site_id, int $gun = 30): array
{
    if (!operasyon_semasi_hazir_mi()) return [];
    $st = db()->prepare("
        SELECT b.id, b.baslik, b.tur, b.planlanan_tarih, d.ad AS demirbas_adi,
               DATEDIFF(b.planlanan_tarih, CURDATE()) AS kalan_gun
        FROM bakimlar b JOIN demirbaslar d ON d.id = b.demirbas_id
        WHERE b.site_id = ? AND b.durum = 'planlandi'
          AND b.planlanan_tarih IS NOT NULL
          AND b.planlanan_tarih <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
        ORDER BY b.planlanan_tarih
    ");
    $st->execute([$site_id, $gun]);
    return $st->fetchAll();
}

// Bir bakım "yapıldı" işaretlendiğinde, periyoda göre bir sonraki
// planlı kaydı üretir. Zincirin kopmaması bu modülün asıl işi:
// yöneticinin her seferinde elle "bir sonrakini de gireyim" demesi
// beklenirse kaçınılmaz olarak unutulur.
function sonraki_bakimi_planla(array $bakim): ?int
{
    $periyot = (int)($bakim['periyot_ay'] ?? 0);
    if ($periyot <= 0) return null;

    $temel = $bakim['yapilan_tarih'] ?: date('Y-m-d');
    $sonraki = date('Y-m-d', strtotime($temel . " +{$periyot} months"));

    // Aynı demirbaş için aynı tarihte planlı kayıt varsa tekrar üretme
    // (bir bakım iki kez "yapıldı" işaretlenirse çift kayıt oluşmasın).
    $st = db()->prepare("SELECT id FROM bakimlar
                         WHERE demirbas_id=? AND planlanan_tarih=? AND durum='planlandi'");
    $st->execute([$bakim['demirbas_id'], $sonraki]);
    if ($st->fetchColumn()) return null;

    db()->prepare("
        INSERT INTO bakimlar (site_id, demirbas_id, tur, baslik, planlanan_tarih, periyot_ay, firma, durum)
        VALUES (?,?,?,?,?,?,?, 'planlandi')
    ")->execute([
        $bakim['site_id'], $bakim['demirbas_id'], $bakim['tur'],
        $bakim['baslik'], $sonraki, $periyot, $bakim['firma'],
    ]);

    return (int)db()->lastInsertId();
}
