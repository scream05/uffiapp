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
            'titel' => 'Mängelmeldung vorbereitet',
            'zeilen' => array_values(array_filter([
                "Kategorie: $kategorie",
                $hit ? "Ort: $ort (auf der Karte markiert, bitte prüfen)" : "Ort „{$ort}“ nicht gefunden – bitte im Formular auf der Karte markieren",
                'Ein Foto kannst du im Formular hinzufügen. Gesendet wird erst mit „Meldung absenden“.',
                Yii::$app->user->isGuest ? (self::gastMeldungen()
                    ? 'Melden geht auch ohne Konto: Name und E-Mail oder Telefon für Rückfragen angeben, der Bauhof prüft die Meldung vor der Veröffentlichung.'
                    : 'Zum Melden ist ein kostenloses Konto nötig – nach der Anmeldung geht es mit dem Formular weiter.') : null,
            ])),
            'knopf' => 'Meldeformular öffnen',
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
        $start = Yii::$app->formatter->asDatetime($entry->start_datetime, $entry->all_day ? 'php:D, d.m.Y' : 'php:D, d.m.Y, H:i') . ($entry->all_day ? '' : ' Uhr');
        $karte = [
            'typ' => 'termin',
            'icon' => 'calendar',
            'titel' => $entry->title,
            'zeilen' => array_values(array_filter([$start, $entry->location ? 'Ort: ' . $entry->location : null])),
            'knoepfe' => [
                ['text' => 'In meinen Kalender (.ics)', 'url' => $entry->content->container->createUrl('/calendar/entry/generateics', ['id' => $entry->id])],
                ['text' => 'Termin ansehen', 'url' => Url::to($entry->getUrl())],
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
            'titel' => 'Termin im Bürgerbüro: ' . mb_substr(trim((string)($args['anliegen'] ?? $amt)), 0, 80),
            'zeilen' => array_values(array_filter([
                "Auf der Buchungsseite zuerst „{$amt}“ wählen, dann Anliegen und Uhrzeit.",
                $mitbringen ? 'Mitbringen: ' . implode(', ', $mitbringen) : null,
                'Gebucht wird direkt bei der Stadt Uffenheim (terminland.de); Rund um Uffni erhält dabei keine Daten.',
            ])),
            'knopf' => 'Termin buchen',
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
        [$titel, $knopf, $action, $zeilen] = $dienst;
        $karte = [
            'typ' => 'link',
            'icon' => 'trash',
            'titel' => $titel,
            'zeilen' => array_merge($zeilen, ['Die Seite gehört dem Landratsamt; Rund um Uffni erhält deine Eingaben nicht.']),
            'knopf' => $knopf,
            'url' => self::PORTAL . $action,
        ];
        return [['ok' => true, 'hinweis' => 'Der Knopf zum Landkreis erscheint unter deiner Antwort. Erkläre kurz, was der Nutzer dort eingibt. '
            . 'Frag nie nach Kundenkonto oder Debitorennummer im Chat und verarbeite sie nicht; das gibt der Nutzer nur beim Landkreis ein. '
            . 'Wo die Nummer steht, ist nicht belegt: nicht raten, bei Bedarf auf die Abfallwirtschaft (abfall@kreis-nea.de, 09161 92-6310) verweisen. '
            . 'Die Zahl der Restmüll-Entleerungen steht laut Landkreis auch in der Jahresabrechnung.'], $karte];
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
            'zeilen' => ['Bitte prüfen, ausdrucken und unterschreiben. Der Download ist eine Stunde lang verfügbar und wird dann gelöscht.'],
            'knopf' => 'PDF herunterladen',
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
