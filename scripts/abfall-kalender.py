#!/usr/bin/env python3
"""Stadtweiten Abfallkalender für Uffenheim erzeugen.

Holt die Leerungstermine für zwei Referenzadressen (Kernstadt / Ortsteil) aus dem
Abfallportal des Landkreises Neustadt a.d.Aisch-Bad Windsheim und schreibt daraus
abfall/abfall.ics. Öffentlich unter https://community.rundumuffni.de/abfall/abfall.ics,
von dort liest sie auch das Modul External Calendar.

Restmüll, Bio und Papier sind in ganz Uffenheim gleich, nur die Gelbe Tonne kommt in
den Ortsteilen einen Tag später (Stand 2026-09, Abgleich aller 154 Straßen).
Termine werden ganztägig und mit stabiler UID ausgegeben, damit der Kalender beim
nächtlichen Abgleich nicht alles neu anlegt.
"""
import hashlib
import html
import http.cookiejar
import os
import re
import sys
import urllib.parse
import urllib.request
from datetime import datetime, timezone

BASE = "https://sperrmuell.kreis-nea.de/WasteManagementNeustadt/WasteManagementServlet"
PORTAL = "https://www.kreis-nea.de/lebenslagen/abfallwirtschaft/abfalltermine"
OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "abfall", "abfall.ics")

KERNSTADT = ("Albrecht-Dürer-Str.", "30")
ORTSTEIL = ("Custenlohr (Custenlohr)", "1")

# Tonnenart im Portal -> (Titel im Kalender, aus welcher Adresse)
TYPES = {
    "Restmuelltonne": [("Restmüll", KERNSTADT)],
    "Biotonne": [("Biotonne", KERNSTADT)],
    "Papiertonne": [("Papiertonne", KERNSTADT)],
    "Gelbe Tonne": [("Gelbe Tonne – Kernstadt", KERNSTADT), ("Gelbe Tonne – Ortsteile", ORTSTEIL)],
    "Muellgebuehren": [("Müllgebühren fällig", KERNSTADT)],
}
# Zusätzlich je Tonnenart eine eigene Datei (abfall/abfall-<name>.ics), damit HumHub jede Art
# als eigenen Kalender in eigener Farbe zeigt; Zuordnung über den Anfang des Titels
FILES = {
    "restmuell": "Restmüll",
    "bio": "Biotonne",
    "papier": "Papiertonne",
    "gelb": "Gelbe Tonne",
    "gebuehren": "Müllgebühren",
}


def fields(page):
    f = {}
    for m in re.finditer(r'<INPUT[^>]*NAME="([^"]+)"[^>]*>', page, re.I):
        tag = m.group(0)
        if re.search(r'TYPE="checkbox"', tag, re.I):
            continue
        v = re.search(r'VALUE="([^"]*)"', tag, re.I)
        f[m.group(1)] = html.unescape(v.group(1)) if v else ""
    return f


def fetch_ics(street, number):
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

    def post(data):
        with opener.open(BASE, urllib.parse.urlencode(data).encode(), timeout=60) as r:
            return r.read().decode("utf-8", "replace")

    with opener.open(BASE + "?SubmitAction=wasteDisposalServices&InFrameMode=TRUE", timeout=60) as r:
        page = r.read().decode("utf-8", "replace")
    f = fields(page)
    f.update(Ort="Uffenheim", SubmitAction="CITYCHANGED")
    f = fields(post(f))
    f.update(Ort="Uffenheim", Strasse=street, Hausnummer=number, SubmitAction="forward")
    for i in range(1, 12):
        f["ContainerGewaehlt_%d" % i] = "on"
    page = post(f)
    if "filedownload_ICAL" not in page:
        raise RuntimeError("Keine Terminliste für %s %s" % (street, number))
    f = fields(page)
    f["SubmitAction"] = "filedownload_ICAL"
    ics = post(f)
    if "BEGIN:VCALENDAR" not in ics:
        raise RuntimeError("Keine iCal-Datei für %s %s" % (street, number))
    return ics


def events(ics):
    """(Tonnenart, Datum) aus der iCal-Datei des Portals."""
    for block in re.findall(r"BEGIN:VEVENT(.*?)END:VEVENT", ics.replace("\r\n ", "").replace("\n ", ""), re.S):
        summary = re.search(r"^SUMMARY[^:]*:(.*)$", block, re.M)
        start = re.search(r"^DTSTART[^:]*:(\d{8})", block, re.M)
        if summary and start:
            yield summary.group(1).strip(), start.group(1)


def write(path, calname, found):
    stamp = datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ")
    lines = [
        "BEGIN:VCALENDAR", "VERSION:2.0", "PRODID:-//Rund um Uffni//Abfallkalender Uffenheim//DE",
        "CALSCALE:GREGORIAN", "X-WR-CALNAME:" + calname.replace(",", "\\,"), "X-WR-TIMEZONE:Europe/Berlin",
    ]
    desc = ("Stadtweite Abfuhrtermine. Termine für die eigene Adresse und Erinnerungen: " + PORTAL).replace(",", "\\,")
    for day, title in found:
        uid = hashlib.sha1(("%s|%s" % (day, title)).encode()).hexdigest()[:16]
        nextday = datetime.strptime(day, "%Y%m%d").toordinal() + 1
        lines += [
            "BEGIN:VEVENT",
            "UID:%s@abfall.rundumuffni.de" % uid,
            "DTSTAMP:" + stamp,
            # Ohne LAST-MODIFIED bricht External Calendar bei der Anzeige ab
            "LAST-MODIFIED:" + stamp,
            "DTSTART;VALUE=DATE:" + day,
            "DTEND;VALUE=DATE:" + datetime.fromordinal(nextday).strftime("%Y%m%d"),
            "SUMMARY:" + title,
            "DESCRIPTION:" + desc,
            "TRANSP:TRANSPARENT",
            "END:VEVENT",
        ]
    lines.append("END:VCALENDAR")

    os.makedirs(os.path.dirname(path), exist_ok=True)
    tmp = path + ".tmp"
    with open(tmp, "w", encoding="utf-8", newline="\r\n") as fh:
        fh.write("\n".join(lines) + "\n")
    os.replace(tmp, path)


def main():
    sources = {KERNSTADT: fetch_ics(*KERNSTADT), ORTSTEIL: fetch_ics(*ORTSTEIL)}
    found = set()
    for key, targets in TYPES.items():
        for title, addr in targets:
            for summary, day in events(sources[addr]):
                if summary.startswith(key):
                    found.add((day, title))
    if len({t for _, t in found}) < 4:
        # Lieber alte Datei behalten als einen halbleeren Kalender ausliefern
        raise RuntimeError("Zu wenige Tonnenarten gefunden: %s" % sorted({t for _, t in found}))

    write(OUT, "Abfuhrtermine Uffenheim", sorted(found))
    for name, prefix in FILES.items():
        write(OUT.replace("abfall.ics", "abfall-%s.ics" % name), "Abfuhrtermine Uffenheim – " + prefix,
              sorted(e for e in found if e[1].startswith(prefix)))
    print("%s: %d Termine geschrieben" % (datetime.now().strftime("%F %T"), len(found)))


if __name__ == "__main__":
    try:
        main()
    except Exception as e:
        print("%s: FEHLER %s" % (datetime.now().strftime("%F %T"), e), file=sys.stderr)
        sys.exit(1)
