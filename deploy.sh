#!/bin/bash
# Wird von Plesk nach jedem Pull des Branches „production“ ausgeführt
# (Git → Weitere Bereitstellungsaktionen: bash deploy.sh).
set -euo pipefail
cd "$(dirname "$0")"

# Plesk legt PHP-Versionen unter /opt/plesk/php/<version>/bin/php ab; „php“ im PATH ist oft eine andere Version.
PHP_BIN="${PHP_BIN:-/opt/plesk/php/8.5/bin/php}"
[ -x "$PHP_BIN" ] || PHP_BIN="php"

if [ ! -f .env ]; then
    echo "Keine .env gefunden – bitte einmalig anlegen (siehe README, Abschnitt Deployment)." >&2
    exit 1
fi

# Erster Lauf ohne SSH: App-Schlüssel selbst erzeugen, falls APP_KEY in der .env fehlt oder leer ist.
if ! grep -qE '^APP_KEY=.+' .env; then
    grep -q '^APP_KEY=' .env || echo 'APP_KEY=' >> .env
    "$PHP_BIN" artisan key:generate --force
fi

# Laravel braucht diese Verzeichnisse beschreibbar; Git legt leere Ordner nicht an.
mkdir -p storage/framework/{cache/data,sessions,views} storage/logs storage/app/private bootstrap/cache

# Doppelte Schlüssel in der .env sind eine häufige Fehlerquelle (es gilt die letzte Zeile).
dupes=$(grep -oE '^[A-Z_]+=' .env | sort | uniq -d | tr -d '=' | tr '\n' ' ')
[ -z "$dupes" ] || echo "WARNUNG: Doppelt in der .env: $dupes" >&2
echo "Datenbank laut .env: $(grep -E '^DB_(CONNECTION|HOST|DATABASE)=' .env | tr '\n' ' ')" >&2

# Zwischengespeicherte Konfiguration verwerfen, sonst greifen Änderungen an der .env nicht
# (nur Dateien – „optimize:clear“ bräuchte für den Cache schon die Datenbank).
"$PHP_BIN" artisan config:clear

"$PHP_BIN" artisan down --retry=15 || true
trap '"$PHP_BIN" artisan up' EXIT   # auch wenn eine Migration scheitert, nicht im Wartungsmodus hängen bleiben

"$PHP_BIN" artisan migrate --force
# Bewusst kein „artisan optimize“: Plesk führt dieses Skript chrooted aus (/packen.allpixx.de/…),
# der Webserver sieht aber /var/www/vhosts/…. Zwischengespeicherte Konfiguration enthielte die
# Chroot-Pfade für Log und Views – die Seite antwortet dann nur noch mit leerer 500.
"$PHP_BIN" artisan config:clear
"$PHP_BIN" artisan view:clear
# Auch Routen- und Event-Cache verwerfen: Ein früheres „optimize“ hat sie angelegt, und ein
# veralteter Routen-Cache kennt neue Routen nicht (→ 500 „Route [...] not defined“).
"$PHP_BIN" artisan route:clear
"$PHP_BIN" artisan event:clear

# Ersten Admin aus PACKEN_ADMIN_* anlegen (tut nichts, wenn nicht gesetzt oder schon vorhanden).
"$PHP_BIN" artisan packen:admin --from-env

echo "Deployment fertig: $(git log -1 --pretty='%h %s' 2>/dev/null || echo 'unbekannter Stand')"
