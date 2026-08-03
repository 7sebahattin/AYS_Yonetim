<?php
// ============================================================
//  includes/hiz_limiti.php — HIZ SINIRLAMA (RATE LIMITING)
//
//  Şifre sıfırlama gibi e-posta gönderen uç noktalar, sınırlanmazsa
//  başkasının adresine spam göndermek için kullanılabilir. Giriş
//  formu ise kaba kuvvet denemesine açıktır.
//
//  Basit kayan pencere: pencere dolduğunda sayaç sıfırlanır.
//  Veritabanı tabanlı olması bilinçli — paylaşımlı hostingde APCu/
//  Redis garanti değil, ama MySQL her zaman var.
// ============================================================

require_once __DIR__ . '/../config.php';

// Bir eylem için kota kontrolü yapar ve sayacı artırır.
// true  → izin var (sayaç artırıldı)
// false → limit aşıldı
//
// Tablo yoksa (göç uygulanmamışsa) güvenli tarafta kalıp TRUE döner:
// koruma devre dışı kalır ama sistem çalışmayı sürdürür.
function hiz_limiti_gec(string $anahtar, int $limit, int $pencere_saniye): bool {
    try {
        $db = db();
        $simdi = new DateTimeImmutable();

        $stmt = $db->prepare("SELECT sayac, pencere_baslangic FROM hiz_limiti WHERE anahtar = ?");
        $stmt->execute([$anahtar]);
        $kayit = $stmt->fetch();

        if (!$kayit) {
            $db->prepare("INSERT INTO hiz_limiti (anahtar, sayac, pencere_baslangic) VALUES (?, 1, ?)")
               ->execute([$anahtar, $simdi->format('Y-m-d H:i:s')]);
            return true;
        }

        $pencere_basi = new DateTimeImmutable($kayit['pencere_baslangic']);
        $gecen = $simdi->getTimestamp() - $pencere_basi->getTimestamp();

        if ($gecen >= $pencere_saniye) {
            // Pencere doldu → sıfırla
            $db->prepare("UPDATE hiz_limiti SET sayac = 1, pencere_baslangic = ? WHERE anahtar = ?")
               ->execute([$simdi->format('Y-m-d H:i:s'), $anahtar]);
            return true;
        }

        if ((int)$kayit['sayac'] >= $limit) {
            return false;
        }

        $db->prepare("UPDATE hiz_limiti SET sayac = sayac + 1 WHERE anahtar = ?")->execute([$anahtar]);
        return true;
    } catch (Throwable $ex) {
        error_log('Hız limiti kontrol edilemedi: ' . $ex->getMessage());
        return true; // koruma çalışmasa bile sistem kullanılabilir kalsın
    }
}

// E-posta adresini anahtar olarak kullanırken düz metin yazmamak için.
function hiz_limiti_anahtari(string $eylem, string $deger): string {
    return $eylem . ':' . hash('sha256', mb_strtolower($deger, 'UTF-8'));
}
