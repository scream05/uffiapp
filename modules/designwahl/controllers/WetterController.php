<?php

namespace humhub\modules\designwahl\controllers;

use humhub\components\access\ControllerAccess;
use humhub\components\Controller;
use Yii;

/**
 * Aktuelles Wetter in Uffenheim für die Begrüßungskarte: Daten des Deutschen Wetterdienstes über Bright Sky
 * (https://brightsky.dev, nächste Station Gollhofen). Abruf über unseren Server und 15 Min. zwischengespeichert,
 * die Nutzer haben keine Verbindung zum Dienst.
 * Dazu die Vorhersage für heute und morgen (DWD-MOSMIX, Punkt Uffenheim) samt amtlichen Warnungen für das
 * Fenster, das sich beim Antippen des Wetters öffnet (30 Min. im Cache).
 */
class WetterController extends Controller
{
    private const URL = 'https://api.brightsky.dev/current_weather?lat=49.5447&lon=10.2333&tz=Europe/Berlin';

    private const BREITE = 49.5447;
    private const LAENGE = 10.2333;
    private const ZONE = 'Europe/Berlin';
    private const API = 'https://api.brightsky.dev/';

    // Niederschlag und Gewitter gehen beim Tagessymbol vor
    private const NASS = ['thunderstorm', 'hail', 'snow', 'sleet', 'rain'];

    private const TEXTE = [
        'clear-day' => 'Sonnig', 'clear-night' => 'Klar', 'partly-cloudy-day' => 'Leicht bewölkt',
        'partly-cloudy-night' => 'Leicht bewölkt', 'cloudy' => 'Bewölkt', 'fog' => 'Nebel', 'wind' => 'Windig',
        'rain' => 'Regen', 'sleet' => 'Schneeregen', 'snow' => 'Schnee', 'hail' => 'Hagel', 'thunderstorm' => 'Gewitter',
    ];

    /** Angezeigter Text einer Wetterlage (TEXTE ist deutsch und steht so im Cache; übersetzt wird bei der Ausgabe) */
    private static function text(string $icon): string
    {
        return match ($icon) {
            'clear-day' => Yii::t('DesignwahlModule.base', 'Sonnig'),
            'clear-night' => Yii::t('DesignwahlModule.base', 'Klar'),
            'partly-cloudy-day', 'partly-cloudy-night' => Yii::t('DesignwahlModule.base', 'Leicht bewölkt'),
            'cloudy' => Yii::t('DesignwahlModule.base', 'Bewölkt'),
            'fog' => Yii::t('DesignwahlModule.base', 'Nebel'),
            'wind' => Yii::t('DesignwahlModule.base', 'Windig'),
            'rain' => Yii::t('DesignwahlModule.base', 'Regen'),
            'sleet' => Yii::t('DesignwahlModule.base', 'Schneeregen'),
            'snow' => Yii::t('DesignwahlModule.base', 'Schnee'),
            'hail' => Yii::t('DesignwahlModule.base', 'Hagel'),
            'thunderstorm' => Yii::t('DesignwahlModule.base', 'Gewitter'),
            default => self::TEXTE[$icon] ?? '',
        };
    }

    /** Tageszeit (im Cache deutsch, siehe tag()) */
    private static function tageszeit(string $name): string
    {
        return match ($name) {
            'Morgens' => Yii::t('DesignwahlModule.base', 'Morgens'),
            'Mittags' => Yii::t('DesignwahlModule.base', 'Mittags'),
            'Nachmittags' => Yii::t('DesignwahlModule.base', 'Nachmittags'),
            'Abends' => Yii::t('DesignwahlModule.base', 'Abends'),
            default => $name,
        };
    }

    /** Vorhersage aus dem Cache in die Sprache der Seite bringen */
    private static function uebersetzen(array $d): array
    {
        foreach (['heute', 'morgen'] as $tag) {
            if (!empty($d[$tag])) {
                $d[$tag]['text'] = self::text($d[$tag]['icon']);
                foreach ($d[$tag]['zeiten'] as $i => $z) {
                    $d[$tag]['zeiten'][$i]['name'] = self::tageszeit($z['name']);
                    $d[$tag]['zeiten'][$i]['text'] = self::text($z['icon']);
                }
            }
        }
        foreach ($d['stunden'] as $i => $s) {
            $d['stunden'][$i]['text'] = self::text($s['icon']);
            if (array_key_exists('stunde', $s)) {
                $d['stunden'][$i]['zeit'] = $s['stunde'] === null ? Yii::t('DesignwahlModule.base', 'Jetzt')
                    : Yii::t('DesignwahlModule.base', '{stunde} Uhr', ['stunde' => (string)$s['stunde']]);
            }
        }
        foreach ($d['warnungen'] ?? [] as $i => $w) {
            if (($w['titel'] ?? null) === null) {
                $d['warnungen'][$i]['titel'] = Yii::t('DesignwahlModule.base', 'Wetterwarnung');
            }
        }
        return $d;
    }

    protected function getAccessRules()
    {
        return []; // auch für Gäste (Startseite)
    }

    public function actionIndex()
    {
        $wetter = Yii::$app->cache->getOrSet('designwahl-wetter', function () {
            $ch = curl_init(self::URL);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5, CURLOPT_USERAGENT => 'rundumuffni.de']);
            $data = json_decode((string)curl_exec($ch), true);
            curl_close($ch);
            $w = $data['weather'] ?? null;
            if (!isset($w['temperature'])) {
                return false; // nicht zwischenspeichern, siehe unten
            }
            return [
                'temperatur' => (int)round($w['temperature']),
                'icon' => isset(self::TEXTE[$w['icon'] ?? '']) ? $w['icon'] : 'cloudy',
                'text' => self::TEXTE[$w['icon'] ?? ''] ?? '',
                'station' => $data['sources'][0]['station_name'] ?? '',
                'zeit' => isset($w['timestamp']) ? date('H:i', strtotime($w['timestamp'])) : '',
            ];
        }, 900);
        if ($wetter === false) {
            Yii::$app->cache->delete('designwahl-wetter');
            return $this->asJson(['fehler' => true]);
        }
        Yii::$app->response->headers->set('Cache-Control', 'private, max-age=600');
        if ($wetter['text'] !== '') {
            $wetter['text'] = self::text($wetter['icon']);
        }
        return $this->asJson($wetter);
    }

    public function actionVorhersage()
    {
        // v2: Stunden mit „stunde“ (übersetzt wird erst bei der Ausgabe)
        $daten = Yii::$app->cache->getOrSet('designwahl-wetter-vorhersage-v2', function () {
            $zone = new \DateTimeZone(self::ZONE);
            $heute = new \DateTimeImmutable('today', $zone);
            $ort = 'lat=' . self::BREITE . '&lon=' . self::LAENGE . '&tz=' . self::ZONE;
            $wetter = $this->holen('weather?' . $ort . '&date=' . $heute->format('Y-m-d')
                . '&last_date=' . $heute->modify('+2 days')->format('Y-m-d'));
            if (empty($wetter['weather'])) {
                return false;
            }
            $tage = [];
            foreach ($wetter['weather'] as $w) {
                if (!isset($w['temperature'], $w['timestamp'])) {
                    continue;
                }
                $t = (new \DateTimeImmutable($w['timestamp']))->setTimezone($zone);
                $tage[$t->format('Y-m-d')][(int)$t->format('G')] = $w;
            }
            $warnungen = $this->holen('alerts?' . $ort)['alerts'] ?? null;
            return [
                'heute' => $this->tag($tage[$heute->format('Y-m-d')] ?? [], $heute),
                'morgen' => $this->tag($tage[$heute->modify('+1 day')->format('Y-m-d')] ?? [], $heute->modify('+1 day')),
                'stunden' => $this->stunden($tage, $zone),
                'warnungen' => $warnungen === null ? null : array_map([$this, 'warnung'], $warnungen),
                'stand' => (new \DateTimeImmutable('now', $zone))->format('H:i'),
            ];
        }, 1800);
        if ($daten === false) {
            Yii::$app->cache->delete('designwahl-wetter-vorhersage-v2');
            return $this->asJson(['fehler' => true]);
        }
        Yii::$app->response->headers->set('Cache-Control', 'private, max-age=900');
        return $this->asJson(self::uebersetzen($daten));
    }

    private function holen(string $pfad): ?array
    {
        $ch = curl_init(self::API . $pfad);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_USERAGENT => 'rundumuffni.de']);
        $data = json_decode((string)curl_exec($ch), true);
        $ok = curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
        curl_close($ch);
        return $ok && is_array($data) ? $data : null;
    }

    /**
     * Symbol einer Stunde. Bright Sky zeigt Niederschlag erst ab 0,5 mm/h, leichter Regen stünde sonst als „Bewölkt“
     * da – daher gilt die gemeldete Wetterlage (condition) schon ab 0,2 mm und 40 % Wahrscheinlichkeit.
     */
    private static function symbol(array $w): string
    {
        $lage = $w['condition'] ?? '';
        if (in_array($lage, self::NASS, true) && ($w['precipitation'] ?? 0) >= 0.2
            && ($w['precipitation_probability'] ?? 100) >= 40) {
            return $lage;
        }
        $icon = $w['icon'] ?? '';
        return isset(self::TEXTE[$icon]) ? $icon : 'cloudy';
    }

    /** Tageswerte: Tiefst-/Höchstwert, Niederschlag, Wind, Sonne und vier Tageszeiten */
    private function tag(array $h, \DateTimeImmutable $datum): ?array
    {
        if (count($h) < 12) {
            return null;
        }
        $temp = array_column($h, 'temperature');
        $tags = array_filter($h, fn($k) => $k >= 8 && $k <= 19, ARRAY_FILTER_USE_KEY);
        $sonne = date_sun_info($datum->setTime(12, 0)->getTimestamp(), self::BREITE, self::LAENGE);
        $zeiten = [];
        foreach (['Morgens' => [6, 10], 'Mittags' => [11, 14], 'Nachmittags' => [15, 18], 'Abends' => [19, 22]] as $name => [$von, $bis]) {
            $teil = array_filter($h, fn($k) => $k >= $von && $k <= $bis, ARRAY_FILTER_USE_KEY);
            if ($teil) {
                $icon = $this->tagessymbol($teil, $name === 'Abends');
                $zeiten[] = [
                    'name' => $name,
                    'icon' => $icon,
                    'text' => self::TEXTE[$icon],
                    'temperatur' => (int)round(max(array_column($teil, 'temperature'))),
                    'regen' => (int)max(array_map(fn($w) => $w['precipitation_probability'] ?? 0, $teil)),
                ];
            }
        }
        $icon = $this->tagessymbol($tags ?: $h);
        return [
            'datum' => $datum->format('Y-m-d'),
            'icon' => $icon,
            'text' => self::TEXTE[$icon],
            'min' => (int)round(min($temp)),
            'max' => (int)round(max($temp)),
            'niederschlag' => round(array_sum(array_map(fn($w) => $w['precipitation'] ?? 0, $h)), 1),
            'regen' => (int)max(array_map(fn($w) => $w['precipitation_probability'] ?? 0, $h)),
            'wind' => (int)round(max(array_map(fn($w) => $w['wind_speed'] ?? 0, $h))),
            'boeen' => (int)round(max(array_map(fn($w) => $w['wind_gust_speed'] ?? 0, $h))),
            'sonnenstunden' => round(array_sum(array_map(fn($w) => $w['sunshine'] ?? 0, $h)) / 60, 1),
            'aufgang' => is_int($sonne['sunrise'] ?? null) ? $datum->setTimestamp($sonne['sunrise'])->format('H:i') : '',
            'untergang' => is_int($sonne['sunset'] ?? null) ? $datum->setTimestamp($sonne['sunset'])->format('H:i') : '',
            'zeiten' => $zeiten,
        ];
    }

    /** Symbol für einen Zeitraum: Gewitter sofort, Niederschlag ab zwei Stunden, sonst das häufigste */
    private function tagessymbol(array $h, bool $nacht = false): string
    {
        $zahl = array_count_values(array_map([self::class, 'symbol'], $h));
        if (isset($zahl['thunderstorm'])) {
            return 'thunderstorm';
        }
        foreach (self::NASS as $nass) {
            if (($zahl[$nass] ?? 0) >= min(2, count($h))) {
                return $nass;
            }
        }
        // Tag und Nacht zusammenzählen
        $gesamt = [];
        foreach ($zahl as $icon => $n) {
            $basis = str_replace(['-day', '-night'], '', $icon);
            $gesamt[$basis] = ($gesamt[$basis] ?? 0) + $n;
        }
        arsort($gesamt);
        $basis = (string)array_key_first($gesamt);
        if (isset(self::TEXTE[$basis])) {
            return $basis;
        }
        $icon = $basis . ($nacht ? '-night' : '-day');
        return isset(self::TEXTE[$icon]) ? $icon : 'cloudy';
    }

    /** Stundenwerte ab jetzt bis Mitternacht, mindestens die nächsten sechs Stunden */
    private function stunden(array $tage, \DateTimeZone $zone): array
    {
        $jetzt = new \DateTimeImmutable('now', $zone);
        $liste = [];
        for ($i = 0; $i < 24; $i++) {
            $t = $jetzt->modify("+$i hours");
            if ($i >= 6 && $t->format('Y-m-d') !== $jetzt->format('Y-m-d')) {
                break;
            }
            $w = $tage[$t->format('Y-m-d')][(int)$t->format('G')] ?? null;
            if ($w === null) {
                continue;
            }
            $icon = self::symbol($w);
            $liste[] = [
                'zeit' => $i === 0 ? 'Jetzt' : $t->format('G') . ' Uhr',
                'stunde' => $i === 0 ? null : (int)$t->format('G'),
                'icon' => $icon,
                'text' => self::TEXTE[$icon],
                'temperatur' => (int)round($w['temperature']),
                'regen' => (int)($w['precipitation_probability'] ?? 0),
            ];
        }
        return $liste;
    }

    private function warnung(array $a): array
    {
        $zeit = function (?string $z) {
            return $z ? (new \DateTimeImmutable($z))->setTimezone(new \DateTimeZone(self::ZONE))->format('d.m., H:i') : '';
        };
        return [
            'titel' => $a['headline_de'] ?? $a['event_de'] ?? null, // null: „Wetterwarnung“ (übersetzt in uebersetzen())
            'stufe' => in_array($a['severity'] ?? '', ['minor', 'moderate', 'severe', 'extreme'], true) ? $a['severity'] : 'minor',
            'von' => $zeit($a['onset'] ?? null),
            'bis' => $zeit($a['expires'] ?? null),
            'text' => $a['description_de'] ?? '',
        ];
    }
}
