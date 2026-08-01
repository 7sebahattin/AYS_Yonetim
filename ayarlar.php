<?php
// ============================================================
//  ayarlar.php — HESAP & APARTMAN AYARLARI
// ============================================================
require_once 'config.php';
require_once 'includes/functions.php';
$sayfa_basligi = 'Ayarlar';

$kullanici = giris_kontrol();
$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_kontrol();
    $islem = $_POST['islem'] ?? '';

    if ($islem === 'apartman_guncelle') {
        $apartman_adi = trim($_POST['apartman_adi'] ?? '');
        $adres        = trim($_POST['adres'] ?? '');
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
          <input type="text" name="apartman_adi" class="input" value="<?= e($info['apartman_adi']) ?>" required>
        </div>
        <div class="form-group full-width">
          <label>Adres</label>
          <input type="text" name="adres" class="input" value="<?= e($info['adres']) ?>" placeholder="Mahalle, sokak, il...">
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