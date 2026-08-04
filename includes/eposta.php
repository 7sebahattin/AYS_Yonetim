<?php
// ============================================================
//  includes/eposta.php — E-POSTA GÖNDERİMİ (SMTP)
//
//  Neden PHP'nin mail() fonksiyonu değil: Paylaşımlı hostingde mail()
//  kimlik doğrulaması yapmadan gönderir; Gmail/Outlook bu iletileri
//  büyük oranda spam'e atar. Şifre sıfırlama e-postası ulaşmazsa
//  özellik işlevsiz kalır, bu yüzden kimliği doğrulanmış SMTP kullanılır.
//
//  Kütüphane: PHPMailer (vendor/PHPMailer, elle eklendi). Composer
//  kullanılmıyor çünkü deploy düz FTP dosya kopyalamasıdır — sunucuda
//  "composer install" çalıştıran bir adım yok.
//
//  ÖNEMLİ — teslim edilebilirlik: SMTP tek başına yetmez. Alan adına
//  SPF ve DKIM DNS kayıtları eklenmezse iletiler yine spam'e düşer.
//  Bu bir DNS yapılandırması işidir, kod işi değil.
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/varsayilanlar.php';

require_once __DIR__ . '/../vendor/PHPMailer/Exception.php';
require_once __DIR__ . '/../vendor/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../vendor/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

// SMTP ayarları girilmiş mi? Girilmemişse özellik "yapılandırılmamış"
// sayılır; arayüz bunu kullanıcıya söyler, sistem çökmez.
function eposta_yapilandirildi_mi(): bool {
    return SMTP_HOST !== '' && SMTP_KULLANICI !== '' && EPOSTA_GONDEREN !== '';
}

// Tek bir e-posta gönderir. Sonucu eposta_kaydi tablosuna yazar.
// Dönüş: true = gönderildi, false = gönderilemedi (ayrıntı log'da)
function eposta_gonder(
    string $alici,
    string $konu,
    string $html_govde,
    string $duz_govde = '',
    string $sablon = ''
): bool {
    if (!eposta_yapilandirildi_mi()) {
        eposta_kaydi_yaz($alici, $konu, $sablon, 'hata', 'SMTP yapılandırılmamış (config.php)');
        error_log('E-posta gönderilemedi: SMTP yapılandırılmamış.');
        return false;
    }

    $posta = new PHPMailer(true);
    try {
        $posta->isSMTP();
        $posta->Host       = SMTP_HOST;
        $posta->Port       = (int)SMTP_PORT;
        $posta->SMTPAuth   = true;
        $posta->Username   = SMTP_KULLANICI;
        $posta->Password   = SMTP_SIFRE;
        $posta->CharSet    = PHPMailer::CHARSET_UTF8; // Türkçe karakterler için şart
        $posta->Encoding   = PHPMailer::ENCODING_BASE64;
        $posta->Timeout    = 20;

        if (SMTP_GUVENLIK === 'ssl') {
            $posta->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif (SMTP_GUVENLIK === 'tls') {
            $posta->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $posta->SMTPSecure  = '';
            $posta->SMTPAutoTLS = false;
        }

        $posta->setFrom(EPOSTA_GONDEREN, EPOSTA_GONDEREN_AD);
        $posta->addAddress($alici);
        $posta->Subject = $konu;
        $posta->isHTML(true);
        $posta->Body    = $html_govde;
        $posta->AltBody = $duz_govde !== '' ? $duz_govde : strip_tags($html_govde);

        $posta->send();
        eposta_kaydi_yaz($alici, $konu, $sablon, 'basarili', null);
        return true;
    } catch (Throwable $ex) {
        $hata = $posta->ErrorInfo ?: $ex->getMessage();
        eposta_kaydi_yaz($alici, $konu, $sablon, 'hata', $hata);
        error_log('E-posta gönderilemedi (' . $alici . '): ' . $hata);
        return false;
    }
}

// Gönderim sonucunu kaydeder. "E-posta gelmedi" şikayetini teşhis
// edebilmek için; jeton/şifre gibi gizli içerik ASLA saklanmaz.
function eposta_kaydi_yaz(string $alici, string $konu, string $sablon, string $durum, ?string $hata): void {
    try {
        db()->prepare("
            INSERT INTO eposta_kaydi (alici, konu, sablon, durum, hata_mesaji)
            VALUES (?,?,?,?,?)
        ")->execute([
            mb_substr($alici, 0, 190, 'UTF-8'),
            mb_substr($konu, 0, 255, 'UTF-8'),
            $sablon !== '' ? mb_substr($sablon, 0, 60, 'UTF-8') : null,
            $durum,
            $hata,
        ]);
    } catch (Throwable $ex) {
        error_log('E-posta kaydı yazılamadı: ' . $ex->getMessage());
    }
}

// Markalı HTML e-posta şablonu. Tablo tabanlı düzen ve satır içi stil
// kullanılır — e-posta istemcileri (özellikle Outlook) modern CSS'i
// desteklemez.
function eposta_sablonu(string $baslik, string $govde_html, string $buton_metni = '', string $buton_url = '', string $alt_not = ''): string {
    $yil = date('Y');
    $buton = '';
    if ($buton_metni !== '' && $buton_url !== '') {
        $buton = '
        <tr><td style="padding:8px 0 24px">
          <a href="' . htmlspecialchars($buton_url, ENT_QUOTES, 'UTF-8') . '"
             style="display:inline-block;background:#e94560;color:#ffffff;text-decoration:none;
                    padding:13px 26px;border-radius:8px;font-weight:600;font-size:15px">'
             . htmlspecialchars($buton_metni, ENT_QUOTES, 'UTF-8') . '</a>
        </td></tr>';
    }
    $alt = '';
    if ($alt_not !== '') {
        $alt = '<tr><td style="padding-top:8px;font-size:12.5px;color:#7a7a9a;line-height:1.6">'
             . $alt_not . '</td></tr>';
    }
    $baslik_g = htmlspecialchars($baslik, ENT_QUOTES, 'UTF-8');
    // E-posta istemcileri site kökünden göreli yol çözemez; mutlak URL şart.
    // varlik() göreli yolu sürümlü döndürür, başına mutlak adres eklenir.
    $logo_url = rtrim(SITE_ADRESI, '/') . varlik('/assets/icons/icon-192.png');

    return <<<HTML
<!DOCTYPE html>
<html lang="tr"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f0f2f5;font-family:-apple-system,'Segoe UI',Arial,sans-serif">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f0f2f5;padding:28px 12px">
<tr><td align="center">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
         style="max-width:520px;background:#ffffff;border-radius:14px;overflow:hidden;
                box-shadow:0 2px 12px rgba(0,0,0,.07)">
    <tr><td style="background:linear-gradient(135deg,#e94560,#c73652);padding:22px 28px">
      <img src="{$logo_url}" width="26" height="26" alt="AYS"
           style="width:26px;height:26px;border-radius:6px;vertical-align:middle;display:inline-block">
      <span style="color:#ffffff;font-size:19px;font-weight:700;letter-spacing:.5px;vertical-align:middle;margin-left:8px">AYS</span>
      <span style="color:rgba(255,255,255,.85);font-size:13px;display:block;margin-top:2px">
        Apartman Yönetim Sistemi</span>
    </td></tr>
    <tr><td style="padding:28px">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr><td style="font-size:18px;font-weight:700;color:#1a1a2e;padding-bottom:14px">{$baslik_g}</td></tr>
        <tr><td style="font-size:14.5px;color:#3d3d55;line-height:1.7;padding-bottom:20px">{$govde_html}</td></tr>
        {$buton}
        {$alt}
      </table>
    </td></tr>
    <tr><td style="background:#fafbfc;padding:16px 28px;border-top:1px solid #eceef1;
                   font-size:12px;color:#8a8aa3;line-height:1.6">
      Bu ileti AYS tarafından otomatik gönderilmiştir, lütfen yanıtlamayın.<br>
      &copy; {$yil} AYS — Apartman Yönetim Sistemi
    </td></tr>
  </table>
</td></tr></table>
</body></html>
HTML;
}
