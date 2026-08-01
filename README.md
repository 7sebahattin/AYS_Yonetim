# AYS — Apartman Yönetim Sistemi

Küçük/orta ölçekli apartman ve site yönetimleri için PHP tabanlı, çok kiracılı (multi-tenant) aidat/gider takip uygulaması. Framework bağımlılığı yoktur; saf PHP + PDO (MySQL) ile yazılmıştır.

## Özellikler

- Kullanıcı bazlı apartman hesabı (kayıt olduğunuzda kendi apartmanınız ve daireleriniz otomatik oluşturulur)
- Daire yönetimi (ekle/düzenle/sil, sakin bilgisi, aylık aidat tutarı)
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
├─ ayarlar.php            → Hesap / apartman / tema ayarları
├─ cikis.php              → Çıkış
├─ config.php             → Veritabanı bağlantı ayarları
├─ manifest.json          → PWA manifest (ad, ikonlar, tema rengi)
├─ sw.js                  → Service worker (yalnızca statik varlıkları önbelleğe alır)
├─ offline.html           → Çevrimdışı iken gösterilen jenerik sayfa
├─ includes/              → Ortak fonksiyonlar, header/footer, yazdırma yardımcıları
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

## Güvenlik Notları

- Tüm veritabanı sorguları PDO prepared statement kullanır; ham SQL birleştirmesi yoktur.
- Kullanıcı girdileri çıktıya yazılırken `e()` (`htmlspecialchars`) ile kaçışlanır.
- Tüm durum değiştiren (POST) istekler CSRF token ile korunur; token karşılaştırması timing-safe `hash_equals()` ile yapılır.
- Şifreler `password_hash()` (bcrypt) ile saklanır; giriş `password_verify()` ile doğrulanır, başarılı girişte oturum kimliği yenilenir (`session_regenerate_id`).
- Oturum çerezleri `HttpOnly`, `SameSite=Lax` ve HTTPS altında otomatik olarak `Secure` bayrağıyla ayarlanır.
- Oturum, 5 dakika hareketsizlikte otomatik sonlanır (`SESSION_SURE`, `config.php`). "Beni Hatırla" işaretlenerek girilen oturumlar bu sınırdan etkilenmez — bkz. aşağıdaki "Beni Hatırla" bölümü.
- Tüm sayfa yanıtlarına `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy` ve temel bir `Content-Security-Policy` (frame-ancestors) header'ı eklenir.
- Veritabanı bağlantı hataları kullanıcıya detay sızdırmaz; hata sunucu log'una (`error_log`) yazılır, kullanıcıya jenerik bir mesaj gösterilir.
- Her sorgu oturum sahibinin `kullanici_id` değeriyle filtrelenir; kiracılar (apartmanlar) arası veri sızıntısını önlemek için bu izolasyon tüm modüllerde korunmalıdır.

**Bilinen sınırlamalar / öneriler:**
- Giriş formunda kaba kuvvet (brute-force) koruması (deneme sınırı, kilitleme, CAPTCHA) bulunmuyor.
- Şifre sıfırlama (e-posta ile) akışı yok.
- Minimum şifre uzunluğu 6 karakterdir, karmaşıklık zorunluluğu yoktur.

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
