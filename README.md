# Packen

Packliste mit Outfit-Planer für Reisen allein oder mit Freunden. Konzept und Meilensteine: [HANDOFF.md](HANDOFF.md).

Laravel 13 · Blade + Alpine.js · Tailwind 4 · MariaDB · Pest

## Lokale Entwicklung (DDEV)

```bash
ddev start
ddev composer install
ddev npm ci
ddev artisan migrate:fresh --seed
ddev npm run dev        # oder: ddev npm run build
```

- App: https://packen.ddev.site
- Mails (Magic Links, Einladungen): `ddev launch -m` (Mailpit)
- Admin aus dem Seeder: `admin@packen.test` / `packen-admin` über `/admin-anmeldung`
- Freunde aus dem Seeder (`anna@packen.test`, `max@packen.test`) melden sich per Link an

`node_modules` immer im Container installieren (`ddev npm ci`), sonst fehlen die Linux-Binaries für Vite.

## Tests

```bash
ddev exec php vendor/bin/pest
```

Die Tests laufen gegen SQLite im Speicher.

## Admin auf dem Server anlegen

```bash
php artisan packen:admin
```

Legt einen lokalen Admin an oder setzt dessen Passwort (Übergang, bis BearID angebunden ist).
