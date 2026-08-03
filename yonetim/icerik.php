<?php
// ============================================================
//  yonetim/icerik.php — TANITIM SAYFASI İÇERİĞİ
//
//  index.php'deki metinler ve SSS bugüne kadar koda gömülüydü: bir
//  yazım hatası düzeltmek için bile deploy gerekiyordu. Buradan
//  düzenlenir.
//
//  Kayıt yoksa uygulama koddaki varsayılan metne düşer, dolayısıyla
//  bu tabloların boş olması tanıtım sayfasını bozmaz.
//
//  SSS içeriği hem sayfada hem de FAQPage yapısal verisinde TEK
//  KAYNAKTAN kullanılır; HTML ile schema.org arasındaki uyuşmazlık
//  Google tarafından cezalandırıldığı için ikisi ayrışmamalıdır.
// ============================================================

require_once __DIR__ . '/ortak.php';
require_once __DIR__ . '/../includes/tanitim_icerik.php';

$yonetici = yonetim_kontrol();

// Panelde düzenlenebilir metin blokları ve neye karşılık geldikleri
$BLOKLAR = [
    'hero'      => ['ad' => 'Hero başlığı',   'aciklama' => 'Tanıtım sayfasının en üstündeki büyük başlık ve alt metin. '
                                                          . 'Başlıkta "|" işareti satır sonu demektir; sonraki bölüm vurgu rengiyle çizilir.'],
    'seo'       => ['ad' => 'SEO künyesi',    'aciklama' => 'Tarayıcı sekmesi başlığı (başlık) ve meta description (gövde)'],
    'neden_ays' => ['ad' => '"Neden AYS?"',   'aciklama' => 'Özellik kartlarının altındaki bölüm'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    yonetim_yazma_kontrol();
    $islem = $_POST['islem'] ?? '';

    if ($islem === 'blok') {
        $anahtar = $_POST['anahtar'] ?? '';
        if (!isset($BLOKLAR[$anahtar])) {
            flash('Geçersiz içerik bloğu.', 'hata');
        } else {
            db()->prepare("INSERT INTO icerik_bloklari (anahtar, baslik, govde) VALUES (?,?,?)
                           ON DUPLICATE KEY UPDATE baslik = VALUES(baslik), govde = VALUES(govde)")
                ->execute([$anahtar, trim($_POST['baslik'] ?? ''), trim($_POST['govde'] ?? '')]);
            yonetim_denetim('icerik_guncellendi', 'icerik', null, ['anahtar' => $anahtar]);
            flash($BLOKLAR[$anahtar]['ad'] . ' güncellendi.');
        }
    }

    if ($islem === 'sss_ekle') {
        $soru  = trim($_POST['soru'] ?? '');
        $cevap = trim($_POST['cevap'] ?? '');
        if ($soru === '' || $cevap === '') {
            flash('Soru ve cevap zorunludur.', 'hata');
        } else {
            $sira = (int)db()->query("SELECT COALESCE(MAX(sira),0)+1 FROM sss_kayitlari")->fetchColumn();
            db()->prepare("INSERT INTO sss_kayitlari (soru, cevap, sira) VALUES (?,?,?)")
                ->execute([$soru, $cevap, $sira]);
            yonetim_denetim('sss_eklendi', 'sss', (int)db()->lastInsertId());
            flash('SSS kaydı eklendi.');
        }
    }

    if ($islem === 'sss_guncelle') {
        $id = (int)($_POST['id'] ?? 0);
        db()->prepare("UPDATE sss_kayitlari SET soru=?, cevap=?, sira=?, durum=? WHERE id=?")
            ->execute([
                trim($_POST['soru'] ?? ''),
                trim($_POST['cevap'] ?? ''),
                (int)($_POST['sira'] ?? 1),
                ($_POST['durum'] ?? '') === 'pasif' ? 'pasif' : 'aktif',
                $id,
            ]);
        yonetim_denetim('sss_guncellendi', 'sss', $id);
        flash('SSS kaydı güncellendi.');
    }

    if ($islem === 'sss_sil') {
        $id = (int)($_POST['id'] ?? 0);
        db()->prepare("DELETE FROM sss_kayitlari WHERE id = ?")->execute([$id]);
        yonetim_denetim('sss_silindi', 'sss', $id);
        flash('SSS kaydı silindi.');
    }

    // Koddaki varsayılan SSS'leri tabloya kopyalar: panel ilk kez
    // kullanılırken sıfırdan yazmak yerine mevcut metinler üzerinden
    // devam edilebilsin diye.
    if ($islem === 'sss_tohumla') {
        $mevcut = (int)db()->query("SELECT COUNT(*) FROM sss_kayitlari")->fetchColumn();
        if ($mevcut > 0) {
            flash('SSS tablosu boş değil; içe aktarma yapılmadı.', 'uyari');
        } else {
            $ins = db()->prepare("INSERT INTO sss_kayitlari (soru, cevap, sira) VALUES (?,?,?)");
            $sira = 1;
            foreach (varsayilan_sss() as $x) {
                $ins->execute([$x['s'], $x['c'], $sira++]);
            }
            yonetim_denetim('sss_tohumlandi', 'sss', null, ['adet' => $sira - 1]);
            flash(($sira - 1) . ' varsayılan SSS kaydı içe aktarıldı.');
        }
    }

    header('Location: /yonetim/icerik.php');
    exit;
}

$mevcut_bloklar = [];
foreach (db()->query("SELECT anahtar, baslik, govde FROM icerik_bloklari") as $b) {
    $mevcut_bloklar[$b['anahtar']] = $b;
}
$sss = db()->query("SELECT * FROM sss_kayitlari ORDER BY sira, id")->fetchAll();

$yazabilir = platform_yazabilir_mi($yonetici['platform_rolu']);
yonetim_basla($yonetici, 'İçerik Yönetimi');
?>

<p class="y-geri">
  Değişiklikler <a href="/" target="_blank" rel="noopener">tanıtım sayfasında</a> anında yayına girer.
  Alan boş bırakılırsa koddaki varsayılan metin kullanılır.
</p>

<?php foreach ($BLOKLAR as $anahtar => $bilgi): ?>
<div class="y-bolum">
  <h2><?= e($bilgi['ad']) ?></h2>
  <p class="y-soluk y-kucuk-yazi"><?= e($bilgi['aciklama']) ?></p>
  <form method="post" class="y-form">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="islem" value="blok">
    <input type="hidden" name="anahtar" value="<?= e($anahtar) ?>">
    <label>Başlık
      <input type="text" name="baslik" maxlength="200" <?= $yazabilir ? '' : 'disabled' ?>
             value="<?= e($mevcut_bloklar[$anahtar]['baslik'] ?? '') ?>">
    </label>
    <label>Gövde
      <textarea name="govde" rows="3" <?= $yazabilir ? '' : 'disabled' ?>><?= e($mevcut_bloklar[$anahtar]['govde'] ?? '') ?></textarea>
    </label>
    <?php if ($yazabilir): ?><button type="submit">Kaydet</button><?php endif; ?>
  </form>
</div>
<?php endforeach; ?>

<div class="y-bolum">
  <h2>Sıkça Sorulan Sorular</h2>
  <p class="y-soluk y-kucuk-yazi">
    Bu liste hem tanıtım sayfasında hem de arama motorlarına verilen FAQPage yapısal
    verisinde kullanılır. Tablo boşsa koddaki varsayılan altı soru gösterilir.
  </p>

  <?php if (!$sss && $yazabilir): ?>
    <form method="post" class="y-form">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="islem" value="sss_tohumla">
      <button type="submit" class="y-ikincil">Varsayılan soruları içe aktar</button>
      <small>Mevcut metinleri tabloya kopyalar; sonrasında buradan düzenlersiniz.</small>
    </form>
  <?php endif; ?>

  <?php foreach ($sss as $s): ?>
    <form method="post" class="y-form y-sss-satir">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="islem" value="sss_guncelle">
      <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
      <label>Soru
        <input type="text" name="soru" value="<?= e($s['soru']) ?>" <?= $yazabilir ? '' : 'disabled' ?>>
      </label>
      <label>Cevap
        <textarea name="cevap" rows="3" <?= $yazabilir ? '' : 'disabled' ?>><?= e($s['cevap']) ?></textarea>
      </label>
      <div class="y-sss-alt">
        <label>Sıra
          <input type="number" name="sira" value="<?= (int)$s['sira'] ?>" min="1" style="width:80px"
                 <?= $yazabilir ? '' : 'disabled' ?>>
        </label>
        <label>Durum
          <select name="durum" <?= $yazabilir ? '' : 'disabled' ?>>
            <option value="aktif" <?= $s['durum'] === 'aktif' ? 'selected' : '' ?>>Aktif</option>
            <option value="pasif" <?= $s['durum'] === 'pasif' ? 'selected' : '' ?>>Pasif</option>
          </select>
        </label>
        <?php if ($yazabilir): ?>
          <button type="submit" class="y-mini">Kaydet</button>
        <?php endif; ?>
      </div>
    </form>
    <?php if ($yazabilir): ?>
    <form method="post" class="y-satir-form y-sss-sil" onsubmit="return confirm('Bu soru silinsin mi?')">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="islem" value="sss_sil">
      <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
      <button type="submit" class="y-mini y-tehlike-btn">Sil</button>
    </form>
    <?php endif; ?>
  <?php endforeach; ?>

  <?php if ($yazabilir): ?>
  <h3>Yeni soru ekle</h3>
  <form method="post" class="y-form">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="islem" value="sss_ekle">
    <label>Soru <input type="text" name="soru" required></label>
    <label>Cevap <textarea name="cevap" rows="3" required></textarea></label>
    <button type="submit">Ekle</button>
  </form>
  <?php endif; ?>
</div>

<?php yonetim_bitir(); ?>
