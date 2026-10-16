<?php

use humhub\modules\content\widgets\WallEntryAddons;
use humhub\modules\gewerbe\Events;
use humhub\modules\space\widgets\Menu;
use humhub\widgets\MetaSearchWidget;

return [
    'id' => 'gewerbe',
    'class' => 'humhub\modules\gewerbe\Module',
    'namespace' => 'humhub\modules\gewerbe',
    'consoleControllerMap' => [
        'gewerbe' => 'humhub\modules\gewerbe\commands\GewerbeController',
    ],
    'events' => [
        ['class' => Menu::class, 'event' => Menu::EVENT_INIT, 'callback' => [Events::class, 'onSpaceMenuInit']],
        ['class' => Menu::class, 'event' => Menu::EVENT_RUN, 'callback' => [Events::class, 'onSpaceMenuRun']],
        ['class' => MetaSearchWidget::class, 'event' => MetaSearchWidget::EVENT_INIT, 'callback' => [Events::class, 'onMetaSearchInit']],
        // „Beitrag von <Betrieb>“ unter freigegebenen Gewerbebeiträgen in der Übersicht
        ['class' => WallEntryAddons::class, 'event' => WallEntryAddons::EVENT_INIT, 'callback' => [Events::class, 'onWallEntryAddonsInit']],
        // Täglicher HumHub-Cron: abgelehnte Beiträge nach 30 Tagen löschen
        ['class' => 'humhub\commands\CronController', 'event' => 'daily', 'callback' => [Events::class, 'onCronDaily']],
        // Kontolöschung: Verwaltungen und nicht veröffentlichte Beiträge der Person löschen
        ['class' => 'humhub\modules\user\models\User', 'event' => 'beforeSoftDelete', 'callback' => [Events::class, 'onUserLoeschen']],
        ['class' => 'humhub\modules\user\models\User', 'event' => 'beforeDelete', 'callback' => [Events::class, 'onUserLoeschen']],
    ],
];
