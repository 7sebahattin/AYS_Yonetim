// ============================================================
//  pwa-install.js — Service worker kaydı + "Uygulamayı Yükle" banner'ı
//  Tüm sayfalara tek satırla dahil edilir, DOM'u kendisi oluşturur.
// ============================================================
(function () {
  'use strict';

  var DISMISS_KEY = 'ays_pwa_install_dismissed_at';
  var DISMISS_DAYS = 14; // bu sure sonunda banner tekrar sorulabilir

  // ── Service Worker kaydı ────────────────────────────────────
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register('/sw.js').catch(function () {
        /* sw kaydı başarısız olursa sessizce yok say, uygulama normal çalışır */
      });
    });
  }

  // ── Zaten yüklenmiş mi (standalone modda mı çalışıyor)? ─────
  function isStandalone() {
    return (
      window.matchMedia('(display-mode: standalone)').matches ||
      window.navigator.standalone === true // iOS Safari
    );
  }
  if (isStandalone()) return;

  // ── Daha önce kapatıldı mı ve süresi dolmadı mı? ─────────────
  function recentlyDismissed() {
    var raw = localStorage.getItem(DISMISS_KEY);
    if (!raw) return false;
    var elapsedDays = (Date.now() - parseInt(raw, 10)) / 86400000;
    return elapsedDays < DISMISS_DAYS;
  }
  if (recentlyDismissed()) return;

  // Panel sayfalarında (bottom-nav varsa) banner'ı navigasyonun üstüne al
  if (document.querySelector('.bottom-nav')) {
    document.body.classList.add('has-bottom-nav');
  }

  var deferredPrompt = null;
  var bannerEl = null;

  function buildBanner(opts) {
    var el = document.createElement('div');
    el.className = 'pwa-install-banner';
    el.setAttribute('role', 'dialog');
    el.setAttribute('aria-label', 'Uygulamayı yükle');

    var icon = document.createElement('div');
    icon.className = 'pwa-install-icon';
    icon.setAttribute('aria-hidden', 'true');
    icon.textContent = '🏢';

    var text = document.createElement('div');
    text.className = 'pwa-install-text';
    var strong = document.createElement('strong');
    strong.textContent = 'AYS\'yi Yükle';
    var span = document.createElement('span');
    span.textContent = opts.message;
    text.appendChild(strong);
    text.appendChild(span);

    var actions = document.createElement('div');
    actions.className = 'pwa-install-actions';

    var dismissBtn = document.createElement('button');
    dismissBtn.type = 'button';
    dismissBtn.className = 'btn btn-sm btn-ghost';
    dismissBtn.textContent = opts.dismissLabel;
    dismissBtn.addEventListener('click', function () {
      localStorage.setItem(DISMISS_KEY, String(Date.now()));
      hideBanner();
    });
    actions.appendChild(dismissBtn);

    if (opts.onAccept) {
      var acceptBtn = document.createElement('button');
      acceptBtn.type = 'button';
      acceptBtn.className = 'btn btn-sm btn-primary';
      acceptBtn.textContent = opts.acceptLabel;
      acceptBtn.addEventListener('click', opts.onAccept);
      actions.appendChild(acceptBtn);
    }

    el.appendChild(icon);
    el.appendChild(text);
    el.appendChild(actions);
    return el;
  }

  function showBanner(el) {
    if (bannerEl) return;
    bannerEl = el;
    document.body.appendChild(bannerEl);
  }

  function hideBanner() {
    if (bannerEl && bannerEl.parentNode) bannerEl.parentNode.removeChild(bannerEl);
    bannerEl = null;
  }

  // ── Chrome/Edge/Android: gerçek yükleme istemi ───────────────
  window.addEventListener('beforeinstallprompt', function (event) {
    event.preventDefault();
    deferredPrompt = event;

    var banner = buildBanner({
      message: 'Ana ekranınıza ekleyin, uygulama gibi hızlıca açın.',
      dismissLabel: 'Kapat',
      acceptLabel: 'Yükle',
      onAccept: function () {
        hideBanner();
        if (!deferredPrompt) return;
        deferredPrompt.prompt();
        deferredPrompt.userChoice.finally(function () {
          deferredPrompt = null;
        });
      },
    });
    showBanner(banner);
  });

  window.addEventListener('appinstalled', function () {
    localStorage.setItem(DISMISS_KEY, String(Date.now()));
    hideBanner();
    deferredPrompt = null;
  });

  // ── iOS Safari: beforeinstallprompt desteklenmez, elle yönlendir ─
  function isIosSafari() {
    var ua = window.navigator.userAgent;
    var isIos = /iPad|iPhone|iPod/.test(ua);
    var isSafari = /Safari/.test(ua) && !/CriOS|FxiOS|EdgiOS/.test(ua);
    return isIos && isSafari;
  }

  if (isIosSafari()) {
    var iosBanner = buildBanner({
      message: 'Kurulum için altta ⬆ Paylaş simgesine, ardından "Ana Ekrana Ekle"ye dokunun.',
      dismissLabel: 'Anladım',
    });
    showBanner(iosBanner);
  }
})();
