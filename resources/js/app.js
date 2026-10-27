import Alpine from 'alpinejs';
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
 */
Alpine.data('packlist', (packed = {}, view = 'groups') => ({
    packed,
    view,
    onlyOpen: storage.get('packen.onlyOpen', '0') === '1',
    init() {
        // Noch nicht übertragene Haken über den (evtl. gecachten) Seitenstand legen.
        for (const [id, change] of Object.entries(pendingChanges())) {
            if (id in this.packed) this.packed[id] = change.packed;
        }
        window.addEventListener('packlist:synced', ({ detail }) => {
            if (detail.id in this.packed && !(detail.id in pendingChanges())) this.packed[detail.id] = detail.packed;
        });
    },
    get total() {
        return Object.keys(this.packed).length;
    },
    get done() {
        return Object.values(this.packed).filter(Boolean).length;
    },
    get percent() {
        return this.total ? Math.round((this.done / this.total) * 100) : 0;
    },
    openIn(ids) {
        return ids.filter((id) => !this.packed[id]).length;
    },
    toggleOnlyOpen() {
        this.onlyOpen = !this.onlyOpen;
        storage.set('packen.onlyOpen', this.onlyOpen ? '1' : '0');
    },
    toggle(id, url) {
        const next = !this.packed[id];
        this.packed[id] = next;
        enqueue(id, url, next);
        flush();
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
