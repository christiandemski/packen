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

## Deployment (https://packen.allpixx.de)

**`main` ist live.** Ein Push auf `main` startet die GitHub Action [`deploy.yml`](.github/workflows/deploy.yml): Tests → Build (vendor ohne Dev-Pakete, Vite-Assets) → Branch `production`. Plesk zieht `production` per Webhook und führt [`deploy.sh`](deploy.sh) aus (Wartungsmodus, Migrationen, Caches).

Einmalig in Plesk (Netcup Webhosting, PHP 8.5):

1. **Git:** Branch auf `production` stellen. Unter „Weitere Bereitstellungsaktionen“: `bash deploy.sh`
2. **Hosting:** Dokumentenstamm `…/public` (ist gesetzt).
3. **Datenbank:** MariaDB-Datenbank und Benutzer anlegen.
4. **`.env`** im Deploy-Verzeichnis anlegen, z. B. im Plesk-Dateimanager (wird von Git nie überschrieben). Vorlage `.env.example`, abweichend:

   ```dotenv
   APP_ENV=production
   APP_DEBUG=false
   APP_KEY=                      # leer lassen – deploy.sh erzeugt ihn beim ersten Lauf
   APP_URL=https://packen.allpixx.de
   DB_HOST=localhost
   DB_DATABASE=…
   DB_USERNAME=…
   DB_PASSWORD=…
   SESSION_SECURE_COOKIE=true
   MAIL_MAILER=smtp
   MAIL_HOST=…                   # SMTP des Hostings
   MAIL_PORT=587
   MAIL_USERNAME=…
   MAIL_PASSWORD=…
   MAIL_FROM_ADDRESS=packen@allpixx.de

   # Erster Admin – wird beim nächsten Deployment einmalig angelegt
   PACKEN_ADMIN_EMAIL=…
   PACKEN_ADMIN_NAME=…
   PACKEN_ADMIN_PASSWORD=…       # mindestens 12 Zeichen
   ```

5. In Plesk unter **Git → „Jetzt bereitstellen“** klicken (oder auf `main` pushen). `deploy.sh` erzeugt den `APP_KEY`, migriert und legt den Admin an. Anmeldung danach unter `/admin-anmeldung`.
6. **`PACKEN_ADMIN_PASSWORD` wieder aus der `.env` löschen.** Ein bestehender Benutzer wird nie überschrieben; das Passwort ändern geht per SSH mit `php artisan packen:admin`.

Eine Queue oder einen Cronjob braucht die App derzeit nicht.

## Offline (PWA)

Reisen, die einmal mit Netz geöffnet wurden (laufende und kommende werden automatisch vorgeladen), lassen sich ohne Netz öffnen und abhaken. Details: Abschnitt 8 im [HANDOFF](HANDOFF.md).

Zum Testen lokal: Reise öffnen, `ddev stop`, Seite neu laden und abhaken, `ddev start` – spätestens nach 15 Sekunden ist die Änderung auf dem Server. Nach Änderungen an `public/sw.js` dort `VERSION` hochzählen.
