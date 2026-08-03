// ============================================================
//  assets/kirpici.js — FOTOĞRAF KIRPMA ARACI
//
//  Fiş/fatura/dekont fotoğrafları çoğu zaman telefonla çekilir ve
//  kenarlarında masa, el ya da fazladan alan kalır. Bu araç, bir
//  <input type="file" data-kirpici> alanına dosya seçildiğinde
//  (yalnızca resimler için) bir kırpma penceresi açar; kullanıcı
//  kırpma alanını sürükleyip onayladığında SONUÇ AYNI input'a
//  DataTransfer ile geri yazılır. Böylece formun normal multipart
//  gönderim akışı hiç değişmez — sunucu tarafı bunun elle kırpılmış
//  bir dosya mı yoksa doğrudan seçilmiş bir dosya mı olduğunu bilmez.
//
//  Tasarım notu — iki ayrı çözünürlük: Etkileşim sırasında (sürükleme,
//  yeniden boyutlandırma) tam çözünürlüklü kaynağı her karede çizmek
//  yavaş olurdu; ekranda küçültülmüş bir kopya gösterilir, kırpma ise
//  onay anında ORİJİNAL kaynak bitmap'ten alınır — böylece kalite
//  kaybı yalnızca dışa aktarım sınırından (MAX_KENAR) kaynaklanır.
// ============================================================
(function () {
    'use strict';

    var MAX_KENAR = 1600; // dışa aktarılan görselin uzun kenarı (px)
    var KIRPILABILIR_TUR = /^image\/(jpeg|png|webp)$/;

    function elOlustur(etiket, sinif) {
        var el = document.createElement(etiket);
        if (sinif) el.className = sinif;
        return el;
    }

    // EXIF döndürme bilgisini tarayıcının kendisinin uygulaması için
    // createImageBitmap tercih edilir; desteklenmiyorsa Image()'e düşülür
    // (bu durumda EXIF döndürmesi bazı tarayıcılarda uygulanmayabilir).
    function kaynakYukle(dosya) {
        if (window.createImageBitmap) {
            return createImageBitmap(dosya, { imageOrientation: 'from-image' })
                .catch(function () { return imgIleYukle(dosya); });
        }
        return imgIleYukle(dosya);
    }

    function imgIleYukle(dosya) {
        return new Promise(function (resolve, reject) {
            var url = URL.createObjectURL(dosya);
            var img = new Image();
            img.onload = function () { URL.revokeObjectURL(url); resolve(img); };
            img.onerror = function () { URL.revokeObjectURL(url); reject(new Error('Görsel yüklenemedi.')); };
            img.src = url;
        });
    }

    function kGenislik(k) { return k.width || k.naturalWidth; }
    function kYukseklik(k) { return k.height || k.naturalHeight; }

    var aktifOverlay = null;
    function aktifPencereyiKapat() {
        if (aktifOverlay) { aktifOverlay.remove(); aktifOverlay = null; }
    }

    function baglan(girdi) {
        girdi.addEventListener('change', function () {
            var dosya = girdi.files && girdi.files[0];
            if (!dosya || !KIRPILABILIR_TUR.test(dosya.type)) return; // PDF vb. kırpılmadan olduğu gibi gönderilir

            kaynakYukle(dosya).then(function (kaynak) {
                penceresiAc(kaynak, dosya, girdi);
            }).catch(function () {
                // Sessizce geç: kullanıcı yine de orijinal dosyayı gönderebilir.
            });
        });
    }

    function penceresiAc(kaynak, orijinalDosya, girdi) {
        aktifPencereyiKapat();

        var genW = kGenislik(kaynak), yukH = kYukseklik(kaynak);
        var donusDerece = 0; // 0, 90, 180, 270

        var overlay = elOlustur('div', 'kirpici-overlay');
        var kutu    = elOlustur('div', 'kirpici-kutu');
        var baslik  = elOlustur('div', 'kirpici-baslik');
        baslik.textContent = 'Fotoğrafı kırp';
        var alan   = elOlustur('div', 'kirpici-alan');
        var canvas = elOlustur('canvas', 'kirpici-canvas');
        var secim  = elOlustur('div', 'kirpici-secim');
        ['nw', 'ne', 'sw', 'se'].forEach(function (yon) {
            var tutamac = elOlustur('div', 'kirpici-tutamac kirpici-tutamac-' + yon);
            tutamac.dataset.yon = yon;
            secim.appendChild(tutamac);
        });
        alan.appendChild(canvas);
        alan.appendChild(secim);

        var arac = elOlustur('div', 'kirpici-arac');
        var dondurBtn = elOlustur('button', 'btn btn-ghost btn-sm');
        dondurBtn.type = 'button';
        dondurBtn.textContent = '⟲ Döndür';
        var iptalBtn = elOlustur('button', 'btn btn-ghost btn-sm');
        iptalBtn.type = 'button';
        iptalBtn.textContent = 'İptal';
        var kullanBtn = elOlustur('button', 'btn btn-primary btn-sm');
        kullanBtn.type = 'button';
        kullanBtn.textContent = '✓ Kırp ve Kullan';
        arac.appendChild(dondurBtn);
        arac.appendChild(iptalBtn);
        arac.appendChild(kullanBtn);

        kutu.appendChild(baslik);
        kutu.appendChild(alan);
        kutu.appendChild(arac);
        overlay.appendChild(kutu);
        document.body.appendChild(overlay);
        aktifOverlay = overlay;

        var ctx = canvas.getContext('2d');
        var ekranGenislik, ekranYukseklik;

        function ekranBoyutuHesapla() {
            var w = (donusDerece % 180 === 90) ? yukH : genW;
            var h = (donusDerece % 180 === 90) ? genW : yukH;
            var azamiW = Math.min(560, window.innerWidth - 64);
            var azamiH = Math.min(420, window.innerHeight - 220);
            var olcek = Math.min(azamiW / w, azamiH / h, 1);
            return { w: Math.max(1, Math.round(w * olcek)), h: Math.max(1, Math.round(h * olcek)) };
        }

        function ciz() {
            var boyut = ekranBoyutuHesapla();
            ekranGenislik = boyut.w;
            ekranYukseklik = boyut.h;
            canvas.width = ekranGenislik;
            canvas.height = ekranYukseklik;
            alan.style.width = ekranGenislik + 'px';
            alan.style.height = ekranYukseklik + 'px';

            var cizimW = (donusDerece % 180 === 90) ? ekranYukseklik : ekranGenislik;
            var cizimH = (donusDerece % 180 === 90) ? ekranGenislik : ekranYukseklik;

            ctx.save();
            ctx.clearRect(0, 0, ekranGenislik, ekranYukseklik);
            ctx.translate(ekranGenislik / 2, ekranYukseklik / 2);
            ctx.rotate(donusDerece * Math.PI / 180);
            ctx.drawImage(kaynak, -cizimW / 2, -cizimH / 2, cizimW, cizimH);
            ctx.restore();
        }

        var sec = { x: 0, y: 0, w: 0, h: 0 };

        function secimiSifirla() {
            var kenar = Math.round(Math.min(ekranGenislik, ekranYukseklik) * 0.86);
            sec.w = Math.min(kenar, ekranGenislik);
            sec.h = Math.min(kenar, ekranYukseklik);
            sec.x = Math.round((ekranGenislik - sec.w) / 2);
            sec.y = Math.round((ekranYukseklik - sec.h) / 2);
            secimiCiz();
        }

        function secimiCiz() {
            secim.style.left   = sec.x + 'px';
            secim.style.top    = sec.y + 'px';
            secim.style.width  = sec.w + 'px';
            secim.style.height = sec.h + 'px';
        }

        function sinirlaSec() {
            sec.w = Math.max(30, Math.min(sec.w, ekranGenislik));
            sec.h = Math.max(30, Math.min(sec.h, ekranYukseklik));
            sec.x = Math.max(0, Math.min(sec.x, ekranGenislik - sec.w));
            sec.y = Math.max(0, Math.min(sec.y, ekranYukseklik - sec.h));
        }

        ciz();
        secimiSifirla();

        // Pointer Events: fare ve dokunmatiği tek koddan yönetir.
        var surukleme = null;
        secim.addEventListener('pointerdown', function (e) {
            var yon = (e.target && e.target.dataset) ? e.target.dataset.yon : null;
            surukleme = { yon: yon || 'tasi', bx: e.clientX, by: e.clientY, sec: { x: sec.x, y: sec.y, w: sec.w, h: sec.h } };
            secim.setPointerCapture(e.pointerId);
            e.preventDefault();
        });
        secim.addEventListener('pointermove', function (e) {
            if (!surukleme) return;
            var dx = e.clientX - surukleme.bx;
            var dy = e.clientY - surukleme.by;
            var b = surukleme.sec;

            if (surukleme.yon === 'tasi') {
                sec.x = b.x + dx; sec.y = b.y + dy;
            } else {
                if (surukleme.yon.indexOf('e') !== -1) sec.w = b.w + dx;
                if (surukleme.yon.indexOf('s') !== -1) sec.h = b.h + dy;
                if (surukleme.yon.indexOf('w') !== -1) { sec.x = b.x + dx; sec.w = b.w - dx; }
                if (surukleme.yon.indexOf('n') !== -1) { sec.y = b.y + dy; sec.h = b.h - dy; }
            }
            sinirlaSec();
            secimiCiz();
        });
        function birak(e) {
            if (surukleme) { try { secim.releasePointerCapture(e.pointerId); } catch (ex) { /* yok say */ } }
            surukleme = null;
        }
        secim.addEventListener('pointerup', birak);
        secim.addEventListener('pointercancel', birak);

        dondurBtn.addEventListener('click', function () {
            donusDerece = (donusDerece + 90) % 360;
            ciz();
            secimiSifirla();
        });

        function kapat() {
            overlay.remove();
            if (aktifOverlay === overlay) aktifOverlay = null;
        }

        iptalBtn.addEventListener('click', function () { girdi.value = ''; kapat(); });
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) { girdi.value = ''; kapat(); }
        });

        kullanBtn.addEventListener('click', function () {
            var donmusW = (donusDerece % 180 === 90) ? yukH : genW;
            var donmusH = (donusDerece % 180 === 90) ? genW : yukH;
            var olcekX = donmusW / ekranGenislik;
            var olcekY = donmusH / ekranYukseklik;

            var kx = sec.x * olcekX, ky = sec.y * olcekY;
            var kw = sec.w * olcekX, kh = sec.h * olcekY;

            // Döndürülmüş kaynağı önce tam boyutlu bir ara canvas'a çiz,
            // seçimi ORADAN kırp — dört sabit açı (0/90/180/270) için
            // matris tersine çevirmekten daha basit ve hatasız.
            var donmusTam = document.createElement('canvas');
            donmusTam.width = donmusW;
            donmusTam.height = donmusH;
            var dctx = donmusTam.getContext('2d');
            dctx.save();
            dctx.translate(donmusW / 2, donmusH / 2);
            dctx.rotate(donusDerece * Math.PI / 180);
            dctx.drawImage(kaynak, -genW / 2, -yukH / 2, genW, yukH);
            dctx.restore();

            var uzunKenar = Math.max(kw, kh);
            var disaOlcek = uzunKenar > MAX_KENAR ? MAX_KENAR / uzunKenar : 1;
            var cikisW = Math.max(1, Math.round(kw * disaOlcek));
            var cikisH = Math.max(1, Math.round(kh * disaOlcek));

            var cikisCanvas = document.createElement('canvas');
            cikisCanvas.width = cikisW;
            cikisCanvas.height = cikisH;
            cikisCanvas.getContext('2d').drawImage(donmusTam, kx, ky, kw, kh, 0, 0, cikisW, cikisH);

            cikisCanvas.toBlob(function (blob) {
                if (!blob) { kapat(); return; }
                var ad = (orijinalDosya.name || 'fis').replace(/\.[^.]+$/, '') + '_kirpik.jpg';
                try {
                    var yeniDosya = new File([blob], ad, { type: 'image/jpeg' });
                    var aktarim = new DataTransfer();
                    aktarim.items.add(yeniDosya);
                    girdi.files = aktarim.files;
                } catch (ex) {
                    // DataTransfer/File desteklenmiyorsa (çok eski tarayıcı):
                    // orijinal dosya olduğu gibi gönderilmeye devam eder.
                }
                kapat();
            }, 'image/jpeg', 0.85);
        });
    }

    function baslat() {
        var girdiler = document.querySelectorAll('input[type="file"][data-kirpici]');
        girdiler.forEach(baglan);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', baslat);
    } else {
        baslat();
    }
})();
