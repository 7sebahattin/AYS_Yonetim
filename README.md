# AYS — Apartman Yönetim Sistemi

Küçük/orta ölçekli apartman ve site yönetimleri için PHP tabanlı, çok kiracılı (multi-tenant) aidat/gider takip uygulaması. Framework bağımlılığı yoktur; saf PHP + PDO (MySQL) ile yazılmıştır.

## Özellikler

- Kullanıcı bazlı apartman hesabı (kayıt olduğunuzda kendi apartmanınız ve daireleriniz otomatik oluşturulur)
- Daire yönetimi (ekle/düzenle/sil, sakin bilgisi, aylık aidat tutarı)
- Dönem bazlı aidat/ödeme takibi, toplu dönem oluşturma, toplu/tekil ödeme girişi
- WhatsApp üzerinden sakine borç/ödeme durumu mesajı hazırlama
- Kategori bazlı gider takibi
- Dashboard ve raporlar (tahsilat oranı, 12 aylık gelir/gider trendi)
- Yazdırılabilir A4 raporlar (aidat, gider, trend, tam rapor, daire detay raporu)
- Açık/koyu tema

## Klasör Yapısı

```
/
├─ index.php              → Giriş / kayıt
├─ dashboard.php          → Ana panel
├─ daireler.php           → Daire yönetimi
├─ daire_detay.php        → Daire bazlı dönem geçmişi ve toplu ödeme
├─ aidatlar.php           → Aidat/ödeme takibi
├─ giderler.php           → Gider takibi
├─ raporlar.php           → Özet ve trend raporları
├─ print.php / daire_print.php → Yazdırılabilir raporlar
├─ ayarlar.php            → Hesap / apartman / tema ayarları
├─ cikis.php              → Çıkış
├─ config.php             → Veritabanı bağlantı ayarları
├─ includes/              → Ortak fonksiyonlar, header/footer, yazdırma yardımcıları
└─ assets/                → CSS dosyaları
```

## Kurulum

1. **Veritabanı**: MySQL/MariaDB üzerinde bir veritabanı oluşturun ve şemayı içe aktarın (uygulamayla birlikte gelen SQL dump'ı kullanarak `kullanicilar`, `daireler`, `aidatlar`, `giderler`, `oturum_loglari` tablolarını kurun). **SQL dump'ı asla gerçek kullanıcı verisiyle birlikte herkese açık bir repoya yüklemeyin.**
2. **`config.php`**: `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` değerlerini kendi sunucunuza göre düzenleyin. Bu dosya gerçek kimlik bilgileriyle **asla** versiyon kontrolüne eklenmemelidir (`.gitignore` bu tür dosyaları hariç tutacak şekilde ayarlanmalıdır; gerekirse `config.php`'yi yerel bir kopya olarak tutup repodakini placeholder değerlerle bırakın).
3. Dosyaları PHP 8+ destekleyen bir web sunucusuna (Apache/Nginx + PHP-FPM veya cPanel) yükleyin, belge kökünü proje köküne işaretleyin.
4. `https://<alan-adiniz>/index.php` adresinden "Yeni Kayıt" ile ilk apartman hesabınızı oluşturun.

## Güvenlik Notları

- Tüm veritabanı sorguları PDO prepared statement kullanır; ham SQL birleştirmesi yoktur.
- Kullanıcı girdileri çıktıya yazılırken `e()` (`htmlspecialchars`) ile kaçışlanır.
- Tüm durum değiştiren (POST) istekler CSRF token ile korunur; token karşılaştırması timing-safe `hash_equals()` ile yapılır.
- Şifreler `password_hash()` (bcrypt) ile saklanır; giriş `password_verify()` ile doğrulanır, başarılı girişte oturum kimliği yenilenir (`session_regenerate_id`).
- Oturum çerezleri `HttpOnly`, `SameSite=Lax` ve HTTPS altında otomatik olarak `Secure` bayrağıyla ayarlanır.
- Tüm sayfa yanıtlarına `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy` ve temel bir `Content-Security-Policy` (frame-ancestors) header'ı eklenir.
- Veritabanı bağlantı hataları kullanıcıya detay sızdırmaz; hata sunucu log'una (`error_log`) yazılır, kullanıcıya jenerik bir mesaj gösterilir.
- Her sorgu oturum sahibinin `kullanici_id` değeriyle filtrelenir; kiracılar (apartmanlar) arası veri sızıntısını önlemek için bu izolasyon tüm modüllerde korunmalıdır.

**Bilinen sınırlamalar / öneriler:**
- Giriş formunda kaba kuvvet (brute-force) koruması (deneme sınırı, kilitleme, CAPTCHA) bulunmuyor.
- Şifre sıfırlama (e-posta ile) akışı yok.
- Minimum şifre uzunluğu 6 karakterdir, karmaşıklık zorunluluğu yoktur.

## Performans Notları

- Dönem oluşturma (`aidatlar.php` → "Dönem(ler) Oluştur") tüm daire × dönem kayıtlarını tek tek değil, 500'lük partiler halinde toplu (multi-row) `INSERT IGNORE` ile yazar.
- Raporlar ve yazdırma sayfalarındaki (`raporlar.php`, `print.php`) gelir/gider trend hesaplaması, ay başına iki ayrı sorgu çalıştırmak yerine `includes/functions.php` içindeki `trend_verisi()` fonksiyonuyla tek bir `GROUP BY donem` sorgusu üzerinden yapılır.

## Otomatik Deploy (GitHub Actions)

`master` branch'ine her push/merge sonrasında `.github/workflows/deploy.yml` iş akışı çalışır ve dosyaları FTPS üzerinden sunucudaki `ays.derspros.com.tr/` dizinine gönderir. Actions sekmesindeki **Run workflow** butonuyla elle de tetiklenebilir.

**Gerekli repository secret'ları** (Settings → Secrets and variables → Actions):

| Secret adı | Değer |
|---|---|
| `FTP_USERNAME` | cPanel FTP kullanıcı adı |
| `FTP_PASSWORD` | cPanel FTP şifresi |

Sunucu adresi ve port hassas bilgi olmadığından workflow dosyasında sabit tanımlıdır.

**Deploy davranışıyla ilgili notlar:**
- Sunucu şifresiz (cleartext) FTP bağlantısını reddettiği için `protocol: ftps` (port 21 üzerinden explicit FTPS) kullanılır.
- FTP sertifikası alan adı yerine sunucu adına düzenlendiğinden hostname uyuşmazlığını tolere etmek için `security: loose` ayarlanmıştır.
- `config.php` **senkronize edilmez** — repodaki sürüm placeholder değerler içerir ve sunucudaki gerçek yapılandırmanın üzerine yazılmamalıdır. Aynı şekilde `README.md`, `.gitignore` ve `.github/` dizini de yüklenmez.
- `dangerous-clean-slate: false` olduğundan sunucuda repoda bulunmayan dosyalar (`.well-known/`, `error_log` vb.) silinmez.
