<?php

use humhub\modules\mitteilungsblatt\Events;
use humhub\modules\space\widgets\Menu;

return [
    'id' => 'mitteilungsblatt',
    'class' => 'humhub\modules\mitteilungsblatt\Module',
    'namespace' => 'humhub\modules\mitteilungsblatt',
    'consoleControllerMap' => [
        'mitteilungsblatt' => 'humhub\modules\mitteilungsblatt\commands\MitteilungsblattController',
    ],
    'events' => [
        ['class' => Menu::class, 'event' => Menu::EVENT_INIT, 'callback' => [Events::class, 'onSpaceMenuInit']],
    ],
];
