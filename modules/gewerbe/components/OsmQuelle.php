<?php

namespace humhub\modules\gewerbe\components;

use humhub\modules\maengelmelder\components\Osm;
use Yii;

/**
 * Betriebe aus OpenStreetMap (Overpass API, Daten unter ODbL): Abruf für Uffenheim mit Ortsteilen und Übersetzung
 * der OSM-Merkmale in die Felder des Gewerbeverzeichnisses.
 */
class OsmQuelle
{
    private const UA = 'community.rundumuffni.de Gewerbeverzeichnis (smartcity@buergerliste-uffenheim.de)';
    private const OVERPASS = 'https://overpass-api.de/api/interpreter';
    /** Stadtgebiet mit Ortsteilen und etwas Rand; ob ein Betrieb wirklich in Uffenheim liegt, entscheidet die Adresse */
    private const BBOX = '49.46,10.10,49.62,10.36';
    /** Einzelne kleine Abfragen – eine große bricht bei Overpass oft mit Zeitüberschreitung ab */
    private const ABFRAGEN = [
        '["shop"]["name"]',
        '["craft"]["name"]',
        '["office"]["name"]',
        '["amenity"~"^(restaurant|cafe|fast_food|pub|bar|biergarten|ice_cream|pharmacy|bank|fuel|car_wash|veterinary|driving_school)$"]["name"]',
        '["tourism"~"^(hotel|guest_house|apartment|camp_site|chalet)$"]["name"]',
    ];

    /** Ortsteile (Ortsmitte laut OpenStreetMap) – der nächstgelegene im Umkreis von 1,5 km */
    public const ORTSTEILE = [
        'Uffenheim' => [49.5448, 10.2305], 'Brackenlohr' => [49.5456, 10.1751], 'Custenlohr' => [49.5135, 10.2433],
        'Langensteinach' => [49.4966, 10.1785], 'Rudolzhofen' => [49.5242, 10.2711], 'Uttenhofen' => [49.5435, 10.2673],
        'Wallmersbach' => [49.5224, 10.1740], 'Welbhausen' => [49.5277, 10.2120],
    ];

    /** Keine Betriebe im Sinne des Verzeichnisses (Behörden, Kirche, Vereine …) */
    private const OHNE = [
        'office' => ['government', 'parish', 'religion', 'research', 'water_utility', 'association', 'ngo', 'political_party',
            'educational_institution', 'diplomatic', 'union', 'foundation'],
        'shop' => ['vacant', 'video'],
    ];

    /** OSM-Merkmal => [Branche, Angebot] */
    private const ARTEN = [
        'shop' => [
            'supermarket' => ['Lebensmittel & Hofladen', 'Supermarkt'], 'bakery' => ['Lebensmittel & Hofladen', 'Bäckerei'],
            'pastry' => ['Lebensmittel & Hofladen', 'Konditorei'], 'butcher' => ['Lebensmittel & Hofladen', 'Metzgerei'],
            'farm' => ['Lebensmittel & Hofladen', 'Hofladen'], 'beverages' => ['Lebensmittel & Hofladen', 'Getränkemarkt'],
            'wine' => ['Lebensmittel & Hofladen', 'Wein'], 'convenience' => ['Lebensmittel & Hofladen', 'Lebensmittel'],
            'agrarian' => ['Landwirtschaft', 'Landhandel'], 'clothes' => ['Einzelhandel', 'Bekleidung'],
            'shoes' => ['Einzelhandel', 'Schuhe'], 'fabric' => ['Einzelhandel', 'Stoffe'], 'jewelry' => ['Einzelhandel', 'Schmuck'],
            'chemist' => ['Einzelhandel', 'Drogerie'], 'florist' => ['Einzelhandel', 'Blumen'],
            'garden_centre' => ['Einzelhandel', 'Gartencenter'], 'doityourself' => ['Einzelhandel', 'Baumarkt'],
            'furniture' => ['Einzelhandel', 'Möbel'], 'interior_decoration' => ['Einzelhandel', 'Wohnaccessoires'],
            'houseware' => ['Einzelhandel', 'Haushaltswaren'], 'electronics' => ['Einzelhandel', 'Elektronik'],
            'computer' => ['Einzelhandel', 'Computer'], 'variety_store' => ['Einzelhandel', 'Sonderposten'],
            'art' => ['Einzelhandel', 'Kunsthandwerk'], 'books' => ['Einzelhandel', 'Bücher'], 'toys' => ['Einzelhandel', 'Spielwaren'],
            'optician' => ['Gesundheit & Pflege', 'Optiker'], 'medical_supply' => ['Gesundheit & Pflege', 'Sanitätshaus'],
            'hearing_aids' => ['Gesundheit & Pflege', 'Hörgeräte'], 'hairdresser' => ['Schönheit & Wellness', 'Friseur'],
            'beauty' => ['Schönheit & Wellness', 'Kosmetik'], 'bicycle' => ['Kfz & Mobilität', 'Fahrräder'],
            'car' => ['Kfz & Mobilität', 'Autohaus'], 'car_repair' => ['Kfz & Mobilität', 'Kfz-Werkstatt'],
            'tyres' => ['Kfz & Mobilität', 'Reifen'], 'travel_agency' => ['Dienstleistung & Beratung', 'Reisebüro'],
            'funeral_directors' => ['Dienstleistung & Beratung', 'Bestattungen'], 'trade' => ['Handwerk & Bau', 'Fachhandel'],
            'plumber' => ['Handwerk & Bau', 'Sanitär, Heizung'], 'yes' => ['Einzelhandel', ''],
        ],
        'craft' => [
            'plumber' => ['Handwerk & Bau', 'Sanitär, Heizung'], 'hvac' => ['Handwerk & Bau', 'Kälte- und Klimatechnik'],
            'electrician' => ['Handwerk & Bau', 'Elektro'], 'carpenter' => ['Handwerk & Bau', 'Schreinerei, Zimmerei'],
            'joiner' => ['Handwerk & Bau', 'Schreinerei'], 'builder' => ['Handwerk & Bau', 'Bau'],
            'metal_construction' => ['Handwerk & Bau', 'Metallbau'], 'tinsmith' => ['Handwerk & Bau', 'Spenglerei, Metallbau'],
            'scaffolder' => ['Handwerk & Bau', 'Gerüstbau'], 'stonemason' => ['Handwerk & Bau', 'Steinmetz'],
            'parquet_layer' => ['Handwerk & Bau', 'Parkett'], 'window_construction' => ['Handwerk & Bau', 'Fensterbau'],
            'roofer' => ['Handwerk & Bau', 'Dachdecker'], 'painter' => ['Handwerk & Bau', 'Maler'],
            'photographer' => ['Dienstleistung & Beratung', 'Fotografie'],
            'electronics_repair' => ['Dienstleistung & Beratung', 'Reparatur'],
            'agricultural_engines' => ['Landwirtschaft', 'Landmaschinen'], 'winery' => ['Lebensmittel & Hofladen', 'Weinbau'],
            'distillery' => ['Lebensmittel & Hofladen', 'Brennerei'],
        ],
        'office' => [
            'lawyer' => ['Dienstleistung & Beratung', 'Rechtsberatung'], 'notary' => ['Dienstleistung & Beratung', 'Notar'],
            'tax_advisor' => ['Dienstleistung & Beratung', 'Steuerberatung'], 'insurance' => ['Dienstleistung & Beratung', 'Versicherung'],
            'architect' => ['Dienstleistung & Beratung', 'Architektur'], 'advertising_agency' => ['Dienstleistung & Beratung', 'Werbung'],
            'it' => ['Dienstleistung & Beratung', 'IT'], 'estate_agent' => ['Dienstleistung & Beratung', 'Immobilien'],
            'employment_agency' => ['Dienstleistung & Beratung', 'Personaldienstleistung'],
            'logistics' => ['Industrie & Produktion', 'Logistik'], 'company' => ['Industrie & Produktion', ''],
        ],
        'amenity' => [
            'restaurant' => ['Gastronomie & Café', 'Restaurant'], 'cafe' => ['Gastronomie & Café', 'Café'],
            'fast_food' => ['Gastronomie & Café', 'Imbiss'], 'pub' => ['Gastronomie & Café', 'Gaststätte'],
            'bar' => ['Gastronomie & Café', 'Bar'], 'biergarten' => ['Gastronomie & Café', 'Biergarten'],
            'ice_cream' => ['Gastronomie & Café', 'Eiscafé'], 'pharmacy' => ['Gesundheit & Pflege', 'Apotheke'],
            'veterinary' => ['Gesundheit & Pflege', 'Tierarzt'], 'bank' => ['Dienstleistung & Beratung', 'Bank'],
            'driving_school' => ['Dienstleistung & Beratung', 'Fahrschule'], 'fuel' => ['Kfz & Mobilität', 'Tankstelle'],
            'car_wash' => ['Kfz & Mobilität', 'Autowäsche'],
        ],
        'tourism' => [
            'hotel' => ['Übernachtung', 'Hotel'], 'guest_house' => ['Übernachtung', 'Pension'],
            'apartment' => ['Übernachtung', 'Ferienwohnung'], 'chalet' => ['Übernachtung', 'Ferienhaus'],
            'camp_site' => ['Übernachtung', 'Campingplatz'],
        ],
    ];

    private const KUECHE = ['pizza' => 'Pizza', 'italian' => 'italienische Küche', 'greek' => 'griechische Küche',
        'kebab' => 'Döner', 'turkish' => 'türkische Küche', 'regional' => 'regionale Küche', 'german' => 'deutsche Küche',
        'chinese' => 'chinesische Küche', 'asian' => 'asiatische Küche', 'burger' => 'Burger', 'ice_cream' => 'Eis',
        'coffee_shop' => 'Kaffee', 'cake' => 'Kuchen', 'fish' => 'Fisch', 'steak_house' => 'Steaks', 'vietnamese' => 'vietnamesische Küche'];

    /** Allgemeine Namen ohne Aussage („Metzgerei“, „Ferienwohnungen“) überspringen */
    private const ALLGEMEIN = ['metzgerei', 'ferienwohnungen', 'ferienwohnung', 'verkaufsraum', 'fun play', 'allianz'];

    /**
     * Alle passenden Objekte im Ausschnitt. Wirft eine Ausnahme, wenn eine Teilabfrage nach mehreren Versuchen
     * scheitert – ein unvollständiges Ergebnis darf den Abgleich nicht glauben lassen, Betriebe seien verschwunden.
     * @return array<string, array> "node/123" => Element mit tags, lat, lon
     */
    public static function laden(): array
    {
        $alle = [];
        foreach (self::ABFRAGEN as $i => $auswahl) {
            $q = '[out:json][timeout:60][bbox:' . self::BBOX . "];\nnwr$auswahl;\nout center tags;";
            $daten = null;
            for ($versuch = 1; $versuch <= 5 && $daten === null; $versuch++) {
                if ($i > 0 || $versuch > 1) {
                    sleep($versuch === 1 ? 3 : 20 * $versuch); // Overpass bremst bei schnellen Folgeanfragen
                }
                $daten = self::post($q);
            }
            if ($daten === null) {
                throw new \RuntimeException("Overpass-Abfrage $auswahl ist fünfmal gescheitert");
            }
            foreach ($daten as $e) {
                $e['lat'] ??= $e['center']['lat'] ?? null;
                $e['lon'] ??= $e['center']['lon'] ?? null;
                if ($e['lat'] !== null) {
                    $alle[$e['type'] . '/' . $e['id']] = $e;
                }
            }
        }
        return $alle;
    }

    private static function post(string $abfrage): ?array
    {
        $ch = curl_init(self::OVERPASS);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['data' => $abfrage]),
            CURLOPT_USERAGENT => self::UA,
            CURLOPT_TIMEOUT => 90,
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $json = $status === 200 && is_string($body) ? json_decode($body, true) : null;
        return is_array($json['elements'] ?? null) ? $json['elements'] : null;
    }

    /** [Branche, Angebot] oder null, wenn es kein Betrieb fürs Verzeichnis ist */
    public static function art(array $tags): ?array
    {
        $name = mb_strtolower(trim((string)($tags['name'] ?? '')));
        if ($name === '' || in_array($name, self::ALLGEMEIN, true)) {
            return null;
        }
        foreach (self::ARTEN as $schluessel => $werte) {
            $wert = explode(';', (string)($tags[$schluessel] ?? ''))[0];
            if ($wert === '') {
                continue;
            }
            if (in_array($wert, self::OHNE[$schluessel] ?? [], true)) {
                return null;
            }
            if (isset($werte[$wert])) {
                [$branche, $angebot] = $werte[$wert];
                $kueche = array_filter(array_map(fn($k) => self::KUECHE[trim($k)] ?? null, explode(';', (string)($tags['cuisine'] ?? ''))));
                return [$branche, implode(', ', array_filter(array_merge([$angebot], $kueche)))];
            }
        }
        return null;
    }

    /** Adresse aus den OSM-Merkmalen („Marktplatz 3, 97215 Uffenheim“) oder null */
    public static function adresse(array $tags): ?string
    {
        $strasse = trim(($tags['addr:street'] ?? $tags['addr:place'] ?? '') . ' ' . ($tags['addr:housenumber'] ?? ''));
        if ($strasse === '') {
            return null;
        }
        $ort = trim(($tags['addr:postcode'] ?? '') . ' ' . ($tags['addr:city'] ?? ''));
        return $strasse . ($ort !== '' ? ", $ort" : '');
    }

    /** Liegt das Objekt in der Stadt Uffenheim? Nach Adresse, sonst über Nominatim (gedrosselt) */
    public static function inUffenheim(array $e, ?string &$adresse): bool
    {
        $t = $e['tags'];
        // Die Postleitzahl 97215 haben auch Nachbargemeinden – nur der Ortsname zählt, sonst Nominatim fragen
        if (isset($t['addr:city'])) {
            return $t['addr:city'] === 'Uffenheim' && ($t['addr:postcode'] ?? '97215') === '97215';
        }
        $treffer = Osm::addressThrottled((float)$e['lat'], (float)$e['lon']);
        if (!is_string($treffer) || !str_ends_with($treffer, 'Uffenheim')) {
            return false;
        }
        // Straßennummern („NEA 51“, „B 13“) sind keine brauchbare Adresse
        if ($adresse === null && str_contains($treffer, ',') && !preg_match('/^(NEA|B|St|A|K|ST)\s?\d/u', $treffer)) {
            $adresse = str_replace(', Uffenheim', ', 97215 Uffenheim', $treffer);
        }
        return true;
    }

    public static function ortsteil(float $lat, float $lng): string
    {
        $best = 'Uffenheim';
        $min = 1.5;
        foreach (self::ORTSTEILE as $name => [$la, $lo]) {
            $km = sqrt((($lat - $la) * 111.2) ** 2 + (($lng - $lo) * 72.3) ** 2);
            if ($km < $min) {
                [$best, $min] = [$name, $km];
            }
        }
        return $best;
    }

    /**
     * Felder fürs Verzeichnis aus einem OSM-Objekt (ohne Name/Branche/Ort, die setzt der Aufrufer).
     * @return array{telefon: ?string, email: ?string, website: ?string, zeiten: ?string, oeffnungszeiten: ?string, social: ?string}
     */
    public static function felder(array $tags): array
    {
        $tel = $tags['phone'] ?? $tags['contact:phone'] ?? $tags['contact:mobile'] ?? null;
        $tel = $tel !== null ? trim(preg_replace('/[^0-9 +\/()-]/', '', explode(';', $tel)[0])) : null;
        $web = $tags['website'] ?? $tags['contact:website'] ?? $tags['url'] ?? null;
        $web = $web !== null ? trim(explode(';', $web)[0]) : null;
        if ($web !== null && !preg_match('#^https?://#', $web)) {
            $web = 'https://' . $web;
        }
        $mail = $tags['email'] ?? $tags['contact:email'] ?? null;
        $mail = $mail !== null ? trim(explode(';', $mail)[0]) : null;
        $oh = isset($tags['opening_hours']) ? trim($tags['opening_hours']) : null;
        $zeiten = $oh !== null ? Oeffnungszeiten::ausOsm($oh) : null;
        $social = [];
        foreach (['contact:facebook' => 'Facebook', 'facebook' => 'Facebook', 'contact:instagram' => 'Instagram', 'instagram' => 'Instagram'] as $k => $typ) {
            if (!empty($tags[$k]) && str_starts_with($tags[$k], 'https://') && !in_array($typ, array_column($social, 'typ'), true)) {
                $social[] = ['typ' => $typ, 'url' => $tags[$k]];
            }
        }
        return [
            'telefon' => $tel !== null && preg_match('/^[0-9 +\/()-]{5,50}$/', $tel) ? $tel : null,
            'email' => $mail !== null && filter_var($mail, FILTER_VALIDATE_EMAIL) ? $mail : null,
            'website' => $web !== null && filter_var($web, FILTER_VALIDATE_URL) ? $web : null,
            'zeiten' => $zeiten !== null ? json_encode($zeiten) : null,
            // Nicht übersetzbare Angaben („nach Vereinbarung“, saisonal) bleiben als Hinweis
            'oeffnungszeiten' => $oh !== null && $zeiten === null
                ? 'Laut OpenStreetMap: ' . str_replace(['"', 'by appointment'], ['', 'nach Vereinbarung'], $oh) : null,
            'social' => $social ? json_encode($social, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ];
    }

    public static function url(string $ref): string
    {
        return 'https://www.openstreetmap.org/' . $ref;
    }
}
