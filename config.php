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
define('SESSION_SURE', 300);          // 5 dakika hareketsizlikte oturum sonlanır (saniye cinsinden)
                                       // "Beni Hatırla" ile giriş yapan kullanıcılar bu sınırdan
                                       // etkilenmez; oturum jetonuyla sessizce yenilenir.

// ─── Site adresi ────────────────────────────────────────────
// E-postalardaki bağlantılar bununla kurulur.
define('SITE_ADRESI', 'https://ays.derspros.com.tr');

// ─── E-posta (SMTP) ─────────────────────────────────────────
// Şifre sıfırlama e-postaları buradan gönderilir. Boş bırakılırsa
// e-posta gönderimi devre dışı kalır (sistem çalışmaya devam eder,
// arayüz "yapılandırılmamış" uyarısı gösterir).
//
// cPanel'de bir e-posta hesabı oluşturup (ör. noreply@alanadiniz.com)
// bilgilerini buraya girin. PHP'nin mail() fonksiyonu KULLANILMIYOR;
// kimliği doğrulanmış SMTP kullanılıyor çünkü paylaşımlı hostingde
// mail() ile gönderilen iletiler büyük oranda spam'e düşer.
//
// ÖNEMLİ: SMTP tek başına yetmez — alan adına SPF ve DKIM DNS
// kayıtlarını da eklemezseniz iletiler yine spam'e düşebilir.
define('SMTP_HOST', '');                       // ör. mail.alanadiniz.com
define('SMTP_PORT', 587);                      // 587 (TLS) veya 465 (SSL)
define('SMTP_KULLANICI', '');                  // ör. noreply@alanadiniz.com
define('SMTP_SIFRE', '');
define('SMTP_GUVENLIK', 'tls');                // tls | ssl | yok
define('EPOSTA_GONDEREN', '');                 // ör. noreply@alanadiniz.com
define('EPOSTA_GONDEREN_AD', 'AYS — Apartman Yönetim Sistemi');

// ─── Şema göçü (migration) anahtarı ─────────────────────────
// Web üzerinden göç çalıştırmak için gereken gizli anahtar.
// Boş bırakılırsa web arayüzünden göç çalıştırılamaz.
// Uzun ve rastgele olmalı, ör: bin2hex(random_bytes(24)) çıktısı.
define('GOC_ANAHTARI', '');

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
            error_log('DB bağlantı hatası: ' . $e->getMessage());
            http_response_code(500);
            die('<div style="font-family:sans-serif;padding:40px;color:#c0392b">
                <h2>Veritabanı Bağlantı Hatası</h2>
                <p>Sunucu şu anda hizmet veremiyor. Lütfen daha sonra tekrar deneyin ya da yönetici ile iletişime geçin.</p>
            </div>');
        }
    }
    return $pdo;
}
