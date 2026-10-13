#!/bin/bash
# Entfernt Einträge älter als 30 Tage aus /data/logs (Zusage in der Datenschutzerklärung).
# Die Log-Tabelle in der Datenbank räumt HumHub selbst nach 7 Tagen auf.
set -euo pipefail
cd "$(dirname "$0")/.."
docker compose exec -T -u www-data humhub sh -c '
  cd /data/logs
  find . -type f -name "*.log.*" -mtime +30 -delete
  cutoff=$(date -d "30 days ago" "+%F %T")
  for f in *.log; do
    [ -f "$f" ] || continue
    awk -v c="$cutoff" "/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9:]{8} /{keep = (substr(\$0, 1, 19) >= c)} keep" "$f" > "/tmp/$f.pruned"
    cat "/tmp/$f.pruned" > "$f" && rm -f "/tmp/$f.pruned"
  done
'

# Protokolle der Cronjobs auf dem Host (keine personenbezogenen Daten) auf die letzten 2000 Zeilen kürzen
for f in rss/*.log abfall/*.log backups/backup.log; do
  [ -f "$f" ] || continue
  tail -n 2000 "$f" > "$f.tmp" && cat "$f.tmp" > "$f" && rm -f "$f.tmp"
done
