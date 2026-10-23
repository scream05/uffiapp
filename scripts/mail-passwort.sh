#!/bin/bash
# Neues Mail-Passwort (Google-App-Passwort für smartcity@…) übernehmen, ohne Admin-Anmeldung:
#   1. Passwort als einzige Zeile in secrets/mail_app_passwort speichern (Leerzeichen egal)
#   2. scripts/mail-passwort.sh
# Setzt es für Versand und Mail-Eingang, löscht die Datei danach und prüft den Mail-Eingang.
set -euo pipefail
cd "$(dirname "$0")/.."
F=secrets/mail_app_passwort
[ -s "$F" ] || { echo "Datei $F fehlt oder ist leer"; exit 1; }
C=$(docker compose ps -q humhub)
docker cp "$F" "$C":/tmp/mailpw >/dev/null
docker compose exec -T humhub sh -c 'chown www-data:www-data /tmp/mailpw && chmod 600 /tmp/mailpw'
# setup/mail-passwort läuft als www-data und löscht /tmp/mailpw selbst; die Datei hier erst nach Erfolg löschen
if scripts/run-setup.sh mail-passwort; then
  shred -u "$F" 2>/dev/null || rm -f "$F"
else
  docker compose exec -T humhub rm -f /tmp/mailpw
  echo "Nicht übernommen – $F bleibt liegen"; exit 1
fi
echo "Mail-Eingang (Probelauf):"
docker compose exec -T -u www-data humhub /app/yii bauhof/mail-abrufen 1 2>&1 | tail -2
