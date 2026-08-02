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
- **Arıza / talep takibi**: kategori, öncelik ve durum akışıyla; fotoğraf eki, personel ataması, işlem geçmişi ve maliyetin gidere aktarılması
- **Demirbaş ve bakım takibi**: asansör, yangın, jeneratör gibi ekipmanların künyesi; yasal periyodik kontroller için otomatik zincir ve e-posta hatırlatma
- **Personel yönetimi**: görev, ücret ve ödeme geçmişi; her ödeme gider defterine otomatik işlenir
- **Platform yönetim paneli** (`/yonetim/`): tüm siteler, kullanıcılar, denetim kaydı, sistem duyuruları, tanıtım sayfası içeriği ve bakım modu — zorunlu iki faktörlü doğrulama arkasında

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
├─ talepler.php           → Arıza / talep bildirimi
├─ demirbaslar.php        → Demirbaş envanteri ve bakım takibi
├─ personel.php           → Personel ve ödeme yönetimi
├─ belge_indir.php        → Dosya indirme bekçisi (yetki kontrollü)
├─ goc.php                → Şema göçü (web arayüzü, anahtarla korumalı)
├─ yonetim/               → Platform yönetim paneli (süper admin)
│  ├─ ortak.php            → Panel önyükleme + yetki bekçisi
│  ├─ giris.php            → Ayrı giriş yolu (şifre + zorunlu 2FA)
│  ├─ index.php            → Platform istatistikleri
│  ├─ siteler.php / site_detay.php → Tüm siteler, askıya alma, destek işlemleri
│  ├─ kullanicilar.php     → Platform rolü, şifre sıfırlama, 2FA sıfırlama
│  ├─ duyurular.php        → Sistem duyuruları
│  ├─ icerik.php           → Tanıtım sayfası metinleri ve SSS
│  ├─ denetim.php          → Denetim kaydı görünümü (filtreli)
│  ├─ goc.php              → Göçleri panelden çalıştırma
│  ├─ ayarlar.php          → Bakım modu, IP kısıtı, bürünme yazma izni
│  ├─ iki_faktor.php       → TOTP kurulumu ve yedek kodlar
│  ├─ burun.php            → Kullanıcı adına görüntüleme başlat/bitir
│  └─ kurulum.php          → İlk süper admin atama (tek seferlik)
├─ araclar/superadmin_ata.php → Platform rolü atama (CLI)
├─ araclar/bakim_hatirlatma.php → Yaklaşan bakım e-postası (cron)
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
│  ├─ platform.php         → Platform rolleri, bakım modu, duyurular, bürünme
│  ├─ totp.php             → İki faktörlü doğrulama (RFC 6238 TOTP)
│  ├─ tanitim_icerik.php   → Tanıtım sayfası varsayılan metinleri
│  ├─ operasyon.php        → Talep/bakım/personel ortak katmanı, gider bağlama, ekler
│  ├─ demirbas_form.php    → Demirbaş form alanları (ekle + düzenle ortak)
│  ├─ personel_form.php    → Personel form alanları (ekle + düzenle ortak)
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
  kaynaktan hem sayfaya hem yapısal veriye yazılır; böylece Google'ın uyuşmazlık
  cezasına yol açan HTML ↔ schema farkı oluşamaz.
- Başlık, meta açıklama, hero metni ve SSS **yönetim panelinden düzenlenebilir**
  (bkz. "Platform Yönetimi"); kayıt yoksa koddaki varsayılan metin kullanılır.
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

## Platform Yönetimi (Süper Admin Paneli)

> Göç: `semalar/004_platform_yonetimi.sql` · Panel: `/yonetim/`

Bugüne kadar sistemde yalnızca **kiracı** düzeyi vardı: her yönetici kendi
apartmanını görüyor, platformun tamamına bakan bir katman bulunmuyordu. Bir sorunu
teşhis etmek, bir hesabı askıya almak ya da tanıtım sayfasındaki bir yazım hatasını
düzeltmek için veritabanına elle girmek veya yeniden deploy etmek gerekiyordu.

`/yonetim/` bu boşluğu dolduruyor. Site içi rollerden (`yonetici`/`muhasebe`/`denetci`)
**bağımsız** bir platform rolü kullanır — ikisi karıştırılsaydı her apartman yöneticisi
tüm apartmanların verisine erişir hale gelirdi.

### Platform rolleri — `kullanicilar.platform_rolu`

| Rol | Yetki |
|---|---|
| `kullanici` | Varsayılan. Panele erişemez. |
| `destek` | Panele girer, her şeyi **görür**, hiçbir şeyi değiştiremez. |
| `superadmin` | Tam yetki: ayarlar, içerik, göç, kimliğe bürünme. |

Salt-okunurluk arayüzde düğme gizlemekle değil, `yonetim_kontrol(true)` bekçisiyle
**sunucu tarafında** uygulanır: `destek` rolüyle gönderilen her POST 403 döner ve
denetim kaydına yazılır.

### İlk süper admin (yumurta-tavuk)

Göç **hiç kimseye** süper admin yetkisi vermez — sessizce `id=1`'i yetkilendirseydi
kimin ne zaman yetkilendiği denetlenemez olurdu. İlk atama bilinçli bir adımdır:

```bash
php araclar/superadmin_ata.php liste            # mevcut platform yetkilileri
php araclar/superadmin_ata.php <kullanici_adi>  # süper admin yap
php araclar/superadmin_ata.php <kullanici_adi> destek
```

SSH erişimi yoksa `/yonetim/kurulum.php` aynı işi yapar, ama **iki koşul birlikte**
aranır: sistemde hiç süper admin olmamalı **ve** `config.php`'deki `GOC_ANAHTARI`
bilinmeli. İlk atama yapıldığı anda sayfa kendini kapatır.

Son süper adminin yetkisi ne panelden ne CLI'dan kaldırılabilir: panele girecek kimse
kalmazdı ve rol atamak için de panel gerekirdi — sistem kendini kilitlerdi.

### Zorunlu iki faktörlü doğrulama (TOTP)

Panel tüm kiracıların mali ve kişisel verisine eriştiği için tek şifreyle korunması
kabul edilmedi. `includes/totp.php` bağımlılıksız bir RFC 6238 uygulamasıdır
(Google Authenticator, Authy, Microsoft Authenticator ile uyumlu; RFC test
vektörlerine karşı doğrulanmıştır).

- **2FA kurulu olmayan hesap panele alınmaz** — şifre doğru olsa bile.
- Kurulum `/yonetim/iki_faktor.php` üzerinden yapılır ve bilinçli olarak **normal
  uygulama oturumu** ister, panel oturumu değil: aksi halde 2FA'yı kurmak için panele,
  panele girmek için 2FA'ya ihtiyaç duyulan kapalı bir döngü oluşurdu.
- **Gizli anahtar, kullanıcı geçerli bir kod üretebildiğini kanıtlayana kadar
  veritabanına yazılmaz.** Yanlış kurulmuş bir doğrulayıcı yüzünden hesap kilitlenmez.
- **Tekrar kullanım engeli**: doğrulanan zaman adımı `totp_son_adim` sütununda saklanır;
  aynı 6 haneli kod 30 saniyelik pencerede ikinci kez kabul edilmez.
- **Yedek kodlar**: 8 adet tek kullanımlık kod üretilir, yalnızca bir kez gösterilir ve
  veritabanında `password_hash()` ile saklanır. Telefon kaybedilirse tek kurtuluş yolu
  budur; tükenirse bir başka süper admin panelden 2FA'yı sıfırlayabilir (bu yetki
  **vermez**, yalnızca kaydı siler — kullanıcı kurulumu baştan yapmak zorundadır).

**QR kodu neden yok:** QR üretmek ya harici bir servise başvurmayı (TOTP gizli
anahtarını üçüncü tarafa göndermek — kabul edilemez) ya da projeye bir QR kütüphanesi
eklemeyi gerektirirdi. Bunun yerine anahtar okunabilir bloklar hâlinde gösterilir; tüm
doğrulayıcı uygulamalar "anahtarı elle gir" seçeneğini destekler.

### Kullanıcı adına görüntüleme (impersonation)

Sistemin en hassas yetkisi: bir süper admin herhangi bir yöneticinin ekranını olduğu
gibi görebilir. Destek için gerekli — kullanıcı "bende şöyle görünüyor" dediğinde tek
güvenilir yol — ama kötüye kullanımı tüm kiracıların verisini açar. Dört kural
pazarlık dışıdır:

1. **Varsayılan salt-okunur.** Yazma, `burunme_yazma_izni` platform ayarıyla ayrıca
   açılır. Kontrol `giris_kontrol()` içindeki **tek bir noktadadır** (POST düzeyinde),
   dolayısıyla yeni bir sayfa eklendiğinde koruma kendiliğinden geçerli olur —
   tek tek hatırlanması gereken bir kontrol bırakılmaz.
2. **Her şey denetim kaydına.** Başlangıç, bitiş ve süre yazılır; arada yapılan her
   işlem `burunen_yonetici_id` ile etiketlenir — aksi halde denetim izi eylemi masum
   kullanıcının üstüne yazardı.
3. **Platform yetkilisinin adına bürünülemez.** Aksi halde bir `destek` hesabı bir
   süper adminin adına bürünüp kendi rolünü yükseltebilirdi.
4. **Kapatılamaz uyarı bandı.** Ekranın başkasının verisi olduğunu unutmak, yanlış
   apartmanda işlem yapmakla sonuçlanır.

Oturum modeli bunu mümkün kılan şeydir: `$_SESSION['yonetim_id']` (gerçek yönetici) ile
`$_SESSION['kullanici_id']` (uygulamanın gördüğü kişi) **ayrı** anahtarlardır. Tek
anahtar kullanılsaydı bürünme sırasında panel yetkisi de hedef kullanıcıya geçerdi.
Bürünme başlarken yöneticinin kendi uygulama oturumu saklanır, bitince geri yüklenir.

### Bakım modu

Açıkken normal kullanıcı panele giremez; `503` ve `Retry-After` ile bir bakım sayfası
görür. Platform yetkilileri erişmeye devam eder — aksi halde bakım modunu kapatacak
kişi de dışarıda kalırdı. Tanıtım sayfası ve giriş ekranı etkilenmez.

### Sistem duyuruları

Panellerin üstünde bant olarak görünür. Tarih aralığı verilerek zamanlanabilir
(`baslangic`/`bitis`) ve `site_id` ile tek bir apartmana hedeflenebilir; boş bırakılırsa
tüm sitelere gider.

### Tanıtım sayfası içerik yönetimi

Hero metni, SEO künyesi ve SSS bugüne kadar `index.php` içine gömülüydü — bir yazım
düzeltmesi için bile deploy gerekiyordu. Artık `icerik_bloklari` ve `sss_kayitlari`
tablolarından okunur ve `/yonetim/icerik.php` üzerinden düzenlenir.

- **Kayıt yoksa koddaki varsayılana düşülür** (`includes/tanitim_icerik.php`), yani
  tablolar boş olsa da ya da göç uygulanmamış olsa da sayfa eksiksiz çizilir.
- SSS içeriği hem sayfada hem `FAQPage` yapısal verisinde **tek kaynaktan** kullanılır;
  HTML ile schema.org arasındaki uyuşmazlık Google tarafından cezalandırılır.
- Hero başlığında `|` işareti satır sonudur; sonraki bölüm vurgu (degrade) rengiyle
  çizilir. Ayraç yoksa başlık tek parça gösterilir.

### Panel güvenlik önlemleri

- **Ayrı giriş yolu** (`/yonetim/giris.php`), uygulama girişinden bağımsız.
- **Hız sınırlama iki eksende**: IP başına 10/15 dk (dağıtık deneme) ve kullanıcı adı
  başına 5/15 dk (tek hesaba yoğunlaşma).
- **Hata mesajları hangi adımın yanlış olduğunu sızdırmaz**: "böyle bir kullanıcı yok",
  "şifre yanlış" ve "bu hesabın panel yetkisi yok" durumlarının hepsi aynı mesajı verir
  — kullanıcı adı sayımına ve rol keşfine kapalıdır.
- **Rol her istekte veritabanından okunur**, oturumda saklanmaz: yetki geri alındığında
  oturum anında düşer.
- **İsteğe bağlı IP kısıtı** (tek IP veya CIDR). Kilitlenme koruması vardır: kendi IP
  adresiniz listede değilse kayıt reddedilir. Dinamik IP kullanıyorsanız (çoğu ev
  bağlantısı) bu kısıtı açmayın.
- Tüm panel yanıtlarında `X-Robots-Tag: noindex, nofollow`.
- **Denetim kaydı yalnızca okunur**: panelden silme veya düzenleme yolu bilinçli olarak
  yoktur — silinebilen bir denetim izi denetim izi değildir.

### Panelden göç çalıştırma

`/yonetim/goc.php`, kök dizindeki `/goc.php` ile aynı işi farklı bir kapıdan yapar:
orada `GOC_ANAHTARI` gerekir (henüz süper admin yokken), burada kimliği doğrulanmış bir
süper admin oturumu yeterlidir. Yedek onay kutusu işaretlenmeden düğme çalışmaz —
MySQL/MariaDB'de DDL transaction'a girmez, yarıda kalan bir göç geri alınamaz.

## Operasyonel Modüller (Talep · Demirbaş/Bakım · Personel)

> Göç: `semalar/005_operasyonel_moduller.sql`

Üç modül aynı deseni paylaşır: site kapsamlı liste, blok/daire ilişkisi, dosya eki ve
gider defteriyle bağlantı. Hepsi `site_id` ile filtrelenir; kiracı izolasyonu Faz 2'deki
modelin aynısıdır.

### Arıza / talep bildirimi — `talepler.php`

**Tasarım kararı — talebi kim açar:** Sakin girişi henüz yok, bu yüzden talebi
**yönetici veya personel** açar; telefonla ya da kapıda gelen arıza sisteme buradan
girilir. Bu, bugünkü iş akışına uyar ve ayrı bir sakin portalı gerektirmez. Daire bazlı
tokenli bağlantı (WhatsApp ile sakine gönderilen, girişsiz talep formu) sonraki bir
aşamaya bırakıldı.

- **Durum akışı:** Açık → İşlemde → Beklemede → Çözüldü → Kapalı (+ İptal)
- **Kategoriler:** Asansör, su/tesisat, elektrik, ısıtma, temizlik, güvenlik, ortak alan, diğer
- **Öncelik:** Düşük / Normal / Yüksek / Acil — liste öncelik sırasına göre dizilir
- Her durum değişikliği `talep_yorumlari`'na `durum_eski`/`durum_yeni` ile yazılır;
  "bu talep ne zaman kim tarafından çözüldü?" sorusu böyle cevaplanır
- Fotoğraf ve belge eki, personel ataması, bildiren kişi kaydı
- **Maliyet → gider:** girilen tutar gider defterine `TAMİRAT` kategorisiyle işlenir

Açık talep sayısı dashboard'da uyarı kartı olarak görünür.

### Demirbaş ve bakım — `demirbaslar.php`

**Modülün asıl değeri envanter listesi değil, hatırlatmadır.** Asansör yıllık muayenesi,
yangın tüpü dolum kontrolü, jeneratör ve paratoner ölçümü **yasal zorunluluktur**;
kaçırılması yönetim için ciddi sorumluluk doğurur. Buna karşı iki mekanizma var:

1. **Otomatik zincir.** Bir bakım "yapıldı" işaretlendiğinde, `periyot_ay` doluysa bir
   sonraki planlı kayıt kendiliğinden oluşturulur. Yöneticinin her seferinde "bir
   sonrakini de gireyim" demesi beklenirse kaçınılmaz olarak unutulur. Zincir yalnızca
   duruma **geçişte** üretilir; aynı kayıt tekrar düzenlendiğinde ikinci bir kopya çıkmaz.
2. **Hatırlatma.** Yaklaşan ve **geciken** bakımlar dashboard'da ve modül sayfasının
   üstünde gösterilir. `araclar/bakim_hatirlatma.php` cron ile çalıştırılarak e-posta
   gönderir — panele bakmayan yöneticiye de ulaşır.

Geçmiş tarihli planlı bakımlar listeden **düşmez**: "tarihi geçti, artık gösterme"
davranışı modülün amacını boşa çıkarırdı.

```bash
# cPanel cron — günde bir kez
php /home/<kullanici>/public_html/araclar/bakim_hatirlatma.php --gun=14

php araclar/bakim_hatirlatma.php --gun=30 --deneme   # göndermeden ne olacağını yazdırır
```

Aynı bakım için ikinci kez e-posta gönderilmez (`bakimlar.hatirlatma_gonderildi`).
E-posta yalnızca **doğrulanmış** adresi olan site yöneticilerine gider; bir siteye ait
tüm bakımlar tek iletide toplanır.

**Yapılmış** bir bakımın tutarı gider defterine `BAKIM` kategorisiyle işlenir. Planlı
(henüz yapılmamış) bir bakımın tahmini bedeli **giderlere girmez** — tahmini harcama
gerçekleşmiş gibi görünmemeli.

### Personel — `personel.php`

- Görev (kapıcı, güvenlik, temizlik, teknisyen, bahçıvan, diğer), telefon, işe
  başlama/ayrılma, aylık ücret
- Ödeme türleri: maaş, avans, ikramiye, SGK primi, diğer
- Personelin üstündeki açık talep sayısı listede görünür
- Ayrılma tarihi girildiğinde durum otomatik "Ayrıldı" olur — ikisi ayrışırsa aktif
  personel listesi ve aylık ücret yükü yanlış hesaplanırdı

**KVKK notu:** Kimlik numarası, SGK sicili gibi alanlar **bilinçli olarak
toplanmıyor.** Bunlar özel nitelikli kişisel veri kategorisine girer; aidat takibi için
gerekmiyor. Toplanacaksa erişim kısıtı ve saklama süresi ayrıca tanımlanmalıdır.

### Gider entegrasyonu — çift sayım nasıl önleniyor

Bakım tutarı, personel ödemesi ve talep maliyeti hem kendi modülünde hem gider
defterinde görünmeli, ama **aynı para iki kez sayılmamalı.** Çözüm:

- Kayıt oluşturulduğunda otomatik bir gider satırı üretilir; iki kayıt
  `giderler.kaynak_tur` + `kaynak_id` ve kaynak tablodaki `gider_id` ile **karşılıklı**
  bağlanır.
- Bu satır **giderler ekranından silinemez ve düzenlenemez** — düğmeler gizlenmekle
  kalmaz, sorgular da `kaynak_tur IS NULL` koşuluyla korunur, yani adres çubuğundan
  zorlanamaz. Onun yerine kaynak kayda giden bir bağlantı gösterilir.
- Kaynak kayıt silinince bağlı gider satırı da silinir. Tutar sıfırlanır/boşaltılırsa
  gider satırı kaldırılır; değiştirilirse güncellenir (yeni satır açılmaz).

`kaynak_tur` NULL olan satırlar elle girilmiş normal giderlerdir ve eskisi gibi
düzenlenip silinebilir.

### Dosya ekleri

Yüklenen fotoğraf, fatura ve rapor belgeleri `includes/dosya.php` üzerinden **web
kökünün dışında** saklanır (`DOSYA_KOK`, varsayılan olarak `public_html`'in bir üstü).
Veritabanında yalnızca göreli yol ve künye tutulur.

- İndirme tek kapıdan yapılır: `belge_indir.php?id=` önce oturumu, sonra dosyanın
  **aktif siteye ait olduğunu** doğrular. Ek id'leri ardışık olduğu için başka bir
  apartmanın faturasını id tahminiyle indirme denemesi beklenmelidir; sorgu `site_id`
  ile filtrelendiğinden böyle bir istek 404 alır ve denetim kaydına yazılır.
- Yetkisiz erişim ile "gerçekten yok" ayrımı yapılmaz — aksi halde hangi id'lerin var
  olduğu sayılabilirdi.
- Dosya her zaman **ek olarak** indirilir (`Content-Disposition: attachment` +
  `nosniff`): yüklenmiş bir HTML/SVG'nin tarayıcıda çalıştırılıp oturum çalmasını önler.
- Uzantıya tek başına güvenilmez: `finfo` ile gerçek içerik türü okunur ve uzantıyla
  eşleşmesi aranır, dosya adı diskte yeniden üretilir. `fatura.php.jpg` gibi dosyalar
  reddedilir.
- Bir kayıt silindiğinde ekleri **diskten de** silinir; FK cascade yalnızca veritabanı
  satırını kaldırır.

### Çapraz site referansı koruması

Talebin dairesi/personeli, demirbaşın bloğu gibi yabancı anahtarlar formdan gelir ve
`site_kaydi_gecerli_mi()` / `gecerli_blok_id()` ile doğrulanır. Başka bir apartmana ait
bir id gönderilirse sessizce `NULL` yazılır — kayıt oluşur ama çapraz site bağlantısı
kurulmaz.

### Göç uygulanmadan önce

`operasyon_semasi_hazir_mi()` kontrolü sayesinde uygulama göç 005 uygulanmadan da
çalışır: üç modül menüde görünmez, doğrudan açılırsa bilgilendirme gösterir, gider
ekranı eski davranışına döner.

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
- Platform yönetim paneli (`/yonetim/`) ayrı bir giriş yolu, zorunlu TOTP, iki eksenli hız sınırlama ve isteğe bağlı IP kısıtı ile korunur; her panel işlemi denetim kaydına yazılır — bkz. "Platform Yönetimi".
- Yüklenen dosyalar web kökünün dışında saklanır ve yalnızca `belge_indir.php` üzerinden, aktif site doğrulandıktan sonra sunulur; gerçek MIME kontrolü uzantıya güvenmez — bkz. "Operasyonel Modüller".
- Formdan gelen yabancı anahtarlar (daire, personel, blok) ait oldukları siteye karşı doğrulanır; başka bir apartmanın kaydına referans verilemez.

**Bilinen sınırlamalar / öneriler:**
- Uygulama giriş formunda (`login.php`) kaba kuvvet koruması yok; hız sınırlama şimdilik yalnızca şifre sıfırlama ve yönetim panelinde uygulanıyor.
- ~~Şifre sıfırlama (e-posta ile) akışı yok.~~ → Eklendi, bkz. "Şifre Sıfırlama & E-posta Doğrulama".
- Minimum şifre uzunluğu 6 karakterdir, karmaşıklık zorunluluğu yoktur.
- İki faktörlü doğrulama yalnızca platform yetkilileri için zorunludur; normal kullanıcılara henüz sunulmuyor (altyapı `includes/totp.php` içinde hazır).

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

Aynı şekilde uygulama, göç uygulanmadan da çalışır. Üç ayrı hazırlık kontrolü var ve
her biri ilgili özelliği sessizce devre dışı bırakıp sistemin geri kalanını ayakta
tutar:

| Kontrol | Göç | Uygulanmadığında |
|---|---|---|
| `eposta_semasi_hazir_mi()` | 001 | E-posta arayüzü ve şifre sıfırlama gizlenir |
| `site_semasi_hazir_mi()` | 003 | Eski tek-site davranışına düşülür; site seçici ve blok arayüzü gizlenir |
| `platform_semasi_hazir_mi()` | 004 | Yönetim paneli erişilemez; tanıtım sayfası koddaki varsayılan metinleri kullanır |
| `operasyon_semasi_hazir_mi()` | 005 | Talep/demirbaş/personel modülleri menüde görünmez; gider ekranı eski davranışına döner |

Doğru sıra:

1. Dosyaları dağıt (deploy)
2. `goc.php` veya `araclar/goc_cli.php` ile göçleri uygula
3. `config.php`'ye SMTP ayarlarını gir
4. `php araclar/superadmin_ata.php <kullanici_adi>` ile ilk süper adminı ata
5. O hesapla `/yonetim/iki_faktor.php` üzerinden 2FA'yı kur ve yedek kodları sakla
6. Bakım hatırlatma için cron tanımla: `php araclar/bakim_hatirlatma.php --gun=14` (günde bir kez)

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
