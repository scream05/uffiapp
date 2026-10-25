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
        // Kontolöschung: Benutzereinstellungen der eigenen Module (Abfuhr, Design, Übersicht, Uffi …) löschen
        ['class' => 'humhub\modules\user\models\User', 'event' => 'beforeSoftDelete', 'callback' => [Events::class, 'onUserLoeschen']],
        ['class' => Application::class, 'event' => Application::EVENT_BEFORE_REQUEST, 'callback' => [Events::class, 'onBeforeRequest']],
        ['class' => 'humhub\modules\user\widgets\AccountTopMenu', 'event' => 'init', 'callback' => [Events::class, 'onAccountTopMenuInit']],
        ['class' => 'humhub\modules\space\widgets\HeaderControlsMenu', 'event' => 'run', 'callback' => [Events::class, 'onSpaceHeaderControlsRun']],
        ['class' => 'humhub\modules\dashboard\stream\DashboardStreamQuery', 'event' => 'beforeFilter', 'callback' => [Events::class, 'onDashboardStreamBeforeFilter']],
        ['class' => 'humhub\modules\content\widgets\WallEntryControls', 'event' => 'init', 'callback' => [Events::class, 'onWallEntryControlsInit']],
        // „Letzte Aktivitäten“ in Spaces nur für Angemeldete
        ['class' => 'humhub\modules\space\widgets\Sidebar', 'event' => 'init', 'callback' => [Events::class, 'onSpaceSidebarInit']],
        ['class' => 'humhub\modules\space\widgets\Sidebar', 'event' => 'afterRun', 'callback' => [Events::class, 'onSpaceSidebarAfterRun']],
        // „Neue Spaces“ in der Seitenleiste der Übersicht nur für Admins
        ['class' => 'humhub\modules\dashboard\widgets\Sidebar', 'event' => 'init', 'callback' => [Events::class, 'onDashboardSidebarInit']],
        // Reihenfolge der Kästen nach „Meine Übersicht“ (nach allen init-Ereignissen, vor dem Sortieren)
        ['class' => 'humhub\modules\dashboard\widgets\Sidebar', 'event' => 'run', 'callback' => [Events::class, 'onDashboardSidebarRun']],
        ['class' => LayoutAddons::class, 'event' => LayoutAddons::EVENT_BEFORE_RUN, 'callback' => [Events::class, 'onLayoutAddons']],
    ],
];
