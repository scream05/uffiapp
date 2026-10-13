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
        ['class' => FooterMenu::class, 'event' => FooterMenu::EVENT_INIT, 'callback' => [Events::class, 'onFooterMenuInit']],
        ['class' => TopMenu::class, 'event' => TopMenu::EVENT_RUN, 'callback' => [Events::class, 'onTopMenuRun']],
        // Klasse als Text: das Kalendermodul ist beim Laden dieser Konfiguration evtl. noch nicht registriert
        ['class' => 'humhub\modules\calendar\widgets\CalendarTypeLegend', 'event' => 'afterRun', 'callback' => [Events::class, 'onCalendarTypeLegend']],
        ['class' => 'humhub\modules\calendar\widgets\CalendarFilterBar', 'event' => 'beforeRun', 'callback' => [Events::class, 'onCalendarFilterBar']],
        ['class' => 'humhub\modules\comment\widgets\Form', 'event' => 'create', 'callback' => [Events::class, 'onCommentFormCreate']],
        ['class' => 'humhub\components\Controller', 'event' => 'beforeAction', 'callback' => [Events::class, 'onControllerBeforeAction']],
        ['class' => LayoutAddons::class, 'event' => LayoutAddons::EVENT_BEFORE_RUN, 'callback' => [Events::class, 'onLayoutAddons']],
    ],
];
