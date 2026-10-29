import Alpine from 'alpinejs';
import Sortable from 'sortablejs';
import { enqueue, flush, initOffline, pendingChanges } from './offline';

/** localStorage kann in privaten Fenstern fehlen oder werfen – dann einfach ohne Merken. */
const storage = {
    get(key, fallback) {
        try {
            return window.localStorage.getItem(key) ?? fallback;
        } catch {
            return fallback;
        }
    },
    set(key, value) {
        try {
            window.localStorage.setItem(key, value);
        } catch {
            // ignorieren
        }
    },
};

Alpine.data('tripOverview', () => ({
    view: storage.get('packen.tripView', 'tiles'),
    setView(view) {
        this.view = view;
        storage.set('packen.tripView', view);
    },
}));

Alpine.data('coverPicker', (initialUrl = null) => ({
    preview: initialUrl,
    removed: false,
    pick(event) {
        const [file] = event.target.files;
        if (!file) return;
        if (this.preview?.startsWith('blob:')) URL.revokeObjectURL(this.preview);
        this.preview = URL.createObjectURL(file);
        this.removed = false;
    },
    clear() {
        this.$refs.file.value = '';
        this.preview = null;
        this.removed = true;
    },
}));

/**
 * Benutzerbild zuschneiden wie in „unterwegs“: Das Foto liegt hinter einem runden Fenster,
 * ziehen verschiebt, Regler / Mausrad / zwei Finger vergrößern, Pfeiltasten verschieben.
 * Es füllt das Fenster immer ganz; gespeichert wird ein Quadrat mit 256 px.
 * x, y: linke obere Ecke des Fotos im Fenster; base: Maßstab, bei dem die kurze Seite genau passt.
 */
Alpine.data('avatarCropper', () => ({
    on: false,
    size: 220,
    w: 0,
    h: 0,
    base: 1,
    z: 1,
    x: 0,
    y: 0,
    url: '',
    error: null,
    pointers: new Map(),
    pinch: 0,

    pick(event) {
        const [file] = event.target.files;
        event.target.value = '';
        if (!file) return;
        const img = this.$refs.img;
        const url = URL.createObjectURL(file);
        img.onload = () => {
            if (this.url) URL.revokeObjectURL(this.url);
            Object.assign(this, { on: true, url, w: img.naturalWidth, h: img.naturalHeight, z: 1, error: null });
            this.base = this.size / Math.min(this.w, this.h);
            this.x = (this.size - this.w * this.base) / 2;
            this.y = (this.size - this.h * this.base) / 2;
            this.draw();
            this.$nextTick(() => this.$refs.stage.focus());
        };
        img.onerror = () => {
            URL.revokeObjectURL(url);
            this.error = 'Das Bild konnte nicht gelesen werden.';
        };
        img.src = url;
    },
    draw() {
        const s = this.base * this.z;
        this.x = Math.min(0, Math.max(this.size - this.w * s, this.x));
        this.y = Math.min(0, Math.max(this.size - this.h * s, this.y));
        this.$refs.img.style.transform = `translate(${this.x}px, ${this.y}px) scale(${s})`;
    },
    /** Vergrößern um die Mitte des Fensters */
    zoom(z) {
        const s0 = this.base * this.z;
        const c = this.size / 2;
        this.z = Math.min(4, Math.max(1, z));
        const k = (this.base * this.z) / s0;
        this.x = c - (c - this.x) * k;
        this.y = c - (c - this.y) * k;
        this.draw();
    },
    down(e) {
        this.$refs.stage.setPointerCapture(e.pointerId);
        this.pointers.set(e.pointerId, [e.clientX, e.clientY]);
        this.pinch = 0;
    },
    move(e) {
        const p = this.pointers.get(e.pointerId);
        if (!p) return;
        if (this.pointers.size === 1) {
            this.x += e.clientX - p[0];
            this.y += e.clientY - p[1];
            this.draw();
        }
        this.pointers.set(e.pointerId, [e.clientX, e.clientY]);
        if (this.pointers.size === 2) {
            const [a, b] = [...this.pointers.values()];
            const d = Math.hypot(a[0] - b[0], a[1] - b[1]);
            if (this.pinch) this.zoom((this.z * d) / this.pinch);
            this.pinch = d;
        }
    },
    up(e) {
        this.pointers.delete(e.pointerId);
        this.pinch = 0;
    },
    wheel(e) {
        this.zoom(this.z * (e.deltaY < 0 ? 1.1 : 1 / 1.1));
    },
    key(e) {
        const d = { ArrowLeft: [16, 0], ArrowRight: [-16, 0], ArrowUp: [0, 16], ArrowDown: [0, -16] }[e.key];
        if (!d) return;
        e.preventDefault();
        this.x += d[0];
        this.y += d[1];
        this.draw();
    },
    cancel() {
        this.on = false;
    },
    /** Vor dem Absenden den Ausschnitt als JPEG ins versteckte Feld schreiben. */
    submit(event) {
        if (!this.on) return;
        const s = this.base * this.z;
        const canvas = document.createElement('canvas');
        canvas.width = canvas.height = 256;
        canvas.getContext('2d').drawImage(this.$refs.img, -this.x / s, -this.y / s, this.size / s, this.size / s, 0, 0, 256, 256);
        this.$refs.data.value = canvas.toDataURL('image/jpeg', 0.85);
        event.target.submit();
    },
}));

/**
 * Packliste: Abhaken sofort im Browser, gespeichert wird über die Warteschlange –
 * online sofort, offline sobald wieder Netz da ist.
 *
 * Rückweg: zweiter Haken je Eintrag („wieder eingepackt“), in der Warteschlange als „r<id>“.
 */
Alpine.data('packlist', (packed = {}, view = 'groups', back = {}, direction = 'there') => ({
    packed,
    back,
    direction,
    view,
    onlyOpen: storage.get('packen.onlyOpen', '0') === '1',
    init() {
        // Noch nicht übertragene Haken über den (evtl. gecachten) Seitenstand legen.
        for (const [key, change] of Object.entries(pendingChanges())) {
            const [state, id] = this.stateFor(key);
            if (id in state) state[id] = change.packed;
        }
        window.addEventListener('packlist:synced', ({ detail }) => {
            const [state, id] = this.stateFor(detail.key);
            if (id in state && !(detail.key in pendingChanges())) state[id] = detail.packed;
        });
    },
    /** Schlüssel der Warteschlange → [Zustand, Eintrag-ID] */
    stateFor(key) {
        return String(key).startsWith('r') ? [this.back, String(key).slice(1)] : [this.packed, String(key)];
    },
    /** Haken der gerade gezeigten Richtung */
    get current() {
        return this.direction === 'back' ? this.back : this.packed;
    },
    get total() {
        return Object.keys(this.current).length;
    },
    get done() {
        return Object.values(this.current).filter(Boolean).length;
    },
    get percent() {
        return this.total ? Math.round((this.done / this.total) * 100) : 0;
    },
    openIn(ids) {
        return ids.filter((id) => id in this.current && !this.current[id]).length;
    },
    toggleOnlyOpen() {
        this.onlyOpen = !this.onlyOpen;
        storage.set('packen.onlyOpen', this.onlyOpen ? '1' : '0');
    },
    toggle(id, url, backUrl) {
        if (this.removed[id]) return;
        const back = this.direction === 'back';
        const next = !this.current[id];
        this.current[id] = next;
        enqueue(back ? `r${id}` : id, back ? backUrl : url, next);
        flush();
    },
    // „Liste anpassen“: Einträge für diese Reise weglassen, ohne die Vorlage zu ändern.
    editing: false,
    removed: {},
    removeError: '',
    present(ids) {
        return ids.filter((id) => id in this.current);
    },
    async remove(id, url) {
        this.removeError = '';
        const before = this.packed[id];
        this.removed[id] = { restorable: false, pending: true };
        const beforeBack = this.back[id];
        delete this.packed[id];
        delete this.back[id];
        try {
            const response = await sendJson(url, 'DELETE');
            if (!response.ok) throw new Error();
            const data = await response.json();
            this.removed[id] = { restorable: data.restorable, pending: false };
        } catch {
            delete this.removed[id];
            this.packed[id] = before;
            if (beforeBack !== undefined) this.back[id] = beforeBack;
            this.removeError = 'Entfernen hat nicht geklappt – bitte noch einmal.';
        }
    },
    /** Ganze Karte weglassen, Eintrag für Eintrag (so bleibt jeder einzeln zurückholbar). */
    async removeAll(rows) {
        for (const { id, url } of rows) {
            if (id in this.packed) await this.remove(id, url);
        }
    },
    async restore(id, url) {
        try {
            const response = await sendJson(url, 'POST');
            if (!response.ok) throw new Error();
            delete this.removed[id];
            this.packed[id] = false;
        } catch {
            this.removeError = 'Zurückholen hat nicht geklappt – bitte noch einmal.';
        }
    },
}));

let csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

/** JSON an die App schicken; ein abgelaufener CSRF-Token wird einmal erneuert. */
async function sendJson(url, method, body = {}) {
    const send = () =>
        fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify(body),
        });

    let response = await send();
    if (response.status === 419) {
        const fresh = await fetch('/csrf-token', { headers: { Accept: 'application/json' }, cache: 'no-store' });
        if (fresh.ok) {
            csrf = (await fresh.json()).token;
            response = await send();
        }
    }
    return response;
}

const errorText = (data) => Object.values(data?.errors ?? {})[0]?.[0] ?? data?.message ?? 'Das hat nicht geklappt.';

/**
 * Schnelleingabe (Katalog, Vorlagen): Enter legt den Gegenstand in dieser Kategorie an,
 * das Feld bleibt fokussiert (Tastatur bleibt offen) für den nächsten. Eine eingefügte
 * Liste mit mehreren Zeilen wird Zeile für Zeile angelegt.
 */
Alpine.data('quickAdd', (url, category, fields = { name: 'name', category: 'category' }) => ({
    category,
    name: '',
    added: [],
    error: '',
    busy: false,
    queue: [],
    add() {
        const name = this.name.trim();
        if (!name) return;
        this.name = '';
        this.enqueue([name]);
    },
    paste(event) {
        const text = event.clipboardData?.getData('text') ?? '';
        // Aufzählungszeichen, Nummern und Checkboxen aus Notizen weglassen
        const lines = text
            .split(/\r?\n/)
            .map((line) => line.replace(/^\s*(?:[-*•–·]|\d+[.)]|\[[ xX]?\])\s*/, '').trim())
            .filter(Boolean);
        if (lines.length < 2) return;
        event.preventDefault();
        this.enqueue(lines);
    },
    async enqueue(names) {
        this.queue.push(...names);
        if (this.busy) return;
        this.busy = true;
        this.error = '';
        const failed = [];
        while (this.queue.length) {
            const name = this.queue.shift();
            try {
                const response = await sendJson(url, 'POST', { [fields.name]: name, [fields.category]: category });
                const data = await response.json().catch(() => ({}));
                if (response.ok) {
                    this.added.push(data);
                    this.$dispatch('quick-added', data);
                } else {
                    failed.push(`„${name}“: ${errorText(data)}`);
                }
            } catch {
                failed.push(`„${name}“: Keine Verbindung – bitte gleich noch einmal.`);
            }
        }
        this.error = failed.join(' ');
        this.busy = false;
        this.$refs.input.focus();
    },
}));

/**
 * Reihenfolge der Kategorien: ziehen (Griff) oder Pfeile; jede Änderung wird gleich gespeichert.
 */
Alpine.data('categoryOrder', (url) => ({
    saved: false,
    error: '',
    init() {
        // forceFallback: Zeiger-Ereignisse statt HTML5-Drag&Drop – verhält sich auf Handy und Desktop gleich
        Sortable.create(this.$refs.list, { handle: '[data-handle]', animation: 150, forceFallback: true, onEnd: () => this.save() });
    },
    move(event, step) {
        const item = event.currentTarget.closest('li');
        const sibling = step < 0 ? item.previousElementSibling : item.nextElementSibling;
        if (!sibling) return;
        step < 0 ? sibling.before(item) : sibling.after(item);
        event.currentTarget.focus();
        this.save();
    },
    async save() {
        this.error = '';
        const order = [...this.$refs.list.children].map((li) => li.dataset.key);
        try {
            const response = await sendJson(url, 'PUT', { order });
            if (!response.ok) throw new Error();
            this.saved = true;
            clearTimeout(this.timer);
            this.timer = setTimeout(() => (this.saved = false), 1500);
        } catch {
            this.error = 'Speichern hat nicht geklappt – bitte Seite neu laden.';
        }
    },
}));

/** Popup mit Inhalt, der beim ersten Öffnen nachgeladen wird (z. B. Benachrichtigungen). */
Alpine.data('popover', (url) => ({
    open: false,
    loaded: false,
    html: '<p class="px-4 py-8 text-center text-sm font-semibold text-muted">Wird geladen …</p>',
    async toggle() {
        this.open = !this.open;
        if (!this.open || this.loaded) return;
        try {
            const response = await fetch(url, { headers: { Accept: 'text/html' }, cache: 'no-store' });
            if (!response.ok) throw new Error();
            this.html = await response.text();
            this.loaded = true;
        } catch {
            this.html = '<p class="px-4 py-8 text-center text-sm font-semibold text-muted">Gerade nicht erreichbar – bitte später noch einmal.</p>';
        }
    },
    close() {
        this.open = false;
    },
}));

/** Erinnerung am Abfahrtstag per Push für dieses Gerät ein- und ausschalten. */
Alpine.data('pushToggle', (publicKey, url, testUrl) => ({
    supported: 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window,
    active: false,
    busy: false,
    message: '',
    async init() {
        if (!this.supported) return;
        const registration = await navigator.serviceWorker.ready;
        this.active = !!(await registration.pushManager.getSubscription());
    },
    async toggle() {
        this.busy = true;
        this.message = '';
        try {
            const registration = await navigator.serviceWorker.ready;
            const current = await registration.pushManager.getSubscription();
            if (this.active && current) {
                await sendJson(url, 'DELETE', { endpoint: current.endpoint });
                await current.unsubscribe();
                this.active = false;
                return;
            }
            if ((await Notification.requestPermission()) !== 'granted') {
                this.message = 'Benachrichtigungen sind für Packen blockiert – in den Browser-Einstellungen erlauben.';
                return;
            }
            const key = Uint8Array.from(atob(publicKey.replace(/-/g, '+').replace(/_/g, '/')), (c) => c.charCodeAt(0));
            const subscription = current ?? (await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: key }));
            const response = await sendJson(url, 'POST', { endpoint: subscription.endpoint });
            if (!response.ok) throw new Error();
            this.active = true;
            this.message = 'Eingeschaltet. Mit „Probe-Nachricht“ kannst du es gleich ausprobieren.';
        } catch {
            this.message = 'Das hat nicht geklappt – bitte noch einmal versuchen.';
        } finally {
            this.busy = false;
        }
    },
    async test() {
        this.busy = true;
        try {
            const response = await sendJson(testUrl, 'POST');
            const data = await response.json();
            this.message = data.sent ? 'Probe-Nachricht ist unterwegs.' : 'Der Push-Dienst hat nicht geantwortet – bitte aus- und wieder einschalten.';
        } catch {
            this.message = 'Das hat nicht geklappt – bitte noch einmal versuchen.';
        } finally {
            this.busy = false;
        }
    },
}));

/** Formulare mit data-keep-scroll laden die Seite neu, ohne dass man nach oben springt. */
document.addEventListener('submit', (event) => {
    if (!event.target.matches('form[data-keep-scroll]')) return;
    try {
        sessionStorage.setItem('packen.scroll', JSON.stringify({ path: location.pathname, y: window.scrollY }));
    } catch {
        // ignorieren
    }
});

try {
    const saved = JSON.parse(sessionStorage.getItem('packen.scroll') ?? 'null');
    sessionStorage.removeItem('packen.scroll');
    if (saved?.path === location.pathname) {
        window.scrollTo(0, saved.y);
    }
} catch {
    // ignorieren
}

// Gemeinsames Auswahlblatt (z. B. Kleidung für einen Outfit-Slot): Ziel-URL und Beschriftung
Alpine.store('picker', { url: '', label: '' });

initOffline({ authenticated: document.body.dataset.auth === '1' });

window.Alpine = Alpine;
Alpine.start();
