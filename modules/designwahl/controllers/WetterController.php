<?php

namespace humhub\modules\designwahl\controllers;

use humhub\components\access\ControllerAccess;
use humhub\components\Controller;
use Yii;

/**
 * Aktuelles Wetter in Uffenheim für die Begrüßungskarte: Daten des Deutschen Wetterdienstes über Bright Sky
 * (https://brightsky.dev, nächste Station Gollhofen). Abruf über unseren Server und 15 Min. zwischengespeichert,
 * die Nutzer haben keine Verbindung zum Dienst.
 */
class WetterController extends Controller
{
    private const URL = 'https://api.brightsky.dev/current_weather?lat=49.5447&lon=10.2333&tz=Europe/Berlin';

    private const TEXTE = [
        'clear-day' => 'Sonnig', 'clear-night' => 'Klar', 'partly-cloudy-day' => 'Leicht bewölkt',
        'partly-cloudy-night' => 'Leicht bewölkt', 'cloudy' => 'Bewölkt', 'fog' => 'Nebel', 'wind' => 'Windig',
        'rain' => 'Regen', 'sleet' => 'Schneeregen', 'snow' => 'Schnee', 'hail' => 'Hagel', 'thunderstorm' => 'Gewitter',
    ];

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
        return $this->asJson($wetter);
    }
}
