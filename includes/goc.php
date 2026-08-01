<?php
// ============================================================
//  includes/goc.php — ŞEMA GÖÇÜ (MIGRATION) ALTYAPISI
//
//  Neden gerekli: Proje şimdiye kadar "kendi kendini onaran" tablolar
//  kullandı (CREATE TABLE IF NOT EXISTS). Bu, YENİ tablo eklemek için
//  yeterli ama VAR OLAN tabloyu değiştirmek (ALTER, veri taşıma) için
//  değil. Sıralı, tekrarlanabilir ve izlenebilir bir mekanizma gerekiyor.
//
//  Nasıl çalışır: semalar/NNN_aciklama.sql dosyaları numara sırasıyla
//  uygulanır, uygulananlar sema_surumu tablosuna yazılır. Aynı göç iki
//  kez çalıştırılmaz.
//
//  MariaDB notu: Göç dosyalarında ALTER TABLE ... ADD COLUMN IF NOT
//  EXISTS / CREATE INDEX IF NOT EXISTS kullanılır. Bu sözdizimi
//  MariaDB'ye özgüdür (hedef sunucu MariaDB 10.6) ve göçleri kendi
//  içinde de idempotent yapar — yarıda kalan bir göç tekrar
//  çalıştırıldığında "duplicate column" hatası vermez.
// ============================================================

require_once __DIR__ . '/../config.php';

function goc_dizini(): string {
    return dirname(__DIR__) . '/semalar';
}

// Göç takip tablosunu hazırlar (kendisi de idempotent).
function goc_tablosunu_hazirla(): void {
    db()->exec("
        CREATE TABLE IF NOT EXISTS sema_surumu (
            surum VARCHAR(100) NOT NULL,
            uygulanma DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            sure_ms INT UNSIGNED DEFAULT NULL,
            PRIMARY KEY (surum)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci
    ");
}

// Uygulanmış göç adlarını döndürür.
function uygulanmis_gocler(): array {
    goc_tablosunu_hazirla();
    return db()->query("SELECT surum FROM sema_surumu ORDER BY surum")
               ->fetchAll(PDO::FETCH_COLUMN);
}

// semalar/ klasöründeki tüm göç dosyalarını sıralı döndürür.
function tum_gocler(): array {
    $dosyalar = glob(goc_dizini() . '/*.sql') ?: [];
    $liste = array_map('basename', $dosyalar);
    sort($liste, SORT_NATURAL);
    return $liste;
}

// Henüz uygulanmamış göçleri döndürür.
function bekleyen_gocler(): array {
    $uygulanmis = uygulanmis_gocler();
    return array_values(array_diff(tum_gocler(), $uygulanmis));
}

// Bir SQL dosyasını çalıştırılabilir ifadelere böler.
// Satır sonundaki ';' ayırıcı kabul edilir; '--' yorum satırları atılır.
function goc_ifadelerine_bol(string $sql): array {
    $satirlar = preg_split('/\R/', $sql);
    $temiz = [];
    foreach ($satirlar as $satir) {
        $kirpik = ltrim($satir);
        if ($kirpik === '' || str_starts_with($kirpik, '--')) continue;
        $temiz[] = $satir;
    }
    $govde = implode("\n", $temiz);

    $ifadeler = [];
    foreach (explode(";\n", $govde . "\n") as $parca) {
        $parca = trim($parca, " \t\n\r;");
        if ($parca !== '') $ifadeler[] = $parca;
    }
    return $ifadeler;
}

// Tek bir göç dosyasını uygular ve kaydeder.
// Not: MySQL/MariaDB'de DDL işlemleri geri alınamaz (transaction'a
// alınamaz). Bu yüzden göç öncesi veritabanı yedeği ALMAK ŞARTTIR —
// yarıda kalan bir göç elle temizlenmek zorunda kalınabilir.
function goc_uygula(string $dosya_adi): array {
    $yol = goc_dizini() . '/' . basename($dosya_adi);
    if (!is_file($yol)) {
        return ['basarili' => false, 'mesaj' => "Göç dosyası bulunamadı: $dosya_adi"];
    }

    $ifadeler = goc_ifadelerine_bol((string)file_get_contents($yol));
    if (!$ifadeler) {
        return ['basarili' => false, 'mesaj' => "Göç dosyası boş: $dosya_adi"];
    }

    $db = db();
    $baslangic = microtime(true);
    try {
        foreach ($ifadeler as $ifade) {
            $db->exec($ifade);
        }
        $sure = (int)round((microtime(true) - $baslangic) * 1000);
        $db->prepare("INSERT INTO sema_surumu (surum, sure_ms) VALUES (?, ?)")
           ->execute([$dosya_adi, $sure]);
        return ['basarili' => true, 'mesaj' => "$dosya_adi uygulandı ({$sure} ms, " . count($ifadeler) . " ifade)"];
    } catch (Throwable $ex) {
        return ['basarili' => false, 'mesaj' => "$dosya_adi BAŞARISIZ: " . $ex->getMessage()];
    }
}

// Bekleyen tüm göçleri sırayla uygular. İlk hatada durur.
function tum_gocleri_uygula(): array {
    goc_tablosunu_hazirla();
    $sonuclar = [];
    foreach (bekleyen_gocler() as $dosya) {
        $sonuc = goc_uygula($dosya);
        $sonuclar[] = $sonuc;
        if (!$sonuc['basarili']) break; // sıra bozulmasın diye ilk hatada dur
    }
    return $sonuclar;
}
