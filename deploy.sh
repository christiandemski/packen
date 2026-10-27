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

# Laravel braucht diese Verzeichnisse beschreibbar; Git legt leere Ordner nicht an.
mkdir -p storage/framework/{cache/data,sessions,views} storage/logs storage/app/private bootstrap/cache

"$PHP_BIN" artisan down --retry=15 || true
trap '"$PHP_BIN" artisan up' EXIT   # auch wenn eine Migration scheitert, nicht im Wartungsmodus hängen bleiben

"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan optimize

echo "Deployment fertig: $(git log -1 --pretty='%h %s' 2>/dev/null || echo 'unbekannter Stand')"
