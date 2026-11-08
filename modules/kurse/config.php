<?php

use humhub\modules\kurse\Events;
use humhub\modules\space\widgets\Menu;

return [
    'id' => 'kurse',
    'class' => 'humhub\modules\kurse\Module',
    'namespace' => 'humhub\modules\kurse',
    'consoleControllerMap' => [
        'kurse' => 'humhub\modules\kurse\commands\KurseController',
    ],
    'events' => [
        ['class' => Menu::class, 'event' => Menu::EVENT_INIT, 'callback' => [Events::class, 'onSpaceMenuInit']],
    ],
];
