import Alpine from 'alpinejs';

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

window.Alpine = Alpine;
Alpine.start();
