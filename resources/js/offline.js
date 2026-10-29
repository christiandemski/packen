/*
 * Offline-Unterstützung: Service Worker, Warteschlange fürs Abhaken, Status-Anzeige.
 *
 * Abhaken landet immer zuerst in der Warteschlange (localStorage) und wird dann übertragen.
 * Pro Eintrag zählt nur die letzte Änderung; mitgeschickt wird der Zeitpunkt auf dem Gerät,
 * der Server entscheidet „letzter Stand gewinnt“.
 */
import Alpine from 'alpinejs';

const QUEUE_KEY = 'packen.queue';
const PAGE_CACHE = 'packen-pages';

function readQueue() {
    try {
        return JSON.parse(window.localStorage.getItem(QUEUE_KEY) ?? '{}') ?? {};
    } catch {
        return {};
    }
}

function writeQueue(queue) {
    try {
        window.localStorage.setItem(QUEUE_KEY, JSON.stringify(queue));
    } catch {
        // Ohne Speicher geht es eben nur online.
    }
    Alpine.store('sync').pending = Object.keys(queue).length;
}

export function pendingChanges() {
    return readQueue();
}

export function enqueue(id, url, packed) {
    const queue = readQueue();
    queue[id] = { url, packed, changedAt: new Date().toISOString() };
    writeQueue(queue);
}

/** Entfernt einen Eintrag nur, wenn er inzwischen nicht erneut geändert wurde. */
function settle(id, changedAt) {
    const queue = readQueue();
    if (!changedAt || queue[id]?.changedAt === changedAt) {
        delete queue[id];
        writeQueue(queue);
    }
}

let token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

async function freshToken() {
    const response = await fetch('/csrf-token', { headers: { Accept: 'application/json' }, cache: 'no-store' });
    if (!response.ok) throw new Error('kein Token');
    token = (await response.json()).token;
    return token;
}

function send(change) {
    return fetch(change.url, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
        body: JSON.stringify({ packed: change.packed, changed_at: change.changedAt }),
    });
}

/** Antworten ohne Kennung stammen nicht von der App (siehe MarkAppResponse). */
const fromApp = (response) => response.headers.get('X-Packen') === '1';

let flushing = false;

export async function flush() {
    if (flushing || !navigator.onLine) return;
    flushing = true;
    let synced = false;

    try {
        for (const [id, change] of Object.entries(readQueue())) {
            let response = await send(change);
            if (!fromApp(response)) break; // Router, Proxy oder WLAN-Vorschaltseite: später erneut
            if (response.status === 419) {
                await freshToken(); // Seite kam aus dem Cache, Token ist veraltet
                response = await send(change);
            }

            if (response.ok) {
                const data = await response.json();
                settle(id, change.changedAt);
                window.dispatchEvent(new CustomEvent('packlist:synced', { detail: { key: id, packed: data.packed } }));
                synced = true;
            } else if (response.status >= 400 && response.status < 500) {
                // Eintrag gibt es nicht mehr oder gehört nicht (mehr) dieser Person: verwerfen.
                settle(id);
            } else {
                break; // Serverfehler: später erneut
            }
        }
    } catch {
        // Netz weg – bleibt in der Warteschlange.
    } finally {
        flushing = false;
    }

    if (synced) refreshCachedPage(location.pathname);
}

/** Gecachte Fassung einer Seite auffrischen, damit sie offline den aktuellen Stand zeigt. */
let refreshTimer = null;
function refreshCachedPage(path) {
    if (!('caches' in window) || !/^\/(reisen\/(?!neu$)[a-z0-9-]+)?$/.test(path)) return;
    clearTimeout(refreshTimer);
    refreshTimer = setTimeout(async () => {
        try {
            const response = await fetch(path, { credentials: 'same-origin', headers: { Accept: 'text/html' } });
            if (response.ok && !response.redirected && fromApp(response)) await (await caches.open(PAGE_CACHE)).put(path, response);
        } catch {
            // egal
        }
    }, 1500);
}

/** Kommende Reisen vorsorglich speichern, damit sie auch ungeöffnet offline verfügbar sind. */
function prefetchTrips() {
    const el = document.querySelector('[data-offline-prefetch]');
    if (!el || !('caches' in window) || !navigator.onLine) return;
    const paths = JSON.parse(el.dataset.offlinePrefetch);
    setTimeout(async () => {
        const cache = await caches.open(PAGE_CACHE);
        for (const path of paths) {
            try {
                const response = await fetch(path, { credentials: 'same-origin', headers: { Accept: 'text/html' } });
                if (response.ok && !response.redirected && fromApp(response)) await cache.put(path, response);
            } catch {
                return;
            }
        }
    }, 2000);
}

/** Nach dem Abmelden darf nichts Persönliches im Cache bleiben. */
export async function clearPersonalCaches() {
    if (!('caches' in window)) return;
    await Promise.all(['packen-pages', 'packen-images'].map((name) => caches.delete(name)));
}

export function initOffline({ authenticated }) {
    Alpine.store('sync', {
        online: navigator.onLine,
        pending: Object.keys(readQueue()).length,
        notice: null,
        noticeTimer: null,
        notify(text) {
            this.notice = text;
            clearTimeout(this.noticeTimer);
            this.noticeTimer = setTimeout(() => (this.notice = null), 4000);
        },
    });

    if (!authenticated) {
        clearPersonalCaches();
        return;
    }

    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    }

    window.addEventListener('online', () => {
        Alpine.store('sync').online = true;
        flush();
    });
    window.addEventListener('offline', () => (Alpine.store('sync').online = false));

    // Formulare (Hinzufügen, Module, Mengen …) brauchen den Server.
    document.addEventListener('submit', (event) => {
        if (navigator.onLine) {
            if (event.target.matches('form[action$="/abmelden"]')) clearPersonalCaches();
            return;
        }
        event.preventDefault();
        event.stopImmediatePropagation();
        Alpine.store('sync').notify('Du bist offline – das geht erst wieder mit Verbindung. Abhaken funktioniert weiter.');
    }, true);

    // Funklöcher melden sich oft nicht als „offline“: solange etwas aussteht, regelmäßig erneut versuchen.
    setInterval(() => {
        if (Alpine.store('sync').pending > 0) flush();
    }, 15000);

    flush();
    prefetchTrips();
}
