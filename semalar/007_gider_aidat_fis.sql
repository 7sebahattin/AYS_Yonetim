-- ============================================================
-- 007 — Gider fişi/faturası ve aidat dekontu görseli
--
-- 'ekler' tablosu Faz 4'te talep/bakim/demirbas/personel için
-- kurulmuştu (005_operasyonel_moduller.sql). Aynı altyapı burada
-- YENİDEN KULLANILIR: yeni tablo yerine hedef_tur ENUM'una 'gider'
-- ve 'aidat' eklenir. Dosyalar yine web kökü dışında saklanır ve
-- belge_indir.php üzerinden, site_id doğrulamasıyla sunulur.
--
-- MODIFY COLUMN doğası gereği idempotenttir (ADD COLUMN'un aksine
-- IF NOT EXISTS gerekmez) — aynı tanımın tekrar uygulanması zararsızdır.
-- ============================================================

ALTER TABLE ekler
    MODIFY COLUMN hedef_tur ENUM('talep','bakim','demirbas','personel','gider','aidat') NOT NULL;
