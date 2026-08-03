<?php
// ============================================================
//  yonetim/duyurular.php — SİSTEM DUYURULARI
//
//  Panelin üstünde bant olarak görünen bildirimler: planlı bakım,
//  yeni özellik, uyarı. Tarih aralığı verilerek zamanlanabilir;
//  site_id ile tek bir apartmana hedeflenebilir.
// ============================================================

require_once __DIR__ . '/ortak.php';
$yonetici = yonetim_kontrol();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    yonetim_yazma_kontrol();
    $islem = $_POST['islem'] ?? '';

    if ($islem === 'ekle') {
        $baslik = trim($_POST['baslik'] ?? '');
        $mesaj  = trim($_POST['mesaj'] ?? '');
        $tip    = in_array($_POST['tip'] ?? '', ['bilgi','uyari','bakim'], true) ? $_POST['tip'] : 'bilgi';
        $site   = (int)($_POST['site_id'] ?? 0) ?: null;

        // datetime-local alanı "2026-08-02T14:30" biçiminde gelir;
        // DATETIME sütunu için 'T' boşluğa çevrilir. Boş alan NULL
        // olmalı — '' değeri katı kipte hata verir.
        $tarih_normalle = static function (?string $ham): ?string {
            $ham = trim((string)$ham);
            if ($ham === '') return null;
            $ham = str_replace('T', ' ', $ham);
            return preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $ham) ? $ham : null;
        };
        $bas = $tarih_normalle($_POST['baslangic'] ?? null);
        $bit = $tarih_normalle($_POST['bitis'] ?? null);

        if ($baslik === '' || $mesaj === '') {
            flash('Başlık ve mesaj zorunludur.', 'hata');
        } else {
            db()->prepare("INSERT INTO duyurular (baslik, mesaj, tip, site_id, baslangic, bitis, olusturan)
                           VALUES (?,?,?,?,?,?,?)")
                ->execute([$baslik, $mesaj, $tip, $site, $bas, $bit, (int)$yonetici['id']]);
            yonetim_denetim('duyuru_eklendi', 'duyuru', (int)db()->lastInsertId(), ['tip' => $tip]);
            flash('Duyuru eklendi.');
        }
    }

    if ($islem === 'durum') {
        $id    = (int)($_POST['id'] ?? 0);
        $durum = ($_POST['durum'] ?? '') === 'aktif' ? 'aktif' : 'pasif';
        db()->prepare("UPDATE duyurular SET durum = ? WHERE id = ?")->execute([$durum, $id]);
        yonetim_denetim('duyuru_durumu_degistirildi', 'duyuru', $id, ['durum' => $durum]);
        flash('Duyuru durumu güncellendi.');
    }

    if ($islem === 'sil') {
        $id = (int)($_POST['id'] ?? 0);
        db()->prepare("DELETE FROM duyurular WHERE id = ?")->execute([$id]);
        yonetim_denetim('duyuru_silindi', 'duyuru', $id);
        flash('Duyuru silindi.');
    }

    header('Location: /yonetim/duyurular.php');
    exit;
}

$duyurular = db()->query("
    SELECT d.*, s.ad AS site_adi, k.kullanici_adi
    FROM duyurular d
    LEFT JOIN siteler s     ON s.id = d.site_id
    LEFT JOIN kullanicilar k ON k.id = d.olusturan
    ORDER BY d.id DESC
")->fetchAll();

$siteler   = db()->query("SELECT id, ad FROM siteler ORDER BY ad")->fetchAll();
$yazabilir = platform_yazabilir_mi($yonetici['platform_rolu']);

yonetim_basla($yonetici, 'Duyurular');
?>

<?php if ($yazabilir): ?>
<div class="y-bolum">
  <h2>Yeni duyuru</h2>
  <form method="post" class="y-form y-form-izgara">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="islem" value="ekle">

    <label class="y-tam">Başlık
      <input type="text" name="baslik" maxlength="150" required placeholder="örn. Planlı bakım">
    </label>
    <label class="y-tam">Mesaj
      <textarea name="mesaj" rows="3" required
                placeholder="Kullanıcılara panelde gösterilecek metin"></textarea>
    </label>
    <label>Tür
      <select name="tip">
        <option value="bilgi">Bilgi</option>
        <option value="uyari">Uyarı</option>
        <option value="bakim">Bakım</option>
      </select>
    </label>
    <label>Hedef
      <select name="site_id">
        <option value="">Tüm siteler</option>
        <?php foreach ($siteler as $s): ?>
          <option value="<?= (int)$s['id'] ?>"><?= e_buyuk($s['ad']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Başlangıç <small>(boşsa hemen)</small>
      <input type="datetime-local" name="baslangic">
    </label>
    <label>Bitiş <small>(boşsa süresiz)</small>
      <input type="datetime-local" name="bitis">
    </label>
    <div class="y-tam"><button type="submit">Duyuruyu Yayınla</button></div>
  </form>
</div>
<?php endif; ?>

<div class="y-bolum">
  <h2>Tüm duyurular</h2>
  <table class="y-tablo">
    <thead><tr><th>Başlık</th><th>Tür</th><th>Hedef</th><th>Aralık</th>
               <th>Durum</th><th>Ekleyen</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($duyurular as $d): ?>
      <tr>
        <td><strong><?= e($d['baslik']) ?></strong>
            <div class="y-soluk y-kucuk-yazi"><?= e(mb_strimwidth($d['mesaj'], 0, 110, '…', 'UTF-8')) ?></div></td>
        <td><span class="y-rozet <?= $d['tip'] === 'bakim' ? 'y-rozet-uyari' : '' ?>"><?= e($d['tip']) ?></span></td>
        <td class="y-soluk"><?= $d['site_adi'] ? e_buyuk($d['site_adi']) : 'Tüm siteler' ?></td>
        <td class="y-soluk y-kucuk-yazi">
          <?= $d['baslangic'] ? e(date('d.m.Y H:i', strtotime($d['baslangic']))) : 'hemen' ?>
          →
          <?= $d['bitis'] ? e(date('d.m.Y H:i', strtotime($d['bitis']))) : 'süresiz' ?>
        </td>
        <td><span class="y-rozet <?= $d['durum'] === 'aktif' ? 'y-rozet-basari' : '' ?>"><?= e($d['durum']) ?></span></td>
        <td class="y-soluk"><?= e($d['kullanici_adi'] ?? '—') ?></td>
        <td class="y-sag">
          <?php if ($yazabilir): ?>
          <form method="post" class="y-satir-form">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="islem" value="durum">
            <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
            <input type="hidden" name="durum" value="<?= $d['durum'] === 'aktif' ? 'pasif' : 'aktif' ?>">
            <button type="submit" class="y-mini"><?= $d['durum'] === 'aktif' ? 'Durdur' : 'Yayınla' ?></button>
          </form>
          <form method="post" class="y-satir-form" onsubmit="return confirm('Duyuru silinsin mi?')">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="islem" value="sil">
            <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
            <button type="submit" class="y-mini y-tehlike-btn">Sil</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$duyurular): ?><tr><td colspan="7" class="y-bos">Henüz duyuru yok.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php yonetim_bitir(); ?>
