<?php
// ============================================================
//  includes/tanitim_icerik.php — TANITIM SAYFASI VARSAYILAN METİNLERİ
//
//  Bu metinler artık paneldeki İçerik Yönetimi'nden düzenlenebiliyor.
//  Buradaki değerler VARSAYILAN: ilgili veritabanı kaydı yoksa (ya da
//  göç 004 henüz uygulanmadıysa) sayfa bunlarla çizilir.
//
//  Tek dosyada tutulmalarının sebebi, aynı metinlerin iki yerden
//  okunması: tanıtım sayfası (index.php) ve panelin "varsayılanları
//  içe aktar" düğmesi (yonetim/icerik.php). İkiye kopyalanmış metin
//  er ya da geç ayrışırdı.
// ============================================================

function varsayilan_sss(): array
{
    return [
        [
            's' => 'AYS apartman yönetim yazılımı nedir?',
            'c' => 'AYS, apartman ve site yöneticilerinin aidat tahsilatını, giderleri ve '
                 . 'daire bilgilerini tek bir panelden takip etmesini sağlayan web tabanlı bir '
                 . 'yönetim yazılımıdır. Tarayıcı üzerinden çalışır, bilgisayara program '
                 . 'kurmanız gerekmez.',
        ],
        [
            's' => 'Site aidat takip sistemi nasıl çalışır?',
            'c' => 'Dairelerinizi ve aylık aidat tutarlarını bir kez tanımlarsınız. Ardından '
                 . 'istediğiniz dönem aralığı için aidat kayıtları toplu şekilde oluşturulur. '
                 . 'Ödeme geldikçe tek tıkla "ödendi" işaretler, tahsilat oranını anlık '
                 . 'görürsünüz.',
        ],
        [
            's' => 'Aidat borcu olan daire sakinlerine bildirim gönderebilir miyim?',
            'c' => 'Evet. Daire detay sayfasındaki WhatsApp butonu, o daireye ait ödenmemiş '
                 . 'dönemleri ve son ödemeleri içeren hazır bir mesaj oluşturur. Mesajı '
                 . 'göndermeden önce düzenleyebilirsiniz.',
        ],
        [
            's' => 'Aidat ve gider raporlarını yazdırabilir miyim?',
            'c' => 'Evet. Aidat detayı, gider detayı, gelir-gider trendi ve daire bazlı '
                 . 'geçmiş için A4 sayfaya optimize edilmiş, imza alanı içeren raporlar tek '
                 . 'tıkla yazdırılabilir.',
        ],
        [
            's' => 'Birden fazla apartman veya blok yönetebilir miyim?',
            'c' => 'Evet. Tek hesapla birden fazla apartman ya da site yönetebilir, üst '
                 . 'menüden aralarında geçiş yapabilirsiniz. Her site kendi bloklarına '
                 . '(A Blok, B Blok…) ayrılabilir ve daireler bloklara atanır.',
        ],
        [
            's' => 'Verilerim güvende mi?',
            'c' => 'Her yönetimin verisi kendi hesabıyla izole edilir; başka bir apartmanın '
                 . 'kayıtlarına erişilemez. Şifreler geri döndürülemez şekilde şifrelenerek '
                 . 'saklanır, tüm formlar CSRF korumalıdır ve bağlantı HTTPS üzerinden '
                 . 'yapılır.',
        ],
        [
            's' => 'Kullanmak için ücret ödemem gerekiyor mu?',
            'c' => 'Hesap oluşturmak ve apartmanınızı kurmak ücretsizdir. Kayıt olduktan '
                 . 'sonra daire sayınızı belirler ve sistemi hemen kullanmaya başlarsınız.',
        ],
    ];
}

function varsayilan_hero(): array
{
    return [
        // '|' işareti satır sonunu belirtir; sonraki bölüm vurgu
        // (degrade) rengiyle çizilir. Panelde de bu kural anlatılıyor.
        'baslik' => 'Aidat takibi artık|dakikalar sürüyor',
        'govde'  => 'AYS; aidat tahsilatını, giderleri ve daire bilgilerini tek panelde toplar. '
                  . 'WhatsApp ile borç bildirir, gelir-gider raporlarını A4 düzeninde yazdırır. '
                  . 'Kurulum yok, mobil uyumlu, ücretsiz başlayın.',
    ];
}

function varsayilan_seo(): array
{
    return [
        'baslik' => 'Apartman Yönetim Yazılımı | Site Aidat Takip Sistemi — AYS',
        'govde'  => 'AYS ile apartman ve site yönetimi tek panelde: otomatik aidat takibi, '
                  . 'WhatsApp ile borç bildirimi, gelir-gider raporları ve tek tıkla A4 rapor '
                  . 'yazdırma. Kurulum gerektirmez, mobil uyumludur, ücretsiz başlayın.',
    ];
}

function varsayilan_neden_ays(): array
{
    return [
        'baslik' => 'Neden AYS?',
        'govde'  => 'Yönetici defteri, dağınık Excel dosyaları ve kaybolan dekontlar geride kalsın.',
    ];
}
