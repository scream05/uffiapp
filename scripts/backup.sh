#!/bin/bash
# Backup von Datenbank + Uploads/Config; behält die letzten 14 Sicherungen lokal, kopiert jede auf die Synology (scripts/nas-copy.sh).
set -euo pipefail
cd "$(dirname "$0")/.."
mkdir -p backups
docker compose run --rm backup
# Sicherungen enthalten alle Nutzerdaten: nur für smartcity lesbar (der Container schreibt sie als root)
docker compose run --rm --no-deps --entrypoint sh backup -c 'chown 1000:1000 /backup/humhub_backup_*.tar && chmod 600 /backup/humhub_backup_*.tar'
ls -1t backups/humhub_backup_*.tar 2>/dev/null | tail -n +15 | xargs -r rm -f
scripts/prune-logs.sh
echo "$(date '+%F %T') Backup ok"
# Kopie auf die Synology; schlägt sie fehl, bleibt die lokale Sicherung trotzdem gültig
scripts/nas-copy.sh || echo "$(date '+%F %T') NAS-Kopie FEHLGESCHLAGEN" >&2
