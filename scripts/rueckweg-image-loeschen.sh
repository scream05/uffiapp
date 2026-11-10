#!/bin/bash
# Einmalig (Crontab 14.11.2026): Rückweg-Image von HumHub 1.19.3 löschen, wenn 1.19.4 gesund läuft.
# Entfernt danach seinen eigenen Crontab-Eintrag.
cd "$(dirname "$0")/.."
LOG=rss/rueckweg-image.log
IMG=humhub/humhub:1.19.3-rueckweg
v=$(docker compose exec -T humhub sh -c 'grep -o "1\.19\.[0-9]*" /opt/humhub/protected/humhub/config/common.php | head -1' 2>/dev/null)
h=$(docker inspect -f '{{.State.Health.Status}}' humhub-setup-humhub-1 2>/dev/null)
if [ "$h" = healthy ] && [ -n "$v" ] && [ "$v" != 1.19.3 ]; then
  docker image rm "$IMG" >> "$LOG" 2>&1 && echo "$(date '+%F %T') $IMG gelöscht (läuft: $v)" >> "$LOG"
else
  echo "$(date '+%F %T') NICHT gelöscht: Zustand=$h Version=$v" >> "$LOG"
fi
crontab -l | grep -v -e 'rueckweg-image-loeschen.sh' -e 'Rückweg-Image HumHub 1.19.3' | crontab -
