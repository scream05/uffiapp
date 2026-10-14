<?php

use humhub\modules\uebersicht\Events;
use humhub\widgets\LayoutAddons;

return [
    'id' => 'uebersicht',
    'class' => 'humhub\modules\uebersicht\Module',
    'namespace' => 'humhub\modules\uebersicht',
    'consoleControllerMap' => [
        'uebersicht' => 'humhub\modules\uebersicht\commands\UebersichtController',
    ],
    'events' => [
        ['class' => 'humhub\modules\dashboard\stream\DashboardStreamQuery', 'event' => 'beforeFilter', 'callback' => [Events::class, 'onDashboardStreamBeforeFilter']],
        ['class' => 'humhub\modules\content\widgets\WallEntryControls', 'event' => 'init', 'callback' => [Events::class, 'onWallEntryControlsInit']],
        ['class' => LayoutAddons::class, 'event' => LayoutAddons::EVENT_BEFORE_RUN, 'callback' => [Events::class, 'onLayoutAddons']],
    ],
];
