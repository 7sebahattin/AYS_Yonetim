-- ============================================================
-- 004 — Platform yönetimi (süper admin katmanı)
--
-- SORUN: Sistemde yalnızca "kiracı" düzeyi var. Platform sahibi
-- olarak tüm hesapları, siteleri, tanıtım sayfası içeriğini veya
-- bakım modunu yönetebileceğimiz bir katman yok; bir sorunu teşhis
-- etmek için veritabanına elle girmek gerekiyor.
--
-- ÇÖZÜM: Site içi rollerden (yonetici/muhasebe/denetci — Faz 2)
-- BAĞIMSIZ bir platform rolü ve onu destekleyen tablolar.
--
-- GÜVENLİK NOTU: Bu göç, uygulandığında hiç kimseye süper admin
-- yetkisi VERMEZ. İlk süper admin bilinçli bir adımla atanır:
--   php araclar/superadmin_ata.php <kullanici_adi>
-- veya /yonetim/kurulum.php (yalnızca hiç süper admin yokken ve
-- GOC_ANAHTARI ile). Göçün sessizce id=1'i yetkilendirmesi, kimin
-- ne zaman yetkilendiği sorusunu denetlenemez hale getirirdi.
-- ============================================================

-- ─── Platform rolü ve giriş izleme ──────────────────────────
-- 'destek': salt-okunur panel erişimi (kimliğe bürünme dahil, yazma yok)
-- 'superadmin': tam yetki
ALTER TABLE kullanicilar
  ADD COLUMN IF NOT EXISTS platform_rolu ENUM('kullanici','destek','superadmin')
  NOT NULL DEFAULT 'kullanici' AFTER eposta_dogrulandi;

ALTER TABLE kullanicilar
  ADD COLUMN IF NOT EXISTS son_giris DATETIME DEFAULT NULL;

CREATE INDEX IF NOT EXISTS ix_kullanici_platform_rolu ON kullanicilar (platform_rolu);

-- ─── İki faktörlü kimlik doğrulama (TOTP) ───────────────────
-- Panel tüm kiracıların mali ve kişisel verisine erişir; tek şifreyle
-- korunması kabul edilebilir değil. Gizli anahtar base32 olarak saklanır.
--
-- totp_son_adim: doğrulanan son zaman adımı. Aynı 6 haneli kod ikinci
-- kez kabul edilmez — omuz sörfü / ağ dinlemesiyle yakalanan bir kodun
-- 30 saniyelik pencerede tekrar kullanılmasını engeller.
ALTER TABLE kullanicilar
  ADD COLUMN IF NOT EXISTS totp_gizli VARCHAR(64) DEFAULT NULL;

ALTER TABLE kullanicilar
  ADD COLUMN IF NOT EXISTS totp_aktif TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE kullanicilar
  ADD COLUMN IF NOT EXISTS totp_son_adim BIGINT UNSIGNED DEFAULT NULL;

-- Telefon kaybedildiğinde tek kullanımlık kurtarma kodları.
-- Kodun kendisi saklanmaz, yalnızca hash'i (şifre gibi davranılır).
CREATE TABLE IF NOT EXISTS totp_yedek_kodlari (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    kullanici_id INT UNSIGNED NOT NULL,
    kod_hash VARCHAR(255) NOT NULL,
    kullanildi TINYINT(1) NOT NULL DEFAULT 0,
    kullanim_zamani DATETIME DEFAULT NULL,
    olusturma DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_yedek_kullanici (kullanici_id),
    CONSTRAINT fk_yedek_kullanici FOREIGN KEY (kullanici_id)
        REFERENCES kullanicilar (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- ─── Platform ayarları (anahtar/değer) ──────────────────────
-- Bakım modu, IP kısıtı gibi ayarlar config.php'de tutulamaz:
-- config.php sunucuya deploy EDİLMEZ, dolayısıyla panelden
-- değiştirilebilir bir ayarın veritabanında olması gerekir.
CREATE TABLE IF NOT EXISTS platform_ayarlari (
    anahtar VARCHAR(60) NOT NULL,
    deger TEXT DEFAULT NULL,
    guncelleyen INT UNSIGNED DEFAULT NULL,
    guncelleme DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (anahtar)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

INSERT IGNORE INTO platform_ayarlari (anahtar, deger) VALUES
    ('bakim_modu',           '0'),
    ('bakim_mesaji',         'Sistem kısa süreli bakımda. Kısa süre içinde tekrar hizmetinizdeyiz.'),
    ('yonetim_ip_listesi',   ''),
    ('burunme_yazma_izni',   '0');

-- ─── Sistem duyuruları ──────────────────────────────────────
-- Tüm panellerin üstünde görünen bant (bakım bildirimi, yeni özellik).
-- site_id NULL ise duyuru tüm sitelere gider.
CREATE TABLE IF NOT EXISTS duyurular (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    baslik VARCHAR(150) NOT NULL,
    mesaj TEXT NOT NULL,
    tip ENUM('bilgi','uyari','bakim') NOT NULL DEFAULT 'bilgi',
    site_id INT UNSIGNED DEFAULT NULL,
    baslangic DATETIME DEFAULT NULL,
    bitis DATETIME DEFAULT NULL,
    durum ENUM('aktif','pasif') NOT NULL DEFAULT 'aktif',
    olusturan INT UNSIGNED DEFAULT NULL,
    olusturma DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_duyuru_durum (durum, baslangic, bitis),
    CONSTRAINT fk_duyuru_site FOREIGN KEY (site_id)
        REFERENCES siteler (id) ON DELETE CASCADE,
    CONSTRAINT fk_duyuru_olusturan FOREIGN KEY (olusturan)
        REFERENCES kullanicilar (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- ─── Tanıtım sayfası içeriği ────────────────────────────────
-- Metinler bugüne kadar index.php içine gömülüydü; bir yazım
-- düzeltmesi için bile deploy gerekiyordu. Panelden düzenlenebilmesi
-- için veritabanına taşınıyor.
--
-- Kod, kayıt bulunmadığında gömülü varsayılan metne düşer — tablo boş
-- kalsa bile tanıtım sayfası eksiksiz görünür.
CREATE TABLE IF NOT EXISTS icerik_bloklari (
    anahtar VARCHAR(60) NOT NULL,
    baslik VARCHAR(200) DEFAULT NULL,
    govde TEXT DEFAULT NULL,
    guncelleme DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (anahtar)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- SSS ayrı tablo: sıralanabilir ve tek tek eklenip çıkarılabilir olmalı.
-- İçerik hem sayfada hem FAQPage yapısal verisinde tek kaynaktan kullanılır;
-- HTML ile schema arasındaki uyuşmazlık Google tarafından cezalandırılır.
CREATE TABLE IF NOT EXISTS sss_kayitlari (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    soru VARCHAR(255) NOT NULL,
    cevap TEXT NOT NULL,
    sira SMALLINT NOT NULL DEFAULT 1,
    durum ENUM('aktif','pasif') NOT NULL DEFAULT 'aktif',
    guncelleme DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_sss_sira (durum, sira)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- ─── Denetim kaydına site bağlamı ───────────────────────────
-- "Bu apartmanda ne oldu?" sorusunu cevaplayabilmek için. NULL,
-- olayın siteye bağlı olmadığı anlamına gelir (giriş, platform ayarı).
ALTER TABLE denetim_kaydi
  ADD COLUMN IF NOT EXISTS site_id INT UNSIGNED DEFAULT NULL AFTER kullanici_id;

CREATE INDEX IF NOT EXISTS ix_denetim_site ON denetim_kaydi (site_id);
