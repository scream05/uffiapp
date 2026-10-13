<?php

use humhub\modules\abfuhr\Events;
use humhub\modules\space\widgets\Menu;
use humhub\modules\user\widgets\AccountMenu;

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
    ],
];
