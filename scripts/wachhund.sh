#!/bin/bash
# Wachhund (Crontab alle 5 Minuten): hält community.rundumuffni.de am Laufen und meldet Störungen per Mail an den
# Admin (setup/admin-hinweis, Benutzer-ID 1). Mails nur bei Zustandswechseln, Speicher-/Plattenwarnung höchstens 1×/Tag.
#
# HumHub-Container „unhealthy“ (Docker-Healthcheck, 5 × 30 s fehlgeschlagen) – Docker selbst startet nur bei Exit neu:
#   Stufe 1: nur den Webserver (frankenphp) im Container beenden, supervisord startet ihn sofort neu.
#            Kein Container-Neustart, weil der beim Start module/update-all ausführt (Marketplace-Updates ungeprüft).
#   Stufe 2: (5 Min. später noch unhealthy) docker compose restart humhub – inkl. module/update-all, nur als Notnagel.
#   Stufe 3: (danach noch unhealthy) nichts mehr automatisch, nur Mail – dann muss ein Mensch ran.
# Datenbank unhealthy: nur Mail (kein automatischer Neustart der Datenbank).
# Embedding-Dienst (Uffi-Suche) antwortet nicht: Container neu starten (harmlos, Suche fällt solange auf Stichwörter zurück).
# Seite von außen nicht erreichbar, Container aber gesund: nur Mail (Netz/Router/DNS – Neustart hilft da nicht).
# Probelauf ohne Eingriffe/Mails: scripts/wachhund.sh probe
cd "$(dirname "$0")/.." || exit 1
ZUSTAND=rss/wachhund.zustand
URL=https://community.rundumuffni.de/user/auth/login
UA="UffiTest Wachhund"
PROBE=0; [ "${1:-}" = probe ] && PROBE=1
jetzt() { date '+%F %T'; }
log() { echo "$(jetzt) $*"; }

# Zustand laden (stufe, extern, db, emb, platte_tag, speicher_tag)
stufe=0; extern=0; db=0; emb=0; platte_tag=; speicher_tag=
[ -f "$ZUSTAND" ] && . "$ZUSTAND"
speichern() {
  [ $PROBE = 1 ] && return
  printf 'stufe=%s\nextern=%s\ndb=%s\nemb=%s\nplatte_tag=%s\nspeicher_tag=%s\n' \
    "$stufe" "$extern" "$db" "$emb" "$platte_tag" "$speicher_tag" > "$ZUSTAND"
}
melden() { # Betreff, Text – braucht einen laufenden HumHub-Container (Konsole), Web darf hängen
  log "MAIL: $1"
  [ $PROBE = 1 ] && return
  timeout 120 scripts/run-setup.sh admin-hinweis "Rund um Uffni: $1" "$2

Zeit: $(jetzt)
Server: $(uptime)
Protokoll: ~/humhub-setup/rss/wachhund.log" >/dev/null 2>&1 || log "Mail konnte nicht verschickt werden"
}
tun() { log "AKTION: $*"; [ $PROBE = 1 ] || "$@"; }
gesundheit() { docker inspect "$1" --format '{{if .State.Running}}{{if .State.Health}}{{.State.Health.Status}}{{else}}running{{end}}{{else}}{{.State.Status}}{{end}}' 2>/dev/null || echo fehlt; }

# 1. HumHub-Container
h=$(gesundheit humhub-setup-humhub-1); [ $PROBE = 1 ] && [ -n "${WACHHUND_TEST:-}" ] && h=$WACHHUND_TEST
if [ "$h" = healthy ] || [ "$h" = starting ]; then
  if [ "$stufe" -gt 0 ] && [ "$h" = healthy ]; then
    melden "wieder erreichbar" "Die Plattform läuft wieder (nach Stufe $stufe des Wachhunds)."
    stufe=0
  fi
else
  case "$stufe" in
    0) log "HumHub $h – Stufe 1: Webserver im Container neu starten"
       tun docker compose exec -T humhub pkill -x frankenphp
       stufe=1
       melden "Störung – Webserver neu gestartet" "Der HumHub-Container war „$h“. Der Wachhund hat den Webserver (frankenphp) im Container neu gestartet. Kommt in 5 Minuten keine Entwarnung, startet er den ganzen Container neu." ;;
    1) log "HumHub weiter $h – Stufe 2: Container neu starten"
       tun docker compose restart humhub
       stufe=2
       melden "Störung – Container neu gestartet" "Der HumHub-Container blieb „$h“, auch nach dem Neustart des Webservers. Der Wachhund hat den Container neu gestartet. Achtung: Dabei läuft module/update-all – Marketplace-Module können sich aktualisiert haben (/app/yii module/list prüfen)." ;;
    2) log "HumHub weiter $h – Stufe 3: keine weiteren Eingriffe"
       stufe=3
       melden "STÖRUNG – bitte eingreifen" "Der HumHub-Container ist auch nach dem Neustart „$h“. Der Wachhund greift nicht weiter ein.
Erste Schritte: cd ~/humhub-setup && docker compose ps && docker compose logs --tail 100 humhub" ;;
    *) log "HumHub weiter $h (Stufe 3, warte auf Eingriff)" ;;
  esac
fi

# 2. Datenbank
d=$(gesundheit humhub-setup-db-1)
if [ "$d" != healthy ] && [ "$d" != starting ]; then
  [ "$db" = 0 ] && melden "Datenbank gestört" "Der Datenbank-Container ist „$d“. Der Wachhund startet die Datenbank nicht selbst neu.
Prüfen: cd ~/humhub-setup && docker compose logs --tail 100 db"
  db=1; log "Datenbank $d"
elif [ "$db" = 1 ]; then
  melden "Datenbank wieder gesund" "Der Datenbank-Container ist wieder „$d“."; db=0
fi

# 3. Embedding-Dienst (Uffi-Suche)
if curl -fs -o /dev/null --max-time 10 http://127.0.0.1:8095/; then
  emb=0
else
  log "Embedding-Dienst antwortet nicht"
  if [ "$emb" = 0 ]; then
    tun docker compose restart embeddings
    emb=1
  elif [ "$emb" = 1 ]; then
    melden "Uffi-Suche gestört" "Der Embedding-Dienst antwortet auch nach einem Neustart nicht. Uffi sucht solange nur nach Stichwörtern.
Prüfen: cd ~/humhub-setup && docker compose logs --tail 50 embeddings"
    emb=2
  fi
fi

# 4. Von außen erreichbar? (nur sinnvoll, wenn der Container gesund ist)
if [ "$h" = healthy ]; then
  if curl -fs -o /dev/null -A "$UA" --max-time 20 "$URL"; then
    [ "$extern" -ge 2 ] && melden "von außen wieder erreichbar" "community.rundumuffni.de ist von außen wieder erreichbar."
    extern=0
  else
    extern=$((extern + 1)); log "von außen nicht erreichbar ($extern×)"
    [ "$extern" = 2 ] && melden "von außen nicht erreichbar" "Der Container ist gesund, aber $URL ist seit ca. 10 Minuten nicht erreichbar. Vermutlich Netz, Router, DNS oder Zertifikat – ein Neustart hilft da nicht."
  fi
fi

# 5. Platte und Speicher (höchstens eine Warnung pro Tag)
heute=$(date +%F)
belegt=$(df --output=pcent / | tail -1 | tr -dc 0-9)
if [ "$belegt" -ge 90 ] && [ "$platte_tag" != "$heute" ]; then
  melden "Festplatte zu $belegt % voll" "Auf dem Server sind nur noch $(df -h --output=avail / | tail -1 | tr -d ' ') frei. Bei voller Platte stürzen Datenbank und Uploads ab.
Größte Brocken: ~/humhub-setup/backups (Sicherungen), Docker-Images (docker image prune)."
  platte_tag=$heute
fi
frei=$(awk '/MemAvailable/ {print int($2/1024)}' /proc/meminfo)
swap_frei=$(awk '/SwapFree/ {print int($2/1024)}' /proc/meminfo)
if [ "$frei" -lt 250 ] && [ "$swap_frei" -lt 300 ] && [ "$speicher_tag" != "$heute" ]; then
  melden "Arbeitsspeicher knapp" "Verfügbar: $frei MB, Swap frei: $swap_frei MB. Es droht ein Abbruch von Prozessen wegen Speichermangels.
Prüfen: docker stats --no-stream"
  speicher_tag=$heute
fi

speichern
[ $PROBE = 1 ] && log "Probe: HumHub=$h DB=$d Platte=$belegt% RAM frei=${frei}MB Swap frei=${swap_frei}MB Stufe=$stufe"
exit 0
