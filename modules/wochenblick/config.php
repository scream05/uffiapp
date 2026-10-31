<?php

use humhub\modules\user\widgets\AccountMenu;
use humhub\modules\wochenblick\Events;

return [
    'id' => 'wochenblick',
    'class' => 'humhub\modules\wochenblick\Module',
    'namespace' => 'humhub\modules\wochenblick',
    'consoleControllerMap' => [
        'wochenblick' => 'humhub\modules\wochenblick\commands\WochenblickController',
    ],
    'events' => [
        ['class' => AccountMenu::class, 'event' => AccountMenu::EVENT_INIT, 'callback' => [Events::class, 'onAccountMenuInit']],
    ],
];
