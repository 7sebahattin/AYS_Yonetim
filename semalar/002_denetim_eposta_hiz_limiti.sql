-- ============================================================
-- 002 — Denetim kaydı, e-posta günlüğü ve hız sınırlama
--
-- Üçü de altyapı tablosu: kullanıcıya doğrudan görünmezler ama
-- güvenlik, teşhis ve kötüye kullanım engelleme için gerekli.
-- ============================================================

-- Kim, ne zaman, neyi değiştirdi. Faz 3'teki süper admin panelinin
-- ("kullanıcı adına görüntüleme" gibi hassas yetkiler) denetlenebilir
-- olması için zorunlu; şimdilik kimlik olayları (giriş, şifre değişimi)
-- yazılıyor.
--
-- kullanici_id NULL olabilir: başarısız giriş denemesi gibi olaylarda
-- henüz kimlik doğrulanmamış olur. ON DELETE SET NULL ile kullanıcı
-- silinse bile denetim izi korunur.
CREATE TABLE IF NOT EXISTS denetim_kaydi (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    kullanici_id INT UNSIGNED DEFAULT NULL,
    eylem VARCHAR(60) NOT NULL,
    hedef_tur VARCHAR(40) DEFAULT NULL,
    hedef_id INT UNSIGNED DEFAULT NULL,
    detay TEXT DEFAULT NULL,
    ip_adresi VARCHAR(45) DEFAULT NULL,
    olusturma DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_denetim_kullanici (kullanici_id),
    KEY ix_denetim_eylem (eylem),
    KEY ix_denetim_olusturma (olusturma),
    CONSTRAINT fk_denetim_kullanici FOREIGN KEY (kullanici_id)
        REFERENCES kullanicilar (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- Gönderilen e-postaların kaydı. "Sıfırlama e-postası gelmedi"
-- şikayetini teşhis edebilmek için şart: gönderim denendi mi, SMTP ne
-- dedi? Jeton veya şifre gibi gizli içerik SAKLANMAZ, yalnızca konu.
CREATE TABLE IF NOT EXISTS eposta_kaydi (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    alici VARCHAR(190) NOT NULL,
    konu VARCHAR(255) NOT NULL,
    sablon VARCHAR(60) DEFAULT NULL,
    durum ENUM('basarili','hata') NOT NULL,
    hata_mesaji TEXT DEFAULT NULL,
    olusturma DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_eposta_alici (alici),
    KEY ix_eposta_olusturma (olusturma)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- Kaba kuvvet ve e-posta bombardımanı engelleme. Anahtar örneği:
-- "sifre_sifirlama:ip:1.2.3.4" veya "sifre_sifirlama:eposta:<sha256>"
-- (adresin kendisi düz metin yazılmaz).
CREATE TABLE IF NOT EXISTS hiz_limiti (
    anahtar VARCHAR(190) NOT NULL,
    sayac INT UNSIGNED NOT NULL DEFAULT 0,
    pencere_baslangic DATETIME NOT NULL,
    PRIMARY KEY (anahtar)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci
