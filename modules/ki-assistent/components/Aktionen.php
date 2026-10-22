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
        if (class_exists(\humhub\modules\maengelmelder\components\Osm::class)) {
            $tools[] = [
                'name' => 'ort_zeigen',
                'description' => 'Zeigt unter der Antwort Knöpfe, die einen Ort auf OpenStreetMap und in Google Maps öffnen. '
                    . 'Aufrufen, sobald der Nutzer wissen will, wo etwas ist oder wo etwas stattfindet (Adresse, Gebäude, Amt, Betrieb, '
                    . 'Apotheke, Veranstaltungsort, Straße), je genanntem Ort einmal, höchstens 4.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string', 'description' => 'Name des Ortes, z. B. „Rathaus Uffenheim“ oder „Stadt-Apotheke“; leer bei einer reinen Adresse'],
                        'adresse' => ['type' => 'string', 'description' => 'Straße mit Hausnummer und Ort, nur aus den Daten; sonst Straße bzw. markanter Ort mit Ortsteil'],
                    ],
                    'required' => ['adresse'],
                ],
            ];
        }
        $bereiche = array_keys(array_filter([
            'betriebe' => Yii::$app->hasModule('gewerbe') && Yii::$app->db->getTableSchema('gewerbe_betrieb') !== null,
            'vereine' => Yii::$app->hasModule('vereine'),
            'veranstaltungen' => Space::findOne(['url' => 'veranstaltungen']) !== null,
        ]));
        if ($bereiche) {
            $tools[] = [
                'name' => 'verzeichnis_suchen',
                'description' => 'Sucht in den Verzeichnissen der Community und liefert die Einträge mit allen Angaben und Link: '
                    . 'betriebe = Geschäfte, Gastronomie, Handwerk, Dienstleister (Angebot, Adresse, Telefon, Öffnungszeiten, ob gerade geöffnet); '
                    . 'vereine = Vereine (Kategorie, Ortsteil, Treffpunkt, Beschreibung, Website); '
                    . 'veranstaltungen = Termine bis 90 Tage voraus (oder im angegebenen Zeitraum). '
                    . 'Aufrufen, sobald Angaben gebraucht werden, die im Prompt nur als Name stehen, oder für Termine nach den nächsten '
                    . Context::TAGE_IM_PROMPT . ' Tagen. Gefunden wird, was eines der Stichwörter enthält (auch als Wortteil).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'bereich' => ['type' => 'string', 'enum' => $bereiche],
                        'stichwoerter' => ['type' => 'string', 'description' => 'Mehrere Stichwörter mit Leerzeichen: Namen oder Namensteile, '
                            . 'Wortstämme und Synonyme (z. B. „Döner Imbiss Pizza“, „Friseur Frisör“, „Kirchweih Kerwa“, „Reusch“); leer = alle'],
                        'nur_geoeffnet' => ['type' => 'boolean', 'description' => 'Nur bei betriebe: nur die gerade geöffneten'],
                        'von' => ['type' => 'string', 'description' => 'Nur bei veranstaltungen: erstes Datum (JJJJ-MM-TT), Standard heute'],
                        'bis' => ['type' => 'string', 'description' => 'Nur bei veranstaltungen: letztes Datum (JJJJ-MM-TT), Standard heute + 90 Tage'],
                    ],
                    'required' => ['bereich'],
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
                'ort_zeigen' => self::ort($args),
                'verzeichnis_suchen' => self::verzeichnis($args),
                'formular_ausfuellen' => self::formularAusfuellen($args),
                default => [['fehler' => "Unbekannte Aktion $name"], null],
            };
        } catch (\Throwable $e) {
            Yii::error("KI-Aktion $name: " . $e->getMessage(), 'ki-assistent');
            return [['fehler' => 'Die Aktion ist fehlgeschlagen: ' . $e->getMessage()], null];
        }
    }

    /** Höchstens so viele Treffer zurückgeben (Kosten); bei mehr soll die KI genauer suchen */
    private const MAX_TREFFER = 20;

    private static function verzeichnis(array $args): array
    {
        $woerter = array_values(array_filter(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower((string)($args['stichwoerter'] ?? ''))),
            fn($w) => mb_strlen($w) >= 3));
        // Treffer = Zahl der gefundenen Stichwörter, Treffer im Namen zählen doppelt
        $punkte = function (string $name, string $text) use ($woerter): int {
            $name = mb_strtolower($name);
            $text = mb_strtolower($text);
            $p = 0;
            foreach ($woerter as $w) {
                $p += str_contains($name, $w) ? 2 : (str_contains($text, $w) ? 1 : 0);
            }
            return $p;
        };
        $eintraege = []; // [Punkte, Zeile]
        switch ($args['bereich'] ?? '') {
            case 'betriebe':
                foreach (\humhub\modules\gewerbe\models\Betrieb::find()->where(['!=', 'status', 'vorgeschlagen'])->orderBy(['name' => SORT_ASC])->all() as $b) {
                    if (!empty($args['nur_geoeffnet']) && empty($b->getStand()['offen'])) {
                        continue;
                    }
                    $eintraege[] = [$punkte($b->name, "$b->kategorie $b->ortsteil $b->angebot $b->adresse"), Context::betrieb($b)];
                }
                break;
            case 'vereine':
                foreach (\humhub\modules\vereine\models\Verein::find()->where(['!=', 'status', 'vorgeschlagen'])->orderBy(['name' => SORT_ASC])->all() as $v) {
                    $eintraege[] = [$punkte($v->name, "$v->kategorie $v->ortsteil $v->treffpunkt $v->beschreibung"), Context::verein($v)];
                }
                break;
            case 'veranstaltungen':
                $datum = fn($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$d) ? (string)$d : null;
                $von = $datum($args['von'] ?? null) ?? date('Y-m-d');
                $bis = date('Y-m-d', strtotime(($datum($args['bis'] ?? null) ?? date('Y-m-d', strtotime('+90 days'))) . ' +1 day'));
                foreach (Context::veranstaltungen($von, $bis, 300) as $zeile) {
                    $eintraege[] = [$punkte(strtok($zeile, "\n"), $zeile), $zeile];
                }
                break;
            default:
                return [['fehler' => 'Unbekannter Bereich'], null];
        }
        if ($woerter) {
            $eintraege = array_filter($eintraege, fn($e) => $e[0] > 0);
            // Betriebe und Vereine nach Treffern, Veranstaltungen bleiben nach Datum
            if (($args['bereich'] ?? '') !== 'veranstaltungen') {
                usort($eintraege, fn($a, $b) => $b[0] <=> $a[0]);
            }
        }
        $gesamt = count($eintraege);
        $ergebnis = ['ok' => true, 'anzahl' => $gesamt, 'eintraege' => implode("\n", array_column(array_slice($eintraege, 0, self::MAX_TREFFER), 1))];
        if ($gesamt === 0) {
            $ergebnis['hinweis'] = 'Nichts gefunden. Andere Stichwörter oder Wortstämme versuchen, sonst sagen, dass im Verzeichnis nichts dazu steht.';
        } elseif ($gesamt > self::MAX_TREFFER) {
            $ergebnis['hinweis'] = 'Nur die ersten ' . self::MAX_TREFFER . ' von ' . $gesamt . ' Treffern. Für mehr genauer suchen bzw. den Zeitraum eingrenzen.';
        }
        return [$ergebnis, null];
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
            'knoepfe' => [
                ['text' => Yii::t('KiAssistentModule.base', 'Route auf der Karte'), 'url' => $url],
                ['text' => Yii::t('KiAssistentModule.base', 'In Google Maps'), 'url' => 'https://www.google.com/maps/dir/?api=1'
                    . ($start ? '&origin=' . $start[0] . ',' . $start[1] : '') . '&destination=' . $ziel[0] . ',' . $ziel[1]
                    . '&travelmode=' . match ($engine) { 'fossgis_osrm_bike' => 'bicycling', 'fossgis_osrm_car' => 'driving', default => 'walking' }],
            ],
        ];
        return [['ok' => true, 'ziel_gefunden' => true, 'start_gefunden' => $start !== null, 'luftlinie_m' => $luft,
            'hinweis' => 'Der Routen-Knopf erscheint unter deiner Antwort. Beschreibe den Weg höchstens grob anhand des Straßenverzeichnisses '
                . '(Lage, Kreuzungen); keine Abbiegeanweisungen erfinden.'], $karte];
    }

    /** Ort auf OpenStreetMap (Markierung, sonst Suche) und in Google Maps (Suche nach Name + Adresse, findet auch Betriebe) */
    private static function ort(array $args): array
    {
        $name = mb_substr(trim((string)($args['name'] ?? '')), 0, 100);
        $adresse = mb_substr(trim((string)($args['adresse'] ?? '')), 0, 150);
        if ($adresse === '' && $name === '') {
            return [['fehler' => 'Keine Adresse angegeben'], null];
        }
        $bounds = Yii::$app->hasModule('maengelmelder') ? Yii::$app->getModule('maengelmelder')->bounds
            : ['south' => 49.45, 'west' => 10.08, 'north' => 49.64, 'east' => 10.40];
        $hit = null;
        foreach (array_unique(array_filter([$adresse, $name])) as $suche) {
            for ($i = 0; $i < 3 && ($hit = \humhub\modules\maengelmelder\components\Osm::searchThrottled($suche, $bounds)) === false; $i++) {
                usleep(1100000); // Nominatim: höchstens eine Anfrage pro Sekunde
            }
            if ($hit) {
                break;
            }
        }
        $text = implode(', ', array_filter([$name, $adresse]));
        if (!preg_match('/uffenheim|brackenlohr|custenlohr|langensteinach|rudolzhofen|uttenhofen|wallmersbach|welbhausen|pfeinach|\b9\d{4}\b/i', $text)) {
            $text .= ', Uffenheim';
        }
        $osm = $hit
            ? sprintf('https://www.openstreetmap.org/?mlat=%1$.5f&mlon=%2$.5f#map=18/%1$.5f/%2$.5f', $hit[0], $hit[1])
            : 'https://www.openstreetmap.org/search?query=' . rawurlencode($text);
        $karte = [
            'typ' => 'link',
            'icon' => 'map-marker',
            'titel' => $name !== '' ? $name : $adresse,
            'zeilen' => array_values(array_filter([
                $name !== '' ? $adresse : null,
                Yii::t('KiAssistentModule.base', 'Google Maps ist ein Dienst von Google; erst beim Klick werden Daten an Google übertragen.'),
            ])),
            'knoepfe' => [
                ['text' => Yii::t('KiAssistentModule.base', 'Auf der Karte (OpenStreetMap)'), 'url' => $osm],
                ['text' => Yii::t('KiAssistentModule.base', 'In Google Maps'), 'url' => 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($text)],
            ],
        ];
        return [['ok' => true, 'gefunden' => $hit !== null,
            'hinweis' => 'Die Karten-Knöpfe erscheinen unter deiner Antwort; die Kartenadressen nicht noch einmal als Link schreiben.'], $karte];
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
