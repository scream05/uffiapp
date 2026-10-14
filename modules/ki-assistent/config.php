<?php

use humhub\modules\kiAssistent\Events;
use humhub\widgets\LayoutAddons;
use humhub\widgets\TopMenu;

return [
    'id' => 'ki-assistent',
    'class' => 'humhub\modules\kiAssistent\Module',
    'namespace' => 'humhub\modules\kiAssistent',
    'events' => [
        ['class' => TopMenu::class, 'event' => TopMenu::EVENT_INIT, 'callback' => [Events::class, 'onTopMenuInit']],
        ['class' => LayoutAddons::class, 'event' => LayoutAddons::EVENT_BEFORE_RUN, 'callback' => [Events::class, 'onLayoutAddons']],
        // Täglicher HumHub-Cron: Bewertungen nach 180 Tagen löschen
        ['class' => 'humhub\commands\CronController', 'event' => 'daily', 'callback' => [Events::class, 'onCronDaily']],
    ],
];
