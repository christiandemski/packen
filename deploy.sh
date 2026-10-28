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

"$PHP_BIN" artisan down --retry=15 || true
trap '"$PHP_BIN" artisan up' EXIT   # auch wenn eine Migration scheitert, nicht im Wartungsmodus hängen bleiben

"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan optimize

# Ersten Admin aus PACKEN_ADMIN_* anlegen (tut nichts, wenn nicht gesetzt oder schon vorhanden).
"$PHP_BIN" artisan packen:admin --from-env

echo "Deployment fertig: $(git log -1 --pretty='%h %s' 2>/dev/null || echo 'unbekannter Stand')"
