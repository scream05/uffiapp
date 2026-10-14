<?php

use humhub\modules\content\widgets\WallEntryAddons;
use humhub\modules\maengelmelder\Events;
use humhub\modules\space\widgets\Menu;

return [
    'id' => 'maengelmelder',
    'class' => 'humhub\modules\maengelmelder\Module',
    'namespace' => 'humhub\modules\maengelmelder',
    'consoleControllerMap' => [
        'bauhof' => 'humhub\modules\maengelmelder\commands\BauhofController',
    ],
    'events' => [
        // Kontolöschung: Kontaktdaten/Telefonnummer, „Betrifft mich auch“; Absender im Bauhof-Verlauf anonymisieren
        ['class' => 'humhub\modules\user\models\User', 'event' => 'beforeSoftDelete', 'callback' => [Events::class, 'onUserLoeschen']],
        ['class' => 'humhub\modules\user\models\User', 'event' => 'beforeDelete', 'callback' => [Events::class, 'onUserLoeschen']],
        // WhatsApp-Nachrichten von Bauhof-Mitarbeitern (Klassen als Text: WhatsApp-Modul evtl. noch nicht geladen)
        ['class' => 'humhub\modules\whatsapp\components\Verarbeitung', 'event' => 'empfangen',
            'callback' => ['humhub\modules\maengelmelder\components\Bauhof', 'onEmpfangen']],
        ['class' => 'humhub\modules\whatsapp\components\Verarbeitung', 'event' => 'empfangen',
            'callback' => ['humhub\modules\maengelmelder\components\Buergerinfo', 'onEmpfangen']],
        ['class' => 'humhub\modules\whatsapp\components\Verarbeitung', 'event' => 'status',
            'callback' => ['humhub\modules\maengelmelder\components\Bauhof', 'onStatus']],
        ['class' => Menu::class, 'event' => Menu::EVENT_INIT, 'callback' => [Events::class, 'onSpaceMenuInit']],
        ['class' => WallEntryAddons::class, 'event' => WallEntryAddons::EVENT_INIT, 'callback' => [Events::class, 'onWallEntryAddonsInit']],
    ],
];
