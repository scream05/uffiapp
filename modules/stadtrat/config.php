<?php

use humhub\modules\dashboard\widgets\Sidebar;
use humhub\modules\space\widgets\Menu;
use humhub\modules\stadtrat\Events;

return [
    'id' => 'stadtrat',
    'class' => 'humhub\modules\stadtrat\Module',
    'namespace' => 'humhub\modules\stadtrat',
    'consoleControllerMap' => [
        'stadtrat' => 'humhub\modules\stadtrat\commands\StadtratController',
    ],
    'events' => [
        ['class' => Sidebar::class, 'event' => Sidebar::EVENT_INIT, 'callback' => [Events::class, 'onDashboardSidebarInit']],
        ['class' => Menu::class, 'event' => Menu::EVENT_INIT, 'callback' => [Events::class, 'onSpaceMenuInit']],
    ],
];
