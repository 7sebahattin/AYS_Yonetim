-- ============================================================
-- 005 — Operasyonel modüller: talep, demirbaş/bakım, personel
--
-- Üçü aynı deseni paylaşır (site kapsamlı, blok/daire ilişkili,
-- giderlerle bağlantılı), bu yüzden tek göçte toplandı.
--
-- ORTAK KURAL: her tablo site_id taşır ve fk ile siteye bağlıdır
-- (ON DELETE CASCADE). Kiracı izolasyonu Faz 2'deki modelin aynısıdır;
-- sorgular $kullanici['site_id'] ile filtrelenir.
-- ============================================================

-- ─── giderler: kaynak izleme ────────────────────────────────
-- Bakım ve personel ödemeleri otomatik olarak bir gider satırı
-- üretir. Bu satırın elle silinmesi/düzenlenmesi ENGELLENİR, aksi
-- halde iki kayıt birbirinden ayrışır ve aynı harcama ya iki kez
-- sayılır ya da hiç görünmez.
--
-- kaynak_tur NULL = elle girilmiş normal gider (mevcut davranış).
ALTER TABLE giderler
  ADD COLUMN IF NOT EXISTS kaynak_tur ENUM('bakim','personel','talep') DEFAULT NULL;
ALTER TABLE giderler
  ADD COLUMN IF NOT EXISTS kaynak_id INT UNSIGNED DEFAULT NULL;

CREATE INDEX IF NOT EXISTS ix_gider_kaynak ON giderler (kaynak_tur, kaynak_id);

-- ─── Personel ───────────────────────────────────────────────
-- KVKK notu: kimlik numarası, SGK sicili gibi hassas alanlar
-- BİLİNÇLİ OLARAK YOK. Gerçekten gerekmedikçe toplanmamalı;
-- toplanırsa erişim kısıtı ve saklama süresi tanımlanması gerekir.
CREATE TABLE IF NOT EXISTS personel (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    site_id INT UNSIGNED NOT NULL,
    ad_soyad VARCHAR(120) NOT NULL,
    gorev ENUM('kapici','guvenlik','temizlik','teknisyen','bahcivan','diger')
          NOT NULL DEFAULT 'kapici',
    telefon VARCHAR(20) DEFAULT NULL,
    baslama_tarihi DATE DEFAULT NULL,
    ayrilma_tarihi DATE DEFAULT NULL,
    aylik_ucret DECIMAL(10,2) NOT NULL DEFAULT 0,
    notlar TEXT DEFAULT NULL,
    durum ENUM('aktif','ayrildi') NOT NULL DEFAULT 'aktif',
    olusturma DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_personel_site (site_id, durum),
    CONSTRAINT fk_personel_site FOREIGN KEY (site_id)
        REFERENCES siteler (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- Ödeme kaydı silinirse ona bağlı gider satırı da silinmeli; bu yüzden
-- gider tarafında değil, ödeme tarafında uygulama mantığı ile temizlenir
-- (giderler.kaynak_id üzerinde FK yok: gider satırı elle de var olabilir).
CREATE TABLE IF NOT EXISTS personel_odemeleri (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    site_id INT UNSIGNED NOT NULL,
    personel_id INT UNSIGNED NOT NULL,
    donem VARCHAR(7) NOT NULL,
    tur ENUM('maas','avans','ikramiye','sgk','diger') NOT NULL DEFAULT 'maas',
    tutar DECIMAL(10,2) NOT NULL DEFAULT 0,
    odeme_tarihi DATE NOT NULL,
    aciklama VARCHAR(255) DEFAULT NULL,
    gider_id INT UNSIGNED DEFAULT NULL,
    olusturma DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_odeme_site_donem (site_id, donem),
    KEY ix_odeme_personel (personel_id),
    CONSTRAINT fk_odeme_site FOREIGN KEY (site_id)
        REFERENCES siteler (id) ON DELETE CASCADE,
    CONSTRAINT fk_odeme_personel FOREIGN KEY (personel_id)
        REFERENCES personel (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- ─── Talepler (arıza / istek) ───────────────────────────────
-- 1. aşama: yönetici/personel açar (telefonla gelen arızayı girer).
-- Sakin girişi gerektirmez; bugünkü iş akışına uyar.
--
-- blok_id ve daire_id ON DELETE SET NULL: bir daire silindiğinde
-- geçmiş talep kaydı kaybolmamalı, yalnızca bağlantısı düşmeli.
CREATE TABLE IF NOT EXISTS talepler (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    site_id INT UNSIGNED NOT NULL,
    blok_id INT UNSIGNED DEFAULT NULL,
    daire_id INT UNSIGNED DEFAULT NULL,
    baslik VARCHAR(160) NOT NULL,
    aciklama TEXT DEFAULT NULL,
    kategori ENUM('asansor','su_tesisat','elektrik','isitma','temizlik',
                  'guvenlik','ortak_alan','diger') NOT NULL DEFAULT 'diger',
    oncelik ENUM('dusuk','normal','yuksek','acil') NOT NULL DEFAULT 'normal',
    durum ENUM('acik','islemde','beklemede','cozuldu','kapali','iptal')
          NOT NULL DEFAULT 'acik',
    atanan_personel_id INT UNSIGNED DEFAULT NULL,
    acan_kullanici_id INT UNSIGNED DEFAULT NULL,
    bildiren VARCHAR(120) DEFAULT NULL,
    maliyet DECIMAL(10,2) DEFAULT NULL,
    gider_id INT UNSIGNED DEFAULT NULL,
    acilis DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    kapanis DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY ix_talep_site_durum (site_id, durum),
    KEY ix_talep_personel (atanan_personel_id),
    CONSTRAINT fk_talep_site FOREIGN KEY (site_id)
        REFERENCES siteler (id) ON DELETE CASCADE,
    CONSTRAINT fk_talep_blok FOREIGN KEY (blok_id)
        REFERENCES bloklar (id) ON DELETE SET NULL,
    CONSTRAINT fk_talep_daire FOREIGN KEY (daire_id)
        REFERENCES daireler (id) ON DELETE SET NULL,
    CONSTRAINT fk_talep_personel FOREIGN KEY (atanan_personel_id)
        REFERENCES personel (id) ON DELETE SET NULL,
    CONSTRAINT fk_talep_acan FOREIGN KEY (acan_kullanici_id)
        REFERENCES kullanicilar (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- İşlem geçmişi: hem serbest yorum hem durum değişikliği kaydı.
-- durum_eski/durum_yeni dolu olan satırlar otomatik üretilir; böylece
-- "bu talep ne zaman kim tarafından çözüldü?" sorusu cevaplanabilir.
CREATE TABLE IF NOT EXISTS talep_yorumlari (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    talep_id INT UNSIGNED NOT NULL,
    kullanici_id INT UNSIGNED DEFAULT NULL,
    yorum TEXT DEFAULT NULL,
    durum_eski VARCHAR(20) DEFAULT NULL,
    durum_yeni VARCHAR(20) DEFAULT NULL,
    olusturma DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_yorum_talep (talep_id),
    CONSTRAINT fk_yorum_talep FOREIGN KEY (talep_id)
        REFERENCES talepler (id) ON DELETE CASCADE,
    CONSTRAINT fk_yorum_kullanici FOREIGN KEY (kullanici_id)
        REFERENCES kullanicilar (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- ─── Demirbaşlar ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS demirbaslar (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    site_id INT UNSIGNED NOT NULL,
    blok_id INT UNSIGNED DEFAULT NULL,
    ad VARCHAR(140) NOT NULL,
    kategori ENUM('asansor','yangin','jenerator','hidrofor','kazan','paratoner',
                  'kamera','otopark','peyzaj','diger') NOT NULL DEFAULT 'diger',
    marka_model VARCHAR(140) DEFAULT NULL,
    seri_no VARCHAR(80) DEFAULT NULL,
    konum VARCHAR(140) DEFAULT NULL,
    alim_tarihi DATE DEFAULT NULL,
    alim_bedeli DECIMAL(10,2) DEFAULT NULL,
    garanti_bitisi DATE DEFAULT NULL,
    durum ENUM('aktif','arizali','hurda') NOT NULL DEFAULT 'aktif',
    notlar TEXT DEFAULT NULL,
    olusturma DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_demirbas_site (site_id, durum),
    CONSTRAINT fk_demirbas_site FOREIGN KEY (site_id)
        REFERENCES siteler (id) ON DELETE CASCADE,
    CONSTRAINT fk_demirbas_blok FOREIGN KEY (blok_id)
        REFERENCES bloklar (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- Bakım kayıtları. MODÜLÜN ASIL DEĞERİ BURADA:
-- asansör yıllık muayenesi, yangın tüpü dolum kontrolü, jeneratör ve
-- paratoner ölçümü gibi kontroller YASAL ZORUNLULUKTUR; kaçırılması
-- ciddi sorumluluk doğurur. planlanan_tarih + periyot_ay ile
-- hatırlatma üretilir.
--
-- Bir bakım "yapıldı" işaretlenince uygulama, periyoda göre bir
-- sonraki planlı kaydı otomatik oluşturur — zincir kopmasın diye.
CREATE TABLE IF NOT EXISTS bakimlar (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    site_id INT UNSIGNED NOT NULL,
    demirbas_id INT UNSIGNED NOT NULL,
    tur ENUM('periyodik','yasal_muayene','ariza','montaj','diger')
        NOT NULL DEFAULT 'periyodik',
    baslik VARCHAR(160) DEFAULT NULL,
    planlanan_tarih DATE DEFAULT NULL,
    yapilan_tarih DATE DEFAULT NULL,
    periyot_ay SMALLINT UNSIGNED DEFAULT NULL,
    firma VARCHAR(140) DEFAULT NULL,
    tutar DECIMAL(10,2) DEFAULT NULL,
    sonuc TEXT DEFAULT NULL,
    durum ENUM('planlandi','yapildi','iptal') NOT NULL DEFAULT 'planlandi',
    hatirlatma_gonderildi TINYINT(1) NOT NULL DEFAULT 0,
    gider_id INT UNSIGNED DEFAULT NULL,
    olusturma DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_bakim_site_durum (site_id, durum, planlanan_tarih),
    KEY ix_bakim_demirbas (demirbas_id),
    CONSTRAINT fk_bakim_site FOREIGN KEY (site_id)
        REFERENCES siteler (id) ON DELETE CASCADE,
    CONSTRAINT fk_bakim_demirbas FOREIGN KEY (demirbas_id)
        REFERENCES demirbaslar (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- ─── Ekler (talep fotoğrafı, bakım raporu, garanti belgesi) ──
-- Dosyalar web kökünün DIŞINDA saklanır (includes/dosya.php);
-- burada yalnızca göreli yol ve künye tutulur. İndirme, yetki
-- kontrolü yapan belge_indir.php üzerinden yapılır.
CREATE TABLE IF NOT EXISTS ekler (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    site_id INT UNSIGNED NOT NULL,
    hedef_tur ENUM('talep','bakim','demirbas','personel') NOT NULL,
    hedef_id INT UNSIGNED NOT NULL,
    yol VARCHAR(255) NOT NULL,
    orijinal_ad VARCHAR(190) NOT NULL,
    mime VARCHAR(120) DEFAULT NULL,
    boyut INT UNSIGNED DEFAULT NULL,
    yukleyen_id INT UNSIGNED DEFAULT NULL,
    olusturma DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_ek_hedef (hedef_tur, hedef_id),
    KEY ix_ek_site (site_id),
    CONSTRAINT fk_ek_site FOREIGN KEY (site_id)
        REFERENCES siteler (id) ON DELETE CASCADE,
    CONSTRAINT fk_ek_yukleyen FOREIGN KEY (yukleyen_id)
        REFERENCES kullanicilar (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
