// ============================================================
//  assets/onizleme.js — YÜKLENMİŞ GÖRSEL EKLERİ ÖNİZLEME (LIGHTBOX)
//
//  [data-lightbox] işaretli bir bağlantıya tıklanınca, yeni sekmeye
//  gitmek/indirmek yerine görsel sayfa üzerinde büyük bir pencerede
//  gösterilir. Yalnızca gerçek (finfo doğrulamalı) raster görseller
//  için kullanılır — bkz. belge_indir.php.
// ============================================================
(function () {
    'use strict';

    var aktifOverlay = null;

    function kapat() {
        if (aktifOverlay) { aktifOverlay.remove(); aktifOverlay = null; }
    }

    function ac(url, baslik) {
        kapat();

        var overlay = document.createElement('div');
        overlay.className = 'onizleme-overlay';

        var kapatBtn = document.createElement('button');
        kapatBtn.type = 'button';
        kapatBtn.className = 'onizleme-kapat';
        kapatBtn.setAttribute('aria-label', 'Kapat');
        kapatBtn.textContent = '×';

        var img = document.createElement('img');
        img.className = 'onizleme-resim';
        img.src = url;
        img.alt = baslik || '';

        // Boyut sınırı satır içi de veriliyor: stil dosyası bir şekilde
        // eski/eksik gelirse görsel ham boyutuyla açılıp ekranı taşırıyordu.
        // Asıl kural style.css'te; buradaki yalnızca emniyet kemeri.
        img.style.maxWidth  = 'min(100%, 900px)';
        img.style.maxHeight = '88vh';
        img.style.width     = 'auto';
        img.style.height    = 'auto';
        img.style.objectFit = 'contain';

        overlay.appendChild(img);
        overlay.appendChild(kapatBtn);
        document.body.appendChild(overlay);
        aktifOverlay = overlay;

        overlay.addEventListener('click', function (e) {
            if (e.target === overlay || e.target === kapatBtn) kapat();
        });
    }

    document.addEventListener('click', function (e) {
        var baglanti = e.target.closest ? e.target.closest('[data-lightbox]') : null;
        if (!baglanti) return;
        e.preventDefault();
        ac(baglanti.getAttribute('href'), baglanti.getAttribute('data-baslik'));
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') kapat();
    });
})();
