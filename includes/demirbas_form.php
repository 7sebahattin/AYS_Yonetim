<?php
// ============================================================
//  includes/demirbas_form.php — DEMİRBAŞ FORM ALANLARI
//
//  Ekleme ve düzenleme modalleri aynı alanları kullanır. Tek dosyada
//  tutuluyor çünkü iki kopya er ya da geç ayrışır: yeni bir alan
//  eklendiğinde birinde unutulur ve düzenleme o alanı sessizce siler.
//
//  Beklenen değişkenler: $mevcut (düzenlemede kayıt, eklemede tanımsız),
//  $bloklar.
// ============================================================
$mevcut = $mevcut ?? [];
?>
<div class="form-grid">
  <div class="form-group full-width">
    <label>Demirbaş adı <span class="req">*</span></label>
    <input type="text" name="ad" class="input buyuk" required
           value="<?= e($mevcut['ad'] ?? '') ?>" placeholder="örn. A BLOK ASANSÖRÜ">
  </div>
  <div class="form-group">
    <label>Kategori</label>
    <select name="kategori" class="input">
      <?php foreach (DEMIRBAS_KATEGORILERI as $k => $ad): ?>
        <option value="<?= e($k) ?>" <?= ($mevcut['kategori'] ?? '')===$k?'selected':'' ?>><?= e($ad) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="form-group">
    <label>Durum</label>
    <select name="durum" class="input">
      <?php foreach (DEMIRBAS_DURUMLARI as $k => $ad): ?>
        <option value="<?= e($k) ?>" <?= ($mevcut['durum'] ?? 'aktif')===$k?'selected':'' ?>><?= e($ad) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php if (count($bloklar) > 1): ?>
  <div class="form-group">
    <label>Blok</label>
    <select name="blok_id" class="input">
      <option value="">— Genel —</option>
      <?php foreach ($bloklar as $b): ?>
        <option value="<?= (int)$b['id'] ?>" <?= (int)($mevcut['blok_id'] ?? 0)===(int)$b['id']?'selected':'' ?>>
          <?= e_buyuk($b['ad']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php endif; ?>
  <div class="form-group">
    <label>Konum</label>
    <input type="text" name="konum" class="input buyuk"
           value="<?= e($mevcut['konum'] ?? '') ?>" placeholder="örn. BODRUM KAT">
  </div>
  <div class="form-group">
    <label>Marka / Model</label>
    <input type="text" name="marka_model" class="input buyuk" value="<?= e($mevcut['marka_model'] ?? '') ?>">
  </div>
  <div class="form-group">
    <label>Seri No</label>
    <input type="text" name="seri_no" class="input buyuk" value="<?= e($mevcut['seri_no'] ?? '') ?>">
  </div>
  <div class="form-group">
    <label>Alım tarihi</label>
    <input type="date" name="alim_tarihi" class="input" value="<?= e($mevcut['alim_tarihi'] ?? '') ?>">
  </div>
  <div class="form-group">
    <label>Alım bedeli (₺)</label>
    <input type="number" name="alim_bedeli" step="0.01" min="0" class="input"
           value="<?= isset($mevcut['alim_bedeli']) && $mevcut['alim_bedeli'] !== null ? e($mevcut['alim_bedeli']) : '' ?>">
  </div>
  <div class="form-group">
    <label>Garanti bitişi</label>
    <input type="date" name="garanti_bitisi" class="input" value="<?= e($mevcut['garanti_bitisi'] ?? '') ?>">
  </div>
  <div class="form-group full-width">
    <label>Notlar</label>
    <textarea name="notlar" class="input" rows="2"><?= e($mevcut['notlar'] ?? '') ?></textarea>
  </div>
  <div class="form-group full-width">
    <label>Garanti belgesi / fatura</label>
    <input type="file" name="ekler[]" class="input" multiple accept=".jpg,.jpeg,.png,.webp,.pdf">
  </div>
</div>
