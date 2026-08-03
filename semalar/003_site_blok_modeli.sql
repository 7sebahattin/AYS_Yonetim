-- ============================================================
-- 003 — Kimlik / Site / Blok modeli (MİMARİ REFACTOR)
--
-- SORUN: Bugüne kadar `kullanicilar` satırı hem KULLANICI HESABI hem
-- YÖNETİLEN APARTMAN anlamına geliyordu (apartman_adi, toplam_daire bu
-- tabloda). Tüm veri tabloları kullanici_id ile filtreleniyordu. Bu
-- birleşim yüzünden bir kullanıcı ikinci bir site yönetemiyor, bir
-- sitenin ikinci yöneticisi olamıyor ve platform düzeyinde (site'e
-- bağlı olmayan) bir yönetici tanımlanamıyordu.
--
-- ÇÖZÜM: Kimlik ile mülk ayrılıyor.
--   kullanicilar              → kim olduğun (kimlik + giriş)
--   siteler                   → neyi yönettiğin (apartman/site)
--   kullanici_site_yetkileri  → hangi sitede hangi yetkiyle
--   bloklar                   → site içi alt bölüm (A Blok, B Blok…)
--
-- GÖÇ GÜVENLİĞİ: Her mevcut kullanıcı için AYNI id ile bir site satırı
-- oluşturulur (siteler.id = kullanicilar.id). Böylece veri tablolarında
-- site_id = kullanici_id ataması birebir ve tek bir COUNT sorgusuyla
-- kanıtlanabilir olur.
--
-- GERİ ALINABİLİRLİK: Eski kullanici_id sütunları bu göçte SİLİNMEZ;
-- doğrulama süresi sonunda ayrı bir göçle kaldırılacaktır. Yalnızca
-- çoklu siteyi fiilen engelleyen uq_kullanici_daire indeksi kaldırılır.
-- ============================================================

-- ─── Yeni tablolar ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS siteler (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ad VARCHAR(100) NOT NULL,
    tip ENUM('apartman','site') NOT NULL DEFAULT 'apartman',
    adres VARCHAR(255) DEFAULT NULL,
    telefon VARCHAR(20) DEFAULT NULL,
    toplam_daire INT UNSIGNED NOT NULL DEFAULT 0,
    durum ENUM('aktif','askida') NOT NULL DEFAULT 'aktif',
    olusturma DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE TABLE IF NOT EXISTS bloklar (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    site_id INT UNSIGNED NOT NULL,
    ad VARCHAR(60) NOT NULL,
    sira SMALLINT NOT NULL DEFAULT 1,
    olusturma DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_site_blok (site_id, ad),
    CONSTRAINT fk_blok_site FOREIGN KEY (site_id) REFERENCES siteler (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- Bir kullanıcı birden çok siteyi yönetebilir; bir sitenin birden çok
-- yöneticisi olabilir. 'rol' sütunu şimdilik hep 'yonetici' — rol
-- tabanlı yetkilendirme arayüzü ileri fazda gelecek, ancak sütunun
-- şimdiden bulunması sonraki göçleri gereksiz kılıyor.
CREATE TABLE IF NOT EXISTS kullanici_site_yetkileri (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    kullanici_id INT UNSIGNED NOT NULL,
    site_id INT UNSIGNED NOT NULL,
    rol ENUM('yonetici','muhasebe','denetci') NOT NULL DEFAULT 'yonetici',
    durum ENUM('aktif','pasif') NOT NULL DEFAULT 'aktif',
    olusturma DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_kullanici_site (kullanici_id, site_id),
    KEY ix_yetki_site (site_id),
    CONSTRAINT fk_yetki_kullanici FOREIGN KEY (kullanici_id) REFERENCES kullanicilar (id) ON DELETE CASCADE,
    CONSTRAINT fk_yetki_site FOREIGN KEY (site_id) REFERENCES siteler (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- gider_kategorileri normalde uygulama tarafından kendiliğinden
-- oluşturuluyor; aşağıda ALTER edileceği için burada varlığı garanti
-- altına alınır (zaten varsa bu ifade hiçbir şey yapmaz).
CREATE TABLE IF NOT EXISTS gider_kategorileri (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    kullanici_id INT UNSIGNED NOT NULL,
    ad VARCHAR(50) NOT NULL,
    olusturma DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_kullanici_kategori (kullanici_id, ad),
    KEY fk_giderkat_kullanici (kullanici_id),
    CONSTRAINT fk_giderkat_kullanici FOREIGN KEY (kullanici_id)
        REFERENCES kullanicilar (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- ─── Veri taşıma: her kullanıcı → aynı id'li bir site ───────
INSERT IGNORE INTO siteler (id, ad, adres, telefon, toplam_daire, olusturma)
SELECT id, apartman_adi, adres, telefon, toplam_daire, olusturma_tarihi FROM kullanicilar;

INSERT IGNORE INTO kullanici_site_yetkileri (kullanici_id, site_id, rol)
SELECT id, id, 'yonetici' FROM kullanicilar;

INSERT IGNORE INTO bloklar (site_id, ad, sira)
SELECT id, 'Ana Blok', 1 FROM siteler;

-- ─── daireler ───────────────────────────────────────────────
ALTER TABLE daireler ADD COLUMN IF NOT EXISTS site_id INT UNSIGNED NULL AFTER kullanici_id;
ALTER TABLE daireler ADD COLUMN IF NOT EXISTS blok_id INT UNSIGNED NULL AFTER site_id;

UPDATE daireler SET site_id = kullanici_id WHERE site_id IS NULL;
UPDATE daireler d JOIN bloklar b ON b.site_id = d.site_id AND b.ad = 'Ana Blok'
   SET d.blok_id = b.id WHERE d.blok_id IS NULL;

-- KRİTİK: uq_kullanici_daire, (kullanici_id, daire_no) üzerinde
-- benzersizlik dayatıyor; iki site yöneten bir kullanıcı ikisinde de
-- "Daire 1" oluşturamazdı. Çoklu sitenin çalışması için kaldırılması ŞART.
--
-- Ancak bu indeks aynı zamanda fk_daire_kullanici yabancı anahtarını
-- taşıyor ("Cannot drop index: needed in a foreign key constraint").
-- Bu yüzden önce FK düşürülür, indeks kaldırılır, kullanici_id için
-- düz (benzersiz olmayan) bir indeks bırakılır.
--
-- fk_daire_kullanici yeniden kurulmaz: artık siteye bağlılığı fk_daire_site
-- sağlıyor. Davranış değişikliği — bir kullanıcı silindiğinde daireleri
-- artık silinmez; site kaydı (ve dolayısıyla veri) korunur, yalnızca o
-- kullanıcının yetkisi düşer. Binanın verisi yöneticiden bağımsızdır.
ALTER TABLE daireler DROP FOREIGN KEY IF EXISTS fk_daire_kullanici;
ALTER TABLE daireler DROP INDEX IF EXISTS uq_kullanici_daire;
ALTER TABLE daireler ADD KEY IF NOT EXISTS ix_daire_kullanici_eski (kullanici_id);
ALTER TABLE daireler ADD UNIQUE KEY IF NOT EXISTS uq_site_daire (site_id, daire_no);
ALTER TABLE daireler ADD CONSTRAINT fk_daire_site
    FOREIGN KEY IF NOT EXISTS (site_id) REFERENCES siteler (id) ON DELETE CASCADE;
ALTER TABLE daireler ADD CONSTRAINT fk_daire_blok
    FOREIGN KEY IF NOT EXISTS (blok_id) REFERENCES bloklar (id) ON DELETE SET NULL;

-- ─── aidatlar ───────────────────────────────────────────────
ALTER TABLE aidatlar ADD COLUMN IF NOT EXISTS site_id INT UNSIGNED NULL AFTER kullanici_id;
UPDATE aidatlar SET site_id = kullanici_id WHERE site_id IS NULL;
ALTER TABLE aidatlar ADD KEY IF NOT EXISTS ix_aidat_site (site_id);
ALTER TABLE aidatlar ADD CONSTRAINT fk_aidat_site
    FOREIGN KEY IF NOT EXISTS (site_id) REFERENCES siteler (id) ON DELETE CASCADE;

-- ─── giderler ───────────────────────────────────────────────
ALTER TABLE giderler ADD COLUMN IF NOT EXISTS site_id INT UNSIGNED NULL AFTER kullanici_id;
UPDATE giderler SET site_id = kullanici_id WHERE site_id IS NULL;
ALTER TABLE giderler ADD KEY IF NOT EXISTS ix_gider_site (site_id);
ALTER TABLE giderler ADD CONSTRAINT fk_gider_site
    FOREIGN KEY IF NOT EXISTS (site_id) REFERENCES siteler (id) ON DELETE CASCADE;

-- ─── gider_kategorileri ─────────────────────────────────────
-- Buradaki uq_kullanici_kategori indeksi FK taşımıyor (FK'nın kendi
-- fk_giderkat_kullanici indeksi var), doğrudan düşürülebilir.
ALTER TABLE gider_kategorileri ADD COLUMN IF NOT EXISTS site_id INT UNSIGNED NULL AFTER kullanici_id;
UPDATE gider_kategorileri SET site_id = kullanici_id WHERE site_id IS NULL;
ALTER TABLE gider_kategorileri DROP INDEX IF EXISTS uq_kullanici_kategori;
ALTER TABLE gider_kategorileri ADD UNIQUE KEY IF NOT EXISTS uq_site_kategori (site_id, ad);
ALTER TABLE gider_kategorileri ADD CONSTRAINT fk_giderkat_site
    FOREIGN KEY IF NOT EXISTS (site_id) REFERENCES siteler (id) ON DELETE CASCADE;

-- ─── Eski kullanici_id sütunları artık isteğe bağlı ─────────
-- Sütunlar geri alınabilirlik için SİLİNMİYOR, ancak yeni kayıtların
-- bunları doldurmak zorunda kalmaması için nullable yapılıyor. Artık
-- veriyi siteye bağlayan alan site_id'dir; kullanici_id yalnızca göç
-- öncesi kayıtlarda tarihsel bilgi olarak kalır.
ALTER TABLE daireler          MODIFY COLUMN kullanici_id INT UNSIGNED NULL;
ALTER TABLE aidatlar          MODIFY COLUMN kullanici_id INT UNSIGNED NULL;
ALTER TABLE giderler          MODIFY COLUMN kullanici_id INT UNSIGNED NULL;
ALTER TABLE gider_kategorileri MODIFY COLUMN kullanici_id INT UNSIGNED NULL;

-- ─── site_id artık zorunlu ──────────────────────────────────
-- Backfill tamamlandığına göre NOT NULL yapılabilir. Bu aynı zamanda
-- bir doğrulama görevi görür: geride site'siz (yetim) satır kalmışsa
-- göç burada yüksek sesle hata verir, sessizce kapsam dışı veri bırakmaz.
ALTER TABLE daireler           MODIFY COLUMN site_id INT UNSIGNED NOT NULL;
ALTER TABLE aidatlar           MODIFY COLUMN site_id INT UNSIGNED NOT NULL;
ALTER TABLE giderler           MODIFY COLUMN site_id INT UNSIGNED NOT NULL;
ALTER TABLE gider_kategorileri MODIFY COLUMN site_id INT UNSIGNED NOT NULL
