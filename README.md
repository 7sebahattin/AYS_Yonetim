# AYS — Apartman Yönetim Sistemi

Küçük/orta ölçekli apartman ve site yönetimleri için PHP tabanlı, çok kiracılı (multi-tenant) aidat/gider takip uygulaması. Framework bağımlılığı yoktur; saf PHP + PDO (MySQL) ile yazılmıştır.

## Özellikler

- Kullanıcı bazlı apartman hesabı (kayıt olduğunuzda kendi apartmanınız ve daireleriniz otomatik oluşturulur)
- **Çoklu site / çoklu blok**: bir kullanıcı birden fazla apartman veya site yönetebilir, aralarında tek tıkla geçiş yapar; her site bloklara (A Blok, B Blok…) ayrılabilir
- Daire yönetimi (ekle/düzenle/sil, blok ataması, sakin bilgisi, aylık aidat tutarı)
- Dönem bazlı aidat/ödeme takibi, toplu dönem oluşturma, toplu/tekil ödeme girişi
- WhatsApp üzerinden sakine borç/ödeme durumu mesajı hazırlama
- Kategori bazlı gider takibi; kategori girişi serbest metin + öneri listesi (yazınca eşleşenler listelenir, eşleşme yoksa "ekle" seçeneği çıkar, her kullanıcı kendi kategori geçmişinden sorumludur)
- Dashboard ve raporlar (tahsilat oranı, 12 aylık gelir/gider trendi)
- Yazdırılabilir A4 raporlar (aidat, gider, trend, tam rapor, daire detay raporu)
- Açık/koyu tema
- Progressive Web App: ana ekrana yüklenebilir, uygulama gibi açılır

## Klasör Yapısı

```
/
├─ index.php              → Tanıtım (landing) sayfası — herkese açık, SEO odaklı
├─ login.php              → Giriş / kayıt
├─ dashboard.php          → Ana panel
├─ daireler.php           → Daire yönetimi
├─ daire_detay.php        → Daire bazlı dönem geçmişi ve toplu ödeme
├─ aidatlar.php           → Aidat/ödeme takibi
├─ giderler.php           → Gider takibi
├─ raporlar.php           → Özet ve trend raporları
├─ print.php / daire_print.php → Yazdırılabilir raporlar
├─ ayarlar.php            → Hesap / apartman / e-posta / tema ayarları
├─ cikis.php              → Çıkış
├─ sifre_unuttum.php      → Şifre sıfırlama talebi
├─ sifre_yenile.php       → Jetonla yeni şifre belirleme
├─ eposta_dogrula.php     → E-posta adresi doğrulama
├─ site_sec.php           → Aktif site değiştirme (çok siteli kullanıcılar)
├─ goc.php                → Şema göçü (web arayüzü, anahtarla korumalı)
├─ config.php             → Veritabanı + SMTP ayarları (deploy EDİLMEZ)
├─ manifest.json          → PWA manifest (ad, ikonlar, tema rengi)
├─ sw.js                  → Service worker (yalnızca statik varlıkları önbelleğe alır)
├─ offline.html           → Çevrimdışı iken gösterilen jenerik sayfa
├─ semalar/               → Şema göçü (migration) SQL dosyaları
├─ araclar/goc_cli.php    → Şema göçü (komut satırı)
├─ vendor/PHPMailer/      → SMTP kütüphanesi (elle eklendi, LGPL-2.1)
├─ includes/              → Ortak fonksiyonlar ve altyapı katmanları
│  ├─ functions.php        → Oturum, CSRF, yetki, biçimlendirme
│  ├─ varsayilanlar.php    → config.php'de tanımsız ayarlara güvenli varsayılan
│  ├─ goc.php              → Göç çalıştırıcı
│  ├─ eposta.php           → SMTP gönderim katmanı + HTML şablon
│  ├─ kimlik.php           → Şifre sıfırlama / e-posta doğrulama jetonları
│  ├─ denetim.php          → Denetim kaydı (audit log)
│  ├─ hiz_limiti.php       → Hız sınırlama (brute-force / spam koruması)
│  ├─ dosya.php            → Dosya yükleme ve güvenli saklama
│  └─ header.php / footer.php / print_utils.php
└─ assets/                → CSS/JS dosyaları
   ├─ style.css            → Panel stilleri (mobil dahil)
   ├─ landing.css           → Tanıtım sayfası stilleri
   ├─ pwa-install.js        → SW kaydı + "Uygulamayı Yükle" banner'ı
   └─ icons/                → PWA/apple-touch/favicon ikonları
```

**Sayfa akışı:** `/` (tanıtım) → `login.php` (giriş/kayıt) → `dashboard.php` (panel).
Oturumu açık bir kullanıcı `/` veya `login.php` adresine giderse doğrudan panele
yönlendirilir; oturumu olmayan kullanıcı panel sayfalarından `login.php`'ye döner.

## Kurulum

1. **Veritabanı**: MySQL/MariaDB üzerinde bir veritabanı oluşturun ve şemayı içe aktarın (uygulamayla birlikte gelen SQL dump'ı kullanarak `kullanicilar`, `daireler`, `aidatlar`, `giderler`, `oturum_loglari` tablolarını kurun). **SQL dump'ı asla gerçek kullanıcı verisiyle birlikte herkese açık bir repoya yüklemeyin.**
2. **`config.php`**: `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` değerlerini kendi sunucunuza göre düzenleyin. Bu dosya gerçek kimlik bilgileriyle **asla** versiyon kontrolüne eklenmemelidir (`.gitignore` bu tür dosyaları hariç tutacak şekilde ayarlanmalıdır; gerekirse `config.php`'yi yerel bir kopya olarak tutup repodakini placeholder değerlerle bırakın).
3. Dosyaları PHP 8+ destekleyen bir web sunucusuna (Apache/Nginx + PHP-FPM veya cPanel) yükleyin, belge kökünü proje köküne işaretleyin.
4. `https://<alan-adiniz>/` adresindeki tanıtım sayfasından **Hemen Başla** ile (ya da doğrudan `login.php?mod=kayit` adresinden) ilk apartman hesabınızı oluşturun.
5. **SEO**: Tanıtım sayfasındaki canonical ve Open Graph adresleri `index.php` başındaki `$site_url` değişkeninden gelir. Alan adınız farklıysa bu değeri güncelleyin.

## Tanıtım Sayfası & SEO

Kök adres (`index.php`) herkese açık bir tanıtım sayfasıdır: hero, özellik kartları,
"Neden AYS?" bölümü, accordion SSS ve footer içerir. Arama motoru görünürlüğü için:

- Anahtar kelime odaklı `<title>`, meta description ve keywords (*apartman yönetim
  yazılımı*, *site aidat takip sistemi* vb.), `canonical`, Open Graph ve Twitter Card etiketleri.
- **JSON-LD yapısal veri**: `SoftwareApplication` künyesi + `FAQPage`. SSS içeriği tek
  bir PHP dizisinden hem sayfaya hem yapısal veriye yazılır; böylece Google'ın
  uyuşmazlık cezasına yol açan HTML ↔ schema farkı oluşamaz.
- SSS accordion'u JavaScript'siz `<details>/<summary>` ile kurulmuştur — içerik ilk
  HTML yanıtında yer aldığı için tarayıcı ve arama motoru tarafından okunabilir.
- `login.php` `noindex, follow` ile işaretlidir; ince/yinelenen içeriğin indekslenmesini
  önleyip indeks gücünü tanıtım sayfasında toplar.

## PWA (Progressive Web App)

Uygulama ana ekrana yüklenip bağımsız bir uygulama gibi açılabilir.

- **`manifest.json`**: uygulama adı, ikonlar (`any` + `maskable` varyantları), `standalone`
  görünüm ve marka renkleri (`#0d0d1a`). `start_url` **`/`** (tanıtım sayfası) — oturumu
  olan/olmayan kullanıcı ayrımı `index.php` tarafından yapılır (bkz. "Oturum Süresi &
  Beni Hatırla" bölümü); uygulama hiçbir zaman doğrudan `login.php`'de açılmaz.
- **`sw.js`**: **yalnızca gerçekten statik dosyaları** (`assets/*.css`, ikonlar, manifest)
  cache-first + arka planda güncelleme stratejisiyle önbelleğe alır. Oturuma bağlı veya
  mali veri içeren `.php` sayfaları **kesinlikle önbelleklenmez** — bunlar her zaman
  ağdan (network-only) yüklenir; çevrimdışıyken yalnızca jenerik `offline.html` gösterilir.
  Bu ayrım kasıtlıdır: aidat/gider gibi finansal verinin cihazda bayat veya güvenli
  olmayan şekilde önbellekte kalmasını önler.
- **`assets/pwa-install.js`**: service worker'ı kaydeder ve tarayıcının
  `beforeinstallprompt` olayını yakalayıp alt kısımda bir "Uygulamayı Yükle" banner'ı
  gösterir. Kullanıcı kapatırsa tercih `localStorage`'da saklanır (14 gün boyunca tekrar
  sorulmaz). iOS Safari `beforeinstallprompt` desteklemediğinden orada "Paylaş → Ana
  Ekrana Ekle" talimatı içeren bir banner gösterilir. Uygulama zaten `standalone` modda
  çalışıyorsa banner hiç gösterilmez.
- İkonlar `assets/icons/` altındadır ve `python -c` ile üretilen basit, marka renklerine
  uygun PNG dosyalarıdır (192/512 + maskable varyantları + `apple-touch-icon`).

## Gider Kategorileri

`giderler.php` içindeki "Kategori" alanı, sabit bir açılır liste yerine yazarken öneri
sunan bir kombobox'tır: eşleşen kategoriler altta listelenir, hiçbiri eşleşmiyorsa yeşil
bir "+ '…' kategorisini ekle" seçeneği çıkar. Bir gider kaydedildiğinde kullandığı
kategori, o kullanıcının `gider_kategorileri` tablosundaki kişisel listesine yazılır
(`INSERT IGNORE`, aynı isim tekrar eklenmeye çalışılırsa sessizce yok sayılır).

- **`includes/functions.php` → `gider_kategorileri_tablosunu_hazirla()`**: tablo yoksa
  `CREATE TABLE IF NOT EXISTS` ile kendiliğinden oluşturur — ayrı bir migration adımı
  gerekmez, `giderler.php` her yüklendiğinde çağrılır ve var olan tabloda ucuzdur.
- **Öneri kaynağı** (`gider_kategori_onerileri()`): varsayılan 11 kategori + kullanıcının
  daha önce kaydettiği özel kategoriler + `giderler` tablosunda o kullanıcı için fiilen
  kullanılmış (bu özellikten önce eklenmiş olabilecek) kategori adları — hepsi
  büyük/küçük harf duyarsız tekilleştirilip alfabetik sıralanır. Bu sayede "Doğalgaz"ı
  daha önce farklı büyük/küçük harfle girmiş bir kullanıcı için de aynı kategori önerilir.
- **Kullanıcı izolasyonu**: `gider_kategorileri.kullanici_id` ile her yönetim yalnızca
  kendi eklediği kategorileri görür/önerir; diğer apartmanların kategori listesine erişim
  yoktur (`kullanicilar(id)` üzerine `ON DELETE CASCADE` yabancı anahtarla).
- Kategori seçimi **zorunludur** (hem tarayıcıda `required`, hem sunucu tarafında);
  boş bırakılırsa kayıt reddedilir, artık sessizce `Diğer`'e düşmez. 50 karaktere kırpılır
  (`giderler.kategori` sütunuyla aynı sınır).
- Her gider kaydı **düzenlenebilir** ("✎ Düzenle" — `daireler.php`'deki desenle aynı:
  `?duzenle=<id>` ile açılan, önceki değerlerle dolu bir modal; `guncelle` işlemi `UPDATE`
  yapar). Hatalı bir girişi düzeltmek için artık silip yeniden eklemeye gerek yok.

## Kimlik, Site ve Blok Modeli

> Göç: `semalar/003_site_blok_modeli.sql`

Sistem başlangıçta **kullanıcı = apartman** varsayımıyla yazılmıştı: apartman adı,
adresi ve daire sayısı `kullanicilar` tablosunda tutuluyor, tüm kayıtlar
`kullanici_id` ile filtreleniyordu. Bu model iki şeyi imkânsız kılıyordu — bir
kişinin birden fazla apartman/site yönetmesi ve bir sitenin birden fazla bloğa
bölünmesi. Faz 2'de **kimlik** (kim giriş yapıyor) ile **kiracı** (hangi binanın
verisi) birbirinden ayrıldı.

### Tablolar

| Tablo | Rolü |
|---|---|
| `kullanicilar` | Yalnızca kimlik: kullanıcı adı, şifre, e-posta, tema |
| `siteler` | Bina/site künyesi: ad, adres, telefon, tip (`apartman`/`site`), daire sayısı |
| `bloklar` | Bir sitenin blokları (A Blok, B Blok…); tek bloklu binada "Ana Blok" |
| `kullanici_site_yetkileri` | Hangi kullanıcı hangi siteye, hangi rolle erişir |
| `daireler`, `aidatlar`, `giderler`, `gider_kategorileri` | Artık `site_id` ile sahiplenilir |

`daireler` ayrıca isteğe bağlı bir `blok_id` taşır (blok silinirse `NULL` olur,
daire kaybolmaz).

### Göçün veri taşıma yöntemi

Göç, her mevcut kullanıcı için bir site kaydı üretirken **siteye kullanıcının
kendi id'sini verir** (`INSERT INTO siteler (id, …) SELECT id, apartman_adi, …`).
Böylece bağlı tabloların backfill'i `UPDATE daireler SET site_id = kullanici_id`
kadar basit ve kayıpsız olur; JOIN ile eşleme yapıp yanlış satır güncelleme
riski oluşmaz. Sonrasında `siteler.id` normal AUTO_INCREMENT olarak devam eder,
yeni sitelerin id'si kullanıcı id'leriyle ilişkisizdir.

Göç sonrası eski `kullanicilar.apartman_adi`/`adres`/`telefon`/`toplam_daire`
sütunları **düşürülmez** — yalnızca okunmaz hâle gelir. Geri dönüş gerekirse veri
yerinde durur.

### Davranış değişikliği: kullanıcı silmek artık binayı silmiyor

Eskiden `daireler.kullanici_id` üzerinde `ON DELETE CASCADE` bir yabancı anahtar
vardı; bir kullanıcı silindiğinde daireleri, dolayısıyla aidat geçmişi de
zincirleme siliniyordu. Yeni modelde daire siteye bağlıdır
(`fk_daire_site … ON DELETE CASCADE`), kullanıcıya değil. `fk_daire_kullanici`
kaldırıldı. Artık bir yöneticinin hesabını silmek yalnızca o kişinin erişimini
sonlandırır; binanın verisi yerinde kalır ve başka bir kullanıcıya yetki
verilebilir.

Aynı göç `uq_kullanici_daire` benzersizlik kısıtını da kaldırıp yerine
`uq_site_daire (site_id, daire_no)` koyar — yani aynı kullanıcı iki farklı sitede
"Daire 1"e sahip olabilir, ki eski kısıt altında bu mümkün değildi.

### Aktif site seçimi (güvenlik kontrolü)

Oturumdaki aktif site `$_SESSION['aktif_site_id']` içinde tutulur, ancak bu değer
**hiçbir zaman doğrudan güvenilmez**. Her istekte `giris_kontrol()` →
`aktif_site_belirle()` çalışır ve istenen site id'si `kullanici_site_yetkileri`
ile JOIN'lenerek doğrulanır:

- Yetki yoksa istenen id yok sayılır, kullanıcının yetkili olduğu ilk siteye düşülür.
- Hiç yetkisi yoksa oturum sonlandırılır.
- Sayfalar `$kullanici['site_id']` kullanır; `$kullanici['id']` yalnızca kimlik içindir.

`site_sec.php` (site değiştirme uç noktası) aynı doğrulamayı bir kez daha yapar
(savunma katmanı), reddedilen denemeleri `site_degistirme_reddedildi` olarak
denetim kaydına yazar ve `geri` parametresini yalnızca site içi göreli yollara
kısıtlayarak açık yönlendirmeyi (open redirect) engeller.

Site seçici arayüzde **yalnızca birden fazla siteye yetkili kullanıcıya**
gösterilir; tek siteli kullanıcı için ekran hiç değişmez.

### Blok yönetimi

Bloklar `ayarlar.php` üzerinden eklenir/silinir; son blok silinemez. Blok
seçimi `daireler.php` içindeki ekleme ve düzenleme formlarında yer alır ve
`gecerli_blok_id()` ile doğrulanır — başka bir siteye ait blok id'si gönderilirse
sessizce `NULL` yazılır, çapraz site referansı oluşmaz. Site tek bloklu ise
daire listesinde blok sütunu gösterilmez.

### Göç uygulanmadan önce

`site_semasi_hazir_mi()` kontrolü sayesinde uygulama göç uygulanmadan da çalışır:
eski `kullanici_id` tabanlı davranışa düşer, site seçici ve blok arayüzü gizlenir.
Bu, "önce deploy, sonra göç" sırasının güvenli olmasını sağlar.

## Güvenlik Notları

- Tüm veritabanı sorguları PDO prepared statement kullanır; ham SQL birleştirmesi yoktur.
- Kullanıcı girdileri çıktıya yazılırken `e()` (`htmlspecialchars`) ile kaçışlanır.
- Tüm durum değiştiren (POST) istekler CSRF token ile korunur; token karşılaştırması timing-safe `hash_equals()` ile yapılır.
- Şifreler `password_hash()` (bcrypt) ile saklanır; giriş `password_verify()` ile doğrulanır, başarılı girişte oturum kimliği yenilenir (`session_regenerate_id`).
- Oturum çerezleri `HttpOnly`, `SameSite=Lax` ve HTTPS altında otomatik olarak `Secure` bayrağıyla ayarlanır.
- Oturum, 5 dakika hareketsizlikte otomatik sonlanır (`SESSION_SURE`, `config.php`). "Beni Hatırla" işaretlenerek girilen oturumlar bu sınırdan etkilenmez — bkz. aşağıdaki "Beni Hatırla" bölümü.
- Tüm sayfa yanıtlarına `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy` ve temel bir `Content-Security-Policy` (frame-ancestors) header'ı eklenir.
- Veritabanı bağlantı hataları kullanıcıya detay sızdırmaz; hata sunucu log'una (`error_log`) yazılır, kullanıcıya jenerik bir mesaj gösterilir.
- Her sorgu **aktif sitenin** `site_id` değeriyle filtrelenir (`$kullanici['site_id']`); aktif site her istekte `kullanici_site_yetkileri` üzerinden yeniden doğrulanır, oturumdaki değere güvenilmez. Kiracılar (binalar) arası veri sızıntısını önlemek için bu izolasyon tüm modüllerde korunmalıdır — bkz. "Kimlik, Site ve Blok Modeli".
- Kullanıcıya ait olmayan bir kayda id ile doğrudan erişim denemesi (`daire_detay.php?id=…`, `daire_print.php?id=…`) sorgu düzeyinde `site_id` filtresine takılır; yetkisiz istek veri döndürmez.

**Bilinen sınırlamalar / öneriler:**
- Giriş formunda kaba kuvvet (brute-force) koruması (deneme sınırı, kilitleme, CAPTCHA) bulunmuyor.
- ~~Şifre sıfırlama (e-posta ile) akışı yok.~~ → Eklendi, bkz. "Şifre Sıfırlama & E-posta Doğrulama".
- Minimum şifre uzunluğu 6 karakterdir, karmaşıklık zorunluluğu yoktur.

## Şema Göçü (Migration)

Proje başlangıçta "kendi kendini onaran" tablolar kullanıyordu (`CREATE TABLE IF NOT
EXISTS`). Bu, yeni tablo eklemek için yeterli ama **var olan tabloyu değiştirmek**
(`ALTER TABLE`, veri taşıma) için değil. Bu yüzden sürümlenmiş bir göç sistemi eklendi.

- Göçler `semalar/NNN_aciklama.sql` dosyalarıdır, numara sırasıyla uygulanır.
- Uygulananlar `sema_surumu` tablosuna yazılır; aynı göç iki kez çalıştırılmaz.
- Göç dosyaları MariaDB'nin `ADD COLUMN IF NOT EXISTS` / `CREATE INDEX IF NOT EXISTS`
  sözdizimini kullanır — böylece yarıda kalan bir göç tekrar çalıştırılabilir.

**Çalıştırma:**

```bash
php araclar/goc_cli.php durum     # bekleyenleri listele
php araclar/goc_cli.php uygula    # bekleyenleri uygula
```

SSH erişimi yoksa `https://<alan-adiniz>/goc.php` üzerinden de çalıştırılabilir;
`config.php` içindeki `GOC_ANAHTARI` gerekir (anahtar POST gövdesinde taşınır, URL'de
değil — aksi halde sunucu erişim günlüklerine düz metin yazılırdı).

> ⚠️ **Göç öncesi veritabanı yedeği alın.** MySQL/MariaDB'de DDL işlemleri geri
> alınamaz (transaction'a girmez); yarıda kalan bir göç elle temizlenmek zorunda
> kalınabilir.

### Dağıtım (deploy) sırası — önemli

`config.php` sunucuya **deploy edilmez**, dolayısıyla koda eklenen yeni ayar sabitleri
sunucudaki eski config'de bulunmaz. `includes/varsayilanlar.php` bu sabitlere güvenli
varsayılan vererek "undefined constant" ölümcül hatasını önler; ilgili özellik
yapılandırılana kadar sessizce devre dışı kalır.

Aynı şekilde uygulama, göç uygulanmadan da çalışır (`eposta_semasi_hazir_mi()`
kontrolü): e-posta arayüzü gizlenir, sistemin geri kalanı normal çalışmaya devam eder.
Doğru sıra:

1. Dosyaları dağıt (deploy)
2. `goc.php` veya `araclar/goc_cli.php` ile göçleri uygula
3. `config.php`'ye SMTP ayarlarını gir

## E-posta Yapılandırması (SMTP)

Şifre sıfırlama e-postaları **kimliği doğrulanmış SMTP** ile gönderilir. PHP'nin
`mail()` fonksiyonu kullanılmaz: paylaşımlı hostingde kimlik doğrulaması yapmadan
gönderdiği için iletiler büyük oranda spam'e düşer ve sıfırlama akışı işlevsiz kalır.

Sunucudaki `config.php` dosyasına eklenmesi gerekenler:

```php
define('SMTP_HOST', 'mail.alanadiniz.com');
define('SMTP_PORT', 587);                   // 587 (TLS) veya 465 (SSL)
define('SMTP_KULLANICI', 'noreply@alanadiniz.com');
define('SMTP_SIFRE', '...');
define('SMTP_GUVENLIK', 'tls');             // tls | ssl | yok
define('EPOSTA_GONDEREN', 'noreply@alanadiniz.com');
define('SITE_ADRESI', 'https://ays.alanadiniz.com');
```

Bu değerler boş bırakılırsa sistem çalışmaya devam eder; e-posta özellikleri
"yapılandırılmamış" olarak işaretlenir ve arayüzde bu bilgi gösterilir.

> ⚠️ **SMTP tek başına yetmez.** Alan adına **SPF** ve **DKIM** DNS kayıtları
> eklenmezse Gmail/Outlook iletileri yine spam'e atabilir. Bu bir DNS yapılandırması
> işidir, kod işi değil.

Gönderim sonuçları `eposta_kaydi` tablosuna yazılır ("e-posta gelmedi" şikayetini
teşhis edebilmek için); jeton, bağlantı veya şifre gibi gizli içerik **saklanmaz**.

## Şifre Sıfırlama & E-posta Doğrulama

**Ön koşul — e-posta toplama:** Bu özellik eklenene kadar sistemde kullanıcı e-postası
hiç toplanmıyordu (`kullanicilar` tablosunda sütun yoktu). Bu yüzden:

- Yeni kayıtlarda e-posta **zorunlu** alandır ve doğrulama bağlantısı otomatik gönderilir.
- Mevcut kullanıcılara panelde kapatılabilir bir uyarı bandı gösterilir; adreslerini
  Ayarlar → E-posta Adresi bölümünden ekleyebilirler.

**Güvenlik tasarımı:**

- Sıfırlama bağlantısı **yalnızca doğrulanmış** adrese gönderilir. Doğrulanmamış bir
  adres yazım hatası olabilir; bağlantı yabancı birinin gelen kutusuna giderse hesap
  ele geçirilebilirdi.
- `sifre_unuttum.php`, adres kayıtlı olsun ya da olmasın **aynı mesajı** gösterir —
  aksi halde form, hangi e-postaların sistemde olduğunu keşfetmek için kullanılabilirdi.
- Jetonlar `hatirlama_jetonlari` ile aynı **selector/validator** desenini kullanır:
  bağlantıda açık duran seçici ile satır bulunur, gizli doğrulayıcı ise veritabanına
  asla düz metin yazılmaz (yalnızca SHA-256 hash'i, `hash_equals` ile karşılaştırılır).
- Jetonlar **tek kullanımlıktır**; kullanıldığında aynı kullanıcının bekleyen diğer
  jetonları da iptal edilir. Sıfırlama 1 saat, doğrulama 48 saat geçerlidir.
- Şifre değiştiğinde **tüm "Beni Hatırla" oturumları düşürülür** — şifre ele geçirildiği
  için sıfırlanıyor olabilir, saldırganın açık kalan kalıcı oturumu da kapanmalıdır.
- Şifre değişiminde kullanıcıya bilgilendirme e-postası gider (hesap ele geçirme erken
  uyarısı).
- Hız sınırlama: adres başına 15 dakikada 3, IP başına 15 dakikada 10 istek. Aksi halde
  bu form başkasının gelen kutusuna spam göndermek için kullanılabilirdi.

## Denetim Kaydı, Hız Sınırlama ve Dosya Saklama

Faz 3'te gelecek süper admin panelinin gerektirdiği altyapılar; şimdilik kimlik
olaylarında kullanılıyor.

- **`denetim_kaydi`**: kim, ne zaman, hangi eylemi yaptı (giriş, şifre sıfırlama,
  e-posta doğrulama, göç çalıştırma, yetkisiz göç denemesi). Denetim yazımı ana işlemi
  asla bozmaz — tablo yoksa veya yazma başarısız olursa sessizce yutulur.
- **`hiz_limiti`**: kayan pencere sayacı. Veritabanı tabanlı olması bilinçli; paylaşımlı
  hostingde APCu/Redis garanti değildir.
- **`includes/dosya.php`**: Faz 4/5'teki belge, fatura ve fotoğraf yüklemeleri için.
  Dosyalar **web kökünün dışında** (`DOSYA_KOK`, varsayılan olarak `public_html`'in bir
  üstü) saklanır ve yalnızca yetki kontrolü yapan bir bekçi betiği üzerinden sunulur —
  içerik kişisel ve mali veri barındırdığı için doğrudan URL ile erişilebilir olmamalıdır.
  Uzantıya tek başına güvenilmez: `finfo` ile gerçek içerik türü doğrulanır (sahte MIME
  başlığıyla gönderilen `.php` içerikli "PDF" reddedilir) ve disktaki dosya adı yeniden
  üretilir.

## Oturum Süresi & "Beni Hatırla"

- **Hareketsizlik zaman aşımı**: `SESSION_SURE` (`config.php`) varsayılan olarak **300 saniye (5 dk)**. Bu süre boyunca istek gelmezse `giris_kontrol()` oturumu sonlandırır ve kullanıcıyı `login.php?mesaj=suresi_doldu`'ya yönlendirir.
- **"Beni Hatırla"**: Giriş formundaki kutu işaretlenirse `hatirlama_jetonu_baslat()` (`includes/functions.php`) selector/validator deseniyle bir jeton üretir — çerezde (`ays_hatirla`, `HttpOnly`, 30 gün) yalnızca *seçici* açık, *doğrulayıcı* ise sadece SHA-256 hash'i (`hatirlama_jetonlari` tablosunda) saklanır; ham doğrulayıcı hiçbir zaman veritabanına yazılmaz.
  - Oturum süresi dolduğunda (ya da hiç oturum yokken `/`, `login.php`, veya herhangi bir panel sayfası açıldığında) `oturumu_hatirlama_ile_dene()` bu çerezi doğrular ve geçerliyse **kullanıcıyı sessizce yeniden oturum açar** — şifre tekrar istenmez.
  - Her başarılı kullanımda jeton **rotasyona sokulur** (eski satır silinir, yeni seçici/doğrulayıcı çifti yazılır, çerez güncellenir) — çerez çalınsa bile tekrar kullanılabilirlik penceresi tek seferle sınırlıdır.
  - Geçersiz/uyuşmayan bir çerez tespit edilirse ilgili çerez ve (varsa) DB kaydı temizlenir.
  - `cikis.php` yalnızca **o cihazın** jetonunu siler; diğer cihazlardaki "Beni Hatırla" oturumları etkilenmez.
  - `hatirlama_jetonlari` tablosu da `gider_kategorileri` gibi ilk kullanımda `CREATE TABLE IF NOT EXISTS` ile kendiliğinden oluşur, ayrı bir migration adımı gerekmez.
- **PWA açılış davranışı**: `manifest.json`'daki `start_url` **`/`**'dir (tanıtım sayfası). Ana ekrandan açılan uygulama; oturum ya da geçerli "Beni Hatırla" çerezi varsa doğrudan `dashboard.php`'ye geçer, yoksa tanıtım sayfasında açılır — asla doğrudan `login.php`'de açılmaz.

## Arayüz Notları

- **Pencereler (modal'lar)**: Tüm `.modal-overlay` iletişim kutuları (daire/aidat/gider ekle-düzenle, dönem oluştur vb.) hem masaüstünde hem mobilde **ekranda ortalanır** (`align-items:center`, hafif büyüyerek beliren `modalPop` animasyonu). Önceki sürümde tüm ekran boylarında alta yaslanan bir "bottom sheet" tasarımıydı; bu geri bildirim üzerine değiştirildi.
- **Pencereler yalnızca kapatma butonuyla kapanır**: Overlay'in boş alanına tıklama ve mobilde aşağı kaydırma (swipe) ile kapatma kaldırıldı — kazara dokunuşla doldurulmuş bir formun kaybolması engellendi. Kapatmak için `×`, "İptal" ya da başarılı kayıt sonrası otomatik kapanma kullanılır.
- **Otomatik büyük harf** (`buyuk`/`e_buyuk`, `includes/functions.php`): Sakin adı, notlar, gider kategorisi/açıklaması, fatura/dekont no, apartman adı ve adres gibi serbest metin alanları kaydedilirken Türkçe kurallara uygun şekilde (i→İ, ı→I) büyük harfe çevrilir (`turkce_buyuk()` — PHP'nin `mb_strtoupper()`'ı bu ayrımı tek başına yapmaz). İlgili `<input>`/`<textarea>` alanları `class="input buyuk"` ile yazarken de görsel olarak büyük gösterilir. Bu özellikten **önce** küçük/karışık harfle kaydedilmiş veriler de görüntülenirken (`e_buyuk()`) büyük gösterilir — veritabanındaki değer fiilen değiştirilmez, yalnızca ekranda/çıktıda büyük görünür; bir sonraki düzenlemede kaydedilirse kalıcı olarak büyük harfe döner. E-posta, kullanıcı adı ve şifre alanları bu dönüşümün dışındadır.

## Performans Notları

- Dönem oluşturma (`aidatlar.php` → "Dönem(ler) Oluştur") tüm daire × dönem kayıtlarını tek tek değil, 500'lük partiler halinde toplu (multi-row) `INSERT IGNORE` ile yazar.
- Raporlar ve yazdırma sayfalarındaki (`raporlar.php`, `print.php`) gelir/gider trend hesaplaması, ay başına iki ayrı sorgu çalıştırmak yerine `includes/functions.php` içindeki `trend_verisi()` fonksiyonuyla tek bir `GROUP BY donem` sorgusu üzerinden yapılır.

## Mobil Uyumluluk

- Tüm veri tabloları (`daireler.php`, `aidatlar.php`, `raporlar.php`, `daire_detay.php`,
  `giderler.php`) `.table-wrap` içinde yatay kaydırmalıdır; tablo daralınca sütunlar
  kırılmaz, kullanıcı yatay kaydırır (`overflow-x:auto`, dokunmatik ivmeli kaydırma).
- Dokunmatik hedefler (küçük butonlar, filtre sekmeleri, giriş sekmeleri, dönem seçici)
  320–767px genişlikte en az 44px yüksekliğe sahiptir (`assets/style.css` içindeki
  `@media(max-width:767px)` bloğu).
- `viewport-fit=cover` + `env(safe-area-inset-*)` ile iOS'ta çentik/alt çubuk alanları
  (Dynamic Island, home indicator) sabit üst/alt barların ve PWA yükleme banner'ının
  altında/üstünde kalmaz.

## Otomatik Deploy (GitHub Actions)

`master` branch'ine her push/merge sonrasında `.github/workflows/deploy.yml` iş akışı çalışır ve dosyaları FTPS üzerinden sunucudaki `ays.derspros.com.tr/` dizinine gönderir. Actions sekmesindeki **Run workflow** butonuyla elle de tetiklenebilir.

**Gerekli repository secret'ları** (Settings → Secrets and variables → Actions):

| Secret adı | Değer |
|---|---|
| `FTP_USERNAME` | cPanel FTP kullanıcı adı |
| `FTP_PASSWORD` | cPanel FTP şifresi |

Sunucu adresi ve port hassas bilgi olmadığından workflow dosyasında sabit tanımlıdır.

**Deploy davranışıyla ilgili notlar:**
- Aktarım `lftp mirror --reverse` ile yapılır. Daha önce `SamKirkland/FTP-Deploy-Action` kullanılıyordu; ancak o action Node 20 için yazılmışken runner Node 24'e zorluyor ve Node 24'ün katı TLS varsayılanları sunucunun FTPS yapılandırmasıyla el sıkışamayıp `Timeout (control socket)` hatası veriyordu. lftp sistemin TLS kitaplığını kullandığından bu uyumsuzluktan etkilenmez.
- Sunucu şifresiz (cleartext) FTP bağlantısını reddettiği için (`421 Please reconnect using TLS security mechanisms`) `ftp:ssl-force` ile port 21 üzerinden explicit FTPS zorunlu tutulur; veri kanalı da `ftp:ssl-protect-data` ile şifrelenir.
- FTP sertifikası alan adı yerine sunucu adına düzenlendiğinden hostname uyuşmazlığını tolere etmek için `ssl:verify-certificate no` ayarlanmıştır.
- `config.php` **senkronize edilmez** — repodaki sürüm placeholder değerler içerir ve sunucudaki gerçek yapılandırmanın üzerine yazılmamalıdır. Aynı şekilde `README.md` ve `.git*` ile başlayan her şey (`.git/`, `.github/`, `.gitignore`) yüklenmez.
- `mirror` komutu `--delete` almadığından sunucuda repoda bulunmayan dosyalar (`.well-known/`, `error_log` vb.) silinmez; yalnızca değişen dosyalar aktarılır.
