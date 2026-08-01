// ============================================================
//  sw.js — AYS Service Worker
//  Yalnızca statik varlıkları (CSS, ikonlar) önbelleğe alır.
//  Oturuma bağlı / mali veri içeren .php sayfaları KESİNLİKLE
//  önbelleklenmez — bunlar her zaman ağdan (network-only) alınır.
// ============================================================

const CACHE_VERSION = 'ays-static-v1';

// Kurulumda önceden önbelleğe alınacak, gerçekten statik dosyalar
const PRECACHE_URLS = [
  '/assets/style.css',
  '/assets/landing.css',
  '/assets/print.css',
  '/assets/icons/icon-192.png',
  '/assets/icons/icon-512.png',
  '/assets/icons/icon-maskable-192.png',
  '/assets/icons/icon-maskable-512.png',
  '/assets/icons/apple-touch-icon.png',
  '/manifest.json',
  '/offline.html',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_VERSION)
      .then((cache) => cache.addAll(PRECACHE_URLS))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((names) => Promise.all(
        names.filter((n) => n !== CACHE_VERSION).map((n) => caches.delete(n))
      ))
      .then(() => self.clients.claim())
  );
});

// Bir istek gerçekten "statik varlık" mı? (CSS / ikon / manifest — hiçbir
// zaman .php uzantılı, oturuma özel veya sorgu dizesi taşıyan içerik değil)
function isStaticAsset(url) {
  if (url.origin !== self.location.origin) return false;
  return (
    url.pathname.startsWith('/assets/') ||
    url.pathname === '/manifest.json'
  );
}

self.addEventListener('fetch', (event) => {
  const req = event.request;

  // Sadece GET istekleri değerlendirilir; POST/aidat-gider formları asla
  // önbellekten karşılanmaz veya önbelleğe yazılmaz.
  if (req.method !== 'GET') return;

  const url = new URL(req.url);

  if (isStaticAsset(url)) {
    // Statik varlıklar: cache-first, arka planda güncelle (stale-while-revalidate)
    event.respondWith(
      caches.open(CACHE_VERSION).then((cache) =>
        cache.match(req).then((cached) => {
          const network = fetch(req)
            .then((res) => {
              if (res && res.status === 200) cache.put(req, res.clone());
              return res;
            })
            .catch(() => cached);
          return cached || network;
        })
      )
    );
    return;
  }

  // Sayfa gezinmeleri (.php dahil): her zaman ağdan. Yalnızca çevrimdışı
  // kalınırsa jenerik offline.html gösterilir — gerçek panel/veri içeriği
  // hiçbir koşulda önbelleğe alınmaz.
  if (req.mode === 'navigate') {
    event.respondWith(
      fetch(req).catch(() => caches.match('/offline.html'))
    );
  }
  // Diğer tüm istekler (dinamik .php, API benzeri çağrılar) service worker
  // tarafından dokunulmadan doğrudan ağa geçer.
});
