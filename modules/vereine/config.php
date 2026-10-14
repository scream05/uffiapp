<?php

use humhub\modules\content\widgets\WallEntryAddons;
use humhub\modules\space\widgets\Menu;
use humhub\modules\vereine\Events;
use humhub\widgets\MetaSearchWidget;
use yii\web\Application;

return [
    'id' => 'vereine',
    'class' => 'humhub\modules\vereine\Module',
    'namespace' => 'humhub\modules\vereine',
    'events' => [
        ['class' => Menu::class, 'event' => Menu::EVENT_INIT, 'callback' => [Events::class, 'onSpaceMenuInit']],
        ['class' => WallEntryAddons::class, 'event' => WallEntryAddons::EVENT_INIT, 'callback' => [Events::class, 'onWallEntryAddonsInit']],
        ['class' => Application::class, 'event' => Application::EVENT_BEFORE_REQUEST, 'callback' => [Events::class, 'onBeforeRequest']],
        ['class' => MetaSearchWidget::class, 'event' => MetaSearchWidget::EVENT_INIT, 'callback' => [Events::class, 'onMetaSearchInit']],
    ],
];
