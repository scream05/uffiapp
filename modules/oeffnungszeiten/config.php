<?php

use humhub\modules\dashboard\widgets\Sidebar;
use humhub\modules\oeffnungszeiten\Events;
use humhub\modules\space\widgets\Menu;

return [
    'id' => 'oeffnungszeiten',
    'class' => 'humhub\modules\oeffnungszeiten\Module',
    'namespace' => 'humhub\modules\oeffnungszeiten',
    'events' => [
        ['class' => Sidebar::class, 'event' => Sidebar::EVENT_INIT, 'callback' => [Events::class, 'onDashboardSidebarInit']],
        ['class' => Menu::class, 'event' => Menu::EVENT_INIT, 'callback' => [Events::class, 'onSpaceMenuInit']],
    ],
];
