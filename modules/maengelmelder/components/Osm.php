<?php

namespace humhub\modules\maengelmelder\components;

use Yii;

/**
 * Zugriff auf OpenStreetMap über unseren Server: Browser der Nutzer sprechen nie direkt mit OSM.
 * Kartenkacheln werden 30 Tage zwischengespeichert (OSM-Nutzungsrichtlinie: eigener User-Agent, Cache, Namensnennung).
 */
class Osm
{
    private const UA = 'community.rundumuffni.de Maengelmelder (smartcity@buergerliste-uffenheim.de)';
    private const TILE_URL = 'https://tile.openstreetmap.org/%d/%d/%d.png';
    private const CACHE_DAYS = 30;
    /** Nur Kacheln rund um Uffenheim (kein offener Proxy) */
    private const TILE_BOUNDS = ['south' => 49.35, 'west' => 9.95, 'north' => 49.75, 'east' => 10.55];
    /** Ab dieser Zoomstufe nur das Stadtgebiet mit Ortsteilen (sonst wären es Hunderttausende Kacheln) */
    private const NAH_ZOOM = 15;
    public const NAH_BOUNDS = ['south' => 49.46, 'west' => 10.10, 'north' => 49.62, 'east' => 10.36];
    /** Höchstens so viele Abrufe bei openstreetmap.org pro Stunde (Tile Usage Policy, Schutz vor Sperre) */
    private const OSM_PRO_STUNDE = 2000;
    /** Zwischenspeicher höchstens so groß; darüber werden neue Kacheln geliefert, aber nicht mehr gespeichert */
    private const CACHE_MAX_MB = 1024;

    /** PNG-Daten einer Kachel oder null, wenn außerhalb des erlaubten Bereichs/Zooms */
    public static function tile(int $z, int $x, int $y): ?string
    {
        if ($z < 10 || $z > 19 || $x < 0 || $y < 0 || $x >= 2 ** $z || $y >= 2 ** $z) {
            return null;
        }
        // Kachelgrenzen in Grad
        $n = 2 ** $z;
        $west = $x / $n * 360 - 180;
        $east = ($x + 1) / $n * 360 - 180;
        $north = rad2deg(atan(sinh(M_PI * (1 - 2 * $y / $n))));
        $south = rad2deg(atan(sinh(M_PI * (1 - 2 * ($y + 1) / $n))));
        $b = $z >= self::NAH_ZOOM ? self::NAH_BOUNDS : self::TILE_BOUNDS;
        if ($east < $b['west'] || $west > $b['east'] || $north < $b['south'] || $south > $b['north']) {
            return null;
        }

        $file = Yii::getAlias("@runtime/maengel-tiles/$z/$x/$y.png");
        if (is_file($file) && filemtime($file) > time() - self::CACHE_DAYS * 86400) {
            return file_get_contents($file);
        }
        // Gesamtgrenze für Abrufe bei OSM (für alle Nutzer zusammen)
        $key = 'maengel-osm-abrufe-' . date('YmdH');
        $abrufe = (int)Yii::$app->cache->get($key);
        if ($abrufe >= self::OSM_PRO_STUNDE) {
            return is_file($file) ? file_get_contents($file) : null;
        }
        Yii::$app->cache->set($key, $abrufe + 1, 3600);
        $png = self::get(sprintf(self::TILE_URL, $z, $x, $y));
        if ($png === null || !str_starts_with($png, "\x89PNG")) {
            return is_file($file) ? file_get_contents($file) : null; // lieber alte Kachel als keine
        }
        if (self::cacheMb() < self::CACHE_MAX_MB) {
            @mkdir(dirname($file), 0775, true);
            file_put_contents($file, $png);
        }
        return $png;
    }

    /** Nächste Adresse zu einem Punkt (Nominatim), z. B. „Marktplatz 16, Uffenheim“; null bei Fehler */
    public static function address(float $lat, float $lng): ?string
    {
        $json = self::get('https://nominatim.openstreetmap.org/reverse?format=jsonv2&zoom=18&addressdetails=1'
            . '&accept-language=de&lat=' . round($lat, 6) . '&lon=' . round($lng, 6));
        $a = json_decode((string)$json, true)['address'] ?? null;
        if (!$a) {
            return null;
        }
        $street = trim(($a['road'] ?? $a['pedestrian'] ?? $a['footway'] ?? $a['path'] ?? '') . ' ' . ($a['house_number'] ?? ''));
        // Ortsteile (village) sind in OSM für Uffenheim unzuverlässig (Marktplatz liegt dort „in Welbhausen“), daher Stadt
        $place = $a['town'] ?? $a['city'] ?? $a['village'] ?? '';
        return trim(implode(', ', array_filter([$street, $place]))) ?: null;
    }

    /**
     * Ort zu einer Beschreibung wie „Hauptstraße 5“ oder „Marktplatz“ (Nominatim), nur im angegebenen Gebiet.
     * @return array|null [lat, lng, Anzeigename]
     */
    public static function search(string $query, array $bounds): ?array
    {
        $query = trim(preg_replace('/\s+/u', ' ', $query));
        if ($query === '') {
            return null;
        }
        if (!preg_match('/uffenheim|brackenlohr|custenlohr|langensteinach|rudolzhofen|uttenhofen|wallmersbach|welbhausen|pfeinach/i', $query)) {
            $query .= ', Uffenheim';
        }
        $json = self::get('https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&countrycodes=de&accept-language=de&bounded=1'
            . '&viewbox=' . $bounds['west'] . ',' . $bounds['north'] . ',' . $bounds['east'] . ',' . $bounds['south']
            . '&q=' . rawurlencode(mb_substr($query, 0, 200)));
        $hit = json_decode((string)$json, true)[0] ?? null;
        if (!$hit || !isset($hit['lat'], $hit['lon'])) {
            return null;
        }
        return [round((float)$hit['lat'], 6), round((float)$hit['lon'], 6), (string)($hit['display_name'] ?? $query)];
    }

    /**
     * Wie search(), aber höchstens eine Anfrage pro Sekunde an Nominatim für die ganze Plattform (Nutzungsrichtlinie).
     * @return array|null|false [lat, lng, Anzeigename], null = nicht gefunden, false = gerade belegt
     */
    public static function searchThrottled(string $query, array $bounds): array|null|false
    {
        $mutex = Yii::$app->mutex;
        if (!$mutex->acquire('maengel-nominatim', 5)) {
            return false;
        }
        try {
            $warte = (float)Yii::$app->cache->get('maengel-nominatim-zuletzt') + 1.0 - microtime(true);
            if ($warte > 0) {
                usleep((int)($warte * 1e6));
            }
            $hit = self::search($query, $bounds);
            Yii::$app->cache->set('maengel-nominatim-zuletzt', microtime(true), 10);
            return $hit;
        } finally {
            $mutex->release('maengel-nominatim');
        }
    }

    /** Größe des Kachel-Zwischenspeichers in MB (stündlich neu gezählt) */
    private static function cacheMb(): int
    {
        return (int)Yii::$app->cache->getOrSet('maengel-tiles-mb', function () {
            $dir = Yii::getAlias('@runtime/maengel-tiles');
            $bytes = 0;
            if (is_dir($dir)) {
                foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $f) {
                    $bytes += $f->getSize();
                }
            }
            return intdiv($bytes, 1024 * 1024);
        }, 3600);
    }

    private static function get(string $url): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => self::UA,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $body !== false && $status === 200 ? $body : null;
    }
}
