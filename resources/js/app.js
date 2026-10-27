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

window.Alpine = Alpine;
Alpine.start();
