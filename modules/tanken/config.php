<?php

use humhub\commands\CronController;
use humhub\modules\dashboard\widgets\Sidebar;
use humhub\modules\space\widgets\Menu;
use humhub\modules\tanken\Events;

return [
    'id' => 'tanken',
    'class' => 'humhub\modules\tanken\Module',
    'namespace' => 'humhub\modules\tanken',
    'events' => [
        ['class' => Sidebar::class, 'event' => Sidebar::EVENT_INIT, 'callback' => [Events::class, 'onDashboardSidebarInit']],
        ['class' => Menu::class, 'event' => Menu::EVENT_INIT, 'callback' => [Events::class, 'onSpaceMenuInit']],
        ['class' => CronController::class, 'event' => CronController::EVENT_ON_HOURLY_RUN, 'callback' => [Events::class, 'onCronHourly']],
    ],
];
