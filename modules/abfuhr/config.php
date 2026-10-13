<?php

use humhub\components\Controller;
use humhub\modules\abfuhr\Events;
use humhub\modules\space\widgets\Menu;
use humhub\modules\user\widgets\AccountMenu;
use yii\web\Application;

return [
    'id' => 'abfuhr',
    'class' => 'humhub\modules\abfuhr\Module',
    'namespace' => 'humhub\modules\abfuhr',
    'consoleControllerMap' => [
        'abfuhr' => 'humhub\modules\abfuhr\commands\AbfuhrController',
    ],
    'events' => [
        ['class' => AccountMenu::class, 'event' => AccountMenu::EVENT_INIT, 'callback' => [Events::class, 'onAccountMenuInit']],
        ['class' => Menu::class, 'event' => Menu::EVENT_INIT, 'callback' => [Events::class, 'onSpaceMenuInit']],
        ['class' => Menu::class, 'event' => Menu::EVENT_RUN, 'callback' => [Events::class, 'onSpaceMenuRun']],
        ['class' => Application::class, 'event' => Application::EVENT_BEFORE_REQUEST, 'callback' => [Events::class, 'onBeforeRequest']],
        ['class' => Controller::class, 'event' => Controller::EVENT_BEFORE_ACTION, 'callback' => [Events::class, 'onControllerBeforeAction']],
    ],
];
