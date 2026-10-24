<?php

/**
 * Öffnungszeiten (Stand 24.10.2026). Quellen: uffenheim.de (Seiten im Feld „quelle“, auch in wissen/),
 * Wertstoffhof/Kompostplatz: kreis-nea.de und Mitteilungsblatt 2026/9. Bei Änderungen hier anpassen.
 *
 * zeiten         ISO-Wochentag (1 = Montag … 7 = Sonntag) => [[von, bis], …]
 * ersterImMonat  optional: am ersten solchen Wochentag des Monats gelten diese Zeiten statt der normalen
 * saisons        optional: [[von, bis], …] (Y-m-d, null = offen) oder Funktion(Jahr) => [von, bis]; außerhalb geschlossen
 * feiertagZu     true = an gesetzlichen Feiertagen in Bayern geschlossen (bei false wissen wir es nicht)
 * zuAn           optional: jedes Jahr zusätzlich geschlossen, ['MM-TT' => Name]
 * pruefen        nächtlicher Abgleich mit der Quelle (oeffnungszeiten/pruefen): Datei in wissen/ (stadtwissen.py),
 *                optional Abschnitt von „ab“ bis vor „bis“ (Regex); verglichen werden nur Zeilen mit Zeiten/Tagen/Saison
 *                (oder, falls angegeben, Zeilen passend zu „nur“)
 * name/hinweis   deutsch, übersetzt über OeffnungszeitenModule.base
 */
$buero = [['08:00', '12:00'], ['14:00', '15:30']];

return [
    'rathaus' => [
        'name' => 'Rathaus', 'icon' => 'university',
        'unterzeile' => 'Stadtverwaltung, Bürgerbüro, Touristinfo',
        'adresse' => 'Marktplatz 16', 'telefon' => '09842 207-0',
        'zeiten' => [1 => $buero, 2 => $buero, 3 => [['08:00', '12:00']], 4 => $buero, 5 => [['08:00', '12:00']]],
        'ersterImMonat' => [4 => [['08:00', '12:00'], ['14:00', '18:00']]],
        'feiertagZu' => true,
        'zuAn' => ['12-24' => 'Heiligabend', '12-31' => 'Silvester'], // laut Nutzer 24.10.2026
        'hinweis' => 'Bitte möglichst vorher einen Termin vereinbaren, dann musst du nicht warten.',
        'link' => ['Termin buchen', 'https://www.terminland.de/uffenheim/?m=38075'],
        'quelle' => 'https://www.uffenheim.de/',
        'pruefen' => ['datei' => 'rathaus-kontakt-oeffnungszeiten.md', 'ab' => '/^## Öffnungszeiten/', 'bis' => '/^## /'],
    ],
    'buecherei' => [
        'name' => 'Stadtbücherei', 'icon' => 'book',
        'adresse' => 'Schloßstraße 3', 'telefon' => '09842 952489',
        'zeiten' => [1 => [['11:30', '14:00'], ['15:00', '18:00']], 3 => [['09:00', '11:00'], ['15:00', '18:00']], 5 => [['15:00', '18:00']]],
        'feiertagZu' => true,
        'zuAn' => ['12-24' => 'Heiligabend', '12-31' => 'Silvester'], // laut Nutzer 24.10.2026
        'hinweis' => 'Ausgeliehenes kannst du jederzeit in den Rückgabekasten werfen.',
        'quelle' => 'https://www.uffenheim.de/leben/kinderbetreuung-bildung/stadtbuecherei',
        'pruefen' => ['datei' => 'leben/kinderbetreuung-bildung/stadtbuecherei.md', 'ab' => '/zu folgenden Zeiten geöffnet/', 'bis' => '/^#/'],
    ],
    'hallenbad' => [
        'name' => 'Hallenbad', 'icon' => 'tint',
        'telefon' => '',
        'zeiten' => [3 => [['16:00', '21:00']], 5 => [['16:00', '21:00']], 7 => [['09:00', '14:00']]],
        'saisons' => [['2026-09-30', null]],
        'feiertagZu' => false,
        'hinweis' => 'Einlass bis eine Stunde vor Schluss, Badezeit endet 30 Minuten vor Schluss.',
        'quelle' => 'https://www.uffenheim.de/erleben/sport-freizeiteinrichtungen/hallenbad',
        'pruefen' => ['datei' => 'erleben/sport-freizeiteinrichtungen/hallenbad.md', 'bis' => '/^## Eintrittspreise/'],
    ],
    'freibad' => [
        'name' => 'Freibad', 'icon' => 'sun-o',
        'telefon' => '09842 9526035',
        'zeiten' => array_fill(1, 7, [['10:00', '20:00']]),
        'saisons' => [[null, '2026-09-13']],
        'feiertagZu' => false,
        'hinweis' => 'Frühschwimmen dienstags und donnerstags 6:30 bis 9:00 Uhr.',
        'quelle' => 'https://www.uffenheim.de/erleben/sport-freizeiteinrichtungen/freibad',
        'pruefen' => ['datei' => 'erleben/sport-freizeiteinrichtungen/freibad.md', 'bis' => '/^## Eintrittspreise/', 'nur' => '/Öffnungszeit|öffnet|schließt|Frühschwimmen|Einlass|\\d{1,2}[:.]\\d{2}/u'],
    ],
    'wertstoffhof' => [
        'name' => 'Wertstoffhof', 'icon' => 'recycle',
        'adresse' => 'Buchenweg 3 (im Kreisbauhof)', 'telefon' => '09161 92-6375',
        'zeiten' => [1 => [['09:00', '12:30']], 3 => [['11:30', '17:00']], 5 => [['09:00', '15:00']], 6 => [['09:00', '12:30']]],
        'feiertagZu' => true,
        'zuAn' => ['12-24' => 'Heiligabend', '12-31' => 'Silvester'], // laut Nutzer 24.10.2026
        'quelle' => 'https://www.kreis-nea.de/service-themen/abfall-natur-umwelt/abfallentsorgung/annahmestellen-oeffnungszeiten/wertstoffhoefe.html',
        'pruefen' => ['datei' => 'landkreis/lebenslagen/abfallwirtschaft/annahmestellen-und-oeffnungszeiten/wertstoffhoefe.md', 'ab' => '/^Wertstoffhof Uffenheim$/', 'bis' => '/^(Wertstoffhof|Kompostplatz) |^#{1,2} /'],
    ],
    'kompostplatz' => [
        'name' => 'Kompostplatz', 'icon' => 'leaf',
        'adresse' => '„Bei den drei Kreuzen“, Richtung Custenlohr (bei der Bauschuttdeponie)', 'telefon' => '',
        'zeiten' => [
            1 => [['08:00', '12:30'], ['13:00', '16:00']], 2 => [['08:00', '12:30'], ['13:00', '16:00']],
            3 => [['08:00', '12:30'], ['13:00', '16:00']], 4 => [['12:30', '17:00']],
            5 => [['08:00', '12:30'], ['13:00', '16:00']], 6 => [['09:00', '14:00']],
        ],
        'feiertagZu' => true,
        'zuAn' => ['12-24' => 'Heiligabend', '12-31' => 'Silvester'], // laut Nutzer 24.10.2026
        'hinweis' => 'Gartenabfälle, Reisig und Baumschnitt. Kleine Mengen Grüngut auch an den Containern am Geckenheimer Steig.',
        'quelle' => 'https://www.kreis-nea.de/service-themen/abfall-natur-umwelt/abfallentsorgung/annahmestellen-oeffnungszeiten/wertstoffhoefe.html',
        'pruefen' => ['datei' => 'landkreis/lebenslagen/abfallwirtschaft/annahmestellen-und-oeffnungszeiten/kompostplaetze.md', 'ab' => '/^Kompostplatz Uffenheim$/', 'bis' => '/^(Wertstoffhof|Kompostplatz) |^#{1,2} /'],
    ],
    'museum' => [
        'name' => 'Gollachgaumuseum', 'icon' => 'institution',
        'adresse' => 'Schloßplatz 5–6', 'telefon' => '09842 8100',
        'zeiten' => [7 => [['13:30', '16:00']]],
        'saisons' => fn(int $jahr) => ["$jahr-05-01", date('Y-m-d', strtotime("last sunday of october $jahr"))],
        'feiertagZu' => false,
        'hinweis' => 'Gruppenführungen nach Anmeldung das ganze Jahr.',
        'quelle' => 'https://www.uffenheim.de/erleben/sport-freizeiteinrichtungen/heimatmuseum',
        'pruefen' => ['datei' => 'erleben/sport-freizeiteinrichtungen/heimatmuseum.md', 'nur' => '/Öffnungszeit|Führung/u'],
    ],
    'juff' => [
        'name' => 'Jugendtreff JUFF', 'icon' => 'gamepad',
        'adresse' => 'Marktplatz 8', 'telefon' => '',
        'zeiten' => [1 => [['14:30', '20:00']], 2 => [['14:30', '22:00']], 3 => [['14:30', '21:00']], 4 => [['14:30', '18:30']], 5 => [['14:30', '22:00']]],
        'feiertagZu' => false,
        'hinweis' => 'Jeden zweiten Samstag bei Bedarf – die Termine stehen auf Instagram („Jugendtreff Uffenheim“).',
        'quelle' => 'https://www.uffenheim.de/leben/angebote-fuer-kinder-jugendliche/staedtischer-jugendteff-juff',
        'pruefen' => ['datei' => 'leben/angebote-fuer-kinder-jugendliche/staedtischer-jugendteff-juff.md', 'ab' => '/Unsere Öffnungszeiten/', 'bis' => '/^#|^Falls ihr Fragen/'],
    ],
];
