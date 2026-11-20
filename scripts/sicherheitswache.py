#!/usr/bin/env -S python3 -I
"""Sicherheitswache (Crontab täglich): sucht bekannte Sicherheitslücken in den Bausteinen der Plattform und
meldet NEUE Funde per Mail an den Admin (setup/admin-hinweis, Benutzer-ID 1). Montags kommt zusätzlich eine
Erinnerung, solange wichtige Funde offen sind. Es wird nichts automatisch aktualisiert.

Geprüft wird:
  1. HumHub-Kern und Marketplace-Module: Sicherheitsmeldungen (GitHub Security Advisories) gegen die
     installierte Version
  2. Docker-Images (HumHub, MariaDB, Python-Basis der Embeddings): neu gebautes Image bzw. neuere HumHub-Version
  3. PHP-Bibliotheken: composer audit für den HumHub-Kern (composer.lock aus dem Image) und die eigenen Module
  4. pdf.js (Mitteilungsblatt) und Python-Pakete des Embedding-Dienstes: OSV.dev
  5. Server (Debian): ausstehende Sicherheitsupdates (älter als 2 Tage, sonst erledigt unattended-upgrades sie)
     und nötiger Neustart
  6. Modus „wid“ (Crontab alle 2 Stunden, der Feed reicht nur ca. 6 Stunden zurück): RSS-Feed des BSI-Warn- und
     Informationsdienstes, gefiltert auf unsere Bausteine (WID_PRODUKTE); jede Meldung wird einmal gemailt

Aufruf: scripts/sicherheitswache.py [probe] | wid [probe]   (probe = nur anzeigen, keine Mail, Zustand bleibt)
Zustand: rss/sicherheitswache.json, Protokoll: rss/sicherheitswache.log (per Crontab)
"""
import datetime
import json
import os
import re
import subprocess
import sys
import tempfile
import urllib.request
import xml.etree.ElementTree as ET

BASIS = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
ZUSTAND = os.path.join(BASIS, 'rss', 'sicherheitswache.json')
PROBE = 'probe' in sys.argv[1:]
WID = 'wid' in sys.argv[1:]
WID_FEED = 'https://wid.cert-bund.de/content/public/securityAdvisory/rss'
# Produkt (Text vor dem Doppelpunkt im Titel) → ab welcher Einstufung gemeldet wird ([UPDATE] älterer Meldungen nur
# bei „kritisch“). Anwendung: jede neue Meldung;
# Betriebssystem: nur hoch/kritisch (Debian-Updates kommen ohnehin automatisch), Kernel nur kritisch.
WID_PRODUKTE = [
    (r'HumHub|\bPHP\b|MariaDB|MySQL|Docker|containerd|\brunc\b|Caddy|FrankenPHP|CPython|\bPython\b|\bYii|Symfony|Composer'
     r'|pdf\.js|PDF\.js|Pillow|Debian|Guzzle|Twig|ImageMagick|libgd|Claude|Anthropic', 'mittel'),
    (r'OpenSSH|OpenSSL|GNU libc|glibc|\bsudo\b|systemd|cURL|libcurl|Samba|cifs-utils|libxml2|expat|zlib|GnuTLS|XZ', 'hoch'),
    (r'Linux Kernel', 'kritisch'),
]
# Fremdpakete in Herstellerprodukten (z. B. „Red Hat Enterprise Linux (php)“) betreffen uns nicht direkt
WID_AUSSCHLUSS = r'^(Red Hat|IBM|SUSE|Oracle|NetApp|HP|HPE|Dell|Ubuntu|Amazon|Microsoft|Google|VMware|Siemens|F5|Cisco|Fortinet|Palo Alto)\b'
STUFEN = ['niedrig', 'mittel', 'hoch', 'kritisch']
UA = 'sicherheitswache/1.0'  # bewusst ohne Projekt- oder Personenangaben
HUMHUB = 'humhub-setup-humhub-1'
EMBEDDINGS = 'humhub-setup-embeddings-1'
# Module ohne öffentliche GitHub-Sicherheitsmeldungen (nur Hinweis im Bericht)
NICHT_PRUEFBAR = {'clean-theme': 'Anbieter cuzy.app', 'sharebetween': 'keine Quelle angegeben'}

heute = datetime.date.today()
befunde = []   # {key, bereich, schwere: wichtig|info, text}
fehler = []    # Prüfungen, die nicht liefen


def log(text):
    print(f"{datetime.datetime.now():%Y-%m-%d %H:%M:%S} {text}", flush=True)


def befund(key, bereich, schwere, text):
    befunde.append({'key': key, 'bereich': bereich, 'schwere': schwere, 'text': text})


def holen(url, daten=None):
    req = urllib.request.Request(url, data=json.dumps(daten).encode() if daten is not None else None,
                                 headers={'User-Agent': UA, 'Accept': 'application/json',
                                          **({'Content-Type': 'application/json'} if daten is not None else {})})
    with urllib.request.urlopen(req, timeout=30) as r:
        return json.load(r)


def sh(*cmd, timeout=300):
    return subprocess.run(cmd, capture_output=True, text=True, timeout=timeout)


def version(v):
    """'1.19.4' → (1, 19, 4); Vorabversionen (-beta) zählen wie die Version davor"""
    return tuple(int(x) for x in re.findall(r'\d+', str(v).split('-')[0])[:4])


def betroffen(installiert, bereich):
    """GitHub-Bereich wie '>= 1.19.0, < 1.19.4' oder '< 1.18.7 and >= 1.19.0, < 1.19.1' (and = oder, Komma = und).
    None = nicht auswertbar."""
    if not bereich:
        return None
    iv = version(installiert)
    # Freitext-Schreibweisen mancher Projekte: „0.16.10, 0.17.1 and prior“, „1.13.0 - 1.18.2“
    if re.search(r'\b(and |or )?(prior|earlier|below)\b', bereich):
        return iv <= max((version(v) for v in re.findall(r'\d+(?:\.\d+)+', bereich)), default=())
    bereich = re.sub(r'([\d.]+)\s+-\s+([\d.]+)', r'>= \1, <= \2', bereich)
    for gruppe in re.split(r'\s+and\s+|\|\|', bereich):
        passt = True
        for teil in gruppe.split(','):
            m = re.match(r'\s*(>=|<=|>|<|=)?\s*v?([\d.]+)\s*$', teil)
            if not m:
                return None
            op, v = m.group(1) or '=', version(m.group(2))
            passt &= {'>=': iv >= v, '<=': iv <= v, '>': iv > v, '<': iv < v, '=': iv == v}[op]
        if passt:
            return True
    return False


def humhub_version():
    r = sh('docker', 'exec', HUMHUB, 'grep', '-o', "'version' => '[0-9.]*'", '/opt/humhub/protected/humhub/config/common.php')
    m = re.search(r"([\d.]+)'", r.stdout)
    if not m:
        raise RuntimeError('HumHub-Version nicht gefunden')
    return m.group(1)


def pruefe_advisories(hh_version):
    quellen = [('HumHub-Kern', 'humhub/humhub', hh_version)]
    nicht = []
    modulordner = os.path.join(BASIS, 'humhub-data', 'modules')
    for m in sorted(os.listdir(modulordner)):
        if os.path.isdir(os.path.join(BASIS, 'modules', m)):
            continue  # eigenes Modul (Code liegt bei uns)
        try:
            info = json.load(open(os.path.join(modulordner, m, 'module.json')))
        except (OSError, ValueError):
            continue
        repo = re.match(r'https://github\.com/([\w.-]+/[\w.-]+)', info.get('homepage') or '')
        if repo:
            quellen.append((f'Modul {m}', repo.group(1), info.get('version', '0')))
        else:
            nicht.append(f"{m} ({NICHT_PRUEFBAR.get(m, info.get('homepage') or 'keine Quelle')})")
    for name, repo, inst in quellen:
        try:
            liste = holen(f'https://api.github.com/repos/{repo}/security-advisories?per_page=100')
        except Exception as e:
            fehler.append(f'GitHub {repo}: {e}')
            continue
        for a in liste:
            ergebnisse = [betroffen(inst, v.get('vulnerable_version_range')) for v in a.get('vulnerabilities') or []]
            if True not in ergebnisse and None not in ergebnisse:
                continue
            behoben = ', '.join(filter(None, (v.get('patched_versions') for v in a.get('vulnerabilities') or []))) or 'noch keine'
            unsicher = '' if True in ergebnisse else ' (Versionsbereich nicht auswertbar, bitte selbst prüfen)'
            befund(f"ghsa:{a['ghsa_id']}", name, 'wichtig',
                   f"{a.get('severity', '?')}: {a.get('summary', '').strip()} – installiert {inst}, behoben in {behoben}{unsicher}\n"
                   f"    {a.get('html_url')}")
    return nicht


def lokaler_digest(ref):
    r = sh('docker', 'image', 'inspect', ref, '--format', '{{json .RepoDigests}}')
    return [d.split('@')[1] for d in json.loads(r.stdout or '[]')] if r.returncode == 0 else []


def pruefe_images(hh_version):
    # Image-Namen aus den laufenden Containern bzw. dem Dockerfile (Tag-Wechsel in compose.yml wandert so mit)
    img = lambda c: sh('docker', 'inspect', c, '--format', '{{.Config.Image}}').stdout.strip()
    py = re.search(r'^FROM\s+(\S+)', open(os.path.join(BASIS, 'embeddings', 'Dockerfile')).read(), re.M)
    for ref, wer, inhalt in ((img(HUMHUB), 'HumHub', 'für Debian, PHP und den Webserver'),
                             (img('humhub-setup-db-1'), 'MariaDB', 'für Ubuntu und MariaDB'),
                             (py.group(1) if py else '', 'Python-Basis der Embeddings', 'für Debian und Python')):
        if ':' not in ref:
            fehler.append(f'Image-Name für {wer} nicht ermittelbar')
            continue
        repo, tag = ref.split(':')
        repo = repo if '/' in repo else 'library/' + repo
        try:
            remote = holen(f'https://hub.docker.com/v2/repositories/{repo}/tags/{tag}')
        except Exception as e:
            fehler.append(f'Docker Hub {ref}: {e}')
            continue
        lokal = lokaler_digest(ref)
        if remote.get('digest') and lokal and remote['digest'] not in lokal:
            # build --pull nutzt die neue Basis, aktualisiert aber den lokalen Tag nicht → vorher docker pull
            tun = (f'docker pull {ref} && docker compose build --no-cache embeddings && docker compose up -d embeddings' if 'python' in ref
                   else 'docker compose pull && docker compose up -d (vorher Sicherung, danach Seite und Module prüfen)')
            befund(f"image:{ref}:{remote['digest']}", 'Docker-Image', 'wichtig',
                   f"{wer}: neu gebautes Image {ref} vom {remote.get('last_updated', '')[:10]} (enthält meist Sicherheitsupdates "
                   f"{inhalt}). Einspielen: {tun}")
    # Neuere HumHub-Versionen (Patch im selben Zweig = wichtig, neuer Zweig = Info)
    try:
        tags = holen('https://hub.docker.com/v2/repositories/humhub/humhub/tags?page_size=100&ordering=last_updated')['results']
    except Exception as e:
        fehler.append(f'Docker Hub Versionen: {e}')
        return
    stabil = sorted({t['name'] for t in tags if re.fullmatch(r'\d+\.\d+\.\d+', t['name'])}, key=version)
    inst = version(hh_version)
    if stabil:
        neueste = stabil[-1]
        im_zweig = [t for t in stabil if version(t)[:2] == inst[:2] and version(t) > inst]
        if im_zweig:
            befund(f'humhub-version:{im_zweig[-1]}', 'HumHub-Version', 'wichtig',
                   f"HumHub {im_zweig[-1]} ist erschienen (installiert {hh_version}). Changelog: "
                   f"https://github.com/humhub/humhub/releases – Einspielen per docker compose pull, danach eigene Module testen.")
        if version(neueste)[:2] > inst[:2]:
            befund(f'humhub-zweig:{neueste}', 'HumHub-Version', 'info',
                   f"Neue HumHub-Hauptversion {neueste} verfügbar (wir nutzen {hh_version[:4]}). Umstieg planen: in compose.yml "
                   f"HUMHUB_VERSION anheben, vorher auf einer Kopie testen.")


def composer_audit(ordner, name):
    r = sh('docker', 'run', '--rm', '-u', f'{os.getuid()}:{os.getgid()}', '-v', f'{ordner}:/app:ro', '-e', 'COMPOSER_HOME=/tmp',
           'composer:2', 'audit', '--locked', '--no-interaction', '--format=json', timeout=600)
    text = r.stdout[r.stdout.find('{'):] if '{' in r.stdout else ''
    if not text:
        fehler.append(f'composer audit {name}: {(r.stderr or r.stdout).strip()[:200]}')
        return
    daten = json.loads(text)
    adv = daten.get('advisories') or {}
    for paket, liste in (adv.items() if isinstance(adv, dict) else []):
        ids = sorted(str(a.get('advisoryId')) for a in liste)
        # Kern: Bibliotheken liefert HumHub mit dem Image, wir können sie nicht selbst tauschen → Info
        befund(f"composer:{name}:{paket}:{','.join(ids)}", f'PHP-Bibliothek ({name})', 'info' if name == 'HumHub-Kern' else 'wichtig',
               f"{paket}: " + '; '.join(f"{a.get('title', '').strip()[:90]}" + (f" ({a['cve']})" if a.get('cve') else '') for a in liste))


def pruefe_composer():
    sh('docker', 'pull', '-q', 'composer:2', timeout=300)  # aktuelle Prüfregeln
    with tempfile.TemporaryDirectory() as tmp:
        ok = all(sh('docker', 'cp', f'{HUMHUB}:/opt/humhub/{f}', tmp).returncode == 0 for f in ('composer.json', 'composer.lock'))
        if ok:
            os.chmod(tmp, 0o755)
            composer_audit(tmp, 'HumHub-Kern')
        else:
            fehler.append('composer.lock des Kerns nicht lesbar')
    for m in sorted(os.listdir(os.path.join(BASIS, 'modules'))):
        ordner = os.path.join(BASIS, 'modules', m)
        if os.path.isfile(os.path.join(ordner, 'composer.lock')):
            composer_audit(ordner, f'Modul {m}')


def osv(pakete, bereich, schwere='wichtig', hinweis=''):
    """pakete: [(ökosystem, name, version)]"""
    if not pakete:
        return
    try:
        antwort = holen('https://api.osv.dev/v1/querybatch',
                        {'queries': [{'package': {'ecosystem': e, 'name': n}, 'version': v} for e, n, v in pakete]})
    except Exception as e:
        fehler.append(f'OSV {bereich}: {e}')
        return
    for (e, n, v), r in zip(pakete, antwort.get('results', [])):
        ids = sorted(x['id'] for x in r.get('vulns') or [])
        if ids:
            # ein Fund je Paket und Version; kommt eine neue Lücke dazu, ändert sich der Schlüssel → neue Meldung
            befund(f"osv:{e}:{n}:{v}:{len(ids)}:{ids[-1]}", bereich, schwere,
                   f"{n} {v}: {len(ids)} bekannte Lücke(n){hinweis} – https://osv.dev/list?q={n}&ecosystem={e}"
                   f"\n    z. B. {', '.join(ids[:5])}{' …' if len(ids) > 5 else ''}")


def pruefe_osv():
    m = re.search(r'^\s*V=([\d.]+)', open(os.path.join(BASIS, 'scripts', 'run-setup.sh')).read(), re.M)
    if m:
        osv([('npm', 'pdfjs-dist', m.group(1))], 'pdf.js (Mitteilungsblatt)')
    r = sh('docker', 'exec', EMBEDDINGS, 'pip', 'list', '--format=json', '--disable-pip-version-check')
    if r.returncode == 0:
        # Dienst nur im Docker-Netz bzw. auf 127.0.0.1 erreichbar und verarbeitet nur Text → Info; mit dem nächsten
        # Neubau (docker compose build --pull embeddings) kommen neuere Pakete
        osv([('PyPI', p['name'], p['version']) for p in json.loads(r.stdout)], 'Python-Paket (Embeddings, nur intern)', 'info',
            ' – Pillow hält fastembed 0.7.3 unter 12 (nur für Bildmodelle, Textsuche nutzt es nicht); beheben = fastembed in '
            'embeddings/Dockerfile anheben, danach ki-suche-test')
    else:
        fehler.append('pip list im Embedding-Dienst fehlgeschlagen')


def pruefe_server():
    r = sh('apt', 'list', '--upgradable')
    docker = []
    for zeile in r.stdout.splitlines():
        m = re.match(r'([^/]+)/(\S+)\s+(\S+)', zeile)
        if m and 'security' in m.group(2):
            befund(f'apt:{m.group(1)}:{m.group(3)}', 'Server (Debian)', 'wichtig',
                   f"Sicherheitsupdate für {m.group(1)} ({m.group(3)}) ist seit mindestens 2 Tagen nicht eingespielt – "
                   f"läuft unattended-upgrades? (systemctl status apt-daily-upgrade.timer, /var/log/unattended-upgrades/)")
        elif m and re.match(r'docker|containerd', m.group(1)):
            docker.append(f'{m.group(1)} {m.group(3).split("~")[0]}')
    if docker:
        befund('apt-docker:' + ','.join(sorted(docker)), 'Server (Docker)', 'info',
               f"Updates für Docker: {', '.join(sorted(docker))} – werden bewusst nicht automatisch eingespielt (startet alle "
               f"Container neu). Bei Gelegenheit: sudo apt upgrade, danach Seite prüfen.")
    if os.path.exists('/var/run/reboot-required'):
        seit = datetime.date.fromtimestamp(os.path.getmtime('/var/run/reboot-required'))
        pakete = open('/var/run/reboot-required.pkgs').read().split() if os.path.exists('/var/run/reboot-required.pkgs') else []
        befund(f'reboot:{seit}', 'Server (Debian)', 'wichtig',
               f"Neustart des Servers nötig (seit {seit}{', wegen ' + ', '.join(sorted(set(pakete))[:8]) if pakete else ''}). "
               f"Erst danach sind z. B. Kernel-Updates aktiv. Nachts: sudo reboot (Container starten von selbst).")


def wid_lauf(zustand):
    """BSI-Feed: neue passende Meldungen einmal mailen; gesehene IDs 180 Tage merken"""
    try:
        req = urllib.request.Request(WID_FEED, headers={'User-Agent': UA})
        with urllib.request.urlopen(req, timeout=30) as r:
            kanal = ET.fromstring(r.read()).find('channel')
    except Exception as e:
        zustand['wid_fehler'] = zustand.get('wid_fehler', 0) + 1
        log(f'WID: Abruf fehlgeschlagen ({e})')
        if zustand['wid_fehler'] == 6:  # ca. 12 Stunden
            mail('Sicherheitswache – BSI-Feed nicht erreichbar', f'Der WID-Feed ist seit ca. 12 Stunden nicht abrufbar ({e}).\n{WID_FEED}')
        return
    zustand['wid_fehler'] = 0
    gesehen = zustand.setdefault('wid_gesehen', {})
    treffer = []
    for item in kanal.findall('item'):
        titel = (item.findtext('title') or '').strip()
        link = (item.findtext('link') or '').strip()
        wid_id = link.rsplit('=', 1)[-1]
        stufe = (item.findtext('category') or '').strip().lower()
        produkt = re.sub(r'^(\[[^\]]*\]\s*)+', '', titel).split(':')[0].strip()
        if not wid_id or wid_id in gesehen or re.match(WID_AUSSCHLUSS, produkt):
            continue
        if titel.startswith('[UPDATE]') and stufe != 'kritisch':
            continue  # Aktualisierung einer älteren Meldung (meist nur ein weiterer Hersteller-Patch)
        for muster, ab in WID_PRODUKTE:
            if re.search(muster, produkt) and stufe in STUFEN and STUFEN.index(stufe) >= STUFEN.index(ab):
                treffer.append((wid_id, titel, link, (item.findtext('description') or '').strip()))
                break
    log(f"WID: {len(kanal.findall('item'))} Meldungen im Feed, {len(treffer)} neu für uns")
    if treffer:
        text = (f"Der BSI-Warn- und Informationsdienst meldet {len(treffer)} Schwachstelle(n) in Bausteinen, die wir einsetzen. "
                "Bitte prüfen, ob unsere Version betroffen ist – Updates kommen meist über ein neues HumHub-/MariaDB-Image "
                "oder Debian (unattended-upgrades); die tägliche Prüfung meldet, wenn eins bereitsteht.\n\n"
                + '\n\n'.join(f'- {t}\n  {d}\n  {l}' for _, t, l, d in treffer)
                + '\n\nFilter: WID_PRODUKTE in scripts/sicherheitswache.py')
        betreff = f"BSI-Warnung: {re.sub(r'^(\[[^\]]*\]\s*)+', '', treffer[0][1])[:70]}" + (f' (+{len(treffer) - 1})' if len(treffer) > 1 else '')
        if not mail(betreff, text):
            return  # beim nächsten Lauf erneut versuchen
    for wid_id, *_ in treffer:
        gesehen[wid_id] = str(heute)
    grenze = str(heute - datetime.timedelta(days=180))
    zustand['wid_gesehen'] = {k: v for k, v in gesehen.items() if v >= grenze}


def zustand_laden():
    zustand = {'bekannt': {}, 'fehler': {}, 'erinnert': ''}
    if os.path.exists(ZUSTAND):
        zustand.update(json.load(open(ZUSTAND)))
    return zustand


def zustand_speichern(zustand):
    if PROBE:
        return
    os.makedirs(os.path.dirname(ZUSTAND), exist_ok=True)
    with open(ZUSTAND + '.tmp', 'w') as f:
        json.dump(zustand, f, indent=1, ensure_ascii=False)
    os.replace(ZUSTAND + '.tmp', ZUSTAND)


def mail(betreff, text):
    log(f'MAIL: {betreff}')
    if PROBE:
        print(text)
        return True
    r = sh(os.path.join(BASIS, 'scripts', 'run-setup.sh'), 'admin-hinweis', f'Rund um Uffni: {betreff}', text, timeout=180)
    return r.returncode == 0


def main():
    zustand = zustand_laden()
    if WID:
        wid_lauf(zustand)
        zustand_speichern(zustand)
        return
    nicht_pruefbar = []
    try:
        hh = humhub_version()
    except Exception as e:
        fehler.append(str(e))
        hh = None
    for name, pruefung in (('advisories', lambda: pruefe_advisories(hh) if hh else []),
                           ('images', lambda: pruefe_images(hh) if hh else None),
                           ('composer', pruefe_composer), ('osv', pruefe_osv), ('server', pruefe_server)):
        try:
            erg = pruefung()
            if name == 'advisories':
                nicht_pruefbar = erg or []
        except Exception as e:
            fehler.append(f'{name}: {e}')

    # Doppelte Schlüssel zusammenfassen, Zustand fortschreiben
    aktuell = {b['key']: b for b in befunde}
    bekannt = zustand['bekannt']
    for key in aktuell:
        bekannt.setdefault(key, str(heute))
    # Funde einer Quelle, die diesmal nicht erreichbar war, gelten nicht als erledigt (sonst Doppelmeldung am Folgetag)
    ausgefallen = tuple(p for art, praefixe in (('GitHub', ('ghsa:',)), ('advisories', ('ghsa:',)),
                                              ('Docker Hub', ('image:', 'humhub-')), ('images', ('image:', 'humhub-')),
                                              ('composer', ('composer:',)), ('OSV', ('osv:',)), ('osv', ('osv:',)),
                                              ('pip', ('osv:',)), ('server', ('apt', 'reboot:')))
                        if any(f.startswith(art) for f in fehler) for p in praefixe)
    for key in list(bekannt):
        if key not in aktuell and not key.startswith(ausgefallen or ('\0',)):
            del bekannt[key]  # erledigt
    # Ausstehende apt-Sicherheitsupdates erst nach 2 Tagen melden (unattended-upgrades läuft täglich)
    reif = {k: b for k, b in aktuell.items()
            if not k.startswith('apt:') or b['schwere'] == 'info' or (heute - datetime.date.fromisoformat(bekannt[k])).days >= 2}
    gemeldet = set(zustand.get('gemeldet', []))
    neu = [b for k, b in reif.items() if k not in gemeldet]
    wichtig_offen = [b for b in reif.values() if b['schwere'] == 'wichtig']

    # Fehlgeschlagene Prüfungen erst melden, wenn sie 3 Läufe in Folge scheitern
    fehlerzahl = zustand['fehler']
    for f in fehler:
        art = f.split(':')[0]
        fehlerzahl[art] = fehlerzahl.get(art, 0) + 1
    for art in list(fehlerzahl):
        if not any(f.split(':')[0] == art for f in fehler):
            del fehlerzahl[art]
    dauerfehler = [f for f in fehler if fehlerzahl.get(f.split(':')[0]) == 3]

    def abschnitt(liste):
        zeilen = []
        for bereich in sorted({b['bereich'] for b in liste}):
            zeilen.append(f'\n{bereich}:')
            zeilen += [f"  - {'[wichtig] ' if b['schwere'] == 'wichtig' else ''}{b['text']}" for b in liste if b['bereich'] == bereich]
        return '\n'.join(zeilen)

    fuss = ('\n\nNicht automatisch prüfbar: ' + ', '.join(nicht_pruefbar) if nicht_pruefbar else '') + \
           '\n\nAmtliche Warnungen zusätzlich: BSI WID (https://wid.cert-bund.de), EU-Schwachstellendatenbank ' \
           '(https://euvd.enisa.europa.eu).\nProtokoll: ~/humhub-setup/rss/sicherheitswache.log – Probelauf: scripts/sicherheitswache.py probe'
    gesendet = True
    if neu or dauerfehler:
        n_w = sum(b['schwere'] == 'wichtig' for b in neu)
        text = (f"Die Sicherheitswache hat {len(neu)} neue Funde ({n_w} wichtig).\n" + abschnitt(neu)
                + (f"\n\nInsgesamt offen und wichtig: {len(wichtig_offen)}" if len(wichtig_offen) > n_w else '')
                + ('\n\nPrüfungen, die seit 3 Tagen nicht laufen:\n' + '\n'.join('  - ' + f for f in dauerfehler) if dauerfehler else '')
                + fuss)
        gesendet = mail(f"Sicherheitswache – {len(neu)} neue Funde" if neu else 'Sicherheitswache – Prüfung gestört', text)
    elif wichtig_offen and heute.weekday() == 0 and zustand.get('erinnert') != str(heute):
        gesendet = mail(f'Sicherheitswache – Erinnerung: {len(wichtig_offen)} offen',
                        'Diese Funde sind noch offen:\n' + abschnitt(wichtig_offen) + fuss)
        zustand['erinnert'] = str(heute)

    log(f"HumHub {hh}: {len(reif)} Funde ({len(wichtig_offen)} wichtig), {len(neu)} neu"
        + (f", {len(aktuell) - len(reif)} Debian-Updates warten auf unattended-upgrades" if len(aktuell) > len(reif) else '')
        + (f", {len(fehler)} Prüfungen fehlgeschlagen ({'; '.join(fehler)[:300]})" if fehler else ''))
    if PROBE and not (neu or dauerfehler):
        print(abschnitt(list(reif.values())) or 'keine Funde')
    if gesendet:
        zustand['gemeldet'] = sorted(k for k in gemeldet | {b['key'] for b in neu} if k in bekannt)
    zustand_speichern(zustand)


if __name__ == '__main__':
    main()
