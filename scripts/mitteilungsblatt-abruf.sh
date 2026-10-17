#!/bin/bash
# Mitteilungsblatt abrufen (Crontab :50, 6–22 Uhr); bei neuer Ausgabe gleich das Community-Wissen für Uffi
# neu aufbauen (stadtwissen.py --nur-community), statt bis zum nächtlichen Lauf um 3:30 zu warten.
cd "$(dirname "$0")/.." || exit 1
AUSGABE=$(docker compose exec -T -u www-data humhub /app/yii mitteilungsblatt/abrufen 2>&1)
echo "$AUSGABE"
if grep -qE "Mitteilungsblatt: [1-9][0-9]* neu" <<<"$AUSGABE"; then
  # Nicht doppelt laufen lassen (dauert mit Embeddings über 10 Minuten)
  flock -n /tmp/stadtwissen-community.lock scripts/stadtwissen.py --nur-community >> rss/stadtwissen.log 2>&1 &
  echo "$(date '+%Y-%m-%d %H:%M') neue Ausgabe → stadtwissen.py --nur-community gestartet"
fi
