#!/bin/bash
# Täglich (Crontab): neues HumHub-Image holen und prüfen, ob PHP ≥ 8.4.27 drin ist (CVE-2026-109618/109619).
# Wenn ja, einmalig Hinweis-Mail an den Admin – Umstellen (pull/up, memory_limit-Zeile entfernen) bleibt Handarbeit,
# siehe README „PHP-Sicherheit“. Der laufende Container wird hier nicht angefasst.
cd "$(dirname "$0")/.." || exit 1
ZIEL=8.4.27
MERKER=rss/php-check.gemeldet
docker pull -q humhub/humhub:1.18 >/dev/null || { echo "$(date '+%F %T') pull fehlgeschlagen"; exit 1; }
NEU=$(docker run --rm --entrypoint php humhub/humhub:1.18 -r 'echo PHP_VERSION;')
LAEUFT=$(docker compose exec -T humhub php -r 'echo PHP_VERSION;')
echo "$(date '+%F %T') Image: PHP $NEU, läuft: PHP $LAEUFT"
if [ "$(printf '%s\n' "$ZIEL" "$NEU" | sort -V | head -1)" = "$ZIEL" ] && [ "$LAEUFT" != "$NEU" ] && [ ! -f "$MERKER" ]; then
  scripts/run-setup.sh admin-hinweis "Rund um Uffni: PHP $NEU verfügbar" \
"Das Image humhub/humhub:1.18 enthält jetzt PHP $NEU (läuft: $LAEUFT) – die Lücken CVE-2026-109618/109619 sind damit behoben.

Umstellen (README „PHP-Sicherheit“):
  cd ~/humhub-setup && docker compose up -d humhub
  in design/php-sicherheit.ini die Zeile memory_limit = -1 entfernen, Container neu starten
  docker compose exec humhub php -v" && echo "$NEU" > "$MERKER"
fi
