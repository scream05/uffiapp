<?php

use humhub\modules\uebersicht\Events;
use humhub\widgets\LayoutAddons;
use yii\web\Application;

return [
    'id' => 'uebersicht',
    'class' => 'humhub\modules\uebersicht\Module',
    'namespace' => 'humhub\modules\uebersicht',
    'consoleControllerMap' => [
        'uebersicht' => 'humhub\modules\uebersicht\commands\UebersichtController',
    ],
    'events' => [
        ['class' => Application::class, 'event' => Application::EVENT_BEFORE_REQUEST, 'callback' => [Events::class, 'onBeforeRequest']],
        ['class' => 'humhub\modules\user\widgets\AccountTopMenu', 'event' => 'init', 'callback' => [Events::class, 'onAccountTopMenuInit']],
        ['class' => 'humhub\modules\space\widgets\HeaderControlsMenu', 'event' => 'run', 'callback' => [Events::class, 'onSpaceHeaderControlsRun']],
        ['class' => 'humhub\modules\dashboard\stream\DashboardStreamQuery', 'event' => 'beforeFilter', 'callback' => [Events::class, 'onDashboardStreamBeforeFilter']],
        ['class' => 'humhub\modules\content\widgets\WallEntryControls', 'event' => 'init', 'callback' => [Events::class, 'onWallEntryControlsInit']],
        ['class' => LayoutAddons::class, 'event' => LayoutAddons::EVENT_BEFORE_RUN, 'callback' => [Events::class, 'onLayoutAddons']],
    ],
];
