<?php
// ============================================================
//  daire_detay.php — DAİRE DETAY SAYFASI
//  Tüm dönem geçmişi, toplu ödeme, WhatsApp, rapor
// ============================================================
require_once 'config.php';
require_once 'includes/functions.php';
$sayfa_basligi = 'Daire Detayı';

$kullanici = giris_kontrol();
$db = db();

$daire_id = (int)($_GET['id'] ?? 0);
if (!$daire_id) { header('Location: /daireler.php'); exit; }

// Daire bu kullanıcıya ait mi?
$stmt = $db->prepare("SELECT * FROM daireler WHERE id=? AND kullanici_id=?");
$stmt->execute([$daire_id, $kullanici['id']]);
$daire = $stmt->fetch();
if (!$daire) { header('Location: /daireler.php'); exit; }

$sayfa_basligi = 'Daire #' . $daire['daire_no'] . ' Detay';

// ─── FORM İŞLEME ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_kontrol();
    $islem = $_POST['islem'] ?? '';

    // ── Toplu / Tekil Ödeme Kaydet ────────────────────────────
    if ($islem === 'toplu_odeme') {
        $baslangic    = $_POST['donem_baslangic'] ?? '';
        $bitis        = $_POST['donem_bitis'] ?? '';
        $tutar_tipi   = $_POST['tutar_tipi'] ?? 'standart'; // standart | sabit | farkli
        $sabit_tutar  = max(0, (float)str_replace(',', '.', $_POST['sabit_tutar'] ?? 0));
        $odeme_tarihi = $_POST['odeme_tarihi'] ?: date('Y-m-d');
        $dekont_no    = buyuk($_POST['dekont_no'] ?? '');
        $notlar       = buyuk($_POST['notlar'] ?? '');

        if ($baslangic && $bitis && $baslangic <= $bitis) {
            // Dönem aralığını oluştur
            $donemler = [];
            $current  = $baslangic;
            while ($current <= $bitis) {
                $donemler[] = $current;
                $current = date('Y-m', strtotime($current . '-01 +1 month'));
            }

            $ins = $db->prepare("INSERT INTO aidatlar
                (kullanici_id, daire_id, donem, tutar, durum, odeme_tarihi, dekont_no, notlar)
                VALUES (?,?,?,?,'odendi',?,?,?)
                ON DUPLICATE KEY UPDATE
                    durum='odendi', tutar=VALUES(tutar),
                    odeme_tarihi=VALUES(odeme_tarihi),
                    dekont_no=VALUES(dekont_no),
                    notlar=VALUES(notlar)");

            $say = 0;
            foreach ($donemler as $d) {
                $tutar = $tutar_tipi === 'sabit' ? $sabit_tutar : (float)$daire['aylik_aidat'];
                $ins->execute([$kullanici['id'], $daire_id, $d, $tutar, $odeme_tarihi, $dekont_no, $notlar]);
                $say++;
            }
            flash("$say dönem için ödeme kaydedildi.");
        } else {
            flash('Geçerli bir dönem aralığı seçin.', 'hata');
        }
    }

    // ── Tek dönem ödeme sil ──────────────────────────────────
    if ($islem === 'sil') {
        $aidat_id = (int)$_POST['aidat_id'];
        $db->prepare("DELETE FROM aidatlar WHERE id=? AND kullanici_id=?")->execute([$aidat_id, $kullanici['id']]);
        flash('Kayıt silindi.');
    }

    // ── Tek dönem durumu değiştir ─────────────────────────────
    if ($islem === 'durum_degistir') {
        $aidat_id = (int)$_POST['aidat_id'];
        $yeni_durum = $_POST['yeni_durum'] ?? 'bekliyor';
        if (in_array($yeni_durum, ['odendi','bekliyor','gecikme'])) {
            $tarih = $yeni_durum === 'odendi' ? date('Y-m-d') : null;
            $db->prepare("UPDATE aidatlar SET durum=?, odeme_tarihi=? WHERE id=? AND kullanici_id=?")
               ->execute([$yeni_durum, $tarih, $aidat_id, $kullanici['id']]);
            flash('Durum güncellendi.');
        }
    }

    header("Location: /daire_detay.php?id=$daire_id");
    exit;
}

// ─── TÜM DÖNEM GEÇMİŞİ ───────────────────────────────────────
$stmt = $db->prepare("
    SELECT * FROM aidatlar
    WHERE daire_id = ? AND kullanici_id = ?
    ORDER BY donem DESC
");
$stmt->execute([$daire_id, $kullanici['id']]);
$tum_aidatlar = $stmt->fetchAll();

// ─── İSTATİSTİK ───────────────────────────────────────────────
$toplam_odenen   = array_sum(array_column(array_filter($tum_aidatlar, fn($r) => $r['durum'] === 'odendi'), 'tutar'));
$odenen_ay_say   = count(array_filter($tum_aidatlar, fn($r) => $r['durum'] === 'odendi'));
$bekleyen_ay_say = count(array_filter($tum_aidatlar, fn($r) => $r['durum'] !== 'odendi'));
$son_odeme       = array_filter($tum_aidatlar, fn($r) => $r['durum'] === 'odendi');
$son_odeme_tarihi = !empty($son_odeme) ? max(array_column($son_odeme, 'odeme_tarihi')) : null;

// Dönem listesi (son 36 ay + gelecek 12 ay)
$donem_secenekleri = [];
for ($i = 35; $i >= -11; $i--) {
    $donem_secenekleri[] = date('Y-m', strtotime("-$i months"));
}

include 'includes/header.php';
?>

<div style="display:flex;align-items:center;gap:16px;margin-bottom:28px">
    <a href="/daireler.php" style="display:flex;align-items:center;gap:6px;color:var(--muted);font-size:13px;padding:8px 14px;background:rgba(255,255,255,0.05);border:1px solid var(--border);border-radius:8px;transition:all .2s" onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">
        ← Dairelere Dön
    </a>
    <div>
        <h2 style="font-family:'Playfair Display',serif;font-size:22px;color:var(--text)">
            Daire #<?= e($daire['daire_no']) ?>
            <?php if ($daire['sakin_adi']): ?>
            <span style="color:var(--muted);font-size:16px;font-family:'DM Sans',sans-serif;font-weight:400"> — <?= e_buyuk($daire['sakin_adi']) ?></span>
            <?php endif; ?>
        </h2>
        <?php if ($daire['telefon']): ?>
        <div style="font-size:12px;color:var(--muted);margin-top:2px">📞 <?= e($daire['telefon']) ?><?php if($daire['eposta']): ?> &nbsp;·&nbsp; ✉ <?= e($daire['eposta']) ?><?php endif; ?></div>
        <?php endif; ?>
    </div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:28px">
    <?php foreach ([
        ['Aylık Aidat', para((float)$daire['aylik_aidat']), 'Standart tutar', '#e94560'],
        ['Toplam Ödenen', para($toplam_odenen), "$odenen_ay_say ay ödendi", '#2ecc71'],
        ['Bekleyen Ay', $bekleyen_ay_say, 'Ödenmemiş dönem', '#f5a623'],
        ['Son Ödeme', $son_odeme_tarihi ? tarih_format($son_odeme_tarihi) : '—', 'Son ödeme tarihi', '#0f9b8e'],
    ] as [$lbl,$val,$alt,$renk]): ?>
    <div class="stat-card" style="border-left-color:<?= $renk ?>">
        <div class="stat-label"><?= $lbl ?></div>
        <div class="stat-value" style="color:<?= $renk ?>;font-size:20px"><?= $val ?></div>
        <div class="stat-alt"><?= $alt ?></div>
    </div>
    <?php endforeach; ?>
</div>

<div style="display:flex;gap:10px;margin-bottom:24px;flex-wrap:wrap">
    <button onclick="document.getElementById('modal-toplu').style.display='flex'" class="btn btn-primary">
        💰 Toplu / Tekil Ödeme Gir
    </button>

    <?php
    // WhatsApp mesajı hazırla
    $durum_listesi = '';
    $odenmemis = array_filter($tum_aidatlar, fn($r) => $r['durum'] !== 'odendi');
    if (!empty($odenmemis)) {
        $durum_listesi .= "\n\n*Ödenmemiş Dönemler:*";
        foreach (array_slice($odenmemis, 0, 6) as $a) {
            $durum_listesi .= "\n• " . donem_adi($a['donem']) . " — " . para((float)$a['tutar']);
        }
    }
    $odenmis = array_filter($tum_aidatlar, fn($r) => $r['durum'] === 'odendi');
    $son3 = array_slice($odenmis, 0, 3);
    if (!empty($son3)) {
        $durum_listesi .= "\n\n*Son Ödemeler:*";
        foreach ($son3 as $a) {
            $durum_listesi .= "\n✓ " . donem_adi($a['donem']) . " — " . para((float)$a['tutar']);
        }
    }
    $apartman_adi_buyuk = turkce_buyuk($kullanici['apartman_adi']);
    $wa_text = "Merhaba " . ($daire['sakin_adi'] ? turkce_buyuk($daire['sakin_adi']) : 'Sayın Daire Sakini') . ",\n\n"
        . $apartman_adi_buyuk . " — Daire #" . $daire['daire_no'] . " Aidat Durumu:"
        . $durum_listesi
        . "\n\nBilginize sunarız.\n" . $apartman_adi_buyuk . " Yönetimi";
    $wa_url = "https://wa.me/" . preg_replace('/[^0-9]/', '', $daire['telefon'] ?? '')
        . "?text=" . rawurlencode($wa_text);
    ?>

    <?php if ($daire['telefon']): ?>
    <a href="<?= e($wa_url) ?>" target="_blank" class="btn" style="background:linear-gradient(135deg,#25d366,#128c7e);color:#fff">
        💬 WhatsApp Gönder
    </a>
    <?php else: ?>
    <button class="btn btn-ghost" onclick="alert('Bu daire için önce telefon numarası giriniz.')">
        💬 WhatsApp Gönder
    </button>
    <?php endif; ?>

    <a href="/daire_print.php?id=<?= $daire['id'] ?>" target="_blank" class="btn btn-ghost">
        🖨 Daire Raporu Yazdır
    </a>

    <a href="/daireler.php?duzenle=<?= $daire['id'] ?>" class="btn btn-ghost">
        ✎ Daire Bilgilerini Düzenle
    </a>
</div>

<div class="card p0">
    <div class="card-header">
        <span>📋 Tüm Dönem Geçmişi</span>
        <span style="font-size:12px;color:var(--muted)"><?= count($tum_aidatlar) ?> kayıt</span>
    </div>
    <?php if (empty($tum_aidatlar)): ?>
    <div class="empty-state">Henüz hiç aidat kaydı yok. "Toplu / Tekil Ödeme Gir" butonunu kullanın.</div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Dönem</th>
                    <th>Tutar</th>
                    <th>Durum</th>
                    <th>Ödeme Tarihi</th>
                    <th>Dekont No</th>
                    <th>Not</th>
                    <th>İşlem</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($tum_aidatlar as $a): ?>
            <tr>
                <td><strong><?= e(donem_adi($a['donem'])) ?></strong></td>
                <td><?= para((float)$a['tutar']) ?></td>
                <td>
                    <span class="badge badge-<?= $a['durum'] === 'odendi' ? 'success' : ($a['durum'] === 'gecikme' ? 'danger' : 'warning') ?>">
                        <?= $a['durum'] === 'odendi' ? '✓ Ödendi' : ($a['durum'] === 'gecikme' ? '! Gecikme' : '⏳ Bekliyor') ?>
                    </span>
                </td>
                <td><?= tarih_format($a['odeme_tarihi'] ?? '') ?></td>
                <td><?= $a['dekont_no'] ? e_buyuk($a['dekont_no']) : '—' ?></td>
                <td style="font-size:12px;color:var(--muted)"><?= $a['notlar'] ? e_buyuk($a['notlar']) : '—' ?></td>
                <td>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="islem" value="durum_degistir">
                        <input type="hidden" name="aidat_id" value="<?= $a['id'] ?>">
                        <?php if ($a['durum'] !== 'odendi'): ?>
                        <input type="hidden" name="yeni_durum" value="odendi">
                        <button type="submit" class="btn btn-sm btn-success">✓ Ödendi</button>
                        <?php else: ?>
                        <input type="hidden" name="yeni_durum" value="bekliyor">
                        <button type="submit" class="btn btn-sm btn-ghost" style="font-size:11px">↩ Geri Al</button>
                        <?php endif; ?>
                    </form>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="islem" value="sil">
                        <input type="hidden" name="aidat_id" value="<?= $a['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger"
                                data-confirm="<?= e(donem_adi($a['donem'])) ?> kaydı silinsin mi?">✕</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td><strong>TOPLAM ÖDENDİ</strong></td>
                    <td><strong style="color:#2ecc71"><?= para($toplam_odenen) ?></strong></td>
                    <td colspan="5"></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endif; ?>
</div>

<div class="modal-overlay" id="modal-toplu" style="display:none">
    <div class="modal" style="max-width:580px">
        <div class="modal-header">
            <h3>💰 Ödeme Gir — Daire #<?= e($daire['daire_no']) ?></h3>
            <button onclick="document.getElementById('modal-toplu').style.display='none'" class="modal-close">×</button>
        </div>
        <form method="post" style="padding:24px">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="islem" value="toplu_odeme">

            <div style="background:rgba(233,69,96,0.06);border:1px solid rgba(233,69,96,0.2);border-radius:10px;padding:16px;margin-bottom:18px">
                <div style="font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:12px">
                    📅 Dönem Aralığı — Tek ay için aynı ayı seçin
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                    <div class="form-group">
                        <label>Başlangıç Dönemi</label>
                        <select name="donem_baslangic" class="input" required>
                            <?php foreach ($donem_secenekleri as $d): ?>
                            <option value="<?= e($d) ?>" <?= $d === date('Y-m') ? 'selected' : '' ?>>
                                <?= e(donem_adi($d)) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Bitiş Dönemi</label>
                        <select name="donem_bitis" class="input" required>
                            <?php foreach ($donem_secenekleri as $d): ?>
                            <option value="<?= e($d) ?>" <?= $d === date('Y-m') ? 'selected' : '' ?>>
                                <?= e(donem_adi($d)) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div style="font-size:11px;color:var(--muted);margin-top:8px">
                    💡 Örnek: Ocak 2026'dan Aralık 2026'ya kadar → 12 ay ödeme kaydedilir
                </div>
            </div>

            <div style="background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:10px;padding:16px;margin-bottom:18px">
                <div style="font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:12px">
                    ₺ Tutar Seçeneği
                </div>
                <div style="display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:8px 14px;border:1px solid var(--border);border-radius:8px;font-size:13px">
                        <input type="radio" name="tutar_tipi" value="standart" checked
                               onchange="document.getElementById('sabit-tutar-wrap').style.display='none'">
                        Standart (₺<?= number_format((float)$daire['aylik_aidat'],2,',','.') ?>/ay)
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:8px 14px;border:1px solid var(--border);border-radius:8px;font-size:13px">
                        <input type="radio" name="tutar_tipi" value="sabit"
                               onchange="document.getElementById('sabit-tutar-wrap').style.display='block'">
                        Farklı tutar gir
                    </label>
                </div>
                <div id="sabit-tutar-wrap" style="display:none" class="form-group">
                    <label>Her ay için tutar (₺)</label>
                    <input type="number" name="sabit_tutar" class="input" step="0.01" placeholder="1250.00">
                </div>
            </div>

            <div class="form-grid" style="margin-bottom:18px">
                <div class="form-group">
                    <label>Ödeme Tarihi</label>
                    <input type="date" name="odeme_tarihi" class="input" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label>Dekont / Makbuz No</label>
                    <input type="text" name="dekont_no" class="input buyuk" placeholder="Dekont numarası...">
                </div>
                <div class="form-group full-width">
                    <label>Not</label>
                    <input type="text" name="notlar" class="input buyuk" placeholder="Toplu ödeme, peşin vb...">
                </div>
            </div>

            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">💾 Kaydet</button>
                <button type="button" onclick="document.getElementById('modal-toplu').style.display='none'" class="btn btn-ghost">İptal</button>
            </div>
        </form>
    </div>
</div>

<style>
@media print {
    .sidebar, .top-bar, .btn, .modal-overlay, [onclick] { display:none!important; }
    .main-wrap { margin-left:0!important; }
    .page-content { padding:20px!important; }
    body { background:#fff!important; color:#000!important; }
    .card { background:#fff!important; border:1px solid #ddd!important; }
    .stat-card { background:#fff!important; border:1px solid #ddd!important; }
    .table th { background:#333!important; color:#fff!important; print-color-adjust:exact; }
    .table td { color:#000!important; }
    .badge { border:1px solid #ccc!important; print-color-adjust:exact; }
    h2 { font-size:18px; }
}
</style>

<?php include 'includes/footer.php'; ?>