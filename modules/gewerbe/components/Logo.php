<?php

namespace humhub\modules\gewerbe\components;

use humhub\modules\file\models\File;
use humhub\modules\gewerbe\models\Betrieb;
use Yii;

/**
 * Logo eines Betriebs von seiner eigenen Website: Bild mit „logo“ im Namen/Alt-Text, sonst das App-Symbol
 * (apple-touch-icon). Keine Fotos und keine fremden Quellen. Nur Rasterbilder (die ImageMagick-Richtlinie
 * lässt SVG nicht zu); verkleinert auf höchstens 400 px, ohne Metadaten.
 */
class Logo
{
    private const UA = 'community.rundumuffni.de Gewerbeverzeichnis (https://community.rundumuffni.de; Kontakt siehe Impressum)';

    /** @return string|null Fehler/Grund, null bei Erfolg */
    public static function holen(Betrieb $betrieb): ?string
    {
        if (!$betrieb->website) {
            return 'keine Website';
        }
        $html = self::laden($betrieb->website, 2_000_000, $endUrl);
        if ($html === null) {
            return 'Website nicht erreichbar';
        }
        if (self::geparkt($html)) {
            return 'Domain scheint nicht mehr zum Betrieb zu gehören';
        }
        // Leitet die Website auf eine andere Domain weiter (Übernahme, Umzug …), lieber kein Logo als ein fremdes
        if ($endUrl !== null && self::domain($endUrl) !== self::domain($betrieb->website)) {
            return 'Website leitet auf andere Domain weiter';
        }
        $seite = self::host($endUrl ?? $betrieb->website);
        foreach (self::kandidaten($html, $endUrl ?? $betrieb->website) as $url) {
            // Nur Bilder der eigenen Website oder eines Bild-CDN, keine Partner-/Herstellerlogos
            $bildHost = self::host($url);
            if (preg_match('/seeklogo|partner|netzwerk|nicsell|sedo|parking|signature_logo|bosch|ford_|sponsor|siegel|award|zertifikat/i', $url)
                || !($bildHost === $seite || str_ends_with($bildHost, '.' . $seite) || preg_match('/cdn|optimole|website-editor|go-asset|e-spirit|cloudfront|wp\.com/', $bildHost))) {
                continue;
            }
            $file = self::bild($url);
            if ($file !== null) {
                if ($alt = $betrieb->getLogo()) {
                    $alt->delete();
                }
                $betrieb->fileManager->attach([$file->guid]);
                $betrieb->updateAttributes(['logo_guid' => $file->guid, 'logo_quelle' => mb_substr($url, 0, 500)]);
                return null;
            }
        }
        return 'kein passendes Logo gefunden';
    }

    /** Domain ohne „www.“ */
    public static function host(string $url): string
    {
        return preg_replace('/^www\./', '', strtolower((string)parse_url($url, PHP_URL_HOST)));
    }

    /** Registrierte Domain (letzte zwei Teile), z. B. uffenheim.reifen-mueller.com → reifen-mueller.com */
    public static function domain(string $url): string
    {
        return implode('.', array_slice(explode('.', self::host($url)), -2));
    }

    /** Geparkte oder zum Verkauf stehende Domain */
    public static function geparkt(string $html): bool
    {
        return (bool)preg_match('/nicsell|sedo\.com|domain (is|ist) (for sale|zu verkaufen)|diese domain (kaufen|steht zum verkauf)|parkingcrew|bodis\.com/i', $html);
    }

    /** Bild-Adressen in sinnvoller Reihenfolge */
    private static function kandidaten(string $html, string $basis): array
    {
        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NOERROR);
        $x = new \DOMXPath($doc);
        $urls = [];
        foreach ($x->query('//img') as $img) {
            $merkmale = strtolower($img->getAttribute('src') . ' ' . $img->getAttribute('alt') . ' ' . $img->getAttribute('class')
                . ' ' . $img->getAttribute('id') . ' ' . ($img->parentNode instanceof \DOMElement ? $img->parentNode->getAttribute('class') : ''));
            $src = $img->getAttribute('src') ?: $img->getAttribute('data-src');
            if ($src !== '' && str_contains($merkmale, 'logo') && !preg_match('/\.svg(\?|$)/i', $src)) {
                $urls[] = $src;
            }
        }
        foreach ($x->query('//link[contains(@rel,"apple-touch-icon")]') as $l) {
            $urls[] = $l->getAttribute('href');
        }
        foreach ($x->query('//meta[@property="og:image"]') as $m) {
            if (str_contains(strtolower($m->getAttribute('content')), 'logo')) {
                $urls[] = $m->getAttribute('content');
            }
        }
        $abs = [];
        foreach (array_slice(array_unique(array_filter($urls)), 0, 6) as $u) {
            $abs[] = self::absolut($u, $basis);
        }
        return array_values(array_filter($abs));
    }

    private static function absolut(string $u, string $basis): ?string
    {
        $u = html_entity_decode(trim($u));
        if (str_starts_with($u, 'data:')) {
            return null;
        }
        if (preg_match('#^https?://#i', $u)) {
            return $u;
        }
        $p = parse_url($basis);
        if (!isset($p['scheme'], $p['host'])) {
            return null;
        }
        $root = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
        if (str_starts_with($u, '//')) {
            return $p['scheme'] . ':' . $u;
        }
        if (str_starts_with($u, '/')) {
            return $root . $u;
        }
        $pfad = isset($p['path']) ? preg_replace('#/[^/]*$#', '/', $p['path']) : '/';
        return $root . $pfad . $u;
    }

    private static function bild(string $url): ?File
    {
        $data = self::laden($url, 3_000_000);
        if ($data === null) {
            return null;
        }
        try {
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($data);
            if (!in_array($mime, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)) {
                return null;
            }
            $im = new \Imagick();
            $im->readImageBlob($data);
            $im = $im->coalesceImages()->getImage();
            if ($im->getImageWidth() < 64 || $im->getImageHeight() < 32) {
                return null; // Favicons und Platzhalter
            }
            if ($im->getImageWidth() > 400 || $im->getImageHeight() > 400) {
                $im->thumbnailImage(400, 400, true);
            }
            $im->stripImage();
            $jpeg = $mime === 'image/jpeg';
            $im->setImageFormat($jpeg ? 'jpeg' : 'png');
            $blob = $im->getImageBlob();
            $file = new File(['file_name' => 'logo' . ($jpeg ? '.jpg' : '.png'), 'mime_type' => $jpeg ? 'image/jpeg' : 'image/png', 'size' => strlen($blob)]);
            $file->save();
            $file->setStoredFileContent($blob);
            return $file;
        } catch (\Throwable $e) {
            Yii::warning("Gewerbe-Logo $url: " . $e->getMessage(), 'gewerbe');
            return null;
        }
    }

    /** Öffentliche Adresse? (keine Abrufe ins eigene Netz über manipulierte Websites) */
    private static function oeffentlich(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || !preg_match('#^https?://#i', $url)) {
            return false;
        }
        $ips = gethostbynamel($host) ?: [];
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }
        return $ips !== [];
    }

    /** GET mit Größenbegrenzung; folgt Weiterleitungen nur zu öffentlichen Adressen */
    public static function laden(string $url, int $max, ?string &$endUrl = null, ?int &$status = null): ?string
    {
        for ($i = 0; $i < 5; $i++) {
            if (!self::oeffentlich($url)) {
                return null;
            }
            $ch = curl_init($url);
            $daten = '';
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_USERAGENT => self::UA,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_WRITEFUNCTION => function ($ch, $chunk) use (&$daten, $max) {
                    $daten .= $chunk;
                    return strlen($daten) > $max ? 0 : strlen($chunk);
                },
            ]);
            curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $weiter = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
            curl_close($ch);
            if ($status >= 300 && $status < 400 && $weiter) {
                $url = $weiter;
                continue;
            }
            $endUrl = $url;
            // Bei Überlänge bricht der Abruf ab – der Anfang reicht (Logo-Suche, Erreichbarkeit)
            return $status === 200 && $daten !== '' ? substr($daten, 0, $max) : null;
        }
        return null;
    }
}
