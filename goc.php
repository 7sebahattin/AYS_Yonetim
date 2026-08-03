<?php
// ============================================================
//  goc.php — ŞEMA GÖÇÜ (WEB ARAYÜZÜ)
//
//  cPanel paylaşımlı hostingde SSH erişimi her zaman bulunmadığı için
//  göçler tarayıcıdan da çalıştırılabilir.
//
//  Güvenlik: config.php içindeki GOC_ANAHTARI gerekir. Anahtar boşsa
//  bu sayfa hiçbir şey yapmaz (yalnızca CLI kalır). Anahtar URL'de
//  değil POST gövdesinde taşınır — aksi halde sunucu erişim
//  günlüklerine düz metin olarak yazılırdı.
//
//  Faz 3 notu: Süper admin rolü geldiğinde bu sayfa o role
//  kilitlenmeli ve anahtar ikincil doğrulama olarak kalmalı.
// ============================================================
require_once 'config.php';
require_once 'includes/functions.php';
require_once 'includes/goc.php';
oturum_baslat();

$mesajlar   = [];
$yetkili    = false;
$hata       = '';
$anahtar_ok = GOC_ANAHTARI !== '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_kontrol();
    $girilen = (string)($_POST['anahtar'] ?? '');

    if (!$anahtar_ok) {
        $hata = 'GOC_ANAHTARI config.php içinde tanımlı değil. Web üzerinden göç çalıştırılamaz.';
    } elseif (!hiz_limiti_gec('goc_denemesi:' . istemci_ip(), 5, 900)) {
        $hata = 'Çok fazla deneme yapıldı. 15 dakika sonra tekrar deneyin.';
    } elseif (!hash_equals(GOC_ANAHTARI, $girilen)) {
        $hata = 'Anahtar hatalı.';
        denetim_yaz('goc_yetkisiz_deneme');
    } else {
        $yetkili = true;
        if (($_POST['islem'] ?? '') === 'uygula') {
            $mesajlar = tum_gocleri_uygula();
            denetim_yaz('goc_uygulandi', null, null, ['sonuc_sayisi' => count($mesajlar)]);
        }
    }
}

// Durum bilgisi yalnızca doğru anahtarla gösterilir — şema sürümü de
// bir bilgi sızıntısıdır.
$bekleyen = $yetkili ? bekleyen_gocler()   : [];
$uygulanan = $yetkili ? uygulanmis_gocler() : [];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Şema Göçü — AYS</title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="stylesheet" href="/assets/style.css?v=2">
</head>
<body class="auth-body">
<div class="auth-card" style="max-width:640px">
  <div class="auth-header">
    <div class="auth-logo">🗃️</div>
    <h1 class="auth-title">Şema Göçü</h1>
    <p class="auth-subtitle">Veritabanı sürüm yönetimi</p>
  </div>

  <?php if ($hata): ?><div class="alert alert-error">✕ <?= e($hata) ?></div><?php endif; ?>

  <?php if (!$anahtar_ok): ?>
    <div class="alert alert-error">
      ⚠ <code>GOC_ANAHTARI</code> tanımlı değil. Sunucudaki <code>config.php</code> dosyasına
      uzun ve rastgele bir anahtar ekleyin, ya da göçleri komut satırından çalıştırın:
      <code>php araclar/goc_cli.php uygula</code>
    </div>
  <?php endif; ?>

  <?php if ($yetkili): ?>
    <?php if ($mesajlar): ?>
      <div class="card" style="margin-bottom:18px">
        <div class="card-header"><span>Sonuç</span></div>
        <?php foreach ($mesajlar as $m): ?>
          <div class="alert <?= $m['basarili'] ? 'alert-success' : 'alert-error' ?>" style="margin-bottom:8px">
            <?= $m['basarili'] ? '✓' : '✕' ?> <?= e($m['mesaj']) ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="card" style="margin-bottom:18px">
      <div class="card-header"><span>Uygulanmış (<?= count($uygulanan) ?>)</span></div>
      <?php if (!$uygulanan): ?><div class="empty-state">Henüz göç uygulanmamış.</div><?php endif; ?>
      <?php foreach ($uygulanan as $g): ?>
        <div style="padding:7px 0;font-size:13px;color:var(--text2)">✓ <?= e($g) ?></div>
      <?php endforeach; ?>
    </div>

    <div class="card" style="margin-bottom:18px">
      <div class="card-header"><span>Bekleyen (<?= count($bekleyen) ?>)</span></div>
      <?php if (!$bekleyen): ?>
        <div class="empty-state">Bekleyen göç yok — şema güncel.</div>
      <?php endif; ?>
      <?php foreach ($bekleyen as $g): ?>
        <div style="padding:7px 0;font-size:13px;color:var(--gold)">• <?= e($g) ?></div>
      <?php endforeach; ?>
    </div>

    <?php if ($bekleyen): ?>
      <div class="alert alert-error">
        ⚠ <strong>Önce veritabanı yedeği alın.</strong> MySQL'de DDL işlemleri geri alınamaz.
      </div>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="islem" value="uygula">
        <input type="hidden" name="anahtar" value="<?= e($_POST['anahtar'] ?? '') ?>">
        <button type="submit" class="btn btn-primary btn-block"
                data-confirm="Yedek aldınız mı? Göçler uygulanacak.">
          Bekleyen <?= count($bekleyen) ?> göçü uygula
        </button>
      </form>
    <?php endif; ?>

  <?php elseif ($anahtar_ok): ?>
    <form method="post" class="auth-form">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="islem" value="durum">
      <div class="form-group">
        <label>Göç Anahtarı</label>
        <input type="password" name="anahtar" class="input" required autofocus
               placeholder="config.php içindeki GOC_ANAHTARI">
      </div>
      <button type="submit" class="btn btn-primary btn-block">Durumu Göster</button>
    </form>
  <?php endif; ?>

  <p style="text-align:center;margin-top:20px;font-size:12.5px;color:var(--muted)">
    <a href="/" style="color:var(--muted)">← Ana sayfa</a>
  </p>
</div>
<script>
document.querySelectorAll('[data-confirm]').forEach(function(b){
  b.addEventListener('click',function(e){ if(!confirm(this.dataset.confirm)) e.preventDefault(); });
});
</script>
</body>
</html>
