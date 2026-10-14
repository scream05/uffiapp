<?php

use humhub\modules\content\widgets\WallEntryAddons;
use humhub\modules\space\widgets\Menu;
use humhub\modules\vereine\Events;
use humhub\widgets\MetaSearchWidget;

return [
    'id' => 'vereine',
    'class' => 'humhub\modules\vereine\Module',
    'namespace' => 'humhub\modules\vereine',
    'events' => [
        ['class' => Menu::class, 'event' => Menu::EVENT_INIT, 'callback' => [Events::class, 'onSpaceMenuInit']],
        ['class' => WallEntryAddons::class, 'event' => WallEntryAddons::EVENT_INIT, 'callback' => [Events::class, 'onWallEntryAddonsInit']],
        ['class' => MetaSearchWidget::class, 'event' => MetaSearchWidget::EVENT_INIT, 'callback' => [Events::class, 'onMetaSearchInit']],
    ],
];
