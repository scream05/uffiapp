<?php

namespace humhub\modules\kiAssistent\components;

use humhub\modules\calendar\models\CalendarEntry;
use humhub\modules\space\models\Space;
use Yii;
use yii\helpers\Url;

/**
 * Werkzeuge, die der KI-Assistent aufrufen kann (Claude Tool Use).
 * Jede Aktion liefert ein Ergebnis für die KI und optional eine „Karte“ für den Chat (Knopf, Download).
 * Nichts wird ohne Zutun des Nutzers abgeschickt: Mängelmeldungen öffnen nur das vorausgefüllte Formular.
 */
class Aktionen
{
    /** Online-Dienste der Abfallwirtschaft des Landkreises (Servlet des Landratsamts): [Titel, Knopf, Adresse, Zeilen] */
    private const PORTAL = 'https://sperrmuell.kreis-nea.de/WasteManagementNeustadt/WasteManagementServlet?SubmitAction=';
    /** Routenplanung auf openstreetmap.org (Dienste der FOSSGIS) je Verkehrsmittel */
    private const ROUTING = ['zu_fuss' => 'fossgis_osrm_foot', 'rad' => 'fossgis_osrm_bike', 'auto' => 'fossgis_osrm_car'];
    private const ABFALLPORTAL = [
        'sperrmuell' => ['Sperrmüll abrufen (Landkreis)', 'Sperrmüll online abrufen', 'disposalOnCall', [
            'Im Formular „Uffenheim“, Straße und Hausnummer wählen und die Gegenstände angeben.',
            'Den Abholtermin teilt der Entsorger per E-Mail oder Telefon mit; bitte bestätigen.',
        ]],
        'tonnenleerungen' => ['Meine Tonnenleerungen (Landkreis)', 'Tonnenleerungen ansehen', 'binClearances', [
            'Ort „Uffenheim“, Straße und Hausnummer wählen und das Kundenkonto (Debitorennummer der Abfallwirtschaft) eingeben.',
            'Danach zeigt der Landkreis, wann welche deiner Tonnen geleert wurde.',
            'Kundenkonto nicht zur Hand? Abfallwirtschaft: abfall@kreis-nea.de, Tel. 09161 92-6310.',
        ]],
        'kundenportal' => ['Kundenportal der Abfallwirtschaft (Landkreis)', 'Kundenportal öffnen', 'login', [
            'Mit den Zugangsdaten der Abfallwirtschaft anmelden: alle Online-Dienste des Landkreises an einer Stelle.',
        ]],
    ];

    /** Angezeigte Texte der Karte zu ABFALLPORTAL (übersetzt): [Titel, Knopf, Zeilen] */
    private static function abfallportalTexte(string $dienst): array
    {
        return match ($dienst) {
            'sperrmuell' => [Yii::t('KiAssistentModule.base', 'Sperrmüll abrufen (Landkreis)'), Yii::t('KiAssistentModule.base', 'Sperrmüll online abrufen'), [
                Yii::t('KiAssistentModule.base', 'Im Formular „Uffenheim“, Straße und Hausnummer wählen und die Gegenstände angeben.'),
                Yii::t('KiAssistentModule.base', 'Den Abholtermin teilt der Entsorger per E-Mail oder Telefon mit; bitte bestätigen.'),
            ]],
            'tonnenleerungen' => [Yii::t('KiAssistentModule.base', 'Meine Tonnenleerungen (Landkreis)'), Yii::t('KiAssistentModule.base', 'Tonnenleerungen ansehen'), [
                Yii::t('KiAssistentModule.base', 'Ort „Uffenheim“, Straße und Hausnummer wählen und das Kundenkonto (Debitorennummer der Abfallwirtschaft) eingeben.'),
                Yii::t('KiAssistentModule.base', 'Danach zeigt der Landkreis, wann welche deiner Tonnen geleert wurde.'),
                Yii::t('KiAssistentModule.base', 'Kundenkonto nicht zur Hand? Abfallwirtschaft: abfall@kreis-nea.de, Tel. 09161 92-6310.'),
            ]],
            default => [Yii::t('KiAssistentModule.base', 'Kundenportal der Abfallwirtschaft (Landkreis)'), Yii::t('KiAssistentModule.base', 'Kundenportal öffnen'), [
                Yii::t('KiAssistentModule.base', 'Mit den Zugangsdaten der Abfallwirtschaft anmelden: alle Online-Dienste des Landkreises an einer Stelle.'),
            ]],
        };
    }

    /** @return array Werkzeuge für Claude (name, description, inputSchema) */
    public static function declarations(): array
    {
        $tools = [];
        $maengel = self::maengelSpace();
        if ($maengel !== null) {
            $tools[] = [
                'name' => 'mangel_melden_vorbereiten',
                'description' => 'Bereitet eine Mängelmeldung (Schlagloch, defekte Laterne, Müll, kaputtes Schild …) im Mängelmelder vor: '
                    . 'öffnet das Meldeformular mit Kategorie, Beschreibung und dem Ort auf der Karte vorausgefüllt. '
                    . 'Der Nutzer prüft den Ort, kann ein Foto anhängen und schickt selbst ab.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'kategorie' => ['type' => 'string', 'enum' => Yii::$app->getModule('maengelmelder')->categories],
                        'beschreibung' => ['type' => 'string', 'description' => 'Kurze, sachliche Beschreibung des Mangels in ganzen Sätzen'],
                        'ort' => ['type' => 'string', 'description' => 'Straße mit Hausnummer, Kreuzung oder markanter Ort, z. B. „Hauptstraße 12“ oder „Marktplatz“; Ortsteil dazu, falls nicht Kernstadt'],
                    ],
                    'required' => ['kategorie', 'beschreibung', 'ort'],
                ],
            ];
        }
        if (Yii::$app->hasModule('calendar')) {
            $tools[] = [
                'name' => 'termin_merken',
                'description' => 'Zeigt zu einem Termin aus den Daten (Veranstaltung) Knöpfe zum Öffnen und zum Eintragen in den eigenen Kalender (.ics).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'termin_url' => ['type' => 'string', 'description' => 'Die URL des Termins aus den Daten (…/calendar/entry/view?id=…)'],
                    ],
                    'required' => ['termin_url'],
                ],
            ];
        }
        $ki = Yii::$app->getModule('ki-assistent');
        $tools[] = [
            'name' => 'rathaus_termin',
            'description' => 'Zeigt einen Knopf zur Online-Terminbuchung der Stadt Uffenheim (Terminland) für das Bürgerbüro, '
                . 'mit Hinweis, welcher Bereich dort zu wählen ist, und einer kurzen Liste, was man mitbringen muss.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'amt' => ['type' => 'string', 'enum' => $ki->terminlandAemter],
                    'anliegen' => ['type' => 'string', 'description' => 'Kurz, z. B. „Ummeldung“, „Personalausweis beantragen“'],
                    'mitbringen' => ['type' => 'array', 'items' => ['type' => 'string'],
                        'description' => 'Was mitzubringen ist, nur aus den Daten (Leistungsbeschreibung der Stadt); leer lassen, wenn unbekannt'],
                ],
                'required' => ['amt', 'anliegen'],
            ],
        ];
        $tools[] = [
            'name' => 'abfallportal',
            'description' => 'Zeigt einen Knopf zum Online-Dienst der Abfallwirtschaft des Landkreises Neustadt a.d.Aisch-Bad Windsheim '
                . 'mit kurzer Anleitung: Sperrmüll abrufen, eigene Tonnenleerungen ansehen (wann welche Tonne geleert wurde) '
                . 'oder das Kundenportal. Die Eingaben (Adresse, Kundenkonto) macht der Nutzer selbst beim Landkreis.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => ['dienst' => ['type' => 'string', 'enum' => array_keys(self::ABFALLPORTAL)]],
                'required' => ['dienst'],
            ],
        ];
        if (class_exists(\humhub\modules\maengelmelder\components\Osm::class)) {
            $tools[] = [
                'name' => 'weg_zeigen',
                'description' => 'Zeigt einen Knopf zur Routenplanung auf OpenStreetMap von einem Ort zu einem Ziel in Uffenheim und Umgebung '
                    . '(zu Fuß, Rad oder Auto), mit Luftlinie. Start leer lassen, wenn der Nutzer keinen genannt hat – dann trägt er ihn auf der Karte ein.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'ziel' => ['type' => 'string', 'description' => 'Straße mit Hausnummer, Straße oder markanter Ort, mit Ortsteil, falls nicht Kernstadt'],
                        'start' => ['type' => 'string', 'description' => 'Wie ziel; leer, wenn nicht genannt'],
                        'verkehrsmittel' => ['type' => 'string', 'enum' => array_keys(self::ROUTING)],
                    ],
                    'required' => ['ziel', 'verkehrsmittel'],
                ],
            ];
        }
        if (Formulare::all()) {
            $ids = array_keys(Formulare::all());
            $tools[] = [
                'name' => 'formular_info',
                'description' => 'Liefert Ablauf (Unterlagen, Kosten, Abgabe, Online-Alternative) und die Felder eines Antragsformulars. Vor dem Ausfüllen immer aufrufen.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['formular_id' => ['type' => 'string', 'enum' => $ids]],
                    'required' => ['formular_id'],
                ],
            ];
            $tools[] = [
                'name' => 'formular_ausfuellen',
                'description' => 'Füllt das Antragsformular mit den Angaben des Nutzers aus und stellt es als PDF zum Herunterladen bereit. '
                    . 'Fehlen Pflichtangaben oder sind Angaben ungültig, kommt eine Liste zurück; dann gezielt nachfragen und erneut aufrufen.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'formular_id' => ['type' => 'string', 'enum' => $ids],
                        'werte' => [
                            'type' => 'array',
                            'description' => 'Alle bisher bekannten Angaben, je Feld ein Eintrag',
                            'items' => [
                                'type' => 'object',
                                'properties' => ['feld' => ['type' => 'string'], 'wert' => ['type' => 'string']],
                                'required' => ['feld', 'wert'],
                            ],
                        ],
                    ],
                    'required' => ['formular_id', 'werte'],
                ],
            ];
        }
        return $tools;
    }

    /**
     * @return array [Ergebnis für die KI, Karte für den Chat oder null]
     */
    public static function run(string $name, array $args): array
    {
        try {
            return match ($name) {
                'mangel_melden_vorbereiten' => self::mangel($args),
                'termin_merken' => self::termin($args),
                'formular_info' => self::formularInfo($args),
                'rathaus_termin' => self::rathausTermin($args),
                'abfallportal' => self::abfallportal($args),
                'weg_zeigen' => self::weg($args),
                'formular_ausfuellen' => self::formularAusfuellen($args),
                default => [['fehler' => "Unbekannte Aktion $name"], null],
            };
        } catch (\Throwable $e) {
            Yii::error("KI-Aktion $name: " . $e->getMessage(), 'ki-assistent');
            return [['fehler' => 'Die Aktion ist fehlgeschlagen: ' . $e->getMessage()], null];
        }
    }

    private static function mangel(array $args): array
    {
        $space = self::maengelSpace();
        $module = Yii::$app->getModule('maengelmelder');
        $kategorie = in_array($args['kategorie'] ?? '', $module->categories, true) ? $args['kategorie'] : 'Sonstiges';
        $beschreibung = mb_substr(trim((string)($args['beschreibung'] ?? '')), 0, 3000);
        $ort = trim((string)($args['ort'] ?? ''));
        $hit = \humhub\modules\maengelmelder\components\Osm::search($ort, $module->bounds);
        $params = ['kategorie' => $kategorie, 'beschreibung' => $beschreibung];
        if ($hit !== null) {
            [$params['lat'], $params['lng']] = $hit;
        }
        $url = $space->createUrl('/maengelmelder/meldung/create', $params);
        $karte = [
            'typ' => 'link',
            'icon' => 'map-marker',
            'titel' => Yii::t('KiAssistentModule.base', 'Mängelmeldung vorbereitet'),
            'zeilen' => array_values(array_filter([
                Yii::t('KiAssistentModule.base', 'Kategorie: {kategorie}', ['kategorie' => $kategorie]),
                $hit ? Yii::t('KiAssistentModule.base', 'Ort: {ort} (auf der Karte markiert, bitte prüfen)', ['ort' => $ort])
                    : Yii::t('KiAssistentModule.base', 'Ort „{ort}“ nicht gefunden – bitte im Formular auf der Karte markieren', ['ort' => $ort]),
                Yii::t('KiAssistentModule.base', 'Ein Foto kannst du im Formular hinzufügen. Gesendet wird erst mit „Meldung absenden“.'),
                Yii::$app->user->isGuest ? (self::gastMeldungen()
                    ? Yii::t('KiAssistentModule.base', 'Melden geht auch ohne Konto: Name und E-Mail oder Telefon für Rückfragen angeben, der Bauhof prüft die Meldung vor der Veröffentlichung.')
                    : Yii::t('KiAssistentModule.base', 'Zum Melden ist ein kostenloses Konto nötig – nach der Anmeldung geht es mit dem Formular weiter.')) : null,
            ])),
            'knopf' => Yii::t('KiAssistentModule.base', 'Meldeformular öffnen'),
            'url' => $url,
        ];
        return [['ok' => true, 'ort_gefunden' => $hit !== null,
            'hinweis' => 'Der Knopf zum vorausgefüllten Meldeformular erscheint unter deiner Antwort. Der Nutzer muss den Ort prüfen und selbst absenden.'
                . (Yii::$app->user->isGuest ? (self::gastMeldungen()
                    ? ' Der Nutzer ist nicht angemeldet: Er kann trotzdem melden (mit Name und E-Mail oder Telefon für Rückfragen); der Bauhof prüft Gastmeldungen vor der Veröffentlichung.'
                    : ' Der Nutzer ist nicht angemeldet: zum Absenden braucht er ein kostenloses Konto (Anmeldung/Registrierung öffnet sich über den Knopf).') : '')], $karte];
    }

    /** Mängel melden ohne Konto eingeschaltet? (yii bauhof/gastmeldungen) */
    private static function gastMeldungen(): bool
    {
        $m = Yii::$app->getModule('maengelmelder');
        return $m !== null && method_exists($m, 'gastMeldungenAktiv') && $m->gastMeldungenAktiv();
    }

    private static function termin(array $args): array
    {
        if (!preg_match('/[?&]id=(\d+)/', (string)($args['termin_url'] ?? ''), $m)) {
            return [['fehler' => 'Keine Termin-URL erkannt'], null];
        }
        $entry = CalendarEntry::findOne((int)$m[1]);
        if ($entry === null || !$entry->content->canView(Yii::$app->user->getIdentity())) {
            return [['fehler' => 'Termin nicht gefunden'], null];
        }
        $start = Yii::$app->formatter->asDatetime($entry->start_datetime, $entry->all_day ? 'php:D, d.m.Y' : 'php:D, d.m.Y, H:i');
        if (!$entry->all_day) {
            $start = Yii::t('KiAssistentModule.base', '{zeit} Uhr', ['zeit' => $start]);
        }
        $karte = [
            'typ' => 'termin',
            'icon' => 'calendar',
            'titel' => $entry->title,
            'zeilen' => array_values(array_filter([$start, $entry->location ? Yii::t('KiAssistentModule.base', 'Ort: {ort}', ['ort' => $entry->location]) : null])),
            'knoepfe' => [
                ['text' => Yii::t('KiAssistentModule.base', 'In meinen Kalender (.ics)'), 'url' => $entry->content->container->createUrl('/calendar/entry/generateics', ['id' => $entry->id])],
                ['text' => Yii::t('KiAssistentModule.base', 'Termin ansehen'), 'url' => Url::to($entry->getUrl())],
            ],
        ];
        return [['ok' => true, 'hinweis' => 'Knöpfe zum Termin erscheinen unter deiner Antwort (Kalenderdatei für Handy/Outlook, Terminseite mit Erinnerung und Zusage).'], $karte];
    }

    private static function rathausTermin(array $args): array
    {
        $ki = Yii::$app->getModule('ki-assistent');
        $amt = in_array($args['amt'] ?? '', $ki->terminlandAemter, true) ? $args['amt'] : 'Einwohnermeldeamt';
        $mitbringen = array_slice(array_values(array_filter(array_map(fn($x) => mb_substr(trim((string)$x), 0, 120),
            (array)($args['mitbringen'] ?? [])))), 0, 8);
        $karte = [
            'typ' => 'link',
            'icon' => 'calendar-check-o',
            'titel' => Yii::t('KiAssistentModule.base', 'Termin im Bürgerbüro: {anliegen}', ['anliegen' => mb_substr(trim((string)($args['anliegen'] ?? $amt)), 0, 80)]),
            'zeilen' => array_values(array_filter([
                Yii::t('KiAssistentModule.base', 'Auf der Buchungsseite zuerst „{amt}“ wählen, dann Anliegen und Uhrzeit.', ['amt' => $amt]),
                $mitbringen ? Yii::t('KiAssistentModule.base', 'Mitbringen: {liste}', ['liste' => implode(', ', $mitbringen)]) : null,
                Yii::t('KiAssistentModule.base', 'Gebucht wird direkt bei der Stadt Uffenheim (terminland.de); Rund um Uffni erhält dabei keine Daten.'),
            ])),
            'knopf' => Yii::t('KiAssistentModule.base', 'Termin buchen'),
            'url' => $ki->terminlandUrl,
        ];
        return [['ok' => true, 'hinweis' => 'Der Buchungsknopf erscheint unter deiner Antwort. Nenne kurz Adresse (Bürgerbüro, Marktplatz 9) und dass man ohne Termin mit Wartezeit rechnen muss.'], $karte];
    }

    private static function abfallportal(array $args): array
    {
        $dienst = self::ABFALLPORTAL[$args['dienst'] ?? ''] ?? null;
        if ($dienst === null) {
            return [['fehler' => 'Unbekannter Dienst'], null];
        }
        [, , $action] = $dienst;
        [$titel, $knopf, $zeilen] = self::abfallportalTexte((string)$args['dienst']);
        $karte = [
            'typ' => 'link',
            'icon' => 'trash',
            'titel' => $titel,
            'zeilen' => array_merge($zeilen, [Yii::t('KiAssistentModule.base', 'Die Seite gehört dem Landratsamt; Rund um Uffni erhält deine Eingaben nicht.')]),
            'knopf' => $knopf,
            'url' => self::PORTAL . $action,
        ];
        return [['ok' => true, 'hinweis' => 'Der Knopf zum Landkreis erscheint unter deiner Antwort. Erkläre kurz, was der Nutzer dort eingibt. '
            . 'Frag nie nach Kundenkonto oder Debitorennummer im Chat und verarbeite sie nicht; das gibt der Nutzer nur beim Landkreis ein. '
            . 'Wo die Nummer steht, ist nicht belegt: nicht raten, bei Bedarf auf die Abfallwirtschaft (abfall@kreis-nea.de, 09161 92-6310) verweisen. '
            . 'Die Zahl der Restmüll-Entleerungen steht laut Landkreis auch in der Jahresabrechnung.'], $karte];
    }

    private static function weg(array $args): array
    {
        $bounds = Yii::$app->hasModule('maengelmelder') ? Yii::$app->getModule('maengelmelder')->bounds
            : ['south' => 49.45, 'west' => 10.08, 'north' => 49.64, 'east' => 10.40];
        $finde = function (string $ort) use ($bounds) {
            for ($i = 0; $i < 3; $i++) {
                $hit = \humhub\modules\maengelmelder\components\Osm::searchThrottled($ort, $bounds);
                if ($hit !== false) {
                    return $hit;
                }
                usleep(1100000); // Nominatim: höchstens eine Anfrage pro Sekunde
            }
            return null;
        };
        $zielText = mb_substr(trim((string)($args['ziel'] ?? '')), 0, 150);
        $startText = mb_substr(trim((string)($args['start'] ?? '')), 0, 150);
        $ziel = $zielText !== '' ? $finde($zielText) : null;
        if ($ziel === null) {
            return [['fehler' => "Ziel „{$zielText}“ nicht gefunden. Nach Straße/Ort genauer fragen."], null];
        }
        $start = $startText !== '' ? $finde($startText) : null;
        $engine = self::ROUTING[$args['verkehrsmittel'] ?? ''] ?? 'fossgis_osrm_foot';
        $url = 'https://www.openstreetmap.org/directions?engine=' . $engine . '&route='
            . rawurlencode(($start ? "$start[0],$start[1]" : '') . ';' . "$ziel[0],$ziel[1]");
        $luft = $start ? (int)round(self::meter($start, $ziel) / 50) * 50 : null;
        $titel = match ($engine) {
            'fossgis_osrm_bike' => $startText !== ''
                ? Yii::t('KiAssistentModule.base', 'Weg mit dem Rad von {start} nach {ziel}', ['start' => $startText, 'ziel' => $zielText])
                : Yii::t('KiAssistentModule.base', 'Weg mit dem Rad nach {ziel}', ['ziel' => $zielText]),
            'fossgis_osrm_car' => $startText !== ''
                ? Yii::t('KiAssistentModule.base', 'Weg mit dem Auto von {start} nach {ziel}', ['start' => $startText, 'ziel' => $zielText])
                : Yii::t('KiAssistentModule.base', 'Weg mit dem Auto nach {ziel}', ['ziel' => $zielText]),
            default => $startText !== ''
                ? Yii::t('KiAssistentModule.base', 'Weg zu Fuß von {start} nach {ziel}', ['start' => $startText, 'ziel' => $zielText])
                : Yii::t('KiAssistentModule.base', 'Weg zu Fuß nach {ziel}', ['ziel' => $zielText]),
        };
        $karte = [
            'typ' => 'link',
            'icon' => 'map-signs',
            'titel' => $titel,
            'zeilen' => array_values(array_filter([
                $startText !== '' && $start === null ? Yii::t('KiAssistentModule.base', 'Start „{start}“ nicht gefunden – bitte auf der Karte eintragen.', ['start' => $startText]) : null,
                $startText === '' ? Yii::t('KiAssistentModule.base', 'Startpunkt auf der Karte eintragen (oder Standort nutzen).') : null,
                $luft ? Yii::t('KiAssistentModule.base', 'Luftlinie etwa {meter} m.', ['meter' => (string)$luft]) : null,
                Yii::t('KiAssistentModule.base', 'Route von OpenStreetMap (FOSSGIS); bitte vor Ort auf Beschilderung achten.'),
            ])),
            'knopf' => Yii::t('KiAssistentModule.base', 'Route auf der Karte'),
            'url' => $url,
        ];
        return [['ok' => true, 'ziel_gefunden' => true, 'start_gefunden' => $start !== null, 'luftlinie_m' => $luft,
            'hinweis' => 'Der Routen-Knopf erscheint unter deiner Antwort. Beschreibe den Weg höchstens grob anhand des Straßenverzeichnisses '
                . '(Lage, Kreuzungen); keine Abbiegeanweisungen erfinden.'], $karte];
    }

    private static function meter(array $a, array $b): float
    {
        $dy = ($a[0] - $b[0]) * 111320;
        $dx = ($a[1] - $b[1]) * 111320 * cos(deg2rad($a[0]));
        return sqrt($dx * $dx + $dy * $dy);
    }

    private static function formularInfo(array $args): array
    {
        $def = Formulare::get((string)($args['formular_id'] ?? ''));
        return $def ? [Formulare::info($def), null] : [['fehler' => 'Unbekanntes Formular'], null];
    }

    private static function formularAusfuellen(array $args): array
    {
        $def = Formulare::get((string)($args['formular_id'] ?? ''));
        if ($def === null) {
            return [['fehler' => 'Unbekanntes Formular'], null];
        }
        $input = [];
        foreach ((array)($args['werte'] ?? []) as $w) {
            if (isset($w['feld'])) {
                $input[(string)$w['feld']] = (string)($w['wert'] ?? '');
            }
        }
        [$values, $missing, $errors] = Formulare::validate($def, $input);
        if ($missing || $errors) {
            $labels = array_column($def['felder'], 'label', 'name');
            return [['ok' => false, 'fehlende_angaben' => array_map(fn($n) => $labels[$n] ?? $n, $missing), 'ungueltig' => $errors,
                'hinweis' => 'Bitte den Nutzer nach diesen Angaben fragen und danach erneut aufrufen.'], null];
        }
        $token = Formulare::render($def, $values);
        $karte = [
            'typ' => 'download',
            'icon' => 'file-pdf-o',
            'titel' => $def['titel'],
            'zeilen' => [Yii::t('KiAssistentModule.base', 'Bitte prüfen, ausdrucken und unterschreiben. Der Download ist eine Stunde lang verfügbar und wird dann gelöscht.')],
            'knopf' => Yii::t('KiAssistentModule.base', 'PDF herunterladen'),
            'url' => Url::to(['/ki-assistent/chat/formular', 'token' => $token]),
        ];
        return [['ok' => true, 'hinweis' => 'Der Download-Knopf erscheint unter deiner Antwort. Erkläre kurz die nächsten Schritte (prüfen, unterschreiben, wohin damit, Kosten).'], $karte];
    }

    private static function maengelSpace(): ?Space
    {
        if (!Yii::$app->hasModule('maengelmelder')) {
            return null;
        }
        foreach (Space::find()->all() as $space) {
            if ($space->moduleManager->isEnabled('maengelmelder')) {
                return $space;
            }
        }
        return null;
    }
}
