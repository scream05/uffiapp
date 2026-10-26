#!/bin/bash
# Führt scripts/SetupController.php im HumHub-Container aus, z. B.:
#   scripts/run-setup.sh spaces
set -euo pipefail
cd "$(dirname "$0")/.."
# Abhängigkeiten des KI-Assistenten (Claude-SDK) nach modules/ki-assistent/vendor (nicht im Git)
if [ "${1:-}" = ki-composer ]; then
  exec docker run --rm -u "$(id -u):$(id -g)" -v "$PWD/modules/ki-assistent:/app" -e COMPOSER_HOME=/tmp composer:2 \
    composer install --no-interaction --no-dev --optimize-autoloader
fi
# Abhängigkeiten des Gewerbeverzeichnisses (QR-Codes der Gutscheine, bacon/bacon-qr-code) nach modules/gewerbe/vendor (nicht im Git)
if [ "${1:-}" = gewerbe-composer ]; then
  exec docker run --rm -u "$(id -u):$(id -g)" -v "$PWD/modules/gewerbe:/app" -e COMPOSER_HOME=/tmp composer:2 \
    composer install --no-interaction --no-dev --optimize-autoloader
fi
# PDF-Reader des Mitteilungsblatts (pdf.js, Legacy-Build für ältere Handys) nach modules/mitteilungsblatt/pdfjs (nicht im Git)
if [ "${1:-}" = pdfjs ]; then
  V=6.4.299
  T=$(mktemp -d)
  curl -fsSL -o "$T/p.zip" "https://github.com/mozilla/pdf.js/releases/download/v$V/pdfjs-$V-legacy-dist.zip"
  unzip -q "$T/p.zip" -d "$T/x"
  rm -f "$T"/x/web/*.pdf "$T"/x/web/debugger.* "$T"/x/web/*.map "$T"/x/build/*.map
  rm -rf modules/mitteilungsblatt/pdfjs
  mv "$T/x" modules/mitteilungsblatt/pdfjs
  rm -rf "$T"
  echo "pdf.js $V in modules/mitteilungsblatt/pdfjs"
  exit 0
fi
# App-/Browser-Icon (design/icon: rund mit transparenten Ecken für Browser/Android, eckig für Apple) nach uploads/icon.
# HumHub (ab 1.19) legt jede Größe als icon_<xxh32 der Optionen>.png neben icon.png ab und rechnet sie nur aus, wenn die
# Datei fehlt – unsere fertigen PNGs kommen deshalb unter genau diesen Namen dorthin (152/167/180 eckig, Rest rund)
if [ "${1:-}" = icon ]; then
  docker compose exec -T -u www-data humhub sh -c 'rm -rf /data/uploads/icon; mkdir -p /data/uploads/icon'
  docker compose cp design/icon/icon.png humhub:/data/uploads/icon/icon.png >/dev/null
  for n in 32 96 152 167 180 192; do
    name=$(docker compose exec -T humhub php -r "echo 'icon_' . hash('xxh32', json_encode(['square' => $n])) . '.png';")
    docker compose cp "design/icon/${n}x${n}.png" "humhub:/data/uploads/icon/$name" >/dev/null
  done
  docker compose exec -T humhub chown -R www-data:www-data /data/uploads/icon
  docker compose exec -T -u www-data humhub /app/yii cache/flush-all >/dev/null
  echo "Icon gesetzt: $(docker compose exec -T humhub ls /data/uploads/icon | tr '\n' ' ')"
  exit 0
fi
# Eigene Übersetzungen (design/messages/<sprache>/<Kategorie>.php) nach config/messages – HumHub mischt sie über
# seine eigenen bzw. die der Module, z. B. fehlende Übersetzungen in Mails von Modulen
if [ "${1:-}" = uebersetzungen ]; then
  docker compose exec -T -u www-data humhub mkdir -p /data/config/messages
  docker compose cp design/messages/. humhub:/data/config/messages/ >/dev/null
  docker compose exec -T humhub sh -c 'chown -R www-data:www-data /data/config/messages'
  docker compose exec -T -u www-data humhub /app/yii cache/flush-all >/dev/null
  echo "Übersetzungen: $(cd design/messages && find . -name '*.php' | sed 's|^\./||' | tr '\n' ' ')"
  exit 0
fi
# Nur ein Lauf gleichzeitig (Cron + manuell), sonst geht die gesicherte console.php verloren
exec 9>/tmp/humhub-run-setup.lock
flock -w 300 9
C=$(docker compose ps -q humhub)
docker cp scripts/SetupController.php "$C":/tmp/SetupController.php
docker compose exec -T humhub rm -rf /tmp/legal
docker cp legal "$C":/tmp/legal
docker compose exec -T humhub rm -rf /tmp/design
docker cp design "$C":/tmp/design
docker compose exec -T humhub sh -c '
  cp /data/config/console.php /tmp/console.php.orig
  printf "<?php\nrequire_once \"/tmp/SetupController.php\";\nreturn [\"controllerMap\" => [\"setup\" => \"SetupController\"]];\n" > /data/config/console.php
'
# Zusätzlich /app/runtime zurück an www-data: Cache-Dateien, die ein Befehl als root angelegt hat,
# kann HumHub nicht überschreiben und liest dann veraltete Werte (am 17.10.2026: 2FA-Code galt sofort als abgelaufen)
trap 'docker compose exec -T humhub sh -c "cp /tmp/console.php.orig /data/config/console.php && chown www-data:www-data /data/config/console.php; chown -R www-data:www-data /app/runtime"' EXIT
docker compose exec -T -u www-data humhub /app/yii "setup/$1" "${@:2}"
