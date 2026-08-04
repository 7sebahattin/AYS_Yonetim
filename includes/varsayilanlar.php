<?php
// ============================================================
//  includes/varsayilanlar.php — YAPILANDIRMA VARSAYILANLARI
//
//  config.php sunucuya deploy EDİLMEZ (gerçek şifreler orada durur).
//  Dolayısıyla koda yeni bir ayar sabiti eklendiğinde, sunucudaki eski
//  config.php'de o sabit bulunmaz ve "undefined constant" ölümcül
//  hatası oluşur.
//
//  Bu dosya deploy EDİLİR ve config.php'de tanımlanmamış her sabite
//  güvenli bir varsayılan verir. Böylece yeni bir özellik canlıya
//  çıktığında sistem çökmez; ilgili özellik sadece "yapılandırılmamış"
//  durumda kalır ve arayüzde bu bilgi gösterilir.
//
//  Yeni bir ayar eklerken: önce buraya varsayılanını yaz.
// ============================================================

// ─── E-posta (SMTP) ─────────────────────────────────────────
// Boş bırakılırsa e-posta gönderimi devre dışı kalır (hata vermez).
defined('SMTP_HOST')          || define('SMTP_HOST', '');
defined('SMTP_PORT')          || define('SMTP_PORT', 587);
defined('SMTP_KULLANICI')     || define('SMTP_KULLANICI', '');
defined('SMTP_SIFRE')         || define('SMTP_SIFRE', '');
defined('SMTP_GUVENLIK')      || define('SMTP_GUVENLIK', 'tls'); // tls | ssl | yok
defined('EPOSTA_GONDEREN')    || define('EPOSTA_GONDEREN', '');
defined('EPOSTA_GONDEREN_AD') || define('EPOSTA_GONDEREN_AD', 'AYS — Apartman Yönetim Sistemi');

// ─── Site adresi ────────────────────────────────────────────
// E-postalardaki bağlantılar bununla kurulur. HTTP_HOST kullanılmaz:
// host başlığı istemci tarafından değiştirilebildiği için sıfırlama
// bağlantısı saldırganın alan adına yönlendirilebilirdi.
defined('SITE_ADRESI')        || define('SITE_ADRESI', 'https://ays.derspros.com.tr');

// ─── Şema göçü (migration) ──────────────────────────────────
// Web üzerinden göç çalıştırmak için gereken anahtar. Boşsa web
// arayüzü göç çalıştırmayı reddeder (yalnızca CLI kalır).
defined('GOC_ANAHTARI')       || define('GOC_ANAHTARI', '');

// ─── Dosya saklama ──────────────────────────────────────────
// Yüklenen belgeler web kökünün DIŞINDA saklanır; içerik kişisel ve
// mali veri barındırdığı için doğrudan URL ile erişilebilir olmamalı.
// Varsayılan: proje kökünün bir üstündeki ays_dosyalar/ klasörü
// (cPanel'de /home/derspros/ays_dosyalar — public_html'in dışı).
defined('DOSYA_KOK')          || define('DOSYA_KOK', dirname(__DIR__, 2) . '/ays_dosyalar');
defined('DOSYA_MAX_BOYUT')    || define('DOSYA_MAX_BOYUT', 10 * 1024 * 1024); // 10 MB

// ─── Jeton ömürleri (saniye) ────────────────────────────────
defined('SIFRE_SIFIRLAMA_OMRU')  || define('SIFRE_SIFIRLAMA_OMRU', 3600);      // 1 saat
defined('EPOSTA_DOGRULAMA_OMRU') || define('EPOSTA_DOGRULAMA_OMRU', 172800);   // 48 saat

// ─── Statik varlık adresi (otomatik önbellek kırma) ─────────
// CSS/JS/ikon adresine dosyanın değişiklik zamanını ?v= olarak ekler.
//
// NEDEN OTOMATİK: Bu sürüm numarası önce ELLE tutuluyordu ve aynı hata
// iki kez yaşandı — dosyanın içeriği değişti, numarayı güncellemek
// unutuldu, tarayıcı ve service worker eski kopyayı sunmaya devam etti
// (bir kez logo, bir kez fiş küçük resmi bu yüzden bozuk göründü; ikinci
// seferde "sürümü artırmayı unutma" notunun kendisi bile yetmedi).
// Damga dosyadan okunduğu için artık kimsenin bir şey hatırlaması
// gerekmiyor: içerik değişti = adres değişti = tarayıcı taze kopya çeker.
//
// Bu dosya hem functions.php hem eposta.php tarafından yüklendiğinden
// varlik() her bağlamda (panel, tanıtım sayfası, e-posta, cron) kullanılabilir.
function varlik(string $yol): string {
    $damga = @filemtime(dirname(__DIR__) . '/' . ltrim($yol, '/'));
    return $yol . ($damga ? '?v=' . $damga : '');
}
