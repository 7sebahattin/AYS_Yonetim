<?php
// ============================================================
//  yonetim/kullanicilar.php — KULLANICI YÖNETİMİ
//
//  Platform rolü atama, şifre sıfırlama bağlantısı gönderme ve
//  2FA sıfırlama (telefonunu kaybeden bir yöneticinin kurtarılması).
// ============================================================

require_once __DIR__ . '/ortak.php';
require_once __DIR__ . '/../includes/kimlik.php';

$yonetici = yonetim_kontrol();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    yonetim_yazma_kontrol();
    $hedef_id = (int)($_POST['kullanici_id'] ?? 0);
    $islem    = $_POST['islem'] ?? '';

    $st = db()->prepare("SELECT id, kullanici_adi, eposta, platform_rolu FROM kullanicilar WHERE id = ?");
    $st->execute([$hedef_id]);
    $hedef = $st->fetch();

    if (!$hedef) {
        flash('Kullanıcı bulunamadı.', 'hata');

    } elseif ($islem === 'rol') {
        $yeni = $_POST['rol'] ?? '';
        if (!in_array($yeni, PLATFORM_ROLLERI, true)) {
            flash('Geçersiz rol.', 'hata');

        // Son süper adminin yetkisi alınamaz: panele girecek kimse
        // kalmaz ve rol atamak için de panel gerekir — sistem kendini
        // kilitler. Bu kontrol olmadan tek yanlış tıkla geri dönüşü
        // veritabanına elle müdahaleyi gerektiren bir durum oluşur.
        } elseif ($hedef['platform_rolu'] === 'superadmin' && $yeni !== 'superadmin'
                  && superadmin_sayisi() <= 1) {
            flash('Son süper adminin yetkisi kaldırılamaz. Önce başka bir süper admin atayın.', 'hata');

        } else {
            db()->prepare("UPDATE kullanicilar SET platform_rolu = ? WHERE id = ?")
                ->execute([$yeni, $hedef_id]);
            yonetim_denetim('platform_rolu_degistirildi', 'kullanici', $hedef_id,
                            ['eski' => $hedef['platform_rolu'], 'yeni' => $yeni]);
            flash($hedef['kullanici_adi'] . ' → platform rolü: ' . $yeni);
        }

    } elseif ($islem === 'sifirlama_gonder') {
        if (empty($hedef['eposta'])) {
            flash('Kullanıcının kayıtlı e-posta adresi yok.', 'hata');
        } elseif (!eposta_yapilandirildi_mi()) {
            flash('SMTP yapılandırılmamış; e-posta gönderilemiyor.', 'hata');
        } else {
            $ok = sifre_sifirlama_gonder($hedef_id, $hedef['eposta']);
            yonetim_denetim('sifirlama_baglantisi_gonderildi', 'kullanici', $hedef_id,
                            ['sonuc' => $ok ? 'gonderildi' : 'hata']);
            flash($ok ? 'Sıfırlama bağlantısı gönderildi.' : 'E-posta gönderilemedi.',
                  $ok ? 'basari' : 'hata');
        }

    } elseif ($islem === '2fa_sifirla') {
        // Telefonunu kaybeden bir platform yetkilisinin tek kurtuluşu.
        // Yetkiyi VERMEZ, yalnızca 2FA kaydını siler — kullanıcı panele
        // girebilmek için kurulumu baştan yapmak zorundadır.
        db()->prepare("UPDATE kullanicilar
                       SET totp_gizli = NULL, totp_aktif = 0, totp_son_adim = NULL WHERE id = ?")
            ->execute([$hedef_id]);
        db()->prepare("DELETE FROM totp_yedek_kodlari WHERE kullanici_id = ?")->execute([$hedef_id]);
        yonetim_denetim('2fa_sifirlandi', 'kullanici', $hedef_id);
        flash($hedef['kullanici_adi'] . ' için 2FA sıfırlandı; yeniden kurmalı.', 'uyari');
    }

    header('Location: /yonetim/kullanicilar.php?' . http_build_query(array_intersect_key($_GET, ['q' => 1, 'rol' => 1])));
    exit;
}

// ─── Filtre ─────────────────────────────────────────────────
$arama = trim($_GET['q'] ?? '');
$rol   = in_array($_GET['rol'] ?? '', PLATFORM_ROLLERI, true) ? $_GET['rol'] : '';

$kosul = [];
$parametre = [];
if ($arama !== '') {
    $kosul[] = '(k.kullanici_adi LIKE ? OR k.eposta LIKE ?)';
    $parametre[] = '%' . $arama . '%';
    $parametre[] = '%' . $arama . '%';
}
if ($rol !== '') {
    $kosul[] = 'k.platform_rolu = ?';
    $parametre[] = $rol;
}
$where = $kosul ? 'WHERE ' . implode(' AND ', $kosul) : '';

[$limit, $ofset, $sayfa] = yonetim_sayfalama(25);

$sayim = db()->prepare("SELECT COUNT(*) FROM kullanicilar k $where");
$sayim->execute($parametre);
$toplam = (int)$sayim->fetchColumn();

$st = db()->prepare("
    SELECT k.id, k.kullanici_adi, k.eposta, k.eposta_dogrulandi, k.platform_rolu,
           k.totp_aktif, k.son_giris, k.olusturma_tarihi,
           (SELECT COUNT(*) FROM kullanici_site_yetkileri y
             WHERE y.kullanici_id = k.id AND y.durum='aktif') AS site_sayisi
    FROM kullanicilar k
    $where
    ORDER BY k.id DESC
    LIMIT $limit OFFSET $ofset
");
$st->execute($parametre);
$kullanicilar = $st->fetchAll();

$yazabilir = platform_yazabilir_mi($yonetici['platform_rolu']);
yonetim_basla($yonetici, 'Kullanıcılar');
?>

<form method="get" class="y-filtre">
  <input type="search" name="q" value="<?= e($arama) ?>" placeholder="Kullanıcı adı veya e-posta ara…">
  <select name="rol">
    <option value="">Tüm platform rolleri</option>
    <?php foreach (PLATFORM_ROLLERI as $r): ?>
      <option value="<?= e($r) ?>" <?= $rol === $r ? 'selected' : '' ?>><?= e($r) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit">Filtrele</button>
  <?php if ($arama !== '' || $rol !== ''): ?>
    <a href="/yonetim/kullanicilar.php" class="y-ikincil-baglanti">Temizle</a>
  <?php endif; ?>
</form>

<div class="y-bolum">
  <table class="y-tablo">
    <thead>
      <tr><th>Kullanıcı</th><th>E-posta</th><th>Site</th><th>Son giriş</th>
          <th>Platform rolü</th><th>2FA</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($kullanicilar as $k): ?>
      <tr>
        <td>
          <strong><?= e($k['kullanici_adi']) ?></strong>
          <div class="y-soluk y-kucuk-yazi">#<?= (int)$k['id'] ?> ·
            <?= e(date('d.m.Y', strtotime($k['olusturma_tarihi']))) ?></div>
        </td>
        <td class="y-soluk">
          <?= e($k['eposta'] ?? '') ?: '—' ?>
          <?php if ($k['eposta'] && !$k['eposta_dogrulandi']): ?>
            <span class="y-rozet y-rozet-uyari">doğrulanmadı</span>
          <?php endif; ?>
        </td>
        <td><?= (int)$k['site_sayisi'] ?></td>
        <td class="y-soluk"><?= $k['son_giris'] ? e(date('d.m.Y H:i', strtotime($k['son_giris']))) : '—' ?></td>
        <td>
          <?php if ($yazabilir): ?>
          <form method="post" class="y-satir-form">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="islem" value="rol">
            <input type="hidden" name="kullanici_id" value="<?= (int)$k['id'] ?>">
            <select name="rol" onchange="this.form.submit()">
              <?php foreach (PLATFORM_ROLLERI as $r): ?>
                <option value="<?= e($r) ?>" <?= $k['platform_rolu'] === $r ? 'selected' : '' ?>><?= e($r) ?></option>
              <?php endforeach; ?>
            </select>
          </form>
          <?php else: ?>
            <?= e($k['platform_rolu']) ?>
          <?php endif; ?>
        </td>
        <td>
          <?php if ((int)$k['totp_aktif'] === 1): ?>
            <span class="y-rozet y-rozet-basari">etkin</span>
          <?php elseif ($k['platform_rolu'] !== 'kullanici'): ?>
            <span class="y-rozet y-rozet-uyari">kurulmadı</span>
          <?php else: ?>
            <span class="y-soluk">—</span>
          <?php endif; ?>
        </td>
        <td class="y-sag">
          <?php if ($yazabilir && !empty($k['eposta'])): ?>
          <form method="post" class="y-satir-form"
                onsubmit="return confirm('Şifre sıfırlama bağlantısı gönderilsin mi?')">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="islem" value="sifirlama_gonder">
            <input type="hidden" name="kullanici_id" value="<?= (int)$k['id'] ?>">
            <button type="submit" class="y-mini">Sıfırlama</button>
          </form>
          <?php endif; ?>
          <?php if ($yazabilir && (int)$k['totp_aktif'] === 1): ?>
          <form method="post" class="y-satir-form"
                onsubmit="return confirm('2FA sıfırlansın mı? Kullanıcı panele girmek için yeniden kurmak zorunda kalır.')">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="islem" value="2fa_sifirla">
            <input type="hidden" name="kullanici_id" value="<?= (int)$k['id'] ?>">
            <button type="submit" class="y-mini y-tehlike-btn">2FA sıfırla</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$kullanicilar): ?>
      <tr><td colspan="7" class="y-bos">Ölçütlere uyan kullanıcı yok.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
  <?php yonetim_sayfalama_ciz($sayfa, $toplam, $limit); ?>
</div>

<div class="y-bolum y-bilgi-kutu">
  <h2>Platform rolleri</h2>
  <ul>
    <li><strong>kullanici</strong> — varsayılan. Yönetim paneline erişemez.</li>
    <li><strong>destek</strong> — panele girer, her şeyi görür, <em>hiçbir şey değiştiremez</em>.</li>
    <li><strong>superadmin</strong> — tam yetki: ayarlar, içerik, göç, kimliğe bürünme.</li>
  </ul>
  <p>Platform rolü olan her hesapta iki faktörlü doğrulama zorunludur; kurulmadan panele
     giriş kabul edilmez.</p>
</div>

<?php yonetim_bitir(); ?>
