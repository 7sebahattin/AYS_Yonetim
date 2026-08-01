<?php
// ============================================================
//  config.php — VERİTABANI BAĞLANTI AYARLARI
//  Bu dosyayı sunucunuza göre düzenleyin
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'veritabani_adi');
define('DB_USER', 'veritabani_kullanici');
define('DB_PASS', 'veritabani_sifresi');
define('DB_CHARSET', 'utf8mb4');

define('SITE_ADI', 'ApartYönet');
define('SESSION_SURE', 7200);         // 2 saat (saniye cinsinden)

// ─── PDO Bağlantısı ─────────────────────────────────────────
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die('<div style="font-family:sans-serif;padding:40px;color:#c0392b">
                <h2>Veritabanı Bağlantı Hatası</h2>
                <p>config.php dosyasındaki DB_HOST, DB_NAME, DB_USER, DB_PASS değerlerini kontrol edin.</p>
                <code>' . htmlspecialchars($e->getMessage()) . '</code>
            </div>');
        }
    }
    return $pdo;
}
