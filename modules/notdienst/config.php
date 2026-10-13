<?php

use humhub\modules\dashboard\widgets\Sidebar;
use humhub\modules\notdienst\Events;
use humhub\modules\space\widgets\Menu;

return [
    'id' => 'notdienst',
    'class' => 'humhub\modules\notdienst\Module',
    'namespace' => 'humhub\modules\notdienst',
    'consoleControllerMap' => [
        'notdienst' => 'humhub\modules\notdienst\commands\NotdienstController',
    ],
    'events' => [
        ['class' => Sidebar::class, 'event' => Sidebar::EVENT_INIT, 'callback' => [Events::class, 'onDashboardSidebarInit']],
        ['class' => Menu::class, 'event' => Menu::EVENT_INIT, 'callback' => [Events::class, 'onSpaceMenuInit']],
    ],
];
