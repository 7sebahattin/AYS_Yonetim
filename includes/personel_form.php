<?php
// ============================================================
//  includes/personel_form.php — PERSONEL FORM ALANLARI
//
//  Ekleme ve düzenleme modalleri aynı alanları kullanır; iki kopya
//  er ya da geç ayrışır ve düzenleme, unutulan alanı sessizce siler.
//
//  KVKK: kimlik/SGK numarası alanı bilinçli olarak YOK. Bu veriler
//  özel nitelikli kişisel veridir; aidat takibi için gerekmiyor.
//
//  Beklenen değişken: $mevcut (düzenlemede kayıt, eklemede tanımsız).
// ============================================================
$mevcut = $mevcut ?? [];
?>
<div class="form-grid">
  <div class="form-group full-width">
    <label>Ad Soyad <span class="req">*</span></label>
    <input type="text" name="ad_soyad" class="input buyuk" required
           value="<?= e($mevcut['ad_soyad'] ?? '') ?>">
  </div>
  <div class="form-group">
    <label>Görev</label>
    <select name="gorev" class="input">
      <?php foreach (PERSONEL_GOREVLERI as $k => $ad): ?>
        <option value="<?= e($k) ?>" <?= ($mevcut['gorev'] ?? '')===$k?'selected':'' ?>><?= e($ad) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="form-group">
    <label>Telefon</label>
    <input type="tel" name="telefon" class="input" value="<?= e($mevcut['telefon'] ?? '') ?>"
           placeholder="05XX XXX XX XX">
  </div>
  <div class="form-group">
    <label>İşe başlama</label>
    <input type="date" name="baslama_tarihi" class="input" value="<?= e($mevcut['baslama_tarihi'] ?? '') ?>">
  </div>
  <div class="form-group">
    <label>Ayrılma tarihi</label>
    <input type="date" name="ayrilma_tarihi" class="input" value="<?= e($mevcut['ayrilma_tarihi'] ?? '') ?>">
    <small class="muted">Doldurulursa durum otomatik "Ayrıldı" olur.</small>
  </div>
  <div class="form-group">
    <label>Aylık ücret (₺)</label>
    <input type="number" name="aylik_ucret" step="0.01" min="0" class="input"
           value="<?= e($mevcut['aylik_ucret'] ?? '0') ?>">
  </div>
  <div class="form-group">
    <label>Durum</label>
    <select name="durum" class="input">
      <option value="aktif"   <?= ($mevcut['durum'] ?? 'aktif')==='aktif'?'selected':'' ?>>Aktif</option>
      <option value="ayrildi" <?= ($mevcut['durum'] ?? '')==='ayrildi'?'selected':'' ?>>Ayrıldı</option>
    </select>
  </div>
  <div class="form-group full-width">
    <label>Notlar</label>
    <textarea name="notlar" class="input" rows="2"><?= e($mevcut['notlar'] ?? '') ?></textarea>
    <small class="muted">
      Kimlik numarası, SGK sicili gibi özel nitelikli kişisel verileri buraya yazmayın.
    </small>
  </div>
</div>
