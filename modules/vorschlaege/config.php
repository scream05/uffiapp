<?php

use humhub\modules\content\widgets\WallEntryAddons;
use humhub\modules\space\widgets\Menu;
use humhub\modules\vorschlaege\Events;

return [
    'id' => 'vorschlaege',
    'class' => 'humhub\modules\vorschlaege\Module',
    'namespace' => 'humhub\modules\vorschlaege',
    'events' => [
        // Kontolöschung: Stimmen entfernen
        ['class' => 'humhub\modules\user\models\User', 'event' => 'beforeSoftDelete', 'callback' => [Events::class, 'onUserLoeschen']],
        ['class' => 'humhub\modules\user\models\User', 'event' => 'beforeDelete', 'callback' => [Events::class, 'onUserLoeschen']],
        ['class' => Menu::class, 'event' => Menu::EVENT_INIT, 'callback' => [Events::class, 'onSpaceMenuInit']],
        ['class' => WallEntryAddons::class, 'event' => WallEntryAddons::EVENT_INIT, 'callback' => [Events::class, 'onWallEntryAddonsInit']],
    ],
];
