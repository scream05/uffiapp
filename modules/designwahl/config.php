<?php

use humhub\modules\designwahl\Events;
use humhub\modules\user\widgets\AccountTopMenu;
use humhub\widgets\LayoutAddons;
use humhub\widgets\FooterMenu;
use humhub\widgets\TopMenu;
use yii\web\Application;

return [
    'id' => 'designwahl',
    'class' => 'humhub\modules\designwahl\Module',
    'namespace' => 'humhub\modules\designwahl',
    'consoleControllerMap' => [
        'designwahl' => 'humhub\modules\designwahl\commands\DesignwahlController',
    ],
    'events' => [
        ['class' => AccountTopMenu::class, 'event' => AccountTopMenu::EVENT_INIT, 'callback' => [Events::class, 'onAccountTopMenuInit']],
        ['class' => Application::class, 'event' => Application::EVENT_BEFORE_REQUEST, 'callback' => [Events::class, 'onBeforeRequest']],
        // Übersetzungen auch in der Konsole (Warteschlange) – Ereignis der gemeinsamen Basisklasse
        ['class' => \yii\base\Application::class, 'event' => \yii\base\Application::EVENT_BEFORE_REQUEST, 'callback' => [Events::class, 'uebersetzungenRegistrieren']],
        ['class' => Application::class, 'event' => Application::EVENT_BEFORE_ACTION, 'callback' => [Events::class, 'onAppBeforeAction']],
        ['class' => FooterMenu::class, 'event' => FooterMenu::EVENT_INIT, 'callback' => [Events::class, 'onFooterMenuInit']],
        ['class' => TopMenu::class, 'event' => TopMenu::EVENT_RUN, 'callback' => [Events::class, 'onTopMenuRun']],
        // Klasse als Text: das Kalendermodul ist beim Laden dieser Konfiguration evtl. noch nicht registriert
        ['class' => 'humhub\modules\calendar\widgets\CalendarTypeLegend', 'event' => 'afterRun', 'callback' => [Events::class, 'onCalendarTypeLegend']],
        ['class' => 'humhub\modules\calendar\widgets\CalendarFilterBar', 'event' => 'beforeRun', 'callback' => [Events::class, 'onCalendarFilterBar']],
        ['class' => 'humhub\modules\calendar\widgets\FullCalendar', 'event' => 'beforeRun', 'callback' => [Events::class, 'onFullCalendar']],
        ['class' => 'humhub\components\Controller', 'event' => 'beforeAction', 'callback' => [Events::class, 'onControllerBeforeAction']],
        ['class' => 'humhub\modules\content\widgets\WallEntryControls', 'event' => 'init', 'callback' => [Events::class, 'onWallEntryControlsInit']],
        ['class' => 'humhub\modules\content\widgets\WallEntryControls', 'event' => 'run', 'callback' => [Events::class, 'onWallEntryControlsRun']],
        ['class' => 'yii\web\Response', 'event' => 'beforeSend', 'callback' => [Events::class, 'onResponseBeforeSend']],
        ['class' => LayoutAddons::class, 'event' => LayoutAddons::EVENT_BEFORE_RUN, 'callback' => [Events::class, 'onLayoutAddons']],
        // Zahl am App-Symbol auch bei neuen Meldungen/Nachrichten per stiller Nachricht nachziehen (siehe Events::kennzeichenNachziehen)
        ['class' => 'humhub\modules\notification\models\Notification', 'event' => 'afterInsert', 'callback' => [Events::class, 'onNotificationInsert']],
        // Zahl am Symbol der Web-App (PWA) bei jedem Push setzen
        ['class' => 'humhub\modules\web\pwa\controllers\ServiceWorkerController', 'event' => 'init', 'callback' => [Events::class, 'onServiceWorkerInit']],
        ['class' => 'humhub\modules\mail\models\MessageEntry', 'event' => 'afterInsert', 'callback' => [Events::class, 'onMessageEntryInsert']],
    ],
];
