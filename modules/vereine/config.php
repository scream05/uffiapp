<?php

use humhub\modules\content\widgets\WallEntryAddons;
use humhub\modules\space\widgets\Menu;
use humhub\modules\vereine\Events;
use humhub\widgets\MetaSearchWidget;

return [
    'id' => 'vereine',
    'class' => 'humhub\modules\vereine\Module',
    'namespace' => 'humhub\modules\vereine',
    'consoleControllerMap' => [
        'vereine' => 'humhub\modules\vereine\commands\VereineController',
    ],
    'events' => [
        ['class' => Menu::class, 'event' => Menu::EVENT_INIT, 'callback' => [Events::class, 'onSpaceMenuInit']],
        ['class' => WallEntryAddons::class, 'event' => WallEntryAddons::EVENT_INIT, 'callback' => [Events::class, 'onWallEntryAddonsInit']],
        // Kontolöschung: Verwaltungen und nicht veröffentlichte Beiträge der Person löschen
        ['class' => 'humhub\modules\user\models\User', 'event' => 'beforeSoftDelete', 'callback' => [Events::class, 'onUserLoeschen']],
        ['class' => 'humhub\modules\user\models\User', 'event' => 'beforeDelete', 'callback' => [Events::class, 'onUserLoeschen']],
        // Täglicher HumHub-Cron: abgelehnte Beiträge nach 30 Tagen löschen
        ['class' => 'humhub\commands\CronController', 'event' => 'daily', 'callback' => [Events::class, 'onCronDaily']],
        ['class' => 'humhub\modules\dashboard\widgets\Sidebar', 'event' => 'init', 'callback' => [Events::class, 'onDashboardSidebarInit']],
        ['class' => MetaSearchWidget::class, 'event' => MetaSearchWidget::EVENT_INIT, 'callback' => [Events::class, 'onMetaSearchInit']],
    ],
];
