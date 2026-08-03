-- ============================================================
-- 006 — Karar defteri, belge arşivi, resmi bilanço altyapısı
--
-- İKİ AYRI İHTİYAÇ, TEK GÖÇTE:
--
-- 1) Karar defteri / belge yönetimi (dijital arşiv)
--    ÖNEMLİ: Kat Mülkiyeti Kanunu'na göre karar defteri NOTER TASDİKLİ
--    FİZİKSEL DEFTER olarak tutulur. Bu modül yasal aslın YERİNE
--    GEÇMEZ; amacı dijital arşiv ve kolay erişimdir. Bu ayrım
--    arayüzde de açıkça belirtilir (kararlar.php, belgeler.php).
--
-- 2) Resmi bilanço / yıl sonu kapanış raporu için eksik veri modeli
--    Mevcut sistemde yalnızca AİDAT geliri ve GİDER var. Bir bilanço
--    hazırlamak için iki şey daha gerekiyordu:
--      a) donem_acilis_bakiye — yıl başında devreden bakiye
--      b) gelirler — aidat dışı gelirler (kira, gecikme cezası,
--         bağış, demirbaş satışı)
--    Bunlar olmadan hazırlanan bir bilanço eksik olurdu.
-- ============================================================

-- ─── Kararlar (karar defteri) ───────────────────────────────
-- karar_no site içinde benzersizdir; sayfada "2026/3" gibi önerilir
-- ama serbest metindir (mevcut fiziksel deftere göre elle de girilebilir).
CREATE TABLE IF NOT EXISTS kararlar (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    site_id INT UNSIGNED NOT NULL,
    karar_no VARCHAR(30) NOT NULL,
    toplanti_tarihi DATE NOT NULL,
    toplanti_turu ENUM('olagan_genel_kurul','olaganustu_genel_kurul','yonetim_kurulu','diger')
                  NOT NULL DEFAULT 'diger',
    baslik VARCHAR(200) NOT NULL,
    karar_metni TEXT NOT NULL,
    katilim_orani DECIMAL(5,2) DEFAULT NULL,
    lehte SMALLINT UNSIGNED DEFAULT NULL,
    aleyhte SMALLINT UNSIGNED DEFAULT NULL,
    cekimser SMALLINT UNSIGNED DEFAULT NULL,
    olusturan_id INT UNSIGNED DEFAULT NULL,
    olusturma DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_site_karar_no (site_id, karar_no),
    KEY ix_karar_site_tarih (site_id, toplanti_tarihi),
    CONSTRAINT fk_karar_site FOREIGN KEY (site_id)
        REFERENCES siteler (id) ON DELETE CASCADE,
    CONSTRAINT fk_karar_olusturan FOREIGN KEY (olusturan_id)
        REFERENCES kullanicilar (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- ─── Belgeler (genel arşiv) ──────────────────────────────────
-- Dosyalar web kökünün DIŞINDA saklanır (includes/dosya.php);
-- burada yalnızca künye ve göreli yol tutulur. karar_id doluysa
-- belge o kararla ilişkilendirilmiştir (ör. toplantı tutanağının
-- taranmış hali); NULL ise bağımsız bir arşiv belgesidir
-- (yönetim planı, sigorta poliçesi, ruhsat gibi).
CREATE TABLE IF NOT EXISTS belgeler (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    site_id INT UNSIGNED NOT NULL,
    karar_id INT UNSIGNED DEFAULT NULL,
    tur ENUM('karar_defteri','yonetim_plani','genel_kurul_tutanagi','sozlesme',
             'sigorta_policesi','ruhsat','bakim_raporu','diger')
        NOT NULL DEFAULT 'diger',
    baslik VARCHAR(200) NOT NULL,
    aciklama TEXT DEFAULT NULL,
    yol VARCHAR(255) NOT NULL,
    orijinal_ad VARCHAR(190) NOT NULL,
    mime VARCHAR(120) DEFAULT NULL,
    boyut INT UNSIGNED DEFAULT NULL,
    yukleyen_id INT UNSIGNED DEFAULT NULL,
    olusturma DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_belge_site_tur (site_id, tur),
    KEY ix_belge_karar (karar_id),
    CONSTRAINT fk_belge_site FOREIGN KEY (site_id)
        REFERENCES siteler (id) ON DELETE CASCADE,
    CONSTRAINT fk_belge_karar FOREIGN KEY (karar_id)
        REFERENCES kararlar (id) ON DELETE SET NULL,
    CONSTRAINT fk_belge_yukleyen FOREIGN KEY (yukleyen_id)
        REFERENCES kullanicilar (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- Metin arama için: başlık + açıklama + karar metni üzerinde basit
-- LIKE araması yeterli (site başına belge/karar hacmi düşük); FULLTEXT
-- indeks gerektirecek ölçek bu projede beklenmiyor.

-- ─── Açılış / devir bakiyesi ─────────────────────────────────
-- Yıl başında kasada/bankada devreden tutar. Bilanço raporunun
-- başlangıç noktasıdır; olmadan "dönem sonu bakiye" rakamı havada
-- kalır. site_id + yil birlikte anahtar: bir site her yıl için tek
-- açılış bakiyesi girer.
CREATE TABLE IF NOT EXISTS donem_acilis_bakiye (
    site_id INT UNSIGNED NOT NULL,
    yil SMALLINT UNSIGNED NOT NULL,
    tutar DECIMAL(12,2) NOT NULL DEFAULT 0,
    aciklama VARCHAR(255) DEFAULT NULL,
    olusturan_id INT UNSIGNED DEFAULT NULL,
    guncelleme DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (site_id, yil),
    CONSTRAINT fk_acilis_site FOREIGN KEY (site_id)
        REFERENCES siteler (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- ─── Aidat dışı gelirler ─────────────────────────────────────
-- Kira (çatı/cephe/dükkân), gecikme cezası, bağış, demirbaş satışı.
-- giderler tablosunun gelir yönündeki karşılığı; aynı desende
-- (site_id, donem, tarih) tutulur ki raporlama sorguları simetrik kalsın.
CREATE TABLE IF NOT EXISTS gelirler (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    site_id INT UNSIGNED NOT NULL,
    tur ENUM('kira','gecikme_cezasi','bagis','demirbas_satisi','diger')
        NOT NULL DEFAULT 'diger',
    aciklama VARCHAR(255) NOT NULL,
    tutar DECIMAL(10,2) NOT NULL DEFAULT 0,
    tarih DATE NOT NULL,
    donem VARCHAR(7) NOT NULL,
    dekont_no VARCHAR(50) DEFAULT NULL,
    olusturma DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_gelir_site_donem (site_id, donem),
    CONSTRAINT fk_gelir_site FOREIGN KEY (site_id)
        REFERENCES siteler (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
