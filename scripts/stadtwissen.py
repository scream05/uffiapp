#!/usr/bin/env python3
"""Öffentliche Informationen von www.uffenheim.de als Markdown nach wissen/ holen (Wissen für den KI-Assistenten).

Je Seite eine Datei unter wissen/<URL-Pfad>.md mit Titel und Quell-URL. Dokumente (PDF usw.) werden nur verlinkt.
Seiten, die die Stadt entfernt hat, werden auch hier gelöscht. Danach Commit im Git-Repo wissen/.
Aufruf: scripts/stadtwissen.py  (per Cron täglich)
"""
import base64
import hashlib
import html
import json
import os
import re
import shutil
import struct
import subprocess
import tempfile
import sys
import time
import urllib.request
from datetime import datetime
from html.parser import HTMLParser
import urllib.parse
from urllib.parse import urljoin, urlparse

BASE = 'https://www.uffenheim.de'
ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'wissen')

# Einzelne Seiten und Seiten mit allen Unterseiten (Pfad-Präfix)
PAGES = [
    '/unsere-stadt/geschichten-und-partnerschaften/geschichte-der-stadt',
    '/rathaus-buergerservice/buergerservice/wichtige-adressen',
    '/rathaus-buergerservice/service/im-notfall',
    '/erleben/vereine/vereinsliste-a-z',
    '/leben/kinderbetreuung-bildung/kinderbetreuung',
    '/leben/kinderbetreuung-bildung/schulen-bildungseinrichtungen',
    '/rathaus-buergerservice/ver-und-entsorgung/abfall-wertstoffe',
    '/rathaus-buergerservice/ver-und-entsorgung/wasser-abwasser',
    '/rathaus-buergerservice/ver-und-entsorgung/strom-gas',
    '/rathaus-buergerservice/ver-und-entsorgung/stadtbauhof-strassenmeistereien',
    '/leben/gesundheit-soziales/aerzte-therapeuten',
    '/leben/gesundheit-soziales/apotheken',
    '/leben/gesundheit-soziales/krankenhaeuser-kliniken',
    '/leben/gesundheit-soziales/tiergesundheit',
    '/leben/gesundheit-soziales/soziale-angebote',
    '/leben/gesundheit-soziales/nachbarschaftshilfe',
    '/leben/gesundheit-soziales/richtigen-zahnarzt-finden',
    '/erleben/sport-freizeiteinrichtungen/freibad',
    '/erleben/sport-freizeiteinrichtungen/hallenbad',
    '/erleben/sport-freizeiteinrichtungen/heimatmuseum',
    '/erleben/sport-freizeiteinrichtungen/stadthalle',
    '/leben/kinderbetreuung-bildung/stadtbuecherei',
    '/leben/kinderbetreuung-bildung/buecherbus',
    '/leben/kinderbetreuung-bildung/familienstuetzpunkt-uffenheim',
    '/rathaus-buergerservice/service/terminbuchung',
    '/rathaus-buergerservice/service/mitteilungsblatt',
    '/rathaus-buergerservice/service/rentensprechtag',
    '/rathaus-buergerservice/service/schadensmelder',
    '/rathaus-buergerservice/service/vermietungen',
    '/rathaus-buergerservice/service/stellenausschreibungen',
    '/rathaus-buergerservice/buergerservice/formulare-a-z',
    '/rathaus-buergerservice/buergerservice/formulare-stadt-uffenheim',
    '/rathaus-buergerservice/buergerservice/mit-der-maus-ins-rathaus',
    '/leben/mobilitaet/oeffentliche-verkehrsmittel',
    '/leben/mobilitaet/nea-mobil',
    '/leben/mobilitaet/e-carsharing',
    '/leben/mobilitaet/e-bike-ladestationen-stromtankstelle-fuer-pkw',
    '/leben/mobilitaet/weitere-verkehrsmittel',
    '/leben/mobilitaet/zulassungsstelle-uffenheim/bad-windsheim',
    '/leben/angebote-fuer-kinder-jugendliche/bolz-spielplaetze',
    '/leben/angebote-fuer-kinder-jugendliche/ferienprogramm-und-ferienpass',
    '/leben/angebote-fuer-kinder-jugendliche/jugendarbeit',
    '/leben/angebote-fuer-kinder-jugendliche/staedtischer-jugendteff-juff',
    '/leben/angebote-fuer-senioren-menschen-mit-behinderung/behindertenbeauftragte',
    '/leben/angebote-fuer-senioren-menschen-mit-behinderung/freizeitangebote',
    '/leben/angebote-fuer-senioren-menschen-mit-behinderung/seniorenbeirat',
    '/leben/angebote-fuer-senioren-menschen-mit-behinderung/wohnen-pflege',
    '/leben/angebote-fuer-senioren-menschen-mit-behinderung/neawis-informationsplattform-zu-versorgungsstrukturen-im-landkreis',
    '/rathaus-buergerservice/politik/stadtrat-und-seine-ausschuesse',
    '/rathaus-buergerservice/politik/sitzungskalender',
    '/rathaus-buergerservice/politik/buerger-und-ratsinformationen',
    '/erleben/tourismus/gastronomie-unterkuenfte',
    '/erleben/tourismus/rad-wanderwege',
    '/erleben/tourismus/stadtfuehrungen',
    '/erleben/tourismus/pavillon-am-schloss',
    '/erleben/tourismus/ziele-in-der-umgebung',
    '/erleben/veranstaltungen/feste-in-uffenheim',
    '/erleben/veranstaltungen/maerkte-in-uffenheim',
    '/erleben/vereine/ehrenamt',
    '/leben/mit-gottes-segen/evangelische-kirche',
    '/leben/mit-gottes-segen/katholische-kirche',
    '/leben/mit-gottes-segen/weitere-religioese-gemeinschaften',
]
# (Pfad, Übersichtsseite selbst speichern?)
TREES = [
    ('/erleben/tourismus/rundgang-historische-bauwerke', True),
    ('/rathaus-buergerservice/buergerservice/leistungen-a-z', False),
    ('/rathaus-buergerservice/buergerservice/organisation', False),
    ('/unsere-stadt/leben-findet-stadt/stadtteile', False),
]
# Kontaktangaben, deren Bezeichnung nur im aria-label steht („Telefonnummer: 09842 207-0“)
LABELS = {'Telefonnummer': 'Tel.', 'Faxnummer': 'Fax', 'E-Mail-Adresse': 'E-Mail', 'Raum': 'Raum'}
# Abschnitte der Leistungsbeschreibungen (BayernPortal), die für Bürgerfragen nichts bringen
SKIP_SECTIONS = {'Rechtsgrundlagen', 'Rechtsbehelf', 'Verwandte Themen'}
STAFF = '/rathaus-buergerservice/buergerservice/mitarbeiter-a-z'

# Satzungen: Kategorien mit PDF-Links. Text per pdftotext, bei Scans per Texterkennung (tesseract, deutsch).
# Die PDFs werden nicht gespeichert, nur der Text; Cache (Änderungsdatum/Größe → Text) in wissen/.satzungen-cache.json
SATZUNGEN = '/rathaus-buergerservice/buergerservice/satzungen-verordnungen'
SATZUNGEN_CACHE = os.path.join(ROOT, '.satzungen-cache.json')
SATZUNG_HINWEIS = ('Text automatisch aus dem PDF der Stadt erzeugt, bei eingescannten Dokumenten per Texterkennung. '
                   'Lesefehler (v. a. bei Zahlen, Beträgen, §-Zeichen) sind möglich, verbindlich ist das Original-PDF.')

# Suchindex für den KI-Assistenten: Seiten in Abschnitte zerlegt (an „## “-Überschriften, bei Satzungen je Paragraf),
# je Abschnitt ein Embedding für die Suche nach Bedeutung, berechnet vom lokalen Dienst „embeddings“ (compose.yml);
# nur neue/geänderte Abschnitte werden berechnet
EMBED_URL = 'http://127.0.0.1:8095/embed'
INDEX_FILE = os.path.join(ROOT, '.abschnitte.json')
# Kleine Abschnitte werden bis etwa CHUNK_TARGET Zeichen zusammengefasst, große ab CHUNK_MAX geteilt
CHUNK_TARGET = 1800
CHUNK_MAX = 3000
# Seiten mit (möglichen) Namen oder privaten Kontaktdaten lebender Personen kommen nicht ins Git (.git/info/exclude),
# damit Personen, die die Stadt entfernt, auch aus dem Verlauf verschwinden. Im Zweifel lieber ausschließen.
PERSON_HINTS = re.compile(
    r'\b(?:Herrn?|Frau|Dr\.(?: med\.)?(?: dent\.)?|Pfarrer(?:in)?|Diakon(?:in)?)\s+[A-ZÄÖÜ][a-zäöüß]+'  # Anrede + Name
    r'|\b(?:Leitung|Ansprech\w*|Vorsitz\w*|Inhaber\w*|\w*[Bb]eauftragte\w*|Ortssprecher\w*|Kontakt)\s*:\s*[A-ZÄÖÜ]'  # Rolle: Name
    r'|\b(?!poststelle|info|kontakt|service|verwaltung|buero|office)[a-z]+\.[a-z]+\d*@')  # vorname.nachname@
# Historische Personen (Rundgang, Stadtgeschichte) dürfen im Git bleiben
HISTORIC = ('erleben/tourismus/rundgang-historische-bauwerke', 'unsere-stadt/geschichten-und-partnerschaften/geschichte-der-stadt')

# Widersprüche (Art. 21 DSGVO): je Zeile ein Name, eine Telefonnummer o. Ä.; Zeilen, die das enthalten,
# werden aus allen Seiten entfernt. Datei nicht im Git (enthält selbst Personendaten).
BLOCKLIST = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'wissen-sperrliste.txt')


def fetch(path):
    req = urllib.request.Request(urljoin(BASE, path), headers={'User-Agent': 'community.rundumuffni.de Stadtwissen'})
    with urllib.request.urlopen(req, timeout=30) as r:
        body = r.read().decode('utf-8')
    time.sleep(1)  # Website der Stadt schonen
    return body


def clean(s):
    return re.sub(r'\s+', ' ', html.unescape(s)).strip()


class MainToMarkdown(HTMLParser):
    """Inhalt von <main> als einfaches Markdown: Überschriften, Absätze, Listen, Links (absolut)."""
    BLOCK = {'p', 'div', 'li', 'tr', 'h1', 'h2', 'h3', 'h4', 'br', 'section', 'article', 'ul', 'ol', 'table', 'figure'}

    def __init__(self, url):
        super().__init__()
        self.url, self.out, self.skip, self.skiptag, self.main, self.href, self.link = url, [], 0, None, False, None, []

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == 'main':
            self.main = True
        if self.skip:
            # Innerhalb eines übersprungenen Elements nur gleichnamige Tags mitzählen
            if tag == self.skiptag:
                self.skip += 1
            return
        classes = set((attrs.get('class') or '').split())
        section = attrs.get('aria-label') if 'pubserv-services-detail--list--item' in classes else None
        if tag in ('script', 'style', 'noscript', 'svg', 'button') or {'sr-only', 'visually-hidden'} & classes \
                or section in SKIP_SECTIONS:
            self.skip, self.skiptag = 1, tag
            return
        if not self.main:
            return
        if section:
            self.out.append(f'\n## {section}\n')
        label = re.match(r'(\w[\w-]*): (.+)', attrs.get('aria-label') or '')
        if tag in ('a', 'li') and label and label.group(1) in LABELS:
            self.out.append(f'{"- " if tag == "li" else ""}{LABELS[label.group(1)]} {clean(label.group(2))}')
            self.skip, self.skiptag = 1, tag
            return
        if tag in self.BLOCK:
            self.out.append('\n')
        if tag in ('h1', 'h2', 'h3', 'h4'):
            self.out.append('#' * int(tag[1]) + ' ')
        elif tag == 'li':
            self.out.append('- ')
        elif tag in ('td', 'th'):
            self.out.append(' | ')
        elif tag == 'a' and attrs.get('href') and not attrs['href'].startswith(('#', 'javascript')):
            self.href, self.link = urljoin(self.url, attrs['href']), []

    def handle_endtag(self, tag):
        if self.skip:
            if tag == self.skiptag:
                self.skip -= 1
            return
        if tag == 'main':
            self.main = False
        if tag == 'a' and self.href is not None:
            text = clean(''.join(self.link))
            href, self.href = self.href, None
            if text and not href.startswith(('tel:', 'mailto:')):
                self.out.append(f'[{text}]({href})')
            elif text:
                self.out.append(text)
        elif tag in self.BLOCK and self.main:
            self.out.append('\n')

    def handle_data(self, data):
        if not self.main or self.skip:
            return
        (self.link if self.href is not None else self.out).append(data)

    def markdown(self):
        text = ''.join(self.out)
        lines = []
        for line in text.split('\n'):
            line = re.sub(r'[ \t\xa0]+', ' ', html.unescape(line)).strip()
            line = re.sub(r'</?p>', '', line).strip()
            # Kartenlinks und Buchstabenleiste (A-Z) der Adresslisten sind nur Ballast
            line = re.sub(r'\[(In Karte anzeigen|Route planen)\]\([^)]*\)', '', line).strip()
            # Kategorie-Filter der Vereinsliste („Alle KategorienFreiwillige Feuerwehr…“)
            if line.startswith('Alle Kategorien'):
                continue
            if line in ('', '-', '|') or line in ('Website vorlesen', '# Leistung Detail', '0-9') \
                    or re.fullmatch(r'[A-Z]', line) or line.startswith('[Alle Leistungen]('):
                continue
            # Abschnittstitel steht bei den Leistungen zusätzlich als Text unter der Überschrift
            if lines and lines[-1] == '## ' + line:
                continue
            line = re.sub(r'^(\|\s*)+', '| ', line)
            lines.append(line)
        # Dateiangaben bei Downloads („(Dateiname: …, Dateigröße: …)“) kürzen
        text = '\n'.join(lines)
        text = re.sub(r'\s*\(Dateiname: [^)]*\)', '', text)
        return re.sub(r'\n(#+ )', r'\n\n\1', text).strip()


def page_markdown(path, html_text):
    p = MainToMarkdown(urljoin(BASE, path))
    p.feed(html_text)
    md = p.markdown()
    m = re.match(r'# (.+)\n?', md)
    title = m.group(1).strip() if m else path.rsplit('/', 1)[-1]
    return title, (md[m.end():].strip() if m else md)


def links_below(path, html_text):
    """Unterseiten-Links im Inhaltsbereich, die mit path beginnen."""
    main = html_text[html_text.find('<main'):html_text.find('</main>')]
    found = []
    for href in re.findall(r'href="([^"#?]+)"', main):
        href = urlparse(urljoin(BASE, href)).path.rstrip('/')
        if href.startswith(path + '/') and href not in found:
            found.append(href)
    return found


def contact_and_hours(html_text):
    """Kontakt und Öffnungszeiten des Rathauses aus dem Seitenfuß."""
    footer = html_text[html_text.find('<footer'):]
    footer = re.sub(r'<(script|style|svg)\b.*?</\1>', '', footer, flags=re.S)
    lines = [clean(t) for t in re.sub(r'<[^>]+>', '\n', footer).split('\n')]
    lines = [l for l in lines if l]
    try:
        end = lines.index('Quicklinks')
    except ValueError:
        raise RuntimeError('Seitenfuß hat sich geändert (kein „Quicklinks“)')
    lines = lines[:end]
    if 'Öffnungszeiten' not in lines:
        raise RuntimeError('Seitenfuß hat sich geändert (keine „Öffnungszeiten“)')
    i = lines.index('Öffnungszeiten')
    contact = [l for l in lines[:i] if l.lower() != 'instagram']
    out = ['## Kontakt', '- ' + ', '.join(contact[:4])]
    rest = contact[4:]
    labels = ['Telefon', 'Fax', 'E-Mail']
    for label, value in zip(labels, rest[:3]):
        out.append(f'- {label}: {value}')
    for j in range(3, len(rest) - 1, 2):
        out.append(f'- {rest[j]}: {rest[j + 1]}')
    out += ['', '## Öffnungszeiten']
    hours = lines[i + 1:]
    j = 0
    while j < len(hours):
        if hours[j].endswith(':') and j + 1 < len(hours) and not hours[j + 1].endswith(':'):
            out.append(f'- {hours[j]} {hours[j + 1]}')
            j += 2
        else:
            out.append(hours[j] if hours[j] != 'Terminvereinbarung' else '\n## Terminvereinbarung')
            j += 1
    text = '\n'.join(out)
    return re.sub(r'(?<=Link)\n(https?://\S+)\n', r' \1 ', text)


def staff(html_text):
    """Mitarbeiter A-Z: je Person Name, Aufgabe, Abteilung, Telefon, Fax, E-Mail, Raum."""
    html_text = re.sub(r'<svg\b.*?</svg>', '', html_text, flags=re.S)
    people = []
    for block in re.split(r'class="pubserv-persons-list--item"', html_text)[1:]:
        name = re.search(r'--head--name">\s*(.*?)\s*</div>', block, re.S)
        if not name:
            continue
        p = {'name': clean(name.group(1))}
        for key, cls in (('aufgabe', 'pubserv-person--title'), ('abteilung', 'pubserv-person-function')):
            m = re.search(rf'class="{cls}">(.*?)</p>', block, re.S)
            if m:
                p[key] = clean(re.sub(r'<[^>]+>', ' ', m.group(1)))
        for key, label in (('telefon', 'Telefonnummer'), ('fax', 'Faxnummer'), ('email', 'E-Mail-Adresse'), ('raum', 'Raum')):
            m = re.search(rf'aria-label="{label}: ([^"]+)"', block)
            if m:
                p[key] = clean(m.group(1))
        people.append(p)
    if len(people) < 10:
        raise RuntimeError(f'Nur {len(people)} Mitarbeiter erkannt, Seitenaufbau geändert?')
    out = []
    for p in people:
        parts = [p.get('aufgabe'), p.get('abteilung')]
        line = f"- **{p['name']}**" + (': ' + ', '.join(x for x in parts if x) if any(parts) else '')
        details = [f"Tel. {p['telefon']}" if 'telefon' in p else None, f"Fax {p['fax']}" if 'fax' in p else None,
                   p.get('email'), f"Raum {p['raum']}" if 'raum' in p else None]
        out.append(line + (' – ' + ', '.join(d for d in details if d) if any(details) else ''))
    return '\n'.join(out), len(people)


def pdf_text(url):
    """Text eines PDFs; ohne Textebene (Scan) per Texterkennung. Liefert (Text, per_ocr)."""
    tmp = tempfile.mkdtemp()
    try:
        pdf = os.path.join(tmp, 'd.pdf')
        req = urllib.request.Request(url, headers={'User-Agent': 'community.rundumuffni.de Stadtwissen'})
        with urllib.request.urlopen(req, timeout=60) as r, open(pdf, 'wb') as f:
            shutil.copyfileobj(r, f)
        time.sleep(1)
        text = subprocess.run(['pdftotext', '-layout', pdf, '-'], capture_output=True, text=True).stdout
        if len(re.sub(r'\s', '', text)) >= 200:
            return text, False
        subprocess.run(['pdftoppm', '-r', '300', '-gray', pdf, os.path.join(tmp, 'p')], check=True)
        pages = sorted(f for f in os.listdir(tmp) if f.endswith('.pgm'))
        text = '\n'.join(subprocess.run(['tesseract', os.path.join(tmp, f), '-', '-l', 'deu', '--psm', '6'],
                                        capture_output=True, text=True).stdout for f in pages)
        return text, True
    finally:
        shutil.rmtree(tmp, ignore_errors=True)


def clean_statute(text):
    """Typische Lesefehler der Texterkennung korrigieren, Paragraphen als Abschnitte (## § n)."""
    text = text.replace('\f', '\n')
    text = re.sub(r'\$ ?(?=\d)', '§ ', text)                     # „$ 2“ → „§ 2“
    text = re.sub(r'\(8 (\d+[a-z]?)\)', r'(§ \1)', text)           # „(8 4)“ → „(§ 4)“
    text = re.sub(r'(\d,(?:--|-|\d\d)) ?£', r'\1 €', text)          # „100,-- £“ → „100,-- €“
    text = re.sub(r'(?m)^[ \t]*\|[ \t]*', '', text)               # „| Titel“: Strich am Zeilenanfang (Rand im Scan)
    text = re.sub(r'(\w)-\n(\w)', r'\1\2', text)                  # Silbentrennung am Zeilenende
    lines, out = [l.strip() for l in text.split('\n')], []
    for i, line in enumerate(lines):
        m = re.fullmatch(r'(?:## )?(?:§|8) ?(\d{1,3}[a-z]?)', line)  # „## “: schon bereinigter Text aus dem Cache
        nxt = lines[i + 1] if i + 1 < len(lines) else ''
        if m and nxt and nxt[0].isupper() and not re.match(r'\d', nxt):
            out.append(f'\n## § {m.group(1)}')
        elif line:
            out.append(line)
    return re.sub(r'[ \t]{2,}', ' ', '\n'.join(out)).strip()


def statutes():
    """Alle Satzungen: [(rel, titel, pdf_url, text)]. Text nur neu holen, wenn Änderungsdatum/Größe anders."""
    cache = json.load(open(SATZUNGEN_CACHE)) if os.path.exists(SATZUNGEN_CACHE) else {}
    top = fetch(SATZUNGEN)
    categories = dict((href, clean(title)) for title, href in re.findall(
        r'title="([^"]+)"\s+href="(' + re.escape(SATZUNGEN) + r'/Ort/[^"]+/Stichwort/[^"]+)"', top))
    found, new_cache, ocr = [], {}, 0
    for cat, cat_name in sorted(categories.items()):
        page = fetch(cat)
        main = page[page.find('<main'):page.find('</main>')]
        for href, title in re.findall(r'href="([^"]+\.pdf)"[^>]*>.*?</a>\s*(?:<[^>]+>\s*)*([^<]+)<', main, re.S):
            url = urljoin(BASE, html.unescape(href))
            title = clean(title)
            if not title or any(f[2] == url for f in found):
                continue
            head = urllib.request.Request(url, method='HEAD', headers={'User-Agent': 'community.rundumuffni.de Stadtwissen'})
            with urllib.request.urlopen(head, timeout=30) as r:
                stamp = f"{r.headers.get('Last-Modified')}|{r.headers.get('Content-Length')}"
            if cache.get(url, {}).get('stamp') == stamp:
                # Bereinigung erneut, damit Verbesserungen an clean_statute auch für alte PDFs gelten
                text, by_ocr = clean_statute(cache[url]['text']), cache[url]['ocr']
            else:
                print(f'  Satzung neu einlesen: {title}', flush=True)
                raw, by_ocr = pdf_text(url)
                text = clean_statute(raw)
                ocr += by_ocr
            new_cache[url] = {'stamp': stamp, 'text': text, 'ocr': by_ocr}
            name = urllib.parse.unquote(url.rsplit('/', 1)[-1]).lower().replace('.pdf', '')
            for a, b in (('ä', 'ae'), ('ö', 'oe'), ('ü', 'ue'), ('ß', 'ss')):
                name = name.replace(a, b)
            slug = re.sub(r'[^a-z0-9]+', '-', name)
            rel = f"{SATZUNGEN.strip('/')}/{cat.rsplit('/', 1)[-1]}/{slug.strip('-')}.md"
            found.append((rel, f'Satzung: {title} ({cat_name})', url, text))
    with open(SATZUNGEN_CACHE + '.tmp', 'w') as f:
        json.dump(new_cache, f)
    os.replace(SATZUNGEN_CACHE + '.tmp', SATZUNGEN_CACHE)
    return found, ocr


def is_entry_start(lines, i):
    """Listeneintrag beginnt mit einer Zeile ohne „- “, auf die „- “-Zeilen (Adresse, Telefon …) folgen."""
    return not lines[i].startswith('- ') and i + 1 < len(lines) and lines[i + 1].startswith('- ')


def split_list(body, size=4000):
    """Lange Listen ohne Abschnitte (Vereinsliste) zwischen zwei Einträgen in Teile von etwa size Zeichen teilen,
    damit der KI-Assistent den passenden Teil findet und nichts abgeschnitten wird.
    Liefert [(erster Eintrag, letzter Eintrag, Text)]."""
    if len(body) <= 2 * size or '\n## ' in body:
        return [(None, None, body)]
    lines = body.split('\n')
    intro, entries = [], []
    for i, line in enumerate(lines):
        if is_entry_start(lines, i):
            entries.append([line])
        elif entries:
            entries[-1].append(line)
        else:
            intro.append(line)
    if len(entries) < 10:
        return [(None, None, body)]  # Fließtext, keine Liste
    parts, cur = [], []
    for e in entries:
        if cur and sum(len('\n'.join(x)) for x in cur) + len('\n'.join(e)) > size:
            parts.append(cur)
            cur = []
        cur.append(e)
    if cur:
        parts.append(cur)
    head = '\n'.join(intro).strip()
    return [(p[0][0], p[-1][0], (head + '\n\n' if head else '') + '\n'.join('\n'.join(e) for e in p)) for p in parts]


def exclude_personal(rels):
    """Seiten mit Personenhinweisen in .git/info/exclude eintragen und, falls schon versioniert, aus dem Index nehmen."""
    personal = sorted(rel for rel in rels if not rel.startswith(HISTORIC)
                      and PERSON_HINTS.search(open(os.path.join(ROOT, rel), encoding='utf-8').read().split('---', 2)[2]))
    with open(os.path.join(ROOT, '.git', 'info', 'exclude'), 'w') as f:
        f.write('# Von scripts/stadtwissen.py erzeugt: Seiten mit Personendaten, nicht versionieren\n' + '\n'.join(personal) + '\n')
    tracked = set(subprocess.run(['git', '-C', ROOT, 'ls-files'], capture_output=True, text=True, check=True).stdout.split('\n'))
    for rel in personal:
        if rel in tracked:
            subprocess.run(['git', '-C', ROOT, 'rm', '-q', '--cached', rel], check=True)
            print(f'! {rel}: enthält Personenhinweise, nicht mehr versioniert (ältere Stände ggf. im Verlauf)', file=sys.stderr)
    return personal


def blocked():
    if not os.path.exists(BLOCKLIST):
        return []
    return [l.strip().lower() for l in open(BLOCKLIST, encoding='utf-8') if l.strip() and not l.startswith('#')]


def write(rel, title, url, body, always=False, extra=None):
    block = blocked()
    if block:
        body = '\n'.join(l for l in body.split('\n') if not any(b in l.lower() for b in block))
    path = os.path.join(ROOT, rel)
    os.makedirs(os.path.dirname(path), exist_ok=True)
    text = (f'---\ntitel: {title}\nquelle: {url}\n' + ('immer: ja\n' if always else '')
            + ''.join(f'{k}: {v}\n' for k, v in (extra or {}).items()) + f'---\n\n# {title}\n\n{body}\n')
    old = open(path, encoding='utf-8').read() if os.path.exists(path) else None
    if old != text:
        with open(path, 'w', encoding='utf-8') as f:
            f.write(text)
    return rel, old is None, old is not None and old != text


def clean_chunk_text(text):
    """Ballast für die KI entfernen: leere Überschriften, direkt doppelte Zeilen (Bildunterschriften), viele Leerzeilen."""
    out = []
    for line in text.split('\n'):
        if re.fullmatch(r'#+\s*', line) or (line.strip() and out and line == out[-1]):
            continue
        out.append(line)
    return re.sub(r'\n{3,}', '\n\n', '\n'.join(out)).strip()


def section_label(part):
    """Überschrift eines Abschnitts; bei Satzungen „§ 25 Ruhezeiten“ (Titel steht in der Zeile nach „## § 25“)."""
    lines = [l for l in part.split('\n') if l.strip()]
    if not lines or not lines[0].startswith('## '):
        return ''
    label = lines[0][3:].strip()
    if re.fullmatch(r'§\s*\d+\s*\w?', label) and len(lines) > 1 and len(lines[1]) < 80 and not lines[1].startswith('('):
        label += ' ' + lines[1].strip()
    return label


def split_long(part, limit):
    """Zu langen Abschnitt teilen: an Leerzeilen und Absätzen „(2)“, notfalls an Zeilen."""
    lines = []
    for block in re.split(r'\n(?=\n|\()', part):
        lines += [block] if len(block) <= limit else block.split('\n')
    pieces, cur = [], ''
    for line in lines:
        if cur and len(cur) + len(line) + 1 > limit:
            pieces.append(cur.strip())
            cur = ''
        cur = f'{cur}\n{line}' if cur else line
    if cur.strip():
        pieces.append(cur.strip())
    return pieces


def chunks(body):
    """Seitentext → [(Überschrift, Text)] in Seitenreihenfolge."""
    body = clean_chunk_text(re.sub(r'^# .*\n+', '', body))
    sections = [p for p in re.split(r'\n(?=## )', body) if p.strip()]
    out, cur, labels = [], '', []
    for sec in sections:
        label = section_label(sec)
        if len(sec) > CHUNK_MAX:
            if cur:
                out.append((' / '.join(l for l in labels if l), cur))
                cur, labels = '', []
            for k, piece in enumerate(split_long(sec, CHUNK_MAX)):
                out.append((label + (' (Fortsetzung)' if k else ''), piece))
            continue
        if cur and len(cur) + len(sec) > CHUNK_TARGET:
            out.append((' / '.join(l for l in labels if l), cur))
            cur, labels = '', []
        cur = f'{cur}\n\n{sec}' if cur else sec
        labels.append(label)
    if cur:
        out.append((' / '.join(l for l in labels if l), cur))
    return out


def embed_batch(texts):
    """Normierte Vektoren (float32, base64) vom lokalen Embedding-Dienst; liefert (Modell, Vektoren)."""
    req = urllib.request.Request(EMBED_URL, data=json.dumps({'texte': texts, 'art': 'dokument'}).encode(),
                                 headers={'Content-Type': 'application/json'})
    with urllib.request.urlopen(req, timeout=600) as r:
        result = json.load(r)
    return result['modell'], [base64.b64encode(struct.pack(f'<{len(v)}f', *v)).decode() for v in result['vektoren']]


def embed_model():
    """Name des Modells im Embedding-Dienst oder None, wenn der Dienst nicht läuft"""
    try:
        with urllib.request.urlopen(EMBED_URL.rsplit('/', 1)[0] + '/', timeout=10) as r:
            return json.load(r)['modell']
    except OSError:
        return None


def update_index(rels):
    """Abschnitte aller Seiten mit Vektoren (float32, base64) in .abschnitte.json; liefert Anzahl neu berechneter."""
    model = embed_model()
    if model is None:
        print('! Embedding-Dienst nicht erreichbar, nur Stichwortsuche möglich', file=sys.stderr)
    old = {}
    if os.path.exists(INDEX_FILE):
        old = {c['hash']: c['vec'] for c in json.load(open(INDEX_FILE))['abschnitte'] if c.get('vec')}
    entries, todo = [], []
    for rel in sorted(rels):
        raw = open(os.path.join(ROOT, rel), encoding='utf-8').read()
        head, body = raw.split('---', 2)[1:]
        meta = dict(l.split(': ', 1) for l in head.strip().split('\n') if ': ' in l)
        for nr, (label, text) in enumerate(chunks(body.strip())):
            embed_text = f"{meta['titel']}\n{label}\n{text}"[:6000]
            digest = hashlib.sha256(f'{model}\n{embed_text}'.encode()).hexdigest()[:16]
            entry = {'rel': rel, 'titel': meta['titel'], 'quelle': meta.get('quelle', ''), 'immer': meta.get('immer') == 'ja',
                     'nr': nr, 'abschnitt': label, 'text': text, 'hash': digest, 'vec': old.get(digest)}
            if meta.get('space'):
                # Community-Inhalt: KI-Assistent zeigt ihn nur Mitgliedern des Space (oder allen, wenn öffentlich)
                entry['space'] = int(meta['space'])
                entry['oeffentlich'] = meta.get('oeffentlich') == 'ja'
            entries.append(entry)
            if not entry['vec'] and model:
                todo.append((entry, embed_text))
    for i in range(0, len(todo), 16):
        batch = todo[i:i + 16]
        used, vectors = embed_batch([t for _, t in batch])
        if used != model:
            raise RuntimeError(f'Embedding-Dienst hat das Modell gewechselt ({model} → {used})')
        for (entry, _), vec in zip(batch, vectors):
            entry['vec'] = vec
    tmp = INDEX_FILE + '.tmp'
    with open(tmp, 'w') as f:
        json.dump({'modell': model, 'abschnitte': entries}, f, ensure_ascii=False, separators=(',', ':'))
    os.replace(tmp, INDEX_FILE)
    old_file = os.path.join(ROOT, '.embeddings.json')
    if os.path.exists(old_file):
        os.remove(old_file)
    return len(todo)


def db_json(sql):
    """SQL im DB-Container, je Zeile ein JSON-Objekt"""
    out = subprocess.run(['docker', 'compose', 'exec', '-T', 'db', 'sh', '-c',
                          'mariadb -N -B --raw -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"'],
                         input=sql, capture_output=True, text=True, check=True, cwd=os.path.dirname(ROOT)).stdout
    return [json.loads(line) for line in out.splitlines() if line.strip()]


def plain_markdown(text):
    """HumHub-Markdown vereinfachen: Bilder/Dateien und Erwähnungen entfernen"""
    text = re.sub(r'!\[[^\]]*\]\([^)]*\)', '', text or '')
    text = re.sub(r'\[([^\]]*)\]\(mention:[^)]*\)', r'\1', text)
    text = re.sub(r'\[([^\]]*)\]\(file-guid:[^)]*\)', r'\1', text)
    return re.sub(r'\n{3,}', '\n\n', text).strip()


def community_inhalte():
    """Wiki-Seiten, Fragen mit Antworten und Beiträge aus den Spaces (ohne Autorennamen) als
    [(rel, titel, url, text, extra)]. Ausgenommen: Mängelmelder (eigener Teil im KI-Kontext), Abfall, automatisch
    übernommene Stadt-Nachrichten. „space“ und „oeffentlich“ steuern, wem der KI-Assistent den Inhalt zeigt."""
    base = 'https://community.rundumuffni.de/s/'
    common = ("FROM content c JOIN space s ON s.contentcontainer_id = c.contentcontainer_id "
              "WHERE c.state = 1 AND s.status = 1 AND s.url NOT IN ('mangelmelder', 'abfall-and-termine') ")
    vis = "'space', s.id, 'surl', s.url, 'sname', s.name, 'offen', (c.visibility = 1 AND s.visibility >= 1), 'cid', c.id"
    found = []
    # Wiki ist abgeschaltet (Modul deaktiviert, Tabellen entfernt) – nur abfragen, wenn es wieder da ist
    wiki_da = db_json("SELECT JSON_OBJECT('n', COUNT(*)) FROM information_schema.tables "
                      "WHERE table_schema = DATABASE() AND table_name = 'wiki_page'")[0]['n'] > 0
    for r in (db_json(f"SELECT JSON_OBJECT({vis}, 'id', w.id, 'titel', w.title, 'text', rv.content, 'datum', c.updated_at) "
                     "FROM wiki_page w JOIN wiki_page_revision rv ON rv.wiki_page_id = w.id AND rv.is_latest = 1 "
                     f"JOIN content c ON c.object_id = w.id AND c.object_model LIKE '%WikiPage' "
                     + common.replace('FROM content c ', '') + "AND w.admin_only = 0") if wiki_da else []):
        url = f"{base}{r['surl']}/wiki/page/view?id={r['id']}"
        found.append((f"community/inhalte/{r['surl']}/wiki-{r['id']}.md", f"Wiki „{r['titel']}“ ({r['sname']})", url,
                      f"Stand: {r['datum'][:10]}\n\n{plain_markdown(r['text'])}", r))
    for r in db_json(f"SELECT JSON_OBJECT({vis}, 'id', q.id, 'frage', q.question, 'text', q.description, 'datum', c.created_at, "
                     "'antworten', (SELECT JSON_ARRAYAGG(JSON_OBJECT('text', a.answer, 'best', a.is_best, 'votes', a.votes_summary)) "
                     "FROM question_answer a WHERE a.question_id = q.id)) "
                     "FROM question q JOIN content c ON c.object_id = q.id AND c.object_model LIKE '%models%Question' "
                     + common.replace('FROM content c ', '')):
        answers = sorted(r.get('antworten') or [], key=lambda a: (-int(a.get('best') or 0), -int(a.get('votes') or 0)))
        body = f"Gefragt am {r['datum'][:10]}\n\n{plain_markdown(r['text'])}\n\n## Antworten\n" + (
            '\n\n'.join(('(beste Antwort) ' if a.get('best') else '') + plain_markdown(a['text']) for a in answers) or '(noch keine Antwort)')
        found.append((f"community/inhalte/{r['surl']}/frage-{r['id']}.md", f"Frage: {r['frage']} ({r['sname']})",
                      f"{base}{r['surl']}/questions/question/view?id={r['id']}", body, r))
    for r in db_json(f"SELECT JSON_OBJECT({vis}, 'id', p.id, 'text', p.message, 'datum', c.created_at) "
                     "FROM post p JOIN content c ON c.object_id = p.id AND c.object_model LIKE '%models%Post' "
                     + common.replace('FROM content c ', '')
                     + "AND c.created_by NOT IN (SELECT id FROM user WHERE username = 'stadt-uffenheim') "
                     "AND c.created_at > NOW() - INTERVAL 1 YEAR"):
        text = plain_markdown(r['text'])
        if len(text) < 40:
            continue
        first = re.sub(r'[*#_>]', '', text.split('\n')[0])[:80].strip()
        found.append((f"community/inhalte/{r['surl']}/beitrag-{r['id']}.md", f"Beitrag „{first}“ ({r['sname']})",
                      f"https://community.rundumuffni.de/content/perma?id={r['cid']}", f"Vom {r['datum'][:10]}\n\n{text}", r))
    return found


def community_vereine():
    """Vereinsseiten aus dem Vereinsverzeichnis der Community (Datenbank) als [(rel, titel, url, text)]."""
    sql = ("SELECT JSON_OBJECT('id', v.id, 'name', v.name, 'kategorie', v.kategorie, 'ortsteil', v.ortsteil, "
           "'beschreibung', v.beschreibung, 'treffpunkt', v.treffpunkt, 'website', v.website, 'email', v.email, "
           "'telefon', v.telefon, 'ansprechpartner', v.ansprechpartner, 'social', v.social, 'status', v.status, "
           "'space', (SELECT s.url FROM space s JOIN contentcontainer_module m ON m.contentcontainer_id = s.contentcontainer_id "
           "AND m.module_id = 'vereine' AND m.module_state = 1 LIMIT 1)) FROM verein v WHERE v.status != 'vorgeschlagen'")
    out = subprocess.run(['docker', 'compose', 'exec', '-T', 'db', 'sh', '-c',
                          'mariadb -N -B --raw -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"'],
                         input=sql, capture_output=True, text=True, check=True, cwd=os.path.dirname(ROOT)).stdout
    found = []
    for line in out.splitlines():
        v = json.loads(line)
        if not v.get('space'):
            continue
        url = f"https://community.rundumuffni.de/s/{v['space']}/vereine/verein/view?id={v['id']}"
        lines = [f"Kategorie: {v['kategorie']} · Ort: {v['ortsteil']}"]
        if v['status'] == 'offen' and not v.get('beschreibung'):
            lines.append('Vom Verein noch nicht gepflegt.')
        if v.get('beschreibung'):
            lines += ['', v['beschreibung'].strip()]
        contact = [f'- {label}: {v[key]}' for key, label in (('treffpunkt', 'Treffpunkt/Vereinsheim'), ('website', 'Website'),
                                                              ('email', 'E-Mail'), ('telefon', 'Telefon')) if v.get(key)]
        for s in json.loads(v['social'] or '[]'):
            contact.append(f"- {s.get('typ') or 'Link'}: {s.get('url')}")
        for p in json.loads(v['ansprechpartner'] or '[]'):
            extra = ', '.join(x for x in (p.get('email'), p.get('telefon')) if x)
            contact.append(f"- {p.get('funktion') or 'Ansprechpartner'}: {p.get('name')}" + (f' ({extra})' if extra else ''))
        if contact:
            lines += ['', '## Kontakt', *contact]
        slug = re.sub(r'[^a-z0-9]+', '-', v['name'].lower().translate(str.maketrans({'ä': 'ae', 'ö': 'oe', 'ü': 'ue', 'ß': 'ss'}))).strip('-')
        found.append((f"community/vereine/{slug}-{v['id']}.md", f"Verein: {v['name']} ({v['ortsteil']})", url, '\n'.join(lines)))
    return found


def main():
    written = []
    home = fetch('/')
    written.append(write('rathaus-kontakt-oeffnungszeiten.md', 'Stadtverwaltung Uffenheim: Kontakt und Öffnungszeiten',
                         BASE + '/', contact_and_hours(home), always=True))
    body, n = staff(fetch(STAFF))
    written.append(write(STAFF.strip('/') + '.md', 'Mitarbeiter der Stadtverwaltung (A-Z)', BASE + STAFF,
                         'Ansprechpersonen im Rathaus mit Aufgabe, Abteilung und dienstlichen Kontaktdaten.\n\n' + body,
                         always=True))
    paths = list(PAGES)
    for tree, with_root in TREES:
        top = fetch(tree)
        if with_root:
            paths.append((tree, top))
        paths += [p for p in links_below(tree, top) if p not in paths]
    for item in paths:
        path, text = item if isinstance(item, tuple) else (item, fetch(item))
        title, body = page_markdown(path, text)
        if len(body) < 50:
            print(f'! {path}: kaum Inhalt, übersprungen', file=sys.stderr)
            continue
        parts = split_list(body)
        if len(parts) == 1:
            written.append(write(path.strip('/') + '.md', title, BASE + path, body))
            continue
        # Übersicht nur mit den Namen, damit „Gibt es einen …verein?“ vollständig beantwortet werden kann
        names = [re.sub(r'\s+-\s+.*$', '', l) for i, l in enumerate(body.split('\n')) if is_entry_start(body.split('\n'), i)]
        written.append(write(f"{path.strip('/')}-uebersicht.md", f'{title}: Übersicht aller Einträge (nur Namen)',
                             BASE + path, f'{len(names)} Einträge. Kontaktdaten stehen in den Teilen der Liste.\n\n'
                             + '\n'.join(f'- {n}' for n in names)))
        for k, (first, last, part) in enumerate(parts, 1):
            first, last = first.split(' - ')[0], last.split(' - ')[0]
            written.append(write(f"{path.strip('/')}-teil-{k}.md", f'{title} (Teil {k}: {first} bis {last})',
                                 BASE + path, part))

    # Satzungen (Text aus den PDFs); Quelle ist das PDF selbst
    found, ocr_count = statutes()
    for rel, title, url, text in found:
        if len(text) < 100:
            print(f'! {url}: kein lesbarer Text', file=sys.stderr)
            continue
        written.append(write(rel, title, url, f'{SATZUNG_HINWEIS}\nÜbersicht aller Satzungen: {BASE + SATZUNGEN}\n\n{text}'))

    # Vereinsverzeichnis der Community (Seiten mit Ansprechpartnern, daher wissen/community/ nicht im Git)
    try:
        for rel, title, url, text in community_vereine():
            written.append(write(rel, title, url, text))
    except Exception as e:
        print(f'! Vereinsverzeichnis nicht gelesen: {e}', file=sys.stderr)
        # Bisherige Dateien behalten, statt sie als „nicht mehr vorhanden“ zu löschen
        for d, _, files in os.walk(os.path.join(ROOT, 'community', 'vereine')):
            written += [(os.path.relpath(os.path.join(d, f), ROOT), False, False) for f in files if f.endswith('.md')]

    # Wiki, Fragen und Beiträge der Community (nicht im Git, Zugriff je Space im KI-Assistenten)
    written += community_written()

    # Was die Stadt nicht mehr hat, auch hier löschen
    keep = {w[0] for w in written}
    removed = 0
    for d, _, files in os.walk(ROOT):
        if '/.git' in d + '/':
            continue
        for f in files:
            rel = os.path.relpath(os.path.join(d, f), ROOT)
            if f.endswith('.md') and rel not in keep and rel != 'README.md':
                os.remove(os.path.join(d, f))
                removed += 1
    new = sum(1 for w in written if w[1])
    changed = sum(1 for w in written if w[2])
    try:
        embedded = update_index(keep)
    except Exception as e:
        embedded = 0
        print(f'! Suchindex nicht aktualisiert: {e}', file=sys.stderr)
    summary = (f'{len(written)} Seiten ({n} Mitarbeiter, {len(found)} Satzungen, {ocr_count} per Texterkennung neu), '
               f'{new} neu, {changed} geändert, {removed} gelöscht')
    exclude_personal(keep)
    # Commit nur, wenn sich versionierte Dateien geändert haben (Seiten mit Personendaten sind ausgenommen)
    subprocess.run(['git', '-C', ROOT, 'add', '-A'], check=True)
    if subprocess.run(['git', '-C', ROOT, 'diff', '--cached', '--quiet']).returncode:
        subprocess.run(['git', '-C', ROOT, 'commit', '-qm', f'Abgleich mit uffenheim.de: {summary}'], check=True)
    print(f'{datetime.now():%Y-%m-%d %H:%M:%S}: {summary}, {embedded} Embeddings berechnet')


def community_written():
    try:
        written = [write(rel, title, url, text, extra={'space': r['space'], 'oeffentlich': 'ja' if r['offen'] else 'nein'})
                   for rel, title, url, text, r in community_inhalte()]
    except Exception as e:
        print(f'! Community-Inhalte nicht gelesen: {e}', file=sys.stderr)
        kept = []
        for d, _, files in os.walk(os.path.join(ROOT, 'community', 'inhalte')):
            kept += [(os.path.relpath(os.path.join(d, f), ROOT), False, False) for f in files if f.endswith('.md')]
        written = kept
    try:
        written += [write(rel, title, url, text) for rel, title, url, text in community_mitteilungsblatt()]
    except Exception as e:
        print(f'! Mitteilungsblatt nicht gelesen: {e}', file=sys.stderr)
        for d, _, files in os.walk(os.path.join(ROOT, 'community', 'mitteilungsblatt')):
            written += [(os.path.relpath(os.path.join(d, f), ROOT), False, False) for f in files if f.endswith('.md')]
    return written


MB_DIR = os.path.join(os.path.dirname(ROOT), 'humhub-data', 'mitteilungsblatt')
# Uffi bekommt nur die Ausgaben der letzten so vielen Monate (ältere stehen im Archiv der App, wären aber oft überholt)
MB_MONATE = 12
MONATE = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember']


def community_mitteilungsblatt():
    """Mitteilungsblatt (Modul mitteilungsblatt) je Seite als [(rel, titel, url, text)]. Text per pdftotext aus der Ablage
    (bessere Lesereihenfolge als der Suchtext in der Datenbank). Enthält Namen (Geburtstage, Vereinsberichte), daher
    wie community/ nicht im Git."""
    found = []
    for a in db_json("SELECT JSON_OBJECT('id', id, 'jahr', jahr, 'nr', nr, 'nr_bis', nr_bis, 'datum', datum, 'seiten', seiten) FROM mb_ausgabe "
                     f"WHERE datum > CURDATE() - INTERVAL {MB_MONATE} MONTH ORDER BY jahr, nr"):
        pdf = os.path.join(MB_DIR, f"{a['jahr']:04d}-{a['nr']:02d}.pdf")
        if not os.path.exists(pdf):
            continue
        j, m, t = (int(x) for x in a['datum'][:10].split('-'))
        nummer = f"{a['nr']}–{a['nr_bis']}" if a.get('nr_bis') else a['nr']
        ausgabe = f"Mitteilungsblatt Nr. {nummer}/{a['jahr']} vom {t}. {MONATE[m - 1]} {j}"
        for seite in range(1, a['seiten'] + 1):
            text = subprocess.run(['pdftotext', '-q', '-f', str(seite), '-l', str(seite), pdf, '-'],
                                  capture_output=True, text=True).stdout
            text = re.sub(r'(\w)-\n(?=[a-zäöüß])', r'\1', text.replace('\f', ''))  # Silbentrennung am Zeilenende
            text = re.sub(r'[ \t]+', ' ', text)
            text = re.sub(r'\n{3,}', '\n\n', text).strip()
            if len(text) < 150:  # nur Anzeigen/Bilder
                continue
            found.append((f"community/mitteilungsblatt/{a['jahr']:04d}-{a['nr']:02d}/seite-{seite:02d}.md",
                          f'{ausgabe}, Seite {seite}',
                          f"https://community.rundumuffni.de/mitteilungsblatt/index/lesen?id={a['id']}&seite={seite}",
                          f"Amtsblatt der Stadt Uffenheim, erschienen am {t:02d}.{m:02d}.{j}. Text automatisch aus dem PDF "
                          f"gelesen (Spalten und Anzeigen können vermischt sein).\n\n{text}"))
    # Terminliste der Stadt (vom Modul beim Abruf gespeichert): Redaktionsschluss und Erscheinungstage
    tage = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag']
    zeilen = []
    for wert in db_json("SELECT value FROM setting WHERE module_id = 'mitteilungsblatt' AND name = 'termine'"):
        for t in wert:
            s, e = (datetime.fromisoformat(t[k]).date() for k in ('schluss', 'erscheint'))
            zeilen.append(f"- Ausgabe vom {tage[e.weekday()]}, {e.day}. {MONATE[e.month - 1]} {e.year}: Redaktionsschluss "
                          f"{tage[s.weekday()]}, {s.day}. {MONATE[s.month - 1]} {s.year}, 10:00 Uhr"
                          + (' (vorverlegt)' if t.get('vorverlegt') else ''))
    if zeilen:
        found.append(('community/mitteilungsblatt/termine.md', 'Mitteilungsblatt: Erscheinungstermine und Redaktionsschluss',
                      'https://community.rundumuffni.de/mitteilungsblatt/index',
                      'Das Mitteilungsblatt der Stadt Uffenheim erscheint alle 14 Tage freitags und wird an alle Haushalte verteilt. '
                      'Beiträge (z. B. von Vereinen) müssen bis zum Redaktionsschluss um 10:00 Uhr bei der Stadt sein. '
                      'Termine laut Terminliste der Stadt (Änderungen vorbehalten):\n\n' + '\n'.join(zeilen)))
    return found


def vereine_only():
    """Nur das Vereinsverzeichnis abgleichen (auch wenn uffenheim.de nicht erreichbar ist); übrige Seiten bleiben."""
    written = [write(rel, title, url, text) for rel, title, url, text in community_vereine()] + community_written()
    keep = {w[0] for w in written}
    removed = 0
    for d, _, files in os.walk(os.path.join(ROOT, 'community')):
        for f in files:
            rel = os.path.relpath(os.path.join(d, f), ROOT)
            if f.endswith('.md') and rel not in keep:
                os.remove(os.path.join(d, f))
                removed += 1
    for d, _, files in os.walk(ROOT):
        if '/.git' not in d + '/':
            keep |= {os.path.relpath(os.path.join(d, f), ROOT) for f in files if f.endswith('.md') and f != 'README.md'}
    embedded = update_index(keep)
    print(f'{datetime.now():%Y-%m-%d %H:%M:%S}: nur Community (Vereine, Wiki, Fragen, Beiträge): {len(written)} Seiten, '
          f'{sum(1 for w in written if w[1])} neu, {sum(1 for w in written if w[2])} geändert, {removed} gelöscht, '
          f'{embedded} Embeddings berechnet')


if __name__ == '__main__':
    try:
        if '--nur-vereine' in sys.argv or '--nur-community' in sys.argv:
            vereine_only()
        else:
            main()
    except Exception as e:
        print(f'{datetime.now():%Y-%m-%d %H:%M:%S}: FEHLER {e}', file=sys.stderr)
        if '--nur-vereine' not in sys.argv and '--nur-community' not in sys.argv:
            # uffenheim.de gestört: wenigstens die Vereinsseiten der Community aktuell halten
            try:
                vereine_only()
            except Exception as e2:
                print(f'{datetime.now():%Y-%m-%d %H:%M:%S}: FEHLER Vereinsverzeichnis {e2}', file=sys.stderr)
        sys.exit(1)
