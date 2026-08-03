-- ============================================================
-- 001 — Kullanıcı e-postası ve kimlik jetonları
--
-- Amaç: Şifre sıfırlama özelliğinin ön koşulu. Sistemde bugüne kadar
-- kullanıcı e-postası hiç toplanmadı (kayıt formunda alan yoktu), bu
-- yüzden mevcut satırlarda eposta NULL kalır ve uygulama kullanıcıdan
-- adres ister.
--
-- eposta üzerindeki UNIQUE indeks nullable sütunda tanımlıdır; MySQL/
-- MariaDB birden çok NULL'a izin verir, dolayısıyla mevcut kayıtlar
-- çakışmaz ama iki hesap aynı adresi kullanamaz.
-- ============================================================

ALTER TABLE kullanicilar
  ADD COLUMN IF NOT EXISTS eposta VARCHAR(190) DEFAULT NULL AFTER kullanici_adi;

ALTER TABLE kullanicilar
  ADD COLUMN IF NOT EXISTS eposta_dogrulandi TINYINT(1) NOT NULL DEFAULT 0 AFTER eposta;

CREATE UNIQUE INDEX IF NOT EXISTS uq_kullanicilar_eposta ON kullanicilar (eposta);

-- Şifre sıfırlama ve e-posta doğrulama jetonları tek tabloda tutulur;
-- yaşam döngüleri aynıdır (tek kullanımlık, süreli, seçici/doğrulayıcı).
--
-- Güvenlik deseni: hatirlama_jetonlari ile aynı — bağlantıda açıkça
-- duran "secici" ile satır bulunur, gizli "dogrulayici" ise veritabanına
-- ASLA düz metin yazılmaz, yalnızca SHA-256 hash'i saklanır ve
-- hash_equals ile karşılaştırılır.
CREATE TABLE IF NOT EXISTS kimlik_jetonlari (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    kullanici_id INT UNSIGNED NOT NULL,
    tur ENUM('sifre_sifirlama','eposta_dogrulama') NOT NULL,
    secici VARCHAR(24) NOT NULL,
    dogrulayici_hash VARCHAR(64) NOT NULL,
    son_kullanim DATETIME NOT NULL,
    kullanildi TINYINT(1) NOT NULL DEFAULT 0,
    ip_adresi VARCHAR(45) DEFAULT NULL,
    olusturma DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_kimlik_secici (secici),
    KEY ix_kimlik_kullanici_tur (kullanici_id, tur),
    CONSTRAINT fk_kimlik_kullanici FOREIGN KEY (kullanici_id)
        REFERENCES kullanicilar (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci
