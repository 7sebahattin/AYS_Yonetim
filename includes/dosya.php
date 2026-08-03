<?php
// ============================================================
//  includes/dosya.php — DOSYA YÜKLEME VE SAKLAMA
//
//  Belge yönetimi (Faz 5), demirbaş fatura/garanti belgeleri ve arıza
//  fotoğrafları (Faz 4) bu katmanı kullanacak.
//
//  KRİTİK GÜVENLİK KARARI — dosyalar web kökünün DIŞINDA saklanır:
//  Yüklenen içerik sakin isimleri, faturalar, sözleşmeler gibi kişisel
//  ve mali veri barındırır. public_html altında dursaydı, dosya adını
//  tahmin eden herkes oturum açmadan indirebilirdi. Bunun yerine
//  DOSYA_KOK (varsayılan: public_html'in bir üstü) altında saklanır ve
//  yalnızca yetki kontrolü yapan bir bekçi betiği üzerinden sunulur.
//
//  Ayrıca uzantıya asla tek başına güvenilmez: "fatura.php.jpg" gibi
//  adlar ve sahte MIME başlıkları için hem gerçek içerik türü
//  (finfo) hem uzantı doğrulanır, dosya adı yeniden üretilir.
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/varsayilanlar.php';

// İzin verilen türler: uzantı => gerçek MIME karşılığı
function dosya_izinli_turler(): array {
    return [
        'pdf'  => ['application/pdf'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'webp' => ['image/webp'],
        'doc'  => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xls'  => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
    ];
}

function dosya_kok_hazirla(): bool {
    if (is_dir(DOSYA_KOK)) return true;
    return @mkdir(DOSYA_KOK, 0750, true);
}

// PHP'nin yükleme hata kodunu okunabilir Türkçe mesaja çevirir.
function dosya_yukleme_hatasi(int $kod): string {
    return match ($kod) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Dosya boyutu sunucu sınırını aşıyor.',
        UPLOAD_ERR_PARTIAL    => 'Dosya yalnızca kısmen yüklendi, tekrar deneyin.',
        UPLOAD_ERR_NO_FILE    => 'Dosya seçilmedi.',
        UPLOAD_ERR_NO_TMP_DIR => 'Sunucuda geçici klasör bulunamadı.',
        UPLOAD_ERR_CANT_WRITE => 'Dosya sunucuya yazılamadı.',
        UPLOAD_ERR_EXTENSION  => 'Yükleme bir sunucu eklentisi tarafından durduruldu.',
        default               => 'Dosya yüklenemedi.',
    };
}

/**
 * Bir $_FILES girdisini doğrular ve saklar.
 *
 * @param array  $dosya       $_FILES['alan_adi']
 * @param string $alt_klasor  DOSYA_KOK altında mantıksal klasör (ör. "belgeler/12")
 * @return array{basarili:bool, mesaj:string, yol?:string, orijinal_ad?:string, mime?:string, boyut?:int}
 */
function dosya_yukle(array $dosya, string $alt_klasor): array {
    if (!isset($dosya['error']) || is_array($dosya['error'])) {
        return ['basarili' => false, 'mesaj' => 'Geçersiz yükleme isteği.'];
    }
    if ($dosya['error'] !== UPLOAD_ERR_OK) {
        return ['basarili' => false, 'mesaj' => dosya_yukleme_hatasi((int)$dosya['error'])];
    }
    // Dosyanın gerçekten HTTP yüklemesiyle geldiğini doğrular; yerel bir
    // yolun ($_FILES taklidiyle) okunmasını engeller.
    if (!is_uploaded_file($dosya['tmp_name'])) {
        return ['basarili' => false, 'mesaj' => 'Geçersiz yükleme kaynağı.'];
    }
    if ((int)$dosya['size'] <= 0) {
        return ['basarili' => false, 'mesaj' => 'Dosya boş.'];
    }
    if ((int)$dosya['size'] > DOSYA_MAX_BOYUT) {
        $mb = round(DOSYA_MAX_BOYUT / 1048576, 1);
        return ['basarili' => false, 'mesaj' => "Dosya çok büyük (en fazla {$mb} MB)."];
    }

    $orijinal_ad = (string)$dosya['name'];
    $uzanti = mb_strtolower(pathinfo($orijinal_ad, PATHINFO_EXTENSION), 'UTF-8');
    $izinli = dosya_izinli_turler();

    if (!isset($izinli[$uzanti])) {
        return ['basarili' => false, 'mesaj' => 'Bu dosya türüne izin verilmiyor (' . e($uzanti) . ').'];
    }

    // Uzantıya güvenme: dosyanın gerçek içerik türünü oku.
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $gercek_mime = (string)$finfo->file($dosya['tmp_name']);
    if (!in_array($gercek_mime, $izinli[$uzanti], true)) {
        return ['basarili' => false, 'mesaj' => 'Dosya içeriği uzantısıyla uyuşmuyor.'];
    }

    if (!dosya_kok_hazirla()) {
        return ['basarili' => false, 'mesaj' => 'Depolama klasörü oluşturulamadı.'];
    }

    // Klasör adını normalize et (dizin atlama girişimlerini engelle)
    $alt_klasor = trim(preg_replace('/[^a-zA-Z0-9_\/-]/', '', $alt_klasor), '/');
    $hedef_dizin = DOSYA_KOK . ($alt_klasor !== '' ? '/' . $alt_klasor : '');
    if (!is_dir($hedef_dizin) && !@mkdir($hedef_dizin, 0750, true)) {
        return ['basarili' => false, 'mesaj' => 'Hedef klasör oluşturulamadı.'];
    }

    // Dosya adı yeniden üretilir: orijinal ad veritabanında saklanır,
    // diskte tahmin edilemez bir ad kullanılır.
    $yeni_ad = date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $uzanti;
    $tam_yol = $hedef_dizin . '/' . $yeni_ad;

    if (!move_uploaded_file($dosya['tmp_name'], $tam_yol)) {
        return ['basarili' => false, 'mesaj' => 'Dosya kaydedilemedi.'];
    }
    @chmod($tam_yol, 0640);

    return [
        'basarili'    => true,
        'mesaj'       => 'Dosya yüklendi.',
        // Göreli yol saklanır: DOSYA_KOK değişse bile kayıtlar geçerli kalır.
        'yol'         => ($alt_klasor !== '' ? $alt_klasor . '/' : '') . $yeni_ad,
        'orijinal_ad' => mb_substr($orijinal_ad, 0, 190, 'UTF-8'),
        'mime'        => $gercek_mime,
        'boyut'       => (int)$dosya['size'],
    ];
}

// Göreli yolu güvenli mutlak yola çevirir. DOSYA_KOK dışına çıkan
// yollar (../ vb.) reddedilir.
function dosya_tam_yol(string $goreli_yol): ?string {
    $aday = DOSYA_KOK . '/' . ltrim($goreli_yol, '/');
    $gercek = realpath($aday);
    $kok    = realpath(DOSYA_KOK);
    if ($gercek === false || $kok === false) return null;
    if (!str_starts_with($gercek, $kok . DIRECTORY_SEPARATOR)) return null;
    return $gercek;
}

function dosya_sil(string $goreli_yol): bool {
    $yol = dosya_tam_yol($goreli_yol);
    return $yol !== null && @unlink($yol);
}

// Dosyayı indirme olarak akıtır. ÇAĞIRAN, yetki kontrolünü kendisi
// yapmış olmalıdır — bu fonksiyon yetki denetlemez.
//
// $satir_ici=true ise tarayıcı dosyayı indirmek yerine sekmede/önizlemede
// gösterir (Content-Disposition: inline). YALNIZCA çağıran tarafından
// gerçek MIME türü doğrulanmış (finfo, yükleme sırasında) güvenli raster
// görsel türleri için kullanılmalı — SVG/HTML gibi betik çalıştırabilen
// türler zaten dosya_izinli_turler() tarafından hiç kabul edilmiyor.
function dosya_akit(string $goreli_yol, string $indirme_adi, string $mime, bool $satir_ici = false): void {
    $yol = dosya_tam_yol($goreli_yol);
    if ($yol === null || !is_file($yol)) {
        http_response_code(404);
        exit('Dosya bulunamadı.');
    }
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($yol));
    $yerlesim = $satir_ici ? 'inline' : 'attachment';
    header('Content-Disposition: ' . $yerlesim . '; filename="' . rawurlencode($indirme_adi) . '"');
    header('X-Content-Type-Options: nosniff');
    readfile($yol);
    exit;
}
