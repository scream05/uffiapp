<?php

use humhub\modules\gewerbe\Events;
use humhub\modules\space\widgets\Menu;

return [
    'id' => 'gewerbe',
    'class' => 'humhub\modules\gewerbe\Module',
    'namespace' => 'humhub\modules\gewerbe',
    'events' => [
        ['class' => Menu::class, 'event' => Menu::EVENT_INIT, 'callback' => [Events::class, 'onSpaceMenuInit']],
        ['class' => Menu::class, 'event' => Menu::EVENT_RUN, 'callback' => [Events::class, 'onSpaceMenuRun']],
        // Kontolöschung: Verwaltungen der Person löschen
        ['class' => 'humhub\modules\user\models\User', 'event' => 'beforeSoftDelete', 'callback' => [Events::class, 'onUserLoeschen']],
        ['class' => 'humhub\modules\user\models\User', 'event' => 'beforeDelete', 'callback' => [Events::class, 'onUserLoeschen']],
    ],
];
