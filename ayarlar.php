<?php
// ============================================================
//  ayarlar.php — HESAP & APARTMAN AYARLARI
// ============================================================
require_once 'config.php';
require_once 'includes/functions.php';
require_once 'includes/kimlik.php';
$sayfa_basligi = 'Ayarlar';

$kullanici = giris_kontrol();
$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_kontrol();
    $islem = $_POST['islem'] ?? '';

    if ($islem === 'apartman_guncelle') {
        $apartman_adi = buyuk($_POST['apartman_adi'] ?? '');
        $adres        = buyuk($_POST['adres'] ?? '');
        $telefon      = trim($_POST['telefon'] ?? '');
        $tema         = $_POST['tema'] === 'acik' ? 'acik' : 'koyu'; // Tema verisini aldık

        if (!$apartman_adi) { flash('Apartman adı zorunludur.', 'hata'); }
        else {
            $db->prepare("UPDATE kullanicilar SET apartman_adi=?, adres=?, telefon=?, tema=? WHERE id=?")
               ->execute([$apartman_adi, $adres, $telefon, $tema, $kullanici['id']]);
            $_SESSION['apartman_adi'] = $apartman_adi;
            $_SESSION['tema'] = $tema; // Oturumu da güncelledik
            flash('Apartman ve tema bilgileri güncellendi.');
        }
    }

    // ── E-posta adresi kaydet / değiştir ─────────────────────
    if ($islem === 'eposta_kaydet' && eposta_semasi_hazir_mi()) {
        $eposta = trim($_POST['eposta'] ?? '');
        if (!filter_var($eposta, FILTER_VALIDATE_EMAIL)) {
            flash('Geçerli bir e-posta adresi girin.', 'hata');
        } else {
            $es = $db->prepare("SELECT id FROM kullanicilar WHERE eposta = ? AND id <> ?");
            $es->execute([$eposta, $kullanici['id']]);
            if ($es->fetch()) {
                flash('Bu e-posta adresi başka bir hesapta kayıtlı.', 'hata');
            } else {
                // Adres değiştiğinde doğrulama sıfırlanır: yeni adresin
                // gerçekten kullanıcıya ait olduğu kanıtlanmadan şifre
                // sıfırlama bağlantısı oraya gönderilmemeli.
                $db->prepare("UPDATE kullanicilar SET eposta = ?, eposta_dogrulandi = 0 WHERE id = ?")
                   ->execute([$eposta, $kullanici['id']]);
                denetim_yaz('eposta_guncellendi', 'kullanici', $kullanici['id']);

                if (eposta_yapilandirildi_mi()) {
                    eposta_dogrulama_gonder($kullanici['id'], $eposta);
                    flash('E-posta kaydedildi. Doğrulama bağlantısı adresinize gönderildi.');
                } else {
                    flash('E-posta kaydedildi. (E-posta gönderimi yapılandırılmadığı için doğrulama iletisi gönderilemedi.)', 'uyari');
                }
            }
        }
    }

    // ── Doğrulama e-postasını tekrar gönder ──────────────────
    if ($islem === 'dogrulama_tekrar' && eposta_semasi_hazir_mi()) {
        $st = $db->prepare("SELECT eposta, eposta_dogrulandi FROM kullanicilar WHERE id = ?");
        $st->execute([$kullanici['id']]);
        $bilgi = $st->fetch();

        if (empty($bilgi['eposta'])) {
            flash('Önce bir e-posta adresi kaydedin.', 'hata');
        } elseif ((int)$bilgi['eposta_dogrulandi'] === 1) {
            flash('E-posta adresiniz zaten doğrulanmış.');
        } elseif (!eposta_yapilandirildi_mi()) {
            flash('E-posta gönderimi yapılandırılmamış.', 'hata');
        } elseif (!hiz_limiti_gec('eposta_dogrulama:' . $kullanici['id'], 3, 900)) {
            flash('Çok fazla istek gönderdiniz. Lütfen 15 dakika sonra tekrar deneyin.', 'hata');
        } else {
            eposta_dogrulama_gonder($kullanici['id'], $bilgi['eposta']);
            flash('Doğrulama bağlantısı tekrar gönderildi.');
        }
    }

    if ($islem === 'sifre_degistir') {
        $mevcut = $_POST['mevcut_sifre'] ?? '';
        $yeni   = $_POST['yeni_sifre'] ?? '';
        $yeni2  = $_POST['yeni_sifre2'] ?? '';
        $stmt = $db->prepare("SELECT sifre_hash FROM kullanicilar WHERE id=?");
        $stmt->execute([$kullanici['id']]);
        $row = $stmt->fetch();
        if (!password_verify($mevcut, $row['sifre_hash'])) { flash('Mevcut şifre yanlış.', 'hata'); }
        elseif (strlen($yeni) < 6) { flash('Yeni şifre en az 6 karakter olmalı.', 'hata'); }
        elseif ($yeni !== $yeni2) { flash('Yeni şifreler eşleşmiyor.', 'hata'); }
        else {
            $db->prepare("UPDATE kullanicilar SET sifre_hash=? WHERE id=?")->execute([password_hash($yeni, PASSWORD_DEFAULT), $kullanici['id']]);
            flash('Şifre başarıyla değiştirildi.');
        }
    }

    header('Location: /ayarlar.php'); exit;
}

$stmt = $db->prepare("SELECT * FROM kullanicilar WHERE id=?");
$stmt->execute([$kullanici['id']]);
$info = $stmt->fetch();

include 'includes/header.php';
?>

<div style="max-width:640px">
  <div class="card" style="margin-bottom:24px">
    <div class="card-header"><span>🏢 Apartman ve Görünüm Ayarları</span></div>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="islem" value="apartman_guncelle">
      <div class="form-grid">
        <div class="form-group full-width">
          <label>Apartman Adı <span class="req">*</span></label>
          <input type="text" name="apartman_adi" class="input buyuk" value="<?= e($info['apartman_adi']) ?>" required>
        </div>
        <div class="form-group full-width">
          <label>Adres</label>
          <input type="text" name="adres" class="input buyuk" value="<?= e($info['adres']) ?>" placeholder="Mahalle, sokak, il...">
        </div>
        <div class="form-group">
          <label>Telefon</label>
          <input type="text" name="telefon" class="input" value="<?= e($info['telefon']) ?>" placeholder="0212 000 00 00">
        </div>
        <div class="form-group">
          <label>Kullanıcı Adı (değiştirilemez)</label>
          <input type="text" class="input" value="<?= e($info['kullanici_adi']) ?>" disabled style="opacity:0.5">
        </div>
        
        <div class="form-group full-width" style="margin-top:10px;">
          <label>Tema Görünümü</label>
          <div style="display:flex; gap:12px; flex-wrap:wrap;">
            <label style="display:flex; align-items:center; gap:8px; cursor:pointer; padding:12px 18px; border:1px solid var(--border); border-radius:10px; background:var(--card-bg);">
                <input type="radio" name="tema" value="koyu" <?= ($info['tema'] ?? 'koyu') === 'koyu' ? 'checked' : '' ?>> 🌙 Koyu Tema
            </label>
            <label style="display:flex; align-items:center; gap:8px; cursor:pointer; padding:12px 18px; border:1px solid var(--border); border-radius:10px; background:var(--card-bg);">
                <input type="radio" name="tema" value="acik" <?= ($info['tema'] ?? 'koyu') === 'acik' ? 'checked' : '' ?>> ☀️ Açık Tema
            </label>
          </div>
        </div>
        
      </div>
      <div style="margin-top:16px">
        <button type="submit" class="btn btn-primary">💾 Kaydet</button>
      </div>
    </form>
  </div>

  <?php if (eposta_semasi_hazir_mi()): ?>
  <?php
    $eposta_mevcut  = (string)($info['eposta'] ?? '');
    $eposta_onayli  = (int)($info['eposta_dogrulandi'] ?? 0) === 1;
  ?>
  <div class="card" style="margin-bottom:24px">
    <div class="card-header">
      <span>✉️ E-posta Adresi</span>
      <?php if ($eposta_mevcut !== ''): ?>
        <span class="badge badge-<?= $eposta_onayli ? 'success' : 'warning' ?>">
          <?= $eposta_onayli ? '✓ Doğrulandı' : '⏳ Doğrulanmadı' ?>
        </span>
      <?php endif; ?>
    </div>

    <?php if (!eposta_yapilandirildi_mi()): ?>
      <div class="alert alert-error" style="margin:0 0 14px">
        ⚠ Sunucuda e-posta gönderimi yapılandırılmamış. Adres kaydedebilirsiniz ancak
        doğrulama ve şifre sıfırlama iletileri gönderilemez.
      </div>
    <?php elseif ($eposta_mevcut === ''): ?>
      <div class="alert alert-error" style="margin:0 0 14px">
        ⚠ Hesabınıza kayıtlı e-posta adresi yok. Şifrenizi unutursanız hesabınızı
        kurtarmanın bir yolu olmaz — lütfen bir adres ekleyin.
      </div>
    <?php elseif (!$eposta_onayli): ?>
      <div class="alert alert-error" style="margin:0 0 14px">
        ⚠ Adresiniz henüz doğrulanmadı. Şifre sıfırlama yalnızca <strong>doğrulanmış</strong>
        adrese gönderilir. Gelen kutunuzu (ve spam klasörünü) kontrol edin.
      </div>
    <?php endif; ?>

    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="islem" value="eposta_kaydet">
      <div class="form-grid">
        <div class="form-group full-width">
          <label>E-posta Adresi</label>
          <input type="email" name="eposta" class="input" value="<?= e($eposta_mevcut) ?>"
                 placeholder="ornek@mail.com" required>
        </div>
      </div>
      <div style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap">
        <button type="submit" class="btn btn-primary">💾 Kaydet</button>
      </div>
    </form>

    <?php if ($eposta_mevcut !== '' && !$eposta_onayli && eposta_yapilandirildi_mi()): ?>
    <form method="post" style="margin-top:10px">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="islem" value="dogrulama_tekrar">
      <button type="submit" class="btn btn-ghost btn-sm">↻ Doğrulama bağlantısını tekrar gönder</button>
    </form>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="card">
    <div class="card-header"><span>🔑 Şifre Değiştir</span></div>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="islem" value="sifre_degistir">
      <div class="form-grid">
        <div class="form-group full-width">
          <label>Mevcut Şifre</label>
          <input type="password" name="mevcut_sifre" class="input" required placeholder="••••••••">
        </div>
        <div class="form-group">
          <label>Yeni Şifre</label>
          <input type="password" name="yeni_sifre" class="input" required minlength="6" placeholder="En az 6 karakter">
        </div>
        <div class="form-group">
          <label>Yeni Şifre Tekrar</label>
          <input type="password" name="yeni_sifre2" class="input" required placeholder="••••••••">
        </div>
      </div>
      <div style="margin-top:16px">
        <button type="submit" class="btn btn-primary">🔑 Şifreyi Değiştir</button>
      </div>
    </form>
  </div>
</div>

<?php include 'includes/footer.php'; ?>