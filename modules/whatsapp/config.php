<?php

use humhub\modules\space\widgets\Menu;
use humhub\modules\whatsapp\Events;

return [
    'id' => 'whatsapp',
    'class' => 'humhub\modules\whatsapp\Module',
    'namespace' => 'humhub\modules\whatsapp',
    'consoleControllerMap' => [
        'whatsapp' => 'humhub\modules\whatsapp\commands\WhatsappController',
    ],
    'events' => [
        ['class' => Menu::class, 'event' => Menu::EVENT_INIT, 'callback' => [Events::class, 'onSpaceMenuInit']],
    ],
];
