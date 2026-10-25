#!/bin/bash
# Täglich (Crontab): Ist die HumHub-App 1.2.86 im App Store (Deutschland)? Darin ist der Fix für die blaue Fläche
# nach einer Pause (humhub/app#362, unser Bericht in app-fehlermeldung/). Wenn ja, einmalig Hinweis-Mail an den Admin –
# dann Hinweis in der App-Anleitung entfernen/abschwächen und Neuigkeit „behoben“ ergänzen (README „App-Anleitung“).
cd "$(dirname "$0")/.." || exit 1
ZIEL=1.2.86
MERKER=rss/app-version-check.gemeldet
LOOKUP=$(curl -s -m 30 "https://itunes.apple.com/lookup?bundleId=com.humhub.app&country=de") || { echo "$(date '+%F %T') Abfrage fehlgeschlagen"; exit 1; }
VERSION=$(printf '%s' "$LOOKUP" | python3 -I -c 'import json,sys; r=json.load(sys.stdin).get("results") or [{}]; print(r[0].get("version",""))')
[ -n "$VERSION" ] || { echo "$(date '+%F %T') keine Version in der Antwort"; exit 1; }
echo "$(date '+%F %T') App Store: HumHub $VERSION"
if [ "$(printf '%s\n' "$ZIEL" "$VERSION" | sort -V | head -1)" = "$ZIEL" ] && [ ! -f "$MERKER" ]; then
  scripts/run-setup.sh admin-hinweis "Rund um Uffni: HumHub-App $VERSION im App Store" \
"Die HumHub-App $VERSION ist im App Store (Deutschland). Sie enthält den Fix für die blaue Fläche nach einer Pause
(https://github.com/humhub/app/issues/362).

Jetzt nachziehen:
  - Hinweis zur blauen Fläche in der App-Anleitung entfernen bzw. auf „App aktualisieren“ umstellen
  - Neuigkeit „behoben“ ergänzen (Eintrag app-blaue-flaeche behält seine id)
  - app-fehlermeldung/ als erledigt kennzeichnen" && echo "$VERSION" > "$MERKER"
fi
