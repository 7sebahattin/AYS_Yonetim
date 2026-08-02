<?php
// ============================================================
//  yonetim/ayarlar.php — PLATFORM AYARLARI
//
//  Bakım modu, kimliğe bürünmede yazma izni ve panel IP kısıtı.
//  Ayarlar veritabanında tutulur çünkü config.php sunucuya deploy
//  edilmiyor — panelden değiştirilebilir bir ayar orada duramaz.
// ============================================================

require_once __DIR__ . '/ortak.php';
$yonetici = yonetim_kontrol();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    yonetim_yazma_kontrol();
    $islem = $_POST['islem'] ?? '';

    if ($islem === 'bakim') {
        $acik  = !empty($_POST['bakim_modu']) ? '1' : '0';
        $mesaj = trim($_POST['bakim_mesaji'] ?? '');
        platform_ayari_yaz('bakim_modu', $acik, (int)$yonetici['id']);
        if ($mesaj !== '') platform_ayari_yaz('bakim_mesaji', $mesaj, (int)$yonetici['id']);
        yonetim_denetim('bakim_modu_degistirildi', 'platform', null, ['acik' => $acik === '1']);
        flash($acik === '1'
            ? 'Bakım modu AÇILDI — normal kullanıcılar panele giremiyor.'
            : 'Bakım modu kapatıldı.', $acik === '1' ? 'uyari' : 'basari');
    }

    if ($islem === 'burunme') {
        $izin = !empty($_POST['burunme_yazma_izni']) ? '1' : '0';
        platform_ayari_yaz('burunme_yazma_izni', $izin, (int)$yonetici['id']);
        yonetim_denetim('burunme_yazma_izni_degistirildi', 'platform', null, ['izin' => $izin === '1']);
        flash($izin === '1'
            ? 'Kullanıcı adına görüntülemede YAZMA açıldı. İşiniz bitince kapatın.'
            : 'Kullanıcı adına görüntüleme yeniden salt-okunur.', $izin === '1' ? 'uyari' : 'basari');
    }

    if ($islem === 'ip') {
        $liste = trim($_POST['yonetim_ip_listesi'] ?? '');

        // KİLİTLENME KORUMASI: liste doluyken kendi IP'niz listede
        // değilse kaydetmeye izin verilmez — aksi halde ayarı düzeltmek
        // için gereken panele bir daha girilemezdi.
        $kendi = $_SERVER['REMOTE_ADDR'] ?? '';
        $uyuyor = $liste === '';
        if (!$uyuyor) {
            foreach (preg_split('/[\s,;]+/', $liste, -1, PREG_SPLIT_NO_EMPTY) as $kural) {
                if (ip_kurala_uyuyor_mu($kendi, $kural)) { $uyuyor = true; break; }
            }
        }

        if (!$uyuyor) {
            flash('Kendi IP adresiniz (' . $kendi . ') listede yok. Kaydedilseydi panele '
                . 'bir daha giremezdiniz; değişiklik uygulanmadı.', 'hata');
        } else {
            platform_ayari_yaz('yonetim_ip_listesi', $liste, (int)$yonetici['id']);
            yonetim_denetim('ip_listesi_degistirildi', 'platform', null,
                            ['bos' => $liste === '']);
            flash($liste === '' ? 'IP kısıtı kaldırıldı.' : 'IP listesi güncellendi.');
        }
    }

    header('Location: /yonetim/ayarlar.php');
    exit;
}

$yazabilir = platform_yazabilir_mi($yonetici['platform_rolu']);
$bakim     = bakim_modu_aktif_mi();
$burunme   = platform_ayari('burunme_yazma_izni', '0') === '1';
$ip_liste  = (string)platform_ayari('yonetim_ip_listesi', '');

yonetim_basla($yonetici, 'Platform Ayarları');
?>

<div class="y-bolum">
  <h2>Bakım modu</h2>
  <p class="y-soluk">
    Açıkken normal kullanıcılar panele giremez, 503 durum koduyla bir bakım sayfası görür.
    Platform yetkilileri (süper admin ve destek) erişmeye devam eder — aksi halde bakım
    modunu kapatacak kişi de dışarıda kalırdı. Tanıtım sayfası ve giriş ekranı etkilenmez.
  </p>
  <form method="post" class="y-form">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="islem" value="bakim">
    <label class="y-onay">
      <input type="checkbox" name="bakim_modu" value="1" <?= $bakim ? 'checked' : '' ?>
             <?= $yazabilir ? '' : 'disabled' ?>>
      Bakım modunu aç
    </label>
    <label>Kullanıcılara gösterilecek mesaj
      <textarea name="bakim_mesaji" rows="2" <?= $yazabilir ? '' : 'disabled' ?>><?= e(bakim_mesaji()) ?></textarea>
    </label>
    <?php if ($yazabilir): ?><button type="submit">Kaydet</button><?php endif; ?>
  </form>
</div>

<div class="y-bolum">
  <h2>Kullanıcı adına görüntüleme</h2>
  <p class="y-soluk">
    Süper admin bir yöneticinin ekranını görebilir. <strong>Varsayılan salt-okunurdur</strong>;
    bu kutu açılmadıkça bürünme sırasında hiçbir POST isteği kabul edilmez. Yazma yetkisi
    yalnızca kullanıcı adına bir düzeltme yapmanız gerektiğinde, iş bitince tekrar
    kapatılmak üzere açılmalıdır. Her bürünme oturumu denetim kaydına yazılır.
  </p>
  <?php if ($burunme): ?>
    <div class="y-uyari y-uyari-uyari">
      Şu anda YAZMA açık. Bürünme sırasında yapılan değişiklikler gerçek veriyi değiştirir.
    </div>
  <?php endif; ?>
  <form method="post" class="y-form">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="islem" value="burunme">
    <label class="y-onay">
      <input type="checkbox" name="burunme_yazma_izni" value="1" <?= $burunme ? 'checked' : '' ?>
             <?= $yazabilir ? '' : 'disabled' ?>>
      Bürünme sırasında yazmaya izin ver
    </label>
    <?php if ($yazabilir): ?><button type="submit">Kaydet</button><?php endif; ?>
  </form>
</div>

<div class="y-bolum">
  <h2>Panel IP kısıtı</h2>
  <p class="y-soluk">
    Boş bırakılırsa kısıt yoktur. Her satıra bir IP adresi ya da CIDR bloğu yazın
    (örn. <code>88.230.10.15</code> veya <code>88.230.10.0/24</code>). Bu, şifre ve 2FA'nın
    <em>üstüne</em> bir katmandır; tek başına güvenlik önlemi sayılmaz.
    Şu anki IP adresiniz: <code><?= e($_SERVER['REMOTE_ADDR'] ?? '—') ?></code>
  </p>
  <?php if ($ip_liste === ''): ?>
    <p class="y-soluk y-kucuk-yazi">
      Dinamik IP kullanıyorsanız (çoğu ev bağlantısı) bu kısıtı açmayın — IP'niz
      değiştiğinde panele giremezsiniz.
    </p>
  <?php endif; ?>
  <form method="post" class="y-form">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="islem" value="ip">
    <label>İzinli adresler
      <textarea name="yonetim_ip_listesi" rows="4" <?= $yazabilir ? '' : 'disabled' ?>
                placeholder="Boş = kısıt yok"><?= e($ip_liste) ?></textarea>
    </label>
    <?php if ($yazabilir): ?><button type="submit">Kaydet</button><?php endif; ?>
  </form>
</div>

<div class="y-bolum y-bilgi-kutu">
  <h2>Yapılandırma durumu</h2>
  <table class="y-tablo y-tablo-kunye">
    <tr><th>SMTP</th><td>
      <?= eposta_yapilandirildi_mi()
          ? '<span class="y-rozet y-rozet-basari">yapılandırıldı</span>'
          : '<span class="y-rozet y-rozet-uyari">yapılandırılmadı — şifre sıfırlama e-postaları gönderilemiyor</span>' ?>
    </td></tr>
    <tr><th>Site adresi</th><td><code><?= e(SITE_ADRESI) ?></code></td></tr>
    <tr><th>Dosya kökü</th><td><code><?= e(DOSYA_KOK) ?></code>
      <?= is_dir(DOSYA_KOK) ? '' : ' <span class="y-rozet y-rozet-uyari">klasör yok</span>' ?></td></tr>
    <tr><th>Göç anahtarı</th><td>
      <?= GOC_ANAHTARI !== '' ? 'tanımlı' : 'tanımsız (web üzerinden göç kapalı)' ?></td></tr>
    <tr><th>Süper admin</th><td><?= superadmin_sayisi() ?> hesap</td></tr>
    <tr><th>PHP</th><td><?= e(PHP_VERSION) ?></td></tr>
    <tr><th>Veritabanı</th><td><?= e(db()->getAttribute(PDO::ATTR_SERVER_VERSION)) ?></td></tr>
  </table>
  <p class="y-soluk y-kucuk-yazi">
    SMTP bilgileri <code>config.php</code>'de tutulur ve deploy edilmez; buradan
    değiştirilemez. Sunucudaki dosyayı düzenlemeniz gerekir.
  </p>
</div>

<?php yonetim_bitir(); ?>
