<?php

namespace humhub\modules\abfuhr;

use humhub\modules\abfuhr\notifications\AbfuhrMorgen;

class Module extends \humhub\components\Module
{
    /** URL des Space mit dem Abfallkalender (External Calendar, siehe scripts/abfall-kalender.py) – bleibt beim Umbenennen gleich */
    public string $spaceUrl = 'abfall-and-termine';

    /** Tonnen: Schlüssel => Anzeigename; Titel im Kalender beginnen mit dem Anzeigenamen */
    public array $tonnen = [
        'rest' => 'Restmüll',
        'bio' => 'Biotonne',
        'papier' => 'Papiertonne',
        'gelb' => 'Gelbe Tonne',
    ];

    /** Zeitpunkte: Schlüssel => [Bezeichnung, Stunde, Tag der Abfuhr relativ zum Versand (1 = morgen, 0 = heute)] */
    public array $zeiten = [
        '18' => ['am Vorabend um 18 Uhr', 18, 1],
        '20' => ['am Vorabend um 20 Uhr', 20, 1],
        '6' => ['am Abfuhrtag um 6 Uhr', 6, 0],
    ];

    public function getNotifications()
    {
        return [AbfuhrMorgen::class];
    }
}
