/*
 * Service Worker für Packen.
 *
 * - Seiten (Startseite, Reisen): erst Netz, sonst Cache, sonst Offline-Seite. Jede geöffnete
 *   Reise landet so automatisch im Cache und lässt sich ohne Netz öffnen.
 * - Gebaute Assets (/build/…) haben Hashes im Namen: Cache zuerst.
 * - Bilder (Titelbilder, Benutzerbilder): Cache zuerst, im Hintergrund auffrischen.
 * - Alles andere (POST, PATCH, JSON) geht direkt ans Netz; das Abhaken puffert die Seite selbst.
 *
 * Beim Abmelden löscht die Seite die Caches (siehe resources/js/offline.js).
 */
const VERSION = 'v5';
const STATIC = `packen-static-${VERSION}`;
const PAGES = 'packen-pages';
const IMAGES = 'packen-images';
const PRECACHE = ['/offline.html', '/manifest.webmanifest', '/app-icons/icon-192.png'];

const CACHEABLE_PAGE = /^\/(reisen\/(?!create$)[a-z0-9-]+)?$/;
const IMAGE = /^\/(reisen\/[a-z0-9-]+\/bild|benutzer\/\d+\/bild)$/;

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(STATIC).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k.startsWith('packen-static-') && k !== STATIC).map((k) => caches.delete(k))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) return;

    if (request.mode === 'navigate') {
        event.respondWith(networkFirstPage(request, url));
    } else if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/app-icons/')) {
        event.respondWith(cacheFirst(request, STATIC));
    } else if (IMAGE.test(url.pathname)) {
        event.respondWith(staleWhileRevalidate(request, IMAGES));
    }
});

/** Antworten ohne Kennung stammen von Router, Proxy oder WLAN-Vorschaltseite – nicht von uns. */
const fromApp = (response) => response.headers.get('X-Packen') === '1';

async function networkFirstPage(request, url) {
    let response;
    try {
        response = await fetch(request);
    } catch {
        response = null;
    }

    // Weiterleitungen (z. B. zur Anmeldung) kommen bei Navigationen als „opaqueredirect“
    // ohne lesbare Header an – die gibt der Browser selbst weiter, sie sind kein Netzausfall.
    if (response && (response.type === 'opaqueredirect' || fromApp(response))) {
        // Nur echte Seiten der angemeldeten Person merken, keine Weiterleitungen zur Anmeldung.
        if (response.ok && !response.redirected && CACHEABLE_PAGE.test(url.pathname)) {
            (await caches.open(PAGES)).put(url.pathname, response.clone());
        }
        return response;
    }

    const cached = await caches.match(url.pathname, { cacheName: PAGES });
    return cached ?? (await caches.match('/offline.html')) ?? response ?? Response.error();
}

async function cacheFirst(request, cacheName) {
    const cached = await caches.match(request);
    if (cached) return cached;
    const response = await fetch(request);
    if (response.ok) (await caches.open(cacheName)).put(request, response.clone());
    return response;
}

async function staleWhileRevalidate(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);
    const network = fetch(request)
        .then((response) => {
            if (response.ok && !response.redirected && fromApp(response)) cache.put(request, response.clone());
            return response;
        })
        .catch(() => cached);
    return cached ?? network;
}
